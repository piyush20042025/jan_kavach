<?php
namespace JanKavach\Models;

use JanKavach\Core\Database;
use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;

/**
 * Scheme master (specification sections 6-15).
 */
class Scheme extends Model
{
    protected string $table = 'schemes';
    protected bool $softDelete = true;

    protected array $fillable = [
        'scheme_code', 'name', 'short_name', 'slug', 'description',
        'scheme_level', 'state_id', 'theme_id', 'service_approach_id',
        'priority_id', 'feasibility_id', 'ministry_id', 'department_id',
        'ministry_name', 'department_name', 'scheme_status', 'active_enrollment',
        'launch_year', 'target_group_summary', 'eligibility_summary',
        'benefit_type', 'benefit_description', 'benefit_amount', 'benefit_amount_text',
        'benefit_mode', 'benefit_frequency', 'benefit_conditions',
        'application_mode', 'application_process', 'application_instructions',
        'portal_url', 'csc_available', 'physical_office', 'helpline_number',
        'source_url', 'source_website', 'last_verified_date', 'verified_by',
        'keywords', 'is_active', 'source_row_no',
    ];

    /** Sortable columns, whitelisted so the request can never inject SQL. */
    public const SORTS = [
        'code'        => 'v.scheme_code',
        'name'        => 'v.name',
        'level'       => 'v.scheme_level',
        'state'       => 'v.state_name',
        'theme'       => 'v.theme_name',
        'priority'    => 'v.priority_rank',
        'feasibility' => 'v.feasibility_rank',
        'status'      => 'v.scheme_status',
        'updated'     => 'v.updated_at',
        'documents'   => 'v.document_count',
        'rules'       => 'v.rule_count',
    ];

    // =================================================================
    // Listing
    // =================================================================

    /**
     * Filtered, scoped, paginated scheme list backed by vw_scheme_listing.
     */
    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $select = 'SELECT v.* FROM vw_scheme_listing v';
        $count  = 'FROM vw_scheme_listing v';

        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC');
        // Keep NULL priority/feasibility at the end rather than the top.
        if (str_contains($sortColumn, 'priority_rank') || str_contains($sortColumn, 'feasibility_rank')) {
            $order = "({$sortColumn} IS NULL), " . $order;
        }
        $order .= ', v.id ASC';

        return $this->paginateQuery($select, $count, $where, $bindings, $order, $page, $perPage, $queryString);
    }

    /** Every matching row (used by exports). */
    public function allFiltered(array $filters, string $sortColumn = 'v.name', string $sortDir = 'ASC', int $limit = 20000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC');

        return $this->db->select(
            "SELECT v.* FROM vw_scheme_listing v WHERE {$where} ORDER BY {$order} LIMIT " . (int)$limit,
            $bindings
        );
    }

    /**
     * Build the WHERE body from the filter set, always AND-ed with the
     * user's geographic scheme scope.
     */
    private function buildFilters(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('v');
        $where = [$scopeSql];

        if (!empty($filters['search'])) {
            $where[] = '(v.name LIKE :search OR v.short_name LIKE :search2 OR v.scheme_code LIKE :search3)';
            $like = '%' . $filters['search'] . '%';
            $bindings['search'] = $like;
            $bindings['search2'] = $like;
            $bindings['search3'] = $like;
        }
        if (!empty($filters['state_id'])) {
            // A State filter should still surface the national catalogue,
            // because those schemes apply in that State too.
            $where[] = "(v.state_id = :f_state OR v.scheme_level = 'National'
                         OR EXISTS (SELECT 1 FROM scheme_states ss WHERE ss.scheme_id = v.id AND ss.state_id = :f_state2))";
            $bindings['f_state']  = (int)$filters['state_id'];
            $bindings['f_state2'] = (int)$filters['state_id'];
        }
        if (!empty($filters['scheme_level'])) {
            $where[] = 'v.scheme_level = :f_level';
            $bindings['f_level'] = $filters['scheme_level'];
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_theme_map stm WHERE stm.scheme_id = v.id AND stm.theme_id = :f_theme)';
            $bindings['f_theme'] = (int)$filters['theme_id'];
        }
        if (!empty($filters['beneficiary_type_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_beneficiary_types sbt WHERE sbt.scheme_id = v.id AND sbt.beneficiary_type_id = :f_btype)';
            $bindings['f_btype'] = (int)$filters['beneficiary_type_id'];
        }
        if (!empty($filters['target_group_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_target_groups stg WHERE stg.scheme_id = v.id AND stg.target_group_id = :f_tgroup)';
            $bindings['f_tgroup'] = (int)$filters['target_group_id'];
        }
        if (!empty($filters['priority_id'])) {
            $where[] = 'v.priority_id = :f_priority';
            $bindings['f_priority'] = (int)$filters['priority_id'];
        }
        if (!empty($filters['feasibility_id'])) {
            $where[] = 'v.feasibility_id = :f_feas';
            $bindings['f_feas'] = (int)$filters['feasibility_id'];
        }
        if (!empty($filters['service_approach_id'])) {
            $where[] = 'v.service_approach_id = :f_sa';
            $bindings['f_sa'] = (int)$filters['service_approach_id'];
        }
        if (!empty($filters['scheme_status'])) {
            $where[] = 'v.scheme_status = :f_status';
            $bindings['f_status'] = $filters['scheme_status'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $where[] = 'v.is_active = :f_active';
            $bindings['f_active'] = (int)$filters['is_active'];
        }
        if (isset($filters['active_enrollment']) && $filters['active_enrollment'] !== '' && $filters['active_enrollment'] !== null) {
            $where[] = 'v.active_enrollment = :f_enroll';
            $bindings['f_enroll'] = (int)$filters['active_enrollment'];
        }
        if (!empty($filters['has_rules'])) {
            $where[] = $filters['has_rules'] === 'yes' ? 'v.rule_count > 0' : 'v.rule_count = 0';
        }

        return [implode(' AND ', $where), $bindings];
    }

    // =================================================================
    // Single scheme
    // =================================================================

    /** Full scheme record with every related master resolved. */
    public function findDetailed(int $id): ?array
    {
        $scheme = $this->db->selectOne(
            'SELECT s.*,
                    st.name  AS state_name,
                    th.name  AS theme_name,  th.color AS theme_color, th.icon AS theme_icon,
                    sa.name  AS service_approach_name, sa.color AS service_approach_color,
                    pl.name  AS priority_name, pl.rank_value AS priority_rank, pl.color AS priority_color,
                    fl.name  AS feasibility_name, fl.rank_value AS feasibility_rank, fl.color AS feasibility_color,
                    mi.name  AS ministry_agency, dp.name AS department_agency,
                    cu.full_name AS created_by_name, uu.full_name AS updated_by_name
               FROM schemes s
               LEFT JOIN states st              ON st.id = s.state_id
               LEFT JOIN scheme_themes th       ON th.id = s.theme_id
               LEFT JOIN service_approaches sa  ON sa.id = s.service_approach_id
               LEFT JOIN priority_levels pl     ON pl.id = s.priority_id
               LEFT JOIN feasibility_levels fl  ON fl.id = s.feasibility_id
               LEFT JOIN implementing_agencies mi ON mi.id = s.ministry_id
               LEFT JOIN implementing_agencies dp ON dp.id = s.department_id
               LEFT JOIN users cu               ON cu.id = s.created_by
               LEFT JOIN users uu               ON uu.id = s.updated_by
              WHERE s.id = :id AND s.deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$scheme) {
            return null;
        }

        $scheme['themes']            = $this->themes($id);
        $scheme['beneficiary_types'] = $this->beneficiaryTypes($id);
        $scheme['target_groups']     = $this->targetGroups($id);
        $scheme['states']            = $this->states($id);
        $scheme['documents']         = $this->documents($id);
        $scheme['benefits']          = $this->benefits($id);
        $scheme['rules']             = $this->rules($id);

        return $scheme;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT id FROM schemes WHERE slug = :slug AND deleted_at IS NULL LIMIT 1',
            ['slug' => $slug]
        );
    }

    public function themes(int $schemeId): array
    {
        return $this->db->select(
            'SELECT th.id, th.name, th.code, th.color, th.icon, stm.is_primary
               FROM scheme_theme_map stm
               JOIN scheme_themes th ON th.id = stm.theme_id
              WHERE stm.scheme_id = :id
              ORDER BY stm.is_primary DESC, th.sort_order',
            ['id' => $schemeId]
        );
    }

    public function beneficiaryTypes(int $schemeId): array
    {
        return $this->db->select(
            'SELECT bt.id, bt.name, bt.code, bt.icon
               FROM scheme_beneficiary_types sbt
               JOIN beneficiary_types bt ON bt.id = sbt.beneficiary_type_id
              WHERE sbt.scheme_id = :id ORDER BY bt.sort_order',
            ['id' => $schemeId]
        );
    }

    public function targetGroups(int $schemeId): array
    {
        return $this->db->select(
            'SELECT tg.id, tg.name, tg.code
               FROM scheme_target_groups stg
               JOIN target_groups tg ON tg.id = stg.target_group_id
              WHERE stg.scheme_id = :id ORDER BY tg.sort_order',
            ['id' => $schemeId]
        );
    }

    public function states(int $schemeId): array
    {
        return $this->db->select(
            'SELECT st.id, st.name, st.state_code
               FROM scheme_states ss JOIN states st ON st.id = ss.state_id
              WHERE ss.scheme_id = :id ORDER BY st.name',
            ['id' => $schemeId]
        );
    }

    public function documents(int $schemeId): array
    {
        return $this->db->select(
            'SELECT sd.*, dt.name, dt.code, dt.category, dt.description AS doc_description,
                    dt.is_sensitive
               FROM scheme_documents sd
               JOIN document_types dt ON dt.id = sd.document_type_id
              WHERE sd.scheme_id = :id
              ORDER BY sd.is_mandatory DESC, sd.sort_order, dt.name',
            ['id' => $schemeId]
        );
    }

    public function benefits(int $schemeId): array
    {
        return $this->db->select(
            'SELECT * FROM scheme_benefits WHERE scheme_id = :id ORDER BY sort_order, id',
            ['id' => $schemeId]
        );
    }

    public function rules(int $schemeId): array
    {
        return $this->db->select(
            'SELECT r.*, ea.label AS attribute_label, ea.data_type, ea.lookup_key,
                    ea.unit, ea.group_label
               FROM scheme_eligibility_rules r
               LEFT JOIN eligibility_attributes ea ON ea.attribute_key = r.attribute_key
              WHERE r.scheme_id = :id
              ORDER BY r.rule_group, r.sort_order, r.id',
            ['id' => $schemeId]
        );
    }

    /** Schemes sharing a theme, for the "Related Schemes" panel. */
    public function related(int $schemeId, ?int $themeId, ?int $stateId, int $limit = 6): array
    {
        if (!$themeId) {
            return [];
        }
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $bindings['id'] = $schemeId;
        $bindings['theme'] = $themeId;

        return $this->db->select(
            "SELECT s.id, s.name, s.short_name, s.scheme_level, th.name AS theme_name, th.color AS theme_color,
                    st.name AS state_name
               FROM schemes s
               JOIN scheme_theme_map stm ON stm.scheme_id = s.id AND stm.theme_id = :theme
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
               LEFT JOIN states st ON st.id = s.state_id
              WHERE s.id <> :id AND s.deleted_at IS NULL AND s.is_active = 1 AND {$scopeSql}
              ORDER BY s.scheme_level, s.name
              LIMIT " . (int)$limit,
            $bindings
        );
    }

    // =================================================================
    // Global search (specification section 34)
    // =================================================================

    public function search(string $term, array $filters = [], int $limit = 40): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL', 's.is_active = 1'];

        $term = trim($term);
        if ($term !== '') {
            // Search the name, acronym, themes, beneficiary types and the
            // target-group text, so "Senior Citizen" finds pension schemes.
            $where[] = '(s.name LIKE :t1 OR s.short_name LIKE :t2 OR s.keywords LIKE :t3
                         OR s.target_group_summary LIKE :t4 OR s.eligibility_summary LIKE :t5
                         OR s.benefit_description LIKE :t6
                         OR EXISTS (SELECT 1 FROM scheme_theme_map m JOIN scheme_themes th ON th.id = m.theme_id
                                     WHERE m.scheme_id = s.id AND th.name LIKE :t7)
                         OR EXISTS (SELECT 1 FROM scheme_beneficiary_types b JOIN beneficiary_types bt ON bt.id = b.beneficiary_type_id
                                     WHERE b.scheme_id = s.id AND bt.name LIKE :t8)
                         OR EXISTS (SELECT 1 FROM scheme_target_groups g JOIN target_groups tg ON tg.id = g.target_group_id
                                     WHERE g.scheme_id = s.id AND tg.name LIKE :t9))';
            $like = '%' . $term . '%';
            for ($i = 1; $i <= 9; $i++) {
                $bindings['t' . $i] = $like;
            }
        }

        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :s_state OR s.scheme_level = 'National')";
            $bindings['s_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_theme_map m WHERE m.scheme_id = s.id AND m.theme_id = :s_theme)';
            $bindings['s_theme'] = (int)$filters['theme_id'];
        }
        if (!empty($filters['beneficiary_type_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_beneficiary_types b WHERE b.scheme_id = s.id AND b.beneficiary_type_id = :s_btype)';
            $bindings['s_btype'] = (int)$filters['beneficiary_type_id'];
        }

        $sql = 'SELECT s.id, s.scheme_code, s.name, s.short_name, s.scheme_level, s.scheme_status,
                       s.target_group_summary, s.benefit_description,
                       th.name AS theme_name, th.color AS theme_color,
                       st.name AS state_name,
                       pl.rank_value AS priority_rank,
                       (SELECT GROUP_CONCAT(bt.name ORDER BY bt.sort_order SEPARATOR ", ")
                          FROM scheme_beneficiary_types sbt JOIN beneficiary_types bt ON bt.id = sbt.beneficiary_type_id
                         WHERE sbt.scheme_id = s.id) AS beneficiary_types
                  FROM schemes s
                  LEFT JOIN scheme_themes th   ON th.id = s.theme_id
                  LEFT JOIN states st          ON st.id = s.state_id
                  LEFT JOIN priority_levels pl ON pl.id = s.priority_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY (s.scheme_level = "National") DESC, s.name
                 LIMIT ' . (int)$limit;

        return $this->db->select($sql, $bindings);
    }

    // =================================================================
    // Writes
    // =================================================================

    public function nextSchemeCode(): string
    {
        return $this->nextCode('scheme_code', 'JK-SCH-', 4);
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = slugify($name, 280) ?: 'scheme';
        $slug = $base;
        $n = 2;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM schemes WHERE slug = :slug';
            $bindings = ['slug' => $slug];
            if ($ignoreId) {
                $sql .= ' AND id <> :id';
                $bindings['id'] = $ignoreId;
            }
            if ((int)$this->db->scalar($sql, $bindings) === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /** Replace the scheme's theme / beneficiary type / target group / state links. */
    public function syncRelations(int $schemeId, array $themeIds, array $beneficiaryTypeIds, array $targetGroupIds, array $stateIds): void
    {
        $this->db->execute('DELETE FROM scheme_theme_map WHERE scheme_id = :id', ['id' => $schemeId]);
        foreach (array_values(array_unique(array_map('intval', $themeIds))) as $i => $themeId) {
            if ($themeId <= 0) continue;
            $this->db->insert('scheme_theme_map', [
                'scheme_id' => $schemeId, 'theme_id' => $themeId, 'is_primary' => $i === 0 ? 1 : 0,
            ]);
        }

        $this->db->execute('DELETE FROM scheme_beneficiary_types WHERE scheme_id = :id', ['id' => $schemeId]);
        foreach (array_unique(array_map('intval', $beneficiaryTypeIds)) as $typeId) {
            if ($typeId <= 0) continue;
            $this->db->insert('scheme_beneficiary_types', ['scheme_id' => $schemeId, 'beneficiary_type_id' => $typeId]);
        }

        $this->db->execute('DELETE FROM scheme_target_groups WHERE scheme_id = :id', ['id' => $schemeId]);
        foreach (array_unique(array_map('intval', $targetGroupIds)) as $groupId) {
            if ($groupId <= 0) continue;
            $this->db->insert('scheme_target_groups', ['scheme_id' => $schemeId, 'target_group_id' => $groupId]);
        }

        $this->db->execute('DELETE FROM scheme_states WHERE scheme_id = :id', ['id' => $schemeId]);
        foreach (array_unique(array_map('intval', $stateIds)) as $stateId) {
            if ($stateId <= 0) continue;
            $this->db->insert('scheme_states', ['scheme_id' => $schemeId, 'state_id' => $stateId]);
        }
    }

    public function syncDocuments(int $schemeId, array $documents): void
    {
        $this->db->execute('DELETE FROM scheme_documents WHERE scheme_id = :id', ['id' => $schemeId]);
        $order = 0;
        foreach ($documents as $doc) {
            $typeId = (int)($doc['document_type_id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            $this->db->insert('scheme_documents', [
                'scheme_id'         => $schemeId,
                'document_type_id'  => $typeId,
                'is_mandatory'      => !empty($doc['is_mandatory']) ? 1 : 0,
                'validity_required' => !empty($doc['validity_required']) ? 1 : 0,
                'remarks'           => $doc['remarks'] ?? null,
                'sort_order'        => ++$order,
            ]);
        }
    }

    public function syncBenefits(int $schemeId, array $benefits): void
    {
        $this->db->execute('DELETE FROM scheme_benefits WHERE scheme_id = :id', ['id' => $schemeId]);
        $order = 0;
        foreach ($benefits as $benefit) {
            if (empty($benefit['description']) && empty($benefit['amount']) && empty($benefit['benefit_type'])) {
                continue;
            }
            $this->db->insert('scheme_benefits', [
                'scheme_id'    => $schemeId,
                'benefit_type' => $benefit['benefit_type'] ?? null,
                'description'  => $benefit['description'] ?? null,
                'amount'       => ($benefit['amount'] ?? '') !== '' ? (float)$benefit['amount'] : null,
                'amount_text'  => $benefit['amount_text'] ?? null,
                'mode'         => $benefit['mode'] ?? null,
                'frequency'    => $benefit['frequency'] ?? null,
                'conditions'   => $benefit['conditions'] ?? null,
                'sort_order'   => ++$order,
            ]);
        }
    }

    /** Replace every eligibility rule for a scheme. */
    public function syncRules(int $schemeId, array $rules, ?int $userId = null): int
    {
        $this->db->execute('DELETE FROM scheme_eligibility_rules WHERE scheme_id = :id', ['id' => $schemeId]);

        $saved = 0;
        $order = 0;
        foreach ($rules as $rule) {
            $attribute = trim((string)($rule['attribute_key'] ?? ''));
            $operator  = trim((string)($rule['operator'] ?? ''));
            if ($attribute === '' || $operator === '') {
                continue;
            }
            $list = $rule['value_list'] ?? null;
            if (is_array($list)) {
                $list = implode(',', array_filter(array_map('trim', $list), fn($v) => $v !== ''));
            }

            $this->db->insert('scheme_eligibility_rules', [
                'scheme_id'     => $schemeId,
                'rule_group'    => max(1, (int)($rule['rule_group'] ?? 1)),
                'attribute_key' => $attribute,
                'operator'      => $operator,
                'value1'        => ($rule['value1'] ?? '') !== '' ? (string)$rule['value1'] : null,
                'value2'        => ($rule['value2'] ?? '') !== '' ? (string)$rule['value2'] : null,
                'value_list'    => ($list ?? '') !== '' ? $list : null,
                'weight'        => min(255, max(1, (int)($rule['weight'] ?? 1))),
                'is_mandatory'  => !empty($rule['is_mandatory']) ? 1 : 0,
                'description'   => ($rule['description'] ?? '') !== '' ? mb_substr((string)$rule['description'], 0, 500) : null,
                'sort_order'    => ++$order,
                'is_active'     => 1,
                'created_by'    => $userId,
            ]);
            $saved++;
        }
        return $saved;
    }

    /**
     * Possible duplicates of a scheme, matched on code, exact name and a
     * normalised name within the same level/State (section 38).
     */
    public function findDuplicates(string $name, ?string $code, string $level, ?int $stateId, ?int $ignoreId = null): array
    {
        $normalised = $this->normaliseName($name);

        $sql = "SELECT id, scheme_code, name, short_name, scheme_level, state_id,
                       (SELECT st.name FROM states st WHERE st.id = s.state_id) AS state_name
                  FROM schemes s
                 WHERE s.deleted_at IS NULL
                   AND (
                        (:code <> '' AND s.scheme_code = :code2)
                     OR LOWER(TRIM(s.name)) = :exact
                     OR (
                          s.scheme_level = :level
                          AND (:state_id IS NULL OR s.state_id <=> :state_id2)
                          AND LOWER(REGEXP_REPLACE(s.name, '[^a-zA-Z0-9]', '')) = :normalised
                        )
                   )";
        $bindings = [
            'code'       => (string)($code ?? ''),
            'code2'      => (string)($code ?? ''),
            'exact'      => mb_strtolower(trim($name)),
            'level'      => $level,
            'state_id'   => $stateId,
            'state_id2'  => $stateId,
            'normalised' => $normalised,
        ];

        if ($ignoreId) {
            $sql .= ' AND s.id <> :ignore';
            $bindings['ignore'] = $ignoreId;
        }

        try {
            return $this->db->select($sql . ' LIMIT 10', $bindings);
        } catch (\Throwable $e) {
            // REGEXP_REPLACE needs MySQL 8 / MariaDB 10.0+. Fall back to the
            // two comparisons every server supports.
            $sql = "SELECT id, scheme_code, name, short_name, scheme_level, state_id,
                           (SELECT st.name FROM states st WHERE st.id = s.state_id) AS state_name
                      FROM schemes s
                     WHERE s.deleted_at IS NULL
                       AND ((:code <> '' AND s.scheme_code = :code2) OR LOWER(TRIM(s.name)) = :exact)";
            $fallback = ['code' => (string)($code ?? ''), 'code2' => (string)($code ?? ''),
                         'exact' => mb_strtolower(trim($name))];
            if ($ignoreId) {
                $sql .= ' AND s.id <> :ignore';
                $fallback['ignore'] = $ignoreId;
            }
            return $this->db->select($sql . ' LIMIT 10', $fallback);
        }
    }

    private function normaliseName(string $name): string
    {
        return mb_strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?: '');
    }

    /** Duplicate a scheme with all of its relations. */
    public function duplicate(int $schemeId, ?int $userId = null): ?int
    {
        $original = $this->find($schemeId);
        if (!$original) {
            return null;
        }

        return $this->db->transaction(function () use ($original, $schemeId, $userId) {
            $data = $original;
            unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at'], $data['view_count']);

            $data['name']        = mb_substr('Copy of ' . $original['name'], 0, 300);
            $data['scheme_code'] = $this->nextSchemeCode();
            $data['slug']        = $this->uniqueSlug($data['name']);
            $data['is_active']   = 0;             // a copy starts inactive
            $data['created_by']  = $userId;
            $data['updated_by']  = $userId;

            $newId = $this->db->insert('schemes', $data);

            foreach (['scheme_theme_map' => ['theme_id', 'is_primary'],
                      'scheme_beneficiary_types' => ['beneficiary_type_id'],
                      'scheme_target_groups' => ['target_group_id'],
                      'scheme_states' => ['state_id']] as $table => $columns) {
                $rows = $this->db->select("SELECT * FROM `{$table}` WHERE scheme_id = :id", ['id' => $schemeId]);
                foreach ($rows as $row) {
                    $insert = ['scheme_id' => $newId];
                    foreach ($columns as $column) {
                        $insert[$column] = $row[$column];
                    }
                    $this->db->insert($table, $insert);
                }
            }

            foreach (['scheme_documents', 'scheme_benefits', 'scheme_eligibility_rules'] as $table) {
                $rows = $this->db->select("SELECT * FROM `{$table}` WHERE scheme_id = :id", ['id' => $schemeId]);
                foreach ($rows as $row) {
                    unset($row['id'], $row['created_at'], $row['updated_at']);
                    $row['scheme_id'] = $newId;
                    $this->db->insert($table, $row);
                }
            }

            return $newId;
        });
    }

    public function incrementViews(int $schemeId): void
    {
        $this->db->execute('UPDATE schemes SET view_count = view_count + 1 WHERE id = :id', ['id' => $schemeId]);
    }

    /** How many beneficiaries are linked to this scheme (blocks deletion). */
    public function usageCount(int $schemeId): array
    {
        return [
            'matches'      => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiary_scheme_matches WHERE scheme_id = :id', ['id' => $schemeId]),
            'applications' => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiary_scheme_applications WHERE scheme_id = :id AND deleted_at IS NULL', ['id' => $schemeId]),
            'benefits'     => (int)$this->db->scalar('SELECT COUNT(*) FROM benefits_received WHERE scheme_id = :id', ['id' => $schemeId]),
        ];
    }
}
