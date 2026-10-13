<?php

namespace JanKavach\Middleware\mobile_api;

use JanKavach\Core\Request;
use JanKavach\Core\Response;
use JanKavach\Services\mobile_api\AuthService;

class AuthMiddleware
{
    private static ?array $user = null;

    /**
     * Middleware entry point.
     */
    public static function handle(Request $request): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        /*
         * Some Apache/PHP setups expose Authorization as REDIRECT_HTTP_AUTHORIZATION.
         */
        if ($header === '') {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (!preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($header),
            $matches
        )) {
            Response::json([
                'success' => false,
                'message' => 'Authorization token is required.',
            ], 401);

            return false;
        }

        $accessToken = trim($matches[1]);

        $service = new AuthService();

        $user = $service->authenticateAccessToken(
            $accessToken
        );

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Invalid or expired access token.',
            ], 401);

            return false;
        }

        self::$user = $user;

        return true;
    }

    /**
     * Get authenticated mobile user.
     */
    public static function user(): ?array
    {
        return self::$user;
    }

    /**
     * Get authenticated user ID.
     */
    public static function id(): ?int
    {
        return self::$user
            ? (int) self::$user['id']
            : null;
    }
}