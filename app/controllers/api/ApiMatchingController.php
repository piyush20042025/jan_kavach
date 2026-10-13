<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Audit;
use JanKavach\Core\Controller;
use JanKavach\Core\Scope;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Models\Beneficiary;

/**
 * Scheme matching over the API.
 */
class ApiMatchingController extends Controller
{
    private EligibilityEngine $engine;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->engine = new EligibilityEngine();
    }

    public function matches(string $id): void
    {
        $beneficiary = (new Beneficiary())->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $includeNegative = $this->request->bool('include_not_eligible');
        $matches = (new Beneficiary())->matches((int)$id, !$includeNegative);

        $this->jsonSuccess([
            'beneficiary_id' => (int)$id,
            'last_matched'   => $beneficiary['last_matched_at'],
            'matches'        => array_map(fn($m) => $this->present($m), $matches),
        ]);
    }

    public function run(string $id): void
    {
        $beneficiary = (new Beneficiary())->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $results = $this->engine->matchAndStore((int)$id, true);

        Audit::log('API', 'Matched', [
            'entity_type'  => 'Beneficiary',
            'entity_id'    => (int)$id,
            'entity_label' => $beneficiary['name'],
            'description'  => 'Scheme matching run through the API: ' . count($results) . ' scheme(s) evaluated',
        ]);

        $applicable = array_values(array_filter($results,
            fn($r) => $r['eligibility_status'] !== EligibilityEngine::NOT));

        $this->jsonSuccess([
            'evaluated'  => count($results),
            'applicable' => count($applicable),
            'matches'    => array_map(fn($r) => [
                'scheme_id'   => (int)$r['scheme_id'],
                'scheme'      => $r['scheme_short_name'] ?: $r['scheme_name'],
                'match'       => (float)$r['match_percent'],
                'eligibility' => $r['eligibility_status'],
                'missing_documents' => $r['missing_documents'] ?? [],
                'reason'      => $r['reason_text'],
            ], $applicable),
        ], 'Matching complete.');
    }

    /**
     * Evaluate a supplied profile without creating a beneficiary record -
     * for an on-the-spot eligibility check in the field.
     */
    public function simulate(): void
    {
        $profile = $this->request->input('profile');
        if (!is_array($profile)) {
            $json = $this->request->json();
            $profile = $json['profile'] ?? [];
        }
        if (!is_array($profile) || $profile === []) {
            $this->jsonError('A profile object is required.', null, 422);
        }

        // Only declared attributes are considered.
        $profile = array_intersect_key($profile, $this->engine->attributes());

        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);

        $results = $this->engine->simulate($profile, $stateId, 60);

        $this->jsonSuccess([
            'attributes_used' => array_keys($profile),
            'count'   => count($results),
            'matches' => array_map(fn($r) => [
                'scheme_id'   => (int)$r['scheme_id'],
                'scheme'      => $r['scheme_short_name'] ?: $r['scheme_name'],
                'level'       => $r['scheme_level'],
                'theme'       => $r['theme_name'],
                'match'       => (float)$r['match_percent'],
                'eligibility' => $r['eligibility_status'],
                'reason'      => $r['reason_text'],
            ], $results),
        ]);
    }

    private function present(array $match): array
    {
        return [
            'scheme_id'   => (int)$match['scheme_id'],
            'scheme'      => $match['short_name'] ?: $match['scheme_name'],
            'scheme_name' => $match['scheme_name'],
            'level'       => $match['scheme_level'],
            'theme'       => $match['theme_name'],
            'match'       => (float)$match['match_percent'],
            'eligibility' => $match['eligibility_status'],
            'rules'       => [
                'total'   => (int)$match['rules_total'],
                'passed'  => (int)$match['rules_passed'],
                'failed'  => (int)$match['rules_failed'],
                'unknown' => (int)$match['rules_unknown'],
            ],
            'missing_documents' => json_list($match['missing_documents']),
            'reason'      => $match['reason_text'],
            'passed_reasons'  => json_list($match['passed_reasons']),
            'failed_reasons'  => json_list($match['failed_reasons']),
            'application_id'  => $match['application_id'] ? (int)$match['application_id'] : null,
        ];
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
