<?php

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
    $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:loc_where, Location) > 0 OR Location = '/all')";
    if ($filterAgent) {
        $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $params[':agent'] = $agent;
    }

    $catValues = nmCategoryLookupValues($category);
    if ($catValues) {
        [$catSql, $catParams] = nmBuildFindInSetClause('category', $catValues, 'cat');
        if ($catSql !== '') {
            $sql .= " AND ({$catSql}";
            $params += $catParams;
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
    }
    $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:loc_order, Location) > 0 THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows && $catValues) {
            $fallbackParams = [
                ':fallback_loc_where' => $loc,
                ':fallback_loc_order' => $loc,
            ];
            $fallbackSql = "SELECT * FROM product WHERE (FIND_IN_SET(:fallback_loc_where, Location) > 0 OR Location = '/all')";
            if ($filterAgent) {
                $fallbackSql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $fallbackParams[':agent'] = $agent;
            }
            $fallbackSql .= " ORDER BY CASE WHEN FIND_IN_SET(:fallback_loc_order, Location) > 0 THEN 0 ELSE 1 END, CAST(price_product AS UNSIGNED) ASC, id DESC";
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
                  AND (FIND_IN_SET(:location_where, Location) > 0 OR Location = '/all')";
        $params = [
            ':code_product' => $codeProduct,
            ':location_where' => $panelName,
            ':location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (FIND_IN_SET(:agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':agent_where'] = $agent;
            $params[':agent_order'] = $agent;
            $sql .= " ORDER BY
                        CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END,
                        CASE WHEN agent = :agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END,
                        id ASC
                      LIMIT 1";
        } else {
            $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END, id ASC LIMIT 1";
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
        $sql = "SELECT * FROM product WHERE name_product = :name_product AND (FIND_IN_SET(:location_where, Location) > 0 OR Location = '/all')";
        $catValues = nmCategoryLookupValues($category);
        if ($catValues) {
            [$catSql, $catParams] = nmBuildFindInSetClause('category', $catValues, 'cat');
            if ($catSql !== '') {
                $sql .= " AND ({$catSql}";
                $params += $catParams;
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
        }
        $agent = trim((string)($agent ?? ''));
        if (!in_array($agent, ['', 'all', '*', 'any'], true)) {
            $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':agent'] = $agent;
        }
        $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:location_order, Location) > 0 THEN 0 ELSE 1 END LIMIT 1";
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

function nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id)
{
    if (!function_exists('createServiceInfoCard')) {
        return null;
    }
    if (!function_exists('getInfoCardStatus') || !getInfoCardStatus()) {
        return null;
    }
    if (is_array($panel_info) && ($panel_info['type'] ?? '') === 'Manualsale') {
        return null;
    }
    if (is_array($panel_info) && function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel_info)) {
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

function nm_sendServiceQrFallback($user_id, string $qrPayload, string $backgroundImage = 'images.jpg', string $caption = '', $replyMarkup = null): bool
{
    if (function_exists('isQrDisabled') && isQrDisabled()) {
        return false;
    }
    if (!function_exists('createqrcode') || !function_exists('telegram')) {
        return false;
    }
    $qrPayload = trim($qrPayload);
    if ($qrPayload === '') {
        return false;
    }
    try {
        $qrCode = createqrcode($qrPayload);
        if ($qrCode === null) {
            return false;
        }
        $urlimage = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
            . DIRECTORY_SEPARATOR . $user_id . bin2hex(random_bytes(3)) . '.png';
        file_put_contents($urlimage, $qrCode->getString());
        if (function_exists('addBackgroundImage')) {
            @addBackgroundImage($urlimage, $qrCode, $backgroundImage);
        }
        telegram('sendphoto', [
            'chat_id'    => $user_id,
            'photo'      => new CURLFile($urlimage),
            'caption'    => $caption !== '' ? $caption : '📥 کیو‌آر کد',
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup,
        ]);
        @unlink($urlimage);
        return true;
    } catch (Throwable $e) {
        error_log('nm_sendServiceQrFallback failed: ' . $e->getMessage());
        return false;
    }
}

if (!function_exists('rx_cleanEmojiAndSymbols')) {
    function rx_cleanEmojiAndSymbols($str): string {
        $str = (string)$str;
        $str = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2000}-\x{32FF}\x{FE00}-\x{FE0F}\x{200D}\x{200C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $str);
        $str = str_replace(["ي", "ك", "ة", "ۀ"], ["ی", "ک", "ه", "ه"], $str);
        $str = preg_replace('/\s+/u', ' ', $str);
        $str = trim($str);
        return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
    }
}

if (!function_exists('rx_resolvePanelFromInput')) {
    function rx_resolvePanelFromInput($input, $pdo = null) {
        if (!is_string($input) || trim($input) === '') {
            return null;
        }
        $rawInput = trim($input);
        if ($rawInput === '/all') {
            return ['code_panel' => '/all', 'name_panel' => '/all', 'type' => 'all'];
        }
        if (function_exists('rx_restorePremiumReplyText')) {
            $restored = rx_restorePremiumReplyText($rawInput);
            if ($restored !== '' && $restored !== $rawInput) {
                if (function_exists('select')) {
                    $direct = select("marzban_panel", "*", "name_panel", $restored, "select");
                    if (is_array($direct) && !empty($direct)) {
                        return $direct;
                    }
                }
            }
        }
        if (function_exists('select')) {
            $direct = select("marzban_panel", "*", "name_panel", $rawInput, "select");
            if (is_array($direct) && !empty($direct)) {
                return $direct;
            }
        }
        if ($pdo === null) {
            global $pdo;
        }
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1");
            $stmt->execute([$rawInput]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row)) {
                return $row;
            }
            $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
            $allPanels = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!is_array($allPanels) || empty($allPanels)) {
                return null;
            }
            $cleanInput = rx_cleanEmojiAndSymbols($rawInput);
            if ($cleanInput !== '') {
                foreach ($allPanels as $p) {
                    if (!isset($p['name_panel'])) continue;
                    $cleanP = rx_cleanEmojiAndSymbols($p['name_panel']);
                    if ($cleanP === $cleanInput) {
                        return $p;
                    }
                }
                foreach ($allPanels as $p) {
                    if (!isset($p['name_panel'])) continue;
                    $cleanP = rx_cleanEmojiAndSymbols($p['name_panel']);
                    if ($cleanP !== '' && ($cleanP === $cleanInput || strpos($cleanP, $cleanInput) !== false || strpos($cleanInput, $cleanP) !== false)) {
                        return $p;
                    }
                }
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }
}

if (!function_exists('rx_normalizeAdminButtonText')) {
    function rx_normalizeAdminButtonText($text) {
        if (!is_string($text) || trim($text) === '') {
            return $text;
        }
        $raw = trim($text);
        if (function_exists('rx_restorePremiumReplyText')) {
            $restored = rx_restorePremiumReplyText($raw);
            if ($restored !== '' && $restored !== $raw) {
                $raw = trim($restored);
            }
        }
        global $textbotlang;
        $canonicalBackAdmin = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
        $canonicalBackMenu  = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        $clean = rx_cleanEmojiAndSymbols($raw);
        if ($clean === '') {
            return $raw;
        }

        if (in_array($clean, [
            'بازگشت به منوی قبل', 'بازگشت به منو قبل', 'بازگشت به قبل', 'منوی قبل', 'بازگشت', 'منو قبل'
        ], true)) {
            return $canonicalBackMenu;
        }
        if (in_array($clean, [
            'بازگشت به منوی مدیریت', 'بازگشت به منوی اصلی', 'بازگشت به منو اصلی', 'بازگشت به خانه',
            'بازگشت به ادمین', 'بازگشت به پنل مدیریت', 'بازگشت به پنل ادمین', 'منوی مدیریت', 'منوی اصلی', 'خانه'
        ], true)) {
            return $canonicalBackAdmin;
        }

        static $adminMap = null;
        if ($adminMap === null) {
            $targets = [
                'admin_status'          => $textbotlang['Admin']['Status']['btn'] ?? "📊 وضعیت سرور",
                'admin_managepanel'     => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] ?? "مدیریت پنل",
                'admin_addpanel'        => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] ?? "افزودن پنل",
                'admin_timeprice'       => "⏳ قیمت سریع زمان",
                'admin_volprice'        => "🔋 قیمت سریع حجم",
                'admin_users'           => $textbotlang['Admin']['btnkeyboardadmin']['managruser'] ?? "مدیریت کاربر",
                'admin_shop'            => "🏬 تنظیمات فروشگاه",
                'admin_finance'         => "💎 مالی و گزارشات",
                'admin_support'         => "🤙 بخش پشتیبانی",
                'admin_help'            => "📚 بخش آموزش",
                'admin_features'        => "🛠 قابلیت های پنل",
                'admin_settings'        => "⚙️ تنظیمات فنی و ربات",
                'admin_invoices'        => "💵 رسید های تایید نشده",
                'admin_panels'          => "📁 مدیریت پنل‌ها و سرورها",
                'admin_channelhub'      => "📢 کانال و اطلاع‌رسانی",
                'admin_usershub'        => "👥 مدیریت کاربران",
                'set_features'          => "⚙️ وضعیت قابلیت ها",
                'set_reports'           => "📣 گزارشات ربات",
                'set_channel'           => "📯 تنظیمات کانال",
                'set_webpanel'          => "✅ پنل تحت وب",
                'set_optimize'          => "🗑 بهینه سازی ربات",
                'set_text'              => "📝 تنظیم متن ربات",
                'set_adminmgr'          => "👨‍🔧 بخش ادمین",
                'set_testlimit'         => "➕ محدودیت تست برای همه",
                'set_agentprice'        => "💰 عضویت نمایندگی",
                'set_qrsettings'        => "📷 تنظیمات کیو آر کد",
                'set_qrbg'              => "🖼 پس‌زمینه کیوآرکد",
                'set_qr_toggle'         => "🔄 وضعیت کیوآرکد",
                'set_webhook'           => "🔗 وبهوک ربات‌های نماینده",
                'shop_status'           => "🛒 وضعیت قابلیت های فروشگاه",
                'shop_category'         => "🗂 مدیریت دسته‌بندی",
                'shop_products'         => "🛍 مدیریت محصولات",
                'shop_giftadd'          => "🎁 ساخت کد هدیه",
                'shop_giftdel'          => "❌ حذف کد هدیه",
                'shop_discountadd'      => "🎁 ساخت کد تخفیف",
                'shop_discountdel'      => "❌ حذف کد تخفیف",
                'shop_minbulk'          => "⬇️ کف خرید عمده",
                'shop_renewcb'          => "🎁 کش بک تمدید",
                'cat_add'               => "🛒 افزودن دسته‌بندی",
                'cat_del'               => "❌ حذف دسته بندی",
                'cat_edit'              => "✏️ ویرایش دسته بندی",
                'cat_back'              => "⬅️ بازگشت به منوی فروشگاه",
                'shopitem_add'          => "🛍 افزودن محصول",
                'shopitem_del'          => "❌ حذف محصول",
                'shopitem_edit'         => "✏️ ویرایش محصول",
                'shopitem_priceinc'     => "⬆️ افزایش قیمت",
                'shopitem_pricedec'     => "⬇️ کاهش گروهی قیمت",
                'shopitem_back'         => "⬅️ بازگشت به منوی فروشگاه",
                'cart_title'            => "🏷️ نام نمایشی درگاه کارت به کارت",
                'cart_setnum'           => "💳 شماره کارت",
                'cart_delnum'           => "❌ حذف شماره کارت",
                'cart_support'          => "👤 آیدی پشتیبانی",
                'cart_pvmode'           => "💳 آفلاین در پیوی",
                'cart_cashback'         => "💰 کش‌بک کارت",
                'cart_firstpay'         => "🔒 کارت پس از اولین پرداخت",
                'cart_min'              => "⬇️ کف کارت به کارت",
                'cart_max'              => "⬆️ سقف کارت به کارت",
                'cart_edu'              => "📚 آموزش کارت به کارت",
                'cart_cvmin'            => "🔑 حداقل مبلغ احراز کارت",
                'cart_hide_num'         => "💰  غیرفعالسازی  نمایش شماره کارت",
                'cart_show_num'         => "💰 فعالسازی نمایش شماره کارت",
                'cart_manage'           => "💳 مدیریت شماره کارت",
                'atlaspay_name'         => "🏷️ نام نمایشی درگاه اطلس‌پی",
                'atlaspay_apikey'       => "🔑 ثبت API Key اطلس‌پی",
                'atlaspay_account'      => "📊 موجودی و اطلاعات حساب",
                'atlaspay_cashback'     => "💰 کش بک اطلس‌پی",
                'atlaspay_min'          => "⬇️ کف اطلس‌پی",
                'atlaspay_max'          => "⬆️ سقف اطلس‌پی",
                'atlaspay_edu'          => "📚 آموزش اطلس‌پی",
                'zpal_name'             => "🏷️ نام نمایشی درگاه زرین پال",
                'zpal_merchant'         => "مرچنت زرین پال",
                'zpal_cashback'         => "💰 کش بک زرین پال",
                'zpal_min'              => "⬇️ کف زرین پال",
                'zpal_max'              => "⬆️ سقف زرین پال",
                'zpal_edu'              => "📚 آموزش زرین پال",
                'zpey_name'             => "🗂 درگاه زرین پی",
                'zpey_token'            => "🔑 توکن زرین پی",
                'zpey_cashback'         => "💰 کش بک زرین پی",
                'zpey_tutorial'         => "🧑🏼‍💻 اموزش اتصال",
                'zpey_min'              => "⬇️ کف زرین پی",
                'zpey_max'              => "⬆️ سقف زرین پی",
                'zpey_edu'              => "📚 آموزش زرین پی",
                'aqaye_name'            => "🗂 نام درگاه آقای پرداخت",
                'aqaye_merchant'        => "مرچنت آقای پرداخت",
                'aqaye_cashback'        => "💰 کش‌بک آقای‌پرداخت",
                'aqaye_min'             => "⬇️ کف آقای پرداخت",
                'aqaye_max'             => "⬆️ سقف آقای پرداخت",
                'aqaye_edu'             => "📚 آموزش درگاه اقای پرداخت",
                'plisio_name'           => "🏷️ نام نمایشی درگاه plisio",
                'plisio_api'            => "🧩 api plisio",
                'plisio_cashback'       => "💰 کش بک plisio",
                'plisio_min'            => "⬇️ کف plisio",
                'plisio_max'            => "⬆️ سقف plisio",
                'plisio_edu'            => "📚 آموزش plisio",
                'help_add'              => "📚 افزودن آموزش",
                'help_del'              => "❌ حذف آموزش",
                'help_edit'             => "✏️ ویرایش آموزش",
                'ch_add'                => "اضافه کردن کانال",
                'ch_del'                => "حذف کانال",
                'ch_list'               => "📯 تنظیمات کانال",
                'p_status'              => "⚙️ وضعیت قابلیت ها پنل",
                'p_type'                => "🔄 تغییر نوع پنل",
                'p_api_mode'            => "🔄 تغییر حالت API",
                'p_name'                => "✍️ نام پنل",
                'p_del'                 => "❌ حذف پنل",
                'p_key'                 => "🔐 ویرایش کلید",
                'p_pass'                => "🔐 ویرایش رمز عبور",
                'p_uname'               => "👤 ویرایش نام",
                'p_conn'                => "⁉️ اتصال به پنل",
                'p_url'                 => "🔗 ویرایش آدرس پنل",
                'p_inbound_proto'       => "⚙️ پروتکل اینباند",
                'p_inbound_id'          => "💎 شناسه اینباند",
                'p_services'            => "⚙️ تنظیم سرویس ها",
                'p_def_svc'             => "⚙️ سرویس پیش‌فرض",
                'p_renew_method'        => "🔋 روش تمدید سرویس",
                'p_gen_uname'           => "💡 ساخت نام کاربری",
                'p_subdomain'           => "🔗 دامنه لینک ساب",
                'p_limit'               => "🚨 محدودیت اکانت",
                'p_group'               => "📍 تغییر گروه",
                'p_test_time'           => "⏳ زمان سرویس تست",
                'p_test_vol'            => "💾 حجم اکانت تست",
                'p_cvol_price'          => "⚙️ قیمت حجم دلخواه",
                'p_xvol_price'          => "➕ قیمت حجم اضافه",
                'p_xtime_price'         => "⏳ قیمت زمان اضافه",
                'p_ctime_price'         => "⏳ قیمت زمان دلخواه",
                'p_chloc_price'         => "🌍 قیمت تغییر مکان",
                'p_min_cvol'            => "📍 کف حجم دلخواه",
                'p_max_cvol'            => "📍 سقف حجم دلخواه",
                'p_min_ctime'           => "📍 کف زمان دلخواه",
                'p_max_ctime'           => "📍 سقف زمان دلخواه",
                'p_inbound_deact'       => "⚙️  اینباند اکانت غیرفعال",
                'p_netmelli'            => "🌐 وضعیت نت ملی",
                'p_hide_panel'          => "🫣 مخفی پنل برای کاربر",
                'p_unhide_panel'        => "❌ حذف از لیست مخفی",
                'p_channel_back'        => "🔙 بازگشت به لیست کانال‌ها",
                'star_title'            => "🏷️ نام نمایشی درگاه استار",
                'star_cb'               => "💰 کش بک استار",
                'star_edu'              => "📚 آموزش استار",
                'star_min'              => "⬇️ کف استار",
                'star_max'              => "⬆️ سقف استار",
                'nowp_title'            => "🏷️ نام نمایشی درگاه nowpayment",
                'nowp_cb'               => "💰 کش بک nowpayment",
                'nowp_edu'              => "📚 آموزش nowpayment",
                'nowp_min'              => "⬇️ کف nowpayment",
                'nowp_max'              => "⬆️ سقف nowpayment",
                'nowp_api'              => "API NOWPAYMENT",
                'nowp_ipn'              => "🔐 IPN Secret nowpayment",
                'limit_free'            => "🆓 محدودیت رایگان",
                'limit_all'             => "↙️ محدودیت کلی",
                'limit_reset'           => "🔄 ریست محدودیت کل کاربران",
                'auto_cart_back'        => "◀️ بازگشت به تنظیمات پیشرفته",
                'seller_users'          => "👤 مدیریت کاربر",
                'support_search'        => "👁‍🗨 جستجو کاربر",
                'feat_info'             => "قابلیت مشاهده اطلاعات اکانت",
                'feat_test'             => "قابلیت اکانت تست",
                'feat_help'             => "قابلیت آموزش",
            ];
            $adminMap = [];
            foreach ($targets as $k => $canonicalStr) {
                $cleaned = rx_cleanEmojiAndSymbols($canonicalStr);
                if ($cleaned !== '') {
                    $adminMap[$cleaned] = $canonicalStr;
                }
            }
        }

        if (isset($adminMap[$clean])) {
            return $adminMap[$clean];
        }
        return $raw;
    }
}
