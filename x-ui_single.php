<?php

if (!defined('REFACTORED_LEGACY_ROOT')) {
    define('REFACTORED_LEGACY_ROOT', __DIR__);
}
@chdir(__DIR__);

require_once 'config.php';
require_once 'request.php';
ini_set('error_log', 'error_log');



if (!function_exists('xui_panel_uses_token')) {

    function xui_panel_uses_token($panel)
    {
        if (!is_array($panel)) {
            return false;
        }
        $hasToken = isset($panel['xui_api_token']) && trim((string) $panel['xui_api_token']) !== '';
        if (array_key_exists('xui_api_mode', $panel)) {
            $mode = trim((string) $panel['xui_api_mode']);
            if ($mode === 'token') {
                return $hasToken;
            }
            if ($mode === 'legacy') {
                return false;
            }
        }
        return $hasToken;
    }
}

if (!function_exists('xui_panel_token')) {
    function xui_panel_token($panel)
    {
        return trim((string) ($panel['xui_api_token'] ?? ''));
    }
}

if (!function_exists('xui_api_token_request')) {

    function xui_api_token_request($panel, $method, $path, $jsonBody = null, $timeout = 8, $source = 'xui_api_token_request', $username = null)
    {
        $base = rtrim((string) ($panel['url_panel'] ?? ''), '/');
        $req = new CurlRequest($base . $path);
        $req->setTimeout($timeout);
        $req->setBearerToken(xui_panel_token($panel));
        $headers = array('Accept: application/json', 'X-Requested-With: XMLHttpRequest');
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $req->setHeaders($headers);

        $method = strtoupper($method);
        $startedAt = microtime(true);
        if ($method === 'GET') {
            $resp = $req->get();
            $loggedBody = null;
        } else {
            $payload = $jsonBody === null
                ? ''
                : (is_string($jsonBody) ? $jsonBody : json_encode($jsonBody));
            $resp = $req->post($payload);
            $loggedBody = $payload;
        }
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (isset($resp['body']) && is_string($resp['body'])) {
            $decoded = json_decode($resp['body'], true);
            if (is_array($decoded) && array_key_exists('success', $decoded) && $decoded['success'] === false) {
                $resp['error'] = $decoded['msg'] ?? 'Unknown panel error';
            }
        }

        if (function_exists('xui_log_http_call')) {
            xui_log_http_call($panel, 'token', $method, $path, $loggedBody, $resp, $elapsedMs, $source, $username);
        }

        return $resp;
    }
}

if (!function_exists('xui_token_single_link')) {
    /**
     * Fetch a client's connection links directly from a modern 3x-ui panel via
     * the token API (/panel/api/clients/links/{email}). Returns the first
     * vless/vmess/trojan link or null.
     */
    function xui_token_single_link($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return null;
        }
        $resp = xui_api_token_request($panel, 'GET', '/panel/api/clients/links/' . rawurlencode($email), null, 6, 'xui_token_single_link', $email);
        if (empty($resp['body'])) {
            return null;
        }
        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded)) {
            return null;
        }
        $obj = $decoded['obj'] ?? $decoded;
        $candidates = array();
        if (is_string($obj)) {
            $candidates = preg_split('/\R/', trim($obj)) ?: array();
        } elseif (is_array($obj)) {
            foreach ($obj as $item) {
                if (is_string($item)) {
                    $candidates[] = $item;
                } elseif (is_array($item)) {
                    foreach (array('uri', 'link', 'url') as $k) {
                        if (!empty($item[$k]) && is_string($item[$k])) {
                            $candidates[] = $item[$k];
                        }
                    }
                }
            }
        }
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('/^(vless|vmess|trojan):\/\//i', $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('xuisingle_cookie_path')) {
    function xuisingle_cookie_path() {
        static $path = null;
        if ($path === null) {
            try {
                $entropy = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                $entropy = uniqid('', true);
            }
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'xuisingle_cookie_' . getmypid() . '_' . $entropy . '.txt';
        }
        return $path;
    }
}

function panel_login_cookie($code_panel)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $curl = curl_init();
    if (function_exists('faoxima_apply_curl_proxy')) faoxima_apply_curl_proxy($curl, 'panel');
    curl_setopt_array($curl, array(
        CURLOPT_URL => $panel['url_panel'] . '/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT_MS => 4000,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => "username={$panel['username_panel']}&password=" . urlencode($panel['password_panel']),
        CURLOPT_COOKIEJAR => xuisingle_cookie_path(),
    ));
    $rxLoginStarted = microtime(true);
    $response = curl_exec($curl);
    $rxLoginInfo = curl_getinfo($curl);
    if (function_exists('rx_perf_log')) {
        rx_perf_log('http', 'http.request', [
            'method' => 'POST',
            'url' => function_exists('rx_perf_safe_url') ? rx_perf_safe_url(($panel['url_panel'] ?? '') . '/login') : '',
            'attempt' => 1,
            'duration_ms' => round((microtime(true) - $rxLoginStarted) * 1000, 3),
            'http_code' => (int) ($rxLoginInfo['http_code'] ?? 0),
            'curl_errno' => (int) curl_errno($curl),
            'curl_error' => (string) curl_error($curl),
            'connect_ms' => round(((float) ($rxLoginInfo['connect_time'] ?? 0)) * 1000, 3),
            'tls_ready_ms' => round(((float) ($rxLoginInfo['appconnect_time'] ?? 0)) * 1000, 3),
            'namelookup_ms' => round(((float) ($rxLoginInfo['namelookup_time'] ?? 0)) * 1000, 3),
            'starttransfer_ms' => round(((float) ($rxLoginInfo['starttransfer_time'] ?? 0)) * 1000, 3),
        ]);
    }
    if (curl_error($curl)) {
        $token = [];
        $token['errror'] = curl_error($curl);
        if (function_exists('xui_log_http_call')) {
            xui_log_http_call($panel, 'legacy', 'POST', '/login', 'username=' . ($panel['username_panel'] ?? '') . '&password=***redacted***', array('status' => 0, 'error' => $token['errror']), 0, 'login', null);
        }
        return $token;
    }
    $loginHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($panel, 'legacy', 'POST', '/login', 'username=' . ($panel['username_panel'] ?? '') . '&password=***redacted***', array('status' => $loginHttpCode, 'body' => $response), 0, 'login', null);
    }
    return $response;
}
function login($code_panel, $verify = true)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    if ($panel['datelogin'] != null && $verify) {
        $date = json_decode($panel['datelogin'], true);
        if (isset($date['time'])) {
            $timecurrent = time();
            $start_date = time() - strtotime($date['time']);
            if ($start_date <= 3000) {
                file_put_contents(xuisingle_cookie_path(), $date['access_token']);
                return;
            }
        }
    }
    $response = panel_login_cookie($panel['code_panel']);
    $time = date('Y/m/d H:i:s');
    $data = json_encode(array(
        'time' => $time,
        'access_token' => file_get_contents(xuisingle_cookie_path())
    ));
    update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
    if (!is_string($response))
        return array('success' => false);
    return json_decode($response, true);
}

function panel_get_inbounds_list($baseUrl)
{
    $req = new CurlRequest(rtrim($baseUrl, '/') . '/panel/api/inbounds/list');
    $req->setTimeout(5);
    $req->setHeaders(['Accept: application/json']);
    $req->setCookie(xuisingle_cookie_path());
    return $req->get();
}

function find_uuid_by_email_in_list($jsonList, $email)
{
    $j = json_decode($jsonList, true);
    if (!($j['success'] ?? false)) {
        return null;
    }
    foreach ($j['obj'] ?? [] as $inb) {
        foreach (($inb['clientStats'] ?? []) as $c) {
            if (($c['email'] ?? '') === $email) {
                return $c['uuid'] ?? $c['id'] ?? null;
            }
        }
        $settings = $inb['settings'] ?? '{}';
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        foreach (($settings['clients'] ?? []) as $c) {
            if (($c['email'] ?? '') === $email) {
                return $c['id'] ?? null;
            }
        }
    }
    return null;
}

function get_client_traffic_by_uuid($baseUrl, $uuid)
{
    $req = new CurlRequest(rtrim($baseUrl, '/') . "/panel/api/inbounds/getClientTrafficsById/{$uuid}");
    $req->setHeaders(['Accept: application/json']);
    $req->setCookie(xuisingle_cookie_path());
    return $req->get();
}

if (!defined('XUI_SUB_LINK_SCHEMES')) {
    define('XUI_SUB_LINK_SCHEMES', '(vless|vmess|trojan|ss|hysteria2?|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|tg)');
}

function extract_links_from_raw_subscription($raw)
{
    if (!is_string($raw)) {
        return [];
    }
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return [];
    }
    $decoded = base64_decode($trimmed, true);
    if ($decoded !== false && preg_match('/' . XUI_SUB_LINK_SCHEMES . ':\/\//i', $decoded)) {
        $text = $decoded;
    } else {
        $text = $raw;
    }
    $links = [];
    $lines = preg_split('/\R/', trim($text));
    if (!is_array($lines)) {
        return [];
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^' . XUI_SUB_LINK_SCHEMES . ':\/\//i', $line)) {
            $links[] = $line;
        }
    }
    return $links;
}

function pick_single_link_text($raw)
{
    $links = extract_links_from_raw_subscription($raw);
    return $links[0] ?? null;
}

function fetch_subscription_links_with_retry($subscriptionUrl, $attempts = 1, $delayMicroseconds = 500000)
{
    $rxSubscriptionStarted = hrtime(true);
    $attempts = max(1, (int)$attempts);
    $links = [];
    $lastBody = null;
    for ($i = 0; $i < $attempts; $i++) {
        $req = new CurlRequest($subscriptionUrl);
        $req->setTimeout(6);
        $req->setHeaders(['User-Agent: SubFetcher/1.3']);
        $res = $req->get();
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300 && isset($res['body'])) {
            $lastBody = $res['body'];
            $links = extract_links_from_raw_subscription($res['body']);
            if (!empty($links)) {
                if (function_exists('rx_perf_span_end')) {
                    rx_perf_span_end('performance', 'handler.subscription_fetch', $rxSubscriptionStarted, [
                        'url' => function_exists('rx_perf_safe_url') ? rx_perf_safe_url($subscriptionUrl) : '',
                        'outer_attempts' => $i + 1,
                        'result' => 'ok',
                    ]);
                }
                return ['links' => $links, 'body' => $res['body']];
            }
        }
        if ($i < $attempts - 1) {
            usleep($delayMicroseconds);
        }
    }
    if (function_exists('rx_perf_span_end')) {
        rx_perf_span_end('performance', 'handler.subscription_fetch', $rxSubscriptionStarted, [
            'url' => function_exists('rx_perf_safe_url') ? rx_perf_safe_url($subscriptionUrl) : '',
            'outer_attempts' => $attempts,
            'retry_delay_ms' => round(max(0, (int) $delayMicroseconds) / 1000, 3),
            'result' => 'empty',
        ]);
    }
    return ['links' => $links, 'body' => $lastBody];
}

function get_subscription_links_with_retry($subscriptionUrl, $attempts = 3, $delayMicroseconds = 1200000)
{
    $result = fetch_subscription_links_with_retry($subscriptionUrl, $attempts, $delayMicroseconds);
    return $result['links'];
}

function pick_single_link_from_sub($subUrl)
{
    $result = fetch_subscription_links_with_retry($subUrl, 1, 0);
    return $result['links'][0] ?? null;
}

function panel_get_inbound($baseUrl, $inboundId)
{
    $req = new CurlRequest(rtrim($baseUrl, '/') . '/panel/api/inbounds/get/' . $inboundId);
    $req->setTimeout(5);
    $req->setHeaders(['Accept: application/json']);
    $req->setCookie(xuisingle_cookie_path());
    $r = $req->get();
    if (($r['status'] ?? 0) >= 200 && ($r['status'] ?? 0) < 300) {
        $j = json_decode($r['body'] ?? '', true);
        if (!is_array($j)) {
            return null;
        }
        $obj = $j['obj'] ?? null;
        if (!is_array($obj)) {
            return null;
        }
        if (isset($obj['settings']) && is_string($obj['settings'])) {
            $decodedSettings = json_decode($obj['settings'], true);
            if (is_array($decodedSettings)) {
                $obj['settings'] = $decodedSettings;
            }
        }
        if (isset($obj['streamSettings']) && is_string($obj['streamSettings'])) {
            $decodedStream = json_decode($obj['streamSettings'], true);
            if (is_array($decodedStream)) {
                $obj['streamSettings'] = $decodedStream;
            }
        }
        return $obj;
    }
    return null;
}

function find_client_by_subId($inbObj, $subId)
{
    $settings = $inbObj['settings'] ?? [];
    if (is_string($settings)) {
        $settings = json_decode($settings, true);
    }
    $settings = is_array($settings) ? $settings : [];
    foreach (($settings['clients'] ?? []) as $c) {
        if (($c['subId'] ?? '') === $subId) {
            return $c;
        }
    }
    return null;
}

function build_mtproto_link_from_inbound($inb, $client, $publicHost)
{
    $secret = $client['secret'] ?? '';
    if ($secret === '') {
        return null;
    }
    $port = $inb['port'] ?? '';
    return "tg://proxy?server={$publicHost}&port={$port}&secret={$secret}";
}

function find_awg_server_settings($inbObj)
{
    $settings = $inbObj['settings'] ?? [];
    if (is_string($settings)) {
        $settings = json_decode($settings, true);
    }
    $settings = is_array($settings) ? $settings : [];
    foreach (array('server', 'Server', 'wireguard', 'WireGuard') as $key) {
        if (isset($settings[$key]) && is_array($settings[$key])) {
            return $settings[$key];
        }
    }
    if (isset($settings['privateKey']) || isset($settings['publicKey'])) {
        return $settings;
    }
    return array();
}

function build_wireguard_conf_from_inbound($inb, $client, $publicHost)
{
    $clientPrivateKey = $client['privateKey'] ?? '';
    if ($clientPrivateKey === '') {
        return null;
    }
    $server = find_awg_server_settings($inb);
    $serverPublicKey = $server['publicKey'] ?? ($client['publicKey'] ?? '');
    if ($serverPublicKey === '') {
        return null;
    }
    $port = $inb['port'] ?? '';
    $addresses = $client['allowedIPs'] ?? array();
    if (!is_array($addresses)) {
        $addresses = array($addresses);
    }
    $address = !empty($addresses) ? implode(', ', $addresses) : '';
    $dns = array();
    if (!empty($server['primaryDns'])) {
        $dns[] = $server['primaryDns'];
    }
    if (!empty($server['secondaryDns'])) {
        $dns[] = $server['secondaryDns'];
    }
    $mtu = $server['mtu'] ?? '';
    $presharedKey = $client['preSharedKey'] ?? '';

    $lines = array();
    $lines[] = '[Interface]';
    $lines[] = "PrivateKey = {$clientPrivateKey}";
    if ($address !== '') {
        $lines[] = "Address = {$address}";
    }
    if (!empty($dns)) {
        $lines[] = 'DNS = ' . implode(', ', $dns);
    }
    if ($mtu !== '') {
        $lines[] = "MTU = {$mtu}";
    }
    foreach (array('jc', 'jmin', 'jmax', 's1', 's2', 's3', 's4', 'h1', 'h2', 'h3', 'h4') as $obfKey) {
        if (isset($server[$obfKey]) && $server[$obfKey] !== '') {
            $lines[] = strtoupper($obfKey) . " = {$server[$obfKey]}";
        }
    }
    $lines[] = '';
    $lines[] = '[Peer]';
    $lines[] = "PublicKey = {$serverPublicKey}";
    if ($presharedKey !== '') {
        $lines[] = "PresharedKey = {$presharedKey}";
    }
    $lines[] = "Endpoint = {$publicHost}:{$port}";
    $lines[] = 'AllowedIPs = 0.0.0.0/0, ::/0';
    return implode("\n", $lines) . "\n";
}

function build_vless_link_from_inbound($inb, $client, $publicHost)
{
    $uuid = $client['id'] ?? $client['uuid'] ?? '';
    if ($uuid === '') {
        return null;
    }
    $port = $inb['port'] ?? '';
    $remark = $inb['remark'] ?? ($client['email'] ?? '');
    $stream = $inb['streamSettings'] ?? [];
    if (is_string($stream)) {
        $stream = json_decode($stream, true);
    }
    $stream = is_array($stream) ? $stream : [];
    $net = $stream['network'] ?? 'tcp';
    $sec = $stream['security'] ?? 'none';
    $q = [
        'type' => $net,
        'security' => $sec,
        'encryption' => 'none',
    ];
    if (!empty($client['flow'])) {
        $q['flow'] = $client['flow'];
    }
    if ($net === 'tcp') {
        $hdr = $stream['tcpSettings']['header']['type'] ?? 'none';
        if ($hdr !== 'none') {
            $q['headerType'] = $hdr;
        }
    }
    if ($sec === 'reality') {
        $rs = $stream['realitySettings'] ?? [];
        $set = $rs['settings'] ?? [];
        $pbk = $set['publicKey'] ?? null;
        $sid = $rs['shortIds'][0] ?? null;
        $sni = $set['serverName'] ?? ($rs['serverNames'][0] ?? null);
        if (!$sni && !empty($rs['dest']) && strpos($rs['dest'], ':') !== false) {
            $parts = explode(':', $rs['dest']);
            $sni = $parts[0];
        }
        $fp = $set['fingerprint'] ?? null;
        $spx = $set['spiderX'] ?? null;
        if ($pbk) {
            $q['pbk'] = $pbk;
        }
        if ($sid) {
            $q['sid'] = $sid;
        }
        if ($sni) {
            $q['sni'] = $sni;
        }
        if ($fp) {
            $q['fp'] = $fp;
        }
        if ($spx && $spx !== '/') {
            $q['spx'] = $spx;
        }
    }
    $query = http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    $hash = rawurlencode($remark ?: ($client['email'] ?? ''));
    return "vless://{$uuid}@{$publicHost}:{$port}?{$query}#{$hash}";
}

if (!function_exists('xui_wg_decode_b64')) {
    function xui_wg_decode_b64($raw)
    {
        $clean = preg_replace('/\s+/', '', (string) $raw);
        if ($clean === '' || !preg_match('#^[A-Za-z0-9+/=_-]+$#', $clean)) {
            return null;
        }
        $std = strtr($clean, '-_', '+/');
        $pad = strlen($std) % 4;
        if ($pad > 0) {
            $std .= str_repeat('=', 4 - $pad);
        }
        $out = @base64_decode($std, true);
        return ($out === false || $out === '') ? null : $out;
    }
}

if (!function_exists('xui_wg_conf_protocol')) {
    function xui_wg_conf_protocol($conf)
    {
        if (!is_string($conf) || stripos($conf, '[Interface]') === false) {
            return null;
        }
        if (preg_match('/^\s*(Jc|Jmin|Jmax|S1|S2|S3|S4|H1|H2|H3|H4|I1|HeaderProtectionKey)\s*=/mi', $conf)) {
            return 'amneziawg';
        }
        return 'wireguard';
    }
}

if (!function_exists('xui_wg_conf_from_link')) {
    function xui_wg_conf_from_link($link)
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        if ($scheme === 'vpn' || $scheme === 'awg' || $scheme === 'amneziawg') {
            $payload = substr($link, strlen($scheme) + 3);
            $payload = explode('#', $payload, 2)[0];
            $conf = xui_wg_decode_b64($payload);
            $proto = $conf !== null ? xui_wg_conf_protocol($conf) : null;
            if ($proto === null) {
                return null;
            }
            return array('protocol' => $proto, 'conf' => $conf);
        }

        if ($scheme !== 'wireguard' && $scheme !== 'wg') {
            return null;
        }

        $body = substr($link, strlen($scheme) + 3);
        $remark = '';
        if (strpos($body, '#') !== false) {
            list($body, $remarkRaw) = explode('#', $body, 2);
            $remark = rawurldecode($remarkRaw);
        }
        $atPos = strrpos($body, '@');
        if ($atPos === false) {
            return null;
        }
        $privateKey = rawurldecode(substr($body, 0, $atPos));
        $rest = substr($body, $atPos + 1);
        $query = '';
        if (strpos($rest, '?') !== false) {
            list($rest, $query) = explode('?', $rest, 2);
        }
        $endpoint = $rest;
        if ($privateKey === '' || $endpoint === '') {
            return null;
        }
        $params = array();
        parse_str($query, $params);
        $lower = array();
        foreach ($params as $k => $v) {
            $lower[strtolower($k)] = is_array($v) ? reset($v) : $v;
        }

        $lines = array('[Interface]');
        $lines[] = "PrivateKey = {$privateKey}";
        if (!empty($lower['address'])) {
            $lines[] = 'Address = ' . str_replace(',', ', ', $lower['address']);
        }
        if (!empty($lower['dns'])) {
            $lines[] = 'DNS = ' . str_replace(',', ', ', $lower['dns']);
        }
        if (!empty($lower['mtu'])) {
            $lines[] = 'MTU = ' . $lower['mtu'];
        }
        $awgKeys = array('jc' => 'Jc', 'jmin' => 'Jmin', 'jmax' => 'Jmax', 's1' => 'S1', 's2' => 'S2', 's3' => 'S3', 's4' => 'S4', 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'i1' => 'I1');
        $isAwg = false;
        foreach ($awgKeys as $qk => $confKey) {
            if (isset($lower[$qk]) && $lower[$qk] !== '') {
                $lines[] = "{$confKey} = {$lower[$qk]}";
                $isAwg = true;
            }
        }
        $lines[] = '';
        if ($remark !== '') {
            $lines[] = '# ' . $remark;
        }
        $lines[] = '[Peer]';
        if (!empty($lower['publickey'])) {
            $lines[] = 'PublicKey = ' . $lower['publickey'];
        }
        if (!empty($lower['presharedkey'])) {
            $lines[] = 'PresharedKey = ' . $lower['presharedkey'];
        }
        $lines[] = 'AllowedIPs = ' . (!empty($lower['allowedips']) ? str_replace(',', ', ', $lower['allowedips']) : '0.0.0.0/0, ::/0');
        $lines[] = "Endpoint = {$endpoint}";
        if (!empty($lower['keepalive'])) {
            $lines[] = 'PersistentKeepalive = ' . $lower['keepalive'];
        }

        return array('protocol' => $isAwg ? 'amneziawg' : 'wireguard', 'conf' => implode("\n", $lines) . "\n");
    }
}

if (!function_exists('xui_wg_conf_filename')) {
    function xui_wg_conf_filename($link, $protocol, $index = 0)
    {
        $remark = '';
        $hashPos = strpos((string) $link, '#');
        if ($hashPos !== false) {
            $remark = rawurldecode(substr((string) $link, $hashPos + 1));
        }
        if ($remark === '') {
            $conf = xui_wg_conf_from_link($link);
            if (is_array($conf) && preg_match('/^\s*#\s*(.+)$/m', $conf['conf'], $m)) {
                $remark = trim($m[1]);
            }
        }
        $base = preg_replace('#[\\/:*?"<>|\s_]+#u', '-', $remark);
        $base = trim((string) preg_replace('/-+/', '-', $base), '-');
        if ($base === '' || preg_match('/^[0-9-]+$/', $base)) {
            $prefix = ($protocol === 'amneziawg' ? 'amneziawg' : 'wireguard');
            $base = $base === '' ? $prefix . '-' . ($index + 1) : $prefix . '-' . $base;
        }
        $base = rtrim(substr($base, 0, 15), '-.');
        return $base . '.conf';
    }
}

function build_single_config_from_inbound($inb, $client, $publicHost)
{
    $protocol = strtolower((string) ($inb['protocol'] ?? ''));
    if ($protocol === 'mtproto') {
        $link = build_mtproto_link_from_inbound($inb, $client, $publicHost);
        return $link ? array('type' => 'link', 'value' => $link) : null;
    }
    if ($protocol === 'wireguard' || $protocol === 'amneziawg') {
        $conf = build_wireguard_conf_from_inbound($inb, $client, $publicHost);
        if (!$conf) {
            return null;
        }
        $remark = $inb['remark'] ?? ($client['email'] ?? 'config');
        $filename = preg_replace('/[^A-Za-z0-9-]+/', '-', str_replace('_', '-', (string) $remark));
        $filename = rtrim(substr(trim((string) preg_replace('/-+/', '-', $filename), '-'), 0, 15), '-.');
        if ($filename === '') {
            $filename = $protocol === 'amneziawg' ? 'amneziawg' : 'wireguard';
        }
        $filename .= '.conf';
        return array('type' => 'file', 'value' => $conf, 'filename' => $filename ?: 'wireguard.conf', 'protocol' => $protocol);
    }
    if (in_array($protocol, array('vless', 'vmess', 'trojan'), true) || $protocol === '') {
        $link = build_vless_link_from_inbound($inb, $client, $publicHost);
        return $link ? array('type' => 'link', 'value' => $link) : null;
    }
    return null;
}

function get_single_link_smart($panelBase, $inboundId, $subscriptionUrl, $username = null, $panelName = null, $panelCode = null)
{
    $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
    if ($subscriptionUrl === '') {
        return null;
    }
    $result = fetch_subscription_links_with_retry($subscriptionUrl, 1, 500000);
    if (!empty($result['links'])) {
        $firstLink = $result['links'][0];
        $wgFromSub = xui_wg_conf_from_link($firstLink);
        if (is_array($wgFromSub)) {
            return array(
                'type' => 'file',
                'value' => $wgFromSub['conf'],
                'filename' => xui_wg_conf_filename($firstLink, $wgFromSub['protocol'], 0),
                'protocol' => $wgFromSub['protocol'],
            );
        }
        return array('type' => 'link', 'value' => $firstLink);
    }
    if ($panelName) {
        $panelRowForToken = select("marzban_panel", "*", "name_panel", $panelName, "select");
        if (xui_panel_uses_token($panelRowForToken)) {
            $tokenLink = xui_token_single_link($panelRowForToken, $username);
            if ($tokenLink) {
                return array('type' => 'link', 'value' => $tokenLink);
            }
        }
    }
    $panelBase = rtrim((string)$panelBase, '/');
    if ($panelBase === '') {
        return null;
    }
    if ($panelCode) {
        login($panelCode);
    }
    $inboundIdList = xui_parse_inbound_ids($inboundId);
    $firstInboundId = !empty($inboundIdList) ? $inboundIdList[0] : $inboundId;
    $inb = panel_get_inbound($panelBase, $firstInboundId);
    if (!$inb) {
        return null;
    }
    $path = parse_url($subscriptionUrl, PHP_URL_PATH) ?: '';
    $subId = basename($path);
    if (!$subId) {
        return null;
    }
    $client = find_client_by_subId($inb, $subId);
    if (!$client) {
        return null;
    }
    $host = parse_url($subscriptionUrl, PHP_URL_HOST) ?: 'localhost';
    return build_single_config_from_inbound($inb, $client, $host);
}

function get_single_link_after_create($panelBase, $inboundId, $subscriptionUrl, $username, $panelName, $panelCode = null)
{
    return get_single_link_smart($panelBase, $inboundId, $subscriptionUrl, $username, $panelName, $panelCode);
}

if (!function_exists('xui_get_full_client')) {
    function xui_get_full_client($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'GET', '/panel/api/clients/get/' . rawurlencode($email), null, 8, 'xui_get_full_client', $email);
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['obj'] ?? null)) {
            return array('status' => false, 'msg' => 'client not found');
        }
        $obj = $decoded['obj'];
        $clientRecord = is_array($obj['client'] ?? null) ? $obj['client'] : $obj;
        if (!isset($clientRecord['email']) || $clientRecord['email'] === '') {
            return array('status' => false, 'msg' => 'client record missing email');
        }
        return array(
            'status' => true,
            'client' => $clientRecord,
            'inboundIds' => is_array($obj['inboundIds'] ?? null) ? $obj['inboundIds'] : array(),
            'usedTraffic' => $obj['usedTraffic'] ?? null,
        );
    }
}

if (!function_exists('xui_server_status')) {
    function xui_server_status($panel)
    {
        if (!xui_panel_uses_token($panel)) {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'GET', '/panel/api/server/status', null, 6, 'xui_server_status');
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['obj'] ?? null)) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        return array('status' => true, 'data' => $decoded['obj']);
    }
}

if (!function_exists('xui_fail2ban_status')) {
    function xui_fail2ban_status($panel)
    {
        if (!xui_panel_uses_token($panel)) {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'GET', '/panel/api/server/fail2banStatus', null, 6, 'xui_fail2ban_status');
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['obj'] ?? null)) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        return array('status' => true, 'data' => $decoded['obj']);
    }
}

if (!function_exists('xui_clients_summary')) {
    function xui_clients_summary($panel)
    {
        if (!xui_panel_uses_token($panel)) {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'GET', '/panel/api/clients/list', null, 8, 'xui_clients_summary');
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['obj'] ?? null)) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        $total = 0;
        $expired = 0;
        $depleted = 0;
        $disabled = 0;
        $now = time() * 1000;
        foreach ($decoded['obj'] as $client) {
            if (!is_array($client)) {
                continue;
            }
            $total++;
            if (empty($client['enable'])) {
                $disabled++;
            }
            $expiryTime = intval($client['expiryTime'] ?? 0);
            if ($expiryTime > 0 && $expiryTime <= $now) {
                $expired++;
            }
            $totalGB = intval($client['totalGB'] ?? 0);
            $traffic = intval($client['traffic'] ?? 0);
            if ($totalGB > 0 && $traffic >= $totalGB) {
                $depleted++;
            }
        }
        return array(
            'status' => true,
            'total' => $total,
            'expired' => $expired,
            'depleted' => $depleted,
            'disabled' => $disabled,
        );
    }
}

if (!function_exists('xui_clients_onlines')) {
    function xui_clients_onlines($panel)
    {
        if (!xui_panel_uses_token($panel)) {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'POST', '/panel/api/clients/onlines', array(), 8, 'xui_clients_onlines');
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        $obj = $decoded['obj'] ?? array();
        return array('status' => true, 'emails' => is_array($obj) ? $obj : array());
    }
}

if (!function_exists('xui_client_is_online')) {
    function xui_client_is_online($panel, $email)
    {
        $onlines = xui_clients_onlines($panel);
        if (empty($onlines['status'])) {
            return array('status' => false, 'msg' => $onlines['msg'] ?? 'unavailable');
        }
        return array('status' => true, 'online' => in_array($email, $onlines['emails'], true));
    }
}

if (!function_exists('xui_client_last_online')) {
    function xui_client_last_online($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'POST', '/panel/api/clients/lastOnline', array(), 8, 'xui_client_last_online');
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['obj'] ?? null)) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        if (!array_key_exists($email, $decoded['obj'])) {
            return array('status' => true, 'lastOnline' => null);
        }
        return array('status' => true, 'lastOnline' => intval($decoded['obj'][$email]));
    }
}

if (!function_exists('xui_parse_inbound_ids')) {
    function xui_parse_inbound_ids($raw)
    {
        if (is_array($raw)) {
            $candidates = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return array();
            }
            $decoded = json_decode($raw, true);
            $candidates = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        $ids = array();
        foreach ($candidates as $candidate) {
            $id = intval(trim((string) $candidate));
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}

if (!function_exists('xui_mask_ip')) {
    function xui_mask_ip($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return '';
        }
        if (strpos($ip, ':') !== false) {
            $parts = explode(':', $ip);
            $keep = array_slice($parts, 0, max(1, count($parts) - 4));
            return implode(':', $keep) . ':xxxx';
        }
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
        }
        return 'xxx.xxx.xxx.xxx';
    }
}

if (!function_exists('xui_client_ip_summary')) {
    function xui_client_ip_summary($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'POST', '/panel/api/clients/ips/' . rawurlencode($email), array(), 8, 'xui_client_ip_summary', $email);
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        $entries = is_array($decoded['obj'] ?? null) ? $decoded['obj'] : array();
        $ips = array();
        foreach ($entries as $entry) {
            $ip = '';
            if (is_string($entry)) {
                $ip = trim(preg_replace('/\s*\(.*\)$/', '', $entry));
            } elseif (is_array($entry) && isset($entry['ip']) && is_string($entry['ip'])) {
                $ip = trim($entry['ip']);
            }
            if ($ip !== '') {
                $ips[$ip] = true;
            }
        }
        $rawIps = array_keys($ips);
        $masked = array_map('xui_mask_ip', $rawIps);
        return array('status' => true, 'count' => count($ips), 'masked' => $masked, 'raw' => $rawIps);
    }
}

if (!function_exists('xui_client_hwid_devices')) {
    function xui_client_hwid_devices($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $resp = xui_api_token_request($panel, 'POST', '/panel/api/clients/hwids/' . rawurlencode($email), array(), 8, 'xui_client_hwid_devices', $email);
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return array('status' => false, 'msg' => 'invalid response');
        }
        $devices = is_array($decoded['obj'] ?? null) ? $decoded['obj'] : array();
        return array('status' => true, 'total' => count($devices), 'devices' => $devices);
    }
}

if (!function_exists('xui_client_clear_hwid')) {
    function xui_client_clear_hwid($panel, $email)
    {
        if (!xui_panel_uses_token($panel) || $email === null || $email === '') {
            return array('status' => false, 'msg' => 'token mode required');
        }
        $base = rtrim((string) ($panel['url_panel'] ?? ''), '/');
        $req = new CurlRequest($base . '/panel/api/clients/hwids/' . rawurlencode($email));
        $req->setTimeout(8);
        $req->setBearerToken(xui_panel_token($panel));
        $req->setHeaders(array('Accept: application/json', 'X-Requested-With: XMLHttpRequest'));
        $startedAt = microtime(true);
        $resp = $req->delete();
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        if (function_exists('xui_log_http_call')) {
            xui_log_http_call($panel, 'token', 'DELETE', '/panel/api/clients/hwids/' . $email, null, $resp, $elapsedMs, 'xui_client_clear_hwid', $email);
        }
        if (!empty($resp['error'])) {
            return array('status' => false, 'msg' => $resp['error']);
        }
        if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
            return array('status' => false, 'msg' => 'HTTP ' . ($resp['status'] ?? 0));
        }
        $decoded = json_decode($resp['body'] ?? '', true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return array('status' => false, 'msg' => $decoded['msg'] ?? 'invalid response');
        }
        return array('status' => true);
    }
}

function get_clinets($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (xui_panel_uses_token($marzban_list_get)) {
        return xui_api_token_request(
            $marzban_list_get,
            'GET',
            '/panel/api/clients/traffic/' . rawurlencode($username),
            null,
            8,
            'get_clinets',
            $username
        );
    }
    login($marzban_list_get['code_panel']);
    $base = rtrim($marzban_list_get['url_panel'], '/');
    $url = $base . "/panel/api/inbounds/getClientTraffics/$username";
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $legacyStartedAt = microtime(true);
    $response = $req->get();
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($marzban_list_get, 'legacy', 'GET', '/panel/api/inbounds/getClientTraffics/' . $username, null, $response, (int) round((microtime(true) - $legacyStartedAt) * 1000), 'get_clinets', $username);
    }

    if (isset($response['body'])) {
        $decodedBody = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBody)) {
            if (isset($decodedBody['success']) && $decodedBody['success'] === false) {
                $response['error'] = $decodedBody['msg'] ?? 'Unknown panel error';
            }
        }
    }

    if (!empty($response['error']) && stripos($response['error'], 'Inbound Not Found For Email') !== false) {
        $list = panel_get_inbounds_list($base);
        if (($list['status'] ?? 0) >= 200 && ($list['status'] ?? 0) < 300) {
            $uuid = find_uuid_by_email_in_list($list['body'] ?? '', $username);
            if ($uuid) {
                $byId = get_client_traffic_by_uuid($base, $uuid);
                if (($byId['status'] ?? 0) >= 200 && ($byId['status'] ?? 0) < 300) {
                    $response = $byId;
                    $response['error'] = null;
                }
            }
        }
    }


    if (!empty($response['error']) && stripos((string) $response['error'], 'Inbound Not Found For Email') === false) {
        $dedupKey = 'xui_single_resp|' . (string) $response['error'];
        if (function_exists('faoxima_dedup_error_log')) {
            faoxima_dedup_error_log($dedupKey, json_encode($response));
        } else {
            error_log(json_encode($response));
        }
    }

    if (is_file(xuisingle_cookie_path())) {
        @unlink(xuisingle_cookie_path());
    }

    return $response;
}
function addClient($namepanel, $usernameac, $Expire, $Total, $Uuid, $Flow, $subid, $inboundid, $name_product, $note = "", $LimitIp = 0, $LimitHwid = 0)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (!xui_panel_uses_token($marzban_list_get)) {
        login($marzban_list_get['code_panel']);
    }
    if ($name_product == "usertest") {
        if ($marzban_list_get['on_hold_test'] == "1") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    } else {
        if ($marzban_list_get['conecton'] == "onconecton") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    }
    if (xui_panel_uses_token($marzban_list_get)) {
        $client = array(
            "id" => $Uuid,
            "email" => $usernameac,
            "totalGB" => $Total,
            "expiryTime" => $timeservice,
            "enable" => true,
            "tgId" => 0,
            "subId" => $subid,
            "limitIp" => max(0, intval($LimitIp)),
            "limitHwid" => max(0, intval($LimitHwid)),
            "reset" => 0,
            "comment" => $note,
        );
        if ($Flow !== "" && $Flow !== null) {
            $client["flow"] = $Flow;
        }
        if (!isset($usernameac)) {
            return array('status' => 500, 'msg' => 'username is null');
        }
        $inboundIds = xui_parse_inbound_ids($inboundid);
        if (empty($inboundIds)) {
            $inboundIds = array(intval($inboundid));
        }
        return xui_api_token_request(
            $marzban_list_get,
            'POST',
            '/panel/api/clients/add',
            array(
                'client' => $client,
                'inboundIds' => $inboundIds,
            ),
            8,
            'addClient',
            $usernameac
        );
    }
    $config = array(
        "id" => intval($inboundid),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                    "id" => $Uuid,
                    "flow" => $Flow,
                    "email" => $usernameac,
                    "totalGB" => $Total,
                    "expiryTime" => $timeservice,
                    "enable" => true,
                    "tgId" => "",
                    "subId" => $subid,
                    "reset" => 0,
                    "comment" => $note
                )
            ),
            'decryption' => 'none',
            'fallbacks' => array(),
        ))
    );
    if (!isset($usernameac))
        return array(
            'status' => 500,
            'msg' => 'username is null'
        );
    $configpanel = json_encode($config, true);
    $url = $marzban_list_get['url_panel'] . '/panel/api/inbounds/addClient';
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $legacyStartedAt = microtime(true);
    $response = $req->post($configpanel);
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($marzban_list_get, 'legacy', 'POST', '/panel/api/inbounds/addClient', $configpanel, $response, (int) round((microtime(true) - $legacyStartedAt) * 1000), 'addClient', $usernameac);
    }
    @unlink(xuisingle_cookie_path());
    return $response;
}
function updateClient($namepanel, $uuid, array $config)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (xui_panel_uses_token($marzban_list_get)) {
        $settings = $config['settings'] ?? array();
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        $client = is_array($settings) && isset($settings['clients'][0]) ? $settings['clients'][0] : null;
        if (!is_array($client) || empty($client['email'])) {
            return array(
                'status' => 500,
                'body' => json_encode(array('success' => false, 'msg' => 'client email missing')),
            );
        }
        $allowedClientKeys = array(
            'email', 'totalGB', 'expiryTime', 'enable', 'tgId', 'subId', 'limitIp',
            'limitHwid', 'reset', 'comment', 'flow', 'security', 'password', 'auth', 'privateKey',
            'publicKey', 'preSharedKey', 'allowedIPs', 'keepAlive', 'secret', 'adTag',
            'group', 'reverse',
        );
        $safeClient = array();
        foreach ($allowedClientKeys as $allowedKey) {
            if (array_key_exists($allowedKey, $client)) {
                $safeClient[$allowedKey] = $client[$allowedKey];
            }
        }
        $rawId = $client['id'] ?? null;
        if (is_string($rawId) && $rawId !== '' && !ctype_digit($rawId)) {
            $safeClient['id'] = $rawId;
        } elseif (isset($client['uuid']) && $client['uuid'] !== '') {
            $safeClient['id'] = $client['uuid'];
        } elseif ($rawId !== null) {
            $safeClient['id'] = $rawId;
        }
        if (isset($safeClient['flow']) && $safeClient['flow'] === '') {
            unset($safeClient['flow']);
        }
        if (array_key_exists('allowedIPs', $safeClient) && !is_array($safeClient['allowedIPs'])) {
            $rawAllowedIPs = trim((string) $safeClient['allowedIPs']);
            $safeClient['allowedIPs'] = $rawAllowedIPs === ''
                ? array()
                : array_values(array_filter(array_map('trim', explode(',', $rawAllowedIPs))));
        }
        return xui_api_token_request(
            $marzban_list_get,
            'POST',
            '/panel/api/clients/update/' . rawurlencode($safeClient['email']),
            $safeClient,
            8,
            'updateClient',
            $safeClient['email']
        );
    }
    login($marzban_list_get['code_panel']);
    $configpanel = json_encode($config, true);
    $url = $marzban_list_get['url_panel'] . '/panel/api/inbounds/updateClient/' . $uuid;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $legacyStartedAt = microtime(true);
    $response = $req->post($configpanel);
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($marzban_list_get, 'legacy', 'POST', '/panel/api/inbounds/updateClient/' . $uuid, $configpanel, $response, (int) round((microtime(true) - $legacyStartedAt) * 1000), 'updateClient', $uuid);
    }
    @unlink(xuisingle_cookie_path());
    return $response;
}
function ResetUserDataUsagex_uisin($usernamepanel, $namepanel)
{
    $panel_token_row = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (xui_panel_uses_token($panel_token_row)) {
        return xui_api_token_request(
            $panel_token_row,
            'POST',
            '/panel/api/clients/resetTraffic/' . rawurlencode($usernamepanel),
            array(),
            8,
            'ResetUserDataUsagex_uisin',
            $usernamepanel
        );
    }
    $data_user = get_clinets($usernamepanel, $namepanel);
    $data_user = json_decode($data_user['body'], true)['obj'];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/{$data_user['inboundId']}/resetClientTraffic/" . $usernamepanel;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $legacyStartedAt = microtime(true);
    $response = $req->post(array());
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($marzban_list_get, 'legacy', 'POST', "/panel/api/inbounds/{$data_user['inboundId']}/resetClientTraffic/" . $usernamepanel, null, $response, (int) round((microtime(true) - $legacyStartedAt) * 1000), 'ResetUserDataUsagex_uisin', $usernamepanel);
    }
    @unlink(xuisingle_cookie_path());
    return $response;
}
function removeClient($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (xui_panel_uses_token($marzban_list_get)) {
        return xui_api_token_request(
            $marzban_list_get,
            'POST',
            '/panel/api/clients/del/' . rawurlencode($username),
            array(),
            8,
            'removeClient',
            $username
        );
    }
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClientByEmail/" . $username;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $legacyStartedAt = microtime(true);
    $response = $req->post(array());
    if (function_exists('xui_log_http_call')) {
        xui_log_http_call($marzban_list_get, 'legacy', 'POST', "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClientByEmail/" . $username, null, $response, (int) round((microtime(true) - $legacyStartedAt) * 1000), 'removeClient', $username);
    }
    @unlink(xuisingle_cookie_path());
    return $response;
}
