<?php

/**
 * @package   p3-pdo
 * @see       https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author    pine3ree https://github.com/pine3ree
 * @license   https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3\PDO\Profiling;

use P3\PDO\Profiling\PDO;

use function microtime;

/**
 * {@inheritDoc}
 *
 * Log and profile query execution info via the calling P3\PDO instance
 */
class PDOStatement extends \PDOStatement
{
    /**
     * @var PDO The P3\PDO instance that created this statement
     */
    private $pdo;

    private $params = [];

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** {@inheritDoc} */
    public function bindValue($parameter, $value, $data_type = null): bool
    {
        $result = parent::bindValue($parameter, $value, $data_type);
        if ($result) {
            $this->params[$parameter] = $value;
        }

        return $result;
    }

    /** {@inheritDoc} */
    public function bindParam(
        $parameter,
        &$variable,
        $data_type = PDO::PARAM_STR,
        $length = null,
        $driver_options = null
    ): bool {
        $result = parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
        if ($result) {
            $this->params[$parameter] = $value = $variable;
        }

        return $result;
    }

    /** {@inheritDoc} */
    public function execute($input_parameters = null): bool
    {
        $t0 = microtime(true);

        $result = parent::execute($input_parameters);

        $this->pdo->log(
            $this->queryString,
            microtime(true) - $t0,
            $input_parameters ?? $this->params
        );

        $this->params = [];

        return $result;
    }
}
