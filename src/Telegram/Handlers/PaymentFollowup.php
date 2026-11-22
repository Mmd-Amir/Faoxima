<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $apcMatch, $callback_query_id, $datain, $from_id, $message_id, $pdo, $rialitActionMatch, $rialitViewMatch, $step_payment, $telegramRoute, $text, $tpcMatch, $update, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentFollowup
{
    public static function handle(
        $apcMatch,
        $callback_query_id,
        $datain,
        $from_id,
        $message_id,
        $pdo,
        $rialitActionMatch,
        $rialitViewMatch,
        $step_payment,
        $telegramRoute,
        $text,
        $tpcMatch,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'tonpay_list':
            if ($user['step'] == "tpc_receipt") {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            }
            $tpcListView = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotListView($from_id);
            if (method_exists(\Faoxima\Payments\Gateways\Atlaspay\BotRuntime::class, 'atlaspayCustomBotListMerge')) {
                $tpcListView = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotListMerge($from_id, $tpcListView);
            }
            if (method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitBotListMerge')) {
                $tpcListView = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitBotListMerge($from_id, $tpcListView);
            }
            Editmessagetext($from_id, $message_id, $tpcListView['text'], $tpcListView['keyboard'], 'HTML');
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
            break;
        case 'rialit_action':
            $rialitOrder = $rialitActionMatch[2];
            $rialitReport = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($pdo, $rialitOrder);
            if ($rialitReport === null || (string) $rialitReport['id_user'] !== (string) $from_id) {
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_invoice_not_found_alert'), true);
                return;
            }
            if ($rialitActionMatch[1] === 'cancel') {
                $rialitCancelKeyboard = json_encode(['inline_keyboard' => [
                    [rx_payment_button('rialit', 'cancel_confirm_approve', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_confirm_abandon'), 'callback_data' => 'rl_abandon_' . $rialitOrder])],
                    [rx_payment_button('rialit', 'cancel_confirm_back', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back'), 'callback_data' => 'rl_view_' . $rialitOrder])],
                ]], JSON_UNESCAPED_UNICODE);
                Editmessagetext($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_cancel_confirm'), $rialitCancelKeyboard, 'HTML');
            } elseif ($rialitActionMatch[1] === 'abandon') {
                $rialitCancelled = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::cancelLocally($pdo, $rialitOrder, $from_id);
                if (empty($rialitCancelled['ok'])) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, (string) $rialitCancelled['message'], true);
                    return;
                }
                $rialitRetryKeyboard = json_encode(['inline_keyboard' => [
                    [rx_payment_button('rialit', 'retry', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_rialit_retry'), 'callback_data' => 'rl_retry_' . $rialitOrder])],
                    [rx_payment_button('rialit', 'back_account', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_account'), 'callback_data' => 'account', '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true])],
                ]], JSON_UNESCAPED_UNICODE);
                Editmessagetext($from_id, $message_id, (string) $rialitCancelled['message'], $rialitRetryKeyboard, 'HTML');
            } else {
                if ((int) ($rialitReport['rialit_abandoned_at'] ?? 0) <= 0) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_rialit_cancel_first_alert'), true);
                    return;
                }
                $rialitInvoiceParts = explode('|', (string) ($rialitReport['id_invoice'] ?? ''), 2);
                $rialitRetryValues = [
                    'Processing_value' => (string) $rialitReport['price'],
                    'Processing_value_tow' => (string) ($rialitInvoiceParts[0] ?? ''),
                    'Processing_value_one' => (string) ($rialitInvoiceParts[1] ?? ''),
                    'Processing_value_four' => '',
                ];
                if ($rialitRetryValues['Processing_value_tow'] === '' && trim((string) ($rialitReport['discount_code'] ?? '')) !== '') {
                    $rialitRetryValues['Processing_value_four'] = 'chg|' . (int) ($rialitReport['charge_bonus'] ?? 0) . '|' . (string) $rialitReport['discount_code'] . '|' . (string) ($rialitReport['price_before_discount'] ?? 0);
                }
                foreach ($rialitRetryValues as $rialitRetryKey => $rialitRetryValue) {
                    \Faoxima\Core\Database\Db::update('user', $rialitRetryKey, $rialitRetryValue, 'id', $from_id);
                }
                \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_choose_new_method'), $step_payment, 'HTML');
            }
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
            break;
        case 'rialit_view':
            $rialitReport = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($pdo, $rialitViewMatch[1]);
            if ($rialitReport === null || (string) $rialitReport['id_user'] !== (string) $from_id) {
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_invoice_not_found_alert'), true);
                return;
            }
            $rialitReport = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitPollThrottled($rialitViewMatch[1], $from_id) ?? $rialitReport;
            $rialitView = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitBotPaymentView($rialitReport);
            Editmessagetext($from_id, $message_id, $rialitView['text'], $rialitView['keyboard'], 'HTML');
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
            break;
        case 'atlaspay_action':
            $apcAction = $apcMatch[1];
            $apcOrder = $apcMatch[2];
            $apcReport = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($pdo, $apcOrder);
            if ($apcReport === null || (string) $apcReport['id_user'] !== (string) $from_id) {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_invoice_not_found_alert'), true);
                return;
            }
            if ($apcAction == 'view') {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomPollThrottled($apcOrder, $from_id);
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $apcOrder);
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id);
            } elseif ($apcAction == 'chk') {
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomPollThrottled($apcOrder, $from_id);
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotRefreshOriginal($from_id, $apcOrder, $message_id);
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_status_refreshed_alert'));
            } elseif ($apcAction == 'can') {
                $apcCancelState = \Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::buildState($apcReport);
                if (!$apcCancelState['can_cancel']) {
                    \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id, \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancelBlockedMessage($apcCancelState, true), true);
                    return;
                }
                $apcConfirmView = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotCancelConfirmView($apcOrder, 'apc_view_' . $apcOrder);
                Editmessagetext($from_id, $message_id, $apcConfirmView['text'], $apcConfirmView['keyboard'], 'HTML');
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id);
            } else {
                $apcResult = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomCancel($apcOrder, $from_id, true);
                if (empty($apcResult['ok'])) {
                    \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id, (string) ($apcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_cancel_failed_alert')), true);
                    \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
                    return;
                }
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $apcOrder);
                \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_transaction_canceled_alert'));
            }
            break;
        case 'tonpay_action':
            $tpcAction = $tpcMatch[1];
            $tpcOrder = $tpcMatch[2];
            $tpcReport = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($pdo, $tpcOrder);
            if ($tpcReport === null || (string) $tpcReport['id_user'] !== (string) $from_id) {
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_invoice_not_found_alert'), true);
                return;
            }
            if (in_array($user['step'], ['tpc_receipt', 'tpc_refund'], true)) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            }
            if ($tpcAction == 'view') {
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPollThrottled($tpcOrder, $from_id);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $tpcOrder);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
            } elseif ($tpcAction == 'cny') {
                $tpcResult = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancel($tpcOrder, $from_id, true);
                if (empty($tpcResult['ok'])) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, (string) ($tpcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_cancel_failed_alert')), true);
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
                    return;
                }
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $tpcOrder);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_transaction_canceled_alert'));
            } elseif ($tpcAction == 'chk') {
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPollThrottled($tpcOrder, $from_id);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRefreshOriginal($from_id, $tpcOrder, $message_id);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_status_refreshed_alert'));
            } elseif ($tpcAction == 'rfd') {
                $tpcRefundState = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($tpcReport);
                if (!$tpcRefundState['can_refund']) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_refund_not_allowed_alert'), true);
                    return;
                }
                \Faoxima\Telegram\Navigation\Steps::step('tpc_refund', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $tpcOrder, "id", $from_id);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
                $tpcRefundView = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRefundPromptView($tpcOrder, 'tpc_view_' . $tpcOrder);
                sendmessage($from_id, $tpcRefundView['text'], $tpcRefundView['keyboard'], 'HTML');
            } elseif ($tpcAction == 'chg') {
                $tpcResult = \Faoxima\Payments\Gateways\Tonpay\Settlement::changeCard($pdo, $tpcOrder, $from_id);
                if (empty($tpcResult['ok'])) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, (string) ($tpcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_change_card_failed_alert')), true);
                    return;
                }
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRender($from_id, $message_id, $tpcOrder, $tpcResult['report']);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_card_changed_alert'));
            } elseif ($tpcAction == 'can') {
                $tpcCancelState = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($tpcReport);
                if (!$tpcCancelState['can_cancel']) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCancelBlockedMessage($tpcCancelState, true), true);
                    return;
                }
                $tpcConfirmView = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotCancelConfirmView($tpcOrder, 'tpc_view_' . $tpcOrder);
                Editmessagetext($from_id, $message_id, $tpcConfirmView['text'], $tpcConfirmView['keyboard'], 'HTML');
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
            } else {
                $tpcReport = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomPollThrottled($tpcOrder, $from_id) ?? $tpcReport;
                $tpcGate = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::receiptGate(\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::buildState($tpcReport));
                if (!$tpcGate['ok']) {
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id, $tpcGate['message'], true);
                    return;
                }
                \Faoxima\Telegram\Navigation\Steps::step('tpc_receipt', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $tpcOrder, "id", $from_id);
                \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotAnswer($callback_query_id);
                sendmessage($from_id, \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomReceiptPromptText(), json_encode(['inline_keyboard' => [[rx_payment_button('tonpay', 'receipt_back', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_to_status'), 'callback_data' => 'tpc_chk_' . $tpcOrder])]]], JSON_UNESCAPED_UNICODE), 'HTML');
            }
            break;
        case 'tonpay_receipt':
            $tpcOrder = (string) $user['Processing_value'];
            $tpcBackKb = json_encode(['inline_keyboard' => [[rx_payment_button('tonpay', 'receipt_back', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_back_to_status'), 'callback_data' => 'tpc_chk_' . $tpcOrder])]]], JSON_UNESCAPED_UNICODE);
            $tpcResult = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotHandleReceiptUpload($from_id, $tpcOrder, $update['message']);
            if (empty($tpcResult['ok'])) {
                sendmessage($from_id, '❌ ' . ($tpcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_receipt_submit_failed_default')), $tpcBackKb, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            sendmessage($from_id, \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomReceiptSubmittedText(), null, 'HTML');
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRefreshOriginal($from_id, $tpcOrder, 0);
            break;
        case 'tonpay_refund':
            $tpcOrder = (string) $user['Processing_value'];
            $tpcRefundView = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRefundPromptView($tpcOrder, 'tpc_view_' . $tpcOrder);
            $tpcRefundLines = preg_split('/\r\n|\r|\n/', trim((string) $text));
            $tpcRefundCard = preg_replace('/[\s\-]/', '', (string) ($tpcRefundLines[0] ?? ''));
            $tpcRefundHolder = trim((string) ($tpcRefundLines[1] ?? ''));
            if (count($tpcRefundLines) < 2 || !preg_match('/^\d{16}$/', $tpcRefundCard) || $tpcRefundHolder === '') {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('payment_tonpay_refund_invalid_format') . "\n\n" . $tpcRefundView['text'], $tpcRefundView['keyboard'], 'HTML');
                return;
            }
            $tpcResult = \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomSubmitRefund($tpcOrder, $from_id, $tpcRefundCard, $tpcRefundHolder);
            if (empty($tpcResult['ok'])) {
                sendmessage($from_id, '❌ ' . ($tpcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_refund_submit_failed_default')), $tpcRefundView['keyboard'], 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            sendmessage($from_id, '✅ ' . ($tpcResult['message'] ?? \Faoxima\Telegram\Support\MessageTemplates::plain('payment_tonpay_refund_registered_default')), null, 'HTML');
            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotRefreshOriginal($from_id, $tpcOrder, 0);
            break;
        case 'checkout_wallet':
            \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_callback($user, (string) $datain, $message_id, $callback_query_id ?? null);
            break;
        }

        return true;
    }
}
