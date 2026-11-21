<?php

namespace Faoxima\Cron\Health;

use PDO;

final class HealthStorage
{
    public static function rx_cron_health_root(): string
    {
        return dirname((dirname(__DIR__, 3) . '/lib'));
    }

    public static function rx_cron_health_window(): int
    {
        $window = 90;
        if (defined('FAOXIMA_CRON_HEALTH_WINDOW')) {
            $window = (int) FAOXIMA_CRON_HEALTH_WINDOW;
        } elseif (isset($GLOBALS['cronHealthWindow']) && is_numeric($GLOBALS['cronHealthWindow'])) {
            $window = (int) $GLOBALS['cronHealthWindow'];
        }
        return max(65, min(600, $window));
    }

    public static function rx_cron_health_settle_grace(): int
    {
        return 45;
    }

    public static function rx_cron_health_new_id(int $bytes = 8): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Throwable $e) {
            return substr(hash('sha256', uniqid('', true) . microtime(true) . getmypid()), 0, $bytes * 2);
        }
    }

    public static function rx_cron_health_valid_id($id): bool
    {
        return is_string($id) && preg_match('/^[a-f0-9]{16}$/', $id) === 1;
    }

    public static function rx_cron_health_h($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function rx_cron_health_runtime_dir(): string
    {
        return self::rx_cron_health_root() . DIRECTORY_SEPARATOR . 'cronbot' . DIRECTORY_SEPARATOR . '.runtime';
    }

    public static function rx_cron_health_journal_dir(bool $create = true): ?string
    {
        $dir = self::rx_cron_health_runtime_dir() . DIRECTORY_SEPARATOR . 'health';
        if ($create && !@is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @is_dir($dir) ? $dir : null;
    }

    public static function rx_cron_health_marker_path(): string
    {
        return self::rx_cron_health_runtime_dir() . DIRECTORY_SEPARATOR . 'cron_health_pending.json';
    }

    public static function rx_cron_health_marker_read(): array
    {
        $path = self::rx_cron_health_marker_path();
        if (!@is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $id => $deadline) {
            if (self::rx_cron_health_valid_id((string) $id)) {
                $out[(string) $id] = (int) $deadline;
            }
        }
        return $out;
    }

    public static function rx_cron_health_marker_pending(): bool
    {
        $now = time();
        foreach (self::rx_cron_health_marker_read() as $deadline) {
            if ($deadline >= $now) {
                return true;
            }
        }
        return false;
    }

    public static function rx_cron_health_marker_write(array $entries, bool $create): bool
    {
        $path = self::rx_cron_health_marker_path();
        if (!$create && !@is_file($path)) {
            return true;
        }
        if (empty($entries)) {
            if (@is_file($path)) {
                @unlink($path);
            }
            return !@is_file($path);
        }
        $dir = self::rx_cron_health_runtime_dir();
        if (!@is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }
        $ok = false;
        if (@flock($fh, LOCK_EX)) {
            $json = (string) json_encode($entries);
            @ftruncate($fh, 0);
            @rewind($fh);
            $ok = @fwrite($fh, $json) === strlen($json);
            @fflush($fh);
            @flock($fh, LOCK_UN);
        }
        @fclose($fh);
        return $ok;
    }

    public static function rx_cron_health_marker_sync(PDO $pdo, bool $create = false): bool
    {
        try {
            $rows = $pdo->query("SELECT test_id, deadline FROM cron_health_test WHERE active_key IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
        $entries = [];
        foreach ((array) $rows as $row) {
            $entries[(string) $row['test_id']] = (int) $row['deadline'];
        }
        return self::rx_cron_health_marker_write($entries, $create);
    }

    public static function rx_cron_health_journal_append(string $inv, array $record): bool
    {
        if (!self::rx_cron_health_valid_id($inv)) {
            return false;
        }
        $dir = self::rx_cron_health_journal_dir(true);
        if ($dir === null) {
            return false;
        }
        $record['inv'] = $inv;
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            return false;
        }
        return @file_put_contents($dir . DIRECTORY_SEPARATOR . 'inv_' . $inv . '.jsonl', $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    public static function rx_cron_health_journal_scan(int $since): array
    {
        $dir = self::rx_cron_health_journal_dir(false);
        if ($dir === null || !@is_readable($dir)) {
            return [];
        }
        $files = @glob($dir . DIRECTORY_SEPARATOR . 'inv_*.jsonl');
        if (!is_array($files)) {
            return [];
        }
        $records = [];
        foreach ($files as $file) {
            if ((int) @filemtime($file) < $since) {
                continue;
            }
            $raw = @file_get_contents($file);
            if (!is_string($raw)) {
                continue;
            }
            foreach (explode("\n", $raw) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $rec = json_decode($line, true);
                if (is_array($rec) && self::rx_cron_health_valid_id((string) ($rec['inv'] ?? ''))) {
                    $records[] = $rec;
                }
            }
        }
        return $records;
    }

    public static function rx_cron_health_journal_prune(int $maxAge = 172800): void
    {
        $dir = self::rx_cron_health_journal_dir(false);
        if ($dir === null) {
            return;
        }
        $files = @glob($dir . DIRECTORY_SEPARATOR . 'inv_*.jsonl');
        if (!is_array($files)) {
            return;
        }
        $limit = time() - $maxAge;
        foreach ($files as $file) {
            if ((int) @filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }

    public static function rx_cron_health_ensure_tables(PDO $pdo): bool
    {
        static $done = false;
        if ($done) {
            return true;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cron_health_test (
                test_id VARCHAR(32) NOT NULL PRIMARY KEY,
                admin_id BIGINT NOT NULL,
                chat_id BIGINT NOT NULL,
                message_id BIGINT NOT NULL DEFAULT 0,
                active_key VARCHAR(32) NULL DEFAULT NULL,
                created_at INT UNSIGNED NOT NULL,
                deadline INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'waiting',
                invocation_id VARCHAR(32) NULL DEFAULT NULL,
                observed_at INT UNSIGNED NULL DEFAULT NULL,
                claim_token VARCHAR(32) NULL DEFAULT NULL,
                claim_until INT UNSIGNED NOT NULL DEFAULT 0,
                report_attempts INT UNSIGNED NOT NULL DEFAULT 0,
                reported_at INT UNSIGNED NULL DEFAULT NULL,
                meta TEXT NULL,
                result MEDIUMTEXT NULL,
                UNIQUE KEY uq_active (active_key),
                KEY idx_status_deadline (status, deadline)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS cron_health_event (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                invocation_id VARCHAR(32) NOT NULL,
                kind VARCHAR(10) NOT NULL,
                job_key VARCHAR(64) NOT NULL DEFAULT '',
                worker INT NOT NULL DEFAULT 0,
                state VARCHAR(24) NOT NULL,
                started_at INT UNSIGNED NOT NULL DEFAULT 0,
                finished_at INT UNSIGNED NOT NULL DEFAULT 0,
                data MEDIUMTEXT NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_event (invocation_id, kind, job_key, worker),
                KEY idx_kind_started (kind, started_at),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
            $done = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function rx_cron_health_current_pdo(): ?PDO
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        return $pdo instanceof PDO ? $pdo : null;
    }

    public static function rx_cron_health_probe_dir(string $dir): array
    {
        clearstatcache();
        if (!@is_dir($dir)) {
            return ['ok' => false, 'step' => 'missing'];
        }
        $name = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.rx_cronhealth_' . self::rx_cron_health_new_id(6) . '.tmp';
        $payload = 'rx-cron-health:' . self::rx_cron_health_new_id(8);
        $fh = @fopen($name, 'x');
        if ($fh === false) {
            return ['ok' => false, 'step' => 'create'];
        }
        $step = '';
        $written = @fwrite($fh, $payload);
        @fflush($fh);
        if ($written !== strlen($payload)) {
            $step = 'write';
        }
        if ($step === '') {
            if (@flock($fh, LOCK_EX | LOCK_NB)) {
                @flock($fh, LOCK_UN);
            } else {
                $step = 'lock';
            }
        }
        @fclose($fh);
        if ($step === '' && @file_get_contents($name) !== $payload) {
            $step = 'read';
        }
        $deleted = @unlink($name);
        clearstatcache(true, $name);
        if ((!$deleted || @file_exists($name)) && $step === '') {
            $step = 'delete';
        }
        return ['ok' => $step === '', 'step' => $step];
    }

    public static function rx_cron_health_fs_checks(): array
    {
        $root = self::rx_cron_health_root();
        $runtime = self::rx_cron_health_runtime_dir();
        if (!@is_dir($runtime)) {
            @mkdir($runtime, 0775, true);
        }
        return [
            'cron/' => self::rx_cron_health_probe_dir($root . DIRECTORY_SEPARATOR . 'cron'),
            'cronbot/.runtime/' => self::rx_cron_health_probe_dir($runtime),
            'temp' => self::rx_cron_health_probe_dir(rtrim(sys_get_temp_dir(), '/\\')),
        ];
    }

    public static function rx_cron_health_required_files(): array
    {
        $root = self::rx_cron_health_root();
        $bad = [];
        foreach (['config.php', 'function.php', 'botapi.php', 'cron/cron.php', 'cronbot/_init.php', 'src/Cron/Health/CronHealth.php'] as $rel) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!@is_file($path) || !@is_readable($path)) {
                $bad[] = $rel;
            }
        }
        return $bad;
    }
}
