<?php

declare(strict_types=1);

final class ServiceSearch
{
    public const VISIBLE_STATUS = "Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin')";
    public const MAX_QUERY_LENGTH = 2048;
    private const REBECCA_CLIENT_FORMATS = [
        'clash', 'clash-meta', 'clashmeta', 'clash.meta', 'mihomo', 'clash-mihomo', 'clash-mi', 'clashmi',
        'sing-box', 'singbox', 'sing',
        'v2ray', 'v2ray-json', 'xray-json', 'json',
        'happ', 'hiddify', 'hiddify-next', 'hiddifynext', 'hiddifynextx',
        'v2raytun', 'v2ray-tun', 'v2ray-tunnel', 'v2raytun-plus',
        'throne', 'thron', 'throne-vpn', 'thronevpn', 'shadowrocket', 'karing', 'outline',
        'incy', 'passwall', 'passwall2', 'nekobox', 'nekobox-plus', 'nekoboxplus', 'nekobox+',
        'openvpn', 'wireguard', 'wg', 'amneziawg', 'awg', 'amnezia-wg',
    ];

    public static function numericId($value): ?string
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $digits = strtr(ltrim(trim((string) $value), '#'), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $id = ltrim($digits, '0');
        return ctype_digit($digits) && $id !== '' && strlen($id) <= 19 ? $id : null;
    }

    private static function uuid($value): ?string
    {
        if (!is_string($value) || !preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})$/iD', $value)) {
            return null;
        }
        return strtolower(str_replace('-', '', $value));
    }

    public static function isPrivate(string $query): bool
    {
        return preg_match('~^https?://~i', $query) === 1 || self::uuid($query) !== null;
    }

    /**
     * $scope is supplied by the authenticated entry point and is reapplied to the final invoice query.
     * Returns a WHERE fragment and its new parameters, using the unaliased invoice table.
     */
    public static function condition(PDO $pdo, string $query, string $scope, array $scopeParams, array $textColumns = ['username'], bool $includeTextMatches = false): array
    {
        $query = trim($query);
        if ($query === '' || strlen($query) > self::MAX_QUERY_LENGTH) {
            return ['0 = 1', []];
        }

        $exact = [];
        $exactParams = [];
        $text = [];
        $textParams = [];
        $isUrl = preg_match('~^https?://~i', $query) === 1;
        $uuid = self::uuid($query);
        $number = $uuid === null ? self::numericId($query) : null;
        if ($isUrl) {
            $url = self::url($query);
            if ($url === null) {
                return ['0 = 1', []];
            }
            $localId = self::localSubscriptionId($query);
            if ($localId !== null) {
                return ['BINARY id_invoice = :svc_order', [':svc_order' => $localId]];
            }
            $exact[] = '(BINARY user_info = :svc_raw_url OR BINARY JSON_UNQUOTE(JSON_EXTRACT('
                . "IF(JSON_VALID(user_info), user_info, NULL), '$.subscription_url')) = :svc_cached_url)";
            $exactParams[':svc_raw_url'] = $query;
            $exactParams[':svc_cached_url'] = $query;
        } else {
            $like = '%' . strtr($query, ['!' => '!!', '_' => '!_', '%' => '!%']) . '%';
            foreach ($textColumns as $index => $column) {
                $param = ':svc_text_' . $index;
                $text[] = "$column LIKE $param ESCAPE '!'";
                $textParams[$param] = $like;
            }
            if ($number !== null) {
                $exact[] = 'id_invoice = :svc_order';
                $exactParams[':svc_order'] = $number;
            }
            if ($uuid !== null) {
                $exact[] = "(LOWER(REPLACE(uuid, '-', '')) = :svc_uuid OR "
                    . "LOWER(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(user_info), user_info, NULL), '$.uuid')), '-', '')) = :svc_cached_uuid)";
                $exactParams[':svc_uuid'] = $uuid;
                $exactParams[':svc_cached_uuid'] = $uuid;
            }
        }

        if ($isUrl || $number !== null || $uuid !== null) {
            $links = [$url ?? null];
            if ($isUrl && in_array(str_replace('_', '-', strtolower(rawurldecode(basename($url[1])))), self::REBECCA_CLIENT_FORMATS, true)) {
                $basePath = dirname($url[1]);
                if ($basePath !== '/' && $basePath !== '.') {
                    array_unshift($links, [$url[0], $basePath]);
                }
            }
            $panels = $pdo->prepare("SELECT DISTINCT Service_location FROM invoice WHERE ($scope)
                AND Service_location IN (SELECT name_panel FROM marzban_panel WHERE type = 'rebecca')");
            $panels->execute($scopeParams);
            foreach ($panels->fetchAll(PDO::FETCH_COLUMN) as $panel) {
                foreach ($links as $link) {
                    $needle = $link === null ? ($number ?? $query) : rawurldecode(basename($link[1]));
                    if ($needle === '') {
                        continue;
                    }
                    $matchesBefore = count($exact);
                    foreach (rebeccaListUsers($panel, search: $needle, links: $uuid !== null) as $user) {
                        $username = (string) ($user['username'] ?? '');
                        if ($username === '' || !self::matchesRebecca($panel, $user, $link, $number, $uuid)) {
                            continue;
                        }
                        $index = count($exact);
                        $exact[] = "(Service_location = :svc_panel_$index AND BINARY username = :svc_user_$index)";
                        $exactParams[":svc_panel_$index"] = $panel;
                        $exactParams[":svc_user_$index"] = $username;
                        if ($number !== null) {
                            break;
                        }
                    }
                    if (count($exact) > $matchesBefore) {
                        break;
                    }
                }
            }
        }
        if ($exact !== []) {
            if ($includeTextMatches && !$isUrl) {
                return ['(' . implode(' OR ', array_merge($exact, $text)) . ')', $exactParams + $textParams];
            }
            $condition = '(' . implode(' OR ', $exact) . ')';
            if ($isUrl) {
                return [$condition, $exactParams];
            }
            // Keep the text search for numeric usernames, notes and volumes when no ID matches.
            $match = $pdo->prepare("SELECT 1 FROM invoice WHERE ($scope) AND $condition LIMIT 1");
            $match->execute($scopeParams + $exactParams);
            if ($match->fetchColumn() !== false) {
                return [$condition, $exactParams];
            }
        }
        return ['(' . implode(' OR ', $text) . ')', $textParams];
    }

    private static function url(string $url, string $panelUrl = ''): ?array
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && $panelUrl !== '') {
            $base = parse_url($panelUrl);
            if (empty($base['scheme']) || empty($base['host'])) {
                return null;
            }
            $url = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $url;
        }
        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        return [strtolower($parts['host']) . ':' . $port, rtrim($path, '/')];
    }

    private static function localSubscriptionId(string $query): ?string
    {
        $domain = $GLOBALS['domainhosts'] ?? '';
        $local = $domain !== '' ? self::url('https://' . $domain . '/sub') : null;
        $input = self::url($query);
        if ($local === null || $input === null || $local[0] !== $input[0]) {
            return null;
        }
        $prefix = $local[1] . '/';
        if (!str_starts_with($input[1], $prefix)) {
            return null;
        }
        $token = substr($input[1], strlen($prefix));
        return preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $token) ? $token : null;
    }

    private static function matchesRebecca(string $panel, array $user, ?array $input, ?string $number, ?string $uuid): bool
    {
        if ($number !== null) {
            return self::numericId($user['id'] ?? null) === $number;
        }
        $urls = (array) ($user['subscription_urls'] ?? []);
        if ($input !== null) {
            $config = getRebeccaPanelConfig($panel);
            $panelUrl = (string) ($config['panel']['url_panel'] ?? '');
            $urls['primary'] = $user['subscription_url'] ?? '';
            foreach ($urls as $kind => $url) {
                if (!is_string($url) || ($known = self::url($url, $panelUrl)) === null) {
                    continue;
                }
                if ($input === $known) {
                    return true;
                }
                // Signed links are regenerated with each response. Verify the supplied token
                // through Rebecca, using the trusted link only to establish the host and path.
                if (($config['status'] ?? false) && explode('@', (string) $kind, 2)[0] === 'token' && $input[0] === $known[0]) {
                    $token = basename($input[1]);
                    if (preg_match('/^[A-Za-z0-9_.-]{15,512}$/D', $token)
                        && dirname($input[1]) === dirname($known[1])) {
                        $endpoint = rtrim(dirname($known[1]), '/') . '/' . rawurlencode($token) . '/info';
                        $panelPath = rtrim((string) parse_url($panelUrl, PHP_URL_PATH), '/');
                        if ($panelPath !== '' && str_starts_with($endpoint, $panelPath . '/')) {
                            $endpoint = substr($endpoint, strlen($panelPath));
                        }
                        $result = rebeccaDecodeResponse(rebeccaApiRequest($config, 'GET', $endpoint, redactUrl: true));
                        if (($result['data']['user']['username'] ?? null) === $user['username']) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        foreach ($urls as $kind => $url) {
            if (is_string($url) && in_array(explode('@', (string) $kind, 2)[0], ['key', 'username-key'], true)
                && self::uuid(basename((string) parse_url($url, PHP_URL_PATH))) === $uuid) {
                return true;
            }
        }
        foreach ((array) ($user['links'] ?? []) as $link) {
            if (!is_string($link)) {
                continue;
            }
            if (stripos($link, 'vless://') === 0) {
                $credential = parse_url($link, PHP_URL_USER);
            } elseif (stripos($link, 'vmess://') === 0) {
                $payload = explode('#', substr($link, 8), 2)[0];
                $config = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
                $credential = $config['id'] ?? $config['uuid'] ?? null;
            } else {
                continue;
            }
            if (self::uuid($credential) === $uuid) {
                return true;
            }
        }
        return false;
    }

    /** Load numeric IDs by exact username in batches grouped by panel. */
    public static function addPanelIds(PDO $pdo, array $rows): array
    {
        $byPanel = [];
        foreach ($rows as $row) {
            if (!empty($row['Service_location']) && isset($row['username']) && $row['username'] !== '') {
                $byPanel[$row['Service_location']][] = (string) $row['username'];
            }
        }
        if ($byPanel === []) {
            return $rows;
        }

        $marks = implode(',', array_fill(0, count($byPanel), '?'));
        $stmt = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE type = 'rebecca' AND name_panel IN ($marks)");
        $stmt->execute(array_keys($byPanel));
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $panel) {
            foreach (array_chunk(array_values(array_unique($byPanel[$panel] ?? [])), 25) as $usernames) {
                foreach (rebeccaListUsers($panel, usernames: $usernames) as $user) {
                    $ids[$panel][(string) ($user['username'] ?? '')] = self::numericId($user['id'] ?? null);
                }
            }
        }
        foreach ($rows as &$row) {
            $row['panel_user_id'] = $ids[$row['Service_location'] ?? ''][$row['username'] ?? ''] ?? null;
        }
        unset($row);
        return $rows;
    }
}
