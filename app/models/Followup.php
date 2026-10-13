<?php
namespace JanKavach\Models;

use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;

/**
 * Follow-up register (specification section 22).
 */
class Followup extends Model
{
    protected string $table = 'application_followups';

    protected array $fillable = [
        'application_id', 'beneficiary_id', 'scheme_id', 'followup_date', 'followup_type',
        'discussion', 'status_id', 'outcome', 'next_followup_date', 'responsible_user_id',
        'state_id', 'district_id',
    ];

    public const SORTS = [
        'date'        => 'f.followup_date',
        'beneficiary' => 'b.name',
        'scheme'      => 's.name',
        'type'        => 'f.followup_type',
        'outcome'     => 'f.outcome',
        'next'        => 'f.next_followup_date',
        'district'    => 'd.name',
        'user'        => 'u.full_name',
    ];

    private const BASE_SELECT =
        'SELECT f.*, b.name AS beneficiary_name, b.beneficiary_code, b.mobile AS beneficiary_mobile,
                s.name AS scheme_name, s.short_name AS scheme_short_name,
                a.application_code, ast.name AS status_name, ast.color AS status_color,
                u.full_name AS responsible_name, cu.full_name AS created_by_name,
                st.name AS state_name, d.name AS district_name
           FROM application_followups f
           JOIN beneficiaries b ON b.id = f.beneficiary_id
           LEFT JOIN schemes s  ON s.id = f.scheme_id
           LEFT JOIN beneficiary_scheme_applications a ON a.id = f.application_id
           LEFT JOIN application_statuses ast ON ast.id = f.status_id
           LEFT JOIN users u  ON u.id  = f.responsible_user_id
           LEFT JOIN users cu ON cu.id = f.created_by
           LEFT JOIN states st ON st.id = f.state_id
           LEFT JOIN districts d ON d.id = f.district_id';

    private const BASE_FROM =
        'FROM application_followups f
           JOIN beneficiaries b ON b.id = f.beneficiary_id
           LEFT JOIN schemes s  ON s.id = f.scheme_id
           LEFT JOIN beneficiary_scheme_applications a ON a.id = f.application_id
           LEFT JOIN application_statuses ast ON ast.id = f.status_id
           LEFT JOIN users u  ON u.id  = f.responsible_user_id
           LEFT JOIN users cu ON cu.id = f.created_by
           LEFT JOIN states st ON st.id = f.state_id
           LEFT JOIN districts d ON d.id = f.district_id';

    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ', f.id DESC';

        return $this->paginateQuery(self::BASE_SELECT, self::BASE_FROM, $where, $bindings, $order, $page, $perPage, $queryString);
    }

    public function allFiltered(array $filters, string $sortColumn = 'f.followup_date', string $sortDir = 'DESC', int $limit = 20000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            self::BASE_SELECT . " WHERE {$where} ORDER BY {$sortColumn} "
            . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ' LIMIT ' . (int)$limit,
            $bindings
        );
    }

    private function buildFilters(array $filters): array
    {
        // The follow-up itself carries state/district, so it can be scoped
        // directly - but fall back to the beneficiary where it is blank.
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        if (!empty($filters['search'])) {
            $where[] = '(b.name LIKE :s1 OR b.beneficiary_code LIKE :s2 OR f.discussion LIKE :s3 OR s.name LIKE :s4)';
            $like = '%' . $filters['search'] . '%';
            foreach (['s1', 's2', 's3', 's4'] as $k) {
                $bindings[$k] = $like;
            }
        }
        foreach ([
            'state_id'       => 'f.state_id',
            'district_id'    => 'f.district_id',
            'scheme_id'      => 'f.scheme_id',
            'beneficiary_id' => 'f.beneficiary_id',
            'application_id' => 'f.application_id',
            'status_id'      => 'f.status_id',
            'responsible_user_id' => 'f.responsible_user_id',
        ] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['followup_type'])) {
            $where[] = 'f.followup_type = :f_type';
            $bindings['f_type'] = $filters['followup_type'];
        }
        if (!empty($filters['outcome'])) {
            $where[] = 'f.outcome = :f_outcome';
            $bindings['f_outcome'] = $filters['outcome'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'f.followup_date >= :f_from';
            $bindings['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'f.followup_date <= :f_to';
            $bindings['f_to'] = $filters['date_to'];
        }
        if (!empty($filters['due'])) {
            $where[] = match ($filters['due']) {
                'overdue'  => 'f.next_followup_date < CURDATE()',
                'today'    => 'f.next_followup_date = CURDATE()',
                'upcoming' => 'f.next_followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
                default    => '1 = 1',
            };
        }

        return [implode(' AND ', $where), $bindings];
    }

    public function findDetailed(int $id): ?array
    {
        return $this->db->selectOne(self::BASE_SELECT . ' WHERE f.id = :id', ['id' => $id]);
    }

    /**
     * Follow-ups pending across the scoped geography, bucketed for the
     * dashboard: overdue / today / upcoming.
     */
    public function dueSummary(array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'a.deleted_at IS NULL', 'b.deleted_at IS NULL',
                  'a.next_followup_date IS NOT NULL', 'a.benefit_received = 0'];

        if (!empty($filters['state_id'])) {
            $where[] = 'a.state_id = :d_state';
            $bindings['d_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'a.district_id = :d_district';
            $bindings['d_district'] = (int)$filters['district_id'];
        }

        $sql = 'SELECT
                  SUM(CASE WHEN a.next_followup_date <  CURDATE() THEN 1 ELSE 0 END) AS overdue,
                  SUM(CASE WHEN a.next_followup_date =  CURDATE() THEN 1 ELSE 0 END) AS today,
                  SUM(CASE WHEN a.next_followup_date >  CURDATE()
                            AND a.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS upcoming,
                  COUNT(*) AS total
                FROM beneficiary_scheme_applications a
                JOIN beneficiaries b ON b.id = a.beneficiary_id
               WHERE ' . implode(' AND ', $where);

        $row = $this->db->selectOne($sql, $bindings) ?: [];
        return [
            'overdue'  => (int)($row['overdue'] ?? 0),
            'today'    => (int)($row['today'] ?? 0),
            'upcoming' => (int)($row['upcoming'] ?? 0),
            'total'    => (int)($row['total'] ?? 0),
        ];
    }

    /** The actual applications needing a follow-up, for the dashboard list. */
    public function dueList(string $bucket = 'overdue', int $limit = 10, array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'a.deleted_at IS NULL', 'b.deleted_at IS NULL',
                  'a.next_followup_date IS NOT NULL', 'a.benefit_received = 0'];

        $where[] = match ($bucket) {
            'today'    => 'a.next_followup_date = CURDATE()',
            'upcoming' => 'a.next_followup_date > CURDATE() AND a.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            default    => 'a.next_followup_date < CURDATE()',
        };

        if (!empty($filters['state_id'])) {
            $where[] = 'a.state_id = :l_state';
            $bindings['l_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'a.district_id = :l_district';
            $bindings['l_district'] = (int)$filters['district_id'];
        }

        return $this->db->select(
            'SELECT a.id, a.application_code, a.next_followup_date,
                    DATEDIFF(CURDATE(), a.next_followup_date) AS days_overdue,
                    b.id AS beneficiary_id, b.name AS beneficiary_name, b.mobile,
                    COALESCE(s.short_name, s.name) AS scheme_name,
                    ast.name AS status_name, ast.color AS status_color,
                    d.name AS district_name, u.full_name AS assigned_to_name
               FROM beneficiary_scheme_applications a
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               JOIN schemes s       ON s.id = a.scheme_id
               JOIN application_statuses ast ON ast.id = a.status_id
               LEFT JOIN districts d ON d.id = a.district_id
               LEFT JOIN users u     ON u.id = a.assigned_to
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.next_followup_date ASC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    /**
     * Save a follow-up, and push the linked application's status and next
     * follow-up date forward so the two never disagree.
     */
    public function record(array $data, ?int $userId): int
    {
        return $this->db->transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;
            $id = $this->db->insert($this->table, $this->filterFillable($data) + ['created_by' => $userId]);

            if (!empty($data['application_id'])) {
                $update = ['next_followup_date' => $data['next_followup_date'] ?? null];
                if (!empty($data['status_id'])) {
                    $update['status_id'] = (int)$data['status_id'];
                }
                $this->db->update('beneficiary_scheme_applications', $update, ['id' => (int)$data['application_id']]);

                if (!empty($data['status_id'])) {
                    $this->db->insert('application_status_history', [
                        'application_id' => (int)$data['application_id'],
                        'from_status_id' => null,
                        'to_status_id'   => (int)$data['status_id'],
                        'remarks'        => 'Updated during follow-up on ' . ($data['followup_date'] ?? date('Y-m-d')),
                        'changed_by'     => $userId,
                    ]);
                }
            }
            return $id;
        });
    }

    public function types(): array
    {
        return ['Phone Call', 'Home Visit', 'Department Visit', 'Document Collection',
                'SMS/WhatsApp', 'Camp', 'Other'];
    }

    public function outcomes(): array
    {
        return ['Resolved', 'Pending', 'Escalated', 'Not Reachable', 'Rescheduled'];
    }
}
