<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $caption, $connect, $dataget, $document, $fileid, $from_id, $json_list_helpkey, $keyboardadmin, $keyboardhelpadmin, $message_id, $pdo, $photo, $photoid, $rxPemPackInputStep, $setting, $text, $textbotlang, $update, $user, $video, $videoid.
 * Inherited or exported by reference: $Response, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiAndHelp
{
    public static function handle(
        &$Response,
        $adminAction,
        $adminExecution,
        $backadmin,
        $caption,
        $connect,
        $dataget,
        $document,
        $fileid,
        $from_id,
        $json_list_helpkey,
        $keyboardadmin,
        $keyboardhelpadmin,
        $message_id,
        $pdo,
        $photo,
        $photoid,
        $rxPemPackInputStep,
        $setting,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            if (!isset($update['message']) || !is_array($update['message'])) { return; }
            $rxPemPackPanel = isset($rxPemPackInputStep[1]) ? max(1, (int)$rxPemPackInputStep[1]) : 1;
            $rxPemPackBackCb = ($rxPemPackPanel > 1) ? "premium_emoji_settings_{$rxPemPackPanel}" : "premium_emoji_settings";
            $rxPemPackErrKb = json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 بازگشت به لیست", 'callback_data' => $rxPemPackBackCb]],
                ],
            ], JSON_UNESCAPED_UNICODE);
            $rxPemPackInput = trim((string)($update['message']['text'] ?? $update['message']['caption'] ?? ''));
            $rxPemPackName = method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiPackParseLink') ? \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiPackParseLink($rxPemPackInput) : null;
            if ($rxPemPackName === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لینک ارسال‌شده معتبر نیست.\n\n📌 لینک پک ایموجی را به این شکل ارسال کنید:\n<code>https://t.me/addemoji/kockrlandrandomemojipack3</code>", $rxPemPackErrKb, 'HTML');
                return;
            }
            $rxPemPackFetched = method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiPackFetch') ? \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiPackFetch($rxPemPackName) : ['ok' => false, 'error' => "❌ خطا در دریافت اطلاعات پک."];
            if (empty($rxPemPackFetched['ok'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, (string)($rxPemPackFetched['error'] ?? "❌ خطا در دریافت اطلاعات پک."), $rxPemPackErrKb, 'HTML');
                return;
            }
            if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPackPage')) {
                if (\Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPackPage($from_id, $rxPemPackName, 1, $rxPemPackPanel, $rxPemPackFetched)) {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                }
            }
            break;
        case 2:
            $iduser = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "iduser", $iduser);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 متن یا تصویر خود را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('sendmessagetext', $from_id);
            break;
        case 3:
            if ($photo) {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "type", "photo");
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "photoid", $photoid);
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "text", $caption);
            } else {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "text", $text);
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "type", "text");
            }
            $textb = "📌 کاربر بتواند پاسخ دهد یاخیر ؟
1 - بله  پاسخ دهد
2 - خیر پاسخ ندهد
پاسخ را به عدد ارسال کنید";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textb, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('sendmessagetid', $from_id);
            break;
        case 4:
            $userdata = json_decode($user['Processing_value'], true);
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            $textsendadmin = "
👤 یک پیام از طرف ادمین ارسال شده است
متن پیام:

{$userdata['text']}";
            if (intval($text) == "1") {
                $Response = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                        ],
                    ]
                ]);
                if ($userdata['type'] == "photo") {
                    telegram('sendphoto', [
                        'chat_id' => $userdata['iduser'],
                        'photo' => $userdata['photoid'],
                        'caption' => $textsendadmin,
                        'reply_markup' => $Response,
                        'parse_mode' => "HTML",
                    ]);
                } else {
                    sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
                }
            } else {
                if ($userdata['type'] == "photo") {
                    telegram('sendphoto', [
                        'chat_id' => $userdata['iduser'],
                        'photo' => $userdata['photoid'],
                        'caption' => $textsendadmin,
                        'parse_mode' => "HTML",
                    ]);
                } else {
                    sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
                }
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getmessageforward', $from_id);
            break;
        case 6:
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "messageid", $message_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getbtnresponseforward', $from_id);
            break;
        case 7:
            $userdata = json_decode($user['Processing_value'], true);
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            forwardMessage($from_id, $userdata['messageid'], $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
            break;
        case 9:
            // Issue #27: load the bundled default bot-usage tutorials into the help table.
            // Idempotent: entries whose name_os already exist are left untouched, so an
            // admin's customized tutorials are never overwritten.
            $rxHelpRoot = REFACTORED_LEGACY_ROOT;
            $rxHelpDefaultsFile = $rxHelpRoot . '/default_help.json';
            if (!is_file($rxHelpDefaultsFile)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فایل default_help.json در سورس ربات یافت نشد.", $keyboardhelpadmin, 'HTML');
                return;
            }
            $rxHelpDefaults = json_decode((string) file_get_contents($rxHelpDefaultsFile), true);
            if (!is_array($rxHelpDefaults) || empty($rxHelpDefaults)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ فایل default_help.json معتبر نیست.", $keyboardhelpadmin, 'HTML');
                return;
            }
            $rxHelpAdded = 0;
            $rxHelpSkipped = 0;
            $rxHelpInsert = $pdo->prepare("INSERT INTO help (name_os,Media_os,type_Media_os,category,Description_os,app_title,app_link) VALUES (?,?,?,?,?,?,?)");
            foreach ($rxHelpDefaults as $rxHelpItem) {
                $rxHelpName = trim((string) ($rxHelpItem['name_os'] ?? ''));
                if ($rxHelpName === '') { $rxHelpSkipped++; continue; }
                if (\Faoxima\Core\Database\Db::select("help", "id", "name_os", $rxHelpName, "select") !== false) { $rxHelpSkipped++; continue; }
                $rxHelpInsert->execute([
                    $rxHelpName,
                    '',
                    'text',
                    (string) ($rxHelpItem['category'] ?? ''),
                    (string) ($rxHelpItem['description'] ?? ''),
                    (string) ($rxHelpItem['app_title'] ?? ''),
                    (string) ($rxHelpItem['app_link'] ?? ''),
                ]);
                $rxHelpAdded++;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📥 آموزش‌های پیش‌فرض بارگذاری شد.\n\n✅ اضافه‌شده: {$rxHelpAdded}\n↩️ از قبل موجود (دست‌نخورده): {$rxHelpSkipped}", $keyboardhelpadmin, 'HTML');
            return;
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_name_help', $from_id);
            break;
        case 11:
            if (strlen($text) >= 150) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
                return;
            }
            $helpexits = \Faoxima\Core\Database\Db::select("help", "*", "name_os", $text, "count");
            if ($helpexits != 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام آموزش وجود دارد از نام دیگری استفاده نمایید.", null, 'HTML');
                return;
            }
            $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
            $stmt->bind_param("s", $text);
            $stmt->execute();
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            if ($setting['categoryhelp'] == "0") {
                \Faoxima\Core\Database\Db::update("help", "category", "0", "name_os", $user['Processing_value']);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('add_dec', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام دسته بندی برای آموزش را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcatgoryhelp', $from_id);
            break;
        case 12:
            \Faoxima\Core\Database\Db::update("help", "category", $text, "name_os", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_dec', $from_id);
            break;
        case 13:
            if ($photo) {
                if (isset($photoid))
                    \Faoxima\Core\Database\Db::update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
                if (isset($caption))
                    \Faoxima\Core\Database\Db::update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
                \Faoxima\Core\Database\Db::update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
            } elseif ($text) {
                \Faoxima\Core\Database\Db::update("help", "Description_os", $text, "name_os", $user['Processing_value']);
            } elseif ($video) {
                if (isset($videoid))
                    \Faoxima\Core\Database\Db::update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
                if (isset($caption))
                    \Faoxima\Core\Database\Db::update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
                \Faoxima\Core\Database\Db::update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
            } elseif ($document) {
                if (isset($fileid))
                    \Faoxima\Core\Database\Db::update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
                if (isset($caption))
                    \Faoxima\Core\Database\Db::update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
                \Faoxima\Core\Database\Db::update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
            }
            $skipAppLinkKb = json_encode([
                'inline_keyboard' => [
                    [['text' => '⏭ رد شدن (بدون لینک برنامه)', 'callback_data' => 'help_skip_app_link']],
                ],
            ], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در صورت تمایل متن دکمه لینک دانلود برنامه را برای این آموزش ارسال کنید، یا رد شدن را انتخاب کنید.", $skipAppLinkKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_app_title_help', $from_id);
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 15:
            if (!isset($update['message']) && empty($text)) { return; }
            if (strlen($text) > 200) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("help", "app_title", $text, "name_os", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('add_app_link_help', $from_id);
            break;
        case 16:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!filter_var($text, FILTER_VALIDATE_URL)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("help", "app_link", $text, "name_os", $user['Processing_value']);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('remove_help', $from_id);
            break;
        case 18:
            $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
            $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
