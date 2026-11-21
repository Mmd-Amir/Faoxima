<?php

namespace Faoxima\Telegram\Rich;

use CURLFile;
use Throwable;

final class Diagnostics
{
    public static function rx_richDiag_enabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $enabled = true;
        $raw = null;
        if (defined('RX_RICH_MESSAGE_DEBUG')) {
            $raw = constant('RX_RICH_MESSAGE_DEBUG');
        }
        $env = getenv('RX_RICH_MESSAGE_DEBUG');
        if (is_string($env) && $env !== '') {
            $raw = $env;
        }
        if ($raw !== null) {
            $normalized = strtolower(trim((string) $raw));
            if (in_array($normalized, ['0', 'off', 'false', 'no', 'disabled'], true)) {
                $enabled = false;
            }
        }
        return $enabled;
    }

    public static function rx_richDiag_path(): string
    {
        static $path = null;
        if ($path === null) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'rich_message_debug.log';
        }
        return $path;
    }

    public static function rx_richDiag_requestId(): string
    {
        static $id = null;
        if ($id === null) {
            try {
                $id = bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                $id = substr(hash('sha256', uniqid('', true) . getmypid()), 0, 16);
            }
        }
        return $id;
    }

    public static function rx_richDiag_traceId(): string
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        if (is_array($state) && !empty($state['trace_id'])) {
            return (string) $state['trace_id'];
        }
        try {
            $trace = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $trace = substr(hash('sha256', uniqid('', true) . getmypid()), 0, 24);
        }
        $stack = (is_array($state) && isset($state['context_stack']) && is_array($state['context_stack'])) ? $state['context_stack'] : [];
        $GLOBALS['rx_rich_diag_state'] = [
            'trace_id' => $trace,
            'message_key' => null,
            'started_at' => microtime(true),
            'message_index' => (int) ($GLOBALS['rx_rich_diag_seq'] ?? 0),
            'context_stack' => $stack,
            'context_depth' => count($stack),
        ];
        return $trace;
    }

    public static function rx_richDiag_active(): bool
    {
        if (!self::rx_richDiag_enabled()) {
            return false;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        return is_array($state) && !empty($state['trace_id']);
    }

    public static function rx_richDiag_utf16Length($text): int
    {
        if (!is_string($text) || $text === '') {
            return 0;
        }
        $encoded = @mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        return is_string($encoded) ? intdiv(strlen($encoded), 2) : 0;
    }

    public static function rx_richDiag_caller(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $root = str_replace('\\', '/', dirname(__DIR__, 3)) . '/';
        foreach ($frames as $index => $frame) {
            if ($index === 0) {
                continue;
            }
            $function = isset($frame['function']) ? (string) $frame['function'] : '';
            if (strncmp($function, 'rx_richDiag_', 12) === 0) {
                continue;
            }
            $file = isset($frame['file']) ? str_replace('\\', '/', (string) $frame['file']) : '';
            if ($file === '') {
                continue;
            }
            if (strpos($file, $root) === 0) {
                $file = substr($file, strlen($root));
            }
            return [$file, $function === '' ? 'global' : $function];
        }
        return ['', ''];
    }

    public static function rx_richDiag_sanitize($value, int $depth = 0)
    {
        if ($depth > 3) {
            return '[depth-limit]';
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/(token|secret|password|passwd|apikey|api_key|authorization|session|cookie|private|card|iban|wallet)/i', $key)) {
                    $out[$key] = '[redacted]';
                    continue;
                }
                $out[$key] = self::rx_richDiag_sanitize($item, $depth + 1);
            }
            return $out;
        }
        if (is_object($value)) {
            return '[object:' . get_class($value) . ']';
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        $text = (string) $value;
        $text = (string) preg_replace('~bot\d+:[A-Za-z0-9_-]{10,}~', 'bot[redacted]', $text);
        $text = (string) preg_replace('~[a-z][a-z0-9+.-]*://[^\s<>"]+~i', '[redacted-url]', $text);
        if (mb_strlen($text, 'UTF-8') > 300) {
            $text = mb_substr($text, 0, 300, 'UTF-8') . '…';
        }
        return $text;
    }

    public static function rx_richDiag_text($text): array
    {
        if (!is_string($text)) {
            return ['present' => false];
        }
        $result = [
            'present' => trim($text) !== '',
            'length' => strlen($text),
            'utf16_length' => self::rx_richDiag_utf16Length($text),
            'sha256' => substr(hash('sha256', $text), 0, 16),
            'html_markup' => (bool) preg_match('~<[a-z][^>]*>~i', $text),
            'html_table' => (bool) preg_match('~<table\b~i', $text),
            'table_count' => (int) preg_match_all('~<table\b~i', $text),
            'row_count' => (int) preg_match_all('~<tr\b~i', $text),
            'max_columns' => 0,
            'tg_emoji_tags' => (int) preg_match_all('~<tg-emoji\b~i', $text),
            'premium_emoji_ids' => 0,
            'variables' => (int) preg_match_all('~\{[a-z0-9_]+\}~i', $text),
            'rtl_marker' => (bool) preg_match('~<!--\s*rxrtl:[01]\s*-->~i', $text),
            'escaped_markup' => (int) preg_match_all('~&lt;/?(?:table|tr|td|th|tg-emoji|p|b)\b~i', $text),
            'long_digit_runs' => (int) preg_match_all('~(?<!\d)\d{14,}(?!\d)~', $text),
            'emoji_attr_fragments' => (int) preg_match_all('~emoji-id\s*=~i', $text),
        ];
        if (preg_match_all('~<tr\b[^>]*>([\s\S]*?)</tr>~i', $text, $rows)) {
            foreach ($rows[1] as $row) {
                $columns = (int) preg_match_all('~<t[hd]\b~i', $row);
                if ($columns > $result['max_columns']) {
                    $result['max_columns'] = $columns;
                }
            }
        }
        if (preg_match_all('~<tg-emoji\b[^>]*emoji-id="(\d+)"~i', $text, $ids)) {
            $result['premium_emoji_ids'] = count(array_unique($ids[1]));
        }
        return $result;
    }

    public static function rx_richDiag_structure($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return ['available' => false];
        }
        $encoded = 'json';
        $json = $raw;
        if (strncmp($raw, 'gz:', 3) === 0) {
            $encoded = 'gz';
            $json = '';
            if (function_exists('gzuncompress')) {
                $decoded = @gzuncompress((string) base64_decode(substr($raw, 3), true));
                if (is_string($decoded)) {
                    $json = $decoded;
                }
            }
        }
        $info = [
            'available' => true,
            'encoded' => $encoded,
            'stored_length' => strlen($raw),
            'sha256' => substr(hash('sha256', $raw), 0, 16),
            'decoded' => false,
            'blocks' => 0,
            'table_blocks' => 0,
            'table_rows' => 0,
            'max_columns' => 0,
            'is_rtl' => false,
            'html_length' => 0,
        ];
        $data = $json === '' ? null : json_decode($json, true);
        if (!is_array($data)) {
            return $info;
        }
        $info['decoded'] = true;
        $blocks = isset($data['blocks']) && is_array($data['blocks']) ? $data['blocks'] : [];
        $info['blocks'] = count($blocks);
        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'table') {
                continue;
            }
            $info['table_blocks']++;
            $info['table_rows'] += isset($block['cells']) && is_array($block['cells']) ? count($block['cells']) : 0;
            if (isset($block['cells']) && is_array($block['cells'])) {
                foreach ($block['cells'] as $row) {
                    $columns = is_array($row) ? count($row) : 0;
                    if ($columns > $info['max_columns']) {
                        $info['max_columns'] = $columns;
                    }
                }
            }
        }
        $info['is_rtl'] = !empty($data['is_rtl']);
        $info['html_length'] = isset($data['html']) && is_string($data['html']) ? strlen($data['html']) : 0;
        return $info;
    }

    public static function rx_richDiag_payload(array $datas): array
    {
        $allowed = ['text', 'caption', 'photo', 'document', 'video', 'animation', 'audio', 'voice', 'media', 'parse_mode', 'reply_markup', 'rich_message', 'entities', 'caption_entities', 'message_id', 'inline_message_id', 'message_thread_id'];
        $present = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $datas)) {
                $present[] = $key;
            }
        }
        $out = ['keys' => $present];
        foreach (['text', 'caption'] as $field) {
            if (isset($datas[$field]) && is_string($datas[$field])) {
                $out[$field] = self::rx_richDiag_text($datas[$field]);
            }
        }
        if (isset($datas['rich_message'])) {
            $raw = is_string($datas['rich_message']) ? $datas['rich_message'] : '';
            $decoded = $raw === '' ? null : json_decode($raw, true);
            $out['rich_message'] = [
                'length' => strlen($raw),
                'sha256' => substr(hash('sha256', $raw), 0, 16),
                'json_ok' => is_array($decoded),
                'mode' => is_array($decoded) ? (isset($decoded['blocks']) ? 'blocks' : 'html') : 'unknown',
                'blocks' => is_array($decoded) && isset($decoded['blocks']) && is_array($decoded['blocks']) ? count($decoded['blocks']) : 0,
            ];
        }
        $out['parse_mode'] = (string) ($datas['parse_mode'] ?? '');
        $out['has_reply_markup'] = !empty($datas['reply_markup']);
        $out['has_entities'] = !empty($datas['entities']) || !empty($datas['caption_entities']);
        $mediaKey = '';
        foreach (['photo', 'document', 'video', 'animation', 'audio', 'voice', 'media'] as $candidate) {
            if (isset($datas[$candidate])) {
                $mediaKey = $candidate;
                break;
            }
        }
        $out['media_kind'] = $mediaKey;
        $out['media_is_upload'] = $mediaKey !== '' && ($datas[$mediaKey] instanceof CURLFile);
        return $out;
    }

    public static function rx_richDiag_result($result): array
    {
        if (!is_array($result)) {
            return ['type' => gettype($result)];
        }
        return [
            'ok' => !empty($result['ok']),
            'error_code' => (int) ($result['error_code'] ?? 0),
            'description' => (string) ($result['description'] ?? ''),
            'message_id' => (int) ($result['result']['message_id'] ?? 0),
            'has_rich_message' => isset($result['result']['rich_message']),
            'fallback_marker' => !empty($result['__fallback']),
        ];
    }

    public static function rx_richDiag_contextStack(): array
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        return (is_array($state) && isset($state['context_stack']) && is_array($state['context_stack'])) ? $state['context_stack'] : [];
    }

    public static function rx_richDiag_contextDepth(): int
    {
        return count(self::rx_richDiag_contextStack());
    }

    public static function rx_richDiag_currentKey(): string
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        $key = is_array($state) ? ($state['message_key'] ?? null) : null;
        return is_string($key) && $key !== '' ? $key : 'unknown';
    }

    public static function rx_richDiag_targetRichMeta(): array
    {
        $callbackMessage = null;
        if (isset($GLOBALS['update']['callback_query']['message']) && is_array($GLOBALS['update']['callback_query']['message'])) {
            $callbackMessage = $GLOBALS['update']['callback_query']['message'];
        }
        $raw = null;
        $blocks = 0;
        $tables = 0;
        if (is_array($callbackMessage) && isset($callbackMessage['rich_message'])) {
            $rich = $callbackMessage['rich_message'];
            if (is_string($rich)) {
                $raw = $rich;
                $decoded = json_decode($rich, true);
            } elseif (is_array($rich)) {
                $decoded = $rich;
                $raw = (string) json_encode($rich, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $decoded = null;
            }
            if (is_array($decoded) && isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $blocks = count($decoded['blocks']);
                foreach ($decoded['blocks'] as $block) {
                    if (is_array($block) && ($block['type'] ?? '') === 'table') {
                        $tables++;
                    }
                }
            }
        }
        return [
            'target_is_rich' => $raw !== null,
            'target_rich_digest' => $raw !== null ? substr(hash('sha256', $raw), 0, 16) : null,
            'target_rich_blocks' => $blocks,
            'target_rich_tables' => $tables,
        ];
    }

    public static function rx_richDiag_stateSnapshot(array $extra = []): array
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = self::rx_richDiag_contextStack();
        $stackKeys = [];
        foreach ($stack as $frame) {
            if (is_array($frame) && isset($frame['message_key'])) {
                $stackKeys[] = (string) $frame['message_key'];
            }
        }
        $callbackMessage = null;
        if (isset($GLOBALS['update']['callback_query']['message']) && is_array($GLOBALS['update']['callback_query']['message'])) {
            $callbackMessage = $GLOBALS['update']['callback_query']['message'];
        }
        $chatId = '';
        if (isset($extra['chat_id']) && $extra['chat_id'] !== null) {
            $chatId = (string) $extra['chat_id'];
        } elseif (isset($GLOBALS['from_id'])) {
            $chatId = (string) $GLOBALS['from_id'];
        }
        $textInfo = null;
        if (isset($extra['text']) && is_string($extra['text'])) {
            $textInfo = self::rx_richDiag_text($extra['text']);
        }
        $pendingKeyboardRemoval = $chatId !== '' && !empty($GLOBALS['rx_pending_reply_kb_remove'][$chatId]);
        $targetMessageId = null;
        if (is_array($callbackMessage) && isset($callbackMessage['message_id'])) {
            $targetMessageId = (int) $callbackMessage['message_id'];
        } elseif (isset($extra['message_id'])) {
            $targetMessageId = (int) $extra['message_id'];
        }
        $targetHasMedia = is_array($callbackMessage) && (!empty($callbackMessage['photo']) || !empty($callbackMessage['video']) || !empty($callbackMessage['animation']) || !empty($callbackMessage['document']));
        $targetMeta = self::rx_richDiag_targetRichMeta();
        return [
            'snapshot_phase' => (string) ($extra['phase'] ?? ''),
            'active_message_key' => $state['message_key'] ?? 'unknown',
            'active_trace_id' => $state['trace_id'] ?? null,
            'context_depth' => count($stack),
            'context_stack_keys' => $stackKeys,
            'rendering_mode' => $extra['rendering_mode'] ?? ($state['delivery_mode'] ?? null),
            'rtl_setting' => $textInfo !== null ? !empty($textInfo['rtl_marker']) : null,
            'rich_destination_flag' => $extra['dest_rich'] ?? null,
            'rich_interception_flag' => $extra['needs_intercept'] ?? null,
            'premium_emoji_tags' => $textInfo !== null ? (int) $textInfo['tg_emoji_tags'] : null,
            'table_present' => $textInfo !== null ? !empty($textInfo['html_table']) : null,
            'table_count' => $textInfo !== null ? (int) $textInfo['table_count'] : null,
            'row_count' => $textInfo !== null ? (int) $textInfo['row_count'] : null,
            'guard_plain_call_depth' => (int) ($GLOBALS['rx_richUi_plain_call'] ?? 0),
            'guard_fallback_reason' => $GLOBALS['rx_rich_diag_fallback_reason'] ?? null,
            'guard_pending_reply_kb_remove' => $pendingKeyboardRemoval,
            'active_ui_section' => $GLOBALS['rx_ui_section'] ?? null,
            'target_message_id' => $targetMessageId,
            'target_is_rich' => !empty($targetMeta['target_is_rich']),
            'target_has_text' => is_array($callbackMessage) && isset($callbackMessage['text']),
            'target_has_media' => $targetHasMedia,
            'target_rich_digest' => $targetMeta['target_rich_digest'] ?? null,
            'target_rich_blocks' => (int) ($targetMeta['target_rich_blocks'] ?? 0),
            'target_rich_tables' => (int) ($targetMeta['target_rich_tables'] ?? 0),
            'chat_hash' => $chatId !== '' ? substr(hash('sha256', $chatId), 0, 12) : null,
            'previous_message_key' => $extra['previous_message_key'] ?? ($stackKeys ? $stackKeys[count($stackKeys) - 1] : null),
        ];
    }

    public static function rx_richDiag_comparisonShape(array $metrics, array $extra = []): array
    {
        return [
            'template_digest' => $metrics['sha256'] ?? null,
            'length' => isset($metrics['length']) ? (int) $metrics['length'] : null,
            'rich_structure_digest' => $extra['structure_sha256'] ?? null,
            'rich_structure_available' => $extra['structure_available'] ?? null,
            'rich_blocks' => $extra['rich_blocks'] ?? null,
            'tables' => isset($metrics['table_count']) ? (int) $metrics['table_count'] : 0,
            'table_rows' => isset($metrics['row_count']) ? (int) $metrics['row_count'] : 0,
            'max_columns' => isset($metrics['max_columns']) ? (int) $metrics['max_columns'] : 0,
            'html_table' => !empty($metrics['html_table']),
            'html_markup' => !empty($metrics['html_markup']),
            'rtl_markers' => !empty($metrics['rtl_marker']),
            'premium_emoji_tags' => isset($metrics['tg_emoji_tags']) ? (int) $metrics['tg_emoji_tags'] : 0,
            'premium_emoji_ids' => isset($metrics['premium_emoji_ids']) ? (int) $metrics['premium_emoji_ids'] : 0,
            'unresolved_variables' => isset($metrics['variables']) ? (int) $metrics['variables'] : 0,
            'escaped_markup' => isset($metrics['escaped_markup']) ? (int) $metrics['escaped_markup'] : 0,
            'long_digit_runs' => isset($metrics['long_digit_runs']) ? (int) $metrics['long_digit_runs'] : 0,
        ];
    }

    public static function rx_richDiag_comparison(string $stage, $templateKey, array $before = [], array $after = [], array $extra = []): void
    {
        if (!self::rx_richDiag_enabled()) {
            return;
        }
        $beforeShape = self::rx_richDiag_comparisonShape($before, isset($extra['before']) && is_array($extra['before']) ? $extra['before'] : []);
        $afterShape = self::rx_richDiag_comparisonShape($after, isset($extra['after']) && is_array($extra['after']) ? $extra['after'] : []);
        $renderMode = $extra['render_mode'] ?? null;
        self::rx_richDiag_log('RICH_RENDER_COMPARISON', $stage, 'compared', [
            'template_key' => (string) ($templateKey ?? 'unknown'),
            'template_source' => $extra['template_source'] ?? null,
            'before' => $beforeShape,
            'after' => $afterShape,
            'render_mode' => $renderMode,
            'telegram_method' => $extra['telegram_method'] ?? null,
            'rich_json_valid' => $extra['rich_json_valid'] ?? null,
            'rich_json_blocks' => $extra['rich_json_blocks'] ?? null,
            'structure_digest' => $extra['structure_digest'] ?? null,
            'structure_available' => $extra['structure_available'] ?? null,
            'table_preserved' => !empty($beforeShape['html_table']) && !empty($afterShape['html_table']),
            'html_table_lost' => !empty($beforeShape['html_table']) && empty($afterShape['html_table']) && $renderMode !== 'blocks',
            'rows_preserved' => (int) $afterShape['table_rows'] >= (int) $beforeShape['table_rows'],
            'max_columns_changed' => (int) $beforeShape['max_columns'] !== (int) $afterShape['max_columns'],
            'length_delta' => (int) $afterShape['length'] - (int) $beforeShape['length'],
            'template_digest_changed' => ($beforeShape['template_digest'] ?? null) !== ($afterShape['template_digest'] ?? null),
            'premium_ids_changed' => (int) $beforeShape['premium_emoji_ids'] !== (int) $afterShape['premium_emoji_ids'],
            'variant' => $extra['variant'] ?? null,
            'phase' => $extra['phase'] ?? null,
        ], 'debug');
    }

    public static function rx_richDiag_enterContext(string $messageKey, string $handler, string $callbackRoute = '', string $templateSource = '', string $origin = '', array $extra = []): string
    {
        if (!self::rx_richDiag_enabled()) {
            return '';
        }
        $previous = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = self::rx_richDiag_contextStack();
        $previousTrace = is_array($previous) && !empty($previous['trace_id']) ? (string) $previous['trace_id'] : null;
        $previousKey = is_array($previous) ? ($previous['message_key'] ?? null) : null;
        $traceOwned = !($previousTrace !== null && $previousKey === $messageKey);
        $token = '';
        try {
            $token = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $token = substr(hash('sha256', uniqid('', true) . getmypid() . $messageKey), 0, 16);
        }
        if ($traceOwned) {
            self::rx_richDiag_begin($messageKey, $origin !== '' ? $origin : 'context_enter', 'context_enter', $stack);
        } else {
            $GLOBALS['rx_rich_diag_state']['message_key'] = $messageKey;
        }
        $depth = count($stack) + 1;
        $stack[] = [
            'token' => $token,
            'message_key' => $messageKey,
            'handler' => $handler,
            'callback_route' => $callbackRoute,
            'template_source' => $templateSource,
            'origin' => $origin,
            'trace_owned' => $traceOwned,
            'trace_id' => $GLOBALS['rx_rich_diag_state']['trace_id'] ?? null,
            'previous_trace_id' => $previousTrace,
            'previous_message_key' => $previousKey,
            'started_at' => microtime(true),
            'depth' => $depth,
        ];
        $GLOBALS['rx_rich_diag_state']['context_stack'] = $stack;
        $GLOBALS['rx_rich_diag_state']['context_depth'] = $depth;
        self::rx_richDiag_log('RICH_MESSAGE_CONTEXT', 'context_enter', 'entered', [
            'handler' => $handler,
            'callback_route' => $callbackRoute,
            'template_source' => $templateSource,
            'template_key' => $messageKey,
            'parent_trace_id' => $previousTrace,
            'context_origin' => $origin,
            'context_depth' => $depth,
            'context_inherited' => !$traceOwned,
            'context_valid' => $messageKey !== '' && self::rx_richDiag_active(),
            'previous_message_key' => $previousKey,
            'previous_trace_active' => $previousTrace !== null,
            'reused_trace' => !$traceOwned,
            'inherited_fields' => !$traceOwned ? ['trace_id'] : [],
        ], 'info');
        return $token;
    }

    public static function rx_richDiag_exitContext(string $token, array $extra = []): void
    {
        if (!self::rx_richDiag_enabled() || $token === '') {
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = self::rx_richDiag_contextStack();
        $beforeTrace = is_array($state) && !empty($state['trace_id']) ? (string) $state['trace_id'] : null;
        $beforeKey = is_array($state) ? ($state['message_key'] ?? 'unknown') : 'unknown';
        $beforeDepth = count($stack);
        $beforeKeys = [];
        foreach ($stack as $entry) {
            if (is_array($entry) && isset($entry['message_key'])) {
                $beforeKeys[] = (string) $entry['message_key'];
            }
        }
        $index = null;
        for ($i = $beforeDepth - 1; $i >= 0; $i--) {
            if (isset($stack[$i]['token']) && (string) $stack[$i]['token'] === $token) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            self::rx_richDiag_log('RICH_CONTEXT_RESET', 'context_exit', 'frame_missing', [
                'context_before' => ['message_key' => $beforeKey, 'trace_id' => $beforeTrace, 'depth' => $beforeDepth, 'stack_keys' => $beforeKeys],
                'context_after' => ['message_key' => $beforeKey, 'trace_id' => $beforeTrace, 'depth' => $beforeDepth, 'stack_keys' => $beforeKeys],
                'trace_stack_depth_before' => $beforeDepth,
                'trace_stack_depth_after' => $beforeDepth,
                'active_message_key_after' => $beforeKey,
                'context_restored' => false,
                'parent_context_restored' => false,
                'unexpected_context_remaining' => true,
            ], 'warning');
            return;
        }
        $frame = is_array($stack[$index]) ? $stack[$index] : [];
        $remaining = array_slice($stack, 0, $index);
        $afterDepth = count($remaining);
        $parentFrame = $index > 0 ? $stack[$index - 1] : null;
        $parentKey = is_array($parentFrame) ? ($parentFrame['message_key'] ?? null) : null;
        $afterKeys = [];
        foreach ($remaining as $entry) {
            if (is_array($entry) && isset($entry['message_key'])) {
                $afterKeys[] = (string) $entry['message_key'];
            }
        }
        $traceOwned = !empty($frame['trace_owned']);
        $afterTrace = $traceOwned ? ($frame['previous_trace_id'] ?? null) : $beforeTrace;
        $contextRestored = $parentKey !== null && $parentKey === ($frame['previous_message_key'] ?? null);
        $parentContextRestored = $parentKey !== null && $parentKey === ($frame['previous_message_key'] ?? null);
        $duration = isset($frame['started_at']) ? (microtime(true) - (float) $frame['started_at']) * 1000.0 : null;
        self::rx_richDiag_log('RICH_CONTEXT_RESET', 'context_exit', 'completed', [
            'context_before' => ['message_key' => $beforeKey, 'trace_id' => $beforeTrace, 'depth' => $beforeDepth, 'stack_keys' => $beforeKeys],
            'context_after' => ['message_key' => $parentKey, 'trace_id' => $afterTrace, 'depth' => $afterDepth, 'stack_keys' => $afterKeys],
            'trace_stack_depth_before' => $beforeDepth,
            'trace_stack_depth_after' => $afterDepth,
            'active_message_key_after' => $parentKey,
            'context_restored' => $contextRestored,
            'parent_context_restored' => $parentContextRestored,
            'unexpected_context_remaining' => $afterDepth > 0 && $parentKey === null,
            'handler' => $frame['handler'] ?? null,
            'callback_route' => $frame['callback_route'] ?? null,
            'template_key' => $frame['message_key'] ?? null,
        ], 'info', $duration);
        $GLOBALS['rx_rich_diag_state']['context_stack'] = $remaining;
        $GLOBALS['rx_rich_diag_state']['context_depth'] = $afterDepth;
        $GLOBALS['rx_rich_diag_state']['message_key'] = $parentKey;
        if ($traceOwned) {
            if (is_string($afterTrace) && $afterTrace !== '') {
                $GLOBALS['rx_rich_diag_state']['trace_id'] = $afterTrace;
            } else {
                unset($GLOBALS['rx_rich_diag_state']['trace_id'], $GLOBALS['rx_rich_diag_state']['started_at'], $GLOBALS['rx_rich_diag_state']['delivery_mode'], $GLOBALS['rx_rich_diag_state']['attempt_index']);
            }
        }
        if (!empty($extra['followup_event'])) {
            self::rx_richDiag_log((string) $extra['followup_event'], 'context_exit', (string) ($extra['followup_status'] ?? 'completed'), isset($extra['followup_context']) && is_array($extra['followup_context']) ? $extra['followup_context'] : []);
        }
    }

    public static function rx_richDiag_log(string $event, string $stage, string $status, array $context = [], string $level = 'info', ?float $durationMs = null): void
    {
        if (!self::rx_richDiag_enabled()) {
            return;
        }
        $traceId = self::rx_richDiag_traceId();
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        [$sourceFile, $function] = self::rx_richDiag_caller();
        if (isset($context['_source_file'])) {
            $sourceFile = (string) $context['_source_file'];
            unset($context['_source_file']);
        }
        if (isset($context['_function'])) {
            $function = (string) $context['_function'];
            unset($context['_function']);
        }
        $entry = [
            'timestamp' => date('Y-m-d\TH:i:sP'),
            'level' => in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $level : 'info',
            'event' => $event,
            'request_id' => self::rx_richDiag_requestId(),
            'message_trace_id' => $traceId,
            'message_key' => $state['message_key'] ?? 'unknown',
            'source_file' => $sourceFile,
            'function' => $function,
            'stage' => $stage,
            'status' => $status,
        ];
        if (!empty($state['message_index'])) {
            $entry['message_index'] = (int) $state['message_index'];
        }
        if (!empty($state['attempt_index'])) {
            $entry['attempt_index'] = (int) $state['attempt_index'];
        }
        if ($event === 'RICH_SEND_METHOD_SELECTED' && $status === 'selected') {
            $GLOBALS['rx_rich_diag_attempt'] = (int) ($GLOBALS['rx_rich_diag_attempt'] ?? 0) + 1;
            $entry['attempt_index'] = (int) $GLOBALS['rx_rich_diag_attempt'];
            $GLOBALS['rx_rich_diag_state']['attempt_index'] = (int) $GLOBALS['rx_rich_diag_attempt'];
            $GLOBALS['rx_rich_diag_state']['delivery_mode'] = (string) ($context['delivery_mode'] ?? $context['api_method'] ?? 'unknown');
        }
        if ($event === 'RICH_FALLBACK_TRIGGERED') {
            $GLOBALS['rx_rich_diag_state']['fallback_used'] = true;
        }
        if ($event === 'RICH_FALLBACK_COMPLETED' && !empty($context['delivery_mode'])) {
            $GLOBALS['rx_rich_diag_state']['delivery_mode'] = (string) $context['delivery_mode'];
        }
        if ($durationMs !== null) {
            $entry['duration_ms'] = round($durationMs, 2);
        }
        foreach (self::rx_richDiag_sanitize($context) as $key => $value) {
            if (!array_key_exists($key, $entry)) {
                $entry[$key] = $value;
            }
        }
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($line) || $line === '') {
            return;
        }
        static $writeFailed = false;
        if ($writeFailed) {
            return;
        }
        $written = @file_put_contents(self::rx_richDiag_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $writeFailed = true;
        }
    }

    public static function rx_richDiag_begin(string $messageKey, string $source = '', string $stage = 'start', array $contextStack = []): string
    {
        if (!self::rx_richDiag_enabled()) {
            return '';
        }
        if (!$contextStack) {
            $contextStack = self::rx_richDiag_contextStack();
        }
        $GLOBALS['rx_rich_diag_seq'] = (int) ($GLOBALS['rx_rich_diag_seq'] ?? 0) + 1;
        $GLOBALS['rx_rich_diag_attempt'] = 0;
        try {
            $trace = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $trace = substr(hash('sha256', uniqid('', true) . getmypid()), 0, 24);
        }
        $GLOBALS['rx_rich_diag_state'] = [
            'trace_id' => $trace,
            'message_key' => $messageKey,
            'started_at' => microtime(true),
            'message_index' => (int) $GLOBALS['rx_rich_diag_seq'],
            'context_stack' => $contextStack,
            'context_depth' => count($contextStack),
        ];
        self::rx_richDiag_log('RICH_DELIVERY_STARTED', $stage, 'started', ['source' => $source], 'info');
        return $trace;
    }

    public static function rx_richDiag_finish(string $status = 'completed', array $context = []): void
    {
        if (!self::rx_richDiag_enabled()) {
            $stack = self::rx_richDiag_contextStack();
            $GLOBALS['rx_rich_diag_state'] = $stack ? ['context_stack' => $stack, 'context_depth' => count($stack)] : [];
            unset($GLOBALS['rx_rich_diag_last_result']);
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = self::rx_richDiag_contextStack();
        $duration = isset($state['started_at']) ? (microtime(true) - (float) $state['started_at']) * 1000.0 : null;
        if (isset($state['delivery_mode'])) {
            $context['delivery_mode'] = $state['delivery_mode'];
        }
        if (!empty($state['fallback_used'])) {
            $context['fallback_used'] = true;
        }
        if (isset($GLOBALS['rx_rich_diag_last_result'])) {
            $context['last_result'] = self::rx_richDiag_result($GLOBALS['rx_rich_diag_last_result']);
            unset($GLOBALS['rx_rich_diag_last_result']);
        }
        $level = in_array($status, ['failed', 'rejected'], true) ? 'error' : 'info';
        self::rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'delivery', $status, $context, $level, $duration);
        $GLOBALS['rx_rich_diag_state'] = $stack ? ['context_stack' => $stack, 'context_depth' => count($stack)] : [];
    }

    public static function rx_richDiag_observeContext(string $stage, array $extra = []): void
    {
        if (!self::rx_richDiag_enabled()) {
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = self::rx_richDiag_contextStack();
        $depth = count($stack);
        $key = is_array($state) ? ($state['message_key'] ?? 'unknown') : 'unknown';
        $trace = is_array($state) && !empty($state['trace_id']) ? (string) $state['trace_id'] : null;
        $keys = [];
        foreach ($stack as $frame) {
            if (is_array($frame) && isset($frame['message_key'])) {
                $keys[] = (string) $frame['message_key'];
            }
        }
        self::rx_richDiag_log('RICH_CONTEXT_RESET', 'context_observe', $stage, array_merge([
            'context_before' => ['message_key' => $key, 'trace_id' => $trace, 'depth' => $depth, 'stack_keys' => $keys],
            'context_after' => ['message_key' => $key, 'trace_id' => $trace, 'depth' => $depth, 'stack_keys' => $keys],
            'trace_stack_depth_before' => $depth,
            'trace_stack_depth_after' => $depth,
            'active_message_key_after' => $key,
            'context_restored' => false,
            'parent_context_restored' => false,
            'unexpected_context_remaining' => false,
            'observation_only' => true,
        ], $extra), 'info');
    }

    public static function rx_richDiag_dispatchSkip(string $reason, array $context = []): ?array
    {
        self::rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'dispatch_guard', 'skipped', $context + ['reason' => $reason, 'delivery_mode' => 'passthrough'], 'debug');
        return null;
    }

    public static function rx_richDiag_traceSend(string $messageKey, callable $sender)
    {
        if (!self::rx_richDiag_enabled()) {
            return $sender();
        }
        $parentState = $GLOBALS['rx_rich_diag_state'] ?? [];
        $parentTrace = is_array($parentState) && !empty($parentState['trace_id']) ? (string) $parentState['trace_id'] : null;
        $parentKey = is_array($parentState) ? ($parentState['message_key'] ?? null) : null;
        if (!self::rx_richDiag_active() || self::rx_richDiag_currentKey() !== $messageKey) {
            self::rx_richDiag_begin($messageKey, 'src/Telegram/Rich/Diagnostics.php', 'message_send');
        }
        $template = $GLOBALS['rx_rich_diag_templates'][$messageKey] ?? null;
        $selection = [];
        $templateSource = 'unknown';
        $textRaw = '';
        if (is_array($template)) {
            $templateSource = isset($template['selection']) && is_array($template['selection']) ? (string) ($template['selection']['template_source'] ?? 'unknown') : 'unknown';
            $textInfo = isset($template['text']) && is_array($template['text']) ? $template['text'] : null;
            if ($textInfo === null) {
                $textInfo = isset($template['text_raw']) ? self::rx_richDiag_text($template['text_raw']) : ['present' => false];
            }
            if (isset($template['text_raw']) && is_string($template['text_raw'])) {
                $textRaw = $template['text_raw'];
            } elseif (isset($template['text_before_raw']) && is_string($template['text_before_raw'])) {
                $textRaw = $template['text_before_raw'];
            }
            self::rx_richDiag_log('RICH_TEMPLATE_LOADED', 'template', !empty($textInfo['present']) ? 'loaded' : 'empty', $textInfo);
            $structure = isset($template['structure']) && is_array($template['structure']) ? $template['structure'] : null;
            if ($structure === null) {
                $structure = isset($template['structure_raw']) ? self::rx_richDiag_structure($template['structure_raw']) : ['available' => false];
            }
            self::rx_richDiag_log(!empty($structure['available']) ? 'RICH_STRUCTURE_FOUND' : 'RICH_STRUCTURE_MISSING', 'structure', !empty($structure['available']) ? 'found' : 'missing', $structure);
            $variables = isset($template['variables']) && is_array($template['variables']) ? $template['variables'] : null;
            if ($variables === null && isset($template['text_before_raw'])) {
                $beforeInfo = self::rx_richDiag_text($template['text_before_raw']);
                $variables = [
                    'before' => (int) $beforeInfo['variables'],
                    'after' => (int) $textInfo['variables'],
                    'resolved' => max(0, (int) $beforeInfo['variables'] - (int) $textInfo['variables']),
                    'unresolved' => (int) $textInfo['variables'],
                    'length_before' => (int) $beforeInfo['length'],
                    'length_after' => (int) $textInfo['length'],
                    'length_delta' => (int) $textInfo['length'] - (int) $beforeInfo['length'],
                    'table_preserved' => !empty($beforeInfo['html_table']) && !empty($textInfo['html_table']),
                    'rows_preserved' => (int) $textInfo['row_count'] >= (int) $beforeInfo['row_count'],
                    'rtl_marker_before' => !empty($beforeInfo['rtl_marker']),
                    'rtl_marker_after' => !empty($textInfo['rtl_marker']),
                ];
                self::rx_richDiag_comparison('variable_substitution', $messageKey, $beforeInfo, $textInfo, [
                    'template_source' => $templateSource,
                    'structure_digest' => $structure['sha256'] ?? null,
                    'structure_available' => !empty($structure['available']),
                    'after' => [
                        'rich_blocks' => $structure['blocks'] ?? 0,
                        'structure_sha256' => $structure['sha256'] ?? null,
                        'structure_available' => !empty($structure['available']),
                    ],
                    'phase' => 'variable_substitution',
                ]);
            }
            if (is_array($variables)) {
                self::rx_richDiag_log('RICH_VARIABLES_APPLIED', 'variables', 'applied', $variables);
            }
            $selection = isset($template['selection']) && is_array($template['selection']) ? $template['selection'] : [];
            self::rx_richDiag_log('RICH_TEMPLATE_SELECTED', 'template', 'selected', $selection);
            self::rx_richDiag_comparison('template_retrieval', $messageKey, $textInfo, $textInfo, [
                'template_source' => $templateSource,
                'structure_digest' => $structure['sha256'] ?? null,
                'structure_available' => !empty($structure['available']),
                'after' => [
                    'rich_blocks' => $structure['blocks'] ?? 0,
                    'structure_sha256' => $structure['sha256'] ?? null,
                    'structure_available' => !empty($structure['available']),
                ],
                'phase' => 'template_retrieval',
            ]);
        } else {
            self::rx_richDiag_log('RICH_TEMPLATE_LOADED', 'template', 'unknown', ['template_key' => $messageKey]);
        }
        self::rx_richDiag_log('RICH_MESSAGE_CONTEXT', 'context_enter', 'entered', [
            'handler' => [\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_traceSend'],
            'callback_route' => $messageKey === 'text_start' ? 'start' : '',
            'template_source' => $templateSource,
            'template_key' => $messageKey,
            'parent_trace_id' => $parentTrace,
            'context_origin' => 'trace_send',
            'context_depth' => self::rx_richDiag_contextDepth(),
            'context_inherited' => $parentKey !== null && $parentKey === $messageKey,
            'context_valid' => true,
            'previous_message_key' => $parentKey,
            'previous_trace_active' => $parentTrace !== null,
            'inherited_fields' => ($parentKey !== null && $parentKey === $messageKey) ? ['trace_id'] : [],
        ], 'info');
        $snapshotExtra = [
            'phase' => $messageKey === 'text_start' ? 'welcome_before_render' : 'message_before_render',
            'rendering_mode' => 'prepared',
        ];
        if ($textRaw !== '') {
            $snapshotExtra['text'] = $textRaw;
        }
        self::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', self::rx_richDiag_stateSnapshot($snapshotExtra) + ['template_key' => $messageKey], 'debug');
        try {
            $result = $sender();
        } catch (Throwable $e) {
            $afterExtra = [
                'phase' => $messageKey === 'text_start' ? 'welcome_after_exception' : 'message_after_exception',
                'rendering_mode' => 'exception',
            ];
            if ($textRaw !== '') {
                $afterExtra['text'] = $textRaw;
            }
            self::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', self::rx_richDiag_stateSnapshot($afterExtra) + ['template_key' => $messageKey], 'warning');
            self::rx_richDiag_log('RICH_RENDER_FAILED', 'send', 'exception', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'error');
            self::rx_richDiag_observeContext('scope_exit', ['scope_message_key' => $messageKey, 'scope_status' => 'exception']);
            self::rx_richDiag_finish('failed', ['result' => ['ok' => false, 'exception' => get_class($e)], '_function' => 'rx_richDiag_traceSend', '_source_file' => 'src/Telegram/Rich/Diagnostics.php']);
            throw $e;
        }
        $status = is_array($result) && array_key_exists('ok', $result) && empty($result['ok']) ? 'failed' : 'completed';
        $afterExtra = [
            'phase' => $messageKey === 'text_start' ? 'welcome_after_render' : 'message_after_render',
            'rendering_mode' => (string) ($GLOBALS['rx_rich_diag_state']['delivery_mode'] ?? 'completed'),
        ];
        if ($textRaw !== '') {
            $afterExtra['text'] = $textRaw;
        }
        self::rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', self::rx_richDiag_stateSnapshot($afterExtra) + ['template_key' => $messageKey, 'scope_result' => self::rx_richDiag_result($result)], $status === 'failed' ? 'warning' : 'debug');
        self::rx_richDiag_observeContext('scope_exit', ['scope_message_key' => $messageKey, 'scope_status' => $status]);
        self::rx_richDiag_finish($status, ['result' => self::rx_richDiag_result($result), '_function' => 'rx_richDiag_traceSend', '_source_file' => 'src/Telegram/Rich/Diagnostics.php']);
        return $result;
    }
}
