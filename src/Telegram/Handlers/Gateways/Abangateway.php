<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Abangateway
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
        &$randomString,
        $setting,
        &$stmt,
        $textbotlang,
        $user,
        $username
    ) {
        $mainbalanceabangateway = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalanceabangateway", "select")['ValuePay'];
                $maxbalanceabangateway = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalanceabangateway", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalanceabangateway || $user['Processing_value'] > $maxbalanceabangateway) {
                    $mainbalanceabangateway = number_format($mainbalanceabangateway);
                    $maxbalanceabangateway = number_format($maxbalanceabangateway);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceabangateway, $maxbalanceabangateway), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'abangateway');
                if ($rxPendingGw !== null) {
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'abangateway');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "abangateway";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $stmt->execute();
                $payment = \Faoxima\Payments\Gateways\GatewayRuntime::abangatewayCreatePayment($randomString, (int) $user['Processing_value'], $from_id);

                // abangatewayCreatePayment() only reports success with an authority and
                // an https link in hand, so one refusal path covers every failure.
                if (!is_array($payment) || empty($payment['success'])) {
                    $text_error = "پیام خطا: " . (is_array($payment) && !empty($payment['message']) ? $payment['message'] : 'پاسخ نامعتبر از آبان گیت وی');
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

                // The callback looks the order up by id and reads the authority from
                // here, the way the other gateways keep their invoice handle.
                \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $payment['authority'], "id_order", $randomString);
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('abangateway', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $payment['pay_url']])
                        ]
                    ]
                ]);
                $pricetoman = number_format($user['Processing_value'], 0);
                $textabangatewaypay = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 صفحه پرداخت شماره کارت و <b>مبلغ دقیق</b> را نشان میدهد. همان عدد را، رقم به رقم، کارت به کارت کنید؛ تایید خودکار با همان عدد انجام میشود.
🔹 مهلت پرداخت روی همان صفحه نمایش داده میشود.
🔹 بعد از واریز لازم نیست رسید بفرستید؛ تایید خودکار است و نتیجه همین جا به شما اطلاع داده میشود.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpabangateway", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode((string) $gethelp, true);
                    if (is_array($data)) {
                        if (($data['type'] ?? '') == "text") {
                            sendmessage($from_id, $data['text'], null, 'HTML');
                        } elseif (($data['type'] ?? '') == "photo") {
                            sendphoto($from_id, $data['photoid'], null);
                        } elseif (($data['type'] ?? '') == "video") {
                            sendvideo($from_id, $data['videoid'], null);
                        }
                    }
                }
                $cardInvoice = \Faoxima\Payments\Gateways\GatewayRuntime::abangatewayCardInvoice(
                    $payment,
                    $randomString
                );

                $message_id = null;

                if ($cardInvoice !== null) {
                    $message_id = sendmessage(
                        $from_id,
                        $cardInvoice['text'],
                        $cardInvoice['keyboard'],
                        'HTML'
                    );
                }

                if (!is_array($message_id) || empty($message_id['ok'])) {
                    $message_id = sendmessage(
                        $from_id,
                        $textabangatewaypay,
                        $paymentkeyboard,
                        'HTML'
                    );
                }

                if (
                    is_array($message_id) &&
                    !empty($message_id['ok']) &&
                    !empty($message_id['result']['message_id'])
                ) {
                    \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId(
                        $message_id,
                        $randomString
                    );
                }

        return true;
    }
}
