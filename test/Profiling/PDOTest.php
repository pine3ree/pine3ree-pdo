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

use function date;
use function md5;
use function rand;
use function sprintf;
use function strtotime;
use function time;

final class PDOTest extends AbstractPDOTest
{
    /** @var string */
    private $dbfile = "/tmp/p3-pdo-sqlit-test.db";

    /** @var string */
    private $dsn = "sqlite:/tmp/p3-pdo-sqlit-test.db";

    public function setUp()
    {
        $pdo = new \PDO($this->dsn);
        $pdo->exec(<<<EOT
CREATE TABLE `user` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `username` TEXT UNIQUE,
    `email` TEXT UNIQUE,
    `enabled` INTEGER DEFAULT '0',
    `created_at` TEXT DEFAULT '0000-00-00 00:00:00',
    `updated_at` TEXT DEFAULT '0000-00-00 00:00:00'
);
EOT
        );

        $stmt = $pdo->prepare(<<<EOT
INSERT INTO `user`
    (`username`, `email`, `enabled`, `created_at`)
VALUES
    (:username, :email, :enabled, :created_at)
EOT
        );

        for ($i = 1; $i <= 10; $i++) {
            $stmt->execute([
                ':username'   => sprintf("username-%03d", $i),
                ':email'      => sprintf("email-%03d@emample.com", $i),
                ':enabled'    => mt_rand(0, 1),
                ':created_at' => date('Y-m-d H:i:s', rand(strtotime('-60 days'), time())),
            ]);
        }
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
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
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
