<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $pdo, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $payment, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Atlaspay
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
        $mainbalanceatlaspay = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalanceatlaspay", "select")['ValuePay'];
                $maxbalanceatlaspay = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalanceatlaspay", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalanceatlaspay || $user['Processing_value'] > $maxbalanceatlaspay) {
                    $mainbalanceatlaspay = number_format($mainbalanceatlaspay);
                    $maxbalanceatlaspay = number_format($maxbalanceatlaspay);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceatlaspay, $maxbalanceatlaspay), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'atlaspay');
                if ($rxPendingGw !== null) {
                    if (\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::fetchReport($pdo, $rxPendingGw['id_order']) !== null) {
                        \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotSend($from_id, $rxPendingGw['id_order']);
                        return;
                    }
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'atlaspay');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "atlaspay";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $stmt->execute();
                $atlaspayCustomMode = \Faoxima\Payments\Gateways\Atlaspay\CustomClient::paymentMode() === 'custom';
                $payment = $atlaspayCustomMode
                    ? \Faoxima\Payments\Gateways\Atlaspay\CustomClient::createOrder($pdo, $randomString, (int) $user['Processing_value'], (int) $from_id)
                    : \Faoxima\Payments\Gateways\Atlaspay\Client::createOrder($pdo, $randomString, (int) $user['Processing_value'], (int) $from_id);

                $paymentErrorData = null;
                if (!is_array($payment)) {
                    $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس اطلس‌پی'];
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

                $atlaspayOrderId = trim((string) ($payment['orderId'] ?? ''));
                $paymentUrl = trim((string) ($payment['customerStartLink'] ?? ''));
                $receiptUrl = \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayReceiptBotUrl($payment['receiptBotLink'] ?? '');
                $trackingCode = trim((string) ($payment['trackingCode'] ?? ''));
                if ($atlaspayOrderId === '' || $paymentUrl === '') {
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
                if ($atlaspayCustomMode) {
                    if (!\Faoxima\Payments\Gateways\Atlaspay\PaymentOrders::storeOrder($pdo, $randomString, $payment)) {
                        \Faoxima\Payments\Gateways\Atlaspay\Client::cancelOrder($atlaspayOrderId);
                        \Faoxima\Core\Database\Db::update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                        sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                        return;
                    }
                    \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayCustomBotSend($from_id, $randomString);
                    return;
                }
                $totalAmountToman = isset($payment['totalAmountToman']) ? (int) $payment['totalAmountToman'] : (int) $user['Processing_value'];
                \Faoxima\Core\Database\Db::update("Payment_report", "atlaspay_order_id", $atlaspayOrderId, "id_order", $randomString);
                \Faoxima\Core\Database\Db::update("Payment_report", "atlaspay_tracking_code", $trackingCode, "id_order", $randomString);
                \Faoxima\Core\Database\Db::update("Payment_report", "atlaspay_payment_url", $paymentUrl, "id_order", $randomString);
                \Faoxima\Core\Database\Db::update("Payment_report", "atlaspay_receipt_url", $receiptUrl, "id_order", $randomString);
                \Faoxima\Core\Database\Db::update("Payment_report", "atlaspay_total_amount", $totalAmountToman, "id_order", $randomString);
                \Faoxima\Payments\Settlement\GatewayFlows::paymentV2Mark($pdo, $randomString, ['atlaspay_created_at' => time()]);
                $atlaspayPaymentButtons = [
                    [
                        rx_payment_button('atlaspay', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentUrl])
                    ]
                ];
                if ($receiptUrl !== '') {
                    $atlaspayPaymentButtons[] = [
                        rx_payment_button('atlaspay', 'send_receipt', ['text' => \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayBotReceiptButtonText(), 'url' => $receiptUrl])
                    ];
                }
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => $atlaspayPaymentButtons
                ]);
                $pricetoman = number_format($totalAmountToman, 0);
                $textnowpayments = \Faoxima\Telegram\Support\MessageTemplates::get('payment_invoice_created_atlaspay', [
                    'tracking_code' => ($trackingCode !== '' ? $trackingCode : $randomString),
                    'amount' => $pricetoman,
                ], [
                    'receipt_block' => $receiptUrl !== '' ? "\n\n" . \Faoxima\Payments\Gateways\Atlaspay\BotRuntime::atlaspayBotReceiptInstructions() : '',
                ]);
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpatlaspay", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
