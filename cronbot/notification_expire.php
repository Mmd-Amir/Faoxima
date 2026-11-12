<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('notification_expire', 120);

if (!rx_cron_require_or_skip('notification_expire', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('notification_expire')) {
    return;
}

try {
    $stmt = $pdo->prepare('DELETE FROM notification WHERE expires_at <= :now');
    $stmt->execute([':now' => time()]);
} catch (Throwable $e) {
    error_log('[notification_expire] ' . $e->getMessage());
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('notification_expire_failed', 120);
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('notification_expire_finished');
}
