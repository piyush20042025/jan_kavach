<?php
namespace JanKavach\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper.
 *
 * Every public method that accepts SQL also accepts a bindings array and
 * runs the statement through prepare()/execute().  There is deliberately
 * no method that concatenates caller input into SQL - identifiers that must
 * be interpolated (ORDER BY columns, for instance) are whitelisted by the
 * calling model before they get here.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private array $config;
    private int $queryCount = 0;
    private array $queryLog = [];

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            $config = require CONFIG_PATH . '/database.php';
            $name   = $config['default'];
            self::$instance = new self($config['connections'][$name]);
        }
        return self::$instance;
    }

    private function connect(): void
    {
        $c   = $this->config;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['database'], $c['charset']);

        try {
            $this->pdo = new PDO($dsn, $c['username'], $c['password'], $c['options']);
            // Keep MySQL from silently truncating bad data.
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            $this->fail('Database connection failed', $e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare + execute. Returns the statement for further fetching.
     */
    public function run(string $sql, array $bindings = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt, $bindings);
            $stmt->execute();

            $this->queryCount++;
            if (Config::get('app.debug')) {
                $this->queryLog[] = ['sql' => $sql, 'bindings' => $bindings];
            }
            return $stmt;
        } catch (PDOException $e) {
            $this->fail('Query failed: ' . $sql, $e);
        }
    }

    /**
     * Bind values with the right PDO type so strict mode stays happy and
     * integer comparisons hit their indexes.
     */
    private function bindValues(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ':') ? $key : ':' . $key);

            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_INT;
                $value = $value ? 1 : 0;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            } else {
                $type = PDO::PARAM_STR;
            }
            $stmt->bindValue($param, $value, $type);
        }
    }

    /** All rows as an array of associative arrays. */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /** First row, or null. */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    /** First column of the first row. */
    public function scalar(string $sql, array $bindings = [])
    {
        $value = $this->run($sql, $bindings)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** key => value pairs from a two-column result. */
    public function pluck(string $sql, array $bindings = []): array
    {
        $out = [];
        foreach ($this->select($sql, $bindings) as $row) {
            $values = array_values($row);
            $out[$values[0]] = $values[1] ?? $values[0];
        }
        return $out;
    }

    /** INSERT and return the new id. */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $columns) . '`',
            implode(', ', array_map(fn($c) => ':' . $c, $columns))
        );
        $this->run($sql, $data);
        return (int)$this->pdo->lastInsertId();
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE for the given columns. */
    public function upsert(string $table, array $data, array $updateColumns = []): int
    {
        $columns = array_keys($data);
        $updateColumns = $updateColumns ?: $columns;
        $updates = [];
        foreach ($updateColumns as $col) {
            $updates[] = sprintf('`%s` = VALUES(`%s`)', $col, $col);
        }
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            '`' . implode('`, `', $columns) . '`',
            implode(', ', array_map(fn($c) => ':' . $c, $columns)),
            implode(', ', $updates)
        );
        $this->run($sql, $data);
        $id = (int)$this->pdo->lastInsertId();
        return $id;
    }

    /** UPDATE by primary key. Returns affected rows. */
    public function update(string $table, array $data, array $where): int
    {
        $sets = [];
        $bindings = [];
        foreach ($data as $col => $value) {
            $sets[] = sprintf('`%s` = :set_%s', $col, $col);
            $bindings['set_' . $col] = $value;
        }
        $conds = [];
        foreach ($where as $col => $value) {
            $conds[] = sprintf('`%s` = :w_%s', $col, $col);
            $bindings['w_' . $col] = $value;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s',
            $table, implode(', ', $sets), implode(' AND ', $conds));

        return $this->run($sql, $bindings)->rowCount();
    }

    /** DELETE. Returns affected rows. */
    public function delete(string $table, array $where): int
    {
        $conds = [];
        $bindings = [];
        foreach ($where as $col => $value) {
            $conds[] = sprintf('`%s` = :w_%s', $col, $col);
            $bindings['w_' . $col] = $value;
        }
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $conds));
        return $this->run($sql, $bindings)->rowCount();
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    // ------------------------------------------------------------------
    // Transactions
    // ------------------------------------------------------------------
    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** Run a closure inside a transaction; rolls back on any exception. */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Build "IN (?, ?, ?)" placeholders for a list, returning the fragment
     * and the positional bindings so callers never interpolate values.
     */
    public static function inClause(array $values, string $prefix = 'in'): array
    {
        $holders  = [];
        $bindings = [];
        $i = 0;
        foreach ($values as $v) {
            $key = $prefix . '_' . $i++;
            $holders[]      = ':' . $key;
            $bindings[$key] = $v;
        }
        return [implode(', ', $holders), $bindings];
    }

    private function fail(string $message, PDOException $e): void
    {
        Logger::error($message . ' :: ' . $e->getMessage());

        if (Config::get('app.debug')) {
            throw new \RuntimeException($message . ' :: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
        throw new \RuntimeException('A database error occurred. Please contact the administrator.');
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }
}
