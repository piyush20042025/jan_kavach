<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Controller;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Models\Scheme;

/**
 * Scheme endpoints for the future mobile application.
 *
 * These reuse the same models and the same Scope guards as the web
 * controllers, so a mobile client can never see more than the signed-in
 * user's role allows.
 */
class ApiSchemeController extends Controller
{
    private Scheme $schemes;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->schemes = new Scheme();
    }

    public function index(): void
    {
        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);

        $filters = [
            'search'              => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'            => $stateId,
            'scheme_level'        => $this->inOrNull('scheme_level', ['National', 'State', 'National+State']),
            'theme_id'            => $this->request->int('theme_id'),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
            'scheme_status'       => $this->inOrNull('scheme_status', ['Active', 'Dormant', 'Discontinued']),
            'is_active'           => 1,
        ];

        $paginator = $this->schemes->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            min(100, Paginator::perPageFrom($this->request)),
            'v.name', 'ASC'
        );

        $this->jsonSuccess([
            'schemes' => array_map([$this, 'summary'], $paginator->items),
            'meta'    => $paginator->toArray()['meta'],
        ]);
    }

    public function search(): void
    {
        $term = trim((string)$this->request->input('q', ''));
        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);

        $results = $this->schemes->search($term, [
            'state_id'            => $stateId,
            'theme_id'            => $this->request->int('theme_id'),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
        ], 50);

        $this->jsonSuccess([
            'query'   => $term,
            'count'   => count($results),
            'schemes' => array_map([$this, 'summary'], $results),
        ]);
    }

    public function show(string $id): void
    {
        $scheme = $this->schemes->findDetailed((int)$id);
        if (!$scheme) {
            $this->jsonError('Scheme not found.', null, 404);
        }

        $this->jsonSuccess([
            'id'            => (int)$scheme['id'],
            'code'          => $scheme['scheme_code'],
            'name'          => $scheme['name'],
            'short_name'    => $scheme['short_name'],
            'level'         => $scheme['scheme_level'],
            'state'         => $scheme['state_name'],
            'status'        => $scheme['scheme_status'],
            'active_enrollment' => (bool)$scheme['active_enrollment'],
            'themes'        => array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'color' => $t['color']], $scheme['themes']),
            'beneficiary_types' => array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name']], $scheme['beneficiary_types']),
            'target_groups' => array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name']], $scheme['target_groups']),
            'target_group_summary' => $scheme['target_group_summary'],
            'eligibility_summary'  => $scheme['eligibility_summary'],
            'benefits'      => array_map(fn($b) => [
                'type'        => $b['benefit_type'],
                'description' => $b['description'],
                'amount'      => $b['amount'] !== null ? (float)$b['amount'] : null,
                'amount_text' => $b['amount_text'],
                'mode'        => $b['mode'],
                'frequency'   => $b['frequency'],
            ], $scheme['benefits']),
            'how_to_apply'  => [
                'mode'            => $scheme['application_mode'],
                'process'         => $scheme['application_process'],
                'instructions'    => $scheme['application_instructions'],
                'portal_url'      => $scheme['portal_url'],
                'csc_available'   => (bool)$scheme['csc_available'],
                'physical_office' => $scheme['physical_office'],
                'helpline'        => $scheme['helpline_number'],
            ],
            'service_approach' => $scheme['service_approach_name'],
            'priority'      => $scheme['priority_name'],
            'feasibility'   => $scheme['feasibility_name'],
            'documents'     => array_map(fn($d) => [
                'id'        => (int)$d['document_type_id'],
                'name'      => $d['name'],
                'mandatory' => (bool)$d['is_mandatory'],
                'remarks'   => $d['remarks'],
            ], $scheme['documents']),
            'source' => [
                'url'           => $scheme['source_url'],
                'website'       => $scheme['source_website'],
                'last_verified' => $scheme['last_verified_date'],
                'verified_by'   => $scheme['verified_by'],
            ],
            'rule_count' => count($scheme['rules']),
            'updated_at' => $scheme['updated_at'],
        ]);
    }

    /** Rules in a shape a mobile client can evaluate offline. */
    public function rules(string $id): void
    {
        $engine = new EligibilityEngine();
        $rules  = $engine->rulesFor((int)$id);

        $this->jsonSuccess([
            'scheme_id' => (int)$id,
            'rules'     => array_map(fn($r) => [
                'group'       => (int)$r['rule_group'],
                'attribute'   => $r['attribute_key'],
                'label'       => $engine->attributeLabel($r['attribute_key']),
                'operator'    => $r['operator'],
                'value1'      => $r['value1'],
                'value2'      => $r['value2'],
                'value_list'  => $r['value_list'] ? explode(',', $r['value_list']) : [],
                'weight'      => (int)$r['weight'],
                'mandatory'   => (bool)$r['is_mandatory'],
                'description' => $r['description'],
            ], $rules),
            'thresholds' => [
                'eligible'  => (int)(setting('eligible_threshold') ?? 90),
                'potential' => (int)(setting('potential_threshold') ?? 60),
            ],
        ]);
    }

    public function documents(string $id): void
    {
        $this->jsonSuccess([
            'scheme_id' => (int)$id,
            'documents' => array_map(fn($d) => [
                'id'        => (int)$d['document_type_id'],
                'name'      => $d['name'],
                'code'      => $d['code'],
                'category'  => $d['category'],
                'mandatory' => (bool)$d['is_mandatory'],
                'remarks'   => $d['remarks'],
            ], $this->schemes->documents((int)$id)),
        ]);
    }

    private function summary(array $scheme): array
    {
        return [
            'id'          => (int)$scheme['id'],
            'code'        => $scheme['scheme_code'] ?? null,
            'name'        => $scheme['name'],
            'short_name'  => $scheme['short_name'] ?? null,
            'level'       => $scheme['scheme_level'] ?? null,
            'state'       => $scheme['state_name'] ?? null,
            'theme'       => $scheme['theme_name'] ?? null,
            'status'      => $scheme['scheme_status'] ?? null,
            'beneficiary_types' => $scheme['beneficiary_types'] ?? null,
            'updated_at'  => $scheme['updated_at'] ?? null,
        ];
    }
}
