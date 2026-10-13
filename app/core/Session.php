<?php
namespace JanKavach\Core;

/**
 * Session handling with hardened cookie flags, idle timeout and periodic
 * id regeneration (defence against session fixation / hijacking).
 */
class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cfg = Config::get('session');

        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => 0,                       // session cookie
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool)$cfg['cookie_secure'],
            'httponly' => (bool)$cfg['cookie_httponly'],
            'samesite' => $cfg['cookie_samesite'],
        ]);

        // Never accept a session id supplied in the URL.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');

        session_start();
        self::$started = true;

        self::enforceIdleTimeout((int)$cfg['lifetime']);
        self::rotateId((int)$cfg['regenerate_every']);
    }

    /**
     * Destroy the session when it has been idle longer than the limit.
     * Records a flag so the login page can explain what happened.
     */
    private static function enforceIdleTimeout(int $lifetime): void
    {
        if (!isset($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = time();
            return;
        }

        if (time() - (int)$_SESSION['_last_activity'] > $lifetime) {
            $wasLoggedIn = isset($_SESSION['user_id']);
            self::destroy();
            session_start();
            self::$started = true;
            if ($wasLoggedIn) {
                $_SESSION['_timed_out'] = true;
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    private static function rotateId(int $every): void
    {
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
            return;
        }
        if (time() - (int)$_SESSION['_created_at'] > $every) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Read and remove in one step (used for flash messages). */
    public static function pull(string $key, $default = null)
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$started = false;
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }
}
