<?php

namespace Faoxima\Cron\Services;

use Faoxima\Core\Database\Db;
use Faoxima\Cron\Runtime\CronBatches;
use Faoxima\Cron\Runtime\CronRuntime;
use Faoxima\Cron\Runtime\NotificationOutbox;
use Faoxima\Cron\Runtime\TaskState;
use Faoxima\Payments\Fulfillment\Claims;
use Faoxima\Services\Fulfillment\ProviderState;
use Faoxima\Services\Fulfillment\WalletRecovery;
use Faoxima\Services\Renewal\ProviderRenewal;
use Faoxima\Services\Renewal\RenewalText;
use Faoxima\Services\Renewal\ReservationCriteria;
use Faoxima\Services\Renewal\ReservationExecution;
use Faoxima\Services\Renewal\ReservationRecovery;
use Faoxima\Services\Renewal\Reservations;
use Throwable;

final class QueuedRenewals
{
    private $pdo;
    private $panel;
    private $setting;
    private $reportThread;
    private NotificationOutbox $outbox;
    private int $worker = 0;
    private int $workers = 1;
    private int $now = 0;

    public function __construct($pdo, $panel, $setting, $reportThread, string $root)
    {
        $this->pdo = $pdo;
        $this->panel = $panel;
        $this->setting = $setting;
        $this->reportThread = $reportThread;
        $this->outbox = new NotificationOutbox(
            $root . '/cronbot' . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'renewal_notify_pending.json',
            static function (array $entry) {
                return telegram('sendmessage', [
                    'chat_id' => (string) $entry['chat_id'],
                    'message_thread_id' => $entry['thread_id'] ?? null,
                    'text' => (string) $entry['text'],
                    'parse_mode' => 'HTML',
                    '_rx_no_network_retry' => true,
                ], ($entry['kind'] ?? '') === 'report' ? null : ($entry['bottype'] ?? null));
            },
            true,
            false,
            true,
            'renewal_notification_persist_failed'
        );
    }

    public function loadNotifications(): bool
    {
        return $this->outbox->load('renewal_notification_queue_invalid');
    }

    public function drainNotifications(): void
    {
        $this->outbox->drain(3);
    }

    private static function esc($value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    private function sendReport(string $text): bool
    {
        if (empty($this->setting['Channel_Report'])) {
            return true;
        }
        return $this->outbox->sendOnce('report:' . hash('sha256', (string) $this->setting['Channel_Report'] . '|' . (string) $this->reportThread . '|' . $text), [
            'kind' => 'report',
            'chat_id' => (string) $this->setting['Channel_Report'],
            'thread_id' => $this->reportThread,
            'text' => $text,
        ]);
    }

    private function report(string $queueKey, string $text): void
    {
        if (!$this->sendReport($text)) {
            $this->outbox->enqueue($queueKey, [
                'kind' => 'report',
                'chat_id' => (string) $this->setting['Channel_Report'],
                'thread_id' => $this->reportThread,
                'text' => $text,
            ]);
        }
    }

    private function sendUser(string $key, $chatId, $botType, string $text): void
    {
        $entry = ['kind' => 'user', 'chat_id' => (string) $chatId, 'bottype' => $botType, 'text' => $text];
        if (!$this->outbox->sendOnce($key, $entry)) {
            $this->outbox->enqueue($key, $entry);
        }
    }

    public function recoverStale(): void
    {
        $this->now = time();
        ReservationExecution::reservationRecoverStale($this->pdo, $this->now);
    }

    public function processDueReservations(): void
    {
        $now = $this->now ?: time();
        [$w, $n] = CronRuntime::rx_cron_shard();
        $this->worker = (int) $w;
        $this->workers = (int) $n;
        $rows = ReservationExecution::reservationDueRows($this->pdo, $now, 30, $this->worker, $this->workers);

        foreach ($rows as $row) {
            if (CronRuntime::rx_cron_budget_exhausted()) {
                break;
            }
            $this->processReservation($row);
        }
    }

    private function processReservation(array $row): void
    {
        $outcome = ReservationExecution::reservationProcessRow($this->pdo, $this->panel, $row, time());
        $status = (string) ($outcome['outcome'] ?? '');
        if (!in_array($status, ['applied', 'failed', 'retry', 'reconciling'], true)) {
            return;
        }
        $fresh = ReservationRecovery::reservationFetch($this->pdo, (int) $row['id']) ?? $row;
        $reasonCode = preg_replace('/[^a-z_]/', '', strtolower((string) strtok((string) ($outcome['reason'] ?? ''), ':'))) ?: 'unknown';
        $notifyKey = $status . ':' . $reasonCode;
        if ((string) ($fresh['notified_state'] ?? '') === $notifyKey) {
            return;
        }
        $fulfillment = Claims::decode($row['fulfillment'] ?? null);
        $fulfillment = is_array($fulfillment) ? $fulfillment : [];
        $adminSource = ($fulfillment['source'] ?? '') === 'admin';
        $notifyCustomer = !$adminSource || ($fulfillment['notify_customer'] ?? true) !== false;
        $diagnostic = "<blockquote>شناسه رزرو : <code>{$row['id']}</code></blockquote>\n"
            . "<blockquote>نام کاربری سرویس : <code>" . self::esc($row['username']) . "</code></blockquote>\n"
            . "<blockquote>نام پنل : " . self::esc($row['name_panel']) . "</blockquote>\n"
            . "<blockquote>زمان فعال‌سازی : " . self::esc(Reservations::rxReservationTriggerLabel((string) ($row['trigger_type'] ?? 'either'), $row['trigger_at'] ?? null, ReservationCriteria::reservationRowConditions($row))) . "</blockquote>\n"
            . "<blockquote>سیاست اجرا : " . self::esc(RenewalText::rxRenewalPolicyLabel((string) ($row['policy'] ?? 'reset_all'), !empty($row['reset_usage']))) . "</blockquote>\n"
            . "<blockquote>تلاش : " . (int) ($fresh['attempt_count'] ?? 0) . "</blockquote>";
        $userKey = 'r' . (int) $row['id'] . ':' . $notifyKey . ':user';
        $reportKey = 'r' . (int) $row['id'] . ':' . $notifyKey . ':report';
        $hasCustomer = $notifyCustomer && (string) ($row['id_user'] ?? '') !== '';

        if ($status === 'applied') {
            $message = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_queued_renewal_activated_tpl', "با سلام خدمت شما کاربر گرامی 👋\n✅ رزرو اشتراک سرویس {username} فعال شد و حجم و زمان سرویس شما تمدید گردید."), [
                'username' => $row['username'],
            ]);
            if ($adminSource) {
                $message = RenewalText::rxRenewalText('adm_customer_activated', ['username' => self::esc($row['username'])]);
            }
            if (is_array($outcome['before'] ?? null) && is_array($outcome['after'] ?? null)) {
                $message .= "\n\n" . RenewalText::rxRenewalResultText($outcome['before'], $outcome['after']);
            }
            if ($hasCustomer) {
                $this->sendUser($userKey, $row['id_user'], $row['bottype'] ?? null, $message);
            }
            $after = is_array($outcome['after'] ?? null) ? $outcome['after'] : [];
            $applied = "📌 اطلاعیه فعال‌سازی رزرو اشتراک\n\n" . $diagnostic
                . (isset($after['data_limit']) ? "\n<blockquote>حجم کل جدید : " . self::esc(RenewalText::rxRenewalFormatBytes($after['data_limit'])) . "</blockquote>" : '')
                . (isset($after['expire']) ? "\n<blockquote>انقضای جدید : " . self::esc(RenewalText::rxRenewalFormatExpire($after['expire'])) . "</blockquote>" : '');
            $this->report($reportKey, $applied);
        } elseif ($status === 'failed') {
            if ($hasCustomer) {
                $this->sendUser($userKey, $row['id_user'], $row['bottype'] ?? null, RenewalText::rxRenewalText('reservation_failed_user_tpl', ['username' => $row['username']]));
            }
            $this->report($reportKey, "❌ خطای دائمی فعال‌سازی رزرو اشتراک (نیازمند بررسی دستی)\n\n" . $diagnostic . "\n<blockquote>دلیل : " . self::esc($outcome['reason'] ?? '') . "</blockquote>");
        } else {
            $title = $status === 'reconciling'
                ? "⚠️ نتیجه فعال‌سازی رزرو اشتراک نامشخص است و به‌صورت خودکار بررسی می‌شود"
                : ($reasonCode === 'admin_blocked'
                    ? "⛔️ رزرو اشتراک به دلیل غیرفعال بودن سرویس توسط مدیر اجرا نشد (تا رفع محدودیت منتظر می‌ماند)"
                    : "⚠️ خطای موقت فعال‌سازی رزرو اشتراک؛ تلاش مجدد خودکار");
            $this->report($reportKey, $title . "\n\n" . $diagnostic . "\n<blockquote>دلیل : " . self::esc($outcome['reason'] ?? '') . "</blockquote>");
        }
        ReservationExecution::reservationMarkNotified($this->pdo, (int) $row['id'], $notifyKey);
    }

    public function reconcileOperations(): void
    {
        if (CronRuntime::rx_cron_time_up() || $this->worker !== 0) {
            return;
        }
        foreach (ReservationRecovery::renewalOpsReconcile($this->pdo, $this->panel, time(), 20) as $result) {
            $op = is_array($result['op'] ?? null) ? $result['op'] : [];
            if ((string) ($result['outcome'] ?? '') !== 'verified') {
                continue;
            }
            $invoice = !empty($op['id_invoice']) ? Db::select("invoice", "*", "id_invoice", $op['id_invoice'], "select") : null;
            if (is_array($invoice) && (string) ($invoice['id_user'] ?? '') !== '' && (string) ($op['source'] ?? '') !== 'admin') {
                $text = RenewalText::rxRenewalText('reconciled_user_tpl', ['username' => self::esc($op['username'] ?? '')]);
                if (is_array($result['after'] ?? null) && ProviderRenewal::renewalSameLinkSupported(ProviderState::panelType((string) ($op['name_panel'] ?? '')))) {
                    $text .= "\n\n" . trim(RenewalText::rxRenewalText('success_same_link'));
                }
                $this->sendUser('op:' . (string) ($op['op_id'] ?? '') . ':user', $invoice['id_user'], $invoice['bottype'] ?? null, $text);
            }
            $this->report('op:' . (string) ($op['op_id'] ?? '') . ':report', "✅ تمدید نامشخص پس از بررسی خودکار تأیید شد\n\n<blockquote>شناسه عملیات : <code>" . self::esc($op['op_id'] ?? '') . "</code></blockquote>\n<blockquote>نام کاربری سرویس : <code>" . self::esc($op['username'] ?? '') . "</code></blockquote>\n<blockquote>نام پنل : " . self::esc($op['name_panel'] ?? '') . "</blockquote>");
        }
    }

    public function recoverWalletFinancials(): void
    {
        if ($this->worker !== 0 || CronRuntime::rx_cron_budget_exhausted()) {
            return;
        }
        try {
            $cursor = (int) CronBatches::rx_cron_invoice_cursor('wallet_financial_recovery');
            $cursor = WalletRecovery::scan(
                $this->pdo, $this->panel, $cursor, [CronRuntime::class, 'rx_cron_budget_exhausted'],
                static fn (string $panel) => $panel === '' ? null : Db::select('marzban_panel', '*', 'name_panel', $panel, 'select'),
                static function (array $row, array $details) {
                    $invoice = (string) $row['value'];
                    if ($row['username'] === 'report') {
                        return telegram('sendmessage', [
                            'chat_id' => $details['chat'], 'message_thread_id' => $details['topic'] ?: null,
                            'text' => '✅ بازیابی خرید اشتراک. کد پیگیری: <code>' . self::esc($invoice) . '</code>',
                            'parse_mode' => 'HTML', '_rx_no_network_retry' => true,
                        ]);
                    }
                    return telegram('sendmessage', [
                        'chat_id' => (string) $row['id_user'],
                        'text' => \Faoxima\Telegram\Support\MessageTemplates::get('notify_renewal_activated', [], ['invoice' => self::esc($invoice)]),
                        'reply_markup' => json_encode(['inline_keyboard' => [[
                            ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('notify_btn_view_service'), 'callback_data' => 'product_' . $invoice],
                        ]]], JSON_UNESCAPED_UNICODE),
                        'parse_mode' => 'HTML', '_rx_no_network_retry' => true,
                    ], $row['bottype'] ?? null);
                }
            );
            CronBatches::rx_cron_invoice_cursor('wallet_financial_recovery', (string) $cursor);
        } catch (Throwable $error) {
            error_log('[queued_renewal] wallet recovery: ' . $error->getMessage());
        }
    }
}
