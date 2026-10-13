<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Controller;
use JanKavach\Models\Master;

/**
 * Master data for a mobile client's first sync.
 */
class ApiMasterController extends Controller
{
    public function all(): void
    {
        $master = new Master();

        $this->jsonSuccess([
            'themes'             => $this->rows('scheme_themes', ['id', 'code', 'name', 'color', 'icon']),
            'beneficiary_types'  => $this->rows('beneficiary_types', ['id', 'code', 'name', 'icon']),
            'target_groups'      => $this->rows('target_groups', ['id', 'code', 'name']),
            'service_approaches' => $this->rows('service_approaches', ['id', 'code', 'name', 'color']),
            'priority_levels'    => $this->rows('priority_levels', ['id', 'code', 'name', 'rank_value', 'color']),
            'feasibility_levels' => $this->rows('feasibility_levels', ['id', 'name', 'rank_value', 'color']),
            'document_types'     => $this->rows('document_types', ['id', 'code', 'name', 'category', 'requires_number', 'validity_required']),
            'application_statuses' => $this->rows('application_statuses', ['id', 'code', 'name', 'stage_order', 'funnel_stage', 'color', 'is_terminal']),
            'lookups'            => $this->allLookups(),
            'eligibility_attributes' => $this->db->select(
                'SELECT attribute_key, label, data_type, lookup_key, source, unit, group_label
                   FROM eligibility_attributes WHERE is_active = 1 ORDER BY sort_order'),
            'synced_at' => date('c'),
        ]);
    }

    public function lookup(string $key): void
    {
        $master = new Master();
        $options = $master->lookup($key);

        if ($options === []) {
            $this->jsonError('No options are defined for "' . $key . '".', null, 404);
        }

        $this->jsonSuccess([
            'lookup_key' => $key,
            'options'    => array_map(fn($label, $code) => ['code' => $code, 'label' => $label],
                                      $options, array_keys($options)),
        ]);
    }

    private function rows(string $table, array $columns): array
    {
        // The table name is fixed by the caller above, never by request input.
        $allowed = ['scheme_themes', 'beneficiary_types', 'target_groups', 'service_approaches',
                    'priority_levels', 'feasibility_levels', 'document_types', 'application_statuses'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $select = implode(', ', array_map(fn($c) => '`' . preg_replace('/[^a-z_]/', '', $c) . '`', $columns));

        return $this->db->select("SELECT {$select} FROM `{$table}` WHERE is_active = 1 ORDER BY id");
    }

    private function allLookups(): array
    {
        $rows = $this->db->select(
            'SELECT lookup_key, code, label, numeric_value FROM lookup_options
              WHERE is_active = 1 ORDER BY lookup_key, sort_order');

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['lookup_key']][] = [
                'code'  => $row['code'],
                'label' => $row['label'],
                'value' => $row['numeric_value'] !== null ? (float)$row['numeric_value'] : null,
            ];
        }
        return $grouped;
    }
}
