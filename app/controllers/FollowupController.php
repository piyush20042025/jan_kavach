<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\Application;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Followup;
use JanKavach\Models\Geography;
use JanKavach\Models\User;

/**
 * Follow-up management (specification section 22).
 */
class FollowupController extends Controller
{
    private Followup $followups;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->followups = new Followup();
        $this->geo = new Geography();
    }

    public function index(): void
    {
        $filters = $this->followupFilters();
        [$column, $dir, $sortKey] = $this->sort(Followup::SORTS, 'date', 'DESC');

        $paginator = $this->followups->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('followups/index', [
            'pageTitle'   => 'Follow-ups',
            'breadcrumbs' => [['label' => 'Follow-ups']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'due'         => $this->followups->dueSummary($filters),
            'dueLists'    => [
                'overdue'  => $this->followups->dueList('overdue', 8, $filters),
                'today'    => $this->followups->dueList('today', 8, $filters),
                'upcoming' => $this->followups->dueList('upcoming', 8, $filters),
            ],
            'types'     => $this->followups->types(),
            'outcomes'  => $this->followups->outcomes(),
            'states'    => $this->geo->stateOptions(),
            'districts' => $this->geo->districtOptions($filters['state_id'] ?? null),
            'users'     => (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null),
        ]);
    }

    public function calendar(): void
    {
        $filters = $this->followupFilters();

        $this->view('followups/calendar', [
            'pageTitle'   => 'Follow-up Calendar',
            'breadcrumbs' => [['label' => 'Follow-ups', 'url' => '/followups'], ['label' => 'Calendar']],
            'due'         => $this->followups->dueSummary($filters),
            'overdue'     => $this->followups->dueList('overdue', 50, $filters),
            'today'       => $this->followups->dueList('today', 50, $filters),
            'upcoming'    => $this->followups->dueList('upcoming', 50, $filters),
            'filters'     => $filters,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
        ]);
    }

    public function show(string $id): void
    {
        $followup = $this->findOrFail($this->followups->findDetailed((int)$id), 'Follow-up');
        $this->assertAccess($followup);

        $this->view('followups/show', [
            'pageTitle'   => 'Follow-up Detail',
            'breadcrumbs' => [['label' => 'Follow-ups', 'url' => '/followups'], ['label' => 'Detail']],
            'followup'    => $followup,
        ]);
    }

    public function create(): void
    {
        $applicationId = $this->request->int('application_id');
        $beneficiaryId = $this->request->int('beneficiary_id');

        $application = null;
        if ($applicationId) {
            $application = (new Application())->findDetailed($applicationId);
            if ($application) {
                Scope::assertAccess(
                    $application['state_id'] ? (int)$application['state_id'] : null,
                    $application['district_id'] ? (int)$application['district_id'] : null
                );
                $beneficiaryId = (int)$application['beneficiary_id'];
            }
        }

        $beneficiary = $beneficiaryId ? (new Beneficiary())->findDetailed($beneficiaryId) : null;
        if ($beneficiary) {
            Scope::assertAccess((int)$beneficiary['state_id'], (int)$beneficiary['district_id'],
                $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null);
        }

        $this->view('followups/form', [
            'pageTitle'   => 'Record Follow-up',
            'breadcrumbs' => [['label' => 'Follow-ups', 'url' => '/followups'], ['label' => 'Record Follow-up']],
            'followup'    => null,
            'application' => $application,
            'beneficiary' => $beneficiary,
            'applications'=> $beneficiaryId ? (new Beneficiary())->applications($beneficiaryId) : [],
            'types'       => $this->followups->types(),
            'outcomes'    => $this->followups->outcomes(),
            'statuses'    => (new Application())->statuses(),
            'users'       => (new User())->fieldUsers(),
            'defaultNextDate' => date('Y-m-d', strtotime('+' . (int)(Config::setting('default_followup_days') ?? 15) . ' days')),
        ]);
    }

    public function store(): void
    {
        $data = $this->validateFollowup(null);

        $id = $this->followups->record($data, Auth::id());

        Audit::log('Follow-ups', 'Created', [
            'entity_type'  => 'Followup',
            'entity_id'    => $id,
            'entity_label' => 'Follow-up on ' . fmt_date($data['followup_date']),
            'description'  => sprintf('%s follow-up recorded for beneficiary #%d (outcome: %s)',
                                      $data['followup_type'], (int)$data['beneficiary_id'], $data['outcome']),
        ]);

        Flash::success('Follow-up recorded.');

        if (!empty($data['application_id'])) {
            $this->redirect('/applications/' . (int)$data['application_id']);
        }
        $this->redirect('/beneficiaries/' . (int)$data['beneficiary_id']);
    }

    public function edit(string $id): void
    {
        $followup = $this->findOrFail($this->followups->findDetailed((int)$id), 'Follow-up');
        $this->assertAccess($followup);

        $this->view('followups/form', [
            'pageTitle'   => 'Edit Follow-up',
            'breadcrumbs' => [['label' => 'Follow-ups', 'url' => '/followups'], ['label' => 'Edit']],
            'followup'    => $followup,
            'application' => $followup['application_id'] ? (new Application())->findDetailed((int)$followup['application_id']) : null,
            'beneficiary' => (new Beneficiary())->findDetailed((int)$followup['beneficiary_id']),
            'applications'=> (new Beneficiary())->applications((int)$followup['beneficiary_id']),
            'types'       => $this->followups->types(),
            'outcomes'    => $this->followups->outcomes(),
            'statuses'    => (new Application())->statuses(),
            'users'       => (new User())->fieldUsers(),
            'defaultNextDate' => $followup['next_followup_date'],
        ]);
    }

    public function update(string $id): void
    {
        $followup = $this->findOrFail($this->followups->findDetailed((int)$id), 'Follow-up');
        $this->assertAccess($followup);

        $data = $this->validateFollowup((int)$id);
        $before = $this->followups->find((int)$id);

        $this->followups->update((int)$id, $data);

        // Keep the application's next follow-up date in step.
        if (!empty($data['application_id'])) {
            $this->db->update('beneficiary_scheme_applications',
                ['next_followup_date' => $data['next_followup_date'] ?? null],
                ['id' => (int)$data['application_id']]);
        }

        $after = $this->followups->find((int)$id);
        Audit::modified('Follow-ups', 'Followup', (int)$id,
            'Follow-up on ' . fmt_date($data['followup_date']), $before ?: [], $after ?: []);

        Flash::success('Follow-up updated.');
        $this->redirect('/followups/' . (int)$id);
    }

    public function destroy(string $id): void
    {
        $followup = $this->findOrFail($this->followups->findDetailed((int)$id), 'Follow-up');
        $this->assertAccess($followup);

        $this->followups->forceDelete((int)$id);
        Audit::deleted('Follow-ups', 'Followup', (int)$id,
            'Follow-up on ' . fmt_date($followup['followup_date']), $followup);

        Flash::success('Follow-up deleted.');
        $this->redirect('/followups');
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->followupFilters();
        [$column, $dir] = $this->sort(Followup::SORTS, 'date', 'DESC');

        $rows = $this->followups->allFiltered($filters, $column, $dir);

        Exporter::send($format, [
            'Follow-up Date' => fn($r) => fmt_date($r['followup_date']),
            'Beneficiary ID' => 'beneficiary_code',
            'Beneficiary'    => 'beneficiary_name',
            'Mobile'         => fn($r) => mask_mobile($r['beneficiary_mobile'] ?? null),
            'Scheme'         => fn($r) => $r['scheme_name'] ?? '-',
            'Application'    => fn($r) => $r['application_code'] ?? '-',
            'Type'           => 'followup_type',
            'Discussion'     => fn($r) => str_limit($r['discussion'] ?? '', 300),
            'Outcome'        => 'outcome',
            'Status After'   => fn($r) => $r['status_name'] ?? '-',
            'Next Follow-up' => fn($r) => fmt_date($r['next_followup_date']),
            'Responsible'    => fn($r) => $r['responsible_name'] ?? '-',
            'State'          => fn($r) => $r['state_name'] ?? '-',
            'District'       => fn($r) => $r['district_name'] ?? '-',
        ], $rows, 'followup-register', [
            'title'       => 'Follow-up Report',
            'module'      => 'Follow-ups',
            'filters'     => $this->describeFilters($filters),
            'orientation' => 'landscape',
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function followupFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'         => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'       => $stateId,
            'district_id'    => $districtId,
            'scheme_id'      => $this->request->int('scheme_id'),
            'beneficiary_id' => $this->request->int('beneficiary_id'),
            'application_id' => $this->request->int('application_id'),
            'status_id'      => $this->request->int('status_id'),
            'responsible_user_id' => $this->request->int('responsible_user_id'),
            'followup_type'  => $this->inOrNull('followup_type', $this->followups->types()),
            'outcome'        => $this->inOrNull('outcome', $this->followups->outcomes()),
            'due'            => $this->inOrNull('due', ['overdue', 'today', 'upcoming']),
            'date_from'      => $this->dateOrNull('date_from'),
            'date_to'        => $this->dateOrNull('date_to'),
        ];
    }

    private function validateFollowup(?int $ignoreId): array
    {
        $validator = Validator::make($this->request->all(), [
            'beneficiary_id'     => 'required|integer|exists:beneficiaries,id',
            'application_id'     => 'nullable|integer|exists:beneficiary_scheme_applications,id',
            'scheme_id'          => 'nullable|integer|exists:schemes,id',
            'followup_date'      => 'required|date|date_before_today',
            'followup_type'      => 'required|in:' . implode(',', $this->followups->types()),
            'outcome'            => 'required|in:' . implode(',', $this->followups->outcomes()),
            'discussion'         => 'nullable|string|max:5000',
            'status_id'          => 'nullable|integer|exists:application_statuses,id',
            'next_followup_date' => 'nullable|date',
            'responsible_user_id'=> 'nullable|integer|exists:users,id',
        ], [
            'beneficiary_id' => 'Beneficiary',
            'followup_date'  => 'Follow-up Date',
            'followup_type'  => 'Follow-up Type',
        ]);

        $fallback = $ignoreId ? '/followups/' . $ignoreId . '/edit' : '/followups/create';

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }

        $beneficiaryId = (int)$this->request->post('beneficiary_id');
        $beneficiary = (new Beneficiary())->find($beneficiaryId);
        if (!$beneficiary) {
            $this->backWithErrors(['beneficiary_id' => 'The selected beneficiary could not be found.'], $fallback);
        }
        Scope::assertAccess((int)$beneficiary['state_id'], (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null);

        $followupDate = $this->dateOrNull('followup_date');
        $nextDate     = $this->dateOrNull('next_followup_date');

        if ($nextDate && $followupDate && strtotime($nextDate) < strtotime($followupDate)) {
            $this->backWithErrors(
                ['next_followup_date' => 'The next follow-up date cannot be before this follow-up.'], $fallback);
        }

        $applicationId = $this->request->int('application_id');
        $schemeId = $this->request->int('scheme_id');

        // Derive the scheme from the application when one was chosen, and
        // confirm the application really belongs to this beneficiary.
        if ($applicationId) {
            $application = (new Application())->find($applicationId);
            if (!$application || (int)$application['beneficiary_id'] !== $beneficiaryId) {
                $this->backWithErrors(
                    ['application_id' => 'The selected application does not belong to this beneficiary.'], $fallback);
            }
            $schemeId = (int)$application['scheme_id'];
        }

        return [
            'application_id'      => $applicationId,
            'beneficiary_id'      => $beneficiaryId,
            'scheme_id'           => $schemeId,
            'followup_date'       => $followupDate,
            'followup_type'       => (string)$this->request->post('followup_type'),
            'discussion'          => $this->request->post('discussion') ?: null,
            'status_id'           => $this->request->int('status_id'),
            'outcome'             => (string)$this->request->post('outcome'),
            'next_followup_date'  => $nextDate,
            'responsible_user_id' => $this->request->int('responsible_user_id') ?: Auth::id(),
            'state_id'            => (int)$beneficiary['state_id'],
            'district_id'         => (int)$beneficiary['district_id'],
        ];
    }

    private function assertAccess(array $followup): void
    {
        Scope::assertAccess(
            $followup['state_id'] !== null ? (int)$followup['state_id'] : null,
            $followup['district_id'] !== null ? (int)$followup['district_id'] : null,
            $followup['created_by'] !== null ? (int)$followup['created_by'] : null
        );
    }

    private function describeFilters(array $filters): array
    {
        $out = [];
        if (!empty($filters['search']))      $out['Search'] = $filters['search'];
        if (!empty($filters['state_id']))    $out['State'] = $this->geo->stateName((int)$filters['state_id']);
        if (!empty($filters['district_id'])) $out['District'] = $this->geo->districtName((int)$filters['district_id']);
        if (!empty($filters['followup_type'])) $out['Type'] = $filters['followup_type'];
        if (!empty($filters['outcome']))     $out['Outcome'] = $filters['outcome'];
        if (!empty($filters['due']))         $out['Due'] = ucfirst($filters['due']);
        if (!empty($filters['date_from']))   $out['From'] = fmt_date($filters['date_from']);
        if (!empty($filters['date_to']))     $out['To'] = fmt_date($filters['date_to']);
        return $out;
    }
}
