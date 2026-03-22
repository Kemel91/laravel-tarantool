<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Tests\Integration;

use Chocofamily\Tarantool\ServiceProvider;
use Chocofamily\Tarantool\Tests\Fixtures\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
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

        $provider = new ServiceProvider($this->app);
        $provider->register();

        $this->db = $this->app->make('db');
        $provider->boot();

        $connection = $this->db->connection('tarantool');

        try {
            $connection->statement('drop table "USERS"');
        } catch (Throwable $exception) {
        }

        $connection->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->integer('age')->nullable();
        });
    }

    protected function tearDown(): void
    {
        try {
            $this->db->connection('tarantool')->statement('drop table "USERS"');
        } catch (Throwable $exception) {
        }

        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_registers_the_tarantool_connection(): void
    {
        $connection = $this->db->connection('tarantool');

        self::assertSame('tarantool', $connection->getDriverName());
        self::assertSame(0, $connection->table('users')->count());
    }

    public function test_query_builder_insert_update_delete_and_aggregate_work(): void
    {
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
        $user = User::query()->create([
            'name' => 'Charlie',
            'age' => 27,
        ]);

        $fresh = User::query()->findOrFail($user->getKey());

        self::assertSame('tarantool', $fresh->getConnectionName());
        self::assertSame('Charlie', $fresh->name);
        self::assertSame(27, $fresh->age);
    }
}
