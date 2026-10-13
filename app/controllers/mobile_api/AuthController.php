<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Services\mobile_api\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->authService = new AuthService();
    }

    /**
     * POST /api/mobile/user/auth/login
     */
    public function login(): void
    {
        $username = trim(
            (string) $this->request->input('username', '')
        );

        $password = (string) $this->request->input(
            'password',
            ''
        );

        $language = strtolower(
            trim(
                (string) $this->request->input(
                    'language',
                    'en'
                )
            )
        );

        if (!in_array($language, ['en', 'hi'], true)) {
            $language = 'en';
        }

        $result = $this->authService->login(
            $username,
            $password,
            $language,
            $this->request
        );

        if (!$result['success']) {
            $this->jsonError(
                $result['message'],
                null,
                $result['status']
            );
        }

        $this->jsonSuccess(
            $result['data'],
            $result['message']
        );
    }

    /**
     * POST /api/mobile/user/auth/refresh
     */
    public function refresh(): void
    {
        $refreshToken = trim(
            (string) $this->request->input(
                'refresh_token',
                ''
            )
        );

        $language = strtolower(
            trim(
                (string) $this->request->input(
                    'language',
                    'en'
                )
            )
        );

        if (!in_array($language, ['en', 'hi'], true)) {
            $language = 'en';
        }

        $result = $this->authService->refresh(
            $refreshToken,
            $language,
            $this->request
        );

        if (!$result['success']) {
            $this->jsonError(
                $result['message'],
                null,
                $result['status']
            );
        }

        $this->jsonSuccess(
            $result['data'],
            $result['message']
        );
    }
    /**
 * POST /api/mobile/user/auth/logout
 */
public function logout(): void
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        $this->jsonError(
            'Authentication token is required.',
            null,
            401
        );
    }

    $accessToken = trim($matches[1]);

    $this->authService->logout($accessToken);

    $this->jsonSuccess(
        null,
        'Logout successful.'
    );
}
}