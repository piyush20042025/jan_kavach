<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Controller;
use JanKavach\Core\Csrf;
use JanKavach\Core\Database;
use JanKavach\Core\Flash;
use JanKavach\Core\Logger;
use JanKavach\Core\Response;
use JanKavach\Core\Session;
use JanKavach\Core\Validator;
use JanKavach\Models\User;

/**
 * Authentication: login, logout and password reset
 * (specification section 3).
 */
class AuthController extends Controller
{
    /** Landing route: straight to the dashboard when signed in. */
    public function root(): void
    {
        $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    // =================================================================
    // Login
    // =================================================================

    public function showLogin(): void
    {
        $this->bare('auth/login', [
            'timedOut'    => (bool)Session::pull('_timed_out'),
            'pageTitle'   => 'Sign In',
            'intendedUrl' => Session::get('_intended_url'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $validator = Validator::make($this->request->all(), [
            'username' => 'required|string|max:150',
            'password' => 'required|string|max:200',
        ], [
            'username' => 'Username or Email',
            'password' => 'Password',
        ]);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please enter your username and password.');
            Flash::setOldInput(['username' => $this->request->post('username')]);
            $this->redirect('/login');
        }

        $result = Auth::attempt(
            (string)$this->request->post('username'),
            (string)$this->request->post('password'),
            $this->request->bool('remember')
        );

        if (!$result['success']) {
            Flash::error($result['message']);
            Flash::setOldInput(['username' => $this->request->post('username')]);
            $this->redirect('/login');
        }

        Flash::success($result['message']);

        // A pending password change takes priority over everything else.
        if (!empty($result['user']['must_change_password'])) {
            $this->redirect('/profile/password');
        }

        // Return the user to wherever they were headed before the redirect.
        $intended = Session::pull('_intended_url');
        if ($intended && $this->isSafeInternalUrl($intended)) {
            Response::redirect($intended);
        }
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::info('You have been signed out.');
        $this->redirect('/login');
    }

    // =================================================================
    // Forgot / reset password
    // =================================================================

    public function showForgotPassword(): void
    {
        $this->bare('auth/forgot-password', ['pageTitle' => 'Forgot Password'], 'layouts/auth');
    }

    /**
     * Issue a reset token.
     *
     * The response is deliberately identical whether or not the address is
     * registered, so this page cannot be used to enumerate accounts.
     */
    public function sendResetLink(): void
    {
        $validator = Validator::make($this->request->all(), [
            'email' => 'required|email|max:150',
        ], ['email' => 'Email Address']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please enter a valid email address.');
            $this->redirect('/forgot-password');
        }

        $email = (string)$this->request->post('email');
        $user  = $this->db->selectOne(
            'SELECT id, full_name, email, is_active FROM users
              WHERE email = :e AND deleted_at IS NULL LIMIT 1',
            ['e' => $email]
        );

        $token = null;
        if ($user && (int)$user['is_active'] === 1) {
            // Only the hash is stored, so the table cannot be replayed.
            $token = bin2hex(random_bytes(32));
            $lifetime = (int)Config::get('security.reset_token_lifetime', 3600);

            $this->db->execute('DELETE FROM password_resets WHERE user_id = :u AND used_at IS NULL',
                ['u' => (int)$user['id']]);

            $this->db->insert('password_resets', [
                'user_id'    => (int)$user['id'],
                'token_hash' => hash('sha256', $token),
                'ip_address' => $this->request->ip(),
                'expires_at' => date('Y-m-d H:i:s', time() + $lifetime),
            ]);

            Audit::log('Authentication', 'Password Reset', [
                'entity_type'  => 'User',
                'entity_id'    => (int)$user['id'],
                'entity_label' => $user['full_name'],
                'description'  => 'Password reset link requested',
            ]);

            $this->dispatchResetEmail($user, $token);
        } else {
            Logger::info('Password reset requested for an unknown or inactive address', ['email' => $email]);
        }

        Flash::info('If that email address is registered with Jan Kavach, a password reset link has been sent to it. '
                  . 'The link is valid for one hour.');

        // With mail disabled (the default for this prototype) the link is
        // surfaced on screen so the flow can be completed and demonstrated.
        if ($token !== null && !Config::get('mail.enabled')) {
            Flash::warning('Email delivery is switched off in this environment. '
                . 'Reset link: ' . url('/reset-password/' . $token));
        }

        $this->redirect('/forgot-password');
    }

    public function showResetPassword(string $token): void
    {
        $reset = $this->findValidReset($token);
        if (!$reset) {
            Flash::error('That password reset link is invalid or has expired. Please request a new one.');
            $this->redirect('/forgot-password');
        }

        $this->bare('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'token'     => $token,
            'fullName'  => $reset['full_name'],
        ], 'layouts/auth');
    }

    public function resetPassword(): void
    {
        $token = (string)$this->request->post('token');
        $reset = $this->findValidReset($token);

        if (!$reset) {
            Flash::error('That password reset link is invalid or has expired. Please request a new one.');
            $this->redirect('/forgot-password');
        }

        $validator = Validator::make($this->request->all(), [
            'password' => 'required|password|confirmed',
        ], ['password' => 'New Password']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please choose a stronger password.');
            $this->redirect('/reset-password/' . $token);
        }

        $userModel = new User();
        $this->db->transaction(function () use ($userModel, $reset, $token) {
            $userModel->setPassword((int)$reset['user_id'], (string)$this->request->post('password'), false);
            $this->db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => (int)$reset['id']]);
        });

        Audit::log('Authentication', 'Password Reset', [
            'entity_type'  => 'User',
            'entity_id'    => (int)$reset['user_id'],
            'entity_label' => $reset['full_name'],
            'description'  => 'Password reset completed using an emailed link',
        ]);

        Flash::success('Your password has been reset. Please sign in with your new password.');
        $this->redirect('/login');
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function findValidReset(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->db->selectOne(
            'SELECT pr.id, pr.user_id, u.full_name, u.email
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = :hash
                AND pr.used_at IS NULL
                AND pr.expires_at > NOW()
                AND u.is_active = 1 AND u.deleted_at IS NULL
              LIMIT 1',
            ['hash' => hash('sha256', $token)]
        );
    }

    /**
     * Send the reset email when mail is enabled. Kept in one place so a
     * real transport can replace mail() without touching the flow.
     */
    private function dispatchResetEmail(array $user, string $token): void
    {
        if (!Config::get('mail.enabled')) {
            return;
        }
        $appName = Config::get('app.name');
        $link    = url('/reset-password/' . $token);

        $subject = $appName . ' - Password Reset';
        $body    = "Dear " . $user['full_name'] . ",\r\n\r\n"
                 . "A password reset was requested for your {$appName} account.\r\n\r\n"
                 . "Open this link to set a new password (valid for one hour):\r\n{$link}\r\n\r\n"
                 . "If you did not request this, no action is needed - your password has not changed.\r\n\r\n"
                 . $appName;

        $headers = 'From: ' . Config::get('mail.from_name') . ' <' . Config::get('mail.from_email') . ">\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        if (!@mail($user['email'], $subject, $body, $headers)) {
            Logger::error('Password reset email could not be sent', ['user_id' => $user['id']]);
        }
    }

    /** Only allow redirects back into this application. */
    private function isSafeInternalUrl(string $url): bool
    {
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }
        // Reject anything absolute or protocol-relative.
        if (preg_match('#^([a-z][a-z0-9+.\-]*:)?//#i', $url)) {
            return false;
        }
        return str_starts_with($url, '/');
    }
}
