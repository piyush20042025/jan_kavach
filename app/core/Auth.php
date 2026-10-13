<?php
namespace JanKavach\Core;

/**
 * Session-based authentication plus the permission gate.
 *
 * The signed-in user's role, permission set and geographic assignment are
 * cached in the session at login and refreshed from the database on every
 * request so that revoking a permission or deactivating an account takes
 * effect immediately rather than at next login.
 */
class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;
    private static bool $loaded = false;

    // ------------------------------------------------------------------
    // Login / logout
    // ------------------------------------------------------------------

    /**
     * Attempt a login.
     * Returns ['success' => bool, 'message' => string, 'user' => ?array].
     */
    public static function attempt(string $identifier, string $password, bool $remember = false): array
    {
        $db      = Database::getInstance();
        $request = new Request();
        $ip      = $request->ip();

        // Throttle by IP as well as by username, so attacking many accounts
        // from one host is also limited.
        if (self::isThrottled($identifier, $ip)) {
            $minutes = (int)Config::get('security.lockout_minutes', 15);
            self::recordAttempt($identifier, $ip, false, 'throttled');
            return [
                'success' => false,
                'message' => "Too many failed attempts. Please try again after {$minutes} minutes.",
                'user'    => null,
            ];
        }

        $user = $db->selectOne(
            'SELECT u.*, r.code AS role_code, r.name AS role_name, r.data_scope
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE (u.username = :u OR u.email = :e)
                AND u.deleted_at IS NULL
              LIMIT 1',
            ['u' => $identifier, 'e' => $identifier]
        );

        if (!$user) {
            self::recordAttempt($identifier, $ip, false, 'unknown user');
            // Same wording as a wrong password: do not disclose which failed.
            return ['success' => false, 'message' => 'Invalid username or password.', 'user' => null];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            self::recordAttempt($identifier, $ip, false, 'account locked');
            $until = date(Config::get('app.datetime_format'), strtotime($user['locked_until']));
            return ['success' => false, 'message' => "Account is locked until {$until}.", 'user' => null];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailure($user);
            self::recordAttempt($identifier, $ip, false, 'bad password');
            return ['success' => false, 'message' => 'Invalid username or password.', 'user' => null];
        }

        if ((int)$user['is_active'] !== 1) {
            self::recordAttempt($identifier, $ip, false, 'inactive account');
            return [
                'success' => false,
                'message' => 'Your account is inactive. Please contact the administrator.',
                'user'    => null,
            ];
        }

        if (!$user['role_code'] || (int)$db->scalar('SELECT is_active FROM roles WHERE id = :id', ['id' => (int)$user['role_id']]) !== 1) {
            self::recordAttempt($identifier, $ip, false, 'inactive role');
            return ['success' => false, 'message' => 'Your role has been deactivated. Please contact the administrator.', 'user' => null];
        }

        // Successful login: rotate the session id and reset the counters.
        Session::regenerate();
        Csrf::regenerate();

        $db->update('users', [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $ip,
        ], ['id' => (int)$user['id']]);

        Session::set('user_id', (int)$user['id']);
        Session::set('_login_at', time());
        Session::set('_last_activity', time());
        Session::set('_fingerprint', self::fingerprint($request));

        self::$loaded = false;
        self::$user = null;

        if ($remember) {
            self::setRememberCookie((int)$user['id']);
        }

        self::recordAttempt($identifier, $ip, true, null);

        Audit::log('Authentication', 'Login', [
            'entity_type' => 'User',
            'entity_id'   => (int)$user['id'],
            'entity_label' => $user['full_name'],
            'description' => 'Signed in successfully',
        ]);

        return ['success' => true, 'message' => 'Welcome back, ' . $user['full_name'] . '.', 'user' => self::user()];
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('Authentication', 'Logout', [
                'entity_type'  => 'User',
                'entity_id'    => self::id(),
                'entity_label' => self::user()['full_name'] ?? null,
                'description'  => 'Signed out',
            ]);
            self::clearRememberCookie();
        }
        Session::destroy();
        self::$user = null;
        self::$permissions = null;
        self::$loaded = false;
    }

    // ------------------------------------------------------------------
    // Current user
    // ------------------------------------------------------------------

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    /**
     * The signed-in user, re-read from the database once per request so
     * permission and status changes apply without a re-login.
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $userId = Session::get('user_id');
        if (!$userId) {
            // Fall back to a valid "remember me" cookie.
            $userId = self::userIdFromRememberCookie();
            if ($userId) {
                Session::set('user_id', $userId);
                Session::set('_last_activity', time());
                Session::set('_fingerprint', self::fingerprint(new Request()));
            }
        }
        if (!$userId) {
            return self::$user = null;
        }

        // Binding the session to a coarse client fingerprint makes a stolen
        // cookie much less useful on a different browser.
        $expected = Session::get('_fingerprint');
        if ($expected !== null && $expected !== self::fingerprint(new Request())) {
            Logger::warning('Session fingerprint mismatch', ['user_id' => $userId]);
            Session::destroy();
            return self::$user = null;
        }

        $user = Database::getInstance()->selectOne(
            'SELECT u.id, u.user_code, u.full_name, u.username, u.email, u.mobile,
                    u.role_id, u.state_id, u.district_id, u.block_id,
                    u.designation, u.organization, u.photo_path, u.is_active,
                    u.must_change_password, u.last_login_at, u.last_login_ip,
                    r.code AS role_code, r.name AS role_name, r.data_scope,
                    s.name AS state_name, d.name AS district_name
               FROM users u
               JOIN roles r     ON r.id = u.role_id
               LEFT JOIN states s    ON s.id = u.state_id
               LEFT JOIN districts d ON d.id = u.district_id
              WHERE u.id = :id AND u.deleted_at IS NULL AND u.is_active = 1 AND r.is_active = 1
              LIMIT 1',
            ['id' => (int)$userId]
        );

        if (!$user) {
            // Account was deactivated or deleted mid-session.
            Session::forget('user_id');
            self::clearRememberCookie();
            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function roleCode(): ?string
    {
        return self::user()['role_code'] ?? null;
    }

    public static function dataScope(): string
    {
        return self::user()['data_scope'] ?? 'own';
    }

    public static function isSuperAdmin(): bool
    {
        return self::roleCode() === 'SUPER_ADMIN';
    }

    public static function stateId(): ?int
    {
        $id = self::user()['state_id'] ?? null;
        return $id ? (int)$id : null;
    }

    public static function districtId(): ?int
    {
        $id = self::user()['district_id'] ?? null;
        return $id ? (int)$id : null;
    }

    // ------------------------------------------------------------------
    // Permissions
    // ------------------------------------------------------------------

    /** All permission codes granted to the current user's role. */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $user = self::user();
        if (!$user) {
            return self::$permissions = [];
        }

        $rows = Database::getInstance()->select(
            'SELECT p.code
               FROM role_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = :rid',
            ['rid' => (int)$user['role_id']]
        );
        return self::$permissions = array_column($rows, 'code');
    }

    /**
     * Permission check. Super Administrator passes everything.
     * Supports wildcards: can('scheme.*') or a granted 'scheme.*'.
     */
    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }

        $granted = self::permissions();
        if (in_array($permission, $granted, true)) {
            return true;
        }

        // A granted "module.*" covers every action in that module.
        $module = explode('.', $permission)[0];
        if (in_array($module . '.*', $granted, true)) {
            return true;
        }

        // Asking for "module.*" is satisfied by any permission in the module.
        if (str_ends_with($permission, '.*')) {
            foreach ($granted as $code) {
                if (str_starts_with($code, $module . '.')) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function cannot(string $permission): bool
    {
        return !self::can($permission);
    }

    /** True when the user holds at least one of the given permissions. */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }

    public static function hasRole(string ...$codes): bool
    {
        return in_array(self::roleCode(), $codes, true);
    }

    /** Abort with 403 unless the permission is held. */
    public static function authorize(string $permission, string $message = ''): void
    {
        if (!self::can($permission)) {
            Audit::log('Security', 'Failed Login', [
                'description' => 'Denied access requiring permission: ' . $permission,
            ]);
            Response::abort(403, $message ?: 'You do not have permission to perform this action.');
        }
    }

    // ------------------------------------------------------------------
    // Throttling
    // ------------------------------------------------------------------

    private static function isThrottled(string $identifier, string $ip): bool
    {
        $max    = (int)Config::get('security.max_login_attempts', 5);
        $window = (int)Config::get('security.login_window_minutes', 15);

        $count = (int)Database::getInstance()->scalar(
            'SELECT COUNT(*) FROM login_attempts
              WHERE (username = :u OR ip_address = :ip)
                AND was_success = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :w MINUTE)',
            ['u' => $identifier, 'ip' => $ip, 'w' => $window]
        );
        return $count >= $max;
    }

    private static function registerFailure(array $user): void
    {
        $max      = (int)Config::get('security.max_login_attempts', 5);
        $lockMins = (int)Config::get('security.lockout_minutes', 15);
        $attempts = (int)$user['failed_attempts'] + 1;

        $data = ['failed_attempts' => $attempts];
        if ($attempts >= $max) {
            $data['locked_until'] = date('Y-m-d H:i:s', time() + $lockMins * 60);
            $data['failed_attempts'] = 0;
        }
        Database::getInstance()->update('users', $data, ['id' => (int)$user['id']]);
    }

    private static function recordAttempt(string $identifier, string $ip, bool $success, ?string $reason): void
    {
        try {
            Database::getInstance()->insert('login_attempts', [
                'username'    => substr($identifier, 0, 150),
                'ip_address'  => $ip,
                'user_agent'  => (new Request())->userAgent(),
                'was_success' => $success ? 1 : 0,
                'reason'      => $reason,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Could not record login attempt: ' . $e->getMessage());
        }

        if (!$success) {
            Audit::log('Authentication', 'Failed Login', [
                'entity_type'  => 'User',
                'entity_label' => $identifier,
                'description'  => 'Failed sign-in attempt' . ($reason ? ' (' . $reason . ')' : ''),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // "Remember me"
    // ------------------------------------------------------------------

    /**
     * Store a random selector:validator pair. Only the hash of the
     * validator is persisted, so a database leak cannot be replayed.
     */
    private static function setRememberCookie(int $userId): void
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $lifetime  = (int)Config::get('session.remember_lifetime', 1209600);
        $expires   = time() + $lifetime;

        Database::getInstance()->update('users', [
            'remember_token'   => $selector . ':' . hash('sha256', $validator),
            'remember_expires' => date('Y-m-d H:i:s', $expires),
        ], ['id' => $userId]);

        setcookie('jk_remember', $selector . ':' . $validator, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => (bool)Config::get('session.cookie_secure'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function userIdFromRememberCookie(): ?int
    {
        $cookie = $_COOKIE['jk_remember'] ?? null;
        if (!$cookie || !str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        $row = Database::getInstance()->selectOne(
            'SELECT id, remember_token FROM users
              WHERE remember_token LIKE :sel
                AND remember_expires > NOW()
                AND is_active = 1 AND deleted_at IS NULL
              LIMIT 1',
            ['sel' => $selector . ':%']
        );
        if (!$row) {
            return null;
        }

        $storedHash = explode(':', $row['remember_token'], 2)[1] ?? '';
        if (!hash_equals($storedHash, hash('sha256', $validator))) {
            // Selector matched but validator did not: possible theft.
            Logger::warning('Remember-me validator mismatch', ['user_id' => $row['id']]);
            Database::getInstance()->update('users',
                ['remember_token' => null, 'remember_expires' => null],
                ['id' => (int)$row['id']]);
            return null;
        }
        return (int)$row['id'];
    }

    private static function clearRememberCookie(): void
    {
        if (self::$user) {
            Database::getInstance()->update('users',
                ['remember_token' => null, 'remember_expires' => null],
                ['id' => (int)self::$user['id']]);
        }
        if (isset($_COOKIE['jk_remember'])) {
            setcookie('jk_remember', '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /** Coarse client fingerprint - stable across a session, not across devices. */
    private static function fingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent() . '|' . Config::get('security.aadhaar_pepper'));
    }

    /** Force a fresh read of the cached user (after a profile update). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
        self::$permissions = null;
    }
}
