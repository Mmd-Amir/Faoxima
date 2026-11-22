<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_acc_styles, $datain, $from_id, $message_id, $pdo, $telegramRoute, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class WalletTransfer
{
    public static function handle(
        $_rx_acc_styles,
        $datain,
        $from_id,
        $message_id,
        $pdo,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'transfer_start':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "0", "id", $from_id);
            $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
            $trBackKb = json_encode(['inline_keyboard' => [
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account', '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true], 'backuser', $_rx_acc_s)],
            ]], JSON_UNESCAPED_UNICODE);
            $trPromptText = "🔄 انتقال موجودی\n\nآیدی عددی تلگرام کاربر مقصد را ارسال کنید:";
            if ($message_id) {
                Editmessagetext($from_id, $message_id, $trPromptText, $trBackKb, 'HTML');
            } else {
                sendmessage($from_id, $trPromptText, $trBackKb, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('getTransferRecipient', $from_id);
            return;
        case 'transfer_recipient':
            if (!\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxBotHandleTransferRecipient($from_id, $text, isset($update['message']))) {
                return;
            }
            break;
        case 'transfer_amount':
            if (!\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxBotHandleTransferAmount($from_id, $text, isset($update['message']), $user, is_array($_rx_acc_styles ?? null) ? $_rx_acc_styles : [])) {
                return;
            }
            break;
        case 'transfer_cancel':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
            Editmessagetext($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('wallet_transfer_canceled'), null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'transfer_confirm':
            if (!\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxBotHandleTransferConfirmation($pdo, $from_id, $message_id, $datain, $user)) {
                return;
            }
            break;
        }

        return true;
    }
}
