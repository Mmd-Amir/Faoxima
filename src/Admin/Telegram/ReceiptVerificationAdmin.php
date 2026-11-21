<?php

namespace Faoxima\Admin\Telegram;

use PDO;
use Throwable;

final class ReceiptVerificationAdmin
{
    public static function rx_sbv_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function rx_sbv_digits(string $value): string
    {
        return strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function rx_sbv_status_label(string $status): string
    {
        $map = [
            'Unpaid' => 'در انتظار پرداخت',
            'pending' => 'در حال ارسال رسید',
            'waiting' => 'رسید ارسال شده (در انتظار بررسی)',
            'processing' => 'در حال پردازش',
            'reconciling' => 'در حال تطبیق با پنل',
            'manual_review' => 'نیازمند بررسی دستی',
            'paid' => 'تأیید شده',
            'reject' => 'رد شده',
            'expire' => 'منقضی شده',
            'cancelled' => 'لغو شده',
        ];
        return $map[$status] ?? ($status === '' ? '—' : $status);
    }

    public static function rx_sbv_state_label(string $state): string
    {
        $map = [
            'open' => 'فعال، در انتظار واریز',
            'matched' => 'واریز تطبیق یافت، در حال تأیید خودکار',
            'review' => 'واریز تطبیق یافت، تکمیل خودکار ناموفق (نیازمند ادمین)',
            'approved' => 'تأیید و تسویه شده',
            'credited' => 'به‌صورت شارژ کیف پول تسویه شده',
            'closed' => 'بسته شده',
            'void' => 'باطل',
        ];
        return $map[$state] ?? $state;
    }

    public static function rx_sbv_deposit_state_label(string $state): string
    {
        $map = [
            'pending' => 'در صف تطبیق',
            'matched' => 'تطبیق یافته',
            'applied' => 'اعمال شده',
            'unmatched' => 'تطبیق‌نیافته',
            'ambiguous' => 'مبهم (چند پرداخت هم‌مبلغ)',
            'late' => 'دیرهنگام / پرداخت غیرفعال',
            'linked' => 'متصل‌شده توسط ادمین',
            'reviewed_rejected' => 'بررسی‌شده — درخواست پرداخت رد شد',
            'dismissed' => 'بایگانی‌شده',
            'ignored' => 'غیرقابل‌استفاده (برداشت)',
            'test' => 'آزمایشی',
        ];
        return $map[$state] ?? $state;
    }

    public static function rx_sbv_toman_from_rial(?int $rial): string
    {
        if ($rial === null) {
            return 'ثبت نشده';
        }
        $toman = intdiv($rial, 10);
        $rem = $rial % 10;
        return \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($toman) . ($rem !== 0 ? '.' . $rem : '') . ' تومان (' . $rial . ' ریال)';
    }

    public static function rx_sbv_cb_ok(string $orderId): bool
    {
        return $orderId !== '' && strlen($orderId) <= 40 && preg_match('/^\w+$/', $orderId) === 1;
    }

    public static function rx_sbv_kb(array $rows): string
    {
        return json_encode(['inline_keyboard' => array_values(array_filter($rows))], JSON_UNESCAPED_UNICODE);
    }

    public static function rx_sbv_settings_view(): array
    {
        \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_ensure_schema();
        $on = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_setting_get('sbv_status', 'off') === 'on';
        $key = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_api_key();
        $url = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_webhook_url();
        $conn = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_connection_status();
        $queue = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_review_queue(0, 1);
        $text = "🔐 <b>تأیید رسید با بررسی (پیامک بانکی از طریق ForApp)</b>\n\n"
            . "وضعیت: " . ($on ? "✅ فعال" : "❌ غیرفعال") . "\n"
            . "اتصال: " . self::rx_sbv_h($conn['label']) . ($conn['last_at'] > 0 ? "\nآخرین درخواست معتبر: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $conn['last_at'], true)) : '') . "\n\n";
        if ($key === '') {
            $text .= "🔑 کلید API هنوز ساخته نشده است. با فعال‌سازی، کلید به‌صورت خودکار ساخته می‌شود.\n\n";
        } else {
            $text .= "━━ <b>تنظیمات مقصد در ForApp</b> ━━\n"
                . "🌐 آدرس (URL):\n" . ($url !== '' ? "<code>" . self::rx_sbv_h($url) . "</code>" : "⚠️ دامنه ربات تنظیم نشده است") . "\n"
                . "📮 متد: <code>POST</code>\n"
                . "📦 بدنه: <code>JSON</code> (قالب پیش‌فرض ForApp را تغییر ندهید)\n"
                . "🔑 کلید API:\n<code>" . self::rx_sbv_h($key) . "</code>\n"
                . "نام هدر احراز هویت: X-API-Key\n"
                . "🔁 هدر تکرار:\n<code>Idempotency-Key: {{id}}</code>\n\n";
        }
        $text .= "⏳ مهلت هر پرداخت: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_lifetime_minutes() . " دقیقه (حداقل " . RX_SBV_LIFETIME_MIN_MINUTES . ")\n"
            . "📤 ارسال رسید: " . intdiv(RX_SBV_RECEIPT_DELAY, 60) . " دقیقه پس از ایجاد پرداخت\n"
            . "🏷 کارمزد شناسایی: " . RX_SBV_FEE_MIN . " تا " . RX_SBV_FEE_MAX . " تومان (جزو شارژ/سفارش نیست)\n"
            . "🔍 محدوده پیشنهاد پرداخت: ±" . number_format(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_suggestion_range_toman()) . " تومان\n"
            . "♻️ استفاده مجدد از یک مبلغ: حداقل " . intdiv(RX_SBV_REUSE_COOLDOWN, 3600) . " ساعت پس از بسته‌شدن پرداخت\n"
            . "📥 واریزهای نیازمند بررسی: " . (int) $queue['total'] . "\n\n"
            . "ℹ️ غیرفعال‌سازی فقط ساخت پرداخت‌های جدید با این روش را متوقف می‌کند؛ پرداخت‌های قبلی همچنان بررسی می‌شوند و کلید تغییر نمی‌کند.";
        $rows = [];
        $rows[] = [['text' => $on ? '❌ غیرفعال‌سازی' : '✅ فعال‌سازی', 'callback_data' => $on ? 'sbv_off' : 'sbv_on']];
        if ($key !== '') {
            $copy = [];
            if ($url !== '') {
                $copy[] = ['text' => '📋 کپی آدرس', 'copy_text' => ['text' => $url]];
            }
            $copy[] = ['text' => '📋 کپی کلید', 'copy_text' => ['text' => $key]];
            $rows[] = $copy;
            $rows[] = [['text' => '♻️ ساخت کلید جدید', 'callback_data' => 'sbv_rg']];
        }
        $rows[] = [['text' => '⏳ مهلت پرداخت', 'callback_data' => 'sbv_lf']];
        $rows[] = [['text' => '🎯 محدوده پیشنهادها', 'callback_data' => 'sbv_sgr']];
        $rows[] = [['text' => '📥 واریزهای تطبیق‌نیافته', 'callback_data' => 'sbv_q_0']];
        $rows[] = [['text' => '🔄 بروزرسانی', 'callback_data' => 'sbv_home']];
        $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'cart_back']];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_invoice_label(string $idInvoice): string
    {
        $parts = explode('|', $idInvoice, 2);
        $head = $parts[0] ?? '';
        $target = explode('%', (string) ($parts[1] ?? ''), 2)[0];
        $map = [
            'getconfigafterpay' => 'خرید سرویس',
            'getextenduser' => 'تمدید سرویس',
            'getextravolumeuser' => 'خرید حجم اضافه',
            'getextratimeuser' => 'خرید زمان اضافه',
        ];
        if (isset($map[$head])) {
            return $map[$head] . ($target !== '' ? ' (' . $target . ')' : '');
        }
        return 'شارژ کیف پول';
    }

    public static function rx_sbv_deposit_lines(array $dep): string
    {
        $body = (string) ($dep['body'] ?? '');
        if (function_exists('mb_strlen') && mb_strlen($body, 'UTF-8') > 400) {
            $body = mb_substr($body, 0, 400, 'UTF-8') . '…';
        }
        return "<blockquote>🆔 شناسه واریز: <code>" . (int) $dep['id'] . "</code>\n"
            . "💵 مبلغ: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($dep['amount_rial'] !== null ? (int) $dep['amount_rial'] : null)) . "\n"
            . "🏦 بانک: " . self::rx_sbv_h($dep['bank'] ?? '—') . " | فرستنده: " . self::rx_sbv_h($dep['sender'] ?? '—') . "\n"
            . "🔢 کد پیامک: " . self::rx_sbv_h($dep['code'] ?? '—') . " (یکتا بودن تضمین نشده)\n"
            . "📱 دستگاه: " . self::rx_sbv_h($dep['device'] ?? '—') . "\n"
            . "🕒 دریافت در دستگاه: " . self::rx_sbv_h($dep['received_at_raw'] ?? '—') . "\n"
            . "🕒 ثبت در سرور: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $dep['stored_at'], true)) . "\n"
            . "📌 وضعیت: " . self::rx_sbv_h(self::rx_sbv_deposit_state_label((string) $dep['state'])) . ((string) ($dep['note'] ?? '') !== '' ? " (" . self::rx_sbv_h($dep['note']) . ")" : '') . "\n"
            . "✉️ متن پیامک:\n" . self::rx_sbv_h($body) . "</blockquote>";
    }

    public static function rx_sbv_order_view(string $orderId): ?array
    {
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($sum === null) {
            return null;
        }
        $csp = $sum['csp'];
        $pr = is_array($sum['report']) ? $sum['report'] : [];
        $dep = $sum['deposit'];
        $status = (string) ($pr['payment_Status'] ?? '');
        $state = (string) $csp['state'];
        $final = in_array($state, ['approved', 'credited'], true) || $status === 'paid';
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $pr);
        $user = \Faoxima\Core\Database\Db::runtimeReady() && !empty($pr['id_user']) ? \Faoxima\Core\Database\Db::select('user', '*', 'id', $pr['id_user'], 'select') : null;
        $received = $sum['received_rial'];
        $diff = $sum['diff_rial'];
        $text = "🔎 <b>بررسی پرداخت با بررسی</b>\n\n"
            . "🛒 کد سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>" . (is_array($user) && !empty($user['username']) ? " (@" . self::rx_sbv_h($user['username']) . ")" : '') . "\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($pr['id_invoice'] ?? ''))) . "\n"
            . "💳 وضعیت پرداخت: " . self::rx_sbv_h($expired ? 'منقضی‌شده (نیازمند بررسی ادمین)' : self::rx_sbv_status_label($status)) . "\n"
            . "🔐 وضعیت تطبیق: " . self::rx_sbv_h(self::rx_sbv_state_label($state)) . "\n\n"
            . "<blockquote>💰 اصل مبلغ (شارژ/سفارش): " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial((int) $csp['payable_rial'])) . "\n"
            . "📥 مبلغ دریافتی: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($received)) . "\n"
            . "➖ اختلاف: " . ($diff === null ? '—' : self::rx_sbv_h(($diff > 0 ? '+' : ($diff < 0 ? '−' : '')) . self::rx_sbv_toman_from_rial(abs($diff)))) . "</blockquote>\n"
            . "<blockquote>🕒 ایجاد: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['created_at'], true)) . "\n"
            . "📤 رسید از: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['receipt_after'], true)) . "\n"
            . "⏳ مهلت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['expires_at'], true)) . (time() >= (int) $csp['expires_at'] ? " (گذشته)" : '') . "</blockquote>\n";
        if ($dep !== null) {
            $text .= "\n📨 <b>واریز متصل</b>\n" . self::rx_sbv_deposit_lines($dep) . "\n";
        }
        if ((string) ($csp['resolution'] ?? '') !== '') {
            $text .= "\n📝 نتیجه: " . self::rx_sbv_h($csp['resolution']) . ((string) ($csp['resolved_by'] ?? '') !== '' ? " — ادمین " . self::rx_sbv_h($csp['resolved_by']) : '') . ((string) ($csp['resolution_note'] ?? '') !== '' ? "\n✍️ " . self::rx_sbv_h($csp['resolution_note']) : '') . "\n";
        }
        $terminalDecision = $final || in_array($status, ['reject', 'cancelled'], true) || (in_array($state, ['closed', 'void'], true) && !$expired);
        $rows = [];
        if (!$terminalDecision && $state !== 'matched') {
            if (in_array($status, ['Unpaid', 'waiting', 'expire'], true) && !$final) {
                $rows[] = [['text' => '✅ تحویل کامل سفارش / تأیید شارژ', 'callback_data' => 'sbv_oa_' . $orderId]];
            }
            if (!$final && (in_array($status, ['Unpaid', 'waiting', 'expire'], true) || $expired)) {
                $rows[] = [['text' => '❌ رد پرداخت', 'callback_data' => 'sbv_or_' . $orderId]];
            }
            $r = [['text' => '✍️ ویرایش مبلغ دریافتی', 'callback_data' => 'sbv_om_' . $orderId]];
            if ($csp['deposit_id'] === null) {
                $r[] = ['text' => '🔗 اتصال واریز', 'callback_data' => 'sbv_ol_' . $orderId];
            }
            $rows[] = $r;
            if ($received !== null && intdiv($received, 10) > 0 && in_array($status, ['Unpaid', 'waiting', 'expire'], true)) {
                $rows[] = [['text' => '💼 واریز کامل ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv($received, 10)) . ' تومان به کیف پول', 'callback_data' => 'sbv_oc_' . $orderId]];
            }
        }
        if (!empty($csp['id_user'])) {
            $rows[] = [['text' => '⚙️ مدیریت کاربر', 'callback_data' => 'manageuser_' . $csp['id_user']]];
        }
        $rows[] = [['text' => '🔄 بروزرسانی', 'callback_data' => 'sbv_oo_' . $orderId]];
        $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'sbv_q_0']];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_queue_view(int $page): array
    {
        $per = 8;
        $q = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_review_queue($page * $per, $per);
        $text = "📥 <b>واریزهای نیازمند بررسی</b>\n\nتعداد کل: " . (int) $q['total'] . "\n"
            . "ℹ️ واریزی که با هیچ پرداختی دقیقاً تطبیق نداشته باشد به‌صورت خودکار به کاربری نسبت داده نمی‌شود.\n";
        $rows = [];
        foreach ($q['rows'] as $dep) {
            $label = '#' . (int) $dep['id'] . ' | ' . ($dep['amount_rial'] !== null ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $dep['amount_rial'], 10)) . ' ت' : '—') . ' | ' . self::rx_sbv_deposit_state_label((string) $dep['state']) . ' | ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $dep['stored_at']);
            $rows[] = [['text' => $label, 'callback_data' => 'sbv_d_' . (int) $dep['id']]];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'sbv_q_' . ($page - 1)];
        }
        if (($page + 1) * $per < (int) $q['total']) {
            $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'sbv_q_' . ($page + 1)];
        }
        if ($nav) {
            $rows[] = $nav;
        }
        $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'sbv_home']];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_deposit_view(int $depositId): ?array
    {
        $dep = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        if ($dep === null) {
            return null;
        }
        $text = "📨 <b>جزئیات واریز</b>\n\n" . self::rx_sbv_deposit_lines($dep);
        if ((string) ($dep['id_order'] ?? '') !== '') {
            $text .= "\n🔗 سفارش متصل: <code>" . self::rx_sbv_h($dep['id_order']) . "</code>";
        }
        $rows = [];
        $state = (string) $dep['state'];
        $hasOrder = (string) ($dep['id_order'] ?? '') !== '' && self::rx_sbv_cb_ok((string) $dep['id_order']);
        if (in_array($state, ['unmatched', 'ambiguous', 'late'], true)) {
            $rows[] = [['text' => '🔗 اتصال به پرداخت', 'callback_data' => 'sbv_dl_' . $depositId]];
            $rows[] = [['text' => '🔍 پرداخت‌های احتمالی', 'callback_data' => 'sbv_ds_' . $depositId]];
            $rows[] = [['text' => '🗑 حذف واریز', 'callback_data' => 'sbv_dd_' . $depositId]];
        }
        if ($hasOrder) {
            $rows[] = [['text' => '🔎 مشاهده سفارش', 'callback_data' => 'sbv_oo_' . $dep['id_order']]];
        }
        $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'sbv_q_0']];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_link_preview(int $depositId, string $orderId, ?string $cancelCallback = null, ?string $confirmCallback = null): ?array
    {
        $dep = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($dep === null || $sum === null || !self::rx_sbv_cb_ok($orderId)) {
            return null;
        }
        $csp = $sum['csp'];
        $pr = is_array($sum['report']) ? $sum['report'] : [];
        $received = $dep['amount_rial'] !== null ? (int) $dep['amount_rial'] : null;
        $diff = $received === null ? null : $received - (int) $csp['payable_rial'];
        $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($orderId, $depositId);
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $pr);
        $linkUser = self::rx_sbv_admin_user((int) $csp['id_user']);
        $linkName = is_array($linkUser) ? trim((string) ($linkUser['first_name'] ?? '')) : '';
        $linkUsername = is_array($linkUser) ? trim((string) ($linkUser['username'] ?? '')) : '';
        $linkWho = $linkName !== '' ? $linkName : ($linkUsername !== '' ? '@' . $linkUsername : 'بدون نام');
        $text = "🔗 <b>تأیید اتصال واریز به پرداخت</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code> | 👤 کاربر: " . self::rx_sbv_h($linkWho) . " (<code>" . self::rx_sbv_h($csp['id_user']) . "</code>)\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($pr['id_invoice'] ?? ''))) . "\n"
            . "💳 وضعیت پرداخت: " . self::rx_sbv_h($expired ? 'منقضی‌شده (نیازمند تصمیم ادمین)' : self::rx_sbv_status_label((string) ($pr['payment_Status'] ?? ''))) . " | تطبیق: " . self::rx_sbv_h(self::rx_sbv_state_label((string) $csp['state'])) . "\n"
            . "<blockquote>💰 اصل مبلغ: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial((int) $csp['payable_rial'])) . "\n"
            . "📥 مبلغ واریز: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($received)) . "\n"
            . "➖ اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($diff)) . "\n"
            . "🕒 ایجاد پرداخت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['created_at'], true)) . " | مهلت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['expires_at'], true)) . "</blockquote>\n"
            . self::rx_sbv_deposit_lines($dep) . "\n\n"
            . "⚠️ اتصال به‌تنهایی پرداخت را تأیید نمی‌کند؛ پس از اتصال، نتیجه را از پنل سفارش انتخاب کنید.";
        if ($guard !== null) {
            $text .= "\n\n🚫 اکنون امکان اتصال وجود ندارد: " . self::rx_sbv_error_text($guard);
        }
        $rows = [];
        if ($guard === null) {
            $rows[] = [['text' => '✅ تأیید اتصال', 'callback_data' => ($confirmCallback !== null && $confirmCallback !== '') ? $confirmCallback : 'sbv_lk_' . $depositId . '_' . $orderId]];
        }
        $rows[] = [['text' => '❌ انصراف', 'callback_data' => ($cancelCallback !== null && $cancelCallback !== '') ? $cancelCallback : 'sbv_cancel']];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_order_deposits_view(string $orderId, int $page = 0): array
    {
        $orderId = trim($orderId);
        $page = max(0, $page);
        $back = [['text' => '🔙 بازگشت به بررسی پرداخت', 'callback_data' => 'sbv_oo_' . $orderId]];
        $summary = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($summary === null || !self::rx_sbv_cb_ok($orderId)) {
            return ['text' => '❌ پرداخت یا سفارش مورد نظر یافت نشد.', 'keyboard' => self::rx_sbv_kb([])];
        }
        $csp = $summary['csp'];
        $report = is_array($summary['report']) ? $summary['report'] : [];
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
        $user = self::rx_sbv_admin_user((int) $csp['id_user']);
        $name = is_array($user) ? trim((string) ($user['first_name'] ?? '')) : '';
        $username = is_array($user) ? trim((string) ($user['username'] ?? '')) : '';
        $who = $name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام');
        $text = "🔗 <b>انتخاب واریز بانکی برای اتصال</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code> | 👤 کاربر: " . self::rx_sbv_h($who) . " (<code>" . self::rx_sbv_h($csp['id_user']) . "</code>)\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($report['id_invoice'] ?? ''))) . "\n"
            . "💳 وضعیت پرداخت: " . self::rx_sbv_h($expired ? 'منقضی‌شده (نیازمند تصمیم ادمین)' : self::rx_sbv_status_label((string) ($report['payment_Status'] ?? ''))) . " | تطبیق: " . self::rx_sbv_h(self::rx_sbv_state_label((string) $csp['state'])) . "\n"
            . "<blockquote>✅ مبلغ مورد انتظار: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial((int) $csp['payable_rial'])) . "\n"
            . "💰 اصل مبلغ: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🎯 محدوده پیشنهاد: ±" . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_suggestion_range_toman()) . " تومان\n"
            . "🕒 ایجاد پرداخت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['created_at'], true)) . "</blockquote>\n";
        $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($orderId);
        if ($guard !== null) {
            $text .= "\n🚫 اکنون امکان اتصال وجود ندارد: " . self::rx_sbv_h(self::rx_sbv_error_text($guard));
            return ['text' => $text, 'keyboard' => self::rx_sbv_kb([$back])];
        }
        $per = 5;
        $result = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_deposit_candidates($orderId, $per, $page);
        $page = (int) ($result['page'] ?? 0);
        $pages = (int) ($result['pages'] ?? 1);
        $total = (int) ($result['total'] ?? 0);
        $rows = [];
        if (empty($result['ok'])) {
            $text .= "\n❌ جست‌وجوی واریزهای بانکی با خطا مواجه شد. این نتیجه به معنی نبود واریز احتمالی نیست؛ دوباره تلاش کنید.";
        } elseif (empty($result['rows'])) {
            $text .= "\n📭 هنوز هیچ واریز بانکی متناظری برای این پرداخت ثبت نشده است.\n"
                . "اگر کاربر پرداخت را انجام داده اما پیامک بانکی هنوز به سیستم نرسیده باشد، رسید در وضعیت در انتظار بررسی باقی می‌ماند و به‌صورت خودکار تأیید یا رد نمی‌شود.\n"
                . "پس از رسیدن پیامک بانکی، دکمه «🔄 بررسی مجدد واریزها» را بزنید. تأیید، رد و اصلاح مبلغ دستی همچنان از صفحه بررسی پرداخت در دسترس است.\n"
                . "صفحه 1 از 1 | تعداد کل: 0";
        } else {
            $shown = $page * $per;
            foreach ($result['rows'] as $dep) {
                $shown++;
                $amountRial = (int) $dep['amount_rial'];
                $eventAt = (int) $dep['stored_at'];
                $ms = $dep['received_at_ms'] !== null ? (int) $dep['received_at_ms'] : 0;
                if ($ms > 0) {
                    $sec = intdiv($ms, 1000);
                    if ($sec > 0 && $sec <= $eventAt + RX_SBV_DEVICE_SKEW && $sec >= $eventAt - 604800) {
                        $eventAt = $sec;
                    }
                }
                $diff = $amountRial - (int) $csp['payable_rial'];
                $text .= "\n<b>" . $shown . ") واریز <code>#" . (int) $dep['id'] . "</code></b>\n"
                    . "💵 مبلغ دریافتی: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($amountRial)) . "\n"
                    . "🕒 زمان واریز: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock($eventAt, true)) . "\n"
                    . "🏦 بانک: " . self::rx_sbv_h($dep['bank'] ?? '—') . " | فرستنده: " . self::rx_sbv_h($dep['sender'] ?? '—') . "\n"
                    . "📌 وضعیت: " . self::rx_sbv_h(self::rx_sbv_deposit_state_label((string) $dep['state'])) . " | اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($diff)) . "\n";
                $rows[] = [[
                    'text' => '💵 ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv($amountRial, 10)) . ' تومان | ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock($eventAt) . ' | #' . (int) $dep['id'],
                    'callback_data' => 'sbv_olc_' . (int) $dep['id'] . '_' . $orderId,
                ]];
            }
            $text .= "\nصفحه " . ($page + 1) . " از " . $pages . " | تعداد کل: " . $total;
            $text .= "\nℹ️ این واریزها فقط پیشنهاد هستند؛ اتصال پس از تأیید نهایی انجام می‌شود و به‌تنهایی پرداخت را تأیید نمی‌کند.";
            $nav = [];
            if ($page > 0) {
                $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'sbv_ols_' . ($page - 1) . '_' . $orderId];
            }
            if ($page + 1 < $pages) {
                $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'sbv_ols_' . ($page + 1) . '_' . $orderId];
            }
            if ($nav) {
                $rows[] = $nav;
            }
        }
        $rows[] = [['text' => '🔄 بررسی مجدد واریزها', 'callback_data' => 'sbv_ol_' . $orderId]];
        $rows[] = $back;
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_error_text(string $reason): string
    {
        $map = [
            'verify_reason_required' => 'برای این تصمیم مالی ثبت دلیل الزامی است.',
            'reason_required' => 'ثبت دلیل الزامی است.',
            'already_paid' => 'این پرداخت قبلاً تسویه شده است.',
            'not_found' => 'پرداخت یا سفارش مورد نظر یافت نشد.',
            'not_verify' => 'این پرداخت با روش «تأیید رسید با بررسی» ایجاد نشده است.',
            'unavailable' => 'سامانه رد پرداخت در حال حاضر در دسترس نیست.',
            'not_eligible' => 'وضعیت فعلی پرداخت اجازه این اقدام را نمی‌دهد.',
            'processing' => 'این پرداخت در حال پردازش یا تطبیق است و اکنون قابل رد نیست.',
            'not_waiting' => 'رسید در وضعیت «در انتظار بررسی» نیست.',
            'race_lost' => 'وضعیت پرداخت هم‌زمان توسط عامل دیگری تغییر کرد.',
            'already_final' => 'این پرداخت قبلاً نهایی شده است.',
            'invalid_user_id' => 'شناسه کاربر باید یک عدد معتبر باشد.',
            'user_not_found' => 'کاربری با این شناسه یافت نشد.',
            'no_eligible_payment' => 'هیچ درخواست پرداخت ForApp واجد شرایطی برای این کاربر پیدا نشد.',
            'selection_changed' => 'فهرست یا وضعیت پرداخت‌ها تغییر کرده است؛ لطفاً دوباره انتخاب کنید.',
            'received_required' => 'ابتدا مبلغ دریافتی را ثبت یا واریز را متصل کنید.',
            'nothing_to_credit' => 'مبلغ دریافتی پس از کسر کارمزد شناسایی مثبت نیست.',
            'purchase_receipts_pending' => 'ابتدا رسیدهای خرید یا تمدید این کاربر را بررسی کنید.',
            'deposit_not_found' => 'واریز یافت نشد.',
            'deposit_linked' => 'این واریز به سفارش یا پرداخت متصل است و حذف آن مجاز نیست.',
            'deposit_reassigned' => 'این واریز به پرداخت دیگری متصل یا بازتخصیص شده است.',
            'deposit_state_changed' => 'وضعیت واریز هنگام تأیید نهایی تغییر کرده است؛ عملیات انجام نشد.',
            'payment_state_changed' => 'وضعیت پرداخت هنگام تأیید نهایی تغییر کرده است؛ عملیات انجام نشد.',
            'audit_failed' => 'ثبت سابقه تصمیم ادمین ناموفق بود؛ هیچ تغییری اعمال نشد.',
            'db_error' => 'خطای پایگاه داده؛ عملیات انجام نشد.',
            'order_not_verify' => 'این سفارش با روش «تأیید رسید با بررسی» ایجاد نشده است.',
            'order_has_deposit' => 'این سفارش از قبل به واریز دیگری متصل است.',
            'deposit_not_eligible' => 'این رکورد واریز قابل اتصال نیست.',
            'invalid_amount' => 'مبلغ نامعتبر است.',
            'invalid_order' => 'شناسه پرداخت نامعتبر است.',
            'not_expired' => 'این پرداخت هنوز منقضی نشده است و حذف آن مجاز نیست.',
            'not_authorized' => 'اجازه انجام این عملیات را ندارید.',
            'order_not_candidate' => 'این پرداخت برای این واریز در فهرست پیشنهادهای فعلی نیست.',
            'candidate_check_failed' => 'بررسی تطبیق این پرداخت با واریز ناموفق بود؛ هیچ تغییری انجام نشد.',
            'fulfillment_check_failed' => 'بررسی وضعیت تسویه پرداخت ناموفق بود؛ هیچ تغییری انجام نشد.',
        ];
        if (isset($map[$reason])) {
            return $map[$reason];
        }
        if (strpos($reason, 'deposit_state_') === 0 || strpos($reason, 'order_state_') === 0) {
            return 'وضعیت فعلی اجازه اتصال نمی‌دهد (' . $reason . ').';
        }
        if (strpos($reason, 'claim_') === 0 || strpos($reason, 'fulfillment_') === 0 || strpos($reason, 'credit_') === 0) {
            return 'عملیات تکمیل نشد (' . $reason . '). وضعیت پرداخت تغییر نکرد یا برای بررسی مجدد نگه داشته شد.';
        }
        return 'خطا: ' . $reason;
    }

    public static function rx_sbv_admin_diff_label(?int $diffRial): string
    {
        if ($diffRial === null) {
            return '—';
        }
        if ($diffRial === 0) {
            return 'بدون اختلاف';
        }
        return \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv(abs($diffRial), 10)) . ' تومان ' . ($diffRial < 0 ? 'کمتر' : 'بیشتر');
    }

    public static function rx_sbv_admin_approval_message(array $res): string
    {
        $outcome = (string) ($res['outcome'] ?? '');
        if ($outcome === 'refunded') {
            return '✅ پرداخت تأیید شد، اما سفارش قابل تحویل نبود و اصل مبلغ طبق مسیر پشتیبانی‌شده به کیف پول بازگشت. سرویس تحویل نشد.';
        }
        if ($outcome === 'wallet_credit') {
            return '✅ پرداخت تأیید شد، اما سفارش قابل تحویل نبود و مبلغ طبق مسیر پشتیبانی‌شده به کیف پول واریز شد. سرویس تحویل نشد.';
        }
        if ($outcome === 'completed' || $outcome === 'paid') {
            return '✅ پرداخت تأیید و سفارش تسویه شد.';
        }
        return '✅ پرداخت تأیید شد؛ نتیجه نهایی سفارش را از جزئیات پرداخت بررسی کنید.';
    }

    public static function rx_sbv_admin_delete_confirm_view(int $depositId, ?string $cancelCallback = null): array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        if (!is_array($deposit)) {
            return ['text' => '❌ واریز یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $deletable = in_array((string) $deposit['state'], ['unmatched', 'ambiguous', 'late'], true) && trim((string) ($deposit['id_order'] ?? '')) === '';
        $text = "🗑 <b>حذف دائمی واریز</b>\n\n"
            . "<blockquote>🆔 شناسه واریز: <code>" . (int) $deposit['id'] . "</code>\n"
            . "💵 مبلغ: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null)) . "\n"
            . "🏦 بانک: " . self::rx_sbv_h($deposit['bank'] ?? '—') . "\n"
            . "🕒 زمان دریافت: " . self::rx_sbv_h((string) ($deposit['received_at_raw'] ?? '—')) . "\n"
            . "🕒 ثبت در سرور: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $deposit['stored_at'], true)) . "</blockquote>\n\n"
            . "⚠️ این اقدام رکورد واریز و payload پیامک ذخیره‌شده را برای همیشه از دیتابیس حذف می‌کند و قابل بازگشت نیست.";
        if (!$deletable) {
            $text .= "\n\n🚫 این واریز در وضعیت قابل حذف نیست.";
        }
        $rows = [];
        if ($deletable) {
            $rows[] = [['text' => '🗑 تأیید حذف دائمی', 'callback_data' => 'sbv_ddx_' . $depositId]];
        }
        $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback !== null && $cancelCallback !== '' ? $cancelCallback : 'sbv_d_' . $depositId]];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_candidate_view(int $depositId, int $page = 0, bool $allowDelete = false): array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        if (!is_array($deposit)) {
            return ['text' => '❌ واریز یافت نشد.', 'keyboard' => self::rx_sbv_kb([[['text' => '📥 صف بررسی', 'callback_data' => 'sbv_q_0']]])];
        }
        $amountRial = $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null;
        $per = 5;
        $result = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_report_candidates($depositId, $per, $page);
        $page = (int) ($result['page'] ?? 0);
        $pages = (int) ($result['pages'] ?? 1);
        $active = (array) ($result['active'] ?? []);
        $expired = (array) ($result['expired'] ?? []);
        $deleteRows = [];
        $text = "🔍 <b>پرداخت‌های احتمالی — صاحب واریز هنوز تأیید نشده است</b>\n\n"
            . "<blockquote>🆔 شناسه واریز: <code>" . (int) $deposit['id'] . "</code>\n"
            . "💵 مبلغ دریافتی: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($amountRial)) . "</blockquote>\n";
        if (empty($result['ok'])) {
            $text .= "\n❌ جست‌وجوی پیشنهاد پرداخت با خطا مواجه شد. این نتیجه به معنی نبود پرداخت احتمالی نیست.";
        } elseif (!$active && !$expired) {
            $text .= "\n✅ جست‌وجو انجام شد اما هیچ پرداخت فعال یا منقضی‌شده‌ای با مبلغ و زمان نزدیک پیدا نشد.";
        } else {
            $shown = $page * $per;
            if ($active) {
                $text .= "\n<b>پرداخت‌های فعال / در انتظار</b>\n";
                foreach ($active as $candidate) {
                    $shown++;
                    $text .= "\n" . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                }
            }
            if ($expired) {
                $text .= "\n<b>پرداخت‌های منقضی‌شده — احتمال ضعیف‌تر؛ نیازمند بررسی ادمین</b>\n";
                foreach ($expired as $candidate) {
                    $shown++;
                    $text .= "\n" . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_candidate_line($candidate, $shown, $amountRial) . "\n";
                    if ($allowDelete) {
                        $candidateOrderId = trim((string) ($candidate['id_order'] ?? ''));
                        $candidateStatus = (string) ($candidate['report_status'] ?? '');
                        $candidateDirect = (int) ($candidate['report_direct_done'] ?? 0);
                        if ($candidateOrderId !== '' && in_array($candidateStatus, ['Unpaid', 'waiting', 'expire'], true) && $candidateDirect === 0 && $candidate['deposit_id'] === null) {
                            $deleteRows[] = [['text' => '🗑 حذف پرداخت منقضی ' . $shown . ' — ' . $candidateOrderId, 'callback_data' => 'sbv_ed_' . $depositId . '_' . $page . '_' . $candidateOrderId]];
                        }
                    }
                }
            }
            if ($deleteRows) {
                $text .= "\n🗑 حذف هر پرداخت منقضی فقط از طریق دکمه همان مورد انجام می‌شود.";
            }
            $text .= "\nصفحه " . ($page + 1) . " از " . $pages . " | تعداد کل: " . (int) ($result['total'] ?? 0);
            $text .= "\n⚠️ پیشنهادها قطعی نیستند و هیچ اتصال، تأیید یا رد خودکاری انجام نمی‌شود.";
        }
        $rows = [];
        $nav = [];
        if (!empty($result['ok']) && $page > 0) {
            $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'sbv_ds_' . $depositId . '_' . ($page - 1)];
        }
        if (!empty($result['ok']) && $page + 1 < $pages) {
            $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'sbv_ds_' . $depositId . '_' . ($page + 1)];
        }
        if ($nav) {
            $rows[] = $nav;
        }
        if ($deleteRows) {
            $rows = array_merge($rows, $deleteRows);
        }
        $rows = array_merge($rows, [
            [['text' => '🔄 بروزرسانی پیشنهادها', 'callback_data' => 'sbv_ds_' . $depositId . '_' . $page]],
            [['text' => '🔗 اتصال به پرداخت', 'callback_data' => 'sbv_dl_' . $depositId]],
            [['text' => '🗑 حذف واریز', 'callback_data' => 'sbv_dd_' . $depositId]],
            [['text' => '🔎 جزئیات واریز', 'callback_data' => 'sbv_d_' . $depositId]],
        ]);
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_delete_expired_confirm_view(string $orderId, int $depositId, int $page = 0): array
    {
        $page = max(0, $page);
        $orderId = trim($orderId);
        $back = [['text' => '🔙 بازگشت به پیشنهادها', 'callback_data' => 'sbv_ds_' . $depositId . '_' . $page]];
        $summary = $orderId !== '' ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId) : null;
        if ($summary === null) {
            return [
                'text' => "❌ <b>پرداخت یافت نشد</b>\n\nشناسه سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>",
                'keyboard' => self::rx_sbv_kb([$back]),
            ];
        }
        $csp = $summary['csp'];
        $report = is_array($summary['report']) ? $summary['report'] : null;
        $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_expired_payment_delete_guard($orderId, $csp, $report, $depositId);
        if ($guard !== null) {
            return [
                'text' => "❌ <b>حذف پرداخت منقضی انجام نمی‌شود</b>\n\n"
                    . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
                    . "📌 وضعیت: " . self::rx_sbv_h(self::rx_sbv_status_label((string) (is_array($report) ? ($report['payment_Status'] ?? '') : ''))) . "\n\n"
                    . self::rx_sbv_h(self::rx_sbv_error_text($guard)),
                'keyboard' => self::rx_sbv_kb([$back]),
            ];
        }
        $text = "🗑 <b>تأیید حذف پرداخت منقضی</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "💰 مبلغ مورد انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📅 ایجاد: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['created_at'], true)) . "\n"
            . "⏳ مهلت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $csp['expires_at'], true)) . "\n"
            . "📌 وضعیت: " . self::rx_sbv_h(self::rx_sbv_status_label((string) ($report['payment_Status'] ?? ''))) . "\n\n"
            . "ℹ️ فقط همین پرداخت منقضی از فهرست پیشنهادها حذف و به‌صورت لغوشده بسته می‌شود. حساب کاربر، فاکتورها و سایر پرداخت‌ها تغییر نمی‌کنند.";
        $rows = [
            [['text' => '✅ حذف پرداخت منقضی', 'callback_data' => 'sbv_edx_' . $depositId . '_' . $page . '_' . $orderId]],
            $back,
        ];
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_credit_confirm_view(string $orderId, string $reason, ?string $cancelCallback = null): array
    {
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($sum === null) {
            return ['text' => '❌ پرداخت یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $csp = $sum['csp'];
        $credit = $sum['received_rial'] !== null ? intdiv((int) $sum['received_rial'], 10) : 0;
        $text = "💼 <b>تأیید واریز کامل مبلغ دریافتی به کیف پول</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code> | 👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "<blockquote>📥 مبلغ دریافتی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($credit) . " تومان\n"
            . "🏷 کارمزد شناسایی اصلی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "💼 مبلغ واریز به کیف پول: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($credit) . " تومان\n"
            . "✍️ دلیل: " . self::rx_sbv_h($reason) . "</blockquote>\n\n"
            . "ℹ️ در این تصمیم دستی، کل مبلغ دریافتی به کیف پول اضافه می‌شود و کارمزد شناسایی کسر نمی‌گردد. سفارش تحویل نخواهد شد.";
        $rows = [];
        if ($credit > 0) {
            $rows[] = [['text' => '✅ تأیید واریز ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($credit) . ' تومان', 'callback_data' => 'sbv_ccc']];
        }
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_amount_confirm_view(string $orderId, int $newToman, string $reason, ?string $cancelCallback = null): array
    {
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($sum === null) {
            return ['text' => '❌ پرداخت یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $csp = $sum['csp'];
        $old = $sum['received_rial'] !== null ? intdiv((int) $sum['received_rial'], 10) : 0;
        $diff = $newToman - $old;
        $text = "✍️ <b>تأیید ویرایش مبلغ دریافتی</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "<blockquote>مبلغ قبلی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($old) . " تومان\n"
            . "مبلغ اصلاح‌شده: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($newToman) . " تومان\n"
            . "اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($diff * 10)) . "\n"
            . "✍️ دلیل: " . self::rx_sbv_h($reason) . "</blockquote>\n\n"
            . "ℹ️ این ویرایش فقط مبلغ مؤثر را اصلاح می‌کند و به‌تنهایی کیف پول را شارژ یا سفارش را تحویل نمی‌دهد. مبلغ اصلی پیامک بانکی حفظ می‌شود.";
        $rows = [[['text' => '✅ تأیید اصلاح مبلغ', 'callback_data' => 'sbv_amc']]];
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_approval_confirm_view(string $orderId, string $reason, ?string $cancelCallback = null): array
    {
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($sum === null) {
            return ['text' => '❌ پرداخت یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $csp = $sum['csp'];
        $report = is_array($sum['report']) ? $sum['report'] : [];
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
        $received = $sum['received_rial'];
        $diff = $sum['diff_rial'];
        $text = "✅ <b>تأیید نهایی تحویل کامل سفارش / شارژ</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($report['id_invoice'] ?? ''))) . "\n"
            . "<blockquote>✅ مبلغ مورد انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ دریافتی: " . ($received !== null ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv($received, 10)) . ' تومان' : 'ثبت نشده') . "\n"
            . "➖ اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($diff)) . "\n"
            . "⏳ وضعیت: " . self::rx_sbv_h($expired ? 'منقضی‌شده' : self::rx_sbv_status_label((string) ($report['payment_Status'] ?? ''))) . "\n"
            . "✍️ دلیل: " . self::rx_sbv_h($reason) . "</blockquote>\n\n"
            . "⚠️ با تأیید، مسیر موجود تحویل/تسویه اجرا می‌شود. اگر سفارش قابل تحویل نباشد، نتیجه واقعی پشتیبانی‌شده نمایش داده می‌شود.";
        $rows = [[['text' => '✅ تأیید نهایی و تحویل', 'callback_data' => 'sbv_aac']]];
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_reject_order_confirm_view(string $orderId, string $reason, ?string $cancelCallback = null): array
    {
        $sum = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if ($sum === null) {
            return ['text' => '❌ پرداخت یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $csp = $sum['csp'];
        $report = is_array($sum['report']) ? $sum['report'] : [];
        $text = "❌ <b>تأیید نهایی رد درخواست پرداخت</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($report['id_invoice'] ?? ''))) . "\n"
            . "<blockquote>✅ مبلغ مورد انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ دریافتی: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($sum['received_rial'])) . "\n"
            . "✍️ دلیل: " . self::rx_sbv_h($reason) . "</blockquote>\n\n"
            . "ℹ️ رکورد واریز بانکی در صورت وجود حفظ می‌شود؛ وجهی بازگشت یا به کیف پول اضافه نمی‌شود و سفارش تحویل نمی‌شود.";
        $rows = [[['text' => '✅ تأیید نهایی رد پرداخت', 'callback_data' => 'sbv_rc']]];
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_choice_expired(array $row): bool
    {
        return \Faoxima\Payments\Receipts\ReceiptOrders::expiredFromValues(
            (string) ($row['state'] ?? ''),
            (string) ($row['report_status'] ?? ''),
            (string) ($row['resolution'] ?? ''),
            (int) ($row['expires_at'] ?? 0),
            (int) ($row['report_direct_done'] ?? 0)
        );
    }

    public static function rx_sbv_admin_choice_status_label(array $row): string
    {
        if (self::rx_sbv_admin_choice_expired($row)) {
            return 'منقضی‌شده';
        }
        $status = (string) ($row['report_status'] ?? '');
        $map = [
            'Unpaid' => 'در انتظار پرداخت',
            'waiting' => 'در انتظار بررسی',
            'expire' => 'منقضی‌شده',
            'processing' => 'در حال پردازش',
            'reconciling' => 'در حال تطبیق',
            'manual_review' => 'نیازمند بررسی',
        ];
        return $map[$status] ?? self::rx_sbv_status_label($status);
    }

    public static function rx_sbv_admin_user(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $pdo = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_pdo();
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
                $stmt->execute([(string) $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    return $row;
                }
                return null;
            } catch (Throwable $e) {
                return null;
            }
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('user', '*', 'id', (string) $userId, 'select', ['cache' => false]);
            return is_array($row) ? $row : null;
        }
        return null;
    }

    public static function rx_sbv_admin_payment_choices(int $userId, int $page, int $per = 5, bool $allowReview = false): array
    {
        $pdo = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_pdo();
        if ($pdo === null || $userId <= 0) {
            return ['rows' => [], 'total' => 0, 'page' => 0, 'pages' => 1, 'per' => $per];
        }
        $per = max(1, min(5, $per));
        $page = max(0, $page);
        $offset = $page * $per;
        $now = time();
        $closedExpired = "(csp.state = 'closed' AND (csp.resolution = 'expired' OR (pr.payment_Status = 'expire' AND (csp.resolution IS NULL OR csp.resolution = '' OR csp.resolution = 'expired'))))";
        $eligibleState = $allowReview
            ? "(csp.state IN ('open', 'review') OR $closedExpired)"
            : "(csp.state = 'open' OR (csp.state = 'review' AND (pr.payment_Status = 'expire' OR csp.expires_at < ?)) OR $closedExpired)";
        $base = " FROM card_sms_payment csp
                  JOIN Payment_report pr ON pr.id_order = csp.id_order
                 WHERE csp.id_user = ?
                   AND csp.deposit_id IS NULL
                   AND $eligibleState
                   AND pr.payment_Status IN ('Unpaid', 'waiting', 'expire')
                   AND COALESCE(pr.direct_payment_done, 0) = 0
                   AND NOT EXISTS (SELECT 1 FROM payment_fulfillment pf WHERE pf.id_order = csp.id_order AND (pf.state = 'completed' OR (pf.state IN ('claimed', 'applying', 'reconciling') AND COALESCE(pf.lease_expires_at, 0) > ?)))";
        $params = [(string) $userId];
        if (!$allowReview) {
            $params[] = $now;
        }
        $params[] = $now;
        try {
            $count = $pdo->prepare("SELECT COUNT(*)" . $base);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pages = max(1, (int) ceil($total / $per));
            if ($page >= $pages) {
                $page = $pages - 1;
                $offset = $page * $per;
            }
            $stmt = $pdo->prepare("SELECT csp.*, pr.payment_Status AS report_status, pr.id_invoice AS report_invoice, pr.time AS report_time, pr.source AS report_source, COALESCE(pr.direct_payment_done, 0) AS report_direct_done" . $base . " ORDER BY csp.created_at DESC, csp.id DESC LIMIT " . $per . " OFFSET " . $offset);
            $stmt->execute($params);
            return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
        } catch (Throwable $e) {
            \Faoxima\Payments\Receipts\Settings::log('SBV_ADMIN_CHOICES_FAILED', $e->getMessage(), ['id_user' => $userId]);
            return ['rows' => [], 'total' => 0, 'page' => 0, 'pages' => 1, 'per' => $per];
        }
    }

    public static function rx_sbv_admin_choice_line(array $row, int $number, ?int $depositRial): string
    {
        $diff = $depositRial === null ? null : $depositRial - (int) $row['payable_rial'];
        $source = (string) ($row['report_source'] ?? '') === 'miniapp' ? 'مینی‌اپ' : 'ربات';
        $statusLabel = self::rx_sbv_admin_choice_status_label($row);
        return "<b>" . $number . ") سفارش <code>" . self::rx_sbv_h($row['id_order']) . "</code></b>\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($row['report_invoice'] ?? ''))) . " | منبع: " . self::rx_sbv_h($source) . "\n"
            . "📅 ایجاد: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $row['created_at'], true)) . " | وضعیت: " . self::rx_sbv_h($statusLabel) . "\n"
            . "⏳ مهلت: " . self::rx_sbv_h(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_clock((int) $row['expires_at'], true)) . "\n"
            . "💰 اصل: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($row['principal_toman']) . " | کارمزد: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($row['fee_toman']) . " | انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $row['payable_rial'], 10)) . " تومان\n"
            . "📥 واریز: " . ($depositRial !== null ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv($depositRial, 10)) . ' تومان' : '—') . " | اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($diff));
    }

    public static function rx_sbv_admin_payment_choices_view(int $userId, int $depositId, int $page, string $mode, ?string $cancelCallback = null): array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        $user = self::rx_sbv_admin_user($userId);
        $cancelRow = $cancelCallback !== null && $cancelCallback !== '' ? [['text' => '❌ لغو', 'callback_data' => $cancelCallback]] : null;
        if (!is_array($deposit)) {
            return ['text' => '❌ واریز یافت نشد.', 'keyboard' => self::rx_sbv_kb(array_filter([$cancelRow]))];
        }
        if (!is_array($user)) {
            return ['text' => '❌ ' . self::rx_sbv_error_text('user_not_found'), 'keyboard' => self::rx_sbv_kb(array_filter([$cancelRow]))];
        }
        $received = $deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null;
        $name = trim((string) ($user['first_name'] ?? ''));
        $username = trim((string) ($user['username'] ?? ''));
        $who = $name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام');
        $choices = self::rx_sbv_admin_payment_choices($userId, $page, 5, $mode === 'reject');
        $page = (int) $choices['page'];
        $prefix = $mode === 'reject' ? 'sbv_rsel_' : 'sbv_lsel_';
        $pagePrefix = $mode === 'reject' ? 'sbv_rpg_' : 'sbv_lpg_';
        $title = $mode === 'reject' ? 'انتخاب پرداخت برای رد' : 'انتخاب پرداخت برای اتصال';
        $text = "👤 <b>" . self::rx_sbv_h($who) . "</b> (<code>" . self::rx_sbv_h($user['id']) . "</code>)\n"
            . "📥 واریز <code>#" . (int) $deposit['id'] . "</code> | " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($received)) . "\n"
            . "🔎 <b>" . self::rx_sbv_h($title) . "</b>\n\n";
        if ((int) $choices['total'] === 0) {
            $text .= "هیچ درخواست پرداخت ForApp واجد شرایطی برای این کاربر پیدا نشد.\n"
                . "پرداخت‌های منقضی‌شده و تسویه‌نشده در صورت وجود نمایش داده می‌شوند؛ پرداخت‌های تکمیل‌شده، متصل‌شده به واریز دیگر، در حال پردازش، ردشده، لغوشده، یا در وضعیت غیرقابل‌اتکا نمایش داده نمی‌شوند.";
            $kbRows = [];
            if ($cancelRow !== null) {
                $kbRows[] = $cancelRow;
            }
            return ['text' => $text, 'keyboard' => self::rx_sbv_kb($kbRows)];
        }
        $index = 0;
        foreach ($choices['rows'] as $row) {
            $index++;
            $text .= self::rx_sbv_admin_choice_line($row, $index, $received) . "\n\n";
        }
        $text .= "صفحه " . ($page + 1) . " از " . (int) $choices['pages'] . "\n"
            . "ℹ️ فهرست فقط پیشنهاد است؛ اتصال یا رد پس از انتخاب و تأیید نهایی انجام می‌شود.";
        $rows = [];
        foreach ($choices['rows'] as $row) {
            $rows[] = [[
                'text' => '💰 ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $row['payable_rial'], 10)) . ' تومان | ' . self::rx_sbv_admin_choice_status_label($row),
                'callback_data' => $prefix . $row['id_order'],
            ]];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '◀️ قبلی', 'callback_data' => $pagePrefix . ($page - 1)];
        }
        if ($page + 1 < (int) $choices['pages']) {
            $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => $pagePrefix . ($page + 1)];
        }
        if ($nav) {
            $rows[] = $nav;
        }
        if ($cancelRow !== null) {
            $rows[] = $cancelRow;
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_reject_preview(int $depositId, string $orderId, ?string $cancelCallback = null): ?array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        $summary = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if (!is_array($deposit) || $summary === null || !self::rx_sbv_cb_ok($orderId)) {
            return null;
        }
        $csp = $summary['csp'];
        if ($csp['deposit_id'] !== null && (int) $csp['deposit_id'] !== $depositId) {
            return null;
        }
        $report = is_array($summary['report']) ? $summary['report'] : [];
        $user = self::rx_sbv_admin_user((int) $csp['id_user']);
        $name = is_array($user) ? trim((string) ($user['first_name'] ?? '')) : '';
        $username = is_array($user) ? trim((string) ($user['username'] ?? '')) : '';
        $who = $name !== '' ? $name : ($username !== '' ? '@' . $username : 'بدون نام');
        $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_reject_guard($orderId);
        $expired = \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($csp, $report);
        $text = "❌ <b>تأیید رد درخواست پرداخت</b>\n\n"
            . "👤 کاربر: " . self::rx_sbv_h($who) . " (<code>" . self::rx_sbv_h($csp['id_user']) . "</code>)\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($report['id_invoice'] ?? ''))) . "\n"
            . "💳 وضعیت پرداخت: " . self::rx_sbv_h($expired ? 'منقضی‌شده (نیازمند تصمیم ادمین)' : self::rx_sbv_status_label((string) ($report['payment_Status'] ?? ''))) . " | تطبیق: " . self::rx_sbv_h(self::rx_sbv_state_label((string) $csp['state'])) . "\n\n"
            . "<blockquote>💰 اصل مبلغ: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['principal_toman']) . " تومان\n"
            . "🏷 کارمزد شناسایی: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($csp['fee_toman']) . " تومان\n"
            . "✅ مبلغ مورد انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "📥 مبلغ واریز: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null)) . "\n"
            . "➖ اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] - (int) $csp['payable_rial'] : null)) . "</blockquote>\n\n"
            . "ℹ️ این اقدام فقط درخواست پرداخت را رد می‌کند. رکورد واریز بانکی حفظ می‌شود و وجهی بازگشت، کیف پول شارژ، سرویس حذف یا کاربر مسدود نمی‌شود.";
        if ($guard !== null) {
            $text .= "\n\n🚫 امکان رد این پرداخت وجود ندارد: " . self::rx_sbv_error_text($guard);
        }
        $rows = [];
        if ($guard === null) {
            $rows[] = [['text' => '✍️ ثبت دلیل و ادامه', 'callback_data' => 'sbv_rr']];
        }
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_reject_confirm_view(int $depositId, string $orderId, string $reason, ?string $cancelCallback = null): array
    {
        $deposit = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_deposit($depositId);
        $summary = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_summary($orderId);
        if (!is_array($deposit) || $summary === null) {
            return ['text' => '❌ واریز یا پرداخت یافت نشد.', 'keyboard' => self::rx_sbv_kb($cancelCallback !== null ? [[['text' => '❌ انصراف', 'callback_data' => $cancelCallback]]] : [])];
        }
        $csp = $summary['csp'];
        $report = is_array($summary['report']) ? $summary['report'] : [];
        $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_reject_guard($orderId);
        $text = "⚠️ <b>تأیید نهایی رد پرداخت</b>\n\n"
            . "🛒 سفارش: <code>" . self::rx_sbv_h($orderId) . "</code>\n"
            . "👤 کاربر: <code>" . self::rx_sbv_h($csp['id_user']) . "</code>\n"
            . "📦 نوع: " . self::rx_sbv_h(self::rx_sbv_invoice_label((string) ($report['id_invoice'] ?? ''))) . "\n"
            . "📥 واریز: " . self::rx_sbv_h(self::rx_sbv_toman_from_rial($deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] : null)) . "\n"
            . "✅ مبلغ مورد انتظار: " . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money(intdiv((int) $csp['payable_rial'], 10)) . " تومان\n"
            . "➖ اختلاف: " . self::rx_sbv_h(self::rx_sbv_admin_diff_label($deposit['amount_rial'] !== null ? (int) $deposit['amount_rial'] - (int) $csp['payable_rial'] : null)) . "\n"
            . "✍️ دلیل: " . self::rx_sbv_h($reason) . "\n\n"
            . "با تأیید، درخواست پرداخت رد می‌شود و به کاربر اطلاع داده می‌شود. واریز بانکی حذف یا بازگشت داده نمی‌شود.";
        if ($guard !== null) {
            $text .= "\n\n🚫 اکنون امکان رد وجود ندارد: " . self::rx_sbv_error_text($guard);
        }
        $rows = [];
        if ($guard === null) {
            $rows[] = [['text' => '✅ تأیید نهایی رد پرداخت', 'callback_data' => 'sbv_rc']];
        }
        if ($cancelCallback !== null && $cancelCallback !== '') {
            $rows[] = [['text' => '❌ انصراف', 'callback_data' => $cancelCallback]];
        }
        return ['text' => $text, 'keyboard' => self::rx_sbv_kb($rows)];
    }

    public static function rx_sbv_admin_handle(array $c): bool
    {
        if (!method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_get')) {
            return false;
        }
        $fromId = (string) ($c['from_id'] ?? '');
        $datain = (string) ($c['datain'] ?? '');
        $text = (string) ($c['text'] ?? '');
        $update = is_array($c['update'] ?? null) ? $c['update'] : [];
        $user = is_array($c['user'] ?? null) ? $c['user'] : [];
        $rule = (string) ($c['rule'] ?? '');
        $cbq = (string) ($c['callback_query_id'] ?? '');
        $messageId = (int) ($c['message_id'] ?? 0);
        $isAdmin = $rule === 'administrator';
        $canReview = $isAdmin || $rule === 'Seller';
        if ($fromId === '' || !$canReview) {
            return false;
        }
        $step = (string) ($user['step'] ?? '');
        $isMessage = isset($update['message']) && !isset($update['callback_query']);
        $msgText = $isMessage ? trim((string) ($update['message']['text'] ?? '')) : '';
        $callbackMessage = is_array($update['callback_query']['message'] ?? null) ? $update['callback_query']['message'] : [];
        $sourceChatId = (string) ($callbackMessage['chat']['id'] ?? '');
        $sourceChatType = (string) ($callbackMessage['chat']['type'] ?? '');
        $sourceThreadId = (int) ($callbackMessage['message_thread_id'] ?? 0);
        $isGroupSource = !$isMessage && in_array($sourceChatType, ['group', 'supergroup', 'channel'], true) && $sourceChatId !== '';
        if ($isGroupSource) {
            $GLOBALS['rx_sbv_panel_source'] = [
                'chat_id' => $sourceChatId,
                'message_id' => $messageId,
                'thread_id' => $sourceThreadId,
                'type' => $sourceChatType,
            ];
        }
        $ackSent = false;
        $answer = function (string $msg = '', bool $alert = false) use ($cbq, &$ackSent) {
            if ($cbq !== '' && !$ackSent && function_exists('telegram')) {
                $ackSent = true;
                $p = ['callback_query_id' => $cbq, 'cache_time' => 0];
                if ($msg !== '') {
                    $p['text'] = function_exists('mb_substr') ? mb_substr($msg, 0, 190, 'UTF-8') : substr($msg, 0, 190);
                    $p['show_alert'] = $alert;
                }
                telegram('answerCallbackQuery', $p);
            }
        };
        $panelKey = 'sbv_panel_' . $fromId;
        $panelId = (int) \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_setting_get($panelKey, '0');
        $retirePanel = function (int $retiredId) use ($fromId) {
            if ($retiredId <= 0) {
                return;
            }
            try {
                telegram('editMessageReplyMarkup', [
                    'chat_id' => $fromId,
                    'message_id' => $retiredId,
                    'reply_markup' => self::rx_sbv_kb([]),
                ]);
            } catch (Throwable $e) {
                \Faoxima\Payments\Receipts\Settings::log('SBV_PANEL_RETIRE_FAILED', $e->getMessage());
            }
        };
        if (!$isMessage && !$isGroupSource && $sourceChatType === 'private' && $sourceChatId === $fromId && $messageId > 0 && (strpos($datain, 'sbv_') === 0 || $datain === 'cart_smsverify')) {
            if ($panelId !== $messageId) {
                $retirePanel($panelId);
            }
            $panelId = $messageId;
            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_setting_set($panelKey, (string) $panelId);
        }
        $show = function (array $view, bool $fresh = false) use ($fromId, $answer, $isGroupSource, $panelKey, &$panelId, $retirePanel) {
            $keyboard = $view['keyboard'] ?? null;
            if (!$isGroupSource && $panelId > 0 && function_exists('Editmessagetext')) {
                $answer();
                try {
                    $edited = Editmessagetext($fromId, $panelId, $view['text'], $keyboard, 'HTML');
                } catch (Throwable $e) {
                    $edited = null;
                }
                $editDesc = is_array($edited) ? strtolower((string) ($edited['description'] ?? '')) : '';
                if ((is_array($edited) && !empty($edited['ok'])) || strpos($editDesc, 'message is not modified') !== false) {
                    return $edited;
                }
            }
            $sent = sendmessage($fromId, $view['text'], $keyboard, 'HTML');
            if (is_array($sent) && !empty($sent['ok'])) {
                if (!empty($sent['result']['message_id'])) {
                    $replacementId = (int) $sent['result']['message_id'];
                    if ($panelId !== $replacementId) {
                        $retirePanel($panelId);
                    }
                    $panelId = $replacementId;
                    \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_setting_set($panelKey, (string) $panelId);
                }
                if ($isGroupSource) {
                    $answer('📩 پنل مدیریت این بخش به پی‌وی شما ارسال شد؛ برای ادامه بررسی وارد گفت‌وگوی خصوصی ربات شوید.', true);
                }
            } elseif ($isGroupSource) {
                $answer('❌ ارسال پنل به پی‌وی انجام نشد؛ ابتدا ربات را در پی‌وی Start کنید و دوباره بزنید.', true);
            }
            if (!$isGroupSource) {
                $answer();
            }
            return $sent;
        };
        $say = function (string $msg, ?string $kb = null) use ($show) {
            return $show(['text' => $msg, 'keyboard' => $kb]);
        };
        $storedRaw = (string) ($user['Processing_value_one'] ?? '');
        $sep = strrpos($storedRaw, '#~');
        $storedPayload = $sep === false ? $storedRaw : substr($storedRaw, 0, $sep);
        $storedNonce = $sep === false ? '' : substr($storedRaw, $sep + 2);
        $cancelKbFor = function (string $nonce): string {
            return self::rx_sbv_kb([[['text' => '❌ لغو', 'callback_data' => 'sbv_cancel_' . $nonce]]]);
        };
        $cancelKb = $cancelKbFor($storedNonce !== '' ? $storedNonce : '00000000');
        $setStep = function (string $s, string $payload) use ($fromId, &$cancelKb, $cancelKbFor) {
            $nonce = bin2hex(random_bytes(4));
            \Faoxima\Core\Database\Db::update('user', 'Processing_value_one', $payload . '#~' . $nonce, 'id', $fromId);
            \Faoxima\Telegram\Navigation\Steps::step($s, $fromId);
            $cancelKb = $cancelKbFor($nonce);
            return $nonce;
        };
        $clearStep = function () use ($fromId) {
            \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
        };
        $orderView = function (string $orderId, bool $fresh = false) use ($show, $answer) {
            $v = self::rx_sbv_order_view($orderId);
            if ($v === null) {
                $answer('این سفارش با روش تأیید رسید با بررسی ایجاد نشده است.', true);
                return;
            }
            $show($v, $fresh);
        };

        if (strpos($step, 'sbv_') === 0) {
            if ($isMessage) {
                $payload = $storedPayload;
                if ($msgText === '') {
                    $say('❌ لطفاً پاسخ را به‌صورت متن ارسال کنید.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_life') {
                    if (!$isAdmin) {
                        $clearStep();
                        return true;
                    }
                    $n = self::rx_sbv_digits($msgText);
                    if (!ctype_digit($n) || !\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_set_lifetime_minutes((int) $n)) {
                        $say('❌ عدد صحیح بین ' . RX_SBV_LIFETIME_MIN_MINUTES . ' و ' . RX_SBV_LIFETIME_MAX_MINUTES . ' دقیقه وارد کنید.', $cancelKb);
                        return true;
                    }
                    $clearStep();
                    $v = self::rx_sbv_settings_view();
                    $say("✅ مهلت پرداخت‌های جدید ذخیره شد. پرداخت‌های قبلی مهلت ثبت‌شده خود را حفظ می‌کنند.\n\n" . $v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_suggestion_range') {
                    if (!$isAdmin) {
                        $clearStep();
                        return true;
                    }
                    $n = preg_replace('/[\s,٬،\x{00A0}]+/u', '', self::rx_sbv_digits($msgText));
                    if ($n === null) {
                        $n = '';
                    }
                    if (!ctype_digit($n) || !\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_set_suggestion_range_toman((int) $n)) {
                        $say('❌ عدد صحیح بین ' . RX_SBV_SUGGEST_RANGE_MIN_TOMAN . ' و ' . RX_SBV_SUGGEST_RANGE_MAX_TOMAN . ' تومان وارد کنید.', $cancelKb);
                        return true;
                    }
                    $clearStep();
                    $v = self::rx_sbv_settings_view();
                    $say("✅ محدوده پیشنهاد پرداخت ذخیره شد: ±" . number_format((int) $n) . " تومان\n\n" . $v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_amount') {
                    $n = self::rx_sbv_digits($msgText);
                    $n = str_replace([',', '٬', ' '], '', $n);
                    if (!ctype_digit($n) || strlen($n) > 12) {
                        $say('❌ مبلغ دریافتی را فقط به‌صورت عدد صحیح و به تومان وارد کنید.', $cancelKb);
                        return true;
                    }
                    $nonce = $setStep('sbv_amount_reason', $payload . '|' . (int) $n);
                    $say('✍️ دلیل اصلاح مبلغ دریافتی را بنویسید. مبلغ اصلی پیامک بانکی حفظ می‌شود و این ویرایش به‌تنهایی کیف پول را شارژ یا سفارش را تحویل نمی‌کند.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_amount_reason') {
                    $parts = explode('|', $payload, 2);
                    $orderId = (string) ($parts[0] ?? '');
                    $newToman = (int) ($parts[1] ?? 0);
                    $reason = function_exists('mb_substr') ? mb_substr(trim($msgText), 0, 500, 'UTF-8') : substr(trim($msgText), 0, 500);
                    if ($reason === '' || $orderId === '' || $newToman <= 0) {
                        $say('❌ ثبت دلیل اصلاح مبلغ الزامی است.', $cancelKb);
                        return true;
                    }
                    $nonce = $setStep('sbv_amount_confirm', $orderId . '|' . $newToman . '|' . base64_encode($reason));
                    $v = self::rx_sbv_admin_amount_confirm_view($orderId, $newToman, $reason, 'sbv_cancel_' . $nonce);
                    $say($v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_amount_confirm') {
                    $say('برای ثبت نهایی یا انصراف از دکمه‌های همین پیام استفاده کنید.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_reject_user') {
                    $uidRaw = preg_replace('/[\s,٬]/u', '', self::rx_sbv_digits($msgText));
                    if (!ctype_digit($uidRaw) || $uidRaw === '' || strlen($uidRaw) > 20 || (int) $uidRaw <= 0) {
                        $say('❌ ' . self::rx_sbv_error_text('invalid_user_id') . ' شناسه عددی تلگرام را دوباره ارسال کنید.', $cancelKb);
                        return true;
                    }
                    $userId = (int) $uidRaw;
                    if (self::rx_sbv_admin_user($userId) === null) {
                        $say('❌ ' . self::rx_sbv_error_text('user_not_found') . ' شناسه دیگری را ارسال کنید.', $cancelKb);
                        return true;
                    }
                    $nonce = $setStep('sbv_reject_pay', $payload . '|' . $userId . '|0');
                    $v = self::rx_sbv_admin_payment_choices_view($userId, (int) $payload, 0, 'reject', 'sbv_cancel_' . $nonce);
                    $say($v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_reject_pay' || $step === 'sbv_reject_ready') {
                    $say('برای ادامه از دکمه‌های همین پیام استفاده کنید یا عملیات را لغو کنید.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_reject_reason') {
                    $parts = explode('|', $payload, 2);
                    $depositId = (int) ($parts[0] ?? 0);
                    $orderId = (string) ($parts[1] ?? '');
                    $reason = function_exists('mb_substr') ? mb_substr(trim($msgText), 0, 500, 'UTF-8') : substr(trim($msgText), 0, 500);
                    if ($reason === '') {
                        $say('❌ ثبت دلیل رد پرداخت الزامی است.', $cancelKb);
                        return true;
                    }
                    $nonce = $setStep('sbv_reject_confirm', $depositId . '|' . $orderId . '|' . base64_encode($reason));
                    $v = self::rx_sbv_admin_reject_confirm_view($depositId, $orderId, $reason, 'sbv_cancel_' . $nonce);
                    $say($v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_reject_confirm' || $step === 'sbv_approve_confirm' || $step === 'sbv_credit_confirm') {
                    $say('برای ثبت نهایی یا انصراف از دکمه‌های همین پیام استفاده کنید.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_link_user') {
                    $uidRaw = preg_replace('/[\s,٬]/u', '', self::rx_sbv_digits($msgText));
                    if (!ctype_digit($uidRaw) || $uidRaw === '' || strlen($uidRaw) > 20 || (int) $uidRaw <= 0) {
                        $say('❌ ' . self::rx_sbv_error_text('invalid_user_id') . ' شناسه عددی تلگرام را دوباره ارسال کنید.', $cancelKb);
                        return true;
                    }
                    $userId = (int) $uidRaw;
                    if (self::rx_sbv_admin_user($userId) === null) {
                        $say('❌ ' . self::rx_sbv_error_text('user_not_found') . ' شناسه دیگری را ارسال کنید.', $cancelKb);
                        return true;
                    }
                    $nonce = $setStep('sbv_link_pay', $payload . '|' . $userId . '|0');
                    $v = self::rx_sbv_admin_payment_choices_view($userId, (int) $payload, 0, 'link', 'sbv_cancel_' . $nonce);
                    $say($v['text'], $v['keyboard']);
                    return true;
                }
                if ($step === 'sbv_link_pay' || $step === 'sbv_link_confirm') {
                    $say('برای ادامه از دکمه‌های همین پیام استفاده کنید یا عملیات را لغو کنید.', $cancelKb);
                    return true;
                }
                if ($step === 'sbv_reason') {
                    $parts = explode('|', $payload, 2);
                    $action = $parts[0] ?? '';
                    $target = $parts[1] ?? '';
                    $reason = function_exists('mb_substr') ? mb_substr(trim($msgText), 0, 500, 'UTF-8') : substr(trim($msgText), 0, 500);
                    if ($reason === '') {
                        $say('❌ ثبت دلیل برای این تصمیم مالی الزامی است.', $cancelKb);
                        return true;
                    }
                    if ($action === 'approve') {
                        $nonce = $setStep('sbv_approve_confirm', $target . '|' . base64_encode($reason));
                        $v = self::rx_sbv_admin_approval_confirm_view($target, $reason, 'sbv_cancel_' . $nonce);
                        $say($v['text'], $v['keyboard']);
                        return true;
                    }
                    if ($action === 'credit') {
                        $nonce = $setStep('sbv_credit_confirm', $target . '|' . base64_encode($reason));
                        $v = self::rx_sbv_admin_credit_confirm_view($target, $reason, 'sbv_cancel_' . $nonce);
                        $say($v['text'], $v['keyboard']);
                        return true;
                    }
                    if ($action === 'reject') {
                        $targetCsp = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_get($target);
                        $depositId = is_array($targetCsp) && $targetCsp['deposit_id'] !== null ? (int) $targetCsp['deposit_id'] : 0;
                        $nonce = $setStep('sbv_reject_confirm', $depositId . '|' . $target . '|' . base64_encode($reason));
                        $v = $depositId > 0
                            ? self::rx_sbv_admin_reject_confirm_view($depositId, $target, $reason, 'sbv_cancel_' . $nonce)
                            : self::rx_sbv_admin_reject_order_confirm_view($target, $reason, 'sbv_cancel_' . $nonce);
                        $say($v['text'], $v['keyboard']);
                        return true;
                    }
                    $clearStep();
                    if ($action === 'block') {
                        $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_reject($target, $fromId, $reason, true);
                        $say(!empty($res['ok']) ? ($res['blocked'] ? '✅ رسید رد و کاربر مسدود شد. سوابق واریز حفظ شد.' : '⚠️ رسید رد شد اما مسدودسازی کاربر ناموفق بود.') : '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error')));
                    } elseif ($action === 'dismiss') {
                        $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_dismiss((int) $target, $fromId, $reason);
                        $say(!empty($res['ok']) ? '✅ واریز بایگانی شد (رکورد حذف نمی‌شود).' : '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error')));
                    }
                    if ($action !== 'dismiss' && self::rx_sbv_cb_ok($target)) {
                        $v = self::rx_sbv_order_view($target);
                        if ($v !== null) {
                            $show($v, true);
                        }
                    }
                    return true;
                }
                $clearStep();
                return false;
            }
            if ($datain !== '' && strpos($datain, 'sbv_') !== 0) {
                $clearStep();
            }
        }

        if ($datain === 'cart_smsverify' || ($text === RX_SBV_MENU_LABEL && $datain === '')) {
            if (!$isAdmin) {
                return false;
            }
            $show(self::rx_sbv_settings_view());
            return true;
        }

        if (preg_match('/^(Confirm_pay|addbalamceuser|reject_pay)_(\w+)$/', $datain, $m) && $m[2] !== '' && strpos($m[2], 'cancel_') !== 0) {
            if (!\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_is_verify_order($m[2])) {
                return false;
            }
            $answer('این پرداخت با روش «تأیید رسید با بررسی» ایجاد شده؛ اقدام را از پنل بررسی انجام دهید.', false);
            if (self::rx_sbv_cb_ok($m[2])) {
                $v = self::rx_sbv_order_view($m[2]);
                if ($v !== null) {
                    sendmessage($fromId, $v['text'], $v['keyboard'], 'HTML');
                }
            }
            return true;
        }

        if (strpos($datain, 'sbv_') !== 0) {
            return false;
        }

        if (preg_match('/^sbv_cancel(?:_([a-f0-9]{8}))?$/', $datain, $cm)) {
            $nonce = (string) ($cm[1] ?? '');
            $current = $nonce !== '' && strpos($step, 'sbv_') === 0 && $storedNonce !== '' && hash_equals($storedNonce, $nonce);
            if ($current) {
                $clearStep();
            }
            $stale = $nonce !== '' && !$current;
            $answer($stale ? 'این درخواست دیگر فعال نیست.' : 'لغو شد');
            if ($messageId > 0) {
                Editmessagetext($fromId, $messageId, $stale ? '⌛️ این درخواست دیگر فعال نیست و تغییری ایجاد نشد.' : '❌ عملیات لغو شد. هیچ تغییری روی پرداخت ایجاد نشد.', null, 'HTML');
            }
            return true;
        }
        if (in_array($datain, ['sbv_home', 'sbv_on', 'sbv_off', 'sbv_rg', 'sbv_rgy', 'sbv_lf', 'sbv_sgr'], true) && !$isAdmin) {
            $answer('این بخش فقط برای مدیر کل در دسترس است.', true);
            return true;
        }
        if ($datain === 'sbv_home') {
            $show(self::rx_sbv_settings_view());
            return true;
        }
        if ($datain === 'sbv_on') {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_enable();
            if (empty($res['ok'])) {
                $answer('❌ فعال‌سازی ناموفق بود (' . (string) $res['reason'] . ')', true);
                return true;
            }
            $v = self::rx_sbv_settings_view();
            $v['text'] = (!empty($res['generated']) ? "✅ فعال شد و کلید API جدید ساخته شد. آدرس و کلید زیر را در ForApp وارد کنید.\n\n" : "✅ فعال شد.\n\n") . $v['text'];
            $show($v);
            return true;
        }
        if ($datain === 'sbv_off') {
            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_disable();
            $v = self::rx_sbv_settings_view();
            $v['text'] = "❌ غیرفعال شد. کلید API حفظ شد.\n\n" . $v['text'];
            $show($v);
            return true;
        }
        if ($datain === 'sbv_rg') {
            $show([
                'text' => "♻️ <b>ساخت کلید API جدید</b>\n\nبا ساخت کلید جدید، کلید فعلی فوراً باطل می‌شود و تا وقتی کلید جدید را در ForApp وارد نکنید، پیامک‌های بانکی دریافت نخواهند شد.\n\nآیا مطمئن هستید؟",
                'keyboard' => self::rx_sbv_kb([
                    [['text' => '✅ بله، کلید جدید بساز', 'callback_data' => 'sbv_rgy']],
                    [['text' => '🔙 انصراف', 'callback_data' => 'sbv_home']],
                ]),
            ]);
            return true;
        }
        if ($datain === 'sbv_rgy') {
            $ok = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_regenerate_key();
            $v = self::rx_sbv_settings_view();
            $v['text'] = ($ok ? "✅ کلید جدید ساخته شد. کلید قبلی دیگر معتبر نیست؛ کلید جدید را در ForApp جایگزین کنید.\n\n" : "❌ ساخت کلید جدید ناموفق بود.\n\n") . $v['text'];
            $show($v);
            return true;
        }
        if ($datain === 'sbv_lf') {
            $answer();
            $setStep('sbv_life', '');
            $say('⏳ مهلت پرداخت‌های جدید را به دقیقه وارد کنید (حداقل ' . RX_SBV_LIFETIME_MIN_MINUTES . ' و حداکثر ' . RX_SBV_LIFETIME_MAX_MINUTES . ').\nمقدار فعلی: ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_lifetime_minutes() . ' دقیقه', $cancelKb);
            return true;
        }
        if ($datain === 'sbv_sgr') {
            $answer();
            $setStep('sbv_suggestion_range', '');
            $say('🎯 محدوده پیشنهاد پرداخت را به تومان وارد کنید (حداقل ' . RX_SBV_SUGGEST_RANGE_MIN_TOMAN . ' و حداکثر ' . RX_SBV_SUGGEST_RANGE_MAX_TOMAN . ').\nمقدار فعلی: ' . number_format(\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_suggestion_range_toman()) . ' تومان', $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_q_(\d+)$/', $datain, $m)) {
            $show(self::rx_sbv_queue_view((int) $m[1]));
            return true;
        }
        if (preg_match('/^sbv_d_(\d+)$/', $datain, $m)) {
            $v = self::rx_sbv_deposit_view((int) $m[1]);
            if ($v === null) {
                $answer('واریز یافت نشد.', true);
                return true;
            }
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_dl_(\d+)$/', $datain, $m)) {
            if (!$isGroupSource) {
                $answer();
            }
            $nonce = $setStep('sbv_link_user', (string) (int) $m[1]);
            $say('🔗 شناسه عددی تلگرام کاربری را که این واریز (#' . (int) $m[1] . ') باید به پرداختش متصل شود ارسال کنید:', $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_dx_(\d+)$/', $datain, $m)) {
            $answer('اقدام بایگانی حذف شده است؛ برای حذف واریز تطبیق‌نیافته از «حذف واریز» استفاده کنید.', true);
            return true;
        }
        if (preg_match('/^sbv_ds_(\d+)(?:_(\d+))?$/', $datain, $m)) {
            $show(self::rx_sbv_admin_candidate_view((int) $m[1], (int) ($m[2] ?? 0), $isAdmin));
            return true;
        }
        if (preg_match('/^sbv_ed_(\d+)_(\d+)_(.+)$/', $datain, $m)) {
            if (!$isAdmin) {
                $answer('فقط مدیر اصلی مجاز به حذف پرداخت منقضی است.', true);
                return true;
            }
            $show(self::rx_sbv_admin_delete_expired_confirm_view(trim((string) $m[3]), (int) $m[1], (int) $m[2]));
            return true;
        }
        if (preg_match('/^sbv_edx_(\d+)_(\d+)_(.+)$/', $datain, $m)) {
            if (!$isAdmin) {
                $answer('فقط مدیر اصلی مجاز به حذف پرداخت منقضی است.', true);
                return true;
            }
            $depositId = (int) $m[1];
            $page = max(0, (int) $m[2]);
            $orderId = trim((string) $m[3]);
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_delete_expired_payment($orderId, $fromId, $depositId);
            $v = self::rx_sbv_admin_candidate_view($depositId, $page, true);
            if (!empty($res['ok'])) {
                $msg = !empty($res['already_removed'])
                    ? "ℹ️ پرداخت <code>" . self::rx_sbv_h($orderId) . "</code> قبلاً از فهرست حذف شده بود."
                    : "✅ پرداخت منقضی <code>" . self::rx_sbv_h($orderId) . "</code> حذف شد.";
            } else {
                $reason = (string) ($res['reason'] ?? 'error');
                $msg = "❌ " . self::rx_sbv_h(self::rx_sbv_error_text($reason));
                $answer("❌ " . self::rx_sbv_error_text($reason), true);
            }
            $v['text'] = $msg . "\n\n" . $v['text'];
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_dd_(\d+)$/', $datain, $m) || preg_match('/^sbv_dr_(\d+)$/', $datain, $m)) {
            $depositId = (int) $m[1];
            $v = self::rx_sbv_admin_delete_confirm_view($depositId, 'sbv_d_' . $depositId);
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_ddx_(\d+)$/', $datain, $m)) {
            $depositId = (int) $m[1];
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_delete_deposit($depositId, $fromId);
            if (empty($res['ok'])) {
                $err = '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error'));
                if ($isGroupSource) {
                    $answer($err, true);
                } else {
                    $v = self::rx_sbv_deposit_view($depositId);
                    if ($v !== null) {
                        $v['text'] = $err . "\n\n" . $v['text'];
                        $show($v);
                    } else {
                        $show(['text' => $err, 'keyboard' => self::rx_sbv_kb([[['text' => '📥 صف بررسی', 'callback_data' => 'sbv_q_0']]])]);
                    }
                }
                return true;
            }
            $q = self::rx_sbv_queue_view(0);
            $q['text'] = "✅ واریز <code>#" . $depositId . "</code> برای همیشه حذف شد و از صف بررسی خارج شد.\n\n" . $q['text'];
            $show($q);
            return true;
        }
        if (preg_match('/^sbv_rpg_(\d+)$/', $datain, $m)) {
            if ($step !== 'sbv_reject_pay') {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload);
            if (count($parts) < 3) {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $nonce = $setStep('sbv_reject_pay', (int) $parts[0] . '|' . (int) $parts[1] . '|' . (int) $m[1]);
            $v = self::rx_sbv_admin_payment_choices_view((int) $parts[1], (int) $parts[0], (int) $m[1], 'reject', 'sbv_cancel_' . $nonce);
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_lpg_(\d+)$/', $datain, $m)) {
            if ($step !== 'sbv_link_pay') {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload);
            if (count($parts) < 3) {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $nonce = $setStep('sbv_link_pay', (int) $parts[0] . '|' . (int) $parts[1] . '|' . (int) $m[1]);
            $v = self::rx_sbv_admin_payment_choices_view((int) $parts[1], (int) $parts[0], (int) $m[1], 'link', 'sbv_cancel_' . $nonce);
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_rsel_(\w+)$/', $datain, $m)) {
            if ($step !== 'sbv_reject_pay') {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload);
            if (count($parts) < 3) {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $orderId = (string) $m[1];
            $selected = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_get($orderId);
            if ($selected === null || (string) $selected['id_user'] !== (string) $parts[1]) {
                $answer(self::rx_sbv_error_text('selection_changed'), true);
                $nonce = $setStep('sbv_reject_pay', (int) $parts[0] . '|' . (int) $parts[1] . '|' . (int) $parts[2]);
                $v = self::rx_sbv_admin_payment_choices_view((int) $parts[1], (int) $parts[0], (int) $parts[2], 'reject', 'sbv_cancel_' . $nonce);
                $show($v);
                return true;
            }
            $nonce = $setStep('sbv_reject_ready', (int) $parts[0] . '|' . $orderId);
            $v = self::rx_sbv_admin_reject_preview((int) $parts[0], $orderId, 'sbv_cancel_' . $nonce);
            if ($v === null) {
                $answer('پرداخت انتخاب‌شده دیگر قابل بررسی نیست.', true);
                return true;
            }
            $show($v);
            return true;
        }
        if (preg_match('/^sbv_lsel_(\w+)$/', $datain, $m)) {
            if ($step !== 'sbv_link_pay') {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload);
            if (count($parts) < 3) {
                $answer('این فهرست دیگر فعال نیست.', true);
                return true;
            }
            $orderId = (string) $m[1];
            $selected = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_get($orderId);
            if ($selected === null || (string) $selected['id_user'] !== (string) $parts[1]) {
                $answer(self::rx_sbv_error_text('selection_changed'), true);
                $nonce = $setStep('sbv_link_pay', (int) $parts[0] . '|' . (int) $parts[1] . '|' . (int) $parts[2]);
                $v = self::rx_sbv_admin_payment_choices_view((int) $parts[1], (int) $parts[0], (int) $parts[2], 'link', 'sbv_cancel_' . $nonce);
                $show($v);
                return true;
            }
            $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($orderId, (int) $parts[0]);
            if ($guard !== null) {
                $answer(self::rx_sbv_error_text($guard), true);
                $nonce = $setStep('sbv_link_pay', (int) $parts[0] . '|' . (int) $parts[1] . '|' . (int) $parts[2]);
                $v = self::rx_sbv_admin_payment_choices_view((int) $parts[1], (int) $parts[0], (int) $parts[2], 'link', 'sbv_cancel_' . $nonce);
                $show($v);
                return true;
            }
            $nonce = $setStep('sbv_link_confirm', (int) $parts[0] . '|' . $orderId);
            $p = self::rx_sbv_link_preview((int) $parts[0], $orderId, 'sbv_cancel_' . $nonce);
            if ($p === null) {
                $answer('پرداخت یا واریز انتخاب‌شده دیگر قابل بررسی نیست.', true);
                return true;
            }
            $say($p['text'], $p['keyboard']);
            return true;
        }
        if ($datain === 'sbv_rr') {
            if ($step !== 'sbv_reject_ready') {
                $answer('این درخواست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload, 2);
            $depositId = (int) ($parts[0] ?? 0);
            $orderId = (string) ($parts[1] ?? '');
            $answer();
            $nonce = $setStep('sbv_reject_reason', $depositId . '|' . $orderId);
            $say('✍️ دلیل رد درخواست پرداخت سفارش <code>' . self::rx_sbv_h($orderId) . '</code> را بنویسید. این دلیل برای مشتری و گزارش مالی ثبت می‌شود.', $cancelKb);
            return true;
        }
        if ($datain === 'sbv_aac') {
            if ($step !== 'sbv_approve_confirm') {
                $answer('این درخواست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload, 2);
            $orderId = (string) ($parts[0] ?? '');
            $reason = isset($parts[1]) ? base64_decode((string) $parts[1], true) : '';
            if ($orderId === '' || $reason === false || \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_confirm_guard($orderId, $reason) !== null) {
                $clearStep();
                $answer('دلیل تأیید معتبر نیست.', true);
                return true;
            }
            $answer();
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_approve($orderId, $fromId, $reason);
            $clearStep();
            $note = !empty($res['ok']) ? self::rx_sbv_admin_approval_message($res) : '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error'));
            $v = self::rx_sbv_order_view($orderId);
            if ($v !== null) {
                $v['text'] = $note . "\n\n" . $v['text'];
                $show($v);
            } else {
                $say($note);
            }
            return true;
        }
        if ($datain === 'sbv_ccc') {
            if ($step !== 'sbv_credit_confirm') {
                $answer('این درخواست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload, 2);
            $orderId = (string) ($parts[0] ?? '');
            $reason = isset($parts[1]) ? base64_decode((string) $parts[1], true) : '';
            if ($orderId === '' || $reason === false || trim($reason) === '') {
                $clearStep();
                $answer('دلیل تأیید معتبر نیست.', true);
                return true;
            }
            $answer();
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_credit($orderId, $fromId, $reason);
            $clearStep();
            $note = !empty($res['ok'])
                ? '✅ کل مبلغ ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money((int) ($res['credit'] ?? 0)) . ' تومان به کیف پول کاربر اضافه شد؛ کارمزد شناسایی کسر نشد.'
                : '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error'));
            $v = self::rx_sbv_order_view($orderId);
            if ($v !== null) {
                $v['text'] = $note . "\n\n" . $v['text'];
                $show($v);
            } else {
                $say($note);
            }
            return true;
        }
        if ($datain === 'sbv_amc') {
            if ($step !== 'sbv_amount_confirm') {
                $answer('این درخواست دیگر فعال نیست.', true);
                return true;
            }
            $parts = explode('|', $storedPayload, 3);
            $orderId = (string) ($parts[0] ?? '');
            $newToman = (int) ($parts[1] ?? 0);
            $reason = isset($parts[2]) ? base64_decode((string) $parts[2], true) : '';
            if ($orderId === '' || $newToman <= 0 || $reason === false || trim($reason) === '') {
                $clearStep();
                $answer('اطلاعات اصلاح مبلغ معتبر نیست.', true);
                return true;
            }
            $answer();
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_set_received($orderId, $fromId, $newToman, $reason);
            $clearStep();
            $note = !empty($res['ok'])
                ? '✅ مبلغ دریافتی به ' . \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_money($newToman) . ' تومان اصلاح شد. این اقدام به‌تنهایی کیف پول را شارژ یا سفارش را تحویل نکرد.'
                : '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error'));
            $v = self::rx_sbv_order_view($orderId);
            if ($v !== null) {
                $v['text'] = $note . "\n\n" . $v['text'];
                $show($v);
            } else {
                $say($note);
            }
            return true;
        }
        if ($datain === 'sbv_rc') {
            if ($step !== 'sbv_reject_confirm') {
                $answer('این درخواست دیگر فعال نیست یا قبلاً ثبت شده است.', true);
                return true;
            }
            $parts = explode('|', $storedPayload, 3);
            $depositId = (int) ($parts[0] ?? 0);
            $orderId = (string) ($parts[1] ?? '');
            $reason = isset($parts[2]) ? base64_decode((string) $parts[2], true) : '';
            if ($reason === false || trim($reason) === '') {
                $clearStep();
                $answer('دلیل رد پرداخت معتبر نیست.', true);
                return true;
            }
            $answer();
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_admin_reject($orderId, $fromId, $reason, false, $depositId > 0 ? $depositId : null);
            if (empty($res['ok'])) {
                $clearStep();
                $err = '❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error'));
                $v = self::rx_sbv_order_view($orderId);
                if ($v !== null) {
                    $v['text'] = $err . "\n\n" . $v['text'];
                    $show($v);
                } else {
                    $say($err);
                }
                return true;
            }
            $alreadyRejected = !empty($res['already_rejected']);
            $reportState = (string) ($res['report_state'] ?? 'failed');
            if ($reportState !== 'failed' && $reportState !== 'busy') {
                $clearStep();
            }
            $msg = $alreadyRejected
                ? "ℹ️ این پرداخت قبلاً رد شده بود و عملیات رد یا اعلان مجدد تکرار نشد."
                : "✅ درخواست پرداخت رد شد. رکورد واریز بانکی حفظ شد و هیچ وجهی بازگشت یا به کیف پول اضافه نشد.";
            if (!$alreadyRejected) {
                if (empty($res['notified'])) {
                    $msg .= "\n⚠️ ارسال پیام خصوصی به مشتری تأیید نشد؛ وضعیت پرداخت و دلیل برای مینی‌اپ ثبت شده است.";
                } else {
                    $msg .= "\n📩 پیام رد پرداخت برای مشتری ارسال شد.";
                }
            }
            if ($reportState === 'sent') {
                $msg .= "\n📣 گزارش مالی در موضوع گزارش پرداخت ثبت شد.";
            } elseif ($reportState === 'failed' || $reportState === 'busy') {
                $msg .= "\n⚠️ ارسال گزارش مالی تأیید نشد. دکمه «تلاش مجدد ارسال گزارش» را بزنید؛ فقط گزارش ارسال می‌شود و رد پرداخت یا اعلان مشتری تکرار نمی‌شود.";
            } elseif ($reportState === 'uncertain') {
                $msg .= "\n⚠️ وضعیت ارسال گزارش مالی نامشخص است. برای جلوگیری از ارسال تکراری، تلاش خودکار انجام نمی‌شود؛ لطفاً موضوع گزارش پرداخت را بررسی کنید.";
            } else {
                $msg .= "\n⚠️ گزارش مالی هنوز ارسال نشده است؛ لطفاً دوباره تأیید کنید.";
            }
            $v = self::rx_sbv_order_view($orderId);
            if ($v !== null) {
                $v['text'] = $msg . "\n\n" . $v['text'];
                if ($reportState === 'failed' || $reportState === 'busy') {
                    $v['keyboard'] = self::rx_sbv_kb([
                        [['text' => '🔁 تلاش مجدد ارسال گزارش', 'callback_data' => 'sbv_rc']],
                        [['text' => '🔙 بازگشت', 'callback_data' => 'sbv_q_0']],
                    ]);
                }
                $show($v);
            } else {
                $say($msg);
            }
            return true;
        }
        if (preg_match('/^sbv_lk_(\d+)_(\w+)$/', $datain, $m)) {
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_link((int) $m[1], $m[2], $fromId);
            if (empty($res['ok'])) {
                $answer('❌ ' . self::rx_sbv_error_text((string) $res['reason']), true);
                return true;
            }
            $clearStep();
            $v = self::rx_sbv_order_view($m[2]);
            if ($v !== null) {
                $v['text'] = "✅ واریز متصل شد. نتیجه را انتخاب کنید.\n\n" . $v['text'];
                $show($v);
            }
            return true;
        }
        if (preg_match('/^sbv_(o|oo)_(\w+)$/', $datain, $m)) {
            $orderView($m[2], $m[1] === 'o');
            return true;
        }
        if (preg_match('/^sbv_oa_(\w+)$/', $datain, $m)) {
            $orderId = $m[1];
            $approveCsp = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_get($orderId);
            $approveReport = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_report($orderId);
            $approveExpired = is_array($approveCsp) && \Faoxima\Payments\Receipts\ReceiptOrders::paymentIsExpired($approveCsp, $approveReport);
            if (\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_confirm_guard($orderId, '') === null && $approveCsp !== null) {
                $answer();
                $nonce = $setStep('sbv_approve_confirm', $orderId . '|' . base64_encode(''));
                $v = self::rx_sbv_admin_approval_confirm_view($orderId, '', 'sbv_cancel_' . $nonce);
                $show($v);
                return true;
            }
            $answer();
            $setStep('sbv_reason', 'approve|' . $orderId);
            $say($approveExpired
                ? "⏳ این پرداخت منقضی شده است. تأیید دستی فقط با تصمیم صریح ادمین و ثبت دلیل انجام می‌شود.\n\nدلیل تأیید را بنویسید:"
                : "✍️ هیچ واریز دقیقاً منطبقی برای این سفارش ثبت نشده است. تأیید کامل یک تصمیم مالی دستی است (مثلاً پذیرش کسری).\n\nدلیل تأیید را بنویسید:", $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_(or|ob)_(\w+)$/', $datain, $m)) {
            $answer();
            $setStep('sbv_reason', ($m[1] === 'ob' ? 'block|' : 'reject|') . $m[2]);
            $say($m[1] === 'ob' ? '🚫 دلیل رد رسید و مسدودسازی کاربر را بنویسید:' : '❌ دلیل رد رسید را بنویسید:', $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_om_(\w+)$/', $datain, $m)) {
            $answer();
            $setStep('sbv_amount', $m[1]);
            $say("✍️ مبلغ دریافتی اصلاح‌شده را به <b>تومان</b> وارد کنید (عدد صحیح).\nاین مبلغ فقط به‌عنوان مبلغ مؤثر ثبت می‌شود و به‌تنهایی کیف پول را شارژ یا سفارش را تحویل نمی‌کند.", $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_oc_(\w+)$/', $datain, $m)) {
            $answer();
            $setStep('sbv_reason', 'credit|' . $m[1]);
            $say('💼 دلیل واریز کامل مبلغ دریافتی به کیف پول (بدون کسر کارمزد شناسایی و بدون تحویل سفارش) را بنویسید:', $cancelKb);
            return true;
        }
        if (preg_match('/^sbv_ols_(\d+)_(\w+)$/', $datain, $m)) {
            $answer();
            $show(self::rx_sbv_admin_order_deposits_view((string) $m[2], (int) $m[1]));
            return true;
        }
        if (preg_match('/^sbv_olv_(\d+)_(\w+)$/', $datain, $m)) {
            $depositId = (int) $m[1];
            $orderId = (string) $m[2];
            if (!self::rx_sbv_cb_ok($orderId) || $depositId <= 0) {
                $answer('سفارش یا واریز انتخاب‌شده نامعتبر است.', true);
                return true;
            }
            $candidate = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_deposit_candidates($orderId, 1, 0, $depositId);
            if (!empty($candidate['blocked'])) {
                $answer(self::rx_sbv_error_text((string) $candidate['blocked']), true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $found = false;
            foreach ((array) ($candidate['rows'] ?? []) as $candidateRow) {
                if ((int) ($candidateRow['id'] ?? 0) === $depositId) {
                    $found = true;
                    break;
                }
            }
            if (empty($candidate['ok']) || !$found) {
                $answer('شرایط تطبیق این واریز تغییر کرده است؛ لطفاً از فهرست به‌روز واریز را انتخاب کنید.', true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_link($depositId, $orderId, $fromId);
            if (empty($res['ok'])) {
                $answer('❌ ' . self::rx_sbv_error_text((string) ($res['reason'] ?? 'error')), true);
                return true;
            }
            $clearStep();
            $v = self::rx_sbv_order_view($orderId);
            if ($v !== null) {
                $v['text'] = "✅ واریز متصل شد. نتیجه را انتخاب کنید.\n\n" . $v['text'];
                $show($v);
            }
            return true;
        }
        if (preg_match('/^sbv_olc_(\d+)_(\w+)$/', $datain, $m)) {
            $depositId = (int) $m[1];
            $orderId = (string) $m[2];
            if (!self::rx_sbv_cb_ok($orderId) || $depositId <= 0) {
                $answer('سفارش یا واریز انتخاب‌شده نامعتبر است.', true);
                return true;
            }
            $candidate = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_order_deposit_candidates($orderId, 1, 0, $depositId);
            if (!empty($candidate['blocked'])) {
                $answer(self::rx_sbv_error_text((string) $candidate['blocked']), true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $found = false;
            foreach ((array) ($candidate['rows'] ?? []) as $candidateRow) {
                if ((int) ($candidateRow['id'] ?? 0) === $depositId) {
                    $found = true;
                    break;
                }
            }
            if (empty($candidate['ok']) || !$found) {
                $answer('این واریز دیگر در فهرست پیشنهادهای این پرداخت نیست یا قبلاً مصرف شده است.', true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $guard = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_payment_link_guard($orderId, $depositId);
            if ($guard !== null) {
                $answer(self::rx_sbv_error_text($guard), true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $preview = self::rx_sbv_link_preview($depositId, $orderId, 'sbv_ol_' . $orderId, 'sbv_olv_' . $depositId . '_' . $orderId);
            if ($preview === null) {
                $answer('واریز یا پرداخت انتخاب‌شده دیگر قابل بررسی نیست.', true);
                $show(self::rx_sbv_admin_order_deposits_view($orderId));
                return true;
            }
            $answer();
            $show($preview);
            return true;
        }
        if (preg_match('/^sbv_ol_(\w+)$/', $datain, $m)) {
            $answer();
            $show(self::rx_sbv_admin_order_deposits_view($m[1]));
            return true;
        }
        $answer();
        return true;
    }
}
