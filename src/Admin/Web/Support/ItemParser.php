<?php

namespace Faoxima\Admin\Web\Support;

final class ItemParser
{
    public static function faoxima_generate_group_id(): string {
        try {
            return 'mg_' . bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            return 'mg_' . str_replace('.', '', uniqid('', true));
        }
    }

    public static function faoxima_is_sub_link(string $line): bool {
    return (bool)preg_match('#^https?://\S+$#i', trim($line));
}

    public static function faoxima_parse_raw_lines(string $rawText): array {
    $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $rawText) ?: []);
    $clean = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || mb_substr($line, 0, 1, 'UTF-8') === '#') continue;
        $clean[] = $line;
    }
    return $clean;
}

    public static function faoxima_parse_payload_items($input): array {
    if (is_string($input) && trim($input) !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    if (is_array($input)) {
        $items = [];
        foreach ($input as $row) {
            if (!is_array($row)) continue;
            $cfg = trim((string)($row['content'] ?? $row['cfg'] ?? ''));
            $sub = trim((string)($row['sub_link'] ?? $row['sub'] ?? ''));
            if ($cfg === '' && $sub === '') continue;

            if ($sub !== '') {
                $items[] = ['cfg' => $cfg, 'sub' => $sub];
            } else {
                $subEntries = self::faoxima_pair_configs_and_links($cfg);
                if (!empty($subEntries)) {
                    foreach ($subEntries as $se) {
                        $items[] = $se;
                    }
                } else {
                    $items[] = ['cfg' => $cfg, 'sub' => ''];
                }
            }
        }
        return $items;
    }
    return [];
}

    public static function faoxima_pair_configs_and_links($input): array {
    $explicit = self::faoxima_parse_payload_items($input);
    if (!empty($explicit)) {
        return $explicit;
    }

    $rawText = is_string($input) ? $input : '';
    $clean = self::faoxima_parse_raw_lines($rawText);
    $entries = [];
    $i = 0;
    $n = count($clean);
    while ($i < $n) {
        $line1 = $clean[$i];
        $isLink1 = self::faoxima_is_sub_link($line1);
        if (!$isLink1 && $i + 1 < $n && self::faoxima_is_sub_link($clean[$i + 1])) {
            $entries[] = ['cfg' => $line1, 'sub' => $clean[$i + 1]];
            $i += 2;
        } elseif ($isLink1 && $i + 1 < $n && !self::faoxima_is_sub_link($clean[$i + 1])) {
            $entries[] = ['cfg' => $clean[$i + 1], 'sub' => $line1];
            $i += 2;
        } else {
            if ($isLink1) {
                $entries[] = ['cfg' => '', 'sub' => $line1];
            } else {
                $entries[] = ['cfg' => $line1, 'sub' => ''];
            }
            $i += 1;
        }
    }
    return $entries;
}

    public static function faoxima_pair_manual_service_items($input, string $nonLinkExt, string $pasteExt): array {
    $explicit = self::faoxima_parse_payload_items($input);
    if (!empty($explicit)) {
        $items = [];
        foreach ($explicit as $exp) {
            $cfg = $exp['cfg'];
            $sub = $exp['sub'];
            if ($cfg !== '' && $sub !== '') {
                $items[] = ['content' => $cfg, 'ext' => $nonLinkExt, 'sub' => $sub];
            } elseif ($sub !== '') {
                $items[] = ['content' => $sub, 'ext' => ($pasteExt !== '' ? $pasteExt : 'sub'), 'sub' => ''];
            } else {
                $items[] = ['content' => $cfg, 'ext' => $nonLinkExt, 'sub' => ''];
            }
        }
        return $items;
    }

    $rawText = is_string($input) ? $input : '';
    $clean = self::faoxima_parse_raw_lines($rawText);
    $items = [];
    $i = 0;
    $n = count($clean);
    while ($i < $n) {
        $line1 = $clean[$i];
        $isLink1 = self::faoxima_is_sub_link($line1);
        if (!$isLink1 && $i + 1 < $n && self::faoxima_is_sub_link($clean[$i + 1])) {
            $items[] = ['content' => $line1, 'ext' => $nonLinkExt, 'sub' => $clean[$i + 1]];
            $i += 2;
        } elseif ($isLink1 && $i + 1 < $n && !self::faoxima_is_sub_link($clean[$i + 1])) {
            $items[] = ['content' => $clean[$i + 1], 'ext' => $nonLinkExt, 'sub' => $line1];
            $i += 2;
        } elseif ($isLink1) {
            $items[] = ['content' => $line1, 'ext' => ($pasteExt !== '' ? $pasteExt : 'sub'), 'sub' => ''];
            $i += 1;
        } else {
            $items[] = ['content' => $line1, 'ext' => $nonLinkExt, 'sub' => ''];
            $i += 1;
        }
    }
    return $items;
}
}
