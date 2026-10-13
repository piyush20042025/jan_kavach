<?php
namespace JanKavach\Libraries;

use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Database;
use JanKavach\Core\Logger;

/**
 * SCHEME ELIGIBILITY / MATCHING ENGINE  (specification sections 9 and 20)
 *
 * The engine holds no knowledge of any particular scheme.  It reads
 * `scheme_eligibility_rules` and evaluates them against a beneficiary
 * profile assembled from the beneficiary, household and assessment tables.
 * Adding a scheme, or changing what a scheme requires, is therefore a data
 * change - no code is touched.
 *
 * Rule semantics
 * --------------
 *   rules sharing a `rule_group` are ANDed;  separate groups are ORed
 *   (the best-scoring group wins, so a scheme can express alternative
 *   qualifying routes).
 *
 *   is_mandatory = 1  a failure disqualifies outright  -> "Not Eligible"
 *   is_mandatory = 0  a failure only reduces the match percentage
 *
 *   A rule whose attribute has no recorded value is neither passed nor
 *   failed: it is counted as UNKNOWN, which keeps the beneficiary in
 *   "Needs Verification" rather than wrongly including or excluding them.
 *
 * Grading
 * -------
 *   match % = (weight of passed rules / weight of all rules in the group)
 *
 *   mandatory failure                      -> Not Eligible
 *   no unknowns and % >= eligible_threshold-> Eligible
 *   % >= potential_threshold               -> Potentially Eligible
 *   otherwise                              -> Needs Verification
 */
class EligibilityEngine
{
    public const ELIGIBLE   = 'Eligible';
    public const POTENTIAL  = 'Potentially Eligible';
    public const VERIFY     = 'Needs Verification';
    public const NOT        = 'Not Eligible';

    private Database $db;
    private array $attributes = [];      // attribute_key => definition
    private array $lookupLabels = [];    // lookup_key => [code => label]
    private array $stateNames = [];
    private array $districtNames = [];
    private int $eligibleThreshold;
    private int $potentialThreshold;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->loadVocabulary();

        $this->eligibleThreshold  = (int)(Config::setting('eligible_threshold')
                                     ?? Config::get('matching.eligible_threshold', 90));
        $this->potentialThreshold = (int)(Config::setting('potential_threshold')
                                     ?? Config::get('matching.potential_threshold', 60));
    }

    // =================================================================
    // PROFILE ASSEMBLY
    // =================================================================

    /**
     * Build the attribute map the rules are evaluated against.
     * Values are returned raw; comparison handles typing.
     */
    public function buildProfile(int $beneficiaryId): array
    {
        $beneficiary = $this->db->selectOne(
            'SELECT b.*, h.annual_income, h.family_size, h.bpl_status, h.ration_card_type,
                    h.land_holding_acres, h.has_electricity, h.has_toilet, h.house_type AS hh_house_type,
                    h.earning_members, h.livestock_count
               FROM beneficiaries b
               LEFT JOIN beneficiary_households h ON h.beneficiary_id = b.id
              WHERE b.id = :id AND b.deleted_at IS NULL',
            ['id' => $beneficiaryId]
        );
        if (!$beneficiary) {
            return [];
        }

        $profile = [];

        // ---- columns that live on beneficiaries / households ---------
        foreach ($this->attributes as $key => $attr) {
            if (!in_array($attr['source'], ['beneficiary', 'household', 'geo'], true)) {
                continue;
            }
            $column = $attr['source_column'] ?: $key;
            if (array_key_exists($column, $beneficiary) && $beneficiary[$column] !== null) {
                $profile[$key] = $beneficiary[$column];
            }
        }
        // land_holding maps onto a differently named column
        if ($beneficiary['land_holding_acres'] !== null) {
            $profile['land_holding'] = $beneficiary['land_holding_acres'];
        }

        // ---- assessment answers --------------------------------------
        $rows = $this->db->select(
            'SELECT attribute_key, value_text, value_number, value_bool, value_date
               FROM beneficiary_profile_attributes WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]
        );
        foreach ($rows as $row) {
            $value = null;
            if ($row['value_bool'] !== null) {
                $value = (int)$row['value_bool'];
            } elseif ($row['value_number'] !== null) {
                $value = $row['value_number'] + 0;
            } elseif ($row['value_text'] !== null && $row['value_text'] !== '') {
                $value = $row['value_text'];
            } elseif ($row['value_date'] !== null) {
                $value = $row['value_date'];
            }
            if ($value !== null) {
                $profile[$row['attribute_key']] = $value;
            }
        }

        // ---- age falls back to the date of birth ---------------------
        if (empty($profile['age']) && !empty($beneficiary['dob'])) {
            $profile['age'] = age_from_dob($beneficiary['dob']);
        }

        return $this->applyDerived($profile);
    }

    /**
     * Compute the attributes whose source is 'derived'.  Keeping these in
     * one place means a scheme rule can use, say, senior_citizen without a
     * field worker having to answer it.
     */
    public function applyDerived(array $profile): array
    {
        // Senior citizen
        if (isset($profile['age']) && $profile['age'] !== '') {
            $profile['senior_citizen'] = ((int)$profile['age'] >= 60) ? 1 : 0;
        }

        // Disability
        if (isset($profile['disability_status'])) {
            $profile['has_disability'] = (strtoupper((string)$profile['disability_status']) !== 'NONE') ? 1 : 0;
        } elseif (isset($profile['disability_percent'])) {
            $profile['has_disability'] = ((float)$profile['disability_percent'] >= 40) ? 1 : 0;
        }

        // Farmer
        $farmerCat  = strtoupper((string)($profile['farmer_category'] ?? ''));
        $occupation = strtoupper((string)($profile['occupation'] ?? ''));
        if ($farmerCat !== '' || $occupation !== '') {
            $isFarmer = ($farmerCat !== '' && $farmerCat !== 'NOT_FARMER')
                     || in_array($occupation, ['CULTIVATOR', 'AGRI_LABOUR'], true);
            $profile['is_farmer'] = $isFarmer ? 1 : 0;
        }

        // Minority
        $category = strtoupper((string)($profile['social_category'] ?? ''));
        $religion = strtoupper((string)($profile['religion'] ?? ''));
        if ($category !== '' || $religion !== '') {
            $profile['is_minority'] = ($category === 'MIN'
                || in_array($religion, ['MUSLIM', 'CHRISTIAN', 'SIKH', 'BUDDHIST', 'JAIN', 'PARSI'], true)) ? 1 : 0;
        }

        // Ration card
        if (isset($profile['ration_card_type'])) {
            $card = strtoupper((string)$profile['ration_card_type']);
            $profile['has_ration_card'] = ($card !== '' && $card !== 'NONE') ? 1 : 0;
        }

        // Transgender - gender 'Other' is the only signal the register carries
        if (isset($profile['gender'])) {
            $profile['is_transgender'] = (strtolower((string)$profile['gender']) === 'other') ? 1 : 0;
        }

        return $profile;
    }

    // =================================================================
    // EVALUATION
    // =================================================================

    /**
     * Evaluate one scheme against a profile.
     *
     * @return array {
     *   scheme_id, match_percent, eligibility_status,
     *   rules_total, rules_passed, rules_failed, rules_unknown,
     *   passed_reasons[], failed_reasons[], unknown_reasons[],
     *   reason_text
     * }
     */
    public function evaluateScheme(array $profile, int $schemeId, ?array $rules = null): array
    {
        if ($rules === null) {
            $rules = $this->rulesFor($schemeId);
        }

        // A scheme with no rules configured cannot be judged - say so
        // rather than pretending everyone qualifies.
        if ($rules === []) {
            return [
                'scheme_id'          => $schemeId,
                'match_percent'      => 0.0,
                'eligibility_status' => self::VERIFY,
                'rules_total'        => 0,
                'rules_passed'       => 0,
                'rules_failed'       => 0,
                'rules_unknown'      => 0,
                'passed_reasons'     => [],
                'failed_reasons'     => [],
                'unknown_reasons'    => [],
                'reason_text'        => 'No eligibility rules have been configured for this scheme yet, so eligibility must be confirmed manually.',
            ];
        }

        // Group the rules; groups are alternative qualifying routes.
        $groups = [];
        foreach ($rules as $rule) {
            $groups[(int)$rule['rule_group']][] = $rule;
        }

        $best = null;
        foreach ($groups as $groupNo => $groupRules) {
            $outcome = $this->evaluateGroup($profile, $groupRules);
            $outcome['rule_group'] = $groupNo;

            if ($best === null || $this->isBetter($outcome, $best)) {
                $best = $outcome;
            }
        }

        $status  = $this->grade($best);
        $percent = round($best['percent'], 2);

        return [
            'scheme_id'          => $schemeId,
            'match_percent'      => $percent,
            'eligibility_status' => $status,
            'rules_total'        => count($best['passed']) + count($best['failed']) + count($best['unknown']),
            'rules_passed'       => count($best['passed']),
            'rules_failed'       => count($best['failed']),
            'rules_unknown'      => count($best['unknown']),
            'passed_reasons'     => $best['passed'],
            'failed_reasons'     => $best['failed'],
            'unknown_reasons'    => $best['unknown'],
            'reason_text'        => $this->explain($status, $best),
        ];
    }

    /** Evaluate the AND-ed rules of one group. */
    private function evaluateGroup(array $profile, array $rules): array
    {
        $passed = $failed = $unknown = [];
        $totalWeight = 0;
        $passedWeight = 0;
        $mandatoryFailed = false;
        $mandatoryCount = 0;

        foreach ($rules as $rule) {
            $weight = max(1, (int)$rule['weight']);
            $totalWeight += $weight;
            if ((int)$rule['is_mandatory'] === 1) {
                $mandatoryCount++;
            }

            $key   = $rule['attribute_key'];
            $value = $profile[$key] ?? null;
            $label = $this->attributeLabel($key);
            $expected = $this->describeExpectation($rule);

            // is_set / not_set are the only operators that can judge absence.
            if (!in_array($rule['operator'], ['is_set', 'not_set'], true)
                && ($value === null || $value === '')) {
                $unknown[] = [
                    'attribute' => $key,
                    'label'     => $label,
                    'expected'  => $expected,
                    'message'   => $label . ' has not been recorded for this beneficiary',
                    'rule'      => $rule['description'],
                    'mandatory' => (int)$rule['is_mandatory'] === 1,
                ];
                continue;
            }

            if ($this->test($value, $rule)) {
                $passedWeight += $weight;
                $passed[] = [
                    'attribute' => $key,
                    'label'     => $label,
                    'actual'    => $this->displayValue($key, $value),
                    'expected'  => $expected,
                    'message'   => $label . ' is ' . $this->displayValue($key, $value),
                    'rule'      => $rule['description'],
                ];
            } else {
                $failed[] = [
                    'attribute' => $key,
                    'label'     => $label,
                    'actual'    => $this->displayValue($key, $value),
                    'expected'  => $expected,
                    'message'   => $label . ' is ' . $this->displayValue($key, $value) . ', but the scheme requires ' . $expected,
                    'rule'      => $rule['description'],
                    'mandatory' => (int)$rule['is_mandatory'] === 1,
                ];
                if ((int)$rule['is_mandatory'] === 1) {
                    $mandatoryFailed = true;
                }
            }
        }

        return [
            'passed'           => $passed,
            'failed'           => $failed,
            'unknown'          => $unknown,
            'percent'          => $totalWeight > 0 ? ($passedWeight / $totalWeight) * 100 : 0.0,
            'mandatory_failed' => $mandatoryFailed,
            'rule_count'       => count($rules),
            'mandatory_count'  => $mandatoryCount,
        ];
    }

    /** Prefer a group with no mandatory failure, then the higher score. */
    private function isBetter(array $candidate, array $current): bool
    {
        if ($candidate['mandatory_failed'] !== $current['mandatory_failed']) {
            return !$candidate['mandatory_failed'];
        }
        if (abs($candidate['percent'] - $current['percent']) > 0.001) {
            return $candidate['percent'] > $current['percent'];
        }
        // Tie-break on fewer unknowns, so the clearer answer wins.
        return count($candidate['unknown']) < count($current['unknown']);
    }

    private function grade(array $outcome): string
    {
        if ($outcome['mandatory_failed']) {
            return self::NOT;
        }
        $percent    = $outcome['percent'];
        $hasUnknown = count($outcome['unknown']) > 0;

        // Evidence sufficiency: a scheme described by a single soft rule can
        // reach 100% on very little information. Grading that as "Eligible"
        // would overstate what the data supports, so the top grade also
        // requires either a mandatory condition or a few corroborating rules.
        $wellEvidenced = $outcome['mandatory_count'] > 0 || $outcome['rule_count'] >= 3;

        if (!$hasUnknown && $percent >= $this->eligibleThreshold && $wellEvidenced) {
            return self::ELIGIBLE;
        }
        if ($percent >= $this->potentialThreshold) {
            return self::POTENTIAL;
        }
        return self::VERIFY;
    }

    /**
     * Single comparison.  Enum codes compare case-insensitively, numbers
     * numerically, booleans after normalisation.
     */
    private function test($value, array $rule): bool
    {
        $operator = $rule['operator'];
        $type     = $this->attributes[$rule['attribute_key']]['data_type'] ?? 'string';

        $v1 = $rule['value1'];
        $v2 = $rule['value2'];
        $list = array_values(array_filter(array_map('trim',
                explode(',', (string)($rule['value_list'] ?? ''))), fn($s) => $s !== ''));

        switch ($operator) {
            case 'is_set':
                return $value !== null && $value !== '';
            case 'not_set':
                return $value === null || $value === '';
        }

        if (in_array($type, ['int', 'decimal'], true)) {
            $actual = (float)$value;
            return match ($operator) {
                'eq'      => abs($actual - (float)$v1) < 0.00001,
                'neq'     => abs($actual - (float)$v1) >= 0.00001,
                'gt'      => $actual >  (float)$v1,
                'gte'     => $actual >= (float)$v1,
                'lt'      => $actual <  (float)$v1,
                'lte'     => $actual <= (float)$v1,
                'between' => $actual >= (float)$v1 && $actual <= (float)$v2,
                'any_of'  => in_array((string)(0 + $actual), array_map(fn($x) => (string)(0 + (float)$x), $list), true),
                'not_in'  => !in_array((string)(0 + $actual), array_map(fn($x) => (string)(0 + (float)$x), $list), true),
                'contains'=> str_contains((string)$value, (string)$v1),
                default   => false,
            };
        }

        if ($type === 'bool') {
            $actual = $this->toBool($value);
            $want   = $this->toBool($v1);
            return match ($operator) {
                'eq'     => $actual === $want,
                'neq'    => $actual !== $want,
                'any_of' => in_array($actual ? '1' : '0', array_map(fn($x) => $this->toBool($x) ? '1' : '0', $list), true),
                'not_in' => !in_array($actual ? '1' : '0', array_map(fn($x) => $this->toBool($x) ? '1' : '0', $list), true),
                default  => false,
            };
        }

        if ($type === 'date') {
            $actual = strtotime((string)$value) ?: 0;
            return match ($operator) {
                'eq'      => $actual === (strtotime((string)$v1) ?: -1),
                'neq'     => $actual !== (strtotime((string)$v1) ?: -1),
                'gt'      => $actual >  (strtotime((string)$v1) ?: 0),
                'gte'     => $actual >= (strtotime((string)$v1) ?: 0),
                'lt'      => $actual <  (strtotime((string)$v1) ?: 0),
                'lte'     => $actual <= (strtotime((string)$v1) ?: 0),
                'between' => $actual >= (strtotime((string)$v1) ?: 0) && $actual <= (strtotime((string)$v2) ?: 0),
                default   => false,
            };
        }

        // A 'set' attribute holds several answers at once (vulnerability
        // groups). It is compared as a set: "equal to" means the set contains
        // that value, and "any of" means the two overlap.
        if ($type === 'set') {
            $lower   = fn($x) => mb_strtolower(trim((string)$x));
            $members = array_values(array_filter(
                array_map($lower, explode(',', (string)$value)),
                fn($m) => $m !== ''
            ));
            $wanted = array_map($lower, $list);

            return match ($operator) {
                'eq', 'contains' => in_array($lower($v1), $members, true),
                'neq'            => !in_array($lower($v1), $members, true),
                'any_of'         => array_intersect($members, $wanted) !== [],
                'not_in'         => array_intersect($members, $wanted) === [],
                default          => false,
            };
        }

        // string / enum
        $actual = mb_strtolower(trim((string)$value));
        $lower  = fn($x) => mb_strtolower(trim((string)$x));

        return match ($operator) {
            'eq'       => $actual === $lower($v1),
            'neq'      => $actual !== $lower($v1),
            'contains' => $v1 !== null && $v1 !== '' && str_contains($actual, $lower($v1)),
            'any_of'   => in_array($actual, array_map($lower, $list), true),
            'not_in'   => !in_array($actual, array_map($lower, $list), true),
            // Ordered enums (education level, ration card, farmer category)
            // compare through the numeric_value on lookup_options.
            'gt', 'gte', 'lt', 'lte', 'between' => $this->compareOrdinal($actual, $rule, $operator),
            default    => false,
        };
    }

    /** Ordered comparison for enums that carry a numeric_value. */
    private function compareOrdinal(string $actual, array $rule, string $operator): bool
    {
        $lookupKey = $this->attributes[$rule['attribute_key']]['lookup_key'] ?? null;
        if (!$lookupKey) {
            return false;
        }
        $ordinals = $this->lookupOrdinals($lookupKey);
        $a = $ordinals[$actual] ?? null;
        $b = $ordinals[mb_strtolower(trim((string)$rule['value1']))] ?? null;
        if ($a === null || $b === null) {
            return false;
        }
        if ($operator === 'between') {
            $c = $ordinals[mb_strtolower(trim((string)$rule['value2']))] ?? null;
            return $c !== null && $a >= $b && $a <= $c;
        }
        return match ($operator) {
            'gt'  => $a >  $b,
            'gte' => $a >= $b,
            'lt'  => $a <  $b,
            'lte' => $a <= $b,
            default => false,
        };
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(mb_strtolower(trim((string)$value)), ['1', 'yes', 'true', 'y', 'on'], true);
    }

    // =================================================================
    // MATCHING A BENEFICIARY AGAINST THE CATALOGUE
    // =================================================================

    /**
     * Evaluate every scheme applicable to the beneficiary's State.
     *
     * @param bool $includeNotEligible keep negative results so the profile
     *                                 screen can explain why a scheme does
     *                                 not apply
     */
    public function matchBeneficiary(int $beneficiaryId, bool $includeNotEligible = true): array
    {
        $profile = $this->buildProfile($beneficiaryId);
        if ($profile === []) {
            return [];
        }

        $stateId = isset($profile['state_id']) ? (int)$profile['state_id'] : null;
        $schemes = $this->applicableSchemes($stateId);
        $rulesByScheme = $this->rulesForSchemes(array_column($schemes, 'id'));

        $results = [];
        foreach ($schemes as $scheme) {
            $schemeId = (int)$scheme['id'];
            $outcome  = $this->evaluateScheme($profile, $schemeId, $rulesByScheme[$schemeId] ?? []);

            if (!$includeNotEligible && $outcome['eligibility_status'] === self::NOT) {
                continue;
            }

            $missing = $this->missingDocuments($beneficiaryId, $schemeId);
            $outcome['missing_documents'] = $missing;
            $outcome['missing_doc_count'] = count($missing);
            $outcome['scheme_name']       = $scheme['name'];
            $outcome['scheme_short_name'] = $scheme['short_name'];
            $outcome['scheme_code']       = $scheme['scheme_code'];
            $outcome['scheme_level']      = $scheme['scheme_level'];
            $outcome['theme_name']        = $scheme['theme_name'];

            $results[] = $outcome;
        }

        usort($results, function ($a, $b) {
            $order = [self::ELIGIBLE => 0, self::POTENTIAL => 1, self::VERIFY => 2, self::NOT => 3];
            $cmp = $order[$a['eligibility_status']] <=> $order[$b['eligibility_status']];
            if ($cmp !== 0) {
                return $cmp;
            }
            return $b['match_percent'] <=> $a['match_percent'];
        });

        return $results;
    }

    /**
     * Run the match and store the outcome, then refresh the beneficiary's
     * status stamps.  Returns the number of schemes recorded.
     */
    public function matchAndStore(int $beneficiaryId, bool $includeNotEligible = true): array
    {
        $results = $this->matchBeneficiary($beneficiaryId, $includeNotEligible);
        $now     = date('Y-m-d H:i:s');
        $userId  = Auth::check() ? Auth::id() : null;

        $this->db->transaction(function () use ($beneficiaryId, $results, $now, $userId) {
            // Replace the previous result set wholesale: a stale row would
            // otherwise keep showing an eligibility that no longer holds.
            $this->db->execute('DELETE FROM beneficiary_scheme_matches WHERE beneficiary_id = :id',
                ['id' => $beneficiaryId]);

            foreach ($results as $r) {
                $this->db->insert('beneficiary_scheme_matches', [
                    'beneficiary_id'     => $beneficiaryId,
                    'scheme_id'          => $r['scheme_id'],
                    'match_percent'      => $r['match_percent'],
                    'eligibility_status' => $r['eligibility_status'],
                    'rules_total'        => $r['rules_total'],
                    'rules_passed'       => $r['rules_passed'],
                    'rules_failed'       => $r['rules_failed'],
                    'rules_unknown'      => $r['rules_unknown'],
                    'passed_reasons'     => $this->jsonReasons($r['passed_reasons']),
                    'failed_reasons'     => $this->jsonReasons($r['failed_reasons']),
                    'unknown_reasons'    => $this->jsonReasons($r['unknown_reasons']),
                    'missing_documents'  => json_encode($r['missing_documents'] ?? [], JSON_UNESCAPED_UNICODE),
                    'missing_doc_count'  => $r['missing_doc_count'] ?? 0,
                    'reason_text'        => mb_substr($r['reason_text'], 0, 1000),
                    'matched_at'         => $now,
                    'matched_by'         => $userId,
                ]);
            }

            $eligible = 0;
            foreach ($results as $r) {
                if ($r['eligibility_status'] !== self::NOT) {
                    $eligible++;
                }
            }

            $update = ['last_matched_at' => $now];
            if ($eligible > 0) {
                // Do not push a record backwards once it is further along.
                $current = $this->db->scalar('SELECT record_status FROM beneficiaries WHERE id = :id',
                    ['id' => $beneficiaryId]);
                if (in_array($current, ['Registered', 'Assessed'], true)) {
                    $update['record_status'] = 'Matched';
                }
            }
            $this->db->update('beneficiaries', $update, ['id' => $beneficiaryId]);
        });

        return $results;
    }

    /**
     * Ad-hoc check used by "Find Eligible Schemes" before a beneficiary
     * exists - the caller supplies the profile directly.
     */
    public function simulate(array $profile, ?int $stateId = null, int $limit = 50): array
    {
        $profile = $this->applyDerived($profile);
        if ($stateId !== null) {
            $profile['state_id'] = $stateId;
        }

        $schemes = $this->applicableSchemes($stateId);
        $rulesByScheme = $this->rulesForSchemes(array_column($schemes, 'id'));

        $results = [];
        foreach ($schemes as $scheme) {
            $schemeId = (int)$scheme['id'];
            $outcome = $this->evaluateScheme($profile, $schemeId, $rulesByScheme[$schemeId] ?? []);
            if ($outcome['eligibility_status'] === self::NOT) {
                continue;
            }
            $outcome['scheme_name']       = $scheme['name'];
            $outcome['scheme_short_name'] = $scheme['short_name'];
            $outcome['scheme_code']       = $scheme['scheme_code'];
            $outcome['scheme_level']      = $scheme['scheme_level'];
            $outcome['theme_name']        = $scheme['theme_name'];
            $outcome['missing_documents'] = [];
            $outcome['missing_doc_count'] = 0;
            $results[] = $outcome;
        }

        usort($results, fn($a, $b) => $b['match_percent'] <=> $a['match_percent']);
        return array_slice($results, 0, $limit);
    }

    // =================================================================
    // SUPPORTING QUERIES
    // =================================================================

    /** Active schemes that apply nationally or in the given State. */
    public function applicableSchemes(?int $stateId): array
    {
        $sql = "SELECT s.id, s.scheme_code, s.name, s.short_name, s.scheme_level, s.state_id,
                       th.name AS theme_name
                  FROM schemes s
                  LEFT JOIN scheme_themes th ON th.id = s.theme_id
                 WHERE s.deleted_at IS NULL
                   AND s.is_active = 1
                   AND s.scheme_status <> 'Discontinued'";
        $bindings = [];

        if ($stateId !== null) {
            $sql .= " AND (s.scheme_level = 'National'
                           OR s.state_id = :state
                           OR EXISTS (SELECT 1 FROM scheme_states ss
                                       WHERE ss.scheme_id = s.id AND ss.state_id = :state2))";
            $bindings['state']  = $stateId;
            $bindings['state2'] = $stateId;
        } else {
            $sql .= " AND s.scheme_level = 'National'";
        }

        return $this->db->select($sql . ' ORDER BY s.id', $bindings);
    }

    public function rulesFor(int $schemeId): array
    {
        return $this->db->select(
            'SELECT * FROM scheme_eligibility_rules
              WHERE scheme_id = :id AND is_active = 1
              ORDER BY rule_group, sort_order, id',
            ['id' => $schemeId]
        );
    }

    /** One query for many schemes, so matching stays a handful of round trips. */
    private function rulesForSchemes(array $schemeIds): array
    {
        if ($schemeIds === []) {
            return [];
        }
        [$holders, $bindings] = Database::inClause(array_map('intval', $schemeIds), 'sid');
        $rows = $this->db->select(
            "SELECT * FROM scheme_eligibility_rules
              WHERE is_active = 1 AND scheme_id IN ({$holders})
              ORDER BY scheme_id, rule_group, sort_order, id",
            $bindings
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['scheme_id']][] = $row;
        }
        return $out;
    }

    /**
     * Mandatory scheme documents the beneficiary has not uploaded (a
     * rejected upload still counts as missing).
     */
    public function missingDocuments(int $beneficiaryId, int $schemeId): array
    {
        $rows = $this->db->select(
            "SELECT dt.name
               FROM scheme_documents sd
               JOIN document_types dt ON dt.id = sd.document_type_id
              WHERE sd.scheme_id = :scheme
                AND sd.is_mandatory = 1
                AND NOT EXISTS (
                      SELECT 1 FROM beneficiary_documents bd
                       WHERE bd.beneficiary_id = :ben
                         AND bd.document_type_id = sd.document_type_id
                         AND bd.verification_status <> 'Rejected')
              ORDER BY sd.sort_order, dt.name",
            ['scheme' => $schemeId, 'ben' => $beneficiaryId]
        );
        return array_column($rows, 'name');
    }

    // =================================================================
    // EXPLANATION - section 20 requires the "why"
    // =================================================================

    /**
     * Plain-language sentence, e.g.
     * "Matched because Age is 62, BPL Status is Yes, State is Odisha."
     */
    private function explain(string $status, array $outcome): string
    {
        $passed  = array_map(fn($r) => $r['label'] . ' is ' . $r['actual'], $outcome['passed']);
        $failed  = array_map(fn($r) => $r['label'] . ' is ' . $r['actual'] . ' (needs ' . $r['expected'] . ')', $outcome['failed']);
        $unknown = array_map(fn($r) => $r['label'], $outcome['unknown']);

        $parts = [];

        if ($status === self::NOT) {
            $blockers = array_map(
                fn($r) => $r['label'] . ' is ' . $r['actual'] . ' but the scheme requires ' . $r['expected'],
                array_filter($outcome['failed'], fn($r) => !empty($r['mandatory']))
            );
            $parts[] = 'Not eligible because ' . implode('; ', array_slice($blockers, 0, 3)) . '.';
            if ($passed) {
                $parts[] = 'Other conditions met: ' . implode(', ', array_slice($passed, 0, 4)) . '.';
            }
            return implode(' ', $parts);
        }

        if ($passed) {
            $parts[] = 'Matched because ' . implode(', ', array_slice($passed, 0, 5)) . '.';
        }
        if ($failed) {
            $parts[] = 'Conditions not met: ' . implode('; ', array_slice($failed, 0, 3)) . '.';
        }
        if ($unknown) {
            $parts[] = 'Still to confirm: ' . implode(', ', array_slice($unknown, 0, 5)) . '.';
        }
        if ($parts === []) {
            $parts[] = 'Eligibility could not be determined from the information recorded so far.';
        }
        return implode(' ', $parts);
    }

    /** Human phrase for the right-hand side of a rule. */
    private function describeExpectation(array $rule): string
    {
        $key  = $rule['attribute_key'];
        $type = $this->attributes[$key]['data_type'] ?? 'string';
        $unit = $this->attributes[$key]['unit'] ?? null;

        $fmt = function ($v) use ($key, $type, $unit) {
            $shown = $this->displayValue($key, $v);
            if ($unit && in_array($type, ['int', 'decimal'], true) && $unit !== '%') {
                return $shown . ' ' . $unit;
            }
            if ($unit === '%') {
                return $shown . '%';
            }
            return $shown;
        };

        $list = array_values(array_filter(array_map('trim',
                explode(',', (string)($rule['value_list'] ?? '')))));

        return match ($rule['operator']) {
            'eq'       => $fmt($rule['value1']),
            'neq'      => 'anything other than ' . $fmt($rule['value1']),
            'gt'       => 'more than ' . $fmt($rule['value1']),
            'gte'      => $fmt($rule['value1']) . ' or above',
            'lt'       => 'less than ' . $fmt($rule['value1']),
            'lte'      => $fmt($rule['value1']) . ' or below',
            'between'  => $fmt($rule['value1']) . ' to ' . $fmt($rule['value2']),
            'contains' => 'text containing "' . $rule['value1'] . '"',
            'any_of'   => 'one of ' . implode(', ', array_map($fmt, $list)),
            'not_in'   => 'none of ' . implode(', ', array_map($fmt, $list)),
            'is_set'   => 'to be recorded',
            'not_set'  => 'to be blank',
            default    => (string)$rule['value1'],
        };
    }

    /** Render a stored code as the label a user would recognise. */
    public function displayValue(string $attributeKey, $value): string
    {
        if ($value === null || $value === '') {
            return 'not recorded';
        }
        $attr = $this->attributes[$attributeKey] ?? null;

        if ($attributeKey === 'state_id') {
            return $this->stateNames[(int)$value] ?? ('State #' . (int)$value);
        }
        if ($attributeKey === 'district_id') {
            return $this->districtNames[(int)$value] ?? ('District #' . (int)$value);
        }
        if ($attr && $attr['data_type'] === 'bool') {
            return $this->toBool($value) ? 'Yes' : 'No';
        }
        if ($attr && $attr['lookup_key']) {
            $labels = $this->lookupLabels($attr['lookup_key']);
            $key = mb_strtolower(trim((string)$value));
            if (isset($labels[$key])) {
                return $labels[$key];
            }
        }
        if ($attr && $attr['data_type'] === 'decimal' && $attr['unit'] === 'INR') {
            return money($value);
        }
        if ($attr && in_array($attr['data_type'], ['int', 'decimal'], true)) {
            return rtrim(rtrim(number_format((float)$value, 2, '.', ','), '0'), '.');
        }
        return (string)$value;
    }

    public function attributeLabel(string $key): string
    {
        return $this->attributes[$key]['label'] ?? ucwords(str_replace('_', ' ', $key));
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    /** Attribute definitions grouped for the rule-builder UI. */
    public function attributesByGroup(): array
    {
        $out = [];
        foreach ($this->attributes as $key => $attr) {
            $out[$attr['group_label'] ?: 'General'][$key] = $attr;
        }
        return $out;
    }

    public function operators(): array
    {
        return [
            'eq'       => 'Equal To',
            'neq'      => 'Not Equal To',
            'gt'       => 'Greater Than',
            'gte'      => 'Greater Than or Equal',
            'lt'       => 'Less Than',
            'lte'      => 'Less Than or Equal',
            'between'  => 'Between',
            'contains' => 'Contains',
            'any_of'   => 'Any Of',
            'not_in'   => 'None Of',
            'is_set'   => 'Is Recorded',
            'not_set'  => 'Is Not Recorded',
        ];
    }

    // =================================================================
    // VOCABULARY CACHE
    // =================================================================

    private function loadVocabulary(): void
    {
        foreach ($this->db->select(
            'SELECT attribute_key, label, data_type, lookup_key, source, source_column,
                    unit, group_label, description, allowed_operators
               FROM eligibility_attributes WHERE is_active = 1
              ORDER BY sort_order, label') as $row) {
            $this->attributes[$row['attribute_key']] = $row;
        }

        foreach ($this->db->select('SELECT id, name FROM states') as $row) {
            $this->stateNames[(int)$row['id']] = $row['name'];
        }
        foreach ($this->db->select('SELECT id, name FROM districts') as $row) {
            $this->districtNames[(int)$row['id']] = $row['name'];
        }
    }

    private function lookupLabels(string $lookupKey): array
    {
        if (isset($this->lookupLabels[$lookupKey])) {
            return $this->lookupLabels[$lookupKey];
        }
        $map = [];
        foreach ($this->db->select(
            'SELECT code, label FROM lookup_options WHERE lookup_key = :k ORDER BY sort_order',
            ['k' => $lookupKey]) as $row) {
            $map[mb_strtolower($row['code'])] = $row['label'];
        }
        return $this->lookupLabels[$lookupKey] = $map;
    }

    private function lookupOrdinals(string $lookupKey): array
    {
        static $cache = [];
        if (isset($cache[$lookupKey])) {
            return $cache[$lookupKey];
        }
        $map = [];
        foreach ($this->db->select(
            'SELECT code, numeric_value, sort_order FROM lookup_options
              WHERE lookup_key = :k ORDER BY sort_order', ['k' => $lookupKey]) as $row) {
            $map[mb_strtolower($row['code'])] = $row['numeric_value'] !== null
                ? (float)$row['numeric_value']
                : (float)$row['sort_order'];
        }
        return $cache[$lookupKey] = $map;
    }

    private function jsonReasons(array $reasons): string
    {
        // Store just what the UI needs, so the JSON column stays small.
        $slim = array_map(fn($r) => [
            'label'     => $r['label']    ?? null,
            'actual'    => $r['actual']   ?? null,
            'expected'  => $r['expected'] ?? null,
            'message'   => $r['message']  ?? null,
            'rule'      => $r['rule']     ?? null,
            'mandatory' => !empty($r['mandatory']),
        ], $reasons);

        return json_encode(array_slice($slim, 0, 40), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
