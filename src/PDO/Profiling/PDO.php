<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDO\Profiling;

use P3\PDO\Profiling\PDOStatement;
use PDOException;

use function func_get_args;
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
    /** @var array */
    private $log_statements = [];

    /** @var array */
    private $log_reruns = [];

    /** @var int */
    private $log_count = 0;

    /** @var float */
    private $log_time = 0.0;

    /**
     * {@inheritDoc}
     * @param \PDO $pdo The decorated pdo instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        // set custom profiling statement class
        $this->pdo->setAttribute(
            self::ATTR_STATEMENT_CLASS,
            [PDOStatement::class, [$this]]
        );
    }

    /** {@inheritDoc} */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** {@inheritDoc} */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** {@inheritDoc} */
    public function errorCode(): string
    {
        return $this->pdo->errorCode();
    }

    /** {@inheritDoc} */
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    /** {@inheritDoc} */
    public function exec($statement): int
    {
        return $this->profile(__FUNCTION__, $statement, [$statement]);
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value internally stored
     */
    public function getAttribute($attribute)
    {
        return $this->pdo->getAttribute($attribute);
    }

    /** {@inheritDoc} */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    /** {@inheritDoc} */
    public function prepare($statement, $driver_options = [])
    {
        return $this->pdo->prepare($statement, $driver_options);
    }

    /**
     * {@inheritDoc}
     * @link https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $statement, int $fetch_style = null, $fetch_argument = null)
    {
        return $this->profile(__FUNCTION__, $statement, func_get_args());
    }

    /** {@inheritDoc} */
    public function quote($string, $paramtype = null): string
    {
        return $this->pdo->quote($string, $paramtype);
    }

    /** {@inheritDoc} */
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
    public function setAttribute($attribute, $value): bool
    {
        if ($attribute === self::ATTR_STATEMENT_CLASS) {
            $stmt_class = $value[0] ?? null;
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

    private function profile(string $method, string $sql, array $args)
    {
        $t0 = microtime(true);
        $result = $this->pdo->{$method}(...$args);
        $this->log($sql, microtime(true) - $t0);

        return $result;
    }

    /**
     * Prepare and execute a sql-statement
     *
     * @param string $statement The SQL expression possibly including parameter markers
     * @param array $input_parameters Substitution parameters for the markers, if any
     * @param array $driver_options  Additional driver options, if any
     * @return PDOStatement|false
     *
     * @see \PDO::prepare()
     * @see \PDOStatement::execute()
     *
     * @link https://www.php.net/manual/en/pdo.prepare.php
     * @link https://www.php.net/manual/en/pdostatement.execute.php
     */
    public function execute(
        string $statement,
        array $input_parameters = [],
        array $driver_options = []
    ) {
        $stmt = $this->prepare($statement, $driver_options);
        if (false  === $stmt || false === $stmt->execute($input_parameters)) {
            return false;
        }

        return $stmt;
    }

    /**
     * Log information for a single statement execution
     *
     * @param string $sql The sql statement
     * @param float $microtime The execution time in seconds.microseconds
     * @param array|null $params The parameters for the sql markes
     * @internal
     */
    public function log(
        string $sql,
        float $microtime,
        array $params = null
    ) {
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
     * @return array
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
