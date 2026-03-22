<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Tests\Unit;

use Chocofamily\Tarantool\Traits\Dsn;
use PHPUnit\Framework\TestCase;

class DsnTest extends TestCase
{
    public function test_it_builds_dsn_from_driver_options_and_encodes_credentials(): void
    {
        $builder = new class {
            use Dsn {
                getHostDsn as public;
            }
        };

        $dsn = $builder->getHostDsn([
            'host' => '127.0.0.1',
            'port' => 3301,
            'username' => 'user@example.com',
            'password' => 'pa:ss@word',
            'driver_options' => [
                'connection_type' => 'tcp',
            ],
            'options' => [
                'connect_timeout' => 5,
                'max_retries' => 3,
            ],
        ]);

        self::assertSame(
            'tcp://user%40example.com:pa%3Ass%40word@127.0.0.1:3301/?connect_timeout=5&max_retries=3',
            $dsn
        );
    }

    public function test_it_supports_legacy_driver_oprions_key(): void
    {
        $builder = new class {
            use Dsn {
                getHostDsn as public;
            }
        };

        $dsn = $builder->getHostDsn([
            'host' => '127.0.0.1',
            'port' => 3301,
            'username' => 'admin',
            'password' => 'admin',
            'driver_oprions' => [
                'connection_type' => 'unix',
            ],
        ]);

        self::assertSame('unix://admin:admin@127.0.0.1:3301', $dsn);
    }
}
