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
if (!rx_rn_ensure_schema()) {
    error_log('[queued_renewal] renewal schema unavailable; skipping run');
    if (function_exists('rx_cron_task_defer')) {
        rx_cron_task_defer('renewal_schema_unavailable', 300);
    }
    return;
}

$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$reportCron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];

$rxQrSendOnce = null;
$rxQrReport = function (string $text) use ($setting, $reportCron, &$rxQrSendOnce) {
    if (empty($setting['Channel_Report'])) {
        return true;
    }
    return $rxQrSendOnce('report:' . hash('sha256', (string) $setting['Channel_Report'] . '|' . (string) $reportCron . '|' . $text), [
        'kind' => 'report',
        'chat_id' => (string) $setting['Channel_Report'],
        'thread_id' => $reportCron,
        'text' => $text,
    ]);
};
$rxQrEsc = function ($value): string {
    return htmlspecialchars(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
};

$rxQrPendingFile = __DIR__ . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'renewal_notify_pending.json';
$rxQrPending = [];
if (is_file($rxQrPendingFile)) {
    $rxQrPending = json_decode((string) file_get_contents($rxQrPendingFile), true);
    if (!is_array($rxQrPending)) {
        rx_cron_task_defer('renewal_notification_queue_invalid', 300);
        return;
    }
}
$rxQrSavePending = function () use (&$rxQrPending, $rxQrPendingFile): void {
    $dir = dirname($rxQrPendingFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $encoded = json_encode($rxQrPending, JSON_UNESCAPED_UNICODE);
    $tmp = $rxQrPendingFile . '.tmp';
    if ($encoded === false || file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $rxQrPendingFile)) {
        throw new RuntimeException('renewal_notification_persist_failed');
    }
};
$rxQrDeliver = function (string $key) use (&$rxQrPending, $rxQrSavePending): bool {
    $entry = $rxQrPending[$key] ?? null;
    if (is_array($entry) && ($entry['state'] ?? '') === 'sent') {
        return true;
    }
    if (!is_array($entry) || ($entry['state'] ?? 'uncertain') !== 'pending' || (int) ($entry['next_attempt_at'] ?? 0) > time() || rx_cron_time_up()) {
        return false;
    }
    $rxQrPending[$key]['state'] = 'sending';
    $rxQrPending[$key]['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
    $rxQrSavePending();
    try {
        $result = telegram('sendmessage', [
            'chat_id' => (string) $entry['chat_id'],
            'message_thread_id' => $entry['thread_id'] ?? null,
            'text' => (string) $entry['text'],
            'parse_mode' => 'HTML',
            '_rx_no_network_retry' => true,
        ], ($entry['kind'] ?? '') === 'report' ? null : ($entry['bottype'] ?? null));
    } catch (Throwable $e) {
        $result = ['ok' => false, 'description' => $e->getMessage()];
    }
    if (is_array($result) && ($result['ok'] ?? false) === true) {
        $rxQrPending[$key]['state'] = 'sent';
        $rxQrSavePending();
        return true;
    }
    $code = (int) ($result['error_code'] ?? 0);
    $rxQrPending[$key]['state'] = $code === 429 && $rxQrPending[$key]['attempts'] < 5 ? 'pending' : ($code >= 400 && $code < 500 ? 'failed' : 'uncertain');
    $rxQrPending[$key]['next_attempt_at'] = time() + max(60, (int) ($result['parameters']['retry_after'] ?? 300));
    $rxQrPending[$key]['last_error'] = substr((string) ($result['description'] ?? 'no_response'), 0, 200);
    $rxQrSavePending();
    return false;
};
$rxQrQueueDelivery = function (string $key, array $entry) use (&$rxQrPending, $rxQrSavePending): string {
    if (isset($rxQrPending[$key])) {
        return $key;
    }
    foreach ($rxQrPending as $existingKey => $existing) {
        if (($entry['kind'] ?? '') === 'report' && (string) ($existing['kind'] ?? '') === 'report'
            && (string) ($existing['chat_id'] ?? '') === (string) ($entry['chat_id'] ?? '')
            && (string) ($existing['thread_id'] ?? '') === (string) ($entry['thread_id'] ?? '')
            && (string) ($existing['bottype'] ?? '') === (string) ($entry['bottype'] ?? '')
            && (string) ($existing['text'] ?? '') === (string) ($entry['text'] ?? '')) {
            return (string) $existingKey;
        }
    }
    if (!isset($rxQrPending[$key])) {
        $entry['state'] = 'pending';
        $entry['attempts'] = 0;
        $entry['next_attempt_at'] = 0;
        $rxQrPending[$key] = $entry;
        $rxQrSavePending();
    }
    return $key;
};
$rxQrSendOnce = function (string $key, array $entry) use ($rxQrQueueDelivery, $rxQrDeliver): bool {
    return $rxQrDeliver($rxQrQueueDelivery($key, $entry));
};
$rxQrNotificationCount = 0;
foreach (array_keys($rxQrPending) as $rxQrPendingKey) {
    if (rx_cron_time_up() || $rxQrNotificationCount >= 3) {
        break;
    }
    if (($rxQrPending[$rxQrPendingKey]['state'] ?? 'uncertain') === 'pending' && (int) ($rxQrPending[$rxQrPendingKey]['next_attempt_at'] ?? 0) <= time()) {
        $rxQrNotificationCount++;
        $rxQrDeliver((string) $rxQrPendingKey);
    }
}

$now = time();
rxReservationRecoverStale($now);

list($w, $n) = function_exists('rx_cron_shard') ? rx_cron_shard() : [0, 1];
$queuedRows = rxReservationDueRows($now, 30, (int) $w, (int) $n);

foreach ($queuedRows as $row) {
    if (function_exists('rx_cron_time_up') && rx_cron_time_up()) {
        if (function_exists('rx_cron_task_defer')) {
            rx_cron_task_defer('time_budget', 60);
        }
        break;
    }

    $outcome = rxReservationProcessRow($ManagePanel, $row, time());
    $status = (string) ($outcome['outcome'] ?? '');
    if (!in_array($status, ['applied', 'failed', 'retry', 'reconciling'], true)) {
        continue;
    }
    $fresh = rxReservationFetch((int) $row['id']) ?? $row;
    $reasonCode = preg_replace('/[^a-z_]/', '', strtolower((string) strtok((string) ($outcome['reason'] ?? ''), ':'))) ?: 'unknown';
    $notifyKey = $status . ':' . $reasonCode;
    if ((string) ($fresh['notified_state'] ?? '') === $notifyKey) {
        continue;
    }
    $rxQrFulfillment = rx_pf_decode($row['fulfillment'] ?? null);
    $rxQrFulfillment = is_array($rxQrFulfillment) ? $rxQrFulfillment : [];
    $rxQrAdminSource = ($rxQrFulfillment['source'] ?? '') === 'admin';
    $rxQrNotifyCustomer = !$rxQrAdminSource || ($rxQrFulfillment['notify_customer'] ?? true) !== false;
    $diagnostic = "<blockquote>شناسه رزرو : <code>{$row['id']}</code></blockquote>\n"
        . "<blockquote>نام کاربری سرویس : <code>" . $rxQrEsc($row['username']) . "</code></blockquote>\n"
        . "<blockquote>نام پنل : " . $rxQrEsc($row['name_panel']) . "</blockquote>\n"
        . "<blockquote>زمان فعال‌سازی : " . $rxQrEsc(rxReservationTriggerLabel((string) ($row['trigger_type'] ?? 'either'), $row['trigger_at'] ?? null, rxReservationRowConditions($row))) . "</blockquote>\n"
        . "<blockquote>سیاست اجرا : " . $rxQrEsc(rxRenewalPolicyLabel((string) ($row['policy'] ?? 'reset_all'), !empty($row['reset_usage']))) . "</blockquote>\n"
        . "<blockquote>تلاش : " . (int) ($fresh['attempt_count'] ?? 0) . "</blockquote>";

    if ($status === 'applied') {
        $message = faoxima_render_text(faoxima_textbot_get('dyn_queued_renewal_activated_tpl', "با سلام خدمت شما کاربر گرامی 👋\n✅ رزرو اشتراک سرویس {username} فعال شد و حجم و زمان سرویس شما تمدید گردید."), [
            'username' => $row['username'],
        ]);
        if ($rxQrAdminSource) {
            $message = rxRenewalText('adm_customer_activated', ['username' => $rxQrEsc($row['username'])]);
        }
        if (is_array($outcome['before'] ?? null) && is_array($outcome['after'] ?? null)) {
            $message .= "\n\n" . rxRenewalResultText($outcome['before'], $outcome['after']);
        }
        if ($rxQrNotifyCustomer && (string) ($row['id_user'] ?? '') !== '') {
            $rxQrCustomerOk = $rxQrSendOnce('r' . (int) $row['id'] . ':' . $notifyKey . ':user', ['kind' => 'user', 'chat_id' => (string) $row['id_user'], 'bottype' => $row['bottype'] ?? null, 'text' => $message]);
            if (!$rxQrCustomerOk) {
                $rxQrQueueDelivery('r' . (int) $row['id'] . ':' . $notifyKey . ':user', [
                    'kind' => 'user',
                    'chat_id' => (string) $row['id_user'],
                    'bottype' => $row['bottype'] ?? null,
                    'text' => $message,
                ]);
            }
        }
        $after = is_array($outcome['after'] ?? null) ? $outcome['after'] : [];
        $rxQrAppliedReport = "📌 اطلاعیه فعال‌سازی رزرو اشتراک\n\n" . $diagnostic
            . (isset($after['data_limit']) ? "\n<blockquote>حجم کل جدید : " . $rxQrEsc(rxRenewalFormatBytes($after['data_limit'])) . "</blockquote>" : '')
            . (isset($after['expire']) ? "\n<blockquote>انقضای جدید : " . $rxQrEsc(rxRenewalFormatExpire($after['expire'])) . "</blockquote>" : '');
        if (!$rxQrReport($rxQrAppliedReport)) {
            $rxQrQueueDelivery('r' . (int) $row['id'] . ':' . $notifyKey . ':report', [
                'kind' => 'report',
                'chat_id' => (string) $setting['Channel_Report'],
                'thread_id' => $reportCron,
                'text' => $rxQrAppliedReport,
            ]);
        }
        rxReservationMarkNotified((int) $row['id'], $notifyKey);
        continue;
    }

    if ($status === 'failed') {
        if ($rxQrNotifyCustomer && (string) ($row['id_user'] ?? '') !== '') {
            $rxQrFailedMessage = rxRenewalText('reservation_failed_user_tpl', ['username' => $row['username']]);
            $rxQrCustomerOk = $rxQrSendOnce('r' . (int) $row['id'] . ':' . $notifyKey . ':user', ['kind' => 'user', 'chat_id' => (string) $row['id_user'], 'bottype' => $row['bottype'] ?? null, 'text' => $rxQrFailedMessage]);
            if (!$rxQrCustomerOk) {
                $rxQrQueueDelivery('r' . (int) $row['id'] . ':' . $notifyKey . ':user', [
                    'kind' => 'user',
                    'chat_id' => (string) $row['id_user'],
                    'bottype' => $row['bottype'] ?? null,
                    'text' => $rxQrFailedMessage,
                ]);
            }
        }
        $rxQrFailedReport = "❌ خطای دائمی فعال‌سازی رزرو اشتراک (نیازمند بررسی دستی)\n\n" . $diagnostic . "\n<blockquote>دلیل : " . $rxQrEsc($outcome['reason'] ?? '') . "</blockquote>";
        if (!$rxQrReport($rxQrFailedReport)) {
            $rxQrQueueDelivery('r' . (int) $row['id'] . ':' . $notifyKey . ':report', [
                'kind' => 'report',
                'chat_id' => (string) $setting['Channel_Report'],
                'thread_id' => $reportCron,
                'text' => $rxQrFailedReport,
            ]);
        }
        rxReservationMarkNotified((int) $row['id'], $notifyKey);
        continue;
    }

    $title = $status === 'reconciling'
        ? "⚠️ نتیجه فعال‌سازی رزرو اشتراک نامشخص است و به‌صورت خودکار بررسی می‌شود"
        : ($reasonCode === 'admin_blocked'
            ? "⛔️ رزرو اشتراک به دلیل غیرفعال بودن سرویس توسط مدیر اجرا نشد (تا رفع محدودیت منتظر می‌ماند)"
            : "⚠️ خطای موقت فعال‌سازی رزرو اشتراک؛ تلاش مجدد خودکار");
    $rxQrPendingReport = $title . "\n\n" . $diagnostic . "\n<blockquote>دلیل : " . $rxQrEsc($outcome['reason'] ?? '') . "</blockquote>";
    if (!$rxQrReport($rxQrPendingReport)) {
        $rxQrQueueDelivery('r' . (int) $row['id'] . ':' . $notifyKey . ':report', [
            'kind' => 'report',
            'chat_id' => (string) $setting['Channel_Report'],
            'thread_id' => $reportCron,
            'text' => $rxQrPendingReport,
        ]);
    }
    rxReservationMarkNotified((int) $row['id'], $notifyKey);
}

if (!(function_exists('rx_cron_time_up') && rx_cron_time_up()) && (int) $w === 0) {
    foreach (rxRenewalOpsReconcile($ManagePanel, time(), 20) as $result) {
        $op = is_array($result['op'] ?? null) ? $result['op'] : [];
        $outcome = (string) ($result['outcome'] ?? '');
        if ($outcome === 'verified') {
            $invoice = !empty($op['id_invoice']) ? select("invoice", "*", "id_invoice", $op['id_invoice'], "select") : null;
            if (is_array($invoice) && (string) ($invoice['id_user'] ?? '') !== '' && (string) ($op['source'] ?? '') !== 'admin') {
                $rxQrReconciledText = rxRenewalText('reconciled_user_tpl', ['username' => $rxQrEsc($op['username'] ?? '')]);
                if (is_array($result['after'] ?? null) && rxRenewalSameLinkSupported(rx_rn_panel_type((string) ($op['name_panel'] ?? '')))) {
                    $rxQrReconciledText .= "\n\n" . trim(rxRenewalText('success_same_link'));
                }
                $rxQrReconciledOk = $rxQrSendOnce('op:' . (string) ($op['op_id'] ?? '') . ':user', ['kind' => 'user', 'chat_id' => (string) $invoice['id_user'], 'bottype' => $invoice['bottype'] ?? null, 'text' => $rxQrReconciledText]);
                if (!$rxQrReconciledOk) {
                    $rxQrQueueDelivery('op:' . (string) ($op['op_id'] ?? '') . ':user', [
                        'kind' => 'user',
                        'chat_id' => (string) $invoice['id_user'],
                        'bottype' => $invoice['bottype'] ?? null,
                        'text' => $rxQrReconciledText,
                    ]);
                }
            }
            $rxQrReconciledReport = "✅ تمدید نامشخص پس از بررسی خودکار تأیید شد\n\n<blockquote>شناسه عملیات : <code>" . $rxQrEsc($op['op_id'] ?? '') . "</code></blockquote>\n<blockquote>نام کاربری سرویس : <code>" . $rxQrEsc($op['username'] ?? '') . "</code></blockquote>\n<blockquote>نام پنل : " . $rxQrEsc($op['name_panel'] ?? '') . "</blockquote>";
            if (!$rxQrReport($rxQrReconciledReport)) {
                $rxQrQueueDelivery('op:' . (string) ($op['op_id'] ?? '') . ':report', [
                    'kind' => 'report',
                    'chat_id' => (string) $setting['Channel_Report'],
                    'thread_id' => $reportCron,
                    'text' => $rxQrReconciledReport,
                ]);
            }
        }
    }
}
if (function_exists('rx_cron_task_complete')) {
    rx_cron_task_complete('queued_renewal_finished');
}
