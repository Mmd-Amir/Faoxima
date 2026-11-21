<?php

namespace Faoxima\Cron\Jobs;

use PDO;

final class StatusdayJob
{
    public static function run()
    {
        global $pdo, $setting;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('statusday', 240);

require_once (dirname(__DIR__, 3) . '/cronbot') . '/../config.php';
require_once (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php';
require_once (dirname(__DIR__, 3) . '/cronbot') . '/../function.php';

if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('statusday')) {
    return;
}

$setting = \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select');

$reportnightRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'reportnight', 'select');
$reportnight    = $reportnightRow['idreport'] ?? null;

if (empty($setting['Channel_Report'])) {
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('statusday_no_report_channel');
    }
    return;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_defer')) {
        \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('pdo_unavailable', 180);
    }
    return;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rxStatusOccurrence = \Faoxima\Cron\Runtime\TaskState::rx_cron_task_occurrence(time());
$report = new \Faoxima\Cron\Services\DailyReport($pdo, \Faoxima\Cron\Services\DailyReport::referenceTimestamp($rxStatusOccurrence));

$chatId = $setting['Channel_Report'];
$rxStatusAllSent = true;
foreach ($report->messages() as $message) {
    $rxStatusSend = telegram('sendmessage', [
        'chat_id'           => $chatId,
        'message_thread_id' => $reportnight,
        'text'              => $message['text'],
        'parse_mode'        => 'HTML',
    ]);
    if (!(is_array($rxStatusSend) && ($rxStatusSend['ok'] ?? false) === true)) {
        $rxStatusAllSent = false;
    }
}
if ($rxStatusAllSent) {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('statusday_report_sent');
} else {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_uncertain('statusday_delivery_uncertain', 1800);
}
    }
}
