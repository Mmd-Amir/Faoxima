<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $SHOP_GROUPS, $_csrf, $query, $r, $row, $savedNum, $shopRows, $showSaved, $tableMissing.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ShopsettingsRequest
{
    public static function handle(
        &$SHOP_GROUPS,
        &$_csrf,
        $pdo,
        &$query,
        &$r,
        &$row,
        &$savedNum,
        &$shopRows,
        &$showSaved,
        &$tableMissing
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $_csrf = $_SESSION['csrf_token'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $incoming = $_POST['_csrf'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
                http_response_code(403);
                exit('درخواست نامعتبر — توکن CSRF اشتباه است');
            }
        }


        $tableMissing = false;
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'shopSetting'");
            if ($check && $check->fetchColumn() === false) $tableMissing = true;
        } catch (\Throwable $e) {
            $tableMissing = true;
        }


        $SHOP_GROUPS = [
            'capabilities' => [
                'title' => 'قابلیت‌های فروشگاه',
                'icon'  => 'package',
                'fields' => [
                    ['type' => 'toggle', 'name' => 'statusextra',        'label' => 'فعال‌سازی خرید حجم اضافه',          'on' => 'onextra',         'off' => 'offextra'],
                    ['type' => 'toggle', 'name' => 'statustimeextra',    'label' => 'فعال‌سازی خرید زمان اضافه',         'on' => 'ontimeextraa',    'off' => 'offtimeextraa'],
                    ['type' => 'toggle', 'name' => 'statusdirectpabuy',  'label' => 'پرداخت مستقیم (بدون شارژ کیف پول)','on' => 'ondirectbuy',     'off' => 'offdirectbuy'],
                    ['type' => 'toggle', 'name' => 'statusdisorder',     'label' => 'حالت غیرفعال‌سازی فروش (Disorder)', 'on' => 'ondisorder',      'off' => 'offdisorder'],
                    ['type' => 'toggle', 'name' => 'statuschangeservice','label' => 'اجازه‌ی تغییر سرویس',                'on' => 'onstatus',        'off' => 'offstatus'],
                    ['type' => 'toggle', 'name' => 'statusshowprice',    'label' => 'نمایش قیمت روی محصولات',            'on' => 'onshowprice',     'off' => 'offshowprice'],
                    ['type' => 'toggle', 'name' => 'configshow',         'label' => 'نمایش کانفیگ به کاربر',              'on' => 'onconfig',        'off' => 'offconfig'],
                    ['type' => 'toggle', 'name' => 'backserviecstatus',  'label' => 'دکمه بازگشت در منوی سرویس',          'on' => 'on',              'off' => 'off'],
                ],
            ],

        ];


        $shopRows = [];
        if (!$tableMissing) {
            try {
                $r = $pdo->query("SELECT Namevalue, value FROM shopSetting");
                if ($r) {
                    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $shopRows[(string)$row['Namevalue']] = (string)$row['value'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[panel/shopsettings] load failed: ' . $e->getMessage());
            }
        }




        $savedCount = 0;
        if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
            $savedCount = \Faoxima\Admin\Web\Pages\ShopsettingsRequestHelpers::fx_shop_save_settings($pdo, $SHOP_GROUPS, $_POST, $shopRows);
            header('Location: shopsettings.php?saved=' . $savedCount);
            exit;
        }

        $showSaved = isset($_GET['saved']);
        $savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;



        return true;
    }
}
