<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class NowPayments
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
        &$randomString,
        $setting,
        &$stmt,
        $textbotlang,
        $user,
        $username
    ) {
        $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['TRX', 'USD']);
                if ($rates === null) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $trx = $rates['TRX'];
                $usd = $rates['USD'];
                if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
                $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
                $mainbalanceplisio = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancenowpayment", "select")['ValuePay'];
                $maxbalanceplisio = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancenowpayment", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
                    $mainbalanceplisio = number_format($mainbalanceplisio);
                    $maxbalanceplisio = number_format($maxbalanceplisio);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'nowpayment');
                if ($rxPendingGw !== null) {
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'nowpayment');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $pay = \Faoxima\Payments\Gateways\GatewayRuntime::nowPayments('invoice', $usdprice, $randomString, "order");
                $Payment_Method = "nowpayment";
                if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
                    $text_error = json_encode($pay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
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
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['id']);
                $stmt->execute();
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('nowpayment', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']]),
                        ]
                    ]
                ]);
                $price_format = number_format($user['Processing_value'], 0);
                $USD = number_format($usd);
                $textnowpayments = \Faoxima\Telegram\Support\MessageTemplates::get('payment_invoice_created_nowpayments', ['order_id' => $randomString, 'price' => $price_format, 'usd_rate' => $USD]);
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpnowpayment", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
