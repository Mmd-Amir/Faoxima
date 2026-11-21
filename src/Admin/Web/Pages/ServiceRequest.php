<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $df, $listservices, $pg, $svcDateKeep, $svcFilterActive, $svcFilterCriteria, $svcFilterParams, $svcQ, $svcStatus, $svcStatusActive, $svcTypeOptions.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceRequest
{
    public static function handle(
        &$df,
        &$listservices,
        $pdo,
        &$pg,
        &$svcDateKeep,
        &$svcFilterActive,
        &$svcFilterCriteria,
        &$svcFilterParams,
        &$svcQ,
        &$svcStatus,
        &$svcStatusActive,
        &$svcTypeOptions
    ) {
        \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);

        if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
            $requestedIds = $_POST['ids'] ?? [];
            $deletedCount = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'service_other', 'id', $requestedIds);
            \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('service.php', count($requestedIds), $deletedCount);
        }





        $svcQ = trim((string)($_GET['q'] ?? ''));
        $df = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();
        $svcTypeOptions = [
            'extend_user'          => 'تمدید سرویس',
            'extend_user_by_admin' => 'تمدید توسط ادمین',
            'extra_user'           => 'حجم اضافه',
            'extra_time_user'      => 'زمان اضافه',
            'transfertouser'       => 'انتقال سرویس',
            'extends_not_user'     => 'تمدید (خارج از لیست)',
            'change_location'      => 'تغییر لوکیشن',
        ];
        $svcStatus = \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current();

        [$whereSql, $whereParams] = \Faoxima\Admin\Web\Pages\ServiceRequestHelpers::fx_service_history_filters($svcQ, $df, $svcStatus, $svcTypeOptions);

        $svcStatusActive = $svcStatus !== '' && isset($svcTypeOptions[$svcStatus]);
        $svcFilterActive = $svcQ !== '' || $df['active'] || $svcStatusActive;
        $svcDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('', $df['active']);
        $svcFilterParams = array_merge(['q' => $svcQ !== '' ? $svcQ : null, 'status' => $svcStatusActive ? $svcStatus : null], $svcDateKeep);
        $svcFilterCriteria = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria($svcStatusActive ? $svcTypeOptions[$svcStatus] : '', $df, $svcQ, 'نوع خدمت');
        if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested()) {
            [$fdMatched, $fdDeleted, $fdFailed] = $svcFilterActive
                ? array_pad(\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_where($pdo, 'service_other', $whereSql, $whereParams), 3, false)
                : [0, 0, false];
            \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('service.php', $svcFilterParams, $fdMatched, $fdDeleted, '', $fdFailed);
        }

        [$pg, $listservices] = \Faoxima\Admin\Web\Pages\ServiceRequestHelpers::fx_service_history_page($pdo, $whereSql, $whereParams);

        return true;
    }
}
