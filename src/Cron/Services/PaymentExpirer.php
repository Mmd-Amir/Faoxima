<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Cache\RedisCache;
use Faoxima\Core\Database\Db;
use Faoxima\Core\Support\Formatting;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Orders\Checkout\Checkout;
use Faoxima\Payments\Receipts\Cancellation;
use Faoxima\Payments\Receipts\ReceiptActions;
use Faoxima\Payments\Receipts\ReceiptRuntime;
use Faoxima\Payments\Receipts\Settings;
use Faoxima\Payments\Settlement\SettlementRuntime;
use PDO;
use PDOStatement;
use Throwable;

final class PaymentExpirer
{
    private const BUTTON_TEXT_KEYS = [
        'carttocart', 'textnowpayment', 'textnowpaymenttron', 'iranpay2', 'iranpay3', 'tonpay', 'rialit', 'cubepay',
        'blupal', 'variza', 'abangateway', 'atlaspay', 'aqayepardakht', 'zarinpal', 'zarinpay', 'perfectmoney',
        'text_fq', 'textpaymentnotverify', 'textrequestagent', 'textpanelagent', 'text_wheel_luck',
        'text_star_telegram', 'textsnowpayment',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buttonTexts(): array
    {
        $texts = array_fill_keys(self::BUTTON_TEXT_KEYS, '');
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'textbot'");
        $stmt->execute();
        if (count($stmt->fetchAll()) > 0) {
            foreach (Db::select("textbot", "*", null, null, "fetchAll") as $row) {
                if (array_key_exists($row['id_text'], $texts) || (is_string($row['text']) && trim($row['text']) !== '')) {
                    $texts[$row['id_text']] = $row['text'];
                }
            }
        }
        return $texts;
    }

    public function run(): void
    {
        $rows = $this->selectExpirable();
        if ($rows === null) {
            return;
        }
        $expire = $this->pdo->prepare("UPDATE Payment_report SET payment_Status = 'expire' WHERE id_order = :o AND payment_Status = 'Unpaid'");

        if (is_callable([Checkout::class, 'expireStale'])) {
            Checkout::expireStale($this->pdo, true);
        }
        if (is_callable([Cancellation::class, 'recoverStaleReceiptUploads'])) {
            $recovery = Cancellation::recoverStaleReceiptUploads($this->pdo, 600, 50);
            if (($recovery['waiting'] + $recovery['unpaid'] + $recovery['failed']) > 0 && function_exists('rx_log_event')) {
                rx_log_event('RECEIPT_UPLOAD_RECOVERY', 'Recovered stale receipt-uploading card payments', $recovery);
            }
        }

        CronBatches::rx_cron_run_poll_batch(
            $rows,
            function (array $result) use ($expire): void {
                $this->expireOne($result, $expire);
            },
            static function (array $result, Throwable $e): void {
                error_log('[payment_expire] order ' . (string) $result['id_order'] . ': ' . $e->getMessage());
            },
            static function (array $result): void {
                $cursor = $result['Payment_Method'] === 'tonpay' ? 'payment_expire_tonpay' : 'payment_expire';
                CronBatches::rx_cron_invoice_cursor($cursor, (string) $result['id']);
            }
        );
        TaskState::rx_cron_task_complete('payment_expire_finished');
    }

    private function selectExpirable(): ?array
    {
        $pdo = $this->pdo;
        $cutoff = date('Y/m/d H:i:s', time() - 1800);
        try {
            $hasCardVerification = method_exists(ReceiptRuntime::class, 'rx_sbv_has_schema') && Settings::hasSchema($pdo);
            $sql = $hasCardVerification
                ? "SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','rialit') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) AND ((time < :cutoff AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)) OR EXISTS (SELECT 1 FROM card_sms_payment csp2 WHERE csp2.id_order = Payment_report.id_order AND csp2.state = 'open' AND csp2.deposit_id IS NULL AND csp2.expires_at + :grace <= :nowts)) AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120"
                : "SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND (crypto_currency IS NULL OR crypto_currency = '') AND Payment_Method NOT IN ('tonpay','rialit') AND (atlaspay_gateway_mode IS NULL OR atlaspay_gateway_mode <> 'custom') AND (payment_processing_version IS NULL OR payment_processing_version < 2 OR (COALESCE(atlaspay_local_state,'') <> 'manual_review' AND COALESCE(tronado_local_state,'') NOT IN ('manual_review','dispute_review'))) AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120";
            $stmt = $pdo->prepare($sql);
            $rows = CronBatches::rx_cron_payment_cursor_page($pdo, 'payment_expire', static function (string $after, $ceiling) use ($stmt, $cutoff, $hasCardVerification): array {
                $params = [':cutoff' => $cutoff, ':cursor' => $after, ':ceiling' => $ceiling];
                if ($hasCardVerification) {
                    $params[':grace'] = RX_SBV_EXPIRE_GRACE;
                    $params[':nowts'] = time();
                }
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            });
            return array_merge($rows, $this->selectTonpay());
        } catch (Throwable $e) {
            error_log('[payment_expire] select pending failed: ' . get_class($e));
            TaskState::rx_cron_task_defer('select_failed', 120);
            return null;
        }
    }

    private function selectTonpay(): array
    {
        $pdo = $this->pdo;
        $cutoff = date('Y/m/d H:i:s', time() - 86400);
        $stmt = null;
        return CronBatches::rx_cron_payment_cursor_page($pdo, 'payment_expire_tonpay', static function (string $after, $ceiling) use ($pdo, $cutoff, &$stmt): array {
            $params = [':cutoff' => $cutoff, ':cursor' => $after, ':ceiling' => $ceiling];
            if ($stmt === null) {
                try {
                    $stmt = $pdo->prepare("SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND (tonpay_gateway_mode IS NULL OR tonpay_gateway_mode <> 'custom') AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120");
                    $stmt->execute($params);
                } catch (Throwable $e) {
                    $stmt = $pdo->prepare("SELECT id, id_order, id_user, Payment_Method, price, time, message_id FROM Payment_report WHERE time < :cutoff AND payment_Status = 'Unpaid' AND Payment_Method = 'tonpay' AND id > :cursor AND id <= :ceiling ORDER BY id ASC LIMIT 120");
                    $stmt->execute($params);
                }
            } else {
                $stmt->execute($params);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    private function expireOne(array $result, PDOStatement $expire): void
    {
        $cardExpired = null;
        if (method_exists(ReceiptActions::class, 'rxCancelAbandonedCardPayment') && in_array((string) $result['Payment_Method'], Cancellation::cardPaymentMethods(), true)) {
            $cardExpired = ReceiptActions::rxCancelAbandonedCardPayment((string) $result['id_order'], (string) $result['id_user'], 'timeout', ['status' => 'expire']);
        }
        if (!is_array($cardExpired) || empty($cardExpired['ok'])) {
            if (is_array($cardExpired) && in_array(($cardExpired['reason'] ?? ''), ['db_error', 'verify_locked', 'verify_window_active'], true)) {
                return;
            }
            $expire->execute([':o' => $result['id_order']]);
            if ($expire->rowCount() !== 1) {
                return;
            }
        }
        if (isset($result['id_user'])) {
            RedisCache::rx_redis_del(RedisCache::rx_payment_status_cache_key($result['id_order'], $result['id_user']));
        }
        if (method_exists(SettlementRuntime::class, 'rx_release_unpaid_discount')) {
            $reference = Formatting::isValidDate($result['time'] ?? '') ? strtotime(str_replace('/', '-', (string) $result['time'])) : null;
            SettlementRuntime::rx_release_unpaid_discount((string) $result['id_user'], null, $reference ?: null);
        }
        deletemessage($result['id_user'], $result['message_id']);
    }
}
