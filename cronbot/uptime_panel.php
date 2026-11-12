<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('uptime_panel', 120);

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
if (!function_exists('xui_panel_uses_token') && is_file(__DIR__ . '/../x-ui_single.php')) {
    require_once __DIR__ . '/../x-ui_single.php';
}

if (!rx_cron_db_ready('uptime_panel')) {
    return;
}

if (!function_exists('rx_uptime_xui_monitor')) {
    function rx_uptime_xui_monitor($location, $admin_ids)
    {
        $prevState = json_decode($location['xui_monitor_state'] ?? '', true);
        if (!is_array($prevState)) {
            $prevState = array('xray' => null, 'token' => 'ok', 'consec_fail' => 0);
        }
        $resp = xui_api_token_request($location, 'GET', '/panel/api/server/status', null, 6, 'uptime_monitor');
        $httpStatus = intval($resp['status'] ?? 0);
        $newState = $prevState;
        $panelName = htmlspecialchars((string)($location['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($httpStatus === 401 || $httpStatus === 403) {
            $newState['consec_fail'] = intval($prevState['consec_fail'] ?? 0) + 1;
            if ($newState['consec_fail'] >= 2 && ($prevState['token'] ?? 'ok') !== 'failed') {
                foreach ($admin_ids as $admin) {
                    sendmessage($admin, faoxima_render_text(faoxima_textbot_get('dyn_uptime_api_access_error_tpl', "⚠️ خطای دسترسی API\n\nتوکن API پنل «{panel_name}» معتبر نیست یا دسترسی آن غیرفعال شده است.\nلطفاً اطلاعات اتصال پنل را بررسی و توکن جدید ثبت کنید."), ['panel_name' => $panelName]), null, 'html');
                }
                $newState['token'] = 'failed';
            }
        } elseif (!empty($resp['error']) || $httpStatus < 200 || $httpStatus >= 300) {
            $newState['consec_fail'] = intval($prevState['consec_fail'] ?? 0) + 1;
        } else {
            $newState['consec_fail'] = 0;
            if (($prevState['token'] ?? 'ok') === 'failed') {
                foreach ($admin_ids as $admin) {
                    sendmessage($admin, faoxima_render_text(faoxima_textbot_get('dyn_uptime_api_access_restored_tpl', '✅ دسترسی API پنل «{panel_name}» دوباره برقرار شد.'), ['panel_name' => $panelName]), null, 'html');
                }
            }
            $newState['token'] = 'ok';
            $decoded = json_decode($resp['body'] ?? '', true);
            $xrayState = is_array($decoded) ? ($decoded['obj']['xray']['state'] ?? null) : null;
            if ($xrayState !== null && $xrayState !== ($prevState['xray'] ?? null)) {
                if ($xrayState === 'stopped') {
                    foreach ($admin_ids as $admin) {
                        sendmessage($admin, faoxima_render_text(faoxima_textbot_get('dyn_uptime_xray_stopped_tpl', '🚨 Xray روی پنل «{panel_name}» متوقف شده است؛ کاربران این پنل قطع هستند.'), ['panel_name' => $panelName]), null, 'html');
                    }
                } elseif (($prevState['xray'] ?? null) === 'stopped') {
                    foreach ($admin_ids as $admin) {
                        sendmessage($admin, faoxima_render_text(faoxima_textbot_get('dyn_uptime_xray_restored_tpl', '✅ Xray روی پنل «{panel_name}» دوباره فعال شد.'), ['panel_name' => $panelName]), null, 'html');
                    }
                }
            }
            $newState['xray'] = $xrayState;
        }

        if ($newState != $prevState) {
            update("marzban_panel", "xui_monitor_state", json_encode($newState), "code_panel", $location['code_panel']);
        }
    }
}

$admin_ids = select("admin", "id_admin",null,null,"FETCH_COLUMN");
$marzbanlist = select("marzban_panel", "*",null ,null ,"fetchAll");
$setting = select("setting", "*");
$status_cron = json_decode($setting['cron_status'] ?? '', true);
if (!is_array($status_cron) || empty($status_cron['uptime_panel'])) return;
$inbounds = [];
foreach($marzbanlist as $location){
    $parsed_url = parse_url($location['url_panel']);
    if ($parsed_url && isset($parsed_url['host'])) {
    $address = $parsed_url['host'];
    $port = empty($parsed_url['port']) ? 443 : $parsed_url['port'];
    if (!checkConnection($address, $port)) {
       foreach ($admin_ids as $admin) {
            $textnode = faoxima_render_text(faoxima_textbot_get('dyn_uptime_panel_not_connected_tpl', '🚨 ادمین عزیز پنل با اسم <code>{panel_name}</code> متصل نیست.'), ['panel_name' => $location['name_panel']]);
        sendmessage($admin, $textnode, null, 'html');
    }
    } elseif (($location['type'] ?? '') === 'x-ui_single' && function_exists('xui_panel_uses_token') && xui_panel_uses_token($location)) {
        rx_uptime_xui_monitor($location, $admin_ids);
    }
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('uptime_panel_finished');
}
