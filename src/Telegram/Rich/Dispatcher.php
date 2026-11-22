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
        unset($GLOBALS['rx_rich_diag_validation_reason']);
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
        $degraded = false;
        $degradedReason = '';
        $chatKey = (string) ($base['chat_id'] ?? '');
        $withRemove = $apiMethod === 'sendRichMessage' && $chatKey !== '' && !empty($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]) && !isset($base['reply_markup']);
        foreach ($variants as [$label, $html]) {
            $payload = $base;
            if ($withRemove) {
                $payload['reply_markup'] = json_encode(['remove_keyboard' => true]);
            }
            $richMessage = \Faoxima\Telegram\Rich\Markup::rx_richUi_buildMessage($html, $buttonsHtml, $media, $rtlOverride);
            $validation = \Faoxima\Telegram\Rich\Validation::rx_richUi_validateRichPayload($richMessage, ['text' => $html, 'media_count' => count($media), 'method' => $apiMethod] + $targetMeta);
            if (!empty($validation['has_table'])) {
                $validationMetrics = \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($html);
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('table_validation', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_currentKey(), $validationMetrics, $validationMetrics, [
                    'render_mode' => $validation['mode'],
                    'telegram_method' => $apiMethod,
                    'rich_json_valid' => $validation['json_ok'],
                    'rich_json_blocks' => $validation['blocks'],
                    'variant' => $label,
                    'phase' => 'rich_table_validation',
                    'after' => [
                        'rich_blocks' => $validation['blocks'],
                        'structure_available' => $validation['valid'],
                    ],
                ]);
            }
            if (!$validation['valid']) {
                $GLOBALS['rx_rich_diag_validation_reason'] = $validation['reason'];
                $last = ['ok' => false, 'error_code' => 0, 'description' => 'rich table validation failed: ' . $validation['reason']];
                $degraded = true;
                $degradedReason = 'table_validation:' . $validation['reason'];
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_RENDER_FAILED', 'validation', 'rejected', [
                    'api_method' => $apiMethod,
                    'variant' => $label,
                    'reason' => $validation['reason'],
                    'validation' => $validation,
                    'table_present' => $validation['has_table'],
                ], 'warning');
                continue;
            }
            $payload['rich_message'] = json_encode($richMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $variantMetrics = \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($html);
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_PAYLOAD_PREPARED', 'payload', 'prepared', [
                'api_method' => $apiMethod,
                'variant' => $label,
                'delivery_mode' => 'rich_message',
                'rich_mode' => isset($richMessage['blocks']) ? 'blocks' : 'html',
                'rich_json_ok' => is_string($payload['rich_message']),
                'rich_json_length' => is_string($payload['rich_message']) ? strlen($payload['rich_message']) : 0,
                'rich_blocks' => isset($richMessage['blocks']) && is_array($richMessage['blocks']) ? count($richMessage['blocks']) : 0,
                'is_rtl' => $richMessage['is_rtl'] ?? null,
                'media_count' => count($media),
                'buttons_length' => strlen($buttonsHtml),
                'text' => $variantMetrics,
                'chat_hash' => substr(hash('sha256', $chatKey), 0, 12),
            ], 'debug');
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_comparison('payload_preparation', \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_currentKey(), $variantMetrics, $variantMetrics, [
                'render_mode' => isset($richMessage['blocks']) ? 'blocks' : 'html',
                'telegram_method' => $apiMethod,
                'rich_json_valid' => is_string($payload['rich_message']),
                'rich_json_blocks' => isset($richMessage['blocks']) && is_array($richMessage['blocks']) ? count($richMessage['blocks']) : 0,
                'variant' => $label,
                'phase' => 'telegram_payload',
                'after' => [
                    'rich_blocks' => isset($richMessage['blocks']) && is_array($richMessage['blocks']) ? count($richMessage['blocks']) : 0,
                ],
            ]);
            $result = telegram($apiMethod, $payload, $token);
            if (is_array($result) && !empty($result['ok'])) {
                unset($GLOBALS['rx_rich_diag_validation_reason']);
                if ($label !== 'full' && $degraded) {
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'payload', 'degraded', [
                        'reason' => $degradedReason !== '' ? $degradedReason : 'structured_table_unavailable',
                        'intended_mode' => 'structured_table',
                        'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_attempt'],
                        'selected_variant' => $label,
                        'api_method' => $apiMethod,
                        'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
                        'table_formatting_lost' => true,
                    ], 'warning');
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'payload', 'completed', [
                        'fallback_method' => $label,
                        'delivery_mode' => 'degraded_' . $label,
                        'reason' => $degradedReason !== '' ? $degradedReason : 'structured_table_unavailable',
                        'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
                        'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
                    ], 'info');
                }
                if ($withRemove) {
                    unset($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]);
                    if (\Faoxima\Core\Database\Db::runtimeReady()) {
                        \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleared', '1', 'id', $chatKey);
                    }
                }
                return $result;
            }
            $last = $result;
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_RENDER_FAILED', 'payload', 'rejected', [
                'api_method' => $apiMethod,
                'variant' => $label,
                'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
                'fallback_eligible' => \Faoxima\Telegram\Rich\Validation::rx_richUi_isFallbackError($result),
                'table_present' => (bool) preg_match('~<table\b~i', $html),
                'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $html),
            ], 'warning');
            if ($label === 'full' && preg_match('~<table\b~i', $textHtml)) {
                $description = is_array($result) ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_sanitize((string) ($result['description'] ?? 'unknown response')) : 'invalid response';
                $log = '[rich-template] ' . $apiMethod . ' rejected table: ' . $description;
                if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
                    \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log('rich_template_' . hash('sha256', $log), $log, 300);
                } else {
                    error_log($log);
                }
            }
            if ($label === 'full') {
                $degraded = true;
                $degradedReason = 'api_rejection';
            }
            if (!\Faoxima\Telegram\Rich\Validation::rx_richUi_isFallbackError($result)) {
                return $result;
            }
        }
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_RENDER_FAILED', 'payload', 'exhausted', [
            'api_method' => $apiMethod,
            'variants' => count($variants),
            'last_error' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($last),
            'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
            'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
        ], 'error');
        $GLOBALS['rx_rich_diag_last_rejection'] = \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($last);
        return ['__fallback' => true, 'last' => $last];
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
        $fallbackReason = (string) ($GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_fallback_call');
        $fallbackText = $text === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text);
        if ($text === '') {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_fallback', 'skipped', [
                'reason' => 'empty_text',
                'intended_mode' => 'rich_message',
                'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_sendPlainFallback'],
                'fallback_method' => $method,
                'original' => $fallbackText,
            ], 'warning');
            return null;
        }
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_fallback', 'started', [
            'reason' => $fallbackReason,
            'intended_mode' => 'rich_message',
            'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_sendPlainFallback'],
            'fallback_method' => $method,
            'selected_fallback_method' => $method,
            'parse_mode' => (string) ($datas['parse_mode'] ?? ''),
            'original' => $fallbackText,
            'table_present' => !empty($fallbackText['html_table']),
            'premium_present' => !empty($fallbackText['tg_emoji_tags']),
            'initial_request_failed' => isset($GLOBALS['rx_rich_diag_last_rejection']),
            'original_error' => $GLOBALS['rx_rich_diag_last_rejection'] ?? null,
        ], 'warning');
        unset($GLOBALS['rx_rich_diag_last_rejection']);
        $plain = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($text, $datas['parse_mode'] ?? '');
        if (trim($plain) === '') {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', 'skipped', [
                'reason' => 'empty_plain_text',
                'fallback_method' => $method,
                'table_formatting_lost' => !empty($fallbackText['html_table']),
            ], 'warning');
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
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', $state === 'confirmed' ? 'completed' : $state, [
                'delivery_mode' => 'plain_text',
                'fallback_method' => $method,
                'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($single),
                'chunks' => 1,
                'plain_length' => strlen($plain),
                'table_formatting_lost' => !empty($fallbackText['html_table']),
                'premium_markup_removed' => !empty($fallbackText['tg_emoji_tags']),
                'parse_mode_dropped' => isset($datas['parse_mode']) && (string) $datas['parse_mode'] !== '',
            ], $state === 'confirmed' ? 'info' : 'warning');
            return $single;
        }
        if (!empty($payload['inline_message_id'])) {
            $inlineFailure = ['ok' => false, 'error_code' => 400, 'description' => 'Long plain fallback cannot be split for an inline message'];
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', 'failed', [
                'reason' => 'inline_message_cannot_split',
                'fallback_method' => $method,
                'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($inlineFailure),
                'chunks' => count($chunks),
                'table_formatting_lost' => !empty($fallbackText['html_table']),
            ], 'error');
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
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', $status, [
                    'delivery_mode' => 'plain_text',
                    'fallback_method' => $method,
                    'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
                    'chunk_index' => $index,
                    'chunks' => count($chunks),
                    'table_formatting_lost' => !empty($fallbackText['html_table']),
                    'premium_markup_removed' => !empty($fallbackText['tg_emoji_tags']),
                ], 'warning');
                if ($index > 0 && is_array($result)) {
                    $result['_rx_confirmed_text_chunks'] = $index;
                }
                return $result;
            }
        }
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', is_array($result) && !empty($result['ok']) ? 'completed' : 'failed', [
            'delivery_mode' => 'plain_text',
            'fallback_method' => $method,
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
            'chunks' => count($chunks),
            'plain_length' => strlen($plain),
            'table_formatting_lost' => !empty($fallbackText['html_table']),
            'premium_markup_removed' => !empty($fallbackText['tg_emoji_tags']),
            'parse_mode_dropped' => isset($datas['parse_mode']) && (string) $datas['parse_mode'] !== '',
        ], is_array($result) && !empty($result['ok']) ? 'info' : 'error');
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
        $fallbackReason = (string) ($GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_media_fallback_call');
        $fallbackCaption = $caption === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($caption);
        if ($caption === '') {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'skipped', [
                'reason' => 'empty_caption',
                'intended_mode' => 'rich_media_message',
                'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_sendPlainMediaFallback'],
                'fallback_method' => $method,
            ], 'warning');
            return null;
        }
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'started', [
            'reason' => $fallbackReason,
            'intended_mode' => 'rich_media_message',
            'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_sendPlainMediaFallback'],
            'fallback_method' => $method,
            'selected_fallback_method' => $method,
            'parse_mode' => (string) ($datas['parse_mode'] ?? ''),
            'original' => $fallbackCaption,
            'table_present' => !empty($fallbackCaption['html_table']),
            'premium_present' => !empty($fallbackCaption['tg_emoji_tags']),
            'initial_request_failed' => isset($GLOBALS['rx_rich_diag_last_rejection']),
            'original_error' => $GLOBALS['rx_rich_diag_last_rejection'] ?? null,
        ], 'warning');
        unset($GLOBALS['rx_rich_diag_last_rejection']);
        $plain = \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($caption, $datas['parse_mode'] ?? '', true);
        if (trim($plain) === '') {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'no_readable_caption', [
                'reason' => 'empty_plain_caption',
                'fallback_method' => $method,
                'table_formatting_lost' => !empty($fallbackCaption['html_table']),
            ], 'warning');
        }
        $payload = $datas;
        $payload['caption'] = trim($plain) === '' ? $caption : htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $payload['_rx_already_transformed'] = true;
        $payload['parse_mode'] = 'HTML';
        unset($payload['caption_entities']);
        $result = rxSendMediaWithCaption($method, $payload, $token, $delivery);
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_media_fallback', $delivery['delivery_status'], [
            'delivery_mode' => 'plain_media',
            'fallback_method' => $method,
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
            'plain_length' => strlen($plain),
            'table_formatting_lost' => !empty($fallbackCaption['html_table']),
            'premium_markup_removed' => !empty($fallbackCaption['tg_emoji_tags']),
            'parse_mode_dropped' => false,
        ] + $delivery, $delivery['delivery_status'] === 'completed' ? 'info' : 'warning');
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
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_RENDER_FAILED', 'prebuilt_validation', 'rejected', [
                        'api_method' => (string) $method,
                        'reason' => $validationReason,
                        'validation' => $prebuiltValidation,
                        'has_text' => isset($datas['text']) && is_string($datas['text']) && trim($datas['text']) !== '',
                        'plain_call_guard' => !empty($GLOBALS['rx_richUi_plain_call']),
                    ], 'error');
                    return [
                        'ok' => false,
                        'error_code' => 400,
                        'description' => 'rich payload validation failed: ' . $validationReason,
                        '_rx_validation_failed' => true,
                    ];
                }
                $plainCallGuard = !empty($GLOBALS['rx_richUi_plain_call']);
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('prebuilt_rich_payload', [
                    'api_method' => (string) $method,
                    'has_text' => isset($datas['text']) && is_string($datas['text']) && trim($datas['text']) !== '',
                    'rich_message_length' => is_string($rawRichMessage) ? strlen($rawRichMessage) : 0,
                    'rich_message_digest' => is_string($rawRichMessage) ? substr(hash('sha256', $rawRichMessage), 0, 16) : null,
                    'rich_mode' => (string) ($prebuiltValidation['mode'] ?? 'unknown'),
                    'rich_blocks' => (int) ($prebuiltValidation['blocks'] ?? 0),
                    'validation' => 'valid',
                    'plain_call_guard' => $plainCallGuard,
                    'interception' => $plainCallGuard ? 'skipped_plain_call' : 'pass_through',
                ]);
            }
        }
        if (!in_array($m, ['sendmessage', 'editmessagetext', 'editmessagereplymarkup'], true) && !isset($mediaKeys[$m])) {
            return null;
        }
        global $APIKEY;
        if ($token !== null && $token !== $APIKEY) {
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('token_mismatch', ['api_method' => (string) $method]);
        }
        if (!empty($GLOBALS['rx_richUi_plain_call'])) {
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('plain_call_guard', ['api_method' => (string) $method]);
        }
        $chatId = $datas['chat_id'] ?? null;
        if (!empty($datas['entities']) || !empty($datas['caption_entities'])) {
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('entities_present', ['api_method' => (string) $method]);
        }
        $parseMode = $datas['parse_mode'] ?? '';
        $mode = strtolower(trim((string) $parseMode));
        if (!in_array($mode, ['', 'none', 'html'], true)) {
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('parse_mode_unsupported', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
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
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'non_user_chat_rich_blocked';
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'non_user_chat_media_rich_blocked';
                $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('non_user_chat_no_fallback', ['api_method' => (string) $method, 'is_user_ui' => false]);
        }
        $explicitRich = $rtlOverride !== null || preg_match('~<table\b~i', $rawText . $rawCaption) === 1;
        $embedButtons = method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') && \Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($chatId, $datas['reply_markup'] ?? null);
        $destRich = $explicitRich || $embedButtons;
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'dispatch_enter', 'observed', [
            'api_method' => (string) $method,
            'parse_mode' => (string) $parseMode,
            'is_user_ui' => $isUserUi,
            'needs_intercept' => $needsIntercept,
            'needs_rich' => $needsRich,
            'html_like' => $htmlLike,
            'explicit_rich' => $explicitRich,
            'embed_buttons' => $embedButtons,
            'dest_rich' => $destRich,
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
            'text' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($rawText),
            'caption' => $rawCaption === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($rawCaption),
            'has_reply_markup' => !empty($datas['reply_markup']),
            'media_kind' => isset($mediaKeys[$m]) ? $mediaKeys[$m] : '',
        ], 'debug');
        if ($m === 'editmessagetext') {
            $editId = (int) ($datas['message_id'] ?? 0);
            $cbMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
            $isCbTarget = $editId > 0 && is_array($cbMessage) && (int) ($cbMessage['message_id'] ?? 0) === $editId && !isset($datas['inline_message_id']);
            $targetRich = $isCbTarget && isset($cbMessage['rich_message']);
            $targetLegacyText = $isCbTarget && !$targetRich && isset($cbMessage['text']);
            if (($destRich && $targetLegacyText) || (!$destRich && $targetRich)) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'replacement_selected', [
                    'original_operation' => (string) $method,
                    'requested_api_method' => (string) $method,
                    'selected_api_method' => 'replaceMessage',
                    'is_edit' => true,
                    'is_new_message' => false,
                    'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
                    'target_message_id' => $editId,
                    'previous_message_id' => $editId,
                    'target_is_rich' => $targetRich,
                    'target_has_text' => $targetLegacyText,
                    'replacement_created' => null,
                    'deletion_requested' => null,
                    'deletion_succeeded' => null,
                    'rich_intercepted_edit' => true,
                    'reason' => (!$destRich && $targetRich) ? 'rich_target_to_legacy' : 'legacy_target_to_rich',
                ], 'info');
                return self::rx_richUi_replaceMessage($datas, $token, $editId, !$destRich);
            }
        }
        if (!$destRich) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'rich_mode_not_required';
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if ($needsIntercept && isset($mediaKeys[$m])) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'rich_mode_not_required_media';
                $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if (!($m === 'editmessagereplymarkup' && self::rx_richUi_targetIsRichMessage((int) ($datas['message_id'] ?? 0)))) {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('passthrough_legacy_message', ['api_method' => (string) $method]);
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
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'markup_without_inline_keyboard';
                    $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'markup_without_inline_keyboard_media';
                    $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('markup_without_inline_keyboard_no_fallback', ['api_method' => (string) $method]);
            }
            if ($embedButtons) $buttonsHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_buttonsHtml($markup['inline_keyboard']);
            if ($buttonsHtml === null) {
                if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'buttons_html_unbuildable';
                    $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'buttons_html_unbuildable_media';
                    $fallback = self::rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('buttons_html_unbuildable_no_fallback', ['api_method' => (string) $method]);
            }
        }

        $passKeys = ['message_thread_id', 'disable_notification', 'protect_content', 'message_effect_id', 'reply_parameters'];
        if ($nativeMarkup !== null) $passKeys[] = 'reply_markup';
        $media = [];

        if (isset($mediaKeys[$m])) {
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('media_rich_not_required', ['api_method' => (string) $method, 'media_kind' => $mediaKeys[$m]]);
            }
            return self::rx_richUi_dispatchMedia($m, $mediaKeys[$m], $datas, $buttonsHtml, $passKeys, $token);
        }

        if ($m === 'sendmessage') {
            if (!isset($datas['text']) || !is_string($datas['text'])) {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('missing_text', ['api_method' => (string) $method]);
            }
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('rich_not_required', ['api_method' => (string) $method]);
            }
            $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($datas['text'], $parseMode);
            $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($datas['text'], $parseMode);
            if ($textHtml === null || $textHtml === '') {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('html_conversion_failed', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
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
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('invalid_edit_target', ['api_method' => (string) $method]);
            }
            $targetIsRich = self::rx_richUi_targetIsRichMessage($messageId);
            if ($m === 'editmessagereplymarkup') {
                if (!$targetIsRich) {
                    return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('reply_markup_target_not_rich', ['api_method' => (string) $method]);
                }
                $cbRich = $GLOBALS['update']['callback_query']['message']['rich_message'] ?? null;
                return \Faoxima\Telegram\Rich\Markup::rx_richUi_editRichButtons($datas, is_array($cbRich) ? $cbRich : [], $markup['inline_keyboard'] ?? [], $token);
            } else {
                if ($buttonsHtml === '' && !$targetIsRich && !$needsIntercept) {
                    return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('edit_rich_not_required', ['api_method' => (string) $method]);
                }
                if (!isset($datas['text']) || !is_string($datas['text'])) {
                    return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('missing_edit_text', ['api_method' => (string) $method]);
                }
                $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($datas['text'], $parseMode);
                $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($datas['text'], $parseMode);
            }
            if ($textHtml === null || $textHtml === '') {
                return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('edit_html_conversion_failed', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
            }
            $base = ['chat_id' => $chatId, 'message_id' => $messageId];
            if ($nativeMarkup !== null) {
                if (!isset($markup['inline_keyboard'])) {
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'replacement_selected', [
                        'original_operation' => (string) $method,
                        'requested_api_method' => (string) $method,
                        'selected_api_method' => 'replaceMessage',
                        'is_edit' => true,
                        'is_new_message' => false,
                        'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
                        'target_message_id' => $messageId,
                        'previous_message_id' => $messageId,
                        'replacement_created' => null,
                        'deletion_requested' => null,
                        'rich_intercepted_edit' => true,
                        'reason' => 'native_markup_without_inline_keyboard',
                    ], 'info');
                    return self::rx_richUi_replaceMessage($datas, $token, $messageId);
                }
                $base['reply_markup'] = $nativeMarkup;
            }
            $apiMethod = 'editMessageText';
        }

        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'send_method', 'selected', [
            'api_method' => $apiMethod,
            'delivery_mode' => $apiMethod === 'sendRichMessage' ? 'rich_message' : 'rich_edit',
            'parse_mode' => (string) $parseMode,
            'text' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($textHtml),
            'buttons_length' => strlen($buttonsHtml),
            'media_count' => count($media),
            'rtl_override' => $rtlOverride,
            'target_is_rich' => $m === 'sendmessage' ? null : ($targetIsRich ?? null),
            'needs_intercept' => $needsIntercept,
            'explicit_rich' => $explicitRich,
        ], 'info');
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'selected', [
            'original_operation' => (string) $method,
            'requested_api_method' => (string) $method,
            'selected_api_method' => $apiMethod,
            'is_edit' => $apiMethod === 'editMessageText',
            'is_new_message' => $apiMethod === 'sendRichMessage',
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
            'target_message_id' => isset($base['message_id']) ? (int) $base['message_id'] : null,
            'previous_message_id' => isset($base['message_id']) ? (int) $base['message_id'] : null,
            'target_is_rich' => $m === 'sendmessage' ? null : ($targetIsRich ?? null),
            'resulting_message_id' => null,
            'replacement_created' => false,
            'deletion_requested' => false,
            'deletion_succeeded' => null,
            'rich_intercepted_edit' => $m === 'editmessagetext' && $apiMethod === 'editMessageText',
            'reason' => $apiMethod === 'sendRichMessage' ? 'new_rich_message' : 'rich_edit_existing',
            'target_rich_meta' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_targetRichMeta(),
        ], 'info');
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
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', is_array($result) && !empty($result['ok']) ? 'delivered' : 'not_delivered', [
            'original_operation' => (string) $method,
            'selected_api_method' => $apiMethod,
            'is_edit' => $apiMethod === 'editMessageText',
            'is_new_message' => $apiMethod === 'sendRichMessage',
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
            'target_message_id' => isset($base['message_id']) ? (int) $base['message_id'] : null,
            'resulting_message_id' => is_array($result) ? (int) ($result['result']['message_id'] ?? 0) : null,
            'replacement_created' => false,
            'deletion_requested' => false,
            'rich_intercepted_edit' => $m === 'editmessagetext' && $apiMethod === 'editMessageText',
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
            'target_rich_meta' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_targetRichMeta(),
        ], is_array($result) && !empty($result['ok']) ? 'info' : 'warning');
        if (is_array($result) && isset($result['__fallback'])) {
            $rxFallbackReason = !empty($GLOBALS['rx_rich_diag_validation_reason']) ? ('table_validation_failed:' . $GLOBALS['rx_rich_diag_validation_reason']) : 'rich_payload_rejected';
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'dispatch_result', 'fallback', [
                'reason' => $rxFallbackReason,
                'intended_mode' => 'rich_message',
                'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_dispatch'],
                'api_method' => $apiMethod,
                'initial_request_failed' => true,
                'last_error' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result['last'] ?? null),
                'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
                'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
            ], 'warning');
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = $rxFallbackReason;
                $fallback = self::rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'dispatch_result', is_array($fallback) && !empty($fallback['ok']) ? 'completed' : 'failed', [
                        'reason' => $rxFallbackReason,
                        'fallback_method' => $m,
                        'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($fallback),
                        'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
                        'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
                    ], is_array($fallback) && !empty($fallback['ok']) ? 'info' : 'error');
                    unset($GLOBALS['rx_rich_diag_validation_reason']);
                    return $fallback;
                }
            }
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'dispatch_result', 'unavailable', [
                'reason' => 'plain_fallback_not_applicable',
                'api_method' => $apiMethod,
                'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
            ], 'warning');
            unset($GLOBALS['rx_rich_diag_last_rejection'], $GLOBALS['rx_rich_diag_validation_reason']);
            return null;
        }
        return $result;
    }

    public static function rx_richUi_replaceMessage(array $datas, $token, int $messageId, bool $toLegacy = false)
    {
        $parseMode = $datas['parse_mode'] ?? null;
        $text = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        if (trim($text) === '') {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'replacement', 'skipped', [
                'original_operation' => 'editmessagetext',
                'requested_api_method' => 'editmessagetext',
                'selected_api_method' => 'none',
                'is_edit' => true,
                'is_new_message' => false,
                'chat_hash' => substr(hash('sha256', (string) ($datas['chat_id'] ?? '')), 0, 12),
                'target_message_id' => $messageId,
                'previous_message_id' => $messageId,
                'replacement_created' => false,
                'deletion_requested' => false,
                'deletion_succeeded' => false,
                'rich_intercepted_edit' => true,
                'reason' => 'replacement_without_text',
            ], 'warning');
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
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'replacement', $replacementCreated ? 'completed' : 'failed', [
            'original_operation' => 'editmessagetext',
            'requested_api_method' => 'editmessagetext',
            'selected_api_method' => 'sendmessage',
            'is_edit' => false,
            'is_new_message' => true,
            'chat_hash' => substr(hash('sha256', (string) ($datas['chat_id'] ?? '')), 0, 12),
            'target_message_id' => $messageId,
            'previous_message_id' => $messageId,
            'original_message_id' => $messageId,
            'replacement_message_id' => $replacementMessageId > 0 ? $replacementMessageId : null,
            'replacement_status' => $replacementCreated ? 'created' : 'not_created',
            'deletion_status' => $deleteResult === null ? 'not_requested' : (self::rx_richUi_deleteWasConfirmed($deleteResult) ? 'confirmed' : 'failed'),
            'resulting_message_id' => is_array($result) ? (int) ($result['result']['message_id'] ?? 0) : null,
            'replacement_created' => $replacementCreated,
            'deletion_requested' => $replacementCreated,
            'deletion_succeeded' => $deleteResult === null ? null : (is_array($deleteResult) && !empty($deleteResult['ok'])),
            'rich_intercepted_edit' => true,
            'reason' => $toLegacy ? 'rich_target_converted_to_legacy' : 'legacy_target_replaced_with_rich',
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
            'delete_result' => $deleteResult === null ? null : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($deleteResult),
        ], $replacementCreated ? 'info' : 'warning');
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
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'media_dispatch', 'selected', [
            'api_method' => $method,
            'delivery_mode' => 'media_caption',
            'media_kind' => $kind,
            'media_type' => is_string($file) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $file) ? 'file_id' : (is_object($file) ? get_class($file) : gettype($file)),
            'reusable_file_id' => is_string($file) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $file) === 1,
            'parse_mode' => (string) $parseMode,
            'caption' => $caption === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($caption),
            'buttons_length' => strlen($buttonsHtml),
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
        ], 'info');
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_MESSAGE_TARGET', 'media_target', 'selected', [
            'original_operation' => $method,
            'requested_api_method' => $method,
            'selected_api_method' => 'sendRichMessage',
            'is_edit' => strtolower($method) === 'editmessagecaption',
            'is_new_message' => strtolower($method) !== 'editmessagecaption',
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
            'target_message_id' => isset($datas['message_id']) ? (int) $datas['message_id'] : null,
            'previous_message_id' => isset($datas['message_id']) ? (int) $datas['message_id'] : null,
            'resulting_message_id' => null,
            'replacement_created' => false,
            'deletion_requested' => false,
            'rich_intercepted_edit' => strtolower($method) === 'editmessagecaption',
            'reason' => 'media_rich_candidate',
            'media_kind' => $kind,
        ], 'info');
        if ($captionHtml === null) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_RENDER_FAILED', 'media_dispatch', 'unsupported_parse_mode', [
                'api_method' => $method,
                'media_kind' => $kind,
                'parse_mode' => (string) $parseMode,
            ], 'warning');
            return \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_dispatchSkip('media_caption_parse_mode_unsupported', ['api_method' => $method, 'media_kind' => $kind]);
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
            $rxMediaCaptionMetrics = $caption === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($caption);
            $rxMediaFallbackReason = !empty($GLOBALS['rx_rich_diag_validation_reason']) ? ('table_validation_failed:' . $GLOBALS['rx_rich_diag_validation_reason']) : 'rich_media_payload_rejected';
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'media_dispatch', 'fallback', [
                'reason' => $rxMediaFallbackReason,
                'intended_mode' => 'rich_media_message',
                'initiator' => [\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_dispatchMedia'],
                'fallback_method' => $method,
                'initial_request_failed' => true,
                'last_error' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result['last'] ?? null),
                'table_present' => !empty($rxMediaCaptionMetrics['html_table']),
                'premium_present' => !empty($rxMediaCaptionMetrics['tg_emoji_tags']),
            ], 'warning');
            unset($GLOBALS['rx_rich_diag_last_rejection'], $GLOBALS['rx_rich_diag_validation_reason']);
        }
        $GLOBALS['rx_rich_diag_fallback_reason'] = $rxMediaFallbackReason ?? 'media_rich_path_unavailable';

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
        $mediaFallbackText = $caption === '' ? ['present' => false] : \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($caption);
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'media_dispatch', $delivery['delivery_status'], [
            'reason' => $GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_media',
            'fallback_method' => $method,
            'delivery_mode' => 'plain_media',
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($mediaResult),
            'table_formatting_lost' => !empty($mediaFallbackText['html_table']),
            'premium_markup_removed' => !empty($mediaFallbackText['tg_emoji_tags']),
            'buttons_detached' => $buttonsHtml !== '',
        ] + $delivery, $delivery['delivery_status'] === 'completed' ? 'info' : 'warning');
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
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'media_controls', $controlState, [
            'delivery_mode' => 'plain_media_controls',
            'media_state' => $delivery['media_state'],
            'text_state' => $delivery['text_state'],
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($controlResult),
        ], $controlState === 'confirmed' ? 'info' : 'warning');
        if ($controlState === 'confirmed') {
            $mediaResult['rx_control_message_id'] = (int) ($controlResult['result']['message_id'] ?? 0);
        }
        return $mediaResult;
    }

    public static function rx_richUi_sendBanner($chatId, $fileId, $text, $keyboard, $parseMode, $section = null)
    {
        if (!method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') || !\Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($chatId, $keyboard, $section)) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => 'rich_banner_inactive',
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
                'text' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text),
            ], 'debug');
            return null;
        }
        $fileId = trim((string) $fileId);
        if ($fileId === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $fileId)) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => 'invalid_banner_file_id',
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
            ], 'debug');
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
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => 'banner_markup_without_inline_keyboard',
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
            ], 'debug');
            return null;
        }
        $buttonsHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_buttonsHtml($markup['inline_keyboard']);
        $textHtml = \Faoxima\Telegram\Rich\Markup::rx_richUi_textToHtml($text, $parseMode);
        if ($buttonsHtml === null || $buttonsHtml === '' || $textHtml === null) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => $buttonsHtml === null ? 'banner_buttons_html_failed' : ($textHtml === null ? 'banner_html_conversion_failed' : 'banner_buttons_empty'),
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
                'text' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text),
            ], 'debug');
            return null;
        }
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'selected', [
            'api_method' => 'sendRichMessage',
            'delivery_mode' => 'rich_banner',
            'section' => (string) $section,
            'text' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text),
            'buttons_length' => strlen($buttonsHtml),
        ], 'info');
        $media = [['id' => 'banner', 'media' => ['type' => 'photo', 'media' => $fileId]]];
        $tag = '<figure><img src="tg://photo?id=banner"/></figure>';
        $plainHtml = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainHtml($text, $parseMode);
        $result = self::rx_richUi_attempt('sendRichMessage', ['chat_id' => $chatId], $tag . $textHtml, $tag . $plainHtml, $buttonsHtml, $media, null, \Faoxima\Telegram\Rich\Markup::rx_richUi_rtlMarker($text));
        $accepted = is_array($result) && !empty($result['ok']);
        $rxBannerResultState = is_array($result) && !isset($result['__fallback']) && is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState'])
            ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($result)
            : ($accepted ? 'confirmed' : 'failed');
        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log($rxBannerResultState === 'confirmed' ? 'RICH_DELIVERY_COMPLETED' : 'RICH_RENDER_FAILED', 'banner_delivery', $rxBannerResultState, [
            'delivery_mode' => 'rich_banner',
            'section' => (string) $section,
            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result),
            'table_present' => (bool) preg_match('~<table\b~i', (string) $textHtml),
            'premium_present' => (bool) preg_match('~<tg-emoji\b~i', (string) $textHtml),
        ], $rxBannerResultState === 'confirmed' ? 'info' : ($rxBannerResultState === 'unknown' ? 'warning' : 'error'));
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
