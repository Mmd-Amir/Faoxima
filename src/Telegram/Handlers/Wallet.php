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
                $numberphone = \Faoxima\Telegram\Support\MessageTemplates::get('account_phone_missing');
            } else {
                $numberphone = $user['number'];
            }
            if ($user['number'] == "confrim number by admin") {
                $numberphone = \Faoxima\Telegram\Support\MessageTemplates::get('account_phone_admin_confirmed');
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
                'f' => \Faoxima\Telegram\Support\MessageTemplates::plain('account_group_normal'),
                'n' => \Faoxima\Telegram\Support\MessageTemplates::plain('account_group_agent'),
                'n2' => \Faoxima\Telegram\Support\MessageTemplates::plain('account_group_agent_advanced'),
            ][$user['agent']];
            $userjoin = jdate('Y/m/d H:i:s', $user['register']);
            if (intval($setting['scorestatus']) == 1) {
                $textscore = \Faoxima\Telegram\Support\MessageTemplates::get('account_score_line', ['score' => $user['score']]);
            } else {
                $textscore = "";
            }
            $textinvite = "";
            if ($setting['verifybucodeuser'] == "onverify" and $setting['verifystart'] == "onverify") {
                $textscore = \Faoxima\Telegram\Support\MessageTemplates::get('account_referral_verify_line', ['bot' => $usernamebot, 'code' => $user['codeInvitation']]);
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
            $rxWalletIsEdit = ($datain == "account" || $datain == "accountbtn");
            $activeKeyboard = \Faoxima\Telegram\Presentation\AccountLayout::rx_account_keyboard_json($from_id);
            if ($rxWalletIsEdit) {
                Editmessagetext($from_id, $message_id, $text_account, $activeKeyboard);
            } else {
                sendmessage($from_id, $text_account, $activeKeyboard, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
        }

        return true;
    }
}
