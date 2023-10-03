<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest;

use P3\PDO as P3PDO;
use P3\PDO\Profiling\PDO as P3ProfilingPDO;
use P3\PDO\Profiling\PDOStatement as P3ProfilingPDOStatement;
use pine3ree\PDO;
use pine3ree\PDO\Profiling\PDO as ProfilingPDO;
use pine3ree\PDO\Profiling\PDOStatement as ProfilingPDOStatement;
use pine3ree\PDOTest\Profiling\AbstractPDOTest;

final class MigrateTest extends AbstractPDOTest
{
    protected static function expectedStatementClass(): string
    {
        return \PDOStatement::class;
    }

    protected function createPDO(): PDO
    {
        return new PDO($this->dsn, '', '', []);
    }

    protected function createProfilingPDO(): ProfilingPDO
    {
        return new ProfilingPDO($this->dsn, '', '', []);
    }

    protected function createP3PDO(): P3PDO
    {
        return new P3PDO($this->dsn, '', '', []);
    }

    protected function createP3ProfilingPDO(P3PDO $p3Pdo): P3ProfilingPDO
    {
        return new P3ProfilingPDO($p3Pdo);
    }

    public function testClassAliasesWorks()
    {
        $pdo = $this->createPDO();
        $p3Pdo = $this->createP3PDO();

        self::assertSame(PDO::class, get_class($p3Pdo));
        self::assertSame(get_class($p3Pdo), get_class($pdo));

        $p3ProfilingPdo = $this->createP3ProfilingPDO($p3Pdo);
        self::assertSame(ProfilingPDO::class, get_class($p3ProfilingPdo));

        $p3Stmt = $p3ProfilingPdo->prepare("SELECT * FROM user WHERE id = :id");
        self::assertSame(ProfilingPDOStatement::class, get_class($p3Stmt));
    }
}
