<?php

namespace Faoxima\Cron\Jobs;

use PDO;
use Throwable;

final class PinExpireJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('pin_expire', 120);

        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('pin_expire', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('pin_expire')) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id, chat_id, message_id FROM pinned_messages WHERE unpin_at IS NOT NULL AND unpin_at <= :now ORDER BY id ASC LIMIT 200"
            );
            $stmt->execute([':now' => time()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $deleteStmt = $pdo->prepare("DELETE FROM pinned_messages WHERE id = :id");

            foreach ($rows as $row) {
                if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
                    break;
                }
                try {
                    telegram('unpinChatMessage', [
                        'chat_id'    => $row['chat_id'],
                        'message_id' => (int) $row['message_id'],
                    ]);
                } catch (Throwable $e) {
                    error_log('[pin_expire] unpin failed for chat_id=' . $row['chat_id'] . ' message_id=' . $row['message_id'] . ' — ' . $e->getMessage());
                }
                $deleteStmt->execute([':id' => $row['id']]);
            }
        } catch (Throwable $e) {
            error_log('[pin_expire] ' . $e->getMessage());
        }
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('pin_expire_finished');
        }
    }
}
