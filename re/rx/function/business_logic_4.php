<?php

function nmEmergencyProductFor(array $sourceProduct, array $emergencyPanel)
{
    global $pdo;
    $loc = $emergencyPanel['name_panel'] ?? '';
    try {
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND code_product=:code LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':code'=>$sourceProduct['code_product'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND name_product=:name LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':name'=>$sourceProduct['name_product'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
        $stmt=$pdo->prepare("SELECT * FROM product WHERE (Location=:loc OR Location='/all') AND Volume_constraint=:vol AND Service_time=:days ORDER BY CAST(price_product AS UNSIGNED) ASC LIMIT 1");
        $stmt->execute([':loc'=>$loc, ':vol'=>$sourceProduct['Volume_constraint'] ?? '', ':days'=>$sourceProduct['Service_time'] ?? '']);
        $p=$stmt->fetch(PDO::FETCH_ASSOC); if ($p) return $p;
    } catch (Throwable $e) { error_log('nmEmergencyProductFor failed: '.$e->getMessage()); }
    return $sourceProduct;
}
function nmEmergencyUserNotice()
{
    return "🚨 حالت پنل اضطراری روشن است. برای سرویس‌های پنل اصلی فعلاً امکان تمدید مستقیم همان کانفیگ وجود ندارد؛ در صورت نیاز می‌توانید سرویس جدید تهیه کنید یا تمدید را از مسیر پنل اضطراری/انبار انجام دهید.";
}


function nmServiceRestrictedNotice()
{
    return 'این سرویس در حال حاضر به دلیل شرایط اینترنت ملی در دسترس نیست !';
}

function nmRestrictedServiceKeyboard(array $invoice = null)
{
    global $textbotlang;
    $buttons = [];
    $invoiceId = is_array($invoice) ? trim((string)($invoice['id_invoice'] ?? '')) : '';
    if ($invoiceId !== '') {
        $buttons[] = [[
            'text' => '🛒 خرید سرویس اینترنت ملی',
            'callback_data' => 'nm_buy_service_' . $invoiceId,
        ]];
    }
    $buttons[] = [[
        'text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها',
        'callback_data' => 'backorder',
    ]];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmNationalBuyPanelKeyboard(array $oldInvoice, array $userRow = null)
{
    global $pdo, $textbotlang;
    $invoiceId = trim((string)($oldInvoice['id_invoice'] ?? ''));
    $agent = is_array($userRow) ? trim((string)($userRow['agent'] ?? '')) : '';
    if ($agent === '') $agent = 'all';
    $buttons = [];
    try {
        $where = "status='active'";
        $params = [];
        if ($agent !== 'all') {
            $where .= " AND (agent=:agent OR agent='all' OR agent='' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $stmt = $pdo->prepare("SELECT id, name_panel, code_panel FROM marzban_panel WHERE {$where} ORDER BY id ASC, name_panel ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $panel) {
            $panelId = trim((string)($panel['id'] ?? ''));
            $name = trim((string)($panel['name_panel'] ?? ''));
            if ($invoiceId === '' || $panelId === '' || $name === '') continue;
            $buttons[] = [[
                'text' => $name,
                'callback_data' => 'nm_buy_panel_' . $invoiceId . '_' . $panelId,
            ]];
        }
    } catch (Throwable $e) {
        error_log('nmNationalBuyPanelKeyboard failed: ' . $e->getMessage());
    }
    if (empty($buttons)) {
        return false;
    }
    $buttons[] = [[
        'text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها',
        'callback_data' => 'backorder',
    ]];
    return json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function nmPanelById($panelId)
{
    $panelId = trim((string)$panelId);
    if ($panelId === '') return false;
    try {
        return select('marzban_panel', '*', 'id', $panelId, 'select');
    } catch (Throwable $e) {
        error_log('nmPanelById failed: ' . $e->getMessage());
        return false;
    }
}

function nmGenerateNationalUsername($userId)
{
    try {
        $suffix = bin2hex(random_bytes(3));
    } catch (Throwable $e) {
        $suffix = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
    }
    return preg_replace('/[^A-Za-z0-9_]/', '', (string)$userId) . '_nm_' . $suffix;
}

function nmCreateNationalServiceFromInvoice(array $oldInvoice, array $userRow, array $selectedPanel = null)
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return ['status' => false, 'message' => '❌ اتصال دیتابیس در دسترس نیست.'];
    }
    if (!nmStockEnsureSchema()) {
        return ['status' => false, 'message' => '❌ جدول‌های انبار شبکه ملی آماده نیستند. table.php را یک‌بار اجرا کنید.'];
    }

    $userId = $userRow['id'] ?? ($oldInvoice['id_user'] ?? null);
    if ($userId === null || (string)$userId === '') {
        return ['status' => false, 'message' => '❌ اطلاعات کاربر پیدا نشد.'];
    }

    $sourcePanel = false;
    try {
        $sourcePanel = select('marzban_panel', '*', 'name_panel', $oldInvoice['Service_location'] ?? '', 'select');
    } catch (Throwable $e) {}
    if (!$sourcePanel) {
        return ['status' => false, 'message' => '❌ پنل سرویس قبلی پیدا نشد.'];
    }

    $targetPanel = is_array($selectedPanel) && !empty($selectedPanel) ? $selectedPanel : $sourcePanel;

    $sourceProduct = nmStockProductForInvoice($oldInvoice);
    $product = $sourceProduct;
    if ($targetPanel !== $sourcePanel && function_exists('nmEmergencyProductFor')) {
        $mapped = nmEmergencyProductFor($sourceProduct, $targetPanel);
        if (is_array($mapped) && $mapped) {
            $product = $mapped;
        }
    }
    if (!is_array($product) || empty($product)) {
        return ['status' => false, 'message' => '❌ محصول متناظر برای خرید اینترنت ملی پیدا نشد.'];
    }

    $price = (float)($product['price_product'] ?? ($oldInvoice['price_product'] ?? 0));
    $balance = (float)($userRow['Balance'] ?? 0);
    if ($price > 0 && $balance < $price && (($userRow['agent'] ?? '') !== 'n2')) {
        return ['status' => false, 'message' => '❌ موجودی کیف پول شما برای خرید سرویس اینترنت ملی کافی نیست. لطفاً ابتدا کیف پول را شارژ کنید.'];
    }

    $invoiceId = bin2hex(random_bytes(4));
    $usernameAc = nmGenerateNationalUsername($userId);
    $exists = true;
    for ($i = 0; $i < 5 && $exists; $i++) {
        try {
            $exists = select('invoice', '*', 'username', $usernameAc, 'select');
        } catch (Throwable $e) {
            $exists = false;
        }
        if ($exists) $usernameAc = nmGenerateNationalUsername($userId);
    }

    $notifctions = json_encode(['volume' => false, 'time' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        $stmt = $pdo->prepare("INSERT INTO invoice (id_user,id_invoice,username,time_sell,Service_location,name_product,price_product,Volume,Service_time,Status,notifctions,source_panel_code) VALUES (:id_user,:id_invoice,:username,:time_sell,:service_location,:name_product,:price_product,:volume,:service_time,'Unpaid',:notifctions,:source_panel_code)");
        $stmt->execute([
            ':id_user' => (string)$userId,
            ':id_invoice' => $invoiceId,
            ':username' => $usernameAc,
            ':time_sell' => time(),
            ':service_location' => (string)($targetPanel['name_panel'] ?? ($sourcePanel['name_panel'] ?? '')),
            ':name_product' => (string)($product['name_product'] ?? ($oldInvoice['name_product'] ?? '')),
            ':price_product' => (string)$price,
            ':volume' => (string)($product['Volume_constraint'] ?? ($oldInvoice['Volume'] ?? 0)),
            ':service_time' => (string)($product['Service_time'] ?? ($oldInvoice['Service_time'] ?? 0)),
            ':notifctions' => $notifctions,
            ':source_panel_code' => (string)($sourcePanel['code_panel'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('nmCreateNationalServiceFromInvoice insert invoice failed: ' . $e->getMessage());
        return ['status' => false, 'message' => '❌ ثبت فاکتور سرویس اینترنت ملی با خطا مواجه شد.'];
    }

    $stockPanelForReserve = $targetPanel;

    $chargeBalance = ($price > 0 && (($userRow['agent'] ?? '') !== 'n2'));
    $stock = nmStockCompleteBuyFromInventory($userId, $userRow, $stockPanelForReserve, $product, $invoiceId, $usernameAc, $chargeBalance, 'national_button_buy');
    if (!$stock && $targetPanel !== $sourcePanel) {
        $stock = nmStockCompleteBuyFromInventory($userId, $userRow, $sourcePanel, $product, $invoiceId, $usernameAc, $chargeBalance, 'national_button_buy');
    }
    if (!$stock) {
        try { update('invoice', 'Status', 'Unpaid', 'id_invoice', $invoiceId); } catch (Throwable $e) {}
        return ['status' => false, 'message' => '❌ موجودی انبار برای محصول متناظر این سرویس تمام شده است.'];
    }

    return [
        'status' => true,
        'message' => "✅ سرویس اینترنت ملی جدید با موفقیت از انبار تحویل شد.\n\n🗂 محصول: " . ($product['name_product'] ?? '') . "\n🔋 حجم: " . ($product['Volume_constraint'] ?? ($oldInvoice['Volume'] ?? 0)) . " گیگ\n⏳ مدت: " . ($product['Service_time'] ?? ($oldInvoice['Service_time'] ?? 0)) . " روز\n🧾 کد پیگیری: <code>{$invoiceId}</code>",
        'invoice_id' => $invoiceId,
    ];
}

function nmDecodeState($raw)
{
    if (is_array($raw)) return $raw;
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function nmResolvePanelFromUserState(array $user = null, $fallbackPanelName = null)
{
    $candidates = [];
    if ($user) {
        foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
            if (!array_key_exists($stateField, $user)) {
                continue;
            }
            $state = nmDecodeState($user[$stateField] ?? '');
            foreach (['namepanel', 'name_panel', 'panel', 'panel_name', 'code_panel', 'codepanel', 'stock_codepanel', 'source_codepanel'] as $key) {
                if (!empty($state[$key])) {
                    $candidates[] = trim((string)$state[$key]);
                }
            }

            if (!is_array($user[$stateField])) {
                $raw = trim((string)$user[$stateField]);
                if ($raw !== '' && $raw !== '0' && $raw !== 'none' && $raw[0] !== '{' && $raw[0] !== '[') {
                    $candidates[] = $raw;
                }
            }
        }
    }
    if ($fallbackPanelName !== null && trim((string)$fallbackPanelName) !== '') {
        $candidates[] = trim((string)$fallbackPanelName);
    }
    foreach (array_unique(array_filter($candidates)) as $candidate) {
        try {
            $panel = select('marzban_panel', '*', 'name_panel', $candidate, 'select');
            if ($panel) return $panel;
            $panel = select('marzban_panel', '*', 'code_panel', $candidate, 'select');
            if ($panel) return $panel;
            if (ctype_digit((string)$candidate)) {
                $panel = select('marzban_panel', '*', 'id', $candidate, 'select');
                if ($panel) return $panel;
            }
        } catch (Throwable $e) {}
    }
    return false;
}

function nmResolvePanelNameForUser(array $user = null, $fallback = null)
{
    if ($user) {
        foreach (['Processing_value', 'Processing_value_one', 'Processing_value_tow', 'Processing_value_four'] as $stateField) {
            if (!array_key_exists($stateField, $user)) continue;
            $raw = $user[$stateField];
            if (is_string($raw)) {
                $trim = trim($raw);
                if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[' && $trim !== '0' && $trim !== 'none') {
                    return $trim;
                }
                $state = nmDecodeState($trim);
                foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                    if (!empty($state[$key])) return trim((string)$state[$key]);
                }
            } elseif (is_array($raw)) {
                foreach (['namepanel', 'name_panel', 'panel', 'panel_name'] as $key) {
                    if (!empty($raw[$key])) return trim((string)$raw[$key]);
                }
            }
        }
    }
    if ($fallback !== null) {
        $fallback = trim((string)$fallback);
        if ($fallback !== '') return $fallback;
    }
    $resolved = nmResolvePanelFromUserState($user, $fallback);
    if (is_array($resolved) && !empty($resolved['name_panel'])) return (string)$resolved['name_panel'];
    return '';
}


function nmNormalizeText($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\x{202a}-\x{202e}]/u', '', $value);
    $value = str_replace(["ي", "ك", "ة", "ۀ"], ["ی", "ک", "ه", "ه"], $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function nmProductButtonName($text)
{
    $text = nmNormalizeText($text);
    $text = preg_replace('/\s*-\s*[0-9۰-۹,،.]+\s*(?:تومان|ریال)?\s*$/u', '', $text);
    return nmNormalizeText($text);
}

function nmTableHasColumn($table, $column)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $stmt->execute([':t' => $table, ':c' => $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

function nmCategoryLookupValues($category)
{
    $values = [];
    $category = nmNormalizeText($category);
    if ($category === '' || $category === 'بدون دسته‌بندی') {
        return [];
    }

    $values[] = $category;
    $lookupColumns = [];
    foreach (['id', 'remark', 'name', 'title'] as $column) {
        if (function_exists('nmTableHasColumn') && nmTableHasColumn('category', $column)) {
            $lookupColumns[] = $column;
        }
    }

    foreach ($lookupColumns as $column) {
        if ($column === 'id' && !ctype_digit((string)$category)) {
            continue;
        }
        try {
            $row = select('category', '*', $column, $category, 'select');
            if (is_array($row) && $row) {
                foreach (['id', 'remark', 'name', 'title'] as $k) {
                    if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
                        $values[] = nmNormalizeText($row[$k]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('nmCategoryLookupValues lookup failed on category.' . $column . ': ' . $e->getMessage());
        }
    }

    if (in_array('remark', $lookupColumns, true) && in_array('id', $lookupColumns, true)) {
        try {
            $row = select('category', '*', 'remark', $category, 'select');
            if (is_array($row) && isset($row['id'])) {
                $values[] = nmNormalizeText($row['id']);
            }
        } catch (Throwable $e) {}
    }

    return array_values(array_unique(array_filter($values, static function($v) { return trim((string)$v) !== ''; })));
}
function nmProductsForPanelCategory(array $panel, $agent = 'all', $category = null)
{
    global $pdo;
    $loc = trim((string)($panel['name_panel'] ?? ''));
    if ($loc === '' || !isset($pdo)) return [];

    $agent = trim((string)($agent ?? ''));
    $filterAgent = !in_array($agent, ['', 'all', '*', 'any'], true);
    $params = [
        ':loc_where' => $loc,
        ':loc_order' => $loc,
    ];
    $sql = "SELECT * FROM product WHERE (Location = :loc_where OR Location = '/all')";
    if ($filterAgent) {
        $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
        $params[':agent'] = $agent;
    }

    $catValues = nmCategoryLookupValues($category);
    if ($catValues) {
        $in = [];
        foreach ($catValues as $i => $value) {
            $key = ':cat' . $i;
            $in[] = $key;
            $params[$key] = $value;
        }
        $sql .= ' AND (category IN (' . implode(',', $in) . ')';
        if (nmTableHasColumn('product', 'category_id')) {
                $inId = [];
                foreach ($catValues as $j => $value) {
                    $key = ':cat_id' . $j;
                    $inId[] = $key;
                    $params[$key] = $value;
                }
                $sql .= ' OR category_id IN (' . implode(',', $inId) . ')';
            }
        $sql .= ')';
    }
    $sql .= " ORDER BY CASE WHEN Location = :loc_order THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows && $catValues) {
            $fallbackParams = [
                ':fallback_loc_where' => $loc,
                ':fallback_loc_order' => $loc,
            ];
            $fallbackSql = "SELECT * FROM product WHERE (Location = :fallback_loc_where OR Location = '/all')";
            if ($filterAgent) {
                $fallbackSql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
                $fallbackParams[':agent'] = $agent;
            }
            $fallbackSql .= " ORDER BY CASE WHEN Location = :fallback_loc_order THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute($fallbackParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        return $rows;
    } catch (Throwable $e) {
        error_log('nmProductsForPanelCategory failed: ' . $e->getMessage());
        return [];
    }
}

function nmProductByNameForShelf($productName, array $panel = null, $agent = null, $category = null)
{
    if (!$panel) return false;
    $wanted = nmProductButtonName($productName);
    if ($wanted === '' || $wanted === '❌ محصولی پیدا نشد') return false;
    $products = nmProductsForPanelCategory($panel, $agent ?: 'all', $category);
    foreach ($products as $product) {
        if (nmNormalizeText($product['name_product'] ?? '') === $wanted) return $product;
    }
    foreach ($products as $product) {
        $name = nmNormalizeText($product['name_product'] ?? '');
        if ($name !== '' && (mb_strpos($wanted, $name) !== false || mb_strpos($name, $wanted) !== false)) return $product;
    }
    $found = nmProductByNameForPanel($wanted, $panel['name_panel'] ?? '', $agent, $category);
    if ($found) return $found;
    $found = nmProductByNameForPanel($wanted, $panel['name_panel'] ?? '', $agent, null);
    if ($found) return $found;
    if (count($products) === 1) return $products[0];
    return false;
}

function nmServicePanelAccessBlocked(array $invoice = null){
    if (!$invoice) return false;
    $serviceLocation = trim((string)($invoice['Service_location'] ?? ''));
    if ($serviceLocation === '') return false;
    try {
        $panel = select('marzban_panel', '*', 'name_panel', $serviceLocation, 'select');
        if (!$panel && !empty($invoice['source_panel_code'])) $panel = select('marzban_panel', '*', 'code_panel', $invoice['source_panel_code'], 'select');
        if (!$panel) return false;
        return nmPanelNationalEnabled($panel) || nmPanelEmergencyEnabled($panel);
    } catch (Throwable $e) {
        error_log('nmServicePanelAccessBlocked failed: ' . $e->getMessage());
        return false;
    }
}

function nmStopIfServicePanelBlocked($invoice, $userId = null, $keyboard = null)
{
    if (!is_array($invoice) || !nmServicePanelAccessBlocked($invoice)) return false;
    if ($keyboard === null && function_exists('nmRestrictedServiceKeyboard')) {
        $keyboard = nmRestrictedServiceKeyboard($invoice);
    }
    if (function_exists('sendmessage') && $userId !== null) {
        sendmessage($userId, nmServiceRestrictedNotice(), $keyboard, 'HTML');
    }
    return true;
}

function nmProductByCodeForPanel($codeProduct, $panelName, $agent = null)
{
    global $pdo;

    $codeProduct = trim((string) $codeProduct);
    $panelName = trim((string) $panelName);
    $agent = $agent === null ? null : trim((string) $agent);

    if (!($pdo instanceof PDO) || $codeProduct === '' || $panelName === '') {
        return false;
    }

    try {
        $sql = "SELECT * FROM product
                WHERE code_product = :code_product
                  AND (Location = :location_where OR Location = '/all')";
        $params = [
            ':code_product' => $codeProduct,
            ':location_where' => $panelName,
            ':location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (agent = :agent_where OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent_where'] = $agent;
            $params[':agent_order'] = $agent;
            $sql .= " ORDER BY
                        CASE WHEN Location = :location_order THEN 0 ELSE 1 END,
                        CASE WHEN agent = :agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END,
                        id ASC
                      LIMIT 1";
        } else {
            $sql .= " ORDER BY CASE WHEN Location = :location_order THEN 0 ELSE 1 END, id ASC LIMIT 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (Throwable $e) {
        error_log('nmProductByCodeForPanel failed: ' . $e->getMessage());
        return false;
    }
}

function nmProductByNameForPanel($productName, $panelName, $agent = null, $category = null)
{
    global $pdo;
    try {
        $productName = nmProductButtonName($productName);
        $panelName = trim((string)$panelName);
        $params = [
            ':name_product' => $productName,
            ':location_where' => $panelName,
            ':location_order' => $panelName,
        ];
        $sql = "SELECT * FROM product WHERE name_product = :name_product AND (Location = :location_where OR Location = '/all')";
        $catValues = nmCategoryLookupValues($category);
        if ($catValues) {
            $in = [];
            foreach ($catValues as $i => $value) { $key=':cat'.$i; $in[]=$key; $params[$key]=$value; }
            $sql .= ' AND (category IN (' . implode(',', $in) . ')';
            if (nmTableHasColumn('product', 'category_id')) {
                $inId = [];
                foreach ($catValues as $j => $value) {
                    $key = ':cat_id' . $j;
                    $inId[] = $key;
                    $params[$key] = $value;
                }
                $sql .= ' OR category_id IN (' . implode(',', $inId) . ')';
            }
            $sql .= ')';
        }
        $agent = trim((string)($agent ?? ''));
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY CASE WHEN Location = :location_order THEN 0 ELSE 1 END LIMIT 1";
        $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) return $row;

        $rows = nmProductsForPanelCategory(['name_panel' => $panelName], $agent ?: 'all', $category);
        foreach ($rows as $r) if (nmNormalizeText($r['name_product'] ?? '') === $productName) return $r;
        foreach ($rows as $r) { $name = nmNormalizeText($r['name_product'] ?? ''); if ($name !== '' && (mb_strpos($productName, $name) !== false || mb_strpos($name, $productName) !== false)) return $r; }
        if (count($rows) === 1) return $rows[0];
        return false;
    } catch (Throwable $e) {
        error_log('nmProductByNameForPanel failed: ' . $e->getMessage());
        return false;
    }
}

function nmAnyNationalNetEnabled()
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active' AND (national_net_status = 'on_national_net' OR emergency_panel_status = 'on_emergency_panel')");
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

if (!function_exists('nmAdminMaybeInlineKeyboard')) {
function nmAdminMaybeInlineKeyboard(array $keyboard)
{
    global $setting;
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == 'oninline' && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($keyboard);
    }
    return json_encode($keyboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
}

if (!function_exists('nmNationalAdminPanelKeyboard')) {
function nmNationalAdminPanelKeyboard($includeInactive = false)
{
    global $pdo;
    $rows = [];
    try {
        $where = $includeInactive ? '1=1' : "status = 'active'";
        $stmt = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE {$where} ORDER BY id ASC, name_panel ASC");
        $stmt->execute();
        while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim((string)($panel['name_panel'] ?? ''));
            if ($name !== '') {
                $rows[] = [['text' => $name]];
            }
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminPanelKeyboard failed: ' . $e->getMessage());
    }
    if (!$rows) {
        $rows[] = [['text' => '❌ پنلی پیدا نشد']];
    }
    $rows[] = [['text' => '🏠 بازگشت به منوی مدیریت']];
    return nmAdminMaybeInlineKeyboard(['keyboard' => $rows, 'resize_keyboard' => true]);
}
}

if (!function_exists('nmNationalAdminCategoryKeyboard')) {
function nmNationalAdminCategoryKeyboard(array $panel = null, $agent = 'all')
{
    global $pdo;
    $labels = [];
    $backAdminText = $GLOBALS['textbotlang']['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $backMenuText  = $GLOBALS['textbotlang']['Admin']['backmenu']  ?? '▶️ بازگشت به منوی قبل';
    $reservedLabels = [
        'بدون دسته‌بندی',
        $backAdminText,
        $backMenuText,
        '🏠 بازگشت به منوی مدیریت',
        '▶️ بازگشت به منوی قبل',
        '🔙 بازگشت به انبار',
    ];
    $addLabel = static function ($value) use (&$labels, $reservedLabels) {
        $label = function_exists('nmNormalizeText') ? nmNormalizeText($value) : trim((string)$value);
        if ($label === '' || $label === '0') return;
        // Filter out anything that looks like a back/navigation button so it
        // doesn't get rendered as a fake category at the top of the list.
        foreach ($reservedLabels as $reserved) {
            if ($label === $reserved) return;
        }
        if (mb_strpos($label, 'بازگشت', 0, 'UTF-8') !== false) return;
        $labels[$label] = $label;
    };

    try {
        $categoryColumns = [];
        foreach (['remark', 'name', 'title', 'id'] as $column) {
            if (!function_exists('nmTableHasColumn') || nmTableHasColumn('category', $column)) {
                $categoryColumns[] = $column;
            }
        }
        $selectColumns = $categoryColumns ? implode(',', array_map(static function ($column) { return '`' . str_replace('`', '', $column) . '`'; }, $categoryColumns)) : '*';
        $stmt = $pdo->prepare("SELECT {$selectColumns} FROM category ORDER BY id ASC");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'name', 'title', 'id'] as $column) {
                if (!empty($row[$column])) {
                    $addLabel($row[$column]);
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminCategoryKeyboard category scan failed: ' . $e->getMessage());
    }

    try {
        $params = [];
        $sql = "SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND TRIM(category) <> ''";
        if ($panel && !empty($panel['name_panel'])) {
            $sql .= " AND (Location = :loc_where OR Location = '/all')";
            $params[':loc_where'] = $panel['name_panel'];
        }
        $agent = trim((string)($agent ?? ''));
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY category ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($category = $stmt->fetchColumn()) {
            $values = nmCategoryLookupValues($category);
            $addLabel($values ? end($values) : $category);
        }
    } catch (Throwable $e) {
        error_log('nmNationalAdminCategoryKeyboard product scan failed: ' . $e->getMessage());
    }

    $rows = [];
    foreach (array_values($labels) as $label) {
        $rows[] = [['text' => $label]];
    }
    $rows[] = [['text' => 'بدون دسته‌بندی']];
    $rows[] = [['text' => '🏠 بازگشت به منوی مدیریت']];
    return nmAdminMaybeInlineKeyboard(['keyboard' => $rows, 'resize_keyboard' => true]);
}
}

/**
 * Robustly resolve an active panel from a (possibly decorated) button label.
 * Tries: exact name -> style-emoji-stripped name -> normalized fuzzy match
 * against all active panels. Returns the panel row or false.
 * Prevents the national-net shelf flow from getting stuck on panel selection
 * (which would stop the admin from ever reaching the category step).
 */
if (!function_exists('nmResolveActivePanelByName')) {
function nmResolveActivePanelByName($name)
{
    global $pdo;
    $name = trim((string)$name);
    if ($name === '') return false;
    $p = select("marzban_panel", "*", "name_panel", $name, "select");
    if ($p) return $p;
    if (function_exists('stripReplyStyleEmoji')) {
        $alt = trim((string)stripReplyStyleEmoji($name));
        if ($alt !== '' && $alt !== $name) {
            $p = select("marzban_panel", "*", "name_panel", $alt, "select");
            if ($p) return $p;
        }
    }
    if (!($pdo instanceof PDO)) return false;
    try {
        $needle = function_exists('nmNormalizeText') ? nmNormalizeText($name) : $name;
        $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE status = 'active'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cand = function_exists('nmNormalizeText') ? nmNormalizeText($row['name_panel'] ?? '') : trim((string)($row['name_panel'] ?? ''));
            if ($cand === '') continue;
            if ($cand === $needle || mb_strpos($needle, $cand) !== false || mb_strpos($cand, $needle) !== false) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        error_log('nmResolveActivePanelByName failed: ' . $e->getMessage());
    }
    return false;
}
}

/**
 * Count the real (non-navigation) categories that the admin category keyboard
 * would show for a given panel scope. Used for diagnostics so we can tell
 * whether "no categories" is a data problem or a flow problem.
 */
if (!function_exists('nmAdminCategoryCount')) {
function nmAdminCategoryCount(array $panel = null)
{
    global $pdo;
    if (!($pdo instanceof PDO)) return 0;
    $labels = [];
    $add = static function ($v) use (&$labels) {
        $l = function_exists('nmNormalizeText') ? nmNormalizeText($v) : trim((string)$v);
        if ($l === '' || $l === '0' || $l === 'بدون دسته‌بندی') return;
        if (mb_strpos($l, 'بازگشت', 0, 'UTF-8') !== false) return;
        $labels[$l] = true;
    };
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['remark', 'name', 'title', 'id'] as $c) {
                if (!empty($row[$c])) { $add($row[$c]); break; }
            }
        }
    } catch (Throwable $e) {}
    try {
        $sql = "SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND TRIM(category) <> ''";
        $params = [];
        if ($panel && !empty($panel['name_panel'])) { $sql .= " AND (Location = :l OR Location = '/all')"; $params[':l'] = $panel['name_panel']; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($c = $stmt->fetchColumn()) { $add($c); }
    } catch (Throwable $e) {}
    return count($labels);
}
}

function nmNationalBuyCategoryKeyboard(array $panel, $agent, $back = 'buybacktow')
{
    if (function_exists('KeyboardCategory')) {
        return KeyboardCategory($panel['name_panel'] ?? '', $agent, $back);
    }
    return json_encode(['inline_keyboard' => [[['text' => '▶️ بازگشت به منوی قبل', 'callback_data' => $back]]]], JSON_UNESCAPED_UNICODE);
}

function nmStockAdminPanelStatusText(array $panel)
{
    $emergency = nmPanelEmergencyEnabled($panel) ? '✅ روشن' : '❌ خاموش';
    $national = nmPanelNationalEnabled($panel) ? '✅ روشن' : '❌ خاموش';
    $stockCode = nmPanelResolveStockCode($panel);
    $syncCount = count(nmStockPanelProducts($stockCode, 'all'));
    $text = '⚙️ وضعیت اضطراری و نت ملی پنل' . "\n\n";
    $text .= '🚨 پنل اضطراری: ' . $emergency . "\n";
    $text .= '🌐 وضعیت نت ملی: ' . $national . "\n";
    $emPanel = nmPanelEmergencyPanel($panel);
    $text .= '📌 پنل اضطراری متصل: ' . ($emPanel ? htmlspecialchars($emPanel['name_panel'], ENT_QUOTES, 'UTF-8') : 'تنظیم نشده') . "\n";
    $text .= '📦 کد انبار متصل: <code>' . htmlspecialchars((string)$stockCode, ENT_QUOTES, 'UTF-8') . '</code>' . "\n";
    $text .= '🛒 محصولات قابل همگام‌سازی: ' . (string)$syncCount . "\n\n";
    $text .= 'وقتی نت ملی روشن باشد، خرید جدید از انبار تحویل می‌شود. وقتی پنل اصلی قطع شود و پنل اضطراری روشن باشد، تمدید از پنل اضطراری یا انبار پشتیبان انجام می‌شود.';
    return $text;
}


function nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id)
{
    if (!function_exists('createServiceInfoCard')) {
        return null;
    }
    global $ManagePanel, $setting;
    try {
        if (!isset($ManagePanel) || !is_object($ManagePanel) || !method_exists($ManagePanel, 'DataUser')) {
            return null;
        }
        $name_panel = is_array($panel_info) ? ($panel_info['name_panel'] ?? '') : '';
        if ($name_panel === '') {
            return null;
        }
        $data = @$ManagePanel->DataUser($name_panel, $username_service);
        if (!is_array($data) || (isset($data['status']) && $data['status'] === 'Unsuccessful')) {
            return null;
        }
        $used  = (float)($data['used_traffic'] ?? 0);
        $total = (float)($data['data_limit']   ?? 0);
        $expire = $data['expire'] ?? 0;
        $unlimitedTime = empty($expire);
        $daysLeft = 0;
        if (!$unlimitedTime) {
            $diff = (int)$expire - time();
            $daysLeft = max(0, (int) floor($diff / 86400));
        }
        $statusVal = (string)($data['status'] ?? 'active');
        $isActive = in_array($statusVal, ['active', 'on_hold'], true);


        $botUsername = '';
        if (is_array($setting ?? null)) {
            foreach (['bot_username', 'username_bot', 'usernamebot', 'BotUsername', 'bot_user'] as $k) {
                if (isset($setting[$k]) && is_string($setting[$k]) && trim($setting[$k]) !== '') {
                    $botUsername = ltrim((string)$setting[$k], '@');
                    break;
                }
            }
        }
        if ($botUsername === '' && function_exists('telegram')) {
            $me = @telegram('getMe', []);
            if (is_array($me) && isset($me['result']['username'])) {
                $botUsername = (string)$me['result']['username'];
            }
        }

        $color = function_exists('getInfoCardColor') ? getInfoCardColor() : 'yellow';
        $params = [
            'config_name'    => (string)$username_service,
            'bot_username'   => $botUsername,
            'user_id'        => (string)$user_id,
            'active'         => $isActive,
            'used_bytes'     => $used,
            'total_bytes'    => $total,
            'days_left'      => $daysLeft,
            'unlimited_time' => $unlimitedTime,
        ];
        $outPath = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
            . DIRECTORY_SEPARATOR . 'infocard_' . $user_id . '_' . bin2hex(random_bytes(3)) . '.png';
        $written = createServiceInfoCard($params, $color, $outPath);
        if ($written === false) {
            return null;
        }
        return $written;
    } catch (\Throwable $e) {
        error_log('nm_renderInfoCardForInvoice failed: ' . $e->getMessage());
        return null;
    }
}