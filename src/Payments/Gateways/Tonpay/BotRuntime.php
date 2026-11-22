<?php

namespace Faoxima\Payments\Gateways\Tonpay;

final class BotRuntime
{
    public static function tonpayCustomCreateInvoice($orderId, $amount, $buyerChatId)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Tonpay\CustomClient::createInvoice($domainhosts, $orderId, $amount, $buyerChatId);
    }

    public static function tonpayCustomFetchReport($orderId)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($pdo, $orderId);
    }

    public static function tonpayCustomPhaseLabel($phase)
    {
        $labels = [
            'awaiting_payment' => 'در انتظار پرداخت و تأیید خودکار',
            'receipt_available' => 'امکان ارسال رسید',
            'action_required_less' => 'نیاز به اقدام: واریز مبلغ باقی‌مانده',
            'action_required_more' => 'نیاز به اقدام: ثبت کارت عودت مبلغ مازاد',
            'local_timeout' => 'پایان مهلت محلی؛ در انتظار بررسی نهایی تون‌پی',
            'cancel_pending' => 'لغو محلی؛ در انتظار لغو در تون‌پی',
            'unknown' => 'وضعیت فاکتور در حال بررسی',
            'receipt_submitted' => 'رسید در حال بررسی تون‌پی',
            'receipt_rejected' => 'رسید در بررسی اولیه رد شده (در حال پیگیری)',
            'review_timeout' => 'پایان مهلت بررسی',
            'confirming' => 'تأیید شده و در حال نهایی‌سازی',
            'paid' => 'پرداخت تأییدشده',
            'rejected' => 'رسید ردشده',
            'canceled' => 'لغو‌شده',
            'expired' => 'منقضی‌شده',
        ];
        return $labels[$phase] ?? '';
    }

    public static function tonpayCustomReceiptPromptText()
    {
        return \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_receipt_prompt', [], true);
    }

    public static function tonpayCustomReceiptSubmittedText()
    {
        return \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_receipt_submitted', [], true);
    }

    public static function tonpayCustomNotifyReceiptRejected($orderId)
    {
        if (!function_exists('sendmessage')) {
            return;
        }
        $report = self::tonpayCustomFetchReport($orderId);
        if ($report === null) {
            return;
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        $text = self::tonpayCustomStatusText($state, true) . "\n\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_invoice_notice', ['invoice_id' => $state['invoice_id']]);
        sendmessage($report['id_user'], $text, self::tonpayCustomFollowUpKeyboard($state), 'HTML');
    }

    public static function tonpayCustomTerminalNotice(array $report, $phase)
    {
        if (!in_array($phase, ['rejected', 'canceled', 'expired'], true)) {
            return '';
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        $state['phase'] = $phase;
        return self::tonpayCustomStatusText($state, true);
    }

    public static function tonpayCustomPollThrottled($orderId, $userId)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::pollThrottled($pdo, $setting, $orderId, $userId);
    }

    public static function tonpayCustomSubmitReceipt($orderId, $userId, $filePath)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::submitReceipt($pdo, $setting, $orderId, $userId, $filePath);
    }

    public static function tonpayCustomCancel($orderId, $userId, $telegram = false)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::cancel($pdo, $setting, $orderId, $userId, $telegram);
    }

    public static function tonpayCustomCancelBlockedMessage(array $state, $telegram = false)
    {
        if ($telegram) {
            return \Faoxima\Telegram\Support\MessageTemplates::get(self::tonpayBotCancelBlockedKey($state));
        }
        if (in_array($state['phase'], ['paid', 'confirming'], true)) {
            return 'پرداخت شما در تون‌پی تأیید شده است و فاکتور قابل لغو نیست.';
        }
        if ($state['phase'] === 'action_required_less') {
            return 'برای این فاکتور مبلغ باقی‌مانده در انتظار واریز است و تا تعیین وضعیت نهایی قابل لغو نیست.';
        }
        if ($state['phase'] === 'action_required_more') {
            return 'برای این فاکتور باید کارت مقصد عودت مبلغ مازاد ثبت شود و فاکتور تا تعیین وضعیت نهایی قابل لغو نیست.';
        }
        if ($state['phase'] === 'processing' || $state['phase'] === 'receipt_submitted') {
            return 'پرداخت شما در حال بررسی تون‌پی است و تا مشخص شدن نتیجه امکان لغو فاکتور وجود ندارد.';
        }
        if ($state['phase'] === 'receipt_rejected' || $state['phase'] === 'rejected') {
            return 'رسید شما رد شده یا در حال پیگیری است و امکان لغو فاکتور وجود ندارد.';
        }
        if ($state['phase'] === 'unknown') {
            return 'وضعیت فاکتور هنوز از تون‌پی تأیید نشده است و امکان لغو آن وجود ندارد.';
        }
        if ($state['remote_status'] !== 'pending') {
            return 'تنها فاکتورهای در انتظار پرداخت در تون‌پی قابل لغو هستند.';
        }
        return 'این فاکتور دیگر قابل لغو نیست.';
    }

    public static function tonpayBotCancelBlockedKey(array $state)
    {
        if (in_array($state['phase'], ['paid', 'confirming'], true)) {
            return 'payment_tonpay_cancel_blocked_paid';
        }
        if ($state['phase'] === 'action_required_less') {
            return 'payment_tonpay_cancel_blocked_remainder';
        }
        if ($state['phase'] === 'action_required_more') {
            return 'payment_tonpay_cancel_blocked_refund_card';
        }
        if ($state['phase'] === 'processing' || $state['phase'] === 'receipt_submitted') {
            return 'payment_tonpay_cancel_blocked_processing';
        }
        if ($state['phase'] === 'receipt_rejected' || $state['phase'] === 'rejected') {
            return 'payment_tonpay_cancel_blocked_rejected';
        }
        if ($state['phase'] === 'unknown') {
            return 'payment_tonpay_cancel_blocked_unknown';
        }
        if ($state['remote_status'] !== 'pending') {
            return 'payment_tonpay_cancel_blocked_not_pending';
        }
        return 'payment_tonpay_cancel_blocked_generic';
    }

    public static function tonpayBotPhaseLabel($phase)
    {
        $known = ['awaiting_payment', 'receipt_available', 'action_required_less', 'action_required_more', 'local_timeout', 'cancel_pending', 'unknown', 'receipt_submitted', 'receipt_rejected', 'review_timeout', 'confirming', 'paid', 'rejected', 'canceled', 'expired'];
        return in_array($phase, $known, true) ? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_phase_label_' . $phase) : '';
    }

    public static function tonpayCustomSubmitRefund($orderId, $userId, $cardNumber, $cardHolder)
    {
        global $pdo, $setting;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::submitRefund($pdo, $setting, $orderId, $userId, $cardNumber, $cardHolder);
    }

    public static function tonpayCustomPurgeProtected($orderId)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::purgeProtected($pdo, $orderId);
    }

    public static function tonpayCustomFormatDateTime($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '—';
        }
        return function_exists('jdate') ? jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
    }

    public static function tonpayCustomTemplateValues(array $state)
    {
        $card = (string) $state['card_number'];
        return [
            'gateway_title' => \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayGatewayHeading(),
            'purchase_amount' => number_format($state['request_amount']),
            'payable_amount' => number_format($state['payable_rial']),
            'card_number' => $state['card_complete'] ? preg_replace('/[\s\-]/', '', $card) : ($card !== '' ? $card : '—'),
            'cardholder_name' => $state['card_name'] !== '' ? $state['card_name'] : '—',
            'remaining_amount' => number_format((int) ($state['remaining_amount'] ?? 0)),
            'remaining_payable_amount' => number_format((int) ($state['remaining_payable_rial'] ?? 0)),
            'remaining_card_number' => !empty($state['remaining_card_complete']) ? preg_replace('/[\s\-]/', '', (string) $state['remaining_card_number']) : ((string) ($state['remaining_card_number'] ?? '') !== '' ? (string) $state['remaining_card_number'] : '—'),
            'remaining_card_holder' => (string) ($state['remaining_card_holder'] ?? '') !== '' ? (string) $state['remaining_card_holder'] : '—',
            'refund_amount' => number_format((int) ($state['refund_amount'] ?? 0)),
            'invoice_id' => $state['invoice_id'],
            'tracking_code' => $state['tracking_code'],
            'review_deadline' => self::tonpayCustomFormatDateTime($state['review_deadline_at']),
            'remaining_time' => self::tonpayCustomFormatClock($state['phase'] === 'awaiting_payment' ? $state['receipt_wait_remaining'] : $state['review_remaining']),
        ];
    }

    public static function tonpayCustomPaymentWarnings($html, $copyButtonName)
    {
        $amountWarning = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_amount_warning', ['copy_button_name' => $copyButtonName], $html);
        $cardOnlyWarning = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_card_only_warning', [], $html);
        return $amountWarning . "\n\n" . $cardOnlyWarning;
    }

    public static function tonpayCustomStatusText(array $state, $html)
    {
        return \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_status_' . $state['phase'], self::tonpayCustomTemplateValues($state), $html);
    }

    public static function tonpayCustomPhaseMessages(array $state)
    {
        return [
            'status' => self::tonpayCustomStatusText($state, false),
            'amount_warning' => \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_amount_warning', ['copy_button_name' => 'کپی مبلغ'], false),
            'card_only_warning' => \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_card_only_warning', [], false),
        ];
    }

    public static function tonpayCustomFormatClock($seconds)
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds >= 3600) {
            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public static function tonpayCustomBotText(array $state)
    {
        $values = self::tonpayCustomTemplateValues($state);
        $open = in_array($state['phase'], ['awaiting_payment', 'receipt_available'], true);
        $less = $state['phase'] === 'action_required_less';
        $values['amount_warning'] = self::tonpayCustomPaymentWarnings(true, 'کپی مبلغ پرداخت');
        $templateKey = $less ? 'dyn_tonpay_remaining_tpl' : ($open ? 'dyn_tonpay_invoice_tpl' : 'dyn_tonpay_progress_tpl');
        $lines = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender($templateKey, $values, true, ['amount_warning'])];
        if ($open && !$state['card_complete']) {
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_card_incomplete');
        }
        if ($less && !$state['remaining_card_complete']) {
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_remaining_card_incomplete');
        }
        if ($state['phase'] === 'action_required_more' && !empty($state['refund_card_submitted'])) {
            $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_refund_card_registered');
        }
        if ($less) {
            $lines[] = self::tonpayCustomPaymentWarnings(true, 'کپی مبلغ مابقی');
            if ($state['remaining_receipt_received']) {
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_remaining_receipt_received');
            }
        }
        $lines[] = '';
        $lines[] = self::tonpayCustomStatusText($state, true);
        if ($state['phase'] === 'awaiting_payment') {
            $lines[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_countdown_wait', $values, true);
        }
        if (in_array($state['phase'], ['receipt_submitted', 'receipt_rejected'], true)) {
            $lines[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateRender('dyn_tonpay_countdown_review', $values, true);
        }
        return implode("\n", $lines);
    }

    public static function tonpayCustomSupportUrl()
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

    public static function tonpayCustomFollowUpKeyboard(array $state)
    {
        $rows = [[rx_payment_button('tonpay', 'view_details', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_view_details'), 'callback_data' => 'tpc_view_' . $state['order_id']])]];
        $supportUrl = self::tonpayCustomSupportUrl();
        if ($supportUrl !== '') {
            $rows[] = [rx_payment_button('tonpay', 'support', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_support'), 'url' => $supportUrl])];
        }
        $rows[] = [rx_payment_button('tonpay', 'pending_list', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_pending_list'), 'callback_data' => 'tpc_list'])];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function tonpayCustomBotKeyboard(array $state)
    {
        $orderId = $state['order_id'];
        $open = in_array($state['phase'], ['awaiting_payment', 'receipt_available'], true);
        $less = $state['phase'] === 'action_required_less';
        $rows = [];
        if ($open) {
            $copyRow = [];
            if ($state['card_complete']) {
                $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_card'), 'copy_text' => ['text' => preg_replace('/[\s\-]/', '', $state['card_number'])], '_rxck' => 'card_num'], 'copy_card');
            }
            $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_amount'), 'copy_text' => ['text' => (string) $state['payable_rial']], '_rxck' => 'card_amount'], 'copy_amount');
            $rows[] = $copyRow;
        } elseif ($less) {
            $copyRow = [];
            if ($state['remaining_card_complete']) {
                $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_remaining_card'), 'copy_text' => ['text' => preg_replace('/[\s\-]/', '', $state['remaining_card_number'])], '_rxck' => 'remaining_card_num'], 'copy_card');
            }
            $copyRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_copy_remaining_amount'), 'copy_text' => ['text' => (string) $state['remaining_payable_rial']], '_rxck' => 'remaining_card_amount'], 'copy_amount');
            $rows[] = $copyRow;
        }
        if ($open && !$state['change_card_exhausted'] && $state['show_change_card'] !== 0) {
            $changeLabel = $state['change_card_wait'] > 0
                ? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_change_card_wait', ['seconds' => $state['change_card_wait']])
                : \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_change_card');
            $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => $changeLabel, 'callback_data' => 'tpc_chg_' . $orderId], 'change_card')];
        }
        if ($state['can_refund']) {
            $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_refund_card'), 'callback_data' => 'tpc_rfd_' . $orderId], 'refund_card')];
        }
        $actionRow = [];
        if (!$state['terminal'] && $state['phase'] !== 'confirming') {
            $actionRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_check_status'), 'callback_data' => 'tpc_chk_' . $orderId], 'check_status');
        }
        if ($state['can_submit_receipt']) {
            $actionRow[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => $less ? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_send_receipt_remaining') : \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_send_receipt_payment'), 'callback_data' => 'tpc_rcp_' . $orderId], 'send_receipt');
        }
        if (!empty($actionRow)) {
            $rows[] = $actionRow;
        }
        if ($state['can_cancel']) {
            $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_transaction'), 'callback_data' => 'tpc_can_' . $orderId], 'cancel_transaction')];
        }
        $rows[] = [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_main'), 'callback_data' => 'backuser'], 'back_main')];
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function tonpayCustomUserPending($userId, $limit = 10)
    {
        global $pdo;
        return \Faoxima\Payments\Gateways\Tonpay\Settlement::userPending($pdo, $userId, $limit);
    }

    public static function tonpayCustomBotListView($userId)
    {
        $reports = self::tonpayCustomUserPending($userId);
        $rows = [];
        if (empty($reports)) {
            $text = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_list_title') . "\n\n" . \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_list_empty_note');
        } else {
            $lines = [\Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_list_title'), ''];
            foreach ($reports as $index => $report) {
                $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
                $createdAt = (int) $state['created_at'];
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_invoice', ['invoice_id' => $state['invoice_id']]);
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_kind', ['kind' => $state['purchase_kind']]);
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_amount', ['amount' => number_format($state['request_amount'])]);
                if ($state['phase'] === 'action_required_less') {
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_remaining_amount', ['amount' => number_format($state['remaining_amount'])]);
                    if ($state['remaining_card_number'] !== '') {
                        $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_remaining_card', ['card_number' => $state['remaining_card_number']]);
                    }
                    if ($state['remaining_card_holder'] !== '') {
                        $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_card_holder', ['card_holder' => $state['remaining_card_holder']]);
                    }
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_remaining_receipt', [], ['state' => \Faoxima\Telegram\Support\MessageTemplates::get($state['remaining_receipt_received'] ? 'payment_tonpay_state_receipt_sent' : 'payment_tonpay_state_receipt_not_sent')]);
                } elseif ($state['phase'] === 'action_required_more') {
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_refund_amount', ['amount' => number_format($state['refund_amount'])]);
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_refund_card', [], ['state' => \Faoxima\Telegram\Support\MessageTemplates::get($state['refund_card_submitted'] ? 'payment_tonpay_state_refund_card_submitted' : 'payment_tonpay_state_refund_card_missing')]);
                }
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_status', ['status' => self::tonpayBotPhaseLabel($state['phase'])]);
                $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_pending_line_created', ['created_at' => self::tonpayCustomFormatDateTime($createdAt)]);
                if (in_array($state['phase'], ['receipt_submitted', 'receipt_rejected'], true) && $state['review_deadline_at'] > 0) {
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_review_remaining', ['remaining' => self::tonpayCustomFormatClock($state['review_remaining'])]);
                } elseif ($state['phase'] === 'awaiting_payment') {
                    $lines[] = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_line_receipt_wait', ['remaining' => self::tonpayCustomFormatClock($state['receipt_wait_remaining'])]);
                }
                $lines[] = '';
                $buttons = [rx_payment_button('tonpay', 'list_view', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_view_details'), 'callback_data' => 'tpc_view_' . $state['order_id']])];
                if ($state['can_submit_receipt']) {
                    $buttons[] = rx_payment_button('tonpay', 'list_receipt', ['text' => $state['phase'] === 'action_required_less' ? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_send_receipt_remaining') : \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_send_receipt_payment'), 'callback_data' => 'tpc_rcp_' . $state['order_id']]);
                }
                if ($state['can_refund']) {
                    $buttons[] = rx_payment_button('tonpay', 'list_refund', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_refund_card_short'), 'callback_data' => 'tpc_rfd_' . $state['order_id']]);
                }
                if ($state['can_cancel']) {
                    $buttons[] = rx_payment_button('tonpay', 'list_cancel', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_transaction'), 'callback_data' => 'tpc_can_' . $state['order_id']]);
                }
                $rows[] = $buttons;
            }
            $text = implode("\n", $lines);
        }
        $rows[] = [rx_payment_button('tonpay', 'back_account', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => 'account'])];
        return ['text' => $text, 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }

    public static function tonpayCustomBotCancelConfirmView($orderId, $backData)
    {
        $text = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_cancel_confirm');
        $keyboard = json_encode(['inline_keyboard' => [
            [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_cancel_confirm_yes'), 'callback_data' => 'tpc_cny_' . $orderId], 'cancel_confirm_approve')],
            [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => $backData], 'cancel_confirm_back')],
        ]], JSON_UNESCAPED_UNICODE);
        return ['text' => $text, 'keyboard' => $keyboard];
    }

    public static function tonpayCustomBotRefundPromptView($orderId, $backData)
    {
        $text = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_refund_prompt');
        $keyboard = json_encode(['inline_keyboard' => [
            [\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayStyledButton(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => $backData], 'cancel_confirm_back')],
        ]], JSON_UNESCAPED_UNICODE);
        return ['text' => $text, 'keyboard' => $keyboard];
    }

    public static function tonpayCustomBotRefreshOriginal($chatId, $orderId, $currentMessageId)
    {
        $report = self::tonpayCustomFetchReport($orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
            return false;
        }
        $originalId = (int) ($report['message_id'] ?? 0);
        if ($originalId > 0 && $originalId !== (int) $currentMessageId) {
            self::tonpayCustomBotRender($chatId, $originalId, $orderId, $report);
            if ((int) $currentMessageId > 0) {
                deletemessage($chatId, $currentMessageId);
            }
            return true;
        }
        return self::tonpayCustomBotRender($chatId, $currentMessageId, $orderId, $report);
    }

    public static function tonpayCustomBotSend($chatId, $orderId)
    {
        $report = self::tonpayCustomFetchReport($orderId);
        if ($report === null) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        $response = sendmessage($chatId, self::tonpayCustomBotText($state), self::tonpayCustomBotKeyboard($state), 'HTML');
        if (method_exists(\Faoxima\Payments\Settlement\PendingInvoices::class, 'updatePaymentMessageId')) {
            \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($response, $orderId);
        }
        return true;
    }

    public static function tonpayCustomBotRender($chatId, $messageId, $orderId, ?array $report = null)
    {
        $report = $report ?? self::tonpayCustomFetchReport($orderId);
        if ($report === null || (string) $report['id_user'] !== (string) $chatId) {
            return false;
        }
        $state = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($report);
        Editmessagetext($chatId, $messageId, self::tonpayCustomBotText($state), self::tonpayCustomBotKeyboard($state), 'HTML');
        return true;
    }

    public static function tonpayCustomBotAnswer($callbackQueryId, $text = '', $alert = false)
    {
        if ($callbackQueryId === '' || $callbackQueryId === 0) {
            return;
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $alert,
            'cache_time' => 0,
        ]);
    }

    public static function tonpayCustomBotDownloadFile($fileId, &$error)
    {
        global $APIKEY;
        $token = is_string($APIKEY ?? null) ? $APIKEY : '';
        if ($token === '') {
            $row = \Faoxima\Core\Database\Db::select('setting', 'token_bot', null, null, 'select');
            $token = is_array($row) ? (string) ($row['token_bot'] ?? '') : '';
        }
        if ($token === '' || !is_string($fileId) || $fileId === '') {
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_download_unavailable');
            return null;
        }
        $meta = telegram('getFile', ['file_id' => $fileId]);
        $filePath = is_array($meta) ? (string) ($meta['result']['file_path'] ?? '') : '';
        $fileSize = is_array($meta) ? (int) ($meta['result']['file_size'] ?? 0) : 0;
        if ($filePath === '') {
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_file_not_found');
            return null;
        }
        if ($fileSize > TONPAY_CUSTOM_RECEIPT_MAX_BYTES) {
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_file_too_large');
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tpcr_');
        if ($tmp === false) {
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_temp_failed');
            return null;
        }
        $handle = fopen($tmp, 'wb');
        if ($handle === false) {
            @unlink($tmp);
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_temp_failed');
            return null;
        }
        $curl = curl_init('https://api.telegram.org/file/bot' . $token . '/' . $filePath);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR => true,
        ]);
        $ok = curl_exec($curl);
        curl_close($curl);
        fclose($handle);
        if ($ok === false || (int) @filesize($tmp) <= 0) {
            @unlink($tmp);
            $error = \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_download_failed');
            return null;
        }
        return $tmp;
    }

    public static function tonpayCustomBotHandleReceiptUpload($chatId, $orderId, array $message)
    {
        $fileId = '';
        if (!empty($message['photo']) && is_array($message['photo'])) {
            $largest = end($message['photo']);
            $fileId = (string) ($largest['file_id'] ?? '');
        } elseif (!empty($message['document']['file_id'])) {
            $mime = strtolower((string) ($message['document']['mime_type'] ?? ''));
            if (strpos($mime, 'image/') !== 0) {
                return ['ok' => false, 'message' => \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_image_only')];
            }
            $fileId = (string) $message['document']['file_id'];
        } elseif (!empty($message['animation']['file_id'])) {
            $fileId = (string) $message['animation']['file_id'];
        }
        if ($fileId === '') {
            return ['ok' => false, 'message' => \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_receipt_photo_required')];
        }
        $error = '';
        $tmp = self::tonpayCustomBotDownloadFile($fileId, $error);
        if ($tmp === null) {
            return ['ok' => false, 'message' => $error];
        }
        try {
            return self::tonpayCustomSubmitReceipt($orderId, $chatId, $tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
