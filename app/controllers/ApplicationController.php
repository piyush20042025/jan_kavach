<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\Exporter;
use JanKavach\Libraries\Notifier;
use JanKavach\Models\Application;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;
use JanKavach\Models\User;

/**
 * Scheme application tracking (specification section 21).
 */
class ApplicationController extends Controller
{
    private Application $applications;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->applications = new Application();
        $this->geo = new Geography();
    }

    public function index(): void
    {
        $filters = $this->applicationFilters();
        [$column, $dir, $sortKey] = $this->sort(Application::SORTS, 'updated', 'DESC');

        $paginator = $this->applications->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('applications/index', [
            'pageTitle'   => 'Scheme Applications',
            'breadcrumbs' => [['label' => 'Applications']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'statuses'    => $this->applications->statuses(),
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'schemes'     => $this->schemeOptions(),
            'funnel'      => $this->applications->funnel($filters),
        ]);
    }

    public function funnel(): void
    {
        $filters = $this->applicationFilters();

        $this->view('applications/funnel', [
            'pageTitle'   => 'Application Funnel',
            'breadcrumbs' => [['label' => 'Applications', 'url' => '/applications'], ['label' => 'Funnel']],
            'funnel'      => $this->applications->funnel($filters),
            'filters'     => $filters,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'schemes'     => $this->schemeOptions(),
        ]);
    }

    public function show(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);

        Audit::viewed('Applications', 'Application', (int)$id, $application['application_code']);

        $this->view('applications/show', [
            'pageTitle'   => $application['application_code'],
            'breadcrumbs' => [
                ['label' => 'Applications', 'url' => '/applications'],
                ['label' => $application['application_code']],
            ],
            'application' => $application,
            'statuses'    => $this->applications->statuses(),
            'assignees'   => (new User())->fieldUsers(
                $application['state_id'] ? (int)$application['state_id'] : null,
                $application['district_id'] ? (int)$application['district_id'] : null),
        ]);
    }

    public function create(): void
    {
        $beneficiaryId = $this->request->int('beneficiary_id');
        $schemeId      = $this->request->int('scheme_id');

        $beneficiary = null;
        if ($beneficiaryId) {
            $beneficiary = (new Beneficiary())->findDetailed($beneficiaryId);
            if ($beneficiary) {
                Scope::assertAccess((int)$beneficiary['state_id'], (int)$beneficiary['district_id'],
                    $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null);
            }
        }

        $this->view('applications/form', [
            'pageTitle'   => 'Initiate Application',
            'breadcrumbs' => [['label' => 'Applications', 'url' => '/applications'], ['label' => 'Initiate Application']],
            'application' => null,
            'beneficiary' => $beneficiary,
            'schemeId'    => $schemeId,
            'statuses'    => $this->applications->statuses(),
            'schemes'     => $this->schemeOptions(),
            'matches'     => $beneficiaryId ? (new Beneficiary())->matches($beneficiaryId, true) : [],
            'assignees'   => (new User())->fieldUsers(),
        ]);
    }

    public function store(): void
    {
        $data = $this->validateApplication(null);

        $existing = $this->applications->existsFor((int)$data['beneficiary_id'], (int)$data['scheme_id']);
        if ($existing) {
            Flash::warning('This beneficiary already has an application for that scheme ('
                . $existing['application_code'] . ').');
            $this->redirect('/applications/' . (int)$existing['id']);
        }

        $applicationId = $this->db->transaction(function () use ($data) {
            $data['application_code'] = $this->applications->nextApplicationCode();
            $id = $this->applications->create($data);

            $this->db->insert('application_status_history', [
                'application_id' => $id,
                'from_status_id' => null,
                'to_status_id'   => (int)$data['status_id'],
                'remarks'        => 'Application record created',
                'changed_by'     => Auth::id(),
            ]);
            return $id;
        });

        $this->applications->refreshBeneficiaryStatus((int)$data['beneficiary_id']);

        Audit::created('Applications', 'Application', $applicationId,
            $this->applications->find($applicationId)['application_code'] ?? null, $data);

        Flash::success('Application record created.');
        $this->redirect('/applications/' . $applicationId);
    }

    public function edit(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);

        $this->view('applications/form', [
            'pageTitle'   => 'Edit Application',
            'breadcrumbs' => [
                ['label' => 'Applications', 'url' => '/applications'],
                ['label' => $application['application_code'], 'url' => '/applications/' . (int)$id],
                ['label' => 'Edit'],
            ],
            'application' => $application,
            'beneficiary' => (new Beneficiary())->findDetailed((int)$application['beneficiary_id']),
            'schemeId'    => (int)$application['scheme_id'],
            'statuses'    => $this->applications->statuses(),
            'schemes'     => $this->schemeOptions(),
            'matches'     => [],
            'assignees'   => (new User())->fieldUsers(
                $application['state_id'] ? (int)$application['state_id'] : null,
                $application['district_id'] ? (int)$application['district_id'] : null),
        ]);
    }

    public function update(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);
        $applicationId = (int)$id;

        $data = $this->validateApplication($applicationId);
        // The beneficiary and scheme of an existing application are fixed.
        unset($data['beneficiary_id'], $data['scheme_id'], $data['application_code']);

        $before = $this->applications->find($applicationId);
        $statusChanged = (int)$before['status_id'] !== (int)$data['status_id'];

        if ($statusChanged) {
            $newStatusId = (int)$data['status_id'];
            unset($data['status_id']);
            $this->applications->update($applicationId, $data);
            $this->applications->changeStatus($applicationId, $newStatusId,
                $this->request->post('status_remarks') ?: 'Updated from the application form', Auth::id(), []);
        } else {
            $this->applications->update($applicationId, $data);
            $this->applications->refreshBeneficiaryStatus((int)$application['beneficiary_id']);
        }

        $after = $this->applications->find($applicationId);
        Audit::modified('Applications', 'Application', $applicationId,
            $application['application_code'], $before ?: [], $after ?: []);

        Flash::success('Application updated.');
        $this->redirect('/applications/' . $applicationId);
    }

    /** Move an application along the pipeline. */
    public function updateStatus(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);
        $applicationId = (int)$id;

        $validator = Validator::make($this->request->all(), [
            'status_id' => 'required|integer|exists:application_statuses,id',
            'remarks'   => 'nullable|string|max:500',
        ], ['status_id' => 'Status']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please choose a status.');
            $this->back('/applications/' . $applicationId);
        }

        $statusId = (int)$this->request->post('status_id');
        $status   = $this->applications->statusById($statusId);

        $extra = [];
        if ($status && $status['code'] === 'REJECTED') {
            $reason = trim((string)$this->request->post('rejection_reason'));
            if ($reason === '') {
                Flash::error('Please record why the application was rejected.');
                $this->back('/applications/' . $applicationId);
            }
            $extra['rejection_reason'] = mb_substr($reason, 0, 500);
        }
        if ($this->request->filled('next_followup_date')) {
            $extra['next_followup_date'] = $this->dateOrNull('next_followup_date');
        }
        if ($this->request->filled('application_number')) {
            $extra['application_number'] = mb_substr((string)$this->request->post('application_number'), 0, 80);
        }

        $before = $this->applications->find($applicationId);
        $this->applications->changeStatus($applicationId, $statusId,
            $this->request->post('remarks'), Auth::id(), $extra);
        $after = $this->applications->find($applicationId);

        Audit::log('Applications', $status && $status['code'] === 'APPROVED' ? 'Approved'
                   : ($status && $status['code'] === 'REJECTED' ? 'Rejected' : 'Modified'), [
            'entity_type'  => 'Application',
            'entity_id'    => $applicationId,
            'entity_label' => $application['application_code'],
            'description'  => 'Status changed from "' . $application['status_name'] . '" to "' . ($status['name'] ?? '?') . '"',
            'old_values'   => $before ?: [],
            'new_values'   => $after ?: [],
        ]);

        // Tell the assigned worker about an outcome they need to act on.
        if ($status && in_array($status['code'], ['APPROVED', 'REJECTED', 'BENEFIT_RECEIVED'], true)) {
            $type = match ($status['code']) {
                'APPROVED' => Notifier::APPLICATION_APPROVED,
                'REJECTED' => Notifier::APPLICATION_REJECTED,
                default    => Notifier::APPLICATION_APPROVED,
            };
            Notifier::send($type, $status['name'] . ': ' . ($application['scheme_short_name'] ?: $application['scheme_name']), [
                'message'     => $application['beneficiary_name'] . ' - ' . $application['application_code'],
                'link'        => '/applications/' . $applicationId,
                'user_id'     => $application['assigned_to'] ? (int)$application['assigned_to'] : null,
                'state_id'    => $application['state_id'] ? (int)$application['state_id'] : null,
                'district_id' => $application['district_id'] ? (int)$application['district_id'] : null,
                'entity_type' => 'Application',
                'entity_id'   => $applicationId,
            ]);
        }

        Flash::success('Status updated to "' . ($status['name'] ?? '') . '".');
        $this->redirect('/applications/' . $applicationId);
    }

    public function recordBenefit(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);
        $applicationId = (int)$id;

        $validator = Validator::make($this->request->all(), [
            'amount'        => 'nullable|numeric|min:0|max:99999999',
            'received_date' => 'required|date|date_before_today',
            'payment_mode'  => 'required|in:DBT,Cheque,Cash,In-kind,Service,Insurance Cover,Other',
            'reference_no'  => 'nullable|string|max:80',
            'instalment_no' => 'nullable|integer|between:1,60',
        ], ['received_date' => 'Benefit Date', 'payment_mode' => 'Payment Mode']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please check the benefit details.');
            $this->back('/applications/' . $applicationId);
        }

        $amount = $this->request->filled('amount') ? (float)$this->request->post('amount') : null;

        $this->applications->recordBenefit([
            'application_id' => $applicationId,
            'beneficiary_id' => (int)$application['beneficiary_id'],
            'scheme_id'      => (int)$application['scheme_id'],
            'benefit_type'   => $this->request->post('benefit_type') ?: null,
            'amount'         => $amount,
            'received_date'  => $this->dateOrNull('received_date'),
            'payment_mode'   => (string)$this->request->post('payment_mode'),
            'reference_no'   => $this->request->post('reference_no') ?: null,
            'instalment_no'  => $this->request->int('instalment_no'),
            'remarks'        => $this->request->post('remarks') ?: null,
            'state_id'       => $application['state_id'] ? (int)$application['state_id'] : null,
            'district_id'    => $application['district_id'] ? (int)$application['district_id'] : null,
            'recorded_by'    => Auth::id(),
        ]);

        // Recording a benefit also advances the application's status.
        $benefitStatus = $this->applications->statusByCode('BENEFIT_RECEIVED');
        if ($benefitStatus && (int)$application['status_id'] !== (int)$benefitStatus['id']) {
            $this->applications->changeStatus($applicationId, (int)$benefitStatus['id'],
                'Benefit recorded', Auth::id(), []);
        }

        Audit::log('Applications', 'Modified', [
            'entity_type'  => 'Application',
            'entity_id'    => $applicationId,
            'entity_label' => $application['application_code'],
            'description'  => 'Benefit recorded' . ($amount !== null ? ' of ' . money($amount) : ''),
        ]);

        Flash::success('Benefit recorded.');
        $this->redirect('/applications/' . $applicationId);
    }

    public function destroy(string $id): void
    {
        $application = $this->findOrFail($this->applications->findDetailed((int)$id), 'Application');
        $this->assertAccess($application);

        $benefits = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM benefits_received WHERE application_id = :id', ['id' => (int)$id]);

        if ($benefits > 0) {
            $message = 'This application has ' . $benefits . ' recorded benefit(s) and cannot be deleted. '
                     . 'Close it instead so the benefit history is preserved.';
            if ($this->request->isAjax()) {
                $this->jsonError($message, null, 409);
            }
            Flash::error($message);
            $this->back('/applications/' . (int)$id);
        }

        $this->applications->delete((int)$id);
        $this->applications->refreshBeneficiaryStatus((int)$application['beneficiary_id']);

        Audit::deleted('Applications', 'Application', (int)$id, $application['application_code'], $application);

        Flash::success('Application deleted.');
        $this->redirect('/applications');
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->applicationFilters();
        [$column, $dir] = $this->sort(Application::SORTS, 'updated', 'DESC');

        $rows = $this->applications->allFiltered($filters, $column, $dir);

        Exporter::send($format, [
            'Application Code'   => 'application_code',
            'Beneficiary ID'     => 'beneficiary_code',
            'Beneficiary'        => 'beneficiary_name',
            'Gender'             => 'gender',
            'Age'                => fn($r) => $r['age'] ?? '-',
            'Mobile'             => fn($r) => mask_mobile($r['mobile'] ?? null),
            'Scheme'             => 'scheme_name',
            'Scheme Level'       => 'scheme_level',
            'Theme'              => fn($r) => $r['theme_name'] ?? '-',
            'State'              => 'state_name',
            'District'           => 'district_name',
            'Eligibility'        => 'eligibility_status',
            'Status'             => 'status_name',
            'Department Number'  => fn($r) => $r['application_number'] ?? '-',
            'Referral Date'      => fn($r) => fmt_date($r['referral_date']),
            'Initiated'          => fn($r) => fmt_date($r['initiated_date']),
            'Submitted'          => fn($r) => fmt_date($r['submission_date']),
            'Approved'           => fn($r) => fmt_date($r['approval_date']),
            'Benefit Received'   => fn($r) => (int)$r['benefit_received'] === 1 ? 'Yes' : 'No',
            'Benefit Amount'     => fn($r) => $r['benefit_amount'] !== null ? (float)$r['benefit_amount'] : '',
            'Benefit Date'       => fn($r) => fmt_date($r['benefit_date']),
            'Rejection Reason'   => fn($r) => $r['rejection_reason'] ?? '',
            'Next Follow-up'     => fn($r) => fmt_date($r['next_followup_date']),
            'Assigned To'        => fn($r) => $r['assigned_to_name'] ?? '-',
        ], $rows, 'scheme-applications', [
            'title'       => 'Scheme Applications Report',
            'module'      => 'Applications',
            'filters'     => $this->describeFilters($filters),
            'orientation' => 'landscape',
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function applicationFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'scheme_id'         => $this->request->int('scheme_id'),
            'theme_id'          => $this->request->int('theme_id'),
            'status_id'         => $this->request->int('status_id'),
            'beneficiary_id'    => $this->request->int('beneficiary_id'),
            'assigned_to'       => $this->request->int('assigned_to'),
            'funnel_stage'      => $this->inOrNull('funnel_stage',
                                    ['Identified', 'Eligible', 'Initiated', 'Submitted', 'Approved', 'Benefit', 'Closed', 'Rejected']),
            'eligibility_status'=> $this->inOrNull('eligibility_status',
                                    ['Eligible', 'Potentially Eligible', 'Needs Verification', 'Not Eligible']),
            'scheme_level'      => $this->inOrNull('scheme_level', ['National', 'State', 'National+State']),
            'followup_due'      => $this->inOrNull('followup_due', ['overdue', 'today', 'upcoming', 'any']),
            'benefit_received'  => $this->request->bool('benefit_received'),
            'pending_only'      => $this->request->bool('pending_only'),
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
        ];
    }

    private function validateApplication(?int $ignoreId): array
    {
        $rules = [
            'beneficiary_id'     => ($ignoreId ? 'nullable' : 'required') . '|integer|exists:beneficiaries,id',
            'scheme_id'          => ($ignoreId ? 'nullable' : 'required') . '|integer|exists:schemes,id',
            'status_id'          => 'required|integer|exists:application_statuses,id',
            'eligibility_status' => 'nullable|in:Eligible,Potentially Eligible,Needs Verification,Not Eligible',
            'referral_date'      => 'nullable|date',
            'initiated_date'     => 'nullable|date',
            'submission_date'    => 'nullable|date',
            'approval_date'      => 'nullable|date',
            'benefit_date'       => 'nullable|date',
            'next_followup_date' => 'nullable|date',
            'application_number' => 'nullable|string|max:80',
            'department'         => 'nullable|string|max:200',
            'benefit_amount'     => 'nullable|numeric|min:0|max:99999999',
            'assigned_to'        => 'nullable|integer|exists:users,id',
            'rejection_reason'   => 'nullable|string|max:500',
        ];

        $validator = Validator::make($this->request->all(), $rules, [
            'beneficiary_id' => 'Beneficiary',
            'scheme_id'      => 'Scheme',
            'status_id'      => 'Status',
        ]);

        $fallback = $ignoreId ? '/applications/' . $ignoreId . '/edit' : '/applications/create';

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }

        // Dates must run in the right order.
        $sequence = [
            'referral_date'   => 'Referral Date',
            'initiated_date'  => 'Initiated Date',
            'submission_date' => 'Submission Date',
            'approval_date'   => 'Approval Date',
            'benefit_date'    => 'Benefit Date',
        ];
        $previousKey = null;
        $previousValue = null;
        foreach ($sequence as $key => $label) {
            $value = $this->dateOrNull($key);
            if ($value === null) {
                continue;
            }
            if ($previousValue !== null && strtotime($value) < strtotime($previousValue)) {
                $this->backWithErrors([
                    $key => $label . ' cannot be earlier than ' . $sequence[$previousKey] . '.',
                ], $fallback);
            }
            $previousKey = $key;
            $previousValue = $value;
        }

        $beneficiaryId = $this->request->int('beneficiary_id');
        $stateId = null;
        $districtId = null;

        if ($beneficiaryId) {
            $beneficiary = (new Beneficiary())->find($beneficiaryId);
            if (!$beneficiary) {
                $this->backWithErrors(['beneficiary_id' => 'The selected beneficiary could not be found.'], $fallback);
            }
            Scope::assertAccess((int)$beneficiary['state_id'], (int)$beneficiary['district_id'],
                $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null);
            $stateId    = (int)$beneficiary['state_id'];
            $districtId = (int)$beneficiary['district_id'];
        } elseif ($ignoreId) {
            $existing = $this->applications->find($ignoreId);
            $stateId    = $existing['state_id'] !== null ? (int)$existing['state_id'] : null;
            $districtId = $existing['district_id'] !== null ? (int)$existing['district_id'] : null;
        }

        return [
            'beneficiary_id'     => $beneficiaryId,
            'scheme_id'          => $this->request->int('scheme_id'),
            'status_id'          => (int)$this->request->post('status_id'),
            'eligibility_status' => $this->request->post('eligibility_status') ?: 'Potentially Eligible',
            'referral_date'      => $this->dateOrNull('referral_date'),
            'initiated_date'     => $this->dateOrNull('initiated_date'),
            'application_number' => $this->request->post('application_number') ?: null,
            'submission_date'    => $this->dateOrNull('submission_date'),
            'department'         => $this->request->post('department') ?: null,
            'verification_date'  => $this->dateOrNull('verification_date'),
            'approval_date'      => $this->dateOrNull('approval_date'),
            'rejection_reason'   => $this->request->post('rejection_reason') ?: null,
            'benefit_received'   => $this->request->bool('benefit_received') ? 1 : 0,
            'benefit_amount'     => $this->request->filled('benefit_amount') ? (float)$this->request->post('benefit_amount') : null,
            'benefit_date'       => $this->dateOrNull('benefit_date'),
            'next_followup_date' => $this->dateOrNull('next_followup_date'),
            'remarks'            => $this->request->post('remarks') ?: null,
            'assigned_to'        => $this->request->int('assigned_to'),
            'state_id'           => $stateId,
            'district_id'        => $districtId,
        ];
    }

    private function schemeOptions(): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        return $this->db->pluck(
            'SELECT s.id, COALESCE(s.short_name, s.name) FROM schemes s
              WHERE ' . $scopeSql . ' AND s.deleted_at IS NULL AND s.is_active = 1
              ORDER BY s.name LIMIT 400',
            $bindings
        );
    }

    private function assertAccess(array $application): void
    {
        Scope::assertAccess(
            $application['state_id'] !== null ? (int)$application['state_id'] : null,
            $application['district_id'] !== null ? (int)$application['district_id'] : null,
            $application['field_user_id'] ?? null ? (int)$application['field_user_id'] : null
        );
    }

    private function describeFilters(array $filters): array
    {
        $out = [];
        if (!empty($filters['search']))      $out['Search'] = $filters['search'];
        if (!empty($filters['state_id']))    $out['State'] = $this->geo->stateName((int)$filters['state_id']);
        if (!empty($filters['district_id'])) $out['District'] = $this->geo->districtName((int)$filters['district_id']);
        if (!empty($filters['status_id'])) {
            $out['Status'] = $this->db->scalar('SELECT name FROM application_statuses WHERE id = :id',
                ['id' => (int)$filters['status_id']]);
        }
        if (!empty($filters['scheme_id'])) {
            $out['Scheme'] = $this->db->scalar('SELECT name FROM schemes WHERE id = :id',
                ['id' => (int)$filters['scheme_id']]);
        }
        if (!empty($filters['date_from'])) $out['From'] = fmt_date($filters['date_from']);
        if (!empty($filters['date_to']))   $out['To'] = fmt_date($filters['date_to']);
        return $out;
    }
}
