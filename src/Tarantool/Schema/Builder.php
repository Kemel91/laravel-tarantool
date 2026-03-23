<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use RuntimeException;

class Builder extends BaseBuilder
{
    /**
     * Get the columns for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null, collation: string|null}>
     */
    public function getColumns($table)
    {
        $space = $this->getSpaceDefinition($table);

        if ($space === null) {
            return [];
        }

        $autoincrementField = $this->getAutoincrementFieldIndex((int) $space['id']);

        return array_map(function (array $column, int $index) use ($autoincrementField): array {
            $typeName = (string) ($column['type'] ?? 'scalar');

            return [
                'name' => (string) $column['name'],
                'type_name' => $typeName,
                'type' => $typeName,
                'collation' => null,
                'nullable' => (bool) ($column['is_nullable'] ?? true),
                'default' => $this->normalizeDefaultValue($column['default'] ?? null),
                'auto_increment' => $autoincrementField === $index,
                'comment' => null,
                'generation' => null,
            ];
        }, $space['format'] ?? [], array_keys($space['format'] ?? []));
    }

    /**
     * Get the indexes for a given table.
     *
     * @param  string  $table
     * @return list<array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}>
     */
    public function getIndexes($table)
    {
        $space = $this->getSpaceDefinition($table);

        if ($space === null) {
            return [];
        }

        $columnsByField = [];

        foreach ($space['format'] ?? [] as $position => $column) {
            $columnsByField[$position] = (string) $column['name'];
        }

        $indexes = $this->connection->selectFromWriteConnection(
            'select * from "_index" where "id" = ? order by "iid" asc',
            [(int) $space['id']]
        );

        return array_map(function (array $index) use ($columnsByField): array {
            $columns = [];

            foreach ($index['parts'] ?? [] as $part) {
                $field = $this->extractIndexField($part);

                if ($field !== null && array_key_exists($field, $columnsByField)) {
                    $columns[] = $columnsByField[$field];
                }
            }

            return [
                'name' => strtolower((string) $index['name']),
                'columns' => $columns,
                'type' => strtolower((string) ($index['type'] ?? 'tree')),
                'unique' => (bool) (($index['opts']['unique'] ?? false) === true),
                'primary' => (int) ($index['iid'] ?? -1) === 0,
            ];
        }, $indexes);
    }

    /**
     * Get the foreign keys for a given table.
     *
     * Tarantool does not currently expose SQL foreign key metadata in a stable
     * shape through the interfaces used by this driver, so we return an empty
     * list instead of throwing unsupported-driver exceptions.
     *
     * @param  string  $table
     * @return list<array{name: string, columns: list<string>, foreign_schema: string, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}>
     */
    public function getForeignKeys($table)
    {
        return [];
    }

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

    /**
     * Get the Tarantool space definition for a table.
     */
    private function getSpaceDefinition(string $table): ?array
    {
        $table = $this->normalizeTableName($table);

        $spaces = $this->connection->selectFromWriteConnection(
            'select * from "_space" where upper("name") = upper(?)',
            [$table]
        );

        return $spaces[0] ?? null;
    }

    /**
     * Determine which field is backed by an autoincrement sequence, if any.
     */
    private function getAutoincrementFieldIndex(int $spaceId): ?int
    {
        $rows = $this->connection->selectFromWriteConnection(
            'select * from "_space_sequence" where "id" = ?',
            [$spaceId]
        );

        if ($rows === []) {
            return null;
        }

        return isset($rows[0]['field']) ? (int) $rows[0]['field'] : null;
    }

    /**
     * Convert Tarantool defaults into SQL literal strings Laravel can round-trip.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeDefaultValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return "'".str_replace("'", "''", $value)."'";
        }

        return (string) $value;
    }

    /**
     * Extract an indexed field number from Tarantool index metadata.
     *
     * @param  mixed  $part
     */
    private function extractIndexField($part): ?int
    {
        if (is_array($part)) {
            if (array_key_exists('field', $part)) {
                return (int) $part['field'];
            }

            if (array_key_exists(0, $part) && is_numeric($part[0])) {
                return (int) $part[0];
            }
        }

        return null;
    }

    /**
     * Normalize schema-qualified table names for Tarantool system-space lookups.
     */
    private function normalizeTableName(string $table): string
    {
        if (str_contains($table, '.')) {
            $segments = explode('.', $table);
            $table = (string) end($segments);
        }

        return $this->connection->getTablePrefix().$table;
    }
}
