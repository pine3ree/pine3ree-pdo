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

use function date;
use function md5;
use function mt_rand;
use function time;

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

    protected function createLazyPDO(): PDO
    {
        return new PDO(new LazyPDO($this->dsn, '', ''));
    }

    protected function getDecoratedPDO(PDO $pdo): \PDO
    {
        $rc = new ReflectionClass($pdo);
        $rp = $rc->getProperty('pdo');
        $rp->setAccessible(true);

        return $rp->getValue($pdo);
    }

    // phpcs:disable

    public function test_method_prepare_returnsProfilingPdoStatement()
    {
        $pdo = $this->createPDO();
        $result = $pdo->prepare("SELECT * FROM user WHERE id = :id");

        self::assertInstanceOf(PDOStatement::class, $result);
    }

    public function test_method_setAttribute_throwsPDOExceptionIfSettingInvalidStatementClass()
    {
        $pdo = $this->createPDO();

        $this->expectException(\PDOException::class);
        $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
    }

    public function test_method_beginTransaction_createsDbConnectionWithExtPDO()
    {
        $pdo = $this->createPDO();
        $pdo->beginTransaction();
        self::assertTrue($pdo->isConnected());
    }

    public function test_method_beginTransaction_createsDbConnectionWithLazyPDO()
    {
        $pdo = $this->createLazyPDO();
        $pdo->beginTransaction();
        self::assertTrue($pdo->isConnected());
    }

    public function test_method_quote_createsDbConnection()
    {
        $pdo = $this->createPDO();

        $pdo->quote('!"£$%&/()=?^^', \PDO::PARAM_STR);

        self::assertTrue($pdo->isConnected());

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame(
            $pdo->quote('!"£$%&/()=?^^', \PDO::PARAM_STR),
            $phpPdo->quote('!"£$%&/()=?^^', \PDO::PARAM_STR)
        );
    }

    public function test_methods_setAttribute_and_getAttribute()
    {
        $attribute = \PDO::ATTR_DEFAULT_FETCH_MODE;
        $attrValue = \PDO::FETCH_ASSOC;

        $pdo = $this->createLazyPDO();
        $pdo->setAttribute($attribute, $attrValue);

        self::assertSame($attrValue, $pdo->getAttribute($attribute));

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame($attrValue, $phpPdo->getAttribute($attribute));
    }

    public function test_methods_errorCode_and_errorInfo_returnSameValuesAsDecoratedPdo()
    {
        $pdo = $this->createPDO();

        // Silence errors
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame('00000', $pdo->errorCode());
        self::assertSame('00000', $phpPdo->errorCode());

        $pdo->prepare('bogus sql');

        self::assertNotSame('000000', $pdo->errorCode());
        self::assertNotSame('000000', $phpPdo->errorCode());

        self::assertSame($phpPdo->errorCode(), $pdo->errorCode());
        self::assertSame($phpPdo->errorInfo(), $pdo->errorInfo());
    }

    public function test_method_lastInsertId_returnsSameValuesAsDecoratedPdo()
    {
        $pdo = $this->createPDO();

        $phpPdo = $this->getDecoratedPDO($pdo);

        self::assertSame($phpPdo->lastInsertId(), $pdo->lastInsertId());

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

    // phpcs:enable

    public function testStatementLogger()
    {
        $pdo = $this->createPDO(0, true);

        $sql1 = "SELECT * FROM user WHERE id = :id";
        $sql2 = "SELECT username FROM user WHERE id = :id";
        $sql3 = "SELECT email FROM user WHERE id = :id";

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
}
