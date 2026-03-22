# Laravel Tarantool

This package adds functionalities to the Eloquent model and Query builder for Tarantool, using the original Laravel API. *This library extends the original Laravel classes, so it uses exactly the same methods.*

Installation
------------
#### Laravel Version Compatibility

Laravel  | Package
:---------|:----------
 11.x     | 2.x
 12.x     | 2.x
 13.x     | 2.x


#### Via Composer

``` 
composer require kemel91/laravel-tarantool
```

Configuration
-------------

You can use Tarantool either as the main database, either as a side database. To do so, add a new `tarantool` connection to `config/database.php`:

```php
'tarantool' => [
    'driver'   => 'tarantool',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => env('DB_PORT', 3301),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'type'     => env('DB_CONNECTION_TYPE', 'tcp'),
    'options'  => [
        'connect_timeout' => 5,
        'max_retries' => 3
    ]
],
```

The package also accepts `driver_options.connection_type` for backward compatibility, but `type` is the preferred option.

Running tests with Docker Compose
---------------------------------

The repository now includes a Docker-based test environment, so PHP and Tarantool do not need to be installed locally.

Start Tarantool:

```bash
docker compose up -d tarantool
```

Run the full Laravel compatibility matrix:

```bash
docker compose run --rm --user "$(id -u):$(id -g)" php sh bin/test-matrix
```

Run tests for a single Laravel major version:

```bash
docker compose run --rm --user "$(id -u):$(id -g)" php sh bin/test-laravel 12
```

Set tarantool as main database

```php
'default' => env('DB_CONNECTION', 'tarantool'),
```

You can also configure connection with dsn string:

```php
'tarantool' => [
    'driver'   => 'tarantool',
    'dsn' => env('DB_DSN'),
    'database' => env('DB_DATABASE'),
],
```
