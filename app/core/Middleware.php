<?php
namespace JanKavach\Core;

/**
 * Middleware dispatcher.
 *
 * Routes declare middleware as strings, optionally with a colon argument:
 *   'auth'                      - must be signed in
 *   'guest'                     - must NOT be signed in
 *   'csrf'                      - verify the CSRF token on writes
 *   'permission:scheme.create'  - must hold the permission
 *   'role:SUPER_ADMIN,STATE_ADMIN'
 *
 * A middleware returning false halts the request (it has already sent its
 * own response or redirect).
 */
class Middleware
{
    public static function run(string $definition, Request $request): bool
    {
        [$name, $argument] = array_pad(explode(':', $definition, 2), 2, null);

        // return match ($name) {
        //     'auth'       => self::auth($request),
        //     'guest'      => self::guest($request),
        //     'csrf'       => self::csrf($request),
        //     'permission' => self::permission($request, (string)$argument),
        //     'role'       => self::role($request, (string)$argument),
        //     'password'   => self::passwordChanged($request),
        //     'ajax'       => self::ajax($request),
        //     default      => throw new \RuntimeException("Unknown middleware [{$name}]."),
        // };
        
        return match ($name) {
    'auth'        => self::auth($request),
    'guest'       => self::guest($request),
    'csrf'        => self::csrf($request),
    'permission'  => self::permission($request, (string)$argument),
    'role'        => self::role($request, (string)$argument),
    'password'    => self::passwordChanged($request),
    'ajax'        => self::ajax($request),

    'mobile_auth' => \JanKavach\Middleware\mobile_api\AuthMiddleware::handle($request),

    default       => throw new \RuntimeException("Unknown middleware [{$name}]."),
};
    }

    /** Require an authenticated session. */
    private static function auth(Request $request): bool
    {
        if (Auth::check()) {
            return true;
        }

        if ($request->isAjax()) {
            Response::json([
                'success'  => false,
                'message'  => 'Your session has expired. Please sign in again.',
                'redirect' => url('/login'),
            ], 401);
        }

        // Remember where they were headed so login can return them there.
        if ($request->isGet()) {
            Session::set('_intended_url', $request->fullUrl());
        }
        Flash::warning(Session::pull('_timed_out') ? 'Your session timed out. Please sign in again.' : 'Please sign in to continue.');
        Response::to('/login');
        return false;
    }

    /** Keep signed-in users away from the login page. */
    private static function guest(Request $request): bool
    {
        if (!Auth::check()) {
            return true;
        }
        Response::to('/dashboard');
        return false;
    }

    /** Verify the CSRF token for every state-changing request. */
    private static function csrf(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        if (Csrf::verify($request->csrfToken())) {
            return true;
        }

        Logger::warning('CSRF token mismatch', [
            'path' => $request->path(),
            'ip'   => $request->ip(),
            'user' => Auth::id(),
        ]);

        if ($request->isAjax()) {
            Response::json([
                'success' => false,
                'message' => 'Security token expired. Please refresh the page and try again.',
                'csrf'    => true,
            ], 419);
        }

        Flash::error('Security token expired or invalid. Please try again.');
        Response::back('/dashboard');
        return false;
    }

    private static function permission(Request $request, string $permission): bool
    {
        // A comma-separated list means "any of these".
        $needed = array_filter(array_map('trim', explode(',', $permission)));

        if (Auth::canAny($needed)) {
            return true;
        }

        Logger::warning('Permission denied', [
            'needed' => $needed,
            'user'   => Auth::id(),
            'role'   => Auth::roleCode(),
            'path'   => $request->path(),
        ]);

        if ($request->isAjax()) {
            Response::json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
        }
        Response::abort(403, 'You do not have permission to access this section.');
        return false;
    }

    private static function role(Request $request, string $roles): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', $roles)));
        if (Auth::hasRole(...$allowed)) {
            return true;
        }
        if ($request->isAjax()) {
            Response::json(['success' => false, 'message' => 'This action is restricted to other roles.'], 403);
        }
        Response::abort(403, 'This section is restricted to other roles.');
        return false;
    }

    /** Force a password change before anything else can be done. */
    private static function passwordChanged(Request $request): bool
    {
        $user = Auth::user();
        if (!$user || (int)$user['must_change_password'] !== 1) {
            return true;
        }
        // Allow the change-password screen itself through.
        if (str_starts_with($request->path(), '/profile/password') || $request->path() === '/logout') {
            return true;
        }
        Flash::warning('Please set a new password before continuing.');
        Response::to('/profile/password');
        return false;
    }

    private static function ajax(Request $request): bool
    {
        if ($request->isAjax()) {
            return true;
        }
        Response::abort(400, 'This endpoint accepts AJAX requests only.');
        return false;
    }
}
