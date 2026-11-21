<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $datatextbot, $diceResponse, $first_name, $from_id, $otherreport, $pdo, $setting, $telegramRoute, $textbotlang, $user, $username, $users.
 * Inherited or exported by reference: $pricelast, $status, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PrivacyAndPrices
{
    public static function handle(
        $datatextbot,
        $diceResponse,
        $first_name,
        $from_id,
        $otherreport,
        $pdo,
        &$pricelast,
        $setting,
        &$status,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        $user,
        $username,
        $users
    ) {
        switch ($telegramRoute) {
        case 'privacy':
            sendmessage($from_id, $datatextbot['text_roll'], null, 'HTML');
            break;
        case 'prize_wheel':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_wheel_luck")) {
                sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
                return;
            }
            if ($setting['wheelagent'] == "0" and $user['agent'] != "f") {
                sendmessage($from_id, $datatextbot['dyn_wheel_button_disabled_you'] ?? "❌ این دکمه برای شما غیرفعال می باشد", null, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND status != 'Unpaid'");
            $stmt->bindParam(':id_user', $from_id);
            $stmt->execute();
            $countinvoice = $stmt->rowCount();
            if (intval($setting['statusfirstwheel']) == 1 and $countinvoice != 0) {
                sendmessage($from_id, $datatextbot['dyn_wheel_no_purchase_users_only'] ?? "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.", null, 'HTML');
                return;
            }
            if ($setting['wheelـluck'] == "0" or ($setting['wheelagent'] == "0" and $users['agent'] != "f")) {
                sendmessage($from_id, $textbotlang['users']['wheel_luck']['feature-disabled'], null, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
            $stmt->execute();
            $USER = $stmt->fetch(PDO::FETCH_ASSOC);
            $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
            if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
                sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
                return;
            }
            if (intval($setting['Dice']) == 1) {
                $diceResponse = telegram('sendDice', [
                    'chat_id' => $from_id,
                    'emoji' => "🎲",
                ]);
                sleep(4.5);
            } else {
                $diceResponse = telegram('sendDice', [
                    'chat_id' => $from_id,
                    'emoji' => "🎰",
                ]);
                sleep(4);
            }
            if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
                $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
                error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
                sendmessage($from_id, $textbotlang['users']['wheel_luck']['error'] ?? '❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.', null, 'HTML');
                return;
            }
            $diceValue = (int) $diceResponse['result']['dice']['value'];
            $dateacc = date('Y/m/d H:i:s');
            $wheelDay = date('Y-m-d');
            $status = false;
            if (intval($setting['Dice']) == 1) {
                if ($diceValue === 6) {
                    $status = true;
                }
            } else {
                if (in_array($diceValue, [1, 43, 64, 22], true)) {
                    $status = true;
                }
            }
            $pricelast = $status ? $setting['wheelـluck_price'] : 0;
            $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price,wheel_day) VALUES (:id_user,:first_name,:wheel_code,:time,:price,:wheel_day)");
            $stmt->bindParam(':id_user', $from_id);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':wheel_code', $diceValue);
            $stmt->bindParam(':time', $dateacc);
            $stmt->bindParam(':price', $pricelast);
            $stmt->bindParam(':wheel_day', $wheelDay);
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
                return;
            }
            if ($status) {
                $wheelPrize = intval($setting['wheelـluck_price']);
                if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'credit']) && $wheelPrize > 0) {
                    $__wheelOk = \Faoxima\Wallet\Balance\Balances::credit($pdo, $from_id, $wheelPrize);
                    if ($__wheelOk && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                        \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'credit', $wheelPrize, 'lottery', 'جایزه گردونه شانس', null, 'wheel_list', (string)$from_id);
                    }
                } else {
                    $balance_last = $wheelPrize + $user['Balance'];
                    \Faoxima\Core\Database\Db::update("user", "Balance", $balance_last, "id", $from_id);
                }
                $price = number_format($setting['wheelـluck_price']);
                sendmessage($from_id, sprintf($textbotlang['users']['wheel_luck']['winner-congratulations'], $price), null, 'HTML');
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $otherreport,
                        'text' => sprintf($textbotlang['users']['wheel_luck']['wheel-winner'], $username, $from_id),
                        'parse_mode' => "HTML"
                    ]);
                }
            } else {
                sendmessage($from_id, $textbotlang['users']['wheel_luck']['notWinner'], null, 'HTML');
            }
            break;
        case 'tron_price':
            $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['TRX']);
            if ($rates === null) {
                sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
                return;
            }
            $price = $rates['TRX'];
            sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tron-price'], $price), null, 'HTML');
            break;
        case 'usd_price':
            $rates = \Faoxima\Payments\Gateways\Tronado\Runtime::requireTronRates(['USD']);
            if ($rates === null) {
                sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
                return;
            }
            $price = $rates['USD'];
            sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tether-price'], $price), null, 'HTML');
            break;
        }

        return true;
    }
}
