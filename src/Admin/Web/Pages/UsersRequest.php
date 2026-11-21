<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $listusers, $pg, $query, $result, $u_active, $u_balance, $u_block, $u_total, $userCardLink, $userFilter, $userQ.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UsersRequest
{
    public static function handle(
        &$listusers,
        $pdo,
        &$pg,
        &$query,
        &$result,
        &$u_active,
        &$u_balance,
        &$u_block,
        &$u_total,
        &$userCardLink,
        &$userFilter,
        &$userQ
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






        [$u_total, $u_block, $u_active, $u_balance] = \Faoxima\Admin\Web\Pages\UsersRequestHelpers::fx_users_summary($pdo);

        $userQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current();
        $userFilterRaw = $_GET['ufilter'] ?? '';
        $userFilter = (is_string($userFilterRaw) && in_array($userFilterRaw, ['active', 'block', 'balance'], true)) ? $userFilterRaw : '';

        $userCardLink = function (string $filter) use ($userQ, $userFilter) {
            $qs = \Faoxima\Admin\Web\Support\Pagination::fx_qs(['q' => $userQ !== '' ? $userQ : null, 'ufilter' => $filter !== '' ? $filter : null]);
            $href = 'users.php' . ($qs !== '' ? '?' . $qs : '');
            $style = 'text-decoration:none; color:inherit; cursor:pointer;' . ($userFilter === $filter ? ' border-color:var(--accent); box-shadow:0 0 0 1px var(--accent);' : '');
            return 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" style="' . $style . '"' . ($userFilter === $filter ? ' aria-current="true"' : '');
        };
        [$pg, $listusers] = \Faoxima\Admin\Web\Pages\UsersRequestHelpers::fx_users_page($pdo, $userQ, $userFilter);

        return true;
    }
}
