<?php

namespace Faoxima\Telegram\Handlers\Gateways;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $__chargeBonus, $connect, $datatextbot, $from_id, $keyboard, $pdo, $textbotlang, $user.
 * Inherited or exported by reference: $data, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Rialit
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
        [$rialitMin, $rialitMax] = is_callable([\Faoxima\Payments\Gateways\Rialit\Client::class, 'amountRange']) ? \Faoxima\Payments\Gateways\Rialit\Client::amountRange($pdo) : [50000, 10000000];
                $rxRialitAmount = (int) $user['Processing_value'];
                if ($rxRialitAmount < $rialitMin || $rxRialitAmount > $rialitMax) {
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", number_format($rialitMin), number_format($rialitMax)), null, 'HTML');
                    return;
                }
                if (!($pdo instanceof PDO)) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    return;
                }
                $rxRialitInitLock = 'faoxima_payment_init_' . $from_id . '_rialit';
                $rxRialitLockStatement = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $rxRialitLockStatement->execute([$rxRialitInitLock]);
                if ((int) $rxRialitLockStatement->fetchColumn() !== 1) {
                    sendmessage($from_id, '⏳ درخواست پرداخت شما در حال پردازش است؛ کمی بعد دوباره تلاش کنید.', null, 'HTML');
                    return;
                }
                register_shutdown_function(static function () use ($pdo, $rxRialitInitLock): void {
                    try {
                        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$rxRialitInitLock]);
                    } catch (Throwable $e) {
                    }
                });
                $rxRialitInvoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $rxRialitChargeParts = explode('|', (string) ($user['Processing_value_four'] ?? ''));
                $rxRialitChargeCode = ($rxRialitChargeParts[0] ?? '') === 'chg' ? (string) ($rxRialitChargeParts[2] ?? '') : '';
                $rxRialitBeforeDiscount = $rxRialitChargeCode !== '' ? (float) ($rxRialitChargeParts[3] ?? 0) : 0;
                $rxRialitCheckout = method_exists(\Faoxima\Orders\Checkout\CheckoutRuntime::class, 'rx_ck_find_for_key') ? \Faoxima\Orders\Checkout\Checkout::findForKey($pdo, $from_id, $rxRialitInvoice) : null;
                $rxRialitContext = [
                    'checkout_id' => (string) ($rxRialitCheckout['checkout_id'] ?? ''),
                    'charge_bonus' => $__chargeBonus,
                    'discount_code' => $rxRialitChargeCode,
                    'discount_amount' => $rxRialitChargeCode !== '' ? $__chargeBonus : 0,
                    'price_before_discount' => $rxRialitBeforeDiscount,
                ];
                $rxRialitPending = is_callable([\Faoxima\Payments\Gateways\Rialit\PaymentOrders::class, 'findPendingForUser']) ? \Faoxima\Payments\Gateways\Rialit\PaymentOrders::findPendingForUser($pdo, $from_id, $rxRialitInvoice, $rxRialitAmount, $rxRialitContext) : null;
                if ($rxRialitPending !== null && trim((string) ($rxRialitPending['rialit_token'] ?? '')) !== '') {
                    $rxRialitOrder = (string) $rxRialitPending['id_order'];
                    $rxRialitFresh = method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitPollThrottled') ? \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitPollThrottled($rxRialitOrder, $from_id) : null;
                    if (is_array($rxRialitFresh)) {
                        $rxRialitPending = $rxRialitFresh;
                    }
                    $rxRialitView = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitBotPaymentView($rxRialitPending);
                    $message_id = sendmessage($from_id, $rxRialitView['text'], $rxRialitView['keyboard'], 'HTML');
                    \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $rxRialitOrder);
                    return;
                }
                if ($rxRialitPending === null && !\Faoxima\Payments\Gateways\Rialit\Client::enabled($pdo)) {
                    sendmessage($from_id, '❌ درگاه ریالیت در حال حاضر غیرفعال است.', $keyboard, 'HTML');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                if ($rxRialitPending !== null) {
                    $randomString = (string) $rxRialitPending['id_order'];
                } else {
                    $randomString = bin2hex(random_bytes(5));
                    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,rialit_created_at,charge_bonus,discount_code,discount_amount,price_before_discount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                    $payment_Status = "Unpaid";
                    $Payment_Method = "rialit";
                    $rxRialitCreatedAt = time();
                    $rxRialitDiscountAmount = $rxRialitContext['discount_amount'];
                    $stmt->bind_param("sssssssiisdd", $from_id, $randomString, $dateacc, $rxRialitAmount, $payment_Status, $Payment_Method, $rxRialitInvoice, $rxRialitCreatedAt, $__chargeBonus, $rxRialitChargeCode, $rxRialitDiscountAmount, $rxRialitBeforeDiscount);
                    $stmt->execute();
                    if ($rxRialitContext['checkout_id'] !== '') {
                        \Faoxima\Orders\Checkout\Checkout::attachPayment($pdo, $rxRialitContext['checkout_id'], $randomString, 'rialit');
                    }
                }
                if (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::markCreated($pdo, $randomString, $rxRialitAmount, (int) $from_id)) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    return;
                }
                $payment = \Faoxima\Payments\Gateways\Rialit\Client::createPayment($pdo, $randomString, $rxRialitAmount, (int) $from_id);
                if (!is_array($payment) || (isset($payment['success']) && $payment['success'] === false)) {
                    $rxRialitErrorCode = is_array($payment) ? (string) ($payment['error_code'] ?? '') : '';
                    \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", "rialit_create_failed:" . $rxRialitErrorCode, "id_order", $randomString);
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                if (!\Faoxima\Payments\Gateways\Rialit\PaymentOrders::storePayment($pdo, $randomString, $payment)) {
                    \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", "rialit_store_failed", "id_order", $randomString);
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                if (strtolower((string) ($payment['status'] ?? '')) === 'paid' && method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitVerifyPayment')) {
                    \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitVerifyPayment($randomString, ['source' => 'bot_init']);
                }
                $rxRialitStored = \Faoxima\Payments\Gateways\Rialit\PaymentOrders::fetchReport($pdo, $randomString);
                if ($rxRialitStored === null || !\Faoxima\Payments\Gateways\Rialit\PaymentOrders::linkAvailable($rxRialitStored)) {
                    if ($rxRialitStored !== null) {
                        $rxRialitView = \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitBotPaymentView($rxRialitStored);
                        $message_id = sendmessage($from_id, $rxRialitView['text'], $rxRialitView['keyboard'], 'HTML');
                        \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);
                    }
                    return;
                }
                $rxRialitUrl = \Faoxima\Payments\Gateways\Rialit\Client::paymentUrl((string) $payment['token']);
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('rialit', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $rxRialitUrl])
                        ],
                        [rx_payment_button('rialit', 'check_status', ['text' => '🔄 بررسی وضعیت', 'callback_data' => 'rl_view_' . $randomString])],
                        [rx_payment_button('rialit', 'cancel_transaction', ['text' => '🚫 انصراف و پرداخت جدید', 'callback_data' => 'rl_cancel_' . $randomString])]
                    ]
                ]);
                $pricetoman = number_format($rxRialitAmount, 0);
                $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 لینک پرداخت تا ۳۰ دقیقه باز است. اگر پس از پایان این مهلت، پرداخت هنوز در انتظار باشد، نتیجه از سمت ریالیت بررسی و در صورت تأیید به‌صورت خودکار اعمال می‌شود.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helprialit", "select")['ValuePay'];
                if ($gethelp != 2) {
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
