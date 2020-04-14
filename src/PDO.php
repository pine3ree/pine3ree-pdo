<?php

/**
 * @package     p3-pdo
 * @see         https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright   https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author      pine3ree https://github.com/pine3ree
 * @license     https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3;

use P3\PDOStatement;
use PDOException;

use function func_get_args;
use function gettype;
use function is_string;
use function is_subclass_of;
use function md5;
use function microtime;

/**
 * PDO is a drop-in replacement for the php extesions PDO
 *
 * The purpose of this class is to establish a connection on first access
 *
 * @property-read null|\PDO $pdo The wrapped php-ext PDO instance or null
 */
final class PDO extends \PDO
{
    /** @var string */
    private $dsn;

    /** @var string */
    private $username;

    /** @var string*/
    private $password;

    /** @var array */
    private $options;

    /** @var array */
    private $attributes;

    /** @var bool */
    private $connected = false;

    /** @var bool */
    private $log;

    /** @var array */
    private $log_queries = [];

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
     */
    public function __construct(
        string $dsn,
        string $username = '',
        string $password = '',
        array $options = [],
        array $attributes = [],
        bool $log = false
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->attributes = $attributes;
        $this->log = $log;
    }

    /**
     * Establish a database connection by calling parent constructor
     *
     * @return bool
     * @throws \PDOException
     * @internal
     */
    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        parent::__construct(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );

        $this->connected = true;

        // set our custom statement-class
        parent::setAttribute(
            self::ATTR_STATEMENT_CLASS,
            [PDOStatement::class, [$this, $this->log]]
        );
        // apply preset attributes, if any
        foreach ($this->attributes as $attribute => $value) {
            parent::setAttribute($attribute, $value);
        }

        return true;
    }

    /**
     * Has the database connection already been established?
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /** {@inheritDoc} */
    public function beginTransaction(): bool
    {
        $this->connected || $this->connect();
        return parent::beginTransaction();
    }

    /** {@inheritDoc} */
    public function errorCode(): string
    {
        if (!$this->connected) {
            return '00000';
        }

        return parent::errorCode();
    }

    /** {@inheritDoc} */
    public function errorInfo(): array
    {
        if (!$this->connected) {
            return ['00000', null, null];
        }

        return parent::errorInfo();
    }

    /** {@inheritDoc} */
    public function exec($statement): int
    {
        $this->connected || $this->connect();

        $this->log && $t0 = microtime(true);

        $result = parent::exec($statement);

        $this->log && $this->log($statement, microtime(true) - $t0);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value internally stored
     */
    public function getAttribute($attribute)
    {
        if ($this->connected) {
            return parent::getAttribute($attribute);
        }

        return $this->attributes[$attribute] ?? null;
    }

    /** {@inheritDoc} */
    public function inTransaction(): bool
    {
        if (!$this->connected) {
            return false;
        }

        return parent::inTransaction();
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null): string
    {
        if (!$this->connected) {
            return '';
        }

        return parent::lastInsertId($name);
    }

    /** {@inheritDoc} */
    public function prepare($statement, $driver_options = [])
    {
        $this->connected || $this->connect();
        return parent::prepare($statement, $driver_options);
    }

    /**
     * {@inheritDoc}
     * @link https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $statement, int $fetch_style = null, $fetch_argument = null)
    {
        $this->connected || $this->connect();

        $this->log && $t0 = microtime(true);

        $stmt = parent::query(...func_get_args());

        $this->log && $this->log($statement, microtime(true) - $t0);

        return $stmt;
    }

    /** {@inheritDoc} */
    public function quote($string, $paramtype = null): string
    {
        $this->connected || $this->connect();
        return parent::quote($string, $paramtype);
    }

    /**
     * {@inheritDoc}
     *
     * Store the attribute internally so that if not connected to a database it
     * may be used when the connection is established
     *
     * Add additional validation for the statement-class attribute
     */
    public function setAttribute($attribute, $value): bool
    {
        // validate ATTR_STATEMENT_CLASS assignment
        if ($attribute === self::ATTR_STATEMENT_CLASS) {
            $stmt_class = $value[0] ?? null;
            if (
                !is_string($stmt_class)
                || !(
                    $stmt_class === PDOStatement::class
                    || is_subclass_of($stmt_class, PDOStatement::class)
                )
            ) {
                throw new PDOException(sprintf(
                    "The statement class must be either %s` or its subclass, `%s` given",
                    PDOStatement::class,
                    is_string($stmt_class) ? $stmt_class : gettype($stmt_class)
                ));
            }
        }

        $this->attributes[$attribute] = $value;

        if ($this->connected) {
            return parent::setAttribute($attribute, $value);
        }

        return true;
    }

    /**
     * Prepare and execute a query
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
        $result = $this->prepare($statement, $driver_options);

        if ($result instanceof PDOStatement) {
            $result->execute($input_parameters);
        }

        return $result;
    }

    /**
     * Log information for a single query execution
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

        $this->log_queries[] = [
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
            'queries' => $this->log_queries,
            'reruns'  => $this->log_reruns,
            'time'    => $this->log_time,
            'count'   => $this->log_count,
        ];
    }

    /**
     * Return the logged query information
     *
     * @param bool $combined_reruns Return combined query reruns?
     * @return array
     */
    public function getExecutedQueries(bool $combined_reruns = false): array
    {
        return $combined_reruns ? $this->log_reruns : $this->log_queries;
    }

    /**
     * Return the total query execution time
     *
     * @return float
     * @internal
     */
    public function getTotalExecTime(): float
    {
        return $this->log_time;
    }

    /**
     * Return the total numer of queries
     *
     * @return int
     * @internal
     */
    public function getTotalQueryCount(bool $include_reruns = true): int
    {
        return $include_reruns ? $this->log_count : count($this->log_reruns);
    }
}
