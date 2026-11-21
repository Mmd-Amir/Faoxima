<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $flash, $invoiceTableMissing, $nationalPanels, $p, $panelNameOf, $panels, $productByCode, $products, $r, $tableMissing.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class StockRequest
{
    public static function handle(
        &$flash,
        &$invoiceTableMissing,
        &$nationalPanels,
        &$p,
        &$panelNameOf,
        &$panels,
        $pdo,
        &$productByCode,
        &$products,
        &$r,
        &$tableMissing
    ) {
        \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);


        $tables = ['nm_stock_shelves' => false, 'nm_config_stock' => false];
        foreach ($tables as $t => $_) {
            try {
                $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
                $r->execute([':t' => $t]);
                $tables[$t] = (bool)$r->fetchColumn();
            } catch (\Throwable $e) {
            }
        }
        $tableMissing = !($tables['nm_stock_shelves'] && $tables['nm_config_stock']);

        $invoiceTableMissing = true;
        try {
            $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' LIMIT 1");
            $r->execute();
            $invoiceTableMissing = !(bool)$r->fetchColumn();
        } catch (\Throwable $e) {
        }

        $flash = ['ok' => '', 'err' => ($_GET['delete_error'] ?? '') === '1' ? 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.' : ''];
        if (($_GET['import_error'] ?? '') === '1') {
            $flash['err'] = 'ورود کانفیگ‌ها انجام نشد. جزئیات در لاگ سرور ثبت شد.';
        }




        $panels = [];
        try {
            $r = $pdo->query("SELECT code_panel, name_panel, type, national_net_status FROM marzban_panel ORDER BY name_panel ASC");
            if ($r) $panels = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $products = [];
        try {
            $r = $pdo->query("SELECT * FROM product ORDER BY id ASC");
            if ($r) $products = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $panelNameOf = [];
        foreach ($panels as $p) $panelNameOf[(string)$p['code_panel']] = (string)$p['name_panel'];

        $nationalPanels = array_values(array_filter($panels, [\Faoxima\Services\Fulfillment\Inventory::class, 'nmPanelNationalEnabled']));
        $nationalNetActive = !empty($nationalPanels);

        $productByCode = [];
        foreach ($products as $p) {
            $code = (string)($p['code_product'] ?? '');
            if ($code !== '') $productByCode[$code] = $p;
        }














        return true;
    }
}
