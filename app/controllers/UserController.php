<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\Geography;
use JanKavach\Models\User;

/**
 * User management (specification section 16).
 */
class UserController extends Controller
{
    private User $users;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->users = new User();
        $this->geo = new Geography();
    }

    public function index(): void
    {
        $filters = $this->userFilters();
        [$column, $dir, $sortKey] = $this->sort(User::SORTS, 'name');

        $paginator = $this->users->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('users/index', [
            'pageTitle'   => 'User Management',
            'breadcrumbs' => [['label' => 'User Management']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'roles'       => $this->users->roles(false),
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, false);

        $this->view('users/show', [
            'pageTitle'   => $user['full_name'],
            'breadcrumbs' => [['label' => 'User Management', 'url' => '/users'], ['label' => $user['full_name']]],
            'user'        => $user,
            'usage'       => $this->users->usageCount((int)$id),
            'activity'    => Auth::can('audit.view') ? $this->users->activity((int)$id, 40) : [],
            'performance' => $this->db->selectOne(
                'SELECT * FROM vw_field_user_performance WHERE user_id = :id', ['id' => (int)$id]),
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', array_merge($this->formData(null), [
            'pageTitle'   => 'Add User',
            'breadcrumbs' => [['label' => 'User Management', 'url' => '/users'], ['label' => 'Add User']],
            'user'        => null,
            'generatedPassword' => $this->users->generatePassword(),
        ]));
    }

    public function edit(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, true);

        $this->view('users/form', array_merge($this->formData($user), [
            'pageTitle'   => 'Edit User',
            'breadcrumbs' => [
                ['label' => 'User Management', 'url' => '/users'],
                ['label' => $user['full_name'], 'url' => '/users/' . (int)$id],
                ['label' => 'Edit'],
            ],
            'user' => $user,
        ]));
    }

    public function store(): void
    {
        $data = $this->validateUser(null);
        $password = $this->request->post('password') ?: $this->users->generatePassword();

        $data['user_code']     = $this->users->nextUserCode();
        $data['password_hash'] = $this->users->hashPassword($password);
        $data['must_change_password'] = $this->request->bool('must_change_password', true) ? 1 : 0;

        $userId = $this->users->create($data);

        Audit::created('Users', 'User', $userId, $data['full_name'],
            array_diff_key($data, ['password_hash' => 1]),
            'Created user account with role ' . $this->roleName((int)$data['role_id']));

        // The generated password is shown once so it can be handed over.
        if (!$this->request->filled('password')) {
            Flash::warning('Temporary password for ' . $data['username'] . ': ' . $password
                . ' - note it now; it cannot be shown again.');
        }

        Flash::success('User created.');
        $this->redirect('/users/' . $userId);
    }

    public function update(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, true);
        $userId = (int)$id;

        $data = $this->validateUser($userId);
        unset($data['user_code']);

        $before = $this->users->find($userId);

        // A user must not be able to change their own role or demote
        // themselves out of the screen they are standing on.
        if ($userId === Auth::id()) {
            unset($data['role_id'], $data['is_active']);
        }

        $this->users->update($userId, $data);

        $after = $this->users->find($userId);
        Audit::modified('Users', 'User', $userId, $data['full_name'],
            array_diff_key($before ?: [], ['password_hash' => 1]),
            array_diff_key($after ?: [], ['password_hash' => 1]));

        if ($userId === Auth::id()) {
            Auth::refresh();
        }

        Flash::success('User updated.');
        $this->redirect('/users/' . $userId);
    }

    public function toggleActive(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, true);

        if ((int)$id === Auth::id()) {
            Flash::error('You cannot deactivate your own account.');
            $this->back('/users/' . (int)$id);
        }

        // Never leave the platform without an active Super Administrator.
        if ($user['role_code'] === 'SUPER_ADMIN' && (int)$user['is_active'] === 1) {
            $remaining = (int)$this->db->scalar(
                "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE r.code = 'SUPER_ADMIN' AND u.is_active = 1 AND u.deleted_at IS NULL AND u.id <> :id",
                ['id' => (int)$id]);
            if ($remaining === 0) {
                Flash::error('This is the only active Super Administrator. Create another before deactivating this one.');
                $this->back('/users/' . (int)$id);
            }
        }

        $new = $this->users->toggleActive((int)$id);

        Audit::log('Users', 'Modified', [
            'entity_type'  => 'User',
            'entity_id'    => (int)$id,
            'entity_label' => $user['full_name'],
            'description'  => $new === 1 ? 'Account activated' : 'Account deactivated',
        ]);

        Flash::success($user['full_name'] . ' ' . ($new === 1 ? 'activated.' : 'deactivated.'));
        $this->back('/users/' . (int)$id);
    }

    public function resetPassword(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, true);

        $password = $this->request->post('password');

        if ($password) {
            $validator = Validator::make(['password' => $password], ['password' => 'required|password'],
                ['password' => 'New Password']);
            if ($validator->fails()) {
                Flash::error($validator->firstError() ?? 'Please choose a stronger password.');
                $this->back('/users/' . (int)$id);
            }
        } else {
            $password = $this->users->generatePassword();
        }

        $this->users->setPassword((int)$id, $password, true);

        Audit::log('Users', 'Password Reset', [
            'entity_type'  => 'User',
            'entity_id'    => (int)$id,
            'entity_label' => $user['full_name'],
            'description'  => 'Password reset by an administrator; the user must change it at next sign-in',
        ]);

        Flash::warning('New password for ' . $user['username'] . ': ' . $password
            . ' - note it now; it cannot be shown again. The user must change it at next sign-in.');
        $this->back('/users/' . (int)$id);
    }

    public function destroy(string $id): void
    {
        $user = $this->findOrFail($this->users->findDetailed((int)$id), 'User');
        $this->assertManageable($user, true);

        if ((int)$id === Auth::id()) {
            Flash::error('You cannot delete your own account.');
            $this->back('/users/' . (int)$id);
        }

        $usage = $this->users->usageCount((int)$id);
        $linked = $usage['beneficiaries'] + $usage['applications'] + $usage['followups'];

        // Field work stays attributed; a working account is deactivated.
        if ($linked > 0) {
            Flash::error(sprintf(
                '%s is linked to %d beneficiary record(s), %d application(s) and %d follow-up(s), so the account cannot be deleted. Deactivate it instead - the field work stays attributed to them.',
                $user['full_name'], $usage['beneficiaries'], $usage['applications'], $usage['followups']));
            $this->back('/users/' . (int)$id);
        }

        $this->users->delete((int)$id);
        Audit::deleted('Users', 'User', (int)$id, $user['full_name'],
            array_diff_key($user, ['password_hash' => 1]));

        Flash::success('User deleted.');
        $this->redirect('/users');
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->userFilters();
        $rows = $this->users->allFiltered($filters);

        Exporter::send($format, [
            'User Code'    => 'user_code',
            'Full Name'    => 'full_name',
            'Username'     => 'username',
            'Email'        => 'email',
            'Mobile'       => fn($r) => $r['mobile'] ?? '-',
            'Role'         => 'role_name',
            'State'        => fn($r) => $r['state_name'] ?? '-',
            'District'     => fn($r) => $r['district_name'] ?? '-',
            'Designation'  => fn($r) => $r['designation'] ?? '-',
            'Organization' => fn($r) => $r['organization'] ?? '-',
            'Status'       => fn($r) => (int)$r['is_active'] === 1 ? 'Active' : 'Inactive',
            'Created On'   => fn($r) => fmt_date($r['created_at']),
            'Last Login'   => fn($r) => fmt_datetime($r['last_login_at']),
        ], $rows, 'user-directory', [
            'title'   => 'User Directory',
            'module'  => 'Users',
            'filters' => ['Data Scope' => Scope::label() ?: 'National'],
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function userFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'      => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'role_id'     => $this->request->int('role_id'),
            'state_id'    => $stateId,
            'district_id' => $districtId,
            'is_active'   => $this->request->filled('is_active') ? $this->request->int('is_active') : null,
        ];
    }

    private function formData(?array $user): array
    {
        $stateId = $user['state_id'] ?? Auth::stateId();

        return [
            'roles'     => $this->users->assignableRoles(),
            'states'    => $this->geo->stateOptions(),
            'districts' => $this->geo->districtOptions($stateId ? (int)$stateId : null),
            'blocks'    => $this->geo->blocks($user['district_id'] ?? null),
        ];
    }

    private function validateUser(?int $ignoreId): array
    {
        $rules = [
            'full_name'    => 'required|string|max:150|no_html',
            'username'     => 'required|username|unique:users,username,' . ($ignoreId ?? 0),
            'email'        => 'required|email|max:150|unique:users,email,' . ($ignoreId ?? 0),
            'mobile'       => 'nullable|mobile|unique:users,mobile,' . ($ignoreId ?? 0),
            'role_id'      => 'required|integer|exists:roles,id',
            'state_id'     => 'nullable|integer|exists:states,id',
            'district_id'  => 'nullable|integer|exists:districts,id',
            'block_id'     => 'nullable|integer|exists:blocks,id',
            'designation'  => 'nullable|string|max:120',
            'organization' => 'nullable|string|max:150',
        ];
        if (!$ignoreId && $this->request->filled('password')) {
            $rules['password'] = 'required|password|confirmed';
        }

        $validator = Validator::make($this->request->all(), $rules, [
            'full_name'   => 'Full Name',
            'role_id'     => 'Role',
            'state_id'    => 'State',
            'district_id' => 'District',
        ]);

        $fallback = $ignoreId ? '/users/' . $ignoreId . '/edit' : '/users/create';

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }

        $roleId = (int)$this->request->post('role_id');
        $role = $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => $roleId]);
        if (!$role) {
            $this->backWithErrors(['role_id' => 'The selected role is not valid.'], $fallback);
        }

        // Only a Super Administrator may grant the Super Administrator role.
        if ($role['code'] === 'SUPER_ADMIN' && !Auth::isSuperAdmin()) {
            $this->backWithErrors(['role_id' => 'You are not permitted to assign the Super Administrator role.'], $fallback);
        }

        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');

        // The role dictates which geography fields are compulsory.
        if ((int)$role['requires_state'] === 1 && !$stateId) {
            $this->backWithErrors(['state_id' => 'A ' . $role['name'] . ' must be assigned to a State.'], $fallback);
        }
        if ((int)$role['requires_district'] === 1 && !$districtId) {
            $this->backWithErrors(['district_id' => 'A ' . $role['name'] . ' must be assigned to a District.'], $fallback);
        }
        if ($role['code'] === 'SUPER_ADMIN') {
            $stateId = null;
            $districtId = null;
        }

        if ($districtId && $stateId) {
            $chainErrors = $this->geo->validateChain($stateId, $districtId, $this->request->int('block_id'));
            if ($chainErrors) {
                $this->backWithErrors($chainErrors, $fallback);
            }
        }

        // A State Administrator can only create users inside their own State.
        if (!Auth::isSuperAdmin() && Auth::stateId() && $stateId !== Auth::stateId()) {
            $this->backWithErrors(['state_id' => 'You can only manage users within your own State.'], $fallback);
        }

        return [
            'full_name'    => (string)$this->request->post('full_name'),
            'username'     => (string)$this->request->post('username'),
            'email'        => (string)$this->request->post('email'),
            'mobile'       => $this->request->post('mobile') ?: null,
            'role_id'      => $roleId,
            'state_id'     => $stateId,
            'district_id'  => $districtId,
            'block_id'     => $this->request->int('block_id'),
            'designation'  => $this->request->post('designation') ?: null,
            'organization' => $this->request->post('organization') ?: null,
            'is_active'    => $this->request->bool('is_active', true) ? 1 : 0,
        ];
    }

    /** 403 unless the signed-in user may see (or manage) this account. */
    private function assertManageable(array $user, bool $forWrite): void
    {
        if (Auth::isSuperAdmin()) {
            return;
        }
        if ((int)$user['id'] === Auth::id()) {
            return;   // always allowed to view your own record
        }
        // Nobody below Super Administrator may touch a Super Administrator.
        if ($user['role_code'] === 'SUPER_ADMIN') {
            Response::abort(403, 'Only a Super Administrator can manage that account.');
        }

        $scope = Auth::dataScope();
        if ($scope === 'state') {
            if ($user['state_id'] !== null && (int)$user['state_id'] === Auth::stateId()) {
                return;
            }
            Response::abort(403, 'That user belongs to a different State.');
        }
        if ($scope === 'district' && !$forWrite) {
            if ($user['district_id'] !== null && (int)$user['district_id'] === Auth::districtId()) {
                return;
            }
        }
        Response::abort(403, 'You do not have permission to manage that user.');
    }

    private function roleName(int $roleId): string
    {
        return (string)$this->db->scalar('SELECT name FROM roles WHERE id = :id', ['id' => $roleId]);
    }
}
