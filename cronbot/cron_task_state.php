<?php

if (!defined('RX_CRON_TASK_STATE_LOADED')) {
    define('RX_CRON_TASK_STATE_LOADED', true);

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
            $marker = dirname(rx_cron_task_runtime_dir()) . DIRECTORY_SEPARATOR . '.cron_task_schema_v1';
            if (!$force && is_file($marker) && (time() - (int) @filemtime($marker)) < 86400) {
                try {
                    $pdo->query('SELECT 1 FROM cron_task_state LIMIT 1');
                    $ready = true;
                    return true;
                } catch (\Throwable $e) {
                }
            }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS cron_task_state (
                    task_key VARCHAR(191) NOT NULL,
                    job_key VARCHAR(191) NOT NULL,
                    script VARCHAR(191) NOT NULL DEFAULT '',
                    worker INT NOT NULL DEFAULT 0,
                    workers INT NOT NULL DEFAULT 1,
                    scheduled_for BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    state VARCHAR(16) NOT NULL DEFAULT 'pending',
                    token VARCHAR(64) NULL DEFAULT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    lease_expires BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    job_dispatched_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    last_error VARCHAR(255) NULL DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (task_key),
                    KEY idx_state_lease (state, lease_expires),
                    KEY idx_state_job (state, job_dispatched_at),
                    KEY idx_state_updated (state, updated_at),
                    KEY idx_job (job_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                @file_put_contents($marker, (string) time(), LOCK_EX);
                $ready = true;
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }
    }

    if (!function_exists('rx_cron_task_write_marker')) {
        function rx_cron_task_write_marker(string $type, string $key, string $token, string $state = '', string $error = '', ?int $lease = null): void
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
                            $state = ((string) ($data['state'] ?? '')) === 'failed' ? 'failed' : 'completed';
                            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = ?, lease_expires = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
                            $stmt->execute([$state, substr((string) ($data['error'] ?? ''), 0, 250), $key, $token]);
                        } else {
                            $markerLease = max(60, $leaseSeconds, (int) ($data['lease'] ?? 0));
                            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'running', lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
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
            return ['key' => $key, 'token' => $token, 'job' => $jobKey];
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
                    $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'running', lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'claimed' AND lease_expires >= ?");
                    $stmt->execute([$now + $lease, $meta['key'], $meta['token'], $now]);
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
            ];
            register_shutdown_function(static function () use ($meta, $ticket, $live): void {
                $error = error_get_last();
                $fatal = is_array($error) && in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
                $state = $fatal ? 'failed' : 'completed';
                $message = $fatal ? (string) ($error['message'] ?? '') : '';
                $pdo = rx_cron_task_pdo();
                $done = false;
                if ($pdo instanceof PDO && rx_cron_task_ensure_table($pdo)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE cron_task_state SET state = ?, lease_expires = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
                        $stmt->execute([$state, substr($message, 0, 250), $meta['key'], $meta['token']]);
                        $done = true;
                    } catch (\Throwable $e) {
                        $done = false;
                    }
                }
                if (!$done) {
                    rx_cron_task_write_marker('done', $meta['key'], $meta['token'], $state, $message);
                }
                @unlink($ticket);
                $GLOBALS['rxCronTaskCurrent'] = null;
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
                $stmt = $pdo->prepare("UPDATE cron_task_state SET lease_expires = GREATEST(lease_expires, ?), updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running')");
                $stmt->execute([$now + (int) $current['lease'], $current['key'], $current['token']]);
            } catch (\Throwable $e) {
            }
        }
    }

    if (!function_exists('rx_cron_task_enqueue')) {
        function rx_cron_task_enqueue(PDO $pdo, string $jobKey, string $script, int $workers, int $worker, int $scheduledFor): void
        {
            $taskKey = rx_cron_task_key($jobKey, $workers, $worker);
            $delete = $pdo->prepare("DELETE FROM cron_task_state WHERE task_key = ? AND state IN ('completed','failed','cancelled') AND scheduled_for < ?");
            $delete->execute([$taskKey, $scheduledFor]);
            $insert = $pdo->prepare("INSERT IGNORE INTO cron_task_state (task_key, job_key, script, worker, workers, scheduled_for, state, attempts, lease_expires, job_dispatched_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, 0, 0)");
            $insert->execute([$taskKey, $jobKey, $script, $worker, $workers, $scheduledFor]);
            $update = $pdo->prepare("UPDATE cron_task_state SET attempts = IF(? > scheduled_for, 0, attempts), scheduled_for = GREATEST(scheduled_for, ?), script = ?, worker = ?, workers = ?, updated_at = NOW() WHERE task_key = ? AND state = 'pending'");
            $update->execute([$scheduledFor, $scheduledFor, $script, $worker, $workers, $taskKey]);
        }
    }

    if (!function_exists('rx_cron_task_cancel_pending')) {
        function rx_cron_task_cancel_pending(PDO $pdo, string $taskKey, string $reason = ''): void
        {
            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', token = NULL, lease_expires = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND state = 'pending'");
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

    if (!function_exists('rx_cron_task_load_pending')) {
        function rx_cron_task_load_pending(PDO $pdo, int $limit, int $maxAttempts, array $deferredJobs = []): array
        {
            $limit = max(1, min(500, $limit));
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $stmt = $pdo->prepare("SELECT task_key, job_key, script, worker, workers, token, attempts, scheduled_for, job_dispatched_at FROM cron_task_state WHERE state = 'pending' AND attempts < ?" . $excluded . " ORDER BY job_dispatched_at ASC, scheduled_for ASC, task_key ASC LIMIT " . $limit);
            $stmt->execute(array_merge([max(1, $maxAttempts)], array_values($deferredJobs)));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        }
    }

    if (!function_exists('rx_cron_task_pending_count')) {
        function rx_cron_task_pending_count(PDO $pdo, array $deferredJobs = []): int
        {
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_task_state WHERE state = 'pending'" . $excluded);
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
                if ((int) ($row['attempts'] ?? 0) >= $maxAttempts) {
                    $fail = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', lease_expires = 0, last_error = ?, updated_at = NOW() WHERE task_key = ?");
                    $fail->execute(['attempt_limit', $taskKey]);
                    $pdo->commit();
                    return null;
                }
                $token = trim((string) ($row['token'] ?? ''));
                if ($token === '') {
                    $token = bin2hex(random_bytes(24));
                }
                $update = $pdo->prepare("UPDATE cron_task_state SET state = 'claimed', token = ?, lease_expires = ?, attempts = attempts + 1, job_dispatched_at = ?, updated_at = NOW() WHERE task_key = ?");
                $update->execute([$token, $now + $leaseSeconds, $now, $taskKey]);
                $pdo->commit();
                rx_cron_task_write_marker('claim', $taskKey, $token, '', '', $leaseSeconds);
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
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'pending', token = NULL, lease_expires = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'claimed'");
                $stmt->execute([substr($reason, 0, 250), $taskKey, (string) $token]);
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

    if (!function_exists('rx_cron_task_reconcile')) {
        function rx_cron_task_reconcile(PDO $pdo, int $maxAttempts, int $now): void
        {
            if (!rx_cron_task_scan_markers($pdo, 120, $now)) {
                throw new RuntimeException('Cron task completion markers could not be persisted');
            }
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
                    $expire = $pdo->prepare("UPDATE cron_task_state SET state = IF(attempts < ?, 'pending', 'failed'), token = NULL, lease_expires = 0, last_error = 'lease_expired', updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running') AND lease_expires < ?");
                    $expire->execute([max(1, $maxAttempts), $key, $token, $now]);
                    if ($expire->rowCount() === 1) {
                        @unlink(rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($key . '|' . $token) . '.json');
                    }
                } finally {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                }
            }
            $stuck = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', lease_expires = 0, last_error = 'attempt_limit', updated_at = NOW() WHERE state = 'pending' AND attempts >= ?");
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
            $concurrency = max(1, (int) ($options['concurrency'] ?? 1));
            $maxAttempts = max(1, (int) ($options['max_attempts'] ?? 3));
            $leaseSeconds = max(60, (int) ($options['lease_seconds'] ?? 120));
            $deadline = (float) ($options['deadline'] ?? (microtime(true) + 20));
            $pollMicros = max(100000, (int) ($options['poll_micros'] ?? 400000));
            $deferredJobs = array_values(array_filter((array) ($options['deferred_jobs'] ?? []), 'is_string'));
            $stats = ['dispatched' => 0, 'failed' => 0, 'active' => 0, 'pending' => 0, 'cancelled' => 0];
            $skip = [];
            $lastReconcile = 0.0;
            $capacityLock = 'fx_cron_capacity';
            try {
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
                if (is_string($dbName) && $dbName !== '') {
                    $capacityLock .= ':' . substr(sha1($dbName), 0, 16);
                }
            } catch (\Throwable $e) {
            }

            while (true) {
                $now = time();
                if (microtime(true) - $lastReconcile >= 0.5) {
                    try {
                        rx_cron_task_reconcile($pdo, $maxAttempts, $now);
                    } catch (\Throwable $e) {
                    }
                    $lastReconcile = microtime(true);
                }
                $claims = [];
                $progress = false;
                $lockState = null;
                try {
                    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 2)');
                    $stmt->execute([$capacityLock]);
                    $lockState = ((int) $stmt->fetchColumn() === 1);
                } catch (\Throwable $e) {
                    $lockState = null;
                }
                if ($lockState !== true) {
                    if (microtime(true) >= $deadline) {
                        break;
                    }
                    usleep($pollMicros);
                    continue;
                }
                try {
                    $active = 0;
                    try {
                        $active = rx_cron_task_active_count($pdo, $now);
                    } catch (\Throwable $e) {
                        $active = $concurrency;
                    }
                    $free = max(0, $concurrency - $active);
                    if ($free > 0) {
                        $pending = [];
                        try {
                            $pending = rx_cron_task_load_pending($pdo, 200, $maxAttempts, $deferredJobs);
                        } catch (\Throwable $e) {
                            $pending = [];
                        }
                        foreach ($pending as $row) {
                            if ($free <= 0 || microtime(true) >= $deadline) {
                                break;
                            }
                            $taskKey = (string) ($row['task_key'] ?? '');
                            if ($taskKey === '' || isset($skip[$taskKey])) {
                                continue;
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
                                continue;
                            }
                            $claims[] = $claim;
                            $free--;
                            $progress = true;
                        }
                    }
                } finally {
                    if ($lockState === true) {
                        try {
                            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$capacityLock]);
                        } catch (\Throwable $e) {
                        }
                    }
                }
                foreach ($claims as $claim) {
                    $taskKey = (string) ($claim['task_key'] ?? '');
                    $result = 'unavailable';
                    try {
                        $result = (string) $dispatch($claim);
                    } catch (\Throwable $e) {
                        $result = 'unavailable';
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
                    } else {
                        try {
                            rx_cron_task_release($pdo, $taskKey, (string) ($claim['token'] ?? ''), $result === 'failed' ? 'dispatch_failed' : 'transport_unavailable');
                        } catch (\Throwable $e) {
                        }
                        $skip[$taskKey] = true;
                        if ($result === 'failed') {
                            $stats['failed']++;
                        }
                    }
                }
                $pendingLeft = 0;
                $eligiblePendingLeft = 0;
                try {
                    $pendingLeft = rx_cron_task_pending_count($pdo);
                    $eligiblePendingLeft = $deferredJobs ? rx_cron_task_pending_count($pdo, $deferredJobs) : $pendingLeft;
                } catch (\Throwable $e) {
                    $pendingLeft = 0;
                }
                try {
                    $stats['active'] = rx_cron_task_active_count($pdo, time());
                } catch (\Throwable $e) {
                    $stats['active'] = 0;
                }
                $stats['pending'] = $pendingLeft;
                if ($eligiblePendingLeft === 0) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    break;
                }
                if (!$progress) {
                    usleep($pollMicros);
                }
            }
            return $stats;
        }
    }
}
