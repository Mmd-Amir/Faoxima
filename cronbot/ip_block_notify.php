<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('ip_block_notify', 120);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('ip_block_notify', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('ip_block_notify')) {
    return;
}

$rxCursorFile = rx_cron_runtime_dir() . DIRECTORY_SEPARATOR . 'ip_block_notify.cursor';
$rxCursor = '';
if (is_file($rxCursorFile)) {
    $rxCursorRaw = trim((string) @file_get_contents($rxCursorFile));
    if ($rxCursorRaw !== '' && strlen($rxCursorRaw) <= 200) {
        $rxCursor = $rxCursorRaw;
    }
}

$rxPanelMap = [];
try {
    $rxPanelStmt = $pdo->query("SELECT * FROM marzban_panel WHERE type IN ('x-ui_single', 'rebecca') AND ip_limit_guard = 'onipguard'");
    $rxPanelRows = $rxPanelStmt ? $rxPanelStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rxPanelRows as $rxPanelRow) {
        $rxPanelMap[strtolower((string) $rxPanelRow['name_panel'])] = $rxPanelRow;
    }
} catch (Throwable $e) {
    $rxPanelMap = [];
}

$rxLoadBatch = static function (string $rxBound) use ($pdo): array {
    $stmt = $pdo->prepare("
        SELECT invoice.id_invoice, invoice.id_user, invoice.username, invoice.Service_location, invoice.ip_limit, invoice.ip_last_seen
        FROM invoice
        INNER JOIN marzban_panel ON marzban_panel.name_panel = invoice.Service_location
        WHERE marzban_panel.type IN ('x-ui_single', 'rebecca')
          AND marzban_panel.ip_limit_guard = 'onipguard'
          AND invoice.Status = 'active'
          AND invoice.ip_limit IS NOT NULL
          AND invoice.ip_limit != '0'
          AND invoice.id_invoice > ?
        ORDER BY invoice.id_invoice ASC
        LIMIT 25
    ");
    $stmt->execute([$rxBound]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
};

$rxInvoices = $rxLoadBatch($rxCursor);
if (!$rxInvoices && $rxCursor !== '') {
    $rxInvoices = $rxLoadBatch('');
}

foreach ($rxInvoices as $invoice) {
    $panel = $rxPanelMap[strtolower((string) $invoice['Service_location'])] ?? null;
    if (!is_array($panel)) {
        continue;
    }

    $limit = (int) $invoice['ip_limit'];
    if ($limit <= 0) {
        continue;
    }

    if ($panel['type'] === 'rebecca') {
        $summary = rebeccaClientIpSummary($panel['name_panel'], $invoice['username']);
    } else {
        $summary = xui_client_ip_summary($panel, $invoice['username']);
    }
    if (empty($summary['status'])) {
        continue;
    }
    if (function_exists('faoxima_cap_ip_summary')) {
        $summary = faoxima_cap_ip_summary($summary, $limit);
    }

    $currentIps = is_array($summary['raw'] ?? null) ? array_values($summary['raw']) : [];
    $previousIps = json_decode((string) ($invoice['ip_last_seen'] ?? ''), true);
    if (!is_array($previousIps)) {
        $previousIps = [];
    }

    $droppedIps = array_values(array_diff($previousIps, $currentIps));
    $newIps = array_values(array_diff($currentIps, $previousIps));

    if (!empty($previousIps) && !empty($droppedIps) && !empty($newIps)) {
        $kickText = "⚠️ چون یک دستگاه/IP جدید به کانفیگ شما وصل شد، اتصال قبلی از IP <code>" . implode(', ', $droppedIps) . "</code> قطع شد.\n"
            . "این IP بعد از مدتی خودکار آزاد می‌شود.\n"
            . "اگر این تغییر از سمت شما نبوده، کانفیگ خود را با کسی به اشتراک نگذارید.";
        sendmessage($invoice['id_user'], $kickText, null, 'HTML');
    }

    if ($currentIps !== $previousIps) {
        update("invoice", "ip_last_seen", json_encode($currentIps), "id_invoice", $invoice['id_invoice']);
    }
}

if (!empty($rxInvoices)) {
    $rxNextCursor = (string) $rxInvoices[count($rxInvoices) - 1]['id_invoice'];
    if ($rxNextCursor !== '' && $rxNextCursor !== $rxCursor) {
        $rxCursorTmp = $rxCursorFile . '.tmp';
        if (@file_put_contents($rxCursorTmp, $rxNextCursor, LOCK_EX) !== false) {
            @rename($rxCursorTmp, $rxCursorFile);
        }
    }
}
