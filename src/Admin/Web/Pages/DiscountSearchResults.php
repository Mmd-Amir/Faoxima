<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $df1, $df2, $discDateKeep, $discFilterActive, $discQ, $giftDateKeep, $giftFilterActive, $giftQ, $pdo.
 * Inherited or exported by reference: $flash, $giftList, $list, $listcategory, $listpanel, $p, $panelNameByCode, $pg1, $pg2, $r, $v.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DiscountSearchResults
{
    public static function handle(
        $adminWebPage,
        $df1,
        $df2,
        $discDateKeep,
        $discFilterActive,
        $discQ,
        &$flash,
        $giftDateKeep,
        $giftFilterActive,
        &$giftList,
        $giftQ,
        &$list,
        &$listcategory,
        &$listpanel,
        &$p,
        &$panelNameByCode,
        $pdo,
        &$pg1,
        &$pg2,
        &$r,
        &$v
    ) {
        if (($adminWebPage ?? null) !== 'discounts.php') {
            return;
        }

        try {
            $where1 = '1=1';
            $params1 = [];
            if ($df1['active']) {
                $where1 .= ' AND `time` BETWEEN :dfrom1 AND :dto1';
                $params1[':dfrom1'] = $df1['from'];
                $params1[':dto1'] = $df1['to'];
            }
            if ($discQ !== '') {
                $where1 .= ' AND (codeDiscount LIKE :dq1 OR target_user LIKE :dq2)';
                $params1[':dq1'] = '%' . $discQ . '%';
                $params1[':dq2'] = '%' . $discQ . '%';
            }
            if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested('1')) {
                [$fdMatched, $fdDeleted] = $discFilterActive ? \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_where($pdo, 'DiscountSell', $where1, $params1) : [0, 0];
                $fdParams = array_merge(['q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null, 'p2' => isset($_GET['p2']) && (int)$_GET['p2'] > 1 ? (string)(int)$_GET['p2'] : null], $discDateKeep, $giftDateKeep);
                \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('discounts.php', $fdParams, $fdMatched, $fdDeleted, '1');
            }
            $pg1 = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM DiscountSell WHERE $where1", $params1, 5, 'p1');
            $r = $pdo->prepare("SELECT * FROM DiscountSell WHERE $where1 ORDER BY id DESC LIMIT :perPage OFFSET :offset");
            foreach ($params1 as $k => $v) $r->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $r->bindValue(':perPage', $pg1['perPage'], PDO::PARAM_INT);
            $r->bindValue(':offset', $pg1['offset'], PDO::PARAM_INT);
            $r->execute();
            $list = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $flash['err'] = 'بارگذاری ناموفق: ' . $e->getMessage();
        }

        $giftList = [];
        $pg2 = ['page' => 1, 'perPage' => 5, 'offset' => 0, 'total' => 0, 'pages' => 1];
        try {
            $where2 = '1=1';
            $params2 = [];
            if ($df2['active']) {
                $where2 .= ' AND expire_at BETWEEN :dfrom2 AND :dto2';
                $params2[':dfrom2'] = $df2['from'];
                $params2[':dto2'] = $df2['to'];
            }
            if ($giftQ !== '') {
                $where2 .= ' AND (code LIKE :gq1 OR target_user LIKE :gq2)';
                $params2[':gq1'] = '%' . $giftQ . '%';
                $params2[':gq2'] = '%' . $giftQ . '%';
            }
            if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested('2')) {
                [$fdMatched, $fdDeleted] = $giftFilterActive ? \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_where($pdo, 'Discount', $where2, $params2) : [0, 0];
                $fdParams = array_merge(['q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null, 'p1' => $pg1['page'] > 1 ? (string)$pg1['page'] : null], $discDateKeep, $giftDateKeep);
                \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('discounts.php', $fdParams, $fdMatched, $fdDeleted, '2');
            }
            $pg2 = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM Discount WHERE $where2", $params2, 5, 'p2');
            $rg = $pdo->prepare("SELECT * FROM Discount WHERE $where2 ORDER BY id DESC LIMIT :perPage OFFSET :offset");
            foreach ($params2 as $k => $v) $rg->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $rg->bindValue(':perPage', $pg2['perPage'], PDO::PARAM_INT);
            $rg->bindValue(':offset', $pg2['offset'], PDO::PARAM_INT);
            $rg->execute();
            $giftList = $rg->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {  }

        $listcategory = [];
        try {
            $catQuery = $pdo->prepare("SELECT remark FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
            $catQuery->execute();
            $listcategory = $catQuery->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {  }

        $listpanel = [];
        $panelNameByCode = [];
        try {
            $panQuery = $pdo->prepare("SELECT * FROM marzban_panel ORDER BY (position = 0) ASC, position ASC, id ASC");
            $panQuery->execute();
            $listpanel = $panQuery->fetchAll(PDO::FETCH_ASSOC);
            foreach ($listpanel as $p) {
                $panelNameByCode[(string)($p['code_panel'] ?? '')] = (string)($p['name_panel'] ?? '');
            }
        } catch (\Throwable $e) {  }







        return true;
    }
}
