<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $buyreport, $connect, $errorreport, $first_name, $from_id, $keyboard, $message_id, $paymentom, $pdo, $setting, $step_payment, $telegramRoute, $text_inline, $textbotlang, $user, $username.
 * Inherited or exported by reference: $configs, $dataoutput, $datatextbot, $info_product, $marzban_list_get, $priceproduct, $randomString, $result, $stmt, $username_ac, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ConfirmBulkPurchase
{
    public static function handle(
        $ManagePanel,
        $buyreport,
        &$configs,
        $connect,
        &$dataoutput,
        &$datatextbot,
        $errorreport,
        $first_name,
        $from_id,
        &$info_product,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        $paymentom,
        $pdo,
        &$priceproduct,
        &$randomString,
        &$result,
        $setting,
        $step_payment,
        &$stmt,
        $telegramRoute,
        $text_inline,
        $textbotlang,
        $user,
        $username,
        &$username_ac,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'bulk_confirm':
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            $parts = explode("_", $user['Processing_value_one']);
            if ($parts[0] == "customvolume") {
                $info_product['Volume_constraint'] = $parts[2];
                $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
                $info_product['code_product'] = "customvolume";
                $info_product['Service_time'] = $parts[1];
                try {
                    $info_product['price_product'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price($marzban_list_get, $user['agent'], $parts[2], $parts[1]);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_back'), 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
                $info_product['data_limit_reset'] = "no_reset";
            } else {
                if (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                    $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($user['Processing_value_one'], $user['Processing_value'], $user['agent']);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                    $stmt->execute([
                        ':code_product' => $user['Processing_value_one'],
                        ':location' => $user['Processing_value'],
                        ':agent' => $user['agent']
                    ]);
                    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            if (!is_array($info_product) || !isset($info_product['price_product'])) {
                error_log('Bulk purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $fxBulkKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($parts[0] != "customvolume") {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            if ($user['Processing_value_price'] !== null && $user['Processing_value_price'] !== '') {
                $priceproduct = (float) $user['Processing_value_price'];
            } else {
                $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
            }
            if (\Faoxima\Orders\Pricing\CustomDuration::fx_custom_quote_context($marzban_list_get, $fxBulkKinds) !== null) {
                $fxBulkFresh = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(intval($info_product['price_product']) * intval($user['Processing_value_four']), $marzban_list_get, $fxBulkKinds);
                $fxBulkQuoteKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('bulk', (string) $user['Processing_value_one'] . '|' . (string) $user['Processing_value_four']);
                if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxBulkQuoteKey, $fxBulkFresh, $marzban_list_get, $fxBulkKinds) || (int) round($priceproduct) !== (int) $fxBulkFresh) {
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_price", $fxBulkFresh, "id", $from_id);
                    \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxBulkQuoteKey, $fxBulkFresh, $marzban_list_get, $fxBulkKinds);
                    Editmessagetext($from_id, $message_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxBulkFresh) . (($parts[0] ?? '') === 'customvolume' ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $fxBulkFresh) : ''), $paymentom);
                    return;
                }
            }
            rxRemoveMessageKeyboard($from_id, $message_id);
            $username_ac = $user['Processing_value_tow'];
            $date = time();
            if (intval($user['pricediscount']) != 0) {
                $result = ($priceproduct * $user['pricediscount']) / 100;
                $priceproduct = $priceproduct - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $priceproduct = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($priceproduct, $marzban_list_get, $fxBulkKinds);
            if ($priceproduct > $user['Balance'] && $user['agent'] != "n2") {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($user['Processing_value'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
                if ($marzbandirectpay == "offdirectbuy") {
                    $minbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $maxbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $bakinfos = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account", '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true],
                            ]
                        ]
                    ]);
                    Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
                    return;
                } else {
                    $Balance_prim = $priceproduct - $user['Balance'];
                    $Balance_prims = $user['Balance'] - $priceproduct;
                    if ($Balance_prims <= 1)
                        $Balance_prims = 0;
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $Balance_prim, "id", $from_id);
                    sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
                    return;
                }
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $datep = strtotime("+" . $info_product['Service_time'] . "days");
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($user['number_username']) + $user['Processing_value_four'];
                \Faoxima\Core\Database\Db::update("user", "number_username", $value, "id", $from_id);
                if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                    $value = intval($setting['numbercount']) + $user['Processing_value_four'];
                    \Faoxima\Core\Database\Db::update("setting", "numbercount", $value);
                }
            }
            if ($info_product['Service_time'] == 0) {
                $datep = 0;
            } else {
                $datep = strtotime(date("Y-m-d H:i:s", $datep));
            }
            $datac = array(
                'expire' => strtotime(date("Y-m-d H:i:s", $datep)),
                'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
                'from_id' => $from_id,
                'username' => $username,
                'type' => 'buyomdh'
            );
            if ($info_product['inbounds'] != null) {
                $marzban_list_get['inboundid'] = $info_product['inbounds'];
            }
            $notifctions = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $Shoppinginfo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
                    ]
                ]
            ]);
            $__bulkQty = max(1, (int)$user['Processing_value_four']);
            $__bulkUnitCharge = intdiv((int)round($priceproduct), $__bulkQty);
            $__bulkUnitChargeRemainder = ((int)round($priceproduct)) - ($__bulkUnitCharge * $__bulkQty);
            $__bulkChargedTotal = 0;
            $__bulkAllowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
            for ($i = 0; $i < $user['Processing_value_four']; $i++) {
                $__bulkItemCharge = $__bulkUnitCharge + (($i == $__bulkQty - 1) ? $__bulkUnitChargeRemainder : 0);
                $random_number = rand(1000000, 9999999);
                $username_acc = $username_ac . "-" . $i;
                if (\Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'username', $username_acc)) {
                    $username_acc = $random_number . "-" . $username_acc;
                }
                $randomString = bin2hex(random_bytes(4));
                if (\Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'id_invoice', $randomString)) {
                    $randomString = $random_number . $randomString;
                }
        
        
                if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get)) {
                    $stock = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockReserveForProduct')
                        ? \Faoxima\Services\Fulfillment\Inventory::nmStockReserveForProduct($pdo, $marzban_list_get, $info_product, $from_id, $randomString, 'national_direct_buy')
                        : false;
                    if (!$stock) {
                        sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_bulk_stock_empty_not_charged'), $keyboard, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                        return;
                    }
                    $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
                    $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
                    $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
                    $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
                    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
                    $Status = "active";
                    $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
                    $stmt->execute();
                    $stmt->close();
                    try {
                        \Faoxima\Core\Database\Db::update("invoice", "user_info", $stock['content'], "id_invoice", $randomString);
                        \Faoxima\Core\Database\Db::update("invoice", "source_panel_code", $marzban_list_get['code_panel'] ?? '', "id_invoice", $randomString);
                    } catch (Throwable $e) {
                        error_log('nm national direct invoice update failed: ' . $e->getMessage());
                    }
                    $inventoryInvoice = [
                        'id_user' => $from_id,
                        'id_invoice' => $randomString,
                        'username' => $username_acc,
                        'Service_location' => $marzban_list_get['name_panel'] ?? '',
                        'name_product' => $info_product['name_product'] ?? '',
                        'Volume' => $info_product['Volume_constraint'] ?? 0,
                        'Service_time' => $info_product['Service_time'] ?? 0,
                        'price_product' => $info_product['price_product'] ?? 0,
                    ];
                    \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stock, $inventoryInvoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار شبکه‌ملی تحویل شد');
                    if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
                        $__bulkChargeNm = \Faoxima\Wallet\Balance\Balances::charge($pdo, $from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
                        if (!empty($__bulkChargeNm['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                            \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس (انبار ملی)', (string)$randomString, 'invoice', (string)$randomString);
                        }
                    }
                    $__bulkChargedTotal += $__bulkItemCharge;
                    continue;
                }
                $get_username_Check = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc) : null;
                if (isset($get_username_Check['username']) || \Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'username', $username_acc)) {
                    $username_acc = $random_number . "-" . $username_acc;
                }
                if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] === 0.0) {
                    $rxJovadBulkFreeze = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelFreezeUnlimitedOrder')
                        ? \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelFreezeUnlimitedOrder($marzban_list_get, $randomString, $username_acc, $info_product['Service_time'])
                        : ['status' => false, 'msg' => 'خرید محصول نامحدود این پنل در حال حاضر امکان‌پذیر نیست.'];
                    if (empty($rxJovadBulkFreeze['status'])) {
                        sendmessage($from_id, (string) ($rxJovadBulkFreeze['msg'] ?? 'خرید محصول نامحدود این پنل امکان‌پذیر نیست.'), $keyboard, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                        return;
                    }
                }
                if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] > 0) {
                    $rxJovadBulkChannels = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelFreezeChannelOrder')
                        ? \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelFreezeChannelOrder($marzban_list_get, $randomString, $username_acc, is_array($info_product) ? $info_product : array())
                        : ['status' => false, 'msg' => 'انتخاب کانال‌های این محصول در دسترس نیست؛ خرید انجام نشد.'];
                    if (empty($rxJovadBulkChannels['status'])) {
                        sendmessage($from_id, (string) ($rxJovadBulkChannels['msg'] ?? 'انتخاب کانال‌های این محصول معتبر نیست؛ خرید انجام نشد.'), $keyboard, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                        return;
                    }
                }
                $datac['id_invoice'] = (string) $randomString;
                $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac, true);
                if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && ($dataoutput['code'] ?? '') === 'uncertain') {
                    if (is_callable([\Faoxima\Payments\Fulfillment\Settlement::class, 'alertAdmin'])) {
                        \Faoxima\Payments\Fulfillment\Settlement::alertAdmin('jovad:' . $randomString, 'نتیجه ساخت این اشتراک از خرید انبوه نامشخص است؛ شناسه نگاشت و تسویه بررسی شود.', ['username' => $username_acc, 'panel' => $marzban_list_get['name_panel'], 'invoice' => $randomString]);
                    }
                    sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_bulk_provision_result_unknown'), $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                if ($dataoutput['username'] == null) {
                    $dataoutput['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
                    sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                    $texterros = "
⭕️ خطا در ساخت اکانت در بخش انبوه
<blockquote>✍️ دلیل خطا :
{$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
                    if (strlen($setting['Channel_Report'] ?? '') > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $texterros,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                $username_acc = \Faoxima\Services\Panels\PanelMessages::rx_adopt_created_username($dataoutput, $username_acc);
                \Faoxima\Services\Panels\PanelMessages::rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
                $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
                $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
                $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
                $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
                $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
                $Status = "active";
                $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
                $stmt->execute();
                $stmt->close();
                if (($parts[0] ?? '') === 'customvolume') {
                    \Faoxima\Orders\Pricing\ExchangePricing::fxStoreInvoiceSnapshot($randomString, \Faoxima\Orders\Pricing\CustomDuration::fx_custom_snapshot($marzban_list_get, $user['agent'], $parts[2], $parts[1], $__bulkItemCharge, null));
                }
                $config = "";
                $output_config_link = \Faoxima\Services\Delivery\ServiceDelivery::rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
                if ($marzban_list_get['config'] == "onconfig") {
                    if (is_array($dataoutput['configs'])) {
                        $rxBulkTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
                        foreach ($rxBulkTextConfigs as $configs) {
                            $config .= "\n" . $configs;
                        }
                    }
                }
                $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
                if ($marzban_list_get['type'] == "WGDashboard") {
                    $datatextbot['textafterpay'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
                }
                $textcreatuser = str_replace('{username}', "<code>" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
                $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
                $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
                $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
                $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
                if (($parts[0] ?? '') === 'customvolume') {
                    $textcreatuser .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $__bulkItemCharge);
                }
                $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
                \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
                $rxBulkFilesPaid = (float) $__bulkItemCharge <= 0;
                if (is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge'])) {
                    $__bulkChargeLive = \Faoxima\Wallet\Balance\Balances::charge($pdo, $from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
                    $rxBulkFilesPaid = $rxBulkFilesPaid || !empty($__bulkChargeLive['ok']);
                    if (!empty($__bulkChargeLive['ok']) && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                        \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس', (string)$randomString, 'invoice', (string)$randomString);
                    }
                } else {
                    $rxBulkDebit = $pdo->prepare('UPDATE user SET Balance = Balance - :amount WHERE id = :uid AND Balance >= :minimum');
                    $rxBulkDebit->execute([':amount' => $__bulkItemCharge, ':uid' => $from_id, ':minimum' => (float) $__bulkItemCharge - max(0.0, (float) $__bulkAllowNeg)]);
                    $rxBulkFilesPaid = $rxBulkFilesPaid || $rxBulkDebit->rowCount() > 0;
                    \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
                }
                if ($rxBulkFilesPaid) {
                    \Faoxima\Services\Delivery\ServiceDelivery::rxAutoDeliverPurchaseFilesSafe($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $from_id, $randomString);
                }
                $__bulkChargedTotal += $__bulkItemCharge;
            }
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            $__bulkBalanceRow = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select");
            $Balance_prim = (float)($__bulkBalanceRow['Balance'] ?? 0);
            $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
            $balanceformatsellbefore = number_format($user['Balance'], 0);
            $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
            $count_service = $user['Processing_value_four'];
            $rxFmtBulkInfoProductPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']);
            $timejalali = jdate('Y/m/d H:i:s');
            $text_report = "📣 جزئیات ساخت اکانت انبوه در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$username_ac}_0-$count_service</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$user['Processing_value']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️تعداد کانفیگ : {$user['Processing_value_four']} عدد</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                \Faoxima\Telegram\Support\Transport::rx_sendTopicReport([
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $buyreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ], ['flow' => 'bulk_buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id]);
            }
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('new_sub', [
                    'user_id'    => $from_id,
                    'amount'     => $info_product['name_product'],
                    'price'      => number_format((float)$pricebulk),
                    'panel_name' => $user['Processing_value'] ?? '',
                    'category'   => $info_product['category'] ?? '',
                ], $setting);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }
        
        return true;
    }
}
