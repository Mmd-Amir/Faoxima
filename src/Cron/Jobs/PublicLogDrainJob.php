<?php

namespace Faoxima\Cron\Jobs;

use PDO;
use Throwable;

final class PublicLogDrainJob
{
    public static function run()
    {
        global $pdo;

require_once (dirname(__DIR__, 3) . '/cronbot') . '/_init.php';
        \Faoxima\Cron\Runtime\CronRuntime::rx_cron_boot('public_log_drain', 120);

        ini_set('error_log', 'error_log');
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_require_or_skip('public_log_drain', [
            (dirname(__DIR__, 3) . '/cronbot') . '/../config.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../botapi.php',
            (dirname(__DIR__, 3) . '/cronbot') . '/../function.php',
        ])) {
            return;
        }
        if (!\Faoxima\Cron\Runtime\CronRuntime::rx_cron_db_ready('public_log_drain')) {
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM PublicLog_Queue WHERE sent_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT 40");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $first = true;
        foreach ($rows as $row) {
            if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) break;

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
        if (method_exists(\Faoxima\Cron\Runtime\TaskState::class, 'rx_cron_task_complete')) {
            \Faoxima\Cron\Runtime\TaskState::rx_cron_task_complete('public_log_drain_finished');
        }
    }
}
