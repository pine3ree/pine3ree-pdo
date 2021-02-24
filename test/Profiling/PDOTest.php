<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDOTest\Profiling;

use P3\PDOTest\Profiling\AbstractPDOTest;
use P3\PDO\Profiling\PDO;
use P3\PDO\Profiling\PDOStatement;

use function md5;

final class PDOTest extends AbstractPDOTest
{
    protected static function expectedStatementClass(): string
    {
        return PDOStatement::class;
    }

    protected function createPDO(): PDO
    {
        return new PDO(new \PDO($this->dsn, '', ''));
    }

    public function test_method_prepare_returnsProfilingPdoStatement()
    {
        $pdo = $this->createPDO();
        $result = $pdo->prepare("SELECT * FROM `user` WHERE `id` = :id");

        self::assertInstanceOf(PDOStatement::class, $result);
    }

    public function test_method_setAttribute_throwsPDOExceptionIfSettingInvalidStatementClass()
    {
        $pdo = $this->createPDO();

        $this->expectException(\PDOException::class);
        $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
    }

    public function testStatementLogger()
    {
        $pdo = $this->createPDO(0, true);

        $sql1 = "SELECT * FROM `user` WHERE `id` = :id";
        $sql2 = "SELECT `username` FROM `user` WHERE `id` = :id";
        $sql3 = "SELECT `email` FROM `user` WHERE `id` = :id";

        $pdo->execute($sql1, [':id' => 1]); // 0
        $pdo->execute($sql1, [':id' => 2]); // 1
        $pdo->execute($sql2, [':id' => 3]); // 2
        $pdo->execute($sql1, [':id' => 4]); // 3
        $pdo->execute($sql2, [':id' => 5]); // 4
        $pdo->execute($sql3, [':id' => 6]); // 5

        $log = $pdo->getLog();

        $stmnts = $log['statements'];
        $reruns = $log['reruns'];

        self::assertSame(6, $log['count']);
        self::assertSame(3, count($reruns));

        self::assertSame(3, $reruns[md5($sql1)]['iter']);
        self::assertSame(2, $reruns[md5($sql2)]['iter']);
        self::assertSame(1, $reruns[md5($sql3)]['iter']);

        self::assertSame(1, $stmnts[0]['iter']);
        self::assertSame(2, $stmnts[1]['iter']);
        self::assertSame(1, $stmnts[2]['iter']);
        self::assertSame(3, $stmnts[3]['iter']);
        self::assertSame(2, $stmnts[4]['iter']);
        self::assertSame(1, $stmnts[5]['iter']);

        self::assertSame(1, $stmnts[0]['params'][':id']);
        self::assertSame(2, $stmnts[1]['params'][':id']);
        self::assertSame(3, $stmnts[2]['params'][':id']);
        self::assertSame(4, $stmnts[3]['params'][':id']);
        self::assertSame(5, $stmnts[4]['params'][':id']);
        self::assertSame(6, $stmnts[5]['params'][':id']);
    }

    // phpcs:enable
}
