<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('pin_expire', 120);

if (!rx_cron_require_or_skip('pin_expire', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('pin_expire')) {
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
        if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
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
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('pin_expire_finished');
}
