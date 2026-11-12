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
$notifiedFile = __DIR__ . '/gift_notified.json';

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
    if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
$pendingNotifyFile = __DIR__ . '/gift_notify_pending.json';
$pendingNotify = [];
if (is_file($pendingNotifyFile)) {
    $pendingNotify = json_decode((string) file_get_contents($pendingNotifyFile), true);
    if (!is_array($pendingNotify)) {
        rx_cron_task_defer('gift_notification_queue_invalid', 300);
        return;
    }
}
$savePendingNotify = function () use (&$pendingNotify, $pendingNotifyFile): void {
    $encoded = json_encode($pendingNotify, JSON_UNESCAPED_UNICODE);
    $tmp = $pendingNotifyFile . '.tmp';
    if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $pendingNotifyFile)) {
        throw new RuntimeException('gift_notification_persist_failed');
    }
};
$deliverGiftNotification = function ($key) use (&$pendingNotify, $savePendingNotify): bool {
    $entry = $pendingNotify[$key] ?? null;
    if (is_array($entry) && ($entry['state'] ?? '') === 'sent') {
        return true;
    }
    if (!is_array($entry) || ($entry['state'] ?? 'uncertain') !== 'pending' || (int) ($entry['next_attempt_at'] ?? 0) > time()) {
        return false;
    }
    $pendingNotify[$key]['state'] = 'sending';
    $pendingNotify[$key]['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
    $savePendingNotify();
    try {
        $result = telegram('sendmessage', [
            'chat_id' => $entry['id_user'],
            'text' => (string) $entry['text'],
            'parse_mode' => 'HTML',
            '_rx_no_network_retry' => true,
        ]);
    } catch (Throwable $e) {
        $result = ['ok' => false, 'description' => $e->getMessage()];
    }
    if (is_array($result) && ($result['ok'] ?? false) === true) {
        $pendingNotify[$key]['state'] = 'sent';
        $pendingNotify[$key]['sent_at'] = time();
        $savePendingNotify();
        return true;
    }
    $code = (int) ($result['error_code'] ?? 0);
    $pendingNotify[$key]['state'] = $code === 429 && $pendingNotify[$key]['attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
    $pendingNotify[$key]['next_attempt_at'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
    $pendingNotify[$key]['last_error'] = substr((string) ($result['description'] ?? 'no_response'), 0, 200);
    $savePendingNotify();
    return false;
};
$rxGiftNotificationCount = 0;
foreach (array_keys($pendingNotify) as $key) {
    if (rx_cron_time_up() || $rxGiftNotificationCount >= 5) {
        break;
    }
    if (($pendingNotify[$key]['state'] ?? 'uncertain') === 'pending' && (int) ($pendingNotify[$key]['next_attempt_at'] ?? 0) <= time()) {
        $rxGiftNotificationCount++;
        $deliverGiftNotification($key);
    }
}
$rxGiftHasPending = static function () use (&$pendingNotify): bool {
    foreach ($pendingNotify as $entry) {
        if (is_array($entry) && ($entry['state'] ?? '') === 'pending') {
            return true;
        }
    }
    return false;
};
if(!is_file($giftFile) || !is_file($queueFile)) {
    if ($rxGiftHasPending()) {
        rx_cron_task_defer('gift_notifications_pending', 60);
    }
    if (function_exists('rx_cron_task_complete')) {
        rx_cron_task_complete('gift_queue_absent');
    }
    return;
}


$userid = json_decode(file_get_contents($queueFile));
if (!is_array($userid)) $userid = [];
if(is_file($giftFile)){
$info = json_decode(file_get_contents($giftFile),true);
}
if(count($userid) == 0){
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    sendmessage($info['id_admin'], faoxima_textbot_get('dyn_gift_all_done', '📌 عملیات برای تمامی سرویس های درخواستی انجام شد.'), null, 'HTML');
    unlink($giftFile);
    unlink($queueFile);
    @unlink($notifiedFile);
    }
    if ($rxGiftHasPending()) {
        rx_cron_task_defer('gift_notifications_pending', 60);
    }
    if (function_exists('rx_cron_task_complete')) {
        rx_cron_task_complete('gift_nothing_to_do');
    }
    return;

}

if(!isset($info['typegift'])) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('gift_metadata_invalid', 600);
    }
    return;
}

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

$notifiedUsers = [];
if (is_file($notifiedFile)) {
    $notifiedUsers = json_decode(file_get_contents($notifiedFile), true) ?: [];
}

if (empty($info['cron_campaign_id'])) {
    $info['cron_campaign_id'] = bin2hex(random_bytes(16));
    $tmp = $giftFile . '.tmp';
    $encoded = json_encode($info, JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $giftFile)) {
        rx_cron_task_defer('gift_campaign_persist_failed', 120);
        return;
    }
}
$rxGiftCampaign = (string) $info['cron_campaign_id'];
$notifyUserOnce = function ($id_user, $text) use (&$notifiedUsers, &$pendingNotify, $notifiedFile, $savePendingNotify, $deliverGiftNotification, $rxGiftCampaign) {
    if ($id_user === null || $id_user === '') {
        return false;
    }
    $userKey = (string) $id_user;
    if (in_array($userKey, $notifiedUsers, true)) {
        return true;
    }
    $key = $rxGiftCampaign . ':' . $userKey;
    if (!isset($pendingNotify[$key])) {
        $pendingNotify[$key] = ['id_user' => $userKey, 'text' => (string) $text, 'state' => 'pending', 'attempts' => 0, 'next_attempt_at' => 0];
        $savePendingNotify();
    }
    if (!$deliverGiftNotification($key)) {
        return false;
    }
    $notifiedUsers[] = $userKey;
    file_put_contents($notifiedFile, json_encode(array_values(array_unique($notifiedUsers)), JSON_UNESCAPED_UNICODE), LOCK_EX);
    return true;
};

$applyingFile = __DIR__ . '/gift_applying.json';
$applying = [];
if (is_file($applyingFile)) {
    $applying = json_decode(file_get_contents($applyingFile), true);
    if (!is_array($applying)) {
        rx_cron_task_defer('gift_operation_guards_invalid', 300);
        return;
    }
}
$saveApplying = function () use (&$applying, $applyingFile) {
    $encoded = json_encode($applying, JSON_UNESCAPED_UNICODE);
    $tmp = $applyingFile . '.tmp';
    if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $applyingFile)) {
        throw new RuntimeException('gift_operation_guard_persist_failed');
    }
};

$batchSize = 5;
$processed = 0;

while (!empty($userid) && $processed < $batchSize) {
    if (rx_cron_time_up()) {
        rx_cron_task_defer('time_budget', 60);
        break;
    }
    $iduser = array_shift($userid);
    try {
    if (!isset($iduser->username)) {
        continue;
    }

    $processed++;
    $get_username_info = $ManagePanel->DataUser($info['name_panel'], $iduser->username);

    $rxGiftOperation = $rxGiftCampaign . ':' . (string) $iduser->username;
    if (in_array('done:' . $rxGiftOperation, array_map('strval', $applying), true)) {
        continue;
    }
    if (in_array($rxGiftOperation, array_map('strval', $applying), true) || in_array((string) $iduser->username, array_map('strval', $applying), true)) {
        $logFailedUser($iduser->username);
        continue;
    }

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

    $applying[] = $rxGiftOperation;
    $applying = array_values(array_unique($applying));
    $saveApplying();

    if ($info['typegift'] == "volume") {
        $extra_volume = $ManagePanel->extra_volume($invoce['username'], $marzban_list_get['code_panel'], $info['value']);
        if ($extra_volume['status'] == false) {
            $hadPersistentError = true;
            $extra_volume['msg'] = rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
            $textreports = faoxima_render_text(faoxima_textbot_get('dyn_gift_error_report_tpl', "خطای اضافه شدن هدیه حجم\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                'panel_name' => $marzban_list_get['name_panel'],
                'username' => $iduser->username,
                'reason' => $extra_volume['msg'],
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            $notifyUserOnce($invoce['id_user'], $info['text']);
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
            $extra_time['msg'] = rx_panel_error_text($extra_time['msg'] ?? null, $extra_time['detail'] ?? null);
            $textreports = faoxima_render_text(faoxima_textbot_get('dyn_gift_error_report_tpl', "خطای اضافه شدن هدیه حجم\n<blockquote>نام پنل : {panel_name}</blockquote>\n<blockquote>نام کاربری سرویس : {username}</blockquote>\n<blockquote>دلیل خطا : {reason}</blockquote>"), [
                'panel_name' => $marzban_list_get['name_panel'],
                'username' => $iduser->username,
                'reason' => $extra_time['msg'],
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            $notifyUserOnce($invoce['id_user'], $info['text']);
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

    if (!$hadPersistentError) {
        $applying = array_values(array_diff($applying, [$rxGiftOperation]));
        $applying[] = 'done:' . $rxGiftOperation;
        $saveApplying();
    }

    if ($hadPersistentError) {
        $logFailedUser($iduser->username);
    }
    } finally {
        $encoded = json_encode(array_values($userid), JSON_UNESCAPED_UNICODE);
        $tmp = $queueFile . '.tmp';
        if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $queueFile)) {
            throw new RuntimeException('gift_queue_persist_failed');
        }
    }
}

if (empty($userid)) {
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    $successText = faoxima_textbot_get('dyn_gift_all_done', '📌 عملیات برای تمامی سرویس های درخواستی انجام شد.');
    if (!empty($failedUsers)) {
        $failedList = implode("\n", $failedUsers);
        $successText .= faoxima_render_text(faoxima_textbot_get('dyn_gift_failed_users_suffix_tpl', "\n⚠️ کاربران با خطای اعمال هدیه:\n{list}"), ['list' => $failedList]);
    }
    sendmessage($info['id_admin'], $successText, null, 'HTML');
    unlink($giftFile);
    unlink($queueFile);
    @unlink($notifiedFile);
    if (!$pendingNotify) {
        @unlink($pendingNotifyFile);
    }
    if (!$applying) {
        @unlink($applyingFile);
    }
    if (empty($failedUsers) && is_file($failedFile)) {
        unlink($failedFile);
    }
    }
}
if ($rxGiftHasPending()) {
    rx_cron_task_defer('gift_notifications_pending', 60);
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('gift_batch_finished');
}

