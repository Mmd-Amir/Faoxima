<?php

namespace Faoxima\Admin\Telegram;

use PDO;
use Throwable;

final class ChannelNavigation
{
    public static function rx_normalize_channel_chat_id($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^-\d{5,}$/', $value)) {
            return $value;
        }
        if (preg_match('/^@[A-Za-z][A-Za-z0-9_]{3,31}$/', $value)) {
            return $value;
        }
        if (preg_match('~^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog)/(?:s/)?([A-Za-z][A-Za-z0-9_]{3,31})/?(?:\?.*)?$~i', $value, $m)
            && strtolower($m[1]) !== 'joinchat') {
            return '@' . $m[1];
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $value)) {
            return '@' . $value;
        }
        return '';
    }

    public static function rx_channel_member_state($channel, $userId)
    {
        $chatId = self::rx_normalize_channel_chat_id($channel);
        $response = null;
        if ($chatId !== '') {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $response = telegram('getChatMember', [
                    'chat_id' => $chatId,
                    'user_id' => $userId
                ]);
                if (is_array($response) && !empty($response['ok'])) {
                    $status = (string) ($response['result']['status'] ?? '');
                    if (in_array($status, ['member', 'creator', 'administrator'], true)) {
                        return 'MEMBER';
                    }
                    if ($status === 'restricted') {
                        return !empty($response['result']['is_member']) ? 'MEMBER' : 'NOT_MEMBER';
                    }
                    if (in_array($status, ['left', 'kicked'], true)) {
                        return 'NOT_MEMBER';
                    }
                    $response = ['ok' => false, 'error_code' => 0, 'description' => 'unexpected member status: ' . $status];
                    break;
                }
                $errorCode = is_array($response) ? (int) ($response['error_code'] ?? 0) : 0;
                $retryAfter = is_array($response) ? (int) ($response['parameters']['retry_after'] ?? 0) : 0;
                if ($attempt === 1 && $errorCode === 429 && $retryAfter <= 2) {
                    sleep(max(1, $retryAfter));
                    continue;
                }
                if ($attempt === 1 && $errorCode >= 500) {
                    usleep(500000);
                    continue;
                }
                break;
            }
        } else {
            $response = ['ok' => false, 'error_code' => 0, 'description' => 'invalid channel identifier'];
        }
        $errorCode = is_array($response) ? (int) ($response['error_code'] ?? 0) : 0;
        $description = is_array($response) ? (string) ($response['description'] ?? '') : 'empty response';
        $parameters = is_array($response) && isset($response['parameters']) ? $response['parameters'] : null;
        $logContext = [
            'status' => $errorCode,
            'body' => (string) $channel . '|' . $description,
            'channel' => (string) $channel,
            'chat_id' => $chatId,
            'user_id' => (string) $userId,
            'error_code' => $errorCode,
            'description' => $description,
            'retry_after' => is_array($parameters) ? (int) ($parameters['retry_after'] ?? 0) : 0,
            'parameters' => $parameters !== null ? json_encode($parameters) : '',
        ];
        if (function_exists('rx_log_event')) {
            rx_log_event('CHANNEL_CHECK_FAILED', 'getChatMember did not return a usable result', $logContext);
        } else {
            error_log('[CHANNEL_CHECK_FAILED] ' . json_encode($logContext));
        }
        return 'CHECK_FAILED';
    }

    public static function channel(array $id_channel, &$check_failed = null)
    {
        global $from_id;
        $missing = self::channelForUser($id_channel, $from_id, $check_failed);
        return func_num_args() < 2 ? array_merge($missing, $check_failed) : $missing;
    }

    public static function channelForUser(array $channels, $fromId, &$checkFailed): array
    {
        $channel_link = [];
        $checkFailed = [];
        foreach ($channels as $channel) {
            $channel = trim((string) $channel);
            if ($channel === '') {
                continue;
            }
            $state = self::rx_channel_member_state($channel, $fromId);
            if ($state === 'NOT_MEMBER') {
                $channel_link[] = $channel;
            } elseif ($state === 'CHECK_FAILED') {
                $checkFailed[] = $channel;
            }
        }
        return $channel_link;
    }

    public static function rx_find_channel_from_input($rawText, $datain, PDO $pdo, &$debug = []): ?array
    {
        $debug = [
            'raw_text' => $rawText,
            'datain' => $datain,
            'registered_channels' => [],
            'matched' => false,
            'matched_channel_id' => null,
            'matched_rule' => '',
            'reason' => '',
        ];

        if (isset($datain) && preg_match('/^ch_manage_(\d+)$/', (string)$datain, $m)) {
            $id = (int)$m[1];
            try {
                $stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $chan = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($chan && !empty($chan['id'])) {
                    $debug['matched'] = true;
                    $debug['matched_channel_id'] = (int)$chan['id'];
                    $debug['matched_rule'] = 'callback_query: ch_manage_' . $id;
                    $debug['reason'] = 'Direct callback match';
                    return $chan;
                }
            } catch (Throwable $e) {
                $debug['reason'] = 'DB error in callback find: ' . $e->getMessage();
                return null;
            }
        }

        $text = trim((string)$rawText);
        if ($text === '') {
            $debug['reason'] = 'Input text is empty and no callback data';
            return null;
        }

        $navButtons = [
            "➕ افزودن کانال",
            "➕ اضافه کردن کانال",
            "🏠 بازگشت به منوی مدیریت",
            "بازگشت به منوی مدیریت 🏠",
            "بازگشت به منوی مدیریت",
            "منوی مدیریت 🏠",
            "🏠 منوی مدیریت",
            "منوی مدیریت",
            "▶️ بازگشت به منوی قبل",
            "بازگشت به منوی قبل ▶️",
            "🔙 بازگشت به منوی قبل",
            "بازگشت به منوی قبل ⬅️",
            "بازگشت به منوی قبل",
            "🔙 بازگشت به لیست کانال‌ها",
            "بازگشت به لیست کانال‌ها",
            "📯 تنظیمات کانال",
            "📣 گزارشات ربات",
            "📢 کانال و اطلاع‌رسانی",
            "❌ حذف کانال",
            "✏️ ویرایش عنوان دکمه",
            "🔗 ویرایش لینک عضویت",
            "🆔 ویرایش آیدی کانال",
        ];
        if (in_array($text, $navButtons, true)) {
            $debug['reason'] = 'Input is reserved navigation button: ' . $text;
            return null;
        }

        $stripBiDi = static function (string $str): string {
            return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200C}\x{200D}]/u', '', $str);
        };

        $stripEmoji = static function (string $str): string {
            $str = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200C}]/u', '', $str);
            return trim($str);
        };

        $cleanBiDi = $stripBiDi($text);
        $cleanNoEmoji = $stripEmoji($cleanBiDi);

        $channels = [];
        try {
            $stmt = $pdo->prepare("SELECT * FROM channels ORDER BY id ASC");
            $stmt->execute();
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $debug['reason'] = 'DB query channels error: ' . $e->getMessage();
            return null;
        }

        if (!is_array($channels) || empty($channels)) {
            $debug['reason'] = 'channels table is empty';
            return null;
        }

        foreach ($channels as $chan) {
            $cId = (int)$chan['id'];
            $rem = trim((string)($chan['remark'] ?? ''));
            $lnk = trim((string)($chan['link'] ?? ''));
            $btnTitle = $rem !== '' ? $rem : ($lnk !== '' ? $lnk : 'کانال ' . $cId);

            $expectedStrings = [
                "📢 " . $btnTitle,
                $btnTitle . " 📢",
                "📢" . $btnTitle,
                $btnTitle . "📢",
                $btnTitle,
                $rem,
                $lnk,
                ltrim($lnk, '@'),
                "کانال " . $cId,
                (string)$cId,
            ];
            if ($lnk !== '') {
                $expectedStrings[] = str_ireplace(['https://t.me/', 'http://t.me/', 't.me/'], '', $lnk);
            }
            $cleanExpected = array_values(array_unique(array_filter(array_map('trim', $expectedStrings))));
            $debug['registered_channels'][$cId] = [
                'id' => $cId,
                'remark' => $rem,
                'link' => $lnk,
                'btn_title' => $btnTitle,
                'match_candidates' => $cleanExpected,
            ];

            foreach ($cleanExpected as $cand) {
                if ($cand === '') continue;
                if ($text === $cand || $cleanBiDi === $cand) {
                    $debug['matched'] = true;
                    $debug['matched_channel_id'] = $cId;
                    $debug['matched_rule'] = 'exact_match: ' . $cand;
                    $debug['reason'] = 'Matched exact candidate [' . $cand . ']';
                    return $chan;
                }
            }

            $candNoEmoji = $stripEmoji($stripBiDi($btnTitle));
            $remNoEmoji = $stripEmoji($stripBiDi($rem));
            $lnkNoEmoji = $stripEmoji($stripBiDi($lnk));

            if ($cleanNoEmoji !== '') {
                $lowerClean = function_exists('mb_strtolower') ? mb_strtolower($cleanNoEmoji, 'UTF-8') : strtolower($cleanNoEmoji);
                if ($candNoEmoji !== '') {
                    $lowerCand = function_exists('mb_strtolower') ? mb_strtolower($candNoEmoji, 'UTF-8') : strtolower($candNoEmoji);
                    if ($cleanNoEmoji === $candNoEmoji || $lowerClean === $lowerCand) {
                        $debug['matched'] = true;
                        $debug['matched_channel_id'] = $cId;
                        $debug['matched_rule'] = 'no_emoji_match: btnTitle ' . $candNoEmoji;
                        $debug['reason'] = 'Matched candidate title without emoji';
                        return $chan;
                    }
                }
                if ($remNoEmoji !== '') {
                    $lowerRem = function_exists('mb_strtolower') ? mb_strtolower($remNoEmoji, 'UTF-8') : strtolower($remNoEmoji);
                    if ($cleanNoEmoji === $remNoEmoji || $lowerClean === $lowerRem) {
                        $debug['matched'] = true;
                        $debug['matched_channel_id'] = $cId;
                        $debug['matched_rule'] = 'no_emoji_match: remark ' . $remNoEmoji;
                        $debug['reason'] = 'Matched remark without emoji';
                        return $chan;
                    }
                }
                if ($lnkNoEmoji !== '') {
                    $lowerLnk = function_exists('mb_strtolower') ? mb_strtolower($lnkNoEmoji, 'UTF-8') : strtolower($lnkNoEmoji);
                    $lowerLnkBare = ltrim($lowerLnk, '@');
                    if ($cleanNoEmoji === $lnkNoEmoji || $lowerClean === $lowerLnk || $lowerClean === $lowerLnkBare) {
                        $debug['matched'] = true;
                        $debug['matched_channel_id'] = $cId;
                        $debug['matched_rule'] = 'no_emoji_match: link ' . $lnkNoEmoji;
                        $debug['reason'] = 'Matched link without emoji';
                        return $chan;
                    }
                }
                if ($cleanNoEmoji === 'کانال ' . $cId || $cleanNoEmoji === (string)$cId) {
                    $debug['matched'] = true;
                    $debug['matched_channel_id'] = $cId;
                    $debug['matched_rule'] = 'id_match: ' . $cId;
                    $debug['reason'] = 'Matched channel ID string';
                    return $chan;
                }
            }

            if ($rem !== '' && (function_exists('mb_strlen') ? mb_strlen($rem, 'UTF-8') : strlen($rem)) >= 2) {
                $hasSubBiDi = function_exists('mb_stripos') ? (mb_stripos($cleanBiDi, $rem, 0, 'UTF-8') !== false) : (stripos($cleanBiDi, $rem) !== false);
                $hasSubRaw = function_exists('mb_stripos') ? (mb_stripos($text, $rem, 0, 'UTF-8') !== false) : (stripos($text, $rem) !== false);
                if ($hasSubBiDi || $hasSubRaw) {
                    $debug['matched'] = true;
                    $debug['matched_channel_id'] = $cId;
                    $debug['matched_rule'] = 'substring_remark: ' . $rem;
                    $debug['reason'] = 'Matched remark substring';
                    return $chan;
                }
            }

            if ($lnk !== '' && (function_exists('mb_strlen') ? mb_strlen($lnk, 'UTF-8') : strlen($lnk)) >= 3) {
                $cleanLnk = ltrim($lnk, '@');
                $hasSubBiDi = function_exists('mb_stripos') ? (mb_stripos($cleanBiDi, $cleanLnk, 0, 'UTF-8') !== false) : (stripos($cleanBiDi, $cleanLnk) !== false);
                $hasSubRaw = function_exists('mb_stripos') ? (mb_stripos($text, $cleanLnk, 0, 'UTF-8') !== false) : (stripos($text, $cleanLnk) !== false);
                if ($hasSubBiDi || $hasSubRaw) {
                    $debug['matched'] = true;
                    $debug['matched_channel_id'] = $cId;
                    $debug['matched_rule'] = 'substring_link: ' . $cleanLnk;
                    $debug['reason'] = 'Matched link substring';
                    return $chan;
                }
            }
        }

        $debug['reason'] = 'Text [' . $text . '] did not match any of the ' . count($channels) . ' registered channels';
        return null;
    }

    public static function rx_render_channel_manage($chanId, $from_id, $prefixMsg = '')
    {
        global $textbotlang, $user, $channelkeyboard;
        $chanId = (int)$chanId;
        $chan = null;
        if ($chanId > 0 && \Faoxima\Core\Database\Db::runtimeReady()) {
            $chan = \Faoxima\Core\Database\Db::select("channels", "*", "id", $chanId, "select", ['cache' => false]);
        }
        if ($chan && !empty($chan['id'])) {
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", (string)$chanId, "id", $from_id);
            }
            if (isset($user) && is_array($user)) {
                $user['Processing_value'] = (string)$chanId;
            }
            if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
                \Faoxima\Telegram\Navigation\Steps::step('channel_manage', $from_id);
            }
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel_manage');
            }
            $msg = "📋 <b>مدیریت کانال:</b> " . htmlspecialchars($chan['remark'] ?? '') . "\n\n"
                . "🏷 <b>عنوان دکمه:</b> <code>" . htmlspecialchars($chan['remark'] ?? '') . "</code>\n"
                . "🔗 <b>لینک عضویت:</b> " . htmlspecialchars($chan['linkjoin'] ?? '') . "\n"
                . "🆔 <b>آیدی کانال:</b> <code>" . htmlspecialchars($chan['link'] ?? '') . "</code>\n\n"
                . "یک گزینه را انتخاب کنید:";
            if ($prefixMsg !== '') {
                $msg = $prefixMsg . "\n\n" . $msg;
            }
            $kb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_manage_keyboard')
                ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_manage_keyboard($chan['id'], true)
                : (method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard);
            if (method_exists(\Faoxima\Telegram\Support\Replies::class, 'nm_adminInstantReply')) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $kb, 'HTML');
            }
            return true;
        }
        $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
        if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
            \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
        }
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
        }
        if (method_exists(\Faoxima\Telegram\Support\Replies::class, 'nm_adminInstantReply')) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کانال مورد نظر یافت نشد.", $channelkeyboard, 'HTML');
        }
        return false;
    }

    public static function rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck)
    {
        return ($adminrulecheck['rule'] ?? '') == "administrator" && empty($setting['Channel_Report']);
    }

    public static function rxAdminPanelEntryForceChannelReportSetup($from_id, $setting, $textbotlang, $backadmin)
    {
        $textreports = "📣 تنظیم گروه گزارشات ربات

در این بخش می‌توانید آیدی عددی گروه موردنظر را برای ارسال اعلان‌ها و گزارشات ربات ثبت نمایید.

آموزش تنظیم گروه:
1 ـ ابتدا یک گروه جدید ایجاد کنید یا گروه موردنظر خود را انتخاب نمایید.
2 ـ از تنظیمات گروه، قابلیت تاپیک را فعال کنید.
3 ـ ربات خود را به گروه اضافه کرده و دسترسی مدیریت موردنیاز را به آن بدهید.
4 ـ سپس داخل همان گروه، یکی از عبارت‌های «آیدی» یا «ایدی» را ارسال کنید.
5 ـ ربات، آیدی عددی گروه را برای شما نمایش می‌دهد. آیدی نمایش‌داده‌شده را کپی کرده و در این بخش برای ربات ارسال نمایید.

⚠️ توجه داشته باشید که قبل از درخواست آیدی، تاپیک گروه باید فعال شده باشد.

⛔️ برای ورود به پنل مدیریت، ابتدا باید گروه گزارشات ربات را تنظیم نمایید.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
        \Faoxima\Telegram\Navigation\Steps::step('addchannelid', $from_id);
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'addchannelid');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textreports, $backadmin, 'HTML');
    }

    public static function rxAdminNavRequiresChannelReport()
    {
        $setting = isset($GLOBALS['setting']) && is_array($GLOBALS['setting']) ? $GLOBALS['setting'] : null;
        $adminrulecheck = isset($GLOBALS['adminrulecheck']) && is_array($GLOBALS['adminrulecheck']) ? $GLOBALS['adminrulecheck'] : null;
        if ($setting === null || $adminrulecheck === null) {
            return false;
        }
        return self::rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck);
    }
}
