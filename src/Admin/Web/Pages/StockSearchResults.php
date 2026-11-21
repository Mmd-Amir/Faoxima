<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $daysActive, $daysFilter, $daysMode, $invoiceTableMissing, $pdo, $tableMissing, $viewShelfId.
 * Inherited or exported by reference: $allowedStatuses, $cfgFilter, $cfgPage, $cfgPages, $cfgQ, $cfgTotal, $flash, $params, $r, $remainingOf, $row, $scanTruncated, $selectedShelf, $sh, $shelfConfigs, $shelfCounts, $shelfDays, $shelves, $sid, $st, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class StockSearchResults
{
    public static function handle(
        $adminWebPage,
        &$allowedStatuses,
        &$cfgFilter,
        &$cfgPage,
        &$cfgPages,
        &$cfgQ,
        &$cfgTotal,
        $daysActive,
        $daysFilter,
        $daysMode,
        &$flash,
        $invoiceTableMissing,
        &$params,
        $pdo,
        &$r,
        &$remainingOf,
        &$row,
        &$scanTruncated,
        &$selectedShelf,
        &$sh,
        &$shelfConfigs,
        &$shelfCounts,
        &$shelfDays,
        &$shelves,
        &$sid,
        &$st,
        &$stmt,
        $tableMissing,
        $viewShelfId
    ) {
        if (($adminWebPage ?? null) !== 'stock.php') {
            return;
        }



        if (!$tableMissing) {
            try {
                $staleReservedBefore = time() - 600;
                $pdo->exec("UPDATE nm_config_stock SET status='active', assigned_user='', assigned_invoice='', assigned_mode='', reserved_at=NULL WHERE status='reserved' AND (reserved_at IS NULL OR reserved_at < {$staleReservedBefore})");
            } catch (\Throwable $e) {
            }
        }
        if (!$tableMissing) {
            try {
                $stmt = $pdo->query(
                    "SELECT shelf_id, status, COUNT(*) AS cnt FROM nm_config_stock
             WHERE shelf_id IS NOT NULL GROUP BY shelf_id, status"
                );
                if ($stmt) {
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $sid = (int)$row['shelf_id'];
                        $st  = (string)$row['status'];
                        if (!isset($shelfCounts[$sid])) {
                            $shelfCounts[$sid] = ['total' => 0, 'active' => 0, 'reserved' => 0, 'delivered' => 0, 'disabled' => 0];
                        }
                        if (isset($shelfCounts[$sid][$st])) $shelfCounts[$sid][$st] = (int)$row['cnt'];
                        $shelfCounts[$sid]['total'] += (int)$row['cnt'];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $shelves = [];
        $shelfConfigs = [];
        if (!$tableMissing) {
            try {
                $r = $pdo->query("SELECT * FROM nm_stock_shelves ORDER BY id DESC");
                if ($r) $shelves = $r->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $flash['err'] = $flash['err'] ?: 'بارگذاری ناموفق: ' . $e->getMessage();
            }
            if ($viewShelfId > 0) {
                foreach ($shelves as $sh) {
                    if ((int)$sh['id'] === $viewShelfId) {
                        $selectedShelf = $sh;
                        break;
                    }
                }
                if ($selectedShelf) {
                    $allowedStatuses = ['active', 'reserved', 'delivered', 'disabled'];
                    $cfgFilter = isset($_GET['cstatus']) ? (string)$_GET['cstatus'] : 'all';
                    if ($cfgFilter !== 'all' && !in_array($cfgFilter, $allowedStatuses, true)) $cfgFilter = 'all';
                    $cfgPerPage = 500;
                    $cfgPage = isset($_GET['cpage']) ? max(1, (int)$_GET['cpage']) : 1;

                    $cfgQ = trim((string)($_GET['q'] ?? ''));

                    $whereSql = "shelf_id = :id";
                    $params = [':id' => $viewShelfId];
                    if ($cfgFilter !== 'all') {
                        $whereSql .= " AND status = :st";
                        $params[':st'] = $cfgFilter;
                    }
                    if ($cfgQ !== '') {
                        $whereSql .= " AND (content LIKE :q1 OR assigned_user LIKE :q2 OR sub_link LIKE :q3)";
                        $cfgLike = '%' . $cfgQ . '%';
                        $params[':q1'] = $cfgLike;
                        $params[':q2'] = $cfgLike;
                        $params[':q3'] = $cfgLike;
                    }

                    $cfgTotal = 0;
                    $shelfDays = (int)($selectedShelf['service_days'] ?? 0);

                    if (!$daysActive) {
                        try {
                            $cnt = $pdo->prepare("SELECT COUNT(*) FROM nm_config_stock WHERE {$whereSql}");
                            $cnt->execute($params);
                            $cfgTotal = (int)$cnt->fetchColumn();
                        } catch (\Throwable $e) {
                        }
                        $cfgPages = max(1, (int)ceil($cfgTotal / $cfgPerPage));
                        if ($cfgPage > $cfgPages) $cfgPage = $cfgPages;
                        $cfgOffset = ($cfgPage - 1) * $cfgPerPage;

                        try {
                            $r = $pdo->prepare("SELECT * FROM nm_config_stock WHERE {$whereSql} ORDER BY id DESC LIMIT {$cfgPerPage} OFFSET {$cfgOffset}");
                            $r->execute($params);
                            $shelfConfigs = $r->fetchAll(PDO::FETCH_ASSOC);
                        } catch (\Throwable $e) {
                        }
                        $remainingOf = \Faoxima\Admin\Web\Pages\StockRequestHelpers::faoxima_stock_resolve_remaining($pdo, $shelfConfigs, $shelfDays, $invoiceTableMissing);
                    } else {
                        $scanLimit = FAOXIMA_REMAINING_SCAN_CAP + 1;
                        $scanned = [];
                        try {
                            $r = $pdo->prepare("SELECT * FROM nm_config_stock WHERE {$whereSql} ORDER BY id DESC LIMIT {$scanLimit}");
                            $r->execute($params);
                            $scanned = $r->fetchAll(PDO::FETCH_ASSOC);
                        } catch (\Throwable $e) {
                        }
                        [$cfgTotal, $cfgPages, $cfgPage, $shelfConfigs, $remainingOf, $scanTruncated] = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_filter_page(
                            $scanned, $cfgPage, $cfgPerPage, $daysMode, $daysFilter,
                            static fn (array $rows) => \Faoxima\Admin\Web\Pages\StockRequestHelpers::faoxima_stock_resolve_remaining($pdo, $rows, $shelfDays, $invoiceTableMissing)
                        );
                    }
                }
            }
        }



        return true;
    }
}
