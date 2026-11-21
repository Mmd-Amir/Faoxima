<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ProductPage
{
    public static function fail2ban(PDO $pdo, string $panelName): array
    {
        $panelRow = null;
        if ($panelName !== '') {
            $stmtF = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = :n LIMIT 1");
            $stmtF->execute([':n' => $panelName]);
            $panelRow = $stmtF->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$panelRow || $panelRow['type'] !== 'x-ui_single' || !method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_panel_uses_token') || !\Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($panelRow)) {
            return ['ok' => false, 'message' => 'not_applicable'];
        }
        $status = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_fail2ban_status($panelRow);
        if (empty($status['status'])) {
            return ['ok' => false, 'message' => 'unreachable'];
        }
        $data = $status['data'];
        $usable = !empty($data['usable']);
        return ['ok' => true, 'usable' => $usable, 'installed' => !empty($data['installed']), 'enabled' => !empty($data['enabled'])];
    }

    public static function delete(PDO $pdo, int $deleteId): void
    {
        if ($deleteId > 0) {
            $stmt = $pdo->prepare("DELETE FROM product WHERE id = :id");
            $stmt->bindValue(':id', $deleteId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    public static function catalog(PDO $pdo, array $queryParams): array
    {
        $prodQ = (trim((string) ($queryParams['q'] ?? '')));
        $prodWhereSql = '1=1';
        $prodParams = [];
        if ($prodQ !== '') {
            $prodLike = '%' . $prodQ . '%';
            $prodWhereSql .= ' AND (id LIKE :pq1 OR name_product LIKE :pq2 OR Location LIKE :pq3 OR category LIKE :pq4)';
            $prodParams[':pq1'] = $prodLike;
            $prodParams[':pq2'] = $prodLike;
            $prodParams[':pq3'] = $prodLike;
            $prodParams[':pq4'] = $prodLike;
        }

        $pg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM product WHERE $prodWhereSql", $prodParams, 5, query: $queryParams);
        $query = $pdo->prepare("SELECT * FROM product WHERE $prodWhereSql ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT :perPage OFFSET :offset");
        foreach ($prodParams as $k => $v) $query->bindValue($k, $v, PDO::PARAM_STR);
        $query->bindValue(':perPage', $pg['perPage'], PDO::PARAM_INT);
        $query->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
        $query->execute();
        $listinvoice = $query->fetchAll();

        \Faoxima\Admin\Web\Support\Ordering::fx_order_ensure_schema($pdo, 'panel');
        \Faoxima\Admin\Web\Support\Ordering::fx_order_ensure_schema($pdo, 'category');
        $query = $pdo->prepare("SELECT * FROM marzban_panel ORDER BY (position = 0) ASC, position ASC, id ASC");
        $query->execute();
        $listpanel = $query->fetchAll();

        $panelTypeMap = [];
        foreach ($listpanel as $panelRow) {
            $panelTypeMap[$panelRow['name_panel']] = $panelRow['type'];
        }

        $catQuery = $pdo->prepare("SELECT remark FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
        $catQuery->execute();
        $listcategory = $catQuery->fetchAll(PDO::FETCH_COLUMN);

        return [$prodQ, $pg, $listinvoice, $listpanel, $panelTypeMap, $listcategory];
    }

    public static function create(PDO $pdo, array $post, $nameProduct): ?string
    {
        $randomString = bin2hex(random_bytes(2));

        $product_count = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameProduct, "count");
        if ($product_count != 0) {
            return "<script>
alert('محصول از قبل وجود دارد'); window.location.href='product.php';
</script>";
        }

        $hidepanel       = "{}";
        $priceProduct    = $post['price_product']    ?? '';
        $volumeProduct   = $post['volume_product']   ?? '';
        $serviceTime     = $post['time_product']     ?? '';
        $agentProduct    = $post['agent_product']    ?? '';
        $agentProduct    = strtolower(trim((string)$agentProduct));
        if (!in_array($agentProduct, ['f', 'n', 'n2', 'all'], true)) {
            return "<script>alert('گروه کاربری نامعتبر است'); window.location.href='product.php';</script>";
        }

        $locationArr = explode(',', (string)($post['namepanel_csv'] ?? ''));
        $locationArr = array_values(array_unique(array_filter(array_map('trim', $locationArr), function ($v) { return $v !== ''; })));
        if (in_array('/all', $locationArr, true)) $locationArr = ['/all'];
        $location = implode(',', $locationArr);

        $categoryArr = explode(',', (string)($post['cetegory_product_csv'] ?? ''));
        $newCategory = trim((string)($post['cetegory_product_new'] ?? ''));
        if ($newCategory !== '') $categoryArr[] = $newCategory;
        $categoryArr = array_values(array_unique(array_filter(array_map('trim', $categoryArr), function ($v) { return $v !== ''; })));
        $category = implode(',', $categoryArr);
        foreach ($categoryArr as $catValue) {
            $catCheck = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = :r");
            $catCheck->execute([':r' => $catValue]);
            if ((int)$catCheck->fetchColumn() === 0) {
                $pdo->prepare("INSERT IGNORE INTO category (remark) VALUES (:r)")->execute([':r' => $catValue]);
            }
        }
        $note            = $post['note_product']     ?? '';
        $dataLimitReset  = 'no_reset';

        $ipLimitRaw = trim((string)($post['ip_limit_product'] ?? '0'));
        if ($ipLimitRaw === '' || !ctype_digit($ipLimitRaw) || (int)$ipLimitRaw > 50) {
            $ipLimit = '0';
        } else {
            $ipLimit = (string)(int)$ipLimitRaw;
        }

        $hwidLimitRaw = trim((string)($post['hwid_limit_product'] ?? '0'));
        if ($hwidLimitRaw === '' || !ctype_digit($hwidLimitRaw) || (int)$hwidLimitRaw > 50) {
            $hwidLimit = '0';
        } else {
            $hwidLimit = (string)(int)$hwidLimitRaw;
        }

        $symbolicLimitEnabled = !empty($post['symbolic_limit_enabled']) ? '1' : '0';
        $symbolicLimitUsersRaw = trim((string)($post['symbolic_limit_users'] ?? '0'));
        if ($symbolicLimitUsersRaw === '' || !ctype_digit($symbolicLimitUsersRaw) || (int)$symbolicLimitUsersRaw > 50) {
            $symbolicLimitUsers = '0';
        } else {
            $symbolicLimitUsers = (string)(int)$symbolicLimitUsersRaw;
        }
        if ($symbolicLimitEnabled === '1' && (int)$symbolicLimitUsers <= 0) {
            $symbolicLimitEnabled = '0';
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0',:ip_limit,:hwid_limit,:symbolic_limit_enabled,:symbolic_limit_users)");
        $stmt->bindParam(':name_product',     $nameProduct, PDO::PARAM_STR);
        $stmt->bindParam(':code_product',     $randomString);
        $stmt->bindParam(':price_product',    $priceProduct, PDO::PARAM_STR);
        $stmt->bindParam(':Volume_constraint',$volumeProduct, PDO::PARAM_STR);
        $stmt->bindParam(':Service_time',     $serviceTime, PDO::PARAM_STR);
        $stmt->bindParam(':Location',         $location, PDO::PARAM_STR);
        $stmt->bindParam(':agent',            $agentProduct, PDO::PARAM_STR);
        $stmt->bindParam(':data_limit_reset', $dataLimitReset);
        $stmt->bindParam(':category',         $category, PDO::PARAM_STR);
        $stmt->bindParam(':note',             $note, PDO::PARAM_STR);
        $stmt->bindParam(':hide_panel',       $hidepanel);
        $stmt->bindParam(':ip_limit',         $ipLimit, PDO::PARAM_STR);
        $stmt->bindParam(':hwid_limit',       $hwidLimit, PDO::PARAM_STR);
        $stmt->bindParam(':symbolic_limit_enabled', $symbolicLimitEnabled, PDO::PARAM_STR);
        $stmt->bindParam(':symbolic_limit_users',   $symbolicLimitUsers, PDO::PARAM_STR);
        $stmt->execute();

        return null;
    }
}
