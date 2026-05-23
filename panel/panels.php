<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

if (extension_loaded('zlib') && empty(ini_get('zlib.output_compression'))) {
    @ini_set('zlib.output_compression', '1');
}

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}


$PANEL_TYPES = [
    'marzban'        => 'مرزبان (Marzban)',
    'pasargard'      => '🎛 پاسارگارد',
    'marzneshin'     => 'مرزنشین (Marzneshin)',
    'hiddify'        => 'هیدیفای (Hiddify)',
    'x-ui_single'    => 'ثنایی تک پورت (Sanaei single)',
    'alireza_single' => 'علیرضا تک پورت (Alireza single)',
    'Manualsale'     => 'فروش دستی (Manual sale)',
    'guard'          => 'Guard / GuardCore',
    'WGDashboard'    => 'WGDashboard (WireGuard)',
    's_ui'           => 's_ui',
    'ibsng'          => 'IBSNG',
    'mikrotik'       => 'میکروتیک (MikroTik)',
];

$flash = ['ok' => '', 'err' => ''];


$PANEL_COLS = [];
try {
    $r = $pdo->query("SHOW COLUMNS FROM marzban_panel");
    if ($r) {
        foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $PANEL_COLS[strtolower((string)$row['Field'])] = true;
        }
    }
} catch (\Throwable $e) {
    error_log('[panel/panels] could not read marzban_panel columns: ' . $e->getMessage());
}
$has_col = function (string $c) use (&$PANEL_COLS): bool {
    return isset($PANEL_COLS[strtolower($c)]);
};


if (!function_exists('faoxima_panel_authlog')) {
    function faoxima_panel_authlog(string $stage, array $data = []): void {
        static $path = null;
        static $opened = false;
        if ($path === null) {
            $path = __DIR__ . '/panel-auth.log';
        }
        try {
            $line = sprintf(
                "[%s] %s %s\n",
                date('Y-m-d H:i:s'),
                $stage,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $ok = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);


            if ($ok !== false && !$opened) {
                @chmod($path, 0600);
                $opened = true;
            }
        } catch (\Throwable $e) {

        }
    }
}


if (!function_exists('faoxima_panel_loghost')) {
    function faoxima_panel_loghost(string $url): string {
        $p = @parse_url($url);
        if (!is_array($p) || empty($p['host'])) return '(invalid-url)';
        $out = (isset($p['scheme']) ? $p['scheme'] . '://' : '') . $p['host'];
        if (!empty($p['port'])) $out .= ':' . $p['port'];


        return $out;
    }
}
if (!function_exists('faoxima_panel_logfp')) {
    function faoxima_panel_logfp(string $s): string {
        if ($s === '') return 'empty';
        return 'len=' . strlen($s) . ',sha=' . substr(sha1($s), 0, 8);
    }
}


function faoxima_test_panel_url_reachable(string $url): array {
    if (!function_exists('curl_init')) return ['ok' => true, 'code' => 0, 'error' => null];
    $ch = curl_init();
    if (!$ch) return ['ok' => false, 'code' => 0, 'error' => 'cURL init ناموفق'];
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'FaoximaPanelWeb/1.0',
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        $msg = $err;
        if (stripos($err, 'Could not resolve host') !== false)      $msg = 'هاست قابل دسترس نیست (DNS)';
        elseif (stripos($err, 'Connection refused') !== false)      $msg = 'اتصال refuse شد';
        elseif (stripos($err, 'timed out') !== false)               $msg = 'تایم‌اوت — سرور پاسخ نداد';
        elseif (stripos($err, 'SSL') !== false)                     $msg = 'خطای SSL: ' . $err;
        return ['ok' => false, 'code' => $code, 'error' => $msg];
    }
    if ($code === 0 || $code >= 500) {
        return ['ok' => false, 'code' => $code, 'error' => 'سرور پاسخ نمی‌دهد (HTTP ' . $code . ')'];
    }
    return ['ok' => true, 'code' => $code, 'error' => null];
}


function faoxima_http_post(string $url, string $body, array $headers = []): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'cURL در PHP نیست', 'code' => 0, 'body' => ''];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'FaoximaPanelWeb/1.0',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_COOKIEJAR      => '',
        CURLOPT_COOKIEFILE     => '',
        CURLOPT_HEADER         => false,
    ]);
    $rawHeaders = '';
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$rawHeaders) {
        $rawHeaders .= $header; return strlen($header);
    });
    $respBody = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) return ['ok' => false, 'error' => $err, 'code' => 0, 'body' => '', 'cookies' => []];
    
    $cookies = [];
    foreach (explode("
", $rawHeaders) as $line) {
        if (stripos($line, 'set-cookie:') === 0) {
            $cookies[] = trim(substr($line, strlen('set-cookie:')));
        }
    }
    return ['ok' => true, 'error' => '', 'code' => $code, 'body' => (string)$respBody, 'cookies' => $cookies];
}

function faoxima_http_get(string $url, array $headers = []): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'cURL در PHP نیست', 'code' => 0, 'body' => ''];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'FaoximaPanelWeb/1.0',
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $respBody = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) return ['ok' => false, 'error' => $err, 'code' => 0, 'body' => ''];
    return ['ok' => true, 'error' => '', 'code' => $code, 'body' => (string)$respBody];
}


function faoxima_test_panel_auth(string $url, string $username, string $password, string $apiKey, string $type): array {

    
    if ($type === 'Manualsale') {
        return ['ok' => true, 'verified' => true, 'message' => 'پنل فروش دستی — نیازی به اعتبارسنجی ندارد'];
    }
    $url = rtrim($url, '/');

    
    if (in_array($type, ['x-ui_single', 'alireza_single', 's_ui'], true)) {
        if ($username === '' || $password === '') {
            return ['ok' => true, 'verified' => false, 'message' => 'یوزرنیم یا پسورد خالی است'];
        }
        $r = faoxima_http_post(
            $url . '/login',
            http_build_query(['username' => $username, 'password' => $password]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        if (!$r['ok']) {
            return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $r['error']];
        }
        $body = trim((string)$r['body']);
        $j    = $body !== '' ? json_decode($body, true) : null;
        
        if (is_array($j) && array_key_exists('success', $j)) {
            $isOk = filter_var($j['success'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isOk === true) {
                return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
            }
            
            if ($r['code'] === 429) {
                return ['ok' => true, 'verified' => false, 'message' => '⏱ سرور rate-limit کرده (429) — چند ثانیه صبر کنید و دوباره تست کنید'];
            }
            $msg = !empty($j['msg']) ? (string)$j['msg'] : 'success=false';
            return ['ok' => true, 'verified' => false, 'message' => '❌ یوزرنیم یا پسورد نادرست (' . $msg . ')'];
        }
        
        if ($body !== '' && $r['code'] > 0 && $r['code'] < 500) {
            return ['ok' => true, 'verified' => true, 'message' => 'سرور پاسخ داد (پاسخ غیر-JSON — ورود احتمالاً موفق)'];
        }
        return ['ok' => false, 'verified' => false, 'message' => 'سرور پاسخ مناسبی نداد (HTTP ' . $r['code'] . ')'];
    }

    
    if (in_array($type, ['marzban', 'pasargard', 'marzneshin'], true)) {
        if ($username === '' || $password === '') {
            return ['ok' => true, 'verified' => false, 'message' => 'یوزرنیم یا پسورد خالی است'];
        }
        $r = faoxima_http_post(
            $url . '/api/admin/token',
            http_build_query(['username' => $username, 'password' => $password, 'grant_type' => 'password']),
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
        );
        if (!$r['ok']) {
            return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $r['error']];
        }
        $j = json_decode($r['body'], true);
        
        if (is_array($j) && isset($j['access_token']) && is_string($j['access_token']) && $j['access_token'] !== '') {
            return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
        }
        
        if ($r['code'] === 429) {
            return ['ok' => true, 'verified' => false, 'message' => '⏱ سرور rate-limit کرده (429) — چند ثانیه صبر کنید و دوباره تست کنید'];
        }
        
        if ($r['code'] === 401 || $r['code'] === 403) {
            $detail = '';
            if (is_array($j) && !empty($j['detail'])) $detail = ' · ' . (is_string($j['detail']) ? $j['detail'] : 'auth refused');
            return ['ok' => true, 'verified' => false, 'message' => '❌ یوزرنیم یا پسورد نادرست (HTTP ' . $r['code'] . $detail . ')'];
        }
        $detail = 'HTTP ' . $r['code'];
        if (is_array($j) && !empty($j['detail'])) $detail .= ' · ' . (is_string($j['detail']) ? $j['detail'] : 'رد شد');
        return ['ok' => true, 'verified' => false, 'message' => '❌ ورود ناموفق (' . $detail . ')'];
    }

    
    if ($type === 'hiddify') {
        $key = $apiKey !== '' ? $apiKey : $password;
        if ($key === '') return ['ok' => true, 'verified' => false, 'message' => 'API key خالی است'];
        $endpoint = $url . '/' . rawurlencode($key) . '/api/v2/admin/me/';
        $r = faoxima_http_get($endpoint);
        if (!$r['ok']) return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $r['error']];
        if ($r['code'] >= 200 && $r['code'] < 300) {
            return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
        }
        if ($r['code'] === 429) {
            return ['ok' => true, 'verified' => false, 'message' => '⏱ سرور rate-limit کرده (429) — چند ثانیه صبر کنید و دوباره تست کنید'];
        }
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['ok' => true, 'verified' => false, 'message' => '❌ API key نادرست (HTTP ' . $r['code'] . ')'];
        }
        return ['ok' => true, 'verified' => false, 'message' => '❌ ورود ناموفق (HTTP ' . $r['code'] . ')'];
    }

    
    if ($type === 'guard') {
        $key = $apiKey !== '' ? $apiKey : $password;
        if ($key === '') return ['ok' => true, 'verified' => false, 'message' => 'API key خالی است'];
        $r = faoxima_http_post(
            $url . '/api/v1/auth/login',
            json_encode(['username' => $username !== '' ? $username : 'admin', 'password' => $key], JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Accept: application/json']
        );
        if (!$r['ok']) return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $r['error']];
        $j = json_decode($r['body'], true);
        $haveToken = is_array($j) && (
            (isset($j['access_token']) && $j['access_token'] !== '') ||
            (isset($j['token'])        && $j['token']        !== '')
        );
        if ($haveToken) return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
        if ($r['code'] === 429) {
            return ['ok' => true, 'verified' => false, 'message' => '⏱ سرور rate-limit کرده (429) — چند ثانیه صبر کنید و دوباره تست کنید'];
        }
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['ok' => true, 'verified' => false, 'message' => '❌ API key نادرست (HTTP ' . $r['code'] . ')'];
        }
        return ['ok' => true, 'verified' => false, 'message' => '❌ احراز هویت گارد ناموفق (HTTP ' . $r['code'] . ')'];
    }

    
    if ($type === 'WGDashboard') {
        if ($username === '' || $password === '') {
            return ['ok' => true, 'verified' => false, 'message' => 'یوزرنیم یا پسورد خالی است'];
        }
        $r = faoxima_http_post(
            $url . '/api/authenticate',
            json_encode(['username' => $username, 'password' => $password], JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Accept: application/json']
        );
        if (!$r['ok']) return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $r['error']];
        $j = json_decode($r['body'], true);
        
        if (is_array($j) && !empty($j['token'])) {
            return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
        }
        
        if (is_array($j) && isset($j['status']) && filter_var($j['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
            return ['ok' => true, 'verified' => true, 'message' => 'ورود موفق ✅'];
        }
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['ok' => true, 'verified' => false, 'message' => '❌ یوزرنیم یا پسورد WGDashboard نادرست'];
        }
        if ($r['code'] === 429) {
            return ['ok' => true, 'verified' => false, 'message' => '⏱ سرور rate-limit کرده (429) — چند ثانیه صبر کنید و دوباره تست کنید'];
        }
        
        if ($r['code'] > 0 && $r['code'] < 500) {
            return ['ok' => true, 'verified' => false, 'message' => '❌ یوزرنیم یا پسورد WGDashboard نادرست (HTTP ' . $r['code'] . ')'];
        }
        return ['ok' => false, 'verified' => false, 'message' => 'اتصال به WGDashboard ناموفق (HTTP ' . $r['code'] . ')'];
    }

    
    
    if (in_array($type, ['ibsng', 'mikrotik'], true)) {
        $reach = faoxima_test_panel_url_reachable($url);
        if ($reach['ok']) {
            return ['ok' => true, 'verified' => true, 'message' => 'سرور قابل دسترس است ⚠️ (اعتبارسنجی کامل برای این نوع پنل از وب پشتیبانی نمی‌شود — در ربات بررسی کنید)'];
        }
        return ['ok' => false, 'verified' => false, 'message' => 'اتصال ناموفق: ' . $reach['error']];
    }

    
    $reach = faoxima_test_panel_url_reachable($url);
    if (!$reach['ok']) {
        return ['ok' => false, 'verified' => false, 'message' => $reach['error']];
    }
    return [
        'ok'       => true,
        'verified' => false,
        'message'  => 'آدرس قابل دسترس بود ولی تست credentials برای این نوع پنل پشتیبانی نمی‌شود — لطفاً در ربات تست کنید',
    ];
}


if (isset($_GET['ajax']) && $_GET['ajax'] === 'test_auth') {
    header('Content-Type: application/json; charset=utf-8');
    $url      = trim((string)($_POST['url']      ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password =       (string)($_POST['password'] ?? '');
    $apiKey   = trim((string)($_POST['api_key']  ?? ''));
    $type     = trim((string)($_POST['type']     ?? 'marzban'));

    if ($type === 'Manualsale') {
        echo json_encode(['ok' => true, 'verified' => true, 'message' => '✅ پنل فروش دستی — نیازی به تست ندارد']);
        exit;
    }
    if ($url === '') {
        echo json_encode(['ok' => false, 'verified' => false, 'message' => '⚠️ آدرس URL خالی است']);
        exit;
    }
    if (!preg_match('~^https?://~i', $url)) {
        echo json_encode(['ok' => false, 'verified' => false, 'message' => '⚠️ آدرس باید با http:// یا https:// شروع شود']);
        exit;
    }

    
    if (($password === '' || $apiKey === '') && !empty($_POST['panel_id'])) {
        $pid = (int)$_POST['panel_id'];
        if ($pid > 0) {
            try {
                $cur = $pdo->prepare("SELECT password_panel, api_key FROM marzban_panel WHERE id = :id LIMIT 1");
                $cur->execute([':id' => $pid]);
                $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($password === '') $password = (string)($row['password_panel'] ?? '');
                if ($apiKey   === '') $apiKey   = (string)($row['api_key']        ?? '');
            } catch (\Throwable $e) {}
        }
    }

    $result = faoxima_test_panel_auth($url, $username, $password, $apiKey, $type);
    echo json_encode($result);
    exit;
}


function faoxima_validate_inbound_id(string $panelUrl, string $username, string $password, string $type, string $inboundId): array
{
    $url  = rtrim($panelUrl, '/');
    $id   = trim($inboundId);

    if ($id === '') {
        return ['ok' => false, 'message' => '⚠️ شناسه اینباند خالی است'];
    }

    
    if ($type === 'WGDashboard') {
        
        $loginResp = faoxima_http_post(
            $url . '/api/authenticate',
            json_encode(['username' => $username, 'password' => $password], JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Accept: application/json']
        );
        if (!$loginResp['ok']) return ['ok' => false, 'message' => 'اتصال به WGDashboard ناموفق: ' . $loginResp['error']];
        $j = json_decode($loginResp['body'], true);
        $token = is_array($j) ? ($j['token'] ?? '') : '';
        if ($token === '') return ['ok' => false, 'message' => '❌ ورود به WGDashboard ناموفق — اعتبارها را بررسی کنید'];

        
        $listResp = faoxima_http_get($url . '/api/getAllPeersData', ['Authorization: Bearer ' . $token]);
        if (!$listResp['ok']) return ['ok' => false, 'message' => 'دریافت لیست کانفیگ‌های WGDashboard ناموفق'];
        $configs = json_decode($listResp['body'], true);
        if (!is_array($configs)) return ['ok' => false, 'message' => 'پاسخ WGDashboard قابل تفسیر نیست'];
        
        $names = [];
        foreach ($configs as $key => $val) {
            $names[] = is_string($key) ? $key : '';
            if (is_array($val) && isset($val['name'])) $names[] = (string)$val['name'];
            if (is_array($val) && isset($val['configuration_name'])) $names[] = (string)$val['configuration_name'];
        }
        if (in_array($id, $names, true)) {
            return ['ok' => true, 'message' => "✅ کانفیگ «{$id}» در WGDashboard یافت شد"];
        }
        return ['ok' => false, 'message' => "❌ کانفیگ «{$id}» در WGDashboard یافت نشد — لطفاً نام صحیح کانفیگ را وارد کنید"];
    }

    
    if (!ctype_digit($id)) {
        return ['ok' => false, 'message' => '⚠️ شناسه اینباند باید یک عدد صحیح باشد (مثلاً: 1 یا 3)'];
    }
    $numId = (int)$id;

    
    $loginResp = faoxima_http_post(
        $url . '/login',
        http_build_query(['username' => $username, 'password' => $password]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    if (!$loginResp['ok']) return ['ok' => false, 'message' => 'اتصال به پنل ناموفق: ' . $loginResp['error']];
    $lj = json_decode($loginResp['body'], true);
    if (!is_array($lj) || empty($lj['success'])) {
        return ['ok' => false, 'message' => '❌ ورود به پنل ناموفق — اعتبارها را بررسی کنید'];
    }
    
    
    
    $cookieMap = [];
    foreach ((array)($loginResp['cookies'] ?? []) as $ck) {
        $nameValue = trim(explode(';', $ck)[0]);
        if ($nameValue === '') continue;
        $eq = strpos($nameValue, '=');
        if ($eq === false) continue;
        $name  = trim(substr($nameValue, 0, $eq));
        $value = substr($nameValue, $eq + 1);
        if ($name === '') continue;
        $cookieMap[$name] = $value; 
    }
    $cookieParts = [];
    foreach ($cookieMap as $n => $v) $cookieParts[] = $n . '=' . $v;
    $cookieHeader = implode('; ', $cookieParts);

    
    
    
    
    
    $attempts = [
        ['method' => 'GET',  'path' => '/panel/api/inbounds/list'],
        ['method' => 'POST', 'path' => '/xui/inbound/list'],
        ['method' => 'POST', 'path' => '/panel/inbound/list'],
    ];
    $listResp = null;
    $ldata    = null;
    $baseHdrs = $cookieHeader !== ''
        ? ['Cookie: ' . $cookieHeader, 'Accept: application/json']
        : ['Accept: application/json'];
    foreach ($attempts as $ep) {
        if ($ep['method'] === 'GET') {
            $listResp = faoxima_http_get($url . $ep['path'], $baseHdrs);
        } else {
            $listResp = faoxima_http_post(
                $url . $ep['path'],
                '',
                array_merge($baseHdrs, ['Content-Type: application/x-www-form-urlencoded'])
            );
        }
        if (!$listResp['ok']) continue;
        $parsed = json_decode((string)$listResp['body'], true);
        if (is_array($parsed)) { $ldata = $parsed; break; }
    }
    if ($listResp === null || !$listResp['ok']) {
        return ['ok' => false, 'message' => 'دریافت لیست اینباندها ناموفق: ' . ($listResp['error'] ?? 'unknown')];
    }

    
    if (!is_array($ldata)) {
        $preview = mb_substr(strip_tags((string)$listResp['body']), 0, 120);
        return ['ok' => false, 'message' => '❌ پاسخ معتبر از پنل دریافت نشد (مسیر یا session) — پاسخ: ' . $preview];
    }
    if (!isset($ldata['obj']) || !is_array($ldata['obj'])) {
        
        if (isset($ldata['success']) && $ldata['success'] === true) {
            return ['ok' => false, 'message' => '⚠️ پنل هیچ اینباندی ندارد — ابتدا اینباند بسازید'];
        }
        $msg = isset($ldata['msg']) ? (string)$ldata['msg'] : 'پاسخ نامعتبر';
        return ['ok' => false, 'message' => '❌ لیست اینباندها خالی یا نامعتبر: ' . $msg];
    }

    foreach ($ldata['obj'] as $inbound) {
        if (isset($inbound['id']) && (int)$inbound['id'] === $numId) {
            $tag      = $inbound['remark'] ?? $inbound['tag'] ?? '';
            $protocol = $inbound['protocol'] ?? '';
            return ['ok' => true, 'message' => "✅ اینباند #{$numId} یافت شد" . ($tag !== '' ? " — «{$tag}»" : '') . ($protocol !== '' ? " ({$protocol})" : '')];
        }
    }

    return ['ok' => false, 'message' => "❌ اینباند #{$numId} در پنل یافت نشد — لطفاً شناسهٔ صحیح اینباند را وارد کنید"];
}


function faoxima_validate_sublink(string $linksubx, string $type): array
{
    $url = trim($linksubx);
    if ($url === '') return ['ok' => false, 'message' => '⚠️ دامنه لینک ساب خالی است'];

    
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'message' => '⚠️ فرمت آدرس نامعتبر است'];
    }

    
    $resp = faoxima_http_get($url);

    if (!$resp['ok']) {
        return ['ok' => false, 'message' => 'اتصال به آدرس ساب ناموفق: ' . ($resp['error'] ?? 'خطای ناشناخته')];
    }
    if ($resp['code'] < 200 || $resp['code'] >= 400) {
        return ['ok' => false, 'message' => "❌ سرور پاسخ HTTP {$resp['code']} داد — آدرس را بررسی کنید"];
    }

    $body = trim((string)($resp['body'] ?? ''));

    
    $decoded = '';
    if (preg_match('/^[A-Za-z0-9+\/\r\n]+=*$/', str_replace(["\r", "\n"], '', $body))) {
        $decoded = @base64_decode($body, true);
    }
    $checkBody = $decoded !== '' && $decoded !== false ? $decoded : $body;

    $protocols = ['vmess://', 'vless://', 'trojan://', 'ss://', 'hysteria2://', 'hy2://'];
    foreach ($protocols as $proto) {
        if (stripos($checkBody, $proto) !== false) {
            
            $parsed  = parse_url($url);
            $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            return ['ok' => true, 'message' => "✅ لینک ساب معتبر است — پروتکل: " . rtrim($proto, '://') . " — دامنه: {$baseUrl}"];
        }
    }

    
    if ($resp['code'] >= 200 && $resp['code'] < 300) {
        $parsed  = parse_url($url);
        $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        return ['ok' => true, 'message' => "✅ آدرس در دسترس است (HTTP {$resp['code']}) — دامنه: {$baseUrl}"];
    }

    return ['ok' => false, 'message' => "❌ محتوای پاسخ حاوی کانفیگ VPN نیست — آدرس را بررسی کنید"];
}


if (isset($_GET['ajax']) && $_GET['ajax'] === 'validate_inbound') {
    header('Content-Type: application/json; charset=utf-8');
    $panelUrl  = trim((string)($_POST['url']        ?? ''));
    $username  = trim((string)($_POST['username']   ?? ''));
    $password  =       (string)($_POST['password']   ?? '');
    $type      = trim((string)($_POST['type']       ?? ''));
    $inboundId = trim((string)($_POST['inbound_id'] ?? ''));

    
    if ($password === '' && !empty($_POST['panel_id'])) {
        $pid = (int)$_POST['panel_id'];
        if ($pid > 0) {
            try {
                $cur = $pdo->prepare("SELECT password_panel FROM marzban_panel WHERE id = :id LIMIT 1");
                $cur->execute([':id' => $pid]);
                $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
                $password = (string)($row['password_panel'] ?? '');
            } catch (\Throwable $e) {}
        }
    }
    echo json_encode(faoxima_validate_inbound_id($panelUrl, $username, $password, $type, $inboundId));
    exit;
}


if (isset($_GET['ajax']) && $_GET['ajax'] === 'validate_sublink') {
    header('Content-Type: application/json; charset=utf-8');
    $linksubx = trim((string)($_POST['linksubx'] ?? ''));
    $type     = trim((string)($_POST['type']     ?? ''));
    echo json_encode(faoxima_validate_sublink($linksubx, $type));
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'toggle') {
    header('Content-Type: application/json; charset=utf-8');
    $id    = (int)($_POST['id']    ?? 0);
    $field = (string)($_POST['field'] ?? '');
    $on    = !empty($_POST['on']);


    $ALLOWED = [
        'emergency_panel_status' => ['on_emergency_panel', 'off_emergency_panel'],
        'national_net_status'    => ['on_national_net',    'off_national_net'],

        'inboundstatus'          => ['offinbounddisable',  'oninbounddisable'],

        'TestAccount'            => ['ONTestAccount',      'OFFTestAccount'],

        
        'sublink'                => ['onsublink',          'offsublink'],
        'config'                 => ['onconfig',           'offconfig'],
        'conecton'               => ['onconecton',         'offconecton'],
    ];
    if (!isset($ALLOWED[$field]) || $id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'bad_request']);
        exit;
    }
    if (!$has_col($field)) {


        echo json_encode(['ok' => true, 'value' => null, 'skipped' => 'column_missing']);
        exit;
    }


    try {
        $exists = $pdo->prepare("SELECT 1 FROM marzban_panel WHERE id = :id LIMIT 1");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => 'panel_not_found']);
            exit;
        }
    } catch (\Throwable $e) {  }

    [$onValue, $offValue] = $ALLOWED[$field];
    $newValue = $on ? $onValue : $offValue;
    try {
        $stmt = $pdo->prepare("UPDATE marzban_panel SET `" . $field . "` = :v WHERE id = :id");
        $stmt->execute([':v' => $newValue, ':id' => $id]);


        $autoNote = null;
        if ($on && in_array($field, ['national_net_status', 'emergency_panel_status'], true)) {
            $sourceCol = $field === 'national_net_status' ? 'stock_source_panel' : 'emergency_source_panel';
            if ($has_col($sourceCol)) {
                try {
                    $cur = $pdo->prepare("SELECT code_panel, `" . $sourceCol . "` AS src FROM marzban_panel WHERE id = :id LIMIT 1");
                    $cur->execute([':id' => $id]);
                    $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($row['code_panel']) && empty($row['src'])) {
                        $pdo->prepare("UPDATE marzban_panel SET `" . $sourceCol . "` = :s WHERE id = :id")
                            ->execute([':s' => (string)$row['code_panel'], ':id' => $id]);
                        $autoNote = 'source_self_assigned';
                    }
                } catch (\Throwable $e) {  }
            }
        }

        echo json_encode(['ok' => true, 'value' => $newValue, 'auto' => $autoNote]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $namePanel = trim((string)($_POST['name_panel']     ?? ''));
        $urlPanel  = trim((string)($_POST['url_panel']      ?? ''));
        $userPanel = trim((string)($_POST['username_panel'] ?? ''));
        $passPanel = (string)($_POST['password_panel']      ?? '');
        $apiKey    = trim((string)($_POST['api_key']        ?? ''));
        $type      = trim((string)($_POST['type']           ?? 'marzban'));
        $agent     = (string)($_POST['agent']               ?? 'all');


        faoxima_panel_authlog('ADD_START', [
            'name'         => $namePanel,
            'type'         => $type,
            'agent'        => $agent,
            'url_host'     => faoxima_panel_loghost($urlPanel),
            'username'     => $userPanel,
            'has_password' => $passPanel !== '',
            'has_api_key'  => $apiKey !== '',
            'post_keys'    => array_keys($_POST),
        ]);


        $errors = [];


        if (!isset($PANEL_TYPES[$type])) {
            $errors[] = 'نوع پنل نامعتبر است.';
            $type = 'marzban';
        }


        if (!in_array($agent, ['all', 'f', 'n', 'n2'], true)) {
            $agent = 'all';
        }


        if ($namePanel === '') {
            $errors[] = 'نام پنل نمی‌تواند خالی باشد.';
        } elseif (mb_strlen($namePanel, 'UTF-8') > 200) {
            $errors[] = 'نام پنل بیش از حد طولانی است (حداکثر ۲۰۰ کاراکتر).';
        }


        if ($type !== 'Manualsale') {

            if ($urlPanel === '') {
                $errors[] = 'آدرس URL پنل اجباری است.';
            } elseif (!preg_match('~^https?://~i', $urlPanel)) {
                $errors[] = 'آدرس URL باید با http:// یا https:// شروع شود.';
            } elseif (!filter_var($urlPanel, FILTER_VALIDATE_URL)) {
                $errors[] = 'فرمت آدرس URL نامعتبر است.';
            } elseif (preg_match('~://[^/?#@]*:(?:/|$|\?|\#)~', $urlPanel) && !parse_url($urlPanel, PHP_URL_PORT)) {
                $errors[] = 'آدرس URL پورت ناقص دارد — مثال درست: https://1.2.3.4:2053';
            } elseif (mb_strlen($urlPanel) > 2000) {
                $errors[] = 'آدرس URL بیش از حد طولانی است.';
            }


            if ($userPanel === '' && $passPanel === '' && $apiKey === '') {
                $errors[] = 'حداقل باید یوزرنیم و پسورد یا API key وارد شود.';
            } elseif ($userPanel === '' && $apiKey === '') {
                $errors[] = 'یوزرنیم خالی است — اگر فقط با API key احراز هویت می‌کنید، آن را وارد کنید.';
            }
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("\n• ", $errors);
            faoxima_panel_authlog('ADD_VALIDATION_FAIL', ['name' => $namePanel, 'errors' => $errors]);
        } else {


            $initialStatus = 'inactive';
            $testNote      = '';

            if ($type !== 'Manualsale') {
                $auth = faoxima_test_panel_auth($urlPanel, $userPanel, $passPanel, $apiKey, $type);


                error_log(sprintf(
                    '[panel/panels] ADD auth test: type=%s url=%s user=%s -> verified=%s ok=%s msg=%s',
                    $type,
                    $urlPanel,
                    $userPanel,
                    $auth['verified'] ? 'YES' : 'NO',
                    $auth['ok']       ? 'YES' : 'NO',
                    $auth['message']  ?? ''
                ));
                faoxima_panel_authlog('ADD_AUTH_TEST', [
                    'type'     => $type,
                    'url_host' => faoxima_panel_loghost($urlPanel),
                    'username' => $userPanel,
                    'verified' => (bool)$auth['verified'],
                    'ok'       => (bool)$auth['ok'],
                    'message'  => (string)($auth['message'] ?? ''),
                ]);

                if ($auth['verified'] === true) {
                    $initialStatus = 'active';
                    $testNote = ' ✅ اعتبار تایید شد — پنل فعال شد.';
                } else {


                    $rawMsg   = (string)($auth['message'] ?? 'نامشخص');
                    $friendly = $rawMsg;
                    $hint     = '';
                    if (stripos($rawMsg, 'Invalid username or password') !== false) {
                        $friendly = 'یوزرنیم یا پسورد نادرست (یا 2FA فعال است)';
                        $hint = ' — 💡 اگر مطمئنید اعتبارها درست‌اند، پنل احتمالاً پس از یک تلاش ناموفق قبلی چند دقیقه rate-limit کرده. ۱-۲ دقیقه صبر کنید و دوباره امتحان کنید.';
                    } elseif (stripos($rawMsg, 'two-factor') !== false || stripos($rawMsg, '2fa') !== false) {
                        $friendly = 'کد دومرحله‌ای (2FA) لازم است — از طریق وب قابل تست نیست';
                    } elseif (stripos($rawMsg, 'Could not resolve host') !== false || stripos($rawMsg, 'DNS') !== false) {
                        $friendly = 'هاست قابل دسترس نیست (DNS resolve نشد)';
                    } elseif (stripos($rawMsg, 'Connection refused') !== false) {
                        $friendly = 'اتصال refuse شد (پورت بسته است)';
                    } elseif (stripos($rawMsg, 'timed out') !== false || stripos($rawMsg, 'timeout') !== false) {
                        $friendly = 'تایم‌اوت — سرور پاسخ نداد';
                    } elseif (stripos($rawMsg, 'SSL') !== false) {
                        $friendly = 'خطای SSL در اتصال به پنل';
                    }
                    if (!empty($auth['ok'])) {
                        $testNote = ' ⚠️ پنل غیرفعال ثبت شد — ' . $friendly . $hint;
                    } else {
                        $testNote = ' ⚠️ پنل غیرفعال ثبت شد — اتصال ناموفق: ' . $friendly;
                    }
                }
            } else {


                $testNote = ' (پنل فروش دستی — برای فعال‌سازی، دستی ادیت کنید.)';
            }

            try {
                $codePanel = strtolower(preg_replace('/[^a-z0-9]/i', '', $namePanel) . '_' . substr(md5(uniqid('', true)), 0, 6));


                $stmt = $pdo->prepare(
                    "INSERT INTO marzban_panel
                     (code_panel, name_panel, status, url_panel, username_panel, password_panel, api_key, type, agent)
                     VALUES (:c, :n, :st, :u, :user, :pass, :api, :type, :agent)"
                );
                $stmt->execute([
                    ':c'    => $codePanel,
                    ':n'    => $namePanel,
                    ':st'   => $initialStatus,
                    ':u'    => $urlPanel,
                    ':user' => $userPanel,
                    ':pass' => $passPanel,
                    ':api'  => $apiKey,
                    ':type' => $type,
                    ':agent'=> $agent,
                ]);
                $statusLabel = $initialStatus === 'active' ? '🟢 «فعال»' : '🔴 «غیرفعال»';
                $flash['ok'] = 'پنل جدید با وضعیت ' . $statusLabel . ' ثبت شد.' . $testNote;
                faoxima_panel_authlog('ADD_DONE', [
                    'name'         => $namePanel,
                    'final_status' => $initialStatus,
                    'flash'        => $flash['ok'],
                ]);
            } catch (\Throwable $e) {
                $flash['err'] = 'خطا در افزودن پنل: ' . $e->getMessage();
                error_log('[panel/panels] add failed: ' . $e->getMessage());
                faoxima_panel_authlog('ADD_ERROR', ['name' => $namePanel, 'error' => $e->getMessage()]);
            }
        }
    }
    elseif ($action === 'edit') {
        $id        = (int)($_POST['id'] ?? 0);
        $namePanel = trim((string)($_POST['name_panel']     ?? ''));
        $urlPanel  = trim((string)($_POST['url_panel']      ?? ''));
        $userPanel = trim((string)($_POST['username_panel'] ?? ''));
        $passPanel = (string)($_POST['password_panel']      ?? '');
        $apiKey    = trim((string)($_POST['api_key']        ?? ''));
        $type      = trim((string)($_POST['type']           ?? 'marzban'));
        $agent     = (string)($_POST['agent']               ?? 'all');
        $status    = !empty($_POST['status']) ? 'active' : 'inactive';

        $valUsertest    = isset($_POST['val_usertest'])    ? trim((string)$_POST['val_usertest'])    : null;
        $timeUsertest   = isset($_POST['time_usertest'])   ? trim((string)$_POST['time_usertest'])   : null;
        $limitPanel     = isset($_POST['limit_panel'])     ? trim((string)$_POST['limit_panel'])     : null;
        $methodExtend   = isset($_POST['Methodextend'])    ? trim((string)$_POST['Methodextend'])    : null;
        $methodUsername = isset($_POST['MethodUsername'])  ? trim((string)$_POST['MethodUsername'])  : null;
        $priceChangeloc = isset($_POST['priceChangeloc'])  ? trim((string)$_POST['priceChangeloc'])  : null;
        $inboundId      = isset($_POST['inboundid'])       ? trim((string)$_POST['inboundid'])       : null;
        $linkSubx       = isset($_POST['linksubx'])        ? trim((string)$_POST['linksubx'])        : null;
        
        $secretCode     = isset($_POST['secret_code'])     ? trim((string)$_POST['secret_code'])     : null;
        $nameCustom     = isset($_POST['namecustom'])      ? trim((string)$_POST['namecustom'])      : null;


        faoxima_panel_authlog('EDIT_START', [
            'id'                => $id,
            'name'              => $namePanel,
            'type'              => $type,
            'agent'             => $agent,
            'status_posted_raw' => array_key_exists('status', $_POST) ? (string)$_POST['status'] : '(missing)',
            'status_computed'   => $status,
            'url_host'          => faoxima_panel_loghost($urlPanel),
            'username'          => $userPanel,
            'has_password'      => $passPanel !== '',
            'has_api_key'       => $apiKey !== '',
            'pw_fp'             => faoxima_panel_logfp($passPanel),
            'post_keys'         => array_keys($_POST),
        ]);

        if (!isset($PANEL_TYPES[$type])) $type = 'marzban';
        if (!in_array($agent, ['all', 'f', 'n', 'n2'], true)) $agent = 'all';


        $errors = [];
        if ($id <= 0) {
            $errors[] = 'شناسه پنل نامعتبر است.';
        }
        if ($namePanel === '') {
            $errors[] = 'نام پنل نمی‌تواند خالی باشد.';
        } elseif (mb_strlen($namePanel, 'UTF-8') > 200) {
            $errors[] = 'نام پنل بیش از حد طولانی است (حداکثر ۲۰۰ کاراکتر).';
        }
        if ($type !== 'Manualsale') {
            if ($urlPanel === '') {
                $errors[] = 'آدرس URL پنل اجباری است.';
            } elseif (!preg_match('~^https?://~i', $urlPanel)) {
                $errors[] = 'آدرس URL باید با http:// یا https:// شروع شود.';
            } elseif (!filter_var($urlPanel, FILTER_VALIDATE_URL)) {
                $errors[] = 'فرمت آدرس URL نامعتبر است.';
            } elseif (preg_match('~://[^/?#@]*:(?:/|$|\?|\#)~', $urlPanel) && !parse_url($urlPanel, PHP_URL_PORT)) {
                
                $errors[] = 'آدرس URL پورت ناقص دارد — مثال درست: https://1.2.3.4:2053 (بدون / بعد از :)';
            }
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("\n• ", $errors);
            faoxima_panel_authlog('EDIT_VALIDATION_FAIL', ['id' => $id, 'errors' => $errors]);
        } else {


            $testNote = '';
            $authFailed = false;


            $willRunAuth = ($type !== 'Manualsale');
            faoxima_panel_authlog('EDIT_BLOCK_DECISION', [
                'id'                 => $id,
                'status_posted'      => $status,
                'type'               => $type,
                'will_run_auth_test' => $willRunAuth,
                'mode'               => $willRunAuth ? 'auto_status_from_auth' : 'manual_status_no_auth',
                'reason_if_skipped'  => $willRunAuth ? null : 'type_is_manualsale',
            ]);

            if ($willRunAuth) {


                $skipTest = false;
                $skipNote = '';
                if ($passPanel === '' && $apiKey === '') {
                    try {
                        $prevStmt = $pdo->prepare("SELECT status, url_panel, username_panel FROM marzban_panel WHERE id = :id");
                        $prevStmt->execute([':id' => $id]);
                        $prev = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $prevWasActive = isset($prev['status']) && $prev['status'] === 'active';
                        $sameUrl       = isset($prev['url_panel'])      && (string)$prev['url_panel']      === $urlPanel;
                        $sameUser      = isset($prev['username_panel']) && (string)$prev['username_panel'] === $userPanel;
                        faoxima_panel_authlog('EDIT_SKIP_CHECK', [
                            'id'              => $id,
                            'pw_empty'        => true,
                            'key_empty'       => true,
                            'prev_status'     => (string)($prev['status'] ?? '(no-row)'),
                            'prev_was_active' => $prevWasActive,
                            'url_match'       => $sameUrl,
                            'user_match'      => $sameUser,
                            'will_skip'       => ($prevWasActive && $sameUrl && $sameUser),
                        ]);
                        
                        $prevUrlValid = !(preg_match('~://[^/?#@]*:(?:/|$|\?|\#)~', (string)($prev['url_panel'] ?? '')) && !parse_url((string)($prev['url_panel'] ?? ''), PHP_URL_PORT));
                        if ($prevWasActive && $sameUrl && $sameUser && $prevUrlValid) {
                            $skipTest = true;
                            $skipNote = ' ✅ تغییری در اعتبارها داده نشده — وضعیت قبلی (فعال) حفظ شد.';
                            error_log(sprintf(
                                '[panel/panels] EDIT auth test SKIPPED (no credential change): id=%d type=%s url=%s user=%s',
                                $id, $type, $urlPanel, $userPanel
                            ));
                        }
                    } catch (\Throwable $e) {
                        faoxima_panel_authlog('EDIT_SKIP_CHECK_ERROR', ['id' => $id, 'error' => $e->getMessage()]);

                    }
                } else {
                    faoxima_panel_authlog('EDIT_SKIP_CHECK', [
                        'id'           => $id,
                        'pw_empty'     => $passPanel === '',
                        'key_empty'    => $apiKey   === '',
                        'will_skip'    => false,
                        'reason'       => 'pw_or_key_provided_in_form',
                    ]);
                }

                if ($skipTest) {
                    $status = 'active';
                    $testNote = $skipNote;
                } else {


                $effectivePass = $passPanel;
                $effectiveKey  = $apiKey;
                if ($effectivePass === '' || $effectiveKey === '') {
                    try {
                        $curStmt = $pdo->prepare("SELECT password_panel, api_key FROM marzban_panel WHERE id = :id");
                        $curStmt->execute([':id' => $id]);
                        $cur = $curStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        if ($effectivePass === '') $effectivePass = (string)($cur['password_panel'] ?? '');
                        if ($effectiveKey  === '') $effectiveKey  = (string)($cur['api_key']        ?? '');
                    } catch (\Throwable $e) {  }
                }

                faoxima_panel_authlog('EDIT_AUTH_RUNNING', [
                    'id'             => $id,
                    'type'           => $type,
                    'url_host'       => faoxima_panel_loghost($urlPanel),
                    'username'       => $userPanel,
                    'effective_pw_fp'  => faoxima_panel_logfp($effectivePass),
                    'effective_key_fp' => faoxima_panel_logfp($effectiveKey),
                    'pw_source'        => $passPanel !== '' ? 'form' : 'db',
                    'key_source'       => $apiKey   !== '' ? 'form' : 'db',
                ]);

                $auth = faoxima_test_panel_auth($urlPanel, $userPanel, $effectivePass, $effectiveKey, $type);


                error_log(sprintf(
                    '[panel/panels] EDIT auth test: id=%d type=%s url=%s user=%s -> verified=%s ok=%s msg=%s',
                    $id,
                    $type,
                    $urlPanel,
                    $userPanel,
                    $auth['verified'] ? 'YES' : 'NO',
                    $auth['ok']       ? 'YES' : 'NO',
                    $auth['message']  ?? ''
                ));
                faoxima_panel_authlog('EDIT_AUTH_TEST', [
                    'id'       => $id,
                    'type'     => $type,
                    'url_host' => faoxima_panel_loghost($urlPanel),
                    'username' => $userPanel,
                    'verified' => (bool)$auth['verified'],
                    'ok'       => (bool)$auth['ok'],
                    'message'  => (string)($auth['message'] ?? ''),
                ]);

                if ($auth['verified'] === true) {
                    $status = 'active';
                    $testNote = ' ✅ اعتبار تایید شد — پنل فعال شد.';
                } else {


                    $status = 'inactive';
                    $authFailed = true;
                    $rawMsg = (string)($auth['message'] ?? 'نامشخص');

                    $friendly = $rawMsg;
                    $hint = '';
                    if (stripos($rawMsg, 'Invalid username or password') !== false) {
                        $friendly = 'یوزرنیم یا پسورد نادرست (یا 2FA فعال است)';
                        $hint = ' — 💡 اگر مطمئنید اعتبارها درست‌اند، پنل احتمالاً پس از یک تلاش ناموفق قبلی چند دقیقه rate-limit کرده. ۱-۲ دقیقه صبر کنید و دوباره ذخیره را بزنید.';
                    } elseif (stripos($rawMsg, 'two-factor') !== false || stripos($rawMsg, '2fa') !== false) {
                        $friendly = 'کد دومرحله‌ای (2FA) لازم است — از طریق وب قابل تست نیست';
                    } elseif (stripos($rawMsg, 'Could not resolve host') !== false || stripos($rawMsg, 'DNS') !== false) {
                        $friendly = 'هاست قابل دسترس نیست (DNS resolve نشد)';
                    } elseif (stripos($rawMsg, 'Connection refused') !== false) {
                        $friendly = 'اتصال refuse شد (پورت بسته است)';
                    } elseif (stripos($rawMsg, 'timed out') !== false || stripos($rawMsg, 'timeout') !== false) {
                        $friendly = 'تایم‌اوت — سرور پاسخ نداد';
                    } elseif (stripos($rawMsg, 'SSL') !== false) {
                        $friendly = 'خطای SSL در اتصال به پنل';
                    }
                    if (empty($auth['ok'])) {
                        $testNote = ' ⚠️ پنل غیرفعال شد چون سرور پاسخ نداد: ' . $friendly;
                    } else {
                        $testNote = ' ⚠️ پنل غیرفعال شد — ' . $friendly . $hint;
                    }

                    if ($passPanel !== '' || $apiKey !== '') {
                        $testNote .= ' (پسورد/کلید جدید ذخیره نشد — مقدار قبلی در دیتابیس حفظ شد.)';
                    }
                }
                }
            } else {


                $testNote = ' (پنل فروش دستی — وضعیت بر اساس انتخاب شما ذخیره شد.)';
            }

            try {
                $sets = ["name_panel = :n", "url_panel = :u", "username_panel = :user", "type = :type", "agent = :agent", "status = :status"];
                $params = [
                    ':n'      => $namePanel,
                    ':u'      => $urlPanel,
                    ':user'   => $userPanel,
                    ':type'   => $type,
                    ':agent'  => $agent,
                    ':status' => $status,
                    ':id'     => $id,
                ];


                if (!$authFailed) {
                    if ($passPanel !== '') { $sets[] = "password_panel = :pass"; $params[':pass'] = $passPanel; }
                    if ($apiKey    !== '') { $sets[] = "api_key = :api";          $params[':api']  = $apiKey;    }
                }
                if ($valUsertest    !== null && $valUsertest    !== '') { $sets[] = "val_usertest = :vu";    $params[':vu']  = $valUsertest; }
                if ($timeUsertest   !== null && $timeUsertest   !== '') { $sets[] = "time_usertest = :tu";   $params[':tu']  = $timeUsertest; }
                if ($limitPanel     !== null && $limitPanel     !== '') { $sets[] = "limit_panel = :lp";     $params[':lp']  = $limitPanel; }
                if ($methodExtend   !== null && $methodExtend   !== '') { $sets[] = "Methodextend = :me";    $params[':me']  = $methodExtend; }
                if ($methodUsername !== null && $methodUsername !== '') { $sets[] = "MethodUsername = :mu";  $params[':mu']  = $methodUsername; }
                if ($priceChangeloc !== null && $priceChangeloc !== '') { $sets[] = "priceChangeloc = :pc";  $params[':pc']  = $priceChangeloc; }
                
                if ($inboundId  !== null && $inboundId  !== '') { $sets[] = "inboundid = :iid";    $params[':iid']  = $inboundId; }
                if ($linkSubx   !== null && $linkSubx   !== '') { $sets[] = "linksubx = :lsx";     $params[':lsx']  = $linkSubx; }
                if ($secretCode !== null && $secretCode !== '') { $sets[] = "secret_code = :sc";   $params[':sc']   = $secretCode; }
                if ($nameCustom !== null && $nameCustom !== '') { $sets[] = "namecustom = :nc";    $params[':nc']   = $nameCustom; }

                $sql = "UPDATE marzban_panel SET " . implode(', ', $sets) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $flash['ok'] = 'پنل به‌روزرسانی شد.' . $testNote;
                faoxima_panel_authlog('EDIT_DONE', [
                    'id'                  => $id,
                    'final_status_in_sql' => $status,
                    'auth_failed'         => $authFailed,
                    'password_persisted'  => !$authFailed && $passPanel !== '',
                    'apikey_persisted'    => !$authFailed && $apiKey   !== '',
                    'columns_set'         => $sets,
                    'rows_affected'       => $stmt->rowCount(),
                    'flash'               => $flash['ok'],
                ]);
            } catch (\Throwable $e) {
                $flash['err'] = 'خطا در به‌روزرسانی: ' . $e->getMessage();
                error_log('[panel/panels] edit failed: ' . $e->getMessage());
                faoxima_panel_authlog('EDIT_ERROR', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash['err'] = 'شناسه پنل نامعتبر است.';
        } else {
            try {

                $check = $pdo->prepare("SELECT code_panel, name_panel FROM marzban_panel WHERE id = :id LIMIT 1");
                $check->execute([':id' => $id]);
                $panelRow = $check->fetch(PDO::FETCH_ASSOC);
                if (!$panelRow) {
                    $flash['err'] = 'پنلی با این شناسه پیدا نشد.';
                } else {


                    $orphanCount = 0;
                    try {
                        $oc = $pdo->prepare("SELECT COUNT(*) FROM service WHERE code_panel = :c");
                        $oc->execute([':c' => $panelRow['code_panel']]);
                        $orphanCount = (int)$oc->fetchColumn();
                    } catch (\Throwable $e) {  }

                    $pdo->prepare("DELETE FROM marzban_panel WHERE id = :id")->execute([':id' => $id]);
                    $flash['ok'] = 'پنل «' . $panelRow['name_panel'] . '» حذف شد.'
                        . ($orphanCount > 0 ? ' ⚠️ ' . $orphanCount . ' سرویس متصل به این پنل بدون پنل ماندند.' : '');
                }
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'set_emergency_source') {
        $id        = (int)($_POST['id'] ?? 0);
        $sourceCol = trim((string)($_POST['emergency_source_panel'] ?? ''));
        if ($id <= 0) {
            $flash['err'] = 'شناسه پنل نامعتبر است.';
        } elseif (!$has_col('emergency_source_panel')) {
            $flash['err'] = 'ستون emergency_source_panel در دیتابیس وجود ندارد.';
        } else {


            $sourceOk = true;
            if ($sourceCol !== '') {
                try {
                    $check = $pdo->prepare("SELECT 1 FROM marzban_panel WHERE code_panel = :c AND id != :id LIMIT 1");
                    $check->execute([':c' => $sourceCol, ':id' => $id]);
                    if (!$check->fetchColumn()) {
                        $sourceOk = false;
                        $flash['err'] = 'پنل مبدأ انتخاب‌شده وجود ندارد یا همان پنل فعلی است.';
                    }
                } catch (\Throwable $e) {  }
            }
            if ($sourceOk) {
                try {
                    $pdo->prepare("UPDATE marzban_panel SET emergency_source_panel = :s WHERE id = :id")
                        ->execute([':s' => $sourceCol, ':id' => $id]);
                    $flash['ok'] = $sourceCol === '' ? 'پنل اضطراری حذف شد.' : 'پنل اضطراری ثبت شد.';
                } catch (\Throwable $e) {
                    $flash['err'] = 'ثبت ناموفق: ' . $e->getMessage();
                }
            }
        }
    }
    elseif ($action === 'set_stock_source') {
        $id        = (int)($_POST['id'] ?? 0);
        $sourceCol = trim((string)($_POST['stock_source_panel'] ?? ''));
        if ($id <= 0) {
            $flash['err'] = 'شناسه پنل نامعتبر است.';
        } elseif (!$has_col('stock_source_panel')) {
            $flash['err'] = 'ستون stock_source_panel در دیتابیس وجود ندارد.';
        } else {
            $sourceOk = true;
            if ($sourceCol !== '') {
                try {
                    $check = $pdo->prepare("SELECT 1 FROM marzban_panel WHERE code_panel = :c AND id != :id LIMIT 1");
                    $check->execute([':c' => $sourceCol, ':id' => $id]);
                    if (!$check->fetchColumn()) {
                        $sourceOk = false;
                        $flash['err'] = 'پنل مبدأ نت ملی انتخاب‌شده وجود ندارد.';
                    }
                } catch (\Throwable $e) {  }
            }
            if ($sourceOk) {
                try {
                    $pdo->prepare("UPDATE marzban_panel SET stock_source_panel = :s WHERE id = :id")
                        ->execute([':s' => $sourceCol, ':id' => $id]);
                    $flash['ok'] = $sourceCol === '' ? 'پنل نت ملی حذف شد.' : 'پنل مبدأ نت ملی ثبت شد.';
                } catch (\Throwable $e) {
                    $flash['err'] = 'ثبت ناموفق: ' . $e->getMessage();
                }
            }
        }
    }
}


$panels = [];
try {
    $q = $pdo->prepare("SELECT * FROM marzban_panel ORDER BY id DESC");
    $q->execute();
    $panels = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $flash['err'] = $flash['err'] ?: ('بارگذاری ناموفق: ' . $e->getMessage());
}

function faoxima_mask_secret($v) {
    $v = (string)$v;
    $n = strlen($v);
    if ($n === 0) return '<span class="text-muted">—</span>';
    if ($n <= 4) return '<code>' . str_repeat('•', $n) . '</code>';
    return '<code>••••' . htmlspecialchars(substr($v, -4), ENT_QUOTES, 'UTF-8') . '</code>';
}
function faoxima_is_truthy_panel_flag($v, $trueValues) {
    return in_array((string)$v, $trueValues, true);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت پنل‌ها | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-extra.css">
    <script src="js/theme.js" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('server', 'svg-icon svg-lg'); ?>
                        مدیریت پنل‌ها
                    </div>
                    <div class="page-head__sub">پنل‌های Marzban / پاسارگارد / Marzneshin / Hiddify / X-UI / فروش دستی / Guard / WGDashboard / s_ui / IBSNG / MikroTik</div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    
                    <div class="view-toggle" role="group" aria-label="نمای نمایش">
                        <button type="button" class="view-toggle__btn active" data-view="cards" title="نمای کارتی (عمودی)">
                            <?php echo icon('grid', 'svg-icon svg-sm'); ?>
                        </button>
                        <button type="button" class="view-toggle__btn" data-view="table" title="نمای جدولی (افقی)">
                            <?php echo icon('list', 'svg-icon svg-sm'); ?>
                        </button>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('modal-add-panel')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن پنل جدید</span>
                    </button>
                </div>
            </div>

            <?php


            $renderFlash = static function (string $msg): string {
                return nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'), false);
            };
            ?>
            <?php if ($flash['ok']): ?>
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo $renderFlash($flash['ok']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert alert-danger">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo $renderFlash($flash['err']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($panels)): ?>
                <div class="card" style="text-align:center; padding: 40px 20px;">
                    <?php echo icon('server', 'svg-icon svg-2xl'); ?>
                    <div style="margin-top:16px; font-size:15px; color:var(--text-muted);">هیچ پنلی ثبت نشده.</div>
                    <button class="btn btn-primary" style="margin-top:14px;" onclick="openModal('modal-add-panel')">افزودن اولین پنل</button>
                </div>
            <?php else: ?>

                
                <div class="panel-cards view-pane" data-view="cards">
                <?php foreach ($panels as $p):
                    $status      = strtolower((string)($p['status'] ?? ''));
                    $isActive    = ($status === 'active' || $status === 'on' || $status === '1');
                    $type        = (string)($p['type'] ?? 'marzban');
                    $typeLabel   = $PANEL_TYPES[$type] ?? $type;
                    $emergencyOn = faoxima_is_truthy_panel_flag($p['emergency_panel_status'] ?? '', ['on_emergency_panel']);
                    $nationalOn  = faoxima_is_truthy_panel_flag($p['national_net_status']    ?? '', ['on_national_net']);
                    $testOn      = faoxima_is_truthy_panel_flag($p['TestAccount']            ?? '', ['ONTestAccount']);
                    $inboundOn   = faoxima_is_truthy_panel_flag($p['inboundstatus']          ?? '', ['offinbounddisable']);
                ?>
                    <div class="panel-card">
                        <div class="panel-card__head">
                            <div class="panel-card__name">
                                <?php echo icon('server', 'svg-icon svg-md'); ?>
                                <span><?php echo htmlspecialchars($p['name_panel'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                <small class="badge badge-info"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php if ($isActive): ?>
                                    <small class="badge badge-active">فعال</small>
                                <?php else: ?>
                                    <small class="badge badge-block">غیرفعال</small>
                                <?php endif; ?>
                                <?php if ($nationalOn): ?>
                                    <small class="badge badge-warning">🌐 نت ملی</small>
                                <?php endif; ?>
                            </div>
                            <div class="panel-card__actions">
                                <button class="btn btn-sm btn-soft-purple"
                                        onclick='openEdit(<?php echo json_encode([
                                            "id"=>(int)$p["id"],
                                            "name_panel"=>$p["name_panel"]??"",
                                            "url_panel"=>$p["url_panel"]??"",
                                            "username_panel"=>$p["username_panel"]??"",
                                            "type"=>$type,
                                            "agent"=>$p["agent"]??"all",
                                            "status"=>$status,
                                            "val_usertest"   => $p["val_usertest"]   ?? "",
                                            "time_usertest"  => $p["time_usertest"]  ?? "",
                                            "limit_panel"    => $p["limit_panel"]    ?? "",
                                            "Methodextend"   => $p["Methodextend"]   ?? "",
                                            "MethodUsername" => $p["MethodUsername"] ?? "",
                                            "priceChangeloc" => $p["priceChangeloc"] ?? "",
                                            "inboundid"       => $p["inboundid"]       ?? "",
                                            "linksubx"        => $p["linksubx"]        ?? "",
                                            "secret_code"     => $p["secret_code"]     ?? "",
                                            "namecustom"      => $p["namecustom"]      ?? "",
                                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                    ویرایش
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('پنل «<?php echo htmlspecialchars($p['name_panel']??'', ENT_QUOTES); ?>» حذف شود؟');">
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-soft-danger">
                                        <?php echo icon('trash', 'svg-icon'); ?>
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="panel-card__body">
                            <div class="panel-card__info">
                                <?php if ($type !== 'Manualsale'): ?>
                                    <div class="info-line">
                                        <span class="info-line__k">آدرس:</span>
                                        <span class="info-line__v ltr"><?php echo htmlspecialchars($p['url_panel'] ?? '—', ENT_QUOTES); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-line__k">یوزرنیم:</span>
                                        <span class="info-line__v"><?php echo htmlspecialchars($p['username_panel'] ?? '—', ENT_QUOTES); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-line__k">پسورد:</span>
                                        <span class="info-line__v"><?php echo faoxima_mask_secret($p['password_panel'] ?? ''); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-line__k">API Key:</span>
                                        <span class="info-line__v"><?php echo faoxima_mask_secret($p['api_key'] ?? ''); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="info-line" style="color:var(--text-muted); font-style:italic;">پنل فروش دستی — بدون اتصال API. کانفیگ‌ها به‌صورت دستی از انبار خارج می‌شوند.</div>
                                <?php endif; ?>
                            </div>

                            <div class="panel-card__toggles">
                                <?php if ($type !== 'Manualsale'): ?>
                                    <label class="toggle-row" title="فعال/غیرفعال inboundها">
                                        <input type="checkbox" class="panel-toggle"
                                               data-id="<?php echo (int)$p['id']; ?>" data-field="inboundstatus"
                                               <?php echo $inboundOn ? 'checked' : ''; ?>>
                                        <span class="switch__slot"></span>
                                        <span class="toggle-row__label">📡 inboundها فعال</span>
                                    </label>
                                <?php endif; ?>

                                <label class="toggle-row" title="اجازه ساخت اکانت تست از این پنل">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="TestAccount"
                                           <?php echo $testOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">🔑 اکانت تست</span>
                                </label>

                                <?php
                                
                                $configOn = faoxima_is_truthy_panel_flag($p['config'] ?? '', ['onconfig']);
                                if (!in_array($type, ['Manualsale', 'WGDashboard', 'hiddify', 'guard'])):
                                ?>
                                <label class="toggle-row" title="ارسال کانفیگ‌های اتصال به کاربر پس از خرید">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="config"
                                           <?php echo $configOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">⚙️ ارسال کانفیگ</span>
                                </label>
                                <?php endif; ?>

                                <?php
                                
                                $sublinkOn = faoxima_is_truthy_panel_flag($p['sublink'] ?? '', ['onsublink']);
                                if (!in_array($type, ['Manualsale', 'WGDashboard', 'hiddify'])):
                                ?>
                                <label class="toggle-row" title="ارسال لینک اشتراک (Subscription URL) به کاربر پس از خرید">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="sublink"
                                           <?php echo $sublinkOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">🔗 ارسال لینک ساب</span>
                                </label>
                                <?php endif; ?>

                                <?php
                                
                                $conectonOn = faoxima_is_truthy_panel_flag($p['conecton'] ?? '', ['onconecton']);
                                if (in_array($type, ['marzban', 'x-ui_single', 'marzneshin'])):
                                ?>
                                <label class="toggle-row" title="فعال‌سازی سطر کانکشن — نمایش زمان اولین اتصال کاربر">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="conecton"
                                           <?php echo $conectonOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">📊 سطر کانکشن</span>
                                </label>
                                <?php endif; ?>

                                <label class="toggle-row" title="پنل اضطراری — هنگام قطعی پنل اصلی، این پنل جایگزین می‌شود">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="emergency_panel_status"
                                           <?php echo $emergencyOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">🚨 پنل اضطراری</span>
                                </label>

                                <label class="toggle-row" title="وضعیت نت ملی — حالت پنل برای زمان قطعی اینترنت خارج">
                                    <input type="checkbox" class="panel-toggle"
                                           data-id="<?php echo (int)$p['id']; ?>" data-field="national_net_status"
                                           <?php echo $nationalOn ? 'checked' : ''; ?>>
                                    <span class="switch__slot"></span>
                                    <span class="toggle-row__label">🌐 وضعیت نت ملی</span>
                                </label>
                            </div>

                            <?php if ($emergencyOn): ?>
                                <div class="emergency-config">
                                    <form method="POST" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <input type="hidden" name="_action" value="set_emergency_source">
                                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                        <label style="font-size:12.5px; color:var(--text-muted);">📌 ثبت پنل جایگزین اضطراری:</label>
                                        <select name="emergency_source_panel" class="form-control" style="flex:1; min-width:160px; padding:6px 10px; font-size:12.5px;">
                                            <option value="">— انتخاب —</option>
                                            <?php foreach ($panels as $other):
                                                if ((int)$other['id'] === (int)$p['id']) continue; ?>
                                                <option value="<?php echo htmlspecialchars($other['code_panel'] ?? '', ENT_QUOTES); ?>"
                                                    <?php echo ((string)($p['emergency_source_panel'] ?? '') === (string)($other['code_panel'] ?? '')) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($other['name_panel'] ?? '', ENT_QUOTES); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-soft-warning">ثبت</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                
                <div class="card view-pane" data-view="table" style="display:none;">
                    <div class="table-wrap">
                        <table class="display app-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>نام پنل</th>
                                    <th>نوع</th>
                                    <th>آدرس</th>
                                    <th>وضعیت</th>
                                    <th>اکانت تست</th>
                                    <th>کانفیگ</th>
                                    <th>لینک ساب</th>
                                    <th>سطر کانکشن</th>
                                    <th>اضطراری</th>
                                    <th>نت ملی</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($panels as $p):
                                $status      = strtolower((string)($p['status'] ?? ''));
                                $isActive    = ($status === 'active' || $status === 'on' || $status === '1');
                                $type        = (string)($p['type'] ?? 'marzban');
                                $typeLabel   = $PANEL_TYPES[$type] ?? $type;
                                $emergencyOn = faoxima_is_truthy_panel_flag($p['emergency_panel_status'] ?? '', ['on_emergency_panel']);
                                $nationalOn  = faoxima_is_truthy_panel_flag($p['national_net_status']    ?? '', ['on_national_net']);
                                $testOn      = faoxima_is_truthy_panel_flag($p['TestAccount']            ?? '', ['ONTestAccount']);
                                $configOn_t  = faoxima_is_truthy_panel_flag($p['config']    ?? '', ['onconfig']);
                                $sublinkOn_t = faoxima_is_truthy_panel_flag($p['sublink']   ?? '', ['onsublink']);
                                $conectonOn_t= faoxima_is_truthy_panel_flag($p['conecton']  ?? '', ['onconecton']);
                            ?>
                                <tr>
                                    <td data-label="نام پنل"><b><?php echo htmlspecialchars($p['name_panel'] ?? '', ENT_QUOTES); ?></b></td>
                                    <td data-label="نوع"><span class="badge badge-info"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES); ?></span></td>
                                    <td data-label="آدرس" style="direction:ltr; text-align:right; font-family:'JetBrains Mono', monospace; font-size:11.5px;">
                                        <?php echo htmlspecialchars($p['url_panel'] ?? '—', ENT_QUOTES); ?>
                                    </td>
                                    <td data-label="وضعیت">
                                        <?php if ($isActive): ?><span class="badge badge-active">فعال</span>
                                        <?php else: ?><span class="badge badge-block">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="اکانت تست"><?php echo $testOn ? '✅' : '❌'; ?></td>
                                    <td data-label="کانفیگ"><?php echo (!in_array($type, ['Manualsale','WGDashboard','hiddify','guard'])) ? ($configOn_t ? '✅' : '❌') : '—'; ?></td>
                                    <td data-label="لینک ساب"><?php echo (!in_array($type, ['Manualsale','WGDashboard','hiddify'])) ? ($sublinkOn_t ? '✅' : '❌') : '—'; ?></td>
                                    <td data-label="سطر کانکشن"><?php echo in_array($type, ['marzban','x-ui_single','marzneshin']) ? ($conectonOn_t ? '✅' : '❌') : '—'; ?></td>
                                    <td data-label="اضطراری"><?php echo $emergencyOn ? '🚨 روشن' : '⚪️'; ?></td>
                                    <td data-label="نت ملی"><?php echo $nationalOn ? '🌐 روشن' : '⚪️'; ?></td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                            <button class="btn btn-sm btn-soft-purple"
                                                    onclick='openEdit(<?php echo json_encode([
                                                        "id"=>(int)$p["id"],
                                                        "name_panel"=>$p["name_panel"]??"",
                                                        "url_panel"=>$p["url_panel"]??"",
                                                        "username_panel"=>$p["username_panel"]??"",
                                                        "type"=>$type,
                                                        "agent"=>$p["agent"]??"all",
                                                        "status"=>$status,
                                                        "val_usertest"   => $p["val_usertest"]   ?? "",
                                                        "time_usertest"  => $p["time_usertest"]  ?? "",
                                                        "limit_panel"    => $p["limit_panel"]    ?? "",
                                                        "Methodextend"   => $p["Methodextend"]   ?? "",
                                                        "MethodUsername" => $p["MethodUsername"] ?? "",
                                                        "priceChangeloc" => $p["priceChangeloc"] ?? "",
                                                        "inboundid"       => $p["inboundid"]       ?? "",
                                                        "linksubx"        => $p["linksubx"]        ?? "",
                                                        "secret_code"     => $p["secret_code"]     ?? "",
                                                        "namecustom"      => $p["namecustom"]      ?? "",
                                                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                            </button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('پنل «<?php echo htmlspecialchars($p['name_panel']??'', ENT_QUOTES); ?>» حذف شود؟');">
                                                <input type="hidden" name="_action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                    <?php echo icon('trash', 'svg-icon'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </section>
</section>


<div id="modal-add-panel" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن پنل جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-panel')">&times;</button>
        </div>
        <form method="POST" action="panels.php">
            <input type="hidden" name="_action" value="add">

            <div class="form-group">
                <label class="form-label">نام پنل</label>
                <input type="text" name="name_panel" class="form-control" placeholder="مثلاً: آلمان فرانکفورت" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع پنل</label>
                <select name="type" class="form-control" required onchange="toggleUrlField(this.value)">
                    <?php foreach ($PANEL_TYPES as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="addUrlGroup">
                <label class="form-label">آدرس کامل پنل</label>
                <input type="url" name="url_panel" id="addUrlInput" class="form-control" placeholder="https://panel.example.com:8080" style="direction:ltr">
            </div>
            <div class="form-row" id="addCredsGroup">
                <div class="form-group">
                    <label class="form-label">یوزرنیم</label>
                    <input type="text" name="username_panel" class="form-control" style="direction:ltr" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">پسورد</label>
                    <input type="password" name="password_panel" class="form-control" style="direction:ltr" autocomplete="new-password">
                </div>
            </div>
            <div class="form-group" id="addApiGroup">
                <label class="form-label">API Key (اختیاری)</label>
                <input type="text" name="api_key" class="form-control" style="direction:ltr" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label">گروه کاربری</label>
                <select name="agent" class="form-control">
                    <option value="all">همه کاربران</option>
                    <option value="f">فقط کاربر عادی</option>
                    <option value="n">فقط نمایندگان</option>
                </select>
            </div>

            
            <div id="add_test_result" style="display:none; margin: 10px 0; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;"></div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-panel')">انصراف</button>
                <button type="button" class="btn btn-soft-purple btn-sm" id="btn_add_test" onclick="testPanelAuth('add')">
                    🔍 تست اتصال
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                    افزودن
                </button>
            </div>
        </form>
    </div>
</div>


<div id="modal-edit-panel" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش پنل</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-panel')">&times;</button>
        </div>
        <form method="POST" action="panels.php">
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label">نام پنل</label>
                <input type="text" name="name_panel" id="edit_name_panel" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع پنل</label>
                <select name="type" id="edit_type" class="form-control" required>
                    <?php foreach ($PANEL_TYPES as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">آدرس پنل</label>
                <input type="url" name="url_panel" id="edit_url_panel" class="form-control" style="direction:ltr">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">یوزرنیم</label>
                    <input type="text" name="username_panel" id="edit_username_panel" class="form-control" style="direction:ltr" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">پسورد جدید <small style="color:var(--text-muted)">(خالی = بدون تغییر)</small></label>
                    <input type="password" name="password_panel" class="form-control" style="direction:ltr" autocomplete="new-password" placeholder="برای تغییر پسورد، اینجا تایپ کنید">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">API Key جدید <small style="color:var(--text-muted)">(خالی = بدون تغییر)</small></label>
                <input type="text" name="api_key" class="form-control" style="direction:ltr" autocomplete="off" placeholder="برای تغییر API key، اینجا تایپ کنید">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">گروه کاربری</label>
                    <select name="agent" id="edit_agent" class="form-control">
                        <option value="all">همه کاربران</option>
                        <option value="f">فقط کاربر عادی</option>
                        <option value="n">فقط نمایندگان</option>
                    </select>
                </div>
                <div class="form-group" id="edit_status_group" style="display:flex; align-items:end; gap:10px; padding-top:24px;">
                    
                    <label class="switch" style="margin-bottom:8px; display:none;" id="edit_status_switch">
                        <input type="checkbox" name="status" id="edit_status" checked>
                        <span class="switch__slot"></span>
                    </label>
                    <span style="font-size:13px;" id="edit_status_label">
                        <span id="edit_status_text_auto">🔄 وضعیت خودکار</span>
                        <span id="edit_status_text_manual" style="display:none;">پنل فعال</span>
                        <small style="display:block; color:var(--text-muted); font-size:11.5px; margin-top:2px;">
                            <span id="edit_status_hint_auto">پس از ذخیره، اگر اعتبار پنل (یوزرنیم/پسورد/آدرس) درست باشد فعال و در غیر این صورت غیرفعال می‌شود.</span>
                            <span id="edit_status_hint_manual" style="display:none;">💡 پنل فروش دستی — وضعیت را خودتان انتخاب کنید.</span>
                        </small>
                    </span>
                </div>
            </div>

            
            <details id="edit_advanced_details" style="margin-top: 12px; border: 1px dashed var(--border-mid); border-radius: 8px; padding: 10px 14px;">
                <summary style="cursor:pointer; font-size:13px; font-weight:700; color:var(--accent);">
                    ⚙️ تنظیمات پیشرفته پنل
                </summary>
                <div style="margin-top: 12px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">⏳ زمان سرویس تست (روز)</label>
                            <input type="number" min="0" name="time_usertest" id="edit_time_usertest" class="form-control" placeholder="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">💾 حجم اکانت تست (٪ یا MB)</label>
                            <input type="text" name="val_usertest" id="edit_val_usertest" class="form-control" placeholder="100" style="direction:ltr">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">🚨 محدودیت ساخت اکانت</label>
                            <input type="text" name="limit_panel" id="edit_limit_panel" class="form-control" placeholder="unlimted یا عدد" style="direction:ltr">
                        </div>
                        <div class="form-group">
                            <label class="form-label">🌍 قیمت تغییر لوکیشن (تومان)</label>
                            <input type="number" min="0" name="priceChangeloc" id="edit_priceChangeloc" class="form-control" placeholder="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">🔋 روش تمدید سرویس</label>
                        <select name="Methodextend" id="edit_Methodextend" class="form-control">
                            <option value="">— انتخاب —</option>
                            <option value="ریست حجم و زمان">ریست حجم و زمان</option>
                            <option value="ریست حجم">فقط ریست حجم</option>
                            <option value="ریست زمان">فقط ریست زمان</option>
                            <option value="بدون ریست">بدون ریست (افزایش)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">💡 روش ساخت نام کاربری</label>
                        <select name="MethodUsername" id="edit_MethodUsername" class="form-control" onchange="onMethodUsernameChange(this.value)">
                            <option value="">— انتخاب روش —</option>
                            <option value="آیدی عددی + حروف و عدد رندوم">آیدی عددی + حروف و عدد رندوم</option>
                            <option value="نام کاربری + حروف و عدد رندوم">نام کاربری + حروف و عدد رندوم</option>
                            <option value="نام کاربری دلخواه + عدد رندوم">نام کاربری دلخواه + عدد رندوم</option>
                            <option value="متن دلخواه + عدد رندوم">متن دلخواه + عدد رندوم</option>
                            <option value="متن دلخواه + عدد ترتیبی">متن دلخواه + عدد ترتیبی</option>
                            <option value="نام کاربری + عدد به ترتیب">نام کاربری + عدد به ترتیب</option>
                            <option value="آیدی عددی+عدد ترتیبی">آیدی عددی + عدد ترتیبی</option>
                            <option value="متن دلخواه نماینده + عدد ترتیبی">متن دلخواه نماینده + عدد ترتیبی</option>
                            <option value="نام کاربری دلخواه">نام کاربری دلخواه (بدون پسوند)</option>
                        </select>
                        <small style="color:var(--text-muted); font-size:11.5px; margin-top:4px; display:block;">روش تولید نام کاربری برای اکانت‌های جدید در این پنل.</small>
                    </div>

                    
                    <div class="form-group" id="edit_namecustom_group" style="display:none;">
                        <label class="form-label">✏️ متن پیشوند دلخواه (namecustom)</label>
                        <input type="text" name="namecustom" id="edit_namecustom" class="form-control" style="direction:ltr" placeholder="مثلاً: vip یا user">
                        <small style="color:var(--text-muted); font-size:11.5px; margin-top:4px; display:block;">متنی که به‌عنوان پیشوند نام کاربری استفاده می‌شود. فقط حروف انگلیسی و عدد.</small>
                    </div>

                    
                    <div class="form-group" id="edit_inboundid_group" style="display:none;">
                        <label class="form-label">💎 شناسه اینباند (Inbound ID)</label>
                        <input type="text" name="inboundid" id="edit_inboundid" class="form-control" style="direction:ltr" placeholder="مثلاً: 1 یا 3 (عدد ID ستون اینباند در پنل)">
                        <small style="color:var(--text-muted); font-size:11.5px; margin-top:4px; display:block;">ثنایی / علیرضا تک‌پورت: شناسه عددی اینباند در پنل. WGDashboard: نام کانفیگ Wireguard.</small>
                        <div id="edit_inbound_result" style="display:none; margin-top:6px; padding:8px 12px; border-radius:6px; font-size:12.5px; font-weight:600;"></div>
                    </div>

                    
                    <div class="form-group" id="edit_linksubx_group" style="display:none;">
                        <label class="form-label">🔗 دامنه لینک ساب (Sub Link Domain)</label>
                        <input type="text" name="linksubx" id="edit_linksubx" class="form-control" style="direction:ltr" placeholder="مثلاً: https://example.com:2053/sub">
                        <small style="color:var(--text-muted); font-size:11.5px; margin-top:4px; display:block;">ثنایی: یک لینک ساب نمونه از پنل کپی کنید تا دامنه خودکار استخراج شود. هیدیفای: آدرس کامل دامنه.</small>
                        <div id="edit_sublink_result" style="display:none; margin-top:6px; padding:8px 12px; border-radius:6px; font-size:12.5px; font-weight:600;"></div>
                    </div>

                    
                    <div class="form-group" id="edit_secret_code_group" style="display:none;">
                        <label class="form-label">🔗 UUID ادمین (Admin UUID)</label>
                        <input type="text" name="secret_code" id="edit_secret_code" class="form-control" style="direction:ltr" placeholder="مثلاً: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        <small style="color:var(--text-muted); font-size:11.5px; margin-top:4px; display:block;">UUID ادمین پنل هیدیفای — از صفحه تنظیمات پنل قابل کپی است.</small>
                    </div>
                </div>
            </details>

            
            <div id="edit_test_result" style="display:none; margin: 10px 0; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;"></div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit-panel')">انصراف</button>
                <button type="button" class="btn btn-soft-purple btn-sm" id="btn_edit_test" onclick="testPanelAuth('edit')">
                    🔍 تست اتصال
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo icon('check', 'svg-icon svg-sm'); ?>
                    ذخیره
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function toggleUrlField(type) {
    var hide = (type === 'Manualsale');
    document.getElementById('addUrlGroup').style.display    = hide ? 'none' : '';
    document.getElementById('addCredsGroup').style.display  = hide ? 'none' : '';
    document.getElementById('addApiGroup').style.display    = hide ? 'none' : '';
    var u = document.getElementById('addUrlInput');
    if (u) u.required = !hide;
}

/**
 * تست سریع اتصال به پنل بدون نیاز به submit فرم
 * mode: 'add' | 'edit'
 */
function testPanelAuth(mode) {
    var prefix   = mode === 'edit' ? 'edit_' : '';
    var resultEl = document.getElementById(mode + '_test_result');
    var btnEl    = document.getElementById('btn_' + mode + '_test');

    // خواندن مقادیر از فرم
    var url      = (document.getElementById(prefix + 'url_panel')      || {}).value || '';
    var username = (document.getElementById(prefix + 'username_panel') || {}).value || '';
    var password = (document.getElementById(prefix + 'password_panel') || {}).value || '';
    var apiKey   = (document.getElementById(prefix + 'api_key')        || {}).value || '';
    var type     = (document.getElementById(prefix + 'type')           || {}).value || 'marzban';
    var panelId  = mode === 'edit' ? ((document.getElementById('edit_id') || {}).value || '') : '';

    if (type === 'Manualsale') {
        showTestResult(resultEl, true, '✅ پنل فروش دستی — نیازی به تست ندارد');
        return;
    }
    if (!url) {
        showTestResult(resultEl, false, '⚠️ ابتدا آدرس URL پنل را وارد کنید');
        return;
    }

    // نمایش حالت loading
    if (resultEl) {
        resultEl.style.display = '';
        resultEl.style.background = 'var(--surface-1)';
        resultEl.style.color = 'var(--text-muted)';
        resultEl.style.border = '1px solid var(--border-mid)';
        resultEl.textContent = '⏳ در حال تست اتصال...';
    }
    if (btnEl) { btnEl.disabled = true; btnEl.textContent = '⏳ ...'; }

    var fd = new FormData();
    fd.append('url',      url);
    fd.append('username', username);
    fd.append('password', password);
    fd.append('api_key',  apiKey);
    fd.append('type',     type);
    if (panelId) fd.append('panel_id', panelId);

    fetch('panels.php?ajax=test_auth', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (btnEl) { btnEl.disabled = false; btnEl.textContent = '🔍 تست اتصال'; }
            showTestResult(resultEl, !!j.verified, j.message || (j.verified ? 'ورود موفق ✅' : '❌ ورود ناموفق'));
        })
        .catch(function(e) {
            if (btnEl) { btnEl.disabled = false; btnEl.textContent = '🔍 تست اتصال'; }
            showTestResult(resultEl, false, '❌ خطا در ارسال درخواست: ' + e.message);
        });
}

function showTestResult(el, success, msg) {
    if (!el) return;
    el.style.display = '';
    el.style.border = '1px solid ' + (success ? 'var(--success, #22c55e)' : 'var(--danger, #ef4444)');
    el.style.background = success ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)';
    el.style.color = success ? 'var(--success, #16a34a)' : 'var(--danger, #dc2626)';
    el.textContent = msg;
}


// ───────────────────────────────────────────────────────────────
//  Real-time validation: وقتی کاربر از فیلد inboundid یا linksubx خارج شد
// ───────────────────────────────────────────────────────────────
(function () {
    function getEditFormData() {
        return {
            url:      (document.getElementById('edit_url_panel')      || {}).value || '',
            username: (document.getElementById('edit_username_panel') || {}).value || '',
            password: (document.getElementById('edit_password_panel') || {}).value || '',
            type:     (document.getElementById('edit_type')           || {}).value || '',
            id:       (document.getElementById('edit_id')             || {}).value || ''
        };
    }

    function attachFieldValidator(fieldId, endpoint, postKeyName, resultId) {
        var field = document.getElementById(fieldId);
        var result = document.getElementById(resultId);
        if (!field || !result) return;

        field.addEventListener('blur', function () {
            var val = field.value.trim();
            if (!val) { result.style.display = 'none'; return; }

            var d = getEditFormData();
            // نمایش حالت loading
            result.style.display = '';
            result.style.border = '1px solid var(--border-mid)';
            result.style.background = 'var(--surface-1)';
            result.style.color = 'var(--text-muted)';
            result.textContent = '⏳ در حال بررسی...';

            var fd = new FormData();
            fd.append(postKeyName, val);
            fd.append('url',      d.url);
            fd.append('username', d.username);
            fd.append('password', d.password);
            fd.append('type',     d.type);
            fd.append('panel_id', d.id);

            fetch('panels.php?ajax=' + endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    result.style.display = '';
                    var ok = !!j.ok;
                    result.style.border = '1px solid ' + (ok ? 'var(--success, #22c55e)' : 'var(--danger, #ef4444)');
                    result.style.background = ok ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.08)';
                    result.style.color = ok ? 'var(--success, #16a34a)' : 'var(--danger, #dc2626)';
                    result.textContent = j.message || (ok ? '✅ معتبر است' : '❌ نامعتبر');
                })
                .catch(function () { result.style.display = 'none'; });
        });
    }

    // وقتی DOM آماده شد validator ها رو attach کن
    document.addEventListener('DOMContentLoaded', function () {
        attachFieldValidator('edit_inboundid', 'validate_inbound', 'inbound_id', 'edit_inbound_result');
        attachFieldValidator('edit_linksubx',  'validate_sublink',  'linksubx',   'edit_sublink_result');
    });
})();

function openEdit(d) {
    document.getElementById('edit_id').value             = d.id;
    document.getElementById('edit_name_panel').value     = d.name_panel || '';
    document.getElementById('edit_url_panel').value      = d.url_panel || '';
    document.getElementById('edit_username_panel').value = d.username_panel || '';
    document.getElementById('edit_type').value           = d.type || 'marzban';
    document.getElementById('edit_agent').value          = d.agent || 'all';


    document.getElementById('edit_status').checked       = (d.status === 'active' || d.status === '1' || d.status === 'on');
    applyEditStatusMode(d.type || 'marzban');

    var setIfExists = function (id, val) {
        var el = document.getElementById(id);
        if (el) el.value = (val == null) ? '' : val;
    };
    setIfExists('edit_val_usertest',    d.val_usertest);
    setIfExists('edit_time_usertest',   d.time_usertest);
    setIfExists('edit_limit_panel',     d.limit_panel);
    setIfExists('edit_Methodextend',    d.Methodextend);
    setIfExists('edit_MethodUsername',  d.MethodUsername);
    setIfExists('edit_priceChangeloc',  d.priceChangeloc);
    setIfExists('edit_inboundid',       d.inboundid);
    setIfExists('edit_linksubx',        d.linksubx);
    setIfExists('edit_secret_code',     d.secret_code);
    setIfExists('edit_namecustom',      d.namecustom);

    // نمایش/مخفی فیلدهای پیشرفته بر اساس نوع پنل
    applyAdvancedFieldsVisibility(d.type || 'marzban');
    // بروزرسانی نمایش namecustom بر اساس مقدار MethodUsername
    onMethodUsernameChange(d.MethodUsername || '');

    // اگر پنل دارای فیلدهای پیشرفته اختصاصی است، <details> را خودکار باز کن
    var needsOpen = ['x-ui_single', 'alireza_single', 'WGDashboard', 'hiddify'].indexOf(d.type || '') !== -1;
    var detailsEl = document.getElementById('edit_advanced_details');
    if (detailsEl && needsOpen) detailsEl.open = true;

    // ریست نتایج validation و تست قبلی
    ['edit_test_result', 'edit_inbound_result', 'edit_sublink_result'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    openModal('modal-edit-panel');
}


function applyEditStatusMode(type) {
    var isManual = (type === 'Manualsale');
    var sw       = document.getElementById('edit_status_switch');
    var txtAuto  = document.getElementById('edit_status_text_auto');
    var txtMan   = document.getElementById('edit_status_text_manual');
    var hintAuto = document.getElementById('edit_status_hint_auto');
    var hintMan  = document.getElementById('edit_status_hint_manual');
    if (sw)       sw.style.display       = isManual ? '' : 'none';
    if (txtAuto)  txtAuto.style.display  = isManual ? 'none' : '';
    if (txtMan)   txtMan.style.display   = isManual ? '' : 'none';
    if (hintAuto) hintAuto.style.display = isManual ? 'none' : '';
    if (hintMan)  hintMan.style.display  = isManual ? '' : 'none';
}


(function () {
    var sel = document.getElementById('edit_type');
    if (sel) sel.addEventListener('change', function () {
        applyEditStatusMode(sel.value);
        applyAdvancedFieldsVisibility(sel.value);
    });
})();

/**
 * نمایش/مخفی کردن فیلدهای پیشرفته بر اساس نوع پنل انتخاب‌شده
 *
 * منطق دقیقاً منطبق با ربات (keyboard/admin_panels.php):
 *   inboundid  → x-ui_single, alireza_single, WGDashboard
 *   linksubx   → x-ui_single, alireza_single, hiddify
 */
function applyAdvancedFieldsVisibility(type) {
    // منطق دقیقاً منطبق با ربات (keyboard/admin_panels.php)
    var needsInbound    = ['x-ui_single', 'alireza_single', 'WGDashboard'].indexOf(type) !== -1;
    var needsSublink    = ['x-ui_single', 'alireza_single', 'hiddify'].indexOf(type) !== -1;
    var needsSecretCode = (type === 'hiddify');

    var grpInbound    = document.getElementById('edit_inboundid_group');
    var grpSublink    = document.getElementById('edit_linksubx_group');
    var grpSecretCode = document.getElementById('edit_secret_code_group');

    if (grpInbound)    grpInbound.style.display    = needsInbound    ? '' : 'none';
    if (grpSublink)    grpSublink.style.display    = needsSublink    ? '' : 'none';
    if (grpSecretCode) grpSecretCode.style.display = needsSecretCode ? '' : 'none';
}

// نمایش فیلد "متن پیشوند" وقتی MethodUsername از نوع "متن دلخواه" باشد
function onMethodUsernameChange(val) {
    var customTextMethods = ['متن دلخواه + عدد رندوم', 'متن دلخواه + عدد ترتیبی', 'متن دلخواه نماینده + عدد ترتیبی'];
    var needsCustom = customTextMethods.indexOf(val) !== -1;
    var grp = document.getElementById('edit_namecustom_group');
    if (grp) grp.style.display = needsCustom ? '' : 'none';
}


document.querySelectorAll('.panel-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var fd = new FormData();
        fd.append('id', cb.dataset.id);
        fd.append('field', cb.dataset.field);
        fd.append('on', cb.checked ? '1' : '');
        cb.disabled = true;
        fetch('panels.php?ajax=toggle', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                cb.disabled = false;
                if (!j.ok) {
                    cb.checked = !cb.checked;
                    alert('خطا: ' + (j.error || 'unknown'));
                } else if (cb.dataset.field === 'emergency_panel_status' || cb.dataset.field === 'national_net_status') {

                    setTimeout(function () { location.reload(); }, 400);
                }
            })
            .catch(function () { cb.disabled = false; cb.checked = !cb.checked; });
    });
});


(function () {
    var STORE_KEY = 'faoxima_panels_view';
    var buttons = document.querySelectorAll('.view-toggle__btn');
    var panes   = document.querySelectorAll('.view-pane');
    if (!buttons.length || !panes.length) return;

    function applyView(view) {
        panes.forEach(function (p) {
            p.style.display = (p.dataset.view === view) ? '' : 'none';
        });
        buttons.forEach(function (b) {
            b.classList.toggle('active', b.dataset.view === view);
        });
    }


    var saved = 'cards';
    try { saved = localStorage.getItem(STORE_KEY) || 'cards'; } catch (e) {}
    applyView(saved);

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var v = btn.dataset.view;
            applyView(v);
            try { localStorage.setItem(STORE_KEY, v); } catch (e) {}
        });
    });
})();
</script>
</body>
</html>


