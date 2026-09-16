<?php

if (!function_exists('rx_redis_config')) {
    function rx_redis_config()
    {
        return [
            'host' => (string) ($GLOBALS['redis_host'] ?? ''),
            'port' => (int) ($GLOBALS['redis_port'] ?? 6379),
            'password' => (string) ($GLOBALS['redis_password'] ?? ''),
            'database' => (int) ($GLOBALS['redis_database'] ?? 0),
        ];
    }
}

if (!function_exists('rx_redis_extension_available')) {
    function rx_redis_extension_available()
    {
        return class_exists('Redis') || class_exists('Predis\\Client');
    }
}

if (!function_exists('rx_redis_state')) {
    function &rx_redis_state()
    {
        static $state = [
            'admin_cached' => null,
            'resolving'    => false,
            'resolved'     => false,
            'client'       => null,
        ];
        return $state;
    }
}

if (!function_exists('rx_redis_reset_resolution')) {
    function rx_redis_reset_resolution()
    {
        $state =& rx_redis_state();
        $state['admin_cached'] = null;
        $state['resolving']    = false;
        $state['resolved']     = false;
        $state['client']       = null;
    }
}

if (!function_exists('rx_redis_admin_enabled')) {
    function rx_redis_admin_enabled()
    {
        $state =& rx_redis_state();
        if ($state['admin_cached'] !== null) {
            return $state['admin_cached'];
        }
        if ($state['resolving']) {
            return false;
        }
        if (!function_exists('select')) {
            return false;
        }
        $state['resolving'] = true;
        $setting = select('setting', 'redis_enabled', null, null, 'select', ['cache' => false]);
        $state['resolving'] = false;
        $value = is_array($setting) ? (string) ($setting['redis_enabled'] ?? '0') : '0';
        return $state['admin_cached'] = ($value === '1');
    }
}

if (!function_exists('rx_redis_connect_retries')) {
    function rx_redis_connect_retries()
    {
        if (defined('RX_REDIS_CONNECT_RETRIES')) {
            return max(0, (int) RX_REDIS_CONNECT_RETRIES);
        }
        return 1;
    }
}

if (!function_exists('rx_redis_connect_backoff_ms')) {
    function rx_redis_connect_backoff_ms()
    {
        if (defined('RX_REDIS_CONNECT_BACKOFF_MS')) {
            return max(0, (int) RX_REDIS_CONNECT_BACKOFF_MS);
        }
        return 100;
    }
}

if (!function_exists('rx_redis_backoff_sleep')) {
    function rx_redis_backoff_sleep($attempt)
    {
        $base = rx_redis_connect_backoff_ms() * 1000;
        $jitter = function_exists('random_int') ? random_int(0, 60000) : mt_rand(0, 60000);
        @usleep($base + $jitter);
    }
}

if (!function_exists('rx_redis_unavailable_flag_path')) {
    function rx_redis_unavailable_flag_path()
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_redis_unavailable.flag';
    }
}

if (!function_exists('rx_redis_mark_unavailable')) {
    function rx_redis_mark_unavailable($reasonCode)
    {
        $flag = rx_redis_unavailable_flag_path();
        if (!is_file($flag) || (time() - (int) @filemtime($flag)) > 3600) {
            error_log('Redis unavailable (' . (string) $reasonCode . ') — falling back to existing behavior.');
        }
        @touch($flag);
    }
}

if (!function_exists('rx_redis_in_cooldown')) {
    function rx_redis_in_cooldown()
    {
        $flag = rx_redis_unavailable_flag_path();
        return is_file($flag) && (time() - (int) @filemtime($flag)) < 60;
    }
}

if (!function_exists('rx_redis_cooldown_remaining')) {
    function rx_redis_cooldown_remaining()
    {
        $flag = rx_redis_unavailable_flag_path();
        if (!is_file($flag)) {
            return 0;
        }
        $remaining = 60 - (time() - (int) @filemtime($flag));
        return max(0, $remaining);
    }
}

if (!function_exists('rx_redis_clear_unavailable')) {
    function rx_redis_clear_unavailable()
    {
        @unlink(rx_redis_unavailable_flag_path());
    }
}

if (!function_exists('rx_redis_docker_env')) {
    function rx_redis_docker_env()
    {
        return defined('FAOXIMA_DOCKER_ENV') || getenv('FAOXIMA_DOCKER_ENV') !== false;
    }
}

if (!function_exists('rx_redis_extension_guidance')) {
    function rx_redis_extension_guidance()
    {
        if (rx_redis_docker_env()) {
            return "⚠️ افزونه Redis در PHP فعال نیست. از منوی install.sh ← Database ← Install/Enable Redis اجرا کنید تا افزونه در ایمیج build و فعال شود. پس از اجرا، مجدداً عملیات بهینه‌سازی را انجام دهید.";
        }
        return "⚠️ افزونه Redis در PHP فعال نیست. لطفاً از طریق cPanel وارد بخش PHP Selector یا Select PHP Version شوید و افزونه Redis را فعال کنید. پس از فعال‌سازی، مجدداً عملیات بهینه‌سازی را اجرا کنید.";
    }
}

if (!function_exists('rx_redis_try_connect')) {
    function rx_redis_try_connect($host, $port, $password, $database)
    {
        $retries = rx_redis_connect_retries();
        $attempt = 0;

        while (true) {
            try {
                if (class_exists('Redis')) {
                    $client = new \Redis();
                    $connected = @$client->connect($host, $port, 1.5);
                    if (!$connected) {
                        throw new \RuntimeException('connect_refused');
                    }
                    if ($password !== '') {
                        if (!@$client->auth($password)) {
                            throw new \RuntimeException('auth_failed');
                        }
                    }
                    if ($database > 0) {
                        @$client->select($database);
                    }
                } elseif (class_exists('Predis\\Client')) {
                    $params = [
                        'host' => $host,
                        'port' => $port,
                        'timeout' => 1.5,
                    ];
                    if ($password !== '') {
                        $params['password'] = $password;
                    }
                    if ($database > 0) {
                        $params['database'] = $database;
                    }
                    $client = new \Predis\Client($params);
                    $client->ping();
                } else {
                    return null;
                }

                return $client;
            } catch (\Throwable $e) {
                if ($attempt < $retries) {
                    rx_redis_backoff_sleep($attempt);
                    $attempt++;
                    continue;
                }
                return null;
            }
        }
    }
}

if (!function_exists('rx_redis_persist_discovered_host')) {
    function rx_redis_persist_discovered_host($host)
    {
        $configPath = defined('APP_ROOT_PATH') ? APP_ROOT_PATH . '/config.php' : null;
        if ($configPath === null || !is_writable($configPath)) {
            return false;
        }
        $contents = @file_get_contents($configPath);
        if ($contents === false) {
            return false;
        }
        $pattern = '/^(\$redis_host\s*=\s*)([\'"])[^\'"]*\2(\s*;.*)$/m';
        if (!preg_match($pattern, $contents)) {
            return false;
        }
        $escapedHost = addslashes($host);
        $updated = preg_replace($pattern, '$1\'' . $escapedHost . '\'$3', $contents, 1);
        if ($updated === null || $updated === $contents) {
            return false;
        }
        $written = @file_put_contents($configPath, $updated, LOCK_EX);
        if ($written !== false) {
            $GLOBALS['redis_host'] = $host;
        }
        return $written !== false;
    }
}

if (!function_exists('getRedisConnection')) {
    function getRedisConnection()
    {
        static $inProgress = false;

        $state =& rx_redis_state();

        if ($state['resolved']) {
            return $state['client'];
        }

        if ($inProgress) {
            return null;
        }
        $inProgress = true;

        if (rx_redis_in_cooldown()) {
            $state['resolved'] = true;
            $state['client'] = null;
            $inProgress = false;
            return null;
        }

        if (!function_exists('select')) {
            $inProgress = false;
            return null;
        }

        if (!rx_redis_admin_enabled()) {
            $state['resolved'] = true;
            $state['client'] = null;
            $inProgress = false;
            return null;
        }

        if (!rx_redis_extension_available()) {
            $state['resolved'] = true;
            $state['client'] = null;
            $inProgress = false;
            return null;
        }

        $config = rx_redis_config();

        $autoDetect = ($config['host'] === '');
        $hostCandidates = $autoDetect
            ? (rx_redis_docker_env() ? ['redis', '127.0.0.1', 'localhost'] : ['127.0.0.1', 'localhost'])
            : [$config['host']];

        foreach ($hostCandidates as $candidateHost) {
            $client = rx_redis_try_connect($candidateHost, $config['port'], $config['password'], $config['database']);
            if ($client !== null) {
                rx_redis_clear_unavailable();
                if ($autoDetect) {
                    rx_redis_persist_discovered_host($candidateHost);
                }
                $state['resolved'] = true;
                $state['client'] = $client;
                $inProgress = false;
                return $state['client'];
            }
        }

        rx_redis_mark_unavailable('connect_refused');
        $state['resolved'] = true;
        $state['client'] = null;
        $inProgress = false;
        return null;
    }
}

if (!function_exists('rx_redis_is_active')) {
    function rx_redis_is_active()
    {
        return getRedisConnection() !== null;
    }
}

if (!function_exists('rx_redis_get')) {
    function rx_redis_get($key)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return null;
        }
        try {
            $value = $client->get($key);
            return $value === false ? null : $value;
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return null;
        }
    }
}

if (!function_exists('rx_redis_set')) {
    function rx_redis_set($key, $value, $ttlSeconds = 0)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return false;
        }
        try {
            if ($ttlSeconds > 0) {
                return (bool) $client->setex($key, $ttlSeconds, $value);
            }
            return (bool) $client->set($key, $value);
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return false;
        }
    }
}

if (!function_exists('rx_redis_del')) {
    function rx_redis_del($keys)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return false;
        }
        $keys = is_array($keys) ? $keys : [$keys];
        $keys = array_values(array_filter($keys, static function ($k) {
            return is_string($k) && $k !== '';
        }));
        if (empty($keys)) {
            return true;
        }
        try {
            $client->del($keys);
            return true;
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return false;
        }
    }
}

if (!function_exists('rx_redis_scan_delete')) {
    function rx_redis_scan_delete($pattern)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return false;
        }
        try {
            $deleted = 0;
            $maxIterations = 10000;
            $iterations = 0;
            $isPhpRedis = $client instanceof \Redis;
            $cursor = $isPhpRedis ? 0 : 0;
            $started = false;

            while ($iterations < $maxIterations) {
                $iterations++;
                if ($started && (int) $cursor === 0) {
                    break;
                }
                $started = true;

                if ($isPhpRedis) {
                    $batch = $client->scan($cursor, $pattern, 200);
                    if ($batch === false) {
                        break;
                    }
                    if (is_array($batch) && !empty($batch)) {
                        rx_redis_del(array_values($batch));
                        $deleted += count($batch);
                    }
                } else {
                    $scanResult = $client->scan($cursor, ['match' => $pattern, 'count' => 200]);
                    if (!is_array($scanResult) || count($scanResult) !== 2) {
                        break;
                    }
                    [$cursor, $batch] = $scanResult;
                    if (is_array($batch) && !empty($batch)) {
                        rx_redis_del(array_values($batch));
                        $deleted += count($batch);
                    }
                }
            }
            return true;
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return false;
        }
    }
}

if (!function_exists('rx_redis_set_nx')) {
    function rx_redis_set_nx($key, $value, $ttlMs)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return null;
        }
        try {
            if ($client instanceof \Redis) {
                $result = $client->set($key, $value, ['NX', 'PX' => $ttlMs]);
                return $result === true;
            }
            $result = $client->set($key, $value, 'PX', $ttlMs, 'NX');
            return $result !== null && (string) $result !== '';
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return null;
        }
    }
}

if (!function_exists('rx_redis_release_lock')) {
    function rx_redis_release_lock($key, $token)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return false;
        }
        $script = "if redis.call('get',KEYS[1])==ARGV[1] then return redis.call('del',KEYS[1]) else return 0 end";
        try {
            if ($client instanceof \Redis) {
                $client->eval($script, [$key, $token], 1);
            } else {
                $client->eval($script, 1, $key, $token);
            }
            return true;
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return false;
        }
    }
}

if (!function_exists('rx_redis_sadd_index')) {
    function rx_redis_sadd_index($indexKey, $member, $ttlSeconds = 600)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return false;
        }
        try {
            $client->sAdd($indexKey, $member);
            $client->expire($indexKey, $ttlSeconds);
            return true;
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return false;
        }
    }
}

if (!function_exists('rx_redis_incr_with_ttl')) {
    function rx_redis_incr_with_ttl($key, $ttlSeconds)
    {
        $client = getRedisConnection();
        if ($client === null) {
            return null;
        }
        $script = "local c = redis.call('incr', KEYS[1]) if c == 1 then redis.call('expire', KEYS[1], ARGV[1]) end return c";
        try {
            if ($client instanceof \Redis) {
                return (int) $client->eval($script, [$key, $ttlSeconds], 1);
            }
            return (int) $client->eval($script, 1, $key, $ttlSeconds);
        } catch (\Throwable $e) {
            rx_redis_mark_unavailable('exception');
            return null;
        }
    }
}
