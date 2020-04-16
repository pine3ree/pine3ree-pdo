<?php

/**
 * @package     p3-pdo
 * @see         https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright   https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author      pine3ree https://github.com/pine3ree
 * @license     https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDOTest;

use P3\PDO;
use P3\PDOStatement;
use PHPUnit\Framework\TestCase;

use function date;
use function is_file;
use function in_array;
use function md5;
use function rand;
use function sprintf;
use function strtotime;
use function time;
use function unlink;

final class PDOTest extends TestCase
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
	`id`	INTEGER PRIMARY KEY AUTOINCREMENT,
	`username`	TEXT UNIQUE,
	`email`	TEXT UNIQUE,
	`enabled`	INTEGER DEFAULT '0',
	`created_at`	TEXT DEFAULT '0000-00-00 00:00:00',
	`updated_at`	TEXT DEFAULT '0000-00-00 00:00:00'
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

    private function createPDO(bool $log = false)
    {
        return new PDO($this->dsn, '', '', [], [], $log);
    }

    // phpcs:disable

    public function test_method_errorCode_returnsNoErrorStringIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame('00000', $pdo->errorCode());
    }

    public function test_method_errorInfo_returnsNoErrorArrayIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame(['00000', null, null], $pdo->errorInfo());
    }

    public function test_method_lastInsertId_returnsEmptyStringIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame('', $pdo->lastInsertId());
    }

    public function test_method_inTransaction_returnsFalseStringIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertFalse($pdo->inTransaction());
    }

    public function test_method_beginTransaction_createsDbConnection()
    {
        $pdo = $this->createPDO();
        $pdo->beginTransaction();
        self::assertTrue($pdo->isConnected());
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     * @expectedExceptionMessage No error: PDO constructor was not called
     */
    public function test_method_commit_triggersWarningIfNotConnected()
    {
        $pdo = $this->createPDO();
        $pdo->commit();
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     * @expectedExceptionMessage No error: PDO constructor was not called
     */
    public function test_method_rollback_triggersWarningIfNotConnected()
    {
        $pdo = $this->createPDO();
        $pdo->rollBack();
    }

    public function test_method_quote_createsDbConnection()
    {
        $pdo = $this->createPDO();
        $pdo->quote('!"£$%&/()=?^^', $pdo::PARAM_STR);
        self::assertTrue($pdo->isConnected());
    }

    public function test_method_prepare_returnsP3PdoStatementIfLogEnabled()
    {
        $pdo = $this->createPDO(true);
        $result = $pdo->prepare("SELECT * FROM `user` WHERE `id` = :id");

        self::assertInstanceOf(PDOStatement::class, $result);
    }

    public function test_method_prepare_returnsExtPdoStatementIfLogDisabled()
    {
        $pdo = $this->createPDO();
        $result = $pdo->prepare("SELECT * FROM `user` WHERE `id` = :id");

        self::assertInstanceOf(\PDOStatement::class, $result);
    }

    public function test_method_setAttribute_throwsPDOExceptionIfSettingInvalidStatementClassAndLogEnabled()
    {
        $pdo = $this->createPDO(true);

        $this->expectException(\PDOException::class);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
    }

    /**
     * @dataProvider provideLogAndExpectedStatementClass
     */
    public function testInsertRow(bool $log, string $statementClass)
    {
        $pdo = $this->createPDO($log);

        $stmt = $pdo->prepare(
            "INSERT INTO `user` (`username`, `email`, `enabled`, `created_at`) "
            . "VALUES (:username, :email, :enabled, :created_at)"
        );

        self::assertInstanceOf($statementClass, $stmt);

        $result = $stmt->execute([
            ':username'   => "username-666",
            ':email'      => "email-666@emample.com",
            ':enabled'    => mt_rand(0, 1),
            ':created_at' => date('Y-m-d H:i:s', mt_rand(strtotime('-60 days'), time())),
        ]);

        self::assertTrue($result);
        self::assertSame(1, $stmt->rowCount());
    }

    public function test_method_exec_updatesRows()
    {
        $pdo = $this->createPDO();

        // update 1 row
        $result = $pdo->exec(
            "UPDATE `user` SET `username` = 'username-001' WHERE `id` = 1"
        );
        self::assertSame(1, $result);

        // update 2 rows
        $result = $pdo->exec(
            "UPDATE `user` SET `username` = `username` || '=' || `id` WHERE `id` IN (2, 3)"
        );
        self::assertSame(2, $result);
    }

    /**
     * @dataProvider provideLogAndExpectedStatementClass
     */
    public function test_method_query_usingSelectReturnRows(bool $log, string $statementClass)
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->query("SELECT * FROM `user`");

        self::assertInstanceOf(\PDOStatement::class, $stmt);

        $rows = $stmt->fetchAll($pdo::FETCH_ASSOC);

        self::assertTrue(is_array($rows));
        self::assertSame(10, count($rows));

        self::assertArrayHasKey('id', $rows[0]);
        self::assertArrayHasKey('username', $rows[0]);
        self::assertArrayHasKey('email', $rows[0]);
        self::assertArrayHasKey('enabled', $rows[0]);
        self::assertArrayHasKey('created_at', $rows[0]);

        self::assertSame('1', $rows[0]['id']);
        self::assertSame('username-001', $rows[0]['username']);
        self::assertSame('email-001@emample.com', $rows[0]['email']);
        self::assertTrue(in_array($rows[0]['enabled'], ['0', '1'], true));
        self::assertRegExp('/\d{4}\-\d{2}\-\d{2} [0-2][0-9]\:[0-5][0-9]\:[0-5][0-9]/', $rows[0]['created_at']);
        self::assertSame('0000-00-00 00:00:00', $rows[0]['updated_at']);
    }

    public function test_method_run_preparesAndExecutesStatement()
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->run("SELECT * FROM `user` WHERE `id` = :id", [':id' => 9]);
        $row = $stmt->fetch($pdo::FETCH_ASSOC);

        self::assertArrayHasKey('id', $row);
        self::assertArrayHasKey('username', $row);
        self::assertArrayHasKey('email', $row);
        self::assertArrayHasKey('enabled', $row);
        self::assertArrayHasKey('created_at', $row);

        self::assertSame('9', $row['id']);
        self::assertSame('username-009', $row['username']);
        self::assertSame('email-009@emample.com', $row['email']);
        self::assertTrue(in_array($row['enabled'], ['0', '1'], true));
        self::assertSame('0000-00-00 00:00:00', $row['updated_at']);
    }

    public function test_method_run_returnsFalseForInvalidQueryWithErrorModeSilent()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute($pdo::ATTR_ERRMODE, $pdo::ERRMODE_SILENT);
        $result = $pdo->run("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);

        self::assertFalse($result);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function test_method_run_triggersWarningForInvalidQueryWithErrorModeWarning()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute($pdo::ATTR_ERRMODE, $pdo::ERRMODE_WARNING);
        $pdo->run("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);
    }

    /**
     * @expectedException \PDOException
     */
    public function test_method_run_throwsPDOExceptionForInvalidQueryWithErrorModeException()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute($pdo::ATTR_ERRMODE, $pdo::ERRMODE_EXCEPTION);
        $pdo->run("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);
    }

    public function testQueryLogger()
    {
        $pdo = new PDO($this->dsn, '', '', [], [], true);

        $sql1 = "SELECT * FROM `user` WHERE `id` = :id";
        $sql2 = "SELECT `username` FROM `user` WHERE `id` = :id";
        $sql3 = "SELECT `email` FROM `user` WHERE `id` = :id";

        $pdo->run($sql1, [':id' => 1]); // 0
        $pdo->run($sql1, [':id' => 2]); // 1
        $pdo->run($sql2, [':id' => 3]); // 2
        $pdo->run($sql1, [':id' => 4]); // 3
        $pdo->run($sql2, [':id' => 5]); // 4
        $pdo->run($sql3, [':id' => 6]); // 5

        $log = $pdo->getLog();

        $queries = $log['queries'];
        $reruns  = $log['reruns'];

        self::assertSame(6, $log['count']);
        self::assertSame(3, count($reruns));

        self::assertSame(3, $reruns[md5($sql1)]['iter']);
        self::assertSame(2, $reruns[md5($sql2)]['iter']);
        self::assertSame(1, $reruns[md5($sql3)]['iter']);

        self::assertSame(1, $queries[0]['iter']);
        self::assertSame(2, $queries[1]['iter']);
        self::assertSame(1, $queries[2]['iter']);
        self::assertSame(3, $queries[3]['iter']);
        self::assertSame(2, $queries[4]['iter']);
        self::assertSame(1, $queries[5]['iter']);

        self::assertSame(1, $queries[0]['params'][':id']);
        self::assertSame(2, $queries[1]['params'][':id']);
        self::assertSame(3, $queries[2]['params'][':id']);
        self::assertSame(4, $queries[3]['params'][':id']);
        self::assertSame(5, $queries[4]['params'][':id']);
        self::assertSame(6, $queries[5]['params'][':id']);
    }

    // phpcs:enable

    public function provideLogAndExpectedStatementClass()
    {
        return [
            [false, \PDOStatement::class],
            [true, PDOStatement::class],
        ];
    }

    public function tearDown()
    {
        parent::tearDown();

        if (is_file($this->dbfile)) {
            unlink($this->dbfile);
        }
    }
}
