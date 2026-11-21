<?php

namespace Faoxima\Admin\Telegram\Handlers;

use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $from_id, $pdo, $rxPemPackMatch, $rxPemPackView, $rxPemPgMatch, $rxPemTotal, $update, $user.
 * Inherited or exported by reference: $rxPemBase.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PremiumEmojiPacks
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $from_id,
        $pdo,
        &$rxPemBase,
        $rxPemPackMatch,
        $rxPemPackView,
        $rxPemPgMatch,
        $rxPemTotal,
        $update,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $rxPemPage = isset($rxPemPgMatch[1]) ? max(1, (int)$rxPemPgMatch[1]) : 1;
            if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPanel')) {
                \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPanel($from_id, $rxPemPage);
            }
            break;
        case 2:
            $rxPemPackPanel = isset($rxPemPackMatch[1]) ? max(1, (int)$rxPemPackMatch[1]) : 1;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔗 <b>آیدی از لینک پک</b>\n\nلینک پک ایموجی تلگرام را ارسال کنید؛ برای مثال:\n<code>https://t.me/addemoji/kockrlandrandomemojipack3</code>\n\n📌 پس از دریافت لینک، آیدی عددی همه ایموجی‌های پک به‌صورت صفحه‌بندی‌شده نمایش داده می‌شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_pack_link_' . $rxPemPackPanel, $from_id);
            break;
        case 3:
            $rxPemPackViewPage = max(1, (int)$rxPemPackView[1]);
            $rxPemPackPanel = max(1, (int)$rxPemPackView[2]);
            $rxPemPackMessage = (string)($update['callback_query']['message']['text'] ?? '');
            $rxPemPackLines = explode("\n", $rxPemPackMessage);
            $rxPemPackName = \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiPackParseLink($rxPemPackLines[1] ?? '');
            if ($rxPemPackName === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ وضعیت این پک منقضی شده است. لطفاً لینک پک را دوباره ارسال کنید.", json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "🔗 دریافت آیدی از لینک پک", 'callback_data' => "premium_emoji_pack_{$rxPemPackPanel}"],
                            ['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings_{$rxPemPackPanel}"],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE), 'HTML');
            } else {
                if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPackPage')) {
                    \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPackPage($from_id, $rxPemPackName, $rxPemPackViewPage, $rxPemPackPanel);
                }
            }
            break;
        case 4:
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'برای استفاده از نسخهٔ جدید، لینک پک را دوباره ارسال کنید.',
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌟 برای مدیریت ایموجی‌های پرمیوم، روی «⚙️ تنظیمات» کنار همین دکمه بزنید یا از دکمه زیر استفاده کنید.", json_encode([
                'inline_keyboard' => [
                    [['text' => "⚙️ تنظیمات ایموجی پرمیوم", 'callback_data' => "premium_emoji_settings"]],
                    [['text' => "❌ بستن", 'callback_data' => "close_stat"]],
                ]
            ]), 'HTML');
            break;
        case 6:
            if (!empty($callback_query_id)) {
                try { telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]); } catch (\Throwable $e) {}
            }
            break;
        case 7:
            $rxPemBase = (string)($user['Processing_value'] ?? '');
            if ($rxPemBase === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا: ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
                ]), 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تأیید شد. حالا <b>ایموجی پرمیوم جدید</b> را برای [<b>{$rxPemBase}</b>] ارسال کنید.\n\n💡 آیدی فعلی پس از دریافت ایموجی جدید، جایگزین می‌شود.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_get_id', $from_id);
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌟 <b>افزودن ایموجی پرمیوم جدید</b>\n\nحالت افزودن را انتخاب کنید:", json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "1️⃣ تکی", 'callback_data' => "premium_emoji_add_single"],
                        ['text' => "📦 دسته‌ای", 'callback_data' => "premium_emoji_add_batch"],
                    ],
                    [['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]],
                ]
            ]), 'HTML');
            break;
        case 9:
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "single:", "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🌟 <b>افزودن ایموجی پرمیوم جدید</b>\n\n<b>مرحله ۱ از ۲:</b>\n📤 لطفاً ابتدا <b>ایموجی عادی (پایه)</b> را ارسال کنید.\n\nمثال: ✅ یا ❌ یا 🔥 یا 💎\n\n⏭ سپس از شما <b>ایموجی پرمیوم متناظر</b> را می‌خواهیم.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_get_char', $from_id);
            break;
        case 10:
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "batch:", "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📦 <b>افزودن دسته‌ای ایموجی پرمیوم</b>\n\n<b>مرحله ۱ از ۲:</b>\n📤 لطفاً <b>ایموجی عادی (پایه)</b> را ارسال کنید.\n\nمثال: ✅ یا ❌ یا 🔥 یا 💎", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_get_char', $from_id);
            break;
        case 11:
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "batch:", "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📦 <b>ادامه افزودن دسته‌ای</b>\n\n📤 لطفاً <b>ایموجی عادی (پایه)</b> بعدی را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('premium_emoji_get_char', $from_id);
            break;
        case 12:
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPanel')) {
                \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPanel($from_id, 1);
            }
            break;
        case 13:
            try {
                $rxPemCountRow = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis")->fetch(PDO::FETCH_ASSOC);
                $rxPemTotal = (int)($rxPemCountRow['c'] ?? 0);
            } catch (\Throwable $e) { $rxPemTotal = 0; }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ <b>حذف همگانی ایموجی‌ها</b>\n\nتعداد کل ایموجی‌های ذخیره‌شده: <b>{$rxPemTotal}</b> عدد\n\n❗️ این عملیات <b>برگشت‌ناپذیر</b> است و تمام ردیف‌های جدول حذف می‌شوند.\n\nآیا مطمئن هستید؟", json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ بله، همه را حذف کن", 'callback_data' => "premium_emoji_del_all_confirm"],
                        ['text' => "❌ لغو", 'callback_data' => "premium_emoji_settings"],
                    ],
                ]
            ]), 'HTML');
            break;
        case 14:
            try {
                $pdo->exec("DELETE FROM premium_emojis");
                if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🗑 تمام ایموجی‌های پرمیوم حذف شدند.", json_encode([
                    'inline_keyboard' => [[['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]]]
                ]), 'HTML');
            } catch (\Throwable $e) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در حذف همگانی: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), null, 'HTML');
            }
            break;
        case 15:
            try {
                $rxPemLockStmt = $pdo->query("SELECT GET_LOCK('rx_pem_scan', 0)");
                $rxPemLockGot  = $rxPemLockStmt ? (int)$rxPemLockStmt->fetchColumn() : 0;
                if (!$rxPemLockGot) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⏳ اسکن دیگری در حال اجرا است. لطفاً چند ثانیه صبر کنید و دوباره امتحان کنید.", null, 'HTML');
                    return;
                }

                try { $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (\Throwable $rxPemCharsetErr) {}

                try {
                    $rxPemColl = '';
                    $rxPemCollSt = $pdo->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='premium_emojis' AND COLUMN_NAME='emoji'");
                    if ($rxPemCollSt) { $rxPemColl = (string)$rxPemCollSt->fetchColumn(); }
                    if ($rxPemColl !== 'utf8mb4_bin') {
                        try { $pdo->exec("ALTER TABLE premium_emojis DROP INDEX uniq_emoji_cid"); } catch (\Throwable $_) {}
                        $pdo->exec("ALTER TABLE premium_emojis MODIFY emoji VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
                        $pdo->exec("ALTER TABLE premium_emojis MODIFY custom_emoji_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''");
                        try { $pdo->exec("ALTER TABLE premium_emojis ADD UNIQUE KEY uniq_emoji_cid (emoji, custom_emoji_id)"); } catch (\Throwable $_) {}
                    }
                } catch (\Throwable $rxPemAlterErr) {}

                try { $pdo->exec("DELETE FROM premium_emojis WHERE emoji = '' OR emoji IS NULL"); } catch (\Throwable $rxPemCleanErr) {}

                $rxPemScanText = '';

                $rxPemScanStmt = $pdo->query("SELECT text FROM textbot");
                if ($rxPemScanStmt) {
                    while ($rxPemScanRow = $rxPemScanStmt->fetch(PDO::FETCH_ASSOC)) {
                        $rxPemScanText .= ' ' . (string)($rxPemScanRow['text'] ?? '');
                    }
                }

                $rxPemScanDirs = [
                    REFACTORED_LEGACY_ROOT . '/src/Core',
                    REFACTORED_LEGACY_ROOT . '/src/Orders',
                    REFACTORED_LEGACY_ROOT . '/src/Payments',
                    REFACTORED_LEGACY_ROOT . '/src/Services',
                    REFACTORED_LEGACY_ROOT . '/src/Wallet',
                    REFACTORED_LEGACY_ROOT . '/src/Telegram',
                    REFACTORED_LEGACY_ROOT . '/src/Admin',
                ];
                $rxPemScannedFiles = [];
                foreach ($rxPemScanDirs as $rxPemDir) {
                    if (!is_dir($rxPemDir)) { continue; }
                    $rxPemIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rxPemDir, FilesystemIterator::SKIP_DOTS));
                    foreach ($rxPemIter as $rxPemFile) {
                        $rxPemExt = $rxPemFile->getExtension();
                        if ($rxPemExt !== 'php' && $rxPemExt !== 'json') { continue; }
                        $rxPemPath = $rxPemFile->getRealPath();
                        if (isset($rxPemScannedFiles[$rxPemPath])) { continue; }
                        if ($rxPemFile->getSize() > 2 * 1024 * 1024) { continue; }
                        if (strpos($rxPemPath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) { continue; }
                        $rxPemScannedFiles[$rxPemPath] = true;
                        $rxPemContent = @file_get_contents($rxPemPath);
                        if ($rxPemContent !== false) {
                            if ($rxPemExt === 'json') {
                                $rxPemDecoded = json_decode($rxPemContent, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $rxPemContent = json_encode($rxPemDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                }
                            }
                            $rxPemScanText .= ' ' . $rxPemContent;
                        }
                    }
                }

                $rxPemJsonPaths = [
                    REFACTORED_LEGACY_ROOT . '/text.json',
                    REFACTORED_LEGACY_ROOT . '/vpnbot/Default/text.json',
                    REFACTORED_LEGACY_ROOT . '/vpnbot/update/text.json',
                ];
                foreach ($rxPemJsonPaths as $rxPemJp) {
                    $rxPemJp = realpath($rxPemJp);
                    if ($rxPemJp && is_file($rxPemJp) && !isset($rxPemScannedFiles[$rxPemJp])) {
                        $rxPemScannedFiles[$rxPemJp] = true;
                        $rxPemC = @file_get_contents($rxPemJp);
                        if ($rxPemC !== false) {
                            $rxPemDecoded = json_decode($rxPemC, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $rxPemC = json_encode($rxPemDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                            $rxPemScanText .= ' ' . $rxPemC;
                        }
                    }
                }

                $rxPemFound = rxPremiumEmojiExtractSources($rxPemScanText);

                $rxPemDbCountBefore = 0;
                $rxPemDbCountStmt = $pdo->query("SELECT COUNT(*) FROM premium_emojis");
                if ($rxPemDbCountStmt) {
                    $rxPemDbCountBefore = (int)$rxPemDbCountStmt->fetchColumn();
                }

                if (empty($rxPemFound)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "ℹ️ <b>شناسایی خودکار</b>\n\nهیچ ایموجی ساده‌ای در متون ربات یافت نشد.", json_encode([
                        'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                } else {
                    $rxPemNow = time();
                    $rxPemInserted = 0;
                    $rxPemSkipped  = 0;
                    $rxPemInsStmt = $pdo->prepare("INSERT IGNORE INTO premium_emojis (emoji, custom_emoji_id, created_at, updated_at) VALUES (CONVERT(UNHEX(?) USING utf8mb4), '', ?, ?)");
                    foreach ($rxPemFound as $rxPemEm) {
                        try {
                            $rxPemInsStmt->execute([bin2hex($rxPemEm), $rxPemNow, $rxPemNow]);
                            if ($rxPemInsStmt->rowCount() > 0) {
                                $rxPemInserted++;
                            } else {
                                $rxPemSkipped++;
                            }
                        } catch (\Throwable $rxPemSkipErr) {}
                    }

                    $rxPemDbCountAfter = 0;
                    $rxPemAfterStmt = $pdo->query("SELECT COUNT(*) FROM premium_emojis");
                    if ($rxPemAfterStmt) { $rxPemDbCountAfter = (int)$rxPemAfterStmt->fetchColumn(); }
                    if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
                    $rxPemMsg = "🔍 <b>شناسایی خودکار انجام شد</b>\n\n"
                        . "• کل ایموجی‌های ساده در متون: <b>" . count($rxPemFound) . "</b>\n"
                        . "• قبلاً در دیتابیس: <b>{$rxPemDbCountBefore}</b>\n"
                        . "• جدید اضافه‌شده: <b>{$rxPemInserted}</b>\n"
                        . "• مجموع در دیتابیس: <b>{$rxPemDbCountAfter}</b>";
                    if ($rxPemSkipped > 0) {
                        $rxPemMsg .= "\n• تکراری (نادیده): <b>{$rxPemSkipped}</b>";
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxPemMsg, json_encode([
                        'inline_keyboard' => [[['text' => "🔙 مشاهده لیست", 'callback_data' => "premium_emoji_settings"]]]
                    ]), 'HTML');
                }
                try { $pdo->exec("SELECT RELEASE_LOCK('rx_pem_scan')"); } catch (\Throwable $_) {}
            } catch (\Throwable $rxPemScanErr) {
                try { $pdo->exec("SELECT RELEASE_LOCK('rx_pem_scan')"); } catch (\Throwable $_) {}
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطا در شناسایی خودکار: " . htmlspecialchars($rxPemScanErr->getMessage(), ENT_QUOTES, 'UTF-8'), null, 'HTML');
            }
            break;
        }

        return true;
    }
}
