<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_acc_styles, $datatextbot, $from_id, $message_id, $request_contact, $rwPickMatch, $setting, $telegramRoute, $textbotlang, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RechargeStart
{
    public static function handle(
        $_rx_acc_styles,
        $datatextbot,
        $from_id,
        $message_id,
        $request_contact,
        $rwPickMatch,
        $setting,
        $telegramRoute,
        $textbotlang,
        $user
    ) {
        switch ($telegramRoute) {
        case 'recharge_start':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user)) {
                sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('get_number', $from_id);
            }
            if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user))
                return;
            $randomWalletStatusRow = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
            $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
            if ($randomWalletStatusValue === '1') {
                $rwAmounts = \Faoxima\Wallet\Balance\ChargePrompts::rx_randomWalletAmounts();
                $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
                if (function_exists('rx_kb_use_defaults') && !rx_kb_use_defaults()) {
                    $_rx_acc_s['rw_pick'] = 'none';
                }
                $rxWalletAmountStyleKey = static function ($amount) use ($_rx_acc_s) {
                    $key = 'rw_pick_' . (int) $amount;
                    return (isset($_rx_acc_s[$key]) && $_rx_acc_s[$key] !== 'default') ? $key : 'rw_pick';
                };
                $rwRows = [];
                for ($rwI = 0; $rwI < count($rwAmounts); $rwI += 2) {
                    $rwRow = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI]], $rxWalletAmountStyleKey((int) $rwAmounts[$rwI]), $_rx_acc_s)];
                    if (isset($rwAmounts[$rwI + 1])) {
                        $rwRow[] = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI + 1]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI + 1]], $rxWalletAmountStyleKey((int) $rwAmounts[$rwI + 1]), $_rx_acc_s);
                    }
                    $rwRows[] = $rwRow;
                }
                $rwRows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_custom_amount'), 'callback_data' => "rw_custom"], 'rw_custom', $_rx_acc_s)];
                $rwRows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account", '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true], 'backinfo', $_rx_acc_s)];
                $rwKeyboard = json_encode(['inline_keyboard' => $rwRows], JSON_UNESCAPED_UNICODE);
                Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_select_amount'] ?? "💰 مبلغ مورد نظر را برای شارژ کیف پول انتخاب کنید:", $rwKeyboard, 'HTML');
                return;
            }
            \Faoxima\Wallet\Balance\ChargePrompts::rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
            break;
        case 'recharge_preset':
            $rwAmount = (int)$rwPickMatch[1];
            deletemessage($from_id, $message_id);
            \Faoxima\Wallet\Balance\ChargePrompts::rx_walletChargeAmountAccepted($from_id, $user, $rwAmount);
            break;
        case 'recharge_custom':
            \Faoxima\Wallet\Balance\ChargePrompts::rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
            break;
        }

        return true;
    }
}
