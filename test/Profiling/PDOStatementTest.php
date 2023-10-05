<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/2.0.x/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/2.0.x/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest\Profiling;

use pine3ree\PDOTest\Profiling\AbstractPDOTest;
use pine3ree\PDO as LazyPDO;
use pine3ree\PDO\Profiling\PDO;
use pine3ree\PDO\Profiling\PDOStatement;
use ReflectionClass;

final class PDOStatementTest extends AbstractPDOTest
{
    protected static function expectedStatementClass(): string
    {
        return PDOStatement::class;
    }

    protected function createPDO(): PDO
    {
        return new PDO(new \PDO($this->dsn, '', ''));
    }

    protected function createLazyPDO(): PDO
    {
        return new PDO(new LazyPDO($this->dsn, '', ''));
    }

    // phpcs:disable

    public function test_method_bindValue()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->prepare("SELECT * FROM `user` WHERE `id` = :id");

        $rc = new ReflectionClass($stmt);
        $rp = $rc->getProperty('params');
        $rp->setAccessible(true);

        $id = 42;

        $stmt->bindValue('id', $id);

        $params = $rp->getValue($stmt);
        self::assertSame($id, $params['id'] ?? null);

        $stmt->execute();
    }

    public function test_method_bindParam()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->prepare("SELECT * FROM `user` WHERE `id` = :id");

        $rc = new ReflectionClass($stmt);
        $rp = $rc->getProperty('params');
        $rp->setAccessible(true);

        $stmt->bindParam('id', $id);

        $params = $rp->getValue($stmt);
        self::assertSame($id, $params['id'] ?? null);

        $stmt->execute();
    }


    // phpcs:enable
}
