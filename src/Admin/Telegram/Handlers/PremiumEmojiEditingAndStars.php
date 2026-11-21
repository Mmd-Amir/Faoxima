<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $Startelegram, $adminAction, $adminExecution, $backadmin, $caption, $from_id, $keyboardadmin, $nowpayment_setting_keyboard, $pdo, $photo, $photoid, $rxPemMatch, $rxPemReplaceMatch, $rxPemRow, $text, $textbotlang, $user, $video, $videoid.
 * Inherited or exported by reference: $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiEditingAndStars
{
    public static function handle(
        $Startelegram,
        $adminAction,
        $adminExecution,
        $backadmin,
        $caption,
        &$data,
        $from_id,
        $keyboardadmin,
        $nowpayment_setting_keyboard,
        $pdo,
        $photo,
        $photoid,
        $rxPemMatch,
        $rxPemReplaceMatch,
        $rxPemRow,
        $text,
        $textbotlang,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $rxPemRowId = (int)$rxPemMatch[1];
            $rxPemEditPage = isset($rxPemMatch[2]) ? max(1, (int)$rxPemMatch[2]) : 1;
            try {
                $rxPemStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id");
                $rxPemStmt->execute([':id' => $rxPemRowId]);
                $rxPemRow = $rxPemStmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) { $rxPemRow = null; }
            if (!$rxPemRow) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ایموجی یافت نشد.", null, 'HTML');
            } else {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxPemRowId . ':' . $rxPemEditPage, "id", $from_id);
                $rxPemCurCid = (string)$rxPemRow['custom_emoji_id'];
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✏️ <b>ویرایش ایموجی [{$rxPemRow['emoji']}]</b>\n\n🆔 آیدی فعلی: <code>{$rxPemCurCid}</code>\n\n📤 <b>ایموجی پرمیوم جدید</b> را همین‌جا ارسال کنید — ربات آیدی جدید را خودکار تشخیص می‌دهد.\n\n💡 می‌توانید ایموجی پرمیوم را به هر شکلی بفرستید: متن، استیکر، Forward یا Reply.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_edit_id', $from_id);
            }
            break;
        case 2:
            $rxPemRowId = (int)$rxPemMatch[1];
            try {
                $rxPemStmt = $pdo->prepare("DELETE FROM premium_emojis WHERE id = :id");
                $rxPemStmt->execute([':id' => $rxPemRowId]);
                if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🗑 ایموجی حذف شد.", json_encode([
                    'inline_keyboard' => [
                        [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
                    ]
                ]), 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در حذف ایموجی.", null, 'HTML');
            }
            break;
        case 3:

            $rxPemReplaceId = (int)$rxPemReplaceMatch[1];
            $rxPemReplaceCid = (string)$rxPemReplaceMatch[2];
            try {
                $rxPemRowStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id LIMIT 1");
                $rxPemRowStmt->execute([':id' => $rxPemReplaceId]);
                $rxPemRowData = $rxPemRowStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($rxPemRowData)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ردیف مورد نظر یافت نشد. ممکن است حذف شده باشد.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                    return;
                }
                $rxPemReplBase = (string)$rxPemRowData['emoji'];
                $rxPemReplOldCid = (string)$rxPemRowData['custom_emoji_id'];
                $rxPemUpd = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE id = :id");
                $rxPemUpd->execute([':c' => $rxPemReplaceCid, ':t' => time(), ':id' => $rxPemReplaceId]);
                if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "♻️ <b>آیدی پرمیوم با موفقیت جایگزین شد!</b>\n\n• ایموجی پایه: {$rxPemReplBase}\n• 🆔 آیدی قبلی: <code>{$rxPemReplOldCid}</code>\n• 🆔 آیدی جدید: <code>{$rxPemReplaceCid}</code>", json_encode([
                    'inline_keyboard' => [
                        [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
                    ]
                ]), 'HTML');
            } catch (\Throwable $rxPemReplErr) {
                @error_log('[premium_emoji_replace] ' . $rxPemReplErr->getMessage());
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در جایگزینی آیدی.", null, 'HTML');
            }
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Startelegram, 'HTML');
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmainaqstar", $from_id);
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "minbalancestar");
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("maxbalancestar", $from_id);
            break;
        case 8:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancestar");
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmainaqnowpayment", $from_id);
            break;
        case 10:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "minbalancenowpayment");
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("maxbalancenowpayment", $from_id);
            break;
        case 12:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancenowpayment");
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gethelpstar", $from_id);
            break;
        case 14:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpstar");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید )", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("chashbackstar", $from_id);
            break;
        case 16:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "chashbackstar");
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت، ابتدا «قیمت گروه کاربر عادی (f)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricef", $from_id);
            break;
        case 18:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "pricef", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده عادی (n)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricnn", $from_id);
            break;
        case 19:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pricen", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده پیشرفته (n2)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricnn2", $from_id);
            break;
        case 20:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pricelist = json_encode(array(
                'f' => $userdata['pricef'],
                'n' => $userdata['pricen'],
                'n2' => $text
            ));
            \Faoxima\Core\Database\Db::update("marzban_panel", "pricecustomvolume", $pricelist, null, null);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 21:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت، ابتدا «قیمت گروه کاربر عادی (f)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpriceftime", $from_id);
            break;
        case 22:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "pricef", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده عادی (n)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricnntime", $from_id);
            break;
        case 23:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "pricen", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 «قیمت گروه نماینده پیشرفته (n2)» را به صورت عدد ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricnn2time", $from_id);
            break;
        case 24:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفاً فقط «عدد» (مبلغ قیمت) ارسال کنید. (مثلاً: 5000)", $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $pricelist = json_encode(array(
                'f' => $userdata['pricef'],
                'n' => $userdata['pricen'],
                'n2' => $text
            ));
            \Faoxima\Core\Database\Db::update("marzban_panel", "pricecustomtime", $pricelist, null, null);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
