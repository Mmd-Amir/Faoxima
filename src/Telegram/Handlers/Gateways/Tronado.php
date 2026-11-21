<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $pdo, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Tronado
{
    public static function handle(
        $connect,
        &$data,
        $datatextbot,
        $errorreport,
        $from_id,
        &$invoice,
        $keyboard,
        &$message_id,
        &$payment,
        $pdo,
        &$randomString,
        $setting,
        &$stmt,
        $textbotlang,
        $user,
        $username
    ) {
        $mainbalanceplisio = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay2", "select")['ValuePay'];
                $maxbalanceplisio = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay2", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
                    $mainbalanceplisio = number_format($mainbalanceplisio);
                    $maxbalanceplisio = number_format($maxbalanceplisio);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'Currency Rial 2');
                if ($rxPendingGw !== null) {
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'iranpay2');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "Currency Rial 2";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $stmt->execute();
                $payment = \Faoxima\Payments\Gateways\Tronado\Runtime::trnado($randomString, (float) $user['Processing_value']);

                $paymentErrorData = null;
                if (!is_array($payment)) {
                    $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس ترونادو'];
                } elseif (isset($payment['success']) && $payment['success'] === false) {
                    $paymentErrorData = $payment;
                }

                if ($paymentErrorData !== null) {
                    $errorLines = [];
                    if (isset($paymentErrorData['status_code'])) {
                        $errorLines[] = "کد وضعیت HTTP: " . $paymentErrorData['status_code'];
                    }
                    if (isset($paymentErrorData['errno'])) {
                        $errorLines[] = "کد خطای cURL: " . $paymentErrorData['errno'];
                    }
                    if (isset($paymentErrorData['error'])) {
                        $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
                    }
                    if (isset($paymentErrorData['raw_response'])) {
                        $errorLines[] = "پاسخ خام: " . $paymentErrorData['raw_response'];
                    }

                    if (empty($errorLines)) {
                        $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    $text_error = implode("\n", $errorLines);
                    \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                    \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
                    $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
                    if (strlen($setting['Channel_Report'] ?? '') > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $ErrorsLinkPayment,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    return;
                }

                $paymentToken = is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'extractPaymentToken']) ? \Faoxima\Payments\Gateways\Tronado\Client::extractPaymentToken($payment) : (string) ($payment['Token'] ?? '');
                if ($paymentToken === '') {
                    $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                    \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
                    $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
                    if (strlen($setting['Channel_Report'] ?? '') > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $ErrorsLinkPayment,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    return;
                }
                \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $paymentToken, "id_order", $randomString);
                if (is_callable([\Faoxima\Payments\Gateways\Tronado\Settlement::class, 'store'])) {
                    \Faoxima\Payments\Gateways\Tronado\Settlement::store($pdo, $randomString, $payment);
                }
                $paymentFullUrl = trim((string) ($payment['FullPaymentUrl'] ?? ''));
                if ($paymentFullUrl === '') {
                    $paymentFullUrl = "https://t.me/tronado_robot/customerpayment?startapp={$paymentToken}";
                }
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('iranpay2', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentFullUrl])
                        ]
                    ]
                ]);
                $pricetoman = number_format($user['Processing_value'], 0);
                $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .
❌ پس از تراکنش 15 تا یک ساعت زمان میبرد تا تراکنش تایید شود

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpiranpay2", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
