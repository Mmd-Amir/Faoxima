<?php

namespace Faoxima\Cron\Jobs;

use PDO;

final class ExpireagentJob
{
    public static function run()
    {
        global $otherreport, $pdo, $setting, $user;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('expireagent', 180);

ini_set('error_log', 'error_log');
require_once (dirname(__DIR__, 3) . '/cronbot') . '/../config.php';
require_once (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php';
require_once (dirname(__DIR__, 3) . '/cronbot') . '/../function.php';

if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('expireagent')) {
    return;
}

$setting = \Faoxima\Core\Database\Db::select("setting", "*");
$otherreport = \Faoxima\Core\Database\Db::select("topicid","idreport","report","otherreport","select")['idreport'];

$now = time();
$stmt = $pdo->prepare("SELECT id, username FROM user WHERE expire IS NOT NULL AND expire < :now ORDER BY expire ASC LIMIT 100");
$stmt->execute([':now' => $now]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$claimStmt = $pdo->prepare("UPDATE user SET agent = 'f', expire = NULL WHERE id = :id AND expire IS NOT NULL AND expire < :now");

foreach ($rows as $user) {
    $claimStmt->execute([':id' => $user['id'], ':now' => $now]);
    if ($claimStmt->rowCount() !== 1) {
        continue;
    }

    $textexpire = "📌 نماینده عزیز زمان نمایندگی شما به پایان. رسید و حساب شما از حالت نمایندگی خارج گردید. چهت فعالسازی مجدد نمایندگی می توانید با پشتیبانی در ارتباط باشید.";
    sendmessage($user['id'],$textexpire, null, 'HTML');
    $textreport = "📌 گروه کاربری کاربر بدلیل انقضای زمان نمایندگی  به f تغییر پیدا کرد

<blockquote>آیدی عددی کاربر :  {$user['id']}</blockquote>
<blockquote>نام کاربری کاربر :‌ {$user['username']}</blockquote>";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
}
if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('expireagent_finished');
}
    }
}
