<?php

namespace Faoxima\Cron\Runtime;

use PDO;

final class TaskState
{
    public static function rx_cron_catchup_seconds(): int
        {
            return 3600;
        }

    public static function rx_cron_catchup_allowed(int $scheduledFor, ?int $now = null): bool
        {
            $now = $now ?? time();
            return $scheduledFor > 0 && $scheduledFor <= $now && $scheduledFor >= $now - self::rx_cron_catchup_seconds();
        }

    public static function rx_cron_task_runtime_dir(): string
        {
            $base = (dirname(__DIR__, 3) . '/cronbot') . DIRECTORY_SEPARATOR . '.runtime';
            if (!@is_dir($base)) {
                @mkdir($base, 0775, true);
            }
            $dir = $base . DIRECTORY_SEPARATOR . 'tasks';
            if (!@is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            return @is_dir($dir) ? $dir : $base;
        }

    public static function rx_cron_task_pdo(): ?PDO
        {
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                return $GLOBALS['pdo'];
            }
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                try {
                    $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
                    return $pdo instanceof PDO ? $pdo : null;
                } catch (\Throwable $e) {
                    return null;
                }
            }
            return null;
        }

    public static function rx_cron_task_key(string $jobKey, int $workers, int $worker): string
        {
            return $jobKey . ':' . max(1, $workers) . ':' . max(0, $worker);
        }

    public static function rx_cron_task_lease_seconds(int $maxAgeSeconds): int
        {
            return max(180, min(3600, $maxAgeSeconds + 120));
        }

    public static function rx_cron_task_ensure_table(?PDO $pdo = null, bool $force = false): bool
        {
            static $ready = false;
            if ($ready) {
                return true;
            }
            $pdo = $pdo instanceof PDO ? $pdo : self::rx_cron_task_pdo();
            if (!($pdo instanceof PDO)) {
                return false;
            }
            $runtime = dirname(self::rx_cron_task_runtime_dir());
            $marker = $runtime . DIRECTORY_SEPARATOR . '.cron_task_schema_v2';
            $failure = $runtime . DIRECTORY_SEPARATOR . '.cron_task_schema_fail';
            if (!$force) {
                $markerAge = is_file($marker) ? (time() - (int) @filemtime($marker)) : PHP_INT_MAX;
                if ($markerAge >= 0 && $markerAge < 600) {
                    $ready = true;
                    return true;
                }
                if (is_file($failure) && (time() - (int) @filemtime($failure)) < 120) {
                    return false;
                }
            }

            $probe = static function () use ($pdo): bool {
                try {
                    $pdo->query('SELECT outcome, next_attempt_at FROM cron_task_state LIMIT 1');
                    return true;
                } catch (\Throwable $e) {
                    return false;
                }
            };

            $lockName = 'faoxima_cron_schema';
            try {
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
                if (is_string($dbName) && $dbName !== '') {
                    $lockName .= ':' . substr(sha1($dbName), 0, 16);
                }
            } catch (\Throwable $e) {
            }

            $locked = false;
            try {
                $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 3)');
                $lockStmt->execute([$lockName]);
                $locked = ((int) $lockStmt->fetchColumn() === 1);
            } catch (\Throwable $e) {
                $locked = false;
            }

            try {
                if (!$locked) {
                    if ($probe()) {
                        @file_put_contents($marker, (string) time(), LOCK_EX);
                        @unlink($failure);
                        $ready = true;
                        return true;
                    }
                    @touch($failure);
                    return false;
                }
                if (!$probe()) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS cron_task_state (
                        task_key VARCHAR(191) NOT NULL,
                        job_key VARCHAR(191) NOT NULL,
                        script VARCHAR(191) NOT NULL DEFAULT '',
                        worker INT NOT NULL DEFAULT 0,
                        workers INT NOT NULL DEFAULT 1,
                        scheduled_for BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        state VARCHAR(16) NOT NULL DEFAULT 'pending',
                        outcome VARCHAR(24) NOT NULL DEFAULT '',
                        token VARCHAR(64) NULL DEFAULT NULL,
                        attempts INT NOT NULL DEFAULT 0,
                        lease_expires BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        next_attempt_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        job_dispatched_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        last_error VARCHAR(255) NULL DEFAULT NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (task_key),
                        KEY idx_state_lease (state, lease_expires),
                        KEY idx_state_job (state, job_dispatched_at),
                        KEY idx_state_next (state, next_attempt_at),
                        KEY idx_state_updated (state, updated_at),
                        KEY idx_job (job_key)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $existingColumns = [];
                    try {
                        $columnStmt = $pdo->query('SHOW COLUMNS FROM cron_task_state');
                        if ($columnStmt !== false) {
                            while ($columnRow = $columnStmt->fetch(\PDO::FETCH_ASSOC)) {
                                $existingColumns[strtolower((string) ($columnRow['Field'] ?? ''))] = true;
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                    foreach ([
                        'outcome' => "ALTER TABLE cron_task_state ADD COLUMN outcome VARCHAR(24) NOT NULL DEFAULT '' AFTER state",
                        'next_attempt_at' => "ALTER TABLE cron_task_state ADD COLUMN next_attempt_at BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER lease_expires",
                    ] as $column => $alter) {
                        if (!isset($existingColumns[$column])) {
                            try {
                                $pdo->exec($alter);
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                    try {
                        $indexStmt = $pdo->query("SHOW INDEX FROM cron_task_state WHERE Key_name = 'idx_state_next'");
                        if ($indexStmt !== false && $indexStmt->fetch(\PDO::FETCH_ASSOC) === false) {
                            $pdo->exec('ALTER TABLE cron_task_state ADD INDEX idx_state_next (state, next_attempt_at)');
                        }
                    } catch (\Throwable $e) {
                    }
                    if (!$probe()) {
                        @touch($failure);
                        return false;
                    }
                }
                @file_put_contents($marker, (string) time(), LOCK_EX);
                @unlink($failure);
                $ready = true;
                return true;
            } catch (\Throwable $e) {
                @touch($failure);
                return false;
            } finally {
                if ($locked) {
                    try {
                        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

    public static function rx_cron_task_write_marker(string $type, string $key, string $token, string $state = '', string $error = '', ?int $lease = null, string $outcome = '', int $nextAttemptAt = 0, int $occurrence = 0): void
        {
            $dir = self::rx_cron_task_runtime_dir();
            if (!@is_dir($dir)) {
                error_log('[cron:task] marker directory unavailable');
                return;
            }
            $name = $type . '_' . sha1($key . '|' . $token) . '.json';
            $payload = json_encode([
                'key' => $key,
                'token' => $token,
                'state' => $state,
                'outcome' => $outcome,
                'next_attempt_at' => $nextAttemptAt,
                'occurrence' => $occurrence,
                'error' => substr($error, 0, 200),
                'lease' => $lease,
                'ts' => time(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                error_log('[cron:task] marker encoding failed');
                return;
            }
            $tmp = $dir . DIRECTORY_SEPARATOR . '.tmp_' . getmypid() . '_' . mt_rand(1000, 999999);
            if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
                @unlink($tmp);
                error_log('[cron:task] marker write failed');
                return;
            }
            if (!@rename($tmp, $dir . DIRECTORY_SEPARATOR . $name)) {
                @unlink($tmp);
                error_log('[cron:task] marker publication failed');
            }
        }

    public static function rx_cron_task_normalize_outcome(string $outcome): string
        {
            $outcome = strtolower(trim($outcome));
            if (!in_array($outcome, ['completed', 'partial', 'deferred', 'retryable', 'uncertain', 'failed'], true)) {
                $outcome = 'partial';
            }
            return $outcome;
        }

    public static function rx_cron_task_finish_plan(string $outcome, int $now, int $nextAttemptAt): array
        {
            if ($outcome === 'completed') {
                $state = 'completed';
                $nextAttemptAt = 0;
                $attemptsSql = 'attempts = 0';
            } elseif ($outcome === 'failed') {
                $state = 'failed';
                $nextAttemptAt = 0;
                $attemptsSql = 'attempts = GREATEST(attempts, 1)';
            } elseif ($outcome === 'uncertain') {
                $state = 'uncertain';
                if ($nextAttemptAt <= 0) {
                    $nextAttemptAt = $now + 1800;
                }
                $attemptsSql = 'attempts = attempts';
            } elseif ($outcome === 'retryable') {
                $state = 'pending';
                if ($nextAttemptAt <= 0) {
                    $nextAttemptAt = $now + 120;
                }
                $attemptsSql = 'attempts = attempts';
            } else {
                $state = 'pending';
                if ($nextAttemptAt <= 0) {
                    $nextAttemptAt = $now + ($outcome === 'partial' ? 300 : 90);
                }
                $attemptsSql = 'attempts = GREATEST(attempts - 1, 0)';
            }
            return [$state, $nextAttemptAt, $attemptsSql];
        }

    public static function rx_cron_task_finish(PDO $pdo, string $taskKey, string $token, string $outcome, string $message = '', int $nextAttemptAt = 0): bool
        {
            $outcome = self::rx_cron_task_normalize_outcome($outcome);
            $now = time();
            [$state, $nextAttemptAt, $attemptsSql] = self::rx_cron_task_finish_plan($outcome, $now, $nextAttemptAt);
            try {
                if ($state === 'pending') {
                    $occ = $pdo->prepare('SELECT scheduled_for FROM cron_task_state WHERE task_key = ? AND token = ?');
                    $occ->execute([$taskKey, $token]);
                    if (!self::rx_cron_catchup_allowed((int) $occ->fetchColumn(), $now)) {
                        $state = 'cancelled';
                        $outcome = 'failed';
                        $message = 'catchup_window_elapsed';
                        $nextAttemptAt = 0;
                    }
                }
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = ?, outcome = ?, lease_expires = 0, next_attempt_at = ?, last_error = ?, " . $attemptsSql . ", updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain')");
                $stmt->execute([$state, $outcome, (int) $nextAttemptAt, substr($message, 0, 250), $taskKey, $token]);
                return $stmt->rowCount() === 1;
            } catch (\Throwable $e) {
                error_log('[cron:task] finish persistence failed: ' . get_class($e));
                return false;
            }
        }

    public static function rx_cron_task_scan_markers(PDO $pdo, int $leaseSeconds, int $now): bool
        {
            $dir = self::rx_cron_task_runtime_dir();
            $healthy = true;
            foreach (['done', 'run'] as $type) {
                $files = @glob($dir . DIRECTORY_SEPARATOR . $type . '_*.json');
                if (!is_array($files)) {
                    continue;
                }
                foreach ($files as $file) {
                    $raw = @file_get_contents($file);
                    $data = is_string($raw) ? json_decode($raw, true) : null;
                    if (!is_array($data)) {
                        @unlink($file);
                        continue;
                    }
                    $key = trim((string) ($data['key'] ?? ''));
                    $token = trim((string) ($data['token'] ?? ''));
                    if ($key === '' || $token === '') {
                        @unlink($file);
                        continue;
                    }
                    try {
                        if ($type === 'done') {
                            $outcome = trim((string) ($data['outcome'] ?? ''));
                            if ($outcome === '') {
                                $outcome = ((string) ($data['state'] ?? '')) === 'failed' ? 'failed' : 'completed';
                            }
                            if (!self::rx_cron_task_finish($pdo, $key, $token, $outcome, (string) ($data['error'] ?? ''), (int) ($data['next_attempt_at'] ?? 0))) {
                                $check = $pdo->prepare('SELECT state, token FROM cron_task_state WHERE task_key = ?');
                                $check->execute([$key]);
                                $current = $check->fetch(PDO::FETCH_ASSOC);
                                if (is_array($current) && hash_equals($token, (string) ($current['token'] ?? '')) && in_array((string) $current['state'], ['claimed', 'running', 'uncertain'], true)) {
                                    $healthy = false;
                                    continue;
                                }
                            }
                        } else {
                            $markerLease = max(60, $leaseSeconds, (int) ($data['lease'] ?? 0));
                            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'running', lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain')");
                            $stmt->execute([(int) ($data['ts'] ?? $now) + $markerLease, $key, $token]);
                        }
                        @unlink($file);
                    } catch (\Throwable $e) {
                        $healthy = false;
                    }
                }
            }
            $tmpFiles = @glob($dir . DIRECTORY_SEPARATOR . '.tmp_*');
            if (is_array($tmpFiles)) {
                foreach ($tmpFiles as $tmp) {
                    if ((time() - (int) @filemtime($tmp)) > 300) {
                        @unlink($tmp);
                    }
                }
            }
            return $healthy;
        }

    public static function rx_cron_task_request(string $jobKey): ?array
        {
            $key = isset($_SERVER['HTTP_X_CRON_TASK']) ? trim((string) $_SERVER['HTTP_X_CRON_TASK']) : '';
            if ($key === '') {
                $envKey = getenv('RX_CRON_TASK');
                $key = is_string($envKey) ? trim($envKey) : '';
            }
            $token = isset($_SERVER['HTTP_X_CRON_TASK_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CRON_TASK_TOKEN']) : '';
            if ($token === '') {
                $envToken = getenv('RX_CRON_TASK_TOKEN');
                $token = is_string($envToken) ? trim($envToken) : '';
            }
            if ($key === '' || $token === '') {
                return null;
            }
            if (!preg_match('/^[A-Za-z0-9_\-]{1,140}:[0-9]{1,3}:[0-9]{1,3}$/', $key)) {
                return null;
            }
            if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
                return null;
            }
            if ($jobKey !== '' && strpos($key, $jobKey . ':') !== 0) {
                return null;
            }
            $occurrence = 0;
            foreach (['HTTP_X_CRON_OCCURRENCE', 'HTTP_X_CRON_TASK_OCCURRENCE'] as $header) {
                if (isset($_SERVER[$header]) && preg_match('/^[0-9]{1,12}$/', trim((string) $_SERVER[$header]))) {
                    $occurrence = (int) trim((string) $_SERVER[$header]);
                    break;
                }
            }
            if ($occurrence <= 0) {
                $envOccurrence = getenv('RX_CRON_OCCURRENCE');
                if (is_string($envOccurrence) && preg_match('/^[0-9]{1,12}$/', trim($envOccurrence))) {
                    $occurrence = (int) trim($envOccurrence);
                }
            }
            return ['key' => $key, 'token' => $token, 'job' => $jobKey, 'occurrence' => $occurrence];
        }

    public static function rx_cron_task_attach(string $jobKey, int $maxAgeSeconds = 180): void
        {
            $meta = self::rx_cron_task_request($jobKey);
            if ($meta === null) {
                return;
            }
            $dir = self::rx_cron_task_runtime_dir();
            $live = @fopen($dir . DIRECTORY_SEPARATOR . 'live_' . sha1($meta['key']) . '.lock', 'c');
            if ($live === false || !@flock($live, LOCK_EX | LOCK_NB)) {
                if (is_resource($live)) {
                    @fclose($live);
                }
                exit;
            }
            $ticket = $dir . DIRECTORY_SEPARATOR . 'claim_' . sha1($meta['key'] . '|' . $meta['token']) . '.json';
            $lease = self::rx_cron_task_lease_seconds($maxAgeSeconds);
            $pdo = self::rx_cron_task_pdo();
            $running = false;
            if ($pdo instanceof PDO && self::rx_cron_task_ensure_table($pdo)) {
                try {
                    $now = time();
                    $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'running', lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND scheduled_for BETWEEN ? AND ? AND (state IN ('claimed','running') OR (state = 'uncertain' AND last_error IN ('dispatch_lease_expired','dispatch_uncertain')))");
                    $stmt->execute([$now + $lease, $meta['key'], $meta['token'], $now - self::rx_cron_catchup_seconds(), $now]);
                    if ($stmt->rowCount() !== 1) {
                        @flock($live, LOCK_UN);
                        @fclose($live);
                        exit;
                    }
                    $running = true;
                } catch (\Throwable $e) {
                    $running = false;
                }
            }
            if (!$running) {
                $raw = @file_get_contents($ticket);
                $claim = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($claim) || ($claim['key'] ?? '') !== $meta['key']
                    || !hash_equals($meta['token'], (string) ($claim['token'] ?? ''))
                    || !self::rx_cron_catchup_allowed((int) ($claim['occurrence'] ?? $meta['occurrence'] ?? 0))
                    || (int) ($claim['ts'] ?? 0) + (int) ($claim['lease'] ?? 0) < time()) {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                    exit;
                }
                self::rx_cron_task_write_marker('run', $meta['key'], $meta['token'], '', '', $lease);
            }
            $GLOBALS['rxCronTaskCurrent'] = [
                'key' => $meta['key'],
                'token' => $meta['token'],
                'lease' => $lease,
                'beat' => 0,
                'scheduled_for' => max(0, (int) ($meta['occurrence'] ?? 0)),
            ];
            if ($GLOBALS['rxCronTaskCurrent']['scheduled_for'] <= 0 && $pdo instanceof PDO) {
                try {
                    $occStmt = $pdo->prepare('SELECT scheduled_for FROM cron_task_state WHERE task_key = ? AND token = ? LIMIT 1');
                    $occStmt->execute([$meta['key'], $meta['token']]);
                    $GLOBALS['rxCronTaskCurrent']['scheduled_for'] = max(0, (int) $occStmt->fetchColumn());
                } catch (\Throwable $e) {
                }
            }
            $GLOBALS['rxCronTaskOutcome'] = null;
            register_shutdown_function(static function () use ($meta, $ticket, $live): void {
                $error = error_get_last();
                $fatal = is_array($error) && in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
                $reported = $GLOBALS['rxCronTaskOutcome'] ?? null;
                if ($fatal) {
                    $outcome = 'failed';
                    $message = (string) ($error['message'] ?? '');
                    $nextAttemptAt = 0;
                } elseif (is_array($reported)) {
                    $outcome = (string) ($reported['outcome'] ?? 'partial');
                    $message = (string) ($reported['message'] ?? '');
                    $nextAttemptAt = (int) ($reported['next_attempt_at'] ?? 0);
                } else {
                    $outcome = 'partial';
                    $message = 'execution_finished_without_confirmation';
                    $nextAttemptAt = time() + 300;
                }
                $pdo = self::rx_cron_task_pdo();
                $done = false;
                if ($pdo instanceof PDO && self::rx_cron_task_ensure_table($pdo)) {
                    try {
                        $done = self::rx_cron_task_finish($pdo, $meta['key'], $meta['token'], $outcome, $message, $nextAttemptAt);
                    } catch (\Throwable $e) {
                        $done = false;
                    }
                }
                if (!$done) {
                    $markerState = in_array($outcome, ['completed', 'failed', 'uncertain'], true) ? $outcome : 'pending';
                    self::rx_cron_task_write_marker('done', $meta['key'], $meta['token'], $markerState === 'pending' ? '' : $markerState, $message, null, $outcome, $nextAttemptAt);
                }
                @unlink($ticket);
                $GLOBALS['rxCronTaskCurrent'] = null;
                $GLOBALS['rxCronTaskOutcome'] = null;
                @flock($live, LOCK_UN);
                @fclose($live);
            });
        }

    public static function rx_cron_task_heartbeat(): void
        {
            $current = $GLOBALS['rxCronTaskCurrent'] ?? null;
            if (!is_array($current) || empty($current['key']) || empty($current['token'])) {
                return;
            }
            $now = time();
            if (($now - (int) ($current['beat'] ?? 0)) < 15) {
                return;
            }
            $GLOBALS['rxCronTaskCurrent']['beat'] = $now;
            $pdo = self::rx_cron_task_pdo();
            if (!($pdo instanceof PDO) || !self::rx_cron_task_ensure_table($pdo)) {
                return;
            }
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain')");
                $stmt->execute([$now + (int) $current['lease'], $current['key'], $current['token']]);
            } catch (\Throwable $e) {
            }
        }

    public static function rx_cron_task_occurrence(int $fallback = 0): int
        {
            $current = $GLOBALS['rxCronTaskCurrent'] ?? null;
            if (is_array($current) && (int) ($current['scheduled_for'] ?? 0) > 0) {
                return (int) $current['scheduled_for'];
            }
            foreach (['HTTP_X_CRON_OCCURRENCE', 'HTTP_X_CRON_TASK_OCCURRENCE'] as $header) {
                if (isset($_SERVER[$header]) && preg_match('/^[0-9]{1,12}$/', trim((string) $_SERVER[$header]))) {
                    return (int) trim((string) $_SERVER[$header]);
                }
            }
            if (isset($_GET['occurrence']) && preg_match('/^[0-9]{1,12}$/', trim((string) $_GET['occurrence']))) {
                return (int) trim((string) $_GET['occurrence']);
            }
            $envOccurrence = getenv('RX_CRON_OCCURRENCE');
            if (is_string($envOccurrence) && preg_match('/^[0-9]{1,12}$/', trim($envOccurrence))) {
                return (int) trim($envOccurrence);
            }
            $meta = self::rx_cron_task_request('');
            if (is_array($meta) && (int) ($meta['occurrence'] ?? 0) > 0) {
                return (int) $meta['occurrence'];
            }
            return max(0, $fallback);
        }

    public static function rx_cron_task_report(string $outcome, string $message = '', int $nextAttemptAt = 0): void
        {
            $outcome = self::rx_cron_task_normalize_outcome($outcome);
            $GLOBALS['rxCronTaskOutcome'] = [
                'outcome' => $outcome,
                'message' => substr($message, 0, 250),
                'next_attempt_at' => max(0, $nextAttemptAt),
            ];
        }

    public static function rx_cron_task_complete(string $message = ''): void
        {
            $current = $GLOBALS['rxCronTaskOutcome'] ?? null;
            if (is_array($current) && in_array((string) ($current['outcome'] ?? ''), ['deferred', 'uncertain', 'retryable', 'failed'], true)) {
                return;
            }
            self::rx_cron_task_report('completed', $message);
        }

    public static function rx_cron_task_partial(string $message = '', int $delay = 300): void
        {
            self::rx_cron_task_report('partial', $message, time() + max(30, $delay));
        }

    public static function rx_cron_task_defer(string $message = '', int $delay = 90): void
        {
            $next = time() + max(15, $delay);
            $GLOBALS['rxCronTaskOutcome'] = [
                'outcome' => 'deferred',
                'message' => substr($message, 0, 250),
                'next_attempt_at' => $next,
            ];
            $current = $GLOBALS['rxCronTaskCurrent'] ?? null;
            if (!is_array($current) || empty($current['key']) || empty($current['token'])) {
                self::rx_cron_task_defer_request($message, $delay);
            }
        }

    public static function rx_cron_task_fail(string $message = '', bool $retryable = false, int $delay = 120): void
        {
            if ($retryable) {
                self::rx_cron_task_report('retryable', $message, time() + max(30, $delay));
                return;
            }
            self::rx_cron_task_report('failed', $message);
        }

    public static function rx_cron_task_uncertain(string $message = '', int $delay = 1800): void
        {
            self::rx_cron_task_report('uncertain', $message, time() + max(60, $delay));
        }

    public static function rx_cron_task_defer_request(string $message = '', int $delay = 90): bool
        {
            $meta = self::rx_cron_task_request('');
            if (!is_array($meta)) {
                return false;
            }
            $pdo = self::rx_cron_task_pdo();
            if (!($pdo instanceof PDO) || !self::rx_cron_task_ensure_table($pdo)) {
                return false;
            }
            $next = time() + max(15, $delay);
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'pending', outcome = 'deferred', token = NULL, lease_expires = 0, next_attempt_at = ?, attempts = GREATEST(attempts - 1, 0), last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain')");
                $stmt->execute([$next, substr($message, 0, 250), $meta['key'], $meta['token']]);
                return $stmt->rowCount() === 1;
            } catch (\Throwable $e) {
                return false;
            }
        }
}
