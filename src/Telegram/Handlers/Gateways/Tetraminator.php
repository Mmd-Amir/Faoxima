<?php

namespace Faoxima\Telegram\Handlers\Gateways;

use PDO;
use Throwable;

final class Tetraminator
{
    public static function handle(
        $__chargeBonus,
        $connect,
        &$data,
        $datatextbot,
        $from_id,
        $keyboard,
        &$message_id,
        &$payment,
        $pdo,
        &$randomString,
        &$stmt,
        $textbotlang,
        $user
    ) {
        [$tetraminatorMin, $tetraminatorMax] = \Faoxima\Payments\Gateways\Tetraminator\Client::amountRange($pdo);
        $rxTetraminatorAmount = (int) $user['Processing_value'];
        if ($rxTetraminatorAmount < $tetraminatorMin || $rxTetraminatorAmount > $tetraminatorMax) {
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", number_format($tetraminatorMin), number_format($tetraminatorMax)), null, 'HTML');
            return;
        }
        if (!($pdo instanceof PDO)) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            return;
        }
        $rxTetraminatorInitLock = 'faoxima_payment_init_' . $from_id . '_tetraminator';
        $rxTetraminatorLockStatement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $rxTetraminatorLockStatement->execute([$rxTetraminatorInitLock]);
        if ((int) $rxTetraminatorLockStatement->fetchColumn() !== 1) {
            sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_processing'), null, 'HTML');
            return;
        }
        register_shutdown_function(static function () use ($pdo, $rxTetraminatorInitLock): void {
            try {
                $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$rxTetraminatorInitLock]);
            } catch (Throwable $e) {
            }
        });
        $rxTetraminatorInvoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $rxTetraminatorChargeParts = explode('|', (string) ($user['Processing_value_four'] ?? ''));
        $rxTetraminatorChargeCode = ($rxTetraminatorChargeParts[0] ?? '') === 'chg' ? (string) ($rxTetraminatorChargeParts[2] ?? '') : '';
        $rxTetraminatorBeforeDiscount = $rxTetraminatorChargeCode !== '' ? (float) ($rxTetraminatorChargeParts[3] ?? 0) : 0;
        $rxTetraminatorCheckout = method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_find_for_key') ? \Faoxima\Orders\Checkout\Checkout::findForKey($pdo, $from_id, $rxTetraminatorInvoice) : null;
        $rxTetraminatorContext = [
            'checkout_id' => (string) ($rxTetraminatorCheckout['checkout_id'] ?? ''),
            'charge_bonus' => $__chargeBonus,
            'discount_code' => $rxTetraminatorChargeCode,
            'discount_amount' => $rxTetraminatorChargeCode !== '' ? $__chargeBonus : 0,
            'price_before_discount' => $rxTetraminatorBeforeDiscount,
        ];
        $rxTetraminatorPending = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::findPendingForUser($pdo, $from_id, $rxTetraminatorInvoice, $rxTetraminatorAmount, $rxTetraminatorContext);
        if ($rxTetraminatorPending !== null) {
            $rxTetraminatorOrder = (string) $rxTetraminatorPending['id_order'];
            $rxTetraminatorFresh = \Faoxima\Payments\Gateways\Tetraminator\BotRuntime::tetraminatorPollThrottled($rxTetraminatorOrder, $from_id);
            if (is_array($rxTetraminatorFresh)) {
                $rxTetraminatorPending = $rxTetraminatorFresh;
            }
            $rxTetraminatorView = \Faoxima\Payments\Gateways\Tetraminator\BotRuntime::tetraminatorBotPaymentView($rxTetraminatorPending);
            $message_id = sendmessage($from_id, $rxTetraminatorView['text'], $rxTetraminatorView['keyboard'], 'HTML');
            \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $rxTetraminatorOrder);
            return;
        }
        if (!\Faoxima\Payments\Gateways\Tetraminator\Client::enabled($pdo)) {
            sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('payment_tetraminator_gateway_disabled'), $keyboard, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,tetraminator_created_at,charge_bonus,discount_code,discount_amount,price_before_discount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "tetraminator";
        $rxTetraminatorCreatedAt = time();
        $rxTetraminatorDiscountAmount = $rxTetraminatorContext['discount_amount'];
        $stmt->bind_param("sssssssiisdd", $from_id, $randomString, $dateacc, $rxTetraminatorAmount, $payment_Status, $Payment_Method, $rxTetraminatorInvoice, $rxTetraminatorCreatedAt, $__chargeBonus, $rxTetraminatorChargeCode, $rxTetraminatorDiscountAmount, $rxTetraminatorBeforeDiscount);
        $stmt->execute();
        if ($rxTetraminatorContext['checkout_id'] !== '') {
            \Faoxima\Orders\Checkout\Checkout::attachPayment($pdo, $rxTetraminatorContext['checkout_id'], $randomString, 'tetraminator');
        }
        $payment = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::openInvoice($pdo, $randomString, $rxTetraminatorAmount, (int) $from_id);
        if (!is_array($payment) || empty($payment['success'])) {
            $rxTetraminatorErrorCode = is_array($payment) ? (string) ($payment['error_code'] ?? '') : '';
            \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", "tetraminator_create_failed:" . $rxTetraminatorErrorCode, "id_order", $randomString);
            \Faoxima\Payments\Settlement\Confirmation::markUnsettledTerminal($pdo, $randomString, 'expire');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
        }
        $rxTetraminatorStored = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::fetchReport($pdo, $randomString);
        if ($rxTetraminatorStored === null || !\Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::linkAvailable($rxTetraminatorStored)) {
            if ($rxTetraminatorStored !== null) {
                $rxTetraminatorView = \Faoxima\Payments\Gateways\Tetraminator\BotRuntime::tetraminatorBotPaymentView($rxTetraminatorStored);
                $message_id = sendmessage($from_id, $rxTetraminatorView['text'], $rxTetraminatorView['keyboard'], 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);
            }
            return;
        }
        $rxTetraminatorUrl = \Faoxima\Payments\Gateways\Tetraminator\PaymentOrders::paymentUrl($rxTetraminatorStored);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    rx_payment_button('tetraminator', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $rxTetraminatorUrl])
                ],
                [rx_payment_button('tetraminator', 'check_status', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_check_status'), 'callback_data' => 'tm_view_' . $randomString])],
                [rx_payment_button('tetraminator', 'cancel_transaction', ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('payment_btn_tetraminator_cancel_new'), 'callback_data' => 'tm_cancel_' . $randomString])]
            ]
        ]);
        $pricetoman = number_format($rxTetraminatorAmount, 0);
        $textnowpayments = \Faoxima\Telegram\Support\MessageTemplates::get('payment_invoice_created_tetraminator', ['tracking_code' => $randomString, 'amount' => $pricetoman]);
        $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helptetraminator", "select")['ValuePay'] ?? '2';
        if ($gethelp != 2 && $gethelp != 0) {
            $data = json_decode($gethelp, true);
            if (is_array($data) && ($data['type'] ?? '') == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif (is_array($data) && ($data['type'] ?? '') == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif (is_array($data) && ($data['type'] ?? '') == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
