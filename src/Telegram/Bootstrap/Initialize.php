<?php

namespace Faoxima\Telegram\Bootstrap;

use ManagePanel;

/**
 * Handler stage. Inherited by value: $Chat_type, $callback_query_id, $chat_member, $is_bot, $update.
 * Inherited or exported by reference: $ManagePanel, $first_name, $from_id, $setting, $status, $textbotlang, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Initialize
{
    public static function handle(
        $Chat_type,
        &$ManagePanel,
        $callback_query_id,
        $chat_member,
        &$first_name,
        &$from_id,
        $is_bot,
        &$setting,
        &$status,
        &$textbotlang,
        $update,
        &$user
    ) {
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange('text.json');
        if ($is_bot)
            return;
        if (isset($update['chat_member'])) {
            $status = $update['chat_member']['new_chat_member']['status'];
            $from_id = $update['chat_member']['new_chat_member']['user']['id'];
            $user = \Faoxima\Core\Database\Db::select("user", "id", $from_id);
            $keyboard_channel_left = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "📌 عضویت مجدد", 'url' => "https://t.me/{$update['chat_member']['chat']['username']}"],
                    ],
                ]
            ]);
            if (in_array($status, ['left', 'kicked', 'restricted'])) {
                sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
                return;
            }
        }
        if (!in_array($Chat_type, ["private", "supergroup"]))
            return;
        if ($Chat_type === "supergroup" && isset($update['message'])) {
            $_rxGroupAdminStep = '';
            if (function_exists('rx_isAdminChat') && rx_isAdminChat($from_id)) {
                $_rxGroupAdminRow = \Faoxima\Core\Database\Db::select("user", "step", "id", $from_id, "select", ['cache' => false]);
                $_rxGroupAdminStep = is_array($_rxGroupAdminRow) ? (string) ($_rxGroupAdminRow['step'] ?? '') : '';
            }
            if (!in_array($_rxGroupAdminStep, ['reject-dec'], true)) {
                if (method_exists(\Faoxima\Telegram\Support\Replies::class, 'rx_replyGroupIdCommand')) {
                    \Faoxima\Telegram\Support\Replies::rx_replyGroupIdCommand($update, $from_id, $Chat_type);
                }
                return;
            }
        }
        if (isset($chat_member))
            return;
        $first_name = \Faoxima\Core\Support\Formatting::sanitizeUserName($first_name);
        if (!is_array($setting)) {
            $rxSettingMissingMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_setting_missing.flag';
            if (!is_file($rxSettingMissingMarker) || (time() - (int) @filemtime($rxSettingMissingMarker)) > 3600) {
                error_log('Settings data is unavailable. Ensure the `setting` table exists and contains records.');
                @touch($rxSettingMissingMarker);
            }
            unset($rxSettingMissingMarker);
            return;
        }
        $ManagePanel = new ManagePanel();
        $keyboard_check = json_decode($setting['keyboardmain'], true);
        if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
            $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
            \Faoxima\Core\Database\Db::update("setting", "keyboardmain", $keyboardmain, null, null);
        }

        if (!\Faoxima\Telegram\Support\Transport::checktelegramip())
            die("Unauthorized access");

        if (intval($from_id) == 0)
            return;

        if (isset($update['message']['successful_payment']) && method_exists(\Faoxima\Payments\Gateways\Tronado\Runtime::class, 'rxStarsHandleSuccessfulPayment') && \Faoxima\Payments\Gateways\Tronado\Runtime::rxStarsHandleSuccessfulPayment($update)) {
            return;
        }

        if (!empty($callback_query_id) && function_exists('rx_flushPendingReplyKeyboardCleanup')) {
            rx_flushPendingReplyKeyboardCleanup($from_id);
        }

        return true;
    }
}
