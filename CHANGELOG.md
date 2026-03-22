# Changelog

All notable changes to this project will be documented in this file.

## [2.0.3] - 2026-03-23

### Fixed
- Added support for Laravel-style pivot tables that define a composite `unique(...)` key but no explicit primary key.
- Promoted the first non-null composite unique key to `PRIMARY KEY` during `CREATE TABLE` when Tarantool requires one.
- Added regression coverage for transition / pivot-style migrations with composite keys on Tarantool `latest`.

## [2.0.2] - 2026-03-23

### Changed
- Docker test environment now defaults to `tarantool/tarantool:latest`.
- Connection configuration examples now document `sql_seq_scan`.

### Fixed
- Enabled `sql_seq_scan` at the Tarantool SQL session level by default to support Laravel migration repository queries on Tarantool 3.x.
- Moved session setup to lazy initialization so connections no longer fail during early container startup.
- Added regression coverage for `DatabaseMigrationRepository` queries against Tarantool `latest`.

## [2.0.0] - 2026-03-22

### Added
- Laravel 11, 12, and 13 support.
- Docker-based PHP and Tarantool integration environment.
- Integration test matrix for Laravel 11, 12, and 13.
- Migration coverage for common Laravel application tables, including `users`, `password_reset_tokens`, and `sessions`.

### Changed
- Package name updated to `kemel91/laravel-tarantool`.
- Package metadata updated for the `Kemel91` fork.
- PHP requirement updated to `^8.2`.
- Supported `illuminate/*` versions updated to `^11.0 || ^12.0 || ^13.0`.
- Tarantool client compatibility updated to support newer driver releases.
- CI workflow updated to run the Laravel support matrix in Docker.

### Fixed
- Query and schema grammar compatibility with modern Laravel versions.
- `QueryException` handling for current `illuminate/database` releases.
- Builder method signatures required by Laravel 13.
- `insert`, `update`, `delete`, and `statement` return values to match Laravel expectations.
- Identifier wrapping that could break Eloquent lookups such as `find()`.
- Schema creation for primary keys and `dropIfExists()`.
- Migration execution for Laravel-style tables using `id()`, `timestamps()`, `rememberToken()`, `unique()`, and indexed foreign keys.
