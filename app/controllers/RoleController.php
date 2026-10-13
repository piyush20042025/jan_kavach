<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Response;
use JanKavach\Core\Validator;

/**
 * Roles and permissions.
 *
 * The specification asks that additional roles can be created later, so
 * roles are data: a new role is a row plus a set of grants, and the
 * permission gate in Auth::can() picks it up with no code change.
 */
class RoleController extends Controller
{
    public function index(): void
    {
        $roles = $this->db->select(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
               FROM roles r ORDER BY r.sort_order, r.name'
        );

        $this->view('users/roles', [
            'pageTitle'   => 'Roles & Permissions',
            'breadcrumbs' => [['label' => 'User Management', 'url' => '/users'], ['label' => 'Roles']],
            'roles'       => $roles,
            'permissionCount' => (int)$this->db->scalar('SELECT COUNT(*) FROM permissions'),
        ]);
    }

    public function create(): void
    {
        $this->view('users/role-form', [
            'pageTitle'   => 'Add Role',
            'breadcrumbs' => [
                ['label' => 'User Management', 'url' => '/users'],
                ['label' => 'Roles', 'url' => '/roles'],
                ['label' => 'Add Role'],
            ],
            'role'        => null,
            'permissions' => $this->groupedPermissions(),
            'granted'     => [],
        ]);
    }

    public function edit(string $id): void
    {
        $role = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => (int)$id]), 'Role');

        $granted = array_map('intval', array_column($this->db->select(
            'SELECT permission_id FROM role_permissions WHERE role_id = :id', ['id' => (int)$id]
        ), 'permission_id'));

        $this->view('users/role-form', [
            'pageTitle'   => 'Edit Role',
            'breadcrumbs' => [
                ['label' => 'User Management', 'url' => '/users'],
                ['label' => 'Roles', 'url' => '/roles'],
                ['label' => $role['name']],
            ],
            'role'        => $role,
            'permissions' => $this->groupedPermissions(),
            'granted'     => $granted,
            'userCount'   => (int)$this->db->scalar(
                'SELECT COUNT(*) FROM users WHERE role_id = :id AND deleted_at IS NULL', ['id' => (int)$id]),
        ]);
    }

    public function store(): void
    {
        $data = $this->validateRole(null);

        $roleId = $this->db->transaction(function () use ($data) {
            $id = $this->db->insert('roles', $data);
            $this->syncPermissions($id);
            return $id;
        });

        Audit::created('Users', 'Role', $roleId, $data['name'], $data,
            'Created role with ' . count($this->request->array('permissions')) . ' permission(s)');

        Flash::success('Role created.');
        $this->redirect('/roles/' . $roleId . '/edit');
    }

    public function update(string $id): void
    {
        $role = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => (int)$id]), 'Role');
        $roleId = (int)$id;

        $data = $this->validateRole($roleId);

        // The Super Administrator role must keep full access and its scope,
        // or an administrator could lock everyone out of the platform.
        if ($role['code'] === 'SUPER_ADMIN') {
            $data['code'] = 'SUPER_ADMIN';
            $data['data_scope'] = 'all';
            $data['is_active'] = 1;
        }

        $before = $role;

        $this->db->transaction(function () use ($roleId, $data, $role) {
            $this->db->update('roles', $data, ['id' => $roleId]);
            if ($role['code'] !== 'SUPER_ADMIN') {
                $this->syncPermissions($roleId);
            }
        });

        $after = $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => $roleId]);
        Audit::modified('Users', 'Role', $roleId, $data['name'], $before, $after ?: []);

        Auth::refresh();

        Flash::success($role['code'] === 'SUPER_ADMIN'
            ? 'Role updated. The Super Administrator role always keeps full access, so its permissions were left unchanged.'
            : 'Role and permissions updated.');
        $this->redirect('/roles/' . $roleId . '/edit');
    }

    public function toggleActive(string $id): void
    {
        $role = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => (int)$id]), 'Role');

        if ($role['code'] === 'SUPER_ADMIN') {
            Flash::error('The Super Administrator role cannot be deactivated.');
            $this->back('/roles');
        }

        $active = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM users WHERE role_id = :id AND is_active = 1 AND deleted_at IS NULL',
            ['id' => (int)$id]);

        if ((int)$role['is_active'] === 1 && $active > 0) {
            Flash::error(sprintf('%d active user(s) hold the "%s" role. Move them to another role before deactivating it.',
                $active, $role['name']));
            $this->back('/roles');
        }

        $new = (int)$role['is_active'] === 1 ? 0 : 1;
        $this->db->update('roles', ['is_active' => $new], ['id' => (int)$id]);

        Audit::log('Users', 'Modified', [
            'entity_type'  => 'Role',
            'entity_id'    => (int)$id,
            'entity_label' => $role['name'],
            'description'  => $new === 1 ? 'Role activated' : 'Role deactivated',
        ]);

        Flash::success($role['name'] . ' ' . ($new === 1 ? 'activated.' : 'deactivated.'));
        $this->back('/roles');
    }

    public function destroy(string $id): void
    {
        $role = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => (int)$id]), 'Role');

        if ((int)$role['is_system'] === 1) {
            Flash::error('"' . $role['name'] . '" is a built-in role and cannot be deleted. Deactivate it instead.');
            $this->back('/roles');
        }

        $users = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM users WHERE role_id = :id AND deleted_at IS NULL', ['id' => (int)$id]);

        if ($users > 0) {
            Flash::error(sprintf('%d user(s) hold the "%s" role, so it cannot be deleted.', $users, $role['name']));
            $this->back('/roles');
        }

        $this->db->delete('roles', ['id' => (int)$id]);
        Audit::deleted('Users', 'Role', (int)$id, $role['name'], $role);

        Flash::success('Role deleted.');
        $this->redirect('/roles');
    }

    // =================================================================
    // Internals
    // =================================================================

    private function validateRole(?int $ignoreId): array
    {
        $validator = Validator::make($this->request->all(), [
            'code'        => 'required|alpha_dash|max:50|unique:roles,code,' . ($ignoreId ?? 0),
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'data_scope'  => 'required|in:all,state,district,own',
            'sort_order'  => 'nullable|integer|between:0,999',
        ], [
            'code'       => 'Role Code',
            'name'       => 'Role Name',
            'data_scope' => 'Data Scope',
        ]);

        $fallback = $ignoreId ? '/roles/' . $ignoreId . '/edit' : '/roles/create';

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }

        $scope = (string)$this->request->post('data_scope');

        // Only a Super Administrator may create another all-access role.
        if ($scope === 'all' && !Auth::isSuperAdmin()) {
            $this->backWithErrors(['data_scope' => 'Only a Super Administrator can create an all-access role.'], $fallback);
        }

        return [
            'code'              => strtoupper((string)$this->request->post('code')),
            'name'              => (string)$this->request->post('name'),
            'description'       => $this->request->post('description') ?: null,
            'data_scope'        => $scope,
            // A district or field role needs both geography fields; a state
            // role only needs the State.
            'requires_state'    => in_array($scope, ['state', 'district', 'own'], true) ? 1 : 0,
            'requires_district' => in_array($scope, ['district', 'own'], true) ? 1 : 0,
            'sort_order'        => $this->request->int('sort_order', 50),
            'is_active'         => $this->request->bool('is_active', true) ? 1 : 0,
        ];
    }

    private function syncPermissions(int $roleId): void
    {
        $this->db->execute('DELETE FROM role_permissions WHERE role_id = :id', ['id' => $roleId]);

        $requested = array_map('intval', $this->request->array('permissions'));
        if ($requested === []) {
            return;
        }

        // Only permissions the granting user themselves holds may be given
        // away, so a role cannot be used to escalate privilege.
        $valid = $this->db->select('SELECT id, code FROM permissions');
        foreach ($valid as $permission) {
            $permissionId = (int)$permission['id'];
            if (!in_array($permissionId, $requested, true)) {
                continue;
            }
            if (!Auth::isSuperAdmin() && !Auth::can($permission['code'])) {
                continue;
            }
            $this->db->insert('role_permissions', [
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }
    }

    private function groupedPermissions(): array
    {
        $grouped = [];
        foreach ($this->db->select('SELECT * FROM permissions ORDER BY sort_order, code') as $permission) {
            $grouped[$permission['module']][] = $permission;
        }
        return $grouped;
    }
}
