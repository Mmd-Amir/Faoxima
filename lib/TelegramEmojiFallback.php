<?php

final class FxTelegramEmojiFallback
{
    /** Inspect the actual rejected payload, rather than reporting every configured emoji. */
    public static function candidates(array $payload): array
    {
        $found = [];
        $add = static function ($id, string $location, string $emoji = '') use (&$found): void {
            $id = trim((string) $id);
            if ($id === '') { return; }
            if (!isset($found[$id])) {
                $found[$id] = ['custom_emoji_id' => $id, 'emoji' => $emoji, 'locations' => []];
            }
            if ($found[$id]['emoji'] === '' && $emoji !== '') { $found[$id]['emoji'] = $emoji; }
            $found[$id]['locations'][$location] = $location;
        };
        if (strtolower((string) ($payload['parse_mode'] ?? '')) === 'html') {
            foreach (['text', 'caption'] as $field) {
                if (!isset($payload[$field]) || !is_string($payload[$field])) { continue; }
                preg_match_all('~<tg-emoji\b([^>]*)>(.*?)</tg-emoji\s*>~is', $payload[$field], $tags, PREG_SET_ORDER);
                foreach ($tags as $tag) {
                    if (preg_match('~\bemoji-id\s*=\s*([\x22\x27])(.*?)\1~is', $tag[1], $id)) {
                        $add(html_entity_decode($id[2], ENT_QUOTES, 'UTF-8'), 'متن',
                            html_entity_decode(strip_tags($tag[2]), ENT_QUOTES, 'UTF-8'));
                    }
                }
            }
        }
        foreach (['entities', 'caption_entities'] as $field) {
            $entities = $payload[$field] ?? [];
            if (is_string($entities)) { $entities = json_decode($entities, true); }
            if (!is_array($entities)) { continue; }
            foreach ($entities as $entity) {
                if (is_array($entity) && ($entity['type'] ?? '') === 'custom_emoji') {
                    $add($entity['custom_emoji_id'] ?? '', 'متن');
                }
            }
        }
        $markup = $payload['reply_markup'] ?? [];
        if (is_string($markup)) { $markup = json_decode($markup, true); }
        if (is_array($markup)) {
            foreach (['inline_keyboard', 'keyboard'] as $key) {
                foreach (($markup[$key] ?? []) as $row) {
                    if (!is_array($row)) { continue; }
                    foreach ($row as $button) {
                        if (is_array($button) && isset($button['icon_custom_emoji_id'])) {
                            $add($button['icon_custom_emoji_id'], 'دکمه');
                        }
                    }
                }
            }
        }
        $rich = $payload['rich_message'] ?? null;
        if (is_string($rich)) { $rich = json_decode($rich, true); }
        if (is_array($rich) && isset($rich['html']) && is_string($rich['html'])) {
            foreach (self::candidates(['text' => $rich['html'], 'parse_mode' => 'HTML']) as $candidate) {
                $add($candidate['custom_emoji_id'], 'متن یا دکمه Rich', $candidate['emoji']);
            }
        }
        return array_values($found);
    }

    /** One bounded read-only lookup, only after an actual message rejection. */
    public static function verify(array $payload, string $token): array
    {
        $ids = array_column(self::candidates($payload), 'custom_emoji_id');
        sort($ids, SORT_STRING);
        $key = hash('sha256', 'v2|' . $token . '|' . json_encode($ids));
        $cacheFile = sys_get_temp_dir() . '/fx_emoji_verify_' . $key . '.json';
        $cached = @file_get_contents($cacheFile);
        $cached = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($cached) && ($cached['expires'] ?? 0) > time() && is_array($cached['result'] ?? null)) {
            return $cached['result'];
        }
        $result = ['status' => 'lookup_failed', 'recognized_ids' => [], 'not_found_ids' => [], 'unknown_ids' => [], 'emoji_by_id' => []];
        $queryIds = [];
        foreach ($ids as $id) {
            if (!preg_match('/^[1-9][0-9]{0,18}$/D', $id) || (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0)) {
                $result['not_found_ids'][] = $id;
            } else {
                $queryIds[] = $id;
            }
        }
        // Telegram accepts at most 200 IDs. Never delay a payment with an unbounded scan.
        $result['unknown_ids'] = array_slice($queryIds, 200);
        $queryIds = array_slice($queryIds, 0, 200);
        if ($queryIds) {
            try {
                $response = telegram('getCustomEmojiStickers', [
                    'custom_emoji_ids' => json_encode($queryIds),
                    '_rx_curl_timeout' => 3,
                    '_rx_no_network_retry' => true,
                ], $token);
                if (!empty($response['ok']) && isset($response['result']) && is_array($response['result'])) {
                    $recognized = [];
                    $wellFormed = true;
                    foreach ($response['result'] as $sticker) {
                        if (!is_array($sticker) || ($sticker['type'] ?? '') !== 'custom_emoji' || empty($sticker['custom_emoji_id'])) {
                            $wellFormed = false;
                            break;
                        }
                        $recognized[(string) $sticker['custom_emoji_id']] = true;
                        $alt = $sticker['emoji'] ?? null;
                        if (is_string($alt) && $alt !== '' && strlen($alt) <= 128 && preg_match('//u', $alt) === 1) {
                            $result['emoji_by_id'][(string) $sticker['custom_emoji_id']] = $alt;
                        }
                    }
                    foreach ($queryIds as $id) {
                        if (!$wellFormed) { $result['unknown_ids'][] = $id; }
                        elseif (isset($recognized[$id])) { $result['recognized_ids'][] = $id; }
                        else { $result['not_found_ids'][] = $id; }
                    }
                } else {
                    $result['unknown_ids'] = array_merge($result['unknown_ids'], $queryIds);
                    $result['lookup_error_code'] = $response['error_code'] ?? null;
                    $result['lookup_reason'] = (string) ($response['description'] ?? 'Invalid lookup response');
                }
            } catch (Throwable $e) {
                $result['unknown_ids'] = array_merge($result['unknown_ids'], $queryIds);
                $result['lookup_reason'] = 'Lookup exception: ' . get_class($e);
            }
        }
        $result['status'] = $result['not_found_ids'] ? 'invalid_ids_found'
            : ($result['unknown_ids'] ? 'lookup_failed' : 'ids_recognized');
        $ttl = $result['unknown_ids'] ? 30 : 300;
        @file_put_contents($cacheFile, json_encode(['expires' => time() + $ttl, 'result' => $result]), LOCK_EX);
        return $result;
    }

    /** Retry with verified emoji retained when possible; otherwise keep the plain safety net. */
    public static function recover(string $method, array $payload, array $response, string $token, ?int $timeout = null): ?array
    {
        $plain = self::prepare($method, $payload, $response);
        if ($plain === null) { return null; }
        $verification = self::verify($payload, $token);
        $send = static function (array $retry) use ($method, $token, $timeout): array {
            $retry['_rx_emoji_fallback_attempt'] = true;
            $retry['_rx_no_network_retry'] = true;
            if ($timeout !== null) { $retry['_rx_curl_timeout'] = $timeout; }
            return telegram($method, $retry, $token);
        };
        if ($verification['not_found_ids'] && $verification['recognized_ids']) {
            $partial = self::prepare($method, $payload, $response, $verification['recognized_ids']);
            if ($partial !== null) {
                $result = $send($partial);
                $verification['delivery_mode'] = 'partial_premium';
                if (!empty($result['ok']) || (int) ($result['error_code'] ?? 0) !== 400
                    || stripos((string) ($result['description'] ?? ''), 'DOCUMENT_INVALID') === false) {
                    // A timeout has an unknown delivery outcome. Never send a duplicate blindly.
                    self::logFailure($method, $payload, $response, $result, $verification);
                    return $result;
                }
                $verification['partial_retry_reason'] = (string) ($result['description'] ?? '');
            }
        }
        if ($verification['status'] === 'ids_recognized' && !isset($payload['rich_message'])) {
            $normalized = $payload;
            $mismatches = [];
            if (strtolower((string) ($payload['parse_mode'] ?? '')) === 'html') {
                foreach (['text', 'caption'] as $field) {
                    if (!isset($normalized[$field]) || !is_string($normalized[$field])) { continue; }
                    $normalized[$field] = preg_replace_callback('~<tg-emoji\b([^>]*)>(.*?)</tg-emoji\s*>~is', static function ($tag) use ($verification, &$mismatches) {
                        if (!preg_match('~\bemoji-id\s*=\s*([\x22\x27])(.*?)\1~is', $tag[1], $id)) { return $tag[0]; }
                        $cid = html_entity_decode($id[2], ENT_QUOTES, 'UTF-8');
                        $alt = $verification['emoji_by_id'][$cid] ?? null;
                        if (!is_string($alt) || $alt === '') { return $tag[0]; }
                        $sent = html_entity_decode(strip_tags($tag[2]), ENT_QUOTES, 'UTF-8');
                        if ($sent === $alt && strpos($tag[2], '<') === false) { return $tag[0]; }
                        $mismatches[] = ['custom_emoji_id' => $cid, 'sent_emoji' => $sent, 'telegram_emoji' => $alt];
                        return '<tg-emoji emoji-id="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '</tg-emoji>';
                    }, $normalized[$field]);
                }
            }
            if ($mismatches) { $verification['alternative_mismatches'] = $mismatches; }
            $entityPayload = self::asEntities($normalized);
            $probe = $entityPayload ?? ($mismatches ? $normalized : null);
            $probeMode = $entityPayload !== null ? 'premium_entities' : 'premium_normalized_alternatives';
            if ($probe !== null) {
                $result = $send($probe);
                $verification['attempts'][] = [
                    'mode' => $probeMode, 'ok' => !empty($result['ok']),
                    'error_code' => $result['error_code'] ?? null,
                    'reason' => !empty($result['ok']) ? null : (string) ($result['description'] ?? ''),
                ];
                if (!empty($result['ok']) || (int) ($result['error_code'] ?? 0) !== 400
                    || stripos((string) ($result['description'] ?? ''), 'DOCUMENT_INVALID') === false) {
                    $verification['delivery_mode'] = $probeMode;
                    if (!empty($result['ok'])) {
                        $verification['failure_scope'] = $entityPayload !== null ? 'html_text_representation_rejected' : 'text_emoji_alternative_mismatch';
                    }
                    self::logFailure($method, $payload, $response, $result, $verification);
                    return $result;
                }
                $normalized = $probe;
            }
            $textSide = $payload;
            $plainTextSide = $plain;
            unset($textSide['reply_markup'], $plainTextSide['reply_markup']);
            $hasTextEmoji = $textSide !== $plainTextSide;
            $hasButtonEmoji = ($payload['reply_markup'] ?? null) !== ($plain['reply_markup'] ?? null);
            if ($hasTextEmoji && $hasButtonEmoji) {
                // Probe the actual payment message, never a separate test message.
                // Stop as soon as one form is delivered successfully.
                $textPremium = $normalized;
                $textPremium['reply_markup'] = $plain['reply_markup'];
                $buttonsPremium = $plain;
                $buttonsPremium['reply_markup'] = $payload['reply_markup'];
                foreach ([
                    ['premium_text_plain_icons', $textPremium, 'button_icons_rejected'],
                    ['plain_text_premium_icons', $buttonsPremium, 'text_emoji_rejected'],
                ] as [$mode, $retry, $scope]) {
                    $result = $send($retry);
                    $verification['attempts'][] = [
                        'mode' => $mode, 'ok' => !empty($result['ok']),
                        'error_code' => $result['error_code'] ?? null,
                        'reason' => !empty($result['ok']) ? null : (string) ($result['description'] ?? ''),
                    ];
                    if (!empty($result['ok'])) {
                        $verification['delivery_mode'] = $mode;
                        $verification['failure_scope'] = $scope;
                        if ($scope === 'text_emoji_rejected') {
                            $result = self::restoreTextOnMessage($method, $payload, $response, $result, $token, $verification);
                        }
                        self::logFailure($method, $payload, $response, $result, $verification);
                        return $result;
                    }
                    if ((int) ($result['error_code'] ?? 0) !== 400
                        || stripos((string) ($result['description'] ?? ''), 'DOCUMENT_INVALID') === false) {
                        $verification['delivery_mode'] = $mode;
                        self::logFailure($method, $payload, $response, $result, $verification);
                        return $result;
                    }
                }
                $verification['failure_scope'] = 'text_and_icons_rejected_check_permissions_or_format';
            }
        }
        $result = $send($plain);
        $verification['delivery_mode'] = 'plain';
        self::logFailure($method, $payload, $response, $result, $verification);
        return $result;
    }

    /** Restore working emoji in-place. Rejected edits leave the delivered invoice untouched. */
    private static function restoreTextOnMessage(string $method, array $payload, array $rejection, array $delivered, string $token, array &$verification): array
    {
        $messageId = (int) ($delivered['result']['message_id'] ?? 0);
        if (strtolower($method) !== 'sendmessage' || $messageId <= 0 || empty($payload['chat_id'])) { return $delivered; }
        $ids = [];
        foreach (self::candidates($payload) as $candidate) {
            if (isset($candidate['locations']['متن'])) { $ids[] = $candidate['custom_emoji_id']; }
        }
        if (!$ids) { return $delivered; }
        $accepted = []; $rejected = []; $pending = [$ids]; $attempts = []; $failedSets = [];
        $deadline = microtime(true) + 8;
        while ($pending && count($attempts) < 32 && microtime(true) < $deadline) {
            $group = array_shift($pending);
            $testIds = array_values(array_unique(array_merge($accepted, $group)));
            $keyIds = $testIds;
            sort($keyIds, SORT_STRING);
            $key = json_encode($keyIds);
            if (!isset($failedSets[$key])) {
                $candidate = self::prepare('sendmessage', $payload, $rejection, $testIds);
                if ($candidate === null) { $candidate = $payload; }
                $candidate['reply_markup'] = $payload['reply_markup'] ?? null;
                if (isset($candidate['text']) && strtolower((string) ($candidate['parse_mode'] ?? '')) === 'html') {
                    $candidate['text'] = preg_replace_callback('~<tg-emoji\b([^>]*)>(.*?)</tg-emoji\s*>~is', static function ($tag) use ($verification) {
                        if (!preg_match('~\bemoji-id\s*=\s*([\x22\x27])(.*?)\1~is', $tag[1], $id)) { return $tag[0]; }
                        $cid = html_entity_decode($id[2], ENT_QUOTES, 'UTF-8');
                        $alt = $verification['emoji_by_id'][$cid] ?? null;
                        return is_string($alt) && $alt !== ''
                            ? '<tg-emoji emoji-id="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '</tg-emoji>'
                            : $tag[0];
                    }, $candidate['text']);
                }
                $candidate = self::asEntities($candidate);
                if ($candidate === null) { array_unshift($pending, $group); break; }
                $edit = [
                    'chat_id' => $payload['chat_id'], 'message_id' => $messageId,
                    'text' => $candidate['text'], 'entities' => $candidate['entities'],
                    'reply_markup' => $payload['reply_markup'] ?? null,
                    '_rx_emoji_fallback_attempt' => true, '_rx_no_network_retry' => true,
                    '_rx_curl_timeout' => 2,
                ];
                foreach (['business_connection_id', 'link_preview_options', 'disable_web_page_preview'] as $field) {
                    if (isset($payload[$field])) { $edit[$field] = $payload[$field]; }
                }
                try { $result = telegram('editMessageText', $edit, $token); }
                catch (Throwable $e) { array_unshift($pending, $group); break; }
                $attempts[] = [
                    'text_ids' => $testIds, 'ok' => !empty($result['ok']),
                    'error_code' => $result['error_code'] ?? null,
                    'reason' => !empty($result['ok']) ? null : (string) ($result['description'] ?? ''),
                ];
                if (!empty($result['ok'])) {
                    $accepted = $testIds;
                    if (isset($result['result']['message_id'])) { $delivered = $result; }
                    continue;
                }
                if ((int) ($result['error_code'] ?? 0) !== 400
                    || stripos((string) ($result['description'] ?? ''), 'DOCUMENT_INVALID') === false) {
                    // Timeout or another error: invoice already exists; do not send another one.
                    array_unshift($pending, $group);
                    break;
                }
                $failedSets[$key] = true;
            }
            if (count($group) === 1) { $rejected[] = $group[0]; }
            else {
                $middle = (int) ceil(count($group) / 2);
                array_unshift($pending, array_slice($group, 0, $middle), array_slice($group, $middle));
            }
        }
        $unchecked = [];
        foreach ($pending as $group) { $unchecked = array_merge($unchecked, $group); }
        $verification['text_restoration'] = [
            'restored_ids' => $accepted, 'rejected_in_text_ids' => $rejected,
            'unchecked_ids' => array_values(array_unique($unchecked)), 'attempts' => $attempts,
        ];
        if ($accepted) {
            $verification['delivery_mode'] = count($accepted) === count($ids) ? 'premium_restored_by_edit' : 'partial_premium_text_restored';
            $verification['failure_scope'] = $rejected ? 'specific_text_emoji_rejected' : 'text_premium_restored_in_place';
        } elseif (count($rejected) === count($ids)) {
            $verification['failure_scope'] = 'all_single_text_emoji_rejected';
        }
        return $delivered;
    }

    /** Preserve Telegram HTML formatting as explicit UTF-16 message entities. */
    public static function asEntities(array $payload): ?array
    {
        if (strtolower((string) ($payload['parse_mode'] ?? '')) !== 'html'
            || !class_exists('DOMDocument') || !empty($payload['entities']) || !empty($payload['caption_entities'])) {
            return null;
        }
        $field = isset($payload['text']) ? 'text' : 'caption';
        if (!isset($payload[$field]) || !is_string($payload[$field])) { return null; }
        $html = str_replace(["\r\n", "\r"], "\n", $payload[$field]);
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (!@$dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="fx-emoji-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) { return null; }
        $root = $dom->getElementById('fx-emoji-root');
        if (!$root) { return null; }
        $text = ''; $offset = 0; $entities = []; $valid = true;
        $walk = static function ($node, bool $inPre = false) use (&$walk, &$text, &$offset, &$entities, &$valid): void {
            if ($node->nodeType === XML_TEXT_NODE) {
                $value = $node->nodeValue;
                $text .= $value;
                $offset += preg_match_all('/./us', $value) + preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value);
                return;
            }
            if ($node->nodeType === XML_COMMENT_NODE) { return; }
            if ($node->nodeType !== XML_ELEMENT_NODE) { $valid = false; return; }
            $name = strtolower($node->nodeName);
            $types = ['b' => 'bold', 'strong' => 'bold', 'i' => 'italic', 'em' => 'italic',
                'u' => 'underline', 'ins' => 'underline', 's' => 'strikethrough', 'strike' => 'strikethrough',
                'del' => 'strikethrough', 'tg-spoiler' => 'spoiler', 'code' => 'code', 'pre' => 'pre'];
            $entity = null;
            if (isset($types[$name])) {
                if (!($name === 'code' && $inPre)) { $entity = ['type' => $types[$name]]; }
                if ($name === 'pre') {
                    $code = $node->getElementsByTagName('code')->item(0);
                    if ($code && preg_match('/\blanguage-([\w+-]+)/', $code->getAttribute('class'), $language)) {
                        $entity['language'] = $language[1];
                    }
                }
            } elseif ($name === 'span' && $node->getAttribute('class') === 'tg-spoiler') {
                $entity = ['type' => 'spoiler'];
            } elseif ($name === 'blockquote') {
                $entity = ['type' => $node->hasAttribute('expandable') ? 'expandable_blockquote' : 'blockquote'];
            } elseif ($name === 'a' && $node->hasAttribute('href')) {
                $entity = ['type' => 'text_link', 'url' => $node->getAttribute('href')];
            } elseif ($name === 'tg-emoji' && ctype_digit($node->getAttribute('emoji-id'))) {
                $entity = ['type' => 'custom_emoji', 'custom_emoji_id' => $node->getAttribute('emoji-id')];
            } else { $valid = false; }
            $start = $offset;
            foreach ($node->childNodes as $child) { $walk($child, $inPre || $name === 'pre'); }
            if ($entity !== null && $offset > $start) {
                $entity['offset'] = $start;
                $entity['length'] = $offset - $start;
                $entities[] = $entity;
            }
        };
        foreach ($root->childNodes as $node) { $walk($node); }
        // Refuse conversion if HTML repair changed visible text or the template is unsupported.
        $expected = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$valid || $text !== $expected || !array_filter($entities, static function ($entity) {
            return $entity['type'] === 'custom_emoji';
        })) { return null; }
        usort($entities, static function ($a, $b) {
            return $a['offset'] <=> $b['offset'] ?: $b['length'] <=> $a['length'];
        });
        $payload[$field] = $text;
        $payload[$field === 'text' ? 'entities' : 'caption_entities'] = $entities;
        unset($payload['parse_mode']);
        return $payload;
    }

    private static function cleanHtml(string $html, array $keep, bool $rich = false): string
    {
        if (!$keep) {
            if ($rich) { $html = preg_replace('~<tg-emoji\b[^>]*>\s*</tg-emoji\s*>~i', '•', $html); }
            return preg_replace('~</?tg-emoji\b[^>]*>~i', '', $html);
        }
        return preg_replace_callback('~<tg-emoji\b([^>]*)>(.*?)</tg-emoji\s*>~is', static function ($tag) use ($keep, $rich) {
            if (preg_match('~\bemoji-id\s*=\s*([\x22\x27])(.*?)\1~is', $tag[1], $id)
                && isset($keep[html_entity_decode($id[2], ENT_QUOTES, 'UTF-8')])) {
                return $tag[0];
            }
            return $rich && trim($tag[2]) === '' ? '•' : $tag[2];
        }, $html);
    }

    /** Record the rejection and retry outcome locally; never send diagnostic messages. */
    public static function logFailure(string $method, array $payload, array $response, array $retryResult, array $verification = []): void
    {
        try {
            $entry = [
                'method' => $method,
                'chat_id' => $payload['chat_id'] ?? null,
                'error_code' => $response['error_code'] ?? null,
                'reason' => (string) ($response['description'] ?? ''),
                'retry_ok' => !empty($retryResult['ok']),
                'retry_error_code' => $retryResult['error_code'] ?? null,
                'retry_reason' => !empty($retryResult['ok']) ? null : (string) ($retryResult['description'] ?? ''),
                // These IDs appeared in the rejected message; they are not proven invalid.
                'suspects' => self::candidates($payload),
            ];
            if ($verification) {
                $entry['verification'] = $verification;
                $entry['source_hint'] = $verification['not_found_ids'] ? 'unrecognized_custom_emoji_id'
                    : ($verification['unknown_ids'] ? 'lookup_failed_no_conclusion' : 'ids_recognized_check_format_or_permissions');
                if (isset($verification['failure_scope'])) { $entry['source_hint'] = $verification['failure_scope']; }
                global $pdo;
                if ($verification['not_found_ids'] && $pdo instanceof PDO) {
                    try {
                        $ids = array_slice($verification['not_found_ids'], 0, 200);
                        $stmt = $pdo->prepare('SELECT id, emoji, custom_emoji_id FROM premium_emojis WHERE custom_emoji_id IN ('
                            . implode(',', array_fill(0, count($ids), '?')) . ')');
                        $stmt->execute($ids);
                        $entry['invalid_database_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $entry['database_lookup_failed'] = true;
                    }
                }
            }
            $line = '[' . date('d-M-Y H:i:s e') . '] [telegram_emoji_fallback] '
                . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
            $dir = defined('REFACTORED_LOG_DIR') ? REFACTORED_LOG_DIR : dirname(__DIR__) . '/logs';
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log(rtrim($line));
                return;
            }
            if (@file_put_contents($dir . '/telegram-emoji.log', $line, FILE_APPEND | LOCK_EX) === false) {
                error_log(rtrim($line));
            }
        } catch (Throwable $e) {
            error_log('[telegram_emoji_fallback] log write failed class=' . get_class($e));
        }
    }

    /** Return a retry payload only for an explicit rejection containing custom emoji. */
    public static function prepare(string $method, array $payload, array $response, array $keepIds = []): ?array
    {
        $methods = ['sendmessage', 'editmessagetext', 'sendphoto', 'sendvideo',
            'senddocument', 'sendanimation', 'sendaudio', 'sendvoice',
            'editmessagecaption', 'editmessagereplymarkup', 'sendrichmessage'];
        if (!in_array(strtolower($method), $methods, true)
            || ($response['ok'] ?? null) !== false
            || (int) ($response['error_code'] ?? 0) !== 400
            || !preg_match('/\bDOCUMENT_INVALID\b/i', (string) ($response['description'] ?? ''))) {
            return null;
        }

        $clean = $payload;
        $keep = array_fill_keys($keepIds, true);
        if (isset($clean['rich_message'])) {
            $wasString = is_string($clean['rich_message']);
            $rich = $wasString ? json_decode($clean['rich_message'], true) : $clean['rich_message'];
            if (is_array($rich) && isset($rich['html']) && is_string($rich['html'])) {
                $originalHtml = $rich['html'];
                // Rich button icons have no alternative text; keep icon-only controls usable.
                $rich['html'] = self::cleanHtml($rich['html'], $keep, true);
                if ($rich['html'] !== $originalHtml) {
                    $clean['rich_message'] = $wasString ? json_encode($rich, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $rich;
                }
            }
        }
        foreach (['text', 'caption'] as $field) {
            if (isset($clean[$field]) && is_string($clean[$field])
                && strtolower((string) ($clean['parse_mode'] ?? '')) === 'html') {
                // Keep alternative emoji text, HTML formatting and financial values intact.
                $clean[$field] = self::cleanHtml($clean[$field], $keep);
            }
        }
        foreach (['entities', 'caption_entities'] as $field) {
            if (!isset($clean[$field])) {
                continue;
            }
            $wasString = is_string($clean[$field]);
            $entities = $wasString ? json_decode($clean[$field], true) : $clean[$field];
            if (is_array($entities)) {
                $filtered = array_values(array_filter($entities, static function ($entity) use ($keep) {
                    return !is_array($entity) || ($entity['type'] ?? '') !== 'custom_emoji'
                        || isset($keep[(string) ($entity['custom_emoji_id'] ?? '')]);
                }));
                if ($filtered !== $entities) {
                    $clean[$field] = $wasString ? json_encode($filtered, JSON_UNESCAPED_UNICODE) : $filtered;
                }
            }
        }
        if (isset($clean['reply_markup'])) {
            $wasString = is_string($clean['reply_markup']);
            $markup = $wasString ? json_decode($clean['reply_markup'], true) : $clean['reply_markup'];
            $originalMarkup = $markup;
            if (is_array($markup)) {
                foreach (['inline_keyboard', 'keyboard'] as $key) {
                    if (!isset($markup[$key]) || !is_array($markup[$key])) {
                        continue;
                    }
                    foreach ($markup[$key] as &$row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        foreach ($row as &$button) {
                            if (is_array($button) && isset($button['icon_custom_emoji_id']) && !isset($keep[(string) $button['icon_custom_emoji_id']])) {
                                unset($button['icon_custom_emoji_id']);
                                if (trim((string) ($button['text'] ?? '')) === '') {
                                    $button['text'] = '•';
                                }
                            }
                        }
                        unset($button);
                    }
                    unset($row);
                }
                if ($markup !== $originalMarkup) {
                    $clean['reply_markup'] = $wasString ? json_encode($markup, JSON_UNESCAPED_UNICODE) : $markup;
                }
            }
        }
        return $clean !== $payload ? $clean : null;
    }
}
