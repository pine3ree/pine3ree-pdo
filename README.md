# pine3ree-PDO

[![Continuous Integration](https://github.com/pine3ree/pine3ree-pdo/actions/workflows/continuous-integration.yml/badge.svg?branch=3.0.x)](https://github.com/pine3ree/pine3ree-pdo/actions/workflows/continuous-integration.yml)

*A lazy-loading PDO drop-in replacement!*

pine3ree-PDO extends PHP ext-PDO in order to provide on demand connection, connection
expiration with auto-reconnect and query logging/profiling.


## Installation

This version (`3.0.x`) of the library requires `php ~8.0 || ~8.1.0 || ~8.2.0`.

For php-7.4 support please use version `2.0.x`.

You can install it library using Composer (with "minimum-stability": "dev"):

```bash
$ composer require pine3ree/pine3ree-pdo
```

## Documentation

Check the [php PDO book](https://www.php.net/manual/en/book.pdo.php) for standard ext PDO methods.

Continue reading below for additional methods.

### How to use the lazy pdo instance

Just instantiate the provided lazy class as you would with the standard ext-pdo
PDO class. A wrapped standard PDO instance will be created on demand when really needed.

```php
$pdo = new pine3ree\PDO(
    $dsn = 'sqlite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = []
);
```

By default `pine3ree\PDO` and its descendant `pine3ree\PDO\Reconnecting\PDO` establish a
database connection on demand.

The methods that trigger the connection are:

- `pine3ree\PDO::beginTransaction()`;
- `pine3ree\PDO::exec(...)`;
- `pine3ree\PDO::prepare(...)`;
- `pine3ree\PDO::query(...)`;
- `pine3ree\PDO::quote(...)`;
- `pine3ree\PDO::execute(...)`;


### How to enable query-profiling

Query logging/profiling can be achieved via the provided profiling class and passing
another pdo instance (either a standard ext-pdo instance or an instance of a class
extending it (such as the lazy-pdo in this package) in the constructor:
```php
$pdo = new pine3ree\PDO\Profiling\PDO(new \PDO(
    $dsn = 'sqlite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = []
));
```
You can retrieve the recorded information by calling the `pine3ree\PDO\Profiling\PDO::getLog()` method.


### How to use the auto-reconnecting/connection-expiration instance

Use the provided reconnecting-pdo class with an extra `$ttl` constructor argument:

```php
$pdo = new pine3ree\PDO\Reconnecting\PDO(
    $dsn = 'sqlite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = [],
    $ttl = 6 // drops the current connection after 6 seconds and establish a new one on demand
);
```


### Additional methods

#### pine3ree\PDO::execute(): \PDOStatement|false
```
pine3ree\PDO::execute(string $statement, array $input_parameters = [], array $driver_options = [])
```
combines `\PDO::prepare()` and `\PDOStatement::execute()` into one method call,
returning `false` if either the statement preparation or execution fails.

This method is inherited by `pine3ree\PDO\Reconnecting\PDO`.

#### pine3ree\PDO::isConnected(): bool

checks if we have an established database connection.

This method is inherited by `pine3ree\PDO\Reconnecting\PDO` and also implemented in `pine3ree\PDO\Profiling\PDO`.

#### pine3ree\PDO\Profiling\PDO::getLog(): array
returns recorded profiling information about all the executed statements in the
following format:
```php
[
    // every runned query including re-runs
    'statements' => [
        0 => [...],
        1 => [...],
        //....
        n => [
            'sql'    => "SELECT * FROM `user` WHERE `id` = :id",
            'iter'   => 2, // the iteration index for this sql expression
            'time'   => 0.000254..., // in seconds.microseconds
            'params' => [':id' => 123]
        ],
    ],
    // queries indexed by sql expression
    'reruns' => [
        'md5(sql1)' => [...],
        //...,
        'md5(sqln)' => [
            'sql'    => "SELECT * FROM `users` WHERE `status` = 1",
            'iter'   => 5, // the number of iterations for this sql expression
            'time'   => 0.001473..., // total time for all re-runs
        ],
    ],
    'time'  => 23.5678, // total query time
    'count' => 15, // total query count
];
```
#### pine3ree\PDO\Reconnecting\PDO::getConnectionCount(): int
returns the number of database connections performed so far

