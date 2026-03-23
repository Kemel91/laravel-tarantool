<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use RuntimeException;

class Builder extends BaseBuilder
{
    /**
     * Drop all tables from the database.
     */
    public function dropAllTables()
    {
        $remaining = array_column($this->getTables(), 'name');

        if ($remaining === []) {
            return;
        }

        while ($remaining !== []) {
            $droppedAtLeastOne = false;
            $nextRemaining = [];

            foreach ($remaining as $table) {
                try {
                    $this->drop($table);
                    $droppedAtLeastOne = true;
                } catch (QueryException $exception) {
                    $nextRemaining[] = $table;
                }
            }

            if (! $droppedAtLeastOne) {
                throw new RuntimeException(
                    sprintf(
                        'Unable to drop all Tarantool tables due to unresolved dependencies: %s',
                        implode(', ', $nextRemaining)
                    )
                );
            }

            $remaining = $nextRemaining;
        }
    }

    /**
     * Tarantool SQL views are not currently supported by this driver.
     */
    public function dropAllViews()
    {
    }

    /**
     * Tarantool SQL user-defined types are not currently supported by this driver.
     */
    public function dropAllTypes()
    {
    }
}
