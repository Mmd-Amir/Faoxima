<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $backuser, $datatextbot, $from_id, $keyboard, $message_id, $pdo, $step_payment, $telegramRoute, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RechargeAmount
{
    public static function handle(
        $backuser,
        $datatextbot,
        $from_id,
        $keyboard,
        $message_id,
        $pdo,
        $step_payment,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'recharge_amount':
            if (!isset($update['message']) && empty($text)) { return; }
            deletemessage($from_id, $user['Processing_value']);
            \Faoxima\Wallet\Balance\ChargePrompts::rx_walletChargeAmountAccepted($from_id, $user, $text);
            break;
        case 'recharge_no_discount':
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment);
            \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
            break;
        case 'recharge_discount_prompt':
            sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcodesellDiscountcharge', $from_id);
            deletemessage($from_id, $message_id);
            break;
        case 'recharge_discount':
            if (!isset($update['message']) && empty($text)) { return; }
            $__amount = intval($user['Processing_value']);
            if ($__amount <= 0) {
                sendmessage($from_id, $datatextbot['dyn_wallet_invalid_charge_amount'] ?? "❌ مبلغ شارژ نامعتبر است. مجددا تلاش کنید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, $text, 'charge', '', '', '', $user);
            if (empty($dv['ok'])) {
                sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
                return;
            }
            $__gatewayAmount = (int) round(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $__amount));
            if ($__gatewayAmount <= 0) {
                sendmessage($from_id, $datatextbot['dyn_wallet_discount_not_applicable_zero'] ?? "❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).", $backuser, 'HTML');
                return;
            }
            $__bonus = $__amount - $__gatewayAmount;
            if ($__bonus < 0) $__bonus = 0;
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $__gatewayAmount, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "chg|" . $__bonus . "|" . $text . "|" . $__amount, "id", $from_id);
            $__amountfmt  = number_format($__amount, 0);
            $__gatewayfmt = number_format($__gatewayAmount, 0);
            $__txt = "🤩 کد تخفیف {$dv['label']} روی شارژ کیف پول اعمال شد.\n\n💎 مبلغ شارژ کیف پول : {$__amountfmt} تومان\n💸 مبلغ قابل پرداخت : {$__gatewayfmt} تومان\n\nروش پرداخت را انتخاب کنید:";
            sendmessage($from_id, $__txt, $step_payment, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
            break;
        }

        return true;
    }
}
