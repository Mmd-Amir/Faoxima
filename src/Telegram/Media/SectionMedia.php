<?php

namespace Faoxima\Telegram\Media;

use PDO;

final class SectionMedia
{
    public static function rx_send_banner_message($chat_id, $section, $text, $keyboard = null, $parse_mode = 'html')
    {
        global $setting;
        $rxDiagOwn = false;
        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_begin') && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_enabled') && \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_enabled() && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_active') && !\Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_active()) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_begin($section === 'start' ? 'text_start' : ('banner_' . $section), 'src/Telegram/Media/SectionMedia.php', 'banner_send');
            $rxDiagOwn = true;
        }
        $rxDiagText = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_text') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($text) : ['present' => false];
        $statusCol = "banner_{$section}_status";
        $fileCol = "banner_{$section}_file_id";
        $status = is_array($setting) ? ($setting[$statusCol] ?? '0') : '0';
        $fileId = is_array($setting) ? trim((string)($setting[$fileCol] ?? '')) : '';
        $startMediaActive = false;
        if ($section === 'start') {
            try {
                $startMediaActive = self::rx_start_media_active_config() !== null;
                if ($startMediaActive) {
                    self::rx_send_start_media($chat_id);
                }
            } catch (\Throwable $startMediaError) {
                self::rx_start_media_log('start hook failed: ' . $startMediaError->getMessage());
            }
        }
        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'observed', [
                'section' => (string) $section,
                'parse_mode' => (string) $parse_mode,
                'banner_status' => (string) $status,
                'banner_file_present' => $fileId !== '',
                'start_media_active' => $startMediaActive,
                'keyboard_present' => !empty($keyboard),
                'text' => $rxDiagText,
                'chat_hash' => substr(hash('sha256', (string) $chat_id), 0, 12),
            ], 'info');
        }
        if ($status == '1' && $fileId !== '' && !$startMediaActive) {
            if (method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_sendBanner')) {
                $richBannerResult = \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_sendBanner($chat_id, $fileId, $text, $keyboard, $parse_mode, $section === 'start' ? 'main_menu' : null);
                if (is_array($richBannerResult)) {
                    $rxBannerState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($richBannerResult) : (!empty($richBannerResult['ok']) ? 'confirmed' : 'unknown');
                    if ($rxBannerState === 'confirmed') {
                        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'banner_delivery', 'completed', [
                                'delivery_mode' => 'rich_banner',
                                'section' => (string) $section,
                                'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($richBannerResult),
                                'table_present' => !empty($rxDiagText['html_table']),
                                'premium_present' => !empty($rxDiagText['tg_emoji_tags']),
                            ], 'info');
                        }
                        \Faoxima\Telegram\Presentation\Decor::rx_decor_record($chat_id, $richBannerResult, 'screen', $section);
                        if ($rxDiagOwn) {
                            $GLOBALS['rx_rich_diag_last_result'] = $richBannerResult;
                            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish('completed', ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
                        }
                        return $richBannerResult;
                    }
                    if ($rxBannerState === 'unknown') {
                        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'banner_delivery', 'unknown', [
                                'delivery_mode' => 'rich_banner',
                                'section' => (string) $section,
                                'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($richBannerResult),
                                'fallback_reason' => 'rich_banner_delivery_ambiguous',
                            ], 'warning');
                        }
                        if ($rxDiagOwn) {
                            $GLOBALS['rx_rich_diag_last_result'] = $richBannerResult;
                            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish('unknown', ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
                        }
                        return $richBannerResult;
                    }
                }
                if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'banner_gate', 'fallback', [
                        'reason' => 'rich_banner_not_delivered',
                        'intended_mode' => 'rich_banner',
                        'initiator' => [\Faoxima\Telegram\Media\SectionMedia::class, 'rx_send_banner_message'],
                        'fallback_method' => 'sendphoto',
                        'initial_request_failed' => true,
                        'table_present' => !empty($rxDiagText['html_table']),
                        'premium_present' => !empty($rxDiagText['tg_emoji_tags']),
                    ], 'warning');
                }
            }
            $rxBannerNeedsRich = strtolower((string) $parse_mode) === 'html'
                && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $fileId) === 1
                && method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_requiresRich')
                && \Faoxima\Telegram\Rich\Markup::rx_richUi_requiresRich((string) $text);
            if ($rxBannerNeedsRich) {
                $rxRichPhotoResult = telegram('sendphoto', [
                    'chat_id' => $chat_id,
                    'photo' => $fileId,
                    'caption' => (string) $text,
                    'reply_markup' => $keyboard,
                    'parse_mode' => $parse_mode,
                ]);
                $rxRichPhotoState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxRichPhotoResult) : (!empty($rxRichPhotoResult['ok']) ? 'confirmed' : 'failed');
                if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                    \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'banner_delivery', $rxRichPhotoState, [
                        'delivery_mode' => 'rich_photo_banner_native_keyboard',
                        'section' => (string) $section,
                        'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($rxRichPhotoResult),
                        'table_present' => !empty($rxDiagText['html_table']),
                    ], $rxRichPhotoState === 'confirmed' ? 'info' : 'warning');
                }
                if ($rxRichPhotoState === 'confirmed' || $rxRichPhotoState === 'unknown') {
                    if ($rxRichPhotoState === 'confirmed') {
                        \Faoxima\Telegram\Presentation\Decor::rx_decor_record($chat_id, $rxRichPhotoResult, 'screen', $section);
                    }
                    if ($rxDiagOwn) {
                        $GLOBALS['rx_rich_diag_last_result'] = $rxRichPhotoResult;
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish($rxRichPhotoState === 'confirmed' ? 'completed' : 'unknown', ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
                    }
                    return $rxRichPhotoResult;
                }
            }
            $rxBannerPhotoCaption = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxPrepareSafeMediaCaption') ? \Faoxima\Services\Delivery\ServiceDelivery::rxPrepareSafeMediaCaption((string) $text) : (string) $text;
            if ($rxBannerPhotoCaption === null && method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_plainText')) {
                $rxBannerPlain = trim((string) \Faoxima\Telegram\Rich\Markup::rx_richUi_plainText((string) $text, $parse_mode, true));
                if ($rxBannerPlain !== '' && mb_strlen($rxBannerPlain, 'UTF-8') <= 1024) {
                    $rxBannerPhotoCaption = htmlspecialchars($rxBannerPlain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $text = $rxBannerPhotoCaption;
                }
            }
            if ($rxBannerPhotoCaption !== null) {
                $photoPayload = [
                    'chat_id' => $chat_id,
                    'photo' => $fileId,
                    'caption' => $rxBannerPhotoCaption,
                    '_rx_rich_split' => false,
                    'reply_markup' => $keyboard,
                    'parse_mode' => $parse_mode,
                ];
                if (strtolower((string) $parse_mode) === 'html') {
                    $photoPayload['caption'] = (string) $text;
                    $photoResult = rxSendMediaWithCaption('sendphoto', $photoPayload, null, $bannerDelivery);
                    $rxPhotoStatus = $bannerDelivery['delivery_status'];
                } else {
                    $photoResult = telegram('sendphoto', $photoPayload);
                    $rxPhotoState = rxMediaResultState($photoResult);
                    $rxPhotoStatus = $rxPhotoState === 'confirmed' ? 'completed' : $rxPhotoState;
                }
                if ($rxPhotoStatus === 'completed') {
                    if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'banner_delivery', 'completed', [
                            'delivery_mode' => 'photo_banner',
                            'section' => (string) $section,
                            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($photoResult),
                            'table_present' => !empty($rxDiagText['html_table']),
                        ], 'info');
                    }
                    \Faoxima\Telegram\Presentation\Decor::rx_decor_record($chat_id, $photoResult, 'screen', $section);
                    if ($rxDiagOwn) {
                        $GLOBALS['rx_rich_diag_last_result'] = $photoResult;
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish('completed', ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
                    }
                    return $photoResult;
                }
                if ($rxPhotoStatus === 'unknown' || $rxPhotoStatus === 'partial') {
                    if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'banner_delivery', $rxPhotoStatus, [
                            'delivery_mode' => 'photo_banner',
                            'section' => (string) $section,
                            'result' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($photoResult),
                            'fallback_reason' => 'photo_banner_delivery_incomplete',
                        ], 'warning');
                    }
                    if ($rxDiagOwn) {
                        $GLOBALS['rx_rich_diag_last_result'] = $photoResult;
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish($rxPhotoStatus, ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
                    }
                    return $photoResult;
                }
            }
        }
        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_log')) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'selected', [
                'delivery_mode' => 'text_delivery',
                'send_call' => 'sendmessage',
                'section' => (string) $section,
                'text' => $rxDiagText,
            ], 'info');
        }
        $plainResult = sendmessage($chat_id, $text, $keyboard, $parse_mode);
        if ($rxDiagOwn) {
            $GLOBALS['rx_rich_diag_last_result'] = $plainResult;
            $rxPlainState = is_callable([\Faoxima\Services\Fulfillment\DeliveryState::class, 'deliveryResultState']) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($plainResult) : ((is_array($plainResult) && ($plainResult['ok'] ?? null) === true) ? 'confirmed' : 'failed');
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_finish($rxPlainState === 'confirmed' ? 'completed' : ($rxPlainState === 'unknown' ? 'unknown' : 'failed'), ['_function' => 'rx_send_banner_message', '_source_file' => 'src/Telegram/Media/SectionMedia.php']);
        }
        return $plainResult;
    }

    public static function rx_start_media_types()
    {
        return ['sticker', 'animation', 'emoji', 'custom_emoji'];
    }

    public static function rx_start_media_log($message)
    {
        $line = '[start_media] ' . $message;
        if (method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_dedup_error_log')) {
            \Faoxima\Services\Panels\PanelHttp::faoxima_dedup_error_log('start_media_' . md5($line), $line, 3600);
            return;
        }
        @error_log($line);
    }

    public static function rx_start_media_utf16_length($value)
    {
        if (!is_string($value) || $value === '') {
            return 0;
        }
        $encoded = @mb_convert_encoding($value, 'UTF-16LE', 'UTF-8');
        return is_string($encoded) ? intdiv(strlen($encoded), 2) : 0;
    }

    public static function rx_start_media_is_emoji_text($value)
    {
        if (!is_string($value) || $value === '' || strlen($value) > 128 || mb_strlen($value, 'UTF-8') > 40) {
            return false;
        }
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\s\x{00A0}\x{2000}-\x{200B}\x{2028}\x{2029}\x{3000}]/u', $value)) {
            return false;
        }
        $pictographic = '[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2190}-\x{21FF}\x{231A}-\x{23FF}\x{24C2}\x{25A0}-\x{25FF}\x{2600}-\x{27BF}\x{2900}-\x{297F}\x{2B00}-\x{2BFF}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1FAFF}]';
        $element = '(?:[0-9#*]\x{FE0F}?\x{20E3}|[\x{1F1E6}-\x{1F1FF}]{2}|' . $pictographic . '[\x{FE0E}\x{FE0F}]?[\x{1F3FB}-\x{1F3FF}]?(?:[\x{E0020}-\x{E007E}]+\x{E007F})?\x{FE0F}?)';
        $sequence = $element . '(?:\x{200D}' . $element . ')*';
        return (bool) preg_match('/^(?:' . $sequence . '){1,5}$/u', $value);
    }

    public static function rx_start_media_normalize_entities($entities, $text)
    {
        if (!is_array($entities) || !is_string($text) || $text === '') {
            return [];
        }
        $textLength = self::rx_start_media_utf16_length($text);
        $normalized = [];
        foreach ($entities as $entity) {
            if (!is_array($entity) || ($entity['type'] ?? '') !== 'custom_emoji') {
                continue;
            }
            $customEmojiId = trim((string)($entity['custom_emoji_id'] ?? ''));
            $offset = $entity['offset'] ?? null;
            $length = $entity['length'] ?? null;
            if ($customEmojiId === '' || !ctype_digit($customEmojiId) || strlen($customEmojiId) > 30) {
                continue;
            }
            if (!is_int($offset) && !(is_string($offset) && ctype_digit($offset))) {
                continue;
            }
            if (!is_int($length) && !(is_string($length) && ctype_digit($length))) {
                continue;
            }
            $offset = (int) $offset;
            $length = (int) $length;
            if ($offset < 0 || $length <= 0 || $offset + $length > $textLength) {
                continue;
            }
            $normalized[] = [
                'type' => 'custom_emoji',
                'offset' => $offset,
                'length' => $length,
                'custom_emoji_id' => $customEmojiId,
            ];
        }
        return $normalized;
    }

    public static function rx_start_media_is_valid_file_id($fileId)
    {
        return is_string($fileId) && $fileId !== '' && strlen($fileId) <= 512 && (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $fileId);
    }

    public static function rx_start_media_build_config($type, $fileId, $text, $entities)
    {
        $type = (string) $type;
        if (!in_array($type, self::rx_start_media_types(), true)) {
            return null;
        }
        if ($type === 'sticker' || $type === 'animation') {
            $fileId = trim((string) $fileId);
            return self::rx_start_media_is_valid_file_id($fileId)
                ? ['type' => $type, 'file_id' => $fileId, 'text' => '', 'entities' => []]
                : null;
        }
        $text = (string) $text;
        if ($type === 'emoji') {
            return self::rx_start_media_is_emoji_text($text)
                ? ['type' => 'emoji', 'file_id' => '', 'text' => $text, 'entities' => []]
                : null;
        }
        if (is_string($entities)) {
            $entities = json_decode($entities, true);
        }
        if (!self::rx_start_media_is_emoji_text($text)) {
            return null;
        }
        $normalizedEntities = self::rx_start_media_normalize_entities($entities, $text);
        if (empty($normalizedEntities)) {
            return null;
        }
        return ['type' => 'custom_emoji', 'file_id' => '', 'text' => $text, 'entities' => $normalizedEntities];
    }

    public static function rx_section_media_sections()
    {
        return [
            'start' => '🚀 استارت (/start)',
            'buy' => '🔐 خرید اشتراک',
            'extend' => '♻️ تمدید سرویس',
            'test' => '🔑 اکانت تست',
            'services' => '🛍 سرویس‌های من',
            'wallet' => '🏦 کیف پول + شارژ',
            'support' => '📞 پشتیبانی',
            'tariff' => '💵 تعرفه اشتراک',
            'help' => '📚 آموزش',
        ];
    }

    public static function rx_section_media_is_valid_section($section)
    {
        return is_string($section) && array_key_exists($section, self::rx_section_media_sections());
    }

    public static function rx_section_media_empty_row()
    {
        return ['status' => '0', 'type' => '', 'file_id' => '', 'text' => '', 'entities' => ''];
    }

    public static function rx_section_media_table_rows($refresh = false)
    {
        global $pdo;
        static $rows = null;
        if ($refresh) {
            $rows = null;
        }
        if ($rows !== null) {
            return $rows;
        }
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $rows = [];
        try {
            $stmt = $pdo->query("SELECT section_id, media_status, media_type, media_file_id, media_text, media_entities FROM section_media");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $record) {
                $rows[(string)$record['section_id']] = [
                    'status' => (string)($record['media_status'] ?? '0'),
                    'type' => (string)($record['media_type'] ?? ''),
                    'file_id' => (string)($record['media_file_id'] ?? ''),
                    'text' => (string)($record['media_text'] ?? ''),
                    'entities' => (string)($record['media_entities'] ?? ''),
                ];
            }
        } catch (\Throwable $tableError) {
            self::rx_start_media_log('section table read failed: ' . $tableError->getMessage());
        }
        return $rows;
    }

    public static function rx_section_media_row($section, $settingRow = null, $refresh = false)
    {
        if (!self::rx_section_media_is_valid_section($section)) {
            return null;
        }
        if ($section === 'start') {
            if ($settingRow === null) {
                $settingRow = $GLOBALS['setting'] ?? null;
            }
            if (!is_array($settingRow)) {
                return null;
            }
            return [
                'status' => (string)($settingRow['start_media_status'] ?? '0'),
                'type' => (string)($settingRow['start_media_type'] ?? ''),
                'file_id' => (string)($settingRow['start_media_file_id'] ?? ''),
                'text' => (string)($settingRow['start_media_text'] ?? ''),
                'entities' => (string)($settingRow['start_media_entities'] ?? ''),
            ];
        }
        $rows = self::rx_section_media_table_rows($refresh);
        return $rows[$section] ?? self::rx_section_media_empty_row();
    }

    public static function rx_section_media_stored_config($section, $settingRow = null, $refresh = false)
    {
        $row = self::rx_section_media_row($section, $settingRow, $refresh);
        if ($row === null) {
            return null;
        }
        return self::rx_start_media_build_config($row['type'], $row['file_id'], $row['text'], $row['entities']);
    }

    public static function rx_section_media_is_enabled($section, $settingRow = null, $refresh = false)
    {
        $row = self::rx_section_media_row($section, $settingRow, $refresh);
        return $row !== null && $row['status'] === '1';
    }

    public static function rx_section_media_active_config($section, $settingRow = null, $refresh = false)
    {
        if (!self::rx_section_media_is_enabled($section, $settingRow, $refresh)) {
            return null;
        }
        return self::rx_section_media_stored_config($section, $settingRow, $refresh);
    }

    public static function rx_start_media_stored_config($settingRow = null)
    {
        return self::rx_section_media_stored_config('start', $settingRow);
    }

    public static function rx_start_media_is_enabled($settingRow = null)
    {
        return self::rx_section_media_is_enabled('start', $settingRow);
    }

    public static function rx_start_media_active_config($settingRow = null)
    {
        return self::rx_section_media_active_config('start', $settingRow);
    }

    public static function rx_start_media_parse_message($message)
    {
        if (!is_array($message)) {
            return null;
        }
        if (isset($message['animation']) && is_array($message['animation'])) {
            return self::rx_start_media_build_config('animation', $message['animation']['file_id'] ?? '', '', []);
        }
        if (isset($message['sticker']) && is_array($message['sticker'])) {
            $sticker = $message['sticker'];
            if (($sticker['type'] ?? '') === 'custom_emoji') {
                $customEmojiId = method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiExtractCustomId')
                    ? \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiExtractCustomId(['sticker' => $sticker])
                    : '';
                $baseEmoji = trim((string)($sticker['emoji'] ?? ''));
                if ($customEmojiId === '' || !self::rx_start_media_is_emoji_text($baseEmoji)) {
                    return null;
                }
                return self::rx_start_media_build_config('custom_emoji', '', $baseEmoji, [[
                    'type' => 'custom_emoji',
                    'offset' => 0,
                    'length' => self::rx_start_media_utf16_length($baseEmoji),
                    'custom_emoji_id' => $customEmojiId,
                ]]);
            }
            return self::rx_start_media_build_config('sticker', $sticker['file_id'] ?? '', '', []);
        }
        foreach (['photo', 'video', 'video_note', 'document', 'audio', 'voice', 'contact', 'location', 'venue', 'poll', 'dice'] as $unsupportedField) {
            if (isset($message[$unsupportedField])) {
                return null;
            }
        }
        if (!isset($message['text']) || !is_string($message['text']) || $message['text'] === '') {
            return null;
        }
        $rawText = $message['text'];
        $entities = (isset($message['entities']) && is_array($message['entities'])) ? $message['entities'] : [];
        $hasCustomEmoji = method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiExtractCustomId')
            ? \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiExtractCustomId(['entities' => $entities]) !== ''
            : false;
        if ($hasCustomEmoji) {
            return self::rx_start_media_build_config('custom_emoji', '', $rawText, $entities);
        }
        return self::rx_start_media_build_config('emoji', '', trim($rawText), []);
    }

    public static function rx_start_media_dispatch($chat_id, array $config)
    {
        $type = (string)($config['type'] ?? '');
        if ($type === 'sticker') {
            return telegram('sendSticker', [
                'chat_id' => $chat_id,
                'sticker' => $config['file_id'],
            ]);
        }
        if ($type === 'animation') {
            return telegram('sendAnimation', [
                'chat_id' => $chat_id,
                'animation' => $config['file_id'],
            ]);
        }
        if ($type === 'emoji') {
            return telegram('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $config['text'],
                '_rx_already_transformed' => true,
            ]);
        }
        if ($type === 'custom_emoji') {
            return telegram('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $config['text'],
                'entities' => json_encode(array_values($config['entities']), JSON_UNESCAPED_UNICODE),
                '_rx_already_transformed' => true,
            ]);
        }
        return ['ok' => false, 'description' => 'unsupported start media type'];
    }

    public static function rx_section_media_send($section, $chat_id)
    {
        try {
            $config = self::rx_section_media_active_config($section);
            if ($config === null) {
                return false;
            }
            $result = self::rx_start_media_dispatch($chat_id, $config);
            if (is_array($result) && !empty($result['ok'])) {
                \Faoxima\Telegram\Presentation\Decor::rx_decor_record($chat_id, $result, 'separate', (string) $section);
                return true;
            }
            $description = is_array($result) ? (string)($result['description'] ?? 'unknown error') : 'no response';
            self::rx_start_media_log($section . ' send ' . $config['type'] . ' failed: ' . $description);
        } catch (\Throwable $startMediaError) {
            self::rx_start_media_log($section . ' send exception: ' . $startMediaError->getMessage());
        }
        return false;
    }

    public static function rx_send_start_media($chat_id)
    {
        return self::rx_section_media_send('start', $chat_id);
    }

    public static function rx_section_media_enter($section, $chat_id, &$datain, $message_id = 0, array $entryCallbacks = [], array $editCallbacks = [])
    {
        static $handled = [];
        try {
            if (isset($handled[$section])) {
                return false;
            }
            $callback = is_string($datain) ? $datain : '';
            if ($callback !== '' && !in_array($callback, $entryCallbacks, true)) {
                return false;
            }
            if (self::rx_section_media_active_config($section) === null) {
                return false;
            }
            $handled[$section] = true;
            if (!self::rx_section_media_send($section, $chat_id)) {
                return false;
            }
            if ($callback !== '' && in_array($callback, $editCallbacks, true) && (int)$message_id > 0) {
                deletemessage($chat_id, $message_id);
                $datain = 'rx_media_entry';
            }
            return true;
        } catch (\Throwable $entryError) {
            self::rx_start_media_log($section . ' entry hook failed: ' . $entryError->getMessage());
        }
        return false;
    }

    public static function rx_buy_banner_file_id()
    {
        global $setting;
        $status = is_array($setting) ? ($setting['banner_buy_status'] ?? '0') : '0';
        $fileId = is_array($setting) ? trim((string)($setting['banner_buy_file_id'] ?? '')) : '';
        if ($status != '1' || $fileId === '') {
            return '';
        }
        try {
            if (self::rx_section_media_active_config('buy') !== null) {
                return '';
            }
        } catch (\Throwable $buyMediaError) {
            self::rx_start_media_log('buy exclusion check failed: ' . $buyMediaError->getMessage());
        }
        return $fileId;
    }

    public static function rx_send_buy_first_step($chat_id, $message_id, $datain, $text, $keyboard, $isCallback)
    {
        $fileId = self::rx_buy_banner_file_id();
        if ($fileId !== '') {
            $rxBuyPhotoCaption = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxPrepareSafeMediaCaption') ? \Faoxima\Services\Delivery\ServiceDelivery::rxPrepareSafeMediaCaption((string) $text) : (string) $text;
            if ($rxBuyPhotoCaption !== null) {
                $photoResult = rxSendMediaWithCaption('sendphoto', [
                    'chat_id' => $chat_id,
                    'photo' => $fileId,
                    'caption' => (string) $text,
                    '_rx_rich_split' => false,
                    'reply_markup' => $keyboard,
                    'parse_mode' => 'HTML',
                ], null, $buyDelivery);
                if ($buyDelivery['delivery_status'] === 'completed') {
                    if ($isCallback) {
                        deletemessage($chat_id, $message_id);
                    }
                    \Faoxima\Telegram\Presentation\Decor::rx_decor_record($chat_id, $photoResult, 'screen', 'buy');
                    return $photoResult;
                }
                if (in_array($buyDelivery['delivery_status'], ['partial', 'unknown'], true)) {
                    return $photoResult;
                }
            }
        }
        if ($isCallback) {
            return Editmessagetext($chat_id, $message_id, $text, $keyboard);
        }
        return sendmessage($chat_id, $text, $keyboard, 'HTML');
    }
}
