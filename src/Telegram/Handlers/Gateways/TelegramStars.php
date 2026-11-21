<?php

namespace Faoxima\Telegram\Handlers\Gateways;

/**
 * Handler stage. Inherited by value: $connect, $datatextbot, $errorreport, $from_id, $keyboard, $pdo, $setting, $textbotlang, $user, $username.
 * Inherited or exported by reference: $data, $invoice, $message_id, $randomString, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TelegramStars
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
        $pdo,
        &$randomString,
        $setting,
        &$stmt,
        $textbotlang,
        $user,
        $username
    ) {
        $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['USD']);
                if ($rates === null) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $usd = $rates['USD'];
                if (!is_numeric($usd) || $usd <= 0) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $userAmountUsd = round($user['Processing_value'] / $usd, 2);
                $starPriceSetting = \Faoxima\Core\Database\Db::getPaySettingValue('star_price_usd', '0.016');
                if (is_string($starPriceSetting)) {
                    $starPriceSetting = str_replace(',', '.', $starPriceSetting);
                }
                $starPriceUsd = is_numeric($starPriceSetting) ? (float) $starPriceSetting : 0.016;
                if ($starPriceUsd <= 0) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $starAmount = (int) ceil($userAmountUsd / $starPriceUsd);
                if ($starAmount < 1) {
                    $starAmount = 1;
                }
                $mainbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancestar", "select")['ValuePay'];
                $maxbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancestar", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
                    $mainbalance = number_format($mainbalance);
                    $maxbalance = number_format($maxbalance);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
                    return;
                }
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
                $dateacc = date('Y/m/d H:i:s');
                $randomString = bin2hex(random_bytes(5));
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                $payment_Status = "Unpaid";
                $Payment_Method = "Star Telegram";
                $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                $saved = $stmt->execute();
                $stmt->close();
                if (!$saved || !\Faoxima\Payments\Settlement\GatewayFlows::paymentV2Mark($pdo, $randomString, ['stars_amount' => $starAmount])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $affilnecurrency = \Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
                $invoiceParams = [
                    'title' => "Buy for Price {$user['Processing_value']}",
                    'description' => "Buy price",
                    'payload' => $randomString,
                    'currency' => "XTR",
                    'prices' => json_encode(array(
                        array(
                            'label' => "Price",
                            'amount' => $starAmount
                        )
                    ))
                ];
                if (($invoiceParams['currency'] ?? null) === 'XTR') {
                    unset($invoiceParams['provider'], $invoiceParams['provider_token']);
                }
                $straCreateLink = telegram('createInvoiceLink', $invoiceParams);
                if ($straCreateLink['ok'] == false) {
                    $text_error = json_encode($straCreateLink);
                    sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    $ErrorsLinkPayment = "
خطا در هنگام ساخت فاکتور استار
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

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
                $paymentkeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            rx_payment_button('startelegrams', 'pay_link', ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $straCreateLink['result']])
                        ]
                    ]
                ]);
                $formatprice = number_format($user['Processing_value'], 0);
                $approxStarUsd = number_format($starAmount * $starPriceUsd, 2);
                $textstar = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری: <code>$randomString</code>
💲 مبلغ تراکنش: $starAmount ⭐ (حدوداً $approxStarUsd دلار | معادل $formatprice تومان)

📌 لطفاً مبلغ $formatprice تومان را به استار تلگرام تبدیل کرده و واریز نمایید.

💢 نکات مهم قبل از پرداخت: 👇
🔹 هر تراکنش ۱ روز معتبر است؛ بعد از انقضا از واریز خودداری کنید.

✅ در صورت مشکل، با پشتیبانی در ارتباط باشید.";
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpstar", "select")['ValuePay'];
                if (intval($gethelp) != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data);
                }
                $message_id = sendmessage($from_id, $textstar, $paymentkeyboard, 'HTML');
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
