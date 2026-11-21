<?php

namespace Faoxima\Orders\Catalog;

final class CatalogLookups
{
    public static function nmDecodeState($raw)
    {
        if (is_array($raw)) return $raw;
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function nmProductButtonName($text)
    {
        $text = \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($text);
        $text = preg_replace('/\s*-\s*[0-9۰-۹,،.]+\s*(?:تومان|ریال)?\s*$/u', '', $text);
        return \Faoxima\Telegram\Support\AdminInput::nmNormalizeText($text);
    }

    public static function nmProductByCodeForPanel($codeProduct, $panelName, $agent = null)
    {
        global $pdo;
        return \Faoxima\Orders\Catalog\Products::nmProductByCodeForPanel($pdo, $codeProduct, $panelName, $agent);
    }

    public static function nmProductByNameForPanel($productName, $panelName, $agent = null, $category = null)
    {
        global $pdo;
        return \Faoxima\Orders\Catalog\Products::nmProductByNameForPanel($pdo, $productName, $panelName, $agent, $category);
    }

    public static function nmMatchingPanelRow(array $panels, string $cleanInput): ?array
    {
        if ($cleanInput === '') {
            return null;
        }
        foreach ($panels as $panel) {
            if (isset($panel['name_panel']) && \Faoxima\Telegram\Support\AdminInput::rx_cleanEmojiAndSymbols($panel['name_panel']) === $cleanInput) {
                return $panel;
            }
        }
        foreach ($panels as $panel) {
            if (!isset($panel['name_panel'])) {
                continue;
            }
            $cleanPanel = \Faoxima\Telegram\Support\AdminInput::rx_cleanEmojiAndSymbols($panel['name_panel']);
            if ($cleanPanel !== '' && ($cleanPanel === $cleanInput || strpos($cleanPanel, $cleanInput) !== false || strpos($cleanInput, $cleanPanel) !== false)) {
                return $panel;
            }
        }
        return null;
    }

    public static function nmSellableCategoryRows($location, $agent)
    {
        global $pdo;
        return \Faoxima\Orders\Catalog\Categories::nmSellableCategoryRows($pdo, $location, $agent);
    }
}
