# p3-PDO

[![Build Status](https://travis-ci.org/pine3ree/p3-pdo.svg?branch=master)](https://travis-ci.org/pine3ree/p3-pdo)

*A lazy-loading PDO drop-in replacement!*

p3-PDO extends PHP ext-PDO in order to provide on demand connection, connection
expiration with auto-reconnect and query logging/profiling.


## Installation

You can install this library using Composer (with "minimum-stability": "dev"):

```bash
$ composer require pine3ree/p3-pdo
```

## Documentation

Check the [php PDO book](https://www.php.net/manual/en/book.pdo.php) for standard ext PDO methods.

Continue reading below for additional methods.

### How to use the lazy pdo instance

Just instantiate the provided lazy class as you would with the standard ext-pdo
PDO class. A wrapped standard PDO instance will be created on demand when really needed.

```php
$pdo = new P3\PDO(
    $dsn = 'sqlite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = []
);
```

By default `P3\PDO` and its descendant `P3\PDO\Reconnecting\PDO` establish a
database connection on demand.

The methods that trigger the connection are:

- `P3\PDO::beginTransaction()`;
- `P3\PDO::exec(...)`;
- `P3\PDO::prepare(...)`;
- `P3\PDO::query(...)`;
- `P3\PDO::quote(...)`;
- `P3\PDO::execute(...)`;


### How to enable query-profiling

Query logging/profiling can be achieved via the provided profiling class and passing
another pdo instance (either a standard ext-pdo instance or an instance of a class
extending it (such as the lazy-pdo in this package) in the constructor:
```php
$pdo = new P3\PDO\Profiling\PDO(new \PDO(
    $dsn = 'slite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = []
));
```
You can retrieve the recorded information by calling the `P3\PDO\Profiling\PDO::getLog()` method.


### How to use the auto-reconnecting/connection-expiration instance

Use the provided reconnecting-pdo class with an extra `$ttl` constructor argument:

```php
$pdo = new P3\PDO\Reconnecting\PDO(
    $dsn = 'slite:my-db.sqlite3',
    $username = '',
    $password = '',
    $options = [],
    $ttl = 6, // drops the current connection after 6 seconds and establish a new one on demand
    $log = false
);
```


### Additional methods

#### P3\PDO::execute(): \PDOStatement|false
```
P3\PDO::execute(string $statement, array $input_parameters = [], array $driver_options = [])
```
combines `\PDO::prepare()` and `\PDOStatement::execute()` into one method call,
returning `false` if either the statement preparation or execution fails.

This method is inherited by `P3\PDO\Reconnecting\PDO`.

#### P3\PDO::isConnected(): bool

checks if we have an established database connection.

This method is inherited by `P3\PDO\Reconnecting\PDO` and also implemented in `P3\PDO\Profiling\PDO`.

#### P3\PDO\Profiling\PDO::getLog(): array
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
#### P3\PDO\Reconnecting\PDO::getConnectionCount(): int
returns the number of database connections performed so far

