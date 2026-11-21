<?php

namespace Faoxima\Cron\Services;

use Faoxima\Cashback\Credits;
use Faoxima\Core\Cache\RedisCache;
use Faoxima\Core\Database\Db;
use Faoxima\Core\Database\SelectCache;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Payments\Crypto\ManualCrypto;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Gateways\CryptoQuotes;
use Faoxima\Payments\Gateways\GatewayRuntime;
use Faoxima\Wallet\Ledger\Ledger;
use PDO;
use RuntimeException;
use Throwable;

final class CryptoCheck
{
    private const UNPAID_TTL = 14400;
    private const HASH_TTL = 1800;
    private const BATCH_SIZE = 25;
    private const TERMINAL_REASONS = [
        'no-currency', 'no-hash', 'no-recipient-on-row', 'no-expected-amount',
        'unsupported-currency', 'bad-hash-format',
        'hash-already-used',
        'wrong-recipient', 'amount-mismatch', 'tx-failed',
        'not-trx-transfer', 'no-matching-trc20-transfer',
        'no-matching-jetton-transfer', 'no-in-msg',
        'sender-mismatch', 'sender-already-bound',
        'memo-mismatch',
        'tx-before-invoice',
    ];
    private const REJECT_REASONS_FA = [
        'hash-already-used'    => 'این هش قبلاً برای فاکتور دیگری استفاده شده است.',
        'wrong-recipient'      => 'آدرس مقصد تراکنش با کیف پول ربات یکسان نیست.',
        'tx-failed'            => 'تراکنش روی شبکه با خطا انجام شده است.',
        'unsupported-currency' => 'این ارز توسط ربات پشتیبانی نمی‌شود.',
        'bad-hash-format'      => 'فرمت هش ارسالی نامعتبر است.',
        'amount-mismatch'      => 'مبلغ تراکنش با مبلغ فاکتور یکسان نیست. لطفاً دقیقاً همان مقدار اعلام‌شده را ارسال کنید.',
        'not-trx-transfer'     => 'این تراکنش یک انتقال ساده‌ی TRX نیست.',
        'no-matching-trc20-transfer'  => 'این هش حاوی انتقال USDT-TRC20 منطبق با فاکتور نیست.',
        'no-matching-jetton-transfer' => 'این هش حاوی انتقال USDT-TON منطبق با فاکتور نیست.',
        'no-in-msg'            => 'تراکنش روی شبکه TON معتبر نیست.',
        'sender-mismatch'      => 'آدرس فرستنده با آدرس ثبت‌شده‌ی فاکتور یکسان نیست. باید از همان کیف پول/صرافی قبلی ارسال کنید.',
        'sender-already-bound' => 'این آدرس فرستنده قبلاً برای فاکتور دیگری استفاده شده است. لطفاً از کیف پول/حساب دیگری ارسال کنید.',
        'tx-before-invoice'    => 'این تراکنش قبل از ساخت فاکتور شما روی شبکه ثبت شده است. لطفاً پس از ساخت فاکتور پرداخت کنید و هش همان تراکنش را ارسال کنید.',
        'memo-mismatch'        => 'ممو (Memo / Comment) تراکنش با مموی فاکتور یکسان نیست. لطفاً ممو دقیقاً همان‌طور که در فاکتور نشان داده شد را در پرداخت وارد کنید.',
    ];

    private PDO $pdo;
    private array $setting;
    private $reportThread;
    private string $logFile;
    private string $imagePath;
    private string $window;
    private string $invoiceWindow;
    private string $aliasedWindow;
    private int $maxChecks = 10;

    public function __construct(PDO $pdo, $setting, $reportThread, string $root)
    {
        $this->pdo = $pdo;
        $this->setting = is_array($setting) ? $setting : [];
        $this->reportThread = $reportThread;
        $this->logFile = $root . '/cryptocheck_debug.log';
        $this->imagePath = $root . '/images.jpeg';
        $this->window = PaymentPolicy::cronWindowSql();
        $this->invoiceWindow = PaymentPolicy::cronWindowSql('Payment_report', self::UNPAID_TTL + 3600);
        $this->aliasedWindow = PaymentPolicy::cronWindowSql('p');
    }

    public static function isAvailable(): bool
    {
        return is_callable([CryptoQuotes::class, 'cryptoCheckPayment']);
    }

    public function expireStaleUnpaid(): void
    {
        $expireBefore = date('Y/m/d H:i:s', time() - self::UNPAID_TTL);
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id_user, id_order, message_id, crypto_currency, time
                   FROM Payment_report
                  WHERE {$this->invoiceWindow} AND payment_Status = 'Unpaid'
                    AND crypto_currency IS NOT NULL
                    AND time < :cutoff
                  LIMIT 50"
            );
            $stmt->execute([':cutoff' => $expireBefore]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (CronRuntime::rx_cron_time_up()) {
                    break;
                }
                $rowTs = strtotime(str_replace('/', '-', (string) ($row['time'] ?? '')));
                if ($rowTs === false || $rowTs === 0 || $rowTs > time() - self::UNPAID_TTL) {
                    continue;
                }
                $upd = $this->pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'expire'
                       WHERE id_order = :o AND payment_Status = 'Unpaid'"
                );
                $upd->execute([':o' => $row['id_order']]);
                if ($upd->rowCount() > 0) {
                    $this->forgetStatusCache($row);
                    $this->tell(
                        $row['id_user'],
                        "⭕️ کاربر گرامی، فاکتور کریپتویی زیر به دلیل عدم پرداخت در مدت مجاز منقضی شد.\n\n"
                        . "🛒 کد فاکتور: <code>{$row['id_order']}</code>\n"
                        . "💎 ارز: " . htmlspecialchars((string) $row['crypto_currency']) . "\n"
                        . "❗️ لطفاً به هیچ عنوان وجهی بابت این فاکتور پرداخت نکنید."
                    );
                    $this->deleteInvoiceMessage($row);
                }
            }
        } catch (Throwable $e) {
            error_log('[cryptocheck] expire pass: ' . $e->getMessage());
        }
    }

    public function expireStaleAwaitingHash(): void
    {
        $cutoff = time() - self::HASH_TTL;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id_user, id_order, message_id, crypto_currency
                   FROM Payment_report
                  WHERE {$this->invoiceWindow} AND payment_Status = 'AwaitingHash'
                    AND crypto_currency IS NOT NULL
                    AND crypto_currency IN ('TRX','TON','USDT_TRC20','USDT_TON')
                    AND crypto_hash_at IS NOT NULL
                    AND crypto_hash_at > 0
                    AND crypto_hash_at < :cutoff
                  LIMIT 50"
            );
            $stmt->execute([':cutoff' => $cutoff]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (CronRuntime::rx_cron_time_up()) {
                    break;
                }
                $upd = $this->pdo->prepare(
                    "UPDATE Payment_report SET payment_Status = 'expire'
                       WHERE id_order = :o AND payment_Status = 'AwaitingHash'"
                );
                $upd->execute([':o' => $row['id_order']]);
                if ($upd->rowCount() > 0) {
                    $this->forgetStatusCache($row);
                    $this->tell(
                        $row['id_user'],
                        "⭕️ کاربر گرامی، فاکتور کریپتویی زیر به دلیل عدم تایید هش در مدت ۳۰ دقیقه منقضی شد.\n\n"
                        . "🛒 کد فاکتور: <code>{$row['id_order']}</code>\n"
                        . "💎 ارز: " . htmlspecialchars((string) $row['crypto_currency']) . "\n"
                        . "❗️ اگر وجهی پرداخت کرده‌اید، با پشتیبانی تماس بگیرید."
                    );
                    $this->deleteInvoiceMessage($row);
                }
            }
        } catch (Throwable $e) {
            error_log('[cryptocheck] hash-expire pass: ' . $e->getMessage());
        }
    }

    public function configureRetryLimit(): void
    {
        $maxChecks = (int) ManualCrypto::crypto_pay_setting('cryptocheck_max_retries', '10');
        $this->maxChecks = max(2, min($maxChecks, 60));
    }

    public function registerFatalLogger(): void
    {
        $logFile = $this->logFile;
        register_shutdown_function(function () use ($logFile) {
            $err = error_get_last();
            if (!$err) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
            if (!in_array($err['type'] ?? 0, $fatal, true)) {
                return;
            }
            $line = '[' . date('Y-m-d H:i:s') . '] [FATAL] PHP fatal at ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?')
                  . ' — ' . ($err['message'] ?? '');
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
            error_log('[cryptocheck-fatal] ' . $line);
        });
    }

    public function refundStuckPaid(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id_order,
                    p.time,
                    p.id_user,
                    p.price,
                    p.crypto_currency,
                    p.id_invoice,
                    p.at_updated,
                    i.id_invoice AS inv_id,
                    i.Status     AS inv_status,
                    i.uuid       AS inv_uuid
                FROM Payment_report p
                LEFT JOIN invoice i
                    ON i.username = SUBSTRING_INDEX(p.id_invoice, '|', -1)
                WHERE {$this->aliasedWindow} AND p.payment_Status = 'paid'
                  AND p.Payment_Method = 'arze digital offline'
                  AND p.id_invoice LIKE 'getconfigafterpay|%'
                  AND (p.dec_not_confirmed IS NULL OR (p.dec_not_confirmed NOT LIKE '%auto-refund%' AND p.dec_not_confirmed NOT LIKE '%service-created%'))
                  AND (i.id_invoice IS NULL OR LOWER(i.Status) = 'unpaid' OR i.uuid IS NULL OR i.uuid = '')
                  AND p.at_updated < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                LIMIT 25
            ");
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($list)) {
                return;
            }
            $this->log('INFO', 'found stuck paid invoices', ['count' => count($list)]);
            foreach ($list as $row) {
                if (CronRuntime::rx_cron_time_up()) {
                    break;
                }
                if (!PaymentPolicy::cronPaymentAllowed($row)) {
                    continue;
                }
                $this->refundOne($row);
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'stuck-refund pass failed', ['error' => $e->getMessage()]);
        }
    }

    private function refundOne(array $row): void
    {
        $orderId = (string) $row['id_order'];
        $userId = (string) $row['id_user'];
        $priceIrr = (int) ($row['price'] ?? 0);
        $currency = (string) $row['crypto_currency'];
        if ($priceIrr <= 0 || $userId === '') {
            $this->log('WARN', 'invalid stuck row, skipping', ['order' => $orderId]);
            return;
        }
        $pdo = $this->pdo;
        try {
            $pdo->beginTransaction();

            $markNote = '[auto-refund: stuck service creation at ' . date('Y-m-d H:i:s') . ']';
            $mark = $pdo->prepare("
                UPDATE Payment_report
                SET dec_not_confirmed = CASE
                        WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :note
                        ELSE CONCAT(dec_not_confirmed, ' | ', :note2)
                    END
                WHERE id_order = :o
                  AND {$this->window}
                  AND payment_Status = 'paid'
                  AND (dec_not_confirmed IS NULL OR (dec_not_confirmed NOT LIKE '%auto-refund%' AND dec_not_confirmed NOT LIKE '%service-created%'))
            ");
            $mark->execute([':note' => $markNote, ':note2' => $markNote, ':o' => $orderId]);
            if ($mark->rowCount() < 1) {
                $pdo->commit();
                $this->log('WARN', 'stuck invoice refund claim lost (already claimed)', ['order' => $orderId]);
                return;
            }

            $balStmt = $pdo->prepare("SELECT Balance FROM user WHERE id = :id LIMIT 1");
            $balStmt->execute([':id' => $userId]);
            $userRow = $balStmt->fetch(PDO::FETCH_ASSOC);
            $oldBalance = $userRow ? (int) $userRow['Balance'] : 0;

            $updBal = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :id");
            $updBal->execute([':d' => $priceIrr, ':id' => $userId]);
            if ($updBal->rowCount() < 1) {
                throw new RuntimeException('stuck refund credit affected no row');
            }

            if (!is_callable([Ledger::class, 'record'])
                || !Ledger::record($pdo, $userId, 'credit', $priceIrr, 'refund', 'بازگشت خودکار پرداخت گیرکرده', $orderId, 'Payment_report', $orderId)) {
                throw new RuntimeException('stuck refund ledger failed');
            }

            $balStmt->execute([':id' => $userId]);
            $userRow = $balStmt->fetch(PDO::FETCH_ASSOC);
            $newBalance = $userRow ? (int) $userRow['Balance'] : ($oldBalance + $priceIrr);

            $pdo->commit();
            SelectCache::clearSelectCacheRow('user', 'id', $userId);
            $this->log('OK', 'stuck invoice refunded', [
                'order' => $orderId,
                'user'  => $userId,
                'amount' => $priceIrr,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ]);

            $this->tell(
                $userId,
                "💰 <b>برگشت پول خودکار</b>\n\n"
                . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
                . "💎 ارز: " . htmlspecialchars($currency) . "\n"
                . "💵 مبلغ برگشت‌خورده: " . number_format($priceIrr) . " تومان\n"
                . "💼 موجودی جدید: " . number_format($newBalance) . " تومان\n\n"
                . "⚠️ پرداخت شما تأیید شد ولی به دلیل خطا در ساخت سرویس، مبلغ به کیف پول شما برگشت داده شد.\n"
                . "💡 می‌توانید مجدداً اقدام به خرید سرویس کنید."
            );
            if (!empty($this->setting['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id'    => $this->setting['Channel_Report'],
                    'text'       => "💰 <b>برگشت پول خودکار (سرویس گیرکرده)</b>\n\n"
                        . "<blockquote>🛒 کد فاکتور: <code>{$orderId}</code></blockquote>\n"
                        . "<blockquote>👤 کاربر: <code>{$userId}</code></blockquote>\n"
                        . "<blockquote>💎 ارز: " . htmlspecialchars($currency) . "</blockquote>\n"
                        . "<blockquote>💵 مبلغ: " . number_format($priceIrr) . " تومان</blockquote>\n"
                        . "<blockquote>⚠️ علت: فاکتور paid شد ولی سرویس ساخته نشد ({$row['inv_status']}, uuid=" . ($row['inv_uuid'] ?: 'NULL') . ")</blockquote>",
                    'parse_mode' => 'HTML',
                ]);
            }
        } catch (Throwable $e) {
            try {
                $pdo->rollBack();
            } catch (Throwable $r) {
            }
            $this->log('ERROR', 'stuck refund transaction failed', [
                'order' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function retryStuckServiceCreation(): void
    {
        $pdo = $this->pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT p.id_order, p.time, p.id_user, p.id_invoice, p.crypto_currency, p.price,
                       p.at_updated, p.crypto_check_count,
                       i.username AS service_username,
                       i.Status AS invoice_status, i.uuid AS invoice_uuid
                FROM Payment_report p
                LEFT JOIN invoice i ON i.username = SUBSTRING_INDEX(p.id_invoice, '|', -1)
                WHERE {$this->aliasedWindow} AND p.payment_Status = 'paid'
                  AND p.Payment_Method = 'arze digital offline'
                  AND p.id_invoice LIKE 'getconfigafterpay|%'
                  AND (p.dec_not_confirmed IS NULL OR (p.dec_not_confirmed NOT LIKE '%auto-refund%' AND p.dec_not_confirmed NOT LIKE '%service-created%'))
                  AND (i.uuid IS NULL OR i.uuid = '' OR LOWER(i.Status) = 'unpaid')
                  AND p.at_updated >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                  AND COALESCE(p.crypto_check_count, 0) < 5
                ORDER BY p.at_updated ASC
                LIMIT 5
            ");
            $stmt->execute();
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($list)) {
                return;
            }
            $this->log('INFO', 'retry pass: found stuck service-creation invoices', ['count' => count($list)]);
            foreach ($list as $row) {
                if (CronRuntime::rx_cron_time_up()) {
                    break;
                }
                if (!PaymentPolicy::cronPaymentAllowed($row)) {
                    continue;
                }
                $this->retryOne($row);
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'retry pass failed', ['error' => $e->getMessage()]);
        }
    }

    private function retryOne(array $row): void
    {
        $pdo = $this->pdo;
        $orderId = (string) $row['id_order'];
        $userId = (string) $row['id_user'];
        $serviceUser = (string) ($row['service_username'] ?? '');
        $tryNow = (int) ($row['crypto_check_count'] ?? 0) + 1;
        $this->log('INFO', 'retry stuck service creation', [
            'order'        => $orderId,
            'user'         => $userId,
            'try'          => $tryNow,
            'invoice_uuid' => $row['invoice_uuid'] ?: 'NULL',
            'invoice_status' => $row['invoice_status'] ?: 'NULL',
            'service_username' => $serviceUser,
        ]);

        try {
            $bump = $pdo->prepare("UPDATE Payment_report SET crypto_check_count = COALESCE(crypto_check_count,0)+1 WHERE id_order = :o");
            $bump->execute([':o' => $orderId]);
        } catch (Throwable $e) {
        }

        $this->runDirectPayment($orderId, 'retry ');

        try {
            if ($serviceUser !== '') {
                $check = $pdo->prepare("SELECT id_invoice, Status, uuid FROM invoice WHERE username = :u LIMIT 1");
                $check->execute([':u' => $serviceUser]);
                $now = $check->fetch(PDO::FETCH_ASSOC);
                $activated = $now && (
                    (string) $now['Status'] === 'active'
                    || !empty($now['uuid'])
                );
                if ($activated && (string) $now['Status'] === 'unpaid') {
                    $fix = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                    $fix->execute([':i' => $now['id_invoice']]);
                }
                if (!$activated) {
                    $this->log('WARN', 'retry did not create service', [
                        'order' => $orderId,
                        'try'   => $tryNow,
                    ]);
                }
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'retry post-check failed', [
                'order' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fetchPending(): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT *
                   FROM Payment_report
                  WHERE {$this->invoiceWindow} AND payment_Status = 'AwaitingHash'
                    AND crypto_currency IS NOT NULL
                    AND crypto_currency IN ('TRX','TON','USDT_TRC20','USDT_TON')
                    AND crypto_tx_hash IS NOT NULL
                    AND crypto_tx_hash <> ''
                    AND COALESCE(crypto_check_count, 0) < :maxChecks
                  ORDER BY COALESCE(crypto_hash_at, 0) ASC
                  LIMIT :limit"
            );
            $stmt->bindValue(':maxChecks', $this->maxChecks, PDO::PARAM_INT);
            $stmt->bindValue(':limit', self::BATCH_SIZE, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $this->log('INFO', 'pending rows fetched', ['count' => count($rows)]);
            }
            return $rows;
        } catch (Throwable $e) {
            $this->log('ERROR', 'select pending failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function processPending(array $rows): void
    {
        foreach ($rows as $row) {
            if (CronRuntime::rx_cron_time_up()) {
                break;
            }
            if (!PaymentPolicy::cronPaymentAllowed($row)) {
                continue;
            }
            $this->verifyAndSettle($row);
        }
        if (!empty($rows)) {
            $this->log('INFO', 'cron run complete', ['processed' => count($rows)]);
        }
    }

    private function verifyAndSettle(array $row): void
    {
        $pdo = $this->pdo;
        $orderId = (string) $row['id_order'];
        $currency = (string) $row['crypto_currency'];
        $this->log('INFO', 'processing invoice', [
            'order'    => $orderId,
            'currency' => $currency,
            'hash'     => substr((string) ($row['crypto_tx_hash'] ?? ''), 0, 16) . '…',
            'amount'   => $row['crypto_amount'] ?? null,
            'try'      => (int) ($row['crypto_check_count'] ?? 0) + 1,
        ]);

        try {
            $bump = $pdo->prepare(
                "UPDATE Payment_report
                    SET crypto_check_count = COALESCE(crypto_check_count,0) + 1
                  WHERE id_order = :o"
            );
            $bump->execute([':o' => $orderId]);
        } catch (Throwable $e) {
            $this->log('ERROR', 'verification attempt counter could not be persisted', ['order' => $orderId, 'exception' => get_class($e)]);
        }

        $result = CryptoQuotes::cryptoCheckPayment($row);
        $this->logVerification($orderId, $result);
        $result = $this->rejectIfBeforeInvoice($row, $result, $orderId);

        if (!is_array($result) || empty($result['ok'])) {
            $this->handleFailedVerification($row, $result, $orderId);
            return;
        }

        if (!$this->markPaid($orderId, $result)) {
            return;
        }
        $this->log('INFO', 'calling DirectPayment', ['order' => $orderId]);
        $this->runDirectPayment($orderId, '');
        $this->activateInvoiceFallback($row, $orderId);
        $this->creditCashbackAndReport($row, $result, $orderId, $currency);
    }

    private function logVerification(string $orderId, $result): void
    {
        $reason = is_array($result) ? (string) ($result['reason'] ?? 'unknown') : 'no-result';
        $verified = is_array($result) && !empty($result['ok']);
        $entry = [
            'order' => $orderId,
            'ok' => $verified,
            'reason' => $reason,
        ];
        if ($verified) {
            $entry['amount'] = $result['detail']['amount'] ?? null;
            $entry['tx_timestamp'] = $result['detail']['tx_timestamp'] ?? null;
        } elseif ($reason === 'amount-mismatch') {
            $entry['observed'] = $result['detail']['observed'] ?? null;
            $entry['expected'] = $result['detail']['want'] ?? null;
        }
        $this->log('INFO', 'verify result', $entry);
    }

    private function rejectIfBeforeInvoice(array $row, $result, string $orderId)
    {
        if (!(is_array($result) && !empty($result['ok']))) {
            return $result;
        }
        $txTimestamp = isset($result['detail']['tx_timestamp']) ? (int) $result['detail']['tx_timestamp'] : 0;
        $createdRaw = (string) ($row['time'] ?? '');
        $createdAt = 0;
        if ($createdRaw !== '') {
            $parsed = strtotime(str_replace('/', '-', $createdRaw));
            if ($parsed !== false) {
                $createdAt = (int) $parsed;
            }
        }
        if ($txTimestamp > 0 && $createdAt > 0) {
            $earliestAllowed = $createdAt - 120;
            if ($txTimestamp < $earliestAllowed) {
                $this->log('WARN', 'transaction predates invoice', [
                    'order' => $orderId,
                    'tx_timestamp' => $txTimestamp,
                    'invoice_created_at' => $createdAt,
                    'diff_sec' => $createdAt - $txTimestamp,
                ]);
                return [
                    'ok' => false,
                    'reason' => 'tx-before-invoice',
                    'detail' => [
                        'tx_timestamp' => $txTimestamp,
                        'invoice_created_at' => $createdAt,
                        'diff_sec' => $createdAt - $txTimestamp,
                    ],
                ];
            }
        }
        return $result;
    }

    private function handleFailedVerification(array $row, $result, string $orderId): void
    {
        $pdo = $this->pdo;
        $reason = is_array($result) ? (string) ($result['reason'] ?? 'unknown') : 'no-result';
        try {
            $errStr = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $upd = $pdo->prepare("UPDATE Payment_report SET crypto_last_error = :err WHERE id_order = :o");
            $upd->execute([':err' => $errStr, ':o' => $orderId]);
        } catch (Throwable $e) {
            $this->log('ERROR', 'verification error could not be persisted', ['order' => $orderId, 'exception' => get_class($e)]);
        }

        $newCount = (int) ($row['crypto_check_count'] ?? 0) + 1;
        $isTerminal = in_array($reason, self::TERMINAL_REASONS, true);
        $isTimeout = !$isTerminal && $newCount >= $this->maxChecks;

        $this->log('INFO', 'verify decision', [
            'order'    => $orderId,
            'reason'   => $reason,
            'terminal' => $isTerminal,
            'timeout'  => $isTimeout,
            'try'      => $newCount,
        ]);

        if (!($isTerminal || $isTimeout)) {
            return;
        }
        try {
            $rej = $pdo->prepare(
                "UPDATE Payment_report SET payment_Status = 'reject'
                   WHERE id_order = :o AND payment_Status = 'AwaitingHash'"
            );
            $rej->execute([':o' => $orderId]);
            if ($rej->rowCount() > 0) {
                $faReason = $isTimeout
                    ? "هش وارد شده در {$this->maxChecks} دقیقه گذشته روی شبکه پیدا نشد. لطفاً مطمئن شوید هش صحیح را ارسال کرده‌اید و تراکنش روی شبکه تایید شده باشد."
                    : (self::REJECT_REASONS_FA[$reason] ?? $reason);
                $this->tell(
                    (string) $row['id_user'],
                    "❌ پرداخت کریپتویی شما تایید نشد.\n\n"
                    . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
                    . "📝 دلیل: " . htmlspecialchars($faReason) . "\n\n"
                    . "در صورت اعتراض با پشتیبانی در ارتباط باشید."
                );
                $this->notifyAdminOfRejection($row, $orderId, $faReason);
            }
        } catch (Throwable $e) {
        }
    }

    private function notifyAdminOfRejection(array $row, string $orderId, string $faReason): void
    {
        try {
            $explorerUrl = $this->explorerUrl((string) ($row['crypto_currency'] ?? ''), (string) ($row['crypto_tx_hash'] ?? ''));
            $dupWarn = '';
            $existing = ManualCrypto::crypto_lookup_verified_hash((string) ($row['crypto_tx_hash'] ?? ''));
            if (is_array($existing) && (string) ($existing['order_id'] ?? '') !== $orderId) {
                $dupWarn = "\n\n⚠️ <b>این هش قبلاً تایید شده</b>\n"
                    . "<blockquote>🛒 فاکتور قبلی: <code>" . htmlspecialchars((string) $existing['order_id']) . "</code></blockquote>\n"
                    . "<blockquote>👤 کاربر قبلی: <code>" . htmlspecialchars((string) ($existing['user_id'] ?? '-')) . "</code></blockquote>";
            }
            $caption = "❌ <b>هش کریپتو توسط ربات تایید نشد</b>\n\n"
                . "<blockquote>🛒 کد فاکتور: <code>{$orderId}</code></blockquote>\n"
                . "<blockquote>👤 کاربر: <code>" . htmlspecialchars((string) ($row['id_user'] ?? '-')) . "</code></blockquote>\n"
                . "<blockquote>💎 ارز: " . htmlspecialchars((string) ($row['crypto_currency'] ?? '-')) . "</blockquote>\n"
                . "<blockquote>🪙 مقدار ادعاشده: <code>" . htmlspecialchars((string) ($row['crypto_amount'] ?? '-')) . "</code></blockquote>\n"
                . "<blockquote>💸 معادل تومانی: " . number_format((int) ($row['price'] ?? 0)) . " تومان</blockquote>\n"
                . "<blockquote>🔗 هش: <code>" . htmlspecialchars((string) ($row['crypto_tx_hash'] ?? '-')) . "</code></blockquote>\n"
                . "<blockquote>📝 دلیل عدم تایید: " . htmlspecialchars($faReason) . "</blockquote>\n"
                . "<blockquote>🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a></blockquote>"
                . $dupWarn
                . "\n\n💡 اگر کاربر درخواست بررسی دستی بده، اعلان جدید با دکمه‌های اقدام به پی‌وی شما ارسال می‌شود.";

            $settingsRow = Db::select('setting', '*');
            $channelReport = is_array($settingsRow) ? (string) ($settingsRow['Channel_Report'] ?? '') : '';
            $threadRow = Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $threadId = is_array($threadRow) ? (string) ($threadRow['idreport'] ?? '') : '';
            $GLOBALS['threadId'] = $threadId;
            if ($channelReport !== '' && function_exists('telegram')) {
                $payload = [
                    'chat_id' => $channelReport,
                    'text' => $caption,
                    'parse_mode' => 'HTML',
                ];
                if ($threadId !== '') {
                    $payload['message_thread_id'] = $threadId;
                }
                @telegram('sendmessage', $payload);
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'admin notify on reject failed', ['order' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    private function markPaid(string $orderId, array $result): bool
    {
        try {
            $sender = isset($result['detail']['sender']) ? trim((string) $result['detail']['sender']) : '';
            $atomic = $this->pdo->prepare(
                "UPDATE Payment_report
                    SET payment_Status = 'paid',
                        at_updated = :now,
                        crypto_sender_address = COALESCE(NULLIF(crypto_sender_address, ''), :sender)
                  WHERE id_order = :o AND payment_Status <> 'paid'"
            );
            $atomic->execute([
                ':now' => date('Y/m/d H:i:s'),
                ':sender' => $sender !== '' ? $sender : null,
                ':o' => $orderId,
            ]);
            if ($atomic->rowCount() < 1) {
                $this->log('WARN', 'mark-paid skipped (already paid?)', ['order' => $orderId]);
                return false;
            }
            ManualCrypto::crypto_record_verified_hash($orderId, 'auto_cron');
            $this->log('OK', 'invoice marked PAID', ['order' => $orderId]);
            return true;
        } catch (Throwable $e) {
            $this->log('ERROR', 'atomic mark-paid failed', ['order' => $orderId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function runDirectPayment(string $orderId, string $labelPrefix): void
    {
        @set_time_limit(90);
        $started = microtime(true);
        try {
            if (method_exists(GatewayRuntime::class, 'DirectPayment')) {
                GatewayRuntime::DirectPayment($orderId, $this->imagePath);
                $this->log('OK', $labelPrefix . 'DirectPayment finished', ['order' => $orderId, 'ms' => round((microtime(true) - $started) * 1000)]);
            } else {
                $this->log('WARN', $labelPrefix . 'DirectPayment not defined', ['order' => $orderId]);
            }
        } catch (Throwable $e) {
            $context = [
                'order' => $orderId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            if ($labelPrefix === '') {
                $context['class'] = get_class($e);
            }
            $context['duration_ms'] = round((microtime(true) - $started) * 1000);
            $this->log('ERROR', $labelPrefix . 'DirectPayment threw', $context);
        }
    }

    private function activateInvoiceFallback(array $row, string $orderId): void
    {
        try {
            $parts = explode('|', (string) ($row['id_invoice'] ?? ''));
            $username = $parts[1] ?? '';
            if ($username === '' || strpos((string) ($row['id_invoice'] ?? ''), 'getconfigafterpay|') !== 0) {
                return;
            }
            $stmt = $this->pdo->prepare("SELECT id_invoice, Status, uuid FROM invoice WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($invoice && !empty($invoice['uuid']) && (string) $invoice['Status'] === 'unpaid') {
                $this->log('WARN', 'invoice created but not activated, fixing', [
                    'order'     => $orderId,
                    'invoice'   => $invoice['id_invoice'],
                    'username'  => $username,
                ]);
                $fix = $this->pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                $fix->execute([':i' => $invoice['id_invoice']]);
                $this->log('OK', 'invoice activated by fallback', [
                    'order'   => $orderId,
                    'invoice' => $invoice['id_invoice'],
                ]);
                $this->tell(
                    (string) $row['id_user'],
                    "✅ <b>پرداخت تایید شد و سرویس شما فعال است</b>\n\n"
                    . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
                    . "👤 نام کاربری سرویس: <code>" . htmlspecialchars($username) . "</code>\n\n"
                    . "ℹ️ اگر پیام تحویل کانفیگ را دریافت نکردید، از بخش «🛍 سرویس‌های من» وارد سرویس شوید و کانفیگ را دریافت کنید.\n"
                    . "در صورت بروز مشکل با پشتیبانی در تماس باشید."
                );
            } elseif (!$invoice) {
                $this->log('WARN', 'invoice not found after DirectPayment', [
                    'order' => $orderId,
                    'username' => $username,
                ]);
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'invoice-state fallback failed', [
                'order' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function creditCashbackAndReport(array $row, array $result, string $orderId, string $currency): void
    {
        try {
            $cashbackKey = 'cryptocheck_cashback_' . $currency;
            $percent = (float) ManualCrypto::crypto_pay_setting($cashbackKey, '0');
            $userStmt = $this->pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
            $userStmt->execute([':id' => (string) $row['id_user']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $row['id_user'], 'username' => '—', 'Balance' => 0];

            $bonus = $percent > 0 ? (int) floor(((float) $row['price'] * $percent) / 100.0) : 0;
            if ($bonus > 0 && Credits::creditOnce($this->pdo, $orderId, $user['id'], $bonus, $cashbackKey, 'هدیه بازگشت وجه کریپتو') === 'credited') {
                $this->tell(
                    (string) $user['id'],
                    "🎁 کاربر عزیز، مبلغ " . number_format($bonus) . " تومان به عنوان هدیه به حساب شما واریز شد."
                );
            }

            $explorerUrl = $this->explorerUrl($currency, (string) $row['crypto_tx_hash']);
            $verifiedAmount = isset($result['detail']['amount']) ? $result['detail']['amount'] : '—';
            $username = isset($user['username']) ? '@' . $user['username'] : '—';
            $GLOBALS['username'] = $username;

            $report = "💵 پرداخت کریپتو جدید (هش‌چکر)\n"
                . "<blockquote>- 👤 نام کاربری: {$username}</blockquote>\n"
                . "<blockquote>- 👤 آیدی عددی: {$user['id']}</blockquote>\n"
                . "<blockquote>- 💸 مبلغ تراکنش (تومان): " . number_format((int) $row['price']) . "</blockquote>\n"
                . "<blockquote>- 💎 ارز: " . htmlspecialchars($currency) . "</blockquote>\n"
                . "<blockquote>- 📥 مبلغ روی شبکه: " . htmlspecialchars((string) $verifiedAmount) . "</blockquote>\n"
                . "<blockquote>- 🔗 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">لینک تراکنش</a></blockquote>\n"
                . "<blockquote>- 🛒 کد فاکتور: <code>{$orderId}</code></blockquote>\n"
                . "<blockquote>- 💳 روش پرداخت: ارز آفلاین (تایید خودکار)</blockquote>";

            if (!empty($this->setting['Channel_Report']) && function_exists('telegram')) {
                $payload = [
                    'chat_id' => $this->setting['Channel_Report'],
                    'text'    => $report,
                    'parse_mode' => 'HTML',
                ];
                if (!empty($this->reportThread)) {
                    $payload['message_thread_id'] = $this->reportThread;
                }
                telegram('sendmessage', $payload);
            }
        } catch (Throwable $e) {
            $this->log('ERROR', 'post-paid handling threw', ['order' => $orderId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    private function explorerUrl(string $currency, string $hash): string
    {
        return is_callable([CryptoQuotes::class, 'cryptoExplorerUrl'])
            ? CryptoQuotes::cryptoExplorerUrl($currency, $hash)
            : $hash;
    }

    private function forgetStatusCache(array $row): void
    {
        if (isset($row['id_user'])) {
            RedisCache::rx_redis_del(RedisCache::rx_payment_status_cache_key($row['id_order'], $row['id_user']));
        }
    }

    private function deleteInvoiceMessage(array $row): void
    {
        $messageId = isset($row['message_id']) ? (int) $row['message_id'] : 0;
        if ($messageId > 0 && function_exists('deletemessage')) {
            @deletemessage($row['id_user'], $messageId);
        }
    }

    private function tell($userId, string $text): void
    {
        if (function_exists('sendmessage')) {
            @sendmessage($userId, $text, null, 'HTML');
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($level !== 'ERROR') {
            return;
        }
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND);
        error_log('[cryptocheck] ' . $message . (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }
}
