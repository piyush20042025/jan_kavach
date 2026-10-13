<?php
namespace JanKavach\Models;

use JanKavach\Core\Database;
use JanKavach\Core\Model;

/**
 * Generic master-data access (specification section 28).
 *
 * One controller and one view drive every simple master because they are all
 * described here rather than coded separately.  Adding a master means adding
 * a row to self::MASTERS - no new controller, model or template.
 */
class Master extends Model
{
    protected string $table = 'scheme_themes';   // replaced per call

    /**
     * key => definition
     *   table   physical table
     *   label   screen title
     *   fields  column => [label, type, required, options]
     *   order   default ORDER BY
     */
    public const MASTERS = [
        'themes' => [
            'table' => 'scheme_themes',
            'label' => 'Scheme Themes',
            'singular' => 'Theme',
            'icon'  => 'bi-tags',
            'description' => 'Thematic classification used across the scheme master, filters and dashboards.',
            'order' => 'sort_order, name',
            'fields' => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 40, 'help' => 'Short machine key, e.g. HEALTH'],
                'name'        => ['label' => 'Theme Name', 'type' => 'text', 'required' => true, 'maxlength' => 120],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 255],
                'color'       => ['label' => 'Colour', 'type' => 'color', 'required' => false],
                'icon'        => ['label' => 'Icon Class', 'type' => 'text', 'required' => false, 'maxlength' => 50, 'help' => 'Bootstrap icon, e.g. bi-heart-pulse'],
                'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => ['scheme_theme_map' => 'theme_id', 'schemes' => 'theme_id'],
        ],

        'beneficiary-types' => [
            'table' => 'beneficiary_types',
            'label' => 'Beneficiary Types',
            'singular' => 'Beneficiary Type',
            'icon'  => 'bi-people',
            'description' => 'Population groups a scheme is aimed at.',
            'order' => 'sort_order, name',
            'fields' => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 40],
                'name'        => ['label' => 'Name', 'type' => 'text', 'required' => true, 'maxlength' => 120],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 255],
                'icon'        => ['label' => 'Icon Class', 'type' => 'text', 'required' => false, 'maxlength' => 50],
                'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => ['scheme_beneficiary_types' => 'beneficiary_type_id'],
        ],

        'target-groups' => [
            'table' => 'target_groups',
            'label' => 'Target Groups',
            'singular' => 'Target Group',
            'icon'  => 'bi-bullseye',
            'description' => 'Specific target populations, e.g. "Pregnant and lactating women".',
            'order' => 'sort_order, name',
            'fields' => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 60],
                'name'        => ['label' => 'Target Group', 'type' => 'text', 'required' => true, 'maxlength' => 200],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 500],
                'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => ['scheme_target_groups' => 'target_group_id'],
        ],

        'service-approaches' => [
            'table' => 'service_approaches',
            'label' => 'Service Approaches',
            'singular' => 'Service Approach',
            'icon'  => 'bi-diagram-3',
            'description' => 'How the programme engages with a scheme: direct linkage, referral or awareness.',
            'order' => 'sort_order, name',
            'fields' => [
                'code'        => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 40],
                'name'        => ['label' => 'Name', 'type' => 'text', 'required' => true, 'maxlength' => 120],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 255],
                'color'       => ['label' => 'Colour', 'type' => 'color', 'required' => false],
                'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => ['schemes' => 'service_approach_id'],
        ],

        'priorities' => [
            'table' => 'priority_levels',
            'label' => 'Priority Ranking',
            'singular' => 'Priority Level',
            'icon'  => 'bi-star',
            'description' => 'Programme prioritisation bands applied to schemes.',
            'order' => 'rank_value',
            'fields' => [
                'rank_value'  => ['label' => 'Rank (1 = highest)', 'type' => 'number', 'required' => true],
                'code'        => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 30],
                'name'        => ['label' => 'Name', 'type' => 'text', 'required' => true, 'maxlength' => 120],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 500],
                'color'       => ['label' => 'Colour', 'type' => 'color', 'required' => false],
            ],
            'usage' => ['schemes' => 'priority_id'],
        ],

        'feasibility' => [
            'table' => 'feasibility_levels',
            'label' => 'Feasibility Ranking',
            'singular' => 'Feasibility Level',
            'icon'  => 'bi-speedometer2',
            'description' => 'How easy a scheme is to access: 1 is difficult, 5 is easy.',
            'order' => 'rank_value',
            'fields' => [
                'rank_value'  => ['label' => 'Rank (1-5)', 'type' => 'number', 'required' => true],
                'name'        => ['label' => 'Name', 'type' => 'text', 'required' => true, 'maxlength' => 80],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 500],
                'color'       => ['label' => 'Colour', 'type' => 'color', 'required' => false],
            ],
            'usage' => ['schemes' => 'feasibility_id'],
        ],

        'document-types' => [
            'table' => 'document_types',
            'label' => 'Document Types',
            'singular' => 'Document Type',
            'icon'  => 'bi-file-earmark-text',
            'description' => 'The document master: what schemes require and what beneficiaries upload.',
            'order' => 'sort_order, name',
            'fields' => [
                'code'              => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 40],
                'name'              => ['label' => 'Document Name', 'type' => 'text', 'required' => true, 'maxlength' => 150],
                'description'       => ['label' => 'Description', 'type' => 'textarea', 'required' => false, 'maxlength' => 500],
                'category'          => ['label' => 'Category', 'type' => 'select', 'required' => true,
                                        'options' => ['Identity', 'Address', 'Income', 'Bank', 'Health', 'Education',
                                                      'Land', 'Category', 'Certificate', 'Photo', 'Other']],
                'requires_number'   => ['label' => 'Document Number Required', 'type' => 'checkbox', 'required' => false],
                'validity_required' => ['label' => 'Issue / Expiry Dates Matter', 'type' => 'checkbox', 'required' => false],
                'is_sensitive'      => ['label' => 'Mask Number in Listings', 'type' => 'checkbox', 'required' => false,
                                        'help' => 'Sensitive numbers are shown masked wherever they appear in lists'],
                'sort_order'        => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => ['scheme_documents' => 'document_type_id', 'beneficiary_documents' => 'document_type_id'],
        ],

        'application-statuses' => [
            'table' => 'application_statuses',
            'label' => 'Application Statuses',
            'singular' => 'Application Status',
            'icon'  => 'bi-list-check',
            'description' => 'The application pipeline. stage_order defines the sequence; funnel_stage groups statuses for the funnel chart.',
            'order' => 'stage_order',
            'fields' => [
                'code'         => ['label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 40],
                'name'         => ['label' => 'Status Name', 'type' => 'text', 'required' => true, 'maxlength' => 100],
                'stage_order'  => ['label' => 'Pipeline Position', 'type' => 'number', 'required' => true],
                'funnel_stage' => ['label' => 'Funnel Group', 'type' => 'select', 'required' => true,
                                   'options' => ['Identified', 'Eligible', 'Initiated', 'Submitted',
                                                 'Approved', 'Benefit', 'Closed', 'Rejected']],
                'color'        => ['label' => 'Colour', 'type' => 'color', 'required' => false],
                'is_terminal'  => ['label' => 'Terminal Status', 'type' => 'checkbox', 'required' => false,
                                   'help' => 'A terminal status ends the application journey'],
            ],
            'usage' => ['beneficiary_scheme_applications' => 'status_id'],
        ],

        'agencies' => [
            'table' => 'implementing_agencies',
            'label' => 'Ministries & Departments',
            'singular' => 'Implementing Agency',
            'icon'  => 'bi-building',
            'description' => 'Implementing ministries, departments, boards and corporations.',
            'order' => 'level, name',
            'fields' => [
                'name'        => ['label' => 'Name', 'type' => 'text', 'required' => true, 'maxlength' => 200],
                'agency_type' => ['label' => 'Type', 'type' => 'select', 'required' => true,
                                  'options' => ['Ministry', 'Department', 'Board', 'Corporation', 'Mission', 'Other']],
                'level'       => ['label' => 'Level', 'type' => 'select', 'required' => true,
                                  'options' => ['National', 'State']],
                'state_id'    => ['label' => 'State', 'type' => 'state', 'required' => false],
                'website'     => ['label' => 'Website', 'type' => 'url', 'required' => false, 'maxlength' => 255],
            ],
            'usage' => ['schemes' => 'ministry_id'],
        ],

        'lookups' => [
            'table' => 'lookup_options',
            'label' => 'Dropdown Options',
            'singular' => 'Dropdown Option',
            'icon'  => 'bi-ui-checks',
            'description' => 'Every configurable dropdown used on the assessment form and in eligibility rules. Add a new lookup_key to create a whole new dropdown without a schema change.',
            'order' => 'lookup_key, sort_order',
            'group_by' => 'lookup_key',
            'fields' => [
                'lookup_key'    => ['label' => 'Dropdown Key', 'type' => 'text', 'required' => true, 'maxlength' => 60,
                                    'help' => 'Groups options together, e.g. social_category'],
                'code'          => ['label' => 'Stored Value', 'type' => 'text', 'required' => true, 'maxlength' => 60],
                'label'         => ['label' => 'Display Label', 'type' => 'text', 'required' => true, 'maxlength' => 200],
                'numeric_value' => ['label' => 'Ordinal Value', 'type' => 'number', 'required' => false,
                                    'help' => 'Optional ranking, so rules can compare with greater-than / less-than'],
                'sort_order'    => ['label' => 'Sort Order', 'type' => 'number', 'required' => false],
            ],
            'usage' => [],
        ],
    ];

    public static function definition(string $key): ?array
    {
        return self::MASTERS[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::MASTERS);
    }

    // =================================================================
    // Generic CRUD against a defined master
    // =================================================================

    public function rows(string $key, ?string $search = null, bool $includeInactive = true): array
    {
        $def = self::definition($key);
        if (!$def) {
            return [];
        }
        $table = $this->safeTable($def['table']);

        $where = ['1 = 1'];
        $bindings = [];

        if (!$includeInactive) {
            $where[] = 'is_active = 1';
        }
        if ($search !== null && $search !== '') {
            $searchable = [];
            foreach (array_keys($def['fields']) as $column) {
                if (in_array($def['fields'][$column]['type'], ['text', 'textarea', 'select'], true)) {
                    $searchable[] = '`' . $this->safeColumn($column) . '` LIKE :search';
                }
            }
            if ($searchable) {
                $where[] = '(' . implode(' OR ', $searchable) . ')';
                $bindings['search'] = '%' . $search . '%';
            }
        }

        $order = $this->safeOrderBy($def['order'] ?? 'id');

        return $this->db->select(
            "SELECT * FROM `{$table}` WHERE " . implode(' AND ', $where) . " ORDER BY {$order}",
            $bindings
        );
    }

    public function row(string $key, int $id): ?array
    {
        $def = self::definition($key);
        if (!$def) {
            return null;
        }
        $table = $this->safeTable($def['table']);
        return $this->db->selectOne("SELECT * FROM `{$table}` WHERE id = :id", ['id' => $id]);
    }

    /** Insert or update from the master form's posted values. */
    public function save(string $key, ?int $id, array $input): int
    {
        $def = self::definition($key);
        if (!$def) {
            throw new \InvalidArgumentException('Unknown master: ' . $key);
        }
        $table = $this->safeTable($def['table']);

        $data = [];
        foreach ($def['fields'] as $column => $field) {
            $column = $this->safeColumn($column);
            $value  = $input[$column] ?? null;

            if ($field['type'] === 'checkbox') {
                $data[$column] = !empty($value) ? 1 : 0;
                continue;
            }
            if (in_array($field['type'], ['number'], true)) {
                $data[$column] = ($value === '' || $value === null) ? null : (0 + $value);
                continue;
            }
            if ($field['type'] === 'state') {
                $data[$column] = ($value === '' || $value === null) ? null : (int)$value;
                continue;
            }
            $data[$column] = ($value === '' || $value === null) ? null : trim((string)$value);
        }

        if (array_key_exists('is_active', $input)) {
            $data['is_active'] = !empty($input['is_active']) ? 1 : 0;
        }

        if ($id) {
            $this->db->update($table, $data, ['id' => $id]);
            return $id;
        }
        if (!array_key_exists('is_active', $data)) {
            $data['is_active'] = 1;
        }
        return $this->db->insert($table, $data);
    }

    public function toggle(string $key, int $id): ?int
    {
        $def = self::definition($key);
        if (!$def) {
            return null;
        }
        $table = $this->safeTable($def['table']);

        $current = $this->db->scalar("SELECT is_active FROM `{$table}` WHERE id = :id", ['id' => $id]);
        if ($current === null) {
            return null;
        }
        $new = (int)$current === 1 ? 0 : 1;
        $this->db->update($table, ['is_active' => $new], ['id' => $id]);
        return $new;
    }

    /** How many records depend on this master row (blocks deletion). */
    public function usage(string $key, int $id): int
    {
        $def = self::definition($key);
        if (!$def || empty($def['usage'])) {
            return 0;
        }
        $total = 0;
        foreach ($def['usage'] as $table => $column) {
            $table  = $this->safeTable($table);
            $column = $this->safeColumn($column);
            $total += (int)$this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :id", ['id' => $id]);
        }
        return $total;
    }

    public function delete(int $id): int
    {
        throw new \LogicException('Use deleteFrom() with an explicit master key.');
    }

    public function deleteFrom(string $key, int $id): bool
    {
        $def = self::definition($key);
        if (!$def) {
            return false;
        }
        if ($this->usage($key, $id) > 0) {
            return false;   // referenced elsewhere; deactivate instead
        }
        $table = $this->safeTable($def['table']);
        return $this->db->delete($table, ['id' => $id]) > 0;
    }

    // =================================================================
    // Option helpers used by every form in the application
    // =================================================================

    public function options(string $labelColumn = 'name', array $conditions = ['is_active' => 1], ?string $orderBy = null): array
    {
        throw new \LogicException('Use optionsFor() with an explicit table.');
    }

    /** id => label for any master table. */
    public function optionsFor(string $table, string $labelColumn = 'name', string $order = 'sort_order, name', bool $activeOnly = true): array
    {
        $table  = $this->safeTable($table);
        $column = $this->safeColumn($labelColumn);
        $order  = $this->safeOrderBy($order);

        $sql = "SELECT id, `{$column}` FROM `{$table}`";
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $this->db->pluck($sql . " ORDER BY {$order}");
    }

    /** code => label for a lookup_options group. */
    public function lookup(string $lookupKey, bool $activeOnly = true): array
    {
        $sql = 'SELECT code, label FROM lookup_options WHERE lookup_key = :k';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return $this->db->pluck($sql . ' ORDER BY sort_order, label', ['k' => $lookupKey]);
    }

    public function lookupKeys(): array
    {
        $rows = $this->db->select('SELECT DISTINCT lookup_key FROM lookup_options ORDER BY lookup_key');
        return array_column($rows, 'lookup_key');
    }

    /** Everything a scheme or beneficiary form needs, in one call. */
    public function formOptions(): array
    {
        return [
            'themes'            => $this->optionsFor('scheme_themes'),
            'beneficiary_types' => $this->optionsFor('beneficiary_types'),
            'target_groups'     => $this->optionsFor('target_groups'),
            'service_approaches'=> $this->optionsFor('service_approaches'),
            'priorities'        => $this->optionsFor('priority_levels', 'name', 'rank_value'),
            'feasibility'       => $this->optionsFor('feasibility_levels', 'name', 'rank_value'),
            'document_types'    => $this->optionsFor('document_types'),
            'statuses'          => $this->optionsFor('application_statuses', 'name', 'stage_order'),
            'agencies'          => $this->optionsFor('implementing_agencies', 'name', 'name'),
        ];
    }

    public function documentTypes(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM document_types';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY sort_order, name');
    }

    public function assessmentQuestions(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM assessment_questions';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY sort_order, id');
    }

    /** Assessment questions grouped by section, with their options resolved. */
    public function assessmentForm(): array
    {
        $questions = $this->assessmentQuestions();
        $sections = [];
        foreach ($questions as $question) {
            if (!empty($question['lookup_key'])) {
                $question['options'] = $this->lookup($question['lookup_key']);
            }
            $sections[$question['section']][] = $question;
        }
        return $sections;
    }

    private function safeTable(string $table): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Unsafe table name.');
        }
        return $table;
    }
}
