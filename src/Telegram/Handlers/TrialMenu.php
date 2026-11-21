<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $admin_ids, $datatextbot, $from_id, $keyboard_buy, $message_id, $request_contact, $setting, $telegramRoute, $textbotlang, $user.
 * Inherited or exported by reference: $datain.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TrialMenu
{
    public static function handle(
        $admin_ids,
        &$datain,
        $datatextbot,
        $from_id,
        $keyboard_buy,
        $message_id,
        $request_contact,
        $setting,
        $telegramRoute,
        $textbotlang,
        $user
    ) {
        switch ($telegramRoute) {
        case 'trial_menu':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_usertest")) {
                sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
                return;
            }
            $rxTestEntryUser = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select", ['cache' => false]);
            if (!is_array($rxTestEntryUser) || empty($rxTestEntryUser)) {
                $rxTestEntryUser = $user;
            }
            $rxTestEntryAdmin = in_array($from_id, $admin_ids);
            $rxTestEntryOverview = \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_overview($rxTestEntryUser, $rxTestEntryAdmin);
            $locationproduct = count($rxTestEntryOverview['panels']);
            if ($locationproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
            if ($locationproduct != 1) {
                $rxTestEntryGate = \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_verification_gate($rxTestEntryUser, $rxTestEntryAdmin);
                if ($rxTestEntryGate === 'phone') {
                    if ($user['step'] != "get_number") {
                        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('get_number', $from_id);
                    }
                    return;
                }
                if ($rxTestEntryGate === 'verify') {
                    sendmessage($from_id, $datatextbot['dyn_testaccount_verify_required'] ?? "⚠️ حساب شما هنوز احراز هویت نشده است.", null, 'HTML');
                    return;
                }
                if (!$rxTestEntryOverview['any_available']) {
                    sendmessage($from_id, $rxTestEntryOverview['reason'] === 'audience_restricted' ? \Faoxima\Services\TestAccounts\TestAccountAdmin::rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
                    return;
                }
                $rxTestEntryKb = ['inline_keyboard' => []];
                foreach ($rxTestEntryOverview['panels'] as $rxTestEntryItem) {
                    if ($rxTestEntryItem['quota']['can_create']) {
                        $rxTestEntryKb['inline_keyboard'][] = [['text' => $rxTestEntryItem['panel']['name_panel'], 'callback_data' => "locationtest_{$rxTestEntryItem['panel']['code_panel']}"]];
                    }
                }
                $rxTestEntryKb['inline_keyboard'][] = [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]];
                \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('test', $from_id, $datain, $message_id, ['usertestbtn']);
                sendmessage($from_id, $datatextbot['textselectlocation'], json_encode($rxTestEntryKb), 'html');
            }
            break;
        }

        return true;
    }
}
