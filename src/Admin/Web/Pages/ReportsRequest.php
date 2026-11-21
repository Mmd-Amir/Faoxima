<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $avgOrder, $discountCount, $discountTopCodes, $discountTotal, $groupRows, $incomeToday, $json_group_labels, $json_group_users, $json_prod_data, $json_prod_labels, $prodData, $prodLabels, $result, $topActive, $topBalance, $topBuyers, $topDeposit, $totalOrders, $totalUsers.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReportsRequest
{
    public static function handle(
        &$avgOrder,
        &$discountCount,
        &$discountTopCodes,
        &$discountTotal,
        &$groupRows,
        &$incomeToday,
        &$json_group_labels,
        &$json_group_users,
        &$json_prod_data,
        &$json_prod_labels,
        $pdo,
        &$prodData,
        &$prodLabels,
        &$result,
        &$topActive,
        &$topBalance,
        &$topBuyers,
        &$topDeposit,
        &$totalOrders,
        &$totalUsers
    ) {
        $sessionUser = isset($_SESSION["user"]) && is_string($_SESSION["user"]) && $_SESSION["user"] !== ''
            ? $_SESSION["user"]
            : null;

        if ($sessionUser === null) {
            header('Location: login.php');
            exit;
        }

        $result = \Faoxima\Admin\Web\Support\Csrf::fx_admin_find_by_username($pdo, $sessionUser);

        if (!$result) {
            $_SESSION = [];
            header('Location: login.php');
            exit;
        }




        $reportData = \Faoxima\Admin\Web\Pages\ReportsRequestHelpers::fx_admin_report_data($pdo);
        [
            'incomeToday' => $incomeToday,
            'totalIncome' => $totalIncome,
            'totalOrders' => $totalOrders,
            'totalUsers' => $totalUsers,
            'avgOrder' => $avgOrder,
            'prodLabels' => $prodLabels,
            'prodData' => $prodData,
            'groupRows' => $groupRows,
            'topBuyers' => $topBuyers,
            'topActive' => $topActive,
            'topDeposit' => $topDeposit,
            'topBalance' => $topBalance,
            'discountTotal' => $discountTotal,
            'discountCount' => $discountCount,
            'discountTopCodes' => $discountTopCodes,
        ] = $reportData;

        $json_prod_labels  = json_encode($prodLabels, JSON_UNESCAPED_UNICODE);
        $json_prod_data    = json_encode($prodData);
        $json_group_labels = json_encode(array_column($groupRows, 'label'), JSON_UNESCAPED_UNICODE);
        $json_group_users  = json_encode(array_map('intval', array_column($groupRows, 'users')));

        return true;
    }
}
