<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Model;
use JanKavach\Core\Paginator;

/**
 * Audit trail reader (specification section 29).
 *
 * Writing is handled by JanKavach\Core\Audit; this model only reads, and
 * restricts what a non-Super-Administrator can see to their own geography's
 * users plus their own activity.
 */
class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected bool $timestamps = false;

    public const SORTS = [
        'time'   => 'a.created_at',
        'user'   => 'a.user_name',
        'module' => 'a.module',
        'action' => 'a.action',
        'entity' => 'a.entity_type',
        'ip'     => 'a.ip_address',
    ];

    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ', a.id DESC';

        return $this->paginateQuery(
            'SELECT a.*, u.full_name AS current_user_name, r.name AS role_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN roles r ON r.code = a.role_code',
            'FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN roles r ON r.code = a.role_code',
            $where, $bindings, $order, $page, $perPage, $queryString
        );
    }

    public function allFiltered(array $filters, int $limit = 20000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            "SELECT a.* FROM audit_logs a WHERE {$where} ORDER BY a.created_at DESC LIMIT " . (int)$limit,
            $bindings
        );
    }

    private function buildFilters(array $filters): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        // Only a Super Administrator sees the whole trail. Others see entries
        // made by users inside their own geography, plus their own.
        if (!Auth::isSuperAdmin()) {
            $scope = Auth::dataScope();
            if ($scope === 'state' && Auth::stateId()) {
                $where[] = '(a.user_id = :self OR a.user_id IN (SELECT id FROM users WHERE state_id = :scope_state))';
                $bindings['self'] = Auth::id();
                $bindings['scope_state'] = Auth::stateId();
            } elseif ($scope === 'district' && Auth::districtId()) {
                $where[] = '(a.user_id = :self OR a.user_id IN (SELECT id FROM users WHERE district_id = :scope_district))';
                $bindings['self'] = Auth::id();
                $bindings['scope_district'] = Auth::districtId();
            } else {
                $where[] = 'a.user_id = :self';
                $bindings['self'] = Auth::id();
            }
        }

        if (!empty($filters['search'])) {
            $where[] = '(a.user_name LIKE :s1 OR a.description LIKE :s2 OR a.entity_label LIKE :s3)';
            $like = '%' . $filters['search'] . '%';
            $bindings['s1'] = $like;
            $bindings['s2'] = $like;
            $bindings['s3'] = $like;
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :f_user';
            $bindings['f_user'] = (int)$filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[] = 'a.module = :f_module';
            $bindings['f_module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = :f_action';
            $bindings['f_action'] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = :f_entity';
            $bindings['f_entity'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[] = 'a.entity_id = :f_entity_id';
            $bindings['f_entity_id'] = (int)$filters['entity_id'];
        }
        if (!empty($filters['ip_address'])) {
            $where[] = 'a.ip_address = :f_ip';
            $bindings['f_ip'] = $filters['ip_address'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= :f_from';
            $bindings['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= :f_to';
            $bindings['f_to'] = $filters['date_to'];
        }
        if (!empty($filters['changes_only'])) {
            $where[] = '(a.old_values IS NOT NULL OR a.new_values IS NOT NULL)';
        }

        return [implode(' AND ', $where), $bindings];
    }

    public function findDetailed(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT a.*, u.full_name AS current_user_name, u.email, r.name AS role_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN roles r ON r.code = a.role_code
              WHERE a.id = :id',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $row['old_decoded'] = json_list($row['old_values']);
        $row['new_decoded'] = json_list($row['new_values']);
        return $row;
    }

    public function modules(): array
    {
        $rows = $this->db->select('SELECT DISTINCT module FROM audit_logs ORDER BY module');
        return array_column($rows, 'module');
    }

    public function actions(): array
    {
        return ['Created', 'Modified', 'Deleted', 'Viewed', 'Login', 'Logout', 'Approved',
                'Rejected', 'Exported', 'Imported', 'Matched', 'Verified', 'Failed Login',
                'Password Reset'];
    }

    public function entityTypes(): array
    {
        $rows = $this->db->select(
            'SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type');
        return array_column($rows, 'entity_type');
    }

    /** Counts by action for the audit summary strip. */
    public function summary(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            "SELECT a.action, COUNT(*) AS n FROM audit_logs a WHERE {$where}
              GROUP BY a.action ORDER BY n DESC",
            $bindings
        );
    }

    /** Failed sign-in attempts, for the security panel. */
    public function recentFailedLogins(int $limit = 20): array
    {
        return $this->db->select(
            'SELECT username, ip_address, user_agent, reason, attempted_at
               FROM login_attempts WHERE was_success = 0
              ORDER BY attempted_at DESC LIMIT ' . (int)$limit
        );
    }
}
