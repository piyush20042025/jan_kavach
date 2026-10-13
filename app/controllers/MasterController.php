<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;

/**
 * Master data management (specification section 28).
 *
 * The domain masters share one generic screen driven by Master::MASTERS.
 * The geographic masters get their own actions because each level depends on
 * the one above it.
 */
class MasterController extends Controller
{
    private Master $masters;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->masters = new Master();
        $this->geo = new Geography();
    }

    public function index(): void
    {
        $cards = [];
        foreach (Master::MASTERS as $key => $definition) {
            $cards[$key] = $definition + [
                'count' => (int)$this->db->scalar(
                    sprintf('SELECT COUNT(*) FROM `%s`', $definition['table'])),
                'active' => (int)$this->db->scalar(
                    sprintf('SELECT COUNT(*) FROM `%s` WHERE is_active = 1', $definition['table'])),
            ];
        }

        $this->view('masters/index', [
            'pageTitle'   => 'Master Data',
            'breadcrumbs' => [['label' => 'Masters']],
            'cards'       => $cards,
            'geography'   => [
                'states'    => (int)$this->db->scalar('SELECT COUNT(*) FROM states WHERE is_active = 1'),
                'districts' => (int)$this->db->scalar('SELECT COUNT(*) FROM districts WHERE is_active = 1'),
                'blocks'    => (int)$this->db->scalar('SELECT COUNT(*) FROM blocks WHERE is_active = 1'),
                'gps'       => (int)$this->db->scalar('SELECT COUNT(*) FROM gram_panchayats WHERE is_active = 1'),
                'villages'  => (int)$this->db->scalar('SELECT COUNT(*) FROM villages WHERE is_active = 1'),
            ],
        ]);
    }

    // =================================================================
    // Generic domain masters
    // =================================================================

    public function generic(string $master): void
    {
        $definition = $this->definition($master);
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $rows = $this->masters->rows($master, $search);

        // The lookup master is grouped by its dropdown key for readability.
        $grouped = null;
        if (!empty($definition['group_by'])) {
            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row[$definition['group_by']]][] = $row;
            }
            ksort($grouped);
        }

        $this->view('masters/generic', [
            'pageTitle'   => $definition['label'],
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => $definition['label']]],
            'master'      => $master,
            'definition'  => $definition,
            'rows'        => $rows,
            'grouped'     => $grouped,
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->masters->row($master, (int)$this->request->input('edit')) : null,
            'states'      => $this->geo->stateOptions(true, false),
        ]);
    }

    public function saveGeneric(string $master): void
    {
        $definition = $this->definition($master);
        $id = $this->request->int('id');

        $rules = [];
        $labels = [];
        foreach ($definition['fields'] as $column => $field) {
            $parts = [$field['required'] ? 'required' : 'nullable'];
            $parts[] = match ($field['type']) {
                'number' => 'integer',
                'url'    => 'url',
                'color'  => 'string|max:9',
                default  => 'string',
            };
            if (!empty($field['maxlength'])) {
                $parts[] = 'max:' . (int)$field['maxlength'];
            }
            if (!empty($field['options'])) {
                $parts[] = 'in:' . implode(',', $field['options']);
            }
            // Checkboxes arrive as present/absent, so they need no rule.
            if ($field['type'] !== 'checkbox') {
                $rules[$column] = implode('|', $parts);
                $labels[$column] = $field['label'];
            }
        }

        $validator = Validator::make($this->request->all(), $rules, $labels);
        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/' . $master);
        }

        // Uniqueness on a code / key column, checked explicitly so the
        // message names the master rather than the table.
        foreach (['code', 'rank_value'] as $unique) {
            if (!isset($definition['fields'][$unique])) {
                continue;
            }
            $value = $this->request->input($unique);
            if ($value === null || $value === '') {
                continue;
            }
            $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :v', $definition['table'], $unique);
            $bindings = ['v' => $value];
            if ($id) {
                $sql .= ' AND id <> :id';
                $bindings['id'] = $id;
            }
            if ((int)$this->db->scalar($sql, $bindings) > 0) {
                $this->backWithErrors([
                    $unique => $definition['fields'][$unique]['label'] . ' is already in use in ' . $definition['label'] . '.',
                ], '/masters/' . $master);
            }
        }

        $before = $id ? $this->masters->row($master, $id) : null;
        $savedId = $this->masters->save($master, $id, $this->request->all());
        $after = $this->masters->row($master, $savedId);

        $label = $after['name'] ?? $after['label'] ?? ('#' . $savedId);

        if ($id) {
            Audit::modified('Masters', $definition['singular'], $savedId, $label, $before ?: [], $after ?: []);
            Flash::success($definition['singular'] . ' updated.');
        } else {
            Audit::created('Masters', $definition['singular'], $savedId, $label, $after ?: []);
            Flash::success($definition['singular'] . ' added.');
        }

        $this->redirect('/masters/' . $master);
    }

    public function toggleGeneric(string $master, string $id): void
    {
        $definition = $this->definition($master);
        $row = $this->masters->row($master, (int)$id);
        if (!$row) {
            Response::abort(404, $definition['singular'] . ' not found.');
        }

        $new = $this->masters->toggle($master, (int)$id);
        $label = $row['name'] ?? $row['label'] ?? ('#' . (int)$id);

        Audit::log('Masters', 'Modified', [
            'entity_type'  => $definition['singular'],
            'entity_id'    => (int)$id,
            'entity_label' => $label,
            'description'  => $new === 1 ? 'Activated' : 'Deactivated',
        ]);

        Flash::success($label . ' ' . ($new === 1 ? 'activated.' : 'deactivated.'));
        $this->back('/masters/' . $master);
    }

    public function deleteGeneric(string $master, string $id): void
    {
        $definition = $this->definition($master);
        $row = $this->masters->row($master, (int)$id);
        if (!$row) {
            Response::abort(404, $definition['singular'] . ' not found.');
        }

        $usage = $this->masters->usage($master, (int)$id);
        $label = $row['name'] ?? $row['label'] ?? ('#' . (int)$id);

        // Referenced master rows are deactivated, never deleted, so existing
        // schemes and beneficiary records keep their meaning.
        if ($usage > 0) {
            Flash::error(sprintf('"%s" is used by %d record(s) and cannot be deleted. Deactivate it instead.',
                $label, $usage));
            $this->back('/masters/' . $master);
        }

        $this->masters->deleteFrom($master, (int)$id);
        Audit::deleted('Masters', $definition['singular'], (int)$id, $label, $row);

        Flash::success($label . ' deleted.');
        $this->back('/masters/' . $master);
    }

    // =================================================================
    // Geography
    // =================================================================

    public function states(): void
    {
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $this->view('masters/states', [
            'pageTitle'   => 'States & Union Territories',
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => 'States']],
            'rows'        => $this->geo->stateList($search),
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->db->selectOne('SELECT * FROM states WHERE id = :id', ['id' => (int)$this->request->input('edit')])
                             : null,
        ]);
    }

    public function saveState(): void
    {
        $this->requireSuperAdmin('States can only be managed by a Super Administrator.');
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'state_code' => 'required|string|max:10|unique:states,state_code,' . ($id ?? 0),
            'name'       => 'required|string|max:120|unique:states,name,' . ($id ?? 0),
            'type'       => 'required|in:State,UT',
            'region'     => 'nullable|string|max:40',
        ], ['state_code' => 'State Code', 'name' => 'State Name']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/states');
        }

        $before = $id ? $this->db->selectOne('SELECT * FROM states WHERE id = :id', ['id' => $id]) : null;
        $savedId = $this->geo->saveState($id, $this->request->all());
        $after = $this->db->selectOne('SELECT * FROM states WHERE id = :id', ['id' => $savedId]);

        $id ? Audit::modified('Masters', 'State', $savedId, $after['name'], $before ?: [], $after ?: [])
            : Audit::created('Masters', 'State', $savedId, $after['name'], $after ?: []);

        Flash::success('State ' . ($id ? 'updated.' : 'added.'));
        $this->redirect('/masters/states');
    }

    public function toggleState(string $id): void
    {
        $this->requireSuperAdmin('States can only be managed by a Super Administrator.');
        $this->toggleGeo('states', (int)$id, 'State');
    }

    public function districts(): void
    {
        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $this->view('masters/districts', [
            'pageTitle'   => 'Districts',
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => 'Districts']],
            'rows'        => $this->geo->districtList($stateId, $search),
            'states'      => $this->geo->stateOptions(),
            'stateId'     => $stateId,
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->db->selectOne('SELECT * FROM districts WHERE id = :id', ['id' => (int)$this->request->input('edit')])
                             : null,
        ]);
    }

    public function saveDistrict(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'state_id'      => 'required|integer|exists:states,id',
            'name'          => 'required|string|max:120',
            'district_code' => 'nullable|string|max:15',
        ], ['state_id' => 'State', 'name' => 'District Name']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/districts');
        }

        $stateId = (int)$this->request->post('state_id');
        Scope::assertAccess($stateId, null);

        // District names must be unique inside their State.
        $sql = 'SELECT COUNT(*) FROM districts WHERE state_id = :s AND LOWER(name) = :n';
        $bindings = ['s' => $stateId, 'n' => mb_strtolower(trim((string)$this->request->post('name')))];
        if ($id) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $id;
        }
        if ((int)$this->db->scalar($sql, $bindings) > 0) {
            $this->backWithErrors(['name' => 'That District already exists in the selected State.'], '/masters/districts');
        }

        $before = $id ? $this->db->selectOne('SELECT * FROM districts WHERE id = :id', ['id' => $id]) : null;
        $savedId = $this->geo->saveDistrict($id, $this->request->all());
        $after = $this->db->selectOne('SELECT * FROM districts WHERE id = :id', ['id' => $savedId]);

        $id ? Audit::modified('Masters', 'District', $savedId, $after['name'], $before ?: [], $after ?: [])
            : Audit::created('Masters', 'District', $savedId, $after['name'], $after ?: []);

        Flash::success('District ' . ($id ? 'updated.' : 'added.'));
        $this->redirect('/masters/districts?state_id=' . $stateId);
    }

    public function toggleDistrict(string $id): void
    {
        $this->toggleGeo('districts', (int)$id, 'District');
    }

    public function blocks(): void
    {
        $districtId = $this->request->int('district_id');
        $stateId    = $this->request->int('state_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $this->view('masters/blocks', [
            'pageTitle'   => 'Blocks',
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => 'Blocks']],
            'rows'        => $this->geo->blockList($districtId, $search),
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($stateId),
            'stateId'     => $stateId,
            'districtId'  => $districtId,
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->db->selectOne('SELECT * FROM blocks WHERE id = :id', ['id' => (int)$this->request->input('edit')])
                             : null,
        ]);
    }

    public function saveBlock(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'district_id' => 'required|integer|exists:districts,id',
            'name'        => 'required|string|max:120',
            'block_type'  => 'nullable|in:Rural,Urban,Mixed',
            'code'        => 'nullable|string|max:15',
        ], ['district_id' => 'District', 'name' => 'Block Name']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/blocks');
        }

        $districtId = (int)$this->request->post('district_id');
        $stateId = $this->geo->stateOfDistrict($districtId);
        Scope::assertAccess($stateId, $districtId);

        $savedId = $this->geo->saveBlock($id, $this->request->all());
        $after = $this->db->selectOne('SELECT * FROM blocks WHERE id = :id', ['id' => $savedId]);

        $id ? Audit::modified('Masters', 'Block', $savedId, $after['name'], [], $after ?: [])
            : Audit::created('Masters', 'Block', $savedId, $after['name'], $after ?: []);

        Flash::success('Block ' . ($id ? 'updated.' : 'added.'));
        $this->redirect('/masters/blocks?district_id=' . $districtId);
    }

    public function toggleBlock(string $id): void
    {
        $this->toggleGeo('blocks', (int)$id, 'Block');
    }

    public function gramPanchayats(): void
    {
        $blockId    = $this->request->int('block_id');
        $districtId = $this->request->int('district_id');
        $stateId    = $this->request->int('state_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $this->view('masters/gram-panchayats', [
            'pageTitle'   => 'Gram Panchayats',
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => 'Gram Panchayats']],
            'rows'        => $this->geo->gramPanchayatList($blockId, $search),
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($stateId),
            'blocks'      => $this->geo->blocks($districtId),
            'stateId'     => $stateId,
            'districtId'  => $districtId,
            'blockId'     => $blockId,
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->db->selectOne('SELECT * FROM gram_panchayats WHERE id = :id', ['id' => (int)$this->request->input('edit')])
                             : null,
        ]);
    }

    public function saveGramPanchayat(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'block_id' => 'required|integer|exists:blocks,id',
            'name'     => 'required|string|max:120',
            'code'     => 'nullable|string|max:15',
        ], ['block_id' => 'Block', 'name' => 'Gram Panchayat Name']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/gram-panchayats');
        }

        $savedId = $this->geo->saveGramPanchayat($id, $this->request->all());
        $after = $this->db->selectOne('SELECT * FROM gram_panchayats WHERE id = :id', ['id' => $savedId]);

        $id ? Audit::modified('Masters', 'Gram Panchayat', $savedId, $after['name'], [], $after ?: [])
            : Audit::created('Masters', 'Gram Panchayat', $savedId, $after['name'], $after ?: []);

        Flash::success('Gram Panchayat ' . ($id ? 'updated.' : 'added.'));
        $this->redirect('/masters/gram-panchayats?block_id=' . (int)$this->request->post('block_id'));
    }

    public function toggleGramPanchayat(string $id): void
    {
        $this->toggleGeo('gram_panchayats', (int)$id, 'Gram Panchayat');
    }

    public function villages(): void
    {
        $gpId       = $this->request->int('gram_panchayat_id');
        $blockId    = $this->request->int('block_id');
        $districtId = $this->request->int('district_id');
        $stateId    = $this->request->int('state_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);
        $search = $this->request->filled('search') ? (string)$this->request->input('search') : null;

        $this->view('masters/villages', [
            'pageTitle'   => 'Villages',
            'breadcrumbs' => [['label' => 'Masters', 'url' => '/masters'], ['label' => 'Villages']],
            'rows'        => $this->geo->villageList($blockId, $gpId, $search),
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($stateId),
            'blocks'      => $this->geo->blocks($districtId),
            'gps'         => $this->geo->gramPanchayats($blockId),
            'stateId'     => $stateId,
            'districtId'  => $districtId,
            'blockId'     => $blockId,
            'gpId'        => $gpId,
            'search'      => $search,
            'editing'     => $this->request->filled('edit')
                             ? $this->db->selectOne('SELECT * FROM villages WHERE id = :id', ['id' => (int)$this->request->input('edit')])
                             : null,
        ]);
    }

    public function saveVillage(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'gram_panchayat_id' => 'nullable|integer|exists:gram_panchayats,id',
            'block_id'          => 'required|integer|exists:blocks,id',
            'name'              => 'required|string|max:150',
            'area_type'         => 'nullable|in:Rural,Urban',
            'pincode'           => 'nullable|pincode',
        ], ['block_id' => 'Block', 'name' => 'Village Name']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/masters/villages');
        }

        $savedId = $this->geo->saveVillage($id, $this->request->all());
        $after = $this->db->selectOne('SELECT * FROM villages WHERE id = :id', ['id' => $savedId]);

        $id ? Audit::modified('Masters', 'Village', $savedId, $after['name'], [], $after ?: [])
            : Audit::created('Masters', 'Village', $savedId, $after['name'], $after ?: []);

        Flash::success('Village ' . ($id ? 'updated.' : 'added.'));
        $this->redirect('/masters/villages?block_id=' . (int)$this->request->post('block_id'));
    }

    public function toggleVillage(string $id): void
    {
        $this->toggleGeo('villages', (int)$id, 'Village');
    }

    // =================================================================
    // Internals
    // =================================================================

    private function definition(string $master): array
    {
        $definition = Master::definition($master);
        if (!$definition) {
            Response::abort(404, 'That master does not exist.');
        }
        return $definition;
    }

    private function toggleGeo(string $table, int $id, string $label): void
    {
        $allowed = ['states', 'districts', 'blocks', 'gram_panchayats', 'villages'];
        if (!in_array($table, $allowed, true)) {
            Response::abort(400, 'Unknown geography level.');
        }

        $row = $this->db->selectOne("SELECT * FROM `{$table}` WHERE id = :id", ['id' => $id]);
        if (!$row) {
            Response::abort(404, $label . ' not found.');
        }

        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->update($table, ['is_active' => $new], ['id' => $id]);

        Audit::log('Masters', 'Modified', [
            'entity_type'  => $label,
            'entity_id'    => $id,
            'entity_label' => $row['name'],
            'description'  => $new === 1 ? 'Activated' : 'Deactivated',
        ]);

        Flash::success($row['name'] . ' ' . ($new === 1 ? 'activated.' : 'deactivated.'));
        $this->back('/masters');
    }

    private function requireSuperAdmin(string $message): void
    {
        if (!Auth::isSuperAdmin()) {
            Response::abort(403, $message);
        }
    }
}
