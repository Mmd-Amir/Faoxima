<?php

if (!defined('RX_CRON_TASK_STATE_LOADED')) {
    define('RX_CRON_TASK_STATE_LOADED', true);

    if (!function_exists('rx_cron_catchup_seconds')) {
        function rx_cron_catchup_seconds(): int
        {
            return 3600;
        }
    }

    if (!function_exists('rx_cron_catchup_allowed')) {
        function rx_cron_catchup_allowed(int $scheduledFor, ?int $now = null): bool
        {
            $now = $now ?? time();
            return $scheduledFor > 0 && $scheduledFor <= $now && $scheduledFor >= $now - rx_cron_catchup_seconds();
        }
    }

    if (!function_exists('rx_cron_task_runtime_dir')) {
        function rx_cron_task_runtime_dir(): string
        {
            $base = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
            if (!@is_dir($base)) {
                @mkdir($base, 0775, true);
            }
            $dir = $base . DIRECTORY_SEPARATOR . 'tasks';
            if (!@is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            return @is_dir($dir) ? $dir : $base;
        }
    }

    if (!function_exists('rx_cron_task_pdo')) {
        function rx_cron_task_pdo(): ?PDO
        {
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                return $GLOBALS['pdo'];
            }
            if (function_exists('getDatabaseConnection')) {
                try {
                    $pdo = getDatabaseConnection();
                    return $pdo instanceof PDO ? $pdo : null;
                } catch (\Throwable $e) {
                    return null;
                }
            }
            return null;
        }
    }

    if (!function_exists('rx_cron_task_key')) {
        function rx_cron_task_key(string $jobKey, int $workers, int $worker): string
        {
            return $jobKey . ':' . max(1, $workers) . ':' . max(0, $worker);
        }
    }

    if (!function_exists('rx_cron_task_lease_seconds')) {
        function rx_cron_task_lease_seconds(int $maxAgeSeconds): int
        {
            return max(180, min(3600, $maxAgeSeconds + 120));
        }
    }

    if (!function_exists('rx_cron_task_ensure_table')) {
        function rx_cron_task_ensure_table(?PDO $pdo = null, bool $force = false): bool
        {
            static $ready = false;
            if ($ready) {
                return true;
            }
            $pdo = $pdo instanceof PDO ? $pdo : rx_cron_task_pdo();
            if (!($pdo instanceof PDO)) {
                return false;
            }
            $runtime = dirname(rx_cron_task_runtime_dir());
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
    }

    if (!function_exists('rx_cron_task_write_marker')) {
        function rx_cron_task_write_marker(string $type, string $key, string $token, string $state = '', string $error = '', ?int $lease = null, string $outcome = '', int $nextAttemptAt = 0, int $occurrence = 0): void
        {
            $dir = rx_cron_task_runtime_dir();
            if (!@is_dir($dir)) {
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
                return;
            }
            $tmp = $dir . DIRECTORY_SEPARATOR . '.tmp_' . getmypid() . '_' . mt_rand(1000, 999999);
            if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
                @unlink($tmp);
                return;
            }
            if (!@rename($tmp, $dir . DIRECTORY_SEPARATOR . $name)) {
                @unlink($tmp);
            }
        }
    }

    if (!function_exists('rx_cron_task_finish')) {
        function rx_cron_task_finish(PDO $pdo, string $taskKey, string $token, string $outcome, string $message = '', int $nextAttemptAt = 0): bool
        {
            $outcome = strtolower(trim($outcome));
            if (!in_array($outcome, ['completed', 'partial', 'deferred', 'retryable', 'uncertain', 'failed'], true)) {
                $outcome = 'partial';
            }
            $now = time();
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
            try {
                if ($state === 'pending') {
                    $occ = $pdo->prepare('SELECT scheduled_for FROM cron_task_state WHERE task_key = ? AND token = ?');
                    $occ->execute([$taskKey, $token]);
                    if (!rx_cron_catchup_allowed((int) $occ->fetchColumn(), $now)) {
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
                return false;
            }
        }
    }

    if (!function_exists('rx_cron_task_scan_markers')) {
        function rx_cron_task_scan_markers(PDO $pdo, int $leaseSeconds, int $now): bool
        {
            $dir = rx_cron_task_runtime_dir();
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
                            if (!rx_cron_task_finish($pdo, $key, $token, $outcome, (string) ($data['error'] ?? ''), (int) ($data['next_attempt_at'] ?? 0))) {
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
    }

    if (!function_exists('rx_cron_task_request')) {
        function rx_cron_task_request(string $jobKey): ?array
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
    }

    if (!function_exists('rx_cron_task_attach')) {
        function rx_cron_task_attach(string $jobKey, int $maxAgeSeconds = 180): void
        {
            $meta = rx_cron_task_request($jobKey);
            if ($meta === null) {
                return;
            }
            $dir = rx_cron_task_runtime_dir();
            $live = @fopen($dir . DIRECTORY_SEPARATOR . 'live_' . sha1($meta['key']) . '.lock', 'c');
            if ($live === false || !@flock($live, LOCK_EX | LOCK_NB)) {
                if (is_resource($live)) {
                    @fclose($live);
                }
                exit;
            }
            $ticket = $dir . DIRECTORY_SEPARATOR . 'claim_' . sha1($meta['key'] . '|' . $meta['token']) . '.json';
            $lease = rx_cron_task_lease_seconds($maxAgeSeconds);
            $pdo = rx_cron_task_pdo();
            $running = false;
            if ($pdo instanceof PDO && rx_cron_task_ensure_table($pdo)) {
                try {
                    $now = time();
                    $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'running', lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND scheduled_for BETWEEN ? AND ? AND (state IN ('claimed','running') OR (state = 'uncertain' AND last_error IN ('dispatch_lease_expired','dispatch_uncertain')))");
                    $stmt->execute([$now + $lease, $meta['key'], $meta['token'], $now - rx_cron_catchup_seconds(), $now]);
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
                    || !rx_cron_catchup_allowed((int) ($claim['occurrence'] ?? $meta['occurrence'] ?? 0))
                    || (int) ($claim['ts'] ?? 0) + (int) ($claim['lease'] ?? 0) < time()) {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                    exit;
                }
                rx_cron_task_write_marker('run', $meta['key'], $meta['token'], '', '', $lease);
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
                $pdo = rx_cron_task_pdo();
                $done = false;
                if ($pdo instanceof PDO && rx_cron_task_ensure_table($pdo)) {
                    try {
                        $done = rx_cron_task_finish($pdo, $meta['key'], $meta['token'], $outcome, $message, $nextAttemptAt);
                    } catch (\Throwable $e) {
                        $done = false;
                    }
                }
                if (!$done) {
                    $markerState = in_array($outcome, ['completed', 'failed', 'uncertain'], true) ? $outcome : 'pending';
                    rx_cron_task_write_marker('done', $meta['key'], $meta['token'], $markerState === 'pending' ? '' : $markerState, $message, null, $outcome, $nextAttemptAt);
                }
                @unlink($ticket);
                $GLOBALS['rxCronTaskCurrent'] = null;
                $GLOBALS['rxCronTaskOutcome'] = null;
                @flock($live, LOCK_UN);
                @fclose($live);
            });
        }
    }

    if (!function_exists('rx_cron_task_heartbeat')) {
        function rx_cron_task_heartbeat(): void
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
            $pdo = rx_cron_task_pdo();
            if (!($pdo instanceof PDO) || !rx_cron_task_ensure_table($pdo)) {
                return;
            }
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain')");
                $stmt->execute([$now + (int) $current['lease'], $current['key'], $current['token']]);
            } catch (\Throwable $e) {
            }
        }
    }

    if (!function_exists('rx_cron_task_occurrence')) {
        function rx_cron_task_occurrence(int $fallback = 0): int
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
            $meta = rx_cron_task_request('');
            if (is_array($meta) && (int) ($meta['occurrence'] ?? 0) > 0) {
                return (int) $meta['occurrence'];
            }
            return max(0, $fallback);
        }
    }

    if (!function_exists('rx_cron_task_report')) {
        function rx_cron_task_report(string $outcome, string $message = '', int $nextAttemptAt = 0): void
        {
            $outcome = strtolower(trim($outcome));
            if (!in_array($outcome, ['completed', 'partial', 'deferred', 'retryable', 'uncertain', 'failed'], true)) {
                $outcome = 'partial';
            }
            $GLOBALS['rxCronTaskOutcome'] = [
                'outcome' => $outcome,
                'message' => substr($message, 0, 250),
                'next_attempt_at' => max(0, $nextAttemptAt),
            ];
        }
    }

    if (!function_exists('rx_cron_task_complete')) {
        function rx_cron_task_complete(string $message = ''): void
        {
            $current = $GLOBALS['rxCronTaskOutcome'] ?? null;
            if (is_array($current) && in_array((string) ($current['outcome'] ?? ''), ['deferred', 'uncertain', 'retryable', 'failed'], true)) {
                return;
            }
            rx_cron_task_report('completed', $message);
        }
    }

    if (!function_exists('rx_cron_task_partial')) {
        function rx_cron_task_partial(string $message = '', int $delay = 300): void
        {
            rx_cron_task_report('partial', $message, time() + max(30, $delay));
        }
    }

    if (!function_exists('rx_cron_task_defer')) {
        function rx_cron_task_defer(string $message = '', int $delay = 90): void
        {
            $next = time() + max(15, $delay);
            $GLOBALS['rxCronTaskOutcome'] = [
                'outcome' => 'deferred',
                'message' => substr($message, 0, 250),
                'next_attempt_at' => $next,
            ];
            $current = $GLOBALS['rxCronTaskCurrent'] ?? null;
            if (!is_array($current) || empty($current['key']) || empty($current['token'])) {
                rx_cron_task_defer_request($message, $delay);
            }
        }
    }

    if (!function_exists('rx_cron_task_fail')) {
        function rx_cron_task_fail(string $message = '', bool $retryable = false, int $delay = 120): void
        {
            if ($retryable) {
                rx_cron_task_report('retryable', $message, time() + max(30, $delay));
                return;
            }
            rx_cron_task_report('failed', $message);
        }
    }

    if (!function_exists('rx_cron_task_uncertain')) {
        function rx_cron_task_uncertain(string $message = '', int $delay = 1800): void
        {
            rx_cron_task_report('uncertain', $message, time() + max(60, $delay));
        }
    }

    if (!function_exists('rx_cron_task_defer_request')) {
        function rx_cron_task_defer_request(string $message = '', int $delay = 90): bool
        {
            $meta = rx_cron_task_request('');
            if (!is_array($meta)) {
                return false;
            }
            $pdo = rx_cron_task_pdo();
            if (!($pdo instanceof PDO) || !rx_cron_task_ensure_table($pdo)) {
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


    if (!function_exists('rx_cron_task_enqueue')) {
        function rx_cron_task_enqueue(PDO $pdo, string $jobKey, string $script, int $workers, int $worker, int $scheduledFor, bool $preserveOccurrence = false): int
        {
            $taskKey = rx_cron_task_key($jobKey, $workers, $worker);
            if (!rx_cron_catchup_allowed($scheduledFor)) {
                return 0;
            }
            $started = false;
            try {
                $pdo->beginTransaction();
                $started = true;
                $stmt = $pdo->prepare('SELECT state, scheduled_for FROM cron_task_state WHERE task_key = ? FOR UPDATE');
                $stmt->execute([$taskKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $now = time();
                if (!is_array($row)) {
                    $insert = $pdo->prepare("INSERT INTO cron_task_state (task_key, job_key, script, worker, workers, scheduled_for, state, outcome, attempts, lease_expires, next_attempt_at, job_dispatched_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', '', 0, 0, 0, 0)");
                    $insert->execute([$taskKey, $jobKey, $script, $worker, $workers, $scheduledFor]);
                    $pdo->commit();
                    return $scheduledFor;
                }
                $state = (string) ($row['state'] ?? '');
                $existingFor = (int) ($row['scheduled_for'] ?? 0);
                if ($state === 'uncertain') {
                    $pdo->commit();
                    return 0;
                }
                if ($state === 'pending' && !rx_cron_catchup_allowed($existingFor, $now)) {
                    $expire = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'failed', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = 'catchup_window_elapsed' WHERE task_key = ? AND state = 'pending'");
                    $expire->execute([$taskKey]);
                    $state = 'cancelled';
                }
                if ($state === 'pending') {
                    if ($existingFor > 0) {
                        $update = $pdo->prepare('UPDATE cron_task_state SET script = ?, worker = ?, workers = ? WHERE task_key = ?');
                        $update->execute([$script, $worker, $workers, $taskKey]);
                        $pdo->commit();
                        return $existingFor;
                    }
                    $update = $pdo->prepare('UPDATE cron_task_state SET scheduled_for = GREATEST(scheduled_for, ?), attempts = IF(? > scheduled_for, 0, attempts), outcome = \'\', next_attempt_at = 0, script = ?, worker = ?, workers = ?, updated_at = NOW() WHERE task_key = ?');
                    $update->execute([$scheduledFor, $scheduledFor, $script, $worker, $workers, $taskKey]);
                    $pdo->commit();
                    return max($existingFor, $scheduledFor);
                }
                if (in_array($state, ['claimed', 'running'], true)) {
                    $pdo->rollBack();
                    $started = false;
                    return 0;
                }
                if ($existingFor >= $scheduledFor) {
                    $pdo->commit();
                    return $existingFor;
                }
                $update = $pdo->prepare("UPDATE cron_task_state SET job_key = ?, script = ?, worker = ?, workers = ?, scheduled_for = ?, state = 'pending', outcome = '', token = NULL, attempts = 0, lease_expires = 0, next_attempt_at = 0, job_dispatched_at = 0, last_error = NULL, updated_at = NOW() WHERE task_key = ?");
                $update->execute([$jobKey, $script, $worker, $workers, $scheduledFor, $taskKey]);
                $pdo->commit();
                return $scheduledFor;
            } catch (\Throwable $e) {
                if ($started && $pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (\Throwable $rollbackError) {
                    }
                }
                return 0;
            }
        }
    }

    if (!function_exists('rx_cron_task_cancel_pending')) {
        function rx_cron_task_cancel_pending(PDO $pdo, string $taskKey, string $reason = ''): void
        {
            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'cancelled', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND state = 'pending'");
            $stmt->execute([substr($reason, 0, 250), $taskKey]);
        }
    }

    if (!function_exists('rx_cron_task_cancel_obsolete')) {
        function rx_cron_task_cancel_obsolete(PDO $pdo, array $validTasks): void
        {
            $stmt = $pdo->query("SELECT task_key, worker, workers FROM cron_task_state WHERE state = 'pending'");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $key = (string) ($row['task_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $valid = isset($validTasks[$key]) && is_array($validTasks[$key]);
                if ($valid) {
                    $valid = (int) ($validTasks[$key]['worker'] ?? -1) === (int) ($row['worker'] ?? -2)
                        && (int) ($validTasks[$key]['workers'] ?? -1) === (int) ($row['workers'] ?? -2);
                }
                if (!$valid) {
                    rx_cron_task_cancel_pending($pdo, $key, 'obsolete');
                }
            }
        }
    }

    if (!function_exists('rx_cron_task_is_payment_job')) {
        function rx_cron_task_is_payment_job(string $jobKey): bool
        {
            return in_array($jobKey, [
                'croncard', 'plisio', 'nowpaymentcheck', 'blupalcheck', 'atlaspaycheck',
                'tronadocheck', 'tonpaycheck', 'rialitcheck', 'cryptocheck',
            ], true);
        }
    }

    if (!function_exists('rx_cron_task_priority_map')) {
        function rx_cron_task_priority_map(): array
        {
            static $map = null;
            if (is_array($map)) {
                return $map;
            }
            $map = [];
            foreach ([
                'croncard', 'plisio', 'nowpaymentcheck', 'blupalcheck', 'atlaspaycheck',
                'tronadocheck', 'tonpaycheck', 'rialitcheck', 'cryptocheck',
                'activeconfig', 'queued_renewal',
            ] as $jobKey) {
                $map[$jobKey] = 0;
            }
            foreach ([
                'sendmessage', 'notifications', 'gift', 'payment_expire', 'ip_block_notify',
                'disableconfig', 'public_log_drain',
            ] as $jobKey) {
                $map[$jobKey] = 1;
            }
            foreach ([
                'statusday', 'configtest', 'uptime_node', 'uptime_panel', 'on_hold', 'expireagent',
                'discount_expire', 'lottery', 'remnawave_usage', 'fx_rate_sync', 'xui_inbound_sync',
                'pin_expire',
            ] as $jobKey) {
                $map[$jobKey] = 2;
            }
            foreach (['backupbot', 'logs_cleanup', 'notification_expire'] as $jobKey) {
                $map[$jobKey] = 3;
            }
            return $map;
        }
    }

    if (!function_exists('rx_cron_task_priority')) {
        function rx_cron_task_priority(string $jobKey): int
        {
            $map = rx_cron_task_priority_map();
            return isset($map[$jobKey]) ? max(0, min(3, (int) $map[$jobKey])) : 2;
        }
    }

    if (!function_exists('rx_cron_task_priority_aging')) {
        function rx_cron_task_priority_aging(): int
        {
            return 300;
        }
    }

    if (!function_exists('rx_cron_task_priority_sql')) {
        function rx_cron_task_priority_sql(string $column = 'job_key'): string
        {
            $cases = '';
            foreach (rx_cron_task_priority_map() as $jobKey => $priority) {
                $literal = str_replace(['\\', "'"], ['\\\\', "''"], (string) $jobKey);
                $cases .= " WHEN '" . $literal . "' THEN " . (int) $priority;
            }
            return 'CASE ' . $column . $cases . ' ELSE 2 END';
        }
    }

    if (!function_exists('rx_cron_task_load_pending')) {
        function rx_cron_task_load_pending(PDO $pdo, int $limit, int $maxAttempts, array $deferredJobs = []): array
        {
            $limit = max(1, min(500, $limit));
            $deferredJobs = array_values(array_filter($deferredJobs, 'is_string'));
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $priority = rx_cron_task_priority_sql();
            $aging = max(60, rx_cron_task_priority_aging());
            $now = time();
            $rank = 'GREATEST(0, (' . $priority . ') - LEAST((' . $priority . '), FLOOR(GREATEST(0, ' . $now . ' - scheduled_for) / ' . $aging . ')))';
            $stmt = $pdo->prepare("SELECT task_key, job_key, script, worker, workers, token, attempts, scheduled_for, job_dispatched_at, next_attempt_at FROM cron_task_state WHERE state = 'pending' AND attempts < ? AND next_attempt_at <= ? AND scheduled_for BETWEEN ? AND ?" . $excluded . " ORDER BY " . $rank . " ASC, scheduled_for ASC, job_dispatched_at ASC, task_key ASC LIMIT " . $limit);
            $params = array_merge([max(1, $maxAttempts), $now, $now - rx_cron_catchup_seconds(), $now], $deferredJobs);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        }
    }

    if (!function_exists('rx_cron_task_pending_count')) {
        function rx_cron_task_pending_count(PDO $pdo, array $deferredJobs = [], int $now = 0): int
        {
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $due = $now > 0 ? ' AND next_attempt_at <= ' . (int) $now : '';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_task_state WHERE state = 'pending'" . $due . $excluded);
            $stmt->execute(array_values($deferredJobs));
            return (int) $stmt->fetchColumn();
        }
    }

    if (!function_exists('rx_cron_task_state_name')) {
        function rx_cron_task_state_name(PDO $pdo, string $taskKey): ?string
        {
            try {
                $stmt = $pdo->prepare('SELECT state FROM cron_task_state WHERE task_key = ?');
                $stmt->execute([$taskKey]);
                $state = $stmt->fetchColumn();
                return is_string($state) ? $state : null;
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    if (!function_exists('rx_cron_task_active_count')) {
        function rx_cron_task_active_count(PDO $pdo, int $now): int
        {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_task_state WHERE state IN ('claimed','running')");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }
    }

    if (!function_exists('rx_cron_task_claim')) {
        function rx_cron_task_claim(PDO $pdo, string $taskKey, int $leaseSeconds, int $maxAttempts, int $now): ?array
        {
            $started = false;
            try {
                $pdo->beginTransaction();
                $started = true;
                $stmt = $pdo->prepare("SELECT task_key, job_key, script, worker, workers, scheduled_for, state, token, attempts, lease_expires FROM cron_task_state WHERE task_key = ? FOR UPDATE");
                $stmt->execute([$taskKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    $pdo->rollBack();
                    return null;
                }
                $state = (string) ($row['state'] ?? '');
                if ($state !== 'pending') {
                    $pdo->rollBack();
                    return null;
                }
                if (!rx_cron_catchup_allowed((int) ($row['scheduled_for'] ?? 0), $now)) {
                    $expire = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'failed', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = 'catchup_window_elapsed' WHERE task_key = ? AND state = 'pending'");
                    $expire->execute([$taskKey]);
                    $pdo->commit();
                    return null;
                }
                if ((int) ($row['attempts'] ?? 0) >= $maxAttempts) {
                    $fail = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', outcome = 'failed', lease_expires = 0, next_attempt_at = 0, last_error = ?, updated_at = NOW() WHERE task_key = ?");
                    $fail->execute(['attempt_limit', $taskKey]);
                    $pdo->commit();
                    return null;
                }
                $token = bin2hex(random_bytes(24));
                $update = $pdo->prepare("UPDATE cron_task_state SET state = 'claimed', outcome = '', token = ?, lease_expires = ?, attempts = attempts + 1, next_attempt_at = 0, job_dispatched_at = ?, updated_at = NOW() WHERE task_key = ?");
                $update->execute([$token, $now + $leaseSeconds, $now, $taskKey]);
                $pdo->commit();
                rx_cron_task_write_marker('claim', $taskKey, $token, '', '', $leaseSeconds, '', 0, (int) $row['scheduled_for']);
                $row['token'] = $token;
                $row['state'] = 'claimed';
                $row['lease_expires'] = $now + $leaseSeconds;
                $row['attempts'] = (int) ($row['attempts'] ?? 0) + 1;
                return $row;
            } catch (\Throwable $e) {
                if ($started) {
                    try {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    } catch (\Throwable $rollbackError) {
                    }
                }
                return null;
            }
        }
    }

    if (!function_exists('rx_cron_task_release')) {
        function rx_cron_task_release(PDO $pdo, string $taskKey, ?string $token, string $reason = ''): void
        {
            $live = @fopen(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($taskKey) . '.lock', 'c');
            if ($live === false || !@flock($live, LOCK_EX | LOCK_NB)) {
                if (is_resource($live)) {
                    @fclose($live);
                }
                return;
            }
            try {
                $resource = stripos($reason, 'unavailable') !== false || stripos($reason, 'resource') !== false;
                $confirmed = strpos($reason, 'dispatch_failed') === 0;
                $delay = $resource ? 60 : ($confirmed ? 30 : 120);
                $attemptsSql = $resource ? 'attempts = GREATEST(attempts - 1, 0), ' : '';
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'pending', outcome = 'deferred', token = NULL, lease_expires = 0, next_attempt_at = ?, " . $attemptsSql . "last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'claimed'");
                $stmt->execute([time() + $delay, substr($reason, 0, 250), $taskKey, (string) $token]);
                if ($stmt->rowCount() === 1) {
                    @unlink(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($taskKey . '|' . (string) $token) . '.json');
                }
            } catch (\Throwable $e) {
            } finally {
                @flock($live, LOCK_UN);
                @fclose($live);
            }
        }
    }

    if (!function_exists('rx_cron_task_extend_occurrence')) {
        function rx_cron_task_extend_occurrence(PDO $pdo, string $taskKey, string $token, int $occurrence): bool
        {
            if ($taskKey === '' || $token === '' || $occurrence <= 0) {
                return false;
            }
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET scheduled_for = GREATEST(scheduled_for, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running','uncertain') AND scheduled_for < ?");
                $stmt->execute([$occurrence, $taskKey, $token, $occurrence]);
                return $stmt->rowCount() === 1;
            } catch (\Throwable $e) {
                return false;
            }
        }
    }

    if (!function_exists('rx_cron_task_dispatch_uncertain')) {
        function rx_cron_task_dispatch_uncertain(PDO $pdo, string $taskKey, string $token, string $message = '', int $delaySeconds = 180): bool
        {
            if ($taskKey === '' || $token === '') {
                return false;
            }
            $delay = max(60, min(1800, $delaySeconds));
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'uncertain', outcome = 'uncertain', lease_expires = 0, next_attempt_at = ?, last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
                $stmt->execute([time() + $delay, substr($message === '' ? 'dispatch_uncertain' : $message, 0, 250), $taskKey, $token]);
                return $stmt->rowCount() === 1;
            } catch (\Throwable $e) {
                return false;
            }
        }
    }

    if (!function_exists('rx_cron_task_reconcile')) {
        function rx_cron_task_reconcile(PDO $pdo, int $maxAttempts, int $now): void
        {
            if (!rx_cron_task_scan_markers($pdo, 120, $now)) {
                throw new RuntimeException('Cron task completion markers could not be persisted');
            }
            $window = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'failed', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = 'catchup_window_elapsed', updated_at = NOW() WHERE state IN ('pending','claimed') AND scheduled_for < ?");
            $window->execute([$now - rx_cron_catchup_seconds()]);
            $expired = $pdo->prepare("SELECT task_key, token FROM cron_task_state WHERE state IN ('claimed','running') AND lease_expires < ?");
            $expired->execute([$now]);
            foreach ($expired->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string) $row['task_key'];
                $token = (string) $row['token'];
                $live = @fopen(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($key) . '.lock', 'c');
                if ($live === false) {
                    continue;
                }
                if (!@flock($live, LOCK_EX | LOCK_NB)) {
                    @fclose($live);
                    $renew = $pdo->prepare("UPDATE cron_task_state SET lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
                    $renew->execute([$now + 120, $key, $token]);
                    continue;
                }
                try {
                    if (!rx_cron_task_scan_markers($pdo, 120, $now)) {
                        throw new RuntimeException('Cron task completion markers could not be persisted');
                    }
                    $expire = $pdo->prepare("UPDATE cron_task_state SET last_error = IF(state = 'claimed', 'dispatch_lease_expired', 'execution_lease_expired'), next_attempt_at = IF(state = 'claimed', ?, ?), state = 'uncertain', outcome = 'uncertain', lease_expires = 0, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running') AND lease_expires < ?");
                    $expire->execute([$now + 300, $now + 1800, $key, $token, $now]);
                    if ($expire->rowCount() === 1) {
                        @unlink(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($key . '|' . $token) . '.json');
                    }
                } finally {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                }
            }
            $uncertain = $pdo->prepare("SELECT task_key, token, attempts FROM cron_task_state WHERE state = 'uncertain' AND next_attempt_at > 0 AND next_attempt_at <= ?");
            $uncertain->execute([$now]);
            foreach ($uncertain->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string) $row['task_key'];
                $token = (string) $row['token'];
                if ($key === '' || $token === '') {
                    continue;
                }
                $live = @fopen(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($key) . '.lock', 'c');
                if ($live === false) {
                    continue;
                }
                if (!@flock($live, LOCK_EX | LOCK_NB)) {
                    @fclose($live);
                    continue;
                }
                try {
                    if ((int) ($row['attempts'] ?? 0) >= max(1, $maxAttempts)) {
                        $fail = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', outcome = 'failed', lease_expires = 0, next_attempt_at = 0, last_error = 'attempt_limit', updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'uncertain'");
                        $fail->execute([$key, $token]);
                    } else {
                        $requeue = $pdo->prepare("UPDATE cron_task_state SET state = 'pending', outcome = 'retryable', token = NULL, lease_expires = 0, next_attempt_at = ?, last_error = 'dispatch_reconciled', updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'uncertain'");
                        $requeue->execute([$now + 30, $key, $token]);
                    }
                    @unlink(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($key . '|' . $token) . '.json');
                } finally {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                }
            }
            $stuck = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', outcome = 'failed', lease_expires = 0, next_attempt_at = 0, last_error = 'attempt_limit', updated_at = NOW() WHERE state = 'pending' AND attempts >= ?");
            $stuck->execute([max(1, $maxAttempts)]);
        }
    }

    if (!function_exists('rx_cron_task_prune')) {
        function rx_cron_task_prune(PDO $pdo, int $now): void
        {
            try {
                $stmt = $pdo->prepare("DELETE FROM cron_task_state WHERE state IN ('completed','failed','cancelled') AND updated_at < FROM_UNIXTIME(?) LIMIT 500");
                $stmt->execute([$now - 604800]);

            } catch (\Throwable $e) {
            }
        }
    }

    if (!function_exists('rx_cron_task_job_dispatched')) {
        function rx_cron_task_job_dispatched(PDO $pdo, string $jobKey, int $now): void
        {
            $stmt = $pdo->prepare("UPDATE cron_task_state SET job_dispatched_at = ? WHERE job_key = ? AND state IN ('pending','claimed','running')");
            $stmt->execute([$now, $jobKey]);
        }
    }

    if (!function_exists('rx_cron_task_run_pending')) {
        function rx_cron_task_run_pending(PDO $pdo, array $options, callable $dispatch, ?callable $validate = null, ?callable $onDispatch = null): array
        {
            $concurrency = max(1, min(8, (int) ($options['concurrency'] ?? 1)));
            $maxAttempts = max(1, (int) ($options['max_attempts'] ?? 3));
            $leaseSeconds = max(60, (int) ($options['lease_seconds'] ?? 120));
            $deadline = (float) ($options['deadline'] ?? (microtime(true) + 20));
            $reservedPaymentSlots = max(0, min(4, (int) ($options['reserved_payment_slots'] ?? 1)));
            $uncertainDelay = max(60, min(1800, (int) ($options['uncertain_delay'] ?? 180)));
            $deferredJobs = array_values(array_filter((array) ($options['deferred_jobs'] ?? []), 'is_string'));
            $stats = ['dispatched' => 0, 'failed' => 0, 'active' => 0, 'pending' => 0, 'cancelled' => 0, 'deferred' => 0, 'uncertain' => 0];
            $skip = [];
            $capacityLock = 'fx_cron_capacity';
            try {
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
                if (is_string($dbName) && $dbName !== '') {
                    $capacityLock .= ':' . substr(sha1($dbName), 0, 16);
                }
            } catch (\Throwable $e) {
            }

            $now = time();
            try {
                rx_cron_task_reconcile($pdo, $maxAttempts, $now);
            } catch (\Throwable $e) {
            }

            $lockState = null;
            try {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $stmt->execute([$capacityLock]);
                $lockState = ((int) $stmt->fetchColumn() === 1);
            } catch (\Throwable $e) {
                $lockState = null;
            }
            if ($lockState !== true) {
                try {
                    $stats['active'] = rx_cron_task_active_count($pdo, $now);
                    $stats['pending'] = rx_cron_task_pending_count($pdo, $deferredJobs, $now);
                } catch (\Throwable $e) {
                }
                return $stats;
            }

            $claims = [];
            try {
                try {
                    $active = rx_cron_task_active_count($pdo, $now);
                } catch (\Throwable $e) {
                    $active = $concurrency;
                }
                $free = max(0, $concurrency - $active);
                $paymentClaimed = 0;
                if ($free > 0) {
                    $pending = [];
                    try {
                        $pending = rx_cron_task_load_pending($pdo, 300, $maxAttempts, $deferredJobs);
                    } catch (\Throwable $e) {
                        $pending = [];
                    }
                    $paymentDue = 0;
                    foreach ($pending as $pendingRow) {
                        if (rx_cron_task_is_payment_job((string) ($pendingRow['job_key'] ?? ''))) {
                            $paymentDue++;
                        }
                    }
                    $paymentReserve = min($reservedPaymentSlots, $paymentDue, max(0, $free - 1));
                    foreach ($pending as $row) {
                        if ($free <= 0 || microtime(true) >= $deadline) {
                            break;
                        }
                        $taskKey = (string) ($row['task_key'] ?? '');
                        if ($taskKey === '' || isset($skip[$taskKey])) {
                            continue;
                        }
                        $isPayment = rx_cron_task_is_payment_job((string) ($row['job_key'] ?? ''));
                        if (!$isPayment) {
                            $remainingReserve = max(0, $paymentReserve - $paymentClaimed);
                            if ($remainingReserve > 0 && $free <= $remainingReserve && $paymentClaimed < $paymentDue) {
                                continue;
                            }
                        }
                        if (is_callable($validate)) {
                            try {
                                $isValid = (bool) $validate($row);
                            } catch (\Throwable $e) {
                                $isValid = false;
                            }
                            if (!$isValid) {
                                try {
                                    rx_cron_task_cancel_pending($pdo, $taskKey, 'obsolete');
                                    $stats['cancelled']++;
                                } catch (\Throwable $e) {
                                }
                                if ($isPayment) {
                                    $paymentDue = max(0, $paymentDue - 1);
                                }
                                $skip[$taskKey] = true;
                                continue;
                            }
                        }
                        $claim = null;
                        try {
                            $claim = rx_cron_task_claim($pdo, $taskKey, $leaseSeconds, $maxAttempts, $now);
                        } catch (\Throwable $e) {
                            $claim = null;
                        }
                        if (!is_array($claim)) {
                            if ($isPayment) {
                                $paymentDue = max(0, $paymentDue - 1);
                            }
                            continue;
                        }
                        $claims[] = $claim;
                        if ($isPayment) {
                            $paymentClaimed++;
                        }
                        $free--;
                    }
                }
            } finally {
                try {
                    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$capacityLock]);
                } catch (\Throwable $e) {
                }
            }

            foreach ($claims as $claim) {
                $taskKey = (string) ($claim['task_key'] ?? '');
                $token = (string) ($claim['token'] ?? '');
                $result = 'uncertain';
                try {
                    $result = (string) $dispatch($claim);
                } catch (\Throwable $e) {
                    $result = 'uncertain';
                }
                if ($result === 'accepted') {
                    $stats['dispatched']++;
                    try {
                        rx_cron_task_job_dispatched($pdo, (string) $claim['job_key'], time());
                    } catch (\Throwable $e) {
                    }
                    if (is_callable($onDispatch)) {
                        try {
                            $onDispatch($claim);
                        } catch (\Throwable $e) {
                        }
                    }
                    continue;
                }
                $skip[$taskKey] = true;
                if ($result === 'failed') {
                    try {
                        rx_cron_task_release($pdo, $taskKey, $token, 'dispatch_failed');
                    } catch (\Throwable $e) {
                    }
                    $stats['failed']++;
                } elseif ($result === 'unavailable') {
                    try {
                        rx_cron_task_release($pdo, $taskKey, $token, 'transport_unavailable');
                    } catch (\Throwable $e) {
                    }
                    $stats['deferred']++;
                } else {
                    try {
                        rx_cron_task_dispatch_uncertain($pdo, $taskKey, $token, 'dispatch_uncertain', $uncertainDelay);
                    } catch (\Throwable $e) {
                    }
                    $stats['uncertain']++;
                }
            }

            try {
                $stats['active'] = rx_cron_task_active_count($pdo, time());
            } catch (\Throwable $e) {
                $stats['active'] = 0;
            }
            try {
                $stats['pending'] = rx_cron_task_pending_count($pdo, $deferredJobs, time());
            } catch (\Throwable $e) {
                $stats['pending'] = 0;
            }
            return $stats;
        }
    }
}
