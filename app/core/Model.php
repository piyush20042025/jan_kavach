<?php
namespace JanKavach\Core;

/**
 * Base model: table-level CRUD, soft deletes, created_by/updated_by stamping
 * and a small query-builder for the filtered listings the UI needs.
 *
 * Sub-classes declare $table, $fillable and (optionally) $softDelete.
 * Only $fillable columns can be written from request data - mass assignment
 * of things like `id` or `created_by` is impossible by construction.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array  $fillable = [];
    protected bool   $softDelete = false;
    protected bool   $timestamps = true;
    protected bool   $stampUsers = true;
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function table(): string
    {
        return $this->table;
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    public function find(int $id, array $columns = ['*']): ?array
    {
        $select = $this->columnList($columns);
        $sql = "SELECT {$select} FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        if ($this->softDelete) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return $this->db->selectOne($sql . ' LIMIT 1', ['id' => $id]);
    }

    /** Find including soft-deleted rows (needed to restore). */
    public function findWithTrashed(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1",
            ['id' => $id]
        );
    }

    public function findBy(string $column, $value, array $columns = ['*']): ?array
    {
        $column = $this->safeColumn($column);
        $select = $this->columnList($columns);
        $sql = "SELECT {$select} FROM `{$this->table}` WHERE `{$column}` = :value";
        if ($this->softDelete) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return $this->db->selectOne($sql . ' LIMIT 1', ['value' => $value]);
    }

    public function all(array $conditions = [], ?string $orderBy = null, ?int $limit = null): array
    {
        [$where, $bindings] = $this->buildWhere($conditions);
        $sql = "SELECT * FROM `{$this->table}` WHERE {$where}";

        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $this->safeOrderBy($orderBy);
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        return $this->db->select($sql, $bindings);
    }

    public function count(array $conditions = []): int
    {
        [$where, $bindings] = $this->buildWhere($conditions);
        return (int)$this->db->scalar("SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}", $bindings);
    }

    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Active rows as id => label, for building <select> options.
     */
    public function options(string $labelColumn = 'name', array $conditions = ['is_active' => 1], ?string $orderBy = null): array
    {
        $labelColumn = $this->safeColumn($labelColumn);
        [$where, $bindings] = $this->buildWhere($conditions);
        $orderBy = $orderBy !== null ? $this->safeOrderBy($orderBy) : "`{$labelColumn}` ASC";

        return $this->db->pluck(
            "SELECT `{$this->primaryKey}`, `{$labelColumn}` FROM `{$this->table}`
              WHERE {$where} ORDER BY {$orderBy}",
            $bindings
        );
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->stampUsers && Auth::check() && $this->hasColumn('created_by')) {
            $data['created_by'] = Auth::id();
            if ($this->hasColumn('updated_by')) {
                $data['updated_by'] = Auth::id();
            }
        }
        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        $data = $this->filterFillable($data);
        if ($data === []) {
            return 0;
        }
        if ($this->stampUsers && Auth::check() && $this->hasColumn('updated_by')) {
            $data['updated_by'] = Auth::id();
        }
        return $this->db->update($this->table, $data, [$this->primaryKey => $id]);
    }

    /** Soft delete where supported, hard delete otherwise. */
    public function delete(int $id): int
    {
        if ($this->softDelete) {
            return $this->db->update($this->table,
                ['deleted_at' => date('Y-m-d H:i:s')],
                [$this->primaryKey => $id]);
        }
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function forceDelete(int $id): int
    {
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function restore(int $id): int
    {
        if (!$this->softDelete) {
            return 0;
        }
        return $this->db->update($this->table, ['deleted_at' => null], [$this->primaryKey => $id]);
    }

    /** Flip an is_active flag and return the new value. */
    public function toggleActive(int $id): ?int
    {
        $row = $this->find($id, ['is_active']);
        if (!$row) {
            return null;
        }
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->update($this->table, ['is_active' => $new], [$this->primaryKey => $id]);
        return $new;
    }

    // ------------------------------------------------------------------
    // Pagination helper used by every listing screen
    // ------------------------------------------------------------------

    /**
     * Run a paginated query.
     *
     * @param string $selectSql  "SELECT ... FROM ... JOIN ..." without WHERE
     * @param string $countFrom  "FROM ... JOIN ..." for the COUNT query
     * @param string $where      WHERE body (already parameterised)
     * @param array  $bindings   bindings for the WHERE
     * @param string $orderBy    validated ORDER BY body
     */
    protected function paginateQuery(
        string $selectSql,
        string $countFrom,
        string $where,
        array $bindings,
        string $orderBy,
        int $page,
        int $perPage,
        array $queryString = [],
        string $groupBy = ''
    ): Paginator {
        $countSql = "SELECT COUNT(*) {$countFrom} WHERE {$where}";
        if ($groupBy !== '') {
            // Counting grouped rows needs a wrapper.
            $countSql = "SELECT COUNT(*) FROM (SELECT 1 {$countFrom} WHERE {$where} {$groupBy}) AS grouped";
        }
        $total = (int)$this->db->scalar($countSql, $bindings);

        $offset = ($page - 1) * $perPage;
        // Re-clamp the page if the caller asked past the end of the result set.
        $lastPage = max(1, (int)ceil($total / $perPage));
        if ($page > $lastPage) {
            $page   = $lastPage;
            $offset = ($page - 1) * $perPage;
        }

        $sql = "{$selectSql} WHERE {$where} {$groupBy} ORDER BY {$orderBy} LIMIT :lim OFFSET :off";
        $rows = $this->db->select($sql, array_merge($bindings, ['lim' => $perPage, 'off' => $offset]));

        return new Paginator($rows, $total, $perPage, $page, $queryString);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** Drop any key that is not declared fillable. */
    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Build a WHERE body from a simple conditions array.
     * Supports ['col' => value], ['col' => null] (IS NULL) and
     * ['col' => ['in' => [...]]] / ['col' => ['op' => '>=', 'value' => 5]].
     */
    protected function buildWhere(array $conditions): array
    {
        $parts    = [];
        $bindings = [];
        $i = 0;

        if ($this->softDelete) {
            $parts[] = 'deleted_at IS NULL';
        }

        foreach ($conditions as $column => $value) {
            $column = $this->safeColumn($column);
            $key    = 'c' . $i++;

            if ($value === null) {
                $parts[] = "`{$column}` IS NULL";
                continue;
            }
            if (is_array($value) && isset($value['in'])) {
                if ($value['in'] === []) {
                    $parts[] = '1 = 0';
                    continue;
                }
                [$holders, $inBindings] = Database::inClause($value['in'], $key);
                $parts[] = "`{$column}` IN ({$holders})";
                $bindings = array_merge($bindings, $inBindings);
                continue;
            }
            if (is_array($value) && isset($value['op'])) {
                $op = $this->safeOperator((string)$value['op']);
                $parts[] = "`{$column}` {$op} :{$key}";
                $bindings[$key] = $value['value'] ?? null;
                continue;
            }
            $parts[] = "`{$column}` = :{$key}";
            $bindings[$key] = $value;
        }

        return [$parts === [] ? '1 = 1' : implode(' AND ', $parts), $bindings];
    }

    protected function columnList(array $columns): string
    {
        if ($columns === ['*'] || $columns === []) {
            return '*';
        }
        return implode(', ', array_map(fn($c) => '`' . $this->safeColumn($c) . '`', $columns));
    }

    protected function safeColumn(string $column): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Unsafe column name [{$column}].");
        }
        return $column;
    }

    protected function safeOperator(string $op): string
    {
        $allowed = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'];
        $op = strtoupper(trim($op));
        if (!in_array($op, $allowed, true)) {
            throw new \InvalidArgumentException("Unsafe SQL operator [{$op}].");
        }
        return $op;
    }

    /** "name ASC" / "created_at DESC" only - anything else is rejected. */
    protected function safeOrderBy(string $orderBy): string
    {
        $pieces = [];
        foreach (explode(',', $orderBy) as $segment) {
            $segment = trim($segment);
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(\s+(ASC|DESC))?$/i', $segment, $m)) {
                throw new \InvalidArgumentException("Unsafe ORDER BY [{$segment}].");
            }
            $pieces[] = '`' . $m[1] . '`' . (isset($m[3]) ? ' ' . strtoupper($m[3]) : '');
        }
        return implode(', ', $pieces);
    }

    protected function hasColumn(string $column): bool
    {
        static $cache = [];
        $key = $this->table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $count = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
            ['t' => $this->table, 'c' => $column]
        );
        return $cache[$key] = ($count > 0);
    }

    /**
     * Generate a sequential code like JK-SCH-0001 from the current max.
     */
    protected function nextCode(string $column, string $prefix, int $pad = 4): string
    {
        $column = $this->safeColumn($column);
        $max = (int)$this->db->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(`{$column}`, :len) AS UNSIGNED)), 0)
               FROM `{$this->table}` WHERE `{$column}` LIKE :prefix",
            ['len' => strlen($prefix) + 1, 'prefix' => $prefix . '%']
        );
        return $prefix . str_pad((string)($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
