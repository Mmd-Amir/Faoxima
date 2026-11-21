<?php

namespace Faoxima\Wallet\Balance;

final class ChargePrompts
{
    public static function rx_randomWalletAmounts()
    {
        $default = [50000, 75000, 100000, 150000, 200000, 250000, 500000, 1000000];
        $row = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "randomwallet_amounts", "select");
        $raw = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded) || count($decoded) !== 8) {
            return $default;
        }
        return array_map('intval', array_values($decoded));
    }

    public static function rx_promptWalletChargeCustomAmount($from_id, $message_id, $user)
    {
        global $textbotlang, $datatextbot, $setting;

        $minbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
        $maxbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
        $_rx_acc_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbs) && !empty($_rx_all_kbs['account'])) {
                $_rx_acc_styles = array_filter($_rx_all_kbs['account'], static function ($v) { return $v !== 'default'; });
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_acc_styles = $_rx_acc_styles + rx_getKeyboardDefaultStyles('account');
        }
        $bakinfos = json_encode([
            'inline_keyboard' => [
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"], 'backinfo', $_rx_acc_styles),
                ]
            ]
        ]);
        $rxWalletAmountPromptTpl = $datatextbot['dyn_wallet_prompt_amount_prompt'] ?? "💸 مبلغ را  به تومان وارد کنید:\n✅  حداقل مبلغ %s حداکثر مبلغ %s تومان می باشد";
        Editmessagetext($from_id, $message_id, sprintf($rxWalletAmountPromptTpl, $minbalance, $maxbalance), $bakinfos, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
        \Faoxima\Core\Database\Db::update("user", 'Processing_value', $message_id, "id", $from_id);
    }

    public static function rx_walletChargeAmountAccepted($from_id, $user, $amount)
    {
        global $setting, $textbotlang, $datatextbot;

        if (!is_numeric($amount)) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
            return false;
        }

        $minbalance = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
        $maxbalance = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
        if ($amount > $maxbalance or $amount < $minbalance) {
            $minbalanceFmt = number_format($minbalance);
            $maxbalanceFmt = number_format($maxbalance);
            $rxWalletOutOfRangeTpl = $datatextbot['dyn_wallet_prompt_amount_out_of_range'] ?? "❌ خطا\n💬 مبلغ باید حداقل %s تومان و حداکثر %s تومان باشد";
            sendmessage($from_id, sprintf($rxWalletOutOfRangeTpl, $minbalanceFmt, $maxbalanceFmt), null, 'HTML');
            return false;
        }

        if ($user['Balance'] < 0 and intval($setting['Debtsettlement']) == 1) {
            $balancruser = abs($user['Balance']);
            if ($amount < $balancruser) {
                $rxDebtMustPayTpl = $datatextbot['dyn_wallet_prompt_debt_must_pay_first'] ?? "❌ شما بدهی دارید، باید حداقل %s تومان پرداخت کنید.\n         میبغ خود را مجددا ارسال نمایید";
                sendmessage($from_id, sprintf($rxDebtMustPayTpl, $balancruser), null, 'HTML');
                return false;
            }
        }

        \Faoxima\Core\Database\Db::update("user", "Processing_value", $amount, "id", $from_id);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);

        $__discEligibleCharge = class_exists('Faoxima\Orders\Discounts\DiscountRules') ? \Faoxima\Orders\Discounts\DiscountRules::hasEligible(\Faoxima\Api\Runtime\Db::pdo(), 'charge', '', '', '', $user) : false;
        if (!$__discEligibleCharge) {
            global $step_payment;
            sendmessage($from_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
            return true;
        }

        $__askdisc = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $datatextbot['dyn_wallet_prompt_has_discount_yes'] ?? "✅ بله، کد تخفیف دارم", 'callback_data' => "chargehasdiscount"],
                ],
                [
                    ['text' => $datatextbot['dyn_wallet_prompt_has_discount_no'] ?? "➡️ خیر، ادامه به پرداخت", 'callback_data' => "chargenodiscount"],
                ]
            ]
        ]);
        sendmessage($from_id, $datatextbot['dyn_wallet_prompt_ask_has_discount'] ?? "🎁 آیا برای شارژ کیف پول کد تخفیف دارید؟", $__askdisc, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return true;
    }
}
