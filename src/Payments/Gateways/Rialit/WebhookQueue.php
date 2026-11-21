<?php

namespace Faoxima\Payments\Gateways\Rialit;

use PDO;
use Throwable;

final class WebhookQueue
{
    public static function webhookTableDdl(): string
    {
        return "CREATE TABLE IF NOT EXISTS rialit_webhook_event (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(64) NOT NULL,
            event_type VARCHAR(40) NOT NULL DEFAULT '',
            token VARCHAR(64) NULL,
            order_id VARCHAR(64) NULL,
            payload MEDIUMTEXT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            next_attempt_at BIGINT NOT NULL DEFAULT 0,
            last_error VARCHAR(255) NULL,
            received_at BIGINT NOT NULL DEFAULT 0,
            processed_at BIGINT NULL,
            UNIQUE KEY uniq_rialit_event (event_id),
            KEY idx_rialit_event_state (state, next_attempt_at),
            KEY idx_rialit_event_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public static function storeWebhookEvent($runtimePdo, $eventId, $eventType, $token, $orderId, $payload): array
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitEnsureWebhookSchema($pdo)) {
            return ['ok' => false, 'duplicate' => false, 'message' => 'schema_unavailable'];
        }
        $eventId = trim((string) $eventId);
        if ($eventId === '' || !preg_match('/^[A-Za-z0-9\-_.:]{1,64}$/', $eventId)) {
            return ['ok' => false, 'duplicate' => false, 'message' => 'invalid_event_id'];
        }
        $now = time();
        try {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO rialit_webhook_event (event_id, event_type, token, order_id, payload, state, attempts, next_attempt_at, received_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $stmt->execute([
                $eventId,
                mb_substr((string) $eventType, 0, 40),
                $token !== '' ? mb_substr((string) $token, 0, 64) : null,
                $orderId !== '' ? mb_substr((string) $orderId, 0, 64) : null,
                (string) $payload,
                'pending',
                $now,
                $now,
            ]);
            return ['ok' => true, 'duplicate' => $stmt->rowCount() === 0, 'message' => ''];
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_WEBHOOK_STORE_FAILED', $e->getMessage(), ['event_id' => $eventId]);
            return ['ok' => false, 'duplicate' => false, 'message' => 'store_failed'];
        }
    }

    public static function webhookRetryDelay($attempts): int
    {
        $steps = [30, 120, 600, 1800, 7200, 21600];
        $index = max(0, min(count($steps) - 1, (int) $attempts));
        return $steps[$index];
    }

    public static function processWebhookEvents($runtimePdo, $runtimeSetting, $limit = 20): array
    {
        $pdo = $runtimePdo;
        $stats = ['processed' => 0, 'completed' => 0, 'retry' => 0, 'failed' => 0];
        if (!($pdo instanceof PDO) || !\Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitEnsureWebhookSchema($pdo)) {
            return $stats;
        }
        $rxPaymentWindow = defined('RX_CRON_INIT_LOADED') ? \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql() : '1=1';
        $limit = max(1, min(100, (int) $limit));
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM rialit_webhook_event
                  WHERE state IN ('pending','retry')
                    AND EXISTS (SELECT 1 FROM Payment_report WHERE Payment_report.id_order = rialit_webhook_event.order_id AND $rxPaymentWindow)
                    AND next_attempt_at <= ?
                  ORDER BY id ASC
                  LIMIT " . $limit
            );
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_WEBHOOK_SELECT_FAILED', $e->getMessage());
            return $stats;
        }
        foreach ((array) $rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $stats['processed']++;
            $eventId = (string) ($row['event_id'] ?? '');
            $orderId = trim((string) ($row['order_id'] ?? ''));
            $token = trim((string) ($row['token'] ?? ''));
            $attempts = (int) ($row['attempts'] ?? 0);
            if ($orderId === '' || $token === '') {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute(['missing_payment_reference', time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                continue;
            }
            $result = \Faoxima\Payments\Gateways\Rialit\Recovery::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'webhook', 'token' => $token]);
            $state = (string) ($result['state'] ?? 'error');
            if ((string) ($row['event_type'] ?? '') === 'payment.paid' && in_array($state, ['pending','expired','cancelled'], true)) {
                $state = 'retry';
            }
            if (in_array($state, ['paid', 'pending', 'expired', 'cancelled'], true)) {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'completed', processed_at = ?, last_error = NULL WHERE event_id = ?")
                        ->execute([time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['completed']++;
                continue;
            }
            $attempts++;
            if ($state === 'mismatch') {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', attempts = ?, last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute([$attempts, mb_substr($state, 0, 250), time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_WEBHOOK_EVENT_FAILED', 'Rialit webhook event could not be verified', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'state' => $state,
                ]);
                continue;
            }
            if ($attempts >= RIALIT_WEBHOOK_MAX_ATTEMPTS) {
                try {
                    $pdo->prepare("UPDATE rialit_webhook_event SET state = 'failed', attempts = ?, last_error = ?, processed_at = ? WHERE event_id = ?")
                        ->execute([$attempts, mb_substr($state, 0, 250), time(), $eventId]);
                } catch (Throwable $e) {
                }
                $stats['failed']++;
                \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_WEBHOOK_EVENT_EXHAUSTED', 'Rialit webhook event reached the retry limit', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                    'state' => $state,
                ]);
                continue;
            }
            try {
                $pdo->prepare("UPDATE rialit_webhook_event SET state = 'retry', attempts = ?, next_attempt_at = ?, last_error = ? WHERE event_id = ?")
                    ->execute([$attempts, time() + self::webhookRetryDelay($attempts), mb_substr($state, 0, 250), $eventId]);
            } catch (Throwable $e) {
            }
            $stats['retry']++;
        }
        return $stats;
    }

    public static function processPendingOrders($runtimePdo, $runtimeSetting, $limit = 20): array
    {
        $pdo = $runtimePdo;
        $stats = ['checked' => 0, 'paid' => 0, 'retry' => 0];
        if (!($pdo instanceof PDO)) {
            return $stats;
        }
        $rxPaymentWindow = defined('RX_CRON_INIT_LOADED') ? \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql() : '1=1';
        $limit = max(1, min(100, (int) $limit));
        $now = time();
        try {
            $stmt = $pdo->prepare(
                "SELECT id_order FROM Payment_report
                  WHERE $rxPaymentWindow AND Payment_Method = 'rialit'
                    AND (
                        (payment_Status IN ('Unpaid','pending','waiting')
                         AND rialit_created_at >= ?
                         AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?))
                        OR
                        (payment_Status IN ('expire','cancelled')
                         AND (rialit_local_state IS NULL OR rialit_local_state <> 'closed')
                         AND rialit_created_at >= ?
                         AND (rialit_last_checked_at IS NULL OR rialit_last_checked_at <= ?))
                    )
                  ORDER BY rialit_last_checked_at IS NOT NULL, rialit_last_checked_at ASC, id ASC
                  LIMIT " . $limit
            );
            $stmt->execute([
                $now - RIALIT_RECONCILE_SECONDS,
                $now - RIALIT_POLL_THROTTLE_SECONDS,
                $now - RIALIT_LATE_RECONCILE_SECONDS,
                $now - RIALIT_POLL_THROTTLE_SECONDS,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            \Faoxima\Payments\Gateways\Rialit\Client::logEvent('RIALIT_POLL_SELECT_FAILED', $e->getMessage());
            return $stats;
        }
        foreach ((array) $rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $orderId = (string) ($row['id_order'] ?? '');
            if ($orderId === '') {
                continue;
            }
            $stats['checked']++;
            \Faoxima\Payments\Gateways\Rialit\PaymentOrders::updateColumns($runtimePdo, $orderId, ['rialit_last_checked_at' => time()], false);
            $result = \Faoxima\Payments\Gateways\Rialit\Recovery::verifyPayment($runtimePdo, $runtimeSetting, $orderId, ['source' => 'poll']);
            $state = (string) ($result['state'] ?? '');
            if ($state === 'paid') {
                $stats['paid']++;
            } elseif (in_array($state, ['retry', 'busy', 'error'], true)) {
                $stats['retry']++;
            }
        }
        return $stats;
    }

    public static function recoverPaidOrders($runtimePdo, $limit = 10): int
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !method_exists(\Faoxima\Payments\Fulfillment\FulfillmentRuntime::class, 'rx_pf_recover')) {
            return 0;
        }
        $rxPaymentWindow = defined('RX_CRON_INIT_LOADED') ? \Faoxima\Payments\Fulfillment\PaymentPolicy::cronWindowSql() : '1=1';
        $limit = max(1, min(50, (int) $limit));
        try {
            $stmt = $pdo->query(
                "SELECT id_order FROM Payment_report
                  WHERE $rxPaymentWindow AND Payment_Method = 'rialit'
                    AND payment_Status = 'paid'
                    AND COALESCE(direct_payment_done, 0) = 0
                    AND NOT EXISTS (
                        SELECT 1 FROM payment_fulfillment pf
                         WHERE pf.id_order = Payment_report.id_order
                           AND pf.state IN ('completed','manual_review')
                    )
                  ORDER BY id ASC
                  LIMIT " . $limit
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (Throwable $e) {
            return 0;
        }
        $recovered = 0;
        foreach ((array) $rows as $orderId) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                break;
            }
            $result = \Faoxima\Payments\Fulfillment\Recovery::recover($runtimePdo, (string) $orderId);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'claimed' || $status === 'reconcile') {
                $token = (string) ($result['token'] ?? '');
                if ($token !== '' && is_callable([\Faoxima\Services\Fulfillment\Delivery::class, 'runClaimed'])) {
                    \Faoxima\Services\Fulfillment\Delivery::runClaimed($runtimePdo, (string) $orderId, $token, dirname(__DIR__, 4) . '/images.jpg', 'waiting');
                }
            }
            if (in_array($status, ['claimed', 'reconcile', 'finalized'], true)) {
                $recovered++;
            }
        }
        return $recovered;
    }

    public static function pruneWebhookEvents($runtimePdo): void
    {
        $pdo = $runtimePdo;
        if (!($pdo instanceof PDO) || !\Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitEnsureWebhookSchema($pdo)) {
            return;
        }
        try {
            $pdo->prepare("DELETE FROM rialit_webhook_event WHERE state IN ('completed','failed') AND processed_at IS NOT NULL AND processed_at < ? LIMIT 500")
                ->execute([time() - 604800]);
        } catch (Throwable $e) {
        }
    }
}
