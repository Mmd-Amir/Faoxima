<?php

namespace Faoxima\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $admin_ids, $backuser, $dataget, $datatextbot, $errorreport, $from_id, $keyboard, $message_id, $otherservice, $pdo, $setting, $telegramRoute, $text, $textbotlang, $user.
 * Inherited or exported by reference: $before, $day, $error, $id_invoice, $marzban_list_get, $nameloc, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AdditionalTime
{
    public static function handle(
        $ManagePanel,
        $admin_ids,
        $backuser,
        &$before,
        $dataget,
        $datatextbot,
        &$day,
        &$error,
        $errorreport,
        $from_id,
        &$id_invoice,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $otherservice,
        $pdo,
        &$result,
        $setting,
        $telegramRoute,
        $text,
        $textbotlang,
        $user
    ) {
        switch ($telegramRoute) {
        case 'time_start':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
        
            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_time on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_time',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($marzban_list_get['status_extend'] == "off_extend") {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_time_unavailable_panel'] ?? "❌ امکان خرید زمان اضافه در این پنل وجود ندارد", null, 'html');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            if ($DataUserOut['status'] == "on_hold") {
                sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
            $extratimepricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_time_ask', ['price' => $extratimepricevalue]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
            \Faoxima\Telegram\Navigation\Steps::step('gettimeextra', $from_id);
            break;
        case 'time_amount':
            if (!ctype_digit($text) || $text < 1) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
                return;
            }
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
            $extratimepricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            $priceextratime = $extratimepricevalue * $text;
            $priceextratime = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($priceextratime, $marzban_list_get, 'extra_time');
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStoreServer($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_time', (string) $nameloc['id_invoice']), $priceextratime, $marzban_list_get, 'extra_time', ['qty' => (int) $text]);
            $__timeSourceProduct = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
            $__timeCategory = is_array($__timeSourceProduct) ? (string)($__timeSourceProduct['category'] ?? '') : '';
            $__discEligibleTime = \Faoxima\Orders\Discounts\DiscountRules::hasEligible($pdo, 'time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory, $user);
            $__timeRows = [
                [
                    ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $priceextratime],
                ],
            ];
            if ($__discEligibleTime) {
                $__timeRows[] = [
                    ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_discount_code'), 'callback_data' => 'discounttime-' . $priceextratime],
                ];
            }
            $keyboardsetting = json_encode(['inline_keyboard' => $__timeRows]);
            $priceextratime = number_format($priceextratime, 0);
            $extrapricevalues = number_format($extrapricevalue, 0);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_time_invoice', ['price' => $extratimepricevalue, 'days' => $text, 'total' => $priceextratime]);
            sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'time_discount_prompt':
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "pretime_" . $dataget[1], "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcodesellDiscounttime', $from_id);
            deletemessage($from_id, $message_id);
            break;
        case 'time_discount':
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $__pv4 = (string)$user['Processing_value_four'];
            $__baseTime = (strpos($__pv4, 'pretime_') === 0) ? intval(substr($__pv4, 8)) : 0;
            if ($__baseTime <= 0) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $__timeSourceProduct2 = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
            $__timeCategory2 = is_array($__timeSourceProduct2) ? (string)($__timeSourceProduct2['category'] ?? '') : '';
            $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, $text, 'time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory2, $user);
            if (empty($dv['ok'])) {
                sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
                return;
            }
            if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteServerAmountMatches($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_time', (string) $nameloc['id_invoice']), $__baseTime) || !\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_time', (string) $nameloc['id_invoice']), $__baseTime, $marzban_list_get, 'extra_time')) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $__discounted = (int)round(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $__baseTime));
            if ($__discounted < 0) $__discounted = 0;
            $__discounted = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($__discounted, $marzban_list_get, 'extra_time');
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "distime_" . $text . "_" . $__baseTime . "_" . $__discounted, "id", $from_id);
            $__basefmt = number_format($__baseTime, 0);
            $__disfmt  = number_format($__discounted, 0);
            $keyboardsetting = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratimediscount-' . $__baseTime],
                    ]
                ]
            ]);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_time_discount_invoice', ['label' => $dv['label'], 'before' => $__basefmt, 'after' => $__disfmt]);
            sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'time_confirm':
            $tmieextra = $dataget[2];
            $__timeDiscountCode = '';
            $__timeCharge = (float)$tmieextra;
            if (($dataget[1] ?? '') === 'discount') {
                $__pv4 = (string)$user['Processing_value_four'];
                if (strpos($__pv4, 'distime_') === 0) {
                    $__pp = explode('_', $__pv4);
                    $__timeDiscountCode = $__pp[1] ?? '';
                    if (isset($__pp[3])) $__timeCharge = (float)$__pp[3];
                }
            }
            $pricelasttime = $__timeCharge;
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (!is_array($nameloc) || (string) ($nameloc['id_user'] ?? '') !== (string) $from_id
                || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
                sendmessage($from_id, $datatextbot['dyn_errors_purchase_failed_restart'] ?? "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
                return;
            }
            $operationId = 'tgextra_' . substr(hash('sha256', $from_id . ':' . $message_id . ':time'), 0, 48);
            if (\Faoxima\Services\Fulfillment\WalletExtensions::blockingOperation(
                $pdo, (string) $from_id, $nameloc['username'], 'extra_time_user', (string) $nameloc['id_invoice'], $operationId
            ) !== null) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_request_already_registered'), null, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
            $extratimepricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
            $fxTimeVerify = \Faoxima\Orders\Pricing\ExchangePricing::fxExtraQuoteVerify($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_time', (string) $nameloc['id_invoice']), $extratimepricevalue, $tmieextra, $marzban_list_get, 'extra_time');
            if ($fxTimeVerify['status'] === 'missing') {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                return;
            }
            if ($fxTimeVerify['status'] === 'changed') {
                sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxTimeVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $fxTimeVerify['amount']]]]]), 'HTML');
                return;
            }
            $fxTimeQty = $fxTimeVerify['qty'];
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $__timeCharge && $user['agent'] != "n2") {
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
                    return;
                } else {
                    $valuetime = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
                    if (intval($user['pricediscount']) != 0) {
                        $result = ($__timeCharge * $user['pricediscount']) / 100;
                        $pricelasttime = $__timeCharge - $result;
                        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
                    }
                    $pricelasttime = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($pricelasttime, $marzban_list_get, 'extra_time');
                    if (intval($pricelasttime) != 0) {
                        \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_begin($user, [
                            'operation' => 'getextratimeuser',
                            'op_key' => "getextratimeuser|{$nameloc['username']}%{$valuetime}",
                            'target' => (string) $nameloc['username'],
                            'final_price' => $pricelasttime,
                            'message_id' => $message_id,
                            'one' => "{$nameloc['username']}%{$valuetime}",
                            'tow' => 'getextratimeuser',
                            'four' => $__timeDiscountCode !== '' ? "dtime|" . $__timeDiscountCode . "|" . $tmieextra : '',
                        ]);
                        return;
                    }
                }
            }
            deletemessage($from_id, $message_id);
            if (intval($user['pricediscount']) != 0 and intval($pricelasttime) != 0) {
                $result = ($__timeCharge * $user['pricediscount']) / 100;
                $pricelasttime = $__timeCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $pricelasttime = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($pricelasttime, $marzban_list_get, 'extra_time');
            $Balance_Low_user = $user['Balance'] - $pricelasttime;
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if ($Balance_Low_user < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
        
            $extratimeday = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $data_for_database = json_encode(array(
                'day' => $extratimeday,
                'priceـper_day' => $extratimeday,
                'old_volume' => $DataUserOut['data_limit'],
                'expire_old' => $DataUserOut['expire']
            ));
            $data_for_database = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($data_for_database, \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($marzban_list_get, 'extra_time', ($fxTimeQty ?? 0) * (\Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($marzban_list_get, 'priceextratime', $user['agent']) ?? 0), $pricelasttime, (float) $tmieextra - (float) $pricelasttime, ['quantity' => $fxTimeQty, 'unit_price' => $extratimepricevalue]));
            $timeservice = $DataUserOut['expire'] - time();
            $day = floor($timeservice / 86400);
            $before = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut, (string) ($marzban_list_get['type'] ?? ''));
            $order = [
                'operation_id' => $operationId,
                'invoice_id' => (string) $nameloc['id_invoice'],
                'ledger_invoice' => (string) $nameloc['id_invoice'],
                'panel' => (string) $marzban_list_get['name_panel'],
                'kind' => 'time',
                'amount' => $extratimeday,
                'discount_code' => (string) $__timeDiscountCode,
                'before' => empty($before['exists']) ? null : \Faoxima\Services\Fulfillment\ProviderState::limits($before),
                'target' => empty($before['ok']) || empty($before['exists']) ? null : \Faoxima\Services\Fulfillment\Adjustments::extraTimeTarget($before, $extratimeday, time()),
                'wallet_state' => 'charged',
            ];
            $reservation = \Faoxima\Services\Fulfillment\WalletExtensions::reserve(
                $pdo, ['id' => $from_id] + $user, (string) $nameloc['username'], $order, 'extra_time_user',
                (float) $pricelasttime, (string) $user['agent'], 'خرید زمان اضافه'
            );
            if (empty($reservation['ok'])) {
                if (!empty($reservation['blocking_operation'])) {
                    try {
                        \Faoxima\Services\Fulfillment\WalletExtensions::reconcileUncertain(
                            $pdo, (string) $from_id, $reservation['blocking_operation'], $ManagePanel, $marzban_list_get
                        );
                    } catch (Throwable $error) {
                        error_log('Extension read-back could not be recorded: ' . $reservation['blocking_operation']);
                    }
                }
                if (!empty($reservation['blocking_operation'])) {
                    sendmessage($from_id, $textbotlang['users']['stateus']['error'] . "\n" . $reservation['blocking_operation'], null, 'HTML');
                } else {
                    sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
                }
                return;
            }
            $Balance_Low_user = $reservation['new_balance'];
            $execution = \Faoxima\Services\Fulfillment\WalletExtensions::applyReserved(
                $pdo, $reservation['id'], (string) $from_id, $order, $ManagePanel, $marzban_list_get, (string) $nameloc['username'], $extratimeday
            );
            $extra_time = is_array($execution['result']) ? $execution['result'] : ['status' => false];
            $order = $execution['order'];
            if ($execution['outcome'] !== 'applied') {
                if ($execution['outcome'] === 'refundable') {
                    \Faoxima\Services\Fulfillment\WalletExtensions::refund(
                        $pdo, $reservation['id'], ['id' => $from_id], $order, (float) $pricelasttime, $extra_time, 'بازگشت وجه زمان اضافه'
                    );
                }
                $extra_time['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extra_time['msg'] ?? null, $extra_time['detail'] ?? null);
                $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_time['msg']}</blockquote>";
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
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
        
            if (!\Faoxima\Services\Fulfillment\WalletExtensions::complete(
                $pdo, $reservation['id'], (string) $from_id, $order, (array) json_decode($data_for_database, true), $extra_time
            )) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'HTML');
                return;
            }
            \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
                'id_user' => $from_id,
                'id_invoice' => $nameloc['id_invoice'] ?? null,
                'Service_location' => $nameloc['Service_location'],
                'kind' => 'time',
                'name_product' => $nameloc['name_product'] ?? null,
                'amount' => $pricelasttime,
                'source' => 'bot',
            ]);
            if ($__timeDiscountCode !== '') {
                \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount($pdo, [
                    'id_user' => $from_id,
                    'id_invoice' => $nameloc['id_invoice'] ?? null,
                    'code' => $__timeDiscountCode,
                    'kind' => 'sell',
                    'price_before' => $tmieextra,
                    'discount_amount' => (float)$tmieextra - (float)$pricelasttime,
                    'price_after' => $pricelasttime,
                    'section' => 'time',
                ]);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "", "id", $from_id);
            }
        
            $keyboardextrafnished = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
                $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
                $scorenew = $user['score'] + 1;
                \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $from_id);
            }
            $volumesformat = number_format($tmieextra);
            $textextratime = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_time_success', ['username' => $nameloc['username'], 'days' => $extratimeday, 'total' => $volumesformat]);
            sendmessage($from_id, $textextratime, $keyboardextrafnished, 'HTML');
            $volumes = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
            $text_report = "⭕️ یک کاربر زمان اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 زمان خریداری شده  : $volumes روز</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('time_extra', [
                    'user_id' => $from_id,
                    'amount'  => $volumes,
                    'price'   => $volumesformat,
                ], $setting);
            }
            break;
        }
        
        return true;
    }
}
