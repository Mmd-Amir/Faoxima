<?php

namespace Faoxima\Cron\Runtime;

use PDO;

final class CronRuntime
{
    public static function rx_cron_release_http(): void
    {
        static $released = false;
        if ($released || PHP_SAPI === 'cli') {
            return;
        }
        $released = true;
        if (function_exists('fastcgi_finish_request')) {
            if (!headers_sent()) {
                @header('Content-Type: text/plain; charset=utf-8');
                @header('Content-Length: 0');
                @header('Connection: close');
            }
            @ob_end_flush();
            @flush();
            @fastcgi_finish_request();
        }
    }

    public static function rx_cron_runtime_dir(): string
    {
        $dir = (dirname(__DIR__, 3) . '/cronbot') . DIRECTORY_SEPARATOR . '.runtime';
        if (!@is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @is_dir($dir) ? $dir : (dirname(__DIR__, 3) . '/cronbot');
    }

    public static function rx_cron_deadline(?int $seconds = null): float
    {
        static $deadline = null;
        if ($deadline === null || $seconds !== null) {
            if ($seconds === null) {
                $p = method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_read_profile') ? \Faoxima\Cron\Runtime\CronBatches::rx_cron_read_profile() : [];
                $seconds = (int) ($p['cron_time_budget'] ?? 22);
            }
            $seconds  = max(5, min(280, (int) $seconds));
            $deadline = microtime(true) + $seconds;
        }
        return $deadline;
    }

    public static function rx_cron_time_up(): bool
    {
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_heartbeat')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_heartbeat();
        }
        return microtime(true) >= self::rx_cron_deadline();
    }

    public static function rx_cron_db_slot_dir(): ?string
    {
        $dir = (dirname(__DIR__, 3) . '/cronbot') . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'dbslots';
        if (@is_dir($dir)) {
            return $dir;
        }
        if (@mkdir($dir, 0775, true) || @is_dir($dir)) {
            return $dir;
        }
        return null;
    }

    public static function rx_cron_db_slot(?int $budget = null): bool
    {
        static $reported = false;
        try {
            if ($budget === null) {
                $p = method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_read_profile') ? \Faoxima\Cron\Runtime\CronBatches::rx_cron_read_profile() : [];
                $budget = (int) ($p['cron_db_budget'] ?? 6);
            }
            $budget = max(1, min(64, (int) $budget));

            $dir = self::rx_cron_db_slot_dir();
            if ($dir === null) {
                if (!$reported) {
                    $reported = true;
                    @error_log('[cron] database slot control unavailable: init_failed');
                }
                return false;
            }

            $busy = false;
            $openFailed = false;
            for ($i = 0; $i < $budget; $i++) {
                $slotFile = $dir . DIRECTORY_SEPARATOR . 'slot_' . $i . '.lock';
                $fh = @fopen($slotFile, 'c');
                if ($fh === false) {
                    $openFailed = true;
                    continue;
                }
                if (@flock($fh, LOCK_EX | LOCK_NB)) {
                    @ftruncate($fh, 0);
                    @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
                    @fflush($fh);
                    register_shutdown_function(static function () use ($fh) {
                        @flock($fh, LOCK_UN);
                        @fclose($fh);
                    });
                    return true;
                }
                $busy = true;
                @fclose($fh);
            }

            if ($openFailed && !$busy && !$reported) {
                $reported = true;
                @error_log('[cron] database slot control unavailable: open_failed');
            }
            return false;
        } catch (\Throwable $e) {
            if (!$reported) {
                $reported = true;
                @error_log('[cron] database slot control error: ' . $e->getMessage());
            }
            return false;
        }
    }

    public static function rx_cron_auth_issue(string $file, string $token): void
    {
        $entry = hash('sha256', $token) . '|' . time();
        $gate = @fopen($file . '.lock', 'c');
        if ($gate === false || !@flock($gate, LOCK_EX)) {
            if (is_resource($gate)) {
                @fclose($gate);
            }
            @file_put_contents($file, $entry, LOCK_EX);
            @chmod($file, 0600);
            return;
        }
        try {
            $lines = [];
            $raw = is_file($file) ? preg_split('/\R/', trim((string) @file_get_contents($file))) : [];
            foreach ((array) $raw as $line) {
                $parts = explode('|', trim((string) $line), 2);
                if (count($parts) === 2 && preg_match('/^[a-f0-9]{64}$/', $parts[0]) && (int) $parts[1] > time() - 120) {
                    $lines[] = trim((string) $line);
                }
            }
            $lines = array_slice($lines, -7);
            $lines[] = $entry;
            $tmp = $file . '.tmp' . getmypid();
            if (@file_put_contents($tmp, implode("\n", $lines), LOCK_EX) !== false) {
                @chmod($tmp, 0600);
                if (!@rename($tmp, $file)) {
                    @unlink($tmp);
                    @file_put_contents($file, $entry, LOCK_EX);
                }
            } else {
                @file_put_contents($file, $entry, LOCK_EX);
            }
            @chmod($file, 0600);
        } finally {
            @flock($gate, LOCK_UN);
            @fclose($gate);
        }
    }

    public static function rx_cron_auth_valid(string $file, string $token): bool
    {
        if ($token === '' || !is_readable($file)) {
            return false;
        }
        $hash = hash('sha256', $token);
        foreach ((array) preg_split('/\R/', trim((string) @file_get_contents($file))) as $line) {
            $parts = explode('|', trim((string) $line), 2);
            if (count($parts) !== 2) {
                continue;
            }
            $issuedAt = (int) $parts[1];
            $expected = trim((string) $parts[0]);
            if ($expected !== '' && $issuedAt > 0 && abs(time() - $issuedAt) <= 120 && hash_equals($expected, $hash)) {
                return true;
            }
        }
        return false;
    }

    public static function rx_cron_verify_web_access(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $rxCronAuthFile = dirname((dirname(__DIR__, 3) . '/cronbot')) . '/cron/.cron_internal_auth';
        $rxCronToken = isset($_SERVER['HTTP_X_CRON_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CRON_TOKEN']) : '';
        $rxCronSource = isset($_SERVER['HTTP_X_CRON_SOURCE']) ? trim((string) $_SERVER['HTTP_X_CRON_SOURCE']) : '';
        $rxCronAuthValid = $rxCronSource === 'cron-orchestrator' && self::rx_cron_auth_valid($rxCronAuthFile, $rxCronToken);
        if (!$rxCronAuthValid) {
            http_response_code(403);
            exit('Cron request is not authorized');
        }
        self::rx_cron_release_http();
    }

    public static function rx_cron_boot(string $jobName, int $maxAgeSeconds = 120, bool $useDbSlot = true): void
    {
        self::rx_cron_verify_web_access();
        $jobName = preg_replace('/[^A-Za-z0-9_\-]/', '', $jobName);
        if ($jobName === '') {
            return;
        }
        $rxW = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
        $rxW = max(0, min(15, $rxW));
        $rxLockName = $jobName . ($rxW > 0 ? "_w{$rxW}" : '');
        $lockFile = self::rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . $rxLockName . '.lock';

        static $heldHandles = [];

        $fh = @fopen($lockFile, 'c');
        if ($fh === false) {
            if (is_file($lockFile) && (time() - (int) @filemtime($lockFile)) < $maxAgeSeconds) {
                exit;
            }
            @file_put_contents($lockFile, getmypid() . '|' . date('Y-m-d H:i:s'));
            register_shutdown_function(static function () use ($lockFile) {
                @unlink($lockFile);
            });
        } else {
            if (!@flock($fh, LOCK_EX | LOCK_NB)) {
                @fclose($fh);
                exit;
            }

            @ftruncate($fh, 0);
            @fwrite($fh, getmypid() . '|' . date('Y-m-d H:i:s'));
            @fflush($fh);
            $heldHandles[] = $fh;

            register_shutdown_function(static function () use ($fh) {
                @flock($fh, LOCK_UN);
                @fclose($fh);
            });
        }

        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_attach')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_attach($jobName, $maxAgeSeconds);
        }

        if ($useDbSlot && method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_db_slot') && !self::rx_cron_db_slot()) {
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('db_slot_unavailable', 45);
            } elseif (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer_request')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer_request('db_slot_unavailable', 45);
            }
            exit;
        }

        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
            $rxRedisLockKey = 'faoxima:cronlock:' . $rxLockName;
            $rxRedisLockToken = uniqid('', true);
            $rxRedisLockResult = \Faoxima\Core\Cache\RedisCache::rx_redis_set_nx($rxRedisLockKey, $rxRedisLockToken, $maxAgeSeconds * 1000);
            if ($rxRedisLockResult === false) {
                if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('redis_lock_busy', 60);
                } elseif (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer_request')) {
                    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer_request('redis_lock_busy', 60);
                }
                exit;
            }
            if ($rxRedisLockResult === true) {
                register_shutdown_function(static function () use ($rxRedisLockKey, $rxRedisLockToken) {
                    \Faoxima\Core\Cache\RedisCache::rx_redis_release_lock($rxRedisLockKey, $rxRedisLockToken);
                });
            }
        }

        if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_deadline')) {
            self::rx_cron_deadline();
        }
    }

    public static function rx_cron_shard(): array
    {
        $n = (int) ($_GET['workers'] ?? $_SERVER['BROADCAST_WORKERS'] ?? 1);
        $n = max(1, min(16, $n));
        $w = (int) ($_GET['worker'] ?? $_SERVER['BROADCAST_WORKER_ID'] ?? 0);
        $w = max(0, min($n - 1, $w));
        return [$w, $n];
    }

    public static function rx_cron_require_or_skip(string $jobName, array $files): bool
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] missing required file: ' . $file
                     . ' — skipping this run (upload the file to fix).';
                @error_log($msg);
                @file_put_contents(self::rx_cron_runtime_dir() . '/_missing_files.log', $msg . PHP_EOL, FILE_APPEND);
                if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('missing_required_file', 300);
                }
                return false;
            }
        }
        foreach ($files as $file) {
            require_once $file;
        }
        return true;
    }

    public static function rx_cron_tables_ready(string $jobName = 'cron'): bool
    {
        global $pdo, $connect;

        static $cachedResult = null;
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $requiredTables = ['admin', 'user', 'invoice', 'setting', 'PaySetting', 'Payment_report', 'marzban_panel'];

        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query('SHOW TABLES');
                $existing = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            } elseif (isset($connect) && $connect !== null) {
                $existing = [];
                $result = mysqli_query($connect, 'SHOW TABLES');
                if ($result) {
                    while ($row = mysqli_fetch_row($result)) {
                        $existing[] = $row[0];
                    }
                }
            } else {
                return $cachedResult = false;
            }
        } catch (\Throwable $e) {
            return $cachedResult = false;
        }

        $existingLower = array_map('strtolower', $existing);
        $missing = [];
        foreach ($requiredTables as $rxReqTable) {
            if (!in_array(strtolower($rxReqTable), $existingLower, true)) {
                $missing[] = $rxReqTable;
            }
        }
        if (empty($missing)) {
            return $cachedResult = true;
        }

        return $cachedResult = false;
    }

    public static function rx_cron_db_ready(string $jobName = 'cron'): bool
    {
        global $pdo, $connect;
        $pdoOk     = isset($pdo) && $pdo instanceof PDO;
        $connectOk = isset($connect) && $connect !== null;
        if (!$pdoOk && !$connectOk) {
            $marker = self::rx_cron_runtime_dir() . '/_db_unavailable.flag';
            $age    = is_file($marker) ? (time() - (int) @filemtime($marker)) : 99999;
            if ($age > 3600) {
                $msg = '[' . date('Y-m-d H:i:s') . '] [cron:' . $jobName . '] DB connection unavailable'
                     . ' — check config.php credentials. Skipping silently for the next hour.';
                @error_log($msg);
                @file_put_contents(self::rx_cron_runtime_dir() . '/_db_unavailable.log', $msg . PHP_EOL, FILE_APPEND);
                @touch($marker);
            }
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('db_unavailable', 120);
            }
            return false;
        }

        if (!self::rx_cron_tables_ready($jobName)) {
            if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('db_tables_unavailable', 120);
            }
            return false;
        }

        return true;
    }

    public static function rx_cron_budget_exhausted(): bool
    {
        if (!self::rx_cron_time_up()) {
            return false;
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('time_budget', 60);
        }
        return true;
    }
}
