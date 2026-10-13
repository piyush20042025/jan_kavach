<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\BeneficiaryDocument;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;

/**
 * "Find Eligible Schemes" - scheme matching for a beneficiary, in bulk,
 * and as a standalone eligibility check (specification section 20).
 */
class MatchingController extends Controller
{
    private EligibilityEngine $engine;
    private Beneficiary $beneficiaries;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->engine = new EligibilityEngine();
        $this->beneficiaries = new Beneficiary();
    }

    // =================================================================
    // Per-beneficiary matching
    // =================================================================

    public function show(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $matches = $this->beneficiaries->matches((int)$id, false);
        $profile = $this->engine->buildProfile((int)$id);

        $this->view('beneficiaries/matching', [
            'pageTitle'   => 'Eligible Schemes',
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 40), 'url' => '/beneficiaries/' . (int)$id],
                ['label' => 'Eligible Schemes'],
            ],
            'beneficiary' => $beneficiary,
            'matches'     => $matches,
            'summary'     => $this->summarise($matches),
            'profile'     => $this->describeProfile($profile),
            'engine'      => $this->engine,
            'outstanding' => (new BeneficiaryDocument())->outstandingFor((int)$id),
            'lastMatched' => $beneficiary['last_matched_at'] ?? null,
            'assessmentComplete' => $beneficiary['assessment_status'] === 'Completed',
        ]);
    }

    public function run(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $results  = $this->engine->matchAndStore((int)$id, true);
        $positive = count(array_filter($results, fn($r) => $r['eligibility_status'] !== EligibilityEngine::NOT));
        $eligible = count(array_filter($results, fn($r) => $r['eligibility_status'] === EligibilityEngine::ELIGIBLE));

        Audit::log('Matching', 'Matched', [
            'entity_type'  => 'Beneficiary',
            'entity_id'    => (int)$id,
            'entity_label' => $beneficiary['name'],
            'description'  => sprintf('Scheme matching run: %d scheme(s) evaluated, %d applicable, %d eligible.',
                                      count($results), $positive, $eligible),
        ]);

        if ($this->request->isAjax()) {
            $this->jsonSuccess([
                'evaluated' => count($results),
                'applicable'=> $positive,
                'eligible'  => $eligible,
            ], 'Matching complete.');
        }

        Flash::success(sprintf('%d scheme(s) evaluated. %d are applicable to this beneficiary (%d graded Eligible).',
            count($results), $positive, $eligible));
        $this->redirect('/beneficiaries/' . (int)$id . '/matching');
    }

    /** Full reasoning for one beneficiary-scheme pair. */
    public function explain(string $id, string $schemeId): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $match = $this->db->selectOne(
            'SELECT m.*, s.name AS scheme_name, s.short_name, s.scheme_code, s.scheme_level,
                    s.benefit_description, s.application_process, s.portal_url, s.helpline_number,
                    th.name AS theme_name, th.color AS theme_color
               FROM beneficiary_scheme_matches m
               JOIN schemes s ON s.id = m.scheme_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
              WHERE m.beneficiary_id = :b AND m.scheme_id = :s',
            ['b' => (int)$id, 's' => (int)$schemeId]
        );

        // Not matched yet: evaluate live so the page can still explain.
        if (!$match) {
            $profile = $this->engine->buildProfile((int)$id);
            $result  = $this->engine->evaluateScheme($profile, (int)$schemeId);
            $scheme  = $this->db->selectOne(
                'SELECT s.*, th.name AS theme_name, th.color AS theme_color
                   FROM schemes s LEFT JOIN scheme_themes th ON th.id = s.theme_id
                  WHERE s.id = :id AND s.deleted_at IS NULL', ['id' => (int)$schemeId]);
            $this->findOrFail($scheme, 'Scheme');

            $match = array_merge($result, [
                'scheme_name'       => $scheme['name'],
                'short_name'        => $scheme['short_name'],
                'scheme_code'       => $scheme['scheme_code'],
                'scheme_level'      => $scheme['scheme_level'],
                'theme_name'        => $scheme['theme_name'],
                'theme_color'       => $scheme['theme_color'],
                'benefit_description' => $scheme['benefit_description'],
                'application_process' => $scheme['application_process'],
                'portal_url'        => $scheme['portal_url'],
                'helpline_number'   => $scheme['helpline_number'],
                'passed_reasons'    => json_encode($result['passed_reasons']),
                'failed_reasons'    => json_encode($result['failed_reasons']),
                'unknown_reasons'   => json_encode($result['unknown_reasons']),
                'missing_documents' => json_encode($this->engine->missingDocuments((int)$id, (int)$schemeId)),
                'matched_at'        => null,
            ]);
        }

        $this->view('beneficiaries/match-explain', [
            'pageTitle'   => 'Why this scheme matched',
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 30), 'url' => '/beneficiaries/' . (int)$id],
                ['label' => 'Eligible Schemes', 'url' => '/beneficiaries/' . (int)$id . '/matching'],
                ['label' => 'Explanation'],
            ],
            'beneficiary' => $beneficiary,
            'match'       => $match,
            'rules'       => $this->engine->rulesFor((int)$schemeId),
            'engine'      => $this->engine,
        ]);
    }

    // =================================================================
    // Bulk matching workbench
    // =================================================================

    public function index(): void
    {
        $filters = $this->matchFilters();
        [$column, $dir, $sortKey] = $this->sort(Beneficiary::SORTS, 'registered', 'DESC');

        $paginator = $this->beneficiaries->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $geo = new Geography();

        $this->view('beneficiaries/matching-index', [
            'pageTitle'   => 'Scheme Matching',
            'breadcrumbs' => [['label' => 'Beneficiaries', 'url' => '/beneficiaries'], ['label' => 'Scheme Matching']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'states'      => $geo->stateOptions(),
            'districts'   => $geo->districtOptions($filters['state_id'] ?? null),
            'stats'       => $this->matchingStats($filters),
        ]);
    }

    public function runBulk(): void
    {
        $filters = $this->matchFilters();
        $limit   = min(500, max(1, $this->request->int('limit', 200) ?? 200));

        $ids = $this->beneficiaries->pendingMatchIds($filters, $limit);

        if ($ids === []) {
            Flash::warning('No beneficiaries matched the current filters, so nothing was processed.');
            $this->back('/matching');
        }

        $summary = ['processed' => 0, 'applicable' => 0, 'eligible' => 0, 'failed' => 0];

        foreach ($ids as $beneficiaryId) {
            try {
                $results = $this->engine->matchAndStore($beneficiaryId, true);
                $summary['processed']++;
                $summary['applicable'] += count(array_filter($results,
                    fn($r) => $r['eligibility_status'] !== EligibilityEngine::NOT));
                $summary['eligible'] += count(array_filter($results,
                    fn($r) => $r['eligibility_status'] === EligibilityEngine::ELIGIBLE));
            } catch (\Throwable $e) {
                // One bad record must not stop the batch.
                $summary['failed']++;
                \JanKavach\Core\Logger::error('Bulk matching failed for a beneficiary', [
                    'beneficiary_id' => $beneficiaryId, 'error' => $e->getMessage(),
                ]);
            }
        }

        Audit::log('Matching', 'Matched', [
            'entity_type' => 'Beneficiary',
            'description' => sprintf('Bulk scheme matching: %d beneficiary record(s) processed, %d scheme match(es) recorded, %d failed.',
                                     $summary['processed'], $summary['applicable'], $summary['failed']),
            'new_values'  => $summary,
        ]);

        Flash::success(sprintf('%d beneficiary record(s) processed. %d scheme match(es) recorded, of which %d are graded Eligible.%s',
            $summary['processed'], $summary['applicable'], $summary['eligible'],
            $summary['failed'] > 0 ? ' ' . $summary['failed'] . ' record(s) could not be processed - see the application log.' : ''));

        $this->back('/matching');
    }

    // =================================================================
    // Standalone eligibility check (no beneficiary record needed)
    // =================================================================

    public function quickCheck(): void
    {
        $this->view('beneficiaries/quick-check', [
            'pageTitle'   => 'Check Scheme Eligibility',
            'breadcrumbs' => [['label' => 'Scheme Matching', 'url' => '/matching'], ['label' => 'Eligibility Check']],
            'attributes'  => $this->engine->attributesByGroup(),
            'lookups'     => $this->lookupPayload(),
            'states'      => (new Geography())->stateOptions(),
            'results'     => null,
            'submitted'   => [],
        ]);
    }

    public function runQuickCheck(): void
    {
        $raw = $_POST['profile'] ?? [];
        $profile = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (!is_string($key) || is_array($value)) {
                    continue;
                }
                $value = trim((string)$value);
                if ($value !== '') {
                    $profile[$key] = $value;
                }
            }
        }

        // Only attributes the engine knows about are considered.
        $known = $this->engine->attributes();
        $profile = array_intersect_key($profile, $known);

        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);

        $results = $this->engine->simulate($profile, $stateId, 60);

        Audit::log('Matching', 'Matched', [
            'description' => sprintf('Standalone eligibility check run against %d attribute(s): %d applicable scheme(s) found.',
                                     count($profile), count($results)),
        ]);

        $this->view('beneficiaries/quick-check', [
            'pageTitle'   => 'Check Scheme Eligibility',
            'breadcrumbs' => [['label' => 'Scheme Matching', 'url' => '/matching'], ['label' => 'Eligibility Check']],
            'attributes'  => $this->engine->attributesByGroup(),
            'lookups'     => $this->lookupPayload(),
            'states'      => (new Geography())->stateOptions(),
            'results'     => $results,
            'submitted'   => $profile,
            'checkState'  => $stateId,
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function matchFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'assessment_status' => $this->inOrNull('assessment_status', ['Pending', 'In Progress', 'Completed']),
            'record_status'     => $this->inOrNull('record_status',
                                    ['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited', 'Closed', 'Rejected']),
            'field_user_id'     => $this->request->int('field_user_id'),
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
        ];
    }

    private function matchingStats(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = 'b.state_id = :s_state';
            $bindings['s_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'b.district_id = :s_district';
            $bindings['s_district'] = (int)$filters['district_id'];
        }
        $whereSql = implode(' AND ', $where);

        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(b.assessment_status = 'Completed') AS assessed,
                    SUM(b.last_matched_at IS NOT NULL) AS matched,
                    SUM(b.assessment_status = 'Completed' AND b.last_matched_at IS NULL) AS awaiting
               FROM beneficiaries b WHERE {$whereSql}",
            $bindings
        ) ?: [];

        $matchCounts = $this->db->select(
            "SELECT m.eligibility_status, COUNT(*) AS n
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE {$whereSql} GROUP BY m.eligibility_status",
            $bindings
        );

        $byStatus = [];
        foreach ($matchCounts as $c) {
            $byStatus[$c['eligibility_status']] = (int)$c['n'];
        }

        return [
            'total'    => (int)($row['total'] ?? 0),
            'assessed' => (int)($row['assessed'] ?? 0),
            'matched'  => (int)($row['matched'] ?? 0),
            'awaiting' => (int)($row['awaiting'] ?? 0),
            'by_status'=> $byStatus,
        ];
    }

    private function summarise(array $matches): array
    {
        $summary = [
            EligibilityEngine::ELIGIBLE  => 0,
            EligibilityEngine::POTENTIAL => 0,
            EligibilityEngine::VERIFY    => 0,
            EligibilityEngine::NOT       => 0,
            'missing_documents'          => 0,
        ];
        foreach ($matches as $match) {
            $status = $match['eligibility_status'];
            $summary[$status] = ($summary[$status] ?? 0) + 1;
            if ($status !== EligibilityEngine::NOT) {
                $summary['missing_documents'] += (int)$match['missing_doc_count'];
            }
        }
        return $summary;
    }

    /** Readable view of the profile the engine used, for transparency. */
    private function describeProfile(array $profile): array
    {
        $described = [];
        foreach ($this->engine->attributes() as $key => $attr) {
            if (!array_key_exists($key, $profile)) {
                continue;
            }
            $described[$attr['group_label'] ?: 'General'][] = [
                'label' => $attr['label'],
                'value' => $this->engine->displayValue($key, $profile[$key]),
                'key'   => $key,
            ];
        }
        return $described;
    }

    private function lookupPayload(): array
    {
        $masters = new Master();
        $payload = [];
        foreach ($this->engine->attributes() as $attr) {
            if (!empty($attr['lookup_key']) && !isset($payload[$attr['lookup_key']])) {
                $payload[$attr['lookup_key']] = $masters->lookup($attr['lookup_key']);
            }
        }
        return $payload;
    }

    private function assertAccess(array $beneficiary): void
    {
        Scope::assertAccess(
            (int)$beneficiary['state_id'],
            (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null
        );
    }
}
