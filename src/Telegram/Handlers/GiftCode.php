<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $backuser, $from_id, $keyboard, $message_id, $otherreport, $pdo, $setting, $telegramRoute, $text, $textbotlang, $update, $username.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GiftCode
{
    public static function handle(
        $backuser,
        $from_id,
        $keyboard,
        $message_id,
        $otherreport,
        $pdo,
        $setting,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        $username
    ) {
        switch ($telegramRoute) {
        case 'discount_prompt':
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account", '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
            \Faoxima\Telegram\Navigation\Steps::step('get_code_user', $from_id);
            break;
        case 'discount_code':
            if (!isset($update['message']) && empty($text)) { return; }
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('Discount', 'code', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
                return;
            }
            $checklimit = \Faoxima\Core\Database\Db::select("Discount", "*", "code", $text, "select");
            $__gstatus = strtolower(trim((string)($checklimit['status'] ?? '')));
            if ($__gstatus !== '' && $__gstatus !== 'active') {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $__gtarget = trim((string)($checklimit['target_user'] ?? ''));
            if ($__gtarget !== '' && $__gtarget !== (string)$from_id) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $__gexp = intval($checklimit['expire_at'] ?? 0);
            if ($__gexp !== 0 && time() >= $__gexp) {
                sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
                return;
            }
            if (intval($checklimit['limituse']) > 0 && intval($checklimit['limitused']) >= intval($checklimit['limituse'])) {
                sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
                return;
            }
            $__giftResult = is_callable([\Faoxima\Wallet\Gifts\Gifts::class, 'giftRedeemAtomic'])
                ? \Faoxima\Wallet\Gifts\Gifts::giftRedeemAtomic($pdo, $text, $from_id)
                : ['ok' => false, 'reason' => 'db_error'];
            if (empty($__giftResult['ok'])) {
                if (($__giftResult['reason'] ?? '') === 'expired' || ($__giftResult['reason'] ?? '') === 'capacity') {
                    sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
                } else {
                    sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                }
                return;
            }
            $__giftAmount = (int)($__giftResult['amount'] ?? 0);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], \Faoxima\Core\Support\Formatting::rxFormatToman($__giftAmount));
            sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
            $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
