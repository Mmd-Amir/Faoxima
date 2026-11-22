<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $_rx_nav_styles, $admin_ids, $backuser, $buyreport, $connect, $datain, $errorreport, $first_name, $from_id, $fund, $keyboard, $message_id, $paymentom, $pdo, $porsantreport, $setting, $telegramRoute, $text_inline, $textbotlang, $user, $username.
 * Inherited or exported by reference: $Response, $dataoutput, $datatextbot, $error, $info_product, $invoice, $marzban_list_get, $priceproduct, $randomString, $result, $stmt, $userdate, $username_ac, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ConfirmPurchase
{
    public static function handle(
        $ManagePanel,
        &$Response,
        $_rx_nav_styles,
        $admin_ids,
        $backuser,
        $buyreport,
        $connect,
        $datain,
        &$dataoutput,
        &$datatextbot,
        &$error,
        $errorreport,
        $first_name,
        $from_id,
        $fund,
        &$info_product,
        &$invoice,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        $paymentom,
        $pdo,
        $porsantreport,
        &$priceproduct,
        &$randomString,
        &$result,
        $setting,
        &$stmt,
        $telegramRoute,
        $text_inline,
        $textbotlang,
        $user,
        &$userdate,
        $username,
        &$username_ac,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'purchase_confirm':
            $userdate = json_decode($user['Processing_value'], true);
            if (!is_array($userdate) || empty($userdate['name_panel'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            rxRemoveMessageKeyboard($from_id, $message_id);

            $parts = explode("_", $user['Processing_value_one']);
        
            $partsdic = explode("_", $user['Processing_value_four']);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            if (!is_array($marzban_list_get)) {
                sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!empty($message_id)) {
                $confirmedInvoice = \Faoxima\Services\Fulfillment\Purchase::walletInvoiceForConfirmation(
                    $pdo, (string) $from_id, 'telegram:' . $message_id
                );
                if ($confirmedInvoice !== null) {
                    try {
                        \Faoxima\Services\Fulfillment\Purchase::recoverWalletInvoice(
                            $pdo, (string) $from_id, $confirmedInvoice, $ManagePanel, $marzban_list_get
                        );
                    } catch (Throwable $error) {
                        error_log('Wallet purchase read-back needs review: ' . $confirmedInvoice);
                    }
                    sendmessage($from_id, $datatextbot['dyn_errors_restart_buy_process_short'] ?? "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
                    return;
                }
            }
            if ($marzban_list_get['status'] == "disable") {
                sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
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
                    $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
                } elseif (method_exists(\Faoxima\Orders\Catalog\CatalogLookups::class, 'nmProductByCodeForPanel')) {
                    $info_product = \Faoxima\Orders\Catalog\Products::nmProductByCodeForPanel($pdo, $user['Processing_value_one'], $userdate['name_panel'], $user['agent']);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                    $stmt->execute([
                        ':code_product' => $user['Processing_value_one'],
                        ':location' => $userdate['name_panel'],
                        ':agent' => $user['agent']
                    ]);
                    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            if (!is_array($info_product) || !isset($info_product['price_product'])) {
                error_log('Purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
        
            if (!array_key_exists('category', $info_product)) {
                $info_product['category'] = '';
            }
            if (!array_key_exists('note', $info_product)) {
                $info_product['note'] = '';
            }
            $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($parts[0] != "customvolume") {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            if ($datain == "confirmandgetserviceDiscount") {
                $discountcode = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[0], "count");
                if ($discountcode == 0) {
                    sendmessage($from_id, $datatextbot['dyn_errors_discount_code_not_applicable'] ?? "❌ امکان خرید با این کد کد تخفیف وجود ندارد", null, 'HTML');
                    return;
                }
                $priceproduct = $partsdic[1];
                $fxBuyDiscountKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('buy_discount', (string) $user['Processing_value_one']);
                if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxBuyDiscountKey, $priceproduct, $marzban_list_get, $fxBuyKinds)) {
                    $fxRecheck = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, (string) $partsdic[0], 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
                    if (empty($fxRecheck['ok'])) {
                        sendmessage($from_id, (string)($fxRecheck['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                        return;
                    }
                    $fxNewDiscounted = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyDiscountRule(\Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxBuyKinds), (string) $fxRecheck['value_type'], (float) $fxRecheck['value']);
                    $fxNewDiscounted = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($fxNewDiscounted, $marzban_list_get, $fxBuyKinds);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $partsdic[0] . "_" . $fxNewDiscounted, "id", $from_id);
                    \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxBuyDiscountKey, $fxNewDiscounted, $marzban_list_get, $fxBuyKinds);
                    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
                    sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxNewDiscounted) . (($parts[0] ?? '') === 'customvolume' ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $fxNewDiscounted) : ''), json_encode(['inline_keyboard' => [
                        [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_pay_and_get'), 'callback_data' => "confirmandgetserviceDiscount"]],
                        [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s)],
                    ]]), 'HTML');
                    return;
                }
            } else {
                $priceproduct = $info_product['price_product'];
                $fxBuyCheckAmount = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($priceproduct, $user['pricediscount']), $marzban_list_get, $fxBuyKinds);
                $fxBuyQuoteKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('buy', (string) $user['Processing_value_one']);
                if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxBuyQuoteKey, $fxBuyCheckAmount, $marzban_list_get, $fxBuyKinds)) {
                    \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxBuyQuoteKey, $fxBuyCheckAmount, $marzban_list_get, $fxBuyKinds);
                    sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxBuyCheckAmount) . (($parts[0] ?? '') === 'customvolume' ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $fxBuyCheckAmount) : ''), $paymentom, 'HTML');
                    return;
                }
            }
            $fxBuyListPrice = $info_product['price_product'];
            $username_ac = strtolower($user['Processing_value_tow']);
            $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
            if (isset($DataUserOut['username']) || \Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'username', $username_ac)) {
                sendmessage($from_id, $datatextbot['dyn_errors_restart_buy_process_short'] ?? "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
                return;
            }
            $date = time();
            $randomString = bin2hex(random_bytes(4));
            $random_number = rand(1000000, 9999999);
            if (\Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'id_invoice', $randomString)) {
                $randomString = $random_number . $randomString;
            }
            if ($marzban_list_get['type'] == "Manualsale") {
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
                $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
                $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
                $stmt->bindParam(':codeproduct', $info_product['code_product']);
                $stmt->execute();
                $configexits = $stmt->rowCount();
                if (intval($configexits) == 0) {
                    sendmessage($from_id, $datatextbot['dyn_errors_stock_low_purchase_blocked'] ?? "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
                    return;
                }
            }
            if (intval($user['pricediscount']) != 0) {
                $result = ($priceproduct * $user['pricediscount']) / 100;
                $priceproduct = $priceproduct - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $priceproduct = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($priceproduct, $marzban_list_get, $fxBuyKinds);
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] === 0.0) {
                $rxJovadFreeze = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelFreezeUnlimitedOrder')
                    ? \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelFreezeUnlimitedOrder($marzban_list_get, $randomString, $username_ac, $info_product['Service_time'])
                    : ['status' => false, 'msg' => 'خرید محصول نامحدود این پنل در حال حاضر امکان‌پذیر نیست.'];
                if (empty($rxJovadFreeze['status'])) {
                    Editmessagetext($from_id, $message_id, (string) ($rxJovadFreeze['msg'] ?? 'خرید محصول نامحدود این پنل امکان‌پذیر نیست.'), null, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
                    return;
                }
            }
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' && is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] > 0) {
                $rxJovadChannelFreeze = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelFreezeChannelOrder')
                    ? \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelFreezeChannelOrder($marzban_list_get, $randomString, $username_ac, is_array($info_product) ? $info_product : array())
                    : ['status' => false, 'msg' => 'انتخاب کانال‌های این محصول در دسترس نیست؛ خرید انجام نشد.'];
                if (empty($rxJovadChannelFreeze['status'])) {
                    Editmessagetext($from_id, $message_id, (string) ($rxJovadChannelFreeze['msg'] ?? 'انتخاب کانال‌های این محصول معتبر نیست؛ خرید انجام نشد.'), null, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
                    return;
                }
            }
            $notifctions = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
            $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
            $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
            $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
            $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?,?,?)");
            $Status = "unpaid";
            $stmt->bind_param("sssssssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
            $stmt->execute();
            $stmt->close();
            $fxBuyBasePrice = $parts[0] == "customvolume" ? \Faoxima\Orders\Pricing\ExchangePricing::fxCustomBasePrice($marzban_list_get, $user['agent'], $parts[2], $parts[1]) : ($info_product['fx_base_price'] ?? $fxBuyListPrice);
            $fxBuySnapshot = \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($marzban_list_get, $fxBuyKinds, $fxBuyBasePrice, $priceproduct, (float) $fxBuyListPrice - (float) $priceproduct, ['product' => (string)($info_product['code_product'] ?? '')]);
            if (($parts[0] ?? '') === 'customvolume') {
                $fxBuySnapshot = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_snapshot($marzban_list_get, $user['agent'], $parts[2], $parts[1], $priceproduct, $fxBuySnapshot);
            }
            \Faoxima\Orders\Pricing\ExchangePricing::fxStoreInvoiceSnapshot($randomString, $fxBuySnapshot);
            if ($priceproduct > \Faoxima\Orders\Checkout\Checkout::availableForUser($user) && $user['agent'] != "n2" && intval($priceproduct) != 0) {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
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
                } else {
                    \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_begin($user, [
                        'operation' => 'getconfigafterpay',
                        'op_key' => "getconfigafterpay|{$username_ac}",
                        'target' => $username_ac,
                        'final_price' => $priceproduct,
                        'message_id' => $message_id,
                        'one' => $username_ac,
                        'tow' => 'getconfigafterpay',
                        'four' => $datain == "confirmandgetserviceDiscount" ? "dis_{$partsdic[0]}" : '',
                    ]);
                }
                return;
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (intval($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $rxProgress = rxBeginProgressMessage($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_provisioning_progress'));
            $SellDiscountlimit = false;
            if ($datain == "confirmandgetserviceDiscount") {
                $SellDiscountlimit = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
                if ($SellDiscountlimit != false) {
                    \Faoxima\Core\Database\Db::update('invoice', 'discount_code', $partsdic[0], 'id_invoice', $randomString);
                    \Faoxima\Core\Database\Db::update('invoice', 'discount_amount', (string)((float)($info_product['price_product'] ?? 0) - (float)$priceproduct), 'id_invoice', $randomString);
                    \Faoxima\Core\Database\Db::update('invoice', 'price_before_discount', (string)($info_product['price_product'] ?? 0), 'id_invoice', $randomString);
                }
            }
            $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
            if ($info_product['Service_time'] == 0) {
                $datetimestep = 0;
            } else {
                $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
            }
            $datac = array(
                'expire' => $datetimestep,
                'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
                'id_invoice' => (string) $randomString,
                'from_id' => $from_id,
                'username' => $username,
                'type' => 'buy'
            );
            $Shoppinginfo = [
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
                    ]
                ]
            ];
            $discountCode = is_array($SellDiscountlimit) ? (string) $partsdic[0] : '';
            $confirmation = 'telegram:' . ($message_id ?: 'invoice:' . $randomString);
            $stockPurchase = \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($marzban_list_get);
            $existingInvoice = \Faoxima\Services\Fulfillment\Purchase::walletInvoiceForConfirmation($pdo, (string) $from_id, $confirmation);
            $panelLimitLock = \Faoxima\Services\Panels\PanelLimits::panel_limit_lock_acquire((string) $marzban_list_get['name_panel']);
            try {
                if ($existingInvoice !== null) {
                    $fund = ['ok' => false, 'reason' => 'invoice_already_claimed', 'existing_invoice' => $existingInvoice];
                } elseif ($panelLimitLock === null || \Faoxima\Services\Panels\PanelLimits::panel_creation_limit_reached_unlocked($marzban_list_get)) {
                    $fund = ['ok' => false, 'reason' => 'panel_limit'];
                } else {
                    $fund = \Faoxima\Services\Fulfillment\Purchase::fundWalletInvoice(
                        $pdo, ['id' => $from_id] + $user, (string) $randomString,
                        (string) $marzban_list_get['name_panel'], (string) $username_ac, (float) (int) $priceproduct,
                        $discountCode, 'خرید سرویس', $confirmation,
                        \Faoxima\Services\Fulfillment\WalletPurchaseEffects::plan(
                            $setting, (array) \Faoxima\Core\Database\Db::select('affiliates', '*', null, null, 'select'), $user, $admin_ids, (float) $priceproduct,
                            \Faoxima\Cashback\AffiliateCommissions::affiliatePaidInvoiceCount($pdo, $from_id) + 1,
                            true, (string) ($buyreport ?? ''), $stockPurchase
                        )
                    );
                }
            } finally {
                \Faoxima\Services\Panels\PanelLimits::panel_limit_lock_release($panelLimitLock);
            }
            if (empty($fund['ok'])) {
                if (!empty($fund['existing_invoice'])) {
                    try {
                        \Faoxima\Services\Fulfillment\Purchase::recoverWalletInvoice(
                            $pdo, (string) $from_id, $fund['existing_invoice'], $ManagePanel, $marzban_list_get
                        );
                    } catch (Throwable $error) {
                        error_log('Wallet purchase read-back needs review: ' . $fund['existing_invoice']);
                    }
                }
                rxEndProgressMessage($from_id, $rxProgress);
                sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $purchaseAmount =(float) (int) $priceproduct;
            if ($stockPurchase) {
                $stock = \Faoxima\Services\Fulfillment\Purchase::settleWalletStock(
                    $pdo, (string) $from_id, (string) $randomString, $marzban_list_get, $info_product, 'national_buy'
                );
                if ($stock !== null) {
                    $invoice = ['id_user' => $from_id, 'id_invoice' => $randomString, 'username' => $username_ac,
                        'Service_location' => $marzban_list_get['name_panel'], 'name_product' => $info_product['name_product'],
                        'Volume' => $info_product['Volume_constraint'], 'Service_time' => $info_product['Service_time']];
                    \Faoxima\Services\Fulfillment\WalletPurchaseEffects::send($pdo, (string) $from_id, (string) $randomString, 'delivery',
                        static fn () => \Faoxima\Services\Fulfillment\StockPresentation::nmStockDeliverConfig($stock, $invoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار پشتیبان تحویل شد')
                    );
                    if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockNotifyExtend')) {
                        \Faoxima\Services\Fulfillment\StockPresentation::nmStockNotifyExtend($from_id, $user, $invoice, $info_product, $marzban_list_get, 'stock', (string) $username_ac, $purchaseAmount, 'buy');
                    }
                    rxEndProgressMessage($from_id, $rxProgress);
                    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    return;
                }
                \Faoxima\Services\Fulfillment\Purchase::refundWalletInvoice(
                    $pdo, (string) $from_id, (string) $randomString,
                    $purchaseAmount, $discountCode, 'بازگشت وجه به دلیل خطای انبار'
                );
                rxEndProgressMessage($from_id, $rxProgress);
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_stock_empty_choose_another'), $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $provision = \Faoxima\Services\Fulfillment\Purchase::provisionFunded(
                $pdo, (string) $from_id, (string) $randomString, $ManagePanel,
                $marzban_list_get, $info_product, (string) $username_ac, $datac
            );
            $dataoutput = $provision['remote'];
            if ($provision['uncertain']) {
                if (empty($provision['claim_unavailable'])) {
                    \Faoxima\Services\Fulfillment\Purchase::finishWalletInvoice($pdo, (string) $from_id, (string) $randomString, 'manual_review');
                }
                if (is_callable([\Faoxima\Payments\Fulfillment\Settlement::class, 'alertAdmin'])) {
                    $reviewKey = ($marzban_list_get['type'] ?? '') === 'jovadpanel' ? 'jovad:' : 'wallet_purchase:';
                    \Faoxima\Payments\Fulfillment\Settlement::alertAdmin($reviewKey . $randomString, 'نتیجه ساخت اشتراک کیف پول نامشخص است؛ پیش از ساخت مجدد، سرویس و تسویه بررسی شود.', ['username' => $username_ac, 'panel' => $marzban_list_get['name_panel'], 'invoice' => $randomString]);
                }
                rxEndProgressMessage($from_id, $rxProgress);
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_provision_result_unknown'), $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
                \Faoxima\Services\Fulfillment\Purchase::refundWalletInvoice(
                    $pdo, (string) $from_id, (string) $randomString,
                    $purchaseAmount, $discountCode, 'بازگشت وجه به دلیل خطای ساخت سرویس'
                );
                $dataoutput['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
                rxEndProgressMessage($from_id, $rxProgress);
                sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                $texterros = "⭕️ خطای ساخت اشتراک
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
            $username_ac = \Faoxima\Services\Panels\PanelMessages::rx_adopt_created_username($dataoutput, $username_ac, $randomString);
            \Faoxima\Services\Panels\PanelMessages::rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
            if (!\Faoxima\Services\Fulfillment\Purchase::finishWalletInvoice($pdo, (string) $from_id, (string) $randomString, 'active')) {
                rxEndProgressMessage($from_id, $rxProgress);
                sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $output_config_link = "";
            $config = "";
            $output_config_link = \Faoxima\Services\Delivery\ServiceDelivery::rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? \Faoxima\Services\Delivery\ServiceDelivery::rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
            if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
                $rxTestTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
                for ($i = 0; $i < count($rxTestTextConfigs); ++$i) {
                    $config .= "\n" . $rxTestTextConfigs[$i];
                }
            }
            $Shoppinginfo = json_encode($Shoppinginfo);
            $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
            $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
            $rxServicePanelType = (string) ($marzban_list_get['type'] ?? '');
            $rxServiceTemplateKey = $rxServicePanelType === 'Manualsale' ? 'textmanual' : ($rxServicePanelType === 'WGDashboard' ? 'text_wgdashboard' : 'textafterpay');
            $rxServiceTemplateBefore = (string) ($datatextbot['textafterpay'] ?? '');
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_enabled') && \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_enabled() && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_active') && !\Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_active() && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_begin')) {
                \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_begin($rxServiceTemplateKey, 'src/Telegram/Handlers/ConfirmPurchase.php', 'service_template_render');
                $GLOBALS['rx_rich_diag_service_key'] = $rxServiceTemplateKey;
            }
            if (intval($info_product['Service_time']) == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' ? (is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] === 0.0) : intval($info_product['Volume_constraint']) == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            $textcreatuser = str_replace('{username}', "<code>" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
            $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
            $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
            $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
            $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
            if (($parts[0] ?? '') === 'customvolume') {
                $textcreatuser .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $priceproduct);
            }
            $textcreatuser = \Faoxima\Core\Database\Db::applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' ? $info_product['Volume_constraint'] === $textbotlang['users']['stateus']['Unlimited'] : intval($info_product['Volume_constraint']) == 0) {
                $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
            }
            if ($marzban_list_get['type'] == "Manualsale") {
                $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
                \Faoxima\Core\Database\Db::update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
            }
            if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_text') && method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_structure')) {
                $rxServiceBefore = \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($rxServiceTemplateBefore);
                $rxServiceAfter = \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_text($textcreatuser);
                $rxServiceRich = isset($datatextbot['richmsg.' . $rxServiceTemplateKey]) ? (string) $datatextbot['richmsg.' . $rxServiceTemplateKey] : '';
                $GLOBALS['rx_rich_diag_templates'][$rxServiceTemplateKey] = [
                    'text' => $rxServiceAfter,
                    'structure' => \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_structure($rxServiceRich),
                    'variables' => [
                        'before' => (int) $rxServiceBefore['variables'],
                        'after' => (int) $rxServiceAfter['variables'],
                        'resolved' => max(0, (int) $rxServiceBefore['variables'] - (int) $rxServiceAfter['variables']),
                        'unresolved' => (int) $rxServiceAfter['variables'],
                        'length_before' => (int) $rxServiceBefore['length'],
                        'length_after' => (int) $rxServiceAfter['length'],
                        'length_delta' => (int) $rxServiceAfter['length'] - (int) $rxServiceBefore['length'],
                        'table_preserved' => !empty($rxServiceBefore['html_table']) && !empty($rxServiceAfter['html_table']),
                        'rows_preserved' => (int) $rxServiceAfter['row_count'] >= (int) $rxServiceBefore['row_count'],
                        'rtl_marker_before' => !empty($rxServiceBefore['rtl_marker']),
                        'rtl_marker_after' => !empty($rxServiceAfter['rtl_marker']),
                    ],
                    'selection' => [
                        'template_key' => $rxServiceTemplateKey,
                        'template_source' => 'textbot_row',
                        'panel_type' => $rxServicePanelType,
                        'selection_mode' => 'panel_type_conditional',
                        'rich_structure_key' => 'richmsg.' . $rxServiceTemplateKey,
                        'rich_structure_present' => trim($rxServiceRich) !== '',
                        'render_input' => 'textbot_text_html',
                        'parse_mode' => 'html',
                        'send_call' => 'sendMessageService',
                        'sub_link_present' => trim((string) $output_config_link) !== '',
                        'config_count' => is_array($dataoutput['configs'] ?? null) ? count($dataoutput['configs']) : 0,
                        'invoice_present' => trim((string) $randomString) !== '',
                    ],
                ];
                $GLOBALS['rx_rich_diag_service_key'] = $rxServiceTemplateKey;
            }
            \Faoxima\Services\Fulfillment\WalletPurchaseEffects::send($pdo, (string) $from_id, (string) $randomString, 'delivery',
                static function () use ($rxServiceTemplateKey, $marzban_list_get, $dataoutput, $output_config_link, $Shoppinginfo, $textcreatuser, $randomString, $from_id) {
                    $deliveryResult = null;
                    $deliver = static function () use ($marzban_list_get, $dataoutput, $output_config_link, $Shoppinginfo, $textcreatuser, $randomString, &$deliveryResult) {
                        \Faoxima\Services\Delivery\ServiceDelivery::sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'],
                            $Shoppinginfo, $textcreatuser, $randomString, null, 'images.jpg', false, $deliveryResult);
                    };
                    if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_traceSend')) {
                        \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_traceSend($rxServiceTemplateKey, $deliver);
                        $GLOBALS['rx_rich_diag_service_key'] = null;
                    } else {
                        $deliver();
                    }
                    $files = \Faoxima\Services\Delivery\ServiceDelivery::rxAutoDeliverPurchaseFilesSafe($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $from_id, $randomString);
                    return !empty($deliveryResult['ok']) && empty($files['failed']) && empty($files['unknown']);
                }
            );
            rxEndProgressMessage($from_id, $rxProgress);
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($user['number_username']) + 1;
                \Faoxima\Core\Database\Db::update("user", "number_username", $value, "id", $from_id);
                if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                    $value = intval($setting['numbercount']) + 1;
                    \Faoxima\Core\Database\Db::update("setting", "numbercount", $value);
                }
            }
            $countinvoice = \Faoxima\Cashback\AffiliateCommissions::affiliatePaidInvoiceCount($pdo, $from_id);
            $referrer = (string) ($user['affiliates'] ?? '');
            $commission = \Faoxima\Services\Fulfillment\WalletPurchaseEffects::commission(
                $pdo, (string) $from_id, (string) $randomString,
                \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_affiliate_commission_ledger_note', 'پورسانت خرید زیرمجموعه')
            );
            if ($commission !== null) {
                if ($commission['awarded_points']) {
                    $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                    sendmessage($referrer, sprintf($rxPointsEarnedTpl, 2), null, 'html');
                }
                $result = number_format($commission['amount']);
                $dateacc = date('Y/m/d H:i:s');
                $textadd = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
                $textreportport = "
مبلغ $result به کاربر $referrer برای پورسانت از کاربر $from_id واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => 'HTML',
                    ]);
                }
                sendmessage($referrer, $textadd, null, 'HTML');
            }
            if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
                $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
                $scorenew = $user['score'] + 1;
                \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $from_id);
            }
            $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
            $textonebuy = "";
            if ($countinvoice == 1) {
                $textonebuy = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_first_buy_flag', "📌 خرید اول کاربر");
            }
            $balanceformatsellbefore = number_format($user['Balance'], 0);
            $rxFmtInfoProductPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']);
            $rxFmtPriceproduct = \Faoxima\Core\Support\Formatting::rxFormatToman($priceproduct);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            $timejalali = jdate('Y/m/d H:i:s');
            $text_report = "📣 جزئیات ساخت اکانت در ربات شما ثبت شد .

$textonebuy
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$userdate['name_panel']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️دسته بندی محصول : {$info_product['category']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtPriceproduct} تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                \Faoxima\Services\Fulfillment\WalletPurchaseEffects::send($pdo, (string) $from_id, (string) $randomString, 'report',
                    static fn () => \Faoxima\Telegram\Support\Transport::rx_sendTopicReport([
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $buyreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ], ['flow' => 'buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id])
                );
            }
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('new_sub', [
                    'user_id'    => $from_id,
                    'amount'     => $info_product['name_product'],
                    'price'      => number_format((float)$priceproduct),
                    'panel_name' => $userdate['name_panel'] ?? '',
                    'category'   => $info_product['category'] ?? '',
                ], $setting);
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "none", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }
        
        return true;
    }
}
