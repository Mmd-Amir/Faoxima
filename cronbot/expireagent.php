<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('expireagent', 180);

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

$setting = select("setting", "*");
$otherreport = select("topicid","idreport","report","otherreport","select")['idreport'];

$stmt = $pdo->prepare("SELECT * FROM user WHERE expire IS NOT NULL");
$stmt->execute();
while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $time_expire = $user['expire'] - time();
    if($time_expire < 0){
    $textexpire = "📌 نماینده عزیز زمان نمایندگی شما به پایان. رسید و حساب شما از حالت نمایندگی خارج گردید. چهت فعالسازی مجدد نمایندگی می توانید با پشتیبانی در ارتباط باشید.";
    sendmessage($user['id'],$textexpire, null, 'HTML');
    update("user","agent","f","id",$user['id']);
    update("user","expire",null,"id",$user['id']);
    $textreport = "📌 گروه کاربری کاربر بدلیل انقضای زمان نمایندگی  به f تغییر پیدا کرد

آیدی عددی کاربر :  {$user['id']}
نام کاربری کاربر :‌ {$user['username']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
    }

}
