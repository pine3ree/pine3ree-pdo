<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDOTest;

use pine3ree\PDO;
use pine3ree\PDOTest\Profiling\AbstractPDOTest;

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
