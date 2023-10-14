<?php

/**
 * @package   pine3ree-pdo
 * @see       https://github.com/pine3ree/pine3ree-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/pine3ree-pdo/blob/3.0.x/LICENSE.md New BSD License
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

    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $result = parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
        if ($result) {
            $this->params[$param] = $var;
        }

        return $result;
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $result = parent::bindValue($param, $value, $type);
        if ($result) {
            $this->params[$param] = $value;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @param array|mixed[]|array<int|string, mixed>|null $params
     */
    public function execute(?array $params = null): bool
    {
        $t0 = microtime(true);
        $result = parent::execute($params);
        $t1 = microtime(true);

        $this->pdo->log(
            $this->queryString,
            $t1 - $t0,
            $params ?? $this->params
        );

        // Clear registered bound values/params after execution
        $this->params = [];

        return $result;
    }
}
