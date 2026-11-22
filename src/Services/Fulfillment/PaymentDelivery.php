<?php

namespace Faoxima\Services\Fulfillment;

use Faoxima\Telegram\Support\PaymentPresentation;

final class PaymentDelivery
{
    public static function deliver(&$pdo, &$ManagePanel, PaymentPresentation $telegram, $order_id, $image = 'images.jpg', $rxToken = null, $commissionSettings = null)
    {
        $order_id = (string) $order_id;
        $rxPf = \Faoxima\Payments\Fulfillment\Claims::begin($pdo, $order_id, $rxToken !== null ? (string) $rxToken : null);
        if (empty($rxPf['proceed'])) {
            return $rxPf['result'];
        }
        $rxToken = (string) $rxPf['token'];
        $rxPhase = (string) $rxPf['phase'];
        $rxBeforeState = \Faoxima\Payments\Fulfillment\Claims::decode($rxPf['record']['before_state'] ?? null);
        $rxTargetState = \Faoxima\Payments\Fulfillment\Claims::decode($rxPf['record']['target_state'] ?? null);
        $buyreport =\Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
        $admin_ids = \Faoxima\Core\Database\Db::select("admin", "id_admin", null, null, "FETCH_COLUMN");
        $otherservice = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
        $otherreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
        $errorreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
        $porsantreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $Payment_report = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $order_id, "select", ['cache' => false]);
        if (!is_array($Payment_report)) {
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'payment_report_unavailable', true);
        }
        if ((string) ($Payment_report['Payment_Method'] ?? '') === 'rialit' && (int) ($Payment_report['rialit_abandoned_at'] ?? 0) > 0) {
            $rxLateCredit = \Faoxima\Wallet\Ledger\FulfillmentCredit::walletCredit($pdo, $order_id, $rxToken, $Payment_report['id_user'], (int) $Payment_report['price'], 'topup_gateway', 'تأیید پرداخت ریالیت پس از انصراف');
            if ($rxLateCredit === 'already_completed') {
                return \Faoxima\Payments\Fulfillment\Claims::result('already_completed', 'rialit_cancelled_already_credited');
            }
            if ($rxLateCredit !== 'credited') {
                return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'rialit_cancelled_credit_failed', true);
            }
            sendmessage($Payment_report['id_user'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_rialit_late_approved_wallet', ['amount' => number_format((int) $Payment_report['price'])]), null, 'HTML');
            return \Faoxima\Payments\Fulfillment\Claims::result('completed', 'wallet_credit', ['resolved' => 'wallet_credit']);
        }
        $receipt = $telegram->receipt($Payment_report);
        $keyboard =& $telegram->keyboard;
        $paymentNote = \Faoxima\Payments\Settlement\PendingInvoices::formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null);
        $format_price_cart = number_format($Payment_report['price']);
        $Balance_id = \Faoxima\Core\Database\Db::select("user", "*", "id", $Payment_report['id_user'], "select");
        $steppay = explode("|", $Payment_report['id_invoice']);
        \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $Balance_id['id']);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
        $rxGate = \Faoxima\Orders\Checkout\Settlement::gateFulfillment($pdo, $Payment_report, $rxToken);
        $rxCk = is_array($rxGate['checkout'] ?? null) ? $rxGate['checkout'] : null;
        $rxGateAction = (string) ($rxGate['action'] ?? 'error');
        $rxGateCode = (string) ($rxGate['code'] ?? 'unknown');
        if ($rxGateAction === 'error') {
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'checkout_gate_error:' . $rxGateCode, true);
        }
        if ($rxGateAction === 'resolved_credit') {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_wallet_insufficient_credited', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($rxGate['credited'])]), $keyboard, 'HTML');
            return \Faoxima\Payments\Fulfillment\Claims::result('completed', 'wallet_credit', ['resolved' => 'wallet_credit']);
        }
        if ($rxGateAction === 'deny') {
            if (!empty($rxGate['terminal'])) {
                return \Faoxima\Payments\Fulfillment\Claims::result('already_completed', 'direct_payment_done');
            }
            if ($rxPhase === 'reconcile') {
                return \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, 'checkout_gate_denied_during_reconcile: ' . $rxGateCode, ['id_order' => $order_id]);
            }
            $__refundCk = \Faoxima\Orders\Checkout\Settlement::refundUnsecured($pdo, $Payment_report, 'بازگشت وجه - سفارش دیگر قابل تکمیل نیست (' . $rxGateCode . ')');
            if ($__refundCk === 'refunded') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_order_unfulfillable_refunded'), $keyboard, 'HTML');
            } elseif ($__refundCk === 'failed') {
                sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\MessageTemplates::get('payment_order_unfulfillable_refund_failed'), $keyboard, 'HTML');
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'checkout_denied:' . $rxGateCode, $__refundCk);
        }
        if (!in_array($rxGateAction, ['proceed', 'legacy'], true)) {
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'checkout_gate_error:' . $rxGateCode, true);
        }
        $context = [
            'payment' => [
                'order_id' => $order_id,
                'report' => $Payment_report,
                'invoice_tag' => $steppay,
                'customer' => $Balance_id,
                'checkout' => $rxCk,
            ],
            'fulfillment' => [
                'token' => $rxToken,
                'phase' => $rxPhase,
                'before' => $rxBeforeState,
                'target' => $rxTargetState,
            ],
            'provider' => [
                'database' => &$pdo,
                'panel' => &$ManagePanel,
                'commission_settings' => $commissionSettings,
            ],
            'presentation' => [
                'settings' => $setting,
                'language' => &$telegram->language,
                'templates' => &$telegram->templates,
                'keyboard' => &$keyboard,
                'renewal_keyboard' => &$telegram->renewalKeyboard,
                'request_user' => &$telegram->requestUser,
                'image' => $image,
                'price_display' => $format_price_cart,
                'payment_note' => $paymentNote,
                'receipt' => $receipt,
            ],
            'reporting' => [
                'administrators' => $admin_ids,
                'purchase' => $buyreport,
                'extension' => $otherservice,
                'general' => $otherreport,
                'error' => $errorreport,
                'affiliate' => $porsantreport,
            ],
        ];
        if ($steppay[0] == "getconfigafterpay") {
            return Purchase::purchase($context);
        }
        if ($steppay[0] == "getextenduser") {
            return Renewal::renew($context);
        }
        if ($steppay[0] == "getextravolumeuser") {
            return ServiceExtensions::addVolume($context);
        }
        if ($steppay[0] == "getextratimeuser") {
            return ServiceExtensions::addTime($context);
        }
        return WalletTopUp::topUp($context);
    }
}
