<?php

namespace Faoxima\Services\Fulfillment;

use Throwable;

final class InventoryProvisioning
{
    public static function nmStockConvertInvoiceToPanelService($runtimeManagePanel, $runtimeErrorreport, $runtimeKeyboard, $runtimePdo, $runtimeSetting, $chatId, array $userRow, array $invoice, array $panel, array $product)
    {
        $ManagePanel = $runtimeManagePanel; $setting = $runtimeSetting; $errorreport = $runtimeErrorreport; $keyboard = $runtimeKeyboard;
        $price = (float)($product['price_product'] ?? 0);
        if ((float)($userRow['Balance'] ?? 0) < $price && ($userRow['agent'] ?? '') !== 'n2') { sendmessage($chatId, '❌ موجودی کیف پول برای تمدید کافی نیست.', null, 'HTML'); return false; }
        $username = \Faoxima\Services\Fulfillment\Inventory::nmStockSafeUsername($chatId, $invoice['username'] ?? '');
        try { $check = $ManagePanel->DataUser($panel['name_panel'], $username); if (is_array($check) && isset($check['username'])) $username = \Faoxima\Services\Fulfillment\Inventory::nmStockSafeUsername($chatId, $chatId . '-' . substr(bin2hex(random_bytes(4)), 0, 8)); } catch (Throwable $e) { }
        $days = (int)($product['Service_time'] ?? 0);
        $expire = $days > 0 ? strtotime('+' . $days . ' days') : 0;
        $datac = ['expire' => $expire, 'data_limit' => (float)($product['Volume_constraint'] ?? 0) * pow(1024, 3), 'from_id' => $chatId, 'username' => '', 'type' => 'buy'];
        $dataoutput = $ManagePanel->createUser($panel['name_panel'], $product['code_product'], $username, $datac);
        if (!is_array($dataoutput) || empty($dataoutput['username'])) {
            $msg = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text(is_array($dataoutput) ? ($dataoutput['msg'] ?? null) : null, is_array($dataoutput) ? ($dataoutput['detail'] ?? null) : null);
            error_log('nmStockConvertInvoiceToPanelService createUser failed: ' . $msg);
            sendmessage($chatId, '❌ تمدید از پنل اصلی انجام نشد. لطفاً گزارش خطا را بررسی کنید.', null, 'HTML');
            if (!empty($setting['Channel_Report'])) telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $errorreport ?? null, 'text' => "خطا در تبدیل سرویس انبار به پنل اصلی
پنل: {$panel['name_panel']}
کاربر: {$chatId}
خطا: {$msg}", 'parse_mode' => 'HTML']);
            return false;
        }
        if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
            $__allowNegBl = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
            $__chargeBl = \Faoxima\Wallet\Balance\Balances::charge($runtimePdo, $chatId, (float)$price, $__allowNegBl);
            if (!empty($__chargeBl['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                \Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $chatId, 'debit', $price, 'service_action', 'تمدید سرویس (تبدیل انبار)', null, 'invoice', (string)$invoice['id_invoice']);
            }
        } else {
            \Faoxima\Core\Database\Db::update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $price, 'id', $chatId);
        }
        \Faoxima\Core\Database\Db::update('invoice', 'username', $dataoutput['username'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'Service_location', $panel['name_panel'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'name_product', $product['name_product'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'price_product', $product['price_product'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'Volume', $product['Volume_constraint'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'Service_time', $product['Service_time'], 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        \Faoxima\Core\Database\Db::update('invoice', 'time_sell', time(), 'id_invoice', $invoice['id_invoice']);
        try { \Faoxima\Core\Database\Db::update('invoice', 'user_info', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
        try { \Faoxima\Core\Database\Db::update('invoice', 'source_panel_code', '', 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) {}
        \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle', 'time_cycle'), array('volume', 'volume_report', 'time', 'time_report'));
        $subLink = $dataoutput['subscription_url'] ?? '';
        $configs = $dataoutput['configs'] ?? [];

        $volumeLabel = intval($product['Volume_constraint'] ?? 0) === 0 ? 'نامحدود' : ($product['Volume_constraint'] . ' گیگ');
        $caption = "✅ وضعیت نت ملی خاموش است؛ سرویس شما از پنل اصلی تمدید و جایگزین شد.

👤 نام سرویس: <code>{$dataoutput['username']}</code>
🛍 محصول: {$product['name_product']}
⏳ مدت: {$product['Service_time']} روز
🗜 حجم: {$volumeLabel}";
        if (trim((string)$subLink) !== '') {
            $caption .= "\n\n🔗 لینک اشتراک:\n<code>" . htmlspecialchars((string)$subLink, ENT_QUOTES, 'UTF-8') . "</code>";
        }
        if (is_array($configs) && count($configs) > 0) {
            $cfgClean = array_values(array_filter($configs, static function ($v) { return is_string($v) && trim($v) !== ''; }));
            if (!empty($cfgClean)) {
                $caption .= "\n\n⚙️ کانفیگ‌ها:";
                foreach ($cfgClean as $idx => $cfg) {
                    $caption .= "\n\n<b>" . ($idx + 1) . ".</b>\n<code>" . htmlspecialchars($cfg, ENT_QUOTES, 'UTF-8') . "</code>";
                }
            }
        }
        if (method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'sendMessageService')) \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($panel, $configs, $subLink, $dataoutput['username'], null, $caption, $invoice['id_invoice'], $chatId); else sendmessage($chatId, $caption, $keyboard ?? null, 'HTML');

        \Faoxima\Services\Fulfillment\StockPresentation::nmStockNotifyExtend($chatId, $userRow, $invoice, $product, $panel, 'panel', $dataoutput['username'] ?? '', $price);
        return true;
        }

    public static function nmStockFallbackForInvoice($runtimePdo, array $invoice, array $product = null, $mode = 'panel_fallback')
    {
        $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $invoice['Service_location'] ?? '', 'select');
        if (!$product) $product = \Faoxima\Services\Fulfillment\Inventory::nmStockProductForInvoice($runtimePdo, $invoice);
        if (is_array($panel) && $panel) {
            $stock = \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($runtimePdo, $panel, $product, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
        } else {
            $volume = $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0);
            $productCode = $product['code_product'] ?? 'auto';
            $stock = \Faoxima\Services\Fulfillment\Inventory::nmStockReserveOne($runtimePdo, 'auto', $productCode, $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
            if (!$stock && $productCode !== 'auto') $stock = \Faoxima\Services\Fulfillment\Inventory::nmStockReserveOne($runtimePdo, 'auto', 'auto', $volume, $invoice['id_user'] ?? '', $invoice['id_invoice'] ?? '', $mode);
        }
        if (!$stock) return false;
        \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stock, $invoice, $mode === 'config_button' ? '✅ کانفیگ مطابق حجم سرویس از انبار شبکه‌ملی تحویل شد' : '✅ تمدید از پنل انجام نشد؛ کانفیگ جایگزین از انبار شبکه‌ملی تحویل شد');
        try { \Faoxima\Core\Database\Db::update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']); \Faoxima\Core\Database\Db::update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoice['id_invoice']); } catch (Throwable $e) { error_log('nmStockFallbackForInvoice invoice update failed: ' . $e->getMessage()); }
        return $stock;
        }

    public static function nmStockCompleteExtendFallback($runtimePdo, $userId, array $userRow, array $invoice, array $product, $priceToCharge = 0, $mode = 'extend_fallback')
    {
        $pdo = $runtimePdo;
        $stock = self::nmStockFallbackForInvoice($runtimePdo, $invoice, $product, $mode);
        if (!$stock) return false;
        try {
            $priceToCharge = (float)$priceToCharge;
            if ($priceToCharge > 0 && isset($userRow['Balance'])) {
                if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
                    $__allowNegFb = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                    $__chargeFb = \Faoxima\Wallet\Balance\Balances::charge($runtimePdo, $userId, $priceToCharge, $__allowNegFb);
                    if (!empty($__chargeFb['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                        \Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $userId, 'debit', $priceToCharge, 'service_action', 'تمدید سرویس (جایگزین انبار)', null, 'invoice', (string)($invoice['id_invoice'] ?? ''));
                    }
                } else {
                    $newBalance = (float)$userRow['Balance'] - $priceToCharge;
                    \Faoxima\Core\Database\Db::update('user', 'Balance', $newBalance, 'id', $userId);
                }
            }
            $value = json_encode([
                'volumebuy' => $product['Volume_constraint'] ?? ($invoice['Volume'] ?? 0),
                'Service_time' => $product['Service_time'] ?? ($invoice['Service_time'] ?? 0),
                'code_product' => $product['code_product'] ?? 'auto',
                'source' => 'nm_stock',
                'stock_id' => $stock['id'],
                'tier' => $stock['tier']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $output = json_encode(['status' => true, 'source' => 'nm_stock', 'stock_id' => $stock['id'], 'tier' => $stock['tier']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (:id_user,:username,:value,'extend_user',:time,:price,:output,'paid')");
            $stmt->execute([
                ':id_user' => $userId,
                ':username' => $invoice['username'] ?? '',
                ':value' => $value,
                ':time' => date('Y/m/d H:i:s'),
                ':price' => $priceToCharge,
                ':output' => $output,
            ]);
            \Faoxima\Core\Database\Db::update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        } catch (Throwable $e) {
            error_log('nmStockCompleteExtendFallback failed: ' . $e->getMessage());
        }
        sendmessage($userId, "✅ پنل در دسترس نبود؛ تمدید با کانفیگ جایگزین انبار شبکه‌ملی تکمیل شد.", null, 'HTML');
        return $stock;
        }

    public static function nmStockCompleteBuyFromInventory($runtimePdo, $userId, array $userRow, array $panel, array $product, $invoiceId, $usernameAc = '', $chargeBalance = true, $mode = 'national_buy')
    {
        $stock = \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($runtimePdo, $panel, $product, $userId, $invoiceId, $mode);
        if (!$stock) return false;
        try {
            if ($chargeBalance) {
                $__pp3 = (float)($product['price_product'] ?? 0);
                if ($__pp3 > 0) {
                    if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
                        $__allowNeg3 = (($userRow['agent'] ?? '') === 'n2') ? (int)($userRow['maxbuyagent'] ?? 0) : 0;
                        $__charge3 = \Faoxima\Wallet\Balance\Balances::charge($runtimePdo, $userId, $__pp3, $__allowNeg3);
                        if (!empty($__charge3['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                            \Faoxima\Wallet\Ledger\Ledger::record($runtimePdo, $userId, 'debit', $__pp3, 'purchase', 'خرید سرویس (انبار ملی)', (string)$invoiceId, 'invoice', (string)$invoiceId);
                        }
                    } else {
                        \Faoxima\Core\Database\Db::update('user', 'Balance', (float)($userRow['Balance'] ?? 0) - $__pp3, 'id', $userId);
                    }
                }
            }
            \Faoxima\Core\Database\Db::update('invoice', 'Status', 'active', 'id_invoice', $invoiceId);
            \Faoxima\Core\Database\Db::update('invoice', 'user_info', $stock['content'], 'id_invoice', $invoiceId);
            try { \Faoxima\Core\Database\Db::update('invoice', 'source_panel_code', $panel['code_panel'] ?? '', 'id_invoice', $invoiceId); } catch (Throwable $e) {}
        } catch (Throwable $e) { error_log('nmStockCompleteBuyFromInventory update failed: ' . $e->getMessage()); }
        $invoice = ['id_user'=>$userId, 'id_invoice'=>$invoiceId, 'username'=>$usernameAc, 'Service_location'=>$panel['name_panel'] ?? '', 'name_product'=>$product['name_product'] ?? '', 'Volume'=>$product['Volume_constraint'] ?? 0, 'Service_time'=>$product['Service_time'] ?? 0];
        \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stock, $invoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار پشتیبان تحویل شد');

        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockNotifyExtend')) {
            $notifyMode = (strpos((string)$mode, 'emergency') !== false) ? 'emergency' : 'stock';
            \Faoxima\Services\Fulfillment\StockPresentation::nmStockNotifyExtend($userId, $userRow, $invoice, $product, $panel, $notifyMode, (string)$usernameAc, (float)($product['price_product'] ?? 0), 'buy');
        }
        return $stock;
        }
}
