<?php

$keyboardlinkapp = json_encode([
    'keyboard' => [
        [['text' => "🔗 اضافه کردن برنامه"],['text' => "❌ حذف برنامه"]],
        [['text' => "✏️ ویرایش برنامه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if (!function_exists('rxProductLookupTokens')) {
function rxProductLookupTokens($rawToken)
{
    $tokens = [];
    $addToken = static function ($value) use (&$tokens) {
        if ($value === null) return;
        $value = trim((string) $value);
        if ($value === '') {
            $tokens[''] = '';
            return;
        }
        $decodedHtml = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decodedUrl = rawurldecode($decodedHtml);
        foreach ([$value, $decodedHtml, $decodedUrl] as $candidate) {
            $candidate = trim((string) $candidate);
            foreach (['prodcutservice_', 'prodcutservices_', 'prodcutserviceom_', 'prodcutservicesom_'] as $prefix) {
                if (strpos($candidate, $prefix) === 0) {
                    $candidate = substr($candidate, strlen($prefix));
                    break;
                }
            }
            $tokens[$candidate] = $candidate;
        }
    };

    $addToken($rawToken);
    if (function_exists('rx_resolveInlineButtonText')) {
        $resolved = rx_resolveInlineButtonText((string) $rawToken);
        if ($resolved !== null) {
            $addToken($resolved);
        }
    }

    return array_values($tokens);
}
}

if (!function_exists('rxResolveProductForPanel')) {
function rxResolveProductForPanel($productToken, $panelName, $agent = null, $category = null, $serviceTime = null)
{
    global $pdo;

    if (!($pdo instanceof PDO)) return false;
    $panelName = trim((string) $panelName);
    if ($panelName === '') return false;
    $agent = $agent === null ? null : trim((string) $agent);
    $tokens = rxProductLookupTokens($productToken);

    $queryProduct = static function ($extraSql, array $extraParams = []) use ($pdo, $panelName, $agent, $category, $serviceTime) {
        $sql = "SELECT * FROM product WHERE (Location = :rx_location_where OR Location = '/all')";
        $params = [
            ':rx_location_where' => $panelName,
            ':rx_location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :rx_agent_where OR agent = 'all')";
            $params[':rx_agent_where'] = $agent;
            $params[':rx_agent_order'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (category = :rx_category OR category = :rx_category_id)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }

        $sql .= ' ' . $extraSql;
        $sql .= " ORDER BY CASE WHEN Location = :rx_location_order THEN 0 ELSE 1 END";
        if ($agent !== null && $agent !== '') {
            $sql .= ", CASE WHEN agent = :rx_agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END";
        }
        $sql .= ", id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + $extraParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    };

    foreach ($tokens as $token) {
        if (preg_match('/^pid:(\d+)$/', (string) $token, $m)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $m[1]]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByCodeForPanel')) {
            $row = nmProductByCodeForPanel($token, $panelName, $agent);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $row = $queryProduct('AND code_product = :rx_code_product', [':rx_code_product' => $token]);
        if ($row) return $row;
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') continue;
        if (ctype_digit($token)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $token]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByNameForPanel')) {
            $row = nmProductByNameForPanel($token, $panelName, $agent, $category);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $cleanName = preg_replace('/\s*-\s*[0-9,\.]+\s*تومان\s*$/u', '', $token);
        foreach (array_unique([$token, trim((string) $cleanName)]) as $nameCandidate) {
            if ($nameCandidate === '') continue;
            $row = $queryProduct('AND name_product = :rx_name_product', [':rx_name_product' => $nameCandidate]);
            if ($row) return $row;
        }
    }

    $nonEmptyTokens = array_filter($tokens, static function ($token) { return trim((string) $token) !== ''; });
    if (empty($nonEmptyTokens)) {
        $sql = "SELECT * FROM product WHERE (Location = :rx_location_where OR Location = '/all')";
        $params = [':rx_location_where' => $panelName];
        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :rx_agent_where OR agent = 'all')";
            $params[':rx_agent_where'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (category = :rx_category OR category = :rx_category_id)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }
        $sql .= " ORDER BY id ASC LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) return $rows[0];
    }

    return false;
}
}

function KeyboardProduct($location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume", $agentFilter = null, $params = []){
    global $pdo,$textbotlang,$from_id;
    $product = ['inline_keyboard' => []];
    $statusshowprice = select("shopSetting","*","Namevalue","statusshowprice","select")['value'];
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    if($valuetow != null){
            $valuetow = "-$valuetow";
    }else{
            $valuetow = "";
        }
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($agentFilter !== null) {
            $productAgent = (string) ($result['agent'] ?? '');
            if ($productAgent !== (string) $agentFilter && $productAgent !== 'all') {
                continue;
            }
        }
        $hide_panel = json_decode($result['hide_panel'], true);
        if (!is_array($hide_panel)) {
            if ($hide_panel === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log(sprintf('Invalid hide_panel JSON for product #%s: %s', $result['id'] ?? 'unknown', json_last_error_msg()));
            }
            $hide_panel = [];
        }


        if(intval($pricediscount) != 0){
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] -$resultper;
        }
        $namekeyboard = $result['name_product']." - ".number_format($result['price_product']) ."تومان";
        if($statusshowprice == "onshowprice"){
            $result['name_product'] = $namekeyboard;
        }
        $callbackToken = trim((string)($result['code_product'] ?? ''));
        $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        if ($callbackToken === '' || strlen($callbackData) > 64) {
            $callbackToken = 'pid:' . (string)($result['id'] ?? '');
            $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        }
        $product['inline_keyboard'][] = [
                ['text' =>  $result['name_product'], 'callback_data' => $callbackData]
            ];
    }
    if ($statuscustom)$product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume]];
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser],
    ];
    return json_encode($product);
}
function KeyboardCategory($location,$agent,$backuser = "backuser"){
    global $pdo,$textbotlang;
    $list_category = ['inline_keyboard' => []];
    // Use the shared, collation-safe matcher so the list shows exactly the
    // categories that have a sellable product (matching by remark OR id OR
    // name/title, normalised). The old code joined product.category =
    // category.remark directly, which under mixed collations / id-based values
    // produced an empty list (national-net "no categories" bug).
    if (function_exists('nmSellableCategoryRows')) {
        foreach (nmSellableCategoryRows($location, $agent) as $row) {
            $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id']]];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM category");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmts = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category AND (agent = :agent OR agent = 'all')");
            $stmts->bindParam(':location', $location, PDO::PARAM_STR);
            $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
            $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
            $stmts->execute();
            if($stmts->rowCount() == 0)continue;
            $list_category['inline_keyboard'][] = [['text' =>$row['remark'],'callback_data' => "categorynames_".$row['id']]];
        }
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ بازگشت به منوی قبل","callback_data" => $backuser],
    ];
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT Service_time FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
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
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => "🗂 نام درگاه استار"]],
        [['text' => "💰 کش بک استار"],['text' => "📚 تنظیم آموزش استار"]],
        [['text' => "⬇️ حداقل مبلغ استار"],['text' => "⬆️ حداکثر مبلغ استار"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => "🆓 محدودیت رایگان"],['text' => "↙️ محدودیت کلی"]],
        [['text' => "🔄 ریست محدودیت کل کاربران"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin(){
    global $pdo, $textbotlang, $setting;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' => $row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];

    $json = json_encode($list_category);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($list_category);
    }
    return $json;
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => "API NOWPAYMENT"],['text' => "🔐 IPN Secret nowpayment"]],
        [['text' => "🗂 نام درگاه nowpayment"],['text' => "💰 کش بک nowpayment"]],
        [['text' => "📚 تنظیم آموزش nowpayment"]],
        [['text' => "⬇️ حداقل مبلغ nowpayment"],['text' => "⬆️ حداکثر مبلغ nowpayment"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ استثناء کردن کاربر"],['text' => "❌ حذف کاربر از لیست"]],
        [['text' => "👁 نمایش لیست افراد"]],
        [['text' => "▶️ بازگشت به منوی تظنیمات کارت"]]
    ],
    'resize_keyboard' => true
]);
function keyboard_config($config_split,$id_invoice,$back_active = true){
    global $textbotlang;
    try {
        $invoiceForKeyboard = select("invoice", "*", "id_invoice", $id_invoice, "select");
        if (function_exists("nmServicePanelAccessBlocked") && is_array($invoiceForKeyboard) && nmServicePanelAccessBlocked($invoiceForKeyboard)) {
            return json_encode(["inline_keyboard" => [[["text" => function_exists("nmServiceRestrictedNotice") ? nmServiceRestrictedNotice() : "این سرویس در حال حاضر به دلیل شرایط اینترنت ملی در دسترس نیست !", "callback_data" => "none"]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } catch (Throwable $e) {
        error_log("keyboard_config national block check failed: " . $e->getMessage());
    }
    $keyboard_config = ['inline_keyboard' => []];
    $keyboard_config['inline_keyboard'][] = [
        ['text' => "⚙️ کانفیگ", 'callback_data' => "none"],
        ['text' => "✏️نام کانفیگ", 'callback_data' => "none"],
        ];
    foreach (array_values($config_split) as $i => $config){
        if(!is_string($config) || $config === ''){
            error_log('Invalid configuration entry encountered while building keyboard');
            continue;
        }

        $split_config = explode("://",$config,2);
        if(count($split_config) !== 2){
            error_log('Malformed configuration string: missing scheme separator');
            continue;
        }

        $type_prtocol = $split_config[0];
        $payload = $split_config[1];
        if(isBase64($payload)){
            $decoded = base64_decode($payload, true);
            if($decoded === false){
                error_log('Failed to decode base64 configuration payload');
                continue;
            }
            $payload = $decoded;
        }

        $displayName = '';
        if($type_prtocol == "vmess"){
            $configJson = json_decode($payload, true);
            if(is_array($configJson) && isset($configJson['ps'])){
                $displayName = $configJson['ps'];
            }
        }else{
            $parts = explode("#",$payload,2);
            if(count($parts) === 2){
                $displayName = $parts[1];
            }
        }

        if($displayName === '' || $displayName === null){
            $displayName = sprintf('Config %d', $i + 1);
        }

        $keyboard_config['inline_keyboard'][] = [
            ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
            ['text' => urldecode($displayName), 'callback_data' => "none"],
        ];

    }
    $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "configget_$id_invoice"."_1520"]];
    if($back_active){
    $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"]];
    }
    return json_encode($keyboard_config);
}
$keyboard_buy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🛍خرید اشتراک", 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboard_stat = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "⏱️ آمار کل", 'callback_data' => 'stat_all_bot'],
                ],[
                    ['text' => "⏱️ یک ساعت اخیر", 'callback_data' => 'hoursago_stat'],
                ],
                [
                    ['text' => "⛅️ امروز", 'callback_data' => 'today_stat'],
                    ['text' => "☀️ دیروز", 'callback_data' => 'yesterday_stat'],
                ],
                [
                    ['text' => "☀️ ماه فعلی ", 'callback_data' => 'month_current_stat'],
                    ['text' => "⛅️ ماه قبل", 'callback_data' => 'month_old_stat'],
                ],
                [
                    ['text' => "🗓 مشاهده آمار در تاریخ مشخص", 'callback_data' => 'view_stat_time'],
                ],
                [
                    ['text' => "❌ بستن", 'callback_data' => 'close_stat'],
                ]
            ]
        ]);
if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline") {



    $all_vars = get_defined_vars();
    foreach ($all_vars as $varName => $varValue) {
        if (is_string($varValue) && strpos($varValue, '"keyboard"') !== false && strpos($varValue, '"inline_keyboard"') === false) {

            $rxDecodedKb = json_decode($varValue, true);
            if (!is_array($rxDecodedKb) || !isset($rxDecodedKb['keyboard']) || !is_array($rxDecodedKb['keyboard'])) {
                continue;
            }
            $rxHasReplyOnly = false;
            foreach ($rxDecodedKb['keyboard'] as $rxRow) {
                if (!is_array($rxRow)) continue;
                foreach ($rxRow as $rxBtn) {
                    if (is_array($rxBtn) && (
                        isset($rxBtn['request_contact']) || isset($rxBtn['request_location']) ||
                        isset($rxBtn['request_poll']) || isset($rxBtn['request_users']) ||
                        isset($rxBtn['request_chat'])
                    )) { $rxHasReplyOnly = true; break 2; }
                }
            }
            if ($rxHasReplyOnly) continue;
            if (function_exists('rx_keyboardToInline')) {
                $$varName = rx_keyboardToInline($rxDecodedKb);
            }
        }
    }
    unset($all_vars, $rxDecodedKb, $rxRow, $rxBtn, $rxHasReplyOnly);
}