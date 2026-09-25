<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/csrf.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

fx_csrf_guard();

$statsQ = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(User_Status)='block' THEN 1 ELSE 0 END) AS blocked, SUM(Balance) AS balance FROM user");
$statsRow = $statsQ ? $statsQ->fetch(PDO::FETCH_ASSOC) : ['total' => 0, 'blocked' => 0, 'balance' => 0];
$u_total = (int)($statsRow['total'] ?? 0);
$u_block = (int)($statsRow['blocked'] ?? 0);
$u_active = $u_total - $u_block;
$u_balance = (int)($statsRow['balance'] ?? 0);

$userQ = fx_search_current();
$userFilterRaw = $_GET['ufilter'] ?? '';
$userFilter = (is_string($userFilterRaw) && in_array($userFilterRaw, ['active', 'block', 'balance'], true)) ? $userFilterRaw : '';
$userWhereSql = '1=1';
$userParams = [];
$userOrderSql = 'id DESC';
if ($userFilter === 'active') {
    $userWhereSql .= " AND (User_Status IS NULL OR LOWER(User_Status) <> 'block')";
} elseif ($userFilter === 'block') {
    $userWhereSql .= " AND LOWER(User_Status) = 'block'";
} elseif ($userFilter === 'balance') {
    $userWhereSql .= ' AND Balance > 0';
    $userOrderSql = 'Balance DESC, id DESC';
}
$userCardLink = function (string $filter) use ($userQ, $userFilter) {
    $qs = fx_qs(['q' => $userQ !== '' ? $userQ : null, 'ufilter' => $filter !== '' ? $filter : null]);
    $href = 'users.php' . ($qs !== '' ? '?' . $qs : '');
    $style = 'text-decoration:none; color:inherit; cursor:pointer;' . ($userFilter === $filter ? ' border-color:var(--accent); box-shadow:0 0 0 1px var(--accent);' : '');
    return 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" style="' . $style . '"' . ($userFilter === $filter ? ' aria-current="true"' : '');
};
if ($userQ !== '') {
    $userLike = '%' . $userQ . '%';
    $userWhereSql .= ' AND (id LIKE :uq1 OR username LIKE :uq2 OR namecustom LIKE :uq3 OR number LIKE :uq4 OR number_username LIKE :uq5)';
    $userParams[':uq1'] = $userLike;
    $userParams[':uq2'] = $userLike;
    $userParams[':uq3'] = $userLike;
    $userParams[':uq4'] = $userLike;
    $userParams[':uq5'] = $userLike;
}

$pg = fx_paginate($pdo, "SELECT COUNT(*) FROM user WHERE $userWhereSql", $userParams, 5);
$query = $pdo->prepare("SELECT * FROM user WHERE $userWhereSql ORDER BY $userOrderSql LIMIT :perPage OFFSET :offset");
foreach ($userParams as $k => $v) $query->bindValue($k, $v, PDO::PARAM_STR);
$query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
$query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$query->execute();
$listusers = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت کاربران | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
<script src="js/theme.js?v=flat50" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-list">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('users', 'svg-icon svg-lg'); ?>
                        لیست کاربران
                    </div>
                    <div class="page-head__sub">مدیریت و مشاهده اطلاعات کاربران ربات</div>
                </div>
            </div>

            <div class="stats-grid">
                <a class="stat-card observe-in" <?php echo $userCardLink(''); ?>>
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">کل کاربران</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$u_total; ?>"><?php echo number_format($u_total); ?></span>
                        </div>
                        <span class="stat-card__icon icon-blue"><?php echo icon('users', 'svg-icon'); ?></span>
                    </div>
                </a>
                <a class="stat-card observe-in" <?php echo $userCardLink('active'); ?>>
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">فعال</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$u_active; ?>"><?php echo number_format($u_active); ?></span>
                        </div>
                        <span class="stat-card__icon icon-green"><?php echo icon('users', 'svg-icon'); ?></span>
                    </div>
                </a>
                <a class="stat-card observe-in" <?php echo $userCardLink('block'); ?>>
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">مسدود</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$u_block; ?>"><?php echo number_format($u_block); ?></span>
                        </div>
                        <span class="stat-card__icon icon-rose"><?php echo icon('users', 'svg-icon'); ?></span>
                    </div>
                </a>
                <a class="stat-card observe-in" <?php echo $userCardLink('balance'); ?>>
                    <div class="stat-card__top">
                        <div class="stat-card__info">
                            <span class="stat-card__label">مجموع موجودی</span>
                            <span class="stat-card__value" data-count="<?php echo (int)$u_balance; ?>" data-suffix=" T"><?php echo number_format($u_balance); ?> T</span>
                        </div>
                        <span class="stat-card__icon icon-amber"><svg class="svg-icon" viewBox="-1.5 0 33 33" aria-hidden="true"><g transform="translate(-259,-776)" fill="currentColor"><path fill="currentColor" stroke="none" d="M283,799 L289,799 L289,797 L283,797 L283,799 Z M287,787 L259,787 L259,807 C259,808.104 259.896,809 261,809 L287,809 C288.104,809 289,808.104 289,807 L289,801 L282,801 C281.448,801 281,800.553 281,800 L281,796 C281,795.448 281.448,795 282,795 L289,795 L289,789 C289,787.896 288.104,787 287,787 L287,787 Z M287,778 C287,777.447 286.764,777.141 286.25,776.938 C285.854,776.781 285.469,776.875 285,777 L259,785 L287,785 L287,778 L287,778 Z"/></g></svg></span>
                    </div>
                </a>
            </div>

            <?php echo fx_search_ui('users.php', $userQ, ['ufilter' => $userFilter !== '' ? $userFilter : null], 'جستجو در شناسه، نام کاربری، شماره تلفن یا نام سفارشی…'); ?>

            <div class="card">
                <div class="table-wrap">
                    <table id="usersTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th>شناسه (ID)</th>
                                <th>نام کاربری</th>
                                <th>شماره تلفن</th>
                                <th>موجودی</th>
                                <th>زیرمجموعه</th>
                                <th>وضعیت</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $u_i = 0; foreach ($listusers as $list):
                            $statusClass = 'badge-active';
                            $statusText  = 'فعال';
                            if (strtolower($list['User_Status']) == 'block') {
                                $statusClass = 'badge-block';
                                $statusText  = 'مسدود';
                            }
                            $number = ($list['number'] == "none") ? '<span class="text-muted">---</span>' : htmlspecialchars($list['number'], ENT_QUOTES, 'UTF-8');
                            $uname = htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8');
                            $uinit = mb_strtoupper(mb_substr(trim((string)$list['username']), 0, 1, 'UTF-8'), 'UTF-8');
                            if ($uinit === '') { $uinit = '#'; }
                            $av = $u_i % 4; $u_i++;
                        ?>
                            <tr data-detail-row data-detail-title="<?php echo $uname; ?>">
                                <td data-label="شناسه (ID)" data-summary="1"><?php echo $list['id']; ?></td>
                                <td data-label="نام کاربری" data-summary="1">
                                    <span class="cell-user">
                                        <span class="user-avatar av<?php echo $av; ?>"><?php echo htmlspecialchars($uinit, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="cell-user__text">
                                            <span class="cell-user__name" style="direction:ltr;"><?php echo $uname; ?></span>
                                            <span class="cell-user__sub">شناسه: <?php echo $list['id']; ?></span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="شماره تلفن"><?php echo $number; ?></td>
                                <td data-label="موجودی"><?php echo number_format($list['Balance']); ?> <small class="text-muted">T</small></td>
                                <td data-label="زیرمجموعه"><?php echo (int)$list['affiliatescount']; ?> <small class="text-muted">نفر</small></td>
                                <td data-label="وضعیت" data-summary="1" data-filter-value="<?php echo $statusText; ?>"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-action" title="مدیریت" aria-label="مدیریت">
                                        <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php echo fx_pager_html($pg['page'], $pg['pages'], $pg['total'], count($listusers), 'users.php', ['q' => $userQ !== '' ? $userQ : null, 'ufilter' => $userFilter !== '' ? $userFilter : null]); ?>
                </div>
                </div>
            </div>

        </div>
    </section>
</section>
</body>
</html>


