<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $backuser, $callback_query_id, $codeproduct, $dataget, $datain, $datatextbot, $from_id, $keyboard, $message_id, $namekeyboard, $pdo, $pricelastextend, $telegramRoute, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $id_invoice, $info_product, $marzban_list_get, $nameloc, $product, $result, $stmt, $textextend, $userdate.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalSelection
{
    public static function handle(
        $ManagePanel,
        $backuser,
        $callback_query_id,
        $codeproduct,
        $dataget,
        $datain,
        $datatextbot,
        $from_id,
        &$id_invoice,
        &$info_product,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        $namekeyboard,
        &$nameloc,
        $pdo,
        $pricelastextend,
        &$product,
        &$result,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        &$textextend,
        $update,
        $user,
        &$userdate
    ) {
        switch ($telegramRoute) {
        case 'renewal_start':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extend on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'extend',
                    ]);
                }
                return;
            }
            if ($nameloc == false) {
                sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
                return;
            }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmMaybeShowStockInvoiceDetails') && \Faoxima\Services\Fulfillment\StockPresentation::nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { \Faoxima\Telegram\Navigation\Steps::step('home', $from_id); return; }
            if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStopIfServicePanelBlocked') && \Faoxima\Services\Fulfillment\StockPresentation::nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($marzban_list_get['status_extend'] == "off_extend") {
                sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
                return;
            }

            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful") {
                $offlineKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_buy_new'), 'callback_data' => 'buy_service']],
                    [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
                ]]);
                sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
                return;
            }
            if ($DataUserOut['status'] == "on_hold") {
                sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $stmt->execute([
                ':service_location' => $marzban_list_get['name_panel'],
                ':agent' => $user['agent'],
            ]);
            $product = $stmt->rowCount();
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "id_invoice", $nameloc['id_invoice']);
            if ($product == 0) {
                $textcustom = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_prompt', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($custompricevalue), 'min' => $mainvolume, 'max' => $maxvolume]);
                sendmessage($from_id, $textcustom, $backuser, 'html');
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Navigation\Steps::step('gettimecustomvolomforextend', $from_id);
                return;
            }
            if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
                $textcustom = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_prompt', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($custompricevalue), 'min' => $mainvolume, 'max' => $maxvolume]);
                sendmessage($from_id, $textcustom, $backuser, 'html');
                deletemessage($from_id, $message_id);
                \Faoxima\Telegram\Navigation\Steps::step('gettimecustomvolomforextend', $from_id);
                return;
            }
            if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'categorytime')) {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':service_location' => $nameloc['Service_location'],
                    ':agent' => $user['agent'],
                ]);
                $productextend = ['inline_keyboard' => []];
                $statusshowprice = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'showprice') ? "onshowprice" : "offshowprice";
                while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $hide_panel = json_decode($result['hide_panel'], true);
                    if (is_array($hide_panel) && in_array($nameloc['Service_location'], $hide_panel)) {
                        error_log("Product {$result['code_product']} is marked hidden for {$nameloc['Service_location']} but was kept visible for extend.");
                    }
                    $result = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($result, $marzban_list_get);
                    if (intval($user['pricediscount']) != 0) {
                        $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                        $result['price_product'] = $result['price_product'] - $resultper;
                    }
                    $result['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($result['price_product'], $marzban_list_get, 'product');
                    if ($statusshowprice == "offshowprice") {
                        $namekeyboard = $result['name_product'];
                    } else {
                        $result['price_product'] = number_format($result['price_product']);
                        $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
                    }
                    $productextend['inline_keyboard'][] = [
                        ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
                    ];
                }
                $productextend['inline_keyboard'][] = [
                    ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_renew_current_plan'), 'callback_data' => "exntedagei"]
                ];
                $productextend['inline_keyboard'][] = [
                    ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_back_to_info'), 'callback_data' => "product_" . $nameloc['id_invoice']]
                ];

                $json_list_product_lists = json_encode($productextend);
                $textselectservice = method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_render_text')
                    ? \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
                    : $textbotlang['users']['extend']['selectservice'];
                Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
            } else {
                $monthkeyboard = \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboardTimeCategory($nameloc['Service_location'], $user['agent'], "productextendmonths_", "product_$id_invoice", false, true);
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
            }
            break;
        case 'renewal_duration':
            $userdate = json_decode($user['Processing_value'], true);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $maintime = json_decode($marzban_list_get['maintime'], true);
            $maintime = $maintime[$user['agent']];
            $maxtime = json_decode($marzban_list_get['maxtime'], true);
            $maxtime = $maxtime[$user['agent']];
            if (!ctype_digit($text)) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
                $texttime = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_invalid', ['min' => $mainvolume, 'max' => $maxvolume]);
                sendmessage($from_id, $texttime, $backuser, 'HTML');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "volume", $text);
            $textcustom = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_duration_prompt', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($customtimevalueprice), 'min' => $maintime, 'max' => $maxtime]);
            if (\Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($marzban_list_get, $user['agent'])['enabled']) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_select_duration'), \Faoxima\Orders\Pricing\CustomDuration::fx_duration_keyboard($marzban_list_get, $user['agent']), 'HTML');
            } else {
                sendmessage($from_id, $textcustom, $backuser, 'html');
            }
            \Faoxima\Telegram\Navigation\Steps::step('getvolumecustomuserforextend', $from_id);
            break;
        case 'renewal_month':
            $monthenumber = $dataget[1];
            $userdate = json_decode($user['Processing_value'], true);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND Service_time = :monthe AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $stmt->execute([
                ':service_location' => $nameloc['Service_location'],
                'monthe' => $monthenumber,
                ':agent' => $user['agent'],
            ]);
            $productextend = ['inline_keyboard' => []];
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $statusshowprice = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($marzban_list_get, 'showprice') ? "onshowprice" : "offshowprice";
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($result, $marzban_list_get);
                if (intval($user['pricediscount']) != 0) {
                    $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                    $result['price_product'] = $result['price_product'] - $resultper;
                }
                $result['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($result['price_product'], $marzban_list_get, 'product');
                if ($statusshowprice == "offshowprice") {
                    $namekeyboard = $result['name_product'];
                } else {
                    $result['price_product'] = number_format($result['price_product']);
                    $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
                }
                $productextend['inline_keyboard'][] = [
                    ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
                ];
            }
            if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
                $productextend['inline_keyboard'][] = [
                    ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_pick_current'), 'callback_data' => "serviceextendselect_pre"]
                ];
            }
            $productextend['inline_keyboard'][] = [
                ['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_back_to_info'), 'callback_data' => "product_" . $nameloc['id_invoice']]
            ];

            $json_list_product_lists = json_encode($productextend);
            $textselectservice = method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_render_text')
                ? \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
                : $textbotlang['users']['extend']['selectservice'];
            Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
            break;
        case 'renewal_product':
            $userdate = json_decode($user['Processing_value'], true);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($user['step'] == "getvolumecustomuserforextend") {
                if (!ctype_digit($text)) {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
                    return;
                }
                $maintime = json_decode($marzban_list_get['maintime'], true);
                $maintime = $maintime[$user['agent']];
                $maxtime = json_decode($marzban_list_get['maxtime'], true);
                $maxtime = $maxtime[$user['agent']];
                if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
                    $texttime = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_duration_invalid', ['min' => $maintime, 'max' => $maxtime]);
                    sendmessage($from_id, $texttime, $backuser, 'HTML');
                    return;
                }
            } elseif ($datain == "exntedagei") {
                $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
                $stmt->execute([
                    ':username' => $nameloc['username'],
                ]);
                if ($stmt->rowCount() == 0) {
                    $codeproduct = \Faoxima\Core\Database\Db::select("product", "*", "name_product", $nameloc['name_product']);
                } else {
                    $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true)))) {
                        sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
                        return;
                    }
                    $service_other = json_decode($service_other['value'], true);
                    $codeproduct = \Faoxima\Core\Database\Db::select("product", "code_product", "code_product", $service_other['code_product'], "select");
                }
                if ($codeproduct == false) {
                    sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
                    return;
                }
                $codeproduct = $codeproduct['code_product'];
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            } else {
                $codeproduct = $dataget[1];
            }
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            if ($user['step'] == "getvolumecustomuserforextend") {
                $product['name_product'] = $nameloc['name_product'];
                $product['code_product'] = "customvolume";
                $product['note'] = "";
                try {
                    $product['price_product'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price($marzban_list_get, $user['agent'], intval($userdate['volume']), $text);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_back'), 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
                $product['Service_time'] = $text;
                $product['Volume_constraint'] = $userdate['volume'];
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':service_location' => $nameloc['Service_location'],
                    ':code_product' => $codeproduct,
                    ':agent' => $user['agent'],
                ]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($product == false) {
                sendmessage($from_id, $datatextbot['dyn_errors_extend_restart_process'] ?? "❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.", $keyboard, 'HTML');
                return;
            }
            $fxRenewKinds = $product['code_product'] === "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($product['code_product'] !== "customvolume") {
                $product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($product, $marzban_list_get);
            }
            $product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($product['price_product'], $marzban_list_get, $fxRenewKinds);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "time", $product['Service_time']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "data_limit", $product['Volume_constraint']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "price_product", $product['price_product']);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "code_product", $product['code_product']);
            $__discEligibleExtend = \Faoxima\Orders\Discounts\DiscountRules::hasEligible($pdo, 'extend', (string)($product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($product['category'] ?? ''), $user);
            $__extendConfirmRow = [['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"]];
            if ($__discEligibleExtend) {
                $__extendConfirmRow[] = ['text' => $textbotlang['users']['extend']['discount'], 'callback_data' => "discountextend"];
            }
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    $__extendConfirmRow,
                    [
                        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
                    ]
                ]
            ]);
            if (intval($user['pricediscount']) != 0) {
                $result = ($product['price_product'] * $user['pricediscount']) / 100;
                $pricelastextend = number_format(round(\Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($product['price_product'] - $result, $marzban_list_get, $fxRenewKinds), 0));
            } else {
                $pricelastextend = number_format($product['price_product']);
            }
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew', (string) $nameloc['id_invoice'] . '|' . (string) $product['code_product']), \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($product['price_product'], $user['pricediscount']), $marzban_list_get, $fxRenewKinds), $marzban_list_get, $fxRenewKinds);
            $volumeextend = intval($product['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $product['Volume_constraint'] . ' گیگ';
            $rxReservationUi = \Faoxima\Services\Renewal\Reservations::rxReservationUiEnabled($marzban_list_get);
            $rxRenewPreview = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService($pdo, $ManagePanel, $marzban_list_get, (string) $nameloc['username'], $product['Volume_constraint'], $product['Service_time'], ['id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_preview']);
            if ($rxReservationUi && empty($rxRenewPreview['blocked'])) {
                $rxRenewPreview['text'] = '';
            }
            if (!empty($rxRenewPreview['blocked'])) {
                $keyboardextend = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
                        ]
                    ]
                ]);
            }
            $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_invoice_preview', ['username' => $nameloc['username'], 'product' => $product['name_product'], 'price' => $pricelastextend, 'days' => $product['Service_time'], 'volume' => $volumeextend, 'note' => $product['note'], 'balance' => \Faoxima\Core\Support\Formatting::rxFormatToman($user['Balance'])]) . "\n" . ($rxRenewPreview['text'] !== '' ? "\n" . $rxRenewPreview['text'] . "\n\n" : '') . (empty($rxRenewPreview['blocked']) ? \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_confirm_hint') : '');
            if ($product['code_product'] === 'customvolume') {
                $textextend .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $product['Volume_constraint'], $product['Service_time'], str_replace(',', '', $pricelastextend));
            }
            if (isset($update['message'])) {
                sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
            } else {
                Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
            }
            break;
        case 'renewal_reservation':
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'), 'show_alert' => true]);
            }
            break;
        case 'renewal_date':
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            sendmessage($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'), $keyboard, 'HTML');
            break;
        case 'renewal_discount_prompt':
            sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcodesellDiscountextend', $from_id);
            deletemessage($from_id, $message_id);
            break;
        case 'renewal_discount':
            if (!isset($update['message']) && empty($text)) { return; }
            $userdate = json_decode($user['Processing_value'], true);
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            if (intval($user['pricediscount']) != 0) {
                sendmessage($from_id, $datatextbot['dyn_errors_exclusive_discount_conflict'] ?? "❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.", $backuser, 'HTML');
                return;
            }
            $__extendProductRow = \Faoxima\Core\Database\Db::select("product", "*", "code_product", $userdate['code_product'] ?? '', "select");
            $__extendCategory = is_array($__extendProductRow) ? (string)($__extendProductRow['category'] ?? '') : '';
            $__dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, $text, 'extend', (string)($userdate['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), $__extendCategory, $user);
            if (empty($__dv['ok'])) {
                sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                return;
            }
            $__dvt = $__dv['value_type'];
            $__dval = (float)$__dv['value'];
            $__dlabel = $__dv['label'];
            $rxDiscountAppliedTpl = $datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.";
            sendmessage($from_id, sprintf($rxDiscountAppliedTpl, $__dlabel), $keyboard, 'HTML');
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || \Faoxima\Services\Renewal\Plans::renewalIsCustomCode($userdate['code_product'] ?? '')) {
                $info_product['code_product'] = "pre";
                $info_product['name_product'] = $nameloc['name_product'];
                try {
                    $info_product['price_product'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price($marzban_list_get, $user['agent'], $userdate['data_limit'], $userdate['time']);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_back'), 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
                $info_product['Service_time'] = $userdate['time'];
                $info_product['Volume_constraint'] = $userdate['data_limit'];
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                $stmt->bindParam(':code_product', $userdate['code_product'], PDO::PARAM_STR);
                $stmt->bindParam(':Location', $marzban_list_get['name_panel'], PDO::PARAM_STR);
                $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
                $stmt->execute();
                $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            $fxRenewCustom = ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || \Faoxima\Services\Renewal\Plans::renewalIsCustomCode($userdate['code_product'] ?? ''));
            $fxRenewKinds = $fxRenewCustom ? ['custom_volume', 'custom_time'] : 'product';
            if (!$fxRenewCustom && is_array($info_product)) {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            if (is_array($info_product) && isset($info_product['price_product'])) {
                $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxRenewKinds);
            }
            if ($__dvt === 'free') {
                $info_product['price_product'] = 0;
            } elseif ($__dvt === 'amount') {
                $info_product['price_product'] = $info_product['price_product'] - $__dval;
            } else {
                $result = ($__dval / 100) * $info_product['price_product'];
                $info_product['price_product'] = $info_product['price_product'] - $result;
            }
            $info_product['price_product'] = round($info_product['price_product']);
            $rxRenewPreview = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService($pdo, $ManagePanel, $marzban_list_get, (string) $nameloc['username'], $info_product['Volume_constraint'], $info_product['Service_time'], ['reservation' => \Faoxima\Services\Renewal\Reservations::rxReservationUiEnabled($marzban_list_get) ? \Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSelection($marzban_list_get) : null, 'id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_preview']);
            if (\Faoxima\Services\Renewal\Reservations::rxReservationUiEnabled($marzban_list_get) && empty($rxRenewPreview['blocked'])) {
                $rxRenewPreview['text'] = '';
            }
            if (intval($info_product['Service_time']) == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            if (intval($info_product['Volume_constraint']) == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            if ($info_product['price_product'] < 0)
                $info_product['price_product'] = 0;
            $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxRenewKinds);
            $rxFmtExtendInfoProductPrice = \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']);
            $rxFmtExtendUserBalance = \Faoxima\Core\Support\Formatting::rxFormatToman($user['Balance']);
            $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_invoice_preview_alt', ['username' => $nameloc['username'], 'product' => $info_product['name_product'], 'price' => $rxFmtExtendInfoProductPrice, 'days' => $info_product['Service_time'], 'volume' => $info_product['Volume_constraint'], 'note' => $info_product['note'], 'balance' => $rxFmtExtendUserBalance]) . "\n" . ($rxRenewPreview['text'] !== '' ? "\n" . $rxRenewPreview['text'] . "\n" : '') . (empty($rxRenewPreview['blocked']) ? "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_confirm_hint') : '');
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"],
                    ]
                ]
            ]);
            sendmessage($from_id, $textextend, empty($rxRenewPreview['blocked']) ? $keyboardextend : null, 'HTML');
            $parametrsendvalue = "dis_" . $text . "_" . $info_product['price_product'];
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew_discount', (string) $nameloc['id_invoice'] . '|' . (string) ($userdate['code_product'] ?? '')), $info_product['price_product'], $marzban_list_get, $fxRenewKinds);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
