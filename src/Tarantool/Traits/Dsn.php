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
    protected function getDsn(array $config)
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
    protected function hasDsnString(array $config)
    {
        return isset($config['dsn']) && ! empty($config['dsn']);
    }

    /**
     * Get the DSN string form configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected function getDsnString(array $config)
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

        if (! empty($config['port']) && strpos($host, ':') === false) {
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

        $options = isset($config['options']) && ! empty($config['options']) ? http_build_query($config['options'], '', '&') : null;

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

        foreach (['driver_options', 'driver_oprions'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                continue;
            }

            if (! empty($config[$key]['type'])) {
                return $config[$key]['type'];
            }

            if (! empty($config[$key]['connection_type'])) {
                return $config[$key]['connection_type'];
            }
        }

        return 'tcp';
    }
}
