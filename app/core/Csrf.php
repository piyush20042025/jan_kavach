<?php
namespace JanKavach\Core;

/**
 * Synchroniser-token CSRF protection.
 *
 * One token per session, rotated on login/logout and after its lifetime
 * expires. Verified with hash_equals() for every state-changing request.
 */
class Csrf
{
    public static function token(): string
    {
        $name     = Config::get('security.csrf_token_name', '_token');
        $lifetime = (int)Config::get('security.csrf_lifetime', 7200);

        $issued = (int)Session::get('_csrf_issued_at', 0);
        if (!Session::has('_csrf_' . $name) || (time() - $issued) > $lifetime) {
            self::regenerate();
        }
        return Session::get('_csrf_' . $name);
    }

    public static function regenerate(): string
    {
        $name  = Config::get('security.csrf_token_name', '_token');
        $token = bin2hex(random_bytes(32));
        Session::set('_csrf_' . $name, $token);
        Session::set('_csrf_issued_at', time());
        return $token;
    }

    /** Timing-safe comparison against the session token. */
    public static function verify(?string $token): bool
    {
        $name    = Config::get('security.csrf_token_name', '_token');
        $session = Session::get('_csrf_' . $name);

        if (!is_string($token) || $token === '' || !is_string($session) || $session === '') {
            return false;
        }
        return hash_equals($session, $token);
    }

    /** Hidden input ready to drop into any form. */
    public static function field(): string
    {
        $name = Config::get('security.csrf_token_name', '_token');
        return sprintf('<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8'));
    }

    /** <meta> tag so AJAX can pick the token up. */
    public static function metaTag(): string
    {
        return sprintf('<meta name="csrf-token" content="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8'));
    }
}
