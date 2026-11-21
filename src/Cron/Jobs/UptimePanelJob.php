<?php

namespace Faoxima\Cron\Jobs;

final class UptimePanelJob
{
    public static function run()
    {
        global $admin_ids, $setting, $status_cron;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('uptime_panel', 120);

        ini_set('error_log', 'error_log');
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../config.php';
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php';
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../function.php';

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('uptime_panel')) {
            return;
        }


        $admin_ids = \Faoxima\Core\Database\Db::select("admin", "id_admin",null,null,"FETCH_COLUMN");
        $marzbanlist = \Faoxima\Core\Database\Db::select("marzban_panel", "*",null ,null ,"fetchAll");
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        $status_cron = json_decode($setting['cron_status'] ?? '', true);
        if (!is_array($status_cron) || empty($status_cron['uptime_panel'])) return;
        $inbounds = [];
        foreach($marzbanlist as $location){
            $parsed_url = parse_url($location['url_panel']);
            if ($parsed_url && isset($parsed_url['host'])) {
            $address = $parsed_url['host'];
            $port = empty($parsed_url['port']) ? 443 : $parsed_url['port'];
            if (!\Faoxima\Telegram\Support\QrAndKeys::checkConnection($address, $port)) {
               foreach ($admin_ids as $admin) {
                    $textnode = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_uptime_panel_not_connected_tpl', '🚨 ادمین عزیز پنل با اسم <code>{panel_name}</code> متصل نیست.'), ['panel_name' => $location['name_panel']]);
                sendmessage($admin, $textnode, null, 'html');
            }
            } elseif (($location['type'] ?? '') === 'x-ui_single' && method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_panel_uses_token') && \Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($location)) {
                \Faoxima\Cron\Monitoring\Uptime::rx_uptime_xui_monitor($location, $admin_ids);
            }
            }
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('uptime_panel_finished');
        }
    }
}
