<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDO\Reconnecting;

use InvalidArgumentException;
use pine3ree\PDO as P3PDO;
use Exception;

use function gettype;
use function is_int;
use function microtime;
use function sprintf;

/**
 * {@inheritDoc}
 *
 * The purpose of this class is to trigger a new connection to the database after
 * ttl seconds
 */
final class PDO extends P3PDO
{
    /** The reconnection interval (titme-to-live) */
    private int $ttl;

    /** The last re-connection timestamp */
    private float $lastConnectedAt = 0;

    /** The number of connections so far */
    private int $connectionCount = 0;

    /** @var int The default ttl value if not given via constructor */
    private const DEFAULT_TTL = 30; //phpcs:ignore

    public const ATTR_CONNECTION_TTL = 'ttl';

    /**
     * {@inheritDoc}
     *
     * @param int $ttl The connection expiry time in seconds (must be positive)
     * @param array|mixed[]|array<int|string, mixed>|null $options Driver-specific connection options.
     */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array $options = null,
        int $ttl = self::DEFAULT_TTL
    ) {
        parent::__construct($dsn, $username, $password, $options);
        $this->setTTL($ttl);
    }

    protected function setTTL(int $ttl): void
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                "The expiry time TTL argument must be a positive integer: `{$ttl}` was provided!"
            );
        }

        $this->ttl = $ttl;
    }

    /**
     * Return the connection expiry time in seconds
     */
    public function getTTL(): int
    {
        return $this->ttl;
    }

    /**
     * {@inheritDoc}
     *
     * Establish or re-establish a pdo-database connection and return it
     */
    protected function pdo(): \PDO
    {
        if (isset($this->pdo)) {
            // Do not disconnect if we are inside a transaction
            if ($this->pdo->inTransaction()) {
                return $this->pdo;
            }
            if ((microtime(true) - $this->lastConnectedAt) <= $this->ttl) {
                return $this->pdo;
            }
            // Disconnect if more than ttl seconds have passed since last connection
            $this->pdo = null;
        }

        try {
            $this->pdo = parent::pdo();

            $this->lastConnectedAt = microtime(true);
            $this->connectionCount += 1;

            return $this->pdo;
            // v-spacer
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Return the ttl property value when for the attribute `ttl`
     *
     * @throws InvalidArgumentException
     */
    public function getAttribute(int|string $attribute): mixed
    {
        if ($attribute === self::ATTR_CONNECTION_TTL) {
            return $this->ttl;
        }

        if (is_int($attribute)) {
            return parent::getAttribute($attribute);
        }

        throw new InvalidArgumentException(sprintf(
            "Invalid PDO attribute type: `%s`, MUST be a standard PDO int attribute"
            . " constant or the string '%s'",
            gettype($attribute),
            self::ATTR_CONNECTION_TTL
        ));
    }

    /**
     * {@inheritDoc}
     *
     * Intercept custom 'ttl' attribute and call internal method setTTL
     */
    public function setAttribute(int|string $attribute, $value): bool
    {
        if ($attribute === self::ATTR_CONNECTION_TTL) {
            if (!is_int($value)) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid TTL type: `%s`! MUST be a an integer.",
                    gettype($value)
                ));
            }
            $this->setTTL($value);
            return true;
        }

        if (is_int($attribute)) {
            return parent::setAttribute($attribute, $value);
        }

        throw new InvalidArgumentException(sprintf(
            "Invalid PDO attribute type: `%s`, MUST be a standard PDO int attribute"
            . " constant or the string '%s'",
            gettype($attribute),
            self::ATTR_CONNECTION_TTL
        ));
    }

    /**
     * Return the number of connection initiated so far
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }
}
