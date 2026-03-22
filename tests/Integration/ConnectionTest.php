<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Tests\Integration;

use Chocofamily\Tarantool\ServiceProvider;
use Chocofamily\Tarantool\Tests\Fixtures\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Throwable;

class ConnectionTest extends TestCase
{
    private Container $app;

    private DatabaseManager $db;

    protected function setUp(): void
    {
        parent::setUp();

        $connectionConfig = [
            'driver' => 'tarantool',
            'host' => getenv('TARANTOOL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('TARANTOOL_PORT') ?: 3301),
            'database' => getenv('TARANTOOL_DATABASE') ?: 'default',
            'username' => getenv('TARANTOOL_USER') ?: 'app',
            'password' => getenv('TARANTOOL_PASSWORD') ?: 'secret',
        ];

        $this->app = new Container();
        Container::setInstance($this->app);

        $this->app->instance(ContainerContract::class, $this->app);
        $this->app->instance('app', $this->app);
        $this->app->instance('config', new \ArrayObject([
            'database.default' => 'tarantool',
            'database.connections' => [
                'tarantool' => $connectionConfig,
            ],
        ], \ArrayObject::ARRAY_AS_PROPS));
        $this->app->singleton('events', fn (Container $app): Dispatcher => new Dispatcher($app));
        $this->app->singleton('db.factory', fn (Container $app): ConnectionFactory => new ConnectionFactory($app));
        $this->app->singleton('db', fn (Container $app): DatabaseManager => new DatabaseManager($app, $app['db.factory']));
        $this->app->singleton('db.schema', fn (Container $app) => $app['db']->connection()->getSchemaBuilder());

        $provider = new ServiceProvider($this->app);
        $provider->register();

        $this->db = $this->app->make('db');
        $provider->boot();
        Facade::setFacadeApplication($this->app);

        $this->dropTables('users', 'password_reset_tokens', 'sessions');
    }

    protected function tearDown(): void
    {
        $this->dropTables('users', 'password_reset_tokens', 'sessions');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_registers_the_tarantool_connection(): void
    {
        $this->createBasicUsersTable();

        $connection = $this->db->connection('tarantool');

        self::assertSame('tarantool', $connection->getDriverName());
        self::assertSame(0, $connection->table('users')->count());
    }

    public function test_query_builder_insert_update_delete_and_aggregate_work(): void
    {
        $this->createBasicUsersTable();

        $connection = $this->db->connection('tarantool');

        $id = $connection->table('users')->insertGetId([
            'name' => 'Alice',
            'age' => 30,
        ]);

        self::assertIsInt($id);
        self::assertTrue($connection->table('users')->insert([
            'name' => 'Bob',
            'age' => null,
        ]));
        self::assertSame(2, $connection->table('users')->count());
        self::assertSame(1, $connection->table('users')->where('id', $id)->update([
            'name' => 'Alice Updated',
        ]));
        self::assertSame('Alice Updated', $connection->table('users')->where('id', $id)->value('name'));
        self::assertSame(1, $connection->table('users')->where('id', $id)->delete());
        self::assertSame(1, $connection->table('users')->count());
    }

    public function test_eloquent_models_use_the_tarantool_connection(): void
    {
        $this->createBasicUsersTable();

        $user = User::query()->create([
            'name' => 'Charlie',
            'age' => 27,
        ]);

        $fresh = User::query()->findOrFail($user->getKey());

        self::assertSame('tarantool', $fresh->getConnectionName());
        self::assertSame('Charlie', $fresh->name);
        self::assertSame(27, $fresh->age);
    }

    public function test_default_laravel_style_migration_runs_successfully(): void
    {
        $migration = new class extends Migration
        {
            public function up(): void
            {
                Schema::create('users', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->string('email')->unique();
                    $table->timestamp('email_verified_at')->nullable();
                    $table->string('email_verification_token')->nullable();
                    $table->string('password');
                    $table->rememberToken();
                    $table->timestamps();
                });

                Schema::create('password_reset_tokens', function (Blueprint $table): void {
                    $table->string('email')->primary();
                    $table->string('token');
                    $table->timestamp('created_at')->nullable();
                });

                Schema::create('sessions', function (Blueprint $table): void {
                    $table->string('id')->primary();
                    $table->foreignId('user_id')->nullable()->index();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->longText('payload');
                    $table->integer('last_activity')->index();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('users');
                Schema::dropIfExists('password_reset_tokens');
                Schema::dropIfExists('sessions');
            }
        };

        $migration->up();

        $connection = $this->db->connection('tarantool');
        $tables = $connection->getSchemaBuilder()->getTableListing();

        self::assertContains('users', array_map('strtolower', $tables));
        self::assertContains('password_reset_tokens', array_map('strtolower', $tables));
        self::assertContains('sessions', array_map('strtolower', $tables));

        $userId = $connection->table('users')->insertGetId([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'email_verified_at' => null,
            'email_verification_token' => 'verify-token',
            'password' => 'secret',
            'remember_token' => 'remember-token',
            'created_at' => '2026-03-22 00:00:00',
            'updated_at' => '2026-03-22 00:00:00',
        ]);

        self::assertIsInt($userId);
        self::assertSame(1, $connection->table('users')->count());

        $connection->table('password_reset_tokens')->insert([
            'email' => 'alice@example.com',
            'token' => 'reset-token',
            'created_at' => '2026-03-22 00:00:00',
        ]);

        $connection->table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => 123456,
        ]);

        self::assertSame('remember-token', $connection->table('users')->where('id', $userId)->value('remember_token'));
        self::assertSame(1, $connection->table('password_reset_tokens')->count());
        self::assertSame(1, $connection->table('sessions')->count());

        $migration->down();

        $tablesAfterDown = array_map('strtolower', $connection->getSchemaBuilder()->getTableListing());

        self::assertNotContains('users', $tablesAfterDown);
        self::assertNotContains('password_reset_tokens', $tablesAfterDown);
        self::assertNotContains('sessions', $tablesAfterDown);
    }

    private function createBasicUsersTable(): void
    {
        $this->db->connection('tarantool')->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->integer('age')->nullable();
        });
    }

    private function dropTables(string ...$tables): void
    {
        $connection = $this->db->connection('tarantool');

        foreach ($tables as $table) {
            try {
                $connection->statement(sprintf('drop table "%s"', strtoupper($table)));
            } catch (Throwable $exception) {
            }
        }
    }
}
