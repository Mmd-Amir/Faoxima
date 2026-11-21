<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $flash, $invoiceTableMissing, $maxStorageBytes, $maxStorageGb, $name, $p, $panelNameOf, $panels, $productByCode, $productNameOf, $products, $r, $tableMissing, $totalStorageUsedBytes.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualServiceRequest
{
    public static function handle(
        &$flash,
        &$invoiceTableMissing,
        &$maxStorageBytes,
        &$maxStorageGb,
        &$name,
        &$p,
        &$panelNameOf,
        &$panels,
        $pdo,
        &$productByCode,
        &$productNameOf,
        &$products,
        &$r,
        &$tableMissing,
        &$totalStorageUsedBytes
    ) {
        \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);

        $tableMissing = true;
        try {
            $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manualsell' LIMIT 1");
            $r->execute();
            $tableMissing = !(bool)$r->fetchColumn();
        } catch (\Throwable $e) {
        }

        $invoiceTableMissing = true;
        try {
            $r = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice' LIMIT 1");
            $r->execute();
            $invoiceTableMissing = !(bool)$r->fetchColumn();
        } catch (\Throwable $e) {
        }

        $flash = ['ok' => '', 'err' => ''];

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS manual_sales_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("ALTER TABLE manualsell MODIFY contentrecord LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
            try {
                @$pdo->exec("ALTER TABLE manualsell ADD COLUMN sub_link MEDIUMTEXT NULL");
            } catch (\Throwable $e) {
            }
        } catch (\Throwable $e) {
        }




        $maxStorageGb = (float)\Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_get_ms_setting($pdo, 'max_storage_gb', '5');
        if ($maxStorageGb <= 0) $maxStorageGb = 5.0;

        $totalStorageUsedBytes = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_get_total_storage_used_bytes($pdo);
        $maxStorageBytes = (int)round($maxStorageGb * 1073741824);

        try {
            $packetBytes = max(67108864, min(1073741824, $maxStorageBytes));
            @$pdo->exec("SET SESSION max_allowed_packet = {$packetBytes}");
            @$pdo->exec("SET GLOBAL max_allowed_packet = {$packetBytes}");
        } catch (\Throwable $e) {
        }

        $panels = [];
        try {
            $r = $pdo->prepare("SELECT code_panel, name_panel, type FROM marzban_panel WHERE type = 'Manualsale' ORDER BY name_panel ASC");
            $r->execute();
            $panels = $r->fetchAll(PDO::FETCH_ASSOC);
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

        $productByCode = [];
        $productNameOf = [];
        foreach ($products as $p) {
            $code = (string)($p['code_product'] ?? '');
            $name = (string)($p['name_product'] ?? $p['Name_product'] ?? '');
            if ($code !== '') {
                $productByCode[$code] = $p;
                $productNameOf[$code] = $name;
            }
        }

        $allowedExt = ['conf', 'ovpn', 'pcf', 'npv'];








        return true;
    }
}
