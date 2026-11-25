<?php

namespace Faoxima\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $dataget, $datatextbot, $errorreport, $first_name, $from_id, $keyboard, $marzban_list_get, $message_id, $otherservice, $pdo, $setting, $step_payment, $telegramRoute, $textbotlang, $user.
 * Inherited or exported by reference: $codeproduct, $datakeyboard, $extend, $location, $nameloc, $prodcut, $query, $queryParams, $result, $statuscustom, $stmt, $textextend, $username, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ExternalRenewal
{
    public static function handle(
        $ManagePanel,
        &$codeproduct,
        $dataget,
        &$datakeyboard,
        $datatextbot,
        $errorreport,
        &$extend,
        $first_name,
        $from_id,
        $keyboard,
        &$location,
        $marzban_list_get,
        $message_id,
        &$nameloc,
        $otherservice,
        $pdo,
        &$prodcut,
        &$query,
        &$queryParams,
        &$result,
        $setting,
        &$statuscustom,
        $step_payment,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        &$textextend,
        $user,
        &$username,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'renewal_start':
            $username = $dataget[1];
            $invoiceIdForExtend = $dataget[2] ?? '';
            $nameloc = false;
            if ($invoiceIdForExtend !== '') {
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $invoiceIdForExtend, "select");
                if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                    if (function_exists('rx_log_event')) {
                        rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extends on non-owned invoice', [
                            'from_id' => $from_id, 'invoice' => $invoiceIdForExtend, 'handler' => 'extends',
                        ]);
                    }
                    $nameloc = false;
                }
            }
            if (!is_array($nameloc)) {
                $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
                $stmtInvoice->execute([':username' => $username, ':id_user' => $from_id]);
                $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
            }
            if (is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmStockPanelForInvoice']) && is_array($nameloc)) {
                $marzban_list_get = \Faoxima\Services\Fulfillment\Inventory::nmStockPanelForInvoice($nameloc);
            } else {
                $marzban_list_get = false;
            }
            if (!is_array($marzban_list_get)) {
                $panelRef = $user['Processing_value_four'] ?? '';
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelRef, "select");
                if (!is_array($marzban_list_get)) {
                    $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $panelRef, "select");
                }
            }
            if (!is_array($marzban_list_get)) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_restart_error'), null, 'html');
                return;
            }
            $location = $marzban_list_get['name_panel'];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $location, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
            if (is_array($nameloc) && !empty($nameloc['id_invoice'])) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
            }

            if (is_array($nameloc) && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get) && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockExtendProductKeyboard')) {
                $stockKeyboard = \Faoxima\Services\Fulfillment\StockPresentation::nmStockExtendProductKeyboard($nameloc, $user, $marzban_list_get);
                if (!$stockKeyboard) {
                    Editmessagetext($from_id, $message_id, "❌ برای این سرویس موجودی انبار قابل تمدید وجود ندارد.", json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE), 'HTML');
                    return;
                }
                Editmessagetext($from_id, $message_id, "📦 وضعیت نت ملی فعال است؛ فقط محصولات موجود در انبار برای تمدید نمایش داده می‌شوند. یکی را انتخاب کنید:", $stockKeyboard, 'HTML');
                return;
            }

            $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $queryParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $customVolumeData = json_decode($marzban_list_get['customvolume'] ?? '{}', true);
            if (!is_array($customVolumeData)) $customVolumeData = [];
            $statuscustomvolume = $customVolumeData[$user['agent']] ?? '0';
            if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                $datakeyboard = "prodcutservicesom_";
            } else {
                $datakeyboard = "prodcutserviceom_";
            }
            $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
            $textextend = \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['extend']['selectservice'], [
                'panel' => $marzban_list_get['name_panel'],
            ]);
            Editmessagetext($from_id, $message_id, $textextend, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username, "customsellvolume", $user['agent'], $queryParams));
            break;
        case 'renewal_product':
            deletemessage($from_id, $message_id);
            $codeproduct = $dataget[1];
            $username = $dataget[2];
            if (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                $prodcut = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:processing_value, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':processing_value' => $user['Processing_value'],
                    ':code_product' => $codeproduct,
                    ':agent' => $user['agent']
                ]);
                $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($prodcut == false) {
                error_log('Service extend preview failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
                return;
            }
            $fxExternalRenewPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (is_array($fxExternalRenewPanel)) {
                $prodcut = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($prodcut, $fxExternalRenewPanel);
                $prodcut['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($prodcut['price_product'], $fxExternalRenewPanel, 'product');
                \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew_external', (string) $username . '|' . (string) $codeproduct), \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($prodcut['price_product'], $user['pricediscount']), $fxExternalRenewPanel, 'product'), $fxExternalRenewPanel, 'product');
            }
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
                    ]
                ]
            ]);
            $prodcutVolumeLabel = intval($prodcut['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $prodcut['Volume_constraint'];
            $rxRenewPreview = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService($pdo, $ManagePanel, \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select"), (string) $username, $prodcut['Volume_constraint'], $prodcut['Service_time'], ['stage' => 'telegram_preview']);
            if (!empty($rxRenewPreview['blocked'])) {
                $keyboardextend = null;
            }
            $rxRenewInvoiceText = sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcutVolumeLabel, $prodcut['note'], $user['Balance']);
            if ($rxRenewPreview['text'] !== '') {
                $rxRenewInvoiceText .= "\n\n" . $rxRenewPreview['text'];
            }
            sendmessage($from_id, $rxRenewInvoiceText, $keyboardextend, 'html');
            break;
        case 'renewal_confirm':
            $codeproduct = $dataget[1];
            $usernamePanelExtends = $dataget[2];
            deletemessage($from_id, $message_id);
            if (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                $prodcut = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:processing_value, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':processing_value' => $user['Processing_value'],
                    ':code_product' => $codeproduct,
                    ':agent' => $user['agent']
                ]);
                $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($prodcut == false) {
                error_log('Service extend confirmation failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if ($marzban_list_get == false) {
                sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
                return;
            }
            $prodcut = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($prodcut, $marzban_list_get);
            $prodcut['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($prodcut['price_product'], $marzban_list_get, 'product');
            $fxExternalRenewKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew_external', (string) $usernamePanelExtends . '|' . (string) $codeproduct);
            $fxExternalRenewAmount = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($prodcut['price_product'], $user['pricediscount']), $marzban_list_get, 'product');
            if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxExternalRenewKey, $fxExternalRenewAmount, $marzban_list_get, 'product')) {
                \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxExternalRenewKey, $fxExternalRenewAmount, $marzban_list_get, 'product');
                sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxExternalRenewAmount), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $usernamePanelExtends]]]]), 'HTML');
                return;
            }
            $nameloc = false;
            if (!empty($user['Processing_value_one'])) {
                $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value_one'], "select");
            }
            if (!is_array($nameloc)) {
                $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
                $stmtInvoice->execute([':username' => $usernamePanelExtends, ':id_user' => $from_id]);
                $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
            }
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $prodcut['price_product'] && $user['agent'] != "n2") {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
                if ($marzbandirectpay == "offdirectbuy") {
                    $minbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $maxbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $bakinfos = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                            ]
                        ]
                    ]);
                    Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
                    return;
                } else {
                    if (intval($user['pricediscount']) != 0) {
                        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                        $prodcut['price_product'] = $prodcut['price_product'] - $result;
                        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
                    }
                    $prodcut['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($prodcut['price_product'], $marzban_list_get, 'product');
                    $Balance_prim = $prodcut['price_product'] - \Faoxima\Orders\Checkout\Checkout::availableForUser($user);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $Balance_prim, "id", $from_id);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                    sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
                    return;
                }
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $prodcut['price_product']) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            if (intval($user['pricediscount']) != 0) {
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $prodcut['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($prodcut['price_product'], $marzban_list_get, 'product');
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get)) {
                if (!is_array($nameloc)) {
                    sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
                    return;
                }
                $stockNew = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockReserveForProduct') ? \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($pdo, $marzban_list_get, $prodcut, $from_id, $nameloc['id_invoice'], 'normal_extend_national_stock') : false;
                if (!$stockNew) {
                    sendmessage($from_id, $datatextbot['dyn_errors_stock_depleted_no_charge'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.", $keyboard, 'HTML');
                    return;
                }
                $__pp = (float)$prodcut['price_product'];
                $__allowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
                $__charge = is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge']) ? \Faoxima\Wallet\Balance\Balances::charge($pdo, $from_id, $__pp, $__allowNeg) : ['ok' => false, 'reason' => 'helper-missing'];
                if (empty($__charge['ok'])) {
                    if (is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmStockReleaseReservation'])) \Faoxima\Services\Fulfillment\Inventory::nmStockReleaseReservation($pdo, $stockNew);
                    sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
                    return;
                }
                $Balance_Low_user = $__charge['new_balance'];
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'debit', $__pp, 'service_action', 'تمدید سرویس (انبار ملی)', null, 'invoice', (string)$nameloc['id_invoice']);
                }
                \Faoxima\Core\Database\Db::update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
                try { \Faoxima\Core\Database\Db::update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($nameloc['id_invoice'], array('volume_cycle', 'time_cycle'), array('volume', 'volume_report', 'time', 'time_report'));
                $invoiceNew = array_merge($nameloc, [
                    'name_product' => $prodcut['name_product'],
                    'price_product' => $prodcut['price_product'],
                    'Volume' => $prodcut['Volume_constraint'],
                    'Service_time' => $prodcut['Service_time'],
                    'time_sell' => time(),
                    'user_info' => $stockNew['content'],
                    'source_panel_code' => $marzban_list_get['code_panel'],
                ]);
                \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
                sendmessage($from_id, $datatextbot['dyn_errors_stock_extend_success'] ?? "✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.", $keyboard, 'HTML');
                return;
            }

            $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
                return;
            }
            $rxFinalizePreview = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService($pdo, $ManagePanel, $marzban_list_get, (string) $usernamePanelExtends, $prodcut['Volume_constraint'], $prodcut['Service_time'], ['snapshot' => \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut, (string) ($marzban_list_get['type'] ?? '')), 'id_invoice' => is_array($nameloc) ? (string) $nameloc['id_invoice'] : '', 'stage' => 'telegram_confirm']);
            if (!empty($rxFinalizePreview['blocked'])) {
                sendmessage($from_id, (string) $rxFinalizePreview['text'], $keyboard, 'HTML');
                return;
            }
            $__pp2 = (float)$prodcut['price_product'];
            $__allowNeg2 = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
            $__charge2 = is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge']) ? \Faoxima\Wallet\Balance\Balances::charge($pdo, $from_id, $__pp2, $__allowNeg2) : ['ok' => false, 'reason' => 'helper-missing'];
            if (empty($__charge2['ok'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
                return;
            }
            $Balance_Low_user = $__charge2['new_balance'];
            if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'debit', $__pp2, 'service_action', 'تمدید سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
            $rxFinalizeRef = 'wallet:' . $from_id . ':' . bin2hex(random_bytes(4));
            $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel'], null, [
                'source' => 'wallet',
                'ref' => $rxFinalizeRef,
                'id_user' => $from_id,
                'price' => $__pp2,
                'reservation' => ['payment_ref' => $rxFinalizeRef],
            ]);
            $rxFinalizePending = \Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend);
            if (empty($extend['status']) && !$rxFinalizePending && is_callable([\Faoxima\Wallet\Balance\Balances::class, 'credit'])) {
                $__refundedFin = \Faoxima\Wallet\Balance\Balances::credit($pdo, $from_id, $__pp2);
                $Balance_Low_user = $user['Balance'];
                if ($__refundedFin && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'credit', $__pp2, 'refund', 'بازگشت وجه تمدید سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
                }
            }
            if ($extend['status'] == false && !$rxFinalizePending) {
                $rxStockEmpty = ($extend['code'] ?? '') === 'manual_stock_empty';
                $rxQueuedExists = ($extend['code'] ?? '') === 'queued_renewal_exists';
                $extend['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
                $textreports = "خطای تمدید سرویس
        <blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
        <blockquote>نام کاربری سرویس : $usernamePanelExtends</blockquote>
        <blockquote>کد خطا : " . htmlspecialchars((string) ($extend['code'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>
        <blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
                if ($rxStockEmpty) {
                    sendmessage($from_id, $datatextbot['dyn_errors_renewal_stock_empty'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
                } elseif ($rxQueuedExists) {
                    sendmessage($from_id, $datatextbot['dyn_errors_queued_renewal_exists'] ?? "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
                } else {
                    sendmessage($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalFailureText($extend, $datatextbot['dyn_errors_renewal_support_error'] ?? "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
                }
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $textreports,
                        'parse_mode' => "HTML"
                    ]);
                }
                return;
            }
            if (is_array($nameloc) && !$rxFinalizePending && empty($extend['queued'])) {
                \Faoxima\Core\Database\Db::update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
                \Faoxima\Core\Database\Db::update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
                try { \Faoxima\Core\Database\Db::update("invoice", "user_info", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
                try { \Faoxima\Core\Database\Db::update("invoice", "source_panel_code", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
            $value = json_encode(array(
                "volumebuy" => $prodcut['Volume_constraint'],
                "Service_time" => $prodcut['Service_time'],
                "oldvolume" => $DataUserOut['data_limit'],
                "oldtime" => $DataUserOut['expire'],
                'code_product' => $prodcut['code_product'],
            ));
            $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($marzban_list_get, 'product', $prodcut['fx_base_price'] ?? $prodcut['price_product'], $prodcut['price_product'], 0, ['product' => (string) ($prodcut['code_product'] ?? '')]));
            $dateacc = date('Y/m/d H:i:s');
            $type = "extends_not_user";
            $stmt->execute([
                ':id_user' => $from_id,
                ':username' => $usernamePanelExtends,
                ':value' => $value,
                ':type' => $type,
                ':time' => $dateacc,
                ':price' => $prodcut['price_product'],
                ':output' => json_encode($extend)
            ]);
            $prodcut['price_product'] = number_format($prodcut['price_product']);
            $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
            if (!empty($extend['queued'])) {
                $rxQueuedSuccessTpl = (string) ($extend['reservation']['confirmation'] ?? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_confirmed'));
                $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_queued_summary', ['service' => $usernamePanelExtends, 'product' => $prodcut['name_product'], 'price' => $prodcut['price_product']], ['confirmation' => $rxQueuedSuccessTpl]);
            } else {
                $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_success', ['service' => $usernamePanelExtends, 'product' => $prodcut['name_product'], 'price' => $prodcut['price_product']]);
                $textextend = \Faoxima\Services\Renewal\RenewalText::rxRenewalDecorateSuccess($textextend, $extend, (string) $usernamePanelExtends, (string) ($marzban_list_get['name_panel'] ?? ''));
            }
            sendmessage($from_id, $textextend, $keyboard, 'HTML');
            if ($marzban_list_get['type'] == "Manualsale") {
                $manualSubLink = is_array($extend) ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($marzban_list_get, $extend['subscription_url'] ?? "", $extend['file_ext'] ?? null) : "";
                \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($marzban_list_get, "", $manualSubLink, $usernamePanelExtends, null, $textextend, $nameloc['id_invoice'] ?? "", $from_id);
            }
            $timejalali = jdate('Y/m/d H:i:s');
            $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewaldetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
