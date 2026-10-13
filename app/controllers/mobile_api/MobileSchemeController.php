<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Models\Scheme;

class MobileSchemeController extends Controller
{
    private Scheme $schemes;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->schemes = new Scheme();
    }

    /**
     * GET /schemes
     * Return the scheme catalogue with pagination.
     */
    public function index(): void
    {
        $page = max(1, $this->request->int('page') ?: 1);

        $perPage = $this->request->int('per_page') ?: 20;
        $perPage = min(100, max(1, $perPage));

        $search = trim((string) $this->request->input('search', ''));

        $where = ['s.deleted_at IS NULL'];
        $bindings = [];

        // Show all non-deleted schemes, including dormant or discontinued
        // schemes. The status is returned so the app can display it.
        if ($search !== '') {
            $where[] = '(
                s.name LIKE :search_name
                OR s.short_name LIKE :search_short
                OR s.scheme_code LIKE :search_code
                OR s.keywords LIKE :search_keywords
            )';

            $term = '%' . $search . '%';

            $bindings = [
                'search_name' => $term,
                'search_short' => $term,
                'search_code' => $term,
                'search_keywords' => $term,
            ];
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS total
             FROM schemes s
             WHERE {$whereSql}",
            $bindings
        );

        $total = (int) ($countRow['total'] ?? 0);

        $items = $this->db->select(
            "SELECT
                s.id,
                s.scheme_code,
                s.name,
                s.short_name,
                s.description,
                s.scheme_level,
                s.state_id,
                st.name AS state_name,
                s.scheme_status,
                s.active_enrollment,
                s.launch_year,
                s.target_group_summary,
                s.eligibility_summary,
                s.benefit_type,
                s.benefit_description,
                s.benefit_amount,
                s.benefit_amount_text,
                s.application_mode,
                s.portal_url,
                s.source_url,
                s.source_website,
                s.last_verified_date,
                s.updated_at,
                th.name AS theme_name,
                th.color AS theme_color,
                th.icon AS theme_icon
             FROM schemes s
             LEFT JOIN states st ON st.id = s.state_id
             LEFT JOIN scheme_themes th ON th.id = s.theme_id
             WHERE {$whereSql}
             ORDER BY s.name ASC, s.id ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        $this->jsonSuccess([
            'items' => array_map(
                static function (array $row): array {
                    return [
                        'id' => (int) $row['id'],
                        'code' => $row['scheme_code'],
                        'name' => $row['name'],
                        'short_name' => $row['short_name'],
                        'description' => $row['description'],
                        'level' => $row['scheme_level'],
                        'state' => [
                            'id' => $row['state_id'] !== null
                                ? (int) $row['state_id']
                                : null,
                            'name' => $row['state_name'],
                        ],
                        'status' => $row['scheme_status'],
                        'active_enrollment' =>
                            (bool) $row['active_enrollment'],
                        'launch_year' => $row['launch_year'] !== null
                            ? (int) $row['launch_year']
                            : null,
                        'theme' => [
                            'name' => $row['theme_name'],
                            'color' => $row['theme_color'],
                            'icon' => $row['theme_icon'],
                        ],
                        'target_group_summary' =>
                            $row['target_group_summary'],
                        'eligibility_summary' =>
                            $row['eligibility_summary'],
                        'benefit' => [
                            'type' => $row['benefit_type'],
                            'description' => $row['benefit_description'],
                            'amount' => $row['benefit_amount'] !== null
                                ? (float) $row['benefit_amount']
                                : null,
                            'amount_text' => $row['benefit_amount_text'],
                        ],
                        'application_mode' => $row['application_mode'],
                        'portal_url' => $row['portal_url'],
                        'source_url' => $row['source_url'],
                        'source_website' => $row['source_website'],
                        'last_verified_date' =>
                            $row['last_verified_date'],
                        'updated_at' => $row['updated_at'],
                    ];
                },
                $items
            ),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total > 0
                    ? (int) ceil($total / $perPage)
                    : 0,
            ],
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
        ], 'Schemes fetched successfully.');
    }

    /**
     * GET /schemes/{id}
     * Return the complete details of one scheme.
     */
    public function show(string $id): void
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            $this->jsonError('Invalid scheme ID.', null, 422);
            return;
        }

        $scheme = $this->schemes->findDetailed((int) $id);

        if (!$scheme) {
            $this->jsonError('Scheme not found.', null, 404);
            return;
        }

        $this->jsonSuccess([
            'id' => (int) $scheme['id'],
            'code' => $scheme['scheme_code'],
            'name' => $scheme['name'],
            'short_name' => $scheme['short_name'],
            'slug' => $scheme['slug'],
            'description' => $scheme['description'],
            'source_url' => $scheme['source_url'],
            'source_website' => $scheme['source_website'],

            'classification' => [
                'level' => $scheme['scheme_level'],
                'status' => $scheme['scheme_status'],
                'is_active' => (bool) $scheme['is_active'],
                'active_enrollment' =>
                    (bool) $scheme['active_enrollment'],
                'launch_year' => $scheme['launch_year'] !== null
                    ? (int) $scheme['launch_year']
                    : null,
            ],

            'geography' => [
                'primary_state' => [
                    'id' => $scheme['state_id'] !== null
                        ? (int) $scheme['state_id']
                        : null,
                    'name' => $scheme['state_name'],
                ],
                'applicable_states' => array_map(
                    static fn(array $state): array => [
                        'id' => (int) $state['id'],
                        'name' => $state['name'],
                        'code' => $state['state_code'],
                    ],
                    $scheme['states']
                ),
            ],

            'themes' => array_map(
                static fn(array $theme): array => [
                    'id' => (int) $theme['id'],
                    'name' => $theme['name'],
                    'code' => $theme['code'],
                    'color' => $theme['color'],
                    'icon' => $theme['icon'],
                    'is_primary' => (bool) $theme['is_primary'],
                ],
                $scheme['themes']
            ),

            'beneficiary_types' => $scheme['beneficiary_types'],
            'target_groups' => $scheme['target_groups'],

            'target_group_summary' => $scheme['target_group_summary'],
            'eligibility_summary' => $scheme['eligibility_summary'],

            'benefits' => array_map(
                static fn(array $benefit): array => [
                    'id' => (int) $benefit['id'],
                    'type' => $benefit['benefit_type'],
                    'description' => $benefit['description'],
                    'amount' => $benefit['amount'] !== null
                        ? (float) $benefit['amount']
                        : null,
                    'amount_text' => $benefit['amount_text'],
                    'mode' => $benefit['mode'],
                    'frequency' => $benefit['frequency'],
                    'conditions' => $benefit['conditions'] ?? null,
                ],
                $scheme['benefits']
            ),

            'application' => [
                'mode' => $scheme['application_mode'],
                'process' => $scheme['application_process'],
                'instructions' => $scheme['application_instructions'],
                'portal_url' => $scheme['portal_url'],
                'source_url' => $scheme['source_url'],
                'csc_available' => (bool) $scheme['csc_available'],
                'physical_office' => $scheme['physical_office'],
                'helpline' => $scheme['helpline_number'],
            ],

            'documents' => array_map(
                static fn(array $document): array => [
                    'id' => (int) $document['document_type_id'],
                    'name' => $document['name'],
                    'code' => $document['code'],
                    'category' => $document['category'],
                    'description' => $document['doc_description'],
                    'mandatory' => (bool) $document['is_mandatory'],
                    'sensitive' => (bool) $document['is_sensitive'],
                    'remarks' => $document['remarks'],
                ],
                $scheme['documents']
            ),

            'eligibility_rules' => array_map(
                static fn(array $rule): array => [
                    'id' => (int) $rule['id'],
                    'group' => (int) $rule['rule_group'],
                    'attribute' => $rule['attribute_key'],
                    'attribute_label' => $rule['attribute_label'],
                    'data_type' => $rule['data_type'],
                    'lookup_key' => $rule['lookup_key'],
                    'operator' => $rule['operator'],
                    'value1' => $rule['value1'],
                    'value2' => $rule['value2'],
                    'value_list' => !empty($rule['value_list'])
                        ? explode(',', $rule['value_list'])
                        : [],
                    'mandatory' => (bool) $rule['is_mandatory'],
                    'weight' => (int) $rule['weight'],
                    'description' => $rule['description'],
                ],
                $scheme['rules']
            ),

            'service_approach' => [
                'id' => $scheme['service_approach_id'] !== null
                    ? (int) $scheme['service_approach_id']
                    : null,
                'name' => $scheme['service_approach_name'],
            ],
            'priority' => [
                'id' => $scheme['priority_id'] !== null
                    ? (int) $scheme['priority_id']
                    : null,
                'name' => $scheme['priority_name'],
                'rank' => $scheme['priority_rank'],
            ],
            'feasibility' => [
                'id' => $scheme['feasibility_id'] !== null
                    ? (int) $scheme['feasibility_id']
                    : null,
                'name' => $scheme['feasibility_name'],
                'rank' => $scheme['feasibility_rank'],
            ],

            'ministry' => $scheme['ministry_agency']
                ?? $scheme['ministry_name'],
            'department' => $scheme['department_agency']
                ?? $scheme['department_name'],

            'source' => [
                'url' => $scheme['source_url'],
                'website' => $scheme['source_website'],
                'last_verified_date' => $scheme['last_verified_date'],
                'verified_by' => $scheme['verified_by'],
            ],

            'keywords' => $scheme['keywords'],
            'created_at' => $scheme['created_at'],
            'updated_at' => $scheme['updated_at'],
        ], 'Scheme details fetched successfully.');
    }
}
