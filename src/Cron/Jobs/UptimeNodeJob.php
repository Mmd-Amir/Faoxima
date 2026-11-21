<?php

namespace Faoxima\Cron\Jobs;

final class UptimeNodeJob
{
    public static function run()
    {
        global $errorreport, $setting, $status_cron;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('uptime_node', 180);
        
        ini_set('error_log', 'error_log');
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../config.php';
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        date_default_timezone_set('Asia/Tehran');
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php';
        require_once (dirname(__DIR__, 3) . '/cronbot') . '/../function.php';
        
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('uptime_node')) {
            return;
        }
        
        $errorreport = \Faoxima\Core\Database\Db::select("topicid","idreport","report","errorreport","select")['idreport'];
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        if (!is_array($setting)) {
            error_log('uptime_node: setting row is not available.');
            return;
        }
        
        if (!array_key_exists('cron_status', $setting)) {
            error_log('uptime_node: cron_status is missing from setting.');
            return;
        }
        
        $status_cron = json_decode($setting['cron_status'], true);
        if (!is_array($status_cron)) {
            error_log('uptime_node: invalid cron_status configuration.');
            return;
        }
        
        if (empty($status_cron['uptime_node'])) {
            return;
        }
        
        
        $marzbanlist = \Faoxima\Core\Database\Db::select("marzban_panel", "*","type" ,"marzban" ,"fetchAll");
        $marzbanlist = is_array($marzbanlist) ? $marzbanlist : [];
        \Faoxima\Cron\Monitoring\Uptime::rxUptimeCheckPanels($marzbanlist, $errorreport, $setting, [\Faoxima\Services\Panels\Drivers\Marzban::class, 'Get_Nodes']);
        
        $pasarguardlist = \Faoxima\Core\Database\Db::select("marzban_panel", "*","type" ,"pasarguard" ,"fetchAll");
        $pasarguardlist = is_array($pasarguardlist) ? $pasarguardlist : [];
        if (method_exists(\Faoxima\Services\Panels\Drivers\PasarGuard::class, 'pasarguardGetNodes')) {
            \Faoxima\Cron\Monitoring\Uptime::rxUptimeCheckPanels($pasarguardlist, $errorreport, $setting, [\Faoxima\Services\Panels\Drivers\PasarGuard::class, 'pasarguardGetNodes']);
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('uptime_node_finished');
        }
    }
}
