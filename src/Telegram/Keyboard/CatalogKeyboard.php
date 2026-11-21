<?php

namespace Faoxima\Telegram\Keyboard;

use PDO;

final class CatalogKeyboard
{
    public static function products($pdo, $textbotlang, $from_id, $location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume", $agentFilter = null, $params = [], $excludeHiddenPanels = false)
    {
        $product = ['inline_keyboard' => []];
        $statusshowprice = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($location, 'showprice') ? "onshowprice" : "offshowprice";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if($valuetow != null){
                $valuetow = "-$valuetow";
        }else{
                $valuetow = "";
            }
        $productRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        usort($productRows, function ($a, $b) {
            $pa = (int)($a['position'] ?? 0);
            $pb = (int)($b['position'] ?? 0);
            $za = $pa === 0 ? 1 : 0;
            $zb = $pb === 0 ? 1 : 0;
            if ($za !== $zb) return $za - $zb;
            if ($pa !== $pb) return $pa - $pb;
            return (int)($a['id'] ?? 0) - (int)($b['id'] ?? 0);
        });
        $fxPanelRow = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
        if (!is_array($fxPanelRow)) $fxPanelRow = [];
        $nmPanelRow = null;
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockHasAvailableForProduct')) {
            $nmPanelRow = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $location, "select");
            if (!is_array($nmPanelRow) || !\Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($nmPanelRow)) $nmPanelRow = null;
        }
        if ($statusshowprice == "onshowprice") {
            $product['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['sell']['grid_header_name'] ?? '📦 نام سرویس', 'callback_data' => 'noop', '_rxcolor' => 'product_header:name'],
                ['text' => $textbotlang['users']['sell']['grid_header_time'] ?? '⏳ زمان', 'callback_data' => 'noop', '_rxcolor' => 'product_header:duration'],
                ['text' => $textbotlang['users']['sell']['grid_header_price'] ?? '💵 قیمت', 'callback_data' => 'noop', '_rxcolor' => 'product_header:price'],
            ];
        }
        foreach ($productRows as $result) {
            if ($agentFilter !== null && (!method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_product_allows_agent') || !\Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_product_allows_agent($result, $agentFilter))) {
                continue;
            }
            if ($nmPanelRow !== null && !\Faoxima\Services\Fulfillment\StockPresentation::nmStockHasAvailableForProduct($nmPanelRow, $result)) {
                continue;
            }
            $hide_panel = json_decode($result['hide_panel'], true);
            if (!is_array($hide_panel)) {
                if ($hide_panel === null && json_last_error() !== JSON_ERROR_NONE) {
                    error_log(sprintf('Invalid hide_panel JSON for product #%s: %s', $result['id'] ?? 'unknown', json_last_error_msg()));
                }
                $hide_panel = [];
            }
            if ($excludeHiddenPanels && in_array((string)$location, array_map('strval', $hide_panel), true)) {
                continue;
            }


            $result = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($result, $fxPanelRow);
            if(intval($pricediscount) != 0){
                $resultper = ($result['price_product'] * $pricediscount) / 100;
                $result['price_product'] = $result['price_product'] -$resultper;
            }
            $result['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($result['price_product'], $fxPanelRow, 'product');
            $callbackToken = trim((string)($result['code_product'] ?? ''));
            $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
            if ($callbackToken === '' || strlen($callbackData) > 64) {
                $callbackToken = 'pid:' . (string)($result['id'] ?? '');
                $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
            }
            if ($statusshowprice == "onshowprice") {
                $days = trim((string)($result['Service_time'] ?? ''));
                $duration = $days !== '' ? "{$days} روزه" : '-';
                $price = number_format((float)$result['price_product']) . ' تومان';
                $product['inline_keyboard'][] = [
                    ['text' => $result['name_product'], 'callback_data' => $callbackData, '_rxcolor' => 'product:' . $result['id'] . ':name'],
                    ['text' => $duration, 'callback_data' => $callbackData, '_rxcolor' => 'product:' . $result['id'] . ':duration'],
                    ['text' => $price, 'callback_data' => $callbackData, '_rxcolor' => 'product:' . $result['id'] . ':price'],
                ];
            } else {
                $product['inline_keyboard'][] = [
                    ['text' => $result['name_product'], 'callback_data' => $callbackData, '_rxcolor' => 'product:' . $result['id'] . ':name'],
                ];
            }
        }
        if ($statuscustom)$product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume]];
        $product['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser, '_rxsk' => 'product_back'],
        ];
        return json_encode($product);
    }
    public static function categories($pdo, $textbotlang, $location,$agent,$backuser = "backuser")
    {
        $list_category = ['inline_keyboard' => []];
        // Use the shared, collation-safe matcher so the list shows exactly the
        // categories that have a sellable product (matching by remark OR id OR
        // name/title, normalised). The old code joined product.category =
        // category.remark directly, which under mixed collations / id-based values
        // produced an empty list (national-net "no categories" bug).
        if (method_exists(\Faoxima\Orders\Catalog\CatalogLookups::class, 'nmSellableCategoryRows')) {
            foreach (\Faoxima\Orders\Catalog\CatalogLookups::nmSellableCategoryRows($location, $agent) as $row) {
                $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id']]];
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stmts = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND FIND_IN_SET(:category, category) > 0 AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmts->bindParam(':location', $location, PDO::PARAM_STR);
                $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
                $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmts->execute();
                if($stmts->rowCount() == 0)continue;
                $list_category['inline_keyboard'][] = [['text' =>$row['remark'],'callback_data' => "categorynames_".$row['id']]];
            }
        }
        $list_category['inline_keyboard'][] = [
            ['text' => "▶️ بازگشت به منوی قبل","callback_data" => $backuser, '_rxsk' => 'category_back'],
        ];
        return json_encode($list_category);
    }

    public static function timeCategories($pdo, $textbotlang, $name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false)
    {
        $stmt = $pdo->prepare("SELECT Service_time FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':location' => $name_panel,
            ':agent' => $agent
        ]);
        $montheproduct = array_flip(array_flip($stmt->fetchAll(PDO::FETCH_COLUMN)));
        $monthkeyboard = ['inline_keyboard' => []];
        if (in_array("1",$montheproduct)){
            $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['1day'], 'callback_data' => "{$callback_data}1"]
                    ];
                }
        if (in_array("7",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['7day'], 'callback_data' => "{$callback_data}7"]
                    ];
                }
        if (in_array("31",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}31"]
                    ];
                }
        if (in_array("30",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}30"]
                    ];
                }
        if (in_array("61",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}61"]
                    ];
                }
        if (in_array("60",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}60"]
                    ];
                }
        if (in_array("91",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}91"]
                    ];
                }
        if (in_array("90",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}90"]
                    ];
                }
        if (in_array("121",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}121"]
                    ];
                }
        if (in_array("120",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}120"]
                    ];
                }
        if (in_array("181",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}181"]
                    ];
                }
        if (in_array("180",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}180"]
                    ];
                }
        if (in_array("365",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['365'], 'callback_data' => "{$callback_data}365"]
                    ];
                }
        if (in_array("0",$montheproduct)){
                    $monthkeyboard['inline_keyboard'][] = [
                        ['text' => $textbotlang['Admin']['month']['unlimited'], 'callback_data' => "{$callback_data}0"]
                    ];
                }
        if($statusbtnextend)$monthkeyboard['inline_keyboard'][] = [['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]];
        if ($statuscustomvolume == true)$monthkeyboard['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => "customsellvolume"]];
        $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $callback_data_back]
                ];
        return json_encode($monthkeyboard);
    }
}
