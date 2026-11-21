<?php

namespace Faoxima\Cron\Runtime;

use PDO;

final class PaymentJob
{
    public static function begin(string $job, int $budget): ?array
    {
        CronRuntime::rx_cron_boot($job, $budget);
        ini_set('error_log', 'error_log');
        $ctx = CronBatches::rx_cron_require_payment_context();
        if ($ctx === null) {
            return null;
        }
        global $pdo;
        if (!($pdo instanceof PDO)) {
            error_log('[' . $job . '] no PDO connection');
            return null;
        }
        return $ctx;
    }

    public static function requireGateway(string $job, string $label, array $handler, string $deferReason): bool
    {
        if (is_callable($handler)) {
            return true;
        }
        error_log('[' . $job . '] ' . $label . ' functions not available');
        if (method_exists(TaskState::class, 'rx_cron_task_defer')) {
            TaskState::rx_cron_task_defer($deferReason, 300);
        }
        return false;
    }
}
