<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest;

use pine3ree\PDO;
use pine3ree\PDOTest\Profiling\AbstractPDOTest;
use ReflectionClass;
use stdClass;

use function is_array;
use function time;

final class PDOTest extends AbstractPDOTest
{
    protected static function expectedStatementClass(): string
    {
        return \PDOStatement::class;
    }

    protected function createPDO(): PDO
    {
        return new PDO($this->dsn, '', '', []);
    }

    protected function createPDOfromDSN(string $dsn): PDO
    {
        return new PDO($dsn, '', '', []);
    }

    protected function getDecoratedPDO(PDO $pdo): \PDO
    {
        $rc = new ReflectionClass($pdo);
        $rm = $rc->getMethod('pdo');
        $rm->setAccessible(true);

        return $rm->invoke($pdo);
    }

    // phpcs:disable

    /**
     * @dataProvider provideDSNs
     */
    public function test_method_getAttribute_returnsCorrectDrivernameIfNotConnected(string $dsn, string $driver_name)
    {
        $pdo = $this->createPDOfromDSN($dsn);
        self::assertSame($driver_name, $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function test_method_errorCode_returnsNoErrorStringIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame('00000', $pdo->errorCode());
    }

    public function test_methods_errorCode_and_errorInfo_returnSameValuesAsDecoratedPdoIfConnected()
    {
        $pdo = $this->createPDO();

        // Silence errors
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $phpPdo = $this->getDecoratedPDO($pdo);

        $pdo->prepare('bogus sql');

        self::assertSame($phpPdo->errorCode(), $pdo->errorCode());
        self::assertSame($phpPdo->errorInfo(), $pdo->errorInfo());
    }

    public function test_method_errorInfo_returnsNoErrorArrayIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame(['00000', null, null], $pdo->errorInfo());
    }

    public function test_method_lastInsertId_returnsFalseIfNotConnected()
    {
        $pdo = $this->createPDO();
        self::assertSame(false, $pdo->lastInsertId());
    }

    public function test_method_lastInsertId_returnsSameValuesAsDecoratedPdo()
    {
        $pdo = $this->createPDO();

        $phpPdo = $this->getDecoratedPDO($pdo);

        $pdo->exec(self::SQL_INSERT);

        $time = time();

        $stmt = $pdo->prepare(self::SQL_INSERT);

        $stmt->execute([
            ':username'   => "username-{$time}",
            ':email'      => "email-{$time}@emample.com",
            ':enabled'    => mt_rand(0, 1),
            ':created_at' => date('Y-m-d H:i:s', $time),
        ]);

        self::assertSame(1, $stmt->rowCount());
        self::assertSame($phpPdo->lastInsertId(), $pdo->lastInsertId());
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

    public function test_method_quote_createsDbConnection()
    {
        $pdo = $this->createPDO();
        $pdo->quote('!"£$%&/()=?^^', \PDO::PARAM_STR);
        self::assertTrue($pdo->isConnected());
    }

    public function test_methods_setAttribute_and_getAttribute()
    {
        $attribute = \PDO::ATTR_DEFAULT_FETCH_MODE;
        $attrValue = \PDO::FETCH_ASSOC;

        $pdo = $this->createPDO();
        $pdo->setAttribute($attribute, $attrValue);

        self::assertSame($attrValue, $pdo->getAttribute($attribute));

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame($attrValue, $phpPdo->getAttribute($attribute));

        $attrValue = \PDO::FETCH_BOTH;
        $pdo->setAttribute($attribute, $attrValue);

        self::assertSame($attrValue, $pdo->getAttribute($attribute));
        self::assertSame($attrValue, $phpPdo->getAttribute($attribute));
    }

    public function test_methods_getAttribute_returnsEmptyStringForStatusAttributesIfNotConnected()
    {
        $pdo = $this->createPDO();

        self::assertSame('', $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS));
        self::assertSame('', $pdo->getAttribute(\PDO::ATTR_SERVER_INFO));
        self::assertSame('', $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION));
    }

    public function testTransactionMethodsWhenConnected()
    {
        $pdo = $this->createPDO();

        $phpPdo = $this->getDecoratedPDO($pdo);

        //----------------------------------------------------------------------

        $pdo->beginTransaction();

        self::assertTrue($pdo->inTransaction());
        self::assertTrue($phpPdo->inTransaction());

        $stmt = $pdo->prepare(self::SQL_UPDATE);

        $result = $stmt->execute([
            'enabled'    => mt_rand(0, 1),
            'updated_at' => date('Y-m-d H:i:s'),
            'id'         => 4,
        ]);

        self::assertSame(3, $stmt->rowCount());

        $pdo->commit();

        self::assertFalse($pdo->inTransaction());
        self::assertFalse($phpPdo->inTransaction());

        //----------------------------------------------------------------------

        $pdo->beginTransaction();

        self::assertTrue($pdo->inTransaction());
        self::assertTrue($phpPdo->inTransaction());

        $stmt = $pdo->prepare(self::SQL_UPDATE);

        $result = $stmt->execute([
            'enabled'    => mt_rand(0, 1),
            'updated_at' => date('Y-m-d H:i:s'),
            'id'         => 4,
        ]);

        self::assertSame(3, $stmt->rowCount());

        $pdo->rollBack();

        self::assertFalse($pdo->inTransaction());
        self::assertFalse($phpPdo->inTransaction());
    }

    public function test_method_query()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->query("SELECT * FROM user WHERE id = 1");

        $row = $stmt->fetch();

        self::assertTrue(is_array($row));
        self::assertArrayHasKey('id', $row);
        self::assertArrayHasKey(0, $row);

        self::assertSame($row['id'], $row[0], $row['id']);
    }

    public function test_method_query_with_fetchMode()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->query("SELECT * FROM user WHERE id = 2", \PDO::FETCH_ASSOC);

        $row = $stmt->fetch();

        self::assertTrue(is_array($row));
        self::assertArrayHasKey('id', $row);
        self::assertArrayNotHasKey(0, $row);

        // SInce php 8.1 integer columns are retuned as php int
        self::assertSame('2', $row['id']);
    }

    public function test_method_query_with_fetchInto()
    {
        $pdo = $this->createPDO();

        $obj = new stdClass();

        $stmt = $pdo->query("SELECT * FROM user WHERE id = 3", \PDO::FETCH_INTO, $obj);

        $obj = $stmt->fetch();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertSame('username-003', $obj->username);
    }

    public function test_method_query_with_fetchColumn()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->query("SELECT * FROM user WHERE id = 4", \PDO::FETCH_COLUMN, 1);

        $value = $stmt->fetch();

        self::assertSame('username-004', $value);
    }

    public function test_method_query_with_fetchClass()
    {
        $pdo = $this->createPDO();

        $stmt = $pdo->query("SELECT * FROM user WHERE id = 5", \PDO::FETCH_CLASS, stdClass::class);

        $obj = $stmt->fetch();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertSame('username-005', $obj->username);
    }

    // phpcs:enable

    public function provideDSNs()
    {
        return [
            ['mysql:dbname=mydb;host=localhost;port=3306;charset=utf8', 'mysql'],
            ['pgsql:dbname=mydb;host=localhost', 'pgsql'],
            ['sqlite::memory:;', 'sqlite'],
            ['sqlsrv:Database=mydb;Server=localhost,12345', 'sqlsrv'],
            ['oci:dbname=//localhost:1234/mydb;charset=utf8', 'oci'],
        ];
    }
}
