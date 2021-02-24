<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDOTest\Reconnecting;

use InvalidArgumentException;
use P3\PDO\Reconnecting\PDO;
use P3\PDOTest\Profiling\AbstractPDOTest;

final class PDOTest extends AbstractPDOTest
{
    protected $ttl = 1;

    protected function createPDO(): PDO
    {
        return $this->createReconnectingPDO($this->ttl);
    }

    protected function createReconnectingPDO(int $ttl): PDO
    {
        return new PDO($this->dsn, '', '', [], $ttl);
    }

    public function testThatCreatingInstanceWithNonPositiveTtlRisesInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $pdo = $this->createReconnectingPDO(0);

        $this->expectException(InvalidArgumentException::class);
        $pdo = $this->createReconnectingPDO(-1);
    }

    public function testThatReconnectionHappensGivingEnoughTime()
    {
        $ttl = 1;
        $pdo = $this->createReconnectingPDO($ttl);
        self::assertFalse($pdo->isConnected());

        // connect
        $pdo->quote('A'); // trigger connection
        self::assertTrue($pdo->isConnected());
        self::assertEquals(1, $pdo->getConnectionCount());

        // pause and see if there is a new connection
        usleep($ttl * 1000 * 1000 + 1);
        $pdo->quote('A'); // trigger reconnection
        self::assertTrue($pdo->isConnected());
        self::assertEquals(2, $pdo->getConnectionCount());
    }

    public function testThatReconnectionDoesNotHappenIfInTransaction()
    {
        $ttl = 1;
        $pdo = $this->createReconnectingPDO($ttl);
        self::assertFalse($pdo->isConnected());

        $pdo->beginTransaction();

        // connect
        $pdo->quote('A'); // trigger connection
        self::assertTrue($pdo->isConnected());
        self::assertEquals(1, $pdo->getConnectionCount());

        // pause and see if there is a new connection
        usleep($ttl * 1000 * 1000 + 1);
        $pdo->quote('A'); // try to trigger new connection
        self::assertTrue($pdo->isConnected());
        self::assertEquals(1, $pdo->getConnectionCount());

        $pdo->commit();
    }
}
