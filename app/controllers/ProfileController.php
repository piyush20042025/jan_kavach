<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Session;
use JanKavach\Core\Validator;
use JanKavach\Models\User;

/**
 * The signed-in user's own profile, password and activity.
 */
class ProfileController extends Controller
{
    public function show(): void
    {
        $users = new User();
        $user = $this->findOrFail($users->findDetailed(Auth::id()), 'Profile');

        $this->view('users/profile', [
            'pageTitle'   => 'My Profile',
            'breadcrumbs' => [['label' => 'My Profile']],
            'user'        => $user,
            'permissions' => $this->groupedPermissions(),
            'performance' => $this->db->selectOne(
                'SELECT * FROM vw_field_user_performance WHERE user_id = :id', ['id' => Auth::id()]),
            'recentActivity' => $users->activity(Auth::id(), 15),
        ]);
    }

    public function update(): void
    {
        $userId = Auth::id();

        $validator = Validator::make($this->request->all(), [
            'full_name' => 'required|string|max:150|no_html',
            'email'     => 'required|email|max:150|unique:users,email,' . $userId,
            'mobile'    => 'nullable|mobile|unique:users,mobile,' . $userId,
            'designation'  => 'nullable|string|max:120',
            'organization' => 'nullable|string|max:150',
        ], ['full_name' => 'Full Name', 'email' => 'Email Address']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/profile');
        }

        $users = new User();
        $before = $users->find($userId);

        // A user may change their own contact details, never their role,
        // geography or active status - those stay with an administrator.
        $users->update($userId, [
            'full_name'    => (string)$this->request->post('full_name'),
            'email'        => (string)$this->request->post('email'),
            'mobile'       => $this->request->post('mobile') ?: null,
            'designation'  => $this->request->post('designation') ?: null,
            'organization' => $this->request->post('organization') ?: null,
        ]);

        $after = $users->find($userId);
        Audit::modified('Profile', 'User', $userId, $after['full_name'] ?? null,
            array_diff_key($before ?: [], ['password_hash' => 1]),
            array_diff_key($after ?: [], ['password_hash' => 1]),
            'Updated own profile');

        Auth::refresh();

        Flash::success('Your profile has been updated.');
        $this->redirect('/profile');
    }

    public function showPassword(): void
    {
        $user = Auth::user();

        $this->bare('users/change-password', [
            'pageTitle'  => 'Change Password',
            'mustChange' => (int)($user['must_change_password'] ?? 0) === 1,
            'user'       => $user,
        ], (int)($user['must_change_password'] ?? 0) === 1 ? 'layouts/auth' : 'layouts/app');
    }

    public function updatePassword(): void
    {
        $userId = Auth::id();

        $validator = Validator::make($this->request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|password|confirmed',
        ], [
            'current_password' => 'Current Password',
            'new_password'     => 'New Password',
        ]);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please check the password fields.');
            $this->redirect('/profile/password');
        }

        $stored = $this->db->scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);

        if (!$stored || !password_verify((string)$this->request->post('current_password'), (string)$stored)) {
            Audit::log('Profile', 'Failed Login', [
                'entity_type'  => 'User',
                'entity_id'    => $userId,
                'description'  => 'Password change attempted with an incorrect current password',
            ]);
            Flash::error('Your current password is not correct.');
            $this->redirect('/profile/password');
        }

        $newPassword = (string)$this->request->post('new_password');

        if (password_verify($newPassword, (string)$stored)) {
            Flash::error('The new password must be different from your current one.');
            $this->redirect('/profile/password');
        }

        (new User())->setPassword($userId, $newPassword, false);

        // Re-issue the session so any parallel session is invalidated.
        Session::regenerate();

        Audit::log('Profile', 'Password Reset', [
            'entity_type'  => 'User',
            'entity_id'    => $userId,
            'entity_label' => Auth::user()['full_name'] ?? null,
            'description'  => 'Changed own password',
        ]);

        Auth::refresh();

        Flash::success('Your password has been changed.');
        $this->redirect('/dashboard');
    }

    public function activity(): void
    {
        $this->view('users/activity', [
            'pageTitle'   => 'My Activity',
            'breadcrumbs' => [['label' => 'My Profile', 'url' => '/profile'], ['label' => 'My Activity']],
            'activity'    => (new User())->activity(Auth::id(), 200),
        ]);
    }

    private function groupedPermissions(): array
    {
        $codes = Auth::permissions();
        if ($codes === [] && !Auth::isSuperAdmin()) {
            return [];
        }

        $sql = 'SELECT * FROM permissions';
        $bindings = [];
        if (!Auth::isSuperAdmin()) {
            [$holders, $bindings] = \JanKavach\Core\Database::inClause($codes, 'p');
            $sql .= " WHERE code IN ({$holders})";
        }

        $grouped = [];
        foreach ($this->db->select($sql . ' ORDER BY module, sort_order', $bindings) as $permission) {
            $grouped[$permission['module']][] = $permission;
        }
        return $grouped;
    }
}
