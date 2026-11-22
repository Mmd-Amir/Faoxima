<?php

namespace Faoxima\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $admin_ids, $backuser, $dataget, $datatextbot, $errorreport, $from_id, $keyboard, $message_id, $otherservice, $pdo, $setting, $telegramRoute, $text, $textbotlang, $user.
 * Inherited or exported by reference: $before, $data_limit, $error, $id_invoice, $marzban_list_get, $nameloc, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AdditionalTraffic
{
    public static function handle(
        $ManagePanel,
        $admin_ids,
        $backuser,
        &$before,
        &$data_limit,
        $dataget,
        $datatextbot,
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
        case 'traffic_start':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
        
            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_volume on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_volume',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($marzban_list_get['status_extend'] == "off_extend") {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_unavailable_panel'] ?? "❌ امکان خرید حجم اضافه در این پنل وجود ندارد", null, 'html');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_traffic_ask', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($extrapricevalue)]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
            \Faoxima\Telegram\Navigation\Steps::step('getvolumeextra', $from_id);
            break;
        case 'traffic_amount':
            if (!ctype_digit($text)) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            if ($text < 1) {
                sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
                return;
            }
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            $priceextra = $extrapricevalue * $text;
            $priceextra = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($priceextra, $marzban_list_get, 'extra_volume');
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStoreServer($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume', (string) $nameloc['id_invoice']), $priceextra, $marzban_list_get, 'extra_volume', ['qty' => (int) $text]);
            $__volSourceProduct = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
            $__volCategory = is_array($__volSourceProduct) ? (string)($__volSourceProduct['category'] ?? '') : '';
            $__discEligibleVolume = \Faoxima\Orders\Discounts\DiscountRules::hasEligible($pdo, 'volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory, $user);
            $__volumeRows = [
                [
                    ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $priceextra],
                ],
            ];
            if ($__discEligibleVolume) {
                $__volumeRows[] = [
                    ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_discount_code'), 'callback_data' => 'discountvolume-' . $priceextra],
                ];
            }
            $keyboardsetting = json_encode(['inline_keyboard' => $__volumeRows]);
            $priceextra = number_format($priceextra, 0);
            $extrapricevalues = number_format($extrapricevalue, 0);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_traffic_invoice', ['price' => $extrapricevalues, 'amount' => $text, 'total' => $priceextra]);
            sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'traffic_discount_prompt':
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "prevol_" . $dataget[1], "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcodesellDiscountvolume', $from_id);
            deletemessage($from_id, $message_id);
            break;
        case 'traffic_discount':
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $__pv4 = (string)$user['Processing_value_four'];
            $__baseVol = (strpos($__pv4, 'prevol_') === 0) ? intval(substr($__pv4, 7)) : 0;
            if ($__baseVol <= 0) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $__volSourceProduct2 = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
            $__volCategory2 = is_array($__volSourceProduct2) ? (string)($__volSourceProduct2['category'] ?? '') : '';
            $dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, $text, 'volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory2, $user);
            if (empty($dv['ok'])) {
                sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
                return;
            }
            if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteServerAmountMatches($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume', (string) $nameloc['id_invoice']), $__baseVol) || !\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume', (string) $nameloc['id_invoice']), $__baseVol, $marzban_list_get, 'extra_volume')) {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $__discounted = (int)round(\Faoxima\Orders\Discounts\DiscountRules::applyToPrice($dv['row'], $__baseVol));
            if ($__discounted < 0) $__discounted = 0;
            $__discounted = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($__discounted, $marzban_list_get, 'extra_volume');
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "disv_" . $text . "_" . $__baseVol . "_" . $__discounted, "id", $from_id);
            $__basefmt = number_format($__baseVol, 0);
            $__disfmt  = number_format($__discounted, 0);
            $keyboardsetting = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextradiscount-' . $__baseVol],
                    ]
                ]
            ]);
            $textextra = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_traffic_discount_invoice', ['label' => $dv['label'], 'before' => $__basefmt, 'after' => $__disfmt]);
            sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'traffic_confirm':
            $volume = $dataget[2];
            $__volDiscountCode = '';
            $__volCharge = (float)$volume;
            if (($dataget[1] ?? '') === 'discount') {
                $__pv4 = (string)$user['Processing_value_four'];
                if (strpos($__pv4, 'disv_') === 0) {
                    $__pp = explode('_', $__pv4);
                    $__volDiscountCode = $__pp[1] ?? '';
                    if (isset($__pp[3])) $__volCharge = (float)$__pp[3];
                }
            }
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
            if (!is_array($nameloc) || (string) ($nameloc['id_user'] ?? '') !== (string) $from_id
                || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
                sendmessage($from_id, $datatextbot['dyn_errors_purchase_failed_restart'] ?? "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
                return;
            }
            $operationId = 'tgextra_' . substr(hash('sha256', $from_id . ':' . $message_id . ':volume'), 0, 48);
            if (\Faoxima\Services\Fulfillment\WalletExtensions::blockingOperation(
                $pdo, (string) $from_id, $nameloc['username'], 'extra_user', (string) $nameloc['id_invoice'], $operationId
            ) !== null) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_request_already_registered'), null, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            $fxExtraVerify = \Faoxima\Orders\Pricing\ExchangePricing::fxExtraQuoteVerify($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume', (string) $nameloc['id_invoice']), $extrapricevalue, $volume, $marzban_list_get, 'extra_volume');
            if ($fxExtraVerify['status'] === 'missing') {
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
                return;
            }
            if ($fxExtraVerify['status'] === 'changed') {
                sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxExtraVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $fxExtraVerify['amount']]]]]), 'HTML');
                return;
            }
            $fxExtraQty = $fxExtraVerify['qty'];
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $__volCharge && $user['agent'] != "n2") {
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
                    $valuevolume = $fxExtraQty ?? intval($volume) / intval($extrapricevalue);
                    if (intval($user['pricediscount']) != 0) {
                        $result = ($__volCharge * $user['pricediscount']) / 100;
                        $__volCharge = $__volCharge - $result;
                        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
                    }
                    $__volCharge = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($__volCharge, $marzban_list_get, 'extra_volume');
                    \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_begin($user, [
                        'operation' => 'getextravolumeuser',
                        'op_key' => "getextravolumeuser|{$nameloc['username']}%{$valuevolume}",
                        'target' => (string) $nameloc['username'],
                        'final_price' => $__volCharge,
                        'message_id' => $message_id,
                        'one' => "{$nameloc['username']}%{$valuevolume}",
                        'tow' => 'getextravolumeuser',
                        'four' => $__volDiscountCode !== '' ? "dvol|" . $__volDiscountCode . "|" . $volume : '',
                    ]);
                    return;
                }
            }
            deletemessage($from_id, $message_id);
            $volumepricelast = $__volCharge;
            if (intval($user['pricediscount']) != 0) {
                $result = ($__volCharge * $user['pricediscount']) / 100;
                $volumepricelast = $__volCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $volumepricelast = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($volumepricelast, $marzban_list_get, 'extra_volume');
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $volumepricelast) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $data_for_database = json_encode(array(
                'volume_value' => $fxExtraQty ?? intval($volume) / intval($extrapricevalue),
                'priceـper_gig' => $extrapricevalue,
                'old_volume' => $DataUserOut['data_limit'],
                'expire_old' => $DataUserOut['expire']
            ));
            $data_for_database = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($data_for_database, \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($marzban_list_get, 'extra_volume', ($fxExtraQty ?? 0) * (\Faoxima\Orders\Pricing\ExchangePricing::fxPanelRawUnitPrice($marzban_list_get, 'priceextravolume', $user['agent']) ?? 0), $volumepricelast, (float) $volume - (float) $volumepricelast, ['quantity' => $fxExtraQty, 'unit_price' => $extrapricevalue]));
            $data_limit = $fxExtraQty ?? intval($volume) / intval($extrapricevalue);
            $before = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut, (string) ($marzban_list_get['type'] ?? ''));
            $order = [
                'operation_id' => $operationId,
                'invoice_id' => (string) $nameloc['id_invoice'],
                'ledger_invoice' => (string) $nameloc['id_invoice'],
                'panel' => (string) $marzban_list_get['name_panel'],
                'kind' => 'volume',
                'amount' => $data_limit,
                'discount_code' => (string) $__volDiscountCode,
                'before' => empty($before['exists']) ? null : \Faoxima\Services\Fulfillment\ProviderState::limits($before),
                'target' => empty($before['ok']) || empty($before['exists']) ? null : \Faoxima\Services\Fulfillment\Adjustments::extraVolumeTarget($before, $data_limit),
                'wallet_state' => 'charged',
            ];
            $reservation = \Faoxima\Services\Fulfillment\WalletExtensions::reserve(
                $pdo, ['id' => $from_id] + $user, (string) $nameloc['username'], $order, 'extra_user',
                (float) $volumepricelast, (string) $user['agent'], 'خرید حجم اضافه'
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
                $pdo, $reservation['id'], (string) $from_id, $order, $ManagePanel, $marzban_list_get, (string) $nameloc['username'], $data_limit
            );
            $extra_volume = is_array($execution['result']) ? $execution['result'] : ['status' => false];
            $order = $execution['order'];
            if ($execution['outcome'] !== 'applied') {
                if ($execution['outcome'] === 'refundable') {
                    \Faoxima\Services\Fulfillment\WalletExtensions::refund(
                        $pdo, $reservation['id'], ['id' => $from_id], $order, (float) $volumepricelast, $extra_volume, 'بازگشت وجه خرید حجم اضافه'
                    );
                }
                $extra_volume['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
                $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
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
                $pdo, $reservation['id'], (string) $from_id, $order, (array) json_decode($data_for_database, true), $extra_volume
            )) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'HTML');
                return;
            }
            \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
                'id_user' => $from_id,
                'id_invoice' => $nameloc['id_invoice'] ?? null,
                'Service_location' => $nameloc['Service_location'],
                'kind' => 'volume',
                'name_product' => $nameloc['name_product'] ?? null,
                'amount' => $volumepricelast,
                'source' => 'bot',
            ]);
            if ($__volDiscountCode !== '') {
                \Faoxima\Orders\Discounts\DiscountRules::logOrderDiscount($pdo, [
                    'id_user' => $from_id,
                    'id_invoice' => $nameloc['id_invoice'] ?? null,
                    'code' => $__volDiscountCode,
                    'kind' => 'sell',
                    'price_before' => $volume,
                    'discount_amount' => (float)$volume - (float)$volumepricelast,
                    'price_after' => $volumepricelast,
                    'section' => 'volume',
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
            $volumesformat = number_format($volumepricelast, 0);
            $volumes = $fxExtraQty ?? $volume / $extrapricevalue;
            $textvolume = \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_traffic_success', ['username' => $nameloc['username'], 'amount' => $volumes, 'total' => $volumesformat]);
            sendmessage($from_id, $textvolume, $keyboardextrafnished, 'HTML');
            $text_report = "⭕️ یک کاربر حجم اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 حجم خریداری شده  : $volumes گیگ</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>
<blockquote>موجودی کاربر قبل خرید : " . \Faoxima\Core\Support\Formatting::rxFormatToman($user['Balance']) . "</blockquote>
";
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
