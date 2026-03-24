<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool;

use Chocofamily\Tarantool\Query\Builder;
use Chocofamily\Tarantool\Query\Grammar as QGrammar;
use Chocofamily\Tarantool\Query\Processor as QProcessor;
use Chocofamily\Tarantool\Schema\Builder as SchemaBuilder;
use Chocofamily\Tarantool\Schema\Grammar as SGrammar;
use Chocofamily\Tarantool\Traits\Dsn;
use Chocofamily\Tarantool\Traits\Helper;
use Chocofamily\Tarantool\Traits\Query;
use Illuminate\Database\Connection as BaseConnection;
use DateTimeInterface;
use Tarantool\Client\Client as TarantoolClient;
use Tarantool\Client\SqlQueryResult;

class Connection extends BaseConnection
{
    use Dsn, Query, Helper;

    protected TarantoolClient $connection;

    protected bool $sessionConfigured = false;

    /**
     * Create a new database connection instance.
     *
     * @param  array $config
     */
    public function __construct(array $config)
    {
        parent::__construct(null, $config['database'] ?? '', $config['prefix'] ?? '', $config);

        $dsn = $this->getDsn($config);
        $this->useDefaultSchemaGrammar();

        $this->setClient($this->createConnection($dsn));
    }

    /**
     * Create a new Tarantool connection.
     *
     * @param  string $dsn
     * @return TarantoolClient
     */
    protected function createConnection(string $dsn): TarantoolClient
    {
        return TarantoolClient::fromDsn($dsn);
    }

    /**
     * Configure Tarantool SQL session settings for this connection.
     */
    public function ensureSessionConfigured(): void
    {
        if ($this->sessionConfigured) {
            return;
        }

        $sqlSeqScan = $this->normalizeBooleanConfigValue(
            $this->config['sql_seq_scan'] ?? true,
            true
        );

        $this->getClient()->executeUpdate(sprintf(
            'SET SESSION "sql_seq_scan" = %s',
            $sqlSeqScan ? 'true' : 'false'
        ));

        $this->sessionConfigured = true;
    }

    /**
     * Normalize bool-like config values coming from env-driven config.
     */
    protected function normalizeBooleanConfigValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $default;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }

    /**
     * Prepare the query bindings for execution.
     */
    public function prepareBindings(array $bindings): array
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            }
        }

        return $bindings;
    }

    /**
     * Normalize prepared bindings for Tarantool's strict SQL typing.
     */
    public function normalizePreparedBindingsForQuery(string $query, array $bindings): array
    {
        $shouldCoerceNumericStrings = $this->normalizeBooleanConfigValue(
            $this->config['coerce_numeric_strings'] ?? true,
            true
        );

        if (! $shouldCoerceNumericStrings || $bindings === []) {
            return $bindings;
        }

        return match ($this->getSqlType($query)) {
            'SELECT', 'DELETE' => $this->coerceNumericStrings($bindings),
            'UPDATE' => $this->coerceNumericStringsAfterOffset(
                $bindings,
                $this->countUpdateValueBindings($query)
            ),
            default => $bindings,
        };
    }

    /**
     * Count bindings that belong to the SET clause of an UPDATE statement.
     */
    protected function countUpdateValueBindings(string $query): int
    {
        if (preg_match('/\bset\b(.*?)(?:\bwhere\b|\border\b|\blimit\b|$)/is', $query, $matches) !== 1) {
            return 0;
        }

        return substr_count($matches[1], '?');
    }

    /**
     * Coerce numeric strings in all bindings.
     */
    protected function coerceNumericStrings(array $bindings): array
    {
        array_walk_recursive($bindings, function (&$value): void {
            $value = $this->coerceNumericStringValue($value);
        });

        return $bindings;
    }

    /**
     * Coerce numeric strings in bindings after a fixed offset.
     */
    protected function coerceNumericStringsAfterOffset(array $bindings, int $offset): array
    {
        foreach ($bindings as $index => $value) {
            if ($index < $offset) {
                continue;
            }

            $bindings[$index] = $this->coerceNumericStringValue($value);
        }

        return $bindings;
    }

    /**
     * Normalize a scalar binding value for strict Tarantool comparisons.
     */
    protected function coerceNumericStringValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)\.\d+$/', $value) === 1) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param \Closure|\Illuminate\Database\Query\Builder|string $table
     * @param string|null $as
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table, $as = null): \Illuminate\Database\Query\Builder
    {
        return $this->query()->from($table, $as);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return \Generator
     * @throws \Exception
     */
    public function cursor($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        /** @var SqlQueryResult $queryResult */
        $queryResult = $this->executeQuery($query, $bindings, $useReadPdo);

        $metaData = $queryResult->getMetadata();

        array_walk_recursive($metaData, function (&$value) {
            $value = strtolower($value);
        });

        $result = new SqlQueryResult($queryResult->getData(), $metaData);

        return $result->getIterator();
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return new Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }

    /**
     * @param $connection
     *
     * @return self
     */
    public function setClient(TarantoolClient $connection): self
    {
        $this->connection = $connection;
        $this->sessionConfigured = false;

        return $this;
    }

    /**
     * return Tarantool object.
     *
     * @return TarantoolClient
     */
    public function getClient(): TarantoolClient
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName()
    {
        return $this->config['database'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return 'tarantool';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor()
    {
        return new QProcessor();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar()
    {
        return (new QGrammar($this))->setTablePrefix($this->tablePrefix);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSchemaGrammar()
    {
        return (new SGrammar($this))->setTablePrefix($this->tablePrefix);
    }
}
