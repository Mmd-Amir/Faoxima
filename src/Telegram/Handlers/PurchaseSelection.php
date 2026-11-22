<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $backuser, $callback_query_id, $dataget, $datain, $datatextbot, $from_id, $keyboard, $location, $message_id, $payment, $paymentom, $pdo, $queryParams, $statusnote, $telegramRoute, $textbotlang, $update, $username, $usernamePromptKb.
 * Inherited or exported by reference: $back, $datakeyboard, $info_product, $loc, $marzban_list_get, $prodcut, $query, $randomString, $statuscustom, $stmt, $text, $user, $userdate, $username_ac, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PurchaseSelection
{
    public static function handle(
        $ManagePanel,
        &$back,
        $backuser,
        $callback_query_id,
        $dataget,
        $datain,
        &$datakeyboard,
        $datatextbot,
        $from_id,
        &$info_product,
        $keyboard,
        &$loc,
        $location,
        &$marzban_list_get,
        $message_id,
        $payment,
        $paymentom,
        $pdo,
        &$prodcut,
        &$query,
        $queryParams,
        &$randomString,
        &$statuscustom,
        $statusnote,
        &$stmt,
        $telegramRoute,
        &$text,
        $textbotlang,
        $update,
        &$user,
        &$userdate,
        $username,
        $usernamePromptKb,
        &$username_ac,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'purchase_panel':
            $userdate = json_decode($user['Processing_value'], true);
            if ($datain != "backproduct") {
                $location = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
            } else {
                $location = $userdate['name_panel'];
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
            $locationproductcount = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "count");
            if (\Faoxima\Services\Panels\PanelLimits::panel_creation_limit_reached($marzban_list_get)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanel'], null, 'HTML');
                return;
            }
            if ($statusnote) {
                \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'save', "name_panel", $location);
            } else {
                \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'clear', "name_panel", $location);
            }
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
                $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
                $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
                $mainvolume = $mainvolume[$user['agent']];
                $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
                $maxvolume = $maxvolume[$user['agent']];
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
                if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
                    return;
                }
                $textcustom = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_prompt', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($custompricevalue), 'min' => $mainvolume, 'max' => $maxvolume]);
                sendmessage($from_id, $textcustom, $backuser, 'html');
                \Faoxima\Telegram\Navigation\Steps::step('gettimecustomvol', $from_id);
                return;
            }
            if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($location, 'categorytime')) {
                if (\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($location, 'categorygeneral') && (!is_callable([\Faoxima\Orders\Catalog\Categories::class, 'nmHasSellableCategories']) || \Faoxima\Orders\Catalog\Categories::nmHasSellableCategories($pdo, $location, $user['agent']))) {
                    $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
                    Editmessagetext($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_select_category'), \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardCategory($location, $user['agent'], "buybacktow"));
                } else {
                    $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                    $queryParams = [
                        ':location' => $location,
                        ':agent' => $user['agent']
                    ];
                    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                    if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                        $datakeyboard = "prodcutservices_";
                    } else {
                        $datakeyboard = "prodcutservice_";
                    }
                    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                        $statuscustom = true;
                    } else {
                        $statuscustom = false;
                    }
                    if (isset($userdate['nameconfig'])) {
                        $back = "buybacktow";
                    } else {
                        $back = "buyback";
                    }
                    $textproduct = \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
                        'panel' => $marzban_list_get['name_panel'],
                    ]);
                    Editmessagetext($from_id, $message_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, "customsellvolume", $user['agent'], $queryParams, true));
                }
            } else {
                $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $productCountStmt->execute($productCountParams);
                $nullproduct = (int)$productCountStmt->fetchColumn();
                if ($nullproduct == 0) {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                    return;
                }
                $statuscustom = false;
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
                    $statuscustom = true;
                $monthkeyboard = \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", "buybacktow", $statuscustom, false);
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
            }
            break;
        case 'purchase_category':
            $categorynames = $dataget[1];
            $categoryId = $categorynames;
            $categoryRow = \Faoxima\Core\Database\Db::select("category", "*", "id", $categoryId, "select");
            $categorynames = is_array($categoryRow) && isset($categoryRow['remark']) ? $categoryRow['remark'] : $categoryId;
            $userdate = json_decode($user['Processing_value'], true);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");


            $catValues = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmCategoryLookupValues']) ? \Faoxima\Orders\Catalog\Products::nmCategoryLookupValues($categorynames) : [];
            foreach ([$categorynames, $categoryId] as $extra) { if (trim((string)$extra) !== '') $catValues[] = (string)$extra; }
            $catValues = array_values(array_unique(array_filter(array_map('strval', $catValues), static function ($v) { return trim($v) !== ''; })));
            if (!$catValues) $catValues = [(string)$categorynames];
            [$catClause, $catParams] = \Faoxima\Orders\Catalog\NameCascades::nmBuildFindInSetClause('category', $catValues, 'catv');
            if ($catClause === '') $catClause = '1=0';
            if (isset($userdate['monthproduct'])) {
                $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $queryParams = array_merge([
                    ':location' => $userdate['name_panel'],
                    ':service_time' => $userdate['monthproduct'],
                    ':agent' => $user['agent']
                ], $catParams);
            } else {
                $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $queryParams = array_merge([
                    ':location' => $userdate['name_panel'],
                    ':agent' => $user['agent']
                ], $catParams);
            }
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                $datakeyboard = "prodcutservices_";
            } else {
                $datakeyboard = "prodcutservice_";
            }
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                $statuscustom = true;
            } else {
                $statuscustom = false;
            }
            $textproduct = \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['sell']['Service-select-first'], [
                'category' => $categorynames,
                'panel' => $marzban_list_get['name_panel'],
            ]);
            Editmessagetext($from_id, $message_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
            break;
        case 'purchase_month':
            $monthenumber = $dataget[1];
            $userdate = json_decode($user['Processing_value'], true);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            if (\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($userdate['name_panel'], 'categorygeneral') && (!is_callable([\Faoxima\Orders\Catalog\Categories::class, 'nmHasSellableCategories']) || \Faoxima\Orders\Catalog\Categories::nmHasSellableCategories($pdo, $userdate['name_panel'], $user['agent']))) {
                \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "monthproduct", $monthenumber);
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
                $stmt = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active'");
                $stmt->execute();
                $count_panel = $stmt->rowCount();
                if ($count_panel == 1) {
                    $back = "buybacktow";
                } else {
                    $back = "location_{$marzban_list_get['code_panel']}";
                }
                Editmessagetext($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_select_category'), \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], $back));
            } else {
                $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $queryParams = [
                    ':location' => $userdate['name_panel'],
                    ':service_time' => $monthenumber,
                    ':agent' => $user['agent']
                ];
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                    $statuscustom = true;
                } else {
                    $statuscustom = false;
                }
                $textproduct = \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['sell']['Service-select-first-no-category'], [
                    'panel' => $marzban_list_get['name_panel'],
                ]);
                Editmessagetext($from_id, $message_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
            }
            break;
        case 'purchase_custom':
            $userdate = json_decode($user['Processing_value'], true);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $textcustom = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_prompt', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($custompricevalue), 'min' => $mainvolume, 'max' => $maxvolume]);
            sendmessage($from_id, $textcustom, $backuser, 'html');
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Navigation\Steps::step('gettimecustomvol', $from_id);
            break;
        case 'purchase_duration':
            if (!isset($update['message']) && empty($text)) { return; }
            $userdate = json_decode($user['Processing_value'], true);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $maintime = json_decode($marzban_list_get['maintime'], true);
            $maintime = $maintime[$user['agent']];
            $maxtime = json_decode($marzban_list_get['maxtime'], true);
            $maxtime = $maxtime[$user['agent']];
            if (!ctype_digit((string) $text)) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            if ((int) $text > (int) $maxvolume || (int) $text < (int) $mainvolume) {
                $texttime = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_volume_invalid', ['min' => $mainvolume, 'max' => $maxvolume]);
                sendmessage($from_id, $texttime, $backuser, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPurchaseDurationPrompt($from_id, $marzban_list_get, $user['agent'], $customtimevalueprice, $maintime, $maxtime, $backuser);
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                \Faoxima\Telegram\Navigation\Steps::step('getvolumecustomusername', $from_id);
            } else {
                \Faoxima\Telegram\Navigation\Steps::step('getvolumecustomuser', $from_id);
            }
            break;
        case 'purchase_username':
            $prodcut = $dataget[1];
            $userdate = json_decode($user['Processing_value'], true);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            if ($user['step'] == "getvolumecustomusername") {
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
                try {
                    \Faoxima\Orders\Pricing\CustomDuration::fx_custom_resolve($marzban_list_get, $user['agent'], $user['Processing_value_one'], $text);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), \Faoxima\Orders\Pricing\CustomDuration::fx_duration_keyboard($marzban_list_get, $user['agent']), 'HTML');
                    return;
                }
                $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
                \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $customvalue, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('endstepusers', $from_id);
            } else {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $prodcut, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('endstepuser', $from_id);
                deletemessage($from_id, $message_id);
            }
            $_rx_selectusername_kb = (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseUsernameUsesRandomSuffix($marzban_list_get['MethodUsername'])) ? $usernamePromptKb : $backuser;
            sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
            break;
        case 'purchase_product':
            $userdate = json_decode($user['Processing_value'], true);
            if (!is_array($userdate) || empty($userdate['name_panel'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($user['step'] == "getvolumecustomuser") {
                if (!ctype_digit($text)) {
                    sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
                    return;
                }
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
                $maintime = json_decode($marzban_list_get['maintime'], true);
                $maintime = $maintime[$user['agent']];
                $maxtime = json_decode($marzban_list_get['maxtime'], true);
                $maxtime = $maxtime[$user['agent']];
                if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
                    $texttime = \Faoxima\Telegram\Support\MessageTemplates::get('service_custom_duration_invalid', ['min' => $maintime, 'max' => $maxtime]);
                    sendmessage($from_id, $texttime, $backuser, 'HTML');
                    return;
                }
                try {
                    \Faoxima\Orders\Pricing\CustomDuration::fx_custom_resolve($marzban_list_get, $user['agent'], $user['Processing_value_one'], $text);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), \Faoxima\Orders\Pricing\CustomDuration::fx_duration_keyboard($marzban_list_get, $user['agent']), 'HTML');
                    return;
                }
                $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
            } elseif ($user['step'] == "endstepusers" || $user['step'] == "endstepuser") {
                $prodcut = $user['Processing_value_one'];
            } else {
                $prodcut = $dataget[1];
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            if (!is_array($marzban_list_get)) {
                sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($marzban_list_get['status'] == "disable") {
                sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                $_rx_usernameKb = (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseUsernameUsesRandomSuffix($marzban_list_get['MethodUsername'])) ? $usernamePromptKb : $backuser;
                if ($datain === 'gen_random_uname') {
                    $text = \Faoxima\Services\Delivery\ServiceDelivery::rx_build_smart_random_username($username ?? '', $from_id);
                    if ($callback_query_id && function_exists('telegram')) {
                        @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
                    }
                }
                if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
                    sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
                    return;
                }
                $loc = $user['Processing_value_one'];
            } else {
                $loc = $prodcut;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $loc, "id", $from_id);
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            $parts = explode("_", $loc);
            if ($parts[0] == "customvolume") {
                $info_product['Volume_constraint'] = $parts[2];
                $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
                $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
                $info_product['Service_time'] = $parts[1];
                try {
                    $info_product['price_product'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price($marzban_list_get, $user['agent'], $parts[2], $parts[1]);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_back'), 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
            } else {
                if (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                    $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($loc, $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
                } elseif (method_exists(\Faoxima\Orders\Catalog\CatalogLookups::class, 'nmProductByCodeForPanel')) {
                    $info_product = \Faoxima\Orders\Catalog\Products::nmProductByCodeForPanel($pdo, $loc, $userdate['name_panel'], $user['agent']);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                    $stmt->execute([
                        ':code_product' => $loc,
                        ':location' => $userdate['name_panel'],
                        ':agent' => $user['agent']
                    ]);
                    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            if (!is_array($info_product) || !isset($info_product['price_product'])) {
                error_log('Purchase preview failed: product not found for code=' . $loc . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($parts[0] != "customvolume") {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            if (intval($user['pricediscount']) != 0) {
                $resultper = ($info_product['price_product'] * $user['pricediscount']) / 100;
                $info_product['price_product'] = $info_product['price_product'] - $resultper;
            }
            $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('buy', (string) $loc), $info_product['price_product'], $marzban_list_get, $fxBuyKinds);
            $randomString = bin2hex(random_bytes(2));
            $text = strtolower($text);
            $username_ac = \Faoxima\Services\Delivery\ServiceDelivery::generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
            $username_ac = strtolower($username_ac);
            $requestedUsername_ac = $username_ac;
            $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
            $random_number = rand(1000000, 9999999);
            $usernameWasRenamed = isset($DataUserOut['username']) || \Faoxima\Core\Database\Db::rxTableValueExists('invoice', 'username', $username_ac);
            if ($usernameWasRenamed) {
                $username_ac = $random_number . "-" . $username_ac;
            }
            if (isset($username_ac))
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $username_ac, "id", $from_id);
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' ? (is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] === 0.0) : intval($info_product['Volume_constraint']) == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            if (intval($info_product['Service_time']) == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            $info_product_price_product = number_format($info_product['price_product']);
            $userBalance = number_format($user['Balance']);
            $replacements = [
                '{username}' => \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get),
                '{name_product}' => $info_product['name_product'],
                '{Service_time}' => $info_product['Service_time'],

                '{note}' => $info_product['note'] ?? '',
                '{price}' => $info_product_price_product,
                '{Volume}' => $info_product['Volume_constraint'],
                '{userBalance}' => $userBalance
            ];
            $invoiceTemplate = (string) $datatextbot['text_pishinvoice'];
            $customPriceSummary = ($parts[0] ?? '') === 'customvolume'
                ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $info_product['price_product']) : '';
            if (preg_match('~<!--\s*rxrtl:[01]\s*-->|<table\b~i', $invoiceTemplate)) {
                foreach ($replacements as $placeholder => $value) {
                    $replacements[$placeholder] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
            }
            $usesPriceSummary = strpos($invoiceTemplate, '{custom_price_summary}') !== false;
            $replacements['{custom_price_summary}'] = method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_plainText')
                ? htmlspecialchars(\Faoxima\Telegram\Rich\Markup::rx_richUi_plainText($customPriceSummary, 'HTML'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : htmlspecialchars(strip_tags($customPriceSummary), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $textin = strtr($invoiceTemplate, $replacements);
            if (!$usesPriceSummary) {
                $textin .= $customPriceSummary;
            }
            if ($usernameWasRenamed) {
                $textin = "⚠️ نام کاربری «" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($requestedUsername_ac, $marzban_list_get) . "» قبلاً استفاده شده بود؛ نام کاربری «" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "» برای شما در نظر گرفته شد.\n\n" . $textin;
            }
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' ? $info_product['Volume_constraint'] === $textbotlang['users']['stateus']['Unlimited'] : intval($info_product['Volume_constraint']) == 0) {
                $textin = str_replace('گیگ', "", $textin);
            }
            $previewSymbolicLimitText = \Faoxima\Telegram\Support\Texts::faoxima_symbolic_limit_label($info_product);
            if ($previewSymbolicLimitText !== null) {
                $textin .= "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('service_preview_line_user_count', [], ['count' => $previewSymbolicLimitText]);
            } elseif (($marzban_list_get['type'] ?? '') == 'x-ui_single' && (($marzban_list_get['ip_limit_guard'] ?? '') === 'onipguard')) {
                $previewIpLimit = intval($info_product['ip_limit'] ?? 0);
                $previewIpLimitText = $previewIpLimit > 0 ? \Faoxima\Telegram\Support\MessageTemplates::get('service_info_user_count', ['count' => $previewIpLimit]) : \Faoxima\Telegram\Support\MessageTemplates::plain('service_unlimited');
                $textin .= "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('service_preview_line_user_count', [], ['count' => $previewIpLimitText]);
            }
            if (in_array($marzban_list_get['type'] ?? '', ['pasarguard', 'remnawave', 'x-ui_single'], true)) {
                $previewHwidLimit = intval($info_product['hwid_limit'] ?? 0);
                if ($previewHwidLimit > 0) {
                    $textin .= "\n" . \Faoxima\Telegram\Support\MessageTemplates::get('service_preview_line_device_limit', ['count' => $previewHwidLimit]);
                }
            }
            $__discEligibleBuy = \Faoxima\Orders\Discounts\DiscountRules::hasEligible($pdo, 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
            $__paymentKeyboard = $__discEligibleBuy ? $payment : $paymentom;
            if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], ["نام کاربری دلخواه", "نام کاربری دلخواه + عدد رندوم", "متن دلخواه کاربر + رندوم"])) {
                Editmessagetext($from_id, $message_id, $textin, $__paymentKeyboard);
            } else {
                sendmessage($from_id, $textin, $__paymentKeyboard, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('payment', $from_id);
            break;
        }

        return true;
    }
}
