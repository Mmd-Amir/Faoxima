<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Variza
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
        $mainbalancevariza = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancevariza", "select")['ValuePay'];
                $maxbalancevariza = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancevariza", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalancevariza || $user['Processing_value'] > $maxbalancevariza) {
                    $mainbalancevariza = number_format($mainbalancevariza);
                    $maxbalancevariza = number_format($maxbalancevariza);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancevariza, $maxbalancevariza), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'variza');
                if ($rxPendingGw !== null) {
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'variza');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "variza";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $stmt->execute();
                $payment = \Faoxima\Payments\Gateways\GatewayRuntime::varizaCreatePayment($randomString, (int) $user['Processing_value']);

                $paymentErrorData = null;
                if (!is_array($payment)) {
                    $paymentErrorData = ['message' => 'پاسخ نامعتبر از سرویس واریزا'];
                } elseif (empty($payment['success'])) {
                    $paymentErrorData = $payment;
                }

                if ($paymentErrorData !== null) {
                    $errorLines = [];
                    if (isset($paymentErrorData['message'])) {
                        $errorLines[] = "پیام خطا: " . $paymentErrorData['message'];
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

                $slug = trim((string) ($payment['slug'] ?? ''));
                $paymentLink = trim((string) ($payment['pay_url'] ?? ''));
                if ($paymentLink === '') {
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
                \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $slug, "id_order", $randomString);
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('variza', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink])
                        ]
                    ]
                ]);
                $pricetoman = number_format($user['Processing_value'], 0);
                $textnowpayments = \Faoxima\Telegram\Support\MessageTemplates::get('payment_invoice_created_variza', ['tracking_code' => $randomString, 'amount' => $pricetoman]);
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpvariza", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
