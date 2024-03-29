<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/LICENSE.md New BSD License
 */

namespace pine3ree;

use function explode;

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

    protected string $dsn;

    protected ?string $username = null;

    protected ?string $password = null;

    /**
     * Driver-specific connection options
     *
     * @var array|mixed[]|array<int|string, mixed>|null
     */
    protected ?array $options = null;

    /**
     * PDO database connection attributes
     *
     * @var array|mixed[]|array<int|string, mixed>
     */
    protected array $attributes = [];

    /**
     * Gather mandatory information later used to establish a database connection
     * on demand
     *
     * @param string $dsn The Data Source Name, or DSN, contains the information
     *      required to connect to the database.
     * @param string|null $username The user name for the DSN string.
     *      This parameter is optional for some PDO drivers.
     * @param string|null $password The password for the DSN string.
     *      This parameter is optional for some PDO drivers.
     * @param array|mixed[]|array<int|string, mixed>|null $options A key=>value
     *      array of driver-specific connection options.
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

        // Apply preset attributes, if any
        foreach ($this->attributes as $attribute => $value) {
            $this->pdo->setAttribute($attribute, $value);
        }

        return $this->pdo;
    }

    /**
     * Has the database connection already been established?
     */
    public function isConnected(): bool
    {
        return isset($this->pdo);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function errorCode(): ?string
    {
        if (isset($this->pdo)) {
            return $this->pdo->errorCode();
        }

        return '00000';
    }

    /**
     * {@inheritDoc}
     *
     * @return array|mixed[]|array{0: string, 1: string|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        if (isset($this->pdo)) {
            return $this->pdo->errorInfo();
        }

        return ['00000', null, null];
    }

    public function exec(string $statement): int|false
    {
        return $this->pdo()->exec($statement);
    }

    /**
     * {@inheritDoc}
     *
     * If not connected to a database return the attribute value stored internally
     */
    public function getAttribute(int $attribute): mixed
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

    public function inTransaction(): bool
    {
        if (isset($this->pdo)) {
            return $this->pdo->inTransaction();
        }

        return false;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        if (isset($this->pdo)) {
            return $this->pdo->lastInsertId($name);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @param array|mixed[]|array<int|string, mixed> $options Optional attribute values for the returned PDOStatement
     */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->pdo()->prepare($query, $options);
    }

    /**
     * {@inheritDoc}
     *
     * @see https://www.php.net/manual/en/pdo.query.php
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return $this->pdo()->query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo()->quote($string, $type);
    }

    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
    }

    /**
     * {@inheritDoc}
     *
     * Store the attribute internally so that if not connected to a database it
     * may be used when the connection is established
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
     * @param string $query The SQL expression possibly including parameter markers
     * @param array|mixed[]|array<int|string, mixed>|null $params Substitution parameters for the markers, if any
     * @param array|mixed[]|array<int|string, mixed>|null $options Optional attribute values
     *      for the returned PDOStatement
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
    ): \PDOStatement|false {
        $stmt = $this->prepare($query, $options ?? []);
        if (false  === $stmt || false === $stmt->execute($params)) {
            return false;
        }

        return $stmt;
    }
}
