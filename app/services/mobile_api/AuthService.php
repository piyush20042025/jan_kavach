<?php

namespace JanKavach\Services\mobile_api;

use JanKavach\Core\Database;
use JanKavach\Core\Request;
use JanKavach\Models\MobileUser;

class AuthService
{
    private const ACCESS_TOKEN_TTL = 900;          // 15 minutes
    private const REFRESH_TOKEN_TTL = 2592000;     // 30 days
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    private Database $db;
    private MobileUser $users;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->users = new MobileUser();
    }

    /**
     * Login using username + password.
     */
    public function login(
        string $username,
        string $password,
        string $language,
        Request $request
    ): array {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Username and password are required.',
                    '???????? ?? ??????? ?????? ????'
                ),
                'status' => 422,
            ];
        }

        $user = $this->users->findForLogin($username);

        /*
         * Do not reveal whether the username exists.
         */
        if (!$user) {
            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'unknown user'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Invalid username or password.',
                    '???????? ?? ??????? ??? ???'
                ),
                'status' => 401,
            ];
        }

        /*
         * Account lock check.
         */
        if (
            $user['locked_until'] !== null &&
            strtotime($user['locked_until']) > time()
        ) {
            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'account locked'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Account is temporarily locked. Please try again later.',
                    '?????? ??????? ??? ?? ??? ??? ????? ??? ??? ?????? ?????'
                ),
                'status' => 429,
            ];
        }

        /*
         * Password verification.
         */
        if (!password_verify($password, $user['password_hash'])) {
            $this->registerFailure((int) $user['id']);

            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'bad password'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Invalid username or password.',
                    '???????? ?? ??????? ??? ???'
                ),
                'status' => 401,
            ];
        }

        /*
         * Account status.
         */
        if ((int) $user['is_active'] !== 1) {
            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'inactive account'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Your account is inactive.',
                    '???? ?????? ????????? ???'
                ),
                'status' => 403,
            ];
        }

        /*
         * Role status.
         */
        if ((int) $user['role_active'] !== 1) {
            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'inactive role'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Your role is inactive.',
                    '???? ?????? ????????? ???'
                ),
                'status' => 403,
            ];
        }

        /**
         * Mobile API role restriction.
         * Only role_id = 4 users are allowed to login.
         */
        if ((int) $user['role_id'] !== 4) {
            $this->recordLoginAttempt(
                $username,
                $request,
                false,
                'unauthorized mobile role'
            );

            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'You are not authorized to use the mobile application.',
                    '???? ?????? ????????? ???????? ???? ?? ?????? ???? ???'
                ),
                'status' => 403,
            ];
        }

        /*
         * Successful login.
         */
        $this->resetFailures((int) $user['id']);

        $this->recordLoginAttempt(
            $username,
            $request,
            true,
            null
        );

        $tokens = $this->createTokens(
            (int) $user['id'],
            $request
        );

        return [
            'success' => true,
            'message' => $this->message(
                $language,
                'Login successful.',
                '????? ??? ????'
            ),
            'status' => 200,
            'data' => [
                'user' => $this->publicUser($user),
                'tokens' => $tokens,
            ],
        ];
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refresh(
        string $refreshToken,
        string $language,
        Request $request
    ): array {
        $refreshToken = trim($refreshToken);

        if ($refreshToken === '') {
            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Refresh token is required.',
                    '??????? ???? ?????? ???'
                ),
                'status' => 422,
            ];
        }

        $hash = hash('sha256', $refreshToken);

        $tokenRow = $this->db->selectOne(
            'SELECT *
             FROM mobile_api_tokens
             WHERE refresh_token_hash = :hash
               AND revoked_at IS NULL
               AND refresh_expires_at > NOW()
             LIMIT 1',
            [
                'hash' => $hash
            ]
        );

        if (!$tokenRow) {
            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'Invalid or expired refresh token.',
                    '??????? ???? ?????? ?? ?????? ?? ??? ???'
                ),
                'status' => 401,
            ];
        }

        $user = $this->users->findActive(
            (int) $tokenRow['user_id']
        );

        if (!$user) {
            return [
                'success' => false,
                'message' => $this->message(
                    $language,
                    'User account is no longer active.',
                    '????? ?????? ?? ?????? ???? ???'
                ),
                'status' => 401,
            ];
        }

        /*
         * Token rotation:
         * Old refresh token is invalidated by replacing both hashes.
         */
        $newTokens = $this->generateTokenPair();

        $this->db->update(
            'mobile_api_tokens',
            [
                'access_token_hash' => $newTokens['access_token_hash'],
                'refresh_token_hash' => $newTokens['refresh_token_hash'],
                'access_expires_at' => $newTokens['access_expires_at'],
                'refresh_expires_at' => $newTokens['refresh_expires_at'],
                'last_used_at' => date('Y-m-d H:i:s'),
                'ip_address' => $request->ip(),
            ],
            [
                'id' => (int) $tokenRow['id']
            ]
        );

        return [
            'success' => true,
            'message' => $this->message(
                $language,
                'Token refreshed successfully.',
                '???? ??????????? ??????? ?? ????'
            ),
            'status' => 200,
            'data' => [
                'access_token' => $newTokens['access_token'],
                'refresh_token' => $newTokens['refresh_token'],
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_TTL,
            ],
        ];
    }

    /**
     * Logout mobile user by revoking the current access token.
     */
    public function logout(string $accessToken): bool
    {
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            return false;
        }

        $hash = hash('sha256', $accessToken);

        $this->db->update(
            'mobile_api_tokens',
            [
                'revoked_at' => date('Y-m-d H:i:s'),
                'last_used_at' => date('Y-m-d H:i:s'),
            ],
            [
                'access_token_hash' => $hash,
            ]
        );

        return true;
    }

    /**
     * Validate access token.
     */
    public function authenticateAccessToken(
        string $accessToken
    ): ?array {
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            return null;
        }

        $hash = hash('sha256', $accessToken);

        $tokenRow = $this->db->selectOne(
            'SELECT user_id
             FROM mobile_api_tokens
             WHERE access_token_hash = :hash
               AND revoked_at IS NULL
               AND access_expires_at > NOW()
             LIMIT 1',
            [
                'hash' => $hash
            ]
        );

        if (!$tokenRow) {
            return null;
        }

        $user = $this->users->findActive(
            (int) $tokenRow['user_id']
        );

        if (!$user) {
            return null;
        }

        return $user;
    }

    /**
     * Generate access + refresh tokens.
     */
    private function createTokens(
        int $userId,
        Request $request
    ): array {
        $tokens = $this->generateTokenPair();

        $this->db->insert(
            'mobile_api_tokens',
            [
                'user_id' => $userId,
                'access_token_hash' => $tokens['access_token_hash'],
                'refresh_token_hash' => $tokens['refresh_token_hash'],
                'access_expires_at' => $tokens['access_expires_at'],
                'refresh_expires_at' => $tokens['refresh_expires_at'],
                'ip_address' => $request->ip(),
            ]
        );

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL,
        ];
    }

    private function generateTokenPair(): array
    {
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,

            'access_token_hash' => hash(
                'sha256',
                $accessToken
            ),

            'refresh_token_hash' => hash(
                'sha256',
                $refreshToken
            ),

            'access_expires_at' => date(
                'Y-m-d H:i:s',
                time() + self::ACCESS_TOKEN_TTL
            ),

            'refresh_expires_at' => date(
                'Y-m-d H:i:s',
                time() + self::REFRESH_TOKEN_TTL
            ),
        ];
    }

    private function registerFailure(int $userId): void
    {
        $user = $this->db->selectOne(
            'SELECT failed_attempts
             FROM users
             WHERE id = :id
             LIMIT 1',
            [
                'id' => $userId
            ]
        );

        if (!$user) {
            return;
        }

        $attempts = (int) $user['failed_attempts'] + 1;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->db->update(
                'users',
                [
                    'failed_attempts' => $attempts,
                    'locked_until' => date(
                        'Y-m-d H:i:s',
                        time() + (self::LOCKOUT_MINUTES * 60)
                    ),
                ],
                [
                    'id' => $userId
                ]
            );

            return;
        }

        $this->db->update(
            'users',
            [
                'failed_attempts' => $attempts,
            ],
            [
                'id' => $userId
            ]
        );
    }

    private function resetFailures(int $userId): void
    {
        $this->db->update(
            'users',
            [
                'failed_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => $userId
            ]
        );
    }

    private function recordLoginAttempt(
        string $username,
        Request $request,
        bool $success,
        ?string $reason
    ): void {
        $this->db->insert(
            'login_attempts',
            [
                'username' => $username,
                'ip_address' => $request->ip(),
                'was_success' => $success ? 1 : 0,
                'reason' => $reason,
            ]
        );
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'user_code' => $user['user_code'],
            'name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'mobile' => $user['mobile'],
            'designation' => $user['designation'],
            'organization' => $user['organization'],
            'role' => [
                'code' => $user['role_code'],
                'name' => $user['role_name'],
            ],
        ];
    }

    private function message(
        string $language,
        string $english,
        string $hindi
    ): string {
        return strtolower($language) === 'hi'
            ? $hindi
            : $english;
    }
}