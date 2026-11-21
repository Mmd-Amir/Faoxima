<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $v.
 * Inherited or exported by reference: $catQ, $categories, $pg, $query, $result, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CategoryRequest
{
    public static function handle(
        &$catQ,
        &$categories,
        $pdo,
        &$pg,
        &$query,
        &$result,
        &$stmt,
        $v
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

        if (!empty($_POST['action']) && $_POST['action'] === 'add') {
            $remark = trim((string)($_POST['remark'] ?? ''));
            if ($remark !== '') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = :remark");
                $stmt->execute([':remark' => $remark]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO category (remark) VALUES (:remark)");
                    $stmt->execute([':remark' => $remark]);
                }
            }
            header('Location: category.php');
            exit;
        }

        if (!empty($_POST['action']) && $_POST['action'] === 'edit') {
            $id     = (int)($_POST['id'] ?? 0);
            $remark = trim((string)($_POST['remark'] ?? ''));
            if ($id > 0 && $remark !== '') {
                $old = $pdo->prepare("SELECT remark FROM category WHERE id = :id");
                $old->execute([':id' => $id]);
                $oldRemark = (string)($old->fetchColumn() ?: '');
                if ($oldRemark !== '' && $oldRemark !== $remark) {
                    \Faoxima\Orders\Catalog\NameCascades::rxCategoryRenameCascade($pdo, $oldRemark, $remark);
                }
            }
            header('Location: category.php');
            exit;
        }

        if (!empty($_POST['action']) && $_POST['action'] === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $old = $pdo->prepare("SELECT remark FROM category WHERE id = :id");
                $old->execute([':id' => $id]);
                $oldRemark = (string)($old->fetchColumn() ?: '');
                if ($oldRemark !== '') {
                    \Faoxima\Orders\Catalog\NameCascades::rxCategoryDeleteGuarded($pdo, $oldRemark, $id);
                }
            }
            header('Location: category.php');
            exit;
        }

        if (!empty($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            $requestedIds = $_POST['ids'] ?? [];
            $deletedCount = 0;
            $remarkStmt = $pdo->prepare("SELECT remark FROM category WHERE id = :id");
            foreach (array_values(array_unique(array_filter(array_map('intval', (array) $requestedIds), function ($v) { return $v > 0; }))) as $bulkId) {
                $remarkStmt->execute([':id' => $bulkId]);
                $bulkRemark = (string)($remarkStmt->fetchColumn() ?: '');
                if ($bulkRemark !== '' && \Faoxima\Orders\Catalog\NameCascades::rxCategoryDeleteGuarded($pdo, $bulkRemark, $bulkId)) {
                    $deletedCount++;
                }
            }
            \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('category.php', count($requestedIds), $deletedCount);
        }

        \Faoxima\Admin\Web\Support\Ordering::fx_order_ensure_schema($pdo, 'category');
        $catQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current();
        $catWhereSql = '1=1';
        $catParams = [];
        if ($catQ !== '') {
            $catLike = '%' . $catQ . '%';
            $catWhereSql .= ' AND (id LIKE :cq1 OR remark LIKE :cq2)';
            $catParams[':cq1'] = $catLike;
            $catParams[':cq2'] = $catLike;
        }

        $pg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM category WHERE $catWhereSql", $catParams, 5);
        $query = $pdo->prepare("SELECT * FROM category WHERE $catWhereSql ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT :perPage OFFSET :offset");
        foreach ($catParams as $k => $v) $query->bindValue($k, $v, PDO::PARAM_STR);
        $query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
        $query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
        $query->execute();
        $categories = $query->fetchAll();

        return true;
    }
}
