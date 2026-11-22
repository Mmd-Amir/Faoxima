<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $_rx_nav_styles, $connect, $from_id, $message_id, $pdo, $request_contact, $setting, $statusnote, $telegramRoute, $text, $textbotlang, $update.
 * Inherited or exported by reference: $back, $backuser, $datain, $datakeyboard, $datatextbot, $list_marzban_panel_user, $location, $marzban_list_get, $query, $queryParams, $statuscustom, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PurchaseStart
{
    public static function handle(
        $_rx_nav_styles,
        &$back,
        &$backuser,
        $connect,
        &$datain,
        &$datakeyboard,
        &$datatextbot,
        $from_id,
        &$list_marzban_panel_user,
        &$location,
        &$marzban_list_get,
        $message_id,
        $pdo,
        &$query,
        &$queryParams,
        $request_contact,
        $setting,
        &$statuscustom,
        $statusnote,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        &$user
    ) {
        switch ($telegramRoute) {
        case 'purchase_note':
            if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user)) {
                sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('get_number', $from_id);
            }
            if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user))
                return;
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_sell")) {
                sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
                return;
            }
            if ($datain != "buyback") {
                \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('buy', $from_id, $datain, $message_id, ['buy'], ['buy']);
            }
            if ($datain == "buy") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['notestep'], $backuser);
            } elseif ($datain == "buyback") {
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
            } else {
                sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step("statusnamecustom", $from_id);
            return;
        case 'purchase_start':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_sell")) {
                sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
                return;
            }
            $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active' ORDER BY (position = 0) ASC, position ASC, id ASC");
            if (mysqli_num_rows($locationproduct) == 0) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
            if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user)) {
                sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('get_number', $from_id);
            }
            if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !\Faoxima\Telegram\Support\Replies::rx_auth_skip_user($user))
                return;
            if ($user['step'] != "statusnamecustom" && $datain != "buybacktow" && $datain != "buyback") {
                \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('buy', $from_id, $datain, $message_id, ['buy'], ['buy']);
            }

            if (!isset($datatextbot['textselectlocation']) || trim((string)$datatextbot['textselectlocation']) === '') {
                $datatextbot['textselectlocation'] = '📍 لطفاً لوکیشن (سرور) مورد نظر را انتخاب کنید:';
            }
            if (mysqli_num_rows($locationproduct) == 1) {
                $singlePanelRow = mysqli_fetch_assoc($locationproduct);
                if (is_callable([\Faoxima\Services\Fulfillment\Inventory::class, 'nmAnyNationalNetEnabled']) && \Faoxima\Services\Fulfillment\Inventory::nmAnyNationalNetEnabled($pdo)) {
                    $_rx_buy_isCb = ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback");
                    \Faoxima\Telegram\Media\SectionMedia::rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, $_rx_buy_isCb);
                    return;
                }
                $location = $singlePanelRow['name_panel'];
                $locationproduct = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
                if ($locationproduct['hide_user'] != null) {
                    $list_user = json_decode($locationproduct['hide_user'], true);
                    if (in_array($from_id, $list_user)) {
                        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                        return;
                    }
                }
                if (\Faoxima\Services\Panels\PanelLimits::panel_creation_limit_reached($locationproduct)) {
                    sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanelfirst'], null, 'HTML');
                    return;
                }
                if ($user['step'] == "statusnamecustom") {
                    if (!isset($update['message']) && empty($text)) { return; }
                    \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'clear', "nameconfig", $text);
                    \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'save', "name_panel", $location);
                    \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                } else {
                    \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'clear', "name_panel", $location);
                }
                if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($location, 'categorytime')) {
                    $marzban_list_get = $locationproduct;
                    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
                    $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
                    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
                    $mainvolume = $mainvolume[$user['agent']];
                    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
                    $maxvolume = $maxvolume[$user['agent']];
                    $productCountParams = [
                        ':location' => $location,
                        ':agent' => $user['agent']
                    ];
                    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                    $productCountStmt->execute($productCountParams);
                    $nullproduct = (int)$productCountStmt->fetchColumn();
                    if ($nullproduct == 0) {
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
                    if (\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($location, 'categorygeneral') && (!is_callable([\Faoxima\Orders\Catalog\Categories::class, 'nmHasSellableCategories']) || \Faoxima\Orders\Catalog\Categories::nmHasSellableCategories($pdo, $location, $user['agent']))) {
                        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
                        if ($setting['statusnamecustom'] == 'onnamecustom') {
                            $backuser = "buyback";
                        } else {
                            $backuser = "backuser";
                        }
                        if ($datain == "buy") {
                            Editmessagetext($from_id, $message_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_select_category'), \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardCategory($location, $user['agent'], $backuser));
                        } else {
                            sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_select_category'), \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardCategory($location, $user['agent'], $backuser), 'HTML');
                        }
                    } else {
                        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                        $queryParams = [
                            ':location' => $location,
                            ':agent' => $user['agent']
                        ];
                        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
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
                        if ($datain == "buy") {
                            Editmessagetext($from_id, $message_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
                        } else {
                            sendmessage($from_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true), 'HTML');
                        }
                    }
                } else {
                    $productCountParams = [
                        ':location' => $location,
                        ':agent' => $user['agent']
                    ];
                    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                    $productCountStmt->execute($productCountParams);
                    $nullproduct = (int)$productCountStmt->fetchColumn();
                    if ($nullproduct == 0) {
                        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                        return;
                    }
                    $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
                    $statuscustom = false;
                    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
                        $statuscustom = true;
                    if ($statusnote) {
                        $back = "buyback";
                    } else {
                        $back = "backuser";
                    }
                    $monthkeyboard = \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", $back, $statuscustom, false);
                    if ($datain == "buy" || $datain == "buybacktow") {
                        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
                    } else {
                        sendmessage($from_id, $textbotlang['Admin']['month']['title'], $monthkeyboard, 'HTML');
                    }
                }
                return;
            }
            if ($user['step'] == "statusnamecustom") {
                if (!isset($update['message']) && empty($text)) { return; }
                \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, 'clear', "nameconfig", $text);
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            }
            $rx_loc_kb = ['inline_keyboard' => []];
            $rx_loc_q = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' ORDER BY (position = 0) ASC, position ASC, id ASC");
            $rx_loc_q->execute();
            while ($rx_p = $rx_loc_q->fetch(PDO::FETCH_ASSOC)) {
                $rx_hide = json_decode((string)($rx_p['hide_user'] ?? ''), true);
                if (is_array($rx_hide) && in_array($from_id, $rx_hide)) continue;
                if (($rx_p['type'] ?? '') == "Manualsale") {
                    $rx_ms = $pdo->prepare("SELECT 1 FROM manualsell WHERE codepanel = :c AND status = 'active'");
                    $rx_ms->execute([':c' => $rx_p['code_panel']]);
                    if ($rx_ms->rowCount() == 0) continue;
                }
                $rx_loc_kb['inline_keyboard'][] = [['text' => (string)$rx_p['name_panel'], 'callback_data' => "location_{$rx_p['code_panel']}"]];
            }
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $_rx_loc_back_key = $statusnote ? "buyback" : "backuser";
            $rx_loc_kb['inline_keyboard'][] = [['text' => $textbotlang['users']['backbtn'], 'callback_data' => $_rx_loc_back_key, '_rxsk' => 'panel_back']];
            $list_marzban_panel_user = json_encode($rx_loc_kb, JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Media\SectionMedia::rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback"));
            break;
        }

        return true;
    }
}
