<?php

namespace Faoxima\Cron\Runtime;

use PDO;
use RuntimeException;

final class TaskQueue
{
    public static function rx_cron_task_enqueue(PDO $pdo, string $jobKey, string $script, int $workers, int $worker, int $scheduledFor, bool $preserveOccurrence = false): int
        {
            $taskKey = \Faoxima\Cron\Runtime\TaskState::rx_cron_task_key($jobKey, $workers, $worker);
            if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_allowed($scheduledFor)) {
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
                if ($state === 'pending' && !\Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_allowed($existingFor, $now)) {
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

    public static function rx_cron_task_cancel_pending(PDO $pdo, string $taskKey, string $reason = ''): void
        {
            $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'cancelled', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = ?, updated_at = NOW() WHERE task_key = ? AND state = 'pending'");
            $stmt->execute([substr($reason, 0, 250), $taskKey]);
        }

    public static function rx_cron_task_cancel_obsolete(PDO $pdo, array $validTasks): void
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
                    self::rx_cron_task_cancel_pending($pdo, $key, 'obsolete');
                }
            }
        }

    public static function rx_cron_task_is_payment_job(string $jobKey): bool
        {
            return in_array($jobKey, [
                'croncard', 'plisio', 'nowpaymentcheck', 'blupalcheck', 'atlaspaycheck',
                'tronadocheck', 'tonpaycheck', 'rialitcheck', 'cryptocheck',
            ], true);
        }

    public static function rx_cron_task_priority_map(): array
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

    public static function rx_cron_task_priority(string $jobKey): int
        {
            $map = self::rx_cron_task_priority_map();
            return isset($map[$jobKey]) ? max(0, min(3, (int) $map[$jobKey])) : 2;
        }

    public static function rx_cron_task_priority_aging(): int
        {
            return 300;
        }

    public static function rx_cron_task_priority_sql(string $column = 'job_key'): string
        {
            $cases = '';
            foreach (self::rx_cron_task_priority_map() as $jobKey => $priority) {
                $literal = str_replace(['\\', "'"], ['\\\\', "''"], (string) $jobKey);
                $cases .= " WHEN '" . $literal . "' THEN " . (int) $priority;
            }
            return 'CASE ' . $column . $cases . ' ELSE 2 END';
        }

    public static function rx_cron_task_load_pending(PDO $pdo, int $limit, int $maxAttempts, array $deferredJobs = []): array
        {
            $limit = max(1, min(500, $limit));
            $deferredJobs = array_values(array_filter($deferredJobs, 'is_string'));
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $priority = self::rx_cron_task_priority_sql();
            $aging = max(60, self::rx_cron_task_priority_aging());
            $now = time();
            $rank = 'GREATEST(0, (' . $priority . ') - LEAST((' . $priority . '), FLOOR(GREATEST(0, ' . $now . ' - scheduled_for) / ' . $aging . ')))';
            $stmt = $pdo->prepare("SELECT task_key, job_key, script, worker, workers, token, attempts, scheduled_for, job_dispatched_at, next_attempt_at FROM cron_task_state WHERE state = 'pending' AND attempts < ? AND next_attempt_at <= ? AND scheduled_for BETWEEN ? AND ?" . $excluded . " ORDER BY " . $rank . " ASC, scheduled_for ASC, job_dispatched_at ASC, task_key ASC LIMIT " . $limit);
            $params = array_merge([max(1, $maxAttempts), $now, $now - \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds(), $now], $deferredJobs);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        }

    public static function rx_cron_task_pending_count(PDO $pdo, array $deferredJobs = [], int $now = 0): int
        {
            $excluded = $deferredJobs ? ' AND job_key NOT IN (' . implode(',', array_fill(0, count($deferredJobs), '?')) . ')' : '';
            $due = $now > 0 ? ' AND next_attempt_at <= ' . (int) $now : '';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_task_state WHERE state = 'pending'" . $due . $excluded);
            $stmt->execute(array_values($deferredJobs));
            return (int) $stmt->fetchColumn();
        }

    public static function rx_cron_task_has_started(PDO $pdo, string $taskKey, string $token): bool
        {
            $stmt = $pdo->prepare("SELECT 1 FROM cron_task_state WHERE task_key = ? AND token = ? AND (state IN ('running','completed') OR (state = 'failed' AND COALESCE(last_error, '') <> 'attempt_limit') OR (state = 'uncertain' AND last_error = 'execution_lease_expired'))");
            $stmt->execute([$taskKey, $token]);
            return (bool) $stmt->fetchColumn();
        }

    public static function rx_cron_task_active_count(PDO $pdo, int $now): int
        {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_task_state WHERE state IN ('claimed','running')");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }

    public static function rx_cron_task_claim(PDO $pdo, string $taskKey, int $leaseSeconds, int $maxAttempts, int $now): ?array
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
                if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_allowed((int) ($row['scheduled_for'] ?? 0), $now)) {
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
                \Faoxima\Cron\Runtime\TaskState::rx_cron_task_write_marker('claim', $taskKey, $token, '', '', $leaseSeconds, '', 0, (int) $row['scheduled_for']);
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

    public static function rx_cron_task_release(PDO $pdo, string $taskKey, ?string $token, string $reason = ''): void
        {
            $live = @fopen(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($taskKey) . '.lock', 'c');
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
                    @unlink(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($taskKey . '|' . (string) $token) . '.json');
                }
            } catch (\Throwable $e) {
                error_log('[cron:task] claim release failed: ' . get_class($e));
            } finally {
                @flock($live, LOCK_UN);
                @fclose($live);
            }
        }

    public static function rx_cron_task_extend_occurrence(PDO $pdo, string $taskKey, string $token, int $occurrence): bool
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

    public static function rx_cron_task_dispatch_uncertain(PDO $pdo, string $taskKey, string $token, string $message = '', int $delaySeconds = 180): bool
        {
            if ($taskKey === '' || $token === '') {
                return false;
            }
            $delay = max(60, min(1800, $delaySeconds));
            try {
                $stmt = $pdo->prepare("UPDATE cron_task_state SET state = 'uncertain', outcome = 'uncertain', lease_expires = 0, next_attempt_at = ?, last_error = ?, updated_at = NOW() WHERE task_key = ? AND token = ? AND state = 'claimed'");
                $stmt->execute([time() + $delay, substr($message === '' ? 'dispatch_uncertain' : $message, 0, 250), $taskKey, $token]);
                return $stmt->rowCount() === 1;
            } catch (\Throwable $e) {
                error_log('[cron:task] uncertain dispatch persistence failed: ' . get_class($e));
                return false;
            }
        }

    public static function rx_cron_task_reconcile(PDO $pdo, int $maxAttempts, int $now): void
        {
            if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_task_scan_markers($pdo, 120, $now)) {
                throw new RuntimeException('Cron task completion markers could not be persisted');
            }
            $window = $pdo->prepare("UPDATE cron_task_state SET state = 'cancelled', outcome = 'failed', token = NULL, lease_expires = 0, next_attempt_at = 0, last_error = 'catchup_window_elapsed', updated_at = NOW() WHERE state IN ('pending','claimed') AND scheduled_for < ?");
            $window->execute([$now - \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds()]);
            $expired = $pdo->prepare("SELECT task_key, token FROM cron_task_state WHERE state IN ('claimed','running') AND lease_expires < ?");
            $expired->execute([$now]);
            foreach ($expired->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string) $row['task_key'];
                $token = (string) $row['token'];
                $live = @fopen(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($key) . '.lock', 'c');
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
                    if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_task_scan_markers($pdo, 120, $now)) {
                        throw new RuntimeException('Cron task completion markers could not be persisted');
                    }
                    $expire = $pdo->prepare("UPDATE cron_task_state SET last_error = IF(state = 'claimed', 'dispatch_lease_expired', 'execution_lease_expired'), next_attempt_at = IF(state = 'claimed', ?, ?), state = 'uncertain', outcome = 'uncertain', lease_expires = 0, updated_at = NOW() WHERE task_key = ? AND token = ? AND state IN ('claimed','running') AND lease_expires < ?");
                    $expire->execute([$now + 300, $now + 1800, $key, $token, $now]);
                    if ($expire->rowCount() === 1) {
                        @unlink(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($key . '|' . $token) . '.json');
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
                $live = @fopen(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'live_' . sha1($key) . '.lock', 'c');
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
                    @unlink(\Faoxima\Cron\Runtime\TaskState::rx_cron_task_runtime_dir() . DIRECTORY_SEPARATOR . 'claim_' . sha1($key . '|' . $token) . '.json');
                } finally {
                    @flock($live, LOCK_UN);
                    @fclose($live);
                }
            }
            $stuck = $pdo->prepare("UPDATE cron_task_state SET state = 'failed', outcome = 'failed', lease_expires = 0, next_attempt_at = 0, last_error = 'attempt_limit', updated_at = NOW() WHERE state = 'pending' AND attempts >= ?");
            $stuck->execute([max(1, $maxAttempts)]);
        }

    public static function rx_cron_task_prune(PDO $pdo, int $now): void
        {
            try {
                $stmt = $pdo->prepare("DELETE FROM cron_task_state WHERE state IN ('completed','failed','cancelled') AND updated_at < FROM_UNIXTIME(?) LIMIT 500");
                $stmt->execute([$now - 604800]);

            } catch (\Throwable $e) {
            }
        }

    public static function rx_cron_task_job_dispatched(PDO $pdo, string $jobKey, int $now): void
        {
            $stmt = $pdo->prepare("UPDATE cron_task_state SET job_dispatched_at = ? WHERE job_key = ? AND state IN ('pending','claimed','running')");
            $stmt->execute([$now, $jobKey]);
        }

    public static function rx_cron_task_select_claims(PDO $pdo, array $limits, int $now, array $deferredJobs, ?callable $validate): array
        {
            [$concurrency, $maxAttempts, $leaseSeconds, $deadline, $reservedPaymentSlots] = $limits;
            $claims = [];
            $skip = [];
            $cancelled = 0;
            try {
                $active = self::rx_cron_task_active_count($pdo, $now);
            } catch (\Throwable $e) {
                $active = $concurrency;
            }
            $free = max(0, $concurrency - $active);
            $paymentClaimed = 0;
            if ($free > 0) {
                $pending = [];
                try {
                    $pending = self::rx_cron_task_load_pending($pdo, 300, $maxAttempts, $deferredJobs);
                } catch (\Throwable $e) {
                    $pending = [];
                }
                $paymentDue = 0;
                foreach ($pending as $pendingRow) {
                    if (self::rx_cron_task_is_payment_job((string) ($pendingRow['job_key'] ?? ''))) {
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
                    $isPayment = self::rx_cron_task_is_payment_job((string) ($row['job_key'] ?? ''));
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
                                self::rx_cron_task_cancel_pending($pdo, $taskKey, 'obsolete');
                                $cancelled++;
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
                        $claim = self::rx_cron_task_claim($pdo, $taskKey, $leaseSeconds, $maxAttempts, $now);
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
            return [$claims, $cancelled];
        }

    public static function rx_cron_task_dispatch_claim(PDO $pdo, array $claim, callable $dispatch, ?callable $onDispatch, int $uncertainDelay): string
        {
            $taskKey = (string) ($claim['task_key'] ?? '');
            $token = (string) ($claim['token'] ?? '');
            $result = 'uncertain';
            try {
                $result = (string) $dispatch($claim);
            } catch (\Throwable $e) {
                $result = 'uncertain';
            }
            if ($result === 'accepted') {
                try {
                    self::rx_cron_task_job_dispatched($pdo, (string) $claim['job_key'], time());
                } catch (\Throwable $e) {
                }
                if (is_callable($onDispatch)) {
                    try {
                        $onDispatch($claim);
                    } catch (\Throwable $e) {
                    }
                }
                return 'dispatched';
            }
            if ($result === 'failed') {
                try {
                    self::rx_cron_task_release($pdo, $taskKey, $token, 'dispatch_failed');
                } catch (\Throwable $e) {
                }
                return 'failed';
            } elseif ($result === 'unavailable') {
                try {
                    self::rx_cron_task_release($pdo, $taskKey, $token, 'transport_unavailable');
                } catch (\Throwable $e) {
                }
                return 'deferred';
            } else {
                try {
                    self::rx_cron_task_dispatch_uncertain($pdo, $taskKey, $token, 'dispatch_uncertain', $uncertainDelay);
                } catch (\Throwable $e) {
                }
                return 'uncertain';
            }
        }

    public static function rx_cron_task_run_pending(PDO $pdo, array $options, callable $dispatch, ?callable $validate = null, ?callable $onDispatch = null): array
        {
            $concurrency = max(1, min(8, (int) ($options['concurrency'] ?? 1)));
            $maxAttempts = max(1, (int) ($options['max_attempts'] ?? 3));
            $leaseSeconds = max(60, (int) ($options['lease_seconds'] ?? 120));
            $deadline = (float) ($options['deadline'] ?? (microtime(true) + 20));
            $reservedPaymentSlots = max(0, min(4, (int) ($options['reserved_payment_slots'] ?? 1)));
            $uncertainDelay = max(60, min(1800, (int) ($options['uncertain_delay'] ?? 180)));
            $deferredJobs = array_values(array_filter((array) ($options['deferred_jobs'] ?? []), 'is_string'));
            $stats = ['dispatched' => 0, 'failed' => 0, 'active' => 0, 'pending' => 0, 'cancelled' => 0, 'deferred' => 0, 'uncertain' => 0];
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
                self::rx_cron_task_reconcile($pdo, $maxAttempts, $now);
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
                    $stats['active'] = self::rx_cron_task_active_count($pdo, $now);
                    $stats['pending'] = self::rx_cron_task_pending_count($pdo, $deferredJobs, $now);
                } catch (\Throwable $e) {
                }
                return $stats;
            }

            $claims = [];
            try {
                [$claims, $stats['cancelled']] = self::rx_cron_task_select_claims(
                    $pdo, [$concurrency, $maxAttempts, $leaseSeconds, $deadline, $reservedPaymentSlots],
                    $now, $deferredJobs, $validate
                );
            } finally {
                try {
                    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$capacityLock]);
                } catch (\Throwable $e) {
                }
            }

            foreach ($claims as $claim) {
                $outcome = self::rx_cron_task_dispatch_claim($pdo, $claim, $dispatch, $onDispatch, $uncertainDelay);
                $stats[$outcome]++;
            }

            try {
                $stats['active'] = self::rx_cron_task_active_count($pdo, time());
            } catch (\Throwable $e) {
                $stats['active'] = 0;
            }
            try {
                $stats['pending'] = self::rx_cron_task_pending_count($pdo, $deferredJobs, time());
            } catch (\Throwable $e) {
                $stats['pending'] = 0;
            }
            return $stats;
        }
}
