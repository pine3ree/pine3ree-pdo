<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest\Reconnecting;

use InvalidArgumentException;
use pine3ree\PDO\Reconnecting\PDO;
use pine3ree\PDOTest\Profiling\AbstractPDOTest;
use LogicException;
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

    protected function getDecoratedPDO(PDO $pdo): \PDO
    {
        $rc = new ReflectionClass($pdo);
        $rm = $rc->getMethod('pdo');
        $rm->setAccessible(true);

        return $rm->invoke($pdo);
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

        $this->expectException(PDOException::class);
        $phpPdo = $this->getDecoratedPDO($pdo);
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

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame($fetchMode, $phpPdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testThatGetAttributeWithInvalidKeyTypeRaisesException()
    {
        $pdo = $this->createReconnectingPDO(42);

        $this->expectException(InvalidArgumentException::class);
        $pdo->getAttribute('abc');
    }

    public function testThatSetAttributeWithInvalidKeyTypeRaisesException()
    {
        $pdo = $this->createReconnectingPDO(42);

        $this->expectException(InvalidArgumentException::class);
        $pdo->setAttribute('abc', 123);
    }

    public function testThatSetTtlAttributeWithInvalidValueRaisesException()
    {
        $pdo = $this->createReconnectingPDO(42);

        $this->expectException(InvalidArgumentException::class);
        $pdo->setAttribute('ttl', 'ABC');
    }

    public function testThatSetTtlAttributeWithValidIntValueSucceds()
    {
        $pdo = $this->createReconnectingPDO(1);
        $pdo->setAttribute('ttl', 42);

        self::assertSame(42, $pdo->getAttribute('ttl'));
    }

    public function testGetTTL()
    {
        $ttl = 42;
        $pdo = $this->createReconnectingPDO($ttl);

        self::assertSame($ttl, $pdo->getTTL());
    }

    public function testSetTTL()
    {
        $pdo = $this->createReconnectingPDO(42);
        $pdo->setTTL(43);

        self::assertSame(43, $pdo->getTTL());
    }

    public function testSettingTtlMultipleTimesBeforConnectingWorks()
    {
        $pdo = $this->createReconnectingPDO(42);

        $pdo->setTTL(43);
        self::assertSame(43, $pdo->getTTL());
        self::assertSame(43, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));

        $pdo->setTTL(44);
        self::assertSame(44, $pdo->getTTL());
        self::assertSame(44, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));

        $pdo->setTTL(45);
        self::assertSame(45, $pdo->getTTL());
        self::assertSame(45, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));

        $pdo->setAttribute($pdo::ATTR_CONNECTION_TTL, 41);
        self::assertSame(41, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));
        self::assertSame(41, $pdo->getTTL());

        $pdo->setAttribute($pdo::ATTR_CONNECTION_TTL, 40);
        self::assertSame(40, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));
        self::assertSame(40, $pdo->getTTL());

        $pdo->setAttribute($pdo::ATTR_CONNECTION_TTL, 39);
        self::assertSame(39, $pdo->getAttribute($pdo::ATTR_CONNECTION_TTL));
        self::assertSame(39, $pdo->getTTL());
    }

    public function testSettingTtlAfterFirstConnectionRaisesException()
    {
        $pdo = $this->createReconnectingPDO(42);

        $this->getDecoratedPDO($pdo);

        $this->expectException(LogicException::class);
        $pdo->setTTL(43);

        $this->expectException(LogicException::class);
        $pdo->setAttribute('ttl', 43);
    }
}
