<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Infrastructure\Support\Env;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * PDO connection and query helper.
 *
 * Every method that touches SQL takes bound parameters. There is no method on
 * this class that interpolates a value into a statement, which is what makes
 * "100% prepared statements" an enforceable property rather than a promise:
 * if a caller wants to inject, they have to bypass this class entirely.
 *
 * Identifiers (column names in ORDER BY, for instance) cannot be bound by any
 * driver, so quoteIdentifier() exists for that one case and validates against
 * an allow-list at the call site.
 */
final class Database
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    public function __construct(
        private readonly string $dsn,
        private readonly string $username,
        private readonly string $password,
        private readonly array $options = [],
    ) {
    }

    public static function fromEnv(): self
    {
        $host    = Env::get('DB_HOST', '127.0.0.1');
        $port    = Env::get('DB_PORT', '3306');
        $name    = Env::require('DB_DATABASE');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, not driver-side emulation. Emulation
            // would re-introduce string interpolation inside the driver and
            // breaks native typing of integer parameters.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        if (Env::has('DB_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = Env::get('DB_SSL_CA');
        }

        return new self($dsn, Env::get('DB_USERNAME', '') ?? '', Env::get('DB_PASSWORD', '') ?? '', $options);
    }

    /** Lazily connect, so a page that needs no database never opens one. */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);

            // Store and compare everything in UTC; localisation happens in
            // the view layer only.
            $this->pdo->exec("SET time_zone = '+00:00'");

            // STRICT_ALL_TABLES turns silent truncation into an error, so an
            // over-length patient name fails loudly instead of being cut off.
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            // Re-thrown without the DSN: the original message contains the
            // host, database name and sometimes the username.
            throw new RuntimeException('Database connection failed.', (int) $e->getCode(), $e);
        }

        return $this->pdo;
    }

    /**
     * @param array<string, mixed>|list<mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($params as $key => $value) {
            $placeholder = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

            $statement->bindValue($placeholder, $value, match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            });
        }

        $statement->execute();

        return $statement;
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** First column of the first row, for COUNT/SUM/EXISTS queries. */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function fetchInt(string $sql, array $params = []): int
    {
        return (int) $this->fetchValue($sql, $params);
    }

    /**
     * Key-value pairs from a two-column result, e.g. id => name.
     *
     * @return array<string|int, mixed>
     */
    public function fetchPairs(string $sql, array $params = []): array
    {
        $pairs = [];

        foreach ($this->fetchAll($sql, $params) as $row) {
            $values = array_values($row);
            $pairs[$values[0]] = $values[1] ?? null;
        }

        return $pairs;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Run a closure inside a transaction, committing on success and rolling
     * back on any throwable.
     *
     * Nested calls join the outer transaction via SAVEPOINT rather than
     * throwing, so a service that manages its own transaction can still be
     * called from inside a larger one.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    public function begin(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trx_' . $this->transactionDepth);
        }

        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT trx_' . $this->transactionDepth);
        }
    }

    public function rollback(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trx_' . $this->transactionDepth);
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * Quote a table or column name.
     *
     * Bound parameters cannot stand in for identifiers, so dynamic ORDER BY
     * columns must pass through here - and callers must first check the value
     * against a hardcoded allow-list. Backticks are stripped so a crafted
     * value cannot close the quoting and append SQL.
     */
    public static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    /**
     * Build an OR-ed LIKE clause across several columns.
     *
     * Each column gets its OWN placeholder. This is not cosmetic: with
     * ATTR_EMULATE_PREPARES disabled, PDO sends the statement to MySQL as a
     * real prepared statement, and a named placeholder may then appear exactly
     * once. Reusing `:q` across four columns fails at execute() with
     * SQLSTATE[HY093] "Invalid parameter number", which is why every search
     * must go through this helper rather than hand-writing the clause.
     *
     * @param list<string> $columns column names (never user input)
     * @return array{string, array<string, string>} [sql fragment, params]
     */
    public static function searchClause(array $columns, string $term, string $prefix = 'q'): array
    {
        $parts  = [];
        $params = [];
        $needle = '%' . $term . '%';

        foreach (array_values($columns) as $index => $column) {
            $name = $prefix . $index;

            $parts[]        = $column . ' LIKE :' . $name;
            $params[$name]  = $needle;
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * Build `IN (?, ?, ?)` with positional placeholders.
     *
     * @return array{string, list<mixed>}
     */
    public static function inClause(array $values): array
    {
        if ($values === []) {
            // An empty IN () is a syntax error in MySQL; this is the
            // always-false equivalent.
            return ['(NULL)', []];
        }

        return ['(' . implode(', ', array_fill(0, count($values), '?')) . ')', array_values($values)];
    }

    /** True when the exception is a duplicate-key violation (SQLSTATE 23000). */
    public static function isDuplicateKey(PDOException $e): bool
    {
        return $e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate entry');
    }
}
