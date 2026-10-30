<?php
require_once __DIR__ . '/_init.php';
rx_cron_boot('remnawave_usage', 900);

ini_set('error_log', 'error_log');
if (!rx_cron_require_or_skip('remnawave_usage', [
    __DIR__ . '/../config.php',
    __DIR__ . '/../botapi.php',
    __DIR__ . '/../panels.php',
    __DIR__ . '/../function.php',
])) {
    return;
}
if (!rx_cron_db_ready('remnawave_usage')) {
    return;
}
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

$ManagePanel = new ManagePanel();

try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'remnawave_users'");
    $stmt->execute();
    if (count($stmt->fetchAll()) === 0) {
        return;
    }
} catch (Throwable $e) {
    error_log("[REMNAMWAVE-CRON-DISCONNECT] table check failed | " . $e->getMessage());
    return;
}

$rxUsageCursor = 0;
if (function_exists('rx_cron_bulk_cursor')) {
    try {
        $rxUsageCursor = rx_cron_bulk_cursor($pdo, 'remnawave_usage');
    } catch (Throwable $e) {
        $rxUsageCursor = 0;
    }
}
try {
    $stmt = $pdo->prepare("SELECT id, name_panel, username, status, panel_user_id, subscription_url FROM remnawave_users WHERE status = 'active' AND panel_user_id IS NOT NULL AND panel_user_id <> '' AND id > ? ORDER BY id ASC LIMIT 50");
    $stmt->execute([$rxUsageCursor]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows && $rxUsageCursor > 0) {
        $stmt->execute([0]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log("[REMNAMWAVE-CRON-DISCONNECT] fetch users failed | " . $e->getMessage());
    return;
}

if (!is_array($rows) || count($rows) === 0) {
    return;
}

$managers = array();
$rxLastScannedId = 0;
foreach ($rows as $row) {
    $rxLastScannedId = (int) ($row['id'] ?? 0);
    $namePanel = (string) ($row['name_panel'] ?? '');
    if ($namePanel === '') {
        continue;
    }
    try {
        $panel = select("marzban_panel", "*", "name_panel", $namePanel, "select");
        if (!is_array($panel) || ($panel['type'] ?? '') !== 'remnawave') {
            continue;
        }
        if (!isset($managers[$namePanel])) {
            $managers[$namePanel] = new RemnawaveManager($panel);
        }
        $mgr = $managers[$namePanel];
        $res = $mgr->getUserById($row['panel_user_id'], $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            error_log("[REMNAMWAVE-CRON-DISCONNECT] Panel: $namePanel | User: {$row['username']} | could not fetch usage");
            continue;
        }
        $data = $res['data'];
        $remoteStatus = strtolower((string) ($data['status'] ?? 'active'));
        $localStatus = ($remoteStatus === 'disabled' || $remoteStatus === 'expired' || $remoteStatus === 'limited') ? $remoteStatus : 'active';
        if ($localStatus !== (string) ($row['status'] ?? 'active')) {
            $upd = $pdo->prepare("UPDATE remnawave_users SET status = :s WHERE id = :id");
            $upd->execute([':s' => $localStatus, ':id' => $row['id']]);
        }
        $subUrl = (string) ($data['subscriptionUrl'] ?? '');
        if ($subUrl !== '' && $subUrl !== (string) ($row['subscription_url'] ?? '')) {
            $upd = $pdo->prepare("UPDATE remnawave_users SET subscription_url = :u WHERE id = :id");
            $upd->execute([':u' => $subUrl, ':id' => $row['id']]);
        }
    } catch (Throwable $e) {
        error_log("[REMNAMWAVE-CRON-DISCONNECT] Panel: $namePanel | User: " . ($row['username'] ?? '') . " | " . $e->getMessage());
        continue;
    }
}

if ($rxLastScannedId > 0 && function_exists('rx_cron_bulk_cursor')) {
    try {
        rx_cron_bulk_cursor($pdo, 'remnawave_usage', $rxLastScannedId);
    } catch (Throwable $e) {
        error_log("[REMNAMWAVE-CRON-DISCONNECT] cursor persist failed | " . $e->getMessage());
    }
}
