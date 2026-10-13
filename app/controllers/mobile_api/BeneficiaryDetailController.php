<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Models\MobileBeneficiary;
use JanKavach\Middleware\mobile_api\AuthMiddleware;

class BeneficiaryDetailController extends Controller
{
    private MobileBeneficiary $mobileBeneficiary;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->mobileBeneficiary = new MobileBeneficiary();
    }

    /**
     * GET /api/mobile/user/beneficiaries/{id}
     *
     * Authentication is handled by mobile_auth middleware.
     */
    public function show(int $id): void
    {
        $user = AuthMiddleware::user();

        if (!$user) {
            Response::error(
                'Unauthorised.',
                null,
                401
            );
        }

        $beneficiary = $this->mobileBeneficiary->findForUser(
            $id,
            (int) $user['id']
        );

        if (!$beneficiary) {
            Response::error(
                'Beneficiary not found.',
                null,
                404
            );
        }

        Response::success(
            $beneficiary,
            'Beneficiary fetched successfully.'
        );
    }
}