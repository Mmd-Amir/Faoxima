<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ServiceRequestHelpers
{
    public static function fx_service_history_filters(string $svcQ, array $df, $svcStatus, array $svcTypeOptions): array
{
    $whereSql = '1=1';
    $whereParams = [];
    if ($svcQ !== '') {
        $svcLike = '%' . $svcQ . '%';
        $whereSql .= ' AND (id_user LIKE :l1 OR username LIKE :l2)';
        $whereParams[':l1'] = $svcLike;
        $whereParams[':l2'] = $svcLike;
    }
    $whereSql .= \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_sql_mixed_named('time', $df['from'], $df['to'], $whereParams, 'd');
    $whereSql .= \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_sql('type', $svcStatus, $svcTypeOptions, $whereParams, ':typeVal');
    return [$whereSql, $whereParams];
}

    public static function fx_service_history_page(PDO $pdo, string $whereSql, array $whereParams): array
{
    $pg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM service_other WHERE $whereSql", $whereParams, 5);

    $query = $pdo->prepare("SELECT * FROM service_other WHERE $whereSql ORDER BY id DESC LIMIT :perPage OFFSET :offset");
    foreach ($whereParams as $k => $v) $query->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
    $query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
    $query->execute();
    $listservices = $query->fetchAll();
    return [$pg, $listservices];
}
}
