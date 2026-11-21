<?php

namespace Faoxima\Admin\Telegram;

final class SettingsReportRoute
{
    public static function select($text, $datain, array $user, array $adminrulecheck, array $textbotlang, &$dataget, &$editLinkMatches): ?int
    {
        if ($adminrulecheck['rule'] == "administrator" && (
            (isset($datain) && preg_match('/^ch_editlink_(\d+)$/', (string)$datain, $editLinkMatches)) ||
            ($text == "🆔 ویرایش آیدی کانال" && !empty($user['Processing_value']) && is_numeric($user['Processing_value']))
        )) {
            return 1;
        } elseif ($user['step'] == "ch_edit_link" && $adminrulecheck['rule'] == "administrator") {
            return 2;
        } elseif (($text == $textbotlang['Admin']['channel']['removechannelbtn'] || $text == "❌ حذف کانال" || (isset($datain) && $datain == 'ch_del')) && $adminrulecheck['rule'] == "administrator") {
            return 3;
        } elseif ($user['step'] == "removechannel") {
            return 4;
        } elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
            return 5;
        } elseif ($user['step'] == "addadmin") {
            return 6;
        } elseif ($user['step'] == "getrule") {
            return 7;
        } elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
            return 8;
        } elseif ($user['step'] == "get_number_limit") {
            return 9;
        } elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
            return 10;
        } elseif ($user['step'] == "limit_usertest_allusers") {
            return 11;
        } elseif (($text == "📯 تنظیمات کانال" || $text == "🔙 بازگشت به لیست کانال‌ها" || (isset($datain) && in_array($datain, ['set_channel', 'ch_list'], true))) && $adminrulecheck['rule'] == "administrator") {
            return 12;
        } elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
            return 13;
        }
        return null;
    }
}
