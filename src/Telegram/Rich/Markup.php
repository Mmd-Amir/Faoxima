<?php

namespace Faoxima\Telegram\Rich;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class Markup
{
    public static function rx_richUi_extractText($node, string $glue = ''): string
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
        $skip = [
            'type' => 1, 'url' => 1, 'callback_data' => 1, 'style' => 1, 'align' => 1, 'valign' => 1, 'name' => 1, 'id' => 1, 'is_rtl' => 1, 'language' => 1,
            'custom_emoji_id' => 1, 'date_time_format' => 1, 'unix_time' => 1, 'file_id' => 1, 'file_unique_id' => 1, 'mime_type' => 1, 'size' => 1,
            'is_header' => 1, 'colspan' => 1, 'rowspan' => 1, 'is_bordered' => 1, 'is_striped' => 1, 'is_compact' => 1,
        ];
        $isList = $node === [] || array_keys($node) === range(0, count($node) - 1);
        if ($type === 'table' && isset($node['cells']) && is_array($node['cells'])) {
            $lines = [];
            if (isset($node['caption'])) {
                $caption = trim(self::rx_richUi_extractText($node['caption']));
                if ($caption !== '') {
                    $lines[] = $caption;
                }
            }
            foreach ($node['cells'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cells = [];
                foreach ($row as $cell) {
                    $cellText = trim((string) preg_replace('/[\x{200E}\x{200F}\x{2066}-\x{2069}]/u', '', self::rx_richUi_extractText(is_array($cell) && array_key_exists('text', $cell) ? $cell['text'] : $cell)));
                    $cellText = trim((string) preg_replace('/\s+/u', ' ', $cellText));
                    if ($cellText !== '') {
                        $cells[] = $cellText;
                    }
                }
                if ($cells) {
                    $lines[] = implode(' ', $cells);
                }
            }
            return implode("
", $lines);
        }
        $parts = [];
        foreach ($node as $key => $value) {
            if (!$isList && isset($skip[$key])) {
                continue;
            }
            $piece = self::rx_richUi_extractText($value, $key === 'blocks' ? "
" : '');
            if ($piece !== '') {
                $parts[] = $piece;
            }
        }
        return trim(implode($isList ? $glue : "
", $parts));
    }

    public static function rx_richUi_blockTags(): array
    {
        return ['p', 'table', 'ul', 'ol', 'pre', 'blockquote', 'details', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'aside', 'footer', 'figure'];
    }

    public static function rx_richUi_htmlLike($text): bool
    {
        return (bool) preg_match('~</?(?:p|b|strong|i|em|u|ins|s|strike|del|code|pre|mark|sub|sup|tg-spoiler|tg-emoji|a|tg-time|tg-math|h[1-6]|br|hr|footer|ul|ol|li|blockquote|aside|details|summary|table|caption|tr|th|td|figure|figcaption|cite|tg-button-row|tg-button)\b~i', (string) $text);
    }

    public static function rx_richUi_requiresRich($text, $parseMode = null): bool
    {
        return (bool) preg_match('~</?(?:p|br|table|ul|ol|details|h[1-6]|hr|aside|footer|figure|mark|sub|sup|tg-time|tg-math|tg-reference|summary|caption|tr|th|td)\b~i', (string) $text);
    }

    public static function rx_richUi_splitBlocks(string $html): array
    {
        $blocks = self::rx_richUi_blockTags();
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

    public static function rx_richUi_inlineToParagraphs(string $chunk): string
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

    public static function rx_richUi_textToHtml($text, $parseMode): ?string
    {
        $text = self::rx_richUi_stripRtlMarker($text);
        $mode = strtolower(trim((string) $parseMode));
        if ($mode === 'html') {
            $html = (string) $text;
        } elseif ($mode === '' || $mode === 'none') {
            $html = self::rx_richUi_htmlLike($text)
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
        foreach (self::rx_richUi_splitBlocks($html) as $segment) {
            if ($segment[0]) {
                if (trim($inline) !== '') {
                    $out .= self::rx_richUi_inlineToParagraphs($inline);
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
            $out .= self::rx_richUi_inlineToParagraphs($inline);
        }
        $normalized = preg_replace('~<tg-emoji\s+emoji-id="(\d+)"\s*>[\s\S]*?</tg-emoji>~u', '<tg-emoji emoji-id="$1"></tg-emoji>', $out);
        return $normalized;
    }

    public static function rx_richUi_fallbackDom(string $html): ?DOMElement
    {
        if (!class_exists('DOMDocument')) {
            return null;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return $loaded ? $doc->getElementsByTagName('body')->item(0) : null;
    }

    public static function rx_richUi_fallbackLinkText(string $label, string $href): string
    {
        $href = trim($href);
        if (!preg_match('~^(?:https?|tg|tg-proxy|proxy|vmess|vless|trojan|ss|ssr|hysteria2?|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|xuifile|xuiawgfile|openvpn|socks5?)://[^\s\p{Cc}\p{Cf}<>]+$~iuD', $href)) {
            return $label;
        }
        if ($label === '' || preg_match('~(?:^|[\s(\[])' . preg_quote($href, '~') . '(?=$|[\s)\]])~u', $label) === 1) {
            return $label === '' ? $href : $label;
        }
        return $label . ' (' . $href . ')';
    }

    public static function rx_richUi_tableNodeText(DOMNode $node): string
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
            $part = self::rx_richUi_tableNodeText($child);
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
            return self::rx_richUi_fallbackLinkText(trim($text), $node->getAttribute('href'));
        }
        if ($node->tagName === 'tg-emoji' && trim($text) === '') {
            return '⭐';
        }
        if ($node->tagName === 'caption') {
            return trim($text) . "\n";
        }
        return $node->tagName === 'table' ? "\n" . trim($text) . "\n" : $text;
    }

    public static function rx_richUi_tableToText(string $tableHtml): string
    {
        $body = self::rx_richUi_fallbackDom($tableHtml);
        $table = $body ? $body->getElementsByTagName('table')->item(0) : null;
        return $table ? trim(self::rx_richUi_tableNodeText($table)) : '';
    }

    public static function rx_richUi_plainFromHtml(string $html, bool $preserveSpacing = false): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = self::rx_richUi_replaceTablesWithText($html);
        if (preg_match('~<(?:a|script|style)\b~i', $html) && ($body = self::rx_richUi_fallbackDom($html))) {
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
                $text = self::rx_richUi_fallbackLinkText($label, $anchor->getAttribute('href'));
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

    public static function rx_richUi_replaceTablesWithText(string $html): string
    {
        if (!preg_match('~<table\b~i', $html)) {
            return $html;
        }
        $body = self::rx_richUi_fallbackDom($html);
        if (!$body || $body->getElementsByTagName('table')->length === 0) {
            return $html;
        }
        $doc = $body->ownerDocument;
        $tables = $body->getElementsByTagName('table');
        while ($tables->length > 0) {
            $table = $tables->item(0);
            $paragraph = $doc->createElement('p');
            foreach (explode("\n", trim(self::rx_richUi_tableNodeText($table))) as $index => $line) {
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

    public static function rx_richUi_plainText($text, $parseMode, bool $preserveSpacing = false): string
    {
        $mode = strtolower(trim((string) $parseMode));
        $text = self::rx_richUi_stripRtlMarker($text);
        if ($mode === 'html') {
            return self::rx_richUi_plainFromHtml($text, $preserveSpacing);
        }
        if (($mode === '' || $mode === 'none') && self::rx_richUi_htmlLike($text)) {
            return self::rx_richUi_plainFromHtml($text, $preserveSpacing);
        }
        return $text;
    }

    public static function rx_richUi_plainHtml($text, $parseMode): ?string
    {
        $mode = strtolower(trim((string) $parseMode));
        if ($mode !== 'html' && $mode !== '' && $mode !== 'none') {
            return null;
        }
        $plain = self::rx_richUi_plainText($text, $parseMode);
        return self::rx_richUi_textToHtml(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'html');
    }

    public static function rx_richUi_buttonHtml(array $btn): ?string
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

    public static function rx_richUi_buttonsHtml(array $inlineRows): ?string
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
                $cell = self::rx_richUi_buttonHtml($btn);
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

    public static function rx_richUi_isRtl(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public static function rx_richUi_rtlMarker($text)
    {
        if (preg_match('~<!--\s*rxrtl:([01])\s*-->~i', (string) $text, $m)) {
            return $m[1] === '1';
        }
        return null;
    }

    public static function rx_richUi_stripRtlMarker($text): string
    {
        return (string) preg_replace('~<!--\s*(?:rxrtl:[01]|rxalign:swap)\s*-->\s*~i', '', (string) $text);
    }

    public static function rx_richUi_domText(DOMNode $parent, int $depth = 0)
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
            $text = self::rx_richUi_domText($node, $depth + 1);
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

    public static function rx_richUi_domBlocks(DOMNode $parent, int $depth = 0): ?array
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
                        $text = self::rx_richUi_domText($cell);
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
                    $text = self::rx_richUi_domText($caption);
                    if ($text === null) return null;
                    $block['caption'] = $text;
                }
                $blocks[] = $block;
            } elseif ($tag === 'tg-button-row') {
                $buttons = [];
                foreach ($node->childNodes as $button) {
                    if ($button instanceof DOMText && trim($button->nodeValue) === '') continue;
                    if (!($button instanceof DOMElement) || strtolower($button->tagName) !== 'tg-button') return null;
                    $text = self::rx_richUi_domText($button);
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
                $nested = self::rx_richUi_domBlocks($node, $depth + 1);
                if ($nested === null) return null;
                $blocks[] = ['type' => 'blockquote', 'blocks' => $nested];
            } elseif (in_array($tag, ['p', 'footer', 'blockquote', 'aside'], true) || preg_match('~^h[1-6]$~', $tag)) {
                $text = self::rx_richUi_domText($node);
                if ($text === null) return null;
                $type = $tag === 'footer' ? 'footer' : ($tag === 'blockquote' ? 'expandable_blockquote' : ($tag === 'aside' ? 'pullquote' : 'paragraph'));
                $block = ['type' => $type, 'text' => $text];
                if ($tag[0] === 'h') { $block['type'] = 'heading'; $block['size'] = (int) substr($tag, 1); }
                $blocks[] = $block;
            } else return null;
        }
        return $blocks;
    }

    public static function rx_richUi_structuredHtml(string $html): ?array
    {
        if (!class_exists('DOMDocument')) return null;
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? self::rx_richUi_domBlocks($body) : null;
    }

    public static function rx_richUi_visibleRichText($text): string
    {
        if (is_string($text)) return $text;
        if (!is_array($text)) return '';
        if (isset($text['type'])) {
            if (array_key_exists('text', $text)) return self::rx_richUi_visibleRichText($text['text']);
            if ($text['type'] === 'custom_emoji') return (string) ($text['alternative_text'] ?? '');
            if ($text['type'] === 'mathematical_expression') return (string) ($text['expression'] ?? '');
            return '';
        }
        $visible = '';
        foreach ($text as $part) $visible .= self::rx_richUi_visibleRichText($part);
        return $visible;
    }

    public static function rx_richUi_cellAlignment(string $align): string
    {
        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'left';
    }

    public static function rx_richUi_cellParagraphs($text)
    {
        if (is_string($text)) {
            return preg_replace_callback('/\r\n|[\n\r\x{0085}\x{2029}]/u', static function ($match) {
                return "\u{2069}" . $match[0] . "\u{200E}\u{2067}";
            }, $text) ?? $text;
        }
        if (!is_array($text)) return $text;
        if (isset($text['type'])) {
            if (array_key_exists('text', $text)) $text['text'] = self::rx_richUi_cellParagraphs($text['text']);
            return $text;
        }
        foreach ($text as &$part) $part = self::rx_richUi_cellParagraphs($part);
        unset($part);
        return $text;
    }

    public static function rx_richUi_cellDirectionalText($text, string $align)
    {
        if ($align === 'center') return $text;
        $visible = self::rx_richUi_visibleRichText($text);
        if (strncmp($visible, "\u{200E}\u{2067}", strlen("\u{200E}\u{2067}")) === 0) return $text;
        if (!preg_match('/\p{L}/u', $visible, $first)
            || !preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $first[0])) return $text;
        $text = self::rx_richUi_cellParagraphs($text);
        $parts = is_array($text) && !isset($text['type']) ? array_values($text) : [$text];
        array_unshift($parts, "\u{200E}\u{2067}");
        $parts[] = "\u{2069}";
        return $parts;
    }

    public static function rx_richUi_physicalTableLayout(array $blocks, bool $rtl): ?array
    {
        foreach ($blocks as &$block) {
            if (isset($block['blocks'])) {
                $nested = self::rx_richUi_physicalTableLayout($block['blocks'], $rtl);
                if ($nested === null) { unset($block); return null; }
                $block['blocks'] = $nested;
            }
            if (($block['type'] ?? '') === 'list') {
                foreach ($block['items'] as &$item) {
                    $nested = self::rx_richUi_physicalTableLayout($item['blocks'] ?? [], $rtl);
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
                    $cell['align'] = self::rx_richUi_cellAlignment((string) $align);
                    $cell['text'] = self::rx_richUi_cellDirectionalText($cell['text'] ?? '', $cell['align']);
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

    public static function rx_richUi_mediaBlocks(array $media): ?array
    {
        $types = ['photo', 'video', 'animation', 'document'];
        $blocks = [];
        foreach ($media as $item) {
            $source = is_array($item) ? ($item['media'] ?? null) : null;
            $type = is_array($source) ? (string) ($source['type'] ?? '') : '';
            if (!in_array($type, $types, true) || !is_string($source['media'] ?? null) || $source['media'] === '') {
                return null;
            }
            $blocks[] = ['type' => $type, $type => $source];
        }
        return $blocks ?: null;
    }

    public static function rx_richUi_buildMessage(string $textHtml, string $buttonsHtml, array $media = [], $rtlOverride = null): array
    {
        $rtl =$rtlOverride === null ? self::rx_richUi_isRtl(strip_tags($textHtml)) : (bool) $rtlOverride;
        $html = $textHtml;
        $blocks = !$media && preg_match('~<table\b~i', $html) ? self::rx_richUi_structuredHtml($html . $buttonsHtml) : null;
        $mediaInline = false;
        if ($blocks === null && $media && preg_match('~<table\b~i', $html)) {
            $mediaBlocks = self::rx_richUi_mediaBlocks($media);
            if ($mediaBlocks !== null) {
                $withoutMediaTags = (string) preg_replace('~<figure\b[^>]*>.*?</figure>|<video\b[^>]*>.*?</video>|<tg-document\b[^>]*>.*?</tg-document>~is', '', $html);
                $textBlocks = self::rx_richUi_structuredHtml($withoutMediaTags . $buttonsHtml);
                if ($textBlocks) {
                    $blocks = array_merge($mediaBlocks, $textBlocks);
                    $mediaInline = true;
                }
            }
        }
        $message = ['is_rtl' => $rtl];
        if ($blocks) {
            $physicalBlocks = self::rx_richUi_physicalTableLayout($blocks, $rtl);
            if ($physicalBlocks !== null) {
                $message['blocks'] = $physicalBlocks;
                $message['is_rtl'] = false;
            } else {
                $message['html'] = $html . $buttonsHtml;
                $mediaInline = false;
            }
        } else $message['html'] = $html . $buttonsHtml;
        if ($media && !$mediaInline) {
            $message['media'] = $media;
        }
        return $message;
    }

    public static function rx_richUi_richHasTable($blocks, int $depth = 0): bool
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
            if (isset($block['blocks']) && is_array($block['blocks']) && self::rx_richUi_richHasTable($block['blocks'], $depth + 1)) {
                return true;
            }
            if (isset($block['items']) && is_array($block['items'])) {
                foreach ($block['items'] as $item) {
                    if (is_array($item) && isset($item['blocks']) && is_array($item['blocks']) && self::rx_richUi_richHasTable($item['blocks'], $depth + 1)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function rx_richUi_inputBlocks(array $blocks, bool $stripButtons = false): ?array
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
                $nested = self::rx_richUi_inputBlocks($block['blocks']);
                if ($nested === null) {
                    return null;
                }
                $block['blocks'] = $nested;
            }
            if ($type === 'list') {
                foreach ($block['items'] as &$item) {
                    $nested = self::rx_richUi_inputBlocks($item['blocks'] ?? []);
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

    public static function rx_richUi_editRichButtons(array $datas, array $rich, array $rows, $token)
    {
        $blocks = isset($rich['blocks']) && is_array($rich['blocks']) ? self::rx_richUi_inputBlocks($rich['blocks'], true) : null;
        if ($blocks === null || !$blocks) {
            return ['ok' => false, 'error_code' => 400, 'description' => 'Cannot preserve rich message blocks while updating buttons'];
        }
        $embedButtons = method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richActiveForSend') && \Faoxima\Telegram\Presentation\Sections::rx_richActiveForSend($datas['chat_id'], $datas['reply_markup'] ?? null);
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
        $richPayloadValidation = \Faoxima\Telegram\Rich\Validation::rx_richUi_validateRichPayload($richPayload, ['method' => 'editMessageText']);
        if (empty($richPayloadValidation['valid'])) {
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
        return \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainCall('editMessageText', $payload, $token);
    }
}
