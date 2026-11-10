<?php

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
        return preg_replace('~<tg-emoji\s+emoji-id="(\d+)"\s*>[\s\S]*?</tg-emoji>~u', '<tg-emoji emoji-id="$1"></tg-emoji>', $out);
    }
}

if (!function_exists('rx_richUi_tableToText')) {
    function rx_richUi_tableToText(string $tableHtml): string
    {
        $caption = '';
        if (preg_match('~<caption\b[^>]*>([\s\S]*?)</caption>~i', $tableHtml, $cap)) {
            $caption = trim(html_entity_decode(strip_tags($cap[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        $lines = [];
        if ($caption !== '') {
            $lines[] = $caption;
        }
        if (preg_match_all('~<tr\b[^>]*>([\s\S]*?)</tr>~i', $tableHtml, $rows)) {
            foreach ($rows[1] as $rowHtml) {
                $cells = [];
                $allHeader = true;
                if (preg_match_all('~<(t[hd])\b[^>]*>([\s\S]*?)</\1>~i', $rowHtml, $cm, PREG_SET_ORDER)) {
                    foreach ($cm as $cell) {
                        if (strtolower($cell[1]) !== 'th') {
                            $allHeader = false;
                        }
                        $value = str_replace(['<br>', '<br/>', '<br />'], ' ', $cell[2]);
                        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
                        if ($value !== '') {
                            $cells[] = $value;
                        }
                    }
                }
                if (!$cells) {
                    continue;
                }
                $lines[] = (count($cells) === 2 && !$allHeader) ? $cells[0] . ': ' . $cells[1] : implode(' | ', $cells);
            }
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('rx_richUi_plainFromHtml')) {
    function rx_richUi_plainFromHtml(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace_callback('~<table\b[\s\S]*?</table>~i', static function ($m) {
            return "\n" . rx_richUi_tableToText($m[0]) . "\n";
        }, $html);
        $html = preg_replace('~<summary\b[^>]*>([\s\S]*?)</summary>~i', '$1' . "\n", $html);
        $html = preg_replace('~<li\b[^>]*>~i', "\n• ", $html);
        $html = preg_replace('~</li>~i', '', $html);
        $html = preg_replace('~<br\s*/?>~i', "\n", $html);
        $html = preg_replace('~<hr\s*/?>~i', "\n──────────\n", $html);
        $html = preg_replace('~</(p|h[1-6]|blockquote|aside|div|footer|figure|figcaption|ul|ol|pre|details|tr)>~i', "\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace('/[ \t]+/u', ' ', $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim((string) $html);
    }
}

if (!function_exists('rx_richUi_plainText')) {
    function rx_richUi_plainText($text, $parseMode): string
    {
        $mode = strtolower(trim((string) $parseMode));
        $text = rx_richUi_stripRtlMarker($text);
        if ($mode === 'html') {
            return rx_richUi_plainFromHtml($text);
        }
        if (($mode === '' || $mode === 'none') && rx_richUi_htmlLike($text)) {
            return rx_richUi_plainFromHtml($text);
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
        return $message;
    }
}

if (!function_exists('rx_richUi_isFallbackError')) {
    function rx_richUi_isFallbackError($result): bool
    {
        if (!is_array($result)) {
            return false;
        }
        $code = (int) ($result['error_code'] ?? 0);
        $desc = strtolower((string) ($result['description'] ?? ''));
        if ($code === 0 || $code === 429 || $code >= 500) {
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
    function rx_richUi_attempt(string $apiMethod, array $base, string $textHtml, string $plainHtml, string $buttonsHtml, array $media, $token, $rtlOverride = null)
    {
        $variants = [['full', $textHtml]];
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
            $payload['rich_message'] = json_encode(rx_richUi_buildMessage($html, $buttonsHtml, $media, $rtlOverride), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = telegram($apiMethod, $payload, $token);
            if (is_array($result) && !empty($result['ok'])) {
                if ($withRemove) {
                    unset($GLOBALS['rx_pending_reply_kb_remove'][$chatKey]);
                    if (function_exists('update')) {
                        update('user', 'reply_kb_cleared', '1', 'id', $chatKey);
                    }
                }
                return $result;
            }
            $last = $result;
            if ($label === 'full' && preg_match('~<table\b~i', $textHtml)) {
                $description = is_array($result) ? (string) ($result['description'] ?? 'unknown response') : 'invalid response';
                $log = '[rich-template] ' . $apiMethod . ' rejected table: ' . $description;
                if (function_exists('faoxima_dedup_error_log')) {
                    faoxima_dedup_error_log('rich_template_' . hash('sha256', $log), $log, 300);
                } else {
                    error_log($log);
                }
            }
            if (!rx_richUi_isFallbackError($result)) {
                return $result;
            }
        }
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
        if ($text === '') {
            return null;
        }
        $plain = rx_richUi_plainText($text, $datas['parse_mode'] ?? '');
        if (trim($plain) === '') {
            return null;
        }
        $payload = $datas;
        $payload['text'] = $plain;
        $payload['_rx_already_transformed'] = true;
        unset($payload['parse_mode'], $payload['entities'], $payload['caption_entities'], $payload['rich_message']);
        $chunks = rx_richUi_plainChunks($plain);
        if (count($chunks) === 1) {
            return rx_richUi_plainCall($method, $payload, $token);
        }
        if (!empty($payload['inline_message_id'])) {
            return ['ok' => false, 'error_code' => 400, 'description' => 'Long plain fallback cannot be split for an inline message'];
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
                return $result;
            }
        }
        return $result;
    }
}

if (!function_exists('rx_richUi_plainChunks')) {
    function rx_richUi_plainChunks(string $text): array
    {
        if (preg_match_all('/\X/u', $text, $matches) === false) {
            return [$text];
        }
        $chars = [];
        foreach ($matches[0] as $grapheme) {
            if (strlen(mb_convert_encoding($grapheme, 'UTF-16LE', 'UTF-8')) > 8192) {
                foreach (preg_split('//u', $grapheme, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                    $chars[] = $char;
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
        $payload = [
            'chat_id' => $datas['chat_id'],
            'message_id' => $datas['message_id'],
            'rich_message' => json_encode(['blocks' => $blocks, 'is_rtl' => !empty($rich['is_rtl'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        if ($caption === '') {
            return null;
        }
        $plain = rx_richUi_plainText($caption, $datas['parse_mode'] ?? '');
        if (trim($plain) === '') {
            return null;
        }
        $payload = $datas;
        $payload['caption'] = $plain;
        $payload['_rx_already_transformed'] = true;
        unset($payload['parse_mode'], $payload['caption_entities']);
        return rx_richUi_plainCall($method, $payload, $token);
    }
}

if (!function_exists('rx_richUi_dispatch')) {
    function rx_richUi_dispatch($method, array $datas, $token)
    {
        $m = strtolower((string) $method);
        $mediaKeys = ['sendphoto' => 'photo', 'sendvideo' => 'video', 'senddocument' => 'document', 'sendanimation' => 'animation'];
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
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            return null;
        }
        $explicitRich = $rtlOverride !== null || preg_match('~<table\b~i', $rawText . $rawCaption) === 1;
        $embedButtons = function_exists('rx_richActiveForSend') && rx_richActiveForSend($chatId, $datas['reply_markup'] ?? null);
        $destRich = $explicitRich || $embedButtons;
        if ($m === 'editmessagetext') {
            $editId = (int) ($datas['message_id'] ?? 0);
            $cbMessage = $GLOBALS['update']['callback_query']['message'] ?? null;
            $isCbTarget = $editId > 0 && is_array($cbMessage) && (int) ($cbMessage['message_id'] ?? 0) === $editId && !isset($datas['inline_message_id']);
            $targetRich = $isCbTarget && isset($cbMessage['rich_message']);
            $targetLegacyText = $isCbTarget && !$targetRich && isset($cbMessage['text']);
            if (($destRich && $targetLegacyText) || (!$destRich && $targetRich)) {
                return rx_richUi_replaceMessage($datas, $token, $editId, !$destRich);
            }
        }
        if (!$destRich) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if ($needsIntercept && isset($mediaKeys[$m])) {
                $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
            if (!($m === 'editmessagereplymarkup' && rx_richUi_targetIsRichMessage((int) ($datas['message_id'] ?? 0)))) {
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
                    $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                }
                return null;
            }
            if ($embedButtons) $buttonsHtml = rx_richUi_buttonsHtml($markup['inline_keyboard']);
            if ($buttonsHtml === null) {
                if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                    $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                } elseif ($needsIntercept && isset($mediaKeys[$m])) {
                    $fallback = rx_richUi_sendPlainMediaFallback($method, $datas, $token);
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
            return rx_richUi_dispatchMedia($m, $mediaKeys[$m], $datas, $buttonsHtml, $passKeys, $token);
        }

        if ($m === 'sendmessage') {
            if (!isset($datas['text']) || !is_string($datas['text'])) {
                return null;
            }
            if ($buttonsHtml === '' && !$needsIntercept && !$explicitRich) {
                return null;
            }
            $textHtml = rx_richUi_textToHtml($datas['text'], $parseMode);
            $plainHtml = (string) rx_richUi_plainHtml($datas['text'], $parseMode);
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
            if (rx_ui_section_for_send($chatId, $datas['reply_markup'] ?? null) === 'main_menu') {
                rx_richUi_markMainMenuKeyboardRemoval($chatId);
            }
        } else {
            $messageId = (int) ($datas['message_id'] ?? 0);
            if ($messageId <= 0 || isset($datas['inline_message_id'])) {
                return null;
            }
            $targetIsRich = rx_richUi_targetIsRichMessage($messageId);
            if ($m === 'editmessagereplymarkup') {
                if (!$targetIsRich) {
                    return null;
                }
                $cbRich = $GLOBALS['update']['callback_query']['message']['rich_message'] ?? null;
                return rx_richUi_editRichButtons($datas, is_array($cbRich) ? $cbRich : [], $markup['inline_keyboard'] ?? [], $token);
            } else {
                if ($buttonsHtml === '' && !$targetIsRich && !$needsIntercept) {
                    return null;
                }
                if (!isset($datas['text']) || !is_string($datas['text'])) {
                    return null;
                }
                $textHtml = rx_richUi_textToHtml($datas['text'], $parseMode);
                $plainHtml = (string) rx_richUi_plainHtml($datas['text'], $parseMode);
            }
            if ($textHtml === null || $textHtml === '') {
                return null;
            }
            $base = ['chat_id' => $chatId, 'message_id' => $messageId];
            if ($nativeMarkup !== null) {
                if (!isset($markup['inline_keyboard'])) return rx_richUi_replaceMessage($datas, $token, $messageId);
                $base['reply_markup'] = $nativeMarkup;
            }
            $apiMethod = 'editMessageText';
        }

        $result = rx_richUi_attempt($apiMethod, $base, $textHtml, $plainHtml, $buttonsHtml, $media, $token, $rtlOverride);
        if (is_array($result) && isset($result['__fallback'])) {
            if ($needsIntercept && ($m === 'sendmessage' || $m === 'editmessagetext')) {
                $fallback = rx_richUi_sendPlainFallback($m, $datas, $token);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
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
        $fromRich = false;
        if (trim($text) === '') {
            $callbackMessage = $GLOBALS['update']['callback_query']['message'] ?? [];
            $text = rx_richUi_extractText($callbackMessage['rich_message'] ?? ($callbackMessage['text'] ?? ''));
            $parseMode = 'HTML';
            $fromRich = true;
        }
        if (trim($text) === '') {
            return null;
        }
        if ($toLegacy) {
            $text = rx_richUi_plainText($text, $fromRich ? 'HTML' : $parseMode);
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
        if (is_array($result) && !empty($result['ok'])) {
            telegram('deletemessage', ['chat_id' => $datas['chat_id'], 'message_id' => $messageId], $token);
        }
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
        }

        $mediaDatas = $datas;
        unset($mediaDatas['reply_markup']);
        if ($buttonsHtml === '' && isset($datas['reply_markup'])) {
            $mediaDatas['reply_markup'] = $datas['reply_markup'];
        }
        if (isset($mediaDatas['caption'])) {
            $plainCaption = rx_richUi_plainText($caption, $parseMode);
            if (trim($plainCaption) !== '') {
                $mediaDatas['caption'] = $plainCaption;
            } else {
                unset($mediaDatas['caption']);
            }
            unset($mediaDatas['parse_mode']);
        }
        $mediaDatas['_rx_already_transformed'] = true;
        $mediaResult = rx_richUi_plainCall($method, $mediaDatas, $token);
        if (!is_array($mediaResult) || empty($mediaResult['ok'])) {
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
        if (is_array($controlResult) && !empty($controlResult['ok'])) {
            $mediaResult['rx_control_message_id'] = (int) ($controlResult['result']['message_id'] ?? 0);
        }
        return $mediaResult;
    }
}

if (!function_exists('rx_richUi_sendBanner')) {
    function rx_richUi_sendBanner($chatId, $fileId, $text, $keyboard, $parseMode, $section = null)
    {
        if (!function_exists('rx_richActiveForSend') || !rx_richActiveForSend($chatId, $keyboard, $section)) {
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
        $buttonsHtml = rx_richUi_buttonsHtml($markup['inline_keyboard']);
        $textHtml = rx_richUi_textToHtml($text, $parseMode);
        if ($buttonsHtml === null || $buttonsHtml === '' || $textHtml === null) {
            return null;
        }
        $media = [['id' => 'banner', 'media' => ['type' => 'photo', 'media' => $fileId]]];
        $tag = '<figure><img src="tg://photo?id=banner"/></figure>';
        $plainHtml = (string) rx_richUi_plainHtml($text, $parseMode);
        $result = rx_richUi_attempt('sendRichMessage', ['chat_id' => $chatId], $tag . $textHtml, $tag . $plainHtml, $buttonsHtml, $media, null, rx_richUi_rtlMarker($text));
        return is_array($result) && !empty($result['ok']) ? $result : null;
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
