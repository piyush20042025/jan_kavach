<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Models\MobileBeneficiary;
use JanKavach\Middleware\mobile_api\AuthMiddleware;

class BeneficiaryController extends Controller
{
    private MobileBeneficiary $mobileBeneficiary;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->mobileBeneficiary = new MobileBeneficiary();
    }

    /**
     * GET /api/mobile/user/beneficiaries
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

        // Query parameters
        $search = $_GET['search'] ?? null;

        $villageId = isset($_GET['village_id'])
            ? (int) $_GET['village_id']
            : null;

        $journeyStage = isset($_GET['journey_stage'])
            ? (int) $_GET['journey_stage']
            : null;

        $page = isset($_GET['page'])
            ? (int) $_GET['page']
            : 1;

        $perPage = isset($_GET['per_page'])
            ? (int) $_GET['per_page']
            : 20;

        $data = $this->mobileBeneficiary->listForUser(
            (int) $user['id'],
            $search,
            $villageId,
            $journeyStage,
            $page,
            $perPage
        );

        Response::success(
            $data,
            'Beneficiaries fetched successfully.'
        );
    }
}