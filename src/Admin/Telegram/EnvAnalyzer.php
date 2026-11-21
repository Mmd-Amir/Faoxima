<?php

namespace Faoxima\Admin\Telegram;

use PDO;

final class EnvAnalyzer
{
    private PDO $pdo;
    private ?float $memPressureCache = null;
    private ?float $cpuPressureCache = null;
    private ?float $dbPressureCache  = null;
    private ?float $pressureCache    = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hardLimit(): int
    {
        $r = (int) ini_get('max_execution_time');
        if ($r <= 0 || $r > 280) {
            return 280;
        }
        return $r;
    }

    public function softTimeLimit(): float
    {
        return $this->hardLimit() * 0.72;
    }

    private function parseMemoryLimit(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($raw, -1));
        $val  = (int) $raw;
        if ($unit === 'g') {
            return $val * 1073741824;
        }
        if ($unit === 'm') {
            return $val * 1048576;
        }
        if ($unit === 'k') {
            return $val * 1024;
        }
        return max(1, $val);
    }

    public function memoryPressure(): float
    {
        if ($this->memPressureCache !== null) {
            return $this->memPressureCache;
        }
        $bytes = memory_get_usage(true);
        $limit = $this->parseMemoryLimit((string) ini_get('memory_limit'));
        if ($limit <= 0 || $limit === PHP_INT_MAX) {
            return $this->memPressureCache = 0.0;
        }
        return $this->memPressureCache = min(1.0, max(0.0, $bytes / $limit));
    }

    public function cpuPressure(): float
    {
        if ($this->cpuPressureCache !== null) {
            return $this->cpuPressureCache;
        }
        if (!function_exists('sys_getloadavg')) {
            return $this->cpuPressureCache = 0.0;
        }
        $load = sys_getloadavg();
        if (!is_array($load) || !isset($load[0])) {
            return $this->cpuPressureCache = 0.0;
        }
        return $this->cpuPressureCache = min(1.0, max(0.0, (float) $load[0] / 2.0));
    }

    public function dbPressure(): float
    {
        if ($this->dbPressureCache !== null) {
            return $this->dbPressureCache;
        }
        $times = [];
        for ($i = 0; $i < 3; $i++) {
            try {
                $t0      = microtime(true);
                $this->pdo->query('SELECT 1');
                $times[] = microtime(true) - $t0;
            } catch (\Throwable $e) {}
        }
        $avg = count($times) > 0 ? array_sum($times) / count($times) : 0.05;
        return $this->dbPressureCache = min(1.0, max(0.0, $avg / 0.05));
    }

    public function pressure(): float
    {
        if ($this->pressureCache !== null) {
            return $this->pressureCache;
        }
        return $this->pressureCache = max(
            $this->memoryPressure(),
            $this->cpuPressure(),
            $this->dbPressure()
        );
    }

    public function batchSize(): int
    {
        return max(5, (int) (50 * (1.0 - $this->pressure())));
    }

    public function cronDbBudget(): int
    {
        return max(2, (int) (6 * (1.0 - $this->dbPressure())));
    }

    public function broadcastWorkers(): int
    {
        return max(1, min(5, (int) (4 * (1.0 - $this->pressure()))));
    }

    public function paymentWorkers(): int
    {
        return max(1, min(5, (int) (4 * (1.0 - $this->pressure()))));
    }

    public function cronTimeBudget(): int
    {
        return max(15, (int) ($this->softTimeLimit() * 0.85));
    }

    public function redisExtensionAvailable(): bool
    {
        return class_exists('Redis') || class_exists('Predis\\Client');
    }

    public function redisReachable(): ?bool
    {
        if (!$this->redisExtensionAvailable()) {
            return null;
        }
        if (!method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_config')) {
            return null;
        }
        $config = \Faoxima\Core\Cache\RedisCache::rx_redis_config();
        if ($config['host'] === '') {
            return false;
        }
        try {
            if (class_exists('Redis')) {
                $client = new \Redis();
                $connected = @$client->connect($config['host'], $config['port'], 1.5);
                if ($connected && $config['password'] !== '') {
                    $connected = (bool) @$client->auth($config['password']);
                }
                @$client->close();
                return (bool) $connected;
            }
            $params = ['host' => $config['host'], 'port' => $config['port'], 'timeout' => 1.5];
            if ($config['password'] !== '') {
                $params['password'] = $config['password'];
            }
            $client = new \Predis\Client($params);
            $client->ping();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function redisAdminEnabled(): bool
    {
        return method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_admin_enabled') ? \Faoxima\Core\Cache\RedisCache::rx_redis_admin_enabled() : false;
    }

    public function redisStatusArray(): array
    {
        $extAvailable = $this->redisExtensionAvailable();
        $reachable = $this->redisReachable();
        return [
            'extension'     => $extAvailable,
            'reachable'     => $reachable,
            'admin_enabled' => $this->redisAdminEnabled(),
            'guidance'      => !$extAvailable && method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_extension_guidance')
                ? \Faoxima\Core\Cache\RedisCache::rx_redis_extension_guidance()
                : null,
        ];
    }

    public function toArray(): array
    {
        return [
            'hard_limit'        => $this->hardLimit(),
            'soft_time_limit'   => round($this->softTimeLimit(), 2),
            'memory_pressure'   => round($this->memoryPressure(), 4),
            'cpu_pressure'      => round($this->cpuPressure(), 4),
            'db_pressure'       => round($this->dbPressure(), 4),
            'pressure'          => round($this->pressure(), 4),
            'batch_size'        => $this->batchSize(),
            'cron_db_budget'    => $this->cronDbBudget(),
            'broadcast_workers' => $this->broadcastWorkers(),
            'payment_workers'   => $this->paymentWorkers(),
            'cron_time_budget'  => $this->cronTimeBudget(),
            'redis_status'      => $this->redisStatusArray(),
            'generated_at'      => time(),
        ];
    }
}
