<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $datatextbot, $from_id, $message_id, $numberphone, $pdo, $setting, $telegramRoute, $text, $textscore, $usernamebot.
 * Inherited or exported by reference: $datain, $first_name, $stmt, $user, $userjoin.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Wallet
{
    public static function handle(
        &$datain,
        $datatextbot,
        &$first_name,
        $from_id,
        $message_id,
        $numberphone,
        $pdo,
        $setting,
        &$stmt,
        $telegramRoute,
        $text,
        $textscore,
        &$user,
        &$userjoin,
        $usernamebot
    ) {
        switch ($telegramRoute) {
        case 'wallet':
            $rxWalletRoute = 'wallet_unknown';
            if ($datain == 'account') {
                $rxWalletRoute = 'account';
            } elseif ($datain == 'accountbtn') {
                $rxWalletRoute = 'accountbtn';
            } elseif ($text == '/wallet') {
                $rxWalletRoute = 'wallet_command';
            } elseif ($text !== '' && $text == ($datatextbot['accountwallet'] ?? '')) {
                $rxWalletRoute = 'wallet_button';
            }
            $rxWalletStartedAt = microtime(true);
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_stateSnapshot(['phase' => 'wallet_before_handler', 'chat_id' => $from_id, 'message_id' => $message_id]), 'debug');
            }
            $rxWalletCtx = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_enterContext') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_enterContext('text_account_info', 'user_flow_wallet_branch', $rxWalletRoute, 'textbot_row', 'user_flow_wallet') : '';
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_HANDLER_ENTER', 'wallet', 'entered', [
                    'handler' => 'user_flow_wallet_branch',
                    'callback_route' => $rxWalletRoute,
                    'is_callback' => $datain !== '' && (int) $message_id > 0,
                    'message_id_present' => (int) $message_id > 0,
                    'chat_hash' => substr(hash('sha256', (string) $from_id), 0, 12),
                    'active_ui_section' => $GLOBALS['rx_ui_section'] ?? null,
                ], 'info');
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_stateSnapshot(['phase' => 'wallet_handler_entered', 'chat_id' => $from_id, 'message_id' => $message_id]), 'debug');
            }
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('wallet', $from_id, $datain, $message_id, ['accountbtn'], ['accountbtn']);
            $dateacc = jdate('Y/m/d');
            $current_time = time();
            $timeacc = jdate('H:i:s', $current_time);
            if (!is_string($user['codeInvitation']) || trim($user['codeInvitation']) === '') {
                $user['codeInvitation'] = \Faoxima\Services\Referrals\Referrals::ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
            }
            $first_name = htmlspecialchars($first_name);
            $Balanceuser = number_format($user['Balance'], 0);
            if ($user['number'] == "none") {
                $numberphone = "🔴 ارسال نشده است 🔴";
            } else {
                $numberphone = $user['number'];
            }
            if ($user['number'] == "confrim number by admin") {
                $numberphone = "✅ تایید شده توسط ادمین";
            } else {
                $numberphone = $numberphone;
            }
            $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
            $stmt->execute([
                ':id_user' => $from_id
            ]);
            $countorder = $stmt->rowCount();
            $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
            $stmt->execute([
                ':from_id' => $from_id
            ]);
            $countpayment = $stmt->rowCount();
            $groupuser = [
                'f' => "عادی",
                'n' => "نماینده",
                'n2' => "نمایندگی پیشرفته",
            ][$user['agent']];
            $userjoin = jdate('Y/m/d H:i:s', $user['register']);
            if (intval($setting['scorestatus']) == 1) {
                $textscore = "🥅 امتیاز حساب کاربری شما : {$user['score']}";
            } else {
                $textscore = "";
            }
            $textinvite = "";
            if ($setting['verifybucodeuser'] == "onverify" and $setting['verifystart'] == "onverify") {
                $textscore = "

🔗 لینک ریفرال جهت احراز زیر مجموعه :
https://t.me/$usernamebot?start={$user['codeInvitation']}";
            }
            $text_account_tpl = $datatextbot['text_account_info'] ?? "
🗂 اطلاعات حساب کاربری شما :

🪪 شناسه کاربری: <code>{shanase}</code>
👤 نام: <code>{name}</code>
👨‍👩‍👦 کد معرف شما : <code>{referral_code}</code>
📱 شماره تماس :{phone}
⌚️زمان ثبت نام : {register_date}
💰 موجودی: {balance} تومان
🛒 تعداد سرویس های خریداری شده : {order_count} عدد
📑 تعداد فاکتور های پرداخت شده :  : {payment_count} عدد
🤝 تعداد زیر مجموعه های شما : {affiliate_count} نفر
🔖 گروه کاربری : {group}

📆 {date_now} → ⏰ {time_now}
                    ";
            $rxWalletStructureRaw = isset($datatextbot['richmsg.text_account_info']) ? (string) $datatextbot['richmsg.text_account_info'] : '';
            $rxWalletTplInfo = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_text') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text_account_tpl) : [];
            $rxWalletStructure = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_structure') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_structure($rxWalletStructureRaw) : ['available' => false];
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_TEMPLATE_LOADED', 'wallet_template', !empty($rxWalletTplInfo['present']) ? 'loaded' : 'empty', [
                    'template_key' => 'text_account_info',
                    'template_source' => trim((string) $text_account_tpl) !== '' ? 'textbot_row' : 'empty_text',
                    'text' => $rxWalletTplInfo,
                    'structure' => $rxWalletStructure,
                    'companion_key' => 'richmsg.text_account_info',
                    'companion_present' => trim($rxWalletStructureRaw) !== '',
                    'parse_mode' => 'html',
                ], 'info');
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_STRUCTURE_RESOLVED', 'wallet_structure', !empty($rxWalletStructure['available']) ? 'found' : 'missing', $rxWalletStructure + [
                    'template_key' => 'text_account_info',
                    'companion_key' => 'richmsg.text_account_info',
                    'companion_present' => trim($rxWalletStructureRaw) !== '',
                ], 'info');
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('template_retrieval', 'text_account_info', $rxWalletTplInfo, $rxWalletTplInfo, [
                    'template_source' => trim((string) $text_account_tpl) !== '' ? 'textbot_row' : 'empty_text',
                    'structure_digest' => $rxWalletStructure['sha256'] ?? null,
                    'structure_available' => !empty($rxWalletStructure['available']),
                    'after' => [
                        'rich_blocks' => $rxWalletStructure['blocks'] ?? 0,
                        'structure_sha256' => $rxWalletStructure['sha256'] ?? null,
                        'structure_available' => !empty($rxWalletStructure['available']),
                    ],
                    'phase' => 'wallet_template_retrieval',
                ]);
            }
            $text_account_replacements = [
                '{shanase}' => $from_id,
                '{name}' => $first_name,
                '{referral_code}' => $user['codeInvitation'],
                '{phone}' => $numberphone,
                '{register_date}' => $userjoin,
                '{balance}' => $Balanceuser,
                '{order_count}' => $countorder,
                '{payment_count}' => $countpayment,
                '{affiliate_count}' => $user['affiliatescount'],
                '{group}' => $groupuser,
                '{date_now}' => $dateacc,
                '{time_now}' => $timeacc,
            ];
            $text_account = strtr($text_account_tpl, $text_account_replacements) . "\n$textscore\n$textinvite";
            $rxWalletTextInfo = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_text') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text_account) : [];
            $rxWalletIsEdit = ($datain == "account" || $datain == "accountbtn");
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('wallet_variables_applied', 'text_account_info', $rxWalletTplInfo, $rxWalletTextInfo, [
                    'template_source' => trim((string) $text_account_tpl) !== '' ? 'textbot_row' : 'empty_text',
                    'structure_digest' => $rxWalletStructure['sha256'] ?? null,
                    'structure_available' => !empty($rxWalletStructure['available']),
                    'after' => [
                        'rich_blocks' => $rxWalletStructure['blocks'] ?? 0,
                        'structure_sha256' => $rxWalletStructure['sha256'] ?? null,
                        'structure_available' => !empty($rxWalletStructure['available']),
                    ],
                    'phase' => 'wallet_variables',
                ]);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_stateSnapshot([
                    'phase' => 'wallet_template_rendered',
                    'text' => $text_account,
                    'chat_id' => $from_id,
                    'message_id' => $message_id,
                    'rendering_mode' => 'template_rendered',
                    'previous_message_key' => 'text_account_info',
                ]), 'debug');
            }
            $activeKeyboard = \Faoxima\Telegram\Presentation\AccountLayout::rx_account_keyboard_json($from_id);
            $rxWalletExplicitRich = !empty($rxWalletTextInfo['rtl_marker']) || !empty($rxWalletTextInfo['html_table']);
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_RENDER_PREPARED', 'wallet_render', 'prepared', [
                    'template_key' => 'text_account_info',
                    'callback_route' => $rxWalletRoute,
                    'render_method' => $rxWalletIsEdit ? 'Editmessagetext' : 'sendmessage',
                    'expected_api_method' => $rxWalletIsEdit ? 'editmessagetext' : 'sendmessage',
                    'is_edit' => $rxWalletIsEdit,
                    'target_message_id' => $rxWalletIsEdit ? (int) $message_id : null,
                    'text' => $rxWalletTextInfo,
                    'table_present' => !empty($rxWalletTextInfo['html_table']),
                    'premium_present' => !empty($rxWalletTextInfo['tg_emoji_tags']),
                    'expected_explicit_rich' => $rxWalletExplicitRich,
                    'expected_embed_buttons' => null,
                    'expected_dest_rich' => $rxWalletExplicitRich,
                    'active_ui_section' => $GLOBALS['rx_ui_section'] ?? null,
                    'parse_mode' => 'html',
                ], 'info', (microtime(true) - $rxWalletStartedAt) * 1000.0);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('wallet_render_prepared', 'text_account_info', $rxWalletTextInfo, $rxWalletTextInfo, [
                    'render_mode' => $rxWalletIsEdit ? 'rich_edit' : 'rich_send',
                    'telegram_method' => $rxWalletIsEdit ? 'editmessagetext' : 'sendmessage',
                    'structure_digest' => $rxWalletStructure['sha256'] ?? null,
                    'structure_available' => !empty($rxWalletStructure['available']),
                    'after' => [
                        'rich_blocks' => $rxWalletStructure['blocks'] ?? 0,
                        'structure_sha256' => $rxWalletStructure['sha256'] ?? null,
                        'structure_available' => !empty($rxWalletStructure['available']),
                    ],
                    'phase' => 'wallet_prepared',
                ]);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_stateSnapshot([
                    'phase' => 'wallet_before_dispatch',
                    'text' => $text_account,
                    'chat_id' => $from_id,
                    'message_id' => $message_id,
                    'rendering_mode' => $rxWalletIsEdit ? 'rich_edit' : 'rich_send',
                    'dest_rich' => $rxWalletExplicitRich,
                    'needs_intercept' => !empty($rxWalletTextInfo['html_table']) || !empty($rxWalletTextInfo['rtl_marker']) || !empty($rxWalletTextInfo['html_markup']),
                    'previous_message_key' => 'text_account_info',
                ]), 'debug');
            }
            $rxWalletResult = null;
            if ($rxWalletIsEdit) {
                $rxWalletResult = Editmessagetext($from_id, $message_id, $text_account, $activeKeyboard);
            } else {
                $rxWalletResult = sendmessage($from_id, $text_account, $activeKeyboard, 'HTML');
            }
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                $rxWalletOk = is_array($rxWalletResult) && !empty($rxWalletResult['ok']);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_DELIVERY_COMPLETED', 'wallet_delivery', $rxWalletOk ? 'completed' : 'failed', [
                    'template_key' => 'text_account_info',
                    'callback_route' => $rxWalletRoute,
                    'render_method' => $rxWalletIsEdit ? 'Editmessagetext' : 'sendmessage',
                    'is_edit' => $rxWalletIsEdit,
                    'target_message_id' => $rxWalletIsEdit ? (int) $message_id : null,
                    'resulting_message_id' => is_array($rxWalletResult) ? (int) ($rxWalletResult['result']['message_id'] ?? 0) : null,
                    'text' => $rxWalletTextInfo,
                    'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($rxWalletResult),
                    'table_present' => !empty($rxWalletTextInfo['html_table']),
                    'premium_present' => !empty($rxWalletTextInfo['tg_emoji_tags']),
                ], $rxWalletOk ? 'info' : 'warning', (microtime(true) - $rxWalletStartedAt) * 1000.0);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('wallet_delivery', 'text_account_info', $rxWalletTextInfo, $rxWalletTextInfo, [
                    'render_mode' => $rxWalletIsEdit ? 'rich_edit' : 'rich_send',
                    'telegram_method' => $rxWalletIsEdit ? 'editmessagetext' : 'sendmessage',
                    'structure_digest' => $rxWalletStructure['sha256'] ?? null,
                    'structure_available' => !empty($rxWalletStructure['available']),
                    'rich_json_valid' => is_array($rxWalletResult) && isset($rxWalletResult['result']['rich_message']),
                    'after' => [
                        'rich_blocks' => $rxWalletStructure['blocks'] ?? 0,
                        'structure_sha256' => $rxWalletStructure['sha256'] ?? null,
                        'structure_available' => !empty($rxWalletStructure['available']),
                    ],
                    'phase' => 'wallet_delivery',
                ]);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_stateSnapshot([
                    'phase' => 'wallet_after_dispatch',
                    'text' => $text_account,
                    'chat_id' => $from_id,
                    'message_id' => $message_id,
                    'previous_message_key' => 'text_account_info',
                ]) + ['scope_result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($rxWalletResult)], $rxWalletOk ? 'debug' : 'warning');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_WALLET_HANDLER_EXIT', 'wallet', (is_array($rxWalletResult) && !empty($rxWalletResult['ok'])) ? 'completed' : 'failed', [
                    'callback_route' => $rxWalletRoute,
                    'template_key' => 'text_account_info',
                    'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($rxWalletResult),
                ], 'info', (microtime(true) - $rxWalletStartedAt) * 1000.0);
            }
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_exitContext')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_exitContext($rxWalletCtx);
            }
            return;
        }

        return true;
    }
}
