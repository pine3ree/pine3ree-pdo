# Changelog

Changes are documented in reverse chronological order by release.


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
