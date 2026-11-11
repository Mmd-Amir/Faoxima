<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
ini_set('display_errors', '0');
session_start();

function fn_json(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo $out === false ? '{"ok":false,"error":"encode"}' : $out;
    exit;
}

function fn_fail(string $error, int $status = 400, array $extra = []): void
{
    fn_json(array_merge(['ok' => false, 'error' => $error], $extra), $status);
}

function fn_post_string($key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

try {
    ob_start();
    require_once __DIR__ . '/../config.php';
    if (!@include_once __DIR__ . '/lib/ad_boot.php') {
        http_response_code(503);
        header('Retry-After: 3600');
        exit;
    }
    ob_end_clean();
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    fn_fail('server', 500);
}

require_once __DIR__ . '/lib/csrf.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fn_fail('server', 500);
}

$fnUser = (string) ($_SESSION['user'] ?? '');
if ($fnUser === '') {
    fn_fail('auth', 401);
}
try {
    $fnAdminStmt = $pdo->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
    $fnAdminStmt->execute([$fnUser]);
    $fnAdminRow = $fnAdminStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fn_fail('server', 500);
}
if (!is_array($fnAdminRow)) {
    fn_fail('forbidden', 403);
}

$fnMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$fnAction = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';

$fnPostActions = ['save', 'delete', 'toggle', 'master', 'preview'];
if (in_array($fnAction, $fnPostActions, true)) {
    if ($fnMethod !== 'POST') {
        fn_fail('method', 405);
    }
    fx_csrf_guard();
}

$fnBootstrapBufferLevel = ob_get_level();
try {
    ob_start();
    if ($fnAction === 'targets') {
        require_once __DIR__ . '/../panels.php';
    } else {
        require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';
    }
    if (!function_exists('rxFileNamingResolve')) {
        require_once __DIR__ . '/../re/rx/function/file_naming.php';
    }
    while (ob_get_level() > $fnBootstrapBufferLevel) {
        ob_end_clean();
    }
} catch (Throwable $e) {
    while (ob_get_level() > $fnBootstrapBufferLevel) {
        ob_end_clean();
    }
    error_log('[filenaming_api bootstrap] ' . $e->getMessage());
    fn_fail('server', 500);
}

if (in_array($fnAction, ['rules', 'save', 'delete', 'toggle'], true)
    && (!function_exists('rxFileNamingTableReady') || !rxFileNamingTableReady())) {
    fn_fail('migration_required', 409);
}

function fn_panel_by_ref($ref)
{
    global $pdo;
    $ref = trim((string) $ref);
    if ($ref === '') {
        return null;
    }
    if (strpos($ref, 'code:') === 0) {
        $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE code_panel = ? LIMIT 1');
        $stmt->execute([substr($ref, 5)]);
    } elseif (strpos($ref, 'name:') === 0) {
        $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1');
        $stmt->execute([substr($ref, 5)]);
    } else {
        return null;
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function fn_panel_targets_manual(array $panel)
{
    global $pdo;
    $key = trim((string) ($panel['code_panel'] ?? ''));
    if ($key === '') {
        return ['targets' => [], 'notice' => 'شناسه پنل فروش دستی در دسترس نیست.'];
    }
    $stmt = $pdo->prepare('SELECT id, namerecord, file_ext, username FROM manualsell WHERE codepanel = ? AND TRIM(contentrecord) <> \'\' ORDER BY id ASC LIMIT 500');
    $stmt->execute([$key]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $targets = [];
    $counts = [];
    foreach ((array) $rows as $row) {
        $ext = strtolower(ltrim(trim((string) ($row['file_ext'] ?? '')), '.'));
        if ($ext === '' || $ext === 'sub' || $ext === 'text') {
            continue;
        }
        $key = rxFileNamingKeyRecord($row['id'] ?? 0);
        if ($key === '') {
            continue;
        }
        $counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;
        $label = trim((string) ($row['namerecord'] ?? ''));
        if ($label === '') {
            $label = 'رکورد #' . (int) ($row['id'] ?? 0);
        }
        $owner = trim((string) ($row['username'] ?? ''));
        $targets[] = [
            'key' => $key,
            'label' => $label,
            'owner' => $owner,
            'basis' => 'record',
            'hint' => rxFileNamingBasisLabel('record'),
            'ext' => $ext,
            'protocol' => $ext === 'conf' ? 'wireguard' : ($ext === 'ovpn' ? 'openvpn' : ''),
            'source' => 'manual',
            'record_id' => (int) ($row['id'] ?? 0),
        ];
    }
    foreach ($targets as $index => $target) {
        $targets[$index]['ambiguous'] = (($counts[$target['key']] ?? 0) > 1);
    }
    return ['targets' => $targets, 'notice' => ''];
}

function fn_target_from_descriptor(array $descriptor)
{
    $basis = (string) ($descriptor['basis'] ?? 'label');
    return [
        'key' => (string) ($descriptor['key'] ?? ''),
        'label' => (string) ($descriptor['label'] ?? ''),
        'owner' => '',
        'basis' => $basis,
        'hint' => rxFileNamingBasisLabel($basis),
        'ext' => (string) ($descriptor['ext'] ?? 'conf'),
        'protocol' => (string) ($descriptor['protocol'] ?? ''),
        'source' => (string) ($descriptor['source'] ?? ''),
        'record_id' => 0,
    ];
}

function fn_finalize_targets(array $targets, $panelRef, $service)
{
    if (empty($_SESSION['file_naming_secret'])) {
        $_SESSION['file_naming_secret'] = bin2hex(random_bytes(32));
    }
    $counts = [];
    foreach ($targets as $target) {
        $indexKey = ($target['source'] ?? '') . '|' . ($target['key'] ?? '');
        $counts[$indexKey] = isset($counts[$indexKey]) ? $counts[$indexKey] + 1 : 1;
    }
    foreach ($targets as $index => $target) {
        $indexKey = ($target['source'] ?? '') . '|' . ($target['key'] ?? '');
        $targets[$index]['ambiguous'] = (($counts[$indexKey] ?? 0) > 1);
        if (!$targets[$index]['ambiguous']) {
            $payload = base64_encode(json_encode([
                'panel_ref' => $panelRef,
                'service' => $service,
                'target' => $target,
                'expires' => time() + 1800,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $targets[$index]['token'] = $payload . '.' . hash_hmac('sha256', $payload, $_SESSION['file_naming_secret']);
        }
    }
    return $targets;
}

if ($fnAction === 'panels') {
    $rows = $pdo->query('SELECT id, code_panel, name_panel, type FROM marzban_panel ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $panels = [];
    foreach ((array) $rows as $row) {
        $panels[] = [
            'ref' => rxFileNamingPanelRef($row),
            'code' => (string) ($row['code_panel'] ?? ''),
            'name' => (string) ($row['name_panel'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
        ];
    }
    fn_json(['ok' => true, 'panels' => $panels]);
}

if ($fnAction === 'services') {
    $ref = isset($_GET['panel_ref']) && is_string($_GET['panel_ref']) ? trim($_GET['panel_ref']) : '';
    $panel = fn_panel_by_ref($ref);
    if ($panel === null) {
        fn_fail('panel_not_found', 404);
    }
    $type = (string) ($panel['type'] ?? '');
    if ($type === 'Manualsale') {
        $stmt = $pdo->prepare('SELECT username FROM manualsell WHERE codepanel = ? AND TRIM(username) <> \'\' GROUP BY username ORDER BY username ASC LIMIT 500');
        $stmt->execute([trim((string) ($panel['code_panel'] ?? ''))]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $services = [];
        foreach ((array) $rows as $row) {
            $services[] = ['value' => (string) ($row['username'] ?? ''), 'label' => (string) ($row['username'] ?? '')];
        }
        fn_json(['ok' => true, 'services' => $services, 'required' => false]);
    }
    $customerId = isset($_GET['user_id']) && is_string($_GET['user_id']) ? trim($_GET['user_id']) : '';
    $customerId = strtr($customerId, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $ruleId = (int) ($_GET['rule_id'] ?? 0);
    $sourceOrder = null;
    if ($customerId === '' && $ruleId > 0 && rxFileNamingTableReady()) {
        $ownerStmt = $pdo->prepare('SELECT i.id_user, i.time_sell, i.id_invoice FROM config_file_rule r JOIN invoice i ON i.username = r.source_service AND i.Service_location = ? WHERE r.id = ? AND r.panel_ref = ? LIMIT 1');
        $ownerStmt->execute([(string) ($panel['name_panel'] ?? ''), $ruleId, $ref]);
        $sourceOrder = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        $customerId = is_array($sourceOrder) ? (string) $sourceOrder['id_user'] : '';
    }
    if (!preg_match('/^[1-9][0-9]{0,19}$/', $customerId)) {
        fn_fail('invalid_user_id');
    }
    $page = min(100000, max(0, (int) ($_GET['page'] ?? 0)));
    if (is_array($sourceOrder)) {
        $rankStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Service_location = ? AND id_user = ? AND Status NOT IN ('unpaid', 'removebyuser', 'removebyadmin', 'disabledn') AND TRIM(username) <> '' AND (COALESCE(time_sell, '') > ? OR (COALESCE(time_sell, '') = ? AND id_invoice > ?))");
        $rankStmt->execute([(string) ($panel['name_panel'] ?? ''), $customerId, (string) ($sourceOrder['time_sell'] ?? ''), (string) ($sourceOrder['time_sell'] ?? ''), (string) $sourceOrder['id_invoice']]);
        $page = (int) floor((int) $rankStmt->fetchColumn() / 20);
    }
    $offset = $page * 20;
    $stmt = $pdo->prepare("SELECT i.id_invoice, i.username, i.Status, i.id_user, i.name_product, u.username AS customer_username, u.namecustom AS customer_name FROM invoice i LEFT JOIN user u ON u.id = i.id_user WHERE i.Service_location = ? AND i.id_user = ? AND i.Status NOT IN ('unpaid', 'removebyuser', 'removebyadmin', 'disabledn') AND TRIM(i.username) <> '' ORDER BY COALESCE(i.time_sell, '') DESC, i.id_invoice DESC LIMIT 21 OFFSET " . $offset);
    $stmt->execute([(string) ($panel['name_panel'] ?? ''), $customerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > 20;
    $rows = array_slice($rows, 0, 20);
    $seen = [];
    $services = [];
    foreach ((array) $rows as $row) {
        $username = trim((string) ($row['username'] ?? ''));
        if ($username === '' || isset($seen[$username])) {
            continue;
        }
        $seen[$username] = true;
        $invoice = trim((string) ($row['id_invoice'] ?? ''));
        $product = trim((string) ($row['name_product'] ?? ''));
        $customer = trim((string) ($row['customer_name'] ?? ''));
        if (in_array(strtolower($customer), ['', 'none', 'null'], true)) {
            $customer = trim((string) ($row['customer_username'] ?? ''));
            $customer = $customer !== '' && strtolower($customer) !== 'none' ? '@' . ltrim($customer, '@') : 'کاربر ' . (string) ($row['id_user'] ?? '');
        }
        $statuses = [
            'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال',
            'disabledn' => 'ناموجود در پنل', 'disabled' => 'غیرفعال',
            'end_of_time' => 'اتمام زمان', 'end_of_volume' => 'اتمام حجم',
            'sendedwarn' => 'هشدار پایانی', 'send_on_hold' => 'خطای اتصال',
            'removebyuser' => 'حذف توسط کاربر', 'removebyadmin' => 'حذف توسط ادمین',
        ];
        $status = $statuses[(string) ($row['Status'] ?? '')] ?? 'وضعیت نامشخص';
        $title = ($product !== '' ? $product : 'سرویس بدون عنوان') . ' · ' . $customer;
        $detail = 'فاکتور: ' . ($invoice !== '' ? $invoice : '—') . ' · ' . $status . ' · نام کاربری سرویس: ' . $username;
        $services[] = [
            'value' => $username,
            'label' => $title,
            'detail' => $detail,
            'search' => implode(' ', [$title, $detail, (string) ($row['id_user'] ?? ''), (string) ($row['customer_username'] ?? '')]),
        ];
    }
    fn_json(['ok' => true, 'services' => $services, 'required' => true, 'user_id' => $customerId, 'page' => $page, 'has_more' => $hasMore]);
}

if ($fnAction === 'targets') {
    $ref = isset($_GET['panel_ref']) && is_string($_GET['panel_ref']) ? trim($_GET['panel_ref']) : '';
    $panel = fn_panel_by_ref($ref);
    if ($panel === null) {
        fn_fail('panel_not_found', 404);
    }
    $type = (string) ($panel['type'] ?? '');
    $service = isset($_GET['service']) && is_string($_GET['service']) ? trim($_GET['service']) : '';

    if ($type === 'Manualsale') {
        $manual = fn_panel_targets_manual($panel);
        fn_json(['ok' => true, 'targets' => fn_finalize_targets($manual['targets'], $ref, ''), 'notice' => $manual['notice'], 'service_required' => false]);
    }

    if ($type === 'WGDashboard') {
        $inboundId = trim((string) ($panel['inboundid'] ?? ''));
        $targets = [];
        $notice = '';
        $targetKey = rxFileNamingKeySubscription($inboundId);
        if ($targetKey === '') {
            $notice = 'اینباند این پنل تعیین نشده است؛ برای تطبیق فایل اشتراک ابتدا اینباند پنل را تنظیم کنید.';
        } else {
            $targets[] = [
                'key' => $targetKey,
                'label' => 'فایل اشتراک اینباند ' . $inboundId,
                'owner' => '',
                'basis' => 'sub',
                'hint' => rxFileNamingBasisLabel('sub'),
                'ext' => 'conf',
                'protocol' => 'wireguard',
                'source' => 'wgdashboard',
                'record_id' => 0,
            ];
        }
        fn_json(['ok' => true, 'targets' => fn_finalize_targets($targets, $ref, ''), 'notice' => $notice, 'service_required' => false]);
    }

    if (!in_array($type, ['x-ui_single', 'jovadpanel', 'rebecca', 'pasarguard'], true)) {
        fn_json(['ok' => true, 'targets' => [], 'notice' => 'بارگذاری فایل‌های این نوع پنل هنوز به تنظیمات نام و کپشن متصل نشده است.', 'service_required' => false]);
    }

    if ($service === '') {
        fn_json(['ok' => true, 'targets' => [], 'notice' => '', 'service_required' => true]);
    }

    $serviceStmt = $pdo->prepare('SELECT 1 FROM invoice WHERE Service_location = ? AND username = ? LIMIT 1');
    $serviceStmt->execute([(string) ($panel['name_panel'] ?? ''), $service]);
    if (!$serviceStmt->fetchColumn()) {
        fn_fail('invalid_target');
    }

    $targets = [];
    $notice = '';
    $output = null;
    try {
        $manager = new ManagePanel();
        $output = $manager->DataUser((string) ($panel['name_panel'] ?? ''), $service);
    } catch (Throwable $e) {
        fn_fail('panel_read_failed', 502);
    }
    if (!is_array($output) || (string) ($output['status'] ?? '') === 'Unsuccessful') {
        $message = is_array($output) && is_scalar($output['msg'] ?? null) ? (string) $output['msg'] : '';
        fn_json(['ok' => true, 'targets' => [], 'notice' => 'خواندن سرویس از پنل ناموفق بود' . ($message !== '' ? ': ' . $message : '.'), 'service_required' => true]);
    }

    if ($type === 'x-ui_single') {
        $singleFile = $output['single_config_file'] ?? null;
        if (is_array($singleFile) && trim((string) ($singleFile['filename'] ?? '')) !== '') {
            $targets[] = [
                'key' => rxFileNamingScopedKey(rxFileNamingKeyFile($singleFile['filename']), $singleFile['protocol'] ?? 'wireguard'),
                'label' => (string) $singleFile['filename'],
                'owner' => '',
                'basis' => 'file',
                'hint' => rxFileNamingBasisLabel('file'),
                'ext' => 'conf',
                'protocol' => (string) ($singleFile['protocol'] ?? 'wireguard'),
                'source' => 'xui_file',
                'record_id' => 0,
            ];
        }
        $links = is_array($output['links'] ?? null) ? $output['links'] : [];
        foreach ($links as $link) {
            if (!is_string($link) || trim($link) === '') {
                continue;
            }
            if (stripos(trim($link), 'xuifile://') === 0 || stripos(trim($link), 'xuiawgfile://') === 0) {
                continue;
            }
            $descriptor = rxFileNamingDescriptorFromLink($link);
            if (is_array($descriptor)) {
                $targets[] = fn_target_from_descriptor($descriptor);
            }
        }
    } elseif (in_array($type, ['rebecca', 'pasarguard'], true)) {
        $links = is_array($output['links'] ?? null) ? $output['links'] : [];
        foreach ($links as $link) {
            if (!is_string($link)) {
                continue;
            }
            $descriptor = rxFileNamingDescriptorFromLink($link);
            if (is_array($descriptor)) {
                $targets[] = fn_target_from_descriptor($descriptor);
            }
        }
        if (empty($targets)) {
            $notice = 'در خروجی این سرویس فایل قابل‌شناسایی پیدا نشد. فایل‌های WireGuard و AmneziaWG با لینک معتبر و فایل‌های دانلودی WireGuard و OpenVPN پشتیبانی می‌شوند؛ لینک‌های معمولی و متن کانفیگ در این فهرست نمایش داده نمی‌شوند.';
        }
    } elseif ($type === 'jovadpanel') {
        $remoteId = trim((string) ($output['remote_id'] ?? ''));
        if ($remoteId === '') {
            $notice = 'شناسه سرویس این پنل در دسترس نیست؛ امکان بارگذاری فایل‌ها وجود ندارد.';
        } else {
            $channels = is_array($output['channels'] ?? null) ? $output['channels'] : [];
            $kinds = [];
            if (in_array('wireguard', $channels, true)) {
                $kinds[] = 'wireguard';
            }
            if (in_array('openvpn', $channels, true)) {
                $kinds[] = 'openvpn';
            }
            $links = is_array($output['links'] ?? null) ? $output['links'] : [];
            foreach ($links as $link) {
                if (!is_string($link)) {
                    continue;
                }
                if (stripos($link, 'jovadwg://') === 0 && !in_array('wireguard', $kinds, true)) {
                    $kinds[] = 'wireguard';
                }
                if (stripos($link, 'jovadovpn://') === 0 && !in_array('openvpn', $kinds, true)) {
                    $kinds[] = 'openvpn';
                }
            }
            if (empty($kinds)) {
                $kinds = ['wireguard', 'openvpn'];
            }
            $rxFoundJovadFiles = false;
            foreach ($kinds as $kind) {
                $files = jovadpanelProtocolFiles($panel, $remoteId, $kind);
                if (($files['status'] ?? false) !== true) {
                    continue;
                }
                foreach ((array) ($files['files'] ?? []) as $file) {
                    $descriptor = rxFileNamingJovadDescriptor($file, $kind);
                    if (!is_array($descriptor)) {
                        continue;
                    }
                    $targets[] = fn_target_from_descriptor($descriptor);
                    $rxFoundJovadFiles = true;
                }
            }
            if (!$rxFoundJovadFiles) {
                $notice = 'هیچ فایل کانفیگی برای این سرویس پیدا نشد.';
            }
        }
    }

    fn_json(['ok' => true, 'targets' => fn_finalize_targets($targets, $ref, $service), 'notice' => $notice, 'service_required' => true]);
}

if ($fnAction === 'rules') {
    $rows = $pdo->query('SELECT * FROM config_file_rule ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $rules = [];
    foreach ((array) $rows as $row) {
        $panelName = (string) ($row['panel_name'] ?? '');
        $panelCode = (string) ($row['panel_code'] ?? '');
        if ($panelCode !== '') {
            $stmt = $pdo->prepare('SELECT name_panel, type FROM marzban_panel WHERE code_panel = ? LIMIT 1');
            $stmt->execute([$panelCode]);
            $panelRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($panelRow)) {
                $panelName = (string) ($panelRow['name_panel'] ?? $panelName);
            }
        }
        $rules[] = [
            'id' => (int) ($row['id'] ?? 0),
            'panel_ref' => (string) ($row['panel_ref'] ?? ''),
            'panel_code' => $panelCode,
            'panel_name' => $panelName,
            'panel_type' => (string) ($row['panel_type'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'source_service' => (string) ($row['source_service'] ?? ''),
            'match_key' => (string) ($row['match_key'] ?? ''),
            'requires_reselect' => in_array((string) ($row['source'] ?? ''), ['xui_wg', 'xui_file', 'rebecca', 'jovad'], true)
                && strpos((string) ($row['match_key'] ?? ''), '|') === false
                && empty(rxFileNamingLegacyRuleKeys($row)),
            'match_label' => (string) ($row['match_label'] ?? ''),
            'match_basis' => (string) ($row['match_basis'] ?? ''),
            'match_basis_label' => rxFileNamingBasisLabel($row['match_basis'] ?? ''),
            'file_ext' => (string) ($row['file_ext'] ?? ''),
            'custom_filename' => (string) ($row['custom_filename'] ?? ''),
            'custom_caption' => (string) ($row['custom_caption'] ?? ''),
            'is_enabled' => ((int) ($row['is_enabled'] ?? 0)) === 1,
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }
    fn_json(['ok' => true, 'rules' => $rules, 'master' => rxFileNamingEnabled(true)]);
}

if ($fnAction === 'preview') {
    $custom = fn_post_string('custom_filename');
    $ext = strtolower(ltrim(fn_post_string('file_ext', 'conf'), '.'));
    $wireguard = ($ext === 'conf');
    $check = rxFileNamingValidateCustomName($custom, $ext, $wireguard);
    $caption = html_entity_decode(rxFileNamingMergeCaption('', fn_post_string('custom_caption')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    fn_json([
        'ok' => true,
        'filename' => $check['filename'],
        'changed' => $check['changed'],
        'error' => $check['error'],
        'wireguard' => $wireguard,
        'caption' => $caption,
        'caption_length' => function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption),
    ]);
}

if ($fnAction === 'save') {
    $id = (int) ($_POST['id'] ?? 0);
    $panelRef = fn_post_string('panel_ref');
    $panel = fn_panel_by_ref($panelRef);
    if ($panel === null) {
        fn_fail('panel_not_found', 404);
    }
    $token = fn_post_string('target_token');
    $parts = explode('.', $token);
    $secret = (string) ($_SESSION['file_naming_secret'] ?? '');
    if (count($parts) !== 2 || $secret === '' || !hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) {
        fn_fail('invalid_target');
    }
    $decoded = base64_decode($parts[0], true);
    $selection = is_string($decoded) ? json_decode($decoded, true) : null;
    if (!is_array($selection) || (int) ($selection['expires'] ?? 0) < time()
        || ($selection['panel_ref'] ?? '') !== $panelRef
        || ($selection['service'] ?? '') !== fn_post_string('source_service')
        || !is_array($selection['target'] ?? null)) {
        fn_fail('invalid_target');
    }
    $verifiedTarget = $selection['target'];
    foreach (['source' => 'source', 'match_key' => 'key', 'file_ext' => 'ext', 'match_label' => 'label'] as $posted => $verified) {
        if (fn_post_string($posted) !== (string) ($verifiedTarget[$verified] ?? '')) {
            fn_fail('invalid_target');
        }
    }
    $source = fn_post_string('source');
    $allowedSources = ['xui_wg', 'xui_file', 'rebecca', 'jovad', 'wgdashboard', 'manual'];
    if (!in_array($source, $allowedSources, true)) {
        fn_fail('invalid_source');
    }
    $matchKey = fn_post_string('match_key');
    $keyPrefixes = ['label:', 'endpoint:', 'file:', 'record:', 'sub:', 'server:'];
    $keyAllowed = false;
    foreach ($keyPrefixes as $prefix) {
        if (strpos($matchKey, $prefix) === 0) {
            $keyAllowed = true;
            break;
        }
    }
    $matchKeyLength = function_exists('mb_strlen') ? mb_strlen($matchKey, 'UTF-8') : strlen($matchKey);
    if (!$keyAllowed || $matchKeyLength > 191) {
        fn_fail('invalid_target');
    }
    $matchLabel = fn_post_string('match_label');
    if (function_exists('mb_substr')) {
        $matchLabel = mb_substr($matchLabel, 0, 255, 'UTF-8');
    } else {
        $matchLabel = substr($matchLabel, 0, 255);
    }
    $basisFromKey = substr($matchKey, 0, (int) strpos($matchKey, ':'));
    $basis = in_array($basisFromKey, ['label', 'endpoint', 'file', 'record', 'sub', 'server'], true) ? $basisFromKey : 'label';
    $allowedBasisBySource = [
        'xui_wg' => ['label', 'endpoint', 'file'],
        'xui_file' => ['file'],
        'rebecca' => ['label', 'file'],
        'jovad' => ['file', 'server'],
        'wgdashboard' => ['sub'],
        'manual' => ['record', 'file'],
    ];
    if (!in_array($basis, $allowedBasisBySource[$source] ?? ['label'], true)) {
        fn_fail('invalid_target');
    }
    $ext = strtolower(ltrim(fn_post_string('file_ext', 'conf'), '.'));
    if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
        fn_fail('invalid_extension');
    }
    $wireguard = ($ext === 'conf');
    $check = rxFileNamingValidateCustomName(fn_post_string('custom_filename'), $ext, $wireguard);
    if (!$check['ok']) {
        fn_fail('invalid_filename', 400, ['preview' => $check]);
    }
    $caption = rxFileNamingTrimCaption(fn_post_string('custom_caption'), 1024);
    $service = fn_post_string('source_service');
    if (function_exists('mb_substr')) {
        $service = mb_substr($service, 0, 191, 'UTF-8');
    } else {
        $service = substr($service, 0, 191);
    }
    $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? 1 : 0;
    $panelCode = (string) ($panel['code_panel'] ?? '');
    $panelName = (string) ($panel['name_panel'] ?? '');
    $panelType = (string) ($panel['type'] ?? '');

    $dupStmt = $pdo->prepare('SELECT id FROM config_file_rule WHERE panel_ref = ? AND source = ? AND match_key = ? AND id <> ? LIMIT 1');
    $dupStmt->execute([$panelRef, $source, $matchKey, $id]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($duplicate)) {
        fn_fail('duplicate_rule', 409);
    }

    $now = time();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE config_file_rule SET panel_ref = ?, panel_code = ?, panel_name = ?, panel_type = ?, source = ?, source_service = ?, match_key = ?, match_label = ?, match_basis = ?, file_ext = ?, custom_filename = ?, custom_caption = ?, is_enabled = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$panelRef, $panelCode, $panelName, $panelType, $source, $service, $matchKey, $matchLabel, $basis, $ext, $check['filename'], $caption, $enabled, $now, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO config_file_rule (panel_ref, panel_code, panel_name, panel_type, source, source_service, match_key, match_label, match_basis, file_ext, custom_filename, custom_caption, is_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$panelRef, $panelCode, $panelName, $panelType, $source, $service, $matchKey, $matchLabel, $basis, $ext, $check['filename'], $caption, $enabled, $now, $now]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'duplicate') !== false) {
            fn_fail('duplicate_rule', 409);
        }
        fn_fail('save_failed', 500);
    }
    fn_json(['ok' => true, 'id' => $id, 'filename' => $check['filename']]);
}

if ($fnAction === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        fn_fail('invalid_id');
    }
    $stmt = $pdo->prepare('DELETE FROM config_file_rule WHERE id = ?');
    $stmt->execute([$id]);
    fn_json(['ok' => true]);
}

if ($fnAction === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        fn_fail('invalid_id');
    }
    $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? 1 : 0;
    $stmt = $pdo->prepare('UPDATE config_file_rule SET is_enabled = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$enabled, time(), $id]);
    fn_json(['ok' => true, 'is_enabled' => $enabled === 1]);
}

if ($fnAction === 'master') {
    $enabled = isset($_POST['is_enabled']) && (string) $_POST['is_enabled'] !== '0' ? '1' : '0';
    try {
        $stmt = $pdo->prepare('UPDATE setting SET config_file_custom_status = ?');
        $stmt->execute([$enabled]);
    } catch (Throwable $e) {
        fn_fail('save_failed', 500);
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('setting');
    }
    if (function_exists('faoxima_bust_bot_selectcache')) {
        faoxima_bust_bot_selectcache('setting');
    }
    if (function_exists('rxFileNamingEnabled')) {
        rxFileNamingEnabled(true);
    }
    fn_json(['ok' => true, 'is_enabled' => $enabled === '1']);
}

fn_fail('unknown_action', 404);
