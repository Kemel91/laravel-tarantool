<?php

namespace Chocofamily\Tarantool\Traits;

trait Dsn
{
    /**
     * Create a DSN string from a configuration.
     *
     * @param  array $config
     * @return string
     */
    protected function getDsn(array $config): string
    {
        return $this->hasDsnString($config)
            ? $this->getDsnString($config)
            : $this->getHostDsn($config);
    }

    /**
     * Determine if the given configuration array has a dsn string.
     *
     * @param  array  $config
     * @return bool
     */
    protected function hasDsnString(array $config): bool
    {
        return ! empty($config['dsn']);
    }

    /**
     * Get the DSN string form configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected function getDsnString(array $config): string
    {
        return $config['dsn'];
    }

    /**
     * Get the DSN string for a host / port configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected function getHostDsn(array $config)
    {
        $host = $config['host'];

        if (! empty($config['port']) && !str_contains($host, ':')) {
            $host = $host.':'.$config['port'];
        }

        $username = isset($config['username']) ? rawurlencode((string) $config['username']) : '';
        $password = isset($config['password']) ? rawurlencode((string) $config['password']) : null;

        $auth = '';

        if ($username !== '') {
            $auth = $username;

            if ($password !== null) {
                $auth .= ':'.$password;
            }

            $auth .= '@';
        }

        $options = ! empty($config['options']) ? http_build_query($config['options'], '', '&') : null;

        $connType = $this->getConnectionType($config);

        return $connType.'://'.$auth.$host.($options ? '/?'.$options : '');
    }

    /**
     * Resolve the configured connection type.
     *
     * Supports the canonical `type` key as well as legacy nested driver options.
     */
    protected function getConnectionType(array $config): string
    {
        if (! empty($config['type'])) {
            return $config['type'];
        }

        $driverOptions = $config['driver_options'] ?? null;
        if (is_array($driverOptions)) {
            if (! empty($driverOptions['type'])) {
                return $driverOptions['type'];
            }
            if (! empty($driverOptions['connection_type'])) {
                return $driverOptions['connection_type'];
            }
        }

        return 'tcp';
    }
}
