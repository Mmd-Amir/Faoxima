<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Faoxima\Admin\Security\TelegramAccess;

final class AntiSpamActions
{
    public static function handle(
        TelegramAccess $access,
        int $adminAction,
        mixed &$setting,
        mixed $callback_query_id,
        mixed $message_id,
        mixed $text,
        array $update,
        array $textbotlang,
        mixed $backadmin,
        mixed $keyboardadmin,
    ): ?bool {
        $from_id = $access->senderId;
        switch ($adminAction) {
            case 1:

                if (!empty($callback_query_id)) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time' => 1,
                        ]);
                    } catch (\Throwable $rxAsNoopErr) {  }
                }
                break;
            case 2:

                $rxAsCurrent = (string)($setting['antispam_status'] ?? '0');
                $rxAsNew = ($rxAsCurrent === '1') ? '0' : '1';
                \Faoxima\Core\Database\Db::update("setting", "antispam_status", $rxAsNew);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) { \Faoxima\Core\Database\SelectCache::clearSelectCache('setting'); }
                if (!empty($callback_query_id)) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'text' => ($rxAsNew === '1') ? '✅ آنتی اسپم فعال شد' : '⛔️ آنتی اسپم غیرفعال شد',
                            'show_alert' => false,
                            'cache_time' => 0,
                        ]);
                    } catch (\Throwable $rxAsTogErr) {  }
                }

                $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select", ['cache' => false]);
                $rxAsRefreshedStatusValue = (string)($setting['antispam_status'] ?? '0');
                $rxAsRefreshedStatusText  = ($rxAsRefreshedStatusValue === '1')
                    ? $textbotlang['Admin']['Status']['statuson']
                    : $textbotlang['Admin']['Status']['statusoff'];
                $rxAsRefreshedMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
                $rxAsRefreshedSeconds  = (int)($setting['antispam_seconds'] ?? 3);
                $rxAsRefreshedMute     = (int)($setting['antispam_mute_seconds'] ?? 5);
                $rxAsRefreshedKb = [
                    [['text' => $rxAsRefreshedStatusText, 'callback_data' => "antispam_toggle"],
                     ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
                    [['text' => (string)$rxAsRefreshedMsgCount, 'callback_data' => "antispam_set_count"],
                     ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
                    [['text' => (string)$rxAsRefreshedSeconds, 'callback_data' => "antispam_set_seconds"],
                     ['text' => "⏱ بازه (ثانیه)", 'callback_data' => "antispam_noop"]],
                    [['text' => (string)$rxAsRefreshedMute, 'callback_data' => "antispam_set_mute"],
                     ['text' => "🔇 آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
                    [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']],
                ];
                $rxAsRefreshedTitle = "🛡 <b>آنتی اسپم</b>\n\n"
                    . "📌 <b>تنظیمات فعلی:</b>\n"
                    . "• وضعیت: {$rxAsRefreshedStatusText}\n"
                    . "• تعداد پیام مجاز: <b>{$rxAsRefreshedMsgCount}</b> پیام\n"
                    . "• بازه زمانی: هر <b>{$rxAsRefreshedSeconds}</b> ثانیه\n"
                    . "• مدت آف بودن: <b>{$rxAsRefreshedMute}</b> ثانیه";
                if (isset($message_id)) {
                    Editmessagetext($from_id, $message_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
                }
                break;
            case 3:

                $rxAsCur = (int)($setting['antispam_msg_count'] ?? 5);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "📌 <b>تنظیم تعداد پیام مجاز آنتی اسپم</b>\n\n"
                    . "مقدار فعلی: <b>{$rxAsCur}</b>\n\n"
                    . "لطفاً یک عدد بین <b>1</b> تا <b>1000</b> ارسال کنید.\n"
                    . "این عدد بیشترین تعداد پیامی است که کاربر می‌تواند در بازه زمانی تعیین‌شده ارسال کند.",
                    $backadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('antispam_get_count', $from_id);
                break;
            case 4:

                $rxAsCur = (int)($setting['antispam_seconds'] ?? 3);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "📌 <b>تنظیم بازه زمانی آنتی اسپم</b>\n\n"
                    . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
                    . "لطفاً یک عدد بین <b>1</b> تا <b>3600</b> (یک ساعت) ارسال کنید.\n"
                    . "این عدد طول بازه زمانی برای شمارش پیام‌ها است.",
                    $backadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('antispam_get_seconds', $from_id);
                break;
            case 5:

                $rxAsCur = (int)($setting['antispam_mute_seconds'] ?? 5);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "📌 <b>تنظیم مدت آف بودن ربات</b>\n\n"
                    . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
                    . "لطفاً یک عدد بین <b>1</b> تا <b>86400</b> (یک روز) ارسال کنید.\n"
                    . "این عدد مدت زمانی است که ربات پس از تخلف کاربر، به او پاسخ نمی‌دهد.\n\n"
                    . "ℹ️ این محدودیت فقط برای کاربران اعمال می‌شود؛ ادمین‌ها هرگز محدود نمی‌شوند.",
                    $backadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('antispam_get_mute', $from_id);
                break;
            case 6:
                if (!isset($update['message']) && empty($text)) { return null; }
                $rxAsTrim = is_string($text) ? trim($text) : '';
                if (!ctype_digit($rxAsTrim)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                $rxAsNum = (int)$rxAsTrim;
                if ($rxAsNum < 1 || $rxAsNum > 1000) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>1000</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                \Faoxima\Core\Database\Db::update("setting", "antispam_msg_count", (string)$rxAsNum);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) { \Faoxima\Core\Database\SelectCache::clearSelectCache('setting'); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "✅ تعداد پیام مجاز آنتی اسپم روی <b>{$rxAsNum}</b> تنظیم شد.",
                    $keyboardadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                break;
            case 7:
                if (!isset($update['message']) && empty($text)) { return null; }
                $rxAsTrim = is_string($text) ? trim($text) : '';
                if (!ctype_digit($rxAsTrim)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                $rxAsNum = (int)$rxAsTrim;
                if ($rxAsNum < 1 || $rxAsNum > 3600) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>3600</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                \Faoxima\Core\Database\Db::update("setting", "antispam_seconds", (string)$rxAsNum);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) { \Faoxima\Core\Database\SelectCache::clearSelectCache('setting'); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "✅ بازه زمانی آنتی اسپم روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
                    $keyboardadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                break;
            case 8:
                if (!isset($update['message']) && empty($text)) { return null; }
                $rxAsTrim = is_string($text) ? trim($text) : '';
                if (!ctype_digit($rxAsTrim)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                $rxAsNum = (int)$rxAsTrim;
                if ($rxAsNum < 1 || $rxAsNum > 86400) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                        $from_id,
                        "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>86400</b> ارسال کنید.",
                        $backadmin,
                        'HTML'
                    );
                    return null;
                }
                \Faoxima\Core\Database\Db::update("setting", "antispam_mute_seconds", (string)$rxAsNum);
                if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) { \Faoxima\Core\Database\SelectCache::clearSelectCache('setting'); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply(
                    $from_id,
                    "✅ مدت آف بودن ربات روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
                    $keyboardadmin,
                    'HTML'
                );
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                break;
        }
        return true;
    }
}
