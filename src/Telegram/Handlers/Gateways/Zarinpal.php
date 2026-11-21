<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Zarinpal
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
        if ($user['Processing_value'] < 5000) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
                    return;
                }
                $mainbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpal", "select")['ValuePay'];
                $maxbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpal", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
                    $mainbalance = number_format($mainbalance);
                    $maxbalance = number_format($maxbalance);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
                    return;
                }
                $rxPendingGw = \Faoxima\Payments\Settlement\PendingInvoices::findPendingGatewayInvoice($from_id, 'zarinpal');
                if ($rxPendingGw !== null) {
                    \Faoxima\Payments\Settlement\PendingInvoices::rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'zarinpal');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $randomString = bin2hex(random_bytes(5));
                $pay = \Faoxima\Payments\Gateways\GatewayRuntime::createPayZarinpal($user['Processing_value'], $randomString);
                if ($pay['data']['code'] != 100) {
                    $text_error = json_encode($pay['errors']);
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پال
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
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $dateacc = date('Y/m/d H:i:s');
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "zarinpal";
                $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['data']['authority']);
                $stmt->execute();
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('zarinpal', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://www.zarinpal.com/pg/StartPay/" . $pay['data']['authority']]),
                        ]
                    ]
                ]);
                $price_format = number_format($user['Processing_value'], 0);
                $textnowpayments = "
✅ فاکتور پرداخت ایجاد شد.

🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpzarinpal", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
