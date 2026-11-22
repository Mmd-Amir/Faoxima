<?php

namespace Faoxima\Payments\Gateways\Atlaspay;

final class BotRuntime
{
    public static function atlaspayCustomRegisterWebhook()
    {
        global $domainhosts, $pdo;
        return \Faoxima\Payments\Gateways\Atlaspay\CustomClient::registerWebhook($domainhosts, $pdo);
    }

    public static function atlaspayReceiptBotUrl($url)
    {
        $url = \Faoxima\Payments\Gateways\Atlaspay\CustomClient::validReceiptUrl($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (strtolower((string) ($parts['path'] ?? '')) !== '/atlaspaybot'
            || isset($query['startapp'])
            || !is_string($query['start'] ?? null)
            || preg_match('/^r_[A-Za-z0-9_-]+$/D', $query['start']) !== 1) {
            return '';
        }
        return 'https://t.me/atlaspaybot?start=' . rawurlencode($query['start']);
    }

    public static function atlaspayReceiptInstructions()
    {
        return '📎 پس از واریز، در صورتی که پرداخت ظرف ۲ دقیقه به‌صورت خودکار تأیید نشد، از دکمه «ارسال رسید» وارد ربات اطلس‌پی شوید و تصویر رسید را ارسال نمایید. برای هر سفارش تنها یک رسید قابل ثبت است.';
    }

    public static function atlaspayBotReceiptButtonText()
    {
        return \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_atlaspay_bot_receipt_button', [], false);
    }

    public static function atlaspayBotReceiptInstructions()
    {
        return \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_atlaspay_bot_receipt_instructions', ['receipt_button' => self::atlaspayBotReceiptButtonText()]);
    }

    public static function atlaspayCustomFetchReport($orderId)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($pdo, $orderId);
    }

    public static function atlaspayCustomPhaseLabel($phase)
    {
        $labels = [
            'awaiting_payment' => 'در انتظار پرداخت',
            'deadline_check' => 'پایان مهلت پرداخت؛ در حال بررسی وضعیت نهایی',
            'under_review' => 'رسید در حال بررسی اطلس‌پی',
            'underpaid_review' => 'بررسی مغایرت مبلغ واریزی',
            'underpaid_remainder' => 'در انتظار تکمیل مبلغ باقی‌مانده',
            'confirming' => 'تأیید شده و در حال نهایی‌سازی',
            'manual_review' => 'تأیید با کسری مبلغ؛ منتظر تصمیم مدیر',
            'paid' => 'پرداخت تأییدشده',
            'rejected' => 'ردشده',
            'expired' => 'منقضی‌شده',
            'canceled' => 'لغو‌شده',
        ];
        return $labels[$phase] ?? '';
    }

    public static function atlaspayCustomAmountWarningTemplate()
    {
        return '⚠️ لطفاً مبلغ پرداخت را حتماً با استفاده از دکمه «%s» کپی کرده و دقیقاً همان مبلغ را واریز کنید. از گرد کردن یا تغییر مبلغ خودداری کنید.';
    }

    public static function atlaspayCustomCardOnlyWarning()
    {
        return '🚫 پرداخت فقط از طریق کارت‌به‌کارت مجاز است. از واریز با شماره شبا، پایا، ساتنا یا سایر روش‌های انتقال بانکی خودداری کنید.';
    }

    public static function atlaspayCustomBotAmountWarning($copyLabel)
    {
        return \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_amount_warning', ['copy_button_name' => $copyLabel]);
    }

    public static function atlaspayCustomBotCardOnlyWarning()
    {
        return \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_card_only_warning');
    }

    public static function atlaspayCustomFormatClock($seconds)
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds >= 3600) {
            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public static function atlaspayCustomFormatDateTime($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '—';
        }
        return function_exists('jdate') ? jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
    }

    public static function atlaspayCustomBotText(array $state)
    {
        $e = [\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::class, 'escape'];
        $lines = ['💠 ' . $e(\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::gatewayTitle()), ''];
        $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_amount', ['amount' => number_format($state['request_amount'])]);
        $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_payable', ['payable_rial' => number_format($state['payable_rial'])]);
        $lines[] = '';
        if ($state['phase'] === 'awaiting_payment') {
            $lines[] = self::atlaspayCustomBotAmountWarning('کپی مبلغ پرداخت');
            $lines[] = '';
            $lines[] = self::atlaspayCustomBotCardOnlyWarning();
            $lines[] = '';
            if ($state['card_complete']) {
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_card_number', ['card_number' => preg_replace('/\D/', '', $state['card_number'])]);
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_card_holder', ['card_holder' => $state['card_name'] !== '' ? $state['card_name'] : '—']);
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_bank', ['bank_name' => $state['bank_name'] !== '' ? $state['bank_name'] : '—']);
            } else {
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get($state['payment_url'] !== '' ? 'payment_atlaspay_card_incomplete_with_link' : 'payment_atlaspay_card_incomplete_no_link');
            }
            $lines[] = '';
        }
        $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_tracking', ['tracking_code' => $state['tracking_code']]);
        $lines[] = '';
        $reason = $state['phase'] === 'rejected' ? $e($state['reason']) : '';
        $lines[] = self::atlaspayBotPhaseDetail($state['phase'], '') . ($reason !== '' ? "\n" . $reason : '');
        if ($state['phase'] === 'awaiting_payment') {
            if ($state['receipt_url'] !== '') {
                $lines[] = '';
                $lines[] = self::atlaspayBotReceiptInstructions();
            }
            $lines[] = '';
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_remaining_time', ['remaining' => self::atlaspayCustomFormatClock($state['remaining'])]);
        }
        return implode("\n", $lines);
    }

    public static function atlaspayBotPhaseLabel($phase)
    {
        $known = ['awaiting_payment', 'deadline_check', 'under_review', 'underpaid_review', 'underpaid_remainder', 'confirming', 'manual_review', 'paid', 'rejected', 'expired', 'canceled'];
        return in_array($phase, $known, true) ? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_atlaspay_phase_label_' . $phase) : '';
    }

    public static function atlaspayBotPhaseDetail($phase, $reason = '')
    {
        $known = ['awaiting_payment', 'deadline_check', 'under_review', 'underpaid_review', 'underpaid_remainder', 'confirming', 'manual_review', 'paid', 'rejected', 'expired', 'canceled'];
        if (!in_array($phase, $known, true)) {
            return '';
        }
        $reason = (string) $reason;
        $raw = $phase === 'rejected' ? ['reason_line' => $reason !== '' ? "\n" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($reason) : ''] : [];
        return \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_phase_detail_' . $phase, [], $raw);
    }

    public static function atlaspayCustomSupportUrl()
    {
        global $usernamebot;
        $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', 'support_username', null, null, 'select') : null;
        $handle = is_array($row) ? ltrim(trim((string) ($row['support_username'] ?? '')), '@') : '';
        if ($handle !== '') {
            return 'https://t.me/' . $handle;
        }
        $bot = isset($usernamebot) ? ltrim(trim((string) $usernamebot), '@') : '';
        return $bot !== '' ? 'https://t.me/' . $bot : '';
    }

    public static function atlaspayCustomFollowUpKeyboard(array $state)
    {
        $rows = [[rx_payment_button('atlaspay', 'view_details', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_view_details'), 'callback_data' => 'apc_view_' . $state['order_id']])]];
        $supportUrl = self::atlaspayCustomSupportUrl();
        if ($supportUrl !== '') {
            $rows[] = [rx_payment_button('atlaspay', 'support', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_support'), 'url' => $supportUrl])];
        }
        $rows[] = [rx_payment_button('atlaspay', 'pending_list', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_pending_list'), 'callback_data' => 'tpc_list'])];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function atlaspayCustomBotKeyboard(array $state)
    {
        $orderId = $state['order_id'];
        $rows = [];
        if ($state['phase'] === 'awaiting_payment') {
            if (!$state['card_complete'] && $state['payment_url'] !== '') {
                $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_view_payment_info'), 'url' => $state['payment_url']], 'pay_link', 'atlaspay')];
            }
            $copyRow = [];
            if ($state['card_complete']) {
                $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_card'), 'copy_text' => ['text' => preg_replace('/\D/', '', $state['card_number'])], '_rxck' => 'card_num'], 'copy_card', 'atlaspay');
            }
            $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_amount'), 'copy_text' => ['text' => (string) $state['payable_rial']], '_rxck' => 'card_amount'], 'copy_amount', 'atlaspay');
            $rows[] = $copyRow;
        }
        $actionRow = [];
        if (!$state['terminal'] && $state['phase'] !== 'confirming') {
            $actionRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_check_status'), 'callback_data' => 'apc_chk_' . $orderId], 'check_status', 'atlaspay');
        }
        if ($state['receipt_url'] !== '') {
            $actionRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => self::atlaspayBotReceiptButtonText(), 'url' => $state['receipt_url']], 'send_receipt', 'atlaspay');
        }
        if (!empty($actionRow)) {
            $rows[] = $actionRow;
        }
        if ($state['can_cancel']) {
            $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_transaction'), 'callback_data' => 'apc_can_' . $orderId], 'cancel_transaction', 'atlaspay')];
        }
        $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_main'), 'callback_data' => 'backuser'], 'back_main', 'atlaspay')];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function atlaspayCustomBotCancelConfirmView($orderId, $backData)
    {
        $text = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_cancel_confirm');
        $keyboard = json_encode(['inline_keyboard' => [
            [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_confirm_yes'), 'callback_data' => 'apc_cny_' . $orderId], 'cancel_confirm_approve', 'atlaspay')],
            [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => $backData], 'cancel_confirm_back', 'atlaspay')],
        ]], JSON_UNESCAPED_UNICODE);
        return ['text' => $text, 'keyboard' => $keyboard];
    }

    public static function atlaspayCustomBotSend($chatId, $orderId)
    {
        $report = self::atlaspayCustomFetchReport($orderId);
        if ($report === null) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
        $response = sendmessage($chatId, self::atlaspayCustomBotText($state), self::atlaspayCustomBotKeyboard($state), 'HTML');
        if (method_exists(\Faoxima\Payments\Settlement\PendingInvoices::class, 'updatePaymentMessageId')) {
            \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($response, $orderId);
        }
        return true;
    }

    public static function atlaspayCustomBotRender($chatId, $messageId, $orderId, ?array $report = null)
    {
        $report = $report ?? self::atlaspayCustomFetchReport($orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
        Editmessagetext($chatId, $messageId, self::atlaspayCustomBotText($state), self::atlaspayCustomBotKeyboard($state), 'HTML');
        return true;
    }

    public static function atlaspayCustomBotRefreshOriginal($chatId, $orderId, $currentMessageId)
    {
        $report = self::atlaspayCustomFetchReport($orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
            return false;
        }
        $originalId = (int) ($report['message_id'] ?? 0);
        if ($originalId > 0 && $originalId !== (int) $currentMessageId) {
            self::atlaspayCustomBotRender($chatId, $originalId, $orderId, $report);
            if ((int) $currentMessageId > 0) {
                deletemessage($chatId, $currentMessageId);
            }
            return true;
        }
        return self::atlaspayCustomBotRender($chatId, $currentMessageId, $orderId, $report);
    }

    public static function atlaspayCustomBotAnswer($callbackQueryId, $text = '', $alert = false)
    {
        if ($callbackQueryId === '' || $callbackQueryId === 0 || $callbackQueryId === null) {
            return;
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $alert,
            'cache_time' => 0,
        ]);
    }

    public static function atlaspayCustomNotifyUser(array $report, $phaseOverride = null)
    {
        if (!function_exists('sendmessage')) {
            return;
        }
        $fresh = self::atlaspayCustomFetchReport($report['id_order']) ?? $report;
        $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($fresh);
        if ($phaseOverride !== null) {
            $state['phase'] = $phaseOverride;
            $state['phase_label'] = self::atlaspayCustomPhaseLabel($phaseOverride);
        }
        $reason = $state['phase'] === 'rejected' ? $state['reason'] : '';
        $text = self::atlaspayBotPhaseDetail($state['phase'], $reason)
            . "\n\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_tracking', ['tracking_code' => $state['tracking_code']]);
        sendmessage($fresh['id_user'], $text, self::atlaspayCustomFollowUpKeyboard($state), 'HTML');
    }

    public static function atlaspayCustomReportToAdmin($text)
    {
        global $setting;
        $channel = is_array($setting ?? null) ? (string) ($setting['Channel_Report'] ?? '') : '';
        if ($channel === '') {
            $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('setting', 'Channel_Report', null, null, 'select') : null;
            $channel = is_array($row) ? (string) ($row['Channel_Report'] ?? '') : '';
        }
        if ($channel === '' || !function_exists('telegram')) {
            return;
        }
        $threadRow = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select");
        $payload = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
        if (is_array($threadRow) && !empty($threadRow['idreport'])) {
            $payload['message_thread_id'] = $threadRow['idreport'];
        }
        telegram('sendmessage', $payload);
    }

    public static function atlaspayCustomNotifyManualDelivery(array $report, $actualReceived)
    {
        $fresh = self::atlaspayCustomFetchReport($report['id_order']) ?? $report;
        $text = "⚠️ سفارش اطلس‌پی با کسری واریز تأیید شد — نیاز به بررسی دستی.\n"
            . "🛒 کد فاکتور: <code>" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($fresh['id_order']) . "</code>\n"
            . "🧾 شناسه پیگیری اطلس‌پی: <code>" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($fresh['atlaspay_tracking_code'] ?? '') . "</code>\n"
            . "👤 آیدی کاربر: <code>" . \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::escape($fresh['id_user']) . "</code>\n"
            . "💰 مبلغ درخواستی (اعتبار/خرید): " . number_format((int) $fresh['price']) . " تومان\n"
            . "💳 مبلغ سفارش اطلس‌پی: " . number_format((int) ($fresh['atlaspay_total_amount'] ?? 0)) . " تومان\n"
            . ($actualReceived !== null ? '💸 مبلغ واریزی واقعی: ' . number_format($actualReceived) . ' تومان' : '💸 مبلغ واریزی واقعی: نامشخص');
        self::atlaspayCustomReportToAdmin($text);
        self::atlaspayCustomNotifyUser($fresh, 'manual_review');
    }

    public static function atlaspayCustomPollThrottled($orderId, $userId)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Atlaspay\Settlement::pollThrottled($pdo, $setting, $orderId, $userId);
    }

    public static function atlaspayCustomCancelBlockedMessage(array $state, $telegram = false)
    {
        if (in_array($state['phase'], ['paid', 'confirming', 'manual_review'], true)) {
            return $telegram ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_cancel_blocked_paid') : 'پرداخت شما در اطلس‌پی تأیید شده است و فاکتور قابل لغو نیست.';
        }
        if (in_array($state['phase'], ['under_review', 'underpaid_review', 'underpaid_remainder'], true)) {
            return $telegram ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_cancel_blocked_review') : 'برای این سفارش پرداخت یا رسید ثبت شده و اطلس‌پی اجازه لغو نمی‌دهد.';
        }
        return $telegram ? \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_cancel_blocked_generic') : 'این فاکتور دیگر قابل لغو نیست.';
    }

    public static function atlaspayCustomCancel($orderId, $userId, $telegram = false)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Atlaspay\Settlement::cancel($pdo, $setting, $orderId, $userId, $telegram);
    }

    public static function atlaspayCustomPurgeProtected($orderId)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Atlaspay\Settlement::purgeProtected($pdo, $orderId);
    }

    public static function atlaspayCustomUserPending($userId, $limit = 10)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Atlaspay\Settlement::userPending($pdo, $userId, $limit);
    }

    public static function atlaspayCustomBotListEntries($userId)
    {
        $lines = [];
        $rows = [];
        foreach (self::atlaspayCustomUserPending($userId) as $report) {
            $state = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($report);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_tracking', ['tracking_code' => $state['tracking_code']]);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_gateway', ['gateway' => \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::gatewayTitle()]);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_kind', ['kind' => $state['purchase_kind']]);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_amount', ['amount' => number_format($state['request_amount'])]);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_status', ['status' => self::atlaspayBotPhaseLabel($state['phase'])]);
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_created', ['created_at' => self::atlaspayCustomFormatDateTime($state['created_at'])]);
            if ($state['phase'] === 'awaiting_payment') {
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_atlaspay_line_remaining_time', ['remaining' => self::atlaspayCustomFormatClock($state['remaining'])]);
            }
            $lines[] = '';
            $buttons = [rx_payment_button('atlaspay', 'list_view', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_view_details'), 'callback_data' => 'apc_view_' . $state['order_id']])];
            if ($state['receipt_url'] !== '') {
                $buttons[] = rx_payment_button('atlaspay', 'list_receipt', ['text' => self::atlaspayBotReceiptButtonText(), 'url' => $state['receipt_url']]);
            }
            $rows[] = $buttons;
            if ($state['can_cancel']) {
                $rows[] = [rx_payment_button('atlaspay', 'list_cancel', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_transaction'), 'callback_data' => 'apc_can_' . $state['order_id']])];
            }
        }
        return ['lines' => $lines, 'rows' => $rows];
    }

    public static function atlaspayCustomBotListMerge($userId, array $view)
    {
        $entries = self::atlaspayCustomBotListEntries($userId);
        if (empty($entries['lines'])) {
            return $view;
        }
        $keyboard = json_decode((string) $view['keyboard'], true);
        $rows = is_array($keyboard) && isset($keyboard['inline_keyboard']) ? $keyboard['inline_keyboard'] : [];
        $back = empty($rows) ? [rx_payment_button('pending', 'back_account', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => 'account'])] : array_pop($rows);
        if (method_exists(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::class, 'tonpayCustomUserPending') && !empty(\Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomUserPending($userId, 1))) {
            $text = rtrim((string) $view['text']) . "\n\n" . implode("\n", $entries['lines']);
        } else {
            $text = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_list_title') . "\n\n" . implode("\n", $entries['lines']);
        }
        foreach ($entries['rows'] as $row) {
            $rows[] = $row;
        }
        $rows[] = $back;
        return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function atlaspayCustomUpdateOfficialChecked($orderId)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Atlaspay\Recovery::updateOfficialChecked($pdo, $orderId);
    }

    public static function atlaspayOfficialV2Process(array $row, $check)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Atlaspay\Recovery::officialV2Process($pdo, $setting, $row, $check);
    }
}
