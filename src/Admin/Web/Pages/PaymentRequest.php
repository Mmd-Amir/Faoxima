<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $df, $listpayment, $payDateKeep, $payExtraKeep, $payFilterActive, $payFilterCriteria, $payFilterParams, $payMethodLabels, $payQ, $payStatus, $payStatusActive, $payStatusOptions, $pg, $query, $result, $v.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentRequest
{
    public static function handle(
        &$df,
        &$listpayment,
        &$payDateKeep,
        &$payExtraKeep,
        &$payFilterActive,
        &$payFilterCriteria,
        &$payFilterParams,
        &$payMethodLabels,
        &$payQ,
        &$payStatus,
        &$payStatusActive,
        &$payStatusOptions,
        $pdo,
        &$pg,
        &$query,
        &$result,
        &$v
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!isset($_SESSION["user"]) || !$result) {
            header('Location: login.php');
            return;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

        if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            $requestedIds = $_POST['ids'] ?? [];
            $deletedCount = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'Payment_report', 'id', $requestedIds);
            \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('payment.php', count($requestedIds), $deletedCount);
        }

        $payQ = trim((string)($_GET['q'] ?? ''));
        $df = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();
        $payStatusOptions = [
            'paid'    => 'پرداخت موفق',
            'Unpaid'  => 'ناموفق',
            'expire'  => 'منقضی شده',
            'reject'  => 'رد شده',
            'cancelled' => 'لغو شده',
            'waiting' => 'در انتظار تایید',
        ];
        $payStatus = \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current();
        $payAf = \Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_resolve();
        $payMethod = \Faoxima\Admin\Web\Support\ExtraFilter::fx_extra_text_param('method', 400);
        $payMethodLabels = [
            'cart to cart'         => 'کارت به کارت',
            'low balance by admin' => 'کسر توسط ادمین',
            'add balance by admin' => 'افزایش توسط ادمین',
            'Currency Rial 1'      => 'درگاه ریالی ۱',
            'Currency Rial tow'    => 'درگاه ریالی ۲',
            'Currency Rial 3'      => 'درگاه ریالی ۳',
            'aqayepardakht'        => 'آقای پرداخت',
            'zarinpal'             => 'زرین‌پال',
            'plisio'               => 'Plisio',
            'arze digital offline' => 'ارز دیجیتال آفلاین',
            'Star Telegram'        => 'استارز تلگرام',
            'nowpayment'           => 'NowPayment',
        ];
        $payMethodOptions = [];
        try {
            $payMethodStmt = $pdo->query("SELECT DISTINCT Payment_Method FROM Payment_report WHERE Payment_Method IS NOT NULL AND Payment_Method <> '' LIMIT 200");
            foreach ($payMethodStmt->fetchAll(PDO::FETCH_COLUMN) as $pmv) {
                $pmv = (string)$pmv;
                $payMethodOptions[$pmv] = $payMethodLabels[$pmv] ?? $pmv;
            }
        } catch (\Throwable $e) {
        }
        if ($payMethod !== '' && !isset($payMethodOptions[$payMethod])) {
            $payMethodOptions[$payMethod] = $payMethodLabels[$payMethod] ?? $payMethod;
        }
        asort($payMethodOptions, SORT_STRING);
        $payExtraKeep = array_merge(\Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_keep($payAf), ['method' => $payMethod !== '' ? $payMethod : null]);

        $whereSql = '1=1';
        $whereParams = [];
        if ($payQ !== '') {
            $payLike = '%' . $payQ . '%';
            $whereSql .= ' AND (id_order LIKE :l1 OR id_user LIKE :l2)';
            $whereParams[':l1'] = $payLike;
            $whereParams[':l2'] = $payLike;
        }
        $whereSql .= \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_sql_mixed_named('time', $df['from'], $df['to'], $whereParams, 'd');
        $whereSql .= \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_sql('payment_Status', $payStatus, $payStatusOptions, $whereParams, ':statusVal');
        $whereSql .= \Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_sql('price', $payAf, $whereParams, 'am');
        if ($payMethod !== '') {
            $whereSql .= ' AND Payment_Method = :pmethod';
            $whereParams[':pmethod'] = $payMethod;
        }

        $payStatusActive = $payStatus !== '' && isset($payStatusOptions[$payStatus]);
        $payFilterActive = $payQ !== '' || $df['active'] || $payStatusActive || $payAf['active'] || $payMethod !== '';
        $payDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('', $df['active']);
        $payFilterParams = array_merge(['q' => $payQ !== '' ? $payQ : null, 'status' => $payStatusActive ? $payStatus : null], $payDateKeep, $payExtraKeep);
        $payExtraCriteria = array_filter([
            'مبلغ' => $payAf['label'],
            'روش پرداخت' => $payMethod !== '' ? ($payMethodOptions[$payMethod] ?? $payMethod) : '',
        ], function ($v) { return $v !== ''; });
        $payFilterCriteria = array_merge(\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria($payStatusActive ? $payStatusOptions[$payStatus] : '', $df, $payQ), $payExtraCriteria);
        if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested()) {
            [$fdMatched, $fdDeleted] = $payFilterActive ? \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_where($pdo, 'Payment_report', $whereSql, $whereParams) : [0, 0];
            \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('payment.php', $payFilterParams, $fdMatched, $fdDeleted);
        }

        $pg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM Payment_report WHERE $whereSql", $whereParams, 5);

        $query = $pdo->prepare("SELECT * FROM Payment_report WHERE $whereSql ORDER BY time DESC LIMIT :perPage OFFSET :offset");
        foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
        $query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
        $query->execute();
        $listpayment = $query->fetchAll();

        return true;
    }
}
