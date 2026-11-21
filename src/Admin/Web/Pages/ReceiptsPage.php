<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ReceiptsPage
{
    public static function submit(array $post, string $actor): array
    {
        $action = (string) $post['_rx_action'];
        $keep = (string) ($post['_keep_qs'] ?? '');
        if ($action === 'bulk_delete') {
            [$flash, $deleted] = ReceiptsRequestHelpers::fx_receipts_bulk_result($post);
            $flag = 'bulk=' . ($deleted > 0 ? 'ok' : 'err');
            return [$flash, 'receipts.php?' . ($keep !== '' ? $keep . '&' : '') . $flag];
        }
        $flash = ReceiptsRequestHelpers::fx_receipts_action($action, (string) ($post['id_order'] ?? ''), $actor, $post);
        return [$flash, 'receipts.php' . ($keep !== '' ? '?' . $keep : '')];
    }

    public static function view(
        PDO $pdo,
        array $query,
        array $rxDf,
        string $rxStatus,
        array $rxAf,
        string $rxType,
        array $rxDateKeep,
        bool $filterDelete,
    ): array {
        $rxQ = trim((string) ($query['q'] ?? ''));
        $rxStatusOptions = [
            'waiting'    => 'در انتظار بررسی',
            'processing' => 'در حال پردازش',
            'paid'       => 'تایید شده',
            'reject'     => 'رد شده',
            'expire'     => 'منقضی شده',
            'cancelled'  => 'لغو شده',
        ];
        $rxTypeOptions = [
            'getconfigafterpay'  => 'خرید سرویس',
            'getextenduser'      => 'تمدید سرویس',
            'getextravolumeuser' => 'افزایش حجم',
            'getextratimeuser'   => 'افزایش زمان',
            'wallet'             => 'شارژ کیف پول',
        ];
        if (!isset($rxTypeOptions[$rxType])) $rxType = '';
        $rxExtraKeep = array_merge(\Faoxima\Admin\Web\Support\ExtraFilter::fx_amount_filter_keep($rxAf), ['otype' => $rxType !== '' ? $rxType : null]);

        [$rxWhereSql, $rxWhereParams] = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::fx_receipts_filter($rxQ, $rxDf, $rxStatus, $rxStatusOptions, $rxAf, $rxType);

        $rxStatusActive = $rxStatus !== '' && isset($rxStatusOptions[$rxStatus]);
        $rxFilterActive = $rxQ !== '' || $rxDf['active'] || $rxStatusActive || $rxAf['active'] || $rxType !== '';
        $rxFilterParams = array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatusActive ? $rxStatus : null], $rxDateKeep, $rxExtraKeep);
        $rxExtraCriteria = array_filter([
            'مبلغ' => $rxAf['label'],
            'نوع سفارش' => $rxType !== '' ? $rxTypeOptions[$rxType] : '',
        ], function ($v) { return $v !== ''; });
        $rxFilterCriteria = array_merge(\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria($rxStatusActive ? $rxStatusOptions[$rxStatus] : '', $rxDf, $rxQ), $rxExtraCriteria);
        if ($filterDelete) {
            $fdMatched = 0;
            $fdDeleted = 0;
            if ($rxFilterActive) {
                $fdOrderIds = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_collect($pdo, 'Payment_report', 'id_order', $rxWhereSql, $rxWhereParams);
                $fdMatched = count($fdOrderIds);
                $fdDeleted = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::fx_receipts_delete_orders($fdOrderIds);
            }
            \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('receipts.php', $rxFilterParams, $fdMatched, $fdDeleted);
        }

        [$rxPg, $rxRows] = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::fx_receipts_page($pdo, $rxWhereSql, $rxWhereParams);

        [$rxPendingCount, $rxConfirmedTodayCount, $rxConfirmedTodayAmount, $rxRejectedTodayCount] = \Faoxima\Admin\Web\Pages\ReceiptsRequestHelpers::fx_receipts_summary($pdo);

        $rxStatusMeta = [
            'waiting'    => ['label' => 'در انتظار بررسی', 'class' => 'badge-warning'],
            'processing' => ['label' => 'در حال پردازش',   'class' => 'badge-info'],
            'paid'       => ['label' => 'تایید شده',        'class' => 'badge-success'],
            'reject'     => ['label' => 'رد شده',           'class' => 'badge-danger'],
            'expire'     => ['label' => 'منقضی شده',        'class' => 'badge-gray'],
            'cancelled'  => ['label' => 'لغو شده',          'class' => 'badge-gray'],
        ];

        $rxKeepQsArr = array_merge(['q' => $rxQ !== '' ? $rxQ : null, 'status' => $rxStatus !== '' ? $rxStatus : null], $rxExtraKeep);
        foreach (['dpreset', 'ddays', 'dfrom', 'dto', 'p'] as $dk) {
            if (isset($query[$dk]) && $query[$dk] !== '') $rxKeepQsArr[$dk] = $query[$dk];
        }
        $rxKeepQsString = http_build_query(array_filter($rxKeepQsArr, function ($v) { return $v !== null; }));

        return [$rxQ, $rxStatusOptions, $rxExtraKeep, $rxStatus, $rxFilterActive, $rxFilterParams, $rxDateKeep, $rxFilterCriteria, $rxPg, $rxRows, $rxPendingCount, $rxConfirmedTodayCount, $rxConfirmedTodayAmount, $rxRejectedTodayCount, $rxStatusMeta, $rxKeepQsArr, $rxKeepQsString];
    }
}
