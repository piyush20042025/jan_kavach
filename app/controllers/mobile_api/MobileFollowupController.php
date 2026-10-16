<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Middleware\mobile_api\AuthMiddleware;
use JanKavach\Services\mobile_api\MobileFollowupService;

class MobileFollowupController extends Controller
{
    private MobileFollowupService $service;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->service = new MobileFollowupService();
    }

    /**
     * GET /api/mobile/user/followup/dashboard
     *
     * Summary cards:
     * - Total Users Registered
     * - Total Applied
     * - Pending (with reason breakdown)
     * - Complete
     */
    public function dashboard(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        try {
            $data = $this->service->dashboardSummary($userId);
            $this->jsonSuccess($data, 'Follow-up dashboard metrics fetched successfully.');
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch dashboard metrics: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/mobile/user/followup/register
     *
     * Paginated follow-up register with search & filters:
     * - search: beneficiary name, beneficiary code, mobile, or scheme
     * - scheme_id: filter by specific scheme
     * - beneficiary_id: filter by specific beneficiary
     * - status: 'Pending', 'Applied', 'Complete'
     * - pending_reason: 'Not applied yet', 'Application pending', 'Document issue', 'Verification pending'
     * - followup_date: exact next followup date (YYYY-MM-DD)
     * - due: 'overdue', 'today', 'upcoming'
     * - page, per_page
     */
    public function register(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        $filters = [
            'search'         => $this->request->filled('search') ? (string) $this->request->input('search') : null,
            'scheme_id'      => $this->request->filled('scheme_id') ? (int) $this->request->input('scheme_id') : null,
            'beneficiary_id' => $this->request->filled('beneficiary_id') ? (int) $this->request->input('beneficiary_id') : null,
            'status'         => $this->request->filled('status') ? (string) $this->request->input('status') : null,
            'pending_reason' => $this->request->filled('pending_reason') ? (string) $this->request->input('pending_reason') : null,
            'followup_date'  => $this->request->filled('followup_date') ? (string) $this->request->input('followup_date') : null,
            'due'            => $this->request->filled('due') ? (string) $this->request->input('due') : null,
        ];

        $page    = (int) $this->request->input('page', 1);
        $perPage = (int) $this->request->input('per_page', 20);

        try {
            $result = $this->service->register($userId, $filters, $page, $perPage);
            $this->jsonSuccess($result, 'Follow-up register records fetched successfully.');
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch follow-up register: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/mobile/user/followup/beneficiaries/{id}
     *
     * Shows beneficiary details, all their eligible schemes, each scheme's
     * independent tracking status, required documents, and application URLs.
     */
    public function beneficiary(string $id): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        $beneficiaryId = (int) $id;
        if ($beneficiaryId <= 0) {
            $this->jsonError('Invalid beneficiary ID.', null, 400);
            return;
        }

        try {
            $data = $this->service->beneficiaryDetail($beneficiaryId, $userId);
            if (!$data) {
                $this->jsonError('Beneficiary not found or does not belong to your account.', null, 404);
                return;
            }

            $this->jsonSuccess($data, 'Beneficiary follow-up details fetched successfully.');
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch beneficiary details: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/mobile/user/followup/record
     *
     * Manually records follow-up status for a scheme:
     * - Scenario A: Not applied yet
     * - Scenario B: Applied
     * - Scenario C: Document issue
     * - Scenario D: Benefit received (Complete)
     */
    public function record(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        $payload = $this->request->all();
        if (!is_array($payload)) {
            $this->jsonError('Invalid request payload.', null, 400);
            return;
        }

        try {
            $result = $this->service->recordFollowup($userId, $payload);
            $this->jsonSuccess($result, 'Follow-up record updated successfully.', 200);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError($e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            $this->jsonError('Failed to save follow-up: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/mobile/user/followup/document-types
     *
     * Master list of active document types for selecting in document issue reports.
     */
    public function documentTypes(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        try {
            $docs = $this->service->documentTypes();
            $this->jsonSuccess($docs, 'Document types fetched successfully.');
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch document types: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/mobile/user/followup/schemes/{id}/documents
     *
     * List of required and optional documents for a specific scheme.
     */
    public function schemeDocuments(string $id): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            $this->jsonError('Unauthorised.', null, 401);
            return;
        }

        $schemeId = (int) $id;
        if ($schemeId <= 0) {
            $this->jsonError('Invalid scheme ID.', null, 400);
            return;
        }

        try {
            $docs = $this->service->schemeDocuments($schemeId);
            $this->jsonSuccess($docs, 'Scheme documents fetched successfully.');
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch scheme documents: ' . $e->getMessage(), null, 500);
        }
    }
}
