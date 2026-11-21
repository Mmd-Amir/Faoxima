<?php

namespace Faoxima\Telegram\Routing;

use PDO;

/**
 * Handler stage. Inherited by value: $callback_query_id, $connect, $datain, $from_id, $message_id, $paymentreports, $pdo, $setting, $textbotlang, $user.
 * Inherited or exported by reference: $dataget, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentCallbacks
{
    public static function handle(
        $callback_query_id,
        $connect,
        &$dataget,
        $datain,
        $from_id,
        $message_id,
        $paymentreports,
        $pdo,
        &$result,
        $setting,
        $textbotlang,
        $user
    ) {
        if (preg_match('/Confirmpay_user_(\w+)_(\w+)/', $datain, $dataget)) {
            $id_payment = $dataget[1];
            $id_order = $dataget[2];


            $stmtPay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :id_order LIMIT 1");
            $stmtPay->bindValue(':id_order', $id_order, PDO::PARAM_STR);
            $stmtPay->execute();
            $Payment_report = $stmtPay->fetch(PDO::FETCH_ASSOC);
            if (!is_array($Payment_report) || (string)($Payment_report['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('PAYMENT_CONFIRM_FORBIDDEN', 'Non-owner attempted Confirmpay_user', [
                        'from_id' => $from_id, 'id_order' => $id_order, 'id_payment' => $id_payment,
                    ]);
                }
                return;
            }
            if ($Payment_report['payment_Status'] == "paid") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['Confirmpayadmin'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                return;
            }
            $StatusPayment = \Faoxima\Payments\Gateways\Nowpayments\Client::status($id_payment);
            if ($StatusPayment['payment_status'] == "finished") {
                $rxClaimPaid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :o AND payment_Status <> 'paid'");
                $rxClaimPaid->execute([':o' => $Payment_report['id_order']]);
                if ($rxClaimPaid->rowCount() < 1) {
                    telegram('answerCallbackQuery', array(
                        'callback_query_id' => $callback_query_id,
                        'text' => $textbotlang['Admin']['Payment']['reviewedpayment'] ?? 'این پرداخت قبلاً بررسی شده است',
                        'show_alert' => true,
                        'cache_time' => 5,
                    ));
                    return;
                }
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('Payment_report');
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['finished'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
                \Faoxima\Payments\Gateways\GatewayRuntime::DirectPayment($Payment_report['id_order']);
                $_uid = $Payment_report['id_user'];
                $_stmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
                $_stmt->bind_param("s", $_uid); $_stmt->execute();
                $Balance_id = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
                $priceFormatted = \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']);
                $text_report = "💵 پرداخت جدید

<blockquote>آیدی عددی کاربر : $from_id</blockquote>
<blockquote>مبلغ تراکنش : {$priceFormatted}</blockquote>
<blockquote>روش پرداخت : درگاه ارزی ریالی اول</blockquote>";
                $pricecashback = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "chashbackiranpay2", "select")['ValuePay'];
                $cashbackEligible = !method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_cashbackEligibleForKey')
                    || \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackEligibleForKey("chashbackiranpay2", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
                if ($cashbackEligible && $pricecashback != "0") {
                    $result = (int) floor(($Payment_report['price'] * $pricecashback) / 100);
                    if (\Faoxima\Payments\Settlement\SettlementRuntime::rx_cashback_credit_once($Payment_report['id_order'], $Payment_report['id_user'], $result, 'chashbackiranpay2', 'هدیه بازگشت وجه درگاه ارزی ریالی') === 'credited') {
                        $pricecashback = number_format($pricecashback);
                        $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], \Faoxima\Core\Support\Formatting::rxFormatToman($result));
                        sendmessage($Payment_report['id_user'], $text_report, null, 'HTML');
                    }
                }
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $paymentreports,
                        'text' => $text_report,
                        'parse_mode' => "HTML"
                    ]);
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "none", "id", $Payment_report['id_user']);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "none", "id", $Payment_report['id_user']);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "none", "id", $Payment_report['id_user']);
            } elseif ($StatusPayment['payment_status'] == "expired") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['expired'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
            } elseif ($StatusPayment['payment_status'] == "refunded") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['refunded'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
            } elseif ($StatusPayment['payment_status'] == "waiting") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['waiting'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
            } elseif ($StatusPayment['payment_status'] == "sending") {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['sending'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
            } else {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['Balance']['Failed'],
                    'show_alert' => true,
                    'cache_time' => 5,
                ));
            }
        }
        if (preg_match('/^sbv(resume|cancel)_(\w+)$/', (string) $datain, $_sbvm) && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_active_payment')) {
            if ($_sbvm[1] === 'cancel') {
                $_sbv_res = \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_active($from_id, $_sbvm[2]);
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $_sbv_res['message'],
                    'show_alert' => empty($_sbv_res['ok']),
                    'cache_time' => 0,
                ]);
                if (!empty($_sbv_res['ok']) || ($_sbv_res['code'] ?? '') === 'not_active') {
                    if (in_array((string) ($user['step'] ?? ''), ['card_select_step', 'card_photo_step', 'card_last4_step', 'cart_to_cart_user'], true)
                        && (string) ($user['Processing_value'] ?? '') === $_sbvm[2]) {
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    }
                    \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_mark_cancelled($from_id, $message_id, !empty($_sbv_res['ok'])
                        ? "❌ این پرداخت لغو شد و دیگر قابل واریز نیست.\n🛒 کد پیگیری: <code>" . htmlspecialchars($_sbvm[2], ENT_QUOTES, 'UTF-8') . "</code>\n\nاکنون می‌توانید پرداخت جدیدی ایجاد کنید."
                        : $_sbv_res['message']);
                }
            } elseif (\Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_resume($from_id, $_sbvm[2], $user)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
                deletemessage($from_id, $message_id);
            } else {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این پرداخت دیگر قابل ادامه نیست.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
            }
            return;
        }

        return true;
    }
}
