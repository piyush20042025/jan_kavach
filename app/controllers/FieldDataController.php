<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Geography;
use JanKavach\Models\User;

/**
 * Field data access and field user performance
 * (specification sections 23 and 27).
 */
class FieldDataController extends Controller
{
    private Beneficiary $beneficiaries;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->beneficiaries = new Beneficiary();
        $this->geo = new Geography();
    }

    /** Field Data > Beneficiary Records (section 23). */
    public function index(): void
    {
        $filters = $this->fieldFilters();
        [$column, $dir, $sortKey] = $this->sort(Beneficiary::SORTS, 'registered', 'DESC');

        $paginator = $this->beneficiaries->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('fielddata/index', [
            'pageTitle'   => 'Field Data - Beneficiary Records',
            'breadcrumbs' => [['label' => 'Field Data']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'fieldUsers'  => (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null),
            'summary'     => $this->collectionSummary($filters),
        ]);
    }

    /** Field User Performance (section 27). */
    public function performance(): void
    {
        $filters = [
            'state_id'    => null,
            'district_id' => null,
            'user_id'     => $this->request->int('user_id'),
            'date_from'   => $this->dateOrNull('date_from'),
            'date_to'     => $this->dateOrNull('date_to'),
        ];
        [$filters['state_id'], $filters['district_id']] =
            Scope::constrainFilters($this->request->int('state_id'), $this->request->int('district_id'));

        $rows = (new User())->performance($filters);

        // Programme totals, so each user's numbers have a denominator.
        $totals = [
            'beneficiaries_registered' => 0,
            'assessments_completed'    => 0,
            'schemes_identified'       => 0,
            'applications_initiated'   => 0,
            'applications_submitted'   => 0,
            'applications_approved'    => 0,
            'benefits_received'        => 0,
            'followups_done'           => 0,
            'field_visits'             => 0,
        ];
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (int)($row[$key] ?? 0);
            }
        }

        $this->view('fielddata/performance', [
            'pageTitle'   => 'Field User Performance',
            'breadcrumbs' => [['label' => 'Field Data', 'url' => '/field-data'], ['label' => 'Performance']],
            'rows'        => $rows,
            'totals'      => $totals,
            'filters'     => $filters,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'users'       => (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null),
        ]);
    }

    /** Field visit log. */
    public function visits(): void
    {
        $filters = $this->fieldFilters();
        [$scopeSql, $bindings] = Scope::clause('b');

        $where = [$scopeSql];
        if (!empty($filters['state_id'])) {
            $where[] = 'fv.state_id = :f_state';
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'fv.district_id = :f_district';
            $bindings['f_district'] = (int)$filters['district_id'];
        }
        if (!empty($filters['field_user_id'])) {
            $where[] = 'fv.field_user_id = :f_user';
            $bindings['f_user'] = (int)$filters['field_user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'fv.visit_date >= :f_from';
            $bindings['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'fv.visit_date <= :f_to';
            $bindings['f_to'] = $filters['date_to'];
        }

        $visits = $this->db->select(
            'SELECT fv.*, b.name AS beneficiary_name, b.beneficiary_code,
                    u.full_name AS field_user_name, d.name AS district_name,
                    bl.name AS block_name, COALESCE(v.name, fv.village_name) AS village_display
               FROM field_visits fv
               LEFT JOIN beneficiaries b ON b.id = fv.beneficiary_id
               LEFT JOIN users u  ON u.id = fv.field_user_id
               LEFT JOIN districts d ON d.id = fv.district_id
               LEFT JOIN blocks bl ON bl.id = fv.block_id
               LEFT JOIN villages v ON v.id = fv.village_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY fv.visit_date DESC, fv.id DESC LIMIT 500',
            $bindings
        );

        $this->view('fielddata/visits', [
            'pageTitle'   => 'Field Visits',
            'breadcrumbs' => [['label' => 'Field Data', 'url' => '/field-data'], ['label' => 'Field Visits']],
            'visits'      => $visits,
            'filters'     => $filters,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'fieldUsers'  => (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null),
        ]);
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $view    = $this->inOrNull('view', ['records', 'performance']) ?? 'records';

        if ($view === 'performance') {
            $filters = [
                'user_id' => $this->request->int('user_id'),
            ];
            [$filters['state_id'], $filters['district_id']] =
                Scope::constrainFilters($this->request->int('state_id'), $this->request->int('district_id'));

            $rows = (new User())->performance($filters);

            Exporter::send($format, [
                'User Code'            => 'user_code',
                'Field User'           => 'full_name',
                'Role'                 => 'role_name',
                'State'                => fn($r) => $r['state_name'] ?? '-',
                'District'             => fn($r) => $r['district_name'] ?? '-',
                'Beneficiaries Registered' => fn($r) => (int)$r['beneficiaries_registered'],
                'Assessments Completed'    => fn($r) => (int)$r['assessments_completed'],
                'Schemes Identified'       => fn($r) => (int)$r['schemes_identified'],
                'Applications Initiated'   => fn($r) => (int)$r['applications_initiated'],
                'Applications Submitted'   => fn($r) => (int)$r['applications_submitted'],
                'Applications Approved'    => fn($r) => (int)$r['applications_approved'],
                'Benefits Received'        => fn($r) => (int)$r['benefits_received'],
                'Follow-ups Recorded'      => fn($r) => (int)$r['followups_done'],
                'Field Visits'             => fn($r) => (int)$r['field_visits'],
                'Active'                   => fn($r) => (int)$r['is_active'] === 1 ? 'Yes' : 'No',
                'Last Login'               => fn($r) => fmt_datetime($r['last_login_at']),
            ], $rows, 'field-user-performance', [
                'title'       => 'Field User Performance Report',
                'module'      => 'Field Data',
                'orientation' => 'landscape',
            ]);
        }

        $filters = $this->fieldFilters();
        [$column, $dir] = $this->sort(Beneficiary::SORTS, 'registered', 'DESC');
        $rows = $this->beneficiaries->allFiltered($filters, $column, $dir);

        Exporter::send($format, [
            'Beneficiary ID'     => 'beneficiary_code',
            'Beneficiary Name'   => 'name',
            'Mobile'             => fn($r) => mask_mobile($r['mobile'] ?? null),
            'State'              => 'state_name',
            'District'           => 'district_name',
            'Village'            => fn($r) => $r['village_name'] ?? '-',
            'Field User'         => fn($r) => $r['field_user_name'] ?? '-',
            'Registration Date'  => fn($r) => fmt_date($r['registration_date']),
            'Schemes Identified' => fn($r) => (int)$r['schemes_identified'],
            'Applications Initiated' => fn($r) => (int)$r['applications_count'],
            'Current Status'     => 'record_status',
        ], $rows, 'field-data-records', [
            'title'       => 'Field Data - Beneficiary Records',
            'module'      => 'Field Data',
            'orientation' => 'landscape',
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function fieldFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'        => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'      => $stateId,
            'district_id'   => $districtId,
            'block_id'      => $this->request->int('block_id'),
            'village_id'    => $this->request->int('village_id'),
            'field_user_id' => $this->request->int('field_user_id'),
            'record_status' => $this->inOrNull('record_status',
                                ['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited', 'Closed', 'Rejected']),
            'assessment_status' => $this->inOrNull('assessment_status', ['Pending', 'In Progress', 'Completed']),
            'date_from'     => $this->dateOrNull('date_from'),
            'date_to'       => $this->dateOrNull('date_to'),
        ];
    }

    private function collectionSummary(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        foreach (['state_id', 'district_id', 'field_user_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "b.{$key} = :s_{$key}";
                $bindings['s_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'b.registration_date >= :s_from';
            $bindings['s_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'b.registration_date <= :s_to';
            $bindings['s_to'] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(b.assessment_status = 'Completed') AS assessed,
                    COUNT(DISTINCT b.field_user_id) AS collectors,
                    COUNT(DISTINCT b.village_id) AS villages,
                    MIN(b.registration_date) AS first_record,
                    MAX(b.registration_date) AS last_record
               FROM beneficiaries b WHERE {$whereSql}",
            $bindings
        ) ?: [];

        return [
            'total'      => (int)($row['total'] ?? 0),
            'assessed'   => (int)($row['assessed'] ?? 0),
            'collectors' => (int)($row['collectors'] ?? 0),
            'villages'   => (int)($row['villages'] ?? 0),
            'first'      => $row['first_record'] ?? null,
            'last'       => $row['last_record'] ?? null,
        ];
    }
}
