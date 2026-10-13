<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Database;
use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;

/**
 * Beneficiary register and field data (specification sections 17-19, 23-24).
 */
class Beneficiary extends Model
{
    protected string $table = 'beneficiaries';
    protected bool $softDelete = true;

    protected array $fillable = [
        'beneficiary_code', 'registration_date', 'name', 'relation_type', 'relation_name',
        'father_name', 'mother_name', 'spouse_name',
        'gender', 'dob', 'age', 'mobile', 'alt_mobile',
        'aadhaar_last4', 'aadhaar_hash', 'has_aadhaar',
        'state_id', 'district_id', 'block_id', 'gram_panchayat_id', 'village_id',
        'village_name', 'post_office', 'address_line', 'pincode', 'area_type', 'photo_path',
        'record_status', 'assessment_status', 'last_assessed_at', 'last_matched_at',
        'consent_given', 'consent_date', 'field_user_id', 'remarks',
    ];

    public const SORTS = [
        'code'       => 'v.beneficiary_code',
        'name'       => 'v.name',
        'age'        => 'v.age',
        'gender'     => 'v.gender',
        'state'      => 'v.state_name',
        'district'   => 'v.district_name',
        'village'    => 'v.village_name',
        'registered' => 'v.registration_date',
        'status'     => 'v.record_status',
        'assessment' => 'v.assessment_status',
        'schemes'    => 'v.schemes_identified',
        'field_user' => 'v.field_user_name',
        'updated'    => 'v.updated_at',
    ];

    // =================================================================
    // Listing
    // =================================================================

    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ', v.id DESC';

        return $this->paginateQuery(
            'SELECT v.* FROM vw_beneficiary_listing v',
            'FROM vw_beneficiary_listing v',
            $where, $bindings, $order, $page, $perPage, $queryString
        );
    }

    public function allFiltered(array $filters, string $sortColumn = 'v.name', string $sortDir = 'ASC', int $limit = 20000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            "SELECT v.* FROM vw_beneficiary_listing v WHERE {$where}
              ORDER BY {$sortColumn} " . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . '
              LIMIT ' . (int)$limit,
            $bindings
        );
    }

    private function buildFilters(array $filters): array
    {
        // Row-level geographic restriction, straight from the user's role.
        [$scopeSql, $bindings] = Scope::clause('v');
        $where = [$scopeSql];

        if (!empty($filters['search'])) {
            $where[] = '(v.name LIKE :s1 OR v.beneficiary_code LIKE :s2 OR v.mobile LIKE :s3)';
            $like = '%' . $filters['search'] . '%';
            $bindings['s1'] = $like;
            $bindings['s2'] = $like;
            $bindings['s3'] = $like;
        }
        if (!empty($filters['state_id'])) {
            $where[] = 'v.state_id = :f_state';
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'v.district_id = :f_district';
            $bindings['f_district'] = (int)$filters['district_id'];
        }
        if (!empty($filters['block_id'])) {
            $where[] = 'v.block_id = :f_block';
            $bindings['f_block'] = (int)$filters['block_id'];
        }
        if (!empty($filters['village_id'])) {
            $where[] = 'v.village_id = :f_village';
            $bindings['f_village'] = (int)$filters['village_id'];
        }
        if (!empty($filters['gender'])) {
            $where[] = 'v.gender = :f_gender';
            $bindings['f_gender'] = $filters['gender'];
        }
        if (!empty($filters['area_type'])) {
            $where[] = 'v.area_type = :f_area';
            $bindings['f_area'] = $filters['area_type'];
        }
        if (!empty($filters['record_status'])) {
            $where[] = 'v.record_status = :f_status';
            $bindings['f_status'] = $filters['record_status'];
        }
        if (!empty($filters['assessment_status'])) {
            $where[] = 'v.assessment_status = :f_assess';
            $bindings['f_assess'] = $filters['assessment_status'];
        }
        if (!empty($filters['field_user_id'])) {
            $where[] = 'v.field_user_id = :f_user';
            $bindings['f_user'] = (int)$filters['field_user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'v.registration_date >= :f_from';
            $bindings['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'v.registration_date <= :f_to';
            $bindings['f_to'] = $filters['date_to'];
        }
        if (!empty($filters['age_group'])) {
            [$lo, $hi] = $this->ageBounds((string)$filters['age_group']);
            if ($lo !== null) {
                $where[] = 'v.age >= :f_agelo';
                $bindings['f_agelo'] = $lo;
            }
            if ($hi !== null) {
                $where[] = 'v.age <= :f_agehi';
                $bindings['f_agehi'] = $hi;
            }
        }
        if (!empty($filters['scheme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM beneficiary_scheme_matches m
                                 WHERE m.beneficiary_id = v.id AND m.scheme_id = :f_scheme
                                   AND m.eligibility_status <> "Not Eligible")';
            $bindings['f_scheme'] = (int)$filters['scheme_id'];
        }
        if (!empty($filters['has_documents_pending'])) {
            $where[] = 'EXISTS (SELECT 1 FROM beneficiary_scheme_matches m
                                 WHERE m.beneficiary_id = v.id AND m.missing_doc_count > 0
                                   AND m.eligibility_status IN ("Eligible","Potentially Eligible"))';
        }

        return [implode(' AND ', $where), $bindings];
    }

    private function ageBounds(string $group): array
    {
        return match ($group) {
            '0-5'   => [0, 5],
            '6-14'  => [6, 14],
            '15-17' => [15, 17],
            '18-35' => [18, 35],
            '36-59' => [36, 59],
            '60+'   => [60, null],
            default => [null, null],
        };
    }

    // =================================================================
    // Single beneficiary - the 360-degree profile (section 24)
    // =================================================================

    public function findDetailed(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT b.*, st.name AS state_name, d.name AS district_name,
                    bl.name AS block_name, gp.name AS gp_name,
                    COALESCE(v.name, b.village_name) AS village_display, v.pincode AS village_pincode,
                    fu.full_name AS field_user_name, fu.mobile AS field_user_mobile,
                    cu.full_name AS created_by_name, uu.full_name AS updated_by_name
               FROM beneficiaries b
               LEFT JOIN states st         ON st.id = b.state_id
               LEFT JOIN districts d       ON d.id  = b.district_id
               LEFT JOIN blocks bl         ON bl.id = b.block_id
               LEFT JOIN gram_panchayats gp ON gp.id = b.gram_panchayat_id
               LEFT JOIN villages v        ON v.id  = b.village_id
               LEFT JOIN users fu          ON fu.id = b.field_user_id
               LEFT JOIN users cu          ON cu.id = b.created_by
               LEFT JOIN users uu          ON uu.id = b.updated_by
              WHERE b.id = :id AND b.deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }

        $row['household'] = $this->household($id);
        $row['members']   = $this->members($id);
        return $row;
    }

    public function household(int $beneficiaryId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM beneficiary_households WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]
        );
    }

    public function members(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT * FROM beneficiary_members WHERE beneficiary_id = :id ORDER BY id',
            ['id' => $beneficiaryId]
        );
    }

    /** The socio-economic answers, joined to their question definitions. */
    /**
     * Recorded answers keyed by question, in the shape the form fields expect:
     * a Yes/No string for booleans, a number for numerics, and an array for a
     * multi-answer question stored as a comma-separated set.
     */
    public function answerMap(int $beneficiaryId): array
    {
        $multi = array_column($this->db->select(
            "SELECT question_key FROM assessment_questions WHERE input_type = 'multiselect'"
        ), 'question_key');

        $map = [];
        foreach ($this->assessmentAnswers($beneficiaryId) as $answer) {
            $key = $answer['question_key'];
            if (in_array($key, $multi, true)) {
                $map[$key] = array_values(array_filter(
                    array_map('trim', explode(',', (string)$answer['value_text']))
                ));
                continue;
            }
            $map[$key] = $answer['value_bool'] !== null
                ? ((int)$answer['value_bool'] === 1 ? 'Yes' : 'No')
                : ($answer['value_number'] !== null ? $answer['value_number'] : $answer['value_text']);
        }
        return $map;
    }

    public function assessmentAnswers(int $beneficiaryId): array
    {
        // The flattened profile keys its rows by attribute_key; the
        // questionnaire keys its questions by question_key. They use the same
        // vocabulary, which is what lets a new question feed the engine.
        return $this->db->select(
            'SELECT a.attribute_key AS question_key,
                    a.value_text, a.value_number, a.value_bool, a.value_date,
                    q.label, q.section, q.input_type, q.lookup_key, q.unit, q.sort_order,
                    lo.label AS option_label
               FROM beneficiary_profile_attributes a
               LEFT JOIN assessment_questions q ON q.question_key = a.attribute_key
               LEFT JOIN lookup_options lo ON lo.lookup_key = q.lookup_key AND lo.code = a.value_text
              WHERE a.beneficiary_id = :id
              ORDER BY q.section IS NULL, q.section, q.sort_order, a.attribute_key',
            ['id' => $beneficiaryId]
        );
    }

    public function latestAssessment(int $beneficiaryId): ?array
    {
        return $this->db->selectOne(
            'SELECT a.*, u.full_name AS assessed_by_name
               FROM beneficiary_assessments a
               LEFT JOIN users u ON u.id = a.assessed_by
              WHERE a.beneficiary_id = :id
              ORDER BY a.assessment_date DESC, a.id DESC LIMIT 1',
            ['id' => $beneficiaryId]
        );
    }

    public function documents(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT bd.*, dt.name AS document_name, dt.code AS document_code,
                    dt.category, dt.is_sensitive, dt.validity_required AS type_validity_required,
                    vu.full_name AS verified_by_name, uu.full_name AS uploaded_by_name
               FROM beneficiary_documents bd
               JOIN document_types dt ON dt.id = bd.document_type_id
               LEFT JOIN users vu ON vu.id = bd.verified_by
               LEFT JOIN users uu ON uu.id = bd.uploaded_by
              WHERE bd.beneficiary_id = :id
              ORDER BY dt.sort_order, bd.created_at DESC',
            ['id' => $beneficiaryId]
        );
    }

    public function matches(int $beneficiaryId, bool $onlyPositive = false): array
    {
        $sql = 'SELECT m.*, s.name AS scheme_name, s.short_name, s.scheme_code, s.scheme_level,
                       s.benefit_description, s.portal_url, s.application_mode,
                       th.name AS theme_name, th.color AS theme_color,
                       pl.rank_value AS priority_rank,
                       sa.name AS service_approach,
                       (SELECT a.id FROM beneficiary_scheme_applications a
                         WHERE a.beneficiary_id = m.beneficiary_id AND a.scheme_id = m.scheme_id
                           AND a.deleted_at IS NULL LIMIT 1) AS application_id
                  FROM beneficiary_scheme_matches m
                  JOIN schemes s ON s.id = m.scheme_id
                  LEFT JOIN scheme_themes th   ON th.id = s.theme_id
                  LEFT JOIN priority_levels pl ON pl.id = s.priority_id
                  LEFT JOIN service_approaches sa ON sa.id = s.service_approach_id
                 WHERE m.beneficiary_id = :id';
        if ($onlyPositive) {
            $sql .= " AND m.eligibility_status <> 'Not Eligible'";
        }
        $sql .= " ORDER BY FIELD(m.eligibility_status,'Eligible','Potentially Eligible','Needs Verification','Not Eligible'),
                           m.match_percent DESC, s.name";

        return $this->db->select($sql, ['id' => $beneficiaryId]);
    }

    public function applications(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT * FROM vw_application_listing WHERE beneficiary_id = :id ORDER BY stage_order DESC, created_at DESC',
            ['id' => $beneficiaryId]
        );
    }

    public function followups(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT f.*, s.short_name AS scheme_short_name, s.name AS scheme_name,
                    ast.name AS status_name, ast.color AS status_color,
                    u.full_name AS responsible_name, cu.full_name AS created_by_name
               FROM application_followups f
               LEFT JOIN schemes s ON s.id = f.scheme_id
               LEFT JOIN application_statuses ast ON ast.id = f.status_id
               LEFT JOIN users u  ON u.id  = f.responsible_user_id
               LEFT JOIN users cu ON cu.id = f.created_by
              WHERE f.beneficiary_id = :id
              ORDER BY f.followup_date DESC, f.id DESC',
            ['id' => $beneficiaryId]
        );
    }

    public function benefits(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT br.*, s.name AS scheme_name, s.short_name
               FROM benefits_received br
               JOIN schemes s ON s.id = br.scheme_id
              WHERE br.beneficiary_id = :id
              ORDER BY br.received_date DESC',
            ['id' => $beneficiaryId]
        );
    }

    public function visits(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT fv.*, u.full_name AS field_user_name, v.name AS village_master_name
               FROM field_visits fv
               LEFT JOIN users u ON u.id = fv.field_user_id
               LEFT JOIN villages v ON v.id = fv.village_id
              WHERE fv.beneficiary_id = :id ORDER BY fv.visit_date DESC',
            ['id' => $beneficiaryId]
        );
    }

    public function auditHistory(int $beneficiaryId, int $limit = 50): array
    {
        return $this->db->select(
            'SELECT * FROM audit_logs
              WHERE (entity_type = "Beneficiary" AND entity_id = :id)
              ORDER BY created_at DESC LIMIT ' . (int)$limit,
            ['id' => $beneficiaryId]
        );
    }

    /**
     * The journey timeline shown on the profile:
     * Registered -> Assessed -> Scheme Identified -> Documents -> Applied -> Approved -> Benefit.
     */
    public function timeline(int $beneficiaryId, array $beneficiary): array
    {
        $timeline = [];

        $timeline[] = [
            'label' => 'Registered',
            'date'  => $beneficiary['registration_date'] ?? null,
            'done'  => true,
            'icon'  => 'bi-person-plus',
            'detail'=> 'Household registered by ' . ($beneficiary['field_user_name'] ?? 'the field team'),
        ];

        $assessment = $this->latestAssessment($beneficiaryId);
        $timeline[] = [
            'label' => 'Assessment Completed',
            'date'  => $assessment['assessment_date'] ?? null,
            'done'  => $assessment !== null && $assessment['status'] === 'Completed',
            'icon'  => 'bi-clipboard-check',
            'detail'=> $assessment
                ? sprintf('%d answers recorded (%s%% complete)', (int)$assessment['answered_count'], $assessment['completeness_pct'])
                : 'Socio-economic assessment not yet completed',
        ];

        $matchCount = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM beneficiary_scheme_matches
              WHERE beneficiary_id = :id AND eligibility_status <> 'Not Eligible'",
            ['id' => $beneficiaryId]);
        $timeline[] = [
            'label' => 'Schemes Identified',
            'date'  => $beneficiary['last_matched_at'] ?? null,
            'done'  => $matchCount > 0,
            'icon'  => 'bi-search',
            'detail'=> $matchCount > 0
                ? $matchCount . ' scheme(s) identified as applicable'
                : 'Scheme matching not yet run',
        ];

        $missing = (int)$this->db->scalar(
            "SELECT COALESCE(SUM(missing_doc_count),0) FROM beneficiary_scheme_matches
              WHERE beneficiary_id = :id AND eligibility_status IN ('Eligible','Potentially Eligible')",
            ['id' => $beneficiaryId]);
        $docCount = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM beneficiary_documents WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]);
        $timeline[] = [
            'label' => 'Documents Collected',
            'date'  => null,
            'done'  => $docCount > 0 && $missing === 0,
            'icon'  => 'bi-folder-check',
            'detail'=> $missing > 0
                ? $missing . ' document requirement(s) still outstanding'
                : ($docCount > 0 ? $docCount . ' document(s) on file' : 'No documents uploaded yet'),
        ];

        $submitted = $this->db->selectOne(
            'SELECT MIN(submission_date) AS d, COUNT(*) AS n FROM beneficiary_scheme_applications
              WHERE beneficiary_id = :id AND submission_date IS NOT NULL AND deleted_at IS NULL',
            ['id' => $beneficiaryId]);
        $timeline[] = [
            'label' => 'Application Submitted',
            'date'  => $submitted['d'] ?? null,
            'done'  => (int)($submitted['n'] ?? 0) > 0,
            'icon'  => 'bi-send',
            'detail'=> (int)($submitted['n'] ?? 0) > 0
                ? (int)$submitted['n'] . ' application(s) submitted to departments'
                : 'No application submitted yet',
        ];

        $approved = $this->db->selectOne(
            'SELECT MIN(approval_date) AS d, COUNT(*) AS n FROM beneficiary_scheme_applications
              WHERE beneficiary_id = :id AND approval_date IS NOT NULL AND deleted_at IS NULL',
            ['id' => $beneficiaryId]);
        $timeline[] = [
            'label' => 'Approved',
            'date'  => $approved['d'] ?? null,
            'done'  => (int)($approved['n'] ?? 0) > 0,
            'icon'  => 'bi-check2-circle',
            'detail'=> (int)($approved['n'] ?? 0) > 0
                ? (int)$approved['n'] . ' application(s) approved'
                : 'Awaiting departmental approval',
        ];

        $benefit = $this->db->selectOne(
            'SELECT MIN(received_date) AS d, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total
               FROM benefits_received WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]);
        $timeline[] = [
            'label' => 'Benefit Received',
            'date'  => $benefit['d'] ?? null,
            'done'  => (int)($benefit['n'] ?? 0) > 0,
            'icon'  => 'bi-award',
            'detail'=> (int)($benefit['n'] ?? 0) > 0
                ? sprintf('%d benefit record(s), %s received', (int)$benefit['n'], money($benefit['total']))
                : 'No benefit recorded yet',
        ];

        return $timeline;
    }

    // =================================================================
    // Writes
    // =================================================================

    /** JK-<STATE>-<YEAR>-<serial>, unique per State and year. */
    public function nextBeneficiaryCode(int $stateId): string
    {
        $stateCode = (string)$this->db->scalar('SELECT state_code FROM states WHERE id = :id', ['id' => $stateId]) ?: 'IN';
        $prefix = sprintf('JK-%s-%s-', $stateCode, date('Y'));

        $max = (int)$this->db->scalar(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(beneficiary_code, :len) AS UNSIGNED)), 0)
               FROM beneficiaries WHERE beneficiary_code LIKE :prefix',
            ['len' => strlen($prefix) + 1, 'prefix' => $prefix . '%']
        );
        return $prefix . str_pad((string)($max + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Write a household record wholesale.
     *
     * The registration form no longer collects these fields, so nothing in the
     * application calls this today - it is kept for the import and API paths
     * that may supply a full household record. Note that it replaces every
     * column it is given, so never call it with a partially populated array
     * against an existing row; deriveHouseholdFacts() updates single columns.
     */
    public function saveHousehold(int $beneficiaryId, array $data): void
    {
        $data['beneficiary_id'] = $beneficiaryId;
        $existing = $this->db->scalar('SELECT id FROM beneficiary_households WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]);

        if ($existing) {
            unset($data['beneficiary_id']);
            $this->db->update('beneficiary_households', $data, ['beneficiary_id' => $beneficiaryId]);
        } else {
            $this->db->insert('beneficiary_households', $data);
        }
    }

    /**
     * Save assessment answers and publish them to the flattened profile
     * the matching engine reads.
     */
    /**
     * Remove answers whose question was not actually asked, because the
     * question it depends on does not hold one of the triggering values.
     * Runs repeatedly so a chain (earning -> occupation -> "if Other") settles.
     */
    private function dropUnaskedAnswers(array $answers, array $byKey): array
    {
        $valueOf = function (string $key) use (&$answers) {
            $raw = $answers[$key] ?? null;
            if ($raw === null || $raw === '') {
                return [];
            }
            return is_array($raw) ? array_map('strval', $raw) : [(string)$raw];
        };

        for ($pass = 0; $pass < 5; $pass++) {
            $dropped = false;
            foreach ($byKey as $key => $question) {
                if (empty($question['depends_on_key']) || !array_key_exists($key, $answers)) {
                    continue;
                }
                $want = array_filter(array_map('trim',
                    explode(',', (string)($question['depends_on_value'] ?? ''))));
                $have = $valueOf($question['depends_on_key']);
                $met  = $want === []
                    ? $have !== []
                    : array_intersect($want, $have) !== [];

                if (!$met) {
                    unset($answers[$key]);
                    $dropped = true;
                }
            }
            if (!$dropped) {
                break;
            }
        }
        return $answers;
    }

    public function saveAssessment(int $beneficiaryId, array $answers, ?int $userId, bool $complete = true): int
    {
        return $this->db->transaction(function () use ($beneficiaryId, $answers, $userId, $complete) {
            $questions = $this->db->select(
                'SELECT question_key, input_type, feeds_eligibility, depends_on_key, depends_on_value
                   FROM assessment_questions WHERE is_active = 1'
            );
            $byKey = [];
            foreach ($questions as $q) {
                $byKey[$q['question_key']] = $q;
            }

            // The browser hides a question whose condition is not met, but the
            // condition is re-checked here: a posted answer to a question that
            // was never asked is discarded rather than stored.
            $answers = $this->dropUnaskedAnswers($answers, $byKey);

            $assessmentId = $this->db->insert('beneficiary_assessments', [
                'beneficiary_id'  => $beneficiaryId,
                'assessment_date' => date('Y-m-d'),
                'status'          => $complete ? 'Completed' : 'Draft',
                'answered_count'  => 0,
                'completeness_pct'=> 0,
                'assessed_by'     => $userId,
            ]);

            $answered = 0;
            foreach ($answers as $key => $raw) {
                if (!isset($byKey[$key])) {
                    continue;   // ignore anything not in the questionnaire
                }
                if (is_array($raw)) {
                    // The hidden companion input keeps an all-unticked group in
                    // the payload, so an empty array means "cleared".
                    $raw = array_values(array_filter($raw, fn($v) => $v !== '' && $v !== null));
                    if ($raw === []) {
                        $raw = '';
                    }
                }
                if ($raw === null || $raw === '') {
                    // A cleared answer removes the stored value.
                    $this->db->execute(
                        'DELETE FROM beneficiary_profile_attributes WHERE beneficiary_id = :b AND attribute_key = :k',
                        ['b' => $beneficiaryId, 'k' => $key]);
                    continue;
                }

                $type   = $byKey[$key]['input_type'];
                $text = $number = $bool = $date = null;

                if (in_array($type, ['number', 'decimal'], true)) {
                    $number = is_numeric($raw) ? (float)$raw : null;
                    if ($number === null) {
                        continue;
                    }
                } elseif ($type === 'date') {
                    $time = strtotime((string)$raw);
                    $date = $time ? date('Y-m-d', $time) : null;
                    if ($date === null) {
                        continue;
                    }
                } elseif ($type === 'radio' && in_array((string)$raw, ['Yes', 'No', '1', '0'], true)) {
                    $bool = in_array((string)$raw, ['Yes', '1'], true) ? 1 : 0;
                    $text = $bool ? 'Yes' : 'No';
                } elseif (is_array($raw)) {
                    $text = implode(',', array_map('strval', $raw));
                } else {
                    $text = mb_substr((string)$raw, 0, 500);
                }

                $this->db->insert('beneficiary_assessment_answers', [
                    'assessment_id' => $assessmentId,
                    'beneficiary_id'=> $beneficiaryId,
                    'question_key'  => $key,
                    'value_text'    => $text,
                    'value_number'  => $number,
                    'value_date'    => $date,
                    'value_bool'    => $bool,
                ]);

                if ((int)$byKey[$key]['feeds_eligibility'] === 1) {
                    $this->db->upsert('beneficiary_profile_attributes', [
                        'beneficiary_id' => $beneficiaryId,
                        'attribute_key'  => $key,
                        'value_text'     => $text,
                        'value_number'   => $number,
                        'value_bool'     => $bool,
                        'value_date'     => $date,
                        'source'         => 'assessment',
                    ], ['value_text', 'value_number', 'value_bool', 'value_date', 'source']);
                }
                $answered++;
            }

            $total = max(1, count($byKey));
            $this->db->update('beneficiary_assessments', [
                'answered_count'   => $answered,
                'completeness_pct' => round($answered * 100 / $total, 2),
            ], ['id' => $assessmentId]);

            if ($complete) {
                $this->db->update('beneficiaries', [
                    'assessment_status' => 'Completed',
                    'last_assessed_at'  => date('Y-m-d H:i:s'),
                    'record_status'     => $this->db->scalar(
                        "SELECT IF(record_status = 'Registered', 'Assessed', record_status)
                           FROM beneficiaries WHERE id = :id", ['id' => $beneficiaryId]),
                ], ['id' => $beneficiaryId]);
            } else {
                $this->db->update('beneficiaries', ['assessment_status' => 'In Progress'], ['id' => $beneficiaryId]);
            }

            $this->deriveHouseholdFacts($beneficiaryId);
            $this->deriveRetiredAnswers($beneficiaryId);

            return $assessmentId;
        });
    }

    /**
     * Restate questionnaire answers under the keys of retired questions that
     * scheme rules are still written against.
     *
     * The registration form now asks exactly what the programme questionnaire
     * asks. Several questions the platform carried before were retired, but
     * their attribute keys appear in live scheme rules. Where the
     * questionnaire states the same fact in different words it is mapped
     * across here, so those rules keep resolving instead of silently becoming
     * "cannot tell" for every newly registered beneficiary.
     *
     * Nothing is inferred. A mapping is only made where the answer given
     * settles the question; otherwise the key is left unset and the engine
     * reports it as unknown.
     */
    private function deriveRetiredAnswers(int $beneficiaryId): void
    {
        $rows = $this->db->select(
            "SELECT attribute_key, value_text FROM beneficiary_profile_attributes
              WHERE beneficiary_id = :id
                AND attribute_key IN ('occupation','marital_status','vulnerability_group')",
            ['id' => $beneficiaryId]
        );
        $answer = array_column($rows, 'value_text', 'attribute_key');

        $set = function (string $key, ?string $text, ?int $bool = null) use ($beneficiaryId): void {
            if ($text === null) {
                return;
            }
            $this->db->upsert('beneficiary_profile_attributes', [
                'beneficiary_id' => $beneficiaryId,
                'attribute_key'  => $key,
                'value_text'     => $text,
                'value_number'   => null,
                'value_bool'     => $bool,
                'value_date'     => null,
                'source'         => 'derived',
            ], ['value_text', 'value_number', 'value_bool', 'value_date', 'source']);
        };

        // Marital status is exclusive, so it settles widowhood either way.
        if (!empty($answer['marital_status'])) {
            $isWidow = $answer['marital_status'] === 'WIDOWED';
            $set('widow_status', $isWidow ? 'Yes' : 'No', $isWidow ? 1 : 0);
        }

        // "Tick every group that applies", so a group left unticked on an
        // answered question is a No rather than a silence.
        if (isset($answer['vulnerability_group']) && $answer['vulnerability_group'] !== '') {
            $groups = array_map('trim', explode(',', $answer['vulnerability_group']));
            foreach (['is_orphan' => 'ORPHAN', 'violence_survivor' => 'VIOLENCE'] as $key => $code) {
                $has = in_array($code, $groups, true);
                $set($key, $has ? 'Yes' : 'No', $has ? 1 : 0);
            }
        }

        $occupation = $answer['occupation'] ?? null;
        if (!$occupation) {
            return;
        }

        // "Government Employee in the Family?" - the beneficiary being one
        // proves there is one; any other occupation says nothing about the
        // rest of the household, so it is left unset.
        if ($occupation === 'GOVT_JOB') {
            $set('govt_employee', 'Yes', 1);
        }

        // Occupation is a single choice, so Student settles "currently studying".
        $isStudent = $occupation === 'STUDENT';
        $set('is_student', $isStudent ? 'Yes' : 'No', $isStudent ? 1 : 0);

        $workerCategory = [
            'AGRI_LABOUR'    => 'AGRI',
            'CONSTRUCTION'   => 'BOCW',
            'DOMESTIC'       => 'DOMESTIC',
            'STREET_VENDOR'  => 'VENDOR',
            'ARTISAN'        => 'WEAVER',
            'WEAVER'         => 'WEAVER',
            'DAILY_WAGE'     => 'INFORMAL',
            'UNORGANISED'    => 'INFORMAL',
            'FISHERMAN'      => 'INFORMAL',
            'SELF_EMPLOYED'  => 'SELF_EMP',
            'SMALL_BUSINESS' => 'SELF_EMP',
            'TRADER'         => 'SELF_EMP',
        ][$occupation] ?? null;

        if ($workerCategory !== null) {
            $this->db->upsert('beneficiary_profile_attributes', [
                'beneficiary_id' => $beneficiaryId,
                'attribute_key'  => 'worker_category',
                'value_text'     => $workerCategory,
                'value_number'   => null,
                'value_bool'     => null,
                'value_date'     => null,
                'source'         => 'derived',
            ], ['value_text', 'value_number', 'value_bool', 'value_date', 'source']);
        }

        // Only the questionnaire's own Weaver option establishes this; a
        // general artisan is not necessarily a handloom weaver.
        if ($occupation === 'WEAVER') {
            $this->db->upsert('beneficiary_profile_attributes', [
                'beneficiary_id' => $beneficiaryId,
                'attribute_key'  => 'handloom_weaver',
                'value_text'     => 'Yes',
                'value_number'   => null,
                'value_bool'     => 1,
                'value_date'     => null,
                'source'         => 'derived',
            ], ['value_text', 'value_number', 'value_bool', 'value_date', 'source']);
        }
    }

    /**
     * Keep the household columns the matching engine reads in step with the
     * questionnaire.
     *
     * The registration form no longer has a household block, but scheme rules
     * are written against `annual_income`, `bpl_status` and `ration_card_type`
     * (all sourced from `beneficiary_households`). The questionnaire still
     * carries the same facts in a different shape, so they are derived here
     * rather than left blank, which would silently turn those rules into
     * "cannot tell" for every newly registered beneficiary.
     *
     * Nothing is guessed. Where an answer does not pin the value down, the
     * column is left alone so the engine reports it as unknown instead of
     * asserting something that was never said.
     */
    private function deriveHouseholdFacts(int $beneficiaryId): void
    {
        $rows = $this->db->select(
            "SELECT attribute_key, value_text FROM beneficiary_profile_attributes
              WHERE beneficiary_id = :id
                AND attribute_key IN ('doc_ration_aay','doc_ration_phh')",
            ['id' => $beneficiaryId]
        );
        $answer = array_column($rows, 'value_text', 'attribute_key');
        if ($answer === []) {
            return;
        }

        $update = [];

        /*
         * `annual_income` is deliberately NOT derived.
         *
         * The form now asks only for the earner's own monthly income, and the
         * ten rules that test annual_income are family-income ceilings written
         * as "at or below". One person's income is a lower bound on the
         * household's, not an upper one, so filling the column from it would
         * wrongly qualify households that actually earn more. Leaving it unset
         * makes the engine report those rules as "cannot tell", which is the
         * truthful answer.
         */

        // Ration card: AAY and PHH are the two priority categories under the
        // National Food Security Act, so holding either establishes priority
        // household status. Holding neither proves nothing about income - the
        // household may simply have no card - so it stays Unknown.
        $aay = $answer['doc_ration_aay'] ?? null;
        $phh = $answer['doc_ration_phh'] ?? null;
        if ($aay === 'Yes' || $phh === 'Yes') {
            $update['bpl_status']       = 'Yes';
            $update['ration_card_type'] = $aay === 'Yes' ? 'AAY' : 'PHH';
        }

        if ($update === []) {
            return;
        }

        $exists = $this->db->scalar(
            'SELECT id FROM beneficiary_households WHERE beneficiary_id = :id',
            ['id' => $beneficiaryId]
        );
        if ($exists) {
            $this->db->update('beneficiary_households', $update, ['beneficiary_id' => $beneficiaryId]);
        } else {
            $update['beneficiary_id'] = $beneficiaryId;
            $this->db->insert('beneficiary_households', $update);
        }
    }

    /**
     * Possible duplicates: the same Aadhaar hash, or a very similar name plus
     * mobile or date of birth in the same district.
     */
    public function findDuplicates(?string $aadhaarHash, string $name, ?string $mobile, ?string $dob, int $districtId, ?int $ignoreId = null): array
    {
        $clauses = [];
        $bindings = ['district' => $districtId];

        if ($aadhaarHash) {
            $clauses[] = 'b.aadhaar_hash = :hash';
            $bindings['hash'] = $aadhaarHash;
        }
        if ($mobile) {
            $clauses[] = '(b.mobile = :mobile AND b.name LIKE :name1)';
            $bindings['mobile'] = $mobile;
            $bindings['name1'] = '%' . mb_substr($name, 0, 6) . '%';
        }
        if ($dob) {
            $clauses[] = '(b.dob = :dob AND b.name LIKE :name2 AND b.district_id = :district)';
            $bindings['dob'] = $dob;
            $bindings['name2'] = '%' . mb_substr($name, 0, 6) . '%';
        }
        $clauses[] = '(LOWER(TRIM(b.name)) = :exact AND b.district_id = :district)';
        $bindings['exact'] = mb_strtolower(trim($name));

        $sql = 'SELECT b.id, b.beneficiary_code, b.name, b.gender, b.age, b.mobile,
                       b.registration_date, d.name AS district_name,
                       COALESCE(v.name, b.village_name) AS village_name
                  FROM beneficiaries b
                  LEFT JOIN districts d ON d.id = b.district_id
                  LEFT JOIN villages v  ON v.id = b.village_id
                 WHERE b.deleted_at IS NULL AND (' . implode(' OR ', $clauses) . ')';

        if ($ignoreId) {
            $sql .= ' AND b.id <> :ignore';
            $bindings['ignore'] = $ignoreId;
        }

        return $this->db->select($sql . ' LIMIT 10', $bindings);
    }

    public function saveVisit(array $data): int
    {
        return $this->db->insert('field_visits', $data);
    }

    /** Beneficiaries eligible for bulk matching, within scope. */
    public function pendingMatchIds(array $filters, int $limit = 500): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $rows = $this->db->select(
            "SELECT v.id FROM vw_beneficiary_listing v WHERE {$where}
             ORDER BY v.id LIMIT " . (int)$limit,
            $bindings
        );
        return array_map('intval', array_column($rows, 'id'));
    }
}
