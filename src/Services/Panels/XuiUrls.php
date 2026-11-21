<?php

namespace Faoxima\Services\Panels;

final class XuiUrls
{
    public static function buildXuiSingleBaseUrl($url, $dropLastSegment = false)
    {
        $url = trim((string) $url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) return rtrim($url, '/');
        $path = $parts['path'] ?? '';
        if ($dropLastSegment && $path !== '') $path = preg_replace('#/[^/]*$#', '', $path);
        $base = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($path, '/');
        return rtrim($base, '/');
    }

    public static function normalizeXuiSingleSubscriptionBaseUrl($url)
    {
        return self::buildXuiSingleBaseUrl($url, true);
    }

    public static function hasLikelyXuiSubscriptionId($url)
    {
        return (bool) preg_match('#/(sub|sub/|xui|proxy|link)/?[A-Za-z0-9_-]{8,}#i', (string) $url);
    }
}
