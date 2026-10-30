<?php

function rx_cap_status_labels()
{
    return [
        'allowed' => '🟢 مجاز (طبق مجوز اعلام‌شده)',
        'denied' => '⛔️ غیرمجاز',
        'verified' => '✅ تأیید شد (تست واقعی)',
        'failed' => '❌ ناموفق',
        'unknown' => '❔ نامشخص',
        'unsupported' => '➖ پشتیبانی نمی‌شود',
        'not_tested' => '⏸ تست نشد',
    ];
}

function rx_cap_labels()
{
    return [
        'connection' => 'اتصال و احراز هویت',
        'read_user' => 'خواندن یک کاربر',
        'list_users' => 'لیست کاربران',
        'configs' => 'دریافت محتوای کانفیگ',
        'create' => 'ساخت کاربر',
        'update' => 'ویرایش و تمدید کاربر',
        'delete' => 'حذف کاربر',
        'reset' => 'ریست مصرف ترافیک',
        'revoke' => 'بازسازی لینک اشتراک',
        'resources' => 'گروه‌ها / اینباندها / منابع',
    ];
}

function rx_cap_write_keys()
{
    return ['create', 'update', 'delete', 'reset', 'revoke'];
}

function rx_cap_new_report(array $panel)
{
    $checks = [];
    foreach (array_keys(rx_cap_labels()) as $key) {
        $checks[$key] = ['status' => 'not_tested', 'note' => ''];
    }
    return [
        'panel' => (string) ($panel['name_panel'] ?? ''),
        'type' => (string) ($panel['type'] ?? ''),
        'checks' => $checks,
        'info' => [],
        'notes' => [],
    ];
}

function rx_cap_set(array &$report, $key, $status, $note = '')
{
    $note = (string) $note;
    if (function_exists('mb_strlen') && mb_strlen($note, 'UTF-8') > 220) {
        $note = mb_substr($note, 0, 220, 'UTF-8') . '…';
    }
    $report['checks'][$key] = ['status' => (string) $status, 'note' => $note];
}

function rx_cap_info(array &$report, $label, $value)
{
    if ($value === null || $value === '') {
        return;
    }
    $report['info'][] = [(string) $label, is_bool($value) ? ($value ? 'بله' : 'خیر') : (string) $value];
}

function rx_cap_note(array &$report, $note)
{
    $report['notes'][] = (string) $note;
}

function rx_cap_clean_text($text, $limit = 160)
{
    $text = (string) $text;
    if (function_exists('pasarguardSanitizeText')) {
        return pasarguardSanitizeText($text, $limit);
    }
    $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
}

function rx_cap_mark_untested_after_connection_failure(array &$report)
{
    foreach ($report['checks'] as $key => $check) {
        if ($key !== 'connection' && $check['status'] === 'not_tested') {
            $report['checks'][$key]['note'] = 'به دلیل برقرار نشدن اتصال بررسی نشد؛ این به معنی نبود دسترسی نیست.';
        }
    }
}

function rx_cap_set_writes(array &$report, $status, $note)
{
    foreach (rx_cap_write_keys() as $key) {
        rx_cap_set($report, $key, $status, $note);
    }
}

function rx_cap_http_read(array $response, $what = '')
{
    $httpStatus = (int) ($response['status'] ?? 0);
    $error = (string) ($response['error'] ?? '');
    if ($httpStatus === 0) {
        return ['failed', 'خطای شبکه' . ($error !== '' ? ': ' . rx_cap_clean_text($error, 120) : '')];
    }
    if ($httpStatus >= 200 && $httpStatus < 300) {
        return ['verified', $what];
    }
    if ($httpStatus === 403) {
        return ['denied', 'پنل درخواست را با HTTP 403 رد کرد'];
    }
    if ($httpStatus === 401) {
        return ['failed', 'احراز هویت رد شد (HTTP 401)'];
    }
    $detail = rx_cap_response_detail($response);
    return ['failed', 'HTTP ' . $httpStatus . ($detail !== '' ? ' — ' . $detail : '')];
}

function rx_cap_response_detail(array $response)
{
    $body = is_string($response['body'] ?? null) ? $response['body'] : '';
    if ($body === '') {
        return '';
    }
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $detail = $decoded['detail'] ?? ($decoded['message'] ?? ($decoded['msg'] ?? ''));
        return rx_cap_clean_text(is_scalar($detail) ? (string) $detail : (string) json_encode($detail, JSON_UNESCAPED_UNICODE), 120);
    }
    return '';
}

function rx_cap_json(array $response)
{
    $body = is_string($response['body'] ?? null) ? $response['body'] : '';
    $decoded = $body !== '' ? json_decode($body, true) : null;
    return is_array($decoded) ? $decoded : null;
}

function rx_cap_sample_usernames($panelName, $limit = 3)
{
    $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("SELECT username FROM invoice WHERE Service_location = ? AND Status IN ('active','sendedwarn','send_on_hold','end_of_time','end_of_volume') AND username IS NOT NULL AND username <> '' ORDER BY time_sell DESC LIMIT 20");
        $stmt->execute([(string) $panelName]);
        $names = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $name = (string) $row['username'];
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
            if (count($names) >= $limit) {
                break;
            }
        }
        return $names;
    } catch (Throwable $e) {
        error_log('rx_cap_sample_usernames: ' . $e->getMessage());
        return [];
    }
}

function rx_cap_no_sample_user(array &$report)
{
    $note = 'هیچ سرویس فعالی از این پنل در ربات ثبت نشده؛ کاربر موجودی برای تست فقط‌خواندنی پیدا نشد.';
    rx_cap_set($report, 'read_user', 'not_tested', $note);
    rx_cap_set($report, 'configs', 'not_tested', $note);
}

function rx_cap_config_entries($links, $subscriptionUrl = '')
{
    $configs = 0;
    $subscriptionOnly = 0;
    foreach ((array) $links as $link) {
        if (!is_string($link) || trim($link) === '') {
            continue;
        }
        $link = trim($link);
        if (preg_match('#^https?://#i', $link) || ($subscriptionUrl !== '' && $link === $subscriptionUrl)) {
            $subscriptionOnly++;
            continue;
        }
        $configs++;
    }
    return ['configs' => $configs, 'subscription_only' => $subscriptionOnly];
}

function rx_cap_probe_sample_user_via_manager(array $panel, array &$report)
{
    $candidates = rx_cap_sample_usernames($panel['name_panel'] ?? '');
    if (empty($candidates)) {
        rx_cap_no_sample_user($report);
        return null;
    }
    if (!class_exists('ManagePanel')) {
        rx_cap_set($report, 'read_user', 'unknown', 'ماژول مدیریت پنل بارگذاری نشده است.');
        return null;
    }
    $manager = new ManagePanel();
    $lastMessage = '';
    foreach ($candidates as $username) {
        $out = $manager->DataUser($panel['name_panel'], $username);
        if (is_array($out) && ($out['status'] ?? '') !== 'Unsuccessful') {
            rx_cap_set($report, 'read_user', 'verified', 'کاربر نمونه ' . $username . ' خوانده شد؛ فقط دسترسی به همین کاربر تأیید شده است.');
            $counts = rx_cap_config_entries($out['links'] ?? [], (string) ($out['subscription_url'] ?? ''));
            if ($counts['configs'] > 0) {
                rx_cap_set($report, 'configs', 'verified', $counts['configs'] . ' کانفیگ برای کاربر نمونه دریافت شد.');
            } elseif ($counts['subscription_only'] > 0) {
                rx_cap_set($report, 'configs', 'unknown', 'فقط لینک اشتراک برگشت؛ محتوای خود کانفیگ دریافت نشد.');
            } else {
                rx_cap_set($report, 'configs', 'failed', 'درخواست کاربر موفق بود اما هیچ کانفیگی برنگشت.');
            }
            return $username;
        }
        $lastMessage = is_array($out) ? (is_scalar($out['msg'] ?? null) ? (string) $out['msg'] : (string) json_encode($out['msg'] ?? '', JSON_UNESCAPED_UNICODE)) : '';
    }
    $cleanMessage = rx_cap_clean_text($lastMessage, 120);
    if (preg_match('/\b403\b|forbidden|not allowed|permission/i', $lastMessage)) {
        rx_cap_set($report, 'read_user', 'denied', 'پنل خواندن کاربر را رد کرد: ' . $cleanMessage);
    } else {
        rx_cap_set($report, 'read_user', 'failed', 'خواندن ' . count($candidates) . ' کاربر نمونه ناموفق بود' . ($cleanMessage !== '' ? ': ' . $cleanMessage : ''));
    }
    rx_cap_set($report, 'configs', 'not_tested', 'چون خواندن کاربر نمونه ناموفق بود، دریافت کانفیگ بررسی نشد.');
    return null;
}

function rx_cap_marzban_family_permissions(array $admin)
{
    $result = ['model' => 'unknown', 'owner' => false, 'role' => null, 'actions' => [], 'limits' => []];
    $map = [
        'create' => ['users', 'create'],
        'update' => ['users', 'update'],
        'delete' => ['users', 'delete'],
        'reset' => ['users', 'reset_usage'],
        'revoke' => ['users', 'revoke_sub'],
        'list_users' => ['users', 'read'],
        'resources' => ['groups', 'read'],
    ];
    if (isset($admin['role']) && is_array($admin['role'])) {
        $role = $admin['role'];
        $result['model'] = 'role';
        $result['role'] = (string) ($role['name'] ?? '');
        $result['owner'] = !empty($role['is_owner']) || !empty($admin['is_owner']);
        $limits = is_array($role['limits'] ?? null) ? $role['limits'] : [];
        if (is_array($admin['permission_overrides'] ?? null)) {
            foreach ($admin['permission_overrides'] as $limitKey => $limitValue) {
                if ($limitValue !== null) {
                    $limits[$limitKey] = $limitValue;
                }
            }
        }
        $result['limits'] = $limits;
        $permissions = $role['permissions'] ?? null;
        foreach ($map as $key => $path) {
            if ($result['owner']) {
                $result['actions'][$key] = ['allowed', 'all'];
                continue;
            }
            $result['actions'][$key] = rx_cap_role_action($permissions, $path[0], $path[1]);
        }
        return $result;
    }
    if (array_key_exists('is_sudo', $admin)) {
        $result['model'] = 'legacy';
        $result['role'] = !empty($admin['is_sudo']) ? 'sudo' : 'admin';
        foreach ($map as $key => $path) {
            if ($key === 'resources') {
                $result['actions'][$key] = ['unknown', null];
                continue;
            }
            $result['actions'][$key] = !empty($admin['is_sudo']) ? ['allowed', 'all'] : ['allowed', 'own'];
        }
        return $result;
    }
    foreach (array_keys($map) as $key) {
        $result['actions'][$key] = ['unknown', null];
    }
    return $result;
}

function rx_cap_role_action($permissions, $section, $action)
{
    if (!is_array($permissions)) {
        return ['unknown', null];
    }
    if (!array_key_exists($section, $permissions) || $permissions[$section] === null) {
        return ['denied', null];
    }
    if (!is_array($permissions[$section])) {
        return ['unknown', null];
    }
    $value = $permissions[$section][$action] ?? null;
    if ($value === null || $value === false) {
        return ['denied', null];
    }
    if ($value === true) {
        return ['allowed', 'all'];
    }
    if (is_array($value) && array_key_exists('scope', $value)) {
        $scope = (int) $value['scope'];
        if ($scope >= 2) {
            return ['allowed', 'all'];
        }
        if ($scope === 1) {
            return ['allowed', 'own'];
        }
        return ['denied', null];
    }
    return ['unknown', null];
}

function rx_cap_scope_note($scope, $model)
{
    $source = $model === 'legacy'
        ? 'بر اساس مدل is_sudo'
        : 'بر اساس مجوز نقش ادمین';
    if ($scope === 'own') {
        return $source . '؛ فقط کاربران متعلق به همین ادمین.';
    }
    if ($scope === 'all') {
        return $source . '؛ همه کاربران.';
    }
    return $source . '.';
}

function rx_cap_apply_marzban_family(array &$report, array $admin)
{
    $perm = rx_cap_marzban_family_permissions($admin);
    if ($perm['model'] === 'unknown') {
        rx_cap_set_writes($report, 'unknown', 'پاسخ /api/admin شامل اطلاعات نقش یا مجوز نیست.');
        return $perm;
    }
    rx_cap_info($report, 'نقش ادمین', $perm['owner'] ? 'owner' : $perm['role']);
    foreach (rx_cap_write_keys() as $key) {
        $action = $perm['actions'][$key] ?? ['unknown', null];
        if ($action[0] === 'allowed') {
            rx_cap_set($report, $key, 'allowed', rx_cap_scope_note($action[1], $perm['model']));
        } elseif ($action[0] === 'denied') {
            rx_cap_set($report, $key, 'denied', $perm['model'] === 'role' ? 'این مجوز در نقش ادمین فعال نیست.' : '');
        } else {
            rx_cap_set($report, $key, 'unknown', 'مقدار مجوز قابل تفسیر نبود.');
        }
    }
    $maxUsers = $perm['limits']['max_users'] ?? null;
    if ($maxUsers !== null) {
        rx_cap_info($report, 'سقف تعداد کاربر', (int) $maxUsers);
    }
    $status = $admin['status'] ?? null;
    if (is_string($status) && $status !== '' && $status !== 'active') {
        rx_cap_info($report, 'وضعیت ادمین', $status);
        if ($report['checks']['create']['status'] === 'allowed') {
            rx_cap_set($report, 'create', 'unknown', 'مجوز ساخت اعلام شده، اما وضعیت ادمین «' . $status . '» است و ممکن است ساخت کاربر مسدود باشد.');
        }
    }
    return $perm;
}

function rx_cap_manualsale_report(array $panel, array &$report)
{
    $note = 'فروش دستی API راه‌دور ندارد؛ کانفیگ‌ها از موجودی ثبت‌شده در ربات تحویل داده می‌شوند.';
    foreach (array_keys($report['checks']) as $key) {
        rx_cap_set($report, $key, 'unsupported', $key === 'connection' ? $note : '');
    }
}

function rx_panel_capability_report(array $panel)
{
    $report = rx_cap_new_report($panel);
    $handlers = [
        'pasarguard' => 'pasarguardCapabilityReport',
        'marzban' => 'marzbanCapabilityReport',
        'rebecca' => 'rebeccaCapabilityReport',
        'jovadpanel' => 'jovadpanelCapabilityReport',
        'guard' => 'guardCapabilityReport',
        'remnawave' => 'remnawave_capability_report',
        'x-ui_single' => 'xui_capability_report',
        'WGDashboard' => 'wgCapabilityReport',
        'Manualsale' => 'rx_cap_manualsale_report',
    ];
    $type = (string) ($panel['type'] ?? '');
    if (!isset($handlers[$type]) || !function_exists($handlers[$type])) {
        foreach (array_keys($report['checks']) as $key) {
            rx_cap_set($report, $key, 'unsupported', $key === 'connection' ? 'این نوع پنل در ربات پشتیبانی نمی‌شود.' : '');
        }
        return $report;
    }
    try {
        $handlers[$type]($panel, $report);
    } catch (Throwable $e) {
        error_log('rx_panel_capability_report: ' . $e->getMessage());
        if ($report['checks']['connection']['status'] === 'not_tested') {
            rx_cap_set($report, 'connection', 'failed', 'خطای داخلی: ' . rx_cap_clean_text($e->getMessage(), 120));
            rx_cap_mark_untested_after_connection_failure($report);
        } else {
            rx_cap_note($report, 'بخشی از بررسی با خطای داخلی متوقف شد: ' . rx_cap_clean_text($e->getMessage(), 120));
        }
    }
    return $report;
}

function rx_cap_render(array $report)
{
    $esc = function ($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    };
    $statusLabels = rx_cap_status_labels();
    $labels = rx_cap_labels();
    $lines = [];
    $lines[] = '🧪 <b>گزارش اتصال و دسترسی پنل</b>';
    $lines[] = 'پنل: <b>' . $esc($report['panel']) . '</b> (' . $esc($report['type']) . ')';
    foreach ($report['info'] as $info) {
        $lines[] = '▫️ ' . $esc($info[0]) . ': <code>' . $esc($info[1]) . '</code>';
    }
    $lines[] = '';
    foreach ($labels as $key => $label) {
        $check = $report['checks'][$key] ?? ['status' => 'not_tested', 'note' => ''];
        $lines[] = '• <b>' . $esc($label) . '</b>: ' . $esc($statusLabels[$check['status']] ?? $check['status']);
        if ($check['note'] !== '') {
            $lines[] = '   └ ' . $esc($check['note']);
        }
    }
    $lines[] = '';
    foreach ($report['notes'] as $note) {
        $lines[] = 'ℹ️ ' . $esc($note);
    }
    $lines[] = 'ℹ️ «مجاز» یعنی پنل این مجوز را اعلام کرده و عملیات نوشتنی به‌صورت واقعی تست نشده است. مجوز ساخت، رعایت سهمیه، گروه یا سایر پیش‌نیازهای یک درخواست خاص را تضمین نمی‌کند.';
    $lines[] = 'ℹ️ در این بررسی هیچ کاربری ساخته، ویرایش یا حذف نشد و مصرف یا لینک اشتراکی تغییر نکرد.';
    return implode("\n", $lines);
}
