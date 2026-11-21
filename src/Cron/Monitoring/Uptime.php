<?php

namespace Faoxima\Cron\Monitoring;

final class Uptime
{
    public static function rx_uptime_xui_monitor($location, $admin_ids)
    {
        $prevState = json_decode($location['xui_monitor_state'] ?? '', true);
        if (!is_array($prevState)) {
            $prevState = array('xray' => null, 'token' => 'ok', 'consec_fail' => 0);
        }
        $resp = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_api_token_request($location, 'GET', '/panel/api/server/status', null, 6, 'uptime_monitor');
        $httpStatus = intval($resp['status'] ?? 0);
        $newState = $prevState;
        $panelName = htmlspecialchars((string)($location['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($httpStatus === 401 || $httpStatus === 403) {
            $newState['consec_fail'] = intval($prevState['consec_fail'] ?? 0) + 1;
            if ($newState['consec_fail'] >= 2 && ($prevState['token'] ?? 'ok') !== 'failed') {
                foreach ($admin_ids as $admin) {
                    sendmessage($admin, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_uptime_api_access_error_tpl', "⚠️ خطای دسترسی API\n\nتوکن API پنل «{panel_name}» معتبر نیست یا دسترسی آن غیرفعال شده است.\nلطفاً اطلاعات اتصال پنل را بررسی و توکن جدید ثبت کنید."), ['panel_name' => $panelName]), null, 'html');
                }
                $newState['token'] = 'failed';
            }
        } elseif (!empty($resp['error']) || $httpStatus < 200 || $httpStatus >= 300) {
            $newState['consec_fail'] = intval($prevState['consec_fail'] ?? 0) + 1;
        } else {
            $newState['consec_fail'] = 0;
            if (($prevState['token'] ?? 'ok') === 'failed') {
                foreach ($admin_ids as $admin) {
                    sendmessage($admin, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_uptime_api_access_restored_tpl', '✅ دسترسی API پنل «{panel_name}» دوباره برقرار شد.'), ['panel_name' => $panelName]), null, 'html');
                }
            }
            $newState['token'] = 'ok';
            $decoded = json_decode($resp['body'] ?? '', true);
            $xrayState = is_array($decoded) ? ($decoded['obj']['xray']['state'] ?? null) : null;
            if ($xrayState !== null && $xrayState !== ($prevState['xray'] ?? null)) {
                if ($xrayState === 'stopped') {
                    foreach ($admin_ids as $admin) {
                        sendmessage($admin, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_uptime_xray_stopped_tpl', '🚨 Xray روی پنل «{panel_name}» متوقف شده است؛ کاربران این پنل قطع هستند.'), ['panel_name' => $panelName]), null, 'html');
                    }
                } elseif (($prevState['xray'] ?? null) === 'stopped') {
                    foreach ($admin_ids as $admin) {
                        sendmessage($admin, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_uptime_xray_restored_tpl', '✅ Xray روی پنل «{panel_name}» دوباره فعال شد.'), ['panel_name' => $panelName]), null, 'html');
                    }
                }
            }
            $newState['xray'] = $xrayState;
        }

        if ($newState != $prevState) {
            \Faoxima\Core\Database\Db::update("marzban_panel", "xui_monitor_state", json_encode($newState), "code_panel", $location['code_panel']);
        }
    }

    public static function rxUptimeCheckPanels($panels, $errorreport, $setting, $getNodesFn)
    {
        foreach ($panels as $location) {
            if (!is_array($location)) {
                continue;
            }
            $nodesResponse = $getNodesFn($location['name_panel']);
            if (!is_array($nodesResponse)) {
                continue;
            }
            if (!empty($nodesResponse['error'])) {
                error_log("uptime_node: " . $nodesResponse['error']);
                continue;
            }
            if (isset($nodesResponse['status']) && $nodesResponse['status'] != 200) {
                error_log("uptime_node: unexpected status code {$nodesResponse['status']} for {$location['name_panel']}");
                continue;
            }
            if (empty($nodesResponse['body'])) {
                continue;
            }
            $decodedNodes = json_decode($nodesResponse['body'], true);
            if (!is_array($decodedNodes)) {
                error_log("uptime_node: invalid nodes payload for {$location['name_panel']}");
                continue;
            }
            if (count($decodedNodes) == 0) {
                continue;
            }
            foreach ($decodedNodes as $data) {
                if (!is_array($data)) {
                    continue;
                }
                $status = $data['status'] ?? null;
                if (!in_array($status, ["connected", "disabled"], true)) {
                    $nodeName = $data['name'] ?? 'نامشخص';
                    $message = $data['message'] ?? 'بدون پیام';
                    $textnode = "🚨 ادمین عزیز نود با اسم {$nodeName} متصل نیست.
<blockquote>وضعیت نود : {$status}</blockquote>
<blockquote>✍️ دلیل خطا : <code> {$message}</code></blockquote>";
                    if (strlen($setting['Channel_Report']) > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $textnode,
                            'parse_mode' => "HTML"
                        ]);
                    }
                }
            }
        }
    }
}
