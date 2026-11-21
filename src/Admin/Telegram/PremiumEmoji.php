<?php

namespace Faoxima\Admin\Telegram;

use PDO;
use PDOException;
use Throwable;

final class PremiumEmoji
{
    public static function rxPremiumEmojiPanelView(array $rows, int $page, string $statusVal): array {
    $pageSize = 10;
    $total = count($rows);
    $totalPages = max(1, (int)ceil($total / $pageSize));
    if ($page > $totalPages) { $page = $totalPages; }
    $sliceStart = ($page - 1) * $pageSize;
    $pageItems = array_slice($rows, $sliceStart, $pageSize);

    $statusText = ($statusVal === '1') ? "☑️ فعال" : "🚫 غیرفعال";

    $msg  = "🌟 <b>تنظیمات ایموجی پرمیوم</b>\n\n";
    $msg .= "وضعیت کلی: <b>{$statusText}</b>\n";
    $msg .= "تعداد ثبت‌شده: <b>{$total}</b>";
    if ($totalPages > 1) {
        $msg .= "  ·  📄 صفحه <b>{$page}</b> از <b>{$totalPages}</b>";
    }
    $msg .= "\n\n";
    if (empty($rows)) {
        $msg .= "📭 هنوز هیچ ایموجی‌ای ثبت نشده است.\n\n";
        $msg .= "برای افزودن، روی دکمه «➕ افزودن ایموجی جدید» بزنید.";
    } else {
        $msg .= "برای ویرایش/حذف، از دکمه‌های زیر استفاده کنید.";
    }

    $kb = ['inline_keyboard' => []];
    foreach ($pageItems as $it) {
        $em = (string)$it['emoji'];
        $id = (int)$it['id'];
        $isValid = function_exists('isValidPremiumEmojiSource') ? isValidPremiumEmojiSource($em) : true;
        $btnLabel = $isValid ? $em : "⚠️ نامعتبر: " . mb_substr($em, 0, 15, 'UTF-8');

        $kb['inline_keyboard'][] = [
            ['text' => $btnLabel, 'callback_data' => "premium_emoji_noop"],
            ['text' => "✏️ ویرایش", 'callback_data' => "premium_emoji_edit_{$id}_{$page}"],
            ['text' => "🗑 حذف", 'callback_data' => "premium_emoji_del_{$id}"],
        ];
    }
    if ($totalPages > 1) {
        $prevCb = ($page > 1) ? "premium_emoji_settings_" . ($page - 1) : "premium_emoji_noop";
        $prevTxt = ($page > 1) ? "« قبل" : "▫️";
        $nextCb = ($page < $totalPages) ? "premium_emoji_settings_" . ($page + 1) : "premium_emoji_noop";
        $nextTxt = ($page < $totalPages) ? "بعد »" : "▫️";
        $kb['inline_keyboard'][] = [
            ['text' => $prevTxt, 'callback_data' => $prevCb],
            ['text' => "📄 {$page} / {$totalPages}", 'callback_data' => "premium_emoji_noop"],
            ['text' => $nextTxt, 'callback_data' => $nextCb],
        ];
    }
    $kb['inline_keyboard'][] = [
        ['text' => "➕ افزودن ایموجی جدید", 'callback_data' => "premium_emoji_add"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔗 آیدی از لینک پک", 'callback_data' => "premium_emoji_pack_{$page}"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔍 شناسایی خودکار ایموجی‌های ساده", 'callback_data' => "premium_emoji_scan"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🗑 حذف همگانی همه ایموجی‌ها", 'callback_data' => "premium_emoji_del_all"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => ($statusVal === '1' ? "🚫 خاموش کردن قابلیت" : "☑️ روشن کردن قابلیت"),
         'callback_data' => "editstsuts-premiumemoji-{$statusVal}"],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => "🔙 بازگشت", 'callback_data' => "featcat_main"],
        ['text' => "🔄 رفرش", 'callback_data' => "premium_emoji_settings_{$page}"],
        ['text' => "❌ بستن", 'callback_data' => "close_stat"],
    ];
    return [$msg, $kb];
}

    public static function rxRenderPremiumEmojiPanel($from_id, $page = 1) {
    global $pdo, $setting;
    $page = max(1, (int)$page);

    $rows = [];
    try {
        $stmt = $pdo->query("SELECT id, emoji, custom_emoji_id FROM premium_emojis ORDER BY id ASC");
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $rows[] = $r; }
        }
    } catch (Throwable $e) { $rows = []; }

    [$msg, $kb] = self::rxPremiumEmojiPanelView($rows, $page, (string)($setting['premium_emoji_status'] ?? '0'));
    global $callback_query_id, $message_id;
    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'premium_emoji');
    }
    $kbJson = json_encode($kb, JSON_UNESCAPED_UNICODE);
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $delivered = false;

    if ($isCallback && function_exists('Editmessagetext')) {
        try {
            $editResult = Editmessagetext($from_id, $message_id, $msg, $kbJson, 'HTML');
            if (is_array($editResult) && !empty($editResult['ok'])) {
                $delivered = true;
                if (function_exists('telegram')) {
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 0,
                        ]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {
            error_log('rxRenderPremiumEmojiPanel Editmessagetext failed: ' . $e->getMessage());
        }
    }


    if (!$delivered && function_exists('telegram')) {
        if ($isCallback) {
            try {
                $rawEdit = telegram('editmessagetext', [
                    'chat_id'      => $from_id,
                    'message_id'   => $message_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
                if (is_array($rawEdit) && !empty($rawEdit['ok'])) {
                    $delivered = true;
                    try {
                        telegram('answerCallbackQuery', [
                            'callback_query_id' => $callback_query_id,
                            'cache_time'        => 0,
                        ]);
                    } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        }
        if (!$delivered) {
            if ($isCallback && function_exists('deletemessage')) {
                try { @deletemessage($from_id, $message_id); } catch (Throwable $e) {}
            }
            try {
                telegram('sendmessage', [
                    'chat_id'      => $from_id,
                    'text'         => $msg,
                    'reply_markup' => $kbJson,
                    'parse_mode'   => 'HTML',
                ]);
            } catch (Throwable $e) {
                error_log('rxRenderPremiumEmojiPanel send failed: ' . $e->getMessage());
            }
        }
    }
}

    public static function rxPremiumEmojiInputSteps() {
    $steps = ['premium_emoji_get_char', 'premium_emoji_get_id', 'premium_emoji_edit_id', 'premium_emoji_pack_link'];
    foreach ([$GLOBALS['user']['step'] ?? '', $GLOBALS['rx_pem_entry_step'] ?? ''] as $step) {
        if (preg_match('/^premium_emoji_pack_link_\d+$/', (string)$step)) { $steps[] = $step; }
    }
    return $steps;
}

    public static function rxPremiumEmojiCancelKind($text, $datain) {
    global $textbotlang;
    $cb = is_string($datain) ? trim($datain) : '';
    if ($cb !== '') {
        if (in_array($cb, ['adm_hub_main', 'backadmin', 'admin'], true)) {
            return 'admin';
        }
        if (in_array($cb, ['backmenu', 'adm_backmenu', 'premium_emoji_settings'], true)
            || preg_match('/^premium_emoji_settings_\d+$/', $cb)) {
            return 'panel';
        }
        return 'leave';
    }
    $raw = is_string($text) ? trim($text) : '';
    if ($raw === '') {
        return null;
    }
    $backAdmin = (string)($textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت');
    $backMenu  = (string)($textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل');
    if ($raw === $backAdmin) {
        return 'admin';
    }
    if ($raw === $backMenu) {
        return 'panel';
    }
    $clean = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_cleanEmojiAndSymbols') ? \Faoxima\Telegram\Support\AdminInput::rx_cleanEmojiAndSymbols($raw) : mb_strtolower($raw, 'UTF-8');
    if ($clean === '') {
        return null;
    }
    if (in_array($clean, [
        'بازگشت به منوی مدیریت', 'بازگشت به منوی اصلی', 'بازگشت به منو اصلی', 'بازگشت به خانه',
        'بازگشت به ادمین', 'بازگشت به پنل مدیریت', 'بازگشت به پنل ادمین', 'منوی مدیریت', 'منوی اصلی', 'خانه',
    ], true)) {
        return 'admin';
    }
    if (in_array($clean, [
        'لغو', 'انصراف', 'بازگشت', 'بازگشت به منوی قبل', 'بازگشت به منو قبل', 'بازگشت به قبل', 'منوی قبل', 'منو قبل',
        'cancel', '/cancel', 'back',
    ], true)) {
        return 'panel';
    }
    return null;
}

    public static function rxPremiumEmojiResetState($from_id) {
    if (\Faoxima\Core\Database\Db::runtimeReady()) {
        \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
    }
    if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
    }
}

    public static function rxPremiumEmojiIsValidBase($value) {
    if (!is_string($value)) { return false; }
    // Use the same validator as the outgoing emoji map; no Unicode letter filter.
    return function_exists('isValidPremiumEmojiSource') && isValidPremiumEmojiSource(trim($value));
}

    public static function rxPremiumEmojiExtractCustomId(array $message) {
    $fromEntities = function ($entities) {
        if (!is_array($entities)) {
            return '';
        }
        foreach ($entities as $ent) {
            if (is_array($ent) && ($ent['type'] ?? '') === 'custom_emoji' && !empty($ent['custom_emoji_id'])) {
                $cid = trim((string)$ent['custom_emoji_id']);
                if ($cid !== '' && ctype_digit($cid) && strlen($cid) <= 30) {
                    return $cid;
                }
            }
        }
        return '';
    };
    $fromSticker = function ($sticker) {
        if (is_array($sticker) && ($sticker['type'] ?? '') === 'custom_emoji' && !empty($sticker['custom_emoji_id'])) {
            $cid = trim((string)$sticker['custom_emoji_id']);
            if ($cid !== '' && ctype_digit($cid) && strlen($cid) <= 30) {
                return $cid;
            }
        }
        return '';
    };

    $cid = $fromEntities($message['entities'] ?? null);
    if ($cid === '') {
        $cid = $fromEntities($message['caption_entities'] ?? null);
    }
    if ($cid === '') {
        $cid = $fromSticker($message['sticker'] ?? null);
    }
    if ($cid === '') {
        $reply = $message['reply_to_message'] ?? null;
        if (is_array($reply)) {
            $cid = $fromEntities($reply['entities'] ?? null);
            if ($cid === '') {
                $cid = $fromEntities($reply['caption_entities'] ?? null);
            }
            if ($cid === '') {
                $cid = $fromSticker($reply['sticker'] ?? null);
            }
        }
    }
    if ($cid === '') {
        $candidate = trim((string)($message['text'] ?? $message['caption'] ?? ''));
        if ($candidate !== '' && ctype_digit($candidate) && strlen($candidate) >= 8 && strlen($candidate) <= 30) {
            $cid = $candidate;
        }
    }
    return $cid;
}

    public static function rxPremiumEmojiIsDuplicateError($e) {
    if ($e instanceof PDOException) {
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[1]) && (int)$info[1] === 1062) {
            return true;
        }
    }
    $msg = (string)$e->getMessage();
    return stripos($msg, 'Duplicate') !== false || strpos($msg, '1062') !== false;
}

    public static function rxPremiumEmojiPackParseLink($text) {
    $raw = trim((string)$text);
    if ($raw === '') { return null; }
    if (preg_match('~^(?:https?://)?(?:www\.)?(?:t|telegram)\.me/(?:addemoji|addstickers)/([A-Za-z][A-Za-z0-9_]{0,63})/?(?:\?[^\s]*)?$~i', $raw, $match) !== 1) {
        return null;
    }
    return $match[1];
}

    public static function rxPremiumEmojiPackFetch($name) {
    $name = trim((string)$name);
    $result = ['ok' => false, 'error' => '', 'set' => [], 'items' => []];
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name) !== 1) {
        $result['error'] = "❌ لینک پک معتبر نیست.";
        return $result;
    }
    $response = telegram('getStickerSet', ['name' => $name]);
    if (!is_array($response) || empty($response['ok']) || !isset($response['result']) || !is_array($response['result'])) {
        $description = is_array($response) ? trim((string)($response['description'] ?? '')) : '';
        $result['error'] = "❌ دریافت اطلاعات پک از تلگرام ناموفق بود.\n\n📌 لینک را بررسی کنید یا کمی بعد دوباره تلاش کنید.";
        if ($description !== '') {
            $result['error'] .= "\n\n<code>" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "</code>";
        }
        return $result;
    }
    $set = $response['result'];
    if ((string)($set['sticker_type'] ?? '') !== 'custom_emoji') {
        $result['error'] = "❌ این پک از نوع <b>ایموجی سفارشی</b> نیست و آیدی ایموجی ندارد.\n\nفقط لینک پک‌های ایموجی پشتیبانی می‌شود.";
        return $result;
    }
    $items = [];
    $seen = [];
    foreach ((is_array($set['stickers'] ?? null) ? $set['stickers'] : []) as $sticker) {
        if (!is_array($sticker)) { continue; }
        $customId = trim((string)($sticker['custom_emoji_id'] ?? ''));
        if ($customId === '' || !ctype_digit($customId) || strlen($customId) > 30) { continue; }
        if (isset($seen[$customId])) { continue; }
        $seen[$customId] = true;
        $alternative = trim((string)($sticker['emoji'] ?? ''));
        $items[] = [
            'custom_emoji_id' => $customId,
            'emoji' => $alternative !== '' ? $alternative : '•',
        ];
    }
    if (empty($items)) {
        $result['error'] = "📭 این پک هیچ ایموجی سفارشی قابل نمایشی ندارد.";
        return $result;
    }
    $result['ok'] = true;
    $result['set'] = $set;
    $result['items'] = $items;
    return $result;
}

    public static function rxRenderPremiumEmojiPackPage($from_id, $name, $page = 1, $panelPage = 1, $fetched = null) {
    global $callback_query_id, $message_id;
    $panelPage = max(1, (int)$panelPage);
    $backCallback = ($panelPage > 1) ? "premium_emoji_settings_{$panelPage}" : "premium_emoji_settings";
    $errorKeyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "🔙 بازگشت به لیست", 'callback_data' => $backCallback]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!is_array($fetched)) {
        $fetched = self::rxPremiumEmojiPackFetch($name);
    }
    if (empty($fetched['ok'])) {
        $errorMessage = (string)($fetched['error'] ?? "❌ خطا در دریافت اطلاعات پک.");
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ دریافت اطلاعات تازهٔ پک ناموفق بود؛ دوباره تلاش کنید.',
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return false;
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $errorMessage, $errorKeyboard, 'HTML');
        return false;
    }
    $items = is_array($fetched['items'] ?? null) ? $fetched['items'] : [];
    $total = count($items);
    if ($total === 0) {
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📭 این پک هیچ ایموجی سفارشی قابل نمایشی ندارد.", $errorKeyboard, 'HTML');
        return false;
    }
    $pageSize = 10;
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = max(1, (int)$page);
    if ($page > $totalPages) { $page = $totalPages; }
    $set = is_array($fetched['set'] ?? null) ? $fetched['set'] : [];
    $title = trim((string)($set['title'] ?? ''));
    $title = preg_replace('/[\r\n]+/', ' ', $title);
    if ($title === '') { $title = (string)$name; }
    $message = "🔗 <b>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</b>\n"
        . "https://t.me/addemoji/" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\n"
        . "📄 صفحه <b>{$page}</b> از <b>{$totalPages}</b>\n\n";
    $index = ($page - 1) * $pageSize;
    foreach (array_slice($items, ($page - 1) * $pageSize, $pageSize) as $item) {
        $index++;
        $customId = htmlspecialchars((string)$item['custom_emoji_id'], ENT_QUOTES, 'UTF-8');
        $alternative = htmlspecialchars((string)$item['emoji'], ENT_QUOTES, 'UTF-8');
        $message .= "{$index}. <tg-emoji emoji-id=\"{$customId}\">{$alternative}</tg-emoji> → <code>{$customId}</code>\n";
    }
    $prevCallback = ($page > 1) ? 'premium_emoji_pack_page_' . ($page - 1) . '_' . $panelPage : 'premium_emoji_noop';
    $nextCallback = ($page < $totalPages) ? 'premium_emoji_pack_page_' . ($page + 1) . '_' . $panelPage : 'premium_emoji_noop';
    $keyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => ($page > 1 ? "« قبل" : "▫️"), 'callback_data' => $prevCallback],
                ['text' => "📄 {$page} / {$totalPages}", 'callback_data' => 'premium_emoji_noop'],
                ['text' => ($page < $totalPages ? "بعد »" : "▫️"), 'callback_data' => $nextCallback],
            ],
            [
                ['text' => "🔙 بازگشت", 'callback_data' => $backCallback],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $isCallback = !empty($callback_query_id) && !empty($message_id);
    $payload = [
        'chat_id' => $from_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard,
        'link_preview_options' => json_encode(['is_disabled' => true]),
        '_rx_already_transformed' => true,
        '_rx_emoji_fallback_attempt' => true,
        '_rx_no_network_retry' => true,
    ];
    if ($isCallback) { $payload['message_id'] = $message_id; }
    $method = $isCallback ? 'editMessageText' : 'sendMessage';
    $response = telegram($method, $payload);
    $unchanged = stripos((string)($response['description'] ?? ''), 'message is not modified') !== false;
    if (empty($response['ok']) && !$unchanged && (int)($response['error_code'] ?? 0) === 400) {
        $payload['text'] = preg_replace('~</?tg-emoji\b[^>]*>~i', '', $message);
        $response = telegram($method, $payload);
        $unchanged = stripos((string)($response['description'] ?? ''), 'message is not modified') !== false;
    }
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => (!empty($response['ok']) || $unchanged) ? '' : '❌ نمایش صفحه ناموفق بود؛ دوباره تلاش کنید.',
            'cache_time' => 0,
        ]);
    } elseif (empty($response['ok']) && !$unchanged) {
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, '❌ نمایش پک ناموفق بود؛ لینک را دوباره ارسال کنید.', $errorKeyboard, 'HTML');
    }
    return !empty($response['ok']) || $unchanged;
}
}
