<?php

namespace Faoxima\Admin\Users;

use PDO;

final class UserDirectory
{
    public static function summary(PDO $pdo): array
    {
        $statsQ = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(User_Status)='block' THEN 1 ELSE 0 END) AS blocked, SUM(Balance) AS balance FROM user");
        $statsRow = $statsQ ? $statsQ->fetch(PDO::FETCH_ASSOC) : ['total' => 0, 'blocked' => 0, 'balance' => 0];
        $u_total = (int)($statsRow['total'] ?? 0);
        $u_block = (int)($statsRow['blocked'] ?? 0);
        $u_active = $u_total - $u_block;
        $u_balance = (int)($statsRow['balance'] ?? 0);
        return [$u_total, $u_block, $u_active, $u_balance];
    }

    public static function page(PDO $pdo, string $userQ, string $userFilter): array
    {
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
        if ($userQ !== '') {
            $userLike = '%' . $userQ . '%';
            $userWhereSql .= ' AND (id LIKE :uq1 OR username LIKE :uq2 OR namecustom LIKE :uq3 OR number LIKE :uq4 OR number_username LIKE :uq5)';
            $userParams[':uq1'] = $userLike;
            $userParams[':uq2'] = $userLike;
            $userParams[':uq3'] = $userLike;
            $userParams[':uq4'] = $userLike;
            $userParams[':uq5'] = $userLike;
        }

        $pg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM user WHERE $userWhereSql", $userParams, 5);
        $query = $pdo->prepare("SELECT * FROM user WHERE $userWhereSql ORDER BY $userOrderSql LIMIT :perPage OFFSET :offset");
        foreach ($userParams as $k => $v) $query->bindValue($k, $v, PDO::PARAM_STR);
        $query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
        $query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
        $query->execute();
        $listusers = $query->fetchAll();
        return [$pg, $listusers];
    }
}
