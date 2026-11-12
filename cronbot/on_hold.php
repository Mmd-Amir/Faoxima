<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('on_hold', 240);

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';

if (!rx_cron_db_ready('on_hold')) {
    return;
}

$ManagePanel = new ManagePanel();

$setting = select("setting", "*");

$rxOnHoldNotify = function ($username, $status) use ($pdo, $setting) {
    if ($status !== 'on_hold') {
        return;
    }
    $invoice = select("invoice", "*", "username", $username, "select");
    if ($invoice == false) {
        return;
    }
    if ($invoice['Status'] == "send_on_hold") {
        return;
    }
    $timebuyremin = (time() - $invoice['time_sell']) / 86400;
    if ($timebuyremin < $setting['on_hold_day']) {
        return;
    }
    $stmtSO = $pdo->prepare("SELECT * FROM service_other WHERE username = :username AND type = 'change_location'");
    $stmtSO->bindParam(':username', $username, PDO::PARAM_STR);
    $stmtSO->execute();
    if ($stmtSO->rowCount() != 0) {
        return;
    }
    $text = "سلام! 🌐

دیدیم که شما هنوز به کانفیگ خود با نام کاربری $username متصل نشده‌اید و بیش از {$setting['on_hold_day']} روز از فعال‌سازی آن گذشته است. اگر در راه‌اندازی یا استفاده از سرویس مشکلی دارید، لطفاً با تیم پشتیبانی ما  از طریق آیدی زیر در ارتباط باشید تا به شما کمک کنیم.
ما آماده‌ایم تا هر گونه سوال یا مشکلی را برطرف کنیم! 📞

اکانت پشتیبانی : @{$setting['id_support']}";
    sendmessage($invoice['id_user'], $text, null, 'HTML');
    update("invoice", "Status", "send_on_hold", "username", $username);
};

$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE type = 'marzban'  ORDER BY RAND() LIMIT 25");
$stmt->execute();
while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        break;
    }
    $users = getusers($panel['name_panel'], "on_hold")['users'] ?? null;
    if (!is_array($users)) continue;
    foreach ($users as $user) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break 2;
        if (($user['status'] ?? '') === "Unsuccessful") continue;
        $rxOnHoldNotify($user['username'], $user['status'] ?? '');
    }
}

if (function_exists('pasarguardGetUsersByStatus')) {
    $stmtPasarguard = $pdo->prepare("SELECT * FROM marzban_panel WHERE type = 'pasarguard' ORDER BY RAND() LIMIT 25");
    $stmtPasarguard->execute();
    while ($panel = $stmtPasarguard->fetch(PDO::FETCH_ASSOC)) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        $pasarguardResult = pasarguardGetUsersByStatus($panel['name_panel'], 'on_hold');
        if (empty($pasarguardResult['status']) || !is_array($pasarguardResult['users'] ?? null)) continue;
        foreach ($pasarguardResult['users'] as $user) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break 2;
            $rxOnHoldNotify($user['username'] ?? '', $user['status'] ?? '');
        }
    }
}

if (function_exists('rebeccaGetOnHoldUsers')) {
    $stmtRebecca = $pdo->prepare("SELECT * FROM marzban_panel WHERE type = 'rebecca' ORDER BY RAND() LIMIT 25");
    $stmtRebecca->execute();
    while ($panel = $stmtRebecca->fetch(PDO::FETCH_ASSOC)) {
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;
        $rebeccaResult = rebeccaGetOnHoldUsers($panel['name_panel']);
        if (empty($rebeccaResult['status']) || !is_array($rebeccaResult['users'] ?? null)) continue;
        foreach ($rebeccaResult['users'] as $user) {
            if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break 2;
            $rxOnHoldNotify($user['username'] ?? '', $user['status'] ?? '');
        }
    }
}
if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('time_budget', 60);
    }
} elseif (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('on_hold_finished');
}
