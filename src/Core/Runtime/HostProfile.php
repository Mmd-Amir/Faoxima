<?php

namespace Faoxima\Core\Runtime;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class HostProfile
{
    public static function rx_host_profile_cache_path()
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT
              : (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 3));
        $cronbot = $root . DIRECTORY_SEPARATOR . 'cronbot';
        $runtime = $cronbot . DIRECTORY_SEPARATOR . '.runtime';
        if (@is_dir($runtime) || @mkdir($runtime, 0775, true) || @is_dir($runtime)) {
            return $runtime . DIRECTORY_SEPARATOR . 'host_profile.json';
        }
        if (@is_dir($cronbot)) {
            return $cronbot . DIRECTORY_SEPARATOR . 'host_profile.json';
        }
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_host_profile_' . md5((string) $root) . '.json';
    }

    public static function rx_exec_disabled()
    {
        foreach (['exec', 'shell_exec'] as $fn) {
            if (!function_exists($fn)) {
                return true;
            }
        }
        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return false;
        }
        foreach (['exec', 'shell_exec'] as $fn) {
            if (preg_match('/(^|,)\s*' . $fn . '\s*(,|$)/', $disabled)) {
                return true;
            }
        }
        return false;
    }

    public static function rx_putenv_disabled()
    {
        if (!function_exists('putenv')) {
            return true;
        }
        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return false;
        }
        return (bool) preg_match('/(^|,)\s*putenv\s*(,|$)/', $disabled);
    }

    public static function rx_mysql_show_value($pdo, $kind, $name)
    {
        try {
            if (!($pdo instanceof PDO)) {
                return 0;
            }
            $kind = ($kind === 'STATUS') ? 'STATUS' : 'VARIABLES';
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
            if ($name === '') {
                return 0;
            }
            $stmt = $pdo->query("SHOW {$kind} LIKE '{$name}'");
            if ($stmt === false) {
                return 0;
            }
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return (is_array($row) && isset($row[1])) ? $row[1] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function rx_resize_for_profile($maxConnections, $profile)
    {
        $maxConnections = (int) $maxConnections;
        if ($profile === 'vps') {
            return [
                'cron_db_budget'       => ($maxConnections > 0) ? max(20, (int) floor($maxConnections * 0.20)) : 20,
                'cron_time_budget'     => 120,
                'broadcast_workers'    => 4,
                'payment_workers'      => 4,
                'cron_max_concurrency' => 3,
            ];
        }
        return [
            'cron_db_budget'       => ($maxConnections > 0) ? max(4, min(8, (int) floor($maxConnections * 0.06))) : 6,
            'cron_time_budget'     => 22,
            'broadcast_workers'    => 1,
            'payment_workers'      => 1,
            'cron_max_concurrency' => 2,
        ];
    }

    public static function rx_detect_host_profile()
    {
        $maxConn = 0;
        $maxUsed = 0;
        $maxUser = -1;
        try {
            $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
            if ($pdo instanceof PDO) {
                $maxConn = (int) self::rx_mysql_show_value($pdo, 'VARIABLES', 'max_connections');
                $maxUser = (int) self::rx_mysql_show_value($pdo, 'VARIABLES', 'max_user_connections');
                $maxUsed = (int) self::rx_mysql_show_value($pdo, 'STATUS', 'Max_used_connections');
            }
        } catch (\Throwable $e) {
        }

        $execDisabled = self::rx_exec_disabled();
        $cpanel       = @is_dir('/usr/local/cpanel');

        $isShared = $cpanel
            || $execDisabled
            || ($maxUser > 0 && $maxUser <= 50);
        $profile = $isShared ? 'shared' : 'vps';

        return array_merge([
            'profile'              => $profile,
            'max_connections'      => $maxConn,
            'max_used_connections' => $maxUsed,
            'max_user_connections' => $maxUser,
            'exec_disabled'        => $execDisabled ? 1 : 0,
            'cpanel'               => $cpanel ? 1 : 0,
            'detected_at'          => time(),
        ], self::rx_resize_for_profile($maxConn, $profile));
    }

    public static function rxHostProfileApplySettings(array &$profile, array $settings): void
    {
        $override = isset($settings['host_profile']) ? strtolower(trim((string) $settings['host_profile'])) : '';
        if (($override === 'shared' || $override === 'vps') && $override !== ($profile['profile'] ?? '')) {
            $profile = array_merge($profile, ['profile' => $override], self::rx_resize_for_profile((int) ($profile['max_connections'] ?? 0), $override));
        }
        foreach (['cron_db_budget', 'cron_time_budget', 'cron_max_concurrency'] as $key) {
            if (isset($settings[$key]) && (int) $settings[$key] > 0) {
                $profile[$key] = $key === 'cron_max_concurrency'
                    ? max(1, min(8, (int) $settings[$key]))
                    : (int) $settings[$key];
            }
        }
    }

    public static function rxHostProfilePersist(string $path, array &$profile): void
    {
        if ($path === '') {
            return;
        }
        $lock = @fopen($path . '.workers.lock', 'c');
        if (!is_resource($lock)) {
            return;
        }
        if (@flock($lock, LOCK_EX | LOCK_NB)) {
            $tmp = null;
            try {
                $current = (@is_file($path)) ? (string) @file_get_contents($path) : '';
                $latest = json_decode($current, true);
                if (is_array($latest)) {
                    if (isset($latest['manual_workers']) && is_array($latest['manual_workers'])) {
                        $profile['manual_workers'] = $latest['manual_workers'];
                    } else {
                        unset($profile['manual_workers']);
                    }
                }
                $new = json_encode($profile, JSON_UNESCAPED_UNICODE);
                if ($new !== false && $new !== $current) {
                    $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
                    if (@file_put_contents($tmp, $new, LOCK_EX) === strlen($new)) {
                        $mode = @fileperms($path);
                        @chmod($tmp, $mode === false ? 0660 : ($mode & 0666));
                        @rename($tmp, $path);
                    }
                }
            } finally {
                if ($tmp !== null && is_file($tmp)) {
                    @unlink($tmp);
                }
                @flock($lock, LOCK_UN);
            }
        }
        @fclose($lock);
    }

    public static function rx_host_profile()
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $path     = self::rx_host_profile_cache_path();
        $resolved = null;
        $manualWorkers = null;

        if ($path !== '' && @is_file($path)) {
            $age = time() - (int) @filemtime($path);
            $raw     = @file_get_contents($path);
            $decoded = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                if (isset($decoded['manual_workers']) && is_array($decoded['manual_workers'])) {
                    $manualWorkers = $decoded['manual_workers'];
                }
                if ($age >= 0 && $age < 604800 && isset($decoded['profile'])) {
                    $resolved = $decoded;
                }
            }
        }

        if ($resolved === null) {
            try {
                $resolved = self::rx_detect_host_profile();
            } catch (\Throwable $e) {
                $resolved = array_merge(['profile' => 'shared', 'max_connections' => 0], self::rx_resize_for_profile(0, 'shared'));
            }
        }
        if ($manualWorkers !== null) {
            $resolved['manual_workers'] = $manualWorkers;
        }

        try {
            $s = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', '*') : null;
            if (is_array($s)) {
                self::rxHostProfileApplySettings($resolved, $s);
            }
        } catch (\Throwable $e) {
        }

        self::rxHostProfilePersist($path, $resolved);

        $cache = $resolved;
        return $cache;
    }

    public static function rx_cron_worker_counts(array $profile, array $settings = []): array
    {
        $counts = [];
        foreach (['broadcast_workers' => 3, 'payment_workers' => 2] as $key => $fallback) {
            $value = max(1, min(8, (int) ($profile[$key] ?? $fallback)));
            if (isset($settings[$key])) {
                $value = max(1, min(8, (int) $settings[$key]));
            }
            if (($profile['profile'] ?? 'shared') === 'shared') {
                $value = 1;
            }
            if (isset($profile['manual_workers'][$key])) {
                $value = max(1, min(8, (int) $profile['manual_workers'][$key]));
            }
            $counts[$key] = $value;
        }
        return $counts;
    }

    public static function rx_save_cron_worker_count(string $key, int $value): array
    {
        if (!in_array($key, ['broadcast_workers', 'payment_workers'], true) || $value < 1 || $value > 8) {
            throw new InvalidArgumentException('Invalid cron worker count');
        }
        $profile = self::rx_host_profile();
        $path = self::rx_host_profile_cache_path();
        $lock = @fopen($path . '.workers.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open worker settings lock');
        }
        $tmp = null;
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Worker settings are busy');
            }
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                $stored = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($stored)) {
                    throw new RuntimeException('Cannot read host profile');
                }
                $profile = array_merge($profile, $stored);
            }
            if (!isset($profile['manual_workers']) || !is_array($profile['manual_workers'])) {
                $profile['manual_workers'] = [];
            }
            $profile['manual_workers'][$key] = $value;
            $body = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
            if (@file_put_contents($tmp, $body, LOCK_EX) !== strlen($body)) {
                throw new RuntimeException('Cannot write worker settings');
            }
            $mode = @fileperms($path);
            @chmod($tmp, $mode === false ? 0660 : ($mode & 0666));
            if (!@rename($tmp, $path)) {
                throw new RuntimeException('Cannot replace worker settings');
            }
            $tmp = null;
            return $profile;
        } finally {
            if ($tmp !== null) {
                @unlink($tmp);
            }
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    public static function rx_cron_db_budget()
    {
        $p = self::rx_host_profile();
        return max(1, (int) ($p['cron_db_budget'] ?? 6));
    }

    public static function rx_cron_time_budget()
    {
        $p = self::rx_host_profile();
        return max(5, (int) ($p['cron_time_budget'] ?? 22));
    }
}
