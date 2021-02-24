<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDO\Reconnecting;

use InvalidArgumentException;
use P3\PDO as P3PDO;
use RuntimeException;

use function microtime;

/**
 * {@inheritDoc}
 *
 * The purpose of this class is to disconnect and reconnect to the database every
 * ttl seconds
 */
final class PDO extends P3PDO
{
    /** @var int */
    private $ttl;

    /** @var int */
    private $lastConnectedAt = 0;

    /** @var int */
    private $connectionCount = 0;

        /**
     * @const int The default ttl value if not given via constructor
     *
     * @internal
     */
    const DEFAULT_TTL = 60; //phpcs:ignore

    /**
     * {@inheritDoc}
     *
     * @param int $ttl The connection expiry time in seconds (must be positive)
     */
    public function __construct(
        string $dsn,
        string $username = '',
        string $password = '',
        array $options = [],
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
            if ((microtime(true) - $this->lastConnectedAt) > $this->ttl
                && !$this->pdo->inTransaction()
            ) {
                // disconnect if ttl seconds have passed since last connection
                $this->pdo = null;
            } else {
                return $this->pdo;
            }
        }

        $this->pdo = parent::pdo();

        if (isset($this->pdo)) {
            $this->lastConnectedAt = microtime(true);
            $this->connectionCount += 1;

            return $this->pdo;
        }

        throw new RuntimeException("Unable to create a PDO instance!");
    }

    /**
     * {@inheritDoc}
     *
     * Return the ttl property value when for the attribute `ttl`
     */
    public function getAttribute($attribute)
    {
        if ('ttl' === $attribute) {
            return $this->ttl;
        }

        return parent::getAttribute($attribute);
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
