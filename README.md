# p3-PDO

[![Build Status](https://travis-ci.org/pine3ree/p3-pdo.svg?branch=master)](https://travis-ci.org/pine3ree/p3-pdo)

*A lazy-loading PDO drop-in replacement!*

p3-PDO extends PHP ext-PDO in order to provide on demand connection and query
logging/profiling.


## Installation

You can install this library using Composer:

```bash
$ composer require pine3ree/p3-pdo
```

## Documentation

 Check the [php PDO book](https://www.php.net/manual/en/book.pdo.php) for standard ext PDO methods.

Continue reading below for additional methods


### Additional methods

#### P3\PDO::run(): PDOStatement|false
```
P3\PDO::run(string $statement, array $input_parameters = [], array $driver_options = [])
```
combines `\PDO::prepare()` and `\PDOStatement::execute()` into one method call.

#### P3\PDO::isConnected(): bool

checks if we have an established database connection.

#### P3\PDO::getLog(): array
return logged profiling information about all the executed queries with the following format:
```php
[
	// every runned query including re-runs
	'queries' => [
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
            'time'   => 0.000254..., // total time for all re-runs
    ],
    'time'  => 23.5678, // total query time
    'count' => 15, // total query count
];
```
