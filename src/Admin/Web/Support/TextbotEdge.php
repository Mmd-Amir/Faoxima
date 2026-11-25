<?php

namespace Faoxima\Admin\Web\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;

final class TextbotEdge
{
    public static function fx_textbot_edge_routing_keys(): array
    {
        return [
            'text_sell',
            'text_usertest',
            'text_help',
            'text_support',
            'accountwallet',
            'text_Tariff_list',
            'text_affiliates',
            'textpanelagent',
            'textrequestagent',
            'text_wheel_luck',
            'text_extend',
            'text_Purchased_services',
            'jsontext.users.backbtn',
            'jsontext.users.agenttext.customnameusername',
            'jsontext.Admin.backadmin',
            'jsontext.Admin.backmenu',
            'jsontext.Admin.Status.btn',
            'jsontext.Admin.btnkeyboardadmin.addpanel',
            'jsontext.Admin.btnkeyboardadmin.managementpanel',
            'jsontext.Admin.btnkeyboardadmin.managruser',
            'jsontext.Admin.channel.removechannelbtn',
            'jsontext.Admin.channel.title',
            'jsontext.Admin.getlimitusertest.setlimitbtn',
        ];
    }

    public static function fx_textbot_is_routing_key(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::fx_textbot_edge_routing_keys() as $known) {
            if ($k === strtolower($known)) {
                return true;
            }
        }
        return false;
    }

    public static function fx_textbot_edge_class(): string
    {
        return '(?![\x{E0020}-\x{E007F}])[\p{Z}\p{Cc}\p{Cf}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2800}\x{3164}\x{FFA0}]';
    }

    public static function fx_textbot_edge_issue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $class = self::fx_textbot_edge_class();
        $lead = preg_match('/\A' . $class . '/u', $value);
        $trail = preg_match('/' . $class . '\z/u', $value);
        if ($lead === false || $trail === false) {
            $lead = $value !== ltrim($value) ? 1 : 0;
            $trail = $value !== rtrim($value) ? 1 : 0;
        }
        if ($lead && $trail) {
            return 'both';
        }
        if ($lead) {
            return 'leading';
        }
        if ($trail) {
            return 'trailing';
        }
        return '';
    }

    public static function fx_textbot_edge_violation(string $key, string $value): string
    {
        if (!self::fx_textbot_is_routing_key($key)) {
            return '';
        }
        return self::fx_textbot_edge_issue($value);
    }

    public static function fx_textbot_edge_error_message(): string
    {
        return 'ابتدا یا انتهای متن نباید شامل فاصله یا کاراکتر نامرئی باشد.';
    }

    public static function fx_textbot_edge_js_config(): string
    {
        return json_encode([
            'keys'    => array_map('strtolower', self::fx_textbot_edge_routing_keys()),
            'cls'     => str_replace('\x{', '\u{', self::fx_textbot_edge_class()),
            'message' => self::fx_textbot_edge_error_message(),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public static function fx_textbot_rich_prefix(): string
    {
        return 'richmsg.';
    }

    public static function fx_textbot_rich_button_keys(): array
    {
        return [
            'text_sell',
            'text_usertest',
            'text_help',
            'text_support',
            'text_fq',
            'text_affiliates',
            'text_Tariff_list',
            'text_Purchased_services',
            'text_extend',
            'text_wheel_luck',
            'accountwallet',
            'textpanelagent',
            'textrequestagent',
            'text_Add_Balance',
            'text_Discount',
            'text_miniapp_button',
            'carttocart',
            'textnowpayment',
            'textsnowpayment',
            'textnowpaymenttron',
            'iranpay1',
            'iranpay2',
            'iranpay3',
            'tonpay',
            'cubepay',
            'blupal',
            'variza',
            'abangateway',
            'atlaspay',
            'rialit',
            'tetraminator',
            'zarinpal',
            'textpaymentnotverify',
            'text_star_telegram',
            'dyn_wallet_prompt_has_discount_yes',
            'dyn_wallet_prompt_has_discount_no',
        ];
    }

    public static function fx_textbot_rich_eligibility(string $key): array
    {
        $k = strtolower(trim($key));
        if (self::fx_textbot_is_routing_key($key)) {
            return [
                'allowed' => false,
                'tables'  => false,
                'kind'    => 'routing',
                'reason'  => 'این متن برای تشخیص و مسیریابی دکمه‌ها استفاده می‌شود؛ افزودن قالب‌بندی آن را از کار می‌اندازد.',
            ];
        }
        if (method_exists(\Faoxima\Payments\Gateways\Tonpay\Templates::class, 'tonpayTemplateIsKnown') && \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateIsKnown($key)) {
            return [
                'allowed' => false,
                'tables'  => false,
                'kind'    => 'tonpay',
                'reason'  => 'این متن مربوط به پرداخت است و محدودیت طول و ساختار مخصوص خود را دارد.',
            ];
        }
        static $buttonKeys = null;
        if ($buttonKeys === null) {
            $buttonKeys = array_map('strtolower', self::fx_textbot_rich_button_keys());
        }
        if (in_array($k, $buttonKeys, true)
            || strpos($k, 'btnkeyboardadmin') !== false
            || preg_match('/(?:^|[._])btn[a-z0-9_]*/i', $k)
            || preg_match('/(?:^|[._])(button|btns|backbtn|backmenu|setlimitbtn|titlebtn|btntitle)(?:$|[._])/i', $k)
            || preg_match('/(?:^|[._])(acceptrequest|rejectrequest|sendmessage)(?:$|[._])/i', $k)
        ) {
            return [
                'allowed' => false,
                'tables'  => false,
                'kind'    => 'button',
                'reason'  => 'این متن به‌عنوان برچسب دکمه استفاده می‌شود؛ در دکمه‌های تلگرام فقط متن ساده و ایموجی پرمیوم پشتیبانی می‌شود.',
            ];
        }
        if (preg_match('/_alert$/i', $k) || $k === 'jsontext.users.agenttext.invalidtypeagent') {
            return [
                'allowed' => false,
                'tables'  => false,
                'kind'    => 'alert',
                'reason'  => 'این متن در اعلان شناور تلگرام نمایش داده می‌شود و قالب‌بندی HTML در آن پشتیبانی نمی‌شود.',
            ];
        }
        if (preg_match('/(?:^|[._])(caption|cap)(?:$|[._])/i', $k)) {
            return [
                'allowed' => true,
                'tables'  => false,
                'kind'    => 'caption',
                'reason'  => 'این متن ممکن است به‌عنوان کپشن رسانه استفاده شود؛ جدول و بلوک‌های ساختاری در کپشن مناسب نیستند.',
            ];
        }
        return [
            'allowed' => true,
            'tables'  => true,
            'kind'    => 'body',
            'reason'  => '',
        ];
    }

    public static function fx_textbot_rich_allowed_tags(bool $inlineOnly = false): array
    {
        $inline = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'code', 'mark', 'sub', 'sup', 'tg-spoiler', 'tg-emoji', 'a', 'tg-time', 'tg-math', 'br', 'tg-reference'];
        if ($inlineOnly) {
            return $inline;
        }
        return array_merge($inline, ['p', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'footer', 'ul', 'ol', 'li', 'blockquote', 'aside', 'details', 'summary', 'table', 'caption', 'tr', 'th', 'td', 'tg-button-row', 'tg-button', 'figure', 'figcaption', 'cite']);
    }

    public static function fx_textbot_rich_attr_allowed(string $tag, string $attr): bool
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'a'             => ['href', 'name'],
                'tg-emoji'      => ['emoji-id'],
                'img'           => ['src', 'alt'],
                'code'          => ['class'],
                'td'            => ['colspan', 'rowspan', 'align', 'valign'],
                'th'            => ['colspan', 'rowspan', 'align', 'valign'],
                'table'         => ['bordered', 'striped', 'compact'],
                'ol'            => ['start', 'type', 'reversed'],
                'details'       => ['open'],
                'tg-time'       => ['unix', 'format'],
                'tg-button-row' => ['align'],
                'tg-button'     => ['type', 'data', 'url', 'query', 'text', 'style', 'forward-text', 'request-write-access'],
                'tg-reference'  => ['name'],
            ];
        }
        return isset($map[$tag]) && in_array($attr, $map[$tag], true);
    }

    public static function fx_textbot_rich_sanitize_node(DOMNode $node, array $allowed, bool $inlineOnly): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (!isset($allowed[$tag])) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'meta', 'link'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                self::fx_textbot_rich_sanitize_node($node, $allowed, $inlineOnly);
                return;
            }
            $names = [];
            foreach ($child->attributes as $attr) {
                $names[] = $attr->name;
            }
            foreach ($names as $name) {
                $lower = strtolower($name);
                if (!self::fx_textbot_rich_attr_allowed($tag, $lower) || preg_match('/^on/i', $lower)) {
                    $child->removeAttribute($name);
                    continue;
                }
                $value = (string) $child->getAttribute($name);
                if ($lower === 'href') {
                    if (!preg_match('~^(https?://|mailto:|tel:|tg://|#)~i', trim($value))) {
                        $child->removeAttribute($name);
                    }
                } elseif ($lower === 'src') {
                    if (!preg_match('~^tg://emoji\?id=\d+$~i', trim($value))) {
                        $child->removeAttribute($name);
                    }
                } elseif ($lower === 'class') {
                    if ($tag !== 'code' || !preg_match('~^language-[a-z0-9_+-]{1,32}$~i', trim($value))) {
                        $child->removeAttribute($name);
                    }
                } elseif ($lower === 'colspan' || $lower === 'rowspan') {
                    $n = (int) $value;
                    if ($n < 1 || $n > ($lower === 'rowspan' ? 100 : 20)) {
                        $child->setAttribute($name, '1');
                    }
                } elseif ($lower === 'align') {
                    if (!in_array($value, ['left', 'center', 'right'], true)) {
                        $child->setAttribute($name, 'left');
                    }
                } elseif ($lower === 'valign') {
                    if (!in_array($value, ['top', 'middle', 'bottom'], true)) {
                        $child->setAttribute($name, 'middle');
                    }
                } elseif ($lower === 'emoji-id') {
                    if (!ctype_digit($value)) {
                        $child->removeAttribute($name);
                    }
                }
            }
            self::fx_textbot_rich_sanitize_node($child, $allowed, $inlineOnly);
        }
    }

    public static function fx_textbot_rich_sanitize_html(string $html, bool $inlineOnly = false): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        if (trim($html) === '') {
            return '';
        }
        $allowed = array_fill_keys(self::fx_textbot_rich_allowed_tags($inlineOnly), true);
        if (!class_exists('DOMDocument')) {
            $allow = '<' . implode('><', array_keys($allowed)) . '>';
            $clean = strip_tags($html, $allow);
            $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean);
            $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean);
            $clean = preg_replace('/\s(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $clean);
            return trim((string) $clean);
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->encoding = 'UTF-8';
        $doc->loadHTML('<?xml encoding="UTF-8">' . '<!DOCTYPE html><html><body><div id="fx-rich-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = null;
        foreach ($doc->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') === 'fx-rich-root') {
                $root = $element;
                break;
            }
        }
        if (!$root) {
            return '';
        }
        self::fx_textbot_rich_sanitize_node($root, $allowed, $inlineOnly);
        $out = '';
        foreach ($root->childNodes as $child) {
            $piece = $doc->saveHTML($child);
            if ($piece !== false) {
                $out .= $piece;
            }
        }
        return trim($out);
    }

    public static function fx_textbot_rich_plain_text(string $value, int $limit = 0): string
    {
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = trim((string) $value);
        if ($limit > 0 && mb_strlen($value, 'UTF-8') > $limit) {
            $value = mb_substr($value, 0, $limit, 'UTF-8');
        }
        return $value;
    }

    public static function fx_textbot_rich_table_normalize(array $block): ?array
    {
        $rows = $block['rows'] ?? null;
        if (!is_array($rows) || !$rows || count($rows) > 100) {
            return null;
        }
        $outRows = [];
        $maxCols = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $outRow = [];
            foreach ($row as $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                $colspan = min(20, max(1, (int) ($cell['colspan'] ?? 1)));
                $rowspan = min(100, max(1, (int) ($cell['rowspan'] ?? 1)));
                $align = (string) ($cell['align'] ?? 'left');
                if (!in_array($align, ['left', 'center', 'right'], true)) {
                    $align = 'left';
                }
                $valign = (string) ($cell['valign'] ?? 'middle');
                if (!in_array($valign, ['top', 'middle', 'bottom'], true)) {
                    $valign = 'middle';
                }
                $outRow[] = [
                    'html'    => self::fx_textbot_rich_sanitize_html((string) ($cell['html'] ?? ''), true),
                    'header'  => !empty($cell['header']),
                    'colspan' => $colspan,
                    'rowspan' => $rowspan,
                    'align'   => $align,
                    'valign'  => $valign,
                ];
            }
            $outRows[] = $outRow;
        }
        if (!$outRows) {
            return null;
        }
        $occupied = [];
        $cellCount = 0;
        foreach ($outRows as $r => $row) {
            $c = 0;
            foreach ($row as $cell) {
                while (!empty($occupied[$r][$c])) {
                    $c++;
                }
                $endCol = $c + $cell['colspan'];
                $endRow = $r + $cell['rowspan'];
                if ($endCol > 20 || $endRow > count($outRows)) {
                    return null;
                }
                for ($rr = $r; $rr < $endRow; $rr++) {
                    for ($cc = $c; $cc < $endCol; $cc++) {
                        if (!empty($occupied[$rr][$cc])) {
                            return null;
                        }
                        $occupied[$rr][$cc] = true;
                    }
                }
                $maxCols = max($maxCols, $endCol);
                $c = $endCol;
                $cellCount++;
            }
            if (!$row && empty($occupied[$r])) {
                return null;
            }
        }
        if ($maxCols > 20 || $cellCount === 0) {
            return null;
        }
        return [
            'type'     => 'table',
            'bordered' => !empty($block['bordered']),
            'striped'  => !empty($block['striped']),
            'compact'  => !empty($block['compact']),
            'caption'  => self::fx_textbot_rich_plain_text((string) ($block['caption'] ?? ''), 200),
            'rows'     => $outRows,
        ];
    }

    public static function fx_textbot_rich_state_normalize($raw): ?array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return null;
        }
        $blocks = $decoded['blocks'] ?? null;
        if (!is_array($blocks) || !$blocks || count($blocks) > 500) {
            return null;
        }
        $outBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if ($type === 'p' || $type === 'h' || $type === 'quote' || $type === 'details') {
                $html = self::fx_textbot_rich_sanitize_html((string) ($block['html'] ?? ''), $type === 'p' || $type === 'h');
                $item = ['type' => $type, 'html' => $html];
                if ($type === 'h') {
                    $level = (int) ($block['level'] ?? 2);
                    $item['level'] = min(6, max(1, $level));
                } elseif ($type === 'quote') {
                    $item['expandable'] = !empty($block['expandable']);
                } elseif ($type === 'details') {
                    $item['summary'] = self::fx_textbot_rich_plain_text((string) ($block['summary'] ?? ''), 200);
                    $item['open'] = !empty($block['open']);
                }
                $outBlocks[] = $item;
            } elseif ($type === 'list') {
                $items = $block['items'] ?? [];
                if (!is_array($items)) {
                    $items = [];
                }
                $clean = [];
                foreach ($items as $li) {
                    $value = self::fx_textbot_rich_sanitize_html((string) $li, true);
                    if (trim($value) !== '') {
                        $clean[] = $value;
                    }
                    if (count($clean) >= 100) {
                        break;
                    }
                }
                if (!$clean) {
                    $clean = [''];
                }
                $outBlocks[] = ['type' => 'list', 'ordered' => !empty($block['ordered']), 'items' => $clean];
            } elseif ($type === 'pre') {
                $text = (string) ($block['text'] ?? '');
                if (mb_strlen($text, 'UTF-8') > 10000) {
                    $text = mb_substr($text, 0, 10000, 'UTF-8');
                }
                $language = preg_replace('/[^a-z0-9_+-]/i', '', (string) ($block['language'] ?? ''));
                $outBlocks[] = ['type' => 'pre', 'text' => $text, 'language' => substr((string) $language, 0, 32)];
            } elseif ($type === 'hr') {
                $outBlocks[] = ['type' => 'hr'];
            } elseif ($type === 'table') {
                $table = self::fx_textbot_rich_table_normalize($block);
                if ($table !== null) {
                    $outBlocks[] = $table;
                } else {
                    return null;
                }
            }
        }
        if (!$outBlocks) {
            return null;
        }
        return [
            'version' => 1,
            'is_rtl'  => !empty($decoded['is_rtl']),
            'blocks'  => $outBlocks,
            'html'    => self::fx_textbot_rich_sanitize_html((string) ($decoded['html'] ?? '')),
            'plain'   => self::fx_textbot_rich_plain_text((string) ($decoded['plain'] ?? ''), 20000),
        ];
    }

    public static function fx_textbot_rich_state_encode(array $state): string
    {
        $json = (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (function_exists('gzcompress')) {
            $packed = base64_encode((string) gzcompress($json, 9));
            if ($packed !== '' && strlen($packed) < strlen($json)) {
                return 'gz:' . $packed;
            }
        }
        return $json;
    }

    public static function fx_textbot_rich_state_decode_storage(string $raw): string
    {
        if (strpos($raw, 'gz:') === 0 && function_exists('gzuncompress')) {
            $decoded = @gzuncompress((string) base64_decode(substr($raw, 3), true));
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        return $raw;
    }

    public static function fx_textbot_rich_state_for_save($raw, string $text, ?string &$issue = null): ?array
    {
        $issue = null;
        if ($raw === '' || $raw === null) {
            return null;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $rawHtml = is_array($decoded) && isset($decoded['html']) && is_string($decoded['html']) ? $decoded['html'] : '';
        $state = self::fx_textbot_rich_state_normalize($raw);
        if ($state === null) {
            $issue = 'ساختار دادهٔ ویرایشگر پرمیوم معتبر نیست؛ نسخهٔ قبلی حفظ شد.';
            return null;
        }
        $stateHtml = trim(str_replace(["\r\n", "\r"], "\n", $rawHtml));
        $stateHtml = trim((string) preg_replace('~^\s*<!--\s*rxrtl:[01]\s*-->\s*~i', '', $stateHtml));
        $textNorm = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $textNorm = trim((string) preg_replace('~^\s*<!--\s*rxrtl:[01]\s*-->\s*~i', '', $textNorm));
        if ($stateHtml === '' || $stateHtml !== $textNorm) {
            $issue = 'متن و دادهٔ ویرایشگر پرمیوم هماهنگ نیستند؛ نسخهٔ قبلی حفظ شد.';
            return null;
        }
        $sanitized = self::fx_textbot_rich_sanitize_html($textNorm);
        if ($sanitized === '') {
            $issue = 'متن پرمیوم پس از پاک‌سازی خالی است؛ نسخهٔ قبلی حفظ شد.';
            return null;
        }
        $rtlTag = !empty($state['is_rtl']) ? '<!--rxrtl:1-->' : '<!--rxrtl:0-->';
        $sanitized = $rtlTag . "\n" . $sanitized;
        if (mb_strlen($sanitized, 'UTF-8') > 16000 || strlen($sanitized) > 60000) {
            $issue = 'متن پرمیوم از محدودیت طول یا حجم ذخیره‌سازی بیشتر است؛ نسخهٔ قبلی حفظ شد.';
            return null;
        }
        $state['html'] = $sanitized;
        if (strlen(self::fx_textbot_rich_state_encode($state)) > 60000) {
            $issue = 'حجم دادهٔ ویرایشگر پرمیوم بیش از حد مجاز است؛ نسخهٔ قبلی حفظ شد.';
            return null;
        }
        return $state;
    }
}
