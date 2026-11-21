<?php

namespace Faoxima\Admin\Web\Support;

final class AdClient
{
    public static function fx_ad_endpoint_path(): string
    {
        return '/api/v1/panel-ads';
    }

    public static function fx_ad_clean_text($value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }
        return substr($value, 0, $max);
    }

    public static function fx_ad_clean_url($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $value)) {
            return '';
        }
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return '';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || $host[0] === '[') {
            return '';
        }
        if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) {
            return '';
        }
        if (isset($parts['port']) && (int)$parts['port'] !== 443) {
            return '';
        }
        return $value;
    }

    public static function fx_ad_clean_time($value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $ts = strtotime(trim($value));
        return $ts === false ? null : (int)$ts;
    }

    public static function fx_ad_parse($doc, string $slot, ?int $now = null): ?array
    {
        $now = $now ?? time();
        if (!is_array($doc) || (int)($doc['schema'] ?? 0) !== 1) {
            return null;
        }
        if (array_key_exists('ads_enabled', $doc) && $doc['ads_enabled'] !== true) {
            return null;
        }
        $key = $slot === 'banner' ? 'login_banner' : 'post_login_modal';
        $block = $doc[$key] ?? null;
        if (!is_array($block) || ($block['enabled'] ?? false) !== true) {
            return null;
        }
        $ad = $block['ad'] ?? null;
        if (!is_array($ad) || ($ad['enabled'] ?? true) !== true) {
            return null;
        }

        $id = $ad['id'] ?? '';
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) {
            return null;
        }

        $schedule = $ad['schedule'] ?? null;
        if ($schedule !== null) {
            if (!is_array($schedule)) {
                return null;
            }
            foreach (['starts_at', 'ends_at'] as $bound) {
                if (isset($schedule[$bound]) && $schedule[$bound] !== null) {
                    $ts = self::fx_ad_clean_time($schedule[$bound]);
                    if ($ts === null) {
                        return null;
                    }
                    if ($bound === 'starts_at' && $now < $ts) {
                        return null;
                    }
                    if ($bound === 'ends_at' && $now >= $ts) {
                        return null;
                    }
                }
            }
        }

        $sponsor = is_array($ad['sponsor'] ?? null) ? $ad['sponsor'] : [];
        $sponsorName = self::fx_ad_clean_text($sponsor['name'] ?? '', 60);
        $headline = self::fx_ad_clean_text($ad['headline'] ?? '', 120);
        if ($sponsorName === '' || $headline === '') {
            return null;
        }

        $description = self::fx_ad_clean_text($ad['description'] ?? '', 400);
        $avatar = self::fx_ad_clean_url($sponsor['avatar_url'] ?? '');

        $media = null;
        $rawMedia = $ad['media'] ?? null;
        if (is_array($rawMedia)) {
            $type = $rawMedia['type'] ?? '';
            $allowed = $slot === 'banner' ? ['image'] : ['image', 'video'];
            $url = self::fx_ad_clean_url($rawMedia['url'] ?? '');
            if (is_string($type) && in_array($type, $allowed, true) && $url !== '') {
                $media = ['type' => $type, 'url' => $url, 'poster_url' => ''];
                if ($type === 'video') {
                    $media['poster_url'] = self::fx_ad_clean_url($rawMedia['poster_url'] ?? '');
                }
            }
        }

        $link = null;
        $rawLink = $ad['link'] ?? null;
        if (is_array($rawLink)) {
            $linkUrl = self::fx_ad_clean_url($rawLink['url'] ?? '');
            if ($linkUrl !== '') {
                $link = ['url' => $linkUrl, 'label' => self::fx_ad_clean_text($rawLink['label'] ?? '', 40)];
            }
        }
        if ($slot === 'banner' && $link === null) {
            return null;
        }

        return [
            'id' => $id,
            'sponsor' => ['name' => $sponsorName, 'avatar_url' => $avatar],
            'headline' => $headline,
            'description' => $description,
            'media' => $media,
            'link' => $link,
        ];
    }

    public static function fx_ad_fetch_raw(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $body = '';
        $limit = 65536;
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => 2000,
            CURLOPT_TIMEOUT_MS => 3500,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_USERAGENT => 'FaoximaPanel',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache, no-store, max-age=0',
                'Pragma: no-cache',
            ],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
                $body .= $chunk;
                return strlen($body) > $limit ? -1 : strlen($chunk);
            },
        ]);
        if (defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $ok = @curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false || $status !== 200 || $body === '' || strlen($body) > $limit) {
            return null;
        }
        return $body;
    }

    public static function fx_ad_fetch(string $slot): ?array
    {
        $slot = $slot === 'banner' ? 'banner' : 'modal';
        $domain = \Faoxima\Admin\Web\Support\AdBoot::fx_ad_gate();
        $version = trim((string)@file_get_contents((dirname(__DIR__, 4) . '/panel/lib') . '/../../version'));
        if (!preg_match('/^v?[0-9A-Za-z.\-]{1,20}$/', $version)) {
            $version = '';
        }
        $query = http_build_query([
            'placement' => $slot === 'banner' ? 'login_banner' : 'post_login_modal',
            'locale' => 'fa',
            'panel_version' => ltrim($version, 'vV'),
            '_' => bin2hex(random_bytes(8)),
        ], '', '&', PHP_QUERY_RFC3986);
        $url = 'https://' . $domain . self::fx_ad_endpoint_path() . '?' . $query;

        $body = self::fx_ad_fetch_raw($url);
        if ($body === null) {
            return null;
        }
        $doc = json_decode($body, true, 12);
        if (!is_array($doc)) {
            return null;
        }
        return self::fx_ad_parse($doc, $slot);
    }
}
