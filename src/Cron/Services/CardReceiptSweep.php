<?php

namespace Faoxima\Cron\Services;

use Faoxima\Cashback\Credits;
use Faoxima\Cashback\Eligibility;
use Faoxima\Core\Cache\RedisCache;
use Faoxima\Core\Database\Db;
use Faoxima\Core\Support\Formatting;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Payments\Fulfillment\FulfillmentRuntime;
use Faoxima\Payments\Fulfillment\PaymentPolicy;
use Faoxima\Payments\Receipts\ReceiptRuntime;
use Faoxima\Payments\Receipts\Settings;
use Faoxima\Payments\Settlement\CallbackClaims;
use Faoxima\Services\Fulfillment\Delivery;
use Faoxima\Telegram\Conversation\ConversationData;
use PDO;
use Throwable;

final class CardReceiptSweep
{
    private $pdo;
    private $setting;
    private $paymentReports;

    private bool $trustModeOn = false;
    private array $exceptions = [];
    private array $trusted = [];
    private $timeCheck = 0;
    private int $worker = 0;
    private int $workers = 1;
    private string $window = '';
    private string $shardSql = '';
    private string $sbvExclude = '';
    private array $queue = [];
    private int $waitingLastId = 0;
    private int $recoverLastId = 0;
    private bool $stopped = false;

    public function __construct($pdo, $setting, $paymentReports)
    {
        $this->pdo = $pdo;
        $this->setting = $setting;
        $this->paymentReports = $paymentReports;
    }

    public static function buttonTexts($rows): array
    {
        $texts = [
            'textafterpay' => '',
            'textaftertext' => '',
            'textmanual' => '',
            'textselectlocation' => '',
            'text_wgdashboard' => '',
        ];
        foreach ($rows as $row) {
            $id = $row['id_text'];
            $text = $row['text'];
            if (array_key_exists($id, $texts) || (is_string($text) && trim($text) !== '')) {
                $texts[$id] = $text;
            }
        }
        return $texts;
    }

    public function configureTrust(): void
    {
        $trustModeActive = Db::select("PaySetting", "ValuePay", "NamePay", "trust_mode_active", "select")['ValuePay'];
        $this->trustModeOn = ($trustModeActive == "on");
        $exceptionsRaw = Db::select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
        $this->exceptions = is_string($exceptionsRaw) ? json_decode($exceptionsRaw, true) : [];
        $this->trusted = [];
        if ($this->trustModeOn) {
            $trustedRaw = Db::select("PaySetting", "ValuePay", "NamePay", "Trust_auto_cart", "select")['ValuePay'];
            $this->trusted = is_string($trustedRaw) ? json_decode($trustedRaw, true) : [];
        }
    }

    public function collectQueue(): void
    {
        $pdo = $this->pdo;
        $this->sbvExclude = (method_exists(ReceiptRuntime::class, 'rx_sbv_has_schema') && Settings::hasSchema($pdo)) ? " AND NOT EXISTS (SELECT 1 FROM card_sms_payment csp WHERE csp.id_order = Payment_report.id_order)" : "";
        [$w, $n] = CronRuntime::rx_cron_shard();
        $this->worker = $w;
        $this->workers = $n;
        $this->shardSql = ($n > 1) ? " AND MOD(id, $n) = $w " : "";
        $this->window = PaymentPolicy::cronReceiptWindowSql();
        $this->queue = [];
        $this->waitingLastId = 0;
        $this->stopped = false;

        $this->collectRecoverable();
        $this->timeCheck = $this->setting['timeauto_not_verify'] * 60;
        $paymentVerify = Db::select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
        $autoConfirm = $paymentVerify != "offauto" && ($this->setting['card_verify_status'] ?? 'offcardverify') !== 'oncardverify';
        if ($autoConfirm) {
            $this->collectWaiting();
        }
    }

    private function collectRecoverable(): void
    {
        $cursor = CronBatches::rx_cron_invoice_cursor('croncard_recover:' . $this->workers . ':' . $this->worker);
        $sql = "SELECT id, id_order FROM Payment_report WHERE {$this->window} AND (payment_Status IN ('processing', 'reconciling') OR (payment_Status = 'paid' AND COALESCE(direct_payment_done, 0) = 0 AND (Payment_Method = 'cart to cart' OR EXISTS (SELECT 1 FROM payment_fulfillment recover_pf WHERE recover_pf.id_order = Payment_report.id_order)))) AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = Payment_report.id_order AND pf.state IN ('completed', 'manual_review')){$this->sbvExclude}{$this->shardSql} AND id > ? ORDER BY id ASC LIMIT 20";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cursor]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows && $cursor !== '') {
            $stmt->execute(['']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->recoverLastId = 0;
        foreach ($rows as $row) {
            $this->queue[] = ['id' => (string) $row['id'], 'id_order' => (string) $row['id_order'], 'mode' => 'recover'];
            $this->recoverLastId = max($this->recoverLastId, (int) $row['id']);
        }
    }

    private function collectWaiting(): void
    {
        $cursor = CronBatches::rx_cron_invoice_cursor('croncard_waiting:' . $this->workers . ':' . $this->worker);
        $sql = "SELECT * FROM Payment_report WHERE {$this->window} AND payment_Status = 'waiting' AND COALESCE(direct_payment_done, 0) = 0 AND (Payment_Method = 'cart to cart' OR Payment_Method = 'arze digital offline') AND bottype IS NULL AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = Payment_report.id_order AND pf.state IN ('completed', 'manual_review')){$this->sbvExclude}{$this->shardSql} AND at_updated IS NOT NULL AND id > ? ORDER BY id ASC LIMIT 50";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cursor]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows && $cursor !== '') {
            $stmt->execute(['']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->waitingLastId = 0;
        foreach ($rows as $row) {
            $this->waitingLastId = max($this->waitingLastId, (int) ($row['id'] ?? 0));
            if (CronRuntime::rx_cron_time_up()) {
                $this->stopped = true;
                break;
            }
            if ($row['at_updated'] == null) {
                continue;
            }
            $sinceStart = time() - strtotime($row['at_updated']);
            $user = Db::select("user", "*", "id", $row['id_user'], "select");
            $userId = (string) $user['id'];
            if ($this->trustModeOn) {
                if (!in_array($userId, array_map('strval', (array) $this->trusted))) {
                    continue;
                }
            } else {
                if (in_array($userId, array_map('strval', (array) $this->exceptions))) {
                    continue;
                }
                if ($sinceStart <= $this->timeCheck) {
                    continue;
                }
            }
            $this->queue[] = ['id' => (string) $row['id'], 'id_order' => (string) $row['id_order'], 'mode' => 'claim', 'balance_before' => $user['Balance'] ?? 0];
        }
    }

    public function processQueue(): void
    {
        foreach ($this->queue as $job) {
            if (CronRuntime::rx_cron_budget_exhausted()) {
                $this->stopped = true;
                break;
            }
            try {
                $this->confirm($job);
            } catch (Throwable $e) {
                error_log('[croncard] order ' . (string) $job['id_order'] . ': ' . $e->getMessage());
            } finally {
                $cursorJob = $job['mode'] === 'recover' ? 'croncard_recover:' : 'croncard_waiting:';
                CronBatches::rx_cron_invoice_cursor($cursorJob . $this->workers . ':' . $this->worker, (string) $job['id']);
            }
        }
    }

    public function finish(): void
    {
        if (!$this->stopped) {
            if (!empty($this->recoverLastId)) {
                CronBatches::rx_cron_invoice_cursor('croncard_recover:' . $this->workers . ':' . $this->worker, (string) $this->recoverLastId);
            }
            if (!empty($this->waitingLastId)) {
                CronBatches::rx_cron_invoice_cursor('croncard_waiting:' . $this->workers . ':' . $this->worker, (string) $this->waitingLastId);
            }
        }
        if ($this->stopped) {
            TaskState::rx_cron_task_defer('time_budget', 60);
        } else {
            TaskState::rx_cron_task_complete('croncard_checks_finished');
        }
    }

    private function openReceiptJob(array $job): ?array
    {
        if ($job['mode'] === 'recover') {
            $recovered = FulfillmentRuntime::rx_pf_recover($job['id_order']);
            $status = (string) ($recovered['status'] ?? '');
            if ($status === 'claimed' || $status === 'reconcile') {
                return ['token' => (string) $recovered['token']];
            }
            return $status === 'finalized' ? ['token' => null] : null;
        }

        $claim = FulfillmentRuntime::rx_pf_claim($job['id_order'], [
            'from' => ['waiting'],
            'to' => 'processing',
            'dec_not_confirmed' => 'تایید توسط ربات بدون بررسی',
            'min_age' => $this->trustModeOn ? null : $this->timeCheck,
        ]);
        return ($claim['status'] ?? '') === 'claimed' ? ['token' => (string) $claim['token']] : null;
    }

    private function confirm(array $job): void
    {
        $pdo = $this->pdo;
        $receiptJob = $this->openReceiptJob($job);
        if ($receiptJob === null) {
            return;
        }
        $token = $receiptJob['token'];
        $payment = Db::select("Payment_report", "*", "id_order", $job['id_order'], "select", ['cache' => false]);
        if (!is_array($payment)) {
            return;
        }
        $verifiedBankSms = $job['mode'] === 'recover' && CallbackClaims::isVerifiedCard(
            $pdo, (string) $payment['id_order'], (string) $payment['id_user']
        );
        $user = Db::select("user", "*", "id", $payment['id_user'], "select");
        $balanceBefore = $job['balance_before'] ?? ($user['Balance'] ?? 0);
        $GLOBALS['textbotlang'] = ConversationData::languagechange('../text.json');
        if (isset($payment['id_user'])) {
            RedisCache::rx_redis_del(RedisCache::rx_payment_status_cache_key($payment['id_order'], $payment['id_user']));
        }
        $reportChatId = trim((string) ($payment['report_chat_id'] ?? ''));
        $reportMessageId = (int) ($payment['report_message_id'] ?? 0);
        $reportThreadId = (int) ($payment['report_thread_id'] ?? 0);
        $privateRaw = (string) ($payment['private_receipt_targets'] ?? '');
        $privateTargets = $privateRaw !== '' ? json_decode($privateRaw, true) : [];
        if (!is_array($privateTargets)) {
            $privateTargets = [];
        }
        if ($token !== null) {
            $run = Delivery::runClaimed($pdo, $job['id_order'], $token, "../images.jpg", $verifiedBankSms ? 'manual_review' : 'reject');
            if (empty($run['finalized'])) {
                return;
            }
        }
        $payment = Db::select("Payment_report", "*", "id_order", $job['id_order'], "select", ['cache' => false]);
        $user = Db::select("user", "*", "id", $payment['id_user'], "select");
        $this->reportAutoConfirmation($payment, $user, $balanceBefore);
        if ($verifiedBankSms) {
            return;
        }
        $this->creditCashback($payment, $user);
        $this->updateReceiptMessages($payment, $user, $reportChatId, $reportMessageId, $reportThreadId, $privateTargets);
    }

    private function reportAutoConfirmation(array $payment, $user, $balanceBefore): void
    {
        $formatPrice = number_format($payment['price']);
        $before = Formatting::rxFormatToman($balanceBefore);
        $after = Formatting::rxFormatToman($user['Balance']);
        $text = "📣 پرداخت به‌صورت خودکار تایید شد.

    اطلاعات :
    <blockquote>💸 روش پرداخت : {$payment['Payment_Method']}</blockquote>
    <blockquote>🤖 نوع تایید : تایید خودکار بدون بررسی</blockquote>
    <blockquote>👤 آیدی عددی کاربر : <code>{$payment['id_user']}</code></blockquote>
    <blockquote>👤 نام کاربری کاربر : @{$user['username']}</blockquote>
    <blockquote>💰 مبلغ پرداخت : $formatPrice</blockquote>
    <blockquote>کد پیگیری پرداخت : {$payment['id_order']}</blockquote>
    <blockquote>💎 موجودی قبل : {$before}</blockquote>
    <blockquote>💎 موجودی بعد : {$after}</blockquote>";
        if (strlen($this->setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $this->setting['Channel_Report'],
                'message_thread_id' => $this->paymentReports,
                'text' => $text,
                'parse_mode' => "HTML"
            ]);
        }
    }

    private function creditCashback(array $payment, $user): void
    {
        $percent = Db::select("PaySetting", "ValuePay", "NamePay", "chashbackcart", "select")['ValuePay'];
        $eligible = !method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_cashbackEligibleForKey')
            || Eligibility::cashbackEligibleForKey($this->pdo, "chashbackcart", $user['register'] ?? null, $payment['id_invoice'] ?? null, $user['id'] ?? null, $payment['id_order'] ?? null);
        if ($eligible && $percent != "0") {
            $amount = intval(($payment['price'] * $percent) / 100);
            if (Credits::creditOnce($this->pdo, $payment['id_order'], $user['id'], $amount, 'chashbackcart', 'هدیه بازگشت وجه کارت به کارت (تایید خودکار)') === 'credited') {
                $text = "🎁 کاربر عزیز مبلغ " . Formatting::rxFormatToman($amount) . " تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
                sendmessage($user['id'], $text, null, 'HTML');
            }
        }
    }

    private function updateReceiptMessages(array $payment, $user, string $reportChatId, int $reportMessageId, int $reportThreadId, array $privateTargets): void
    {
        $price = Formatting::rxFormatToman($payment['price']);
        $text = "✅ تایید شده (تایید خودکار بدون بررسی)

    <blockquote>آیدی عددی کاربر : {$user['id']}</blockquote>
    <blockquote>مبلغ تراکنش {$price}</blockquote>
    <blockquote>روش پرداخت :  تایید خودکار بدون بررسی</blockquote>
    <blockquote>{$payment['Payment_Method']}</blockquote>";
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $payment['id_user']]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $targets = [];
        if ($reportChatId !== '' && $reportMessageId > 0) {
            $targets[] = ['chat_id' => $reportChatId, 'message_id' => $reportMessageId, 'thread_id' => $reportThreadId > 0 ? $reportThreadId : null];
        }
        foreach ($privateTargets as $target) {
            $chatId = (string) ($target['chat_id'] ?? '');
            $messageId = (int) ($target['message_id'] ?? 0);
            if ($chatId === '' || $messageId <= 0) {
                continue;
            }
            $targets[] = ['chat_id' => $chatId, 'message_id' => $messageId, 'thread_id' => null];
        }
        $seen = [];
        $edited = false;
        foreach ($targets as $target) {
            $dedupKey = $target['chat_id'] . ':' . $target['message_id'];
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;
            try {
                $result = Editmessagetext($target['chat_id'], $target['message_id'], $text, $keyboard, 'HTML', $target['thread_id']);
                if (is_array($result) && !empty($result['ok'])) {
                    $edited = true;
                } elseif (function_exists('rx_log_event')) {
                    rx_log_event('AUTO_CONFIRM_RECEIPT_EDIT_FAILED', 'Editmessagetext returned failure for a receipt target', [
                        'id_order' => $payment['id_order'],
                        'chat_id' => $target['chat_id'],
                        'message_id' => $target['message_id'],
                        'desc' => is_array($result) ? (string) ($result['description'] ?? '') : '',
                    ]);
                }
            } catch (Throwable $e) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('AUTO_CONFIRM_RECEIPT_EDIT_FAILED', 'Editmessagetext failed for a receipt target', [
                        'id_order' => $payment['id_order'],
                        'chat_id' => $target['chat_id'],
                        'message_id' => $target['message_id'],
                        'err' => $e->getMessage(),
                    ]);
                }
            }
        }
        if (!$edited && strlen($this->setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $this->setting['Channel_Report'],
                'message_thread_id' => $this->paymentReports,
                'text' => $text,
                'reply_markup' => $keyboard,
                'parse_mode' => "HTML"
            ]);
        }
    }
}
