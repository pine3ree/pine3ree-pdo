<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest\Profiling;

use Error;
use PHPUnit\Framework\TestCase;
use Throwable;

use function date;
use function in_array;
use function is_file;
use function rand;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function strtotime;
use function time;
use function unlink;

use const PHP_VERSION_ID;

abstract class AbstractPDOTest extends TestCase
{
    /** @var string */
    protected $dbfile = "/tmp/pine3ree-pdo-sqlite-test.db";

    /** @var string */
    protected $dsn = "sqlite:/tmp/pine3ree-pdo-sqlite-test.db";

    protected const SQL_INSERT = <<<EOSQL
        INSERT INTO user
            (username, email, enabled, created_at)
        VALUES
            (:username, :email, :enabled, :created_at)
        EOSQL;

    protected const SQL_UPDATE = <<<EOSQL
        UPDATE user
            SET enabled    = :enabled,
                updated_at = :updated_at
        WHERE
            id < :id
        EOSQL;

    public function setUp(): void
    {
        $pdo = new \PDO($this->dsn);
        $pdo->exec(<<<EOSQL
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                email TEXT UNIQUE,
                enabled INTEGER DEFAULT '0',
                created_at TEXT DEFAULT '0000-00-00 00:00:00',
                updated_at TEXT DEFAULT '0000-00-00 00:00:00'
            );
            EOSQL
        );

        $stmt = $pdo->prepare(self::SQL_INSERT);

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

        $stmt = $pdo->prepare(self::SQL_INSERT);

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

    // phpcs:disable

    public function test_method_exec_updatesRows()
    {
        $pdo = $this->createPDO();

        // update 1 row
        $result = $pdo->exec(
            "UPDATE user SET username = 'username-001' WHERE id = 1"
        );
        self::assertSame(1, $result);

        // update 2 rows
        $result = $pdo->exec(
            "UPDATE user SET username = username || '=' || id WHERE id IN (2, 3)"
        );
        self::assertSame(2, $result);
    }

    public function test_method_query_usingSelectReturnRows()
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->query("SELECT * FROM user", null);

        self::assertInstanceOf(static::expectedStatementClass(), $stmt);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        self::assertTrue(is_array($rows));
        self::assertSame(10, count($rows));

        self::assertArrayHasKey('id', $rows[0]);
        self::assertArrayHasKey('username', $rows[0]);
        self::assertArrayHasKey('email', $rows[0]);
        self::assertArrayHasKey('enabled', $rows[0]);
        self::assertArrayHasKey('created_at', $rows[0]);

        self::assertSame(PHP_VERSION_ID < 80100 ? '1' : 1, $rows[0]['id']);
        self::assertSame('username-001', $rows[0]['username']);
        self::assertSame('email-001@emample.com', $rows[0]['email']);
        self::assertTrue(in_array($rows[0]['enabled'], PHP_VERSION_ID < 80100 ? ['0', '1'] : [0, 1], true));
        self::assertMatchesRegularExpression('/\d{4}\-\d{2}\-\d{2} [0-2][0-9]\:[0-5][0-9]\:[0-5][0-9]/', $rows[0]['created_at']);
        self::assertSame('0000-00-00 00:00:00', $rows[0]['updated_at']);
    }

    public function test_new_method_execute_preparesAndExecutesStatement()
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->execute("SELECT * FROM user WHERE id = :id", [':id' => 9]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertArrayHasKey('id', $row);
        self::assertArrayHasKey('username', $row);
        self::assertArrayHasKey('email', $row);
        self::assertArrayHasKey('enabled', $row);
        self::assertArrayHasKey('created_at', $row);

        self::assertSame(PHP_VERSION_ID < 80100 ? '9' : 9, $row['id']);
        self::assertSame('username-009', $row['username']);
        self::assertSame('email-009@emample.com', $row['email']);
        self::assertTrue(in_array($row['enabled'], PHP_VERSION_ID < 80100 ? ['0', '1'] : [0, 1], true));
        self::assertSame('0000-00-00 00:00:00', $row['updated_at']);
    }

    public function test_method_execute_returnsFalseForInvalidQueryWithErrorModeSilent()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $result = $pdo->execute("SELECT * FROM user WHERE nonexistent = :nonexistent", [':nonexistent' => 42]);

        self::assertFalse($result);
    }

    public function test_method_execute_triggersWarningForInvalidQueryWithErrorModeWarning()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        $this->expectException(Error::class);
        set_error_handler(function(int $errno, string $errstr) {
            throw new Error($errstr, $errno);
        });
        try {
            $pdo->execute("SELECT * FROM `user` WHERE `nonexistent` = :nonexistent", [':nonexistent' => 42]);
            restore_error_handler();
        } catch (Throwable $ex) {
            restore_error_handler();
            throw $ex;
            $this->expectException(Error::class);
        }
    }

    public function test_method_execute_throwsPDOExceptionForInvalidQueryWithErrorModeException()
    {
        $pdo = $this->createPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->expectException(\PDOException::class);
        $pdo->execute("SELECT * FROM user WHERE nonexistent = :nonexistent", [':nonexistent' => 42]);
    }

    // phpcs:enable

    public function tearDown(): void
    {
        parent::tearDown();

        if (is_file($this->dbfile)) {
            unlink($this->dbfile);
        }
    }
}
