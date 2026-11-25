<?php

namespace Faoxima\Payments\Fulfillment;

use Throwable;
use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;

final class PaymentPolicy
{
    public static function isReceiptPayment(array $payment): bool
    {
        return in_array((string) ($payment['Payment_Method'] ?? ''), ['cart to cart', 'arze digital offline'], true);
    }

    public static function cronPaymentAllowed(array $payment, ?int $now = null): bool
    {
        if (!defined('RX_CRON_INIT_LOADED')) {
            return true;
        }
        $now = $now ?? time();
        $raw = trim((string) ($payment['time'] ?? ''));
        if (preg_match('/^[0-9]{10}$/D', $raw)) {
            $created = (int) $raw;
        } else {
            $normalized = str_replace('/', '-', $raw);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, new DateTimeZone('Asia/Tehran'));
            if ($date === false || $date->format('Y-m-d H:i:s') !== $normalized) {
                return false;
            }
            $created = $date->getTimestamp();
        }
        if (self::isReceiptPayment($payment)
            && in_array((string) ($payment['payment_Status'] ?? ''), ['Unpaid', 'waiting', 'pending', 'processing', 'reconciling', 'paid'], true)) {
            return $created > 0 && $created <= $now;
        }
        if ((string) ($payment['Payment_Method'] ?? '') === 'tonpay'
            && (string) ($payment['tonpay_gateway_mode'] ?? '') === 'custom') {
            return $created > 0 && $created <= $now;
        }
        if ((string) ($payment['Payment_Method'] ?? '') === 'tetraminator' && defined('TETRAMINATOR_RECONCILE_SECONDS')) {
            $tetraminatorCreated = (int) ($payment['tetraminator_created_at'] ?? 0);
            if ($tetraminatorCreated <= 0) {
                $tetraminatorCreated = $created;
            }
            return $tetraminatorCreated > 0 && $tetraminatorCreated <= $now && $tetraminatorCreated >= $now - TETRAMINATOR_RECONCILE_SECONDS;
        }
        return $created > 0 && $created <= $now && $created >= $now - (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_catchup_seconds') ? \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds() : 3600);
    }

    public static function cronPaymentRecent(array $payment, ?int $now = null, ?int $seconds = null): bool
    {
        $now = $now ?? time();
        $span = $seconds !== null && $seconds > 0 ? $seconds : (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_catchup_seconds') ? \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds() : 3600);
        $raw = trim((string) ($payment['time'] ?? ''));
        if (preg_match('/^[0-9]{10}$/D', $raw)) {
            $created = (int) $raw;
        } else {
            $normalized = str_replace('/', '-', $raw);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, new DateTimeZone('Asia/Tehran'));
            if ($date === false || $date->format('Y-m-d H:i:s') !== $normalized) {
                return false;
            }
            $created = $date->getTimestamp();
        }
        return $created > 0 && $created <= $now && $created >= $now - $span;
    }

    public static function cronWindowSql(string $alias = 'Payment_report', ?int $seconds = null): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Invalid payment table alias');
        }
        $now = time();
        $span = $seconds !== null && $seconds > 0 ? $seconds : (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_catchup_seconds') ? \Faoxima\Cron\Runtime\TaskState::rx_cron_catchup_seconds() : 3600);
        $zone = new DateTimeZone('Asia/Tehran');
        $until = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d H:i:s');
        $since = (new DateTimeImmutable('@' . ($now - $span)))->setTimezone($zone)->format('Y-m-d H:i:s');
        $column = $alias . '.`time`';
        return "(CASE WHEN $column REGEXP '^[0-9]{10}$' THEN CAST($column AS UNSIGNED) BETWEEN " . ($now - $span) . " AND $now ELSE REPLACE($column, '/', '-') BETWEEN '$since' AND '$until' END)";
    }

    public static function cronReceiptWindowSql(string $alias = 'Payment_report'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Invalid payment table alias');
        }
        return '((' . self::cronWindowSql($alias) . ') OR ' . $alias . ".payment_Status IN ('Unpaid', 'waiting', 'pending', 'processing', 'reconciling', 'paid'))";
    }

    public static function log(string $event, string $message, array $ctx = []): void
    {
        if (function_exists('rx_log_event')) {
            try {
                rx_log_event($event, $message, $ctx);
                return;
            } catch (Throwable $e) {
            }
        }
        error_log('[' . $event . '] ' . $message . ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
