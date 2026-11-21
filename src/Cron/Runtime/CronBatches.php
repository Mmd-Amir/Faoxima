<?php

namespace Faoxima\Cron\Runtime;

use PDO;

use ManagePanel;
use Throwable;

final class CronBatches
{
    public static function rx_cron_read_profile(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $defaults = [
            'profile'              => 'shared',
            'cron_db_budget'       => 6,
            'cron_time_budget'     => 22,
            'broadcast_workers'    => 1,
            'payment_workers'      => 1,
            'cron_max_concurrency' => 2,
            'batch_size'           => 30,
        ];
        $rxOptCfg = dirname((dirname(__DIR__, 3) . '/cronbot')) . DIRECTORY_SEPARATOR . 'optimization_config.php';
        if (@is_file($rxOptCfg)) {
            $rxOptData = @include $rxOptCfg;
            if (is_array($rxOptData) && !empty($rxOptData)) {
                $rxAllowed = ['cron_db_budget', 'cron_time_budget', 'broadcast_workers', 'payment_workers', 'cron_max_concurrency', 'batch_size'];
                $defaults  = array_merge($defaults, array_intersect_key($rxOptData, array_flip($rxAllowed)));
            }
        }
        $path = \Faoxima\Cron\Runtime\CronRuntime::rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'host_profile.json';
        if (@is_file($path)) {
            $raw     = @file_get_contents($path);
            $decoded = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                return $cache = array_merge($defaults, $decoded);
            }
        }
        return $cache = $defaults;
    }

    public static function rx_cron_batch_size(int $cap = 50): int
    {
        $profile = method_exists(\Faoxima\Cron\Runtime\CronBatches::class, 'rx_cron_read_profile') ? self::rx_cron_read_profile() : [];
        $size    = isset($profile['batch_size']) ? (int) $profile['batch_size'] : 30;
        $size    = max(1, min($cap, $size));
        list(, $workers) = method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_shard') ? \Faoxima\Cron\Runtime\CronRuntime::rx_cron_shard() : [0, 1];
        if ($workers > 1) {
            $size = max(1, (int) ceil($size / $workers));
        }
        return $size;
    }

    public static function rx_cron_bulk_cursor(PDO $pdo, string $job, ?int $invoiceId = null): int
    {
        $key = '_bulk_cursor_' . $job;
        if ($invoiceId !== null) {
            $invoiceId = max(0, $invoiceId);
            $stmt = $pdo->prepare("INSERT INTO cron_runtime_state (job_key, last_run, unit, value, enabled) VALUES (?, ?, 'disabled', 1, 0) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)");
            $stmt->execute([$key, $invoiceId]);
            return $invoiceId;
        }
        $stmt = $pdo->prepare('SELECT last_run FROM cron_runtime_state WHERE job_key = ?');
        $stmt->execute([$key]);
        return max(0, (int) $stmt->fetchColumn());
    }

    public static function rx_cron_write_invoice_cursor(string $job, string $invoiceId): bool
    {
        $file = \Faoxima\Cron\Runtime\CronRuntime::rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'invoice_cursor_' . hash('sha256', $job) . '.json';
        $encoded = json_encode($invoiceId, JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.tmp';
        if ($encoded === false || @file_put_contents($tmp, $encoded, LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            error_log('[cron] invoice cursor persist failed: ' . $job);
            return false;
        }
        return true;
    }

    public static function rx_cron_invoice_cursor(string $job, ?string $invoiceId = null): string
    {
        if ($invoiceId !== null) {
            self::rx_cron_write_invoice_cursor($job, $invoiceId);
            return $invoiceId;
        }
        $file = \Faoxima\Cron\Runtime\CronRuntime::rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'invoice_cursor_' . hash('sha256', $job) . '.json';
        if (!is_file($file)) return '';
        $raw = @file_get_contents($file, false, null, 0, 4096);
        if ($raw === false) return '';
        $cursor = json_decode($raw, true);
        return is_string($cursor) && strlen($cursor) <= 800 ? $cursor : '';
    }

    public static function rx_cron_bulk_invoices(PDO $pdo, string $job, string $condition, array $params, int $limit): array
    {
        $cursor = self::rx_cron_invoice_cursor($job);
        $stmt = $pdo->prepare('SELECT invoice.* FROM invoice WHERE (' . $condition . ') AND id_invoice > ? ORDER BY id_invoice LIMIT ' . max(1, min(50, $limit)));
        $stmt->execute(array_merge($params, [$cursor]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows && $cursor !== '') {
            $stmt->execute(array_merge($params, ['']));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $rows;
    }

    public static function rx_cron_payment_cursor_page(?PDO $pdo, string $job, callable $fetch): array
    {
        $after = self::rx_cron_invoice_cursor($job);
        $ceilingKey = 'payment_poll_ceiling_' . $job;
        $ceiling = null;
        if ($pdo !== null) {
            try {
                $ceiling = self::rx_cron_bulk_cursor($pdo, $ceilingKey);
                if ($ceiling === 0) {
                    $ceiling = self::rx_cron_bulk_cursor($pdo, $ceilingKey, (int) $pdo->query('SELECT MAX(id) FROM Payment_report')->fetchColumn());
                }
            } catch (Throwable $e) {
                error_log('[cron] payment cursor ceiling unavailable: ' . $job . ' ' . get_class($e));
                $ceiling = null;
            }
        }

        $rows = $fetch($after, $ceiling ?? '4294967295');
        if ($rows) {
            return $rows;
        }

        $previousCeiling = $ceiling;
        if ($pdo !== null && $ceiling !== null && ($after === '' || self::rx_cron_write_invoice_cursor($job, ''))) {
            try {
                $ceiling = (int) $pdo->query('SELECT MAX(id) FROM Payment_report')->fetchColumn();
                if ($ceiling !== $previousCeiling) {
                    self::rx_cron_bulk_cursor($pdo, $ceilingKey, $ceiling);
                }
            } catch (Throwable $e) {
                error_log('[cron] payment cursor ceiling refresh failed: ' . $job . ' ' . get_class($e));
                $ceiling = null;
            }
        }
        return $after !== '' || $ceiling !== $previousCeiling
            ? $fetch('', $ceiling ?? '4294967295')
            : [];
    }

    public static function rx_cron_load_payment_context(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $unavailable = [
            'setting'        => [],
            'paymentreports' => null,
            'datatextbot'    => [],
            'managePanel'    => null,
            'db_ready'       => false,
        ];

        $root = dirname((dirname(__DIR__, 3) . '/cronbot'));
        $required = [
            $root . '/config.php',
            $root . '/botapi.php',
            $root . '/panels.php',
            $root . '/function.php',
            $root . '/keyboard.php',
            $root . '/jdf.php',
        ];
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('payment_context', $required)) {
            return $cached = $unavailable;
        }
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        }


        $hasPdo = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO;
        $hasConn = isset($GLOBALS['connect']) && $GLOBALS['connect'] !== null;
        if (!$hasPdo && !$hasConn) {
            return $cached = $unavailable;
        }
        if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_tables_ready') && !\Faoxima\Cron\Runtime\CronRuntime::rx_cron_tables_ready('payment_context')) {
            return $cached = $unavailable;
        }

        $setting = \Faoxima\Core\Database\Db::select('setting', '*');

        $reportRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
        $paymentreports = is_array($reportRow) ? ($reportRow['idreport'] ?? null) : null;

        $datatextbotget = \Faoxima\Core\Database\Db::select('textbot', '*', null, null, 'fetchAll');
        $datatextbot = [
            'textafterpay'       => '',
            'textaftertext'      => '',
            'textmanual'         => '',
            'textselectlocation' => '',
            'text_wgdashboard'   => '',
        ];
        if (is_array($datatextbotget)) {
            foreach ($datatextbotget as $row) {
                $key = $row['id_text'] ?? '';
                if ($key !== '' && (array_key_exists($key, $datatextbot) || trim((string) ($row['text'] ?? '')) !== '')) {
                    $datatextbot[$key] = $row['text'] ?? '';
                }
            }
        }

        $cached = [
            'setting'        => $setting,
            'paymentreports' => $paymentreports,
            'datatextbot'    => $datatextbot,
            'managePanel'    => new ManagePanel(),
            'db_ready'       => true,
        ];
        return $cached;
    }

    public static function rx_cron_require_payment_context(): ?array
    {
        $context = self::rx_cron_load_payment_context();
        if (!empty($context['db_ready'])) {
            return $context;
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('payment_context_unavailable', 180);
        }
        return null;
    }

    public static function rx_cron_drain_notifications(array &$pending, int $limit, callable $deliver): void
    {
        $attempted = 0;
        foreach (array_keys($pending) as $key) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up() || $attempted >= $limit) {
                break;
            }
            if (($pending[$key]['state'] ?? 'uncertain') === 'pending' && (int) ($pending[$key]['next_attempt_at'] ?? 0) <= time()) {
                $attempted++;
                $deliver($key);
            }
        }
    }

    public static function rx_cron_deliver_pending_notification(array &$pending, $key, callable $persist, callable $send, bool $checkBudget, bool $recordSentTime): bool
    {
        $entry = $pending[$key] ?? null;
        if (is_array($entry) && ($entry['state'] ?? '') === 'sent') {
            return true;
        }
        if (!is_array($entry) || ($entry['state'] ?? 'uncertain') !== 'pending' || (int) ($entry['next_attempt_at'] ?? 0) > time() || ($checkBudget && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up())) {
            return false;
        }
        $persistState = static function (string $state) use ($persist): void {
            try {
                $persist();
            } catch (Throwable $e) {
                error_log('[cron:notification] could not persist ' . $state . ': ' . get_class($e));
                throw $e;
            }
        };
        if (!\Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_allowed((int) ($entry['created_at'] ?? 0))) {
            $pending[$key]['state'] = 'expired';
            $pending[$key]['next_attempt_at'] = 0;
            $pending[$key]['last_error'] = 'catchup_window_elapsed';
            $persistState('expired');
            return false;
        }
        $pending[$key]['state'] = 'sending';
        $pending[$key]['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
        $persistState('sending');
        try {
            $result = $send($entry);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'description' => $e->getMessage()];
        }
        if (is_array($result) && ($result['ok'] ?? false) === true) {
            $pending[$key]['state'] = 'sent';
            if ($recordSentTime) {
                $pending[$key]['sent_at'] = time();
            }
            $persistState('sent');
            return true;
        }
        $code = (int) ($result['error_code'] ?? 0);
        $pending[$key]['state'] = $code === 429 && $pending[$key]['attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 && $code !== 408 ? 'failed' : 'uncertain');
        $pending[$key]['next_attempt_at'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
        $pending[$key]['last_error'] = substr((string) ($result['description'] ?? 'no_response'), 0, 200);
        $persistState($pending[$key]['state']);
        return false;
    }

    public static function rx_cron_run_poll_batch(array $rows, callable $poll, callable $onError, callable $advance): void
    {
        foreach ($rows as $row) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_budget_exhausted()) {
                break;
            }
            try {
                $poll($row);
            } catch (Throwable $e) {
                $onError($row, $e);
            } finally {
                $advance($row);
            }
        }
    }

    public static function rx_cron_bulk_service_status(PDO $pdo, ManagePanel $managePanel, string $job, string $eligible, string $request, string $providerBefore, string $providerAfter, string $invoiceAfter): bool
    {
        $limit = self::rx_cron_batch_size(30);
        $finished = $pdo->prepare("SELECT id FROM user WHERE checkstatus = ? AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($eligible)) ORDER BY id LIMIT " . (int) $limit);
        $finished->execute([$request]);
        $finishedUsers = $finished->fetchAll(PDO::FETCH_COLUMN);
        $clear = $pdo->prepare("UPDATE user SET checkstatus = '0' WHERE id = ? AND checkstatus = ? AND NOT EXISTS (SELECT 1 FROM invoice WHERE id_user = user.id AND ($eligible))");
        foreach ($finishedUsers as $userId) {
            if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $clear->execute([$userId, $request]);
        }
        if (\Faoxima\Cron\Runtime\CronRuntime::rx_cron_budget_exhausted()) {
            return false;
        }

        $invoices = self::rx_cron_bulk_invoices($pdo, $job, $eligible . ' AND id_user IN (SELECT id FROM user WHERE checkstatus = ?)', [$request], $limit);
        self::rx_cron_run_poll_batch(
            $invoices,
            static function (array $invoice) use ($pdo, $managePanel, $request, $providerBefore, $providerAfter, $invoiceAfter): void {
                $requested = $pdo->prepare('SELECT checkstatus FROM user WHERE id = ?');
                $requested->execute([$invoice['id_user']]);
                if ((string) $requested->fetchColumn() !== $request) {
                    return;
                }
                if (self::rx_cron_service_target_reached($managePanel, $invoice, $providerBefore, $providerAfter)) {
                    \Faoxima\Core\Database\Db::update('invoice', 'Status', $invoiceAfter, 'id_invoice', $invoice['id_invoice']);
                }
            },
            static function (array $invoice, Throwable $e) use ($job): void {
                error_log('[cron:' . $job . '] invoice ' . $invoice['id_invoice'] . ': ' . $e->getMessage());
            },
            static function (array $invoice) use ($job): void {
                self::rx_cron_invoice_cursor($job, (string) $invoice['id_invoice']);
            }
        );
        return true;
    }

    public static function rx_cron_service_target_reached(ManagePanel $managePanel, array $invoice, string $before, string $after): bool
    {
        $current = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (($current['paused_by'] ?? '') === 'credit') {
            return false;
        }
        $status = is_array($current) ? (string) ($current['status'] ?? '') : '';
        if ($status === $after) {
            return true;
        }
        if ($status !== $before || \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
            return false;
        }
        $managePanel->Change_status($invoice['username'], $invoice['Service_location']);
        $verified = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        return is_array($verified) && ($verified['status'] ?? '') === $after;
    }
}
