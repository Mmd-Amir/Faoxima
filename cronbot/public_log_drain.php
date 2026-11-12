<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('public_log_drain', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('public_log_drain', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('public_log_drain')) {
    return;
}

$stmt = $pdo->prepare("SELECT * FROM PublicLog_Queue WHERE sent_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT 40");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$first = true;
foreach ($rows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) break;

    if (!$first) {
        usleep(1100000);
    }
    $first = false;

    $ok = false;
    try {
        $res = telegram('sendmessage', array_filter([
            'chat_id'      => $row['chat_id'],
            'text'         => $row['text'],
            'parse_mode'   => 'HTML',
            'reply_markup' => $row['reply_markup'],
        ], static fn($v) => $v !== null && $v !== ''));
        $ok = is_array($res) && !empty($res['ok']);
    } catch (Throwable $e) {
        error_log('[public_log_drain] send failed for queue id ' . $row['id'] . ': ' . $e->getMessage());
    }

    if ($ok) {
        $upd = $pdo->prepare('UPDATE PublicLog_Queue SET sent_at = NOW() WHERE id = :id');
        $upd->execute([':id' => $row['id']]);
    } else {
        $upd = $pdo->prepare('UPDATE PublicLog_Queue SET attempts = attempts + 1 WHERE id = :id');
        $upd->execute([':id' => $row['id']]);
    }
}

try {
    $pdo->prepare("DELETE FROM PublicLog_Queue WHERE sent_at IS NOT NULL AND sent_at < (NOW() - INTERVAL 3 DAY)")->execute();
} catch (Throwable $e) {
    error_log('[public_log_drain] cleanup failed: ' . $e->getMessage());
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('public_log_drain_finished');
}
