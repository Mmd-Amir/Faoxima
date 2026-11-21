<?php

namespace Faoxima\Cron\Runtime;

use PDO;
use Throwable;

final class PollRun
{
    /**
     * Cursor-paged gateway poll: select a page (plus optional extra rows), defer on selection failure,
     * complete when idle, run the batch with per-row error logging and cursor advance, then complete.
     *
     * @param callable(string, mixed): array $page      page loader used by CronBatches::rx_cron_payment_cursor_page
     * @param callable(array): void          $handler   per-row handler
     * @param null|callable(): array         $extraRows additional rows appended after the cursor page
     */
    public static function cursorBatch(PDO $pdo, string $jobLabel, string $pollKey, callable $page, callable $handler, string $doneReason, ?callable $extraRows = null): void
    {
        try {
            $rows = CronBatches::rx_cron_payment_cursor_page($pdo, $pollKey, $page);
            if ($extraRows !== null) {
                $rows = array_merge($rows, $extraRows());
            }
        } catch (Throwable $e) {
            error_log('[' . $jobLabel . '] select pending failed: ' . $e->getMessage());
            TaskState::rx_cron_task_defer('select_failed', 120);
            return;
        }

        if (empty($rows)) {
            TaskState::rx_cron_task_complete('no_pending_payments');
            return;
        }

        CronBatches::rx_cron_run_poll_batch(
            $rows,
            $handler,
            static function (array $row, Throwable $e) use ($pollKey): void {
                error_log('[' . $pollKey . '] order ' . (string) ($row['id_order'] ?? '') . ': ' . $e->getMessage());
            },
            static function (array $row) use ($pollKey): void {
                if ((int) ($row['id'] ?? 0) > 0) {
                    CronBatches::rx_cron_invoice_cursor($pollKey, (string) $row['id']);
                }
            }
        );
        TaskState::rx_cron_task_complete($doneReason);
    }
}
