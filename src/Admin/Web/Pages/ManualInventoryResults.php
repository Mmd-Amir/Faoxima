<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $allowedStatuses, $invoiceTableMissing, $panelNameOf, $pdo, $tableMissing.
 * Inherited or exported by reference: $c, $configs, $counts, $daysActive, $daysFilter, $daysMode, $msQ, $page, $pages, $panelFilter, $params, $r, $remainingOf, $row, $scanTruncated, $st, $statusFilter, $total.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualInventoryResults
{
    public static function handle(
        $adminWebPage,
        $allowedStatuses,
        &$c,
        &$configs,
        &$counts,
        &$daysActive,
        &$daysFilter,
        &$daysMode,
        $invoiceTableMissing,
        &$msQ,
        &$page,
        &$pages,
        &$panelFilter,
        $panelNameOf,
        &$params,
        $pdo,
        &$r,
        &$remainingOf,
        &$row,
        &$scanTruncated,
        &$st,
        &$statusFilter,
        $tableMissing,
        &$total
    ) {
        if (($adminWebPage ?? null) !== 'manual_service.php') {
            return;
        }

        if ($statusFilter !== 'all' && !in_array($statusFilter, $allowedStatuses, true)) $statusFilter = 'all';

        $panelFilter = isset($_GET['panel']) ? (string)$_GET['panel'] : '';
        if ($panelFilter !== '' && !isset($panelNameOf[$panelFilter])) $panelFilter = '';

        $daysRaw = isset($_GET['days']) ? trim((string)$_GET['days']) : '';
        $daysFilter = ($daysRaw !== '' && ctype_digit($daysRaw)) ? (int)$daysRaw : null;
        $daysMode = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_dmode($_GET['dmode'] ?? '');
        $daysActive = ($daysFilter !== null && !$invoiceTableMissing);

        $msQ = trim((string)($_GET['q'] ?? ''));

        $perPage = 500;
        $page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;



        $whereSql = '1=1';
        $params = [];
        if ($statusFilter !== 'all') {
            $whereSql .= " AND status = :st";
            $params[':st'] = $statusFilter;
        }
        if ($panelFilter !== '') {
            $whereSql .= " AND codepanel = :cp";
            $params[':cp'] = $panelFilter;
        }
        if ($msQ !== '') {
            $whereSql .= " AND (namerecord LIKE :q1 OR username LIKE :q2 OR codeproduct LIKE :q3)";
            $msLike = '%' . $msQ . '%';
            $params[':q1'] = $msLike;
            $params[':q2'] = $msLike;
            $params[':q3'] = $msLike;
        }

        $total = 0;
        $configs = [];
        $remainingOf = [];
        $scanTruncated = false;
        $counts = ['total' => 0, 'active' => 0, 'selled' => 0, 'disabled' => 0, 'delete' => 0];



        if (!$tableMissing) {
            try {
                $c = $pdo->query("SELECT status, COUNT(*) AS cnt FROM manualsell GROUP BY status");
                if ($c) {
                    foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $st = (string)$row['status'];
                        if (isset($counts[$st])) $counts[$st] = (int)$row['cnt'];
                        $counts['total'] += (int)$row['cnt'];
                    }
                }
            } catch (\Throwable $e) {
            }

            if (!$daysActive) {
                try {
                    $cnt = $pdo->prepare("SELECT COUNT(*) FROM manualsell WHERE {$whereSql}");
                    $cnt->execute($params);
                    $total = (int)$cnt->fetchColumn();
                } catch (\Throwable $e) {
                }
                $pages = max(1, (int)ceil($total / $perPage));
                if ($page > $pages) $page = $pages;
                $offset = ($page - 1) * $perPage;
                try {
                    $r = $pdo->prepare("SELECT * FROM manualsell WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
                    $r->execute($params);
                    $configs = $r->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                }
                $remainingOf = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_ms_resolve_remaining($pdo, $configs, $invoiceTableMissing);
            } else {
                $scan = [];
                $cap = FAOXIMA_REMAINING_SCAN_CAP;
                try {
                    $r = $pdo->prepare("SELECT * FROM manualsell WHERE {$whereSql} ORDER BY id DESC LIMIT " . ($cap + 1));
                    $r->execute($params);
                    $scan = $r->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                }
                [$total, $pages, $page, $configs, $remainingOf, $scanTruncated] = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_filter_page(
                    $scan, $page, $perPage, $daysMode, $daysFilter,
                    static fn (array $rows) => \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_ms_resolve_remaining($pdo, $rows, $invoiceTableMissing)
                );
            }
        } else {
            $pages = 1;
        }



        return true;
    }
}
