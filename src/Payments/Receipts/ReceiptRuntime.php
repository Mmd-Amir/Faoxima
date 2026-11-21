<?php

namespace Faoxima\Payments\Receipts;

use DateTime;
use DateTimeZone;
use PDO;
use Throwable;

final class ReceiptRuntime
{
    public static function rx_sbv_pdo(): ?PDO
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::pdo($pdo);
    }

    public static function rx_sbv_ensure_schema(): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::ensureSchema($pdo);
    }

    public static function rx_sbv_has_schema(): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::hasSchema($pdo);
    }

    public static function rx_sbv_setting_get(string $name, ?string $default = null): ?string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::settingGet($pdo, $name, $default);
    }

    public static function rx_sbv_setting_set(string $name, string $value): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::settingSet($pdo, $name, $value);
    }

    public static function rx_sbv_api_key(): string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::apiKey($pdo);
    }

    public static function rx_sbv_lifetime_minutes(): int
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::lifetimeMinutes($pdo);
    }

    public static function rx_sbv_set_lifetime_minutes(int $minutes): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::setLifetimeMinutes($pdo, $minutes);
    }

    public static function rx_sbv_suggestion_range_toman(): int
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::suggestionRangeToman($pdo);
    }

    public static function rx_sbv_set_suggestion_range_toman(int $toman): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::setSuggestionRangeToman($pdo, $toman);
    }

    public static function rx_sbv_enable(): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::enable($pdo);
    }

    public static function rx_sbv_disable(): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::disable($pdo);
    }

    public static function rx_sbv_regenerate_key(): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::regenerateKey($pdo);
    }

    public static function rx_sbv_webhook_url(): string
    {
        $host = isset($GLOBALS['domainhosts']) ? trim((string) $GLOBALS['domainhosts']) : '';
        $host = rtrim((string) preg_replace('#^https?://#i', '', $host), '/');
        return $host === '' ? '' : 'https://' . $host . '/payment/forapp.php';
    }

    public static function rx_sbv_clock(int $ts, bool $withDate = false): string
    {
        try {
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
            if ($withDate && function_exists('jdate')) {
                return jdate('Y/m/d H:i', $ts);
            }
            return $dt->format($withDate ? 'Y/m/d H:i' : 'H:i');
        } catch (Throwable $e) {
            return date($withDate ? 'Y/m/d H:i' : 'H:i', $ts);
        }
    }

    public static function rx_sbv_money($value): string
    {
        return number_format((int) $value);
    }

    public static function rx_sbv_connection_status(): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Settings::connectionStatus($pdo);
    }

    public static function rx_sbv_audit(?string $orderId, ?int $depositId, ?string $actor, string $action, array $data = []): void
    {
        global $pdo;
        \Faoxima\Payments\Receipts\ReceiptOrders::audit($pdo, $orderId, $depositId, $actor, $action, $data);
    }

    public static function rx_sbv_get(string $orderId, bool $lock = false): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::get($pdo, $orderId, $lock);
    }

    public static function rx_sbv_is_verify_order(string $orderId): bool
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::isVerifyOrder($pdo, $orderId);
    }

    public static function rx_sbv_deposit(int $depositId, bool $lock = false): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::deposit($pdo, $depositId, $lock);
    }

    public static function rx_sbv_payment_report(string $orderId): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::paymentReport($pdo, $orderId);
    }

    public static function rx_sbv_active_payment($userId): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::activePayment($pdo, $userId);
    }

    public static function rx_sbv_pending_message(): string
    {
        return 'شما یک پرداخت ناتمام دارید. برای ایجاد پرداخت جدید، ابتدا پرداخت قبلی را تکمیل یا لغو کنید.';
    }

    public static function rx_sbv_public_view(string $orderId, ?array $csp = null): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptOrders::publicView($pdo, $orderId, $csp);
    }

    public static function rx_sbv_expired_from_values(string $state, string $status, string $resolution, int $expiresAt, int $directDone = 0): bool
    {
        return \Faoxima\Payments\Receipts\ReceiptOrders::expiredFromValues($state, $status, $resolution, $expiresAt, $directDone);
    }

    public static function rx_sbv_payment_is_expired(array $csp, ?array $report = null): bool
    {
        return \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
    }

    public static function rx_sbv_payment_block(array $view): string
    {
        return "\n\n🔐 <b>تأیید خودکار واریز فعال است</b>\n"
            . "<blockquote>💰 مبلغ سفارش/شارژ: " . self::rx_sbv_money($view['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . self::rx_sbv_money($view['fee_toman']) . " تومان\n"
            . "✅ مبلغ دقیق قابل واریز: <b>" . self::rx_sbv_money($view['payable_toman']) . " تومان</b>\n"
            . "🔢 معادل ریالی: <code>" . (int) $view['payable_rial'] . "</code> ریال</blockquote>\n"
            . "⚠️ دقیقاً همین مبلغ را یکجا واریز کنید؛ مبلغ کمتر، بیشتر یا چند واریز جداگانه به‌صورت خودکار تأیید نمی‌شود.\n"
            . "ℹ️ کارمزد شناسایی جزو شارژ کیف پول یا مبلغ سفارش نیست و بازگردانده نمی‌شود.\n"
            . "⏳ مهلت پرداخت تا ساعت <b>" . $view['expires_at_clock'] . "</b>\n"
            . "🤖 پس از واریز، پرداخت به‌صورت خودکار بررسی و تأیید می‌شود.\n"
            . "📤 اگر تا ساعت " . $view['receipt_after_clock'] . " تأیید نشد، می‌توانید رسید را ارسال کنید.";
    }

    public static function rx_sbv_bot_payment_view(string $orderId): ?array
    {
        $view = self::rx_sbv_public_view($orderId);
        if ($view === null) {
            return null;
        }
        return [
            'view' => $view,
            'valueprice' => self::rx_sbv_money($view['payable_toman']),
            'price_copy' => (string) $view['payable_rial'],
            'block' => self::rx_sbv_payment_block($view),
            'receipt_label' => '📤 ارسال رسید (از ساعت ' . $view['receipt_after_clock'] . ')',
            'copy_label' => 'کپی مبلغ به ریال',
        ];
    }

    public static function rx_sbv_cashback(array $report): int
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptAudit::cashback($pdo, $report);
    }

    public static function rx_sbv_report(string $text, $keyboard = null, array $options = []): array
    {
        if (!\Faoxima\Core\Database\Db::runtimeReady() || !function_exists('telegram')) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'telegram_unavailable'];
        }
        try {
            $setting = \Faoxima\Core\Database\Db::select('setting', '*');
            $channel = is_array($setting) ? trim((string) ($setting['Channel_Report'] ?? '')) : '';
            if ($channel === '') {
                return ['state' => 'failed', 'ok' => false, 'error' => 'report_channel_missing'];
            }
            $topic = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'paymentreport', 'select');
            $payload = [
                'chat_id' => $channel,
                'message_thread_id' => is_array($topic) ? ($topic['idreport'] ?? null) : null,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];
            if (!empty($options['no_network_retry'])) {
                $payload['_rx_no_network_retry'] = true;
            }
            if ($keyboard !== null && $keyboard !== '') {
                $payload['reply_markup'] = $keyboard;
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REPORT_FAILED', $e->getMessage());
            return ['state' => 'failed', 'ok' => false, 'error' => $e->getMessage()];
        }
        try {
            $response = telegram('sendmessage', $payload);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REPORT_FAILED', $e->getMessage());
            return ['state' => 'uncertain', 'ok' => false, 'error' => $e->getMessage(), 'uncertain' => true];
        }
        if (!is_array($response)) {
            return ['state' => 'uncertain', 'ok' => false, 'error' => 'invalid_telegram_response', 'uncertain' => true];
        }
        if (($response['ok'] ?? null) === true) {
            $result = is_array($response['result'] ?? null) ? $response['result'] : [];
            $chatId = $result['chat']['id'] ?? $channel;
            $messageId = isset($result['message_id']) ? (int) $result['message_id'] : 0;
            if ($messageId <= 0) {
                return ['state' => 'uncertain', 'ok' => false, 'error' => 'missing_telegram_message_id', 'uncertain' => true];
            }
            return [
                'state' => 'sent',
                'ok' => true,
                'chat_id' => (string) $chatId,
                'message_id' => $messageId,
                'response' => $response,
            ];
        }
        $errorCode = (int) ($response['error_code'] ?? 0);
        $error = (string) ($response['description'] ?? 'telegram_send_failed');
        $definitiveFailure = ($response['ok'] ?? null) === false
            && empty($response['_rx_invalid_response'])
            && $errorCode >= 400 && $errorCode < 500;
        $state = $definitiveFailure ? 'failed' : 'uncertain';
        return [
            'state' => $state,
            'ok' => false,
            'error' => $error,
            'error_code' => $errorCode,
            'uncertain' => $state === 'uncertain',
            'response' => $response,
        ];
    }

    public static function rx_sbv_deliver_tracked_report(string $action, ?int $depositId, ?string $orderId, ?string $actor, string $text, $keyboard = null, array $meta = []): array
    {
        $pdo = self::rx_sbv_pdo();
        if ($pdo === null) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'db_unavailable'];
        }
        $lockName = 'rx_sbv_rpt_' . md5($action . '|' . ($depositId ?? '') . '|' . ($orderId ?? ''));
        $locked = false;
        try {
            $locked = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn() === 1;
        } catch (Throwable $e) {
            $locked = false;
        }
        if (!$locked) {
            return ['state' => 'busy', 'ok' => false, 'error' => 'report_lock_busy'];
        }
        $markerId = null;
        $attempts = 1;
        $data = [];
        try {
            $marker = \Faoxima\Payments\Receipts\ReceiptAudit::reportMarkerFetch($pdo, $action, $depositId, $orderId);
            if (is_array($marker)) {
                $decoded = json_decode((string) ($marker['reason'] ?? ''), true);
                $existing = is_array($decoded) ? $decoded : [];
                $markerState = (string) ($marker['outcome'] ?? ($existing['state'] ?? ''));
                if ($markerState === 'sent') {
                    return [
                        'state' => 'sent',
                        'ok' => true,
                        'already_sent' => true,
                        'chat_id' => (string) ($existing['chat_id'] ?? ''),
                        'message_id' => (int) ($existing['message_id'] ?? 0),
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                if ($markerState === 'sending' || $markerState === 'uncertain') {
                    return [
                        'state' => 'uncertain',
                        'ok' => false,
                        'uncertain' => true,
                        'error' => (string) ($existing['error'] ?? 'delivery_uncertain'),
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                if (!in_array($markerState, ['failed'], true)) {
                    return [
                        'state' => 'uncertain',
                        'ok' => false,
                        'uncertain' => true,
                        'error' => 'legacy_delivery_unknown',
                        'marker_id' => (int) $marker['id'],
                    ];
                }
                $markerId = (int) $marker['id'];
                $attempts = max(1, (int) ($existing['attempts'] ?? 0) + 1);
            }
            $data = array_merge($meta, [
                'state' => 'sending',
                'attempts' => $attempts,
                'started_at' => time(),
                'updated_at' => time(),
                'action' => $action,
                'deposit_id' => $depositId,
                'order_id' => $orderId,
                'text_hash' => hash('sha256', $text),
            ]);
            $markerId = \Faoxima\Payments\Receipts\ReceiptAudit::reportMarkerSave($pdo, $markerId, $action, $depositId, $orderId, $actor, $data);
            if ($markerId === null) {
                return ['state' => 'failed', 'ok' => false, 'error' => 'report_tracking_failed'];
            }
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REPORT_TRACKING_FAILED', $e->getMessage(), ['action' => $action, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'report_tracking_failed'];
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
            } catch (Throwable $e) {
            }
        }
        $delivery = self::rx_sbv_report($text, $keyboard, ['no_network_retry' => true]);
        $final = array_merge($data, [
            'state' => (string) ($delivery['state'] ?? 'uncertain'),
            'updated_at' => time(),
            'chat_id' => $delivery['chat_id'] ?? null,
            'message_id' => $delivery['message_id'] ?? null,
            'error' => $delivery['error'] ?? null,
            'error_code' => $delivery['error_code'] ?? null,
        ]);
        \Faoxima\Payments\Receipts\ReceiptAudit::reportMarkerSave($pdo, $markerId, $action, $depositId, $orderId, $actor, $final);
        return array_merge($delivery, ['marker_id' => $markerId, 'attempts' => $attempts]);
    }

    public static function rx_sbv_finalize(string $orderId, string $state, string $resolution, ?string $actor, ?string $note): void
    {
        global $pdo;
        \Faoxima\Payments\Receipts\Recovery::finalize($pdo, $orderId, $state, $resolution, $actor, $note);
    }

    public static function rx_sbv_after_approval(string $orderId, string $resolution, string $actor, ?string $reason, bool $withCashback): void
    {
        $csp = self::rx_sbv_get($orderId);
        $outcome = self::rx_sbv_fulfillment_outcome($orderId);
        $special = is_array($outcome) && in_array($outcome['outcome'], ['refunded', 'wallet_credit'], true) ? (string) $outcome['outcome'] : null;
        $isAuto = $resolution === 'auto';
        if ($special !== null) {
            $resolution .= $special === 'refunded' ? '_refund' : '_wallet_credit';
            $withCashback = false;
        }
        self::rx_sbv_finalize($orderId, $special !== null ? 'credited' : 'approved', $resolution, $actor, $reason);
        $report = self::rx_sbv_payment_report($orderId);
        if (!is_array($report) || $csp === null) {
            return;
        }
        $cashback = $withCashback ? self::rx_sbv_cashback($report) : 0;
        self::rx_sbv_audit($orderId, $csp['deposit_id'] !== null ? (int) $csp['deposit_id'] : null, $actor, $isAuto ? 'auto_approve' : 'admin_approve', [
            'principal_toman' => (int) $csp['principal_toman'],
            'fee_toman' => (int) $csp['fee_toman'],
            'expected_rial' => (int) $csp['payable_rial'],
            'received_rial' => $csp['received_rial'] !== null ? (int) $csp['received_rial'] : null,
            'credit_toman' => $special !== null ? (int) ($outcome['amount'] ?? 0) : (int) $csp['principal_toman'],
            'outcome' => $resolution,
            'reason' => $reason,
        ]);
        $user = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]) : null;
        $uname = is_array($user) ? (string) ($user['username'] ?? '') : '';
        $kind = $isAuto ? 'تأیید خودکار با بررسی پیامک بانکی' : 'تأیید ادمین (پرداخت با بررسی)';
        if ($special === 'refunded') {
            $kind .= ' — سفارش تکمیل نشد و اصل مبلغ به کیف پول بازگشت';
        } elseif ($special === 'wallet_credit') {
            $kind .= ' — سفارش تکمیل نشد و اصل مبلغ به کیف پول واریز شد';
        }
        $text = "📣 پرداخت کارت‌به‌کارت تأیید شد.\n\n"
            . "<blockquote>🤖 نوع تایید : {$kind}</blockquote>\n"
            . "<blockquote>👤 آیدی عددی کاربر : <code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code></blockquote>\n"
            . "<blockquote>👤 نام کاربری کاربر : @" . htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') . "</blockquote>\n"
            . "<blockquote>💰 مبلغ اصل سفارش/شارژ : " . self::rx_sbv_money($csp['principal_toman']) . " تومان</blockquote>\n"
            . "<blockquote>🏷 کارمزد شناسایی : " . self::rx_sbv_money($csp['fee_toman']) . " تومان</blockquote>\n"
            . "<blockquote>✅ مبلغ قابل پرداخت : " . self::rx_sbv_money($csp['payable_toman']) . " تومان</blockquote>\n"
            . "<blockquote>📥 مبلغ دریافتی : " . ($csp['received_rial'] !== null ? self::rx_sbv_money(intdiv((int) $csp['received_rial'], 10)) . ' تومان' : 'ثبت نشده') . "</blockquote>\n"
            . "<blockquote>کد پیگیری پرداخت : <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code></blockquote>"
            . ($cashback > 0 ? "\n<blockquote>🎁 کش‌بک : " . self::rx_sbv_money($cashback) . " تومان</blockquote>" : '')
            . ($reason !== null && $reason !== '' ? "\n<blockquote>✍️ دلیل : " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</blockquote>" : '')
            . ($actor !== 'system' ? "\n<blockquote>👤 ادمین : <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code></blockquote>" : '');
        $delivered = (int) ($report['report_message_id'] ?? 0) > 0 || trim((string) ($report['private_receipt_targets'] ?? '')) !== '';
        if ($delivered && method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxReceiptSyncTelegram')) {
            $kb = json_encode(['inline_keyboard' => [[['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $report['id_user']]]]], JSON_UNESCAPED_UNICODE);
            \Faoxima\Payments\Receipts\ReceiptActions::rxReceiptSyncTelegram($report, $text, $kb);
        } else {
            self::rx_sbv_report($text);
        }
    }

    public static function rx_sbv_fulfillment_outcome(string $orderId): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Recovery::fulfillmentOutcome($pdo, $orderId);
    }

    public static function rx_sbv_confirm_guard(string $orderId, string $reason): ?string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReviewQueries::confirmGuard($pdo, $orderId, $reason);
    }

    public static function rx_sbv_payment_reject_guard(string $orderId): ?string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReviewQueries::paymentRejectGuard($pdo, $orderId);
    }

    public static function rx_sbv_payment_link_guard(string $orderId, int $depositId = 0): ?string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReviewQueries::paymentLinkGuard($pdo, $orderId, $depositId);
    }

    public static function rx_sbv_match_failure_label(string $state, string $note): string
    {
        $raw = trim($note);
        $code = strpos($raw, ':') !== false ? substr($raw, 0, strpos($raw, ':')) : $raw;
        $map = [
            'no_matching_payment' => 'هیچ پرداخت فعالی با مبلغ دقیق این واریز پیدا نشد.',
            'multiple_candidates' => 'چند پرداخت فعال با مبلغ دقیق این واریز وجود داشت؛ تطبیق خودکار برای جلوگیری از انتخاب اشتباه متوقف شد.',
            'exact_amount_inactive_payment' => 'مبلغ دقیق با یک پرداخت غیرفعال یا خارج از مهلت برابر بود.',
            'sms_predates_payment' => 'زمان پیامک بانکی پیش از ایجاد پرداخت متناظر است.',
            'order_not_payable' => 'پرداخت متناظر دیگر در وضعیت قابل پرداخت نیست.',
            'candidate_changed' => 'وضعیت پرداخت کاندید هنگام تطبیق هم‌زمان تغییر کرد.',
            'invalid_amount' => 'مبلغ واریز نامعتبر یا صفر است.',
            'not_eligible' => 'واریز برای تطبیق خودکار واجد شرایط نیست.',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        if ($state === 'ambiguous') {
            return 'چند پرداخت هم‌مبلغ فعال بود و انتخاب خودکار امن نیست.';
        }
        if ($state === 'late') {
            return 'مبلغ واریز به یک پرداخت غیرفعال یا منقضی نزدیک است.';
        }
        return 'مبلغ یا زمان واریز با هیچ پرداخت فعالی تطبیق خودکار پیدا نکرد.';
    }

    public static function rx_sbv_report_candidates(int $depositId, int $limit = 8, int $page = 0, ?string $orderId = null): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReviewQueries::reportCandidates($pdo, $depositId, $limit, $page, $orderId);
    }

    public static function rx_sbv_order_deposit_candidates(string $orderId, int $limit = 5, int $page = 0, ?int $depositId = null): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReviewQueries::orderDepositCandidates($pdo, $orderId, $limit, $page, $depositId);
    }

    public static function rx_sbv_candidate_line(array $candidate, int $number, ?int $amountRial): string
    {
        $expired = !empty($candidate['candidate_expired']);
        $status = $expired ? 'منقضی‌شده — احتمال ضعیف‌تر؛ فقط بررسی دستی' : \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_status_label((string) ($candidate['report_status'] ?? ''));
        $name = trim((string) ($candidate['namecustom'] ?? ''));
        if ($name === 'none') {
            $name = '';
        }
        $username = trim((string) ($candidate['username'] ?? ''));
        $display = $name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام');
        $source = (string) ($candidate['report_source'] ?? '') === 'miniapp' ? 'مینی‌اپ' : 'ربات';
        $diff = $amountRial === null ? null : $amountRial - (int) $candidate['payable_rial'];
        $diffLabel = $diff === null ? '—' : (self::rx_sbv_money(intdiv(abs($diff), 10)) . ' تومان ' . ($diff < 0 ? 'کمتر' : ($diff > 0 ? 'بیشتر' : 'بدون اختلاف')));
        $amountToman = $amountRial === null ? 0 : intdiv($amountRial, 10);
        $explain = '';
        if ($amountRial !== null && (int) $candidate['principal_toman'] === $amountToman && (int) $candidate['payable_rial'] > $amountRial) {
            $explain = 'پرداخت احتمالی با اصل مبلغ برابر است؛ کارمزد شناسایی در واریز لحاظ نشده است.';
        } elseif ($diff !== null && $diff < 0) {
            $explain = 'مبلغ واریز از مبلغ مورد انتظار کمتر است.';
        } elseif ($diff !== null && $diff > 0) {
            $explain = 'مبلغ واریز از مبلغ مورد انتظار بیشتر است.';
        } else {
            $explain = 'مبلغ واریز با مبلغ مورد انتظار برابر است.';
        }
        return "<b>" . $number . ") سفارش <code>" . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($candidate['id_order']) . "</code></b>\n"
            . "👤 <code>" . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($candidate['id_user']) . "</code> | " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($display) . "\n"
            . "📦 " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_invoice_label((string) ($candidate['report_invoice'] ?? ''))) . " | منبع: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($source) . "\n"
            . "💰 اصل: " . self::rx_sbv_money($candidate['principal_toman']) . " تومان | کارمزد: " . self::rx_sbv_money($candidate['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . self::rx_sbv_money(intdiv((int) $candidate['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ واریز: " . ($amountRial !== null ? self::rx_sbv_money($amountToman) . ' تومان' : '—') . "\n"
            . "➖ اختلاف: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($diffLabel) . "\n"
            . "📅 ایجاد: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(self::rx_sbv_clock((int) $candidate['created_at'], true)) . " | مهلت: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(self::rx_sbv_clock((int) $candidate['expires_at'], true)) . "\n"
            . "📌 وضعیت: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($status) . "\n"
            . "💡 " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($explain);
    }

    public static function rx_sbv_report_unmatched_deposit(int $depositId): bool
    {
        $deposit = self::rx_sbv_deposit($depositId);
        $pdo = self::rx_sbv_pdo();
        if (!is_array($deposit) || $pdo === null || !in_array((string) $deposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
            return false;
        }
        try {
            $marker = \Faoxima\Payments\Receipts\ReceiptAudit::reportMarkerFetch($pdo, 'deposit_review_report', $depositId, null);
        } catch (Throwable $e) {
            return false;
        }
        if (is_array($marker)) {
            $markerData = json_decode((string) ($marker['reason'] ?? ''), true);
            $markerData = is_array($markerData) ? $markerData : [];
            $markerState = (string) ($marker['outcome'] ?? ($markerData['state'] ?? ''));
            if ($markerState === 'sent') {
                return true;
            }
            if ($markerState !== 'failed') {
                return false;
            }
        }
        $lockName = 'rx_sbv_report_' . $depositId;
        $locked = false;
        $text = null;
        $keyboard = null;
        try {
            $locked = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn() === 1;
        } catch (Throwable $e) {
            $locked = false;
        }
        if (!$locked) {
            return false;
        }
        try {
            $freshDeposit = self::rx_sbv_deposit($depositId);
            if (!is_array($freshDeposit) || !in_array((string) $freshDeposit['state'], ['unmatched', 'ambiguous', 'late'], true)) {
                return false;
            }
            $deposit = $freshDeposit;
            $candidateResult = self::rx_sbv_report_candidates($depositId, 30);
            $activeCandidates = (array) ($candidateResult['active'] ?? []);
            $expiredCandidates = (array) ($candidateResult['expired'] ?? []);
            $candidateSearchOk = !empty($candidateResult['ok']);
            $candidateHasMore = !empty($candidateResult['has_more']) || count($activeCandidates) > 3 || count($expiredCandidates) > 2;
            $amountRial = $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null;
            $text = "⚠️ <b>واریز نیازمند بررسی ادمین</b>\n\n"
                . "<blockquote>🆔 شناسه واریز: <code>" . (int) $deposit['id'] . "</code>\n"
                . "💵 مبلغ دریافتی: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(\Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_toman_from_rial($amountRial)) . "\n"
                . "🏦 بانک: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h($deposit['bank'] ?? '—') . "\n"
                . "🕒 زمان دریافت: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h((string) ($deposit['received_at_raw'] ?? '—')) . "\n"
                . "🕒 ثبت در سرور: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(self::rx_sbv_clock((int) $deposit['stored_at'], true)) . "\n"
                . "👤 صاحب واریز: هنوز مشخص نیست\n"
                . "📌 وضعیت: منتظر بررسی ادمین\n"
                . "🧩 دلیل عدم تطبیق خودکار: " . \Faoxima\Admin\Telegram\ReceiptVerificationAdmin::rx_sbv_h(self::rx_sbv_match_failure_label((string) $deposit['state'], (string) ($deposit['note'] ?? ''))) . "</blockquote>\n";
            if (!$candidateSearchOk) {
                $text .= "\n🔍 جست‌وجوی پیشنهاد پرداخت با خطا مواجه شد. این واریز به هیچ کاربری نسبت داده نمی‌شود و بررسی دستی لازم است.";
            } elseif ($activeCandidates || $expiredCandidates) {
                $text .= "\n🔍 <b>پرداخت‌های احتمالی — صاحب واریز هنوز تأیید نشده است</b>\n";
                $shown = 0;
                if ($activeCandidates) {
                    $text .= "\n<b>پرداخت‌های فعال / در انتظار</b>\n";
                    foreach (array_slice($activeCandidates, 0, 3) as $candidate) {
                        $shown++;
                        $text .= "\n" . self::rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                    }
                }
                if ($expiredCandidates) {
                    $text .= "\n<b>پرداخت‌های منقضی‌شده — احتمال ضعیف‌تر؛ نیازمند بررسی ادمین</b>\n";
                    foreach (array_slice($expiredCandidates, 0, 2) as $candidate) {
                        $shown++;
                        $text .= "\n" . self::rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                    }
                }
                if ($candidateHasMore) {
                    $text .= "\nℹ️ تعداد بیشتری پرداخت احتمالی وجود دارد. از «جزئیات واریز ← پرداخت‌های احتمالی» همه موارد را در پنل خصوصی بررسی کنید.";
                }
                $text .= "\n⚠️ این موارد فقط پیشنهاد هستند و هیچ پرداخت یا کاربری به‌صورت خودکار متصل، تأیید یا رد نمی‌شود.";
            } else {
                $text .= "\n🔍 جست‌وجوی پیشنهاد انجام شد اما هیچ پرداخت فعال یا منقضی‌شده‌ای با مبلغ و زمان نزدیک پیدا نشد.";
            }
            $text .= "\n\n📌 تصمیم نهایی فقط با بررسی ادمین ثبت می‌شود.";
            $keyboard = json_encode(['inline_keyboard' => [
                [['text' => '🔗 اتصال به پرداخت', 'callback_data' => 'sbv_dl_' . (int) $deposit['id']]],
                [['text' => '🔍 پرداخت‌های احتمالی', 'callback_data' => 'sbv_ds_' . (int) $deposit['id']]],
                [['text' => '🗑 حذف واریز', 'callback_data' => 'sbv_dd_' . (int) $deposit['id']]],
                [['text' => '🔎 جزئیات واریز', 'callback_data' => 'sbv_d_' . (int) $deposit['id']]],
                [['text' => '📥 صف بررسی', 'callback_data' => 'sbv_q_0']],
            ]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REVIEW_REPORT_FAILED', $e->getMessage(), ['deposit_id' => $depositId]);
            $text = null;
            $keyboard = null;
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
            } catch (Throwable $e) {
            }
        }
        if ($text === null) {
            return false;
        }
        $delivery = self::rx_sbv_deliver_tracked_report('deposit_review_report', $depositId, null, 'system', $text, $keyboard, [
            'received_rial' => $amountRial,
            'deposit_state' => (string) $deposit['state'],
            'reason' => (string) ($deposit['note'] ?? ''),
        ]);
        return (string) ($delivery['state'] ?? '') === 'sent';
    }

    public static function rx_sbv_order_summary(string $orderId): ?array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Approval::orderSummary($pdo, $orderId);
    }

    public static function rx_sbv_admin_approve(string $orderId, string $actor, string $reason, string $image = 'images.jpg'): array
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Receipts\Approval::adminApprove($pdo, $setting, $orderId, $actor, $reason, $image);
    }

    public static function rx_sbv_admin_set_received(string $orderId, string $actor, int $receivedToman, string $reason = ''): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Approval::adminSetReceived($pdo, $orderId, $actor, $receivedToman, $reason);
    }

    public static function rx_sbv_admin_credit(string $orderId, string $actor, string $reason): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Approval::adminCredit($pdo, $orderId, $actor, $reason);
    }

    public static function rx_sbv_delete_deposit(int $depositId, string $actor): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptRetention::deleteDeposit($pdo, $depositId, $actor);
    }

    public static function rx_sbv_expired_payment_delete_guard(string $orderId, ?array $csp = null, ?array $report = null, ?int $depositId = null): ?string
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptRetention::expiredPaymentDeleteGuard($pdo, $orderId, $csp, $report, $depositId);
    }

    public static function rx_sbv_admin_delete_expired_payment(string $orderId, string $actor, ?int $depositId = null): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\ReceiptRetention::adminDeleteExpiredPayment($pdo, $orderId, $actor, $depositId);
    }

    public static function rx_sbv_rejection_report_payload(string $orderId, ?int $depositId, string $actor, string $reason, int $decisionAt): ?array
    {
        $csp = self::rx_sbv_get($orderId);
        $report = self::rx_sbv_payment_report($orderId);
        if ($csp === null || !is_array($report)) {
            return null;
        }
        $deposit = $depositId !== null ? self::rx_sbv_deposit($depositId) : null;
        $receivedRial = $csp['received_rial'] !== null ? (int) $csp['received_rial'] : (is_array($deposit) && $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null);
        $user = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $report['id_user'], 'select', ['cache' => false]) : null;
        $name = is_array($user) ? trim((string) ($user['first_name'] ?? '')) : '';
        $username = is_array($user) ? trim((string) ($user['username'] ?? '')) : '';
        $text = "❌ <b>رد درخواست پرداخت کارت‌به‌کارت</b>\n\n"
            . "<blockquote>🛒 کد پیگیری: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "👤 کاربر: " . htmlspecialchars($name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام'), ENT_QUOTES, 'UTF-8') . " (<code>" . htmlspecialchars((string) $report['id_user'], ENT_QUOTES, 'UTF-8') . "</code>)\n"
            . "👤 ادمین: <code>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "🆔 شناسه واریز: " . ($depositId !== null ? '<code>' . $depositId . '</code>' : '—') . "\n"
            . "💰 اصل مبلغ: " . self::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . self::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . self::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ دریافتی: " . ($receivedRial !== null ? self::rx_sbv_money(intdiv($receivedRial, 10)) . ' تومان' : 'ثبت نشده') . "\n"
            . "✍️ دلیل: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "\n"
            . "🕒 زمان تصمیم: " . self::rx_sbv_clock($decisionAt, true) . "\n"
            . "📌 تصمیم: رد درخواست پرداخت؛ رکورد واریز بانکی حفظ شد و وجهی بازگشت یا به کیف پول اضافه نشد.</blockquote>";
        $kbRows = [[['text' => '🔎 مشاهده سفارش', 'callback_data' => 'sbv_oo_' . $orderId]]];
        if ($depositId !== null) {
            $kbRows[] = [['text' => '📨 جزئیات واریز', 'callback_data' => 'sbv_d_' . $depositId]];
        }
        $keyboard = json_encode(['inline_keyboard' => $kbRows], JSON_UNESCAPED_UNICODE);
        return [
            'text' => $text,
            'keyboard' => is_string($keyboard) ? $keyboard : null,
            'meta' => [
                'principal_toman' => (int) $csp['principal_toman'],
                'fee_toman' => (int) $csp['fee_toman'],
                'expected_rial' => (int) $csp['payable_rial'],
                'received_rial' => $receivedRial,
                'credit_toman' => null,
                'customer_id' => (string) $report['id_user'],
                'order_id' => $orderId,
                'deposit_id' => $depositId,
                'admin_id' => $actor,
                'reason' => $reason,
                'decision_at' => $decisionAt,
            ],
        ];
    }

    public static function rx_sbv_send_rejection_report(string $orderId, ?int $depositId, string $actor, string $reason, int $decisionAt): array
    {
        try {
            $payload = self::rx_sbv_rejection_report_payload($orderId, $depositId, $actor, $reason, $decisionAt);
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REJECTION_REPORT_BUILD_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_build_failed'];
        }
        if ($payload === null) {
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_data_unavailable'];
        }
        try {
            return self::rx_sbv_deliver_tracked_report('admin_reject_report', $depositId, $orderId, $actor, (string) $payload['text'], $payload['keyboard'] ?? null, (array) ($payload['meta'] ?? []));
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_REJECTION_REPORT_DELIVERY_FAILED', $e->getMessage(), ['id_order' => $orderId, 'deposit_id' => $depositId]);
            return ['state' => 'failed', 'ok' => false, 'error' => 'rejection_report_delivery_failed'];
        }
    }

    public static function rx_sbv_admin_reject(string $orderId, string $actor, string $reason, bool $block, ?int $expectedDepositId = null): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\Rejection::adminReject($pdo, $orderId, $actor, $reason, $block, $expectedDepositId);
    }

    public static function rx_sbv_link(int $depositId, string $orderId, string $actor): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\DepositMatching::link($pdo, $depositId, $orderId, $actor);
    }

    public static function rx_sbv_dismiss(int $depositId, string $actor, string $reason): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\DepositMatching::dismiss($pdo, $depositId, $actor, $reason);
    }

    public static function rx_sbv_review_queue(int $offset, int $limit): array
    {
        global $pdo;
        return \Faoxima\Payments\Receipts\DepositMatching::reviewQueue($pdo, $offset, $limit);
    }

    public static function rx_sbv_receipt_admin_note(string $orderId): string
    {
        $csp = self::rx_sbv_get($orderId);
        if ($csp === null) {
            return '';
        }
        $state = (string) $csp['state'];
        $match = $state === 'matched' ? 'واریز تطبیق داده شده، در حال تأیید خودکار' : ($csp['deposit_id'] !== null ? 'واریز متصل شده (نیازمند تصمیم ادمین)' : 'هنوز واریز تطبیق‌یافته‌ای دریافت نشده');
        return "\n\n🔐 <b>پرداخت با بررسی</b>\n"
            . "<blockquote>💰 اصل مبلغ: " . self::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . self::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ قابل پرداخت: " . self::rx_sbv_money($csp['payable_toman']) . " تومان (" . (int) $csp['payable_rial'] . " ریال)\n"
            . "⏳ مهلت: " . self::rx_sbv_clock((int) $csp['expires_at'], true) . "\n"
            . "🔎 وضعیت تطبیق: " . $match . "</blockquote>";
    }

    public static function rx_sbv_receipt_keyboard($keyboardJson, string $orderId)
    {
        if (self::rx_sbv_get($orderId) === null) {
            return $keyboardJson;
        }
        $kb = is_string($keyboardJson) ? json_decode($keyboardJson, true) : $keyboardJson;
        if (!is_array($kb) || !isset($kb['inline_keyboard']) || !is_array($kb['inline_keyboard'])) {
            $kb = ['inline_keyboard' => []];
        }
        $kb['inline_keyboard'][] = [['text' => '🔎 بررسی تطبیق و اقدامات مالی', 'callback_data' => 'sbv_o_' . $orderId]];
        return json_encode($kb, JSON_UNESCAPED_UNICODE);
    }

    public static function rx_sbv_cancel_row(string $orderId): ?array
    {
        $csp = self::rx_sbv_get($orderId);
        if ($csp === null || (string) $csp['state'] !== 'open' || $csp['deposit_id'] !== null) {
            return null;
        }
        return [['text' => '❌ لغو پرداخت', 'callback_data' => 'sbvcancel_' . $orderId, '_rxsk' => 'pay_cancel']];
    }

    public static function rx_sbv_cancel_keyboard(string $orderId): ?string
    {
        $row = self::rx_sbv_cancel_row($orderId);
        return $row === null ? null : json_encode(['inline_keyboard' => [$row]], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_sbv_bot_mark_cancelled($chatId, $messageId, string $text): void
    {
        $kb = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser']]]], JSON_UNESCAPED_UNICODE);
        if ((int) $messageId <= 0) {
            sendmessage($chatId, $text, $kb, 'HTML');
            return;
        }
        $r = function_exists('Editmessagetext') ? Editmessagetext($chatId, $messageId, $text, $kb, 'HTML') : null;
        if (is_array($r) && !empty($r['ok'])) {
            return;
        }
        $r = telegram('editMessageCaption', ['chat_id' => $chatId, 'message_id' => (int) $messageId, 'caption' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
        if (is_array($r) && !empty($r['ok'])) {
            return;
        }
        telegram('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => (int) $messageId, 'reply_markup' => json_encode(['inline_keyboard' => []])]);
        sendmessage($chatId, $text, $kb, 'HTML');
    }

    public static function rx_sbv_bot_pending_notice($chatId, $messageId, array $active): void
    {
        $s = \Faoxima\Payments\Receipts\ReceiptOrders::pendingSummary($active);
        $text = "⚠️ " . self::rx_sbv_pending_message() . "\n\n"
            . "<blockquote>🛒 کد پیگیری: <code>" . htmlspecialchars($s['order_id'], ENT_QUOTES, 'UTF-8') . "</code>\n"
            . "💰 مبلغ سفارش/شارژ: " . self::rx_sbv_money($s['principal_toman']) . " تومان\n"
            . "✅ مبلغ دقیق قابل واریز: " . self::rx_sbv_money($s['payable_toman']) . " تومان\n"
            . "⏳ مهلت پرداخت: تا ساعت " . self::rx_sbv_clock($s['expires_at']) . "\n"
            . "📌 وضعیت: " . $s['status_label'] . "</blockquote>";
        $rows = [];
        if ($s['resumable']) {
            $rows[] = [['text' => '▶️ ادامه پرداخت قبلی', 'callback_data' => 'sbvresume_' . $s['order_id'], '_rxsk' => 'pay_resume']];
        }
        if ($s['cancellable']) {
            $rows[] = [['text' => '❌ لغو پرداخت قبلی', 'callback_data' => 'sbvcancel_' . $s['order_id'], '_rxsk' => 'pay_cancel']];
        }
        $rows[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'backuser']];
        $kb = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        $sent = null;
        if ((int) $messageId > 0 && function_exists('Editmessagetext')) {
            $sent = Editmessagetext($chatId, $messageId, $text, $kb, 'HTML');
        }
        if (!is_array($sent) || empty($sent['ok'])) {
            sendmessage($chatId, $text, $kb, 'HTML');
        }
    }

    public static function rx_sbv_cancel_active($userId, string $orderId): array
    {
        $userId = trim((string) $userId);
        $active = self::rx_sbv_active_payment($userId);
        if ($active === null || (string) $active['id_order'] !== $orderId) {
            return ['ok' => false, 'code' => 'not_active', 'message' => 'این پرداخت دیگر فعال نیست. اکنون می‌توانید پرداخت جدید ایجاد کنید.'];
        }
        if (empty($active['cancellable'])) {
            return ['ok' => false, 'code' => 'not_cancellable', 'message' => 'این پرداخت قابل لغو نیست؛ واریز یا رسید آن در حال بررسی است.'];
        }
        if (!method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxCancelAbandonedCardPayment')) {
            return ['ok' => false, 'code' => 'unavailable', 'message' => 'لغو پرداخت در حال حاضر ممکن نیست.'];
        }
        $res = \Faoxima\Payments\Receipts\ReceiptActions::rxCancelAbandonedCardPayment($orderId, $userId, 'user_cancel');
        if (empty($res['ok'])) {
            return ['ok' => false, 'code' => (string) ($res['reason'] ?? 'error'), 'message' => 'لغو پرداخت انجام نشد؛ ممکن است واریز یا رسید آن در حال بررسی باشد.'];
        }
        if (method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_release_for_order')) {
            \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_release_for_order($orderId, 'cancelled', 'payment_reset');
        }
        return ['ok' => true, 'code' => 'cancelled', 'message' => '✅ پرداخت قبلی لغو شد. اکنون می‌توانید پرداخت جدید ایجاد کنید.'];
    }

    public static function rx_sbv_bot_resume($chatId, string $orderId, array $user): bool
    {
        global $setting, $datatextbot;
        $uid = (string) $chatId;
        $active = self::rx_sbv_active_payment($uid);
        if ($active === null || (string) $active['id_order'] !== $orderId || empty($active['resumable'])) {
            return false;
        }
        $pdo = self::rx_sbv_pdo();
        $pr = self::rx_sbv_payment_report($orderId);
        $view = self::rx_sbv_bot_payment_view($orderId);
        if ($pdo === null || $pr === null || $view === null) {
            return false;
        }
        $cfg = is_array($setting ?? null) ? $setting : (\Faoxima\Core\Database\Db::runtimeReady() ? (array) \Faoxima\Core\Database\Db::select('setting', '*') : []);
        $texts = is_array($datatextbot ?? null) ? $datatextbot : [];
        $adminIds = \Faoxima\Core\Database\Db::runtimeReady() ? (array) \Faoxima\Core\Database\Db::select('admin', 'id_admin', null, null, 'FETCH_COLUMN') : [];
        $cvActive = !in_array($uid, array_map('strval', $adminIds), true)
            && ($cfg['card_verify_status'] ?? 'offcardverify') === 'oncardverify'
            && (int) ($user['card_verify_bypass'] ?? 0) !== 1;
        $cvMin = (int) ($cfg['card_verify_min_amount'] ?? 0);
        if ($cvActive && $cvMin > 0 && (int) $pr['price'] < $cvMin) {
            $cvActive = false;
        }
        if ($cvActive && trim((string) ($pr['card_last4'] ?? '')) === '') {
            \Faoxima\Core\Database\Db::update('user', 'Processing_value', $orderId, 'id', $uid);
            $stmt = $pdo->prepare("SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmt->execute([$uid]);
            $verified = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($verified)) {
                $rows = [];
                foreach ($verified as $l4) {
                    $rows[] = [['text' => "💳 **** **** **** {$l4}", 'callback_data' => "cv_use_{$l4}"]];
                }
                $rows[] = [['text' => "📷 پرداخت با کارت جدید", 'callback_data' => "cv_new"]];
                $cancelRow = self::rx_sbv_cancel_row($orderId);
                if ($cancelRow !== null) {
                    $rows[] = $cancelRow;
                }
                \Faoxima\Telegram\Navigation\Steps::step('card_select_step', $uid);
                sendmessage($uid, $texts['dyn_wallet_select_verified_card'] ?? "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید یا با کارت جدید پرداخت کنید:", json_encode(['inline_keyboard' => $rows]), 'HTML');
            } else {
                \Faoxima\Telegram\Navigation\Steps::step('card_photo_step', $uid);
                sendmessage($uid, $texts['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.", self::rx_sbv_cancel_keyboard($orderId), 'HTML');
            }
            return true;
        }
        $access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = ?))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = ?)";
        $mode = (string) self::rx_sbv_setting_get('card_display_mode', 'random');
        $orderSql = $mode === 'direct' ? 'cn.created_at ASC LIMIT 2' : 'RAND() LIMIT 1';
        try {
            $stmt = $pdo->prepare("SELECT * FROM card_number cn WHERE {$access} ORDER BY {$orderSql}");
            $stmt->execute([$uid, $uid, $uid]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $cards = [];
        }
        $card = $cards[0] ?? null;
        $card2 = $mode === 'direct' ? ($cards[1] ?? null) : null;
        if (!is_array($card) || empty($card['cardnumber'])) {
            sendmessage($uid, $texts['dyn_wallet_no_active_card'] ?? "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            return true;
        }
        if (is_array($card2)) {
            $text = "💳 <b>کارت اول</b>\n🔢 <code>{$card['cardnumber']}</code>\n👤 {$card['namecard']}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$card2['cardnumber']}</code>\n👤 {$card2['namecard']}\n\n💰 مبلغ: {$view['valueprice']} تومان";
        } else {
            $text = strtr((string) ($texts['text_cart'] ?? "💳 مبلغ {price} تومان را به کارت {card_number} به نام {name_card} واریز کنید."), ['{price}' => $view['valueprice'], '{card_number}' => $card['cardnumber'], '{name_card}' => $card['namecard']]);
        }
        $text .= $view['block'];
        $copyAllowed = (string) ($cfg['statuscopycart'] ?? '') === '1';
        $copyRow = [];
        if ($copyAllowed) {
            $copyRow[] = ['text' => "کپی کارت اول", 'copy_text' => ['text' => (string) $card['cardnumber']], '_rxck' => 'card_num'];
        }
        $copyRow[] = ['text' => $view['copy_label'], 'copy_text' => ['text' => $view['price_copy']], '_rxck' => 'card_amount'];
        $rows = [$copyRow];
        if ($copyAllowed && is_array($card2) && !empty($card2['cardnumber'])) {
            $rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ['text' => (string) $card2['cardnumber']], '_rxck' => 'card_num_2']];
        }
        $rows[] = [['text' => $view['receipt_label'], 'callback_data' => 'sendresidcart-' . $orderId]];
        $cancelRow = self::rx_sbv_cancel_row($orderId);
        if ($cancelRow !== null) {
            $rows[] = $cancelRow;
        }
        $kb = json_encode(['inline_keyboard' => $rows]);
        $res = method_exists(\Faoxima\Telegram\Media\SectionMedia::class, 'rx_send_banner_message')
            ? \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($uid, 'cart', $text, $kb, 'html')
            : sendmessage($uid, $text, $kb, 'HTML');
        if (method_exists(\Faoxima\Payments\Settlement\PendingInvoices::class, 'updatePaymentMessageId')) {
            \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($res, $orderId);
        }
        return true;
    }
}
