<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;

/**
 * Handler stage. Inherited by value: $backuser, $callback_query_id, $connect, $dataget, $datain, $datatextbot, $from_id, $keyboard, $list_marzban_panel_userom, $message_id, $paymentom, $pdo, $request_contact, $setting, $telegramRoute, $textbotlang, $update, $user, $username, $usernamePromptKb.
 * Inherited or exported by reference: $datakeyboard, $info_product, $loc, $location, $marzban_list_get, $prodcut, $query, $randomString, $statuscustom, $stmt, $text, $username_ac.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BulkPurchaseSelection
{
    public static function handle(
        $backuser,
        $callback_query_id,
        $connect,
        $dataget,
        $datain,
        &$datakeyboard,
        $datatextbot,
        $from_id,
        &$info_product,
        $keyboard,
        $list_marzban_panel_userom,
        &$loc,
        &$location,
        &$marzban_list_get,
        $message_id,
        $paymentom,
        $pdo,
        &$prodcut,
        &$query,
        &$randomString,
        $request_contact,
        $setting,
        &$statuscustom,
        &$stmt,
        $telegramRoute,
        &$text,
        $textbotlang,
        $update,
        $user,
        $username,
        $usernamePromptKb,
        &$username_ac
    ) {
        switch ($telegramRoute) {
        case 'bulk_start':
            if ($setting['bulkbuy'] == "offbulk") {
                sendmessage($from_id, $datatextbot['dyn_errors_section_disabled'] ?? "❌ این بخش در حال غیرفعال می باشد", null, 'HTML');
                return;
            }
            $PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shopSetting WHERE Namevalue = 'minbalancebuybulk'"))['value'];
            if ($user['Balance'] < $PaySetting) {
                $rxMinBulkTpl = $datatextbot['dyn_errors_min_bulk_purchase_balance'] ?? "❌ برای خرید انبوه باید حداقل %s تومان موجودی داشته باشید.";
                sendmessage($from_id, sprintf($rxMinBulkTpl, $PaySetting), null, 'HTML');
                return;
            }
            $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel ORDER BY (position = 0) ASC, position ASC, id ASC");
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

            if ($datain == "kharidanbuh") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
            } else {
                sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('getcountconfig', $from_id);
            break;
        case 'bulk_count':
            if (!isset($update['message']) && empty($text)) { return; }
            if (intval($text) > 15 || intval($text) < 1)
                return sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backuser, 'HTML');
            if (!is_numeric($text))
                return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
            sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_userom, 'HTML');
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'bulk_panel':
            $location = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[1], "select");
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
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $location, "id", $from_id);
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                $datakeyboard = "prodcutservicesom_";
            } else {
                $datakeyboard = "prodcutserviceom_";
            }
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                $statuscustom = true;
            } else {
                $statuscustom = false;
            }
            $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $textproduct = \Faoxima\Telegram\Support\Texts::faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
                'panel' => $marzban_list_get['name_panel'],
            ]);
            Editmessagetext($from_id, $message_id, $textproduct, \Faoxima\Telegram\Keyboard\LegacyFunctionsHelpers::KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolumeom", $user['agent'], $productCountParams, true));
            break;
        case 'bulk_custom':
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $textcustom = "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :
📌 تعرفه هر گیگ :  " . \Faoxima\Core\Support\Formatting::rxFormatToman($custompricevalue) . "
🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.";
            sendmessage($from_id, $textcustom, $backuser, 'html');
            deletemessage($from_id, $message_id);
            \Faoxima\Telegram\Navigation\Steps::step('gettimecustomvolom', $from_id);
            break;
        case 'bulk_duration':
            if (!isset($update['message']) && empty($text)) { return; }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $maintime = json_decode($marzban_list_get['maintime'], true);
            $maintime = $maintime[$user['agent']];
            $maxtime = json_decode($marzban_list_get['maxtime'], true);
            $maxtime = $maxtime[$user['agent']];
            if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
                $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
                sendmessage($from_id, $texttime, $backuser, 'HTML');
                return;
            }
            if (!ctype_digit($text)) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $text, "id", $from_id);
            \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPurchaseDurationPrompt($from_id, $marzban_list_get, $user['agent'], $customtimevalueprice, $maintime, $maxtime, $backuser);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseNeedsUsername($marzban_list_get['MethodUsername'], $textbotlang['users']['customusername'])) {
                \Faoxima\Telegram\Navigation\Steps::step('getvolumecustomusernameom', $from_id);
            } else {
                \Faoxima\Telegram\Navigation\Steps::step('getvolumecustomuserom', $from_id);
            }
            break;
        case 'bulk_username':
            $prodcut = $dataget[1];
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if ($user['step'] == "getvolumecustomusernameom") {
                if (!ctype_digit($text)) {
                    sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
                    return;
                }
                $maintime = json_decode($marzban_list_get['maintime'], true);
                $maintime = $maintime[$user['agent']];
                $maxtime = json_decode($marzban_list_get['maxtime'], true);
                $maxtime = $maxtime[$user['agent']];
                if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
                    $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
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
                \Faoxima\Telegram\Navigation\Steps::step('endstepusersom', $from_id);
            } else {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $prodcut, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('endstepuserom', $from_id);
            }
            $_rx_selectusername_kb = (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxPurchaseUsernameUsesRandomSuffix($marzban_list_get['MethodUsername'])) ? $usernamePromptKb : $backuser;
            sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
            break;
        case 'bulk_product':
            if ($user['step'] == "getvolumecustomuserom") {
                if (!ctype_digit($text)) {
                    sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
                    return;
                }
                $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                $maintime = json_decode($marzban_list_get['maintime'], true);
                $maintime = $maintime[$user['agent']];
                $maxtime = json_decode($marzban_list_get['maxtime'], true);
                $maxtime = $maxtime[$user['agent']];
                if (intval($text) > $maxtime || intval($text) < $maintime) {
                    $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
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
            } elseif ($user['step'] == "endstepusersom" || $user['step'] == "endstepuserom") {
                $prodcut = $user['Processing_value_one'];
            } else {
                $prodcut = $dataget[1];
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
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
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => 'بازگشت', 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
            } else {
                if (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                    $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($loc, $user['Processing_value'], $user['agent']);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                    $stmt->execute([
                        ':code_product' => $loc,
                        ':location' => $user['Processing_value'],
                        ':agent' => $user['agent']
                    ]);
                    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            if (!is_array($info_product) || !isset($info_product['price_product'])) {
                error_log('Bulk purchase preview failed: product not found for code=' . ($loc ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $randomString = bin2hex(random_bytes(2));
            $username_ac = \Faoxima\Services\Delivery\ServiceDelivery::generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
            $username_ac = strtolower($username_ac);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $username_ac, "id", $from_id);
            if ($info_product['Volume_constraint'] == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            if ($info_product['Service_time'] == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            $fxBulkKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($parts[0] != "customvolume") {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            $info_product['price_product'] = intval($info_product['price_product']) * intval($user['Processing_value_four']);
            $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxBulkKinds);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_price", $info_product['price_product'], "id", $from_id);
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('bulk', (string) $loc . '|' . (string) $user['Processing_value_four']), $info_product['price_product'], $marzban_list_get, $fxBulkKinds);
            $price_product_format = number_format($info_product['price_product']);
            $userbalancepish = number_format($user['Balance']);
            $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>" . \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername($username_ac, $marzban_list_get) . "</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت: $price_product_format  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : $userbalancepish
⭕️تعداد کانفیگ : {$user['Processing_value_four']}

💰 سفارش شما آماده پرداخت است.  ";
            if (($parts[0] ?? '') === 'customvolume') {
                $textin .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $parts[2], $parts[1], $info_product['price_product']);
            }
            sendmessage($from_id, $textin, $paymentom, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('payments', $from_id);
            break;
        }

        return true;
    }
}
