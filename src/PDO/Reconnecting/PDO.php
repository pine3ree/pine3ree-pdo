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
use RuntimeException;

use function is_int;
use function microtime;

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
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                "The expiry time TTL argument must be a positive integer: `{$ttl}` was provided!"
            );
        }
        $this->ttl = $ttl;
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

        $this->pdo = parent::pdo();

        if (isset($this->pdo)) {
            $this->lastConnectedAt = microtime(true);
            $this->connectionCount += 1;
            return $this->pdo;
        }

        throw new RuntimeException(
            "Unable to estabilish a PDO database connection!"
        );
    }

    /**
     * {@inheritDoc}
     *
     * Return the ttl property value when for the attribute `ttl`
     *
     * @param int|string $attribute One of the PDO::ATTR_* constants, or the string "ttl"
     */
    public function getAttribute($attribute)
    {
        if ($attribute === self::ATTR_CONNECTION_TTL) {
            return $this->ttl;
        }

        if (is_int($attribute)) {
            return parent::getAttribute($attribute);
        }

        return null;
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
