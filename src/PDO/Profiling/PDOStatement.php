<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/master/LICENSE.md New BSD License
 */

namespace pine3ree\PDO\Profiling;

use pine3ree\PDO\Profiling\PDO;

use function microtime;

/**
 * {@inheritDoc}
 *
 * Log and profile query execution info via the calling pine3ree\PDO instance
 */
class PDOStatement extends \PDOStatement
{
    /** The pine3ree\PDO instance that created this statement */
    private PDO $pdo;

    /**
     * Params accumulator
     *
     * @var array|mixed[]|array<string|int, mixed>
     */
    private array $params = [];

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindValue($param, $value, $type = null): bool
    {
        $result = parent::bindValue($param, $value, $type);
        if ($result) {
            $this->params[$param] = $value;
        }

        return $result;
    }

    public function bindParam(
        $param,
        &$var,
        $type = PDO::PARAM_STR,
        $maxLength = null,
        $driverOptions = null
    ): bool {
        $result = parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
        if ($result) {
            $this->params[$param] = $var;
        }

        return $result;
    }

    public function execute($params = null): bool
    {
        $t0 = microtime(true);

        $result = parent::execute($params);

        $this->pdo->log(
            $this->queryString,
            microtime(true) - $t0,
            $params ?? $this->params
        );

        $this->params = [];

        return $result;
    }
}
