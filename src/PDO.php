<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree;

use function explode;

use const PHP_VERSION_ID;

/**
 * PDO is a drop-in replacement for the php-extension "ext-pdo".
 *
 * The purpose of this class is to lazily establish a database connection the
 * first time the connection is needed.
 */
class PDO extends \PDO
{
    /** The wrapped php-PDO instance */
    protected ?\PDO $pdo = null;

    protected ?string $dsn = null;

    protected ?string $username = null;

    protected ?string $password = null;

    protected ?array $options = null;

    protected array $attributes = [];

    /**
     * Gather mandatory information later used to establish a database connection
     * on demand
     *
     * @param string $dsn The Data Source Name, or DSN, contains the information required to connect to the database.
     * @param string|null $username The user name for the DSN string. This parameter is optional for some PDO drivers.
     * @param string|null $password The password for the DSN string. This parameter is optional for some PDO drivers.
     * @param array|null $options A key=>value array of driver-specific connection options.
     */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $options = null
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    public function __destruct()
    {
        $this->pdo = null;
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
    public function exec(string $statement): int
    {
        return $this->pdo()->exec($statement);
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value internally stored
     */
    public function getAttribute(int $attribute)
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
    public function lastInsertId(?string $name = null): string|false
    {
        if (isset($this->pdo)) {
            return $this->pdo->lastInsertId($name);
        }

        return false;
    }

    /** {@inheritDoc} */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->pdo()->prepare($query, $options);
    }

    /**
     * {@inheritDoc}
     * @see https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs): \PDOStatement|false
    {
        return $this->pdo()->query($query, $fetchMode, ...$fetchModeArgs);
    }

    /** {@inheritDoc} */
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo()->quote($string, $type);
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
    public function setAttribute(int $attribute, $value): bool
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
     * @param array $params Substitution parameters for the markers, if any
     * @param array $options  Additional driver options, if any
     * @return \PDOStatement|false
     *
     * @see \PDO::prepare()
     * @see \PDOStatement::execute()
     *
     * @link https://www.php.net/manual/en/pdo.prepare.php
     * @link https://www.php.net/manual/en/pdostatement.execute.php
     */
    public function execute(
        string $statement,
        array $params = [],
        array $options = []
    ) {
        $stmt = $this->prepare($statement, $options);
        if (false  === $stmt || false === $stmt->execute($params)) {
            return false;
        }

        return $stmt;
    }
}
