<?php

namespace Faoxima\Cron\Jobs;

use Throwable;

final class DiscountExpireJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
\Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('discount_expire', 600);

ini_set('error_log', 'error_log');
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('discount_expire', [
    (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
    (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
])) {
    return;
}
if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('discount_expire')) {
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
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('discount_sell_update_failed', 120);
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
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_defer('discount_gift_update_failed', 120);
}
if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
    \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('discount_expire_finished');
}
    }
}
