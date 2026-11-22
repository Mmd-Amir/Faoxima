<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $pdo, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Tonpay
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
        $mainbalancetonpay = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancetonpay", "select")['ValuePay'];
                $maxbalancetonpay = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancetonpay", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalancetonpay || $user['Processing_value'] > $maxbalancetonpay) {
                    $mainbalancetonpay = number_format($mainbalancetonpay);
                    $maxbalancetonpay = number_format($maxbalancetonpay);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancetonpay, $maxbalancetonpay), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'tonpay');
                if ($rxPendingGw !== null) {
                    if (\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::fetchReport($pdo, $rxPendingGw['id_order']) !== null) {
                        \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotSend($from_id, $rxPendingGw['id_order']);
                        return;
                    }
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'tonpay');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "tonpay";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $stmt->execute();
                $tonpayCustomMode = \Faoxima\Payments\Gateways\Tonpay\Client::paymentMode() === 'custom';
                $payment = $tonpayCustomMode
                    ? \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomCreateInvoice($randomString, (int) $user['Processing_value'], (int) $from_id)
                    : \Faoxima\Payments\Gateways\GatewayRuntime::tonpayCreateInvoice($randomString, (int) $user['Processing_value'], (int) $from_id);

                $paymentErrorData = null;
                if (!is_array($payment)) {
                    $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس تون‌پی'];
                } elseif (isset($payment['success']) && $payment['success'] === false) {
                    $paymentErrorData = $payment;
                }

                if ($paymentErrorData !== null) {
                    $errorLines = [];
                    if (isset($paymentErrorData['error'])) {
                        $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
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

                if ($tonpayCustomMode) {
                    if (!\Faoxima\Payments\Gateways\Tonpay\PaymentOrders::storeInvoice($pdo, $randomString, $payment)) {
                        $recovery = \Faoxima\Payments\Gateways\Tonpay\PaymentOrders::recoverUnstoredInvoice($pdo, $randomString, $payment);
                        if (!empty($recovery['saved'])) {
                            \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotSend($from_id, $randomString);
                            return;
                        }
                        $invoiceRef = (string) ($recovery['invoice_id'] ?? ($payment['invoice_id'] ?? ''));
                        $remoteState = (string) ($recovery['remote_state'] ?? 'unknown');
                        $storeFailure = 'tonpay_custom_store_unresolved:' . $invoiceRef . ':' . $remoteState;
                        error_log('TonPay custom store unresolved: ' . json_encode([
                            'order_id' => $randomString,
                            'invoice_id' => $invoiceRef,
                            'remote_state' => $remoteState,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                        \Faoxima\Core\Database\Db::update("Payment_report", "dec_not_confirmed", $storeFailure, "id_order", $randomString);
                        sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                        return;
                    }
                    \Faoxima\Payments\Gateways\Tonpay\BotRuntime::tonpayCustomBotSend($from_id, $randomString);
                    return;
                }
                $invoiceId = trim((string) ($payment['invoice_id'] ?? ''));
                $invoiceUrl = trim((string) ($payment['invoice_url'] ?? ''));
                if ($invoiceId === '' || $invoiceUrl === '') {
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
                \Faoxima\Core\Database\Db::update("Payment_report", "tonpay_invoice_id", $invoiceId, "id_order", $randomString);
                \Faoxima\Core\Database\Db::update("Payment_report", "tonpay_invoice_url", $invoiceUrl, "id_order", $randomString);
                \Faoxima\Payments\Gateways\Tonpay\Client::storeWebInvoiceUrl($pdo, $randomString, $payment['web_invoice_url'] ?? '');
                $tonpayPaymentUrl = \Faoxima\Payments\Gateways\Tonpay\Client::selectPaymentUrl($invoiceUrl, $payment['web_invoice_url'] ?? '', $randomString);
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('tonpay', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $tonpayPaymentUrl])
                        ]
                    ]
                ]);
                $pricetoman = number_format($user['Processing_value'], 0);
                $textnowpayments = \Faoxima\Telegram\Support\MessageTemplates::get('payment_invoice_created_tonpay', ['tracking_code' => $randomString, 'amount' => $pricetoman]);
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helptonpay", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
