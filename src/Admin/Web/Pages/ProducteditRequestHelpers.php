<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class ProducteditRequestHelpers
{
    public static function fx_product_edit_normalize_list(array $values): array
{
    return array_values(array_unique(array_filter(array_map(function ($value) {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }, $values), function ($value) { return $value !== ''; })));
}

    public static function fx_product_edit_limit($value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '' || !ctype_digit($raw) || (int)$raw > 50) {
        return null;
    }
    return (string)(int)$raw;
}

    public static function fx_product_edit_save(PDO $pdo, $product, string $id_product, array $post): array
{
    $statusmessage = false;
    $infomesssage = "";
    $name_product = htmlspecialchars($post['name_product'], ENT_QUOTES, 'UTF-8');
    $prodcutcheck = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $name_product, "count");
    if ($product['name_product'] != $name_product && $prodcutcheck != 0) {
        $statusmessage = true;
        $infomesssage  = "نام محصول تکراری است.";
    } else {
        if ($product['name_product'] != $name_product) {
            \Faoxima\Core\Database\Db::update("product", "name_product", $name_product, "id", $id_product);
        }
    }

    $price_product = htmlspecialchars($post['price_product'], ENT_QUOTES, 'UTF-8');
    if (!is_numeric($price_product)) {
        $statusmessage = true; $infomesssage = "مبلغ محصول باید عدد باشد";
    } elseif ($product['price_product'] != $price_product) {
        \Faoxima\Core\Database\Db::update("product", "price_product", $price_product, "id", $id_product);
    }

    $Volume_constraint = htmlspecialchars($post['Volume_constraint'], ENT_QUOTES, 'UTF-8');
    if (!is_numeric($Volume_constraint)) {
        $statusmessage = true; $infomesssage = "حجم محصول باید عدد باشد";
    } elseif ($product['Volume_constraint'] != $Volume_constraint) {
        \Faoxima\Core\Database\Db::update("product", "Volume_constraint", $Volume_constraint, "id", $id_product);
    }

    $Service_time = htmlspecialchars($post['Service_time'], ENT_QUOTES, 'UTF-8');
    if (!is_numeric($Service_time)) {
        $statusmessage = true; $infomesssage = "زمان محصول باید عدد باشد";
    } elseif ($product['Service_time'] != $Service_time) {
        \Faoxima\Core\Database\Db::update("product", "Service_time", $Service_time, "id", $id_product);
    }

    $agent = htmlspecialchars($post['agent'], ENT_QUOTES, 'UTF-8');
    if (!in_array($agent, ['f', 'n', 'n2'])) {
        $statusmessage = true; $infomesssage = "گروه کاربری نامعتبر است";
    } elseif ($product['agent'] != $agent) {
        \Faoxima\Core\Database\Db::update("product", "agent", $agent, "id", $id_product);
    }

    $categoryArr = explode(',', (string)($post['category_csv'] ?? ''));
    $newCategory = trim((string)($post['category_new'] ?? ''));
    if ($newCategory !== '') $categoryArr[] = $newCategory;
    $categoryArr = self::fx_product_edit_normalize_list($categoryArr);
    $category = implode(',', $categoryArr);
    foreach ($categoryArr as $catValue) {
        $catCheck = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = :r");
        $catCheck->execute([':r' => $catValue]);
        if ((int)$catCheck->fetchColumn() === 0) {
            $pdo->prepare("INSERT IGNORE INTO category (remark) VALUES (:r)")->execute([':r' => $catValue]);
        }
    }
    if ($product['category'] != $category) {
        \Faoxima\Core\Database\Db::update("product", "category", $category, "id", $id_product);
    }

    $locationArr = explode(',', (string)($post['Location_csv'] ?? ''));
    $locationArr = self::fx_product_edit_normalize_list($locationArr);
    if (in_array('/all', $locationArr, true)) $locationArr = ['/all'];
    $locationPost = implode(',', $locationArr);
    if ($product['Location'] != $locationPost) {
        \Faoxima\Core\Database\Db::update("product", "Location", $locationPost, "id", $id_product);
    }

    $note = htmlspecialchars($post['note'], ENT_QUOTES, 'UTF-8');
    if ($product['note'] != $note) {
        \Faoxima\Core\Database\Db::update("product", "note", $note, "id", $id_product);
    }

    $ipLimit = self::fx_product_edit_limit($post['ip_limit'] ?? '0');
    if ($ipLimit === null) {
        $statusmessage = true; $infomesssage = "محدودیت IP باید عددی بین ۰ تا ۵۰ باشد";
    } else {
        if ((string)($product['ip_limit'] ?? '0') != $ipLimit) {
            \Faoxima\Core\Database\Db::update("product", "ip_limit", $ipLimit, "id", $id_product);
        }
    }

    $hwidLimit = self::fx_product_edit_limit($post['hwid_limit'] ?? '0');
    if ($hwidLimit === null) {
        $statusmessage = true; $infomesssage = "محدودیت HWID باید عددی بین ۰ تا ۵۰ باشد";
    } else {
        if ((string)($product['hwid_limit'] ?? '0') != $hwidLimit) {
            \Faoxima\Core\Database\Db::update("product", "hwid_limit", $hwidLimit, "id", $id_product);
        }
    }

    $symbolicLimitUsers = self::fx_product_edit_limit($post['symbolic_limit_users'] ?? '0');
    if ($symbolicLimitUsers === null) {
        $statusmessage = true; $infomesssage = "تعداد کاربر Limiet باید عددی بین ۰ تا ۵۰ باشد";
    } else {
        $symbolicLimitEnabled = (!empty($post['symbolic_limit_enabled']) && (int)$symbolicLimitUsers > 0) ? '1' : '0';
        if ((string)($product['symbolic_limit_users'] ?? '0') != $symbolicLimitUsers) {
            \Faoxima\Core\Database\Db::update("product", "symbolic_limit_users", $symbolicLimitUsers, "id", $id_product);
        }
        if ((string)($product['symbolic_limit_enabled'] ?? '0') != $symbolicLimitEnabled) {
            \Faoxima\Core\Database\Db::update("product", "symbolic_limit_enabled", $symbolicLimitEnabled, "id", $id_product);
        }
    }
    return [$statusmessage, $infomesssage];
}
}
