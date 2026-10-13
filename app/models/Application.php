<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;

/**
 * Beneficiary-scheme applications and their status pipeline
 * (specification section 21).
 */
class Application extends Model
{
    protected string $table = 'beneficiary_scheme_applications';
    protected bool $softDelete = true;

    protected array $fillable = [
        'application_code', 'beneficiary_id', 'scheme_id', 'eligibility_status', 'status_id',
        'referral_date', 'initiated_date', 'application_number', 'submission_date',
        'department', 'verification_date', 'approval_date', 'rejection_reason',
        'benefit_received', 'benefit_amount', 'benefit_date', 'next_followup_date',
        'remarks', 'assigned_to', 'state_id', 'district_id',
    ];

    public const SORTS = [
        'code'        => 'v.application_code',
        'beneficiary' => 'v.beneficiary_name',
        'scheme'      => 'v.scheme_name',
        'status'      => 'v.stage_order',
        'state'       => 'v.state_name',
        'district'    => 'v.district_name',
        'referral'    => 'v.referral_date',
        'submitted'   => 'v.submission_date',
        'approved'    => 'v.approval_date',
        'followup'    => 'v.next_followup_date',
        'updated'     => 'v.updated_at',
    ];

    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ', v.id DESC';

        return $this->paginateQuery(
            'SELECT v.* FROM vw_application_listing v',
            'FROM vw_application_listing v',
            $where, $bindings, $order, $page, $perPage, $queryString
        );
    }

    public function allFiltered(array $filters, string $sortColumn = 'v.created_at', string $sortDir = 'DESC', int $limit = 20000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            "SELECT v.* FROM vw_application_listing v WHERE {$where}
              ORDER BY {$sortColumn} " . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ' LIMIT ' . (int)$limit,
            $bindings
        );
    }

    private function buildFilters(array $filters): array
    {
        // Owner-scoped: a field user sees applications for the beneficiaries
        // they collected, matching what the beneficiary listing shows them.
        [$scopeSql, $bindings] = Scope::clause('v');
        $where = [$scopeSql];

        if (!empty($filters['search'])) {
            $where[] = '(v.beneficiary_name LIKE :s1 OR v.application_code LIKE :s2
                         OR v.application_number LIKE :s3 OR v.scheme_name LIKE :s4
                         OR v.beneficiary_code LIKE :s5)';
            $like = '%' . $filters['search'] . '%';
            foreach (['s1', 's2', 's3', 's4', 's5'] as $key) {
                $bindings[$key] = $like;
            }
        }
        foreach ([
            'state_id'      => 'v.state_id',
            'district_id'   => 'v.district_id',
            'scheme_id'     => 'v.scheme_id',
            'theme_id'      => 'v.theme_id',
            'status_id'     => 'v.status_id',
            'beneficiary_id'=> 'v.beneficiary_id',
            'assigned_to'   => 'v.assigned_to',
        ] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['funnel_stage'])) {
            $where[] = 'v.funnel_stage = :f_funnel';
            $bindings['f_funnel'] = $filters['funnel_stage'];
        }
        if (!empty($filters['eligibility_status'])) {
            $where[] = 'v.eligibility_status = :f_elig';
            $bindings['f_elig'] = $filters['eligibility_status'];
        }
        if (!empty($filters['scheme_level'])) {
            $where[] = 'v.scheme_level = :f_level';
            $bindings['f_level'] = $filters['scheme_level'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(v.created_at) >= :f_from';
            $bindings['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(v.created_at) <= :f_to';
            $bindings['f_to'] = $filters['date_to'];
        }
        if (!empty($filters['benefit_received'])) {
            $where[] = 'v.benefit_received = 1';
        }
        if (!empty($filters['followup_due'])) {
            $where[] = match ($filters['followup_due']) {
                'overdue'  => 'v.next_followup_date < CURDATE()',
                'today'    => 'v.next_followup_date = CURDATE()',
                'upcoming' => 'v.next_followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
                default    => 'v.next_followup_date IS NOT NULL',
            };
        }
        if (!empty($filters['pending_only'])) {
            $where[] = "v.funnel_stage NOT IN ('Benefit','Closed','Rejected')";
        }

        return [implode(' AND ', $where), $bindings];
    }

    public function findDetailed(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT a.*, b.beneficiary_code, b.name AS beneficiary_name, b.gender, b.age,
                    b.mobile AS beneficiary_mobile, b.aadhaar_last4, b.field_user_id,
                    s.name AS scheme_name, s.short_name AS scheme_short_name, s.scheme_code,
                    s.scheme_level, s.portal_url, s.application_mode, s.helpline_number,
                    s.benefit_description, s.application_process,
                    th.name AS theme_name, th.color AS theme_color,
                    ast.name AS status_name, ast.code AS status_code, ast.color AS status_color,
                    ast.stage_order, ast.funnel_stage,
                    st.name AS state_name, d.name AS district_name,
                    u.full_name AS assigned_to_name, cu.full_name AS created_by_name
               FROM beneficiary_scheme_applications a
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               JOIN schemes s       ON s.id = a.scheme_id
               JOIN application_statuses ast ON ast.id = a.status_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
               LEFT JOIN states st   ON st.id = a.state_id
               LEFT JOIN districts d ON d.id  = a.district_id
               LEFT JOIN users u     ON u.id  = a.assigned_to
               LEFT JOIN users cu    ON cu.id = a.created_by
              WHERE a.id = :id AND a.deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $row['history']   = $this->history($id);
        $row['followups'] = $this->followups($id);
        $row['benefits']  = $this->benefits($id);
        return $row;
    }

    public function history(int $applicationId): array
    {
        return $this->db->select(
            'SELECT h.*, fs.name AS from_status, ts.name AS to_status, ts.color AS to_color,
                    u.full_name AS changed_by_name
               FROM application_status_history h
               LEFT JOIN application_statuses fs ON fs.id = h.from_status_id
               JOIN application_statuses ts      ON ts.id = h.to_status_id
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.application_id = :id
              ORDER BY h.changed_at DESC, h.id DESC',
            ['id' => $applicationId]
        );
    }

    public function followups(int $applicationId): array
    {
        return $this->db->select(
            'SELECT f.*, u.full_name AS responsible_name, ast.name AS status_name
               FROM application_followups f
               LEFT JOIN users u ON u.id = f.responsible_user_id
               LEFT JOIN application_statuses ast ON ast.id = f.status_id
              WHERE f.application_id = :id ORDER BY f.followup_date DESC, f.id DESC',
            ['id' => $applicationId]
        );
    }

    public function benefits(int $applicationId): array
    {
        return $this->db->select(
            'SELECT * FROM benefits_received WHERE application_id = :id ORDER BY received_date DESC',
            ['id' => $applicationId]
        );
    }

    public function nextApplicationCode(): string
    {
        return $this->nextCode('application_code', 'JK-APP-', 6);
    }

    public function statuses(): array
    {
        return $this->db->select(
            'SELECT * FROM application_statuses WHERE is_active = 1 ORDER BY stage_order'
        );
    }

    public function statusById(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM application_statuses WHERE id = :id', ['id' => $id]);
    }

    public function statusByCode(string $code): ?array
    {
        return $this->db->selectOne('SELECT * FROM application_statuses WHERE code = :c', ['c' => $code]);
    }

    /**
     * Move an application to a new status, stamping the date field that
     * belongs to that stage and writing a history row.
     */
    public function changeStatus(int $applicationId, int $toStatusId, ?string $remarks, ?int $userId, array $extra = []): bool
    {
        $application = $this->find($applicationId);
        if (!$application) {
            return false;
        }
        $fromStatusId = (int)$application['status_id'];
        $status = $this->statusById($toStatusId);
        if (!$status) {
            return false;
        }

        $update = array_merge(['status_id' => $toStatusId], $extra);
        $today  = date('Y-m-d');

        // Fill the stage's date only when it is still blank, so a correction
        // to an earlier stage does not overwrite a recorded date.
        switch ($status['code']) {
            case 'INITIATED':
                $update['initiated_date'] = $application['initiated_date'] ?: ($extra['initiated_date'] ?? $today);
                break;
            case 'SUBMITTED':
                $update['submission_date'] = $application['submission_date'] ?: ($extra['submission_date'] ?? $today);
                break;
            case 'UNDER_VERIFICATION':
                $update['verification_date'] = $application['verification_date'] ?: $today;
                break;
            case 'APPROVED':
                $update['approval_date'] = $application['approval_date'] ?: ($extra['approval_date'] ?? $today);
                break;
            case 'BENEFIT_RECEIVED':
                $update['benefit_received'] = 1;
                $update['benefit_date'] = $application['benefit_date'] ?: ($extra['benefit_date'] ?? $today);
                break;
            case 'REJECTED':
                $update['rejection_reason'] = $extra['rejection_reason'] ?? $application['rejection_reason'];
                break;
        }

        $this->db->transaction(function () use ($applicationId, $update, $fromStatusId, $toStatusId, $remarks, $userId) {
            $this->db->update($this->table, $update, ['id' => $applicationId]);
            $this->db->insert('application_status_history', [
                'application_id' => $applicationId,
                'from_status_id' => $fromStatusId ?: null,
                'to_status_id'   => $toStatusId,
                'remarks'        => $remarks ? mb_substr($remarks, 0, 500) : null,
                'changed_by'     => $userId,
            ]);
        });

        $this->refreshBeneficiaryStatus((int)$application['beneficiary_id']);
        return true;
    }

    /** Keep the beneficiary's headline status in step with their applications. */
    public function refreshBeneficiaryStatus(int $beneficiaryId): void
    {
        $row = $this->db->selectOne(
            "SELECT MAX(ast.stage_order) AS best,
                    SUM(a.benefit_received) AS benefits
               FROM beneficiary_scheme_applications a
               JOIN application_statuses ast ON ast.id = a.status_id
              WHERE a.beneficiary_id = :id AND a.deleted_at IS NULL",
            ['id' => $beneficiaryId]
        );
        if (!$row || $row['best'] === null) {
            return;
        }

        $status = (int)$row['benefits'] > 0 ? 'Benefited'
                : ((int)$row['best'] >= 5 ? 'In Process' : 'Matched');

        $this->db->update('beneficiaries', ['record_status' => $status], ['id' => $beneficiaryId]);
    }

    public function recordBenefit(array $data): int
    {
        $id = $this->db->insert('benefits_received', $data);

        $this->db->update($this->table, [
            'benefit_received' => 1,
            'benefit_amount'   => $data['amount'] ?? null,
            'benefit_date'     => $data['received_date'] ?? date('Y-m-d'),
        ], ['id' => (int)$data['application_id']]);

        return $id;
    }

    /** Does this beneficiary already have an application for this scheme? */
    public function existsFor(int $beneficiaryId, int $schemeId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, application_code FROM beneficiary_scheme_applications
              WHERE beneficiary_id = :b AND scheme_id = :s AND deleted_at IS NULL LIMIT 1',
            ['b' => $beneficiaryId, 's' => $schemeId]
        );
    }

    /**
     * Funnel counts (section 5): Identified -> Eligible -> Initiated ->
     * Submitted -> Approved -> Benefit Received.
     */
    public function funnel(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $rows = $this->db->select(
            "SELECT v.funnel_stage, COUNT(*) AS n FROM vw_application_listing v
              WHERE {$where} GROUP BY v.funnel_stage",
            $bindings
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['funnel_stage']] = (int)$row['n'];
        }

        // Each stage of the funnel counts everything at or beyond it.
        $order = ['Identified', 'Eligible', 'Initiated', 'Submitted', 'Approved', 'Benefit'];
        $cumulative = [];
        $running = 0;
        foreach (array_reverse($order) as $stage) {
            $running += $counts[$stage] ?? 0;
            $cumulative[$stage] = $running;
        }

        $labels = [
            'Identified' => 'Identified',
            'Eligible'   => 'Eligible',
            'Initiated'  => 'Application Initiated',
            'Submitted'  => 'Application Submitted',
            'Approved'   => 'Approved',
            'Benefit'    => 'Benefit Received',
        ];

        $out = [];
        foreach ($order as $stage) {
            $out[] = [
                'stage' => $stage,
                'label' => $labels[$stage],
                'count' => $cumulative[$stage] ?? 0,
            ];
        }

        // Rejected and Closed sit outside the funnel but still matter.
        $out[] = ['stage' => 'Rejected', 'label' => 'Rejected', 'count' => $counts['Rejected'] ?? 0, 'aside' => true];
        $out[] = ['stage' => 'Closed',   'label' => 'Closed',   'count' => $counts['Closed'] ?? 0,   'aside' => true];

        return $out;
    }
}
