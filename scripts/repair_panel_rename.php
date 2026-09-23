<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../re/rx/function/database_helpers_1.php';
require_once __DIR__ . '/../re/rx/function/csv_matching_helpers.php';

$opts = getopt('', ['old:', 'new:', 'apply', 'delete-phantoms']);
$oldName = trim((string) ($opts['old'] ?? ''));
$newName = trim((string) ($opts['new'] ?? ''));
$apply = isset($opts['apply']);
$deletePhantoms = isset($opts['delete-phantoms']);

if ($oldName === '' || $newName === '' || $oldName === $newName) {
    fwrite(STDERR, "Usage: php scripts/repair_panel_rename.php --old=\"OLD NAME\" --new=\"NEW NAME\" [--apply] [--delete-phantoms]\n");
    exit(1);
}
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection unavailable\n");
    exit(1);
}

$panelCount = function (string $name) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE BINARY name_panel = BINARY ?");
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn();
};
if ($panelCount($newName) !== 1) {
    fwrite(STDERR, "Refused: no panel named \"$newName\" exists in marzban_panel\n");
    exit(1);
}
$looseOld = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE name_panel = ?");
$looseOld->execute([$oldName]);
if ((int) $looseOld->fetchColumn() !== 0) {
    fwrite(STDERR, "Refused: a panel named \"$oldName\" still exists, so it is not a stale reference\n");
    exit(1);
}

$like = '%' . addcslashes($oldName, '%_\\') . '%';
$snapshot = function () use ($pdo, $like, $oldName) {
    $stmt = $pdo->prepare("SELECT id, name_product, Location FROM product WHERE Location LIKE ? OR FIND_IN_SET(?, Location) > 0 ORDER BY id");
    $stmt->execute([$like, $oldName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$countWhere = function (string $sql, array $params) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$before = $snapshot();
$invoiceBefore = $countWhere("SELECT COUNT(*) FROM invoice WHERE Service_location = ?", [$oldName]);

$pdo->beginTransaction();
$ok = rxPanelNameCascade($pdo, $oldName, $newName);
if (!$ok) {
    $pdo->rollBack();
    fwrite(STDERR, "Repair failed, nothing changed (see error_log)\n");
    exit(1);
}
$afterById = [];
$stmt = $pdo->prepare("SELECT Location FROM product WHERE id = ?");
foreach ($before as $row) {
    $stmt->execute([$row['id']]);
    $afterById[$row['id']] = (string) $stmt->fetchColumn();
}

$changed = 0;
echo ($apply ? "APPLY" : "DRY RUN") . ": \"$oldName\" -> \"$newName\"\n";
foreach ($before as $row) {
    $after = $afterById[$row['id']];
    if ($after === (string) $row['Location']) {
        continue;
    }
    $changed++;
    echo "  product #{$row['id']} ({$row['name_product']}): {$row['Location']}  =>  {$after}\n";
}
echo "  products to update: $changed\n";
echo "  invoices with Service_location = old name: $invoiceBefore\n";

$phantomStmt = $pdo->prepare("SELECT id FROM product WHERE BINARY Location = BINARY ? AND (name_product IS NULL OR name_product = '') AND (code_product IS NULL OR code_product = '')");
$phantomStmt->execute([$newName]);
$phantoms = $phantomStmt->fetchAll(PDO::FETCH_COLUMN);
echo "  empty product rows with Location = new name (created by old rename): " . (count($phantoms) ? implode(', ', $phantoms) : 'none') . "\n";

if (!$apply) {
    $pdo->rollBack();
    echo "Nothing written. Re-run with --apply to commit" . (count($phantoms) ? " (add --delete-phantoms to also remove the empty rows)" : "") . ".\n";
    exit(0);
}
if ($deletePhantoms && count($phantoms)) {
    $del = $pdo->prepare("DELETE FROM product WHERE id = ? AND (name_product IS NULL OR name_product = '') AND (code_product IS NULL OR code_product = '')");
    foreach ($phantoms as $pid) {
        $del->execute([$pid]);
    }
    echo "  deleted empty product rows: " . implode(', ', $phantoms) . "\n";
}
$pdo->commit();
clearSelectCache('product');
clearSelectCache('invoice');
faoxima_bust_bot_selectcache('product');
faoxima_bust_bot_selectcache('invoice');
echo "Committed.\n";
