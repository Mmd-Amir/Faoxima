<?php
require_once 'config.php';
require_once 'request.php';
ini_set('error_log', 'error_log');


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
    $response = curl_exec($curl);
    if (curl_error($curl)) {
        $token = [];
        $token['errror'] = curl_error($curl);
        return $token;
    }
    curl_close($curl);
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
    if ($decoded !== false && preg_match('/(vless|vmess|trojan):\/\//i', $decoded)) {
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
        if (preg_match('/^(vless|vmess|trojan):\/\//i', $line)) {
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
                return ['links' => $links, 'body' => $res['body']];
            }
        }
        if ($i < $attempts - 1) {
            usleep($delayMicroseconds);
        }
    }
    return ['links' => $links, 'body' => $lastBody];
}

function fallback_single_link_from_clients_api($username, $panelName)
{
    if (!$username || !$panelName || !function_exists('GetClientsS_UI')) {
        return null;
    }
    $clientData = GetClientsS_UI($username, $panelName);
    if (!is_array($clientData) || empty($clientData)) {
        return null;
    }
    $candidates = [];
    if (isset($clientData['uri'])) {
        if (is_array($clientData['uri'])) {
            $candidates = array_merge($candidates, $clientData['uri']);
        } else {
            $candidates[] = $clientData['uri'];
        }
    }
    if (isset($clientData['links']) && is_array($clientData['links'])) {
        foreach ($clientData['links'] as $item) {
            if (is_string($item)) {
                $candidates[] = $item;
            } elseif (is_array($item) && isset($item['uri'])) {
                $candidates[] = $item['uri'];
            }
        }
    }
    if (isset($clientData['config']) && is_array($clientData['config'])) {
        foreach ($clientData['config'] as $item) {
            if (is_string($item)) {
                $candidates[] = $item;
            } elseif (is_array($item) && isset($item['uri'])) {
                $candidates[] = $item['uri'];
            }
        }
    }
    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }
        if (preg_match('/^(vless|vmess|trojan):\/\//i', $candidate)) {
            return $candidate;
        }
    }
    return null;
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

function get_single_link_smart($panelBase, $inboundId, $subscriptionUrl, $username = null, $panelName = null, $panelCode = null)
{
    $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
    if ($subscriptionUrl === '') {
        return null;
    }
    $result = fetch_subscription_links_with_retry($subscriptionUrl, 1, 500000);
    if (!empty($result['links'])) {
        return $result['links'][0];
    }
    $fallback = fallback_single_link_from_clients_api($username, $panelName);
    if ($fallback) {
        return $fallback;
    }
    $panelBase = rtrim((string)$panelBase, '/');
    if ($panelBase === '') {
        return null;
    }
    if ($panelCode) {
        login($panelCode);
    }
    $inb = panel_get_inbound($panelBase, $inboundId);
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
    return build_vless_link_from_inbound($inb, $client, $host);
}

function get_single_link_after_create($panelBase, $inboundId, $subscriptionUrl, $username, $panelName, $panelCode = null)
{
    return get_single_link_smart($panelBase, $inboundId, $subscriptionUrl, $username, $panelName, $panelCode);
}

function get_clinets($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
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
    $response = $req->get();

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
        error_log(json_encode($response));
    }

    if (is_file(xuisingle_cookie_path())) {
        @unlink(xuisingle_cookie_path());
    }

    return $response;
}
function addClient($namepanel, $usernameac, $Expire, $Total, $Uuid, $Flow, $subid, $inboundid, $name_product, $note = "")
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
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
    $response = $req->post($configpanel);
    @unlink(xuisingle_cookie_path());
    return $response;
}
function updateClient($namepanel, $uuid, array $config)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
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
    $response = $req->post($configpanel);
    @unlink(xuisingle_cookie_path());
    return $response;
}
function ResetUserDataUsagex_uisin($usernamepanel, $namepanel)
{
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
    $response = $req->post(array());
    @unlink(xuisingle_cookie_path());
    return $response;
}
function removeClient($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    login($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClientByEmail/" . $username;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie(xuisingle_cookie_path());
    $response = $req->post(array());
    @unlink(xuisingle_cookie_path());
    return $response;
}
