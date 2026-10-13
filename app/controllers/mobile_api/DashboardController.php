<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Models\MobileUser;
use JanKavach\Middleware\mobile_api\AuthMiddleware;

class DashboardController extends Controller
{
    private MobileUser $mobileUser;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->mobileUser = new MobileUser();
    }

    /**
     * GET /api/mobile/user/dashboard
     *
     * Authentication is handled by mobile_auth middleware.
     */
    public function index(): void
    {
        $user = AuthMiddleware::user();

        if (!$user) {
            Response::error(
                'Unauthorised.',
                null,
                401
            );
        }

        $data = $this->mobileUser->dashboardData(
            (int) $user['id']
        );

        if (!$data) {
            Response::error(
                'User not found.',
                null,
                404
            );
        }

        Response::success(
            $data,
            'Dashboard data fetched successfully.'
        );
    }
}