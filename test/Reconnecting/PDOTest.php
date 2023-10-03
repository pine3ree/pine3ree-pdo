<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest\Reconnecting;

use InvalidArgumentException;
use pine3ree\PDO\Reconnecting\PDO;
use pine3ree\PDOTest\Profiling\AbstractPDOTest;
use PDOException;
use ReflectionClass;

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

    public function testThatReconnectionDoesNotHappensIfNotExceedingTTL()
    {
        $ttl = 10;
        $pdo = $this->createReconnectingPDO($ttl);
        self::assertFalse($pdo->isConnected());

        // connect
        $pdo->quote('A'); // trigger connection
        self::assertTrue($pdo->isConnected());
        self::assertEquals(1, $pdo->getConnectionCount());

        // pause less than ttl and verify that there is no new connection
        usleep(500);
        $pdo->quote('A'); // trigger reconnection if needed
        self::assertTrue($pdo->isConnected());
        self::assertEquals(1, $pdo->getConnectionCount());
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

    public function testThatPdoThrowsPDOExceptionIfCannotConnectToDb()
    {
        $ttl = 42;
        $fetchMode = \PDO::FETCH_ASSOC;
        $pdo = new PDO('mysql://db=non-existent', '', '', [], $ttl);

        $rc = new ReflectionClass(PDO::class);
        $rm = $rc->getMethod('pdo');
        $rm->setAccessible(true);

        $this->expectException(PDOException::class);
        $rm->invoke($pdo);
    }

    public function testThatGetAttributeWithCustomStringKeyRetunsValidTtlValue()
    {
        $ttl = 42;
        $pdo = $this->createReconnectingPDO($ttl);

        self::assertSame($ttl, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));
        self::assertSame($ttl, $pdo->getAttribute('ttl'));
    }

    public function testThatGetAttributeWithIntKeyFallsBackToStandardBehavior()
    {
        $ttl = 1;
        $fetchMode = \PDO::FETCH_ASSOC;
        $pdo = $this->createReconnectingPDO($ttl);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $fetchMode);

        self::assertSame($fetchMode, $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));

        $rc = new ReflectionClass(PDO::class);
        $rm = $rc->getMethod('pdo');
        $rm->setAccessible(true);

        $wrappedPdo = $rm->invoke($pdo);
        self::assertSame($fetchMode, $wrappedPdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testThatGetAttributeWithInvalidKeyTypeRaisesException()
    {
        $pdo = $this->createReconnectingPDO(1);

        $this->expectException(InvalidArgumentException::class);
        $pdo->getAttribute(3.14);
    }

    public function testGetTTL()
    {
        $ttl = 42;
        $pdo = $this->createReconnectingPDO($ttl);

        self::assertSame($ttl, $pdo->getTTL());
    }
}
