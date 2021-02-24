<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3;

use P3\PDOStatement;

use function explode;
use function func_get_args;

/**
 * PDO is a drop-in replacement for the php-extension "ext-pdo".
 *
 * The purpose of this class is to lazily establish a database connection the
 * first time the connection is needed.
 */
class PDO extends \PDO
{
    /** @var \PDO|null */
    protected $pdo;

    /** @var string */
    protected $dsn;

    /** @var string */
    protected $username;

    /** @var string*/
    protected $password;

    /** @var array */
    protected $options;

    /** @var array */
    protected $attributes = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(
        string $dsn,
        string $username = '',
        string $password = '',
        array $options = []
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    /**
     * Establish or re-establish a pdo-database connection and return it
     *
     * @return \PDO
     * @throws \PDOException
     */
    protected function pdo(): \PDO
    {
        if (isset($this->pdo)) {
            return $this->pdo;
        }

        $this->pdo = new \PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );

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
        $this->attributes[$attribute] = $value;

        if (isset($this->pdo)) {
            return $this->pdo->setAttribute($attribute, $value);
        }

        return true;
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
}
