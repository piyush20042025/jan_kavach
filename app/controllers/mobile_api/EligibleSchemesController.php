<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Middleware\mobile_api\AuthMiddleware;
use JanKavach\Services\mobile_api\EligibleSchemesService;

class EligibleSchemesController extends Controller
{
    private EligibleSchemesService $service;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->service = new EligibleSchemesService();
    }

    /**
     * POST /api/mobile/user/beneficiaries/{id}/eligible-schemes
     * POST /api/mobile/user/beneficiaries/{id}/eligible-schemes/next
     *
     * Runs scheme eligibility matching for a beneficiary added by the
     * authenticated serviceman and returns Eligible & Potentially Eligible schemes.
     */
    public function match(string $id): void
    {
        $user = AuthMiddleware::user();

        if (!$user || empty($user['id'])) {
            Response::error('Unauthorised.', null, 401);
        }

        $beneficiaryId = (int) $id;

        if ($beneficiaryId <= 0) {
            Response::error('Invalid beneficiary ID.', null, 422);
        }

        $payload = $this->request->all();
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $data = $this->service->evaluateForUser(
                $beneficiaryId,
                (int) $user['id'],
                $payload,
                true
            );
        } catch (\Throwable $e) {
            error_log(
                '[Mobile Eligible Schemes] ' .
                $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );

            Response::error(
                'Failed to evaluate eligible schemes.',
                null,
                500
            );
        }

        if ($data === null) {
            Response::error('Beneficiary not found.', null, 404);
        }

        Response::success(
            $data,
            'Eligible schemes fetched successfully.'
        );
    }

    /**
     * GET /api/mobile/user/beneficiaries/{id}/eligible-schemes
     *
     * Fetches Eligible & Potentially Eligible schemes for a beneficiary
     * (runs matching automatically if not yet matched).
     */
    public function show(string $id): void
    {
        $user = AuthMiddleware::user();

        if (!$user || empty($user['id'])) {
            Response::error('Unauthorised.', null, 401);
        }

        $beneficiaryId = (int) $id;

        if ($beneficiaryId <= 0) {
            Response::error('Invalid beneficiary ID.', null, 422);
        }

        $payload = $this->request->all();
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $data = $this->service->evaluateForUser(
                $beneficiaryId,
                (int) $user['id'],
                $payload,
                false
            );
        } catch (\Throwable $e) {
            error_log(
                '[Mobile Eligible Schemes] ' .
                $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );

            Response::error(
                'Failed to fetch eligible schemes.',
                null,
                500
            );
        }

        if ($data === null) {
            Response::error('Beneficiary not found.', null, 404);
        }

        Response::success(
            $data,
            'Eligible schemes fetched successfully.'
        );
    }
}
