<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $datatextbot, $from_id, $keyboardadmin, $message_id, $pdo, $rxPemBase, $rxPemButtons, $rxPemMode, $rxPemSettingsCb, $rxPemSuccessMsg, $text, $textbot, $update, $user.
 * Inherited or exported by reference: $rxPemRow.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TextAndHelpSettings
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $datatextbot,
        $from_id,
        $keyboardadmin,
        $message_id,
        $pdo,
        $rxPemBase,
        $rxPemButtons,
        $rxPemMode,
        &$rxPemRow,
        $rxPemSettingsCb,
        $rxPemSuccessMsg,
        $text,
        $textbot,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            @file_put_contents('users.json', json_encode(array()));
            if (method_exists(\Faoxima\Admin\Broadcast\BroadcastQueue::class, 'rx_broadcast_cancel')) {
                \Faoxima\Admin\Broadcast\BroadcastQueue::rx_broadcast_cancel('cronbot');
            } else {
                @unlink('cronbot/users.json');
                @unlink('cronbot/users.txt');
                @unlink('cronbot/users.txt.new');
                @unlink('cronbot/users.txt.tail.tmp');
                @unlink('cronbot/info');
            }
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ عملیات ارسال پیام لغو شد و قابل ادامه نیست.", null, 'HTML');
            break;
        case 2:
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📝 این بخش به پنل تحت وب منتقل شده است.\n\nبرای ویرایش متن‌های ربات به پنل مدیریت مراجعه کنید.", $keyboardadmin, 'HTML');
            break;
        case 3:
            $_ms_cur = htmlspecialchars((string)($datatextbot['miniapp_suggest_1'] ?? ''), ENT_QUOTES);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📝 <b>متن پیشنهاد مینی‌اپ</b>\n\nمتن فعلی:\n<code>{$_ms_cur}</code>\n\n📌 متن جدید را ارسال کنید:", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('edit_miniapp_suggest_text', $from_id);
            break;
        case 4:
            if ($text !== '') {
                \Faoxima\Core\Database\Db::update("textbot", "text", $text, "id_text", "miniapp_suggest_1");
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ متن با موفقیت ذخیره شد.", $textbot, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ متن نمی‌تواند خالی باشد.", $textbot, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            if (!isset($update['message']) || !is_array($update['message'])) { return; }
            try {
                $rxPemMsg = $update['message'];
                $rxPemRawText = trim((string)($rxPemMsg['text'] ?? ''));
                $rxPemSticker = $rxPemMsg['sticker'] ?? null;

                $rxPemBase = '';
                if ($rxPemRawText !== '') {
                    $rxPemBase = $rxPemRawText;
                } elseif (is_array($rxPemSticker) && !empty($rxPemSticker['emoji'])) {
                    $rxPemBase = trim((string)$rxPemSticker['emoji']);
                }

                if (!\Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiIsValidBase($rxPemBase)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ایموجی نامعتبر است.\n\nلطفاً فقط یک <b>ایموجی عادی</b> ارسال کنید (مثل ✅، ❌، 🔥، 💎).\nمتن معمولی، عدد یا چند ایموجی همراه با متن پذیرفته نمی‌شود.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                    return;
                }

                $rxPemPrevVal = (string)($user['Processing_value'] ?? '');
                $rxPemMode = (strpos($rxPemPrevVal, 'batch:') === 0) ? 'batch' : 'single';
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $rxPemMode . ':' . $rxPemBase, "id", $from_id);

                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ <b>ایموجی پایه ذخیره شد:</b> {$rxPemBase}\n\n📌 حالا <b>ایموجی پرمیوم متناظر</b> را ارسال کنید.\n\nربات خودکار آیدی آن را تشخیص می‌دهد و این دو را به هم متصل می‌کند ✨\n\n💡 می‌توانید ایموجی پرمیوم را به هر شکلی بفرستید: متن، استیکر، Forward یا Reply.", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
                ]), 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_get_id', $from_id);
            } catch (\Throwable $rxPemErr) {
                @error_log('[premium_emoji_get_char] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
                ]), 'HTML');
                return;
            }
            break;
        case 6:
            if (!isset($update['message']) || !is_array($update['message'])) { return; }
            try {
                $rxPemMsg = $update['message'];
                $rxPemRaw = (string)($user['Processing_value'] ?? '');
                if (strpos($rxPemRaw, 'batch:') === 0) {
                    $rxPemMode = 'batch';
                    $rxPemBase = substr($rxPemRaw, 6);
                } elseif (strpos($rxPemRaw, 'single:') === 0) {
                    $rxPemMode = 'single';
                    $rxPemBase = substr($rxPemRaw, 7);
                } else {
                    $rxPemMode = 'single';
                    $rxPemBase = $rxPemRaw;
                }
                if (!\Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiIsValidBase($rxPemBase)) {
                    \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ایموجی پایه معتبر یافت نشد. لطفاً دوباره از ابتدا شروع کنید.", json_encode([
                        'inline_keyboard' => [
                            [['text' => "➕ افزودن ایموجی جدید", 'callback_data' => "premium_emoji_add"]],
                            [['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]],
                        ]
                    ]), 'HTML');
                    return;
                }

                $rxPemTableOk = true;
                try {
                    $rxPemCheck = $pdo->query("SHOW TABLES LIKE 'premium_emojis'");
                    $rxPemTableOk = ($rxPemCheck && $rxPemCheck->fetchColumn() !== false);
                } catch (\Throwable $rxPemTblErr) { $rxPemTableOk = false; }
                if (!$rxPemTableOk) {
                    \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ <b>جدول دیتابیس آماده نیست</b>\n\nقبل از این، باید <code>table.php</code> را در مرورگر اجرا کنید.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                    return;
                }

                $rxPemCid = \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiExtractCustomId($rxPemMsg);

                if ($rxPemCid === '') {
                    $rxPemDiag = '';
                    $rxPemSentText = trim((string)($rxPemMsg['text'] ?? $rxPemMsg['caption'] ?? ''));
                    $rxPemStkType = is_array($rxPemMsg['sticker'] ?? null) ? (string)($rxPemMsg['sticker']['type'] ?? '') : '';
                    if ($rxPemStkType !== '' && $rxPemStkType !== 'custom_emoji') {
                        $rxPemDiag = "🔎 شما یک <b>استیکر معمولی</b> فرستادید (نوع آن custom_emoji نیست).";
                    } elseif ($rxPemSentText !== '' && ctype_digit($rxPemSentText)) {
                        $rxPemDiag = "🔎 آیدی عددی نامعتبر است (طول باید بین ۸ تا ۳۰ رقم باشد).";
                    } elseif ($rxPemSentText !== '' && mb_strlen($rxPemSentText, 'UTF-8') <= 4) {
                        $rxPemDiag = "🔎 شما یک <b>ایموجی عادی</b> فرستادید: <b>" . htmlspecialchars($rxPemSentText, ENT_QUOTES, 'UTF-8') . "</b>\nاین فاقد متادیتای پرمیوم است.";
                    }
                    if ($rxPemDiag !== '') { $rxPemDiag .= "\n\n"; }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ایموجی پرمیوم یافت نشد.\n\n{$rxPemDiag}📌 لطفاً <b>ایموجی پرمیوم</b> را برای ایموجی پایه «{$rxPemBase}» ارسال کنید.\n\n💡 یا اگر آیدی عددی پرمیوم را دارید، آن را پیست کنید.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                    return;
                }

                $rxPemNow = time();
                $rxPemAlreadyExists = false;
                $rxPemExistStmt = $pdo->prepare("SELECT id, emoji FROM premium_emojis WHERE custom_emoji_id = :c");
                $rxPemExistStmt->execute([':c' => $rxPemCid]);
                while ($rxPemExistRow = $rxPemExistStmt->fetch(PDO::FETCH_ASSOC)) {
                    if ((string)$rxPemExistRow['emoji'] === $rxPemBase) {
                        $rxPemAlreadyExists = true;
                        break;
                    }
                }
                if (!$rxPemAlreadyExists) {
                    try {
                        $rxPemIns = $pdo->prepare("INSERT INTO premium_emojis (emoji, custom_emoji_id, created_at, updated_at) VALUES (:e, :c, :t1, :t2)");
                        $rxPemIns->execute([':e' => $rxPemBase, ':c' => $rxPemCid, ':t1' => $rxPemNow, ':t2' => $rxPemNow]);
                    } catch (\Throwable $rxPemInsErr) {
                        if (!\Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiIsDuplicateError($rxPemInsErr)) {
                            throw $rxPemInsErr;
                        }
                        $rxPemAlreadyExists = true;
                    }
                }
                if ($rxPemAlreadyExists) {
                    try {
                        $rxPemTouch = $pdo->prepare("UPDATE premium_emojis SET updated_at = :t WHERE emoji = :e AND custom_emoji_id = :c");
                        $rxPemTouch->execute([':t' => $rxPemNow, ':e' => $rxPemBase, ':c' => $rxPemCid]);
                    } catch (\Throwable $rxPemTouchErr) {  }
                }
                if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);

                if ($rxPemAlreadyExists) {
                    $rxPemSuccessMsg = "ℹ️ <b>این ایموجی پرمیوم با همین آیدی قبلاً ثبت شده بود</b>\n\n"
                        . "• ایموجی پایه: {$rxPemBase}\n"
                        . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                        . "هیچ ردیف تکراری اضافه نشد.";
                } else {
                    $rxPemSuccessMsg = "✅ <b>ایموجی پرمیوم جدید با موفقیت اضافه شد!</b>\n\n"
                        . "• ایموجی پایه: {$rxPemBase}\n"
                        . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                        . "✨ از این لحظه، در پیام‌های ربات «{$rxPemBase}» به نسخه پرمیوم تبدیل می‌شود.\n\n"
                        . "💡 می‌توانید برای همین «{$rxPemBase}» آیدی‌های پرمیوم بیشتری هم اضافه کنید — جدیدترین آیدی به‌صورت فعال استفاده می‌شود و قبلی‌ها در لیست باقی می‌مانند.";
                }
                if ($rxPemMode === 'batch') {
                    $rxPemButtons = [
                        [['text' => "➕ ادامه افزودن", 'callback_data' => "premium_emoji_batch_continue"]],
                        [['text' => "✅ تایید و پایان", 'callback_data' => "premium_emoji_batch_end"]],
                    ];
                } else {
                    $rxPemButtons = [
                        [['text' => "➕ افزودن ایموجی دیگر", 'callback_data' => "premium_emoji_add"]],
                        [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
                    ];
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxPemSuccessMsg, json_encode(['inline_keyboard' => $rxPemButtons]), 'HTML');
            } catch (\Throwable $rxPemErr) {
                @error_log('[premium_emoji_get_id] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ذخیره ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
                ]), 'HTML');
                return;
            }
            break;
        case 7:
            if (!isset($update['message']) || !is_array($update['message'])) { return; }
            try {
                $rxPemMsg = $update['message'];
                $rxPemStored = (string)($user['Processing_value'] ?? '');
                $rxPemRowId = (int)$rxPemStored;
                $rxPemEditPage = 1;
                if (preg_match('/^\d+:(\d+)$/', $rxPemStored, $rxPemPageMatch)) {
                    $rxPemEditPage = max(1, (int)$rxPemPageMatch[1]);
                }
                $rxPemSettingsCb = ($rxPemEditPage > 1) ? "premium_emoji_settings_{$rxPemEditPage}" : "premium_emoji_settings";
                $rxPemBackKb = json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت به لیست", 'callback_data' => $rxPemSettingsCb]]]
                ]);
                $rxPemRow = null;
                if ($rxPemRowId > 0) {
                    $rxPemRowStmt = $pdo->prepare("SELECT id, emoji, custom_emoji_id FROM premium_emojis WHERE id = :id LIMIT 1");
                    $rxPemRowStmt->execute([':id' => $rxPemRowId]);
                    $rxPemRow = $rxPemRowStmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!is_array($rxPemRow)) {
                    \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا: ردیف ایموجی یافت نشد. ممکن است حذف شده باشد.", $rxPemBackKb, 'HTML');
                    return;
                }

                $rxPemCid = \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiExtractCustomId($rxPemMsg);
                if ($rxPemCid === '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیدی ایموجی پرمیوم یافت نشد.\n\n📌 لطفاً <b>ایموجی پرمیوم</b> را ارسال کنید یا آیدی عددی را وارد نمایید.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => $rxPemSettingsCb]]]
                    ]), 'HTML');
                    return;
                }

                $rxPemRowEmoji = (string)$rxPemRow['emoji'];
                if ((string)$rxPemRow['custom_emoji_id'] !== $rxPemCid) {
                    $rxPemDupStmt = $pdo->prepare("SELECT id, emoji FROM premium_emojis WHERE custom_emoji_id = :c AND id <> :id");
                    $rxPemDupStmt->execute([':c' => $rxPemCid, ':id' => $rxPemRowId]);
                    while ($rxPemDupRow = $rxPemDupStmt->fetch(PDO::FETCH_ASSOC)) {
                        if ((string)$rxPemDupRow['emoji'] === $rxPemRowEmoji) {
                            \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "ℹ️ این آیدی پرمیوم برای ایموجی {$rxPemRowEmoji} در ردیف دیگری ثبت شده است و تغییری اعمال نشد.\n\n🆔 <code>{$rxPemCid}</code>", $rxPemBackKb, 'HTML');
                            return;
                        }
                    }
                    try {
                        $rxPemStmt = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE id = :id");
                        $rxPemStmt->execute([':id' => $rxPemRowId, ':c' => $rxPemCid, ':t' => time()]);
                    } catch (\Throwable $rxPemUpdErr) {
                        if (!\Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiIsDuplicateError($rxPemUpdErr)) {
                            throw $rxPemUpdErr;
                        }
                        \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "ℹ️ این آیدی پرمیوم برای ایموجی {$rxPemRowEmoji} در ردیف دیگری ثبت شده است و تغییری اعمال نشد.\n\n🆔 <code>{$rxPemCid}</code>", $rxPemBackKb, 'HTML');
                        return;
                    }
                    if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                }
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ایموجی پرمیوم به‌روزرسانی شد.\n\n🆔 <code>{$rxPemCid}</code>", $rxPemBackKb, 'HTML');
            } catch (\Throwable $rxPemEditErr) {
                @error_log('[premium_emoji_edit_id] EXCEPTION: ' . $rxPemEditErr->getMessage() . ' @ ' . $rxPemEditErr->getFile() . ':' . $rxPemEditErr->getLine());
                \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiResetState($from_id);
                $rxPemErrMsg = htmlspecialchars($rxPemEditErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ویرایش ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => $rxPemSettingsCb]]]
                ]), 'HTML');
                return;
            }
            break;
        }

        return true;
    }
}
