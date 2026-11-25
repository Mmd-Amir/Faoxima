<?php

namespace Faoxima\Telegram\Rich;

use PDO;
use Throwable;

final class Dispatcher
{
    private static array $replacedTargets = [];

    public static function rx_richUi_noteReplacement($chatId, int $oldId, int $newId): void
    {
        if ($oldId <= 0) {
            return;
        }
        if (count(self::$replacedTargets) >= 256) {
            array_shift(self::$replacedTargets);
        }
        self::$replacedTargets[(string) $chatId . ':' . $oldId] = $newId > 0 ? $newId : 0;
    }

    public static function rx_richUi_resolveTarget($chatId, int $messageId): array
    {
        $current = $messageId;
        for ($hops = 0; $hops < 16 && $current > 0; $hops++) {
            $key = (string) $chatId . ':' . $current;
            if (!array_key_exists($key, self::$replacedTargets)) {
                return ['id' => $current, 'deleted' => false, 'redirected' => $current !== $messageId];
            }
            $next = self::$replacedTargets[$key];
            if ($next <= 0) {
                return ['id' => $current, 'deleted' => true, 'redirected' => $current !== $messageId];
            }
            $current = $next;
        }
        return ['id' => $current, 'deleted' => false, 'redirected' => $current !== $messageId];
    }

    public static function rx_richUi_deleteWasConfirmed($result): bool
    {
        if (!is_array($result)) {
            return false;
        }
        if (!empty($result['ok'])) {
            return true;
        }
        return (int) ($result['error_code'] ?? 0) === 400 && stripos((string) ($result['description'] ?? ''), 'message to delete not found') !== false;
    }

    public static function rx_richUi_chatIsUserUi($chatId): bool
    {
        $chatId = trim((string) $chatId);
        if ($chatId === '' || $chatId === '0' || !ctype_digit($chatId)) {
            return false;
        }
        $isAdmin = function_exists('rx_isAdminChat') && rx_isAdminChat($chatId);
        if (!$isAdmin) {
            return true;
        }
        $actor = isset($GLOBALS['from_id']) ? trim((string) $GLOBALS['from_id']) : '';
        if ($actor === '' || $actor !== $chatId) {
            return false;
        }
        return empty($GLOBALS['rx_admin_scope']);
    }

    public static function rx_richUi_targetIsRichMessage($messageId): bool
    {
        $callbackMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
        if (!is_array($callbackMessage) || !isset($callbackMessage['rich_message'])) {
            return false;
        }
        return (int) ($callbackMessage['message_id'] ?? 0) === (int) $messageId && (int) $messageId > 0;
    }

    public static function rx_richUi_attempt(string $apiMethod, array $base, string $textHtml, string $plainHtml, string $buttonsHtml, array $media, $token, $rtlOverride = null, array $targetMeta = [])
    {
        $variants = [['full', $textHtml]];
        if (preg_match('~<table\b~i', $textHtml)) {
            $tableTextHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_replaceTablesWithText($textHtml);
            if ($tableTextHtml !== '' && $tableTextHtml !== $textHtml) {
                $variants[] = ['table_text_html', $tableTextHtml];
            }
        }
        if ($plainHtml !== '' && $plainHtml !== $textHtml) {
            $variants[] = ['plain', $plainHtml];
        }
        $last = null;
        $chatKey = (string) ($base['chat_id'] ?? '');
        $withRemove = $apiMethod === 'sendRichMessage' && $chatKey !== '' && !empty($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]) && !isset($base['reply_markup']);
        foreach ($variants as [$label, $html]) {
            $payload = $base;
            if ($withRemove) {
                $payload['reply_markup'] = json_encode(['remove_keyboard' => true]);
            }
            $richMessage = \Faoxima\Telegram\Rich\Markup::rx_richUi_buildMessage($html, $buttonsHtml, $media, $rtlOverride);
            $validation = \Faoxima\Telegram\Rich\Validation::rx_richUi_validateRichPayload($richMessage, ['text' => $html, 'media_count' => count($media), 'method' => $apiMethod] + $targetMeta);
            if (!$validation['valid']) {
                $last = ['ok' => false, 'error_code' => 0, 'description' => 'rich table validation failed: ' . $validation['reason']];
                continue;
            }
            $payload['rich_message'] = json_encode($richMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = telegram($apiMethod, $payload, $token);
            if (is_array($result) && !empty($result['ok'])) {
                if ($withRemove) {
                    unset($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]);
                    if (\Faoxima\Core\Database\Db::runtimeReady()) {
                        \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleared', '1', 'id', $chatKey);
                    }
                }
                return $result;
            }
            $last = $result;
            if ($label === 'full' && preg_match('~<table\b~i', $textHtml)) {
                $description = is_array($result) ? self::rx_richUi_redactLogText((string) ($result['description'] ?? 'unknown response')) : 'invalid response';
                $log = '[rich-template] ' . $apiMethod . ' rejected table: ' . $description;
                if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
                    \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log('rich_template_' . hash('sha256', $log), $log, 300);
                } else {
                    error_log($log);
                }
            }
            if (!\Faoxima\Telegram\Rich\Validation::rx_richUi_isFallbackError($result)) {
                return $result;
            }
        }
        return ['__fallback' => true, 'last' => $last];
    }

    public static function rx_richUi_redactLogText($value): string
    {
        $text = (string) $value;
        $text = (string) preg_replace('~bot\d+:[A-Za-z0-9_-]{10,}~', 'bot[redacted]', $text);
        $text = (string) preg_replace('~[a-z][a-z0-9+.-]*://[^\s<>"]+~i', '[redacted-url]', $text);
        if (mb_strlen($text, 'UTF-8') > 300) {
            $text = mb_substr($text, 0, 300, 'UTF-8') . '…';
        }
        return $text;
    }

    public static function rx_richUi_plainCall(string $method, array $payload, $token)
    {
        $GLOBALS['rx_richUi_plain_call'] = (int) ($GLOBALS['rx_richUi_plain_call'] ?? 0) + 1;
        try {
            return telegram($method, $payload, $token);
        } finally {
            $GLOBALS['rx_richUi_plain_call'] = max(0, (int) ($GLOBALS['rx_richUi_plain_call'] ?? 1) - 1);
        }
    }

    public static function rx_richUi_sendPlainFallback(string $method, array $datas, $token)
    {
        $text = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        if ($text === '') {
            return null;
        }
        $plain = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($text, $datas['parse_mode'] ?? '');
        if (trim($plain) === '') {
            return null;
        }
        $payload = $datas;
        $payload['text'] = $plain;
        $payload['_rx_already_transformed'] = true;
        unset($payload['parse_mode'], $payload['entities'], $payload['caption_entities'], $payload['rich_message']);
        $chunks = self::rx_richUi_plainChunks($plain);
        if (count($chunks) === 1) {
            $single = self::rx_richUi_plainCall($method, $payload, $token);
            $state = rxMediaResultState($single);
            return $single;
        }
        if (!empty($payload['inline_message_id'])) {
            $inlineFailure = ['ok' => false, 'error_code' => 400, 'description' => 'Long plain fallback cannot be split for an inline message'];
            return $inlineFailure;
        }
        if (strtolower($method) === 'editmessagetext' && !isset($payload['message_thread_id'])) {
            $original = $GLOBALS['update']['callback_query']['message'] ?? [];
            if ((string) ($original['chat']['id'] ?? '') === (string) ($payload['chat_id'] ?? '')
                && (int) ($original['message_id'] ?? 0) === (int) ($payload['message_id'] ?? 0)
                && !empty($original['message_thread_id'])) {
                $payload['message_thread_id'] = $original['message_thread_id'];
            }
        }
        $result = null;
        $last = count($chunks) - 1;
        foreach ($chunks as $index => $chunk) {
            $part = $payload;
            $part['text'] = $chunk;
            $partMethod = $index === 0 ? $method : 'sendmessage';
            if ($index !== $last) {
                unset($part['reply_markup']);
                if ($index === 0 && strtolower($method) === 'editmessagetext') {
                    $part['reply_markup'] = json_encode(['inline_keyboard' => []]);
                }
            }
            if ($index > 0) {
                unset($part['message_id'], $part['inline_message_id'], $part['reply_parameters'], $part['reply_to_message_id']);
            }
            $result = self::rx_richUi_plainCall($partMethod, $part, $token);
            $notModified = $index === 0 && strtolower($method) === 'editmessagetext'
                && is_array($result) && (int) ($result['error_code'] ?? 0) === 400
                && stripos((string) ($result['description'] ?? ''), 'message is not modified') !== false;
            if ((!is_array($result) || empty($result['ok'])) && !$notModified) {
                $state = rxMediaResultState($result);
                $status = $state === 'unknown' ? 'unknown' : ($index > 0 ? 'partial' : 'failed');
                if ($index > 0 && is_array($result)) {
                    $result['_rx_confirmed_text_chunks'] = $index;
                }
                return $result;
            }
        }
        return $result;
    }

    public static function rx_richUi_plainChunks(string $text): array
    {
        if (preg_match_all('~[a-z][a-z0-9+.-]*://[^\s<>]+|\X~iu', $text, $matches) === false) {
            return [$text];
        }
        $chars = [];
        foreach ($matches[0] as $grapheme) {
            if (strlen(mb_convert_encoding($grapheme, 'UTF-16LE', 'UTF-8')) > 8192) {
                preg_match_all('/\X/u', $grapheme, $pieces);
                foreach ($pieces[0] as $piece) {
                    if (strlen(mb_convert_encoding($piece, 'UTF-16LE', 'UTF-8')) > 8192) {
                        array_push($chars, ...preg_split('//u', $piece, -1, PREG_SPLIT_NO_EMPTY));
                    } else {
                        $chars[] = $piece;
                    }
                }
            } else {
                $chars[] = $grapheme;
            }
        }
        $chunks = [];
        $chunk = '';
        $units = 0;
        foreach ($chars as $char) {
            $width = (int) (strlen(mb_convert_encoding($char, 'UTF-16LE', 'UTF-8')) / 2);
            if ($units + $width > 4096) {
                $chunks[] = $chunk;
                $chunk = '';
                $units = 0;
            }
            $chunk .= $char;
            $units += $width;
        }
        if ($chunk !== '') {
            $chunks[] = $chunk;
        }
        return $chunks;
    }

    public static function rx_richUi_sendPlainMediaFallback(string $method, array $datas, $token)
    {
        $caption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        if ($caption === '') {
            return null;
        }
        $plain = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($caption, $datas['parse_mode'] ?? '', true);
        $payload = $datas;
        $payload['caption'] = trim($plain) === '' ? $caption : htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $payload['_rx_already_transformed'] = true;
        $payload['parse_mode'] = 'HTML';
        unset($payload['caption_entities']);
        $result = rxSendMediaWithCaption($method, $payload, $token, $delivery);
        return $result;
    }

    public static function rx_richUi_dispatch($method, array $datas, $token)
    {
        $m = strtolower((string) $method);
        $mediaKeys = ['sendphoto' => 'photo', 'sendvideo' => 'video', 'senddocument' => 'document', 'sendanimation' => 'animation'];
        if (isset($datas['rich_message'])) {
            $rawRichMessage = $datas['rich_message'];
            $prebuilt = is_string($rawRichMessage) ? trim($rawRichMessage) : $rawRichMessage;
            if (is_string($prebuilt) ? $prebuilt !== '' : !empty($prebuilt)) {
                $decodedRichMessage = is_string($rawRichMessage) ? json_decode($rawRichMessage, true) : (is_array($rawRichMessage) ? $rawRichMessage : null);
                $prebuiltValidation = is_array($decodedRichMessage)
                    ? \Faoxima\Telegram\Rich\Validation::rx_richUi_validateRichPayload($decodedRichMessage, ['method' => (string) $method])
                    : ['valid' => false, 'reason' => 'rich_json_invalid', 'mode' => 'unknown'];
                if (empty($prebuiltValidation['valid'])) {
                    $validationReason = (string) ($prebuiltValidation['reason'] ?? 'rich_validation_failed');
                    return [
                        'ok' => false,
                        'error_code' => 400,
                        'description' => 'rich payload validation failed: ' . $validationReason,
                        '_rx_validation_failed' => true,
                    ];
                }
                return null;
            }
        }
        if (!in_array($m, ['sendmessage', 'editmessagetext', 'editmessagereplymarkup'], true) && !isset($mediaKeys[$m])) {
            return null;
        }
        global $APIKEY;
        if ($token !== null && $token !== $APIKEY) {
            return null;
        }
        if (!empty($GLOBALS['rx_richUi_plain_call'])) {
            return null;
        }
        $chatId = $datas['chat_id'] ?? null;
        if (!empty($datas['entities']) || !empty($datas['caption_entities'])) {
            return null;
        }
        $parseMode = $datas['parse_mode'] ?? '';
        $mode = strtolower(trim((string) $parseMode));
        if (!in_array($mode, ['', 'none', 'html'], true)) {
            return null;
        }
        $rawText = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        $rawCaption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        $rtlOverride = \Faoxima\Telegram\Rich\Markup::rx_richUi_rtlMarker($rawText);
        if ($rtlOverride === null) {
            $rtlOverride = \Faoxima\Telegram\Rich\Markup::rx_richUi_rtlMarker($rawCaption);
        }
        $htmlLike = \Faoxima\Telegram\Rich\Markup::rx_richUi_htmlLike($rawText) || \Faoxima\Telegram\Rich\Markup::rx_richUi_htmlLike($rawCaption);
        $needsRich = \Faoxima\Telegram\Rich\Markup::rx_richUi_requiresRich($rawText) || \Faoxima\Telegram\Rich\Markup::rx_richUi_requiresRich($rawCaption);
        $needsIntercept = $needsRich || ($mode !== 'html' && $htmlLike);
        $isUserUi = self::rx_richUi_chatIsUserUi($chatId);
        if (!$isUserUi) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            return null;
        }
        $explicitRich = $rtlOverride !== null || preg_match('~<table\b~i', $rawText . $rawCaption) === 1;
        $embedButtons = method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') && \Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($chatId, $datas['reply_markup'] ?? null);
        $destRich = $explicitRich || $embedButtons;
        if ($m === 'editmessagetext') {
            $editId = (int) ($datas['message_id'] ?? 0);
            $cbMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
            $isCbTarget = $editId > 0 && is_array($cbMessage) && (int) ($cbMessage['message_id'] ?? 0) === $editId && !isset($datas['inline_message_id']);
            $targetRich = $isCbTarget && isset($cbMessage['rich_message']);
            $targetLegacyText = $isCbTarget && !$targetRich && isset($cbMessage['text']);
            if (($destRich && $targetLegacyText) || (!$destRich && $targetRich)) {
                return self::rx_richUi_replaceMessage($datas, $token, $editId, !$destRich);
            }
        }
        if (!$destRich) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if ($needsIntercept && isset($mediaKeys[$m])) {
                $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if (!($m === 'editmessagereplymarkup' && self::rx_richUi_targetIsRichMessage((int) ($datas['message_id'] ?? 0)))) {
                return null;
            }
        }

        $markup = $datas['reply_markup'] ?? null;
        if (is_string($markup) && $markup !== '') {
            $markup = json_decode($markup, true);
        }
        $buttonsHtml = '';
        $nativeMarkup = null;
        if (is_array($markup) && $markup) {
            if (!$embedButtons) {
                $nativeMarkup = $datas['reply_markup'];
            } elseif (!isset($markup['inline_keyboard']) || !is_array($markup['inline_keyboard'])) {
                if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                    $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return null;
            }
            if ($embedButtons) $buttonsHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_buttonsHtml($markup['inline_keyboard']);
            if ($buttonsHtml === null) {
                if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                    $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return null;
            }
        }

        $passKeys = ['message_thread_id', 'disable_notification', 'protect_content', 'message_effect_id', 'reply_parameters'];
        if ($nativeMarkup !== null) $passKeys[] = 'reply_markup';
        $media = [];

        if (isset($mediaKeys[$m])) {
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return null;
            }
            return self::rx_richUi_dispatchMedia($m, $mediaKeys[$m], $datas, $buttonsHtml, $passKeys, $token);
        }

        if ($m === 'sendmessage') {
            if (!isset($datas['text']) || !is_string($datas['text'])) {
                return null;
            }
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return null;
            }
            $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($datas['text'], $parseMode);
            $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($datas['text'], $parseMode);
            if ($textHtml === null || $textHtml === '') {
                return null;
            }
            $base = ['chat_id' => $chatId];
            foreach ($passKeys as $passKey) {
                if (isset($datas[$passKey])) {
                    $base[$passKey] = $datas[$passKey];
                }
            }
            if (!isset($base['reply_parameters']) && !empty($datas['reply_to_message_id'])) {
                $base['reply_parameters'] = json_encode(['message_id' => (int) $datas['reply_to_message_id']]);
            }
            $apiMethod = 'sendRichMessage';
            if (\Faoxima\Telegram\Presentation\Sections::rx_ui_section_for_send($chatId, $datas['reply_markup'] ?? null) === 'main_menu') {
                self::rx_richUi_markMainMenuKeyboardRemoval($chatId);
            }
        } else {
            $messageId = (int) ($datas['message_id'] ?? 0);
            if ($messageId <= 0 || isset($datas['inline_message_id'])) {
                return null;
            }
            $targetIsRich = self::rx_richUi_targetIsRichMessage($messageId);
            if ($m === 'editmessagereplymarkup') {
                if (!$targetIsRich) {
                    return null;
                }
                $cbRich = $GLOBALS['update']['callback_query']['message']['rich_message'] ?? null;
                return \Faoxima\Telegram\Rich\Markup::rx_richUi_editRichButtons($datas, is_array($cbRich) ? $cbRich : [], $markup['inline_keyboard'] ?? [], $token);
            } else {
                if ($buttonsHtml === '' && !$targetIsRich && !$needsIntercept) {
                    return null;
                }
                if (!isset($datas['text']) || !is_string($datas['text'])) {
                    return null;
                }
                $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($datas['text'], $parseMode);
                $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($datas['text'], $parseMode);
            }
            if ($textHtml === null || $textHtml === '') {
                return null;
            }
            $base = ['chat_id' => $chatId, 'message_id' => $messageId];
            if ($nativeMarkup !== null) {
                if (!isset($markup['inline_keyboard'])) {
                    return self::rx_richUi_replaceMessage($datas, $token, $messageId);
                }
                $base['reply_markup'] = $nativeMarkup;
            }
            $apiMethod = 'editMessageText';
        }

        $rxTargetMessageId = (int) ($datas['message_id'] ?? 0);
        $rxCallbackMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
        $rxEditTargetKnown = $m === 'editmessagetext' && $rxTargetMessageId > 0 && is_array($rxCallbackMessage) && (int) ($rxCallbackMessage['message_id'] ?? 0) === $rxTargetMessageId && !isset($datas['inline_message_id']);
        $rxEditTargetIsRich = $rxEditTargetKnown ? isset($rxCallbackMessage['rich_message']) : null;
        $rxEditTargetLegacy = $rxEditTargetKnown && !$rxEditTargetIsRich && (isset($rxCallbackMessage['text']) || !empty($rxCallbackMessage['photo']) || !empty($rxCallbackMessage['video']) || !empty($rxCallbackMessage['animation']) || !empty($rxCallbackMessage['document']));
        $result = self::rx_richUi_attempt($apiMethod, $base, $textHtml, $plainHtml, $buttonsHtml, $media, $token, $rtlOverride, [
            'is_edit' => $apiMethod === 'editMessageText',
            'is_cb_target' => $rxEditTargetKnown,
            'edit_target_known' => $rxEditTargetIsRich === true || $rxEditTargetLegacy,
            'target_is_rich' => $rxEditTargetIsRich,
        ]);
        if (is_array($result) && isset($result['__fallback'])) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            return null;
        }
        return $result;
    }

    public static function rx_richUi_replaceMessage(array $datas, $token, int $messageId, bool $toLegacy = false)
    {
        $parseMode = $datas['parse_mode'] ?? null;
        $text = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        if (trim($text) === '') {
            return null;
        }
        if ($toLegacy) {
            $text = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($text, $parseMode);
            $parseMode = null;
            if (trim($text) === '') {
                return null;
            }
        }
        $send = [
            'chat_id' => $datas['chat_id'],
            'text' => $text,
            'reply_markup' => $datas['reply_markup'] ?? null,
            '_rx_already_transformed' => true,
        ];
        if ($parseMode !== null && $parseMode !== '') {
            $send['parse_mode'] = $parseMode;
        }
        $thread = (int) ($GLOBALS['update']['callback_query']['message']['message_thread_id'] ?? 0);
        if ($thread > 0) {
            $send['message_thread_id'] = $thread;
        }
        $result = $toLegacy ? self::rx_richUi_sendPlainFallback('sendmessage', $send, $token) : telegram('sendmessage', $send, $token);
        $replacementCreated = is_array($result) && !empty($result['ok']);
        $deleteResult = null;
        $replacementMessageId = $replacementCreated ? (int) ($result['result']['message_id'] ?? 0) : 0;
        if ($replacementCreated) {
            $deleteResult = telegram('deletemessage', ['chat_id' => $datas['chat_id'], 'message_id' => $messageId], $token);
            if (self::rx_richUi_deleteWasConfirmed($deleteResult)) {
                self::rx_richUi_noteReplacement($datas['chat_id'], $messageId, $replacementMessageId);
            }
        }
        return $result;
    }

    public static function rx_richUi_markMainMenuKeyboardRemoval($chatId): void
    {
        $chatKey = trim((string) $chatId);
        if ($chatKey === '' || !ctype_digit($chatKey) || !function_exists('rx_autoRemoveReplyKeyboardOn') || !rx_autoRemoveReplyKeyboardOn() || !\Faoxima\Core\Database\Db::runtimeReady()) {
            return;
        }
        try {
            $flag = \Faoxima\Core\Database\Db::select('user', 'reply_kb_cleared', 'id', $chatKey, 'select', ['cache' => false]);
            if (!is_scalar($flag) || (string) $flag !== '1') {
                $GLOBALS['rx_pending_reply_kb_remove'][$chatKey] = true;
            }
        } catch (Throwable $e) {
        }
    }

    public static function rx_richUi_dispatchMedia(string $method, string $kind, array $datas, string $buttonsHtml, array $passKeys, $token)
    {
        $chatId = $datas['chat_id'];
        $file = $datas[$kind] ?? null;
        $caption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        $parseMode = $datas['parse_mode'] ?? '';
        $captionHtml = $caption === '' ? '' : \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($caption, $parseMode);
        if ($captionHtml === null) {
            return null;
        }

        if (is_string($file) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $file)) {
            $mediaType = $kind;
            $tags = [
                'photo' => '<figure><img src="tg://photo?id=m1"/></figure>',
                'video' => '<video src="tg://video?id=m1"></video>',
                'animation' => '<video src="tg://video?id=m1"></video>',
                'document' => '<tg-document src="tg://document?id=m1"></tg-document>',
            ];
            $tag = $tags[$kind];
            $mediaItem = [['id' => 'm1', 'media' => ['type' => $mediaType, 'media' => $file]]];
            $plainCaption = $caption === '' ? '' : (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($caption, $parseMode);
            $base = ['chat_id' => $chatId];
            foreach ($passKeys as $passKey) {
                if (isset($datas[$passKey])) {
                    $base[$passKey] = $datas[$passKey];
                }
            }
            $result = self::rx_richUi_attempt('sendRichMessage', $base, $tag . $captionHtml, $tag . $plainCaption, $buttonsHtml, $mediaItem, $token, \Faoxima\Telegram\Rich\Markup::rx_richUi_rtlMarker($caption));
            if (!(is_array($result) && isset($result['__fallback']))) {
                return $result;
            }
        }

        $mediaDatas = $datas;
        unset($mediaDatas['reply_markup']);
        if ($buttonsHtml === '' && isset($datas['reply_markup'])) {
            $mediaDatas['reply_markup'] = $datas['reply_markup'];
        }
        if (isset($mediaDatas['caption'])) {
            $plainCaption = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($caption, $parseMode, true);
            $mediaDatas['caption'] = trim($plainCaption) !== ''
                ? htmlspecialchars($plainCaption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $caption;
            $mediaDatas['parse_mode'] = 'HTML';
        }
        $mediaDatas['_rx_already_transformed'] = true;
        $mediaResult = rxSendMediaWithCaption($method, $mediaDatas, $token, $delivery);
        if ($delivery['media_state'] !== 'confirmed') {
            return $mediaResult;
        }
        if ($buttonsHtml === '') {
            return $mediaResult;
        }
        $control = [
            'chat_id' => $chatId,
            'text' => 'برای ادامه یکی از گزینه‌های زیر را انتخاب کنید.',
            'parse_mode' => 'HTML',
            'reply_markup' => is_string($datas['reply_markup']) ? $datas['reply_markup'] : json_encode($datas['reply_markup'], JSON_UNESCAPED_UNICODE),
            '_rx_already_transformed' => true,
        ];
        foreach ($passKeys as $passKey) {
            if (isset($datas[$passKey]) && $passKey !== 'reply_parameters') {
                $control[$passKey] = $datas[$passKey];
            }
        }
        $controlResult = self::rx_richUi_plainCall('sendmessage', $control, $token);
        $controlState = rxMediaResultState($controlResult);
        if ($controlState === 'confirmed') {
            $mediaResult['rx_control_message_id'] = (int) ($controlResult['result']['message_id'] ?? 0);
        }
        return $mediaResult;
    }

    public static function rx_richUi_sendBanner($chatId, $fileId, $text, $keyboard, $parseMode, $section = null)
    {
        if (!method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') || !\Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($chatId, $keyboard, $section)) {
            return null;
        }
        $fileId = trim((string) $fileId);
        if ($fileId === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $fileId)) {
            return null;
        }
        if (function_exists('applyPremiumEmojiTransform')) {
            $text = applyPremiumEmojiTransform($text, $parseMode);
        }
        if (function_exists('processKeyboardStyles')) {
            $keyboard = processKeyboardStyles($keyboard, false, $chatId);
        }
        if (function_exists('applyPremiumEmojiToKeyboard')) {
            $keyboard = applyPremiumEmojiToKeyboard($keyboard);
        }
        $markup = is_string($keyboard) ? json_decode($keyboard, true) : $keyboard;
        if (!is_array($markup) || !isset($markup['inline_keyboard']) || !is_array($markup['inline_keyboard'])) {
            return null;
        }
        $buttonsHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_buttonsHtml($markup['inline_keyboard']);
        $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($text, $parseMode);
        if ($buttonsHtml === null || $buttonsHtml === '' || $textHtml === null) {
            return null;
        }
        $media = [['id' => 'banner', 'media' => ['type' => 'photo', 'media' => $fileId]]];
        $tag = '<figure><img src="tg://photo?id=banner"/></figure>';
        $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($text, $parseMode);
        $result = self::rx_richUi_attempt('sendRichMessage', ['chat_id' => $chatId], $tag . $textHtml, $tag . $plainHtml, $buttonsHtml, $media, null, \Faoxima\Telegram\Rich\Markup::rx_richUi_rtlMarker($text));
        $accepted = is_array($result) && !empty($result['ok']);
        $rxBannerResultState = is_array($result) && !isset($result['__fallback']) && is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState'])
            ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($result)
            : ($accepted ? 'confirmed' : 'failed');
        if ($rxBannerResultState === 'unknown') {
            return $result;
        }
        return $accepted ? $result : null;
    }

    public static function rx_richUi_readModes(): ?array
    {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->query("SELECT inlinebtnmain, user_rich_buttons_enabled FROM setting LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!is_array($row)) {
            return null;
        }
        return [
            'glass' => (string) ($row['inlinebtnmain'] ?? '') === 'oninline',
            'rich' => (string) ($row['user_rich_buttons_enabled'] ?? 'off') === 'on',
        ];
    }

    public static function rx_richUi_refreshSettings(): void
    {
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
            \Faoxima\Core\Database\SelectCache::clearSelectCache('setting');
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'faoxima_bust_bot_selectcache')) {
            \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('setting');
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $fresh = \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select', ['cache' => false]);
            if (is_array($fresh)) {
                $GLOBALS['setting'] = $fresh;
            }
        }
    }

    public static function rx_richUi_toggleMode(string $which, string $embeddedValue): array
    {
        global $pdo;
        $modes = self::rx_richUi_readModes();
        if ($modes === null) {
            return ['ok' => false, 'changed' => false, 'message' => 'خطا در خواندن تنظیمات.'];
        }
        $isGlass = $which === 'glass';
        $current = $isGlass ? $modes['glass'] : $modes['rich'];
        $embeddedOn = $isGlass ? $embeddedValue === 'oninline' : $embeddedValue === 'on';
        if ($embeddedOn !== $current) {
            return ['ok' => true, 'changed' => false, 'message' => 'وضعیت تغییر کرده بود؛ صفحه به‌روز شد.'];
        }

        $column = $isGlass ? 'inlinebtnmain' : 'user_rich_buttons_enabled';
        $target = $isGlass ? ($current ? 'offinline' : 'oninline') : ($current ? 'off' : 'on');
        $onValue = $isGlass ? 'oninline' : 'on';
        $guard = $current ? "{$column} = ?" : "COALESCE({$column}, '') <> ?";
        $stmt = $pdo->prepare("UPDATE setting SET {$column} = ? WHERE {$guard}");
        $stmt->execute([$target, $onValue]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => true, 'changed' => false, 'message' => 'وضعیت تغییر کرده بود؛ صفحه به‌روز شد.'];
        }
        return ['ok' => true, 'changed' => true, 'message' => ''];
    }

    public static function rx_richUi_adminToggle(string $which, string $embeddedValue, bool $authorized, $callbackQueryId = null): array
    {
        $outcome = ['ok' => false, 'changed' => false, 'message' => 'شما دسترسی تغییر این تنظیم را ندارید.'];
        if ($authorized) {
            try {
                $outcome = self::rx_richUi_toggleMode($which, $embeddedValue);
            } catch (Throwable $e) {
                error_log('[rich_ui] toggle failed: ' . $e->getMessage());
                $outcome = ['ok' => false, 'changed' => false, 'message' => 'خطا در ذخیره تنظیمات.'];
            }
            self::rx_richUi_refreshSettings();
        }
        if (!empty($callbackQueryId) && $outcome['message'] !== '' && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => $outcome['message'],
                'show_alert' => true,
            ]);
        }
        return $outcome;
    }
}
