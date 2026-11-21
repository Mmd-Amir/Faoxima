<?php

namespace Faoxima\Telegram\Rich;

final class Validation
{
    public static function rx_richUi_validateInlineText($text, int $depth = 0): bool
    {
        if ($depth > 16) {
            return false;
        }
        if (is_string($text)) {
            return true;
        }
        if (!is_array($text)) {
            return false;
        }
        if (isset($text['type'])) {
            $type = (string) $text['type'];
            $inlineTypes = ['bold', 'italic', 'underline', 'strikethrough', 'code', 'marked', 'subscript', 'superscript', 'spoiler', 'url', 'custom_emoji', 'date_time', 'mathematical_expression'];
            if (!in_array($type, $inlineTypes, true)) {
                return false;
            }
            if ($type === 'custom_emoji') {
                return isset($text['custom_emoji_id']) && ctype_digit((string) $text['custom_emoji_id']);
            }
            if ($type === 'date_time') {
                return isset($text['unix_time']) && is_numeric($text['unix_time']);
            }
            if ($type === 'url') {
                return isset($text['url'])
                    && preg_match('~^https?://~i', (string) $text['url']) === 1
                    && array_key_exists('text', $text)
                    && self::rx_richUi_validateInlineText($text['text'], $depth + 1);
            }
            if ($type === 'mathematical_expression') {
                return isset($text['expression']) && is_string($text['expression']);
            }
            return array_key_exists('text', $text) && self::rx_richUi_validateInlineText($text['text'], $depth + 1);
        }
        foreach ($text as $part) {
            if (!self::rx_richUi_validateInlineText($part, $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    public static function rx_richUi_validateBlockTree(array $blocks, int $depth = 0): array
    {
        $result = ['valid' => true, 'reason' => '', 'blocks' => 0, 'tables' => 0, 'rows' => 0, 'max_columns' => 0];
        if ($depth > 16) {
            $result['valid'] = false;
            $result['reason'] = 'block_depth_limit';
            return $result;
        }
        if (!$blocks) {
            $result['valid'] = false;
            $result['reason'] = 'blocks_empty';
            return $result;
        }
        $blockTypes = ['paragraph', 'heading', 'pre', 'blockquote', 'expandable_blockquote', 'pullquote', 'table', 'buttons', 'list', 'divider', 'footer', 'photo', 'video', 'animation', 'audio', 'document', 'voice_note'];
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['type']) || !is_string($block['type']) || $block['type'] === '') {
                $result['valid'] = false;
                $result['reason'] = 'block_invalid';
                return $result;
            }
            $type = $block['type'];
            if (!in_array($type, $blockTypes, true)) {
                $result['valid'] = false;
                $result['reason'] = 'block_type_unsupported';
                return $result;
            }
            $result['blocks']++;
            if (isset($block['blocks'])) {
                if (!is_array($block['blocks'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'nested_blocks_invalid';
                    return $result;
                }
                $nested = self::rx_richUi_validateBlockTree($block['blocks'], $depth + 1);
                if (!$nested['valid']) {
                    return $nested;
                }
                $result['blocks'] += $nested['blocks'];
                $result['tables'] += $nested['tables'];
                $result['rows'] += $nested['rows'];
                $result['max_columns'] = max($result['max_columns'], (int) $nested['max_columns']);
            }
            if ($type === 'list') {
                if (!isset($block['items']) || !is_array($block['items']) || !$block['items']) {
                    $result['valid'] = false;
                    $result['reason'] = 'list_items_invalid';
                    return $result;
                }
                foreach ($block['items'] as $item) {
                    if (!is_array($item) || !isset($item['blocks']) || !is_array($item['blocks'])) {
                        $result['valid'] = false;
                        $result['reason'] = 'list_item_invalid';
                        return $result;
                    }
                    $nested = self::rx_richUi_validateBlockTree($item['blocks'], $depth + 1);
                    if (!$nested['valid']) {
                        return $nested;
                    }
                    $result['blocks'] += $nested['blocks'];
                    $result['tables'] += $nested['tables'];
                    $result['rows'] += $nested['rows'];
                    $result['max_columns'] = max($result['max_columns'], (int) $nested['max_columns']);
                }
            }
            if ($type === 'table') {
                if (!isset($block['cells']) || !is_array($block['cells']) || !$block['cells']) {
                    $result['valid'] = false;
                    $result['reason'] = 'table_cells_missing';
                    return $result;
                }
                $rowCount = 0;
                foreach ($block['cells'] as $row) {
                    if (!is_array($row) || !$row) {
                        $result['valid'] = false;
                        $result['reason'] = 'table_row_invalid';
                        return $result;
                    }
                    $rowCount++;
                    $columnCount = 0;
                    foreach ($row as $cell) {
                        if (!is_array($cell) || !array_key_exists('text', $cell) || !self::rx_richUi_validateInlineText($cell['text'])) {
                            $result['valid'] = false;
                            $result['reason'] = 'table_cell_invalid';
                            return $result;
                        }
                        if (isset($cell['align']) && !in_array($cell['align'], ['left', 'center', 'right'], true)) {
                            $result['valid'] = false;
                            $result['reason'] = 'table_cell_alignment_invalid';
                            return $result;
                        }
                        if (isset($cell['valign']) && !in_array($cell['valign'], ['top', 'middle', 'bottom'], true)) {
                            $result['valid'] = false;
                            $result['reason'] = 'table_cell_vertical_alignment_invalid';
                            return $result;
                        }
                        $colspan = isset($cell['colspan']) ? (int) $cell['colspan'] : 1;
                        $rowspan = isset($cell['rowspan']) ? (int) $cell['rowspan'] : 1;
                        if ($colspan < 1 || $colspan > 20) {
                            $result['valid'] = false;
                            $result['reason'] = 'table_colspan_invalid';
                            return $result;
                        }
                        if ($rowspan < 1 || $rowspan > count($block['cells'])) {
                            $result['valid'] = false;
                            $result['reason'] = 'table_rowspan_invalid';
                            return $result;
                        }
                        $columnCount += $colspan;
                    }
                    if ($columnCount > 20) {
                        $result['valid'] = false;
                        $result['reason'] = 'table_column_limit_exceeded';
                        return $result;
                    }
                    $result['max_columns'] = max($result['max_columns'], $columnCount);
                }
                if ($rowCount < 1) {
                    $result['valid'] = false;
                    $result['reason'] = 'table_rows_missing';
                    return $result;
                }
                if (isset($block['caption']) && !self::rx_richUi_validateInlineText($block['caption'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'table_caption_invalid';
                    return $result;
                }
                $result['tables']++;
                $result['rows'] += $rowCount;
            }
            if ($type === 'buttons') {
                if (!isset($block['buttons']) || !is_array($block['buttons']) || !$block['buttons']) {
                    $result['valid'] = false;
                    $result['reason'] = 'buttons_invalid';
                    return $result;
                }
                foreach ($block['buttons'] as $button) {
                    if (!is_array($button) || !isset($button['text']) || !self::rx_richUi_validateInlineText($button['text'])) {
                        $result['valid'] = false;
                        $result['reason'] = 'button_text_invalid';
                        return $result;
                    }
                    $hasAction = false;
                    if (isset($button['callback_data'])) {
                        $hasAction = true;
                        if (!is_string($button['callback_data']) || $button['callback_data'] === '' || strlen($button['callback_data']) > 64) {
                            $result['valid'] = false;
                            $result['reason'] = 'button_callback_invalid';
                            return $result;
                        }
                    }
                    foreach (['url', 'switch_inline_query', 'switch_inline_query_current_chat'] as $actionKey) {
                        if (isset($button[$actionKey])) {
                            $hasAction = true;
                            if (!is_string($button[$actionKey]) || $button[$actionKey] === '') {
                                $result['valid'] = false;
                                $result['reason'] = 'button_action_invalid';
                                return $result;
                            }
                        }
                    }
                    foreach (['web_app', 'login_url', 'copy_text'] as $actionKey) {
                        if (isset($button[$actionKey])) {
                            $hasAction = true;
                            if (!is_array($button[$actionKey])) {
                                $result['valid'] = false;
                                $result['reason'] = 'button_action_invalid';
                                return $result;
                            }
                            $actionField = $actionKey === 'copy_text' ? 'text' : 'url';
                            if (!isset($button[$actionKey][$actionField]) || !is_string($button[$actionKey][$actionField]) || $button[$actionKey][$actionField] === '') {
                                $result['valid'] = false;
                                $result['reason'] = 'button_action_invalid';
                                return $result;
                            }
                        }
                    }
                    if (!$hasAction) {
                        $result['valid'] = false;
                        $result['reason'] = 'button_action_missing';
                        return $result;
                    }
                    if (isset($button['style']) && !in_array($button['style'], ['primary', 'success', 'danger'], true)) {
                        $result['valid'] = false;
                        $result['reason'] = 'button_style_invalid';
                        return $result;
                    }
                }
            }
            if (in_array($type, ['photo', 'video', 'animation', 'audio', 'document', 'voice_note'], true)) {
                $source = $block[$type] ?? null;
                if (!is_array($source) || empty($source['media'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'media_source_invalid';
                    return $result;
                }
            }
            if (in_array($type, ['paragraph', 'heading', 'footer', 'pullquote', 'expandable_blockquote'], true)) {
                if (!array_key_exists('text', $block) || !self::rx_richUi_validateInlineText($block['text'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'block_text_invalid';
                    return $result;
                }
            }
            if ($type === 'heading') {
                if (!isset($block['size']) || !is_int($block['size']) || $block['size'] < 1 || $block['size'] > 6) {
                    $result['valid'] = false;
                    $result['reason'] = 'heading_size_invalid';
                    return $result;
                }
            }
            if ($type === 'blockquote' && !isset($block['blocks']) && !array_key_exists('text', $block)) {
                $result['valid'] = false;
                $result['reason'] = 'blockquote_content_missing';
                return $result;
            }
            if ($type === 'pre') {
                if (!isset($block['text']) || !is_string($block['text'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'pre_text_invalid';
                    return $result;
                }
                if (isset($block['language']) && (!is_string($block['language']) || !preg_match('~^[a-z0-9_+-]{1,32}$~i', $block['language']))) {
                    $result['valid'] = false;
                    $result['reason'] = 'pre_language_invalid';
                    return $result;
                }
            }
        }
        return $result;
    }

    public static function rx_richUi_validateGeneralRichPayload(array $richMessage, array $context = []): array
    {
        $result = ['valid' => true, 'reason' => '', 'mode' => 'html', 'blocks' => 0, 'tables' => 0, 'rows' => 0, 'max_columns' => 0, 'json_ok' => false, 'json_length' => 0, 'visible_length' => 0, 'has_table' => false, 'general' => true];
        $method = strtolower(trim((string) ($context['method'] ?? '')));
        if ($method !== '' && !in_array($method, ['sendrichmessage', 'editmessagetext'], true)) {
            $result['valid'] = false;
            $result['reason'] = 'rich_method_unsupported';
            return $result;
        }
        $hasBlocks = isset($richMessage['blocks']);
        $hasHtml = isset($richMessage['html']);
        $hasMedia = isset($richMessage['media']);
        if (isset($richMessage['entities']) || isset($richMessage['caption_entities'])) {
            $result['valid'] = false;
            $result['reason'] = 'rich_entities_unsupported';
            return $result;
        }
        if (!$hasBlocks && !$hasHtml) {
            $result['valid'] = false;
            $result['reason'] = 'rich_content_missing';
            return $result;
        }
        if ($hasBlocks && $hasHtml) {
            $result['valid'] = false;
            $result['reason'] = 'rich_content_ambiguous';
            return $result;
        }
        if ($hasBlocks) {
            if (!is_array($richMessage['blocks']) || !$richMessage['blocks']) {
                $result['valid'] = false;
                $result['reason'] = 'rich_blocks_empty';
                return $result;
            }
            $tree = self::rx_richUi_validateBlockTree($richMessage['blocks']);
            $result['blocks'] = (int) $tree['blocks'];
            $result['tables'] = (int) $tree['tables'];
            $result['rows'] = (int) $tree['rows'];
            $result['max_columns'] = (int) $tree['max_columns'];
            $result['mode'] = 'blocks';
            $result['has_table'] = $tree['tables'] > 0 || \Faoxima\Telegram\Rich\Markup::rx_richUi_richHasTable($richMessage['blocks']);
            if (!$tree['valid']) {
                $result['valid'] = false;
                $result['reason'] = $tree['reason'];
                return $result;
            }
        }
        if ($hasHtml) {
            if (!is_string($richMessage['html']) || trim($richMessage['html']) === '') {
                $result['valid'] = false;
                $result['reason'] = 'rich_html_invalid';
                return $result;
            }
            if (!mb_check_encoding($richMessage['html'], 'UTF-8')) {
                $result['valid'] = false;
                $result['reason'] = 'rich_html_encoding_invalid';
                return $result;
            }
            if (preg_match('~</?table\b~i', $richMessage['html']) === 1) {
                $result['valid'] = false;
                $result['reason'] = 'rich_html_table_unsupported';
                return $result;
            }
            if (preg_match('~<\s*(script|style|iframe|object|embed|form|input|textarea|select)\b~i', $richMessage['html'])) {
                $result['valid'] = false;
                $result['reason'] = 'rich_html_tag_unsupported';
                return $result;
            }
            $result['mode'] = 'html';
        }
        if ($hasMedia) {
            if (!is_array($richMessage['media']) || !$richMessage['media']) {
                $result['valid'] = false;
                $result['reason'] = 'rich_media_invalid';
                return $result;
            }
            foreach ($richMessage['media'] as $mediaItem) {
                if (!is_array($mediaItem) || !isset($mediaItem['media']) || !is_array($mediaItem['media'])) {
                    $result['valid'] = false;
                    $result['reason'] = 'rich_media_item_invalid';
                    return $result;
                }
                $mediaType = (string) ($mediaItem['media']['type'] ?? '');
                $mediaValue = $mediaItem['media']['media'] ?? null;
                if ($mediaType === '' || !is_string($mediaValue) || $mediaValue === '') {
                    $result['valid'] = false;
                    $result['reason'] = 'rich_media_item_invalid';
                    return $result;
                }
            }
        }
        if (isset($richMessage['is_rtl']) && !is_bool($richMessage['is_rtl'])) {
            $result['valid'] = false;
            $result['reason'] = 'rtl_metadata_invalid';
            return $result;
        }
        $encoded = json_encode($richMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            $result['valid'] = false;
            $result['reason'] = 'json_encode_failed';
            return $result;
        }
        $result['json_ok'] = true;
        $result['json_length'] = strlen($encoded);
        $roundTrip = json_decode($encoded, true);
        if (!is_array($roundTrip)) {
            $result['valid'] = false;
            $result['reason'] = 'json_roundtrip_failed';
            return $result;
        }
        if ($hasBlocks && (!isset($roundTrip['blocks']) || !is_array($roundTrip['blocks']) || count($roundTrip['blocks']) !== count($richMessage['blocks']))) {
            $result['valid'] = false;
            $result['reason'] = 'json_roundtrip_failed';
            return $result;
        }
        if ($hasHtml && (!isset($roundTrip['html']) || !is_string($roundTrip['html']) || $roundTrip['html'] !== $richMessage['html'])) {
            $result['valid'] = false;
            $result['reason'] = 'json_roundtrip_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['reason'] = 'valid';
        return $result;
    }

    public static function rx_richUi_validateRichPayload(array $richMessage, array $context = []): array
    {
        $general = self::rx_richUi_validateGeneralRichPayload($richMessage, $context);
        if (empty($general['valid'])) {
            return $general;
        }
        $result = $general;
        $text = isset($context['text']) && is_string($context['text']) ? $context['text'] : '';
        $hasTable = !empty($context['has_table']) || !empty($general['has_table']) || ($text !== '' && preg_match('~<table\b~i', $text) === 1);
        $result['has_table'] = $hasTable;
        if (!$hasTable) {
            return $result;
        }
        $result['valid'] = false;
        if (!empty($context['media_count'])) {
            $result['reason'] = 'media_caption_table_unsupported';
            return $result;
        }
        if (!isset($richMessage['blocks']) || !is_array($richMessage['blocks']) || !$richMessage['blocks']) {
            $result['reason'] = 'structured_blocks_missing';
            return $result;
        }
        $tree = self::rx_richUi_validateBlockTree($richMessage['blocks']);
        $result['blocks'] = (int) $tree['blocks'];
        $result['tables'] = (int) $tree['tables'];
        $result['rows'] = (int) $tree['rows'];
        $result['max_columns'] = (int) $tree['max_columns'];
        if (!$tree['valid']) {
            $result['reason'] = $tree['reason'];
            return $result;
        }
        if ($tree['tables'] < 1) {
            $result['reason'] = 'table_block_missing';
            return $result;
        }
        if (array_key_exists('edit_target_known', $context) && !empty($context['edit_target_known']) && array_key_exists('target_is_rich', $context) && $context['target_is_rich'] === false) {
            $result['reason'] = 'edit_target_not_rich';
            return $result;
        }
        $visible = \Faoxima\Telegram\Rich\Markup::rx_richUi_visibleRichText($richMessage['blocks']);
        $result['visible_length'] = method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_utf16Length') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_utf16Length($visible) : strlen($visible);
        if (mb_strlen($visible, 'UTF-8') > 16000) {
            $result['reason'] = 'text_length_exceeds_limit';
            return $result;
        }
        if (isset($richMessage['is_rtl']) && !is_bool($richMessage['is_rtl'])) {
            $result['reason'] = 'rtl_metadata_invalid';
            return $result;
        }
        $encoded = json_encode($richMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            $result['reason'] = 'json_encode_failed';
            return $result;
        }
        $result['json_ok'] = true;
        $result['json_length'] = strlen($encoded);
        $roundTrip = json_decode($encoded, true);
        if (!is_array($roundTrip) || !isset($roundTrip['blocks']) || !is_array($roundTrip['blocks']) || count($roundTrip['blocks']) !== count($richMessage['blocks'])) {
            $result['reason'] = 'json_roundtrip_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['reason'] = 'valid';
        return $result;
    }

    public static function rx_richUi_isFallbackError($result): bool
    {
        if (!is_array($result) || ($result['ok'] ?? null) !== false || !empty($result['_rx_invalid_response'])) {
            return false;
        }
        $code = (int) ($result['error_code'] ?? 0);
        $desc = strtolower((string) ($result['description'] ?? ''));
        if ($code < 400 || $code === 429 || $code >= 500) {
            return false;
        }
        foreach (['not modified', 'not found', 'blocked', 'deactivated', 'chat not found'] as $needle) {
            if (strpos($desc, $needle) !== false) {
                return false;
            }
        }
        return true;
    }
}
