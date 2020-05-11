<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3;

use InvalidArgumentException;
use P3\PDOStatement;

use function explode;
use function func_get_args;
use function gettype;
use function is_string;
use function is_subclass_of;
use function md5;
use function microtime;
use function sprintf;
use function time;

/**
 * PDO is a drop-in replacement for the php-extension "ext-pdo".
 *
 * The purpose of this class is to lazily establish a database connection the
 * first time the connection is needed.
 *
 * @property-read string $dsn The database DSN
 * @property-read string $connections The number of database connections established
 * @property-read int $ttl The database connection expiry time in seconds
 * @property-read array $log The logged information
 */
final class PDO extends \PDO
{
    /** @var \PDO|null */
    private $pdo;

    /** @var string */
    private $dsn;

    /** @var string */
    private $username;

    /** @var string*/
    private $password;

    /** @var array */
    private $options;

    /** @var array */
    private $attributes = [];

    /** @var int */
    private $time_connected = 0;

    /** @var int */
    private $connections = 0;

    /** @var int */
    private $ttl = 0;

    /** @var bool */
    private $log;

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
     *
     * @param bool $log Activate query-logging/profiling?
     * @param int $ttl The connection expiry time in seconds, 0 for no-expire
     */
    public function __construct(
        string $dsn,
        string $username = '',
        string $password = '',
        array $options = [],
        int $ttl = 0,
        bool $log = false
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->ttl = $ttl;
        $this->log = $log;
    }

    /**
     * Establish or re-establish a pdo-database connection and return it
     *
     * @return \PDO
     * @throws \PDOException
     */
    private function pdo(): \PDO
    {
        if (isset($this->pdo)) {
            if ($this->ttl > 0
                && time() - $this->time_connected > $this->ttl
                && !$this->pdo->inTransaction()
            ) {
                $this->pdo = null;
            } else {
                return $this->pdo;
            }
        }

        $this->pdo = new \PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );

        $this->time_connected = time();
        $this->connections += 1;

        // set our custom statement-class if query-log is enabled
        if ($this->log) {
            $this->pdo->setAttribute(
                self::ATTR_STATEMENT_CLASS,
                [PDOStatement::class, [$this]]
            );
        }

        // apply preset attributes, if any
        foreach ($this->attributes as $attribute => $value) {
            $this->pdo->setAttribute($attribute, $value);
        }

        return $this->pdo;
    }

    /**
     * Has the database connection already been established?
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return isset($this->pdo);
    }

    /** {@inheritDoc} */
    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    /** {@inheritDoc} */
    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    /** {@inheritDoc} */
    public function errorCode(): string
    {
        if (isset($this->pdo)) {
            return $this->pdo->errorCode();
        }

        return '00000';
    }

    /** {@inheritDoc} */
    public function errorInfo(): array
    {
        if (isset($this->pdo)) {
            return $this->pdo->errorInfo();
        }

        return ['00000', null, null];
    }

    /** {@inheritDoc} */
    public function exec($statement): int
    {
        if ($this->log) {
            return $this->profile(__FUNCTION__, $statement, func_get_args());
        }

        return $this->pdo()->exec($statement);
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value internally stored
     */
    public function getAttribute($attribute)
    {
        if (isset($this->pdo)) {
            return $this->pdo->getAttribute($attribute);
        }

        switch ($attribute) {
            case \PDO::ATTR_DRIVER_NAME:
                return explode(':', $this->dsn)[0];

            case \PDO::ATTR_CONNECTION_STATUS:
            case \PDO::ATTR_SERVER_INFO:
            case \PDO::ATTR_SERVER_VERSION:
                return '';
        }

        return $this->attributes[$attribute] ?? null;
    }

    /** {@inheritDoc} */
    public function inTransaction(): bool
    {
        if (isset($this->pdo)) {
            return $this->pdo->inTransaction();
        }

        return false;
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null): string
    {
        if (isset($this->pdo)) {
            return $this->pdo->lastInsertId($name);
        }

        return '';
    }

    /** {@inheritDoc} */
    public function prepare($statement, $driver_options = [])
    {
        return $this->pdo()->prepare($statement, $driver_options);
    }

    /**
     * {@inheritDoc}
     * @link https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $statement, int $fetch_style = null, $fetch_argument = null)
    {
        if ($this->log) {
            return $this->profile(__FUNCTION__, $statement, func_get_args());
        }

        return $this->pdo()->query(...func_get_args());
    }

    /** {@inheritDoc} */
    public function quote($string, $paramtype = null): string
    {
        return $this->pdo()->quote($string, $paramtype);
    }

    /** {@inheritDoc} */
    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
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
        // validate ATTR_STATEMENT_CLASS assignment if query-log is enabled
        if ($this->log
            && $attribute === self::ATTR_STATEMENT_CLASS
        ) {
            $stmt_class = $value[0] ?? null;
            if (!is_string($stmt_class)
                || !(
                    $stmt_class === PDOStatement::class
                    || is_subclass_of($stmt_class, PDOStatement::class)
                )
            ) {
                throw new \PDOException(sprintf(
                    "When query-logging is enabled the statement-class must be"
                    . " either %s` or its subclass, `%s` given!",
                    PDOStatement::class,
                    is_string($stmt_class) ? $stmt_class : gettype($stmt_class)
                ));
            }
        }

        $this->attributes[$attribute] = $value;

        if (isset($this->pdo)) {
            return $this->pdo->setAttribute($attribute, $value);
        }

        return true;
    }

    private function profile(string $method, string $sql, array $args)
    {
        $t0 = microtime(true);
        $result = $this->pdo()->{$method}(...$args);
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
    public function run(
        string $statement,
        array $input_parameters = [],
        array $driver_options = []
    ) {
        $stmt = $this->prepare($statement, $driver_options);
        if ($stmt instanceof \PDOStatement) {
            $stmt->execute($input_parameters);
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
            'statements'  => $this->log_statements,
            'reruns'      => $this->log_reruns,
            'time'        => $this->log_time,
            'count'       => $this->log_count,
            'connections' => $this->connections,
            'ttl'         => $this->ttl,
        ];
    }

    public function __get(string $name)
    {
        if ($name === 'dsn') {
            return $this->dsn;
        }
        if ($name === 'ttl') {
            return $this->ttl;
        }
        if ($name === 'connections') {
            return $this->connections;
        }
        if ($name === 'log') {
            return $this->getLog();
        }

        // do not expose the internal pdo-connection
        return null;
    }
}
