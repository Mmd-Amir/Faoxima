<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('discount_expire', 600);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('discount_expire', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('discount_expire')) {
    return;
}

$now = time();

try {
    $chk = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'status'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare(
            "UPDATE DiscountSell
                SET status = 'expired'
              WHERE (status IS NULL OR status = 'active')
                AND time IS NOT NULL AND time <> '' AND time <> '0'
                AND CAST(time AS UNSIGNED) <= :now"
        );
        $stmt->execute([':now' => $now]);
    }
} catch (Throwable $e) {
    error_log('[discount_expire] sell: ' . $e->getMessage());
    rx_cron_task_defer('discount_sell_update_failed', 120);
}

try {
    $chk = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'expire_at'");
    if ($chk && $chk->rowCount() === 1) {
        $stmt = $pdo->prepare(
            "UPDATE Discount
                SET status = 'expired'
              WHERE (status IS NULL OR status = 'active')
                AND expire_at IS NOT NULL AND expire_at <> '' AND expire_at <> '0'
                AND CAST(expire_at AS UNSIGNED) <= :now"
        );
        $stmt->execute([':now' => $now]);
    }
} catch (Throwable $e) {
    error_log('[discount_expire] gift: ' . $e->getMessage());
    rx_cron_task_defer('discount_gift_update_failed', 120);
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('discount_expire_finished');
}
