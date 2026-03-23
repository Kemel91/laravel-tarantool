<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Tests\Unit;

use Chocofamily\Tarantool\Traits\Query;
use PHPUnit\Framework\TestCase;
use Tarantool\Client\SqlQueryResult;

class HydrationTest extends TestCase
{
    public function test_it_hydrates_rows_with_lowercase_keys(): void
    {
        $hydrator = new class {
            use Query {
                getDataWithKeys as public hydrateRows;
            }
        };

        $result = new SqlQueryResult(
            [
                [1, 'Alice', 10],
                [2, 'Bob', 20],
            ],
            [
                ['ID', 'integer'],
                ['Name', 'string'],
                ['SCORE', 'integer'],
            ]
        );

        self::assertSame(
            [
                ['id' => 1, 'name' => 'Alice', 'score' => 10],
                ['id' => 2, 'name' => 'Bob', 'score' => 20],
            ],
            $hydrator->hydrateRows($result)
        );
    }
}
