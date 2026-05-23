<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('gift', 240);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('gift', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('gift')) {
    return;
}
$ManagePanel = new ManagePanel();

$giftFile = __DIR__ . '/gift';
$queueFile = __DIR__ . '/username.json';

$setting = select("setting", "*");
$errorreport = select("topicid","idreport","report","errorreport","select")['idreport'];

$datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
$datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'text_usertest' => '',
    'text_support' => '',
    'text_help' => '',
    'text_sell' => '',
    'text_affiliates' => '',
    'text_Add_Balance' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
if(!is_file($giftFile))return;
if(!is_file($queueFile))return;


$userid = json_decode(file_get_contents($queueFile));
if(is_file($giftFile)){
$info = json_decode(file_get_contents($giftFile),true);
}
if(count($userid) == 0){
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    sendmessage($info['id_admin'], "📌 عملیات برای تمامی سرویس های درخواستی انجام شد.", null, 'HTML');
    unlink($giftFile);
    unlink($queueFile);
    }
    return;

}

if(!isset($info['typegift']))return;

$failedFile = __DIR__ . '/gift_failed.json';
$failedUsers = [];
if (is_file($failedFile)) {
    $failedUsers = json_decode(file_get_contents($failedFile), true) ?: [];
}

$logFailedUser = function ($username) use (&$failedUsers, $failedFile) {
    $failedUsers[] = $username;
    $failedUsers = array_values(array_unique($failedUsers));
    file_put_contents($failedFile, json_encode($failedUsers, JSON_UNESCAPED_UNICODE));
};

$batchSize = 5;
$processed = 0;

while (!empty($userid) && $processed < $batchSize) {
    $iduser = array_shift($userid);
    file_put_contents($queueFile, json_encode(array_values($userid), JSON_UNESCAPED_UNICODE));
    if (!isset($iduser->username)) {
        continue;
    }

    $processed++;
    $get_username_info = $ManagePanel->DataUser($info['name_panel'], $iduser->username);

    if (!is_array($get_username_info)) {
        $logFailedUser($iduser->username);
        continue;
    }

    if (($get_username_info['status'] ?? '') === 'Unsuccessful') {
        $logFailedUser($iduser->username);
        continue;
    }

    $hasExpire = array_key_exists('expire', $get_username_info) && $get_username_info['expire'] !== null && $get_username_info['expire'] !== '';
    if (!$hasExpire) {
        $logFailedUser($iduser->username);
        continue;
    }

    $hasDataLimit = array_key_exists('data_limit', $get_username_info) && $get_username_info['data_limit'] !== null && $get_username_info['data_limit'] !== '';
    if (!$hasDataLimit) {
        $logFailedUser($iduser->username);
        continue;
    }

    $hadPersistentError = false;

    $invoce = select("invoice", "*", "username", $iduser->username, "select");
    if (!is_array($invoce) || !isset($invoce['id_user']) || empty($invoce['id_user'])) {
        $logFailedUser($iduser->username);
        continue;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $info['name_panel'], "select");

    if ($info['typegift'] == "volume") {
        $extra_volume = $ManagePanel->extra_volume($invoce['username'], $marzban_list_get['code_panel'], $info['value']);
        if ($extra_volume['status'] == false) {
            $hadPersistentError = true;
            $extra_volume['msg'] = json_encode($extra_volume['msg']);
            $textreports = "خطای اضافه شدن هدیه حجم\nنام پنل : {$marzban_list_get['name_panel']}\nنام کاربری سرویس : {$iduser->username}\nدلیل خطا : {$extra_volume['msg']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            sendmessage($invoce['id_user'], $info['text'], null, "html");
        }

        $data_for_database = json_encode(array(
            'volume_value' => $info['value'],
            'old_volume' => $get_username_info['data_limit'],
            'expire_old' => $get_username_info['expire']
        ));
        $volumepricelast = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
        $value = $data_for_database;
        $dateacc = date('Y/m/d H:i:s');
        $type = "gift_volume";
        $stmt->execute([
            ':id_user' => $invoce['id_user'],
            ':username' => $invoce['username'],
            ':value' => $value,
            ':type' => $type,
            ':time' => $dateacc,
            ':price' => $volumepricelast,
            ':output' => json_encode($extra_volume)
        ]);
    } else {
        $extra_time = $ManagePanel->extra_time($get_username_info['username'], $marzban_list_get['code_panel'], intval($info['value']));
        if ($extra_time['status'] == false) {
            $hadPersistentError = true;
            $extra_time['msg'] = json_encode($extra_time['msg']);
            $textreports = "خطای اضافه شدن هدیه حجم\nنام پنل : {$marzban_list_get['name_panel']}\nنام کاربری سرویس : {$iduser->username}\nدلیل خطا : {$extra_time['msg']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            sendmessage($invoce['id_user'], $info['text'], null, "html");
        }

        $data_for_database = json_encode(array(
            'time_value' => $info['value'],
            'old_volume' => $get_username_info['data_limit'],
            'expire_old' => $get_username_info['expire']
        ));
        $volumepricelast = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
        $value = $data_for_database;
        $dateacc = date('Y/m/d H:i:s');
        $type = "gift_time";
        $stmt->execute([
            ':id_user' => $invoce['id_user'],
            ':username' => $invoce['username'],
            ':value' => $value,
            ':type' => $type,
            ':time' => $dateacc,
            ':price' => $volumepricelast,
            ':output' => json_encode($extra_time)
        ]);
    }

    if ($hadPersistentError) {
        $logFailedUser($iduser->username);
    }
}

if (empty($userid)) {
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    $successText = "📌 عملیات برای تمامی سرویس های درخواستی انجام شد.";
    if (!empty($failedUsers)) {
        $failedList = implode("\n", $failedUsers);
        $successText .= "\n⚠️ کاربران با خطای اعمال هدیه:\n" . $failedList;
    }
    sendmessage($info['id_admin'], $successText, null, 'HTML');
    unlink($giftFile);
    unlink($queueFile);
    if (empty($failedUsers) && is_file($failedFile)) {
        unlink($failedFile);
    }
    }
}

