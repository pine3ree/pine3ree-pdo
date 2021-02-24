<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDOTest\Profiling;

use P3\PDO\Profiling\PDO;
use P3\PDO\Profiling\PDOStatement;
use PHPUnit\Framework\TestCase;

use function date;
use function is_file;
use function in_array;
use function rand;
use function sprintf;
use function strtotime;
use function time;
use function unlink;

abstract class AbstractPDOTest extends TestCase
{
    /** @var string */
    protected $dbfile = "/tmp/p3-pdo-sqlit-test.db";

    /** @var string */
    protected $dsn = "sqlite:/tmp/p3-pdo-sqlit-test.db";

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

    abstract protected function createPDO();
    
    protected static function expectedStatementClass(): string
    {
        return \PDOStatement::class;
    }

    public function testInsertRow()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->prepare(
            "INSERT INTO `user` (`username`, `email`, `enabled`, `created_at`) "
            . "VALUES (:username, :email, :enabled, :created_at)"
        );

        self::assertInstanceOf(static::expectedStatementClass(), $stmt);

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

    public function test_method_query_usingSelectReturnRows()
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->query("SELECT * FROM `user`");

        self::assertInstanceOf(static::expectedStatementClass(), $stmt);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

    public function test_new_method_execute_preparesAndExecutesStatement()
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->execute("SELECT * FROM `user` WHERE `id` = :id", [':id' => 9]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

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

    public function test_method_execute_returnsFalseForInvalidQueryWithErrorModeSilent()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $result = $pdo->execute("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);

        self::assertFalse($result);
    }

    /**
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function test_method_execute_triggersWarningForInvalidQueryWithErrorModeWarning()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        $pdo->execute("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);
    }

    /**
     * @expectedException \PDOException
     */
    public function test_method_execute_throwsPDOExceptionForInvalidQueryWithErrorModeException()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->execute("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);
    }

    // phpcs:enable

    public function tearDown()
    {
        parent::tearDown();

        if (is_file($this->dbfile)) {
            unlink($this->dbfile);
        }
    }
}
