# Changelog

Changes are documented in reverse chronological order by release.


## 1.1.1 - 2021-02-25

### Added

- Nothing

### Deprecated

- Nothing

### Removed

- Nothing

### Fixed

- Nothing.


## 1.1.0 - 2021-02-25

### Added

- Added `P3\PDO\Profiling\PDO::isConnected():  bool` method

### Deprecated

- Nothing

### Removed

- Nothing

### Fixed

- Changed reconnecting pdo default timeout to 30 seconds according to the default
  ext-pdo attribute `PDO::ATTR_TIMEOUT`.


## 1.0.0 - 2021-02-24

This is a BC-breaking update.

The lazy-connection, the profiling and the auto-disconnect/reconnect features have
been implemented using separate classes.

### Added

- Added separate profiling classes P3\PDO\Profiling\PDO and P3\PDO\Profiling\PDOStatement
- Added separate reconnecting class P3\PDO\Reconnecting\PDO

### Deprecated

- Nothing

### Removed

- The base class now only provides the lazy-connection feature, extra constructor arguments and
  getLog() methods have been removed

### Fixed

- Nothing.


## 0.6.0 - 2020-05-11

### Added

- Added $ttl property to enable automatic reconnection after ttl seconds
- P3\PDO now wraps an internal \PDO instance but stills extends the \PDO class
- Added `$dsn`, `$connections` and `$ttl` to the log-data

### Deprecated

- Nothing.

### Removed

- The getLOG() array 'queries' key has been replaced by the 'statements' key

### Fixed

- Nothing.


## 0.5.1 - 2020-05-11

### Added

- Added the possibility of retrieving the database driver name from the dsn
  without establishing a connection

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


## 0.5.0 - 2020-04-24

### Added

- Added logging for parameter-binding in pdo-statement

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


## 0.4.1 - 2020-04-18

### Added

- Fixed and readded static analysis

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


## 0.4.0 - 2020-04-18

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Removed the $attributes argument in constructor to make the signature closer
  to ext-pdo

- The previously public @internal method `connect()` is now private

### Fixed

- Nothing.


## 0.3.0 - 2020-04-16

The custom P3\PDOStatement class is now used only if query-logging is enabled.

### Added

- added private method for internal query profiling

- updated setAttribute statement class validation

- added test cases

### Deprecated

- Nothing.

### Removed

- Removed internal PDOStatement `$log` property as form now on this class is
  only used when profiling.

- Removed composer shortcuts `cs-check`, `cs-fix` in favor of `check-cs`, `fix-cs`
  Removed `check` shortcut.

### Fixed

- Fixed README file query-logging constructor example


## 0.2.0 - 2020-04-14

### Added

- Documentation about methods triggering a database connection.

### Deprecated

- Nothing.

### Removed

- Removed unused/reduntant and undocumented log/debug methods, namely:
    - `getExecutedQueries(...)`;
    - `getTotalExecTime()`;
    - `getTotalQueryCount(...)`;
- Remove reference to virtual property in php-doc block leftover from older implementation

### Fixed

- Nothing.


## 0.1.1 - 2020-04-14

### Added

- Added licence and type key in composer.json file.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


## 0.1.0 - 2020-04-14

Initial release.

### Added

- Everything.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
