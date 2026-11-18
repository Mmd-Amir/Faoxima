<?php

if (!function_exists('rx_richDiag_enabled')) {
    function rx_richDiag_enabled(): bool
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
}

if (!function_exists('rx_richDiag_path')) {
    function rx_richDiag_path(): string
    {
        static $path = null;
        if ($path === null) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'rich_message_debug.log';
        }
        return $path;
    }
}

if (!function_exists('rx_richDiag_requestId')) {
    function rx_richDiag_requestId(): string
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
}

if (!function_exists('rx_richDiag_traceId')) {
    function rx_richDiag_traceId(): string
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
}

if (!function_exists('rx_richDiag_active')) {
    function rx_richDiag_active(): bool
    {
        if (!rx_richDiag_enabled()) {
            return false;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        return is_array($state) && !empty($state['trace_id']);
    }
}

if (!function_exists('rx_richDiag_utf16Length')) {
    function rx_richDiag_utf16Length($text): int
    {
        if (!is_string($text) || $text === '') {
            return 0;
        }
        $encoded = @mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        return is_string($encoded) ? intdiv(strlen($encoded), 2) : 0;
    }
}

if (!function_exists('rx_richDiag_caller')) {
    function rx_richDiag_caller(): array
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
}

if (!function_exists('rx_richDiag_sanitize')) {
    function rx_richDiag_sanitize($value, int $depth = 0)
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
                $out[$key] = rx_richDiag_sanitize($item, $depth + 1);
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
}

if (!function_exists('rx_richDiag_text')) {
    function rx_richDiag_text($text): array
    {
        if (!is_string($text)) {
            return ['present' => false];
        }
        $result = [
            'present' => trim($text) !== '',
            'length' => strlen($text),
            'utf16_length' => rx_richDiag_utf16Length($text),
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
}

if (!function_exists('rx_richDiag_structure')) {
    function rx_richDiag_structure($raw): array
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
}

if (!function_exists('rx_richDiag_payload')) {
    function rx_richDiag_payload(array $datas): array
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
                $out[$field] = rx_richDiag_text($datas[$field]);
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
}

if (!function_exists('rx_richDiag_result')) {
    function rx_richDiag_result($result): array
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
}

if (!function_exists('rx_richDiag_contextStack')) {
    function rx_richDiag_contextStack(): array
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        return (is_array($state) && isset($state['context_stack']) && is_array($state['context_stack'])) ? $state['context_stack'] : [];
    }
}

if (!function_exists('rx_richDiag_contextDepth')) {
    function rx_richDiag_contextDepth(): int
    {
        return count(rx_richDiag_contextStack());
    }
}

if (!function_exists('rx_richDiag_currentKey')) {
    function rx_richDiag_currentKey(): string
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? null;
        $key = is_array($state) ? ($state['message_key'] ?? null) : null;
        return is_string($key) && $key !== '' ? $key : 'unknown';
    }
}

if (!function_exists('rx_richDiag_targetRichMeta')) {
    function rx_richDiag_targetRichMeta(): array
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
}

if (!function_exists('rx_richDiag_stateSnapshot')) {
    function rx_richDiag_stateSnapshot(array $extra = []): array
    {
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = rx_richDiag_contextStack();
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
            $textInfo = rx_richDiag_text($extra['text']);
        }
        $pendingKeyboardRemoval = $chatId !== '' && !empty($GLOBALS['rx_pending_reply_kb_remove'][$chatId]);
        $targetMessageId = null;
        if (is_array($callbackMessage) && isset($callbackMessage['message_id'])) {
            $targetMessageId = (int) $callbackMessage['message_id'];
        } elseif (isset($extra['message_id'])) {
            $targetMessageId = (int) $extra['message_id'];
        }
        $targetHasMedia = is_array($callbackMessage) && (!empty($callbackMessage['photo']) || !empty($callbackMessage['video']) || !empty($callbackMessage['animation']) || !empty($callbackMessage['document']));
        $targetMeta = rx_richDiag_targetRichMeta();
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
}

if (!function_exists('rx_richDiag_comparisonShape')) {
    function rx_richDiag_comparisonShape(array $metrics, array $extra = []): array
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
}

if (!function_exists('rx_richDiag_comparison')) {
    function rx_richDiag_comparison(string $stage, $templateKey, array $before = [], array $after = [], array $extra = []): void
    {
        if (!rx_richDiag_enabled()) {
            return;
        }
        $beforeShape = rx_richDiag_comparisonShape($before, isset($extra['before']) && is_array($extra['before']) ? $extra['before'] : []);
        $afterShape = rx_richDiag_comparisonShape($after, isset($extra['after']) && is_array($extra['after']) ? $extra['after'] : []);
        $renderMode = $extra['render_mode'] ?? null;
        rx_richDiag_log('RICH_RENDER_COMPARISON', $stage, 'compared', [
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
}

if (!function_exists('rx_richDiag_enterContext')) {
    function rx_richDiag_enterContext(string $messageKey, string $handler, string $callbackRoute = '', string $templateSource = '', string $origin = '', array $extra = []): string
    {
        if (!rx_richDiag_enabled()) {
            return '';
        }
        $previous = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = rx_richDiag_contextStack();
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
            rx_richDiag_begin($messageKey, $origin !== '' ? $origin : 'context_enter', 'context_enter', $stack);
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
        rx_richDiag_log('RICH_MESSAGE_CONTEXT', 'context_enter', 'entered', [
            'handler' => $handler,
            'callback_route' => $callbackRoute,
            'template_source' => $templateSource,
            'template_key' => $messageKey,
            'parent_trace_id' => $previousTrace,
            'context_origin' => $origin,
            'context_depth' => $depth,
            'context_inherited' => !$traceOwned,
            'context_valid' => $messageKey !== '' && rx_richDiag_active(),
            'previous_message_key' => $previousKey,
            'previous_trace_active' => $previousTrace !== null,
            'reused_trace' => !$traceOwned,
            'inherited_fields' => !$traceOwned ? ['trace_id'] : [],
        ], 'info');
        return $token;
    }
}

if (!function_exists('rx_richDiag_exitContext')) {
    function rx_richDiag_exitContext(string $token, array $extra = []): void
    {
        if (!rx_richDiag_enabled() || $token === '') {
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = rx_richDiag_contextStack();
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
            rx_richDiag_log('RICH_CONTEXT_RESET', 'context_exit', 'frame_missing', [
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
        rx_richDiag_log('RICH_CONTEXT_RESET', 'context_exit', 'completed', [
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
            rx_richDiag_log((string) $extra['followup_event'], 'context_exit', (string) ($extra['followup_status'] ?? 'completed'), isset($extra['followup_context']) && is_array($extra['followup_context']) ? $extra['followup_context'] : []);
        }
    }
}


if (!function_exists('rx_richDiag_log')) {
    function rx_richDiag_log(string $event, string $stage, string $status, array $context = [], string $level = 'info', ?float $durationMs = null): void
    {
        if (!rx_richDiag_enabled()) {
            return;
        }
        $traceId = rx_richDiag_traceId();
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        [$sourceFile, $function] = rx_richDiag_caller();
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
            'request_id' => rx_richDiag_requestId(),
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
        foreach (rx_richDiag_sanitize($context) as $key => $value) {
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
        $written = @file_put_contents(rx_richDiag_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $writeFailed = true;
        }
    }
}

if (!function_exists('rx_richDiag_begin')) {
    function rx_richDiag_begin(string $messageKey, string $source = '', string $stage = 'start', array $contextStack = []): string
    {
        if (!rx_richDiag_enabled()) {
            return '';
        }
        if (!$contextStack) {
            $contextStack = rx_richDiag_contextStack();
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
        rx_richDiag_log('RICH_DELIVERY_STARTED', $stage, 'started', ['source' => $source], 'info');
        return $trace;
    }
}

if (!function_exists('rx_richDiag_finish')) {
    function rx_richDiag_finish(string $status = 'completed', array $context = []): void
    {
        if (!rx_richDiag_enabled()) {
            $stack = rx_richDiag_contextStack();
            $GLOBALS['rx_rich_diag_state'] = $stack ? ['context_stack' => $stack, 'context_depth' => count($stack)] : [];
            unset($GLOBALS['rx_rich_diag_last_result']);
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = rx_richDiag_contextStack();
        $duration = isset($state['started_at']) ? (microtime(true) - (float) $state['started_at']) * 1000.0 : null;
        if (isset($state['delivery_mode'])) {
            $context['delivery_mode'] = $state['delivery_mode'];
        }
        if (!empty($state['fallback_used'])) {
            $context['fallback_used'] = true;
        }
        if (isset($GLOBALS['rx_rich_diag_last_result'])) {
            $context['last_result'] = rx_richDiag_result($GLOBALS['rx_rich_diag_last_result']);
            unset($GLOBALS['rx_rich_diag_last_result']);
        }
        $level = in_array($status, ['failed', 'rejected'], true) ? 'error' : 'info';
        rx_richDiag_log('RICH_DELIVERY_COMPLETED', 'delivery', $status, $context, $level, $duration);
        $GLOBALS['rx_rich_diag_state'] = $stack ? ['context_stack' => $stack, 'context_depth' => count($stack)] : [];
    }
}

if (!function_exists('rx_richDiag_observeContext')) {
    function rx_richDiag_observeContext(string $stage, array $extra = []): void
    {
        if (!rx_richDiag_enabled()) {
            return;
        }
        $state = $GLOBALS['rx_rich_diag_state'] ?? [];
        $stack = rx_richDiag_contextStack();
        $depth = count($stack);
        $key = is_array($state) ? ($state['message_key'] ?? 'unknown') : 'unknown';
        $trace = is_array($state) && !empty($state['trace_id']) ? (string) $state['trace_id'] : null;
        $keys = [];
        foreach ($stack as $frame) {
            if (is_array($frame) && isset($frame['message_key'])) {
                $keys[] = (string) $frame['message_key'];
            }
        }
        rx_richDiag_log('RICH_CONTEXT_RESET', 'context_observe', $stage, array_merge([
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
}


if (!function_exists('rx_richDiag_dispatchSkip')) {
    function rx_richDiag_dispatchSkip(string $reason, array $context = []): ?array
    {
        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'dispatch_guard', 'skipped', $context + ['reason' => $reason, 'delivery_mode' => 'passthrough'], 'debug');
        return null;
    }
}

if (!function_exists('rx_richDiag_traceSend')) {
    function rx_richDiag_traceSend(string $messageKey, callable $sender)
    {
        if (!rx_richDiag_enabled()) {
            return $sender();
        }
        $parentState = $GLOBALS['rx_rich_diag_state'] ?? [];
        $parentTrace = is_array($parentState) && !empty($parentState['trace_id']) ? (string) $parentState['trace_id'] : null;
        $parentKey = is_array($parentState) ? ($parentState['message_key'] ?? null) : null;
        if (!rx_richDiag_active() || rx_richDiag_currentKey() !== $messageKey) {
            rx_richDiag_begin($messageKey, 're/rx/function/rich_ui.php', 'message_send');
        }
        $template = $GLOBALS['rx_rich_diag_templates'][$messageKey] ?? null;
        $selection = [];
        $templateSource = 'unknown';
        $textRaw = '';
        if (is_array($template)) {
            $templateSource = isset($template['selection']) && is_array($template['selection']) ? (string) ($template['selection']['template_source'] ?? 'unknown') : 'unknown';
            $textInfo = isset($template['text']) && is_array($template['text']) ? $template['text'] : null;
            if ($textInfo === null) {
                $textInfo = isset($template['text_raw']) ? rx_richDiag_text($template['text_raw']) : ['present' => false];
            }
            if (isset($template['text_raw']) && is_string($template['text_raw'])) {
                $textRaw = $template['text_raw'];
            } elseif (isset($template['text_before_raw']) && is_string($template['text_before_raw'])) {
                $textRaw = $template['text_before_raw'];
            }
            rx_richDiag_log('RICH_TEMPLATE_LOADED', 'template', !empty($textInfo['present']) ? 'loaded' : 'empty', $textInfo);
            $structure = isset($template['structure']) && is_array($template['structure']) ? $template['structure'] : null;
            if ($structure === null) {
                $structure = isset($template['structure_raw']) ? rx_richDiag_structure($template['structure_raw']) : ['available' => false];
            }
            rx_richDiag_log(!empty($structure['available']) ? 'RICH_STRUCTURE_FOUND' : 'RICH_STRUCTURE_MISSING', 'structure', !empty($structure['available']) ? 'found' : 'missing', $structure);
            $variables = isset($template['variables']) && is_array($template['variables']) ? $template['variables'] : null;
            if ($variables === null && isset($template['text_before_raw'])) {
                $beforeInfo = rx_richDiag_text($template['text_before_raw']);
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
                rx_richDiag_comparison('variable_substitution', $messageKey, $beforeInfo, $textInfo, [
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
                rx_richDiag_log('RICH_VARIABLES_APPLIED', 'variables', 'applied', $variables);
            }
            $selection = isset($template['selection']) && is_array($template['selection']) ? $template['selection'] : [];
            rx_richDiag_log('RICH_TEMPLATE_SELECTED', 'template', 'selected', $selection);
            rx_richDiag_comparison('template_retrieval', $messageKey, $textInfo, $textInfo, [
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
            rx_richDiag_log('RICH_TEMPLATE_LOADED', 'template', 'unknown', ['template_key' => $messageKey]);
        }
        rx_richDiag_log('RICH_MESSAGE_CONTEXT', 'context_enter', 'entered', [
            'handler' => 'rx_richDiag_traceSend',
            'callback_route' => $messageKey === 'text_start' ? 'start' : '',
            'template_source' => $templateSource,
            'template_key' => $messageKey,
            'parent_trace_id' => $parentTrace,
            'context_origin' => 'trace_send',
            'context_depth' => rx_richDiag_contextDepth(),
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
        rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', rx_richDiag_stateSnapshot($snapshotExtra) + ['template_key' => $messageKey], 'debug');
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
            rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', rx_richDiag_stateSnapshot($afterExtra) + ['template_key' => $messageKey], 'warning');
            rx_richDiag_log('RICH_RENDER_FAILED', 'send', 'exception', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'error');
            rx_richDiag_observeContext('scope_exit', ['scope_message_key' => $messageKey, 'scope_status' => 'exception']);
            rx_richDiag_finish('failed', ['result' => ['ok' => false, 'exception' => get_class($e)], '_function' => 'rx_richDiag_traceSend', '_source_file' => 're/rx/function/rich_ui.php']);
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
        rx_richDiag_log('RICH_STATE_BEFORE_AFTER', 'state_snapshot', 'captured', rx_richDiag_stateSnapshot($afterExtra) + ['template_key' => $messageKey, 'scope_result' => rx_richDiag_result($result)], $status === 'failed' ? 'warning' : 'debug');
        rx_richDiag_observeContext('scope_exit', ['scope_message_key' => $messageKey, 'scope_status' => $status]);
        rx_richDiag_finish($status, ['result' => rx_richDiag_result($result), '_function' => 'rx_richDiag_traceSend', '_source_file' => 're/rx/function/rich_ui.php']);
        return $result;
    }
}

if (!function_exists('rx_richUi_chatIsUserUi')) {
    function rx_richUi_chatIsUserUi($chatId): bool
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
}

if (!function_exists('rx_richUi_extractText')) {
    function rx_richUi_extractText($node, string $glue = ''): string
    {
        if (is_string($node)) {
            return $node;
        }
        if (!is_array($node)) {
            return '';
        }
        $type = $node['type'] ?? null;
        if ($type === 'buttons' || $type === 'button') {
            return '';
        }
        $skip = ['type' => 1, 'url' => 1, 'callback_data' => 1, 'style' => 1, 'align' => 1, 'name' => 1, 'id' => 1, 'is_rtl' => 1, 'language' => 1];
        $isList = $node === [] || array_keys($node) === range(0, count($node) - 1);
        $parts = [];
        foreach ($node as $key => $value) {
            if (!$isList && isset($skip[$key])) {
                continue;
            }
            $piece = rx_richUi_extractText($value, $key === 'blocks' ? "
" : '');
            if ($piece !== '') {
                $parts[] = $piece;
            }
        }
        return trim(implode($isList ? $glue : "
", $parts));
    }
}

if (!function_exists('rx_richUi_blockTags')) {
    function rx_richUi_blockTags(): array
    {
        return ['p', 'table', 'ul', 'ol', 'pre', 'blockquote', 'details', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'aside', 'footer', 'figure'];
    }
}

if (!function_exists('rx_richUi_htmlLike')) {
    function rx_richUi_htmlLike($text): bool
    {
        return (bool) preg_match('~</?(?:p|b|strong|i|em|u|ins|s|strike|del|code|pre|mark|sub|sup|tg-spoiler|tg-emoji|a|tg-time|tg-math|h[1-6]|br|hr|footer|ul|ol|li|blockquote|aside|details|summary|table|caption|tr|th|td|figure|figcaption|cite|tg-button-row|tg-button)\b~i', (string) $text);
    }
}

if (!function_exists('rx_richUi_requiresRich')) {
    function rx_richUi_requiresRich($text, $parseMode = null): bool
    {
        return (bool) preg_match('~</?(?:p|br|table|ul|ol|details|h[1-6]|hr|aside|footer|figure|mark|sub|sup|tg-time|tg-math|tg-reference|summary|caption|tr|th|td)\b~i', (string) $text);
    }
}

if (!function_exists('rx_richUi_splitBlocks')) {
    function rx_richUi_splitBlocks(string $html): array
    {
        $blocks = rx_richUi_blockTags();
        $len = strlen($html);
        $pos = 0;
        $out = [];
        while ($pos < $len) {
            if (!preg_match('~<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>~', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
                $out[] = [false, substr($html, $pos)];
                break;
            }
            $tag = strtolower($m[1][0]);
            $tagStart = $m[0][1];
            $tagText = $m[0][0];
            if ($tagStart > $pos) {
                $out[] = [false, substr($html, $pos, $tagStart - $pos)];
            }
            if (!in_array($tag, $blocks, true)) {
                $out[] = [false, $tagText];
                $pos = $tagStart + strlen($tagText);
                continue;
            }
            if ($tag === 'hr' || substr(rtrim($tagText), -2) === '/>') {
                $end = $tagStart + strlen($tagText);
                $out[] = [true, substr($html, $tagStart, $end - $tagStart)];
                $pos = $end;
                continue;
            }
            $depth = 1;
            $scan = $tagStart + strlen($tagText);
            $end = null;
            while ($scan < $len) {
                if (!preg_match('~<(/?)' . preg_quote($tag, '~') . '\b[^>]*>~i', $html, $mm, PREG_OFFSET_CAPTURE, $scan)) {
                    break;
                }
                $full = $mm[0][0];
                if ($mm[1][0] === '/') {
                    $depth--;
                } elseif (substr(rtrim($full), -2) !== '/>') {
                    $depth++;
                }
                $scan = $mm[0][1] + strlen($full);
                if ($depth <= 0) {
                    $end = $scan;
                    break;
                }
            }
            if ($end === null) {
                $out[] = [false, substr($html, $tagStart)];
                break;
            }
            $out[] = [true, substr($html, $tagStart, $end - $tagStart)];
            $pos = $end;
        }
        return $out;
    }
}

if (!function_exists('rx_richUi_inlineToParagraphs')) {
    function rx_richUi_inlineToParagraphs(string $chunk): string
    {
        $lines = [];
        $cur = '';
        $len = strlen($chunk);
        $inTag = false;
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $chunk[$i];
            if ($inTag) {
                $cur .= $ch;
                if ($quote !== null) {
                    if ($ch === $quote) {
                        $quote = null;
                    }
                } elseif ($ch === '"' || $ch === "'") {
                    $quote = $ch;
                } elseif ($ch === '>') {
                    $inTag = false;
                }
                continue;
            }
            if ($ch === '<') {
                $inTag = true;
                $cur .= $ch;
                continue;
            }
            if ($ch === "\n") {
                $lines[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        $lines[] = $cur;
        $out = '';
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $out .= '<p>' . $line . '</p>';
        }
        return $out;
    }
}

if (!function_exists('rx_richUi_textToHtml')) {
    function rx_richUi_textToHtml($text, $parseMode): ?string
    {
        $text = rx_richUi_stripRtlMarker($text);
        $mode = strtolower(trim((string) $parseMode));
        if ($mode === 'html') {
            $html = (string) $text;
        } elseif ($mode === '' || $mode === 'none') {
            $html = rx_richUi_htmlLike($text)
                ? (string) $text
                : htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            return null;
        }
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        if (trim($html) === '') {
            return '';
        }
        $out = '';
        $inline = '';
        foreach (rx_richUi_splitBlocks($html) as $segment) {
            if ($segment[0]) {
                if (trim($inline) !== '') {
                    $out .= rx_richUi_inlineToParagraphs($inline);
                    $inline = '';
                }
                if (preg_match('~^<blockquote\b~i', ltrim($segment[1]))) {
                    $out .= str_replace("\n", '<br>', $segment[1]);
                } else {
                    $out .= $segment[1];
                }
            } else {
                $inline .= $segment[1];
            }
        }
        if (trim($inline) !== '') {
            $out .= rx_richUi_inlineToParagraphs($inline);
        }
        $normalized = preg_replace('~<tg-emoji\s+emoji-id="(\d+)"\s*>[\s\S]*?</tg-emoji>~u', '<tg-emoji emoji-id="$1"></tg-emoji>', $out);
        if (rx_richDiag_active() && strpos((string) $normalized, '<tg-emoji') !== false) {
            rx_richDiag_log('RICH_PREMIUM_EMOJI_PROCESS', 'html_normalize', 'normalized', [
                'parse_mode' => (string) $parseMode,
                'input' => rx_richDiag_text($text),
                'output' => rx_richDiag_text((string) $normalized),
                'fallback_text_removed' => (bool) preg_match('~<tg-emoji\s+emoji-id="\d+"\s*>[^<]+</tg-emoji>~u', $out),
            ], 'debug');
        }
        return $normalized;
    }
}

function rx_richUi_fallbackDom(string $html): ?DOMElement
{
    if (!class_exists('DOMDocument')) {
        rx_richDiag_log('RICH_RENDER_FAILED', 'html_to_text', 'unavailable', ['reason' => 'dom_unavailable'], 'warning');
        return null;
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    return $loaded ? $doc->getElementsByTagName('body')->item(0) : null;
}

function rx_richUi_fallbackLinkText(string $label, string $href): string
{
    $href = trim($href);
    if (!preg_match('~^(?:https?|tg|tg-proxy|proxy|vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|xuifile|xuiawgfile|openvpn|socks5?)://[^\s\p{Cc}\p{Cf}<>]+$~iuD', $href)) {
        if ($href !== '') {
            rx_richDiag_log('RICH_RENDER_FAILED', 'html_to_text', 'degraded', ['reason' => 'link_destination_unrepresentable'], 'warning');
        }
        return $label;
    }
    if ($label === '' || preg_match('~(?:^|[\s(\[])' . preg_quote($href, '~') . '(?=$|[\s)\]])~u', $label) === 1) {
        return $label === '' ? $href : $label;
    }
    return $label . ' (' . $href . ')';
}

function rx_richUi_tableNodeText(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return $node->nodeValue;
    }
    if (!($node instanceof DOMElement) || in_array($node->tagName, ['script', 'style'], true)) {
        return '';
    }
    if ($node->tagName === 'br') {
        return ' ';
    }
    $parts = [];
    $headers = true;
    foreach ($node->childNodes as $child) {
        $part = rx_richUi_tableNodeText($child);
        if ($node->tagName === 'tr') {
            if (!($child instanceof DOMElement) || !in_array($child->tagName, ['td', 'th'], true)) {
                continue;
            }
            $headers = $headers && $child->tagName === 'th';
            $part = trim((string) preg_replace('/\s+/u', ' ', $part));
        }
        $parts[] = $part;
    }
    if ($node->tagName === 'tr') {
        return implode('', $parts) === '' ? '' : implode(count($parts) === 2 && !$headers ? ': ' : ' | ', $parts) . "\n";
    }
    $text = implode('', $parts);
    if ($node->tagName === 'a') {
        return rx_richUi_fallbackLinkText(trim($text), $node->getAttribute('href'));
    }
    if ($node->tagName === 'tg-emoji' && trim($text) === '') {
        return '⭐';
    }
    if ($node->tagName === 'caption') {
        return trim($text) . "\n";
    }
    return $node->tagName === 'table' ? "\n" . trim($text) . "\n" : $text;
}

if (!function_exists('rx_richUi_tableToText')) {
    function rx_richUi_tableToText(string $tableHtml): string
    {
        $body = rx_richUi_fallbackDom($tableHtml);
        $table = $body ? $body->getElementsByTagName('table')->item(0) : null;
        return $table ? trim(rx_richUi_tableNodeText($table)) : '';
    }
}

if (!function_exists('rx_richUi_plainFromHtml')) {
    function rx_richUi_plainFromHtml(string $html, bool $preserveSpacing = false): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = rx_richUi_replaceTablesWithText($html);
        if (preg_match('~<(?:a|script|style)\b~i', $html) && ($body = rx_richUi_fallbackDom($html))) {
            foreach (['script', 'style'] as $tag) {
                $elements = $body->getElementsByTagName($tag);
                while ($elements->length > 0) {
                    $element = $elements->item(0);
                    $element->parentNode->removeChild($element);
                }
            }
            $anchors = iterator_to_array($body->getElementsByTagName('a'));
            foreach (array_reverse($anchors) as $anchor) {
                $label = trim($anchor->textContent);
                $text = rx_richUi_fallbackLinkText($label, $anchor->getAttribute('href'));
                $anchor->parentNode->replaceChild($body->ownerDocument->createTextNode($text), $anchor);
            }
            $html = '';
            foreach ($body->childNodes as $child) {
                $html .= $body->ownerDocument->saveHTML($child);
            }
        }
        $html = preg_replace('~<summary\b[^>]*>([\s\S]*?)</summary>~i', '$1' . "\n", $html);
        $html = preg_replace('~<li\b[^>]*>~i', "\n• ", $html);
        $html = preg_replace('~</li>~i', '', $html);
        $html = preg_replace('~<br\s*/?>~i', "\n", $html);
        $html = preg_replace('~<hr\s*/?>~i', "\n──────────\n", $html);
        $html = preg_replace('~</(p|h[1-6]|blockquote|aside|div|footer|figure|figcaption|ul|ol|pre|details|tr)>~i', "\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if (!$preserveSpacing) {
            $html = preg_replace('/[ \t]+/u', ' ', $html);
            $html = preg_replace('/\n{3,}/', "\n\n", $html);
        }
        return trim((string) $html);
    }
}

if (!function_exists('rx_richUi_replaceTablesWithText')) {
    function rx_richUi_replaceTablesWithText(string $html): string
    {
        if (!preg_match('~<table\b~i', $html)) {
            return $html;
        }
        $body = rx_richUi_fallbackDom($html);
        if (!$body || $body->getElementsByTagName('table')->length === 0) {
            return $html;
        }
        $doc = $body->ownerDocument;
        $tables = $body->getElementsByTagName('table');
        while ($tables->length > 0) {
            $table = $tables->item(0);
            $paragraph = $doc->createElement('p');
            foreach (explode("\n", trim(rx_richUi_tableNodeText($table))) as $index => $line) {
                if ($index > 0) {
                    $paragraph->appendChild($doc->createElement('br'));
                }
                $paragraph->appendChild($doc->createTextNode($line));
            }
            $table->parentNode->replaceChild($paragraph, $table);
        }
        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return $output;
    }
}

if (!function_exists('rx_richUi_plainText')) {
    function rx_richUi_plainText($text, $parseMode, bool $preserveSpacing = false): string
    {
        $mode = strtolower(trim((string) $parseMode));
        $text = rx_richUi_stripRtlMarker($text);
        if ($mode === 'html') {
            return rx_richUi_plainFromHtml($text, $preserveSpacing);
        }
        if (($mode === '' || $mode === 'none') && rx_richUi_htmlLike($text)) {
            return rx_richUi_plainFromHtml($text, $preserveSpacing);
        }
        return $text;
    }
}

if (!function_exists('rx_richUi_plainHtml')) {
    function rx_richUi_plainHtml($text, $parseMode): ?string
    {
        $mode = strtolower(trim((string) $parseMode));
        if ($mode !== 'html' && $mode !== '' && $mode !== 'none') {
            return null;
        }
        $plain = rx_richUi_plainText($text, $parseMode);
        return rx_richUi_textToHtml(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'html');
    }
}

if (!function_exists('rx_richUi_buttonHtml')) {
    function rx_richUi_buttonHtml(array $btn): ?string
    {
        $esc = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        if (!isset($btn['text']) || !is_string($btn['text'])) {
            return '';
        }
        $label = $esc($btn['text']);
        $emojiId = isset($btn['icon_custom_emoji_id']) ? trim((string) $btn['icon_custom_emoji_id']) : '';
        if ($emojiId !== '' && ctype_digit($emojiId)) {
            $label = '<tg-emoji emoji-id="' . $emojiId . '"></tg-emoji>' . $label;
        }
        if (trim($btn['text']) === '' && $emojiId === '') {
            return '';
        }
        $style = isset($btn['style']) && in_array($btn['style'], ['primary', 'success', 'danger'], true)
            ? ' style="' . $btn['style'] . '"' : '';

        if (isset($btn['callback_data'])) {
            $data = (string) $btn['callback_data'];
            if ($data === '' || strlen($data) > 64) {
                return null;
            }
            return '<tg-button type="callback_data"' . $style . ' data="' . $esc($data) . '">' . $label . '</tg-button>';
        }
        if (isset($btn['url'])) {
            return '<tg-button type="url"' . $style . ' url="' . $esc($btn['url']) . '">' . $label . '</tg-button>';
        }
        if (isset($btn['web_app']['url'])) {
            return '<tg-button type="web_app"' . $style . ' url="' . $esc($btn['web_app']['url']) . '">' . $label . '</tg-button>';
        }
        if (isset($btn['login_url']['url'])) {
            $extra = '';
            if (!empty($btn['login_url']['forward_text'])) {
                $extra .= ' forward-text="' . $esc($btn['login_url']['forward_text']) . '"';
            }
            if (!empty($btn['login_url']['request_write_access'])) {
                $extra .= ' request-write-access';
            }
            return '<tg-button type="login_url"' . $style . ' url="' . $esc($btn['login_url']['url']) . '"' . $extra . '>' . $label . '</tg-button>';
        }
        if (isset($btn['switch_inline_query'])) {
            return '<tg-button type="switch_inline_query"' . $style . ' query="' . $esc($btn['switch_inline_query']) . '">' . $label . '</tg-button>';
        }
        if (isset($btn['switch_inline_query_current_chat'])) {
            return '<tg-button type="switch_inline_query_current_chat"' . $style . ' query="' . $esc($btn['switch_inline_query_current_chat']) . '">' . $label . '</tg-button>';
        }
        if (isset($btn['copy_text']['text'])) {
            return '<tg-button type="copy_text"' . $style . ' text="' . $esc($btn['copy_text']['text']) . '">' . $label . '</tg-button>';
        }
        return null;
    }
}

if (!function_exists('rx_richUi_buttonsHtml')) {
    function rx_richUi_buttonsHtml(array $inlineRows): ?string
    {
        $html = '';
        foreach ($inlineRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cells = [];
            foreach ($row as $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                $cell = rx_richUi_buttonHtml($btn);
                if ($cell === null) {
                    return null;
                }
                if ($cell !== '') {
                    $cells[] = $cell;
                }
            }
            foreach (array_chunk($cells, 8) as $chunk) {
                $html .= '<tg-button-row align="center">' . implode('', $chunk) . '</tg-button-row>';
            }
        }
        return $html;
    }
}

if (!function_exists('rx_richUi_isRtl')) {
    function rx_richUi_isRtl(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }
}

if (!function_exists('rx_richUi_rtlMarker')) {
    function rx_richUi_rtlMarker($text)
    {
        if (preg_match('~<!--\s*rxrtl:([01])\s*-->~i', (string) $text, $m)) {
            return $m[1] === '1';
        }
        return null;
    }
}

if (!function_exists('rx_richUi_stripRtlMarker')) {
    function rx_richUi_stripRtlMarker($text): string
    {
        return (string) preg_replace('~<!--\s*(?:rxrtl:[01]|rxalign:swap)\s*-->\s*~i', '', (string) $text);
    }
}

if (!function_exists('rx_richUi_targetIsRichMessage')) {
    function rx_richUi_targetIsRichMessage($messageId): bool
    {
        $callbackMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
        if (!is_array($callbackMessage) || !isset($callbackMessage['rich_message'])) {
            return false;
        }
        return (int) ($callbackMessage['message_id'] ?? 0) === (int) $messageId && (int) $messageId > 0;
    }
}

if (!function_exists('rx_richUi_domText')) {
    function rx_richUi_domText(DOMNode $parent, int $depth = 0)
    {
        if ($depth > 16) return null;
        $out = [];
        $types = ['b' => 'bold', 'strong' => 'bold', 'i' => 'italic', 'em' => 'italic', 'u' => 'underline', 'ins' => 'underline', 's' => 'strikethrough', 'strike' => 'strikethrough', 'del' => 'strikethrough', 'code' => 'code', 'mark' => 'marked', 'sub' => 'subscript', 'sup' => 'superscript', 'tg-spoiler' => 'spoiler'];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) { $out[] = $node->nodeValue; continue; }
            if ($node instanceof DOMComment) continue;
            if (!($node instanceof DOMElement)) return null;
            $tag = strtolower($node->tagName);
            if ($tag === 'br') { $out[] = "\n"; continue; }
            $text = rx_richUi_domText($node, $depth + 1);
            if ($text === null) return null;
            if (isset($types[$tag])) $out[] = ['type' => $types[$tag], 'text' => $text];
            elseif ($tag === 'a' && preg_match('~^https?://~i', $node->getAttribute('href'))) $out[] = ['type' => 'url', 'text' => $text, 'url' => $node->getAttribute('href')];
            elseif ($tag === 'tg-emoji' && ctype_digit($node->getAttribute('emoji-id'))) $out[] = ['type' => 'custom_emoji', 'custom_emoji_id' => $node->getAttribute('emoji-id'), 'alternative_text' => $node->textContent !== '' ? $node->textContent : '⭐'];
            elseif ($tag === 'tg-time') {
                $item = ['type' => 'date_time', 'text' => $text, 'unix_time' => (int) $node->getAttribute('unix')];
                if ($node->hasAttribute('format')) $item['date_time_format'] = $node->getAttribute('format');
                $out[] = $item;
            } else return null;
        }
        if (!$out) return '';
        return count($out) === 1 ? $out[0] : $out;
    }
}

if (!function_exists('rx_richUi_domBlocks')) {
    function rx_richUi_domBlocks(DOMNode $parent, int $depth = 0): ?array
    {
        if ($depth > 16) return null;
        $blocks = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMComment) continue;
            if ($node instanceof DOMText) {
                if (trim($node->nodeValue) !== '') $blocks[] = ['type' => 'paragraph', 'text' => $node->nodeValue];
                continue;
            }
            if (!($node instanceof DOMElement)) return null;
            $tag = strtolower($node->tagName);
            if ($tag === 'table') {
                $cells = [];
                foreach ($node->getElementsByTagName('tr') as $row) {
                    $line = [];
                    foreach ($row->childNodes as $cell) {
                        if (!($cell instanceof DOMElement)) continue;
                        if (!in_array(strtolower($cell->tagName), ['td', 'th'], true)) return null;
                        $text = rx_richUi_domText($cell);
                        if ($text === null) return null;
                        $align = $cell->getAttribute('align');
                        $valign = $cell->getAttribute('valign');
                        $item = ['text' => $text, 'align' => in_array($align, ['left', 'center', 'right'], true) ? $align : 'left', 'valign' => in_array($valign, ['top', 'middle', 'bottom'], true) ? $valign : 'middle'];
                        if (strtolower($cell->tagName) === 'th') $item['is_header'] = true;
                        foreach (['colspan', 'rowspan'] as $span) if ((int) $cell->getAttribute($span) > 1) $item[$span] = (int) $cell->getAttribute($span);
                        $line[] = $item;
                    }
                    $cells[] = $line;
                }
                $block = ['type' => 'table', 'cells' => $cells];
                foreach (['bordered', 'striped', 'compact'] as $flag) if ($node->hasAttribute($flag)) $block['is_' . $flag] = true;
                $caption = $node->getElementsByTagName('caption')->item(0);
                if ($caption) {
                    $text = rx_richUi_domText($caption);
                    if ($text === null) return null;
                    $block['caption'] = $text;
                }
                $blocks[] = $block;
            } elseif ($tag === 'tg-button-row') {
                $buttons = [];
                foreach ($node->childNodes as $button) {
                    if ($button instanceof DOMText && trim($button->nodeValue) === '') continue;
                    if (!($button instanceof DOMElement) || strtolower($button->tagName) !== 'tg-button') return null;
                    $text = rx_richUi_domText($button);
                    if ($text === null) return null;
                    $item = ['text' => $text];
                    $type = $button->getAttribute('type');
                    if ($type === 'callback_data') $item['callback_data'] = $button->getAttribute('data');
                    elseif ($type === 'url') $item['url'] = $button->getAttribute('url');
                    elseif ($type === 'web_app') $item['web_app'] = ['url' => $button->getAttribute('url')];
                    elseif ($type === 'login_url') {
                        $item['login_url'] = ['url' => $button->getAttribute('url')];
                        if ($button->hasAttribute('forward-text')) $item['login_url']['forward_text'] = $button->getAttribute('forward-text');
                        if ($button->hasAttribute('request-write-access')) $item['login_url']['request_write_access'] = true;
                    } elseif ($type === 'switch_inline_query' || $type === 'switch_inline_query_current_chat') $item[$type] = $button->getAttribute('query');
                    elseif ($type === 'copy_text') $item['copy_text'] = ['text' => $button->getAttribute('text')];
                    else return null;
                    if ($button->hasAttribute('style')) $item['style'] = $button->getAttribute('style');
                    $buttons[] = $item;
                }
                $blocks[] = ['type' => 'buttons', 'buttons' => $buttons, 'align' => $node->getAttribute('align') ?: 'center'];
            } elseif ($tag === 'hr') {
                $blocks[] = ['type' => 'divider'];
            } elseif ($tag === 'pre') {
                $block = ['type' => 'pre', 'text' => $node->textContent];
                $code = $node->getElementsByTagName('code')->item(0);
                if ($code && preg_match('~^language-([a-z0-9_+-]+)$~i', $code->getAttribute('class'), $match)) $block['language'] = $match[1];
                $blocks[] = $block;
            } elseif ($tag === 'blockquote' && !$node->hasAttribute('expandable')) {
                $nested = rx_richUi_domBlocks($node, $depth + 1);
                if ($nested === null) return null;
                $blocks[] = ['type' => 'blockquote', 'blocks' => $nested];
            } elseif (in_array($tag, ['p', 'footer', 'blockquote', 'aside'], true) || preg_match('~^h[1-6]$~', $tag)) {
                $text = rx_richUi_domText($node);
                if ($text === null) return null;
                $type = $tag === 'footer' ? 'footer' : ($tag === 'blockquote' ? 'expandable_blockquote' : ($tag === 'aside' ? 'pullquote' : 'paragraph'));
                $block = ['type' => $type, 'text' => $text];
                if ($tag[0] === 'h') { $block['type'] = 'heading'; $block['size'] = (int) substr($tag, 1); }
                $blocks[] = $block;
            } else return null;
        }
        return $blocks;
    }
}

if (!function_exists('rx_richUi_structuredHtml')) {
    function rx_richUi_structuredHtml(string $html): ?array
    {
        if (!class_exists('DOMDocument')) return null;
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? rx_richUi_domBlocks($body) : null;
    }
}

if (!function_exists('rx_richUi_visibleRichText')) {
    function rx_richUi_visibleRichText($text): string
    {
        if (is_string($text)) return $text;
        if (!is_array($text)) return '';
        if (isset($text['type'])) {
            if (array_key_exists('text', $text)) return rx_richUi_visibleRichText($text['text']);
            if ($text['type'] === 'custom_emoji') return (string) ($text['alternative_text'] ?? '');
            if ($text['type'] === 'mathematical_expression') return (string) ($text['expression'] ?? '');
            return '';
        }
        $visible = '';
        foreach ($text as $part) $visible .= rx_richUi_visibleRichText($part);
        return $visible;
    }
}

if (!function_exists('rx_richUi_cellAlignment')) {
    function rx_richUi_cellAlignment(string $align): string
    {
        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'left';
    }
}

if (!function_exists('rx_richUi_cellParagraphs')) {
    function rx_richUi_cellParagraphs($text)
    {
        if (is_string($text)) {
            return preg_replace_callback('/\r\n|[\n\r\x{0085}\x{2029}]/u', static function ($match) {
                return "\u{2069}" . $match[0] . "\u{200E}\u{2067}";
            }, $text) ?? $text;
        }
        if (!is_array($text)) return $text;
        if (isset($text['type'])) {
            if (array_key_exists('text', $text)) $text['text'] = rx_richUi_cellParagraphs($text['text']);
            return $text;
        }
        foreach ($text as &$part) $part = rx_richUi_cellParagraphs($part);
        unset($part);
        return $text;
    }
}

if (!function_exists('rx_richUi_cellDirectionalText')) {
    function rx_richUi_cellDirectionalText($text, string $align)
    {
        if ($align === 'center') return $text;
        $visible = rx_richUi_visibleRichText($text);
        if (strncmp($visible, "\u{200E}\u{2067}", strlen("\u{200E}\u{2067}")) === 0) return $text;
        if (!preg_match('/\p{L}/u', $visible, $first)
            || !preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $first[0])) return $text;
        $text = rx_richUi_cellParagraphs($text);
        $parts = is_array($text) && !isset($text['type']) ? array_values($text) : [$text];
        array_unshift($parts, "\u{200E}\u{2067}");
        $parts[] = "\u{2069}";
        return $parts;
    }
}

if (!function_exists('rx_richUi_physicalTableLayout')) {
    function rx_richUi_physicalTableLayout(array $blocks, bool $rtl): ?array
    {
        foreach ($blocks as &$block) {
            if (isset($block['blocks'])) {
                $nested = rx_richUi_physicalTableLayout($block['blocks'], $rtl);
                if ($nested === null) { unset($block); return null; }
                $block['blocks'] = $nested;
            }
            if (($block['type'] ?? '') === 'list') {
                foreach ($block['items'] as &$item) {
                    $nested = rx_richUi_physicalTableLayout($item['blocks'] ?? [], $rtl);
                    if ($nested === null) { unset($item, $block); return null; }
                    $item['blocks'] = $nested;
                }
                unset($item);
            }
            if (($block['type'] ?? '') !== 'table') continue;
            $rows = $block['cells'] ?? [];
            $grid = [];
            $records = [];
            $width = 0;
            foreach ($rows as $r => $row) {
                $c = 0;
                foreach ($row as $cell) {
                    while (isset($grid[$r][$c])) $c++;
                    $cols = max(1, (int) ($cell['colspan'] ?? 1));
                    $span = max(1, (int) ($cell['rowspan'] ?? 1));
                    if ($c + $cols > 20 || $r + $span > count($rows)) { unset($block); return null; }
                    $id = count($records);
                    $align = $cell['align'] ?? 'left';
                    $cell['align'] = rx_richUi_cellAlignment((string) $align);
                    $cell['text'] = rx_richUi_cellDirectionalText($cell['text'] ?? '', $cell['align']);
                    $records[] = ['cell' => $cell, 'r' => $r, 'c' => $c, 'cols' => $cols, 'span' => $span];
                    for ($rr = $r; $rr < $r + $span; $rr++) {
                        for ($cc = $c; $cc < $c + $cols; $cc++) {
                            if (isset($grid[$rr][$cc])) { unset($block); return null; }
                            $grid[$rr][$cc] = $id;
                        }
                    }
                    $c += $cols;
                    $width = max($width, $c);
                }
            }
            $origins = [];
            $occupied = [];
            foreach ($records as $id => $record) {
                $column = $rtl ? $width - $record['c'] - $record['cols'] : $record['c'];
                $origins[$record['r']][$column] = $id;
                for ($r = $record['r']; $r < $record['r'] + $record['span']; $r++) {
                    for ($c = $column; $c < $column + $record['cols']; $c++) $occupied[$r][$c] = true;
                }
            }
            $physicalRows = [];
            foreach ($rows as $r => $row) {
                $physical = [];
                for ($c = 0; $c < $width;) {
                    if (isset($origins[$r][$c])) {
                        $record = $records[$origins[$r][$c]];
                        $physical[] = $record['cell'];
                        $c += $record['cols'];
                    } elseif (isset($occupied[$r][$c])) {
                        $c++;
                    } else {
                        $physical[] = ['text' => '', 'align' => 'left', 'valign' => 'middle'];
                        $c++;
                    }
                }
                $physicalRows[] = $physical;
            }
            $block['cells'] = $physicalRows;
        }
        unset($block);
        return $blocks;
    }
}

if (!function_exists('rx_richUi_buildMessage')) {
    function rx_richUi_buildMessage(string $textHtml, string $buttonsHtml, array $media = [], $rtlOverride = null): array
    {
        $hasTable = (bool) preg_match('~<table\b~i', $textHtml);
        $inputMetrics = null;
        if ($hasTable) {
            $inputMetrics = rx_richDiag_text($textHtml);
            rx_richDiag_log('RICH_TABLE_RENDER_START', 'table_render', 'started', [
                'text' => $inputMetrics,
                'buttons_length' => strlen($buttonsHtml),
                'media_count' => count($media),
                'rtl_override' => $rtlOverride,
            ], 'debug');
        }
        $rtl = $rtlOverride === null ? rx_richUi_isRtl(strip_tags($textHtml)) : (bool) $rtlOverride;
        $html = $textHtml;
        $blocks = !$media && preg_match('~<table\b~i', $html) ? rx_richUi_structuredHtml($html . $buttonsHtml) : null;
        $message = ['is_rtl' => $rtl];
        if ($blocks) {
            $physicalBlocks = rx_richUi_physicalTableLayout($blocks, $rtl);
            if ($physicalBlocks !== null) {
                $message['blocks'] = $physicalBlocks;
                $message['is_rtl'] = false;
            } else {
                $message['html'] = $html . $buttonsHtml;
            }
        } else $message['html'] = $html . $buttonsHtml;
        if ($media) {
            $message['media'] = $media;
        }
        if ($hasTable) {
            $tableBlocks = 0;
            $tableRows = 0;
            if (isset($message['blocks']) && is_array($message['blocks'])) {
                foreach ($message['blocks'] as $block) {
                    if (!is_array($block) || ($block['type'] ?? '') !== 'table') {
                        continue;
                    }
                    $tableBlocks++;
                    $tableRows += isset($block['cells']) && is_array($block['cells']) ? count($block['cells']) : 0;
                }
            }
            rx_richDiag_log('RICH_TABLE_RENDER_END', 'table_render', 'completed', [
                'render_mode' => isset($message['blocks']) ? 'blocks' : 'html',
                'structured_parse' => $blocks !== null,
                'physical_layout' => isset($message['blocks']),
                'blocks' => isset($message['blocks']) && is_array($message['blocks']) ? count($message['blocks']) : 0,
                'table_blocks' => $tableBlocks,
                'table_rows' => $tableRows,
                'html_preserved' => !isset($message['blocks']),
                'html_length' => isset($message['html']) ? strlen($message['html']) : 0,
                'is_rtl' => $message['is_rtl'],
                'media_count' => count($media),
            ], isset($message['blocks']) ? 'debug' : 'warning');
            rx_richDiag_comparison('table_render', rx_richDiag_currentKey(), $inputMetrics ?? [], $inputMetrics ?? [], [
                'render_mode' => isset($message['blocks']) ? 'blocks' : 'html',
                'rich_json_blocks' => isset($message['blocks']) && is_array($message['blocks']) ? count($message['blocks']) : 0,
                'structure_available' => $blocks !== null,
                'after' => [
                    'rich_blocks' => isset($message['blocks']) && is_array($message['blocks']) ? count($message['blocks']) : 0,
                ],
                'phase' => 'rich_table_render',
            ]);
        }
        return $message;
    }
}

if (!function_exists('rx_richUi_validateInlineText')) {
    function rx_richUi_validateInlineText($text, int $depth = 0): bool
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
                    && rx_richUi_validateInlineText($text['text'], $depth + 1);
            }
            if ($type === 'mathematical_expression') {
                return isset($text['expression']) && is_string($text['expression']);
            }
            return array_key_exists('text', $text) && rx_richUi_validateInlineText($text['text'], $depth + 1);
        }
        foreach ($text as $part) {
            if (!rx_richUi_validateInlineText($part, $depth + 1)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('rx_richUi_validateBlockTree')) {
    function rx_richUi_validateBlockTree(array $blocks, int $depth = 0): array
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
                $nested = rx_richUi_validateBlockTree($block['blocks'], $depth + 1);
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
                    $nested = rx_richUi_validateBlockTree($item['blocks'], $depth + 1);
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
                        if (!is_array($cell) || !array_key_exists('text', $cell) || !rx_richUi_validateInlineText($cell['text'])) {
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
                if (isset($block['caption']) && !rx_richUi_validateInlineText($block['caption'])) {
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
                    if (!is_array($button) || !isset($button['text']) || !rx_richUi_validateInlineText($button['text'])) {
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
                if (!array_key_exists('text', $block) || !rx_richUi_validateInlineText($block['text'])) {
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
}

if (!function_exists('rx_richUi_richHasTable')) {
    function rx_richUi_richHasTable($blocks, int $depth = 0): bool
    {
        if ($depth > 16 || !is_array($blocks)) {
            return false;
        }
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'table') {
                return true;
            }
            if (isset($block['blocks']) && is_array($block['blocks']) && rx_richUi_richHasTable($block['blocks'], $depth + 1)) {
                return true;
            }
            if (isset($block['items']) && is_array($block['items'])) {
                foreach ($block['items'] as $item) {
                    if (is_array($item) && isset($item['blocks']) && is_array($item['blocks']) && rx_richUi_richHasTable($item['blocks'], $depth + 1)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

if (!function_exists('rx_richUi_validateGeneralRichPayload')) {
    function rx_richUi_validateGeneralRichPayload(array $richMessage, array $context = []): array
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
            $tree = rx_richUi_validateBlockTree($richMessage['blocks']);
            $result['blocks'] = (int) $tree['blocks'];
            $result['tables'] = (int) $tree['tables'];
            $result['rows'] = (int) $tree['rows'];
            $result['max_columns'] = (int) $tree['max_columns'];
            $result['mode'] = 'blocks';
            $result['has_table'] = $tree['tables'] > 0 || rx_richUi_richHasTable($richMessage['blocks']);
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
}

if (!function_exists('rx_richUi_validateRichPayload')) {
    function rx_richUi_validateRichPayload(array $richMessage, array $context = []): array
    {
        $general = rx_richUi_validateGeneralRichPayload($richMessage, $context);
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
        $tree = rx_richUi_validateBlockTree($richMessage['blocks']);
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
        $visible = rx_richUi_visibleRichText($richMessage['blocks']);
        $result['visible_length'] = function_exists('rx_richDiag_utf16Length') ? rx_richDiag_utf16Length($visible) : strlen($visible);
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
}


if (!function_exists('rx_richUi_isFallbackError')) {
    function rx_richUi_isFallbackError($result): bool
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

if (!function_exists('rx_richUi_attempt')) {
    function rx_richUi_attempt(string $apiMethod, array $base, string $textHtml, string $plainHtml, string $buttonsHtml, array $media, $token, $rtlOverride = null, array $targetMeta = [])
    {
        unset($GLOBALS['rx_rich_diag_validation_reason']);
        $variants = [['full', $textHtml]];
        if (preg_match('~<table\b~i', $textHtml)) {
            $tableTextHtml = rx_richUi_replaceTablesWithText($textHtml);
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
            $richMessage = rx_richUi_buildMessage($html, $buttonsHtml, $media, $rtlOverride);
            $validation = rx_richUi_validateRichPayload($richMessage, ['text' => $html, 'media_count' => count($media), 'method' => $apiMethod] + $targetMeta);
            if (!empty($validation['has_table'])) {
                $validationMetrics = rx_richDiag_text($html);
                rx_richDiag_comparison('table_validation', rx_richDiag_currentKey(), $validationMetrics, $validationMetrics, [
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
                rx_richDiag_log('RICH_RENDER_FAILED', 'validation', 'rejected', [
                    'api_method' => $apiMethod,
                    'variant' => $label,
                    'reason' => $validation['reason'],
                    'validation' => $validation,
                    'table_present' => $validation['has_table'],
                ], 'warning');
                continue;
            }
            $payload['rich_message'] = json_encode($richMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $variantMetrics = rx_richDiag_text($html);
            rx_richDiag_log('RICH_PAYLOAD_PREPARED', 'payload', 'prepared', [
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
            rx_richDiag_comparison('payload_preparation', rx_richDiag_currentKey(), $variantMetrics, $variantMetrics, [
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
                    rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'payload', 'degraded', [
                        'reason' => $degradedReason !== '' ? $degradedReason : 'structured_table_unavailable',
                        'intended_mode' => 'structured_table',
                        'initiator' => 'rx_richUi_attempt',
                        'selected_variant' => $label,
                        'api_method' => $apiMethod,
                        'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
                        'table_formatting_lost' => true,
                    ], 'warning');
                    rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'payload', 'completed', [
                        'fallback_method' => $label,
                        'delivery_mode' => 'degraded_' . $label,
                        'reason' => $degradedReason !== '' ? $degradedReason : 'structured_table_unavailable',
                        'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
                        'result' => rx_richDiag_result($result),
                    ], 'info');
                }
                if ($withRemove) {
                    unset($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]);
                    if (function_exists('update')) {
                        update('user', 'reply_kb_cleared', '1', 'id', $chatKey);
                    }
                }
                return $result;
            }
            $last = $result;
            rx_richDiag_log('RICH_RENDER_FAILED', 'payload', 'rejected', [
                'api_method' => $apiMethod,
                'variant' => $label,
                'result' => rx_richDiag_result($result),
                'fallback_eligible' => rx_richUi_isFallbackError($result),
                'table_present' => (bool) preg_match('~<table\b~i', $html),
                'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $html),
            ], 'warning');
            if ($label === 'full' && preg_match('~<table\b~i', $textHtml)) {
                $description = is_array($result) ? rx_richDiag_sanitize((string) ($result['description'] ?? 'unknown response')) : 'invalid response';
                $log = '[rich-template] ' . $apiMethod . ' rejected table: ' . $description;
                if (function_exists('faoxima_dedup_error_log')) {
                    faoxima_dedup_error_log('rich_template_' . hash('sha256', $log), $log, 300);
                } else {
                    error_log($log);
                }
            }
            if ($label === 'full') {
                $degraded = true;
                $degradedReason = 'api_rejection';
            }
            if (!rx_richUi_isFallbackError($result)) {
                return $result;
            }
        }
        rx_richDiag_log('RICH_RENDER_FAILED', 'payload', 'exhausted', [
            'api_method' => $apiMethod,
            'variants' => count($variants),
            'last_error' => rx_richDiag_result($last),
            'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
            'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
        ], 'error');
        $GLOBALS['rx_rich_diag_last_rejection'] = rx_richDiag_result($last);
        return ['__fallback' => true, 'last' => $last];
    }
}

if (!function_exists('rx_richUi_plainCall')) {
    function rx_richUi_plainCall(string $method, array $payload, $token)
    {
        $GLOBALS['rx_richUi_plain_call'] = (int) ($GLOBALS['rx_richUi_plain_call'] ?? 0) + 1;
        try {
            return telegram($method, $payload, $token);
        } finally {
            $GLOBALS['rx_richUi_plain_call'] = max(0, (int) ($GLOBALS['rx_richUi_plain_call'] ?? 1) - 1);
        }
    }
}

if (!function_exists('rx_richUi_sendPlainFallback')) {
    function rx_richUi_sendPlainFallback(string $method, array $datas, $token)
    {
        $text = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        $fallbackReason = (string) ($GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_fallback_call');
        $fallbackText = $text === '' ? ['present' => false] : rx_richDiag_text($text);
        if ($text === '') {
            rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_fallback', 'skipped', [
                'reason' => 'empty_text',
                'intended_mode' => 'rich_message',
                'initiator' => 'rx_richUi_sendPlainFallback',
                'fallback_method' => $method,
                'original' => $fallbackText,
            ], 'warning');
            return null;
        }
        rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_fallback', 'started', [
            'reason' => $fallbackReason,
            'intended_mode' => 'rich_message',
            'initiator' => 'rx_richUi_sendPlainFallback',
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
        $plain = rx_richUi_plainText($text, $datas['parse_mode'] ?? '');
        if (trim($plain) === '') {
            rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', 'skipped', [
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
        $chunks = rx_richUi_plainChunks($plain);
        if (count($chunks) === 1) {
            $single = rx_richUi_plainCall($method, $payload, $token);
            $state = rxMediaResultState($single);
            rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', $state === 'confirmed' ? 'completed' : $state, [
                'delivery_mode' => 'plain_text',
                'fallback_method' => $method,
                'result' => rx_richDiag_result($single),
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
            rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', 'failed', [
                'reason' => 'inline_message_cannot_split',
                'fallback_method' => $method,
                'result' => rx_richDiag_result($inlineFailure),
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
            $result = rx_richUi_plainCall($partMethod, $part, $token);
            $notModified = $index === 0 && strtolower($method) === 'editmessagetext'
                && is_array($result) && (int) ($result['error_code'] ?? 0) === 400
                && stripos((string) ($result['description'] ?? ''), 'message is not modified') !== false;
            if ((!is_array($result) || empty($result['ok'])) && !$notModified) {
                $state = rxMediaResultState($result);
                $status = $state === 'unknown' ? 'unknown' : ($index > 0 ? 'partial' : 'failed');
                rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', $status, [
                    'delivery_mode' => 'plain_text',
                    'fallback_method' => $method,
                    'result' => rx_richDiag_result($result),
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
        rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_fallback', is_array($result) && !empty($result['ok']) ? 'completed' : 'failed', [
            'delivery_mode' => 'plain_text',
            'fallback_method' => $method,
            'result' => rx_richDiag_result($result),
            'chunks' => count($chunks),
            'plain_length' => strlen($plain),
            'table_formatting_lost' => !empty($fallbackText['html_table']),
            'premium_markup_removed' => !empty($fallbackText['tg_emoji_tags']),
            'parse_mode_dropped' => isset($datas['parse_mode']) && (string) $datas['parse_mode'] !== '',
        ], is_array($result) && !empty($result['ok']) ? 'info' : 'error');
        return $result;
    }
}

if (!function_exists('rx_richUi_plainChunks')) {
    function rx_richUi_plainChunks(string $text): array
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
}

if (!function_exists('rx_richUi_inputBlocks')) {
    function rx_richUi_inputBlocks(array $blocks, bool $stripButtons = false): ?array
    {
        $out = [];
        $mediaTypes = ['photo', 'video', 'animation', 'audio', 'document', 'voice_note'];
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['type'])) {
                return null;
            }
            $type = $block['type'];
            if ($stripButtons && $type === 'buttons') {
                continue;
            }
            if (isset($block['blocks'])) {
                $nested = rx_richUi_inputBlocks($block['blocks']);
                if ($nested === null) {
                    return null;
                }
                $block['blocks'] = $nested;
            }
            if ($type === 'list') {
                foreach ($block['items'] as &$item) {
                    $nested = rx_richUi_inputBlocks($item['blocks'] ?? []);
                    if ($nested === null) {
                        unset($item);
                        return null;
                    }
                    $item['blocks'] = $nested;
                    unset($item['label']);
                }
                unset($item);
            }
            if (in_array($type, $mediaTypes, true)) {
                $source = $block[$type] ?? null;
                if ($type === 'photo' && is_array($source)) {
                    $source = end($source);
                }
                if (!is_array($source) || empty($source['file_id'])) {
                    return null;
                }
                $media = ['type' => $type, 'media' => $source['file_id']];
                $fields = ['duration'];
                if (in_array($type, ['video', 'animation'], true)) {
                    $fields = array_merge($fields, ['width', 'height']);
                } elseif ($type === 'audio') {
                    $fields = array_merge($fields, ['title', 'performer']);
                }
                foreach ($fields as $field) {
                    if (isset($source[$field])) {
                        $media[$field] = $source[$field];
                    }
                }
                if (isset($block['has_spoiler'])) {
                    $media['has_spoiler'] = $block['has_spoiler'];
                    unset($block['has_spoiler']);
                }
                $block[$type] = $media;
            }
            $out[] = $block;
        }
        return $out;
    }
}

if (!function_exists('rx_richUi_editRichButtons')) {
    function rx_richUi_editRichButtons(array $datas, array $rich, array $rows, $token)
    {
        $blocks = isset($rich['blocks']) && is_array($rich['blocks']) ? rx_richUi_inputBlocks($rich['blocks'], true) : null;
        if ($blocks === null || !$blocks) {
            return ['ok' => false, 'error_code' => 400, 'description' => 'Cannot preserve rich message blocks while updating buttons'];
        }
        $embedButtons = function_exists('rx_richActiveForSend') && rx_richActiveForSend($datas['chat_id'], $datas['reply_markup'] ?? null);
        foreach ($embedButtons ? $rows : [] as $row) {
            $buttons = [];
            foreach ($row as $button) {
                if (!is_array($button) || !isset($button['text']) || !is_string($button['text'])) {
                    continue;
                }
                $icon = $button['icon_custom_emoji_id'] ?? '';
                unset($button['icon_custom_emoji_id']);
                if ($icon !== '' && ctype_digit((string) $icon)) {
                    $button['text'] = [['type' => 'custom_emoji', 'custom_emoji_id' => (string) $icon, 'alternative_text' => '⭐'], $button['text']];
                }
                $buttons[] = $button;
            }
            foreach (array_chunk($buttons, 8) as $chunk) {
                $blocks[] = ['type' => 'buttons', 'align' => 'center', 'buttons' => $chunk];
            }
        }
        $richPayload = ['blocks' => $blocks, 'is_rtl' => !empty($rich['is_rtl'])];
        $richPayloadValidation = rx_richUi_validateRichPayload($richPayload, ['method' => 'editMessageText']);
        if (empty($richPayloadValidation['valid'])) {
            rx_richDiag_log('RICH_RENDER_FAILED', 'edit_rich_buttons', 'validation_failed', [
                'reason' => (string) ($richPayloadValidation['reason'] ?? 'rich_validation_failed'),
                'validation' => $richPayloadValidation,
            ], 'error');
            return [
                'ok' => false,
                'error_code' => 400,
                'description' => 'rich button update validation failed: ' . (string) ($richPayloadValidation['reason'] ?? 'rich_validation_failed'),
                '_rx_validation_failed' => true,
            ];
        }
        $payload = [
            'chat_id' => $datas['chat_id'],
            'message_id' => $datas['message_id'],
            'rich_message' => json_encode($richPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '_rx_already_transformed' => true,
        ];
        if (!$embedButtons) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (isset($datas['business_connection_id'])) {
            $payload['business_connection_id'] = $datas['business_connection_id'];
        }
        return rx_richUi_plainCall('editMessageText', $payload, $token);
    }
}

if (!function_exists('rx_richUi_sendPlainMediaFallback')) {
    function rx_richUi_sendPlainMediaFallback(string $method, array $datas, $token)
    {
        $caption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        $fallbackReason = (string) ($GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_media_fallback_call');
        $fallbackCaption = $caption === '' ? ['present' => false] : rx_richDiag_text($caption);
        if ($caption === '') {
            rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'skipped', [
                'reason' => 'empty_caption',
                'intended_mode' => 'rich_media_message',
                'initiator' => 'rx_richUi_sendPlainMediaFallback',
                'fallback_method' => $method,
            ], 'warning');
            return null;
        }
        rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'started', [
            'reason' => $fallbackReason,
            'intended_mode' => 'rich_media_message',
            'initiator' => 'rx_richUi_sendPlainMediaFallback',
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
        $plain = rx_richUi_plainText($caption, $datas['parse_mode'] ?? '', true);
        if (trim($plain) === '') {
            rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'plain_media_fallback', 'no_readable_caption', [
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
        rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'plain_media_fallback', $delivery['delivery_status'], [
            'delivery_mode' => 'plain_media',
            'fallback_method' => $method,
            'result' => rx_richDiag_result($result),
            'plain_length' => strlen($plain),
            'table_formatting_lost' => !empty($fallbackCaption['html_table']),
            'premium_markup_removed' => !empty($fallbackCaption['tg_emoji_tags']),
            'parse_mode_dropped' => false,
        ] + $delivery, $delivery['delivery_status'] === 'completed' ? 'info' : 'warning');
        return $result;
    }
}

if (!function_exists('rx_richUi_dispatch')) {
    function rx_richUi_dispatch($method, array $datas, $token)
    {
        $m = strtolower((string) $method);
        $mediaKeys = ['sendphoto' => 'photo', 'sendvideo' => 'video', 'senddocument' => 'document', 'sendanimation' => 'animation'];
        if (isset($datas['rich_message'])) {
            $rawRichMessage = $datas['rich_message'];
            $prebuilt = is_string($rawRichMessage) ? trim($rawRichMessage) : $rawRichMessage;
            if (is_string($prebuilt) ? $prebuilt !== '' : !empty($prebuilt)) {
                $decodedRichMessage = is_string($rawRichMessage) ? json_decode($rawRichMessage, true) : (is_array($rawRichMessage) ? $rawRichMessage : null);
                $prebuiltValidation = is_array($decodedRichMessage)
                    ? rx_richUi_validateRichPayload($decodedRichMessage, ['method' => (string) $method])
                    : ['valid' => false, 'reason' => 'rich_json_invalid', 'mode' => 'unknown'];
                if (empty($prebuiltValidation['valid'])) {
                    $validationReason = (string) ($prebuiltValidation['reason'] ?? 'rich_validation_failed');
                    rx_richDiag_log('RICH_RENDER_FAILED', 'prebuilt_validation', 'rejected', [
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
                return rx_richDiag_dispatchSkip('prebuilt_rich_payload', [
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
            return rx_richDiag_dispatchSkip('token_mismatch', ['api_method' => (string) $method]);
        }
        if (!empty($GLOBALS['rx_richUi_plain_call'])) {
            return rx_richDiag_dispatchSkip('plain_call_guard', ['api_method' => (string) $method]);
        }
        $chatId = $datas['chat_id'] ?? null;
        if (!empty($datas['entities']) || !empty($datas['caption_entities'])) {
            return rx_richDiag_dispatchSkip('entities_present', ['api_method' => (string) $method]);
        }
        $parseMode = $datas['parse_mode'] ?? '';
        $mode = strtolower(trim((string) $parseMode));
        if (!in_array($mode, ['', 'none', 'html'], true)) {
            return rx_richDiag_dispatchSkip('parse_mode_unsupported', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
        }
        $rawText = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        $rawCaption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        $rtlOverride = rx_richUi_rtlMarker($rawText);
        if ($rtlOverride === null) {
            $rtlOverride = rx_richUi_rtlMarker($rawCaption);
        }
        $htmlLike = rx_richUi_htmlLike($rawText) || rx_richUi_htmlLike($rawCaption);
        $needsRich = rx_richUi_requiresRich($rawText) || rx_richUi_requiresRich($rawCaption);
        $needsIntercept = $needsRich || ($mode !== 'html' && $htmlLike);
        $isUserUi = rx_richUi_chatIsUserUi($chatId);
        if (!$isUserUi) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'non_user_chat_rich_blocked';
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'non_user_chat_media_rich_blocked';
                $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            return rx_richDiag_dispatchSkip('non_user_chat_no_fallback', ['api_method' => (string) $method, 'is_user_ui' => false]);
        }
        $explicitRich = $rtlOverride !== null || preg_match('~<table\b~i', $rawText . $rawCaption) === 1;
        $embedButtons = function_exists('rx_richActiveForSend') && rx_richActiveForSend($chatId, $datas['reply_markup'] ?? null);
        $destRich = $explicitRich || $embedButtons;
        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'dispatch_enter', 'observed', [
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
            'text' => rx_richDiag_text($rawText),
            'caption' => $rawCaption === '' ? ['present' => false] : rx_richDiag_text($rawCaption),
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
                rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'replacement_selected', [
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
                return rx_richUi_replaceMessage($datas, $token, $editId, !$destRich);
            }
        }
        if (!$destRich) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'rich_mode_not_required';
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if ($needsIntercept && isset($mediaKeys[$m])) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = 'rich_mode_not_required_media';
                $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if (!($m === 'editmessagereplymarkup' && rx_richUi_targetIsRichMessage((int) ($datas['message_id'] ?? 0)))) {
                return rx_richDiag_dispatchSkip('passthrough_legacy_message', ['api_method' => (string) $method]);
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
                    $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'markup_without_inline_keyboard_media';
                    $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return rx_richDiag_dispatchSkip('markup_without_inline_keyboard_no_fallback', ['api_method' => (string) $method]);
            }
            if ($embedButtons) $buttonsHtml = rx_richUi_buttonsHtml($markup['inline_keyboard']);
            if ($buttonsHtml === null) {
                if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'buttons_html_unbuildable';
                    $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $GLOBALS['rx_rich_diag_fallback_reason'] = 'buttons_html_unbuildable_media';
                    $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return rx_richDiag_dispatchSkip('buttons_html_unbuildable_no_fallback', ['api_method' => (string) $method]);
            }
        }

        $passKeys = ['message_thread_id', 'disable_notification', 'protect_content', 'message_effect_id', 'reply_parameters'];
        if ($nativeMarkup !== null) $passKeys[] = 'reply_markup';
        $media = [];

        if (isset($mediaKeys[$m])) {
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return rx_richDiag_dispatchSkip('media_rich_not_required', ['api_method' => (string) $method, 'media_kind' => $mediaKeys[$m]]);
            }
            return rx_richUi_dispatchMedia($m, $mediaKeys[$m], $datas, $buttonsHtml, $passKeys, $token);
        }

        if ($m === 'sendmessage') {
            if (!isset($datas['text']) || !is_string($datas['text'])) {
                return rx_richDiag_dispatchSkip('missing_text', ['api_method' => (string) $method]);
            }
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return rx_richDiag_dispatchSkip('rich_not_required', ['api_method' => (string) $method]);
            }
            $textHtml = rx_richUi_textToHtml($datas['text'], $parseMode);
            $plainHtml = (string) rx_richUi_plainHtml($datas['text'], $parseMode);
            if ($textHtml === null || $textHtml === '') {
                return rx_richDiag_dispatchSkip('html_conversion_failed', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
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
            if (rx_ui_section_for_send($chatId, $datas['reply_markup'] ?? null) === 'main_menu') {
                rx_richUi_markMainMenuKeyboardRemoval($chatId);
            }
        } else {
            $messageId = (int) ($datas['message_id'] ?? 0);
            if ($messageId <= 0 || isset($datas['inline_message_id'])) {
                return rx_richDiag_dispatchSkip('invalid_edit_target', ['api_method' => (string) $method]);
            }
            $targetIsRich = rx_richUi_targetIsRichMessage($messageId);
            if ($m === 'editmessagereplymarkup') {
                if (!$targetIsRich) {
                    return rx_richDiag_dispatchSkip('reply_markup_target_not_rich', ['api_method' => (string) $method]);
                }
                $cbRich = $GLOBALS['update']['callback_query']['message']['rich_message'] ?? null;
                return rx_richUi_editRichButtons($datas, is_array($cbRich) ? $cbRich : [], $markup['inline_keyboard'] ?? [], $token);
            } else {
                if ($buttonsHtml === '' && !$targetIsRich && !$needsIntercept) {
                    return rx_richDiag_dispatchSkip('edit_rich_not_required', ['api_method' => (string) $method]);
                }
                if (!isset($datas['text']) || !is_string($datas['text'])) {
                    return rx_richDiag_dispatchSkip('missing_edit_text', ['api_method' => (string) $method]);
                }
                $textHtml = rx_richUi_textToHtml($datas['text'], $parseMode);
                $plainHtml = (string) rx_richUi_plainHtml($datas['text'], $parseMode);
            }
            if ($textHtml === null || $textHtml === '') {
                return rx_richDiag_dispatchSkip('edit_html_conversion_failed', ['api_method' => (string) $method, 'parse_mode' => (string) $parseMode]);
            }
            $base = ['chat_id' => $chatId, 'message_id' => $messageId];
            if ($nativeMarkup !== null) {
                if (!isset($markup['inline_keyboard'])) {
                    rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'replacement_selected', [
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
                    return rx_richUi_replaceMessage($datas, $token, $messageId);
                }
                $base['reply_markup'] = $nativeMarkup;
            }
            $apiMethod = 'editMessageText';
        }

        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'send_method', 'selected', [
            'api_method' => $apiMethod,
            'delivery_mode' => $apiMethod === 'sendRichMessage' ? 'rich_message' : 'rich_edit',
            'parse_mode' => (string) $parseMode,
            'text' => rx_richDiag_text($textHtml),
            'buttons_length' => strlen($buttonsHtml),
            'media_count' => count($media),
            'rtl_override' => $rtlOverride,
            'target_is_rich' => $m === 'sendmessage' ? null : ($targetIsRich ?? null),
            'needs_intercept' => $needsIntercept,
            'explicit_rich' => $explicitRich,
        ], 'info');
        rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', 'selected', [
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
            'target_rich_meta' => rx_richDiag_targetRichMeta(),
        ], 'info');
        $rxTargetMessageId = (int) ($datas['message_id'] ?? 0);
        $rxCallbackMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
        $rxEditTargetKnown = $m === 'editmessagetext' && $rxTargetMessageId > 0 && is_array($rxCallbackMessage) && (int) ($rxCallbackMessage['message_id'] ?? 0) === $rxTargetMessageId && !isset($datas['inline_message_id']);
        $rxEditTargetIsRich = $rxEditTargetKnown ? isset($rxCallbackMessage['rich_message']) : null;
        $rxEditTargetLegacy = $rxEditTargetKnown && !$rxEditTargetIsRich && (isset($rxCallbackMessage['text']) || !empty($rxCallbackMessage['photo']) || !empty($rxCallbackMessage['video']) || !empty($rxCallbackMessage['animation']) || !empty($rxCallbackMessage['document']));
        $result = rx_richUi_attempt($apiMethod, $base, $textHtml, $plainHtml, $buttonsHtml, $media, $token, $rtlOverride, [
            'is_edit' => $apiMethod === 'editMessageText',
            'is_cb_target' => $rxEditTargetKnown,
            'edit_target_known' => $rxEditTargetIsRich === true || $rxEditTargetLegacy,
            'target_is_rich' => $rxEditTargetIsRich,
        ]);
        rx_richDiag_log('RICH_MESSAGE_TARGET', 'dispatch_target', is_array($result) && !empty($result['ok']) ? 'delivered' : 'not_delivered', [
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
            'result' => rx_richDiag_result($result),
            'target_rich_meta' => rx_richDiag_targetRichMeta(),
        ], is_array($result) && !empty($result['ok']) ? 'info' : 'warning');
        if (is_array($result) && isset($result['__fallback'])) {
            $rxFallbackReason = !empty($GLOBALS['rx_rich_diag_validation_reason']) ? ('table_validation_failed:' . $GLOBALS['rx_rich_diag_validation_reason']) : 'rich_payload_rejected';
            rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'dispatch_result', 'fallback', [
                'reason' => $rxFallbackReason,
                'intended_mode' => 'rich_message',
                'initiator' => 'rx_richUi_dispatch',
                'api_method' => $apiMethod,
                'initial_request_failed' => true,
                'last_error' => rx_richDiag_result($result['last'] ?? null),
                'table_present' => (bool) preg_match('~<table\b~i', $textHtml),
                'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
            ], 'warning');
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $GLOBALS['rx_rich_diag_fallback_reason'] = $rxFallbackReason;
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'dispatch_result', is_array($fallback) && !empty($fallback['ok']) ? 'completed' : 'failed', [
                        'reason' => $rxFallbackReason,
                        'fallback_method' => $m,
                        'result' => rx_richDiag_result($fallback),
                        'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
                        'premium_present' => (bool) preg_match('~<tg-emoji\b~i', $textHtml),
                    ], is_array($fallback) && !empty($fallback['ok']) ? 'info' : 'error');
                    unset($GLOBALS['rx_rich_diag_validation_reason']);
                    return $fallback;
                }
            }
            rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'dispatch_result', 'unavailable', [
                'reason' => 'plain_fallback_not_applicable',
                'api_method' => $apiMethod,
                'table_formatting_lost' => (bool) preg_match('~<table\b~i', $textHtml),
            ], 'warning');
            unset($GLOBALS['rx_rich_diag_last_rejection'], $GLOBALS['rx_rich_diag_validation_reason']);
            return null;
        }
        return $result;
    }
}

if (!function_exists('rx_richUi_replaceMessage')) {
    function rx_richUi_replaceMessage(array $datas, $token, int $messageId, bool $toLegacy = false)
    {
        $parseMode = $datas['parse_mode'] ?? null;
        $text = isset($datas['text']) && is_string($datas['text']) ? $datas['text'] : '';
        if (trim($text) === '') {
            rx_richDiag_log('RICH_MESSAGE_TARGET', 'replacement', 'skipped', [
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
            $text = rx_richUi_plainText($text, $parseMode);
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
        $result = $toLegacy ? rx_richUi_sendPlainFallback('sendmessage', $send, $token) : telegram('sendmessage', $send, $token);
        $replacementCreated = is_array($result) && !empty($result['ok']);
        $deleteResult = null;
        if ($replacementCreated) {
            $deleteResult = telegram('deletemessage', ['chat_id' => $datas['chat_id'], 'message_id' => $messageId], $token);
        }
        rx_richDiag_log('RICH_MESSAGE_TARGET', 'replacement', $replacementCreated ? 'completed' : 'failed', [
            'original_operation' => 'editmessagetext',
            'requested_api_method' => 'editmessagetext',
            'selected_api_method' => 'sendmessage',
            'is_edit' => false,
            'is_new_message' => true,
            'chat_hash' => substr(hash('sha256', (string) ($datas['chat_id'] ?? '')), 0, 12),
            'target_message_id' => $messageId,
            'previous_message_id' => $messageId,
            'resulting_message_id' => is_array($result) ? (int) ($result['result']['message_id'] ?? 0) : null,
            'replacement_created' => $replacementCreated,
            'deletion_requested' => $replacementCreated,
            'deletion_succeeded' => $deleteResult === null ? null : (is_array($deleteResult) && !empty($deleteResult['ok'])),
            'rich_intercepted_edit' => true,
            'reason' => $toLegacy ? 'rich_target_converted_to_legacy' : 'legacy_target_replaced_with_rich',
            'result' => rx_richDiag_result($result),
            'delete_result' => $deleteResult === null ? null : rx_richDiag_result($deleteResult),
        ], $replacementCreated ? 'info' : 'warning');
        return $result;
    }
}

if (!function_exists('rx_richUi_markMainMenuKeyboardRemoval')) {
    function rx_richUi_markMainMenuKeyboardRemoval($chatId): void
    {
        $chatKey = trim((string) $chatId);
        if ($chatKey === '' || !ctype_digit($chatKey) || !function_exists('rx_autoRemoveReplyKeyboardOn') || !rx_autoRemoveReplyKeyboardOn() || !function_exists('select')) {
            return;
        }
        try {
            $flag = select('user', 'reply_kb_cleared', 'id', $chatKey, 'select', ['cache' => false]);
            if (!is_scalar($flag) || (string) $flag !== '1') {
                $GLOBALS['rx_pending_reply_kb_remove'][$chatKey] = true;
            }
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('rx_richUi_dispatchMedia')) {
    function rx_richUi_dispatchMedia(string $method, string $kind, array $datas, string $buttonsHtml, array $passKeys, $token)
    {
        $chatId = $datas['chat_id'];
        $file = $datas[$kind] ?? null;
        $caption = isset($datas['caption']) && is_string($datas['caption']) ? $datas['caption'] : '';
        $parseMode = $datas['parse_mode'] ?? '';
        $captionHtml = $caption === '' ? '' : rx_richUi_textToHtml($caption, $parseMode);
        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'media_dispatch', 'selected', [
            'api_method' => $method,
            'delivery_mode' => 'media_caption',
            'media_kind' => $kind,
            'media_type' => is_string($file) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $file) ? 'file_id' : (is_object($file) ? get_class($file) : gettype($file)),
            'reusable_file_id' => is_string($file) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $file) === 1,
            'parse_mode' => (string) $parseMode,
            'caption' => $caption === '' ? ['present' => false] : rx_richDiag_text($caption),
            'buttons_length' => strlen($buttonsHtml),
            'chat_hash' => substr(hash('sha256', (string) $chatId), 0, 12),
        ], 'info');
        rx_richDiag_log('RICH_MESSAGE_TARGET', 'media_target', 'selected', [
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
            rx_richDiag_log('RICH_RENDER_FAILED', 'media_dispatch', 'unsupported_parse_mode', [
                'api_method' => $method,
                'media_kind' => $kind,
                'parse_mode' => (string) $parseMode,
            ], 'warning');
            return rx_richDiag_dispatchSkip('media_caption_parse_mode_unsupported', ['api_method' => $method, 'media_kind' => $kind]);
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
            $plainCaption = $caption === '' ? '' : (string) rx_richUi_plainHtml($caption, $parseMode);
            $base = ['chat_id' => $chatId];
            foreach ($passKeys as $passKey) {
                if (isset($datas[$passKey])) {
                    $base[$passKey] = $datas[$passKey];
                }
            }
            $result = rx_richUi_attempt('sendRichMessage', $base, $tag . $captionHtml, $tag . $plainCaption, $buttonsHtml, $mediaItem, $token, rx_richUi_rtlMarker($caption));
            if (!(is_array($result) && isset($result['__fallback']))) {
                return $result;
            }
            $rxMediaCaptionMetrics = $caption === '' ? ['present' => false] : rx_richDiag_text($caption);
            $rxMediaFallbackReason = !empty($GLOBALS['rx_rich_diag_validation_reason']) ? ('table_validation_failed:' . $GLOBALS['rx_rich_diag_validation_reason']) : 'rich_media_payload_rejected';
            rx_richDiag_log('RICH_FALLBACK_TRIGGERED', 'media_dispatch', 'fallback', [
                'reason' => $rxMediaFallbackReason,
                'intended_mode' => 'rich_media_message',
                'initiator' => 'rx_richUi_dispatchMedia',
                'fallback_method' => $method,
                'initial_request_failed' => true,
                'last_error' => rx_richDiag_result($result['last'] ?? null),
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
            $plainCaption = rx_richUi_plainText($caption, $parseMode, true);
            $mediaDatas['caption'] = trim($plainCaption) !== ''
                ? htmlspecialchars($plainCaption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $caption;
            $mediaDatas['parse_mode'] = 'HTML';
        }
        $mediaDatas['_rx_already_transformed'] = true;
        $mediaResult = rxSendMediaWithCaption($method, $mediaDatas, $token, $delivery);
        $mediaFallbackText = $caption === '' ? ['present' => false] : rx_richDiag_text($caption);
        rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'media_dispatch', $delivery['delivery_status'], [
            'reason' => $GLOBALS['rx_rich_diag_fallback_reason'] ?? 'plain_media',
            'fallback_method' => $method,
            'delivery_mode' => 'plain_media',
            'result' => rx_richDiag_result($mediaResult),
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
        $controlResult = rx_richUi_plainCall('sendmessage', $control, $token);
        $controlState = rxMediaResultState($controlResult);
        rx_richDiag_log('RICH_FALLBACK_COMPLETED', 'media_controls', $controlState, [
            'delivery_mode' => 'plain_media_controls',
            'media_state' => $delivery['media_state'],
            'text_state' => $delivery['text_state'],
            'result' => rx_richDiag_result($controlResult),
        ], $controlState === 'confirmed' ? 'info' : 'warning');
        if ($controlState === 'confirmed') {
            $mediaResult['rx_control_message_id'] = (int) ($controlResult['result']['message_id'] ?? 0);
        }
        return $mediaResult;
    }
}

if (!function_exists('rx_richUi_sendBanner')) {
    function rx_richUi_sendBanner($chatId, $fileId, $text, $keyboard, $parseMode, $section = null)
    {
        if (!function_exists('rx_richActiveForSend') || !rx_richActiveForSend($chatId, $keyboard, $section)) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => 'rich_banner_inactive',
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
                'text' => rx_richDiag_text($text),
            ], 'debug');
            return null;
        }
        $fileId = trim((string) $fileId);
        if ($fileId === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $fileId)) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
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
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => 'banner_markup_without_inline_keyboard',
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
            ], 'debug');
            return null;
        }
        $buttonsHtml = rx_richUi_buttonsHtml($markup['inline_keyboard']);
        $textHtml = rx_richUi_textToHtml($text, $parseMode);
        if ($buttonsHtml === null || $buttonsHtml === '' || $textHtml === null) {
            rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'skipped', [
                'reason' => $buttonsHtml === null ? 'banner_buttons_html_failed' : ($textHtml === null ? 'banner_html_conversion_failed' : 'banner_buttons_empty'),
                'delivery_mode' => 'rich_banner',
                'section' => (string) $section,
                'text' => rx_richDiag_text($text),
            ], 'debug');
            return null;
        }
        rx_richDiag_log('RICH_SEND_METHOD_SELECTED', 'banner_gate', 'selected', [
            'api_method' => 'sendRichMessage',
            'delivery_mode' => 'rich_banner',
            'section' => (string) $section,
            'text' => rx_richDiag_text($text),
            'buttons_length' => strlen($buttonsHtml),
        ], 'info');
        $media = [['id' => 'banner', 'media' => ['type' => 'photo', 'media' => $fileId]]];
        $tag = '<figure><img src="tg://photo?id=banner"/></figure>';
        $plainHtml = (string) rx_richUi_plainHtml($text, $parseMode);
        $result = rx_richUi_attempt('sendRichMessage', ['chat_id' => $chatId], $tag . $textHtml, $tag . $plainHtml, $buttonsHtml, $media, null, rx_richUi_rtlMarker($text));
        $accepted = is_array($result) && !empty($result['ok']);
        $rxBannerResultState = is_array($result) && !isset($result['__fallback']) && function_exists('rxDeliveryResultState')
            ? rxDeliveryResultState($result)
            : ($accepted ? 'confirmed' : 'failed');
        rx_richDiag_log($rxBannerResultState === 'confirmed' ? 'RICH_DELIVERY_COMPLETED' : 'RICH_RENDER_FAILED', 'banner_delivery', $rxBannerResultState, [
            'delivery_mode' => 'rich_banner',
            'section' => (string) $section,
            'result' => rx_richDiag_result($result),
            'table_present' => (bool) preg_match('~<table\b~i', (string) $textHtml),
            'premium_present' => (bool) preg_match('~<tg-emoji\b~i', (string) $textHtml),
        ], $rxBannerResultState === 'confirmed' ? 'info' : ($rxBannerResultState === 'unknown' ? 'warning' : 'error'));
        if ($rxBannerResultState === 'unknown') {
            return $result;
        }
        return $accepted ? $result : null;
    }
}

if (!function_exists('rx_richUi_readModes')) {
    function rx_richUi_readModes(): ?array
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
}

if (!function_exists('rx_richUi_refreshSettings')) {
    function rx_richUi_refreshSettings(): void
    {
        if (function_exists('clearSelectCache')) {
            clearSelectCache('setting');
        }
        if (function_exists('faoxima_bust_bot_selectcache')) {
            faoxima_bust_bot_selectcache('setting');
        }
        if (function_exists('select')) {
            $fresh = select('setting', '*', null, null, 'select', ['cache' => false]);
            if (is_array($fresh)) {
                $GLOBALS['setting'] = $fresh;
            }
        }
    }
}

if (!function_exists('rx_richUi_toggleMode')) {
    function rx_richUi_toggleMode(string $which, string $embeddedValue): array
    {
        global $pdo;
        $modes = rx_richUi_readModes();
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
}

if (!function_exists('rx_richUi_adminToggle')) {
    function rx_richUi_adminToggle(string $which, string $embeddedValue, bool $authorized, $callbackQueryId = null): array
    {
        $outcome = ['ok' => false, 'changed' => false, 'message' => 'شما دسترسی تغییر این تنظیم را ندارید.'];
        if ($authorized) {
            try {
                $outcome = rx_richUi_toggleMode($which, $embeddedValue);
            } catch (Throwable $e) {
                error_log('[rich_ui] toggle failed: ' . $e->getMessage());
                $outcome = ['ok' => false, 'changed' => false, 'message' => 'خطا در ذخیره تنظیمات.'];
            }
            rx_richUi_refreshSettings();
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
