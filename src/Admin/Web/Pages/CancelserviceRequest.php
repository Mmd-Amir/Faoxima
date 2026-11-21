<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $st.
 * Inherited or exported by reference: $cur, $df, $flash, $method, $query, $r, $reqDateKeep, $reqFilterActive, $reqFilterCriteria, $reqFilterDeletable, $reqFilterNote, $reqFilterParams, $reqPage, $reqPages, $reqQ, $reqStatus, $reqStatusActive, $reqStatusOptions, $reqTotal, $result, $rows, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CancelserviceRequest
{
    public static function handle(
        &$cur,
        &$df,
        &$flash,
        &$method,
        $pdo,
        &$query,
        &$r,
        &$reqDateKeep,
        &$reqFilterActive,
        &$reqFilterCriteria,
        &$reqFilterDeletable,
        &$reqFilterNote,
        &$reqFilterParams,
        &$reqPage,
        &$reqPages,
        &$reqQ,
        &$reqStatus,
        &$reqStatusActive,
        &$reqStatusOptions,
        &$reqTotal,
        &$result,
        &$rows,
        $st,
        &$stmt
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!isset($_SESSION["user"]) || !$result) {
            header('Location: login.php');
            return;
        }

        /* ---------- helpers ---------- */

        /* normalized status -> [label, badgeClass, filterKey] */

        /* request "type" label from id_order + method */






        $flash = ['ok' => '', 'err' => ''];



        if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            $requestedTokens = $_POST['ids'] ?? [];
            $deletedCount = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_delete_tokens($pdo, is_array($requestedTokens) ? $requestedTokens : []);
            \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('cancelService.php', count($requestedTokens), $deletedCount);
        }

        /* ---------- delete actions ---------- */
        // legacy: delete a cancel_service row
        if (isset($_GET['removeid']) && $_GET['removeid'] !== '') {
            try {
                $stmt = $pdo->prepare("DELETE FROM cancel_service WHERE id = :id");
                $stmt->execute([':id' => (int)$_GET['removeid']]);
            } catch (\Throwable $e) {}
            header("Location: cancelService.php");
            exit;
        }
        // delete a resolved (reject/expire) payment request record (cleanup only)
        if (isset($_GET['delpr']) && $_GET['delpr'] !== '') {
            try {
                $cur = $pdo->prepare("SELECT payment_Status FROM Payment_report WHERE id = :id LIMIT 1");
                $cur->execute([':id' => (int)$_GET['delpr']]);
                $st = strtolower((string)$cur->fetchColumn());
                if (in_array($st, ['reject','rejected','expire','expired'], true)) {
                    $pdo->prepare("DELETE FROM Payment_report WHERE id = :id")->execute([':id' => (int)$_GET['delpr']]);
                }
            } catch (\Throwable $e) {}
            header("Location: cancelService.php");
            exit;
        }

        /* ---------- aggregate all requests ---------- */
        $prHasSource = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_table_has_col($pdo, 'Payment_report', 'source');
        $reqQ = trim((string)($_GET['q'] ?? ''));
        $reqLike = '%' . $reqQ . '%';
        $rows = [];

        // 1) Payment_report — admin-actionable money requests (bank receipts, wallet charges, crypto, gateway)
        try {
            if ($reqQ !== '') {
                $st = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user LIKE :q1 OR id_order LIKE :q2 OR id_invoice LIKE :q3 ORDER BY id DESC LIMIT 1000");
                $st->execute([':q1' => $reqLike, ':q2' => $reqLike, ':q3' => $reqLike]);
            } else {
                $st = $pdo->query("SELECT * FROM Payment_report ORDER BY id DESC LIMIT 1000");
            }
            foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                $method = (string)($r['Payment_Method'] ?? '');
                // skip purely automatic/empty rows with no actionable method
                [$stLabel, $stClass, $stKey] = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_status($r['payment_Status'] ?? '');
                $src = $prHasSource ? (string)($r['source'] ?? '') : '';
                $purpose = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_purpose($r['id_order'] ?? '');
                if ($purpose === '') $purpose = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_purpose($r['id_invoice'] ?? '');
                $svcUser = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_invoice_username($r['id_invoice'] ?? '');
                $details = [];
                if ($method !== '') $details[] = 'روش: ' . \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_method_label($method);
                if ($purpose !== '') $details[] = $purpose;
                if ($svcUser !== '') $details[] = 'سرویس: ' . $svcUser;
                if ($stKey === 'reject' && !empty($r['dec_not_confirmed'])) $details[] = (string)$r['dec_not_confirmed'];
                $rows[] = [
                    'sortid'  => 2000000000 + (int)($r['id'] ?? 0),
                    'sortts'  => \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_ts($r['time'] ?? ''),
                    'idlabel' => '#' . (int)($r['id'] ?? 0),
                    'type'    => \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_type($r['id_order'] ?? '', $method),
                    'source'  => ($src === 'miniapp') ? 'مینی‌اپ' : 'ربات',
                    'user'    => (string)($r['id_user'] ?? ''),
                    'details' => implode(' — ', $details),
                    'amount'  => (int)($r['price'] ?? 0),
                    'date'    => (string)($r['time'] ?? ''),
                    'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
                    'del'     => in_array($stKey, ['reject','expire'], true) ? ('cancelService.php?delpr=' . (int)($r['id'] ?? 0)) : '',
                    'pk'      => 'pr:' . (int)($r['id'] ?? 0),
                    'fdok'    => in_array(strtolower((string)($r['payment_Status'] ?? '')), ['reject', 'rejected', 'expire', 'expired'], true),
                ];
            }
        } catch (\Throwable $e) { $flash['err'] = 'بارگذاری درخواست‌های پرداخت ناموفق: ' . $e->getMessage(); }

        // 2) cancel_service — service cancel requests
        try {
            if ($reqQ !== '') {
                $st = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user LIKE :q1 OR username LIKE :q2 ORDER BY id DESC LIMIT 2000");
                $st->execute([':q1' => $reqLike, ':q2' => $reqLike]);
            } else {
                $st = $pdo->query("SELECT * FROM cancel_service ORDER BY id DESC LIMIT 2000");
            }
            foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                [$stLabel, $stClass, $stKey] = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_status($r['status'] ?? '');
                $rows[] = [
                    'sortid'  => 1000000000 + (int)($r['id'] ?? 0),
                    'sortts'  => 0,
                    'idlabel' => 'C' . (int)($r['id'] ?? 0),
                    'type'    => 'لغو سرویس',
                    'source'  => 'ربات',
                    'user'    => (string)($r['id_user'] ?? ''),
                    'details' => 'سرویس: ' . (string)($r['username'] ?? '') . (trim((string)($r['description'] ?? '')) !== '' ? ' — ' . (string)$r['description'] : ''),
                    'amount'  => 0,
                    'date'    => '',
                    'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
                    'del'     => 'cancelService.php?removeid=' . (int)($r['id'] ?? 0),
                    'pk'      => 'cs:' . (int)($r['id'] ?? 0),
                    'fdok'    => true,
                ];
            }
        } catch (\Throwable $e) {}

        // 3) national-net refund requests (invoice waiting for admin approval)
        try {
            if ($reqQ !== '') {
                $st = $pdo->prepare("SELECT id_invoice, id_user, username, name_product, price_product, time_sell FROM invoice WHERE Status = 'nm_refund_pending' AND (id_user LIKE :q1 OR username LIKE :q2 OR id_invoice LIKE :q3) ORDER BY id DESC LIMIT 500");
                $st->execute([':q1' => $reqLike, ':q2' => $reqLike, ':q3' => $reqLike]);
            } else {
                $st = $pdo->query("SELECT id_invoice, id_user, username, name_product, price_product, time_sell FROM invoice WHERE Status = 'nm_refund_pending' ORDER BY id DESC LIMIT 500");
            }
            foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                $refundTs = \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_ts($r['time_sell'] ?? '');
                $rows[] = [
                    'sortid'  => 1500000000,
                    'sortts'  => $refundTs,
                    'idlabel' => (string)($r['id_invoice'] ?? ''),
                    'type'    => 'بازگشت وجه (نت ملی)',
                    'source'  => 'ربات',
                    'user'    => (string)($r['id_user'] ?? ''),
                    'details' => 'سرویس: ' . (string)($r['username'] ?? '') . ' — ' . (string)($r['name_product'] ?? ''),
                    'amount'  => (int)($r['price_product'] ?? 0),
                    'date'    => $refundTs > 0 ? date('Y/m/d H:i:s', $refundTs) : '',
                    'stLabel' => 'در انتظار', 'stClass' => 'badge-warning', 'stKey' => 'wait',
                    'del'     => '',
                    'pk'      => '',
                ];
            }
        } catch (\Throwable $e) {}

        // newest first
        usort($rows, function ($a, $b) {
            $t = ($b['sortts'] ?? 0) <=> ($a['sortts'] ?? 0);
            if ($t !== 0) return $t;
            return ($b['sortid'] ?? 0) <=> ($a['sortid'] ?? 0);
        });
        $df = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();
        if ($df['active']) {
            $rows = array_values(array_filter($rows, function ($r) use ($df) {
                if ((int)($r['sortts'] ?? 0) === 0) return true;
                return \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_matches_ts((int)$r['sortts'], $df['from'], $df['to']);
            }));
        }

        $reqStatusOptions = [
            'wait'   => 'در انتظار',
            'ok'     => 'تاییدشده',
            'reject' => 'ردشده',
            'expire' => 'منقضی',
            'other'  => 'سایر',
        ];
        $reqStatus = \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current();
        if ($reqStatus !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($reqStatus) {
                return \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_matches($reqStatus, (string)($r['stKey'] ?? ''));
            }));
        }

        $reqStatusActive = $reqStatus !== '' && isset($reqStatusOptions[$reqStatus]);
        $reqFilterActive = $reqQ !== '' || $df['active'] || $reqStatusActive;
        $reqDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('', $df['active']);
        $reqFilterParams = array_merge(['q' => $reqQ !== '' ? $reqQ : null, 'status' => $reqStatusActive ? $reqStatus : null], $reqDateKeep);
        $reqFilterCriteria = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria($reqStatusActive ? $reqStatusOptions[$reqStatus] : '', $df, $reqQ);
        $reqFilterTokens = [];
        $reqFilterDeletable = 0;
        foreach ($rows as $r) {
            if ((string)($r['pk'] ?? '') === '') continue;
            $reqFilterTokens[] = (string)$r['pk'];
            if (!empty($r['fdok'])) $reqFilterDeletable++;
        }
        if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested()) {
            $fdMatched = $reqFilterActive ? count($rows) : 0;
            $fdDeleted = ($reqFilterActive && $reqFilterTokens) ? \Faoxima\Admin\Web\Pages\CancelserviceRequestHelpers::fx_req_delete_tokens($pdo, $reqFilterTokens) : 0;
            \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('cancelService.php', $reqFilterParams, $fdMatched, $fdDeleted);
        }
        $reqFilterNote = $reqFilterDeletable < count($rows)
            ? 'از ' . number_format(count($rows)) . ' نتیجه فیلترشده فقط ' . number_format($reqFilterDeletable) . ' مورد طبق قوانین فعلی قابل حذف است و بقیه حذف نمی‌شوند.'
            : '';

        foreach ($rows as $i => $r) {
            $rows[$i]['idlabel'] = (string)($i + 1);
        }

        $reqPerPage = 5;
        $reqTotal = count($rows);
        $reqPages = max(1, (int)ceil($reqTotal / $reqPerPage));
        $reqPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        if ($reqPage > $reqPages) $reqPage = $reqPages;
        $rows = array_slice($rows, ($reqPage - 1) * $reqPerPage, $reqPerPage);

        return true;
    }
}
