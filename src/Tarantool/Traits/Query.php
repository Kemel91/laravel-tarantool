<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Traits;

use Closure;
use Exception;
use Illuminate\Database\QueryException;
use Tarantool\Client\Client;
use Tarantool\Client\SqlQueryResult;
use Tarantool\Client\SqlUpdateResult;

use function array_change_key_case;

trait Query
{
    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @param array $fetchUsing
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = false, array $fetchUsing = []): array
    {
        /** @var SqlQueryResult $result */
        $result = $this->executeQuery($query, $bindings, $useReadPdo);

        return $this->getDataWithKeys($result);
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function insert($query, $bindings = [])
    {
        /** @var SqlUpdateResult $result */
        $result = $this->executeQuery($query, $bindings);

        $this->recordsHaveBeenModified($result->count() > 0);

        return true;
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return array
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function update($query, $bindings = [])
    {
        /** @var SqlUpdateResult $result */
        $result = $this->executeQuery($query, $bindings);

        $this->recordsHaveBeenModified($result->count() > 0);

        return $result->count();
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = []): int
    {
        /** @var SqlUpdateResult $result */
        $result = $this->executeQuery($query, $bindings);

        $this->recordsHaveBeenModified($result->count() > 0);

        return $result->count();
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        $result = $this->executeQuery($query, $bindings);

        if ($result instanceof SqlUpdateResult) {
            $this->recordsHaveBeenModified($result->count() > 0);
        } elseif ($result instanceof SqlQueryResult) {
            $this->recordsHaveBeenModified($result->count() > 0);
        }

        return true;
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        $class = $this;
        $client = $this->getClient();

        return $this->run($query, [], function ($query) use ($class, $client) {
            if ($this->pretending()) {
                return true;
            }
            $this->recordsHaveBeenModified(
                $change = $class->runQuery($client, $query, []) !== false
            );

            return $change;
        });
    }

    /**
     * Run query.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function executeQuery(string $query, array $bindings, bool $useReadPdo = false)
    {
        $client = $this->getClient();
        $preparedBindings = $this->prepareBindings($bindings);

        return $this->run($query, $bindings, function ($query, $bindings) use ($client, $preparedBindings) {
            if ($this->pretending()) {
                return [];
            }

            return $this->runQuery($client, $query, $preparedBindings);
        });
    }

    /**
     * Run a SQL statement.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        }
        catch (Exception $e) {
            throw new QueryException(
                $this->getName(), $query, $this->prepareBindings($bindings), $e
            );
        }

        return $result;
    }

    /**
     * Runs a SQL query.
     *
     * @param Client $client
     * @param string $sql
     * @param array $params
     * @param string $operationType
     * @return SqlQueryResult|SqlUpdateResult
     */
    private function runQuery(Client $client, string $sql, array $params, $operationType = '')
    {
        $this->ensureSessionConfigured();

        if (! $operationType) {
            $operationType = $this->getSqlType($sql);
        }

        if ($operationType === 'SELECT') {
            $result = $client->executeQuery($sql, ...$params);
        } else {
            $result = $client->executeUpdate($sql, ...$params);
        }

        return $result;
    }

    /**
     * @param  SqlQueryResult $result
     * @return array
     */
    private function getDataWithKeys(SqlQueryResult $result) : array
    {
        $data = [];
        foreach ($result as $key => $value) {
            $data[$key] = array_change_key_case($value);
        }

        return $data;
    }
}
