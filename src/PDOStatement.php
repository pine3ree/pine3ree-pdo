<?php

/**
 * @package     p3-pdo
 * @see         https://github.com/pine3ree/p3-pdo for the canonical source repository
 * @copyright   https://github.com/pine3ree/p3-pdo/blob/master/COPYRIGHT.md
 * @author      pine3ree https://github.com/pine3ree
 * @license     https://github.com/pine3ree/p3-pdo/blob/master/LICENSE.md New BSD License
 */

namespace P3;

use P3\PDO;

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

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** {@inheritDoc} */
    public function execute($input_parameters = null): bool
    {
        $t0 = microtime(true);

        $result = parent::execute($input_parameters);

        $this->pdo->log(
            $this->queryString,
            microtime(true) - $t0,
            $input_parameters
        );

        return $result;
    }
}
