<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('queued_renewal', 600);

ini_set('error_log', 'error_log');

if (!rx_cron_require_or_skip('queued_renewal', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('queued_renewal')) {
    return;
}

$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$reportCron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];

list($w, $n) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$shard = ($n > 1) ? " AND MOD(id, $n) = $w " : "";
$stmt = $pdo->prepare("SELECT * FROM queued_renewal WHERE status = 'pending' $shard ORDER BY id LIMIT 30");
$stmt->execute();
$queuedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($queuedRows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;

    $userData = $ManagePanel->DataUser($row['name_panel'], $row['username']);
    if (!is_array($userData) || $userData['status'] == "Unsuccessful") {
        continue;
    }

    $dataLimit = (float)($userData['data_limit'] ?? 0);
    $usedTraffic = (float)($userData['used_traffic'] ?? 0);
    $expire = (int)($userData['expire'] ?? 0);

    $remaining = $dataLimit - $usedTraffic;
    $volumeTrigger = $dataLimit > 0 && $remaining <= 0;
    $timeTrigger = $expire > 0 && ($expire - time()) < 86400;

    if (!$volumeTrigger && !$timeTrigger) {
        continue;
    }

    $extend = $ManagePanel->extend("ریست حجم و زمان", $row['new_limit_gb'], $row['time_day'], $row['username'], $row['code_product'], $row['code_panel']);

    if (is_array($extend) && ($extend['status'] ?? false) === true) {
        update("queued_renewal", "status", "applied", "id", $row['id']);
        update("queued_renewal", "applied_at", date('Y/m/d H:i:s'), "id", $row['id']);
        update("queued_renewal", "output", json_encode($extend, JSON_UNESCAPED_UNICODE), "id", $row['id']);

        $message = faoxima_render_text(faoxima_textbot_get('dyn_queued_renewal_activated_tpl', "با سلام خدمت شما کاربر گرامی 👋\n✅ رزرو اشتراک سرویس {username} فعال شد و حجم و زمان سرویس شما تمدید گردید."), [
            'username' => $row['username'],
        ]);
        sendmessage($row['id_user'], $message, null, 'HTML', $row['bottype']);

        if (!empty($setting['Channel_Report'])) {
            $reportMessage = "📌 اطلاعیه فعال‌سازی رزرو اشتراک\n\n<blockquote>نام کاربری سرویس : <code>{$row['username']}</code></blockquote>\n<blockquote>نام پنل : {$row['name_panel']}</blockquote>";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportCron,
                'text' => $reportMessage,
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        $errorMsg = is_array($extend) ? rx_panel_error_text($extend['msg'] ?? $extend, $extend['detail'] ?? null) : htmlspecialchars((string)$extend, ENT_QUOTES, 'UTF-8');
        update("queued_renewal", "status", "failed", "id", $row['id']);
        update("queued_renewal", "output", $errorMsg, "id", $row['id']);

        if (!empty($setting['Channel_Report'])) {
            $reportMessage = "❌ خطای فعال‌سازی رزرو اشتراک\n\n<blockquote>نام کاربری سرویس : <code>{$row['username']}</code></blockquote>\n<blockquote>نام پنل : {$row['name_panel']}</blockquote>\n<blockquote>دلیل خطا : $errorMsg</blockquote>";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $reportCron,
                'text' => $reportMessage,
                'parse_mode' => "HTML"
            ]);
        }
    }
}
