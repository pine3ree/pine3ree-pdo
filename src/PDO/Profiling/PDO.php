<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDO\Profiling;

use pine3ree\PDO as P3PDO;
use pine3ree\PDO\Profiling\PDOStatement;
use PDOException;

use function is_array;
use function gettype;
use function is_string;
use function is_subclass_of;
use function md5;
use function microtime;
use function sprintf;

/**
 * A PDO wrapper for profiling query/command executions
 */
final class PDO extends \PDO
{
    private \PDO $pdo;

    /**
     * @var array<int, array{sql: string, iter: int, time: float, params: mixed[]|null}>
     */
    private array $log_statements = [];

    /**
     * @var array<string, array{sql: string, iter: int, time: float}>
     */
    private array $log_reruns = [];

    private int $log_count = 0;

    private float $log_time = 0.0;

    /**
     * Wraps a php PDO instance representing a connection to a database and
     * set a custom statement class
     *
     * @param \PDO $pdo The decorated pdo instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        // set custom profiling statement class
        $this->pdo->setAttribute(
            \PDO::ATTR_STATEMENT_CLASS,
            [PDOStatement::class, [$this]]
        );
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function errorCode(): ?string
    {
        return $this->pdo->errorCode();
    }

    /**
     * {@inheritDoc}
     *
     * @return array|mixed[]|array{0: string, 1: string|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    public function exec(string $statement): int|false
    {
        $t0 = microtime(true);
        $result = $this->pdo->exec($statement);
        $t1 = microtime(true);

        $this->log($statement, $t1 - $t0);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value internally stored
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritDoc}
     *
     * @param array|mixed[]|array<int|string, mixed> $options
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->pdo->prepare($query, $options);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $t0 = microtime(true);
        $result = $this->pdo->query($query, $fetchMode, ...$fetchModeArgs);
        $t1 = microtime(true);

        $this->log($query, $t1 - $t0);

        return $result;
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $type);
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * {@inheritDoc}
     *
     * Store the attribute internally so that if not connected to a database it
     * may be used when the connection is established
     *
     * Add additional validation for the statement-class attribute if query-logging
     * is enabled
     */
    public function setAttribute(int $attribute, $value): bool
    {
        if ($attribute === \PDO::ATTR_STATEMENT_CLASS) {
            $stmt_class = is_array($value) ? ($value[0] ?? null) : null;
            if (!is_string($stmt_class)
                || !(
                    $stmt_class === PDOStatement::class
                    || is_subclass_of($stmt_class, PDOStatement::class)
                )
            ) {
                throw new PDOException(sprintf(
                    "The statement-class for a profiling pdo instance must be"
                    . " either %s` or its subclass, `%s` given!",
                    PDOStatement::class,
                    is_string($stmt_class) ? $stmt_class : gettype($stmt_class)
                ));
            }
        }

        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Has the database connection already been established?
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        if ($this->pdo instanceof P3PDO) {
            return $this->pdo->isConnected();
        }

        return $this->pdo instanceof \PDO;
    }

    /**
     * Prepare and execute a sql-statement
     *
     * @param string $query The SQL expression possibly including parameter markers
     * @param array|mixed[]|array<int|string, mixed>|null $params Substitution parameters for the markers, if any
     * @param array|string[]|array{0: string, 1: string|null, 2: string|null} $options Additional driver options, if any
     *
     * @see \PDO::prepare()
     * @see \PDOStatement::execute()
     *
     * @link https://www.php.net/manual/en/pdo.prepare.php
     * @link https://www.php.net/manual/en/pdostatement.execute.php
     */
    public function execute(
        string $query,
        ?array $params = null,
        ?array $options = null
    ): PDOStatement|false {
        $stmt = $this->prepare($query, $options ?? []);
        if (false  === $stmt || false === $stmt->execute($params)) {
            return false;
        }

        return $stmt;
    }

    /**
     * Log information for a single statement execution
     *
     * @param string $sql The sql statement
     * @param float $microtime The execution time in seconds.microseconds
     * @param array|mixed[]|array<int|string, mixed>|null $params The parameters for the sql markes
     * @internal
     */
    public function log(
        string $sql,
        float $microtime,
        ?array $params = null
    ): void {
        $key = md5($sql);

        $time = $this->log_reruns[$key]['time'] ?? 0.0;
        $iter = $this->log_reruns[$key]['iter'] ?? 0;
        $iter += 1;

        $this->log_count += 1;

        $this->log_statements[] = [
            'sql'    => $sql,
            'iter'   => $iter,
            'time'   => $microtime,
            'params' => $params,
        ];

        $this->log_reruns[$key] = [
            'sql'  => $sql,
            'iter' => $iter,
            'time' => $time + $microtime,
        ];

        $this->log_time += $microtime;
    }

    /**
     * Return the combined log/profiling information
     *
     * @return array|mixed[]|array<string, array|float|int>
     */
    public function getLog(): array
    {
        return [
            'statements' => $this->log_statements,
            'reruns'     => $this->log_reruns,
            'time'       => $this->log_time,
            'count'      => $this->log_count,
        ];
    }
}
