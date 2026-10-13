<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;

/**
 * User management (specification section 16).
 */
class User extends Model
{
    protected string $table = 'users';
    protected bool $softDelete = true;

    protected array $fillable = [
        'user_code', 'full_name', 'username', 'email', 'mobile', 'password_hash',
        'role_id', 'state_id', 'district_id', 'block_id', 'designation', 'organization',
        'photo_path', 'is_active', 'must_change_password',
    ];

    public const SORTS = [
        'code'     => 'u.user_code',
        'name'     => 'u.full_name',
        'username' => 'u.username',
        'email'    => 'u.email',
        'role'     => 'r.sort_order',
        'state'    => 's.name',
        'district' => 'd.name',
        'status'   => 'u.is_active',
        'created'  => 'u.created_at',
        'login'    => 'u.last_login_at',
    ];

    private const BASE_SELECT =
        'SELECT u.id, u.user_code, u.full_name, u.username, u.email, u.mobile,
                u.designation, u.organization, u.is_active, u.must_change_password,
                u.last_login_at, u.last_login_ip, u.created_at, u.updated_at,
                u.role_id, r.name AS role_name, r.code AS role_code, r.data_scope,
                u.state_id, s.name AS state_name,
                u.district_id, d.name AS district_name,
                u.block_id, bl.name AS block_name,
                cu.full_name AS created_by_name
           FROM users u
           JOIN roles r ON r.id = u.role_id
           LEFT JOIN states s    ON s.id  = u.state_id
           LEFT JOIN districts d ON d.id  = u.district_id
           LEFT JOIN blocks bl   ON bl.id = u.block_id
           LEFT JOIN users cu    ON cu.id = u.created_by';

    private const BASE_FROM =
        'FROM users u
           JOIN roles r ON r.id = u.role_id
           LEFT JOIN states s    ON s.id  = u.state_id
           LEFT JOIN districts d ON d.id  = u.district_id
           LEFT JOIN blocks bl   ON bl.id = u.block_id
           LEFT JOIN users cu    ON cu.id = u.created_by';

    public function paginate(array $filters, int $page, int $perPage, string $sortColumn, string $sortDir, array $queryString = []): Paginator
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $order = $sortColumn . ' ' . ($sortDir === 'DESC' ? 'DESC' : 'ASC') . ', u.id DESC';

        return $this->paginateQuery(self::BASE_SELECT, self::BASE_FROM, $where, $bindings, $order, $page, $perPage, $queryString);
    }

    public function allFiltered(array $filters, int $limit = 5000): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        return $this->db->select(
            self::BASE_SELECT . " WHERE {$where} ORDER BY u.full_name LIMIT " . (int)$limit,
            $bindings
        );
    }

    private function buildFilters(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::userClause('u');
        $where = [$scopeSql, 'u.deleted_at IS NULL'];

        if (!empty($filters['search'])) {
            $where[] = '(u.full_name LIKE :s1 OR u.username LIKE :s2 OR u.email LIKE :s3
                         OR u.mobile LIKE :s4 OR u.user_code LIKE :s5)';
            $like = '%' . $filters['search'] . '%';
            foreach (['s1', 's2', 's3', 's4', 's5'] as $k) {
                $bindings[$k] = $like;
            }
        }
        foreach (['role_id' => 'u.role_id', 'state_id' => 'u.state_id',
                  'district_id' => 'u.district_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $where[] = 'u.is_active = :f_active';
            $bindings['f_active'] = (int)$filters['is_active'];
        }

        return [implode(' AND ', $where), $bindings];
    }

    public function findDetailed(int $id): ?array
    {
        return $this->db->selectOne(self::BASE_SELECT . ' WHERE u.id = :id AND u.deleted_at IS NULL', ['id' => $id]);
    }

    public function roles(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM roles';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY sort_order, name');
    }

    /**
     * Roles the signed-in user may assign.  A State Administrator must not
     * be able to mint a Super Administrator.
     */
    public function assignableRoles(): array
    {
        $roles = $this->roles();
        if (Auth::isSuperAdmin()) {
            return $roles;
        }
        return array_values(array_filter($roles, function ($role) {
            if ($role['code'] === 'SUPER_ADMIN') {
                return false;
            }
            // A State Administrator creates District and Field users only.
            if (Auth::roleCode() === 'STATE_ADMIN') {
                return in_array($role['code'], ['DISTRICT_USER', 'FIELD_USER', 'VIEWER'], true);
            }
            return false;
        }));
    }

    public function nextUserCode(): string
    {
        return $this->nextCode('user_code', 'JK-USR-', 5);
    }

    public function hashPassword(string $plain): string
    {
        return password_hash($plain, Config::get('security.password_algo', PASSWORD_BCRYPT),
                             ['cost' => (int)Config::get('security.password_cost', 11)]);
    }

    /** Readable temporary password for an administrator-driven reset. */
    public function generatePassword(int $length = 10): string
    {
        // Ambiguous characters are left out so the password can be read aloud.
        $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower  = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all    = $upper . $lower . $digits;

        $password = $upper[random_int(0, strlen($upper) - 1)]
                  . $lower[random_int(0, strlen($lower) - 1)]
                  . $digits[random_int(0, strlen($digits) - 1)];

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($password);
    }

    public function setPassword(int $userId, string $plain, bool $mustChange = false): void
    {
        $this->db->update($this->table, [
            'password_hash'        => $this->hashPassword($plain),
            'must_change_password' => $mustChange ? 1 : 0,
            'failed_attempts'      => 0,
            'locked_until'         => null,
            'remember_token'       => null,
            'remember_expires'     => null,
        ], ['id' => $userId]);
    }

    /** Field users a coordinator can pick from, inside their own scope. */
    public function fieldUsers(?int $stateId = null, ?int $districtId = null): array
    {
        [$scopeSql, $bindings] = Scope::userClause('u');
        $where = [$scopeSql, 'u.deleted_at IS NULL', 'u.is_active = 1'];

        if ($stateId) {
            $where[] = 'u.state_id = :fs';
            $bindings['fs'] = $stateId;
        }
        if ($districtId) {
            $where[] = 'u.district_id = :fd';
            $bindings['fd'] = $districtId;
        }

        return $this->db->select(
            'SELECT u.id, u.full_name, u.username, r.name AS role_name, d.name AS district_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
               LEFT JOIN districts d ON d.id = u.district_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY u.full_name',
            $bindings
        );
    }

    public function performance(array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::userClause('p', 'user_id');
        $where = [$scopeSql];

        if (!empty($filters['state_id'])) {
            $where[] = 'p.state_id = :f_state';
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'p.district_id = :f_district';
            $bindings['f_district'] = (int)$filters['district_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'p.user_id = :f_user';
            $bindings['f_user'] = (int)$filters['user_id'];
        }
        if (!empty($filters['role_name'])) {
            $where[] = 'p.role_name = :f_role';
            $bindings['f_role'] = $filters['role_name'];
        }
        // Only the roles that actually collect field data are meaningful here.
        $where[] = "p.role_name IN ('Field User','District Coordinator')";

        return $this->db->select(
            'SELECT * FROM vw_field_user_performance p WHERE ' . implode(' AND ', $where) . '
              ORDER BY beneficiaries_registered DESC, full_name',
            $bindings
        );
    }

    /** Recent audit entries for a user's own activity log. */
    public function activity(int $userId, int $limit = 60): array
    {
        return $this->db->select(
            'SELECT * FROM audit_logs WHERE user_id = :id ORDER BY created_at DESC LIMIT ' . (int)$limit,
            ['id' => $userId]
        );
    }

    /** Records that would be orphaned if the user were removed. */
    public function usageCount(int $userId): array
    {
        return [
            'beneficiaries' => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiaries WHERE field_user_id = :id AND deleted_at IS NULL', ['id' => $userId]),
            'applications'  => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiary_scheme_applications WHERE assigned_to = :id AND deleted_at IS NULL', ['id' => $userId]),
            'followups'     => (int)$this->db->scalar('SELECT COUNT(*) FROM application_followups WHERE responsible_user_id = :id', ['id' => $userId]),
        ];
    }
}
