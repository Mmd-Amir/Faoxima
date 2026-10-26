<?php

function rx_sr_label()
{
    return '🚦 آمادگی فروش';
}

function rx_sr_pdo()
{
    $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : null;
    if (!$pdo instanceof PDO && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }
    return $pdo instanceof PDO ? $pdo : null;
}

function rx_sr_fa($value)
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function rx_sr_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rx_sr_defs()
{
    return [
        1 => ['label' => 'پنل فروش', 'route' => [['🛠 مدیریت پنل‌ها', 'admin_managepanel'], ['➕ افزودن پنل', 'admin_addpanel']]],
        2 => ['label' => 'اتصال پنل', 'route' => [['🛠 مدیریت پنل‌ها', 'admin_managepanel']]],
        3 => ['label' => 'پلن و قیمت', 'route' => [['🛍 مدیریت محصولات', 'shop_products'], ['🔋 قیمت سریع حجم', 'admin_volprice'], ['⏳ قیمت سریع زمان', 'admin_timeprice']]],
        4 => ['label' => 'تنظیمات تحویل', 'route' => [['🛠 مدیریت پنل‌ها', 'admin_managepanel']]],
        5 => ['label' => 'دریافت پرداخت', 'route' => [['💎 مالی و گزارشات', 'admin_finance']]],
        6 => ['label' => 'دسترسی مشتری', 'route' => [['⚙️ وضعیت قابلیت‌ها', 'set_features']]],
    ];
}

function rx_sr_st($status, $message)
{
    return ['s' => $status, 'm' => (string) $message];
}

function rx_sr_blank($value)
{
    $value = trim((string) $value);
    return $value === '' || $value === '0' || strtolower($value) === 'null' || $value === 'تنظیم نشده';
}

function rx_sr_json_array($raw)
{
    if (is_array($raw)) {
        return $raw;
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function rx_sr_agent_value($raw, $agent = 'f')
{
    $data = rx_sr_json_array($raw);
    return array_key_exists($agent, $data) ? $data[$agent] : null;
}

function rx_sr_positive($value)
{
    return is_numeric($value) && (float) $value > 0;
}

function rx_sr_known_types()
{
    return ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'Manualsale', 'WGDashboard', 'remnawave', 'rebecca'];
}

function rx_sr_manual_stock(PDO $pdo, array $panel)
{
    $stmt = $pdo->prepare("SELECT codeproduct, COUNT(*) AS c, SUM(CASE WHEN contentrecord <> '' THEN 1 ELSE 0 END) AS filled FROM manualsell WHERE codepanel = ? AND status = 'active' GROUP BY codeproduct");
    $stmt->execute([(string) ($panel['code_panel'] ?? '')]);
    $map = [];
    $total = 0;
    $filled = 0;
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $map[(string) $row['codeproduct']] = (int) $row['filled'];
        $total += (int) $row['c'];
        $filled += (int) $row['filled'];
    }
    return ['map' => $map, 'total' => $total, 'filled' => $filled];
}

function rx_sr_stage_panel(array $panel)
{
    if (function_exists('panel_creation_limit_reached_unlocked')) {
        try {
            if (panel_creation_limit_reached_unlocked($panel)) {
                return rx_sr_st('fail', 'سقف تعداد سرویس این پنل پر شده و برای مشتری عادی نمایش داده نمی‌شود.');
            }
        } catch (Throwable $e) {
        }
    }
    return rx_sr_st('ok', 'پنل فعال است و در لیست خرید مشتری عادی قرار می‌گیرد.');
}

function rx_sr_stage_connection(PDO $pdo, array $panel, $live, $deadline)
{
    $type = (string) ($panel['type'] ?? '');
    if ($type === 'Manualsale') {
        $stock = rx_sr_manual_stock($pdo, $panel);
        if ($stock['filled'] > 0) {
            return rx_sr_st('ok', 'فروش دستی: ' . rx_sr_fa($stock['filled']) . ' کانفیگ فعال در موجودی ثبت شده است.');
        }
        return rx_sr_st('fail', 'فروش دستی: هیچ کانفیگ فعال و دارای محتوا در موجودی وجود ندارد.');
    }
    if (!in_array($type, rx_sr_known_types(), true)) {
        return rx_sr_st('fail', 'نوع پنل («' . $type . '») توسط ربات پشتیبانی نمی‌شود.');
    }
    if (!$live) {
        return rx_sr_st('pending', 'بررسی زنده انجام نشد.');
    }
    if (microtime(true) > $deadline) {
        return rx_sr_st('pending', 'به‌دلیل محدودیت زمان، اتصال این پنل بررسی نشد؛ «بررسی مجدد» را بزنید.');
    }
    if (!function_exists('rx_panel_capability_report')) {
        $capFile = __DIR__ . '/panel_capability.php';
        if (is_file($capFile)) {
            require_once $capFile;
        }
    }
    if (!function_exists('rx_panel_capability_report')) {
        return rx_sr_st('pending', 'ماژول بررسی اتصال پنل در دسترس نیست.');
    }
    try {
        $report = rx_panel_capability_report($panel);
    } catch (Throwable $e) {
        return rx_sr_st('pending', 'بررسی اتصال با خطای داخلی متوقف شد: ' . rx_cap_clean_text($e->getMessage(), 100));
    }
    $check = $report['checks']['connection'] ?? ['status' => 'unknown', 'note' => ''];
    $note = (string) ($check['note'] ?? '');
    $status = (string) ($check['status'] ?? 'unknown');
    if ($status === 'verified' || $status === 'allowed') {
        return rx_sr_st('ok', 'ورود و احراز هویت موفق بود' . ($note !== '' ? ' (' . $note . ')' : '.'));
    }
    if ($status === 'failed' || $status === 'denied') {
        return rx_sr_st('fail', 'اتصال یا احراز هویت ناموفق: ' . ($note !== '' ? $note : 'پاسخ معتبر از پنل دریافت نشد.'));
    }
    if ($status === 'unsupported') {
        return rx_sr_st('pending', 'این نوع پنل بررسی خودکار اتصال ندارد.');
    }
    return rx_sr_st('pending', 'نتیجه اتصال قطعی نشد' . ($note !== '' ? ': ' . $note : '.'));
}

function rx_sr_custom_offer(array $panel, $agent = 'f')
{
    if ((string) ($panel['type'] ?? '') === 'Manualsale') {
        return false;
    }
    if ((string) rx_sr_agent_value($panel['customvolume'] ?? '', $agent) !== '1') {
        return false;
    }
    if (!rx_sr_positive(rx_sr_agent_value($panel['pricecustomvolume'] ?? '', $agent))
        || !rx_sr_positive(rx_sr_agent_value($panel['pricecustomtime'] ?? '', $agent))) {
        return false;
    }
    $minVol = rx_sr_agent_value($panel['mainvolume'] ?? '', $agent);
    $maxVol = rx_sr_agent_value($panel['maxvolume'] ?? '', $agent);
    $minTime = rx_sr_agent_value($panel['maintime'] ?? '', $agent);
    $maxTime = rx_sr_agent_value($panel['maxtime'] ?? '', $agent);
    foreach ([$minVol, $maxVol, $minTime, $maxTime] as $bound) {
        if (!is_numeric($bound) || (float) $bound < 0) {
            return false;
        }
    }
    return (float) $maxVol > 0 && (float) $maxVol >= (float) $minVol && (float) $maxTime > 0 && (float) $maxTime >= (float) $minTime;
}

function rx_sr_product_allows_agent(array $product, $agent = 'f')
{
    if (function_exists('rx_product_allows_agent')) {
        return rx_product_allows_agent($product, $agent);
    }
    $values = array_map('trim', explode(',', str_replace(' ', '', (string) ($product['agent'] ?? ''))));
    return in_array($agent, $values, true) || in_array('all', $values, true) || in_array('allusers', $values, true);
}

function rx_sr_evaluate_offers(PDO $pdo, array $panel, $agent = 'f')
{
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all')");
    $stmt->execute([':loc' => (string) ($panel['name_panel'] ?? '')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $isManual = (string) ($panel['type'] ?? '') === 'Manualsale';
    $stock = $isManual ? rx_sr_manual_stock($pdo, $panel) : null;
    $nationalPanel = function_exists('nmPanelNationalEnabled') && function_exists('nmStockHasAvailableForProduct') && nmPanelNationalEnabled($panel);
    $reasons = ['agent' => 0, 'hidden' => 0, 'price' => 0, 'data' => 0, 'stock' => 0];
    $valid = [];
    foreach ($rows as $product) {
        if (!rx_sr_product_allows_agent($product, $agent)) {
            $reasons['agent']++;
            continue;
        }
        $hidden = rx_sr_json_array($product['hide_panel'] ?? '');
        if (in_array((string) ($panel['name_panel'] ?? ''), array_map('strval', $hidden), true)) {
            $reasons['hidden']++;
            continue;
        }
        if (trim((string) ($product['name_product'] ?? '')) === '') {
            $reasons['data']++;
            continue;
        }
        if (!rx_sr_positive($product['price_product'] ?? null)) {
            $reasons['price']++;
            continue;
        }
        if (!is_numeric($product['Volume_constraint'] ?? null) || (float) $product['Volume_constraint'] < 0
            || !is_numeric($product['Service_time'] ?? null) || (float) $product['Service_time'] < 0) {
            $reasons['data']++;
            continue;
        }
        if ($isManual && empty($stock['map'][(string) ($product['code_product'] ?? '')])) {
            $reasons['stock']++;
            continue;
        }
        if ($nationalPanel && !nmStockHasAvailableForProduct($panel, $product)) {
            $reasons['stock']++;
            continue;
        }
        $valid[] = $product;
    }
    $custom = rx_sr_custom_offer($panel, $agent);
    return ['assigned' => count($rows), 'valid' => $valid, 'custom' => $custom, 'reasons' => $reasons];
}

function rx_sr_offer_stage(array $offers, array $panel)
{
    $count = count($offers['valid']);
    if ($count > 0 || $offers['custom']) {
        $parts = [];
        if ($count > 0) {
            $parts[] = rx_sr_fa($count) . ' پلن قابل خرید';
        }
        if ($offers['custom']) {
            $parts[] = 'خرید حجم/زمان دلخواه فعال با قیمت معتبر';
        }
        return rx_sr_st('ok', implode(' و ', $parts) . '.');
    }
    if ($offers['assigned'] === 0) {
        $msg = 'هیچ محصولی به این پنل اختصاص داده نشده است.';
    } else {
        $why = [];
        $labels = ['agent' => 'مخصوص گروه دیگری از کاربران', 'hidden' => 'برای این پنل مخفی', 'price' => 'قیمت نامعتبر یا صفر', 'data' => 'حجم/زمان/نام نامعتبر', 'stock' => ((string) ($panel['type'] ?? '') === 'Manualsale' ? 'بدون موجودی کانفیگ' : 'بدون موجودی')];
        foreach ($labels as $key => $label) {
            if ($offers['reasons'][$key] > 0) {
                $why[] = rx_sr_fa($offers['reasons'][$key]) . ' مورد ' . $label;
            }
        }
        $msg = rx_sr_fa($offers['assigned']) . ' محصول به این پنل اختصاص دارد اما هیچ‌کدام برای مشتری عادی قابل خرید نیست (' . implode('، ', $why) . ').';
    }
    if ((string) ($panel['type'] ?? '') !== 'Manualsale' && (string) rx_sr_agent_value($panel['customvolume'] ?? '', 'f') === '1') {
        $msg .= ' خرید حجم دلخواه روشن است ولی قیمت/محدوده حجم یا زمان آن معتبر نیست.';
    }
    return rx_sr_st('fail', $msg);
}

function rx_sr_products_override(array $offers, $needCustom)
{
    if ($needCustom || empty($offers['valid'])) {
        return false;
    }
    foreach ($offers['valid'] as $product) {
        if (empty(rx_sr_json_array($product['inbounds'] ?? ''))) {
            return false;
        }
    }
    return true;
}

function rx_sr_stage_delivery(array $panel, array $offers)
{
    $type = (string) ($panel['type'] ?? '');
    if ($type === 'Manualsale') {
        return rx_sr_st('ok', 'تحویل از محتوای کانفیگ‌های موجودی ثبت‌شده انجام می‌شود.');
    }
    $missing = [];
    $notes = [];
    $override = rx_sr_products_override($offers, $offers['custom']);
    $inbounds = rx_sr_json_array($panel['inbounds'] ?? '');
    if ($type === 'marzban' || $type === 'pasarguard') {
        $isGroups = $type === 'pasarguard' || (string) ($panel['version_panel'] ?? '0') === '1';
        if ($isGroups) {
            if (empty($inbounds) && !$override) {
                $missing[] = 'گروه‌های ساخت کاربر (Group) تنظیم نشده است';
            }
        } else {
            $proxies = rx_sr_json_array($panel['proxies'] ?? '');
            if (empty($proxies)) {
                $missing[] = 'پروتکل‌ها/پروکسی‌های ساخت کاربر (Proxies) تنظیم نشده است';
            }
        }
    } elseif ($type === 'x-ui_single') {
        $ids = function_exists('xui_parse_inbound_ids') ? xui_parse_inbound_ids($panel['inboundid'] ?? '') : array_filter(array_map('intval', explode(',', (string) ($panel['inboundid'] ?? ''))));
        if (empty($ids)) {
            $missing[] = 'شناسه اینباند تنظیم نشده است';
        }
    } elseif ($type === 'guard') {
        $ids = function_exists('guardParseServiceIds') ? guardParseServiceIds($panel['guard_service_ids'] ?? null) : rx_sr_json_array($panel['guard_service_ids'] ?? '');
        if (empty($ids) && !$override) {
            $missing[] = 'سرویس‌های قابل ساخت Guard تنظیم نشده است';
        }
    } elseif ($type === 'remnawave') {
        $raw = trim((string) ($panel['remna_squad'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($panel['inboundid'] ?? ''));
        }
        $valid = 0;
        foreach (explode(',', $raw) as $uuid) {
            $uuid = trim($uuid);
            if (function_exists('remnawave_is_uuid') ? remnawave_is_uuid($uuid) : (bool) preg_match('/^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$/', $uuid)) {
                $valid++;
            }
        }
        if ($valid === 0) {
            $missing[] = 'Squad معتبر (UUID) تنظیم نشده است';
        }
    } elseif ($type === 'rebecca') {
        if (trim((string) ($panel['rebecca_service_id'] ?? '')) === '' && !$override) {
            $notes[] = 'سرویس پیش‌فرض Rebecca ثبت نشده؛ ربات اولین سرویس موجود پنل را استفاده می‌کند.';
        }
    } elseif ($type === 'WGDashboard') {
        foreach (['url_panel' => 'آدرس پنل', 'inboundid' => 'نام کانفیگ WireGuard', 'password_panel' => 'کلید/رمز API'] as $column => $label) {
            if (trim((string) ($panel[$column] ?? '')) === '') {
                $missing[] = $label . ' تنظیم نشده است';
            }
        }
    }
    $sub = (string) ($panel['sublink'] ?? '') === 'onsublink';
    $cfg = (string) ($panel['config'] ?? '') === 'onconfig';
    if (!$sub && !$cfg) {
        $missing[] = 'هر دو گزینه «ارسال لینک اشتراک» و «ارسال کانفیگ» خاموش است و مشتری چیزی دریافت نمی‌کند';
    }
    if (!empty($missing)) {
        return rx_sr_st('fail', implode('؛ ', $missing) . '.');
    }
    $delivery = [];
    if ($sub) {
        $delivery[] = 'لینک اشتراک';
    }
    if ($cfg) {
        $delivery[] = 'کانفیگ';
    }
    return rx_sr_st('ok', 'منابع ساخت کاربر تنظیم است؛ تحویل: ' . implode(' و ', $delivery) . '.' . (empty($notes) ? '' : ' ' . implode(' ', $notes)));
}

function rx_sr_cards(PDO $pdo)
{
    $result = ['total' => 0, 'usable' => 0, 'invalid' => 0, 'hidden' => 0, 'inactive' => 0, 'restricted' => 0];
    try {
        $rows = $pdo->query("SELECT cn.id, cn.cardnumber, cn.namecard, cn.is_active, cn.is_visible FROM card_number cn")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $result;
    }
    $restricted = [];
    try {
        foreach ($pdo->query("SELECT DISTINCT card_id FROM card_whitelist")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $restricted[(string) $id] = true;
        }
    } catch (Throwable $e) {
    }
    foreach ($rows as $row) {
        $result['total']++;
        $digits = preg_replace('/\D+/', '', (string) $row['cardnumber']);
        if ((int) $row['is_active'] !== 1) {
            $result['inactive']++;
            continue;
        }
        if (isset($restricted[(string) $row['id']])) {
            $result['restricted']++;
            continue;
        }
        if ((int) $row['is_visible'] !== 1) {
            $result['hidden']++;
            continue;
        }
        if (strlen($digits) !== 16 || $digits === '6037000000000000' || preg_match('/^(\d)\1+$/', $digits) || rx_sr_blank($row['namecard'])) {
            $result['invalid']++;
            continue;
        }
        $result['usable']++;
    }
    return $result;
}

function rx_sr_pay_values(PDO $pdo)
{
    $values = [];
    try {
        foreach ($pdo->query("SELECT NamePay, ValuePay FROM PaySetting")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[(string) $row['NamePay']] = (string) $row['ValuePay'];
        }
    } catch (Throwable $e) {
    }
    return $values;
}

function rx_sr_payment_methods(PDO $pdo)
{
    $values = rx_sr_pay_values($pdo);
    $v = function ($name) use ($values) {
        return array_key_exists($name, $values) ? $values[$name] : '';
    };
    $methods = [];
    $gateway = function ($name, $enabled, array $required) use (&$methods, $v) {
        if (!$enabled) {
            return;
        }
        $missing = [];
        foreach ($required as $label => $keys) {
            $filled = false;
            foreach ((array) $keys as $key) {
                if (!rx_sr_blank($v($key))) {
                    $filled = true;
                    break;
                }
            }
            if (!$filled) {
                $missing[] = $label;
            }
        }
        $methods[] = empty($missing)
            ? ['name' => $name, 's' => 'ok', 'm' => 'فعال و تنظیمات الزامی ثبت شده است.', 'first_hidden' => false]
            : ['name' => $name, 's' => 'fail', 'm' => 'فعال است اما ناقص: ' . implode('، ', $missing) . '.', 'first_hidden' => false];
    };

    if ($v('Cartstatus') === 'oncard') {
        if ($v('Cartstatuspv') === 'oncardpv') {
            $direct = trim($v('CartDirect'));
            if (preg_match('/^@?[A-Za-z][A-Za-z0-9_]{4,31}$/', $direct) && ltrim(strtolower($direct), '@') !== 'cart') {
                $methods[] = ['name' => 'کارت به کارت (آفلاین در پیوی)', 's' => 'ok', 'm' => 'مشتری به آیدی پشتیبانی هدایت می‌شود؛ شماره کارت لازم نیست.', 'first_hidden' => $v('checkpaycartfirst') === 'onpayverify'];
            } else {
                $methods[] = ['name' => 'کارت به کارت (آفلاین در پیوی)', 's' => 'fail', 'm' => 'آیدی تلگرام معتبر برای پیوی ثبت نشده است.', 'first_hidden' => false];
            }
        } else {
            $cards = rx_sr_cards($pdo);
            if ($cards['usable'] > 0) {
                $methods[] = ['name' => 'کارت به کارت', 's' => 'ok', 'm' => rx_sr_fa($cards['usable']) . ' کارت فعال و قابل مشاهده برای مشتری عادی ثبت شده است.', 'first_hidden' => $v('checkpaycartfirst') === 'onpayverify'];
            } else {
                $why = [];
                $labels = ['invalid' => 'شماره/نام نامعتبر یا پیش‌فرض', 'hidden' => 'نمایش شماره خاموش', 'inactive' => 'غیرفعال', 'restricted' => 'محدود به کاربران خاص'];
                foreach ($labels as $key => $label) {
                    if ($cards[$key] > 0) {
                        $why[] = rx_sr_fa($cards[$key]) . ' کارت ' . $label;
                    }
                }
                $methods[] = ['name' => 'کارت به کارت', 's' => 'fail', 'm' => 'هیچ کارت فعال و قابل مشاهده‌ای برای مشتری عادی وجود ندارد' . (empty($why) ? ' (کارتی ثبت نشده).' : ' (' . implode('، ', $why) . ').'), 'first_hidden' => false];
            }
        }
    }
    $gateway('Plisio', $v('nowpaymentstatus') === 'onnowpayment', ['کلید API' => ['api_plisio', 'apinowpayment']]);
    $gateway('NowPayments', $v('statusnowpayment') === '1', ['کلید API' => ['api_nowpayment', 'marchent_tronseller']]);
    if ($v('digistatus') === 'ondigi') {
        $wallets = function_exists('crypto_active_wallets') ? count(crypto_active_wallets()) : 0;
        $methods[] = $wallets > 0
            ? ['name' => 'واریز رمزارز', 's' => 'ok', 'm' => rx_sr_fa($wallets) . ' کیف پول فعال ثبت شده است.', 'first_hidden' => false]
            : ['name' => 'واریز رمزارز', 's' => 'fail', 'm' => 'فعال است اما هیچ کیف پول فعالی ثبت نشده است.', 'first_hidden' => false];
    }
    $gateway('ترونادو', $v('statustarnado') === 'onternado', ['کلید API' => ['apiternado'], 'آدرس کیف پول' => ['walletaddress'], 'کلید امضای IPN' => ['ipnsigningkeytronado']]);
    $gateway('تون‌پی', $v('statustonpay') === 'ontonpay', ['کلید API' => ['apitonpay']]);
    $gateway('کیوب‌پی', $v('statuscubepay') === 'oncubepay', ['توکن API' => ['apicubepay']]);
    $gateway('بلوپال', $v('statusblupal') === 'onblupal', ['کلید API' => ['apiblupal']]);
    $gateway('NoaPay', $v('statusnoapay') === 'onnoapay', ['کلید API' => ['apinoapay'], 'کلید وب‌هوک' => ['noapay_webhook_secret']]);
    $gateway('واریزا', $v('statusvariza') === 'onvariza', ['توکن API' => ['apivariza'], 'کلید وب‌هوک' => ['variza_webhook_secret']]);
    if ($v('statusabangateway') === 'onabangateway') {
        $ready = function_exists('abangatewayIsReady') ? abangatewayIsReady() : (stripos(trim($v('urlabangateway')), 'https://') === 0 && !rx_sr_blank($v('apiabangateway')));
        $methods[] = $ready
            ? ['name' => 'آبان گیت وی', 's' => 'ok', 'm' => 'فعال با آدرس https و کلید معتبر.', 'first_hidden' => false]
            : ['name' => 'آبان گیت وی', 's' => 'fail', 'm' => 'فعال است اما آدرس https یا کلید ثبت نشده است.', 'first_hidden' => false];
    }
    $gateway('اطلس‌پی', $v('statusatlaspay') === 'onatlaspay', ['کلید API' => ['apiatlaspay']]);
    $gateway('زرین‌پال', $v('zarinpalstatus') === 'onzarinpal', ['مرچنت' => ['merchant_zarinpal']]);
    $gateway('درگاه ریالی', $v('paymentstatussnotverify') === 'onverifypay', ['مرچنت' => ['marchentpaynotverify']]);
    if ((int) $v('statusstar') === 1) {
        $methods[] = ['name' => 'Telegram Stars', 's' => 'ok', 'm' => 'فعال است و تنظیم اضافه‌ای ندارد.', 'first_hidden' => false];
    }
    return $methods;
}

function rx_sr_check_payment(PDO $pdo)
{
    $methods = rx_sr_payment_methods($pdo);
    $lines = [];
    foreach ($methods as $method) {
        $icon = $method['s'] === 'ok' ? '✅' : '❌';
        $lines[] = $icon . ' ' . $method['name'] . ': ' . $method['m'];
    }
    $readyAny = false;
    $readyForNew = false;
    foreach ($methods as $method) {
        if ($method['s'] === 'ok') {
            $readyAny = true;
            if (!$method['first_hidden']) {
                $readyForNew = true;
            }
        }
    }
    $lines[] = 'ℹ️ فقط کامل بودن تنظیمات بررسی شد؛ هیچ پرداخت آزمایشی انجام نشد و صحت کلیدها در درگاه تایید نشده است.';
    if (empty($methods)) {
        return array_merge(rx_sr_st('fail', 'هیچ روش پرداختی فعال نیست.'), ['lines' => $lines]);
    }
    if (!$readyAny) {
        return array_merge(rx_sr_st('fail', 'روش‌های پرداخت فعال هستند اما هیچ‌کدام تنظیمات کامل ندارند.'), ['lines' => $lines]);
    }
    if (!$readyForNew) {
        $lines[] = '⏳ گزینه «کارت به کارت پس از اولین پرداخت» روشن است؛ کارت به کارت برای مشتریانی که هنوز پرداخت موفق ندارند مخفی است و روش دیگری برای آن‌ها فعال نیست.';
        return array_merge(rx_sr_st('pending', 'روش آماده وجود دارد اما برای مشتری جدید نمایش داده نمی‌شود.'), ['lines' => $lines]);
    }
    $okCount = 0;
    foreach ($methods as $method) {
        if ($method['s'] === 'ok') {
            $okCount++;
        }
    }
    return array_merge(rx_sr_st('ok', rx_sr_fa($okCount) . ' روش پرداخت با تنظیمات کامل فعال است.'), ['lines' => $lines]);
}

function rx_sr_check_access(PDO $pdo, array $paths)
{
    $lines = [];
    $row = [];
    try {
        $row = $pdo->query("SELECT Bot_Status, keyboardmain, get_number, iran_number FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return array_merge(rx_sr_st('pending', 'خواندن تنظیمات ربات ممکن نشد.'), ['lines' => []]);
    }
    $botOn = (string) ($row['Bot_Status'] ?? '') !== 'botstatusoff';
    $lines[] = ($botOn ? '✅' : '❌') . ' وضعیت ربات: ' . ($botOn ? 'روشن' : 'خاموش؛ مشتریان عادی پیام خاموشی می‌بینند.');
    $buttonOn = function_exists('check_active_btn') ? check_active_btn($row['keyboardmain'] ?? '', 'text_sell') : true;
    $lines[] = ($buttonOn ? '✅' : '❌') . ' دکمه خرید در منوی مشتری: ' . ($buttonOn ? 'فعال' : 'غیرفعال');
    $eligible = 0;
    foreach ($paths as $path) {
        if (($path['stages'][1]['s'] ?? '') === 'ok' && !empty($path['offer_count'])) {
            $eligible++;
        }
    }
    $availablePanels = 0;
    foreach ($paths as $path) {
        if (($path['stages'][1]['s'] ?? '') === 'ok') {
            $availablePanels++;
        }
    }
    if ($availablePanels > 0) {
        $lines[] = ($eligible > 0 ? '✅' : '❌') . ' پنل‌های دارای پیشنهاد قابل خرید برای مشتری عادی: ' . rx_sr_fa($eligible) . ' از ' . rx_sr_fa($availablePanels);
    }
    $authOn = (string) ($row['get_number'] ?? '') === 'onAuthenticationphone' || (string) ($row['iran_number'] ?? '') === 'onAuthenticationiran';
    if ($authOn) {
        $lines[] = 'ℹ️ احراز شماره موبایل برای مشتریان فعال است؛ مشتری قبل از خرید باید شماره خود را تایید کند.';
    }
    if (!$botOn) {
        return array_merge(rx_sr_st('fail', 'ربات خاموش است و مشتری عادی نمی‌تواند خرید کند.'), ['lines' => $lines]);
    }
    if (!$buttonOn) {
        return array_merge(rx_sr_st('fail', 'دکمه «خرید» در منوی مشتری غیرفعال است.'), ['lines' => $lines]);
    }
    if ($availablePanels === 0) {
        return array_merge(rx_sr_st('pending', 'تا زمانی که پنل فعالی وجود نداشته باشد، مسیر خرید مشتری قابل بررسی نیست.'), ['lines' => $lines]);
    }
    if ($eligible === 0) {
        return array_merge(rx_sr_st('fail', 'مشتری عادی به هیچ پیشنهاد قابل خریدی دسترسی ندارد.'), ['lines' => $lines]);
    }
    return array_merge(rx_sr_st('ok', 'ربات روشن است و مشتری عادی به ' . rx_sr_fa($eligible) . ' پنل دارای پیشنهاد قابل خرید دسترسی دارد.'), ['lines' => $lines]);
}

function rx_sr_aggregate_stage(array $paths, $k)
{
    foreach ($paths as $path) {
        $allOk = true;
        for ($i = 1; $i <= $k; $i++) {
            if (($path['stages'][$i]['s'] ?? 'pending') !== 'ok') {
                $allOk = false;
                break;
            }
        }
        if ($allOk) {
            return 'ok';
        }
    }
    if ($k === 1) {
        return 'fail';
    }
    $reachable = [];
    foreach ($paths as $path) {
        $prevOk = true;
        for ($i = 1; $i < $k; $i++) {
            if (($path['stages'][$i]['s'] ?? 'pending') !== 'ok') {
                $prevOk = false;
                break;
            }
        }
        if ($prevOk) {
            $reachable[] = $path;
        }
    }
    if (empty($reachable)) {
        return 'pending';
    }
    foreach ($reachable as $path) {
        if (($path['stages'][$k]['s'] ?? 'pending') === 'pending') {
            return 'pending';
        }
    }
    return 'fail';
}

function rx_sr_evaluate($live = true)
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(90);
    }
    $defs = rx_sr_defs();
    $result = ['time' => time(), 'live' => (bool) $live, 'checks' => [], 'paths' => [], 'registered' => 0, 'active' => 0, 'inactive_names' => []];
    $pdo = rx_sr_pdo();
    if ($pdo === null) {
        foreach ($defs as $i => $def) {
            $result['checks'][$i] = ['label' => $def['label'], 's' => 'pending', 'm' => 'اتصال به پایگاه داده برقرار نیست.', 'lines' => []];
        }
        $result['completed'] = 0;
        $result['ready_paths'] = 0;
        $result['verdict'] = 'unknown';
        $result['blocking'] = [];
        return $result;
    }
    $panels = $pdo->query("SELECT * FROM marzban_panel ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $result['registered'] = count($panels);
    $deadline = microtime(true) + 28;
    $paths = [];
    foreach ($panels as $panel) {
        if ((string) ($panel['status'] ?? '') !== 'active') {
            $result['inactive_names'][] = (string) ($panel['name_panel'] ?? '');
            continue;
        }
        $path = ['panel' => (string) ($panel['name_panel'] ?? ''), 'type' => (string) ($panel['type'] ?? ''), 'stages' => [], 'offer_count' => 0];
        $path['stages'][1] = rx_sr_stage_panel($panel);
        $path['stages'][2] = rx_sr_stage_connection($pdo, $panel, $live, $deadline);
        $offers = rx_sr_evaluate_offers($pdo, $panel, 'f');
        $path['offer_count'] = count($offers['valid']) + ($offers['custom'] ? 1 : 0);
        $path['stages'][3] = rx_sr_offer_stage($offers, $panel);
        $path['stages'][4] = rx_sr_stage_delivery($panel, $offers);
        $ready = true;
        for ($i = 1; $i <= 4; $i++) {
            if ($path['stages'][$i]['s'] !== 'ok') {
                $ready = false;
            }
        }
        $path['ready'] = $ready;
        $paths[] = $path;
    }
    $result['paths'] = $paths;
    $result['active'] = count($paths);
    $readyPaths = 0;
    foreach ($paths as $path) {
        if ($path['ready']) {
            $readyPaths++;
        }
    }
    $result['ready_paths'] = $readyPaths;

    for ($k = 1; $k <= 4; $k++) {
        $status = rx_sr_aggregate_stage($paths, $k);
        $result['checks'][$k] = ['label' => $defs[$k]['label'], 's' => $status, 'm' => '', 'lines' => []];
    }
    if ($result['active'] === 0) {
        $result['checks'][1]['m'] = $result['registered'] === 0
            ? 'هیچ پنلی در ربات ثبت نشده است.'
            : 'هیچ پنل فعالی وجود ندارد؛ ' . rx_sr_fa($result['registered']) . ' پنل ثبت شده اما غیرفعال است.';
    } elseif ($result['checks'][1]['s'] === 'ok') {
        $availablePanels = 0;
        foreach ($paths as $path) {
            if ($path['stages'][1]['s'] === 'ok') {
                $availablePanels++;
            }
        }
        $result['checks'][1]['m'] = rx_sr_fa($availablePanels) . ' پنل فعال برای مشتری عادی در دسترس است.';
    } else {
        $result['checks'][1]['m'] = 'پنل‌های فعال برای مشتری عادی در دسترس نیستند.';
    }
    $summaries = [
        2 => ['ok' => 'اتصال حداقل یک پنل تایید شد.', 'fail' => 'اتصال هیچ پنل قابل فروشی تایید نشد.', 'pending' => 'اتصال بررسی نشد یا نتیجه قطعی نیست.'],
        3 => ['ok' => 'حداقل یک پنل متصل پلن قابل خرید با قیمت معتبر دارد.', 'fail' => 'هیچ پنل متصلی پلن قابل خرید با قیمت معتبر ندارد.', 'pending' => 'پس از تایید پنل و اتصال بررسی می‌شود.'],
        4 => ['ok' => 'تنظیمات تحویل حداقل یک مسیر کامل است.', 'fail' => 'تنظیمات تحویل هیچ مسیر فروشی کامل نیست.', 'pending' => 'پس از تکمیل مراحل قبل بررسی می‌شود.'],
    ];
    for ($k = 2; $k <= 4; $k++) {
        $result['checks'][$k]['m'] = $summaries[$k][$result['checks'][$k]['s']];
    }
    $payment = rx_sr_check_payment($pdo);
    $result['checks'][5] = ['label' => $defs[5]['label'], 's' => $payment['s'], 'm' => $payment['m'], 'lines' => $payment['lines']];
    $access = rx_sr_check_access($pdo, $paths);
    $result['checks'][6] = ['label' => $defs[6]['label'], 's' => $access['s'], 'm' => $access['m'], 'lines' => $access['lines']];

    $completed = 0;
    $blocking = [];
    $pendingCount = 0;
    foreach ($result['checks'] as $i => $check) {
        if ($check['s'] === 'ok') {
            $completed++;
        } elseif ($check['s'] === 'fail') {
            $blocking[] = $check['label'] . ': ' . $check['m'];
        } else {
            $pendingCount++;
        }
    }
    $result['completed'] = $completed;
    $result['blocking'] = $blocking;
    $result['pending'] = $pendingCount;
    if ($readyPaths > 0 && $result['checks'][5]['s'] === 'ok' && $result['checks'][6]['s'] === 'ok') {
        $result['verdict'] = 'ready';
    } elseif (!empty($blocking)) {
        $result['verdict'] = 'not_ready';
    } else {
        $result['verdict'] = 'unknown';
    }
    return $result;
}

function rx_sr_cache_file()
{
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'rx_sales_readiness_' . md5(__DIR__) . '.json';
}

function rx_sr_cache_write(array $result)
{
    @file_put_contents(rx_sr_cache_file(), json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function rx_sr_cache_read($maxAge = 1800)
{
    $file = rx_sr_cache_file();
    if (!is_file($file) || (time() - (int) @filemtime($file)) > $maxAge) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) && isset($data['checks']) ? $data : null;
}

function rx_sr_icon($status)
{
    if ($status === 'ok') {
        return '✅';
    }
    if ($status === 'fail') {
        return '❌';
    }
    return '⏳';
}

function rx_sr_style($status)
{
    if ($status === 'ok') {
        return 'success';
    }
    if ($status === 'fail') {
        return 'danger';
    }
    return 'primary';
}

function rx_sr_button($text, $callback, $status = null)
{
    $btn = ['text' => $text, 'callback_data' => $callback];
    if ($status === null) {
        $btn['_rxap'] = 1;
    } else {
        $btn['style'] = rx_sr_style($status);
        $btn['_rxkeep'] = 1;
    }
    return $btn;
}

function rx_sr_render_list(array $result)
{
    $verdictLine = [
        'ready' => '🟢 <b>ربات آماده فروش است.</b>',
        'not_ready' => '🔴 <b>ربات هنوز آماده فروش نیست.</b>',
        'unknown' => '🔵 <b>نتیجه نهایی هنوز قطعی نیست.</b>',
    ][$result['verdict']] ?? '';
    $lines = [];
    $lines[] = '🚦 <b>آمادگی فروش</b>';
    $lines[] = '';
    $lines[] = $verdictLine;
    $lines[] = '📊 <b>' . rx_sr_fa($result['completed']) . ' از ' . rx_sr_fa(6) . ' مورد تکمیل</b>';
    $lines[] = '🛤 مسیر فروش کامل: <b>' . rx_sr_fa($result['ready_paths']) . ' از ' . rx_sr_fa($result['active']) . '</b> پنل فعال';
    if (!empty($result['pending'])) {
        $lines[] = '⏳ بررسی‌نشده یا نامشخص: ' . rx_sr_fa($result['pending']) . ' مورد';
    }
    if (!empty($result['blocking'])) {
        $lines[] = '';
        $lines[] = '⛔️ <b>موارد مسدودکننده:</b>';
        foreach ($result['blocking'] as $item) {
            $lines[] = '• ' . rx_sr_h($item);
        }
    }
    $problemPanels = [];
    foreach ($result['paths'] as $path) {
        if (!$path['ready']) {
            foreach ([1, 2, 3, 4] as $i) {
                if (($path['stages'][$i]['s'] ?? '') !== 'ok') {
                    $problemPanels[] = '• ' . rx_sr_h($path['panel']) . ' — ' . rx_sr_h(rx_sr_defs()[$i]['label']) . ': ' . rx_sr_h($path['stages'][$i]['m']);
                    break;
                }
            }
        }
    }
    if (!empty($problemPanels) && $result['verdict'] === 'ready') {
        $lines[] = '';
        $lines[] = '⚠️ <b>پنل‌هایی که مسیر کامل ندارند (مانع فروش نیستند):</b>';
    } elseif (!empty($problemPanels)) {
        $lines[] = '';
        $lines[] = '🔎 <b>وضعیت پنل‌ها:</b>';
    }
    foreach (array_slice($problemPanels, 0, 6) as $line) {
        $lines[] = $line;
    }
    if (count($problemPanels) > 6) {
        $lines[] = '… و ' . rx_sr_fa(count($problemPanels) - 6) . ' پنل دیگر';
    }
    $lines[] = '';
    $lines[] = '👇 برای دیدن جزئیات هر مورد روی آن بزنید.';
    $lines[] = '🕒 آخرین بررسی: ' . rx_sr_fa(date('H:i:s', (int) $result['time'])) . (empty($result['live']) ? ' (بدون بررسی زنده اتصال)' : '');
    $rows = [];
    foreach ($result['checks'] as $i => $check) {
        $rows[] = [rx_sr_button(rx_sr_icon($check['s']) . ' ' . $check['label'], 'srd_i' . $i, $check['s'])];
    }
    $rows[] = [rx_sr_button('🔄 بررسی مجدد', 'srd_refresh'), rx_sr_button('↩️ بازگشت', 'adm_hub_main')];
    return [implode("\n", $lines), json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function rx_sr_render_detail(array $result, $index)
{
    $defs = rx_sr_defs();
    $check = $result['checks'][$index] ?? null;
    if ($check === null) {
        return rx_sr_render_list($result);
    }
    $statusText = ['ok' => '✅ تکمیل شده', 'fail' => '❌ ناقص', 'pending' => '⏳ بررسی نشده / نامشخص'][$check['s']] ?? '';
    $lines = [];
    $lines[] = rx_sr_icon($check['s']) . ' <b>' . rx_sr_h($check['label']) . '</b>';
    $lines[] = 'وضعیت: ' . $statusText;
    $lines[] = rx_sr_h($check['m']);
    $lines[] = '';
    if ($index >= 5) {
        foreach ($check['lines'] as $line) {
            $lines[] = rx_sr_h($line);
        }
    } else {
        if (empty($result['paths'])) {
            $lines[] = 'پنل فعالی برای بررسی وجود ندارد.';
        }
        foreach ($result['paths'] as $path) {
            $blockedAt = null;
            for ($i = 1; $i < $index; $i++) {
                if (($path['stages'][$i]['s'] ?? '') !== 'ok') {
                    $blockedAt = $i;
                    break;
                }
            }
            $head = '🔹 <b>' . rx_sr_h($path['panel']) . '</b> (' . rx_sr_h($path['type']) . ')';
            if ($blockedAt !== null) {
                $lines[] = $head;
                $lines[] = '   ⏭ به‌دلیل مشکل در «' . rx_sr_h($defs[$blockedAt]['label']) . '» این مرحله برای این پنل بررسی نشد.';
            } else {
                $stage = $path['stages'][$index];
                $lines[] = $head;
                $lines[] = '   ' . rx_sr_icon($stage['s']) . ' ' . rx_sr_h($stage['m']);
            }
        }
        if ($index === 1 && !empty($result['inactive_names'])) {
            $lines[] = '';
            $lines[] = '🚫 پنل‌های غیرفعال (برای مشتری نمایش داده نمی‌شوند): ' . rx_sr_h(implode('، ', array_slice($result['inactive_names'], 0, 8)));
        }
    }
    $lines[] = '';
    $lines[] = '🕒 آخرین بررسی: ' . rx_sr_fa(date('H:i:s', (int) $result['time']));
    $text = implode("\n", $lines);
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 3800) {
        $text = mb_substr($text, 0, 3800, 'UTF-8') . '…';
    }
    $rows = [];
    if ($check['s'] !== 'ok') {
        $routes = [];
        foreach ($defs[$index]['route'] as $route) {
            $routes[] = rx_sr_button($route[0], $route[1]);
        }
        foreach (array_chunk($routes, 2) as $chunk) {
            $rows[] = $chunk;
        }
    }
    $rows[] = [rx_sr_button('🔄 بررسی مجدد', 'srd_r' . $index), rx_sr_button('↩️ بازگشت به چک‌لیست', 'srd_list')];
    return [$text, json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function rx_sr_answer($text = '', $alert = false)
{
    $callbackId = $GLOBALS['callback_query_id'] ?? '';
    if ($callbackId === '' || $callbackId === null || !function_exists('telegram')) {
        return;
    }
    $payload = ['callback_query_id' => $callbackId, 'cache_time' => 0];
    if ($text !== '') {
        $payload['text'] = $text;
        $payload['show_alert'] = $alert;
    }
    telegram('answerCallbackQuery', $payload);
}

function rx_sr_show($chatId, $messageId, array $result, $detailIndex = null)
{
    $view = $detailIndex === null ? rx_sr_render_list($result) : rx_sr_render_detail($result, $detailIndex);
    if ((int) $messageId > 0) {
        Editmessagetext($chatId, $messageId, $view[0], $view[1], 'HTML');
        return;
    }
    sendmessage($chatId, $view[0], $view[1], 'HTML');
}

function rx_sr_handle_admin_update()
{
    $datain = isset($GLOBALS['datain']) ? (string) $GLOBALS['datain'] : '';
    $text = isset($GLOBALS['text']) && is_string($GLOBALS['text']) ? trim($GLOBALS['text']) : '';
    $fromId = $GLOBALS['from_id'] ?? null;
    $messageId = (int) ($GLOBALS['message_id'] ?? 0);
    $isCallback = !empty($GLOBALS['callback_query_id']);

    $isEntry = $datain === 'admin_readiness' || ($datain === '' && $text === rx_sr_label());
    $isAction = $datain !== '' && strpos($datain, 'srd_') === 0;
    if (!$isEntry && !$isAction) {
        return false;
    }

    $adminIds = array_map('strval', (array) ($GLOBALS['admin_ids'] ?? []));
    $rule = (string) (($GLOBALS['adminrulecheck']['rule'] ?? ''));
    if ($fromId === null || !in_array((string) $fromId, $adminIds, true) || $rule !== 'administrator') {
        if ($isCallback) {
            rx_sr_answer('⛔️ شما به این بخش دسترسی ندارید.', true);
        } elseif ($fromId !== null && function_exists('sendmessage')) {
            sendmessage($fromId, '⛔️ شما به این بخش دسترسی ندارید.', null, 'HTML');
        }
        return true;
    }

    if ($isEntry) {
        if (function_exists('step')) {
            step('home', $fromId);
        }
        if (isset($GLOBALS['user']) && is_array($GLOBALS['user'])) {
            $GLOBALS['user']['step'] = 'home';
        }
        if ($isCallback) {
            rx_sr_answer('در حال بررسی…');
            $targetMessage = $messageId;
        } else {
            $placeholder = sendmessage($fromId, '⏳ در حال بررسی آمادگی فروش؛ ممکن است چند ثانیه طول بکشد…', null, 'HTML');
            $targetMessage = is_array($placeholder) ? (int) ($placeholder['result']['message_id'] ?? 0) : 0;
        }
        $result = rx_sr_evaluate(true);
        rx_sr_cache_write($result);
        rx_sr_show($fromId, $targetMessage, $result);
        return true;
    }

    if ($datain === 'srd_refresh' || preg_match('/^srd_r([1-6])$/', $datain, $refreshMatch)) {
        rx_sr_answer('در حال بررسی مجدد…');
        $result = rx_sr_evaluate(true);
        rx_sr_cache_write($result);
        rx_sr_show($fromId, $messageId, $result, isset($refreshMatch[1]) ? (int) $refreshMatch[1] : null);
        return true;
    }

    $result = rx_sr_cache_read();
    if ($datain === 'srd_list') {
        rx_sr_answer();
        if ($result === null) {
            $result = rx_sr_evaluate(true);
            rx_sr_cache_write($result);
        }
        rx_sr_show($fromId, $messageId, $result);
        return true;
    }
    if (preg_match('/^srd_i([1-6])$/', $datain, $m)) {
        rx_sr_answer();
        if ($result === null) {
            $result = rx_sr_evaluate(true);
            rx_sr_cache_write($result);
        }
        rx_sr_show($fromId, $messageId, $result, (int) $m[1]);
        return true;
    }
    rx_sr_answer();
    return true;
}
