<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;

/**
 * Handler stage. Inherited by value: $_rx_nav_styles, $backuser, $datatextbot, $from_id, $keyboard, $message_id, $pdo, $telegramRoute, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $info_product, $marzban_list_get, $result, $stmt, $userdate.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PurchaseDiscount
{
    public static function handle(
        $_rx_nav_styles,
        $backuser,
        $datatextbot,
        $from_id,
        &$info_product,
        $keyboard,
        &$marzban_list_get,
        $message_id,
        $pdo,
        &$result,
        &$stmt,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        $user,
        &$userdate
    ) {
        switch ($telegramRoute) {
        case 'purchase_discount_prompt':
            sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getcodesellDiscount', $from_id);
            deletemessage($from_id, $message_id);
            break;
        case 'purchase_discount':
            if (!isset($update['message']) && empty($text)) { return; }
            $userdate = json_decode($user['Processing_value'], true);
            if (!isset($userdate['name_panel'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_restart_process'] ?? "❌ مراحل خرید را مجددا از اول انجام دهید", $keyboard, 'HTML');
                return;
            }
            $parts = explode("_", (string)$user['Processing_value_one']);
            if (($parts[0] ?? '') === "customvolume") {
                $info_product = [
                    'code_product' => 'customvolume',
                    'name_product' => $textbotlang['users']['customsellvolume']['title'],
                    'Volume_constraint' => $parts[2] ?? 0,
                    'Service_time' => $parts[1] ?? 0,
                ];
            } elseif (method_exists(\Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::class, 'rxResolveProductForPanel')) {
                $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
                $stmt->execute([
                    ':code_product' => $user['Processing_value_one'],
                    ':Location' => $userdate['name_panel'],
                    ':agent' => $user['agent'],
                ]);
                $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!is_array($info_product) || !isset($info_product['code_product'])) {
                error_log('Discount code check failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
            if (!\Faoxima\Core\Database\Db::rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
                sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
                return;
            }
            $__dv = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, $text, 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
            if (empty($__dv['ok'])) {
                sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                return;
            }
            $__dvt = $__dv['value_type'];
            $__dval = (float)$__dv['value'];
            $__dlabel = $__dv['label'];
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.", $__dlabel), null, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('payment', $from_id);
            $parts = explode("_", $user['Processing_value_one']);
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
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
                    $info_product = \Faoxima\Admin\Keyboard\SharedCatalogLookupHelpers::rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
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
                error_log('Discount purchase preview failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
                sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
            if ($parts[0] != "customvolume") {
                $info_product = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($info_product, $marzban_list_get);
            }
            $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
            $info_productmain = $info_product['price_product'];
            if ($__dvt === 'free') {
                $info_product['price_product'] = 0;
            } elseif ($__dvt === 'amount') {
                $info_product['price_product'] = $info_product['price_product'] - $__dval;
            } else {
                $result = ($__dval / 100) * $info_product['price_product'];
                $info_product['price_product'] = $info_product['price_product'] - $result;
            }
            $info_product['price_product'] = round($info_product['price_product']);
            if ($info_product['Service_time'] == 0)
                $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
            if (($marzban_list_get['type'] ?? '') === 'jovadpanel' ? (is_numeric($info_product['Volume_constraint']) && (float) $info_product['Volume_constraint'] === 0.0) : intval($info_product['Volume_constraint']) == 0)
                $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
            if ($info_product['price_product'] < 0)
                $info_product['price_product'] = 0;
            $info_product['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
            $rxFmtInfoProductmain = \Faoxima\Core\Support\Formatting::rxFormatToman($info_productmain);
            $rxFmtInfoProductPriceDiscounted = \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']);
            $rxFmtUserBalanceInvoicePreview = \Faoxima\Core\Support\Formatting::rxFormatToman($user['Balance']);
            $textin = \Faoxima\Telegram\Support\MessageTemplates::get('service_discount_invoice_preview', ['username' => $user['Processing_value_tow'], 'product' => $info_product['name_product'], 'days' => $info_product['Service_time'], 'price_main' => $rxFmtInfoProductmain, 'price_discounted' => $rxFmtInfoProductPriceDiscounted, 'volume' => $info_product['Volume_constraint'], 'balance' => $rxFmtUserBalanceInvoicePreview]);
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $paymentDiscount = json_encode([
                'inline_keyboard' => [
                    [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_pay_and_get'), 'callback_data' => "confirmandgetserviceDiscount"]],
                    [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s)]
                ]
            ]);
            $parametrsendvalue = $text . "_" . $info_product['price_product'];
            \Faoxima\Core\Database\Db::update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('buy_discount', (string) $user['Processing_value_one']), $info_product['price_product'], $marzban_list_get, $fxBuyKinds);
            sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
            break;
        }

        return true;
    }
}
