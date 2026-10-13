<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Models\Application;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Followup;

/**
 * Application and follow-up endpoints.
 */
class ApiApplicationController extends Controller
{
    private Application $applications;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->applications = new Application();
    }

    public function index(): void
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        $filters = [
            'search'         => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'       => $stateId,
            'district_id'    => $districtId,
            'scheme_id'      => $this->request->int('scheme_id'),
            'status_id'      => $this->request->int('status_id'),
            'beneficiary_id' => $this->request->int('beneficiary_id'),
            'pending_only'   => $this->request->bool('pending_only'),
        ];

        $paginator = $this->applications->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            min(100, Paginator::perPageFrom($this->request)),
            'v.updated_at', 'DESC'
        );

        $this->jsonSuccess([
            'applications' => array_map(fn($a) => [
                'id'            => (int)$a['id'],
                'code'          => $a['application_code'],
                'beneficiary'   => ['id' => (int)$a['beneficiary_id'], 'code' => $a['beneficiary_code'], 'name' => $a['beneficiary_name']],
                'scheme'        => ['id' => (int)$a['scheme_id'], 'name' => $a['scheme_short_name'] ?: $a['scheme_name']],
                'status'        => ['id' => (int)$a['status_id'], 'name' => $a['status_name'], 'funnel' => $a['funnel_stage']],
                'eligibility'   => $a['eligibility_status'],
                'submitted_on'  => $a['submission_date'],
                'approved_on'   => $a['approval_date'],
                'benefit_received' => (bool)$a['benefit_received'],
                'next_followup' => $a['next_followup_date'],
            ], $paginator->items),
            'meta'   => $paginator->toArray()['meta'],
            'funnel' => $this->applications->funnel($filters),
        ]);
    }

    public function show(string $id): void
    {
        $application = $this->applications->findDetailed((int)$id);
        if (!$application) {
            $this->jsonError('Application not found.', null, 404);
        }
        $this->assertAccess($application);

        $this->jsonSuccess([
            'id'          => (int)$application['id'],
            'code'        => $application['application_code'],
            'beneficiary' => [
                'id'   => (int)$application['beneficiary_id'],
                'code' => $application['beneficiary_code'],
                'name' => $application['beneficiary_name'],
                'mobile' => mask_mobile($application['beneficiary_mobile']),
            ],
            'scheme' => [
                'id'         => (int)$application['scheme_id'],
                'code'       => $application['scheme_code'],
                'name'       => $application['scheme_name'],
                'portal_url' => $application['portal_url'],
                'helpline'   => $application['helpline_number'],
            ],
            'status'      => ['id' => (int)$application['status_id'], 'code' => $application['status_code'], 'name' => $application['status_name']],
            'eligibility' => $application['eligibility_status'],
            'dates' => [
                'referral'     => $application['referral_date'],
                'initiated'    => $application['initiated_date'],
                'submitted'    => $application['submission_date'],
                'verification' => $application['verification_date'],
                'approved'     => $application['approval_date'],
                'benefit'      => $application['benefit_date'],
                'next_followup'=> $application['next_followup_date'],
            ],
            'application_number' => $application['application_number'],
            'benefit' => [
                'received' => (bool)$application['benefit_received'],
                'amount'   => $application['benefit_amount'] !== null ? (float)$application['benefit_amount'] : null,
            ],
            'rejection_reason' => $application['rejection_reason'],
            'history'   => $application['history'],
            'followups' => $application['followups'],
        ]);
    }

    public function store(): void
    {
        $validator = Validator::make($this->request->all(), [
            'beneficiary_id' => 'required|integer|exists:beneficiaries,id',
            'scheme_id'      => 'required|integer|exists:schemes,id',
            'status_id'      => 'nullable|integer|exists:application_statuses,id',
        ], ['beneficiary_id' => 'Beneficiary', 'scheme_id' => 'Scheme']);

        if ($validator->fails()) {
            $this->jsonError('Validation failed.', $validator->firstErrors(), 422);
        }

        $beneficiaryId = (int)$this->request->input('beneficiary_id');
        $schemeId      = (int)$this->request->input('scheme_id');

        $beneficiary = (new Beneficiary())->find($beneficiaryId);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        Scope::assertAccess((int)$beneficiary['state_id'], (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null);

        $existing = $this->applications->existsFor($beneficiaryId, $schemeId);
        if ($existing) {
            $this->jsonError('An application already exists for this beneficiary and scheme.', [
                'application_id' => (int)$existing['id'],
                'code'           => $existing['application_code'],
            ], 409);
        }

        $statusId = $this->request->int('status_id');
        if (!$statusId) {
            $default = $this->applications->statusByCode('IDENTIFIED');
            $statusId = $default ? (int)$default['id'] : null;
        }
        if (!$statusId) {
            $this->jsonError('No application status is configured.', null, 500);
        }

        $applicationId = $this->db->transaction(function () use ($beneficiaryId, $schemeId, $statusId, $beneficiary) {
            $id = $this->applications->create([
                'application_code'   => $this->applications->nextApplicationCode(),
                'beneficiary_id'     => $beneficiaryId,
                'scheme_id'          => $schemeId,
                'eligibility_status' => $this->request->input('eligibility_status') ?: 'Potentially Eligible',
                'status_id'          => $statusId,
                'referral_date'      => $this->dateOrNull('referral_date') ?: date('Y-m-d'),
                'next_followup_date' => $this->dateOrNull('next_followup_date'),
                'assigned_to'        => Auth::id(),
                'state_id'           => (int)$beneficiary['state_id'],
                'district_id'        => (int)$beneficiary['district_id'],
            ]);

            $this->db->insert('application_status_history', [
                'application_id' => $id,
                'to_status_id'   => $statusId,
                'remarks'        => 'Created through the API',
                'changed_by'     => Auth::id(),
            ]);
            return $id;
        });

        $this->applications->refreshBeneficiaryStatus($beneficiaryId);

        Audit::created('API', 'Application', $applicationId,
            $this->applications->find($applicationId)['application_code'] ?? null, [],
            'Application created through the API');

        $this->jsonSuccess(['id' => $applicationId], 'Application created.');
    }

    public function updateStatus(string $id): void
    {
        $application = $this->applications->findDetailed((int)$id);
        if (!$application) {
            $this->jsonError('Application not found.', null, 404);
        }
        $this->assertAccess($application);

        $validator = Validator::make($this->request->all(), [
            'status_id' => 'required|integer|exists:application_statuses,id',
            'remarks'   => 'nullable|string|max:500',
        ], ['status_id' => 'Status']);

        if ($validator->fails()) {
            $this->jsonError('Validation failed.', $validator->firstErrors(), 422);
        }

        $statusId = (int)$this->request->input('status_id');
        $status = $this->applications->statusById($statusId);

        $extra = [];
        if ($status && $status['code'] === 'REJECTED') {
            $reason = trim((string)$this->request->input('rejection_reason'));
            if ($reason === '') {
                $this->jsonError('A rejection reason is required when setting the status to Rejected.',
                    ['rejection_reason' => 'Required'], 422);
            }
            $extra['rejection_reason'] = mb_substr($reason, 0, 500);
        }
        if ($this->request->filled('next_followup_date')) {
            $extra['next_followup_date'] = $this->dateOrNull('next_followup_date');
        }

        $this->applications->changeStatus((int)$id, $statusId,
            $this->request->input('remarks'), Auth::id(), $extra);

        Audit::log('API', 'Modified', [
            'entity_type'  => 'Application',
            'entity_id'    => (int)$id,
            'entity_label' => $application['application_code'],
            'description'  => 'Status changed to "' . ($status['name'] ?? '?') . '" through the API',
        ]);

        $this->jsonSuccess([
            'id'     => (int)$id,
            'status' => $status['name'] ?? null,
        ], 'Status updated.');
    }

    public function addFollowup(string $id): void
    {
        $application = $this->applications->findDetailed((int)$id);
        if (!$application) {
            $this->jsonError('Application not found.', null, 404);
        }
        $this->assertAccess($application);

        $followups = new Followup();

        $validator = Validator::make($this->request->all(), [
            'followup_date' => 'required|date|date_before_today',
            'followup_type' => 'required|in:' . implode(',', $followups->types()),
            'outcome'       => 'required|in:' . implode(',', $followups->outcomes()),
            'discussion'    => 'nullable|string|max:5000',
            'next_followup_date' => 'nullable|date',
        ], ['followup_date' => 'Follow-up Date']);

        if ($validator->fails()) {
            $this->jsonError('Validation failed.', $validator->firstErrors(), 422);
        }

        $followupId = $followups->record([
            'application_id'      => (int)$id,
            'beneficiary_id'      => (int)$application['beneficiary_id'],
            'scheme_id'           => (int)$application['scheme_id'],
            'followup_date'       => $this->dateOrNull('followup_date'),
            'followup_type'       => (string)$this->request->input('followup_type'),
            'discussion'          => $this->request->input('discussion') ?: null,
            'status_id'           => $this->request->int('status_id'),
            'outcome'             => (string)$this->request->input('outcome'),
            'next_followup_date'  => $this->dateOrNull('next_followup_date'),
            'responsible_user_id' => Auth::id(),
            'state_id'            => $application['state_id'] ? (int)$application['state_id'] : null,
            'district_id'         => $application['district_id'] ? (int)$application['district_id'] : null,
        ], Auth::id());

        Audit::created('API', 'Followup', $followupId,
            'Follow-up on ' . fmt_date($this->dateOrNull('followup_date')), [],
            'Follow-up recorded through the API');

        $this->jsonSuccess(['id' => $followupId], 'Follow-up recorded.');
    }

    public function followupsDue(): void
    {
        $bucket = $this->inOrNull('bucket', ['overdue', 'today', 'upcoming']) ?? 'overdue';
        $limit  = min(100, max(1, $this->request->int('limit', 25) ?? 25));

        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        $followups = new Followup();
        $filters = ['state_id' => $stateId, 'district_id' => $districtId];

        $this->jsonSuccess([
            'bucket'  => $bucket,
            'summary' => $followups->dueSummary($filters),
            'items'   => array_map(fn($f) => [
                'application_id' => (int)$f['id'],
                'code'           => $f['application_code'],
                'beneficiary'    => ['id' => (int)$f['beneficiary_id'], 'name' => $f['beneficiary_name']],
                'mobile'         => mask_mobile($f['mobile'] ?? null),
                'scheme'         => $f['scheme_name'],
                'status'         => $f['status_name'],
                'due_on'         => $f['next_followup_date'],
                'days_overdue'   => (int)$f['days_overdue'],
                'district'       => $f['district_name'],
            ], $followups->dueList($bucket, $limit, $filters)),
        ]);
    }

    private function assertAccess(array $application): void
    {
        Scope::assertAccess(
            $application['state_id'] !== null ? (int)$application['state_id'] : null,
            $application['district_id'] !== null ? (int)$application['district_id'] : null,
            isset($application['field_user_id']) && $application['field_user_id'] ? (int)$application['field_user_id'] : null
        );
    }
}
