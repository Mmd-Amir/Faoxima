<?php

namespace Faoxima\Services\Panels;

use PDO;
use Throwable;

final class SalesReadiness
{
    public static function rx_sr_pdo()
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : null;
        if (!$pdo instanceof PDO && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $pdo = $GLOBALS['pdo'];
        }
        return $pdo instanceof PDO ? $pdo : null;
    }

    public static function rx_sr_defs()
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

    public static function rx_sr_st($status, $message)
    {
        return ['s' => $status, 'm' => (string) $message];
    }

    public static function rx_sr_blank($value)
    {
        $value = trim((string) $value);
        return $value === '' || $value === '0' || strtolower($value) === 'null' || $value === 'تنظیم نشده';
    }

    public static function rx_sr_json_array($raw)
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

    public static function rx_sr_agent_value($raw, $agent = 'f')
    {
        $data = self::rx_sr_json_array($raw);
        return array_key_exists($agent, $data) ? $data[$agent] : null;
    }

    public static function rx_sr_positive($value)
    {
        return is_numeric($value) && (float) $value > 0;
    }

    public static function rx_sr_known_types()
    {
        return ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'Manualsale', 'WGDashboard', 'remnawave', 'rebecca', 'jovadpanel'];
    }

    public static function rx_sr_manual_stock(PDO $pdo, array $panel)
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

    public static function rx_sr_stage_panel(array $panel)
    {
        if (method_exists(\Faoxima\Services\Panels\PanelLimits::class, 'panel_creation_limit_reached_unlocked')) {
            try {
                if (\Faoxima\Services\Panels\PanelLimits::panel_creation_limit_reached_unlocked($panel)) {
                    return self::rx_sr_st('fail', 'سقف تعداد سرویس این پنل پر شده و برای مشتری عادی نمایش داده نمی‌شود.');
                }
            } catch (Throwable $e) {
            }
        }
        return self::rx_sr_st('ok', 'پنل فعال است و در لیست خرید مشتری عادی قرار می‌گیرد.');
    }

    public static function rx_sr_stage_connection(PDO $pdo, array $panel, $live, $deadline)
    {
        $type = (string) ($panel['type'] ?? '');
        if ($type === 'Manualsale') {
            $stock = self::rx_sr_manual_stock($pdo, $panel);
            if ($stock['filled'] > 0) {
                return self::rx_sr_st('ok', 'فروش دستی: ' . \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($stock['filled']) . ' کانفیگ فعال در موجودی ثبت شده است.');
            }
            return self::rx_sr_st('fail', 'فروش دستی: هیچ کانفیگ فعال و دارای محتوا در موجودی وجود ندارد.');
        }
        if (!in_array($type, self::rx_sr_known_types(), true)) {
            return self::rx_sr_st('fail', 'نوع پنل («' . $type . '») توسط ربات پشتیبانی نمی‌شود.');
        }
        if (!$live) {
            return self::rx_sr_st('pending', 'بررسی زنده انجام نشد.');
        }
        if (microtime(true) > $deadline) {
            return self::rx_sr_st('pending', 'به‌دلیل محدودیت زمان، اتصال این پنل بررسی نشد؛ «بررسی مجدد» را بزنید.');
        }
        if (!method_exists(\Faoxima\Services\Panels\PanelCapability::class, 'rx_panel_capability_report')) {
            return self::rx_sr_st('pending', 'ماژول بررسی اتصال پنل در دسترس نیست.');
        }
        try {
            $report = \Faoxima\Services\Panels\PanelCapability::rx_panel_capability_report($panel);
        } catch (Throwable $e) {
            return self::rx_sr_st('pending', 'بررسی اتصال با خطای داخلی متوقف شد: ' . \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text($e->getMessage(), 100));
        }
        $check = $report['checks']['connection'] ?? ['status' => 'unknown', 'note' => ''];
        $note = (string) ($check['note'] ?? '');
        $status = (string) ($check['status'] ?? 'unknown');
        if ($status === 'verified' || $status === 'allowed') {
            return self::rx_sr_st('ok', 'ورود و احراز هویت موفق بود' . ($note !== '' ? ' (' . $note . ')' : '.'));
        }
        if ($status === 'failed' || $status === 'denied') {
            return self::rx_sr_st('fail', 'اتصال یا احراز هویت ناموفق: ' . ($note !== '' ? $note : 'پاسخ معتبر از پنل دریافت نشد.'));
        }
        if ($status === 'unsupported') {
            return self::rx_sr_st('pending', 'این نوع پنل بررسی خودکار اتصال ندارد.');
        }
        return self::rx_sr_st('pending', 'نتیجه اتصال قطعی نشد' . ($note !== '' ? ': ' . $note : '.'));
    }

    public static function rx_sr_custom_offer(array $panel, $agent = 'f')
    {
        if ((string) ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if ((string) self::rx_sr_agent_value($panel['customvolume'] ?? '', $agent) !== '1') {
            return false;
        }
        $durationPricing = method_exists(\Faoxima\Orders\Pricing\CustomDuration::class, 'fx_duration_config') && \Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, (string)$agent)['enabled'];
        if ($durationPricing ? count(\Faoxima\Orders\Pricing\CustomDuration::fx_duration_tariffs($panel, (string)$agent)) === 0
            : (!self::rx_sr_positive(self::rx_sr_agent_value($panel['pricecustomvolume'] ?? '', $agent))
                || !self::rx_sr_positive(self::rx_sr_agent_value($panel['pricecustomtime'] ?? '', $agent)))) {
            return false;
        }
        $minVol = self::rx_sr_agent_value($panel['mainvolume'] ?? '', $agent);
        $maxVol = self::rx_sr_agent_value($panel['maxvolume'] ?? '', $agent);
        $minTime = self::rx_sr_agent_value($panel['maintime'] ?? '', $agent);
        $maxTime = self::rx_sr_agent_value($panel['maxtime'] ?? '', $agent);
        foreach ([$minVol, $maxVol, $minTime, $maxTime] as $bound) {
            if (!is_numeric($bound) || (float) $bound < 0) {
                return false;
            }
        }
        return (float) $maxVol > 0 && (float) $maxVol >= (float) $minVol && (float) $maxTime > 0 && (float) $maxTime >= (float) $minTime;
    }

    public static function rx_sr_product_allows_agent(array $product, $agent = 'f')
    {
        if (method_exists(\Faoxima\Admin\Telegram\AgentCashbackKeyboards::class, 'rx_product_allows_agent')) {
            return \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_product_allows_agent($product, $agent);
        }
        $values = array_map('trim', explode(',', str_replace(' ', '', (string) ($product['agent'] ?? ''))));
        return in_array($agent, $values, true) || in_array('all', $values, true) || in_array('allusers', $values, true);
    }

    public static function rx_sr_evaluate_offers(PDO $pdo, array $panel, $agent = 'f')
    {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all')");
        $stmt->execute([':loc' => (string) ($panel['name_panel'] ?? '')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $isManual = (string) ($panel['type'] ?? '') === 'Manualsale';
        $stock = $isManual ? self::rx_sr_manual_stock($pdo, $panel) : null;
        $nationalPanel = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockHasAvailableForProduct') && \Faoxima\Services\Fulfillment\StockPresentation::nmPanelNationalEnabled($panel);
        $reasons = ['agent' => 0, 'hidden' => 0, 'price' => 0, 'data' => 0, 'stock' => 0];
        $valid = [];
        foreach ($rows as $product) {
            if (!self::rx_sr_product_allows_agent($product, $agent)) {
                $reasons['agent']++;
                continue;
            }
            $hidden = self::rx_sr_json_array($product['hide_panel'] ?? '');
            if (in_array((string) ($panel['name_panel'] ?? ''), array_map('strval', $hidden), true)) {
                $reasons['hidden']++;
                continue;
            }
            if (trim((string) ($product['name_product'] ?? '')) === '') {
                $reasons['data']++;
                continue;
            }
            if (!self::rx_sr_positive($product['price_product'] ?? null)) {
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
            if ($nationalPanel && !\Faoxima\Services\Fulfillment\StockPresentation::nmStockHasAvailableForProduct($panel, $product)) {
                $reasons['stock']++;
                continue;
            }
            $valid[] = $product;
        }
        $custom = self::rx_sr_custom_offer($panel, $agent);
        return ['assigned' => count($rows), 'valid' => $valid, 'custom' => $custom, 'reasons' => $reasons];
    }

    public static function rx_sr_offer_stage(array $offers, array $panel)
    {
        $count = count($offers['valid']);
        if ($count > 0 || $offers['custom']) {
            $parts = [];
            if ($count > 0) {
                $parts[] = \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($count) . ' پلن قابل خرید';
            }
            if ($offers['custom']) {
                $parts[] = 'خرید حجم/زمان دلخواه فعال با قیمت معتبر';
            }
            return self::rx_sr_st('ok', implode(' و ', $parts) . '.');
        }
        if ($offers['assigned'] === 0) {
            $msg = 'هیچ محصولی به این پنل اختصاص داده نشده است.';
        } else {
            $why = [];
            $labels = ['agent' => 'مخصوص گروه دیگری از کاربران', 'hidden' => 'برای این پنل مخفی', 'price' => 'قیمت نامعتبر یا صفر', 'data' => 'حجم/زمان/نام نامعتبر', 'stock' => ((string) ($panel['type'] ?? '') === 'Manualsale' ? 'بدون موجودی کانفیگ' : 'بدون موجودی')];
            foreach ($labels as $key => $label) {
                if ($offers['reasons'][$key] > 0) {
                    $why[] = \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($offers['reasons'][$key]) . ' مورد ' . $label;
                }
            }
            $msg = \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($offers['assigned']) . ' محصول به این پنل اختصاص دارد اما هیچ‌کدام برای مشتری عادی قابل خرید نیست (' . implode('، ', $why) . ').';
        }
        if ((string) ($panel['type'] ?? '') !== 'Manualsale' && (string) self::rx_sr_agent_value($panel['customvolume'] ?? '', 'f') === '1') {
            $msg .= ' خرید حجم دلخواه روشن است ولی قیمت/محدوده حجم یا زمان آن معتبر نیست.';
        }
        return self::rx_sr_st('fail', $msg);
    }

    public static function rx_sr_products_override(array $offers, $needCustom)
    {
        if ($needCustom || empty($offers['valid'])) {
            return false;
        }
        foreach ($offers['valid'] as $product) {
            if (empty(self::rx_sr_json_array($product['inbounds'] ?? ''))) {
                return false;
            }
        }
        return true;
    }

    public static function rx_sr_stage_delivery(array $panel, array $offers)
    {
        $type = (string) ($panel['type'] ?? '');
        if ($type === 'Manualsale') {
            return self::rx_sr_st('ok', 'تحویل از محتوای کانفیگ‌های موجودی ثبت‌شده انجام می‌شود.');
        }
        $missing = [];
        $notes = [];
        $override = self::rx_sr_products_override($offers, $offers['custom']);
        $inbounds = self::rx_sr_json_array($panel['inbounds'] ?? '');
        if ($type === 'marzban' || $type === 'pasarguard') {
            $isGroups = $type === 'pasarguard' || (string) ($panel['version_panel'] ?? '0') === '1';
            if ($isGroups) {
                if (empty($inbounds) && !$override) {
                    $missing[] = 'گروه‌های ساخت کاربر (Group) تنظیم نشده است';
                }
            } else {
                $proxies = self::rx_sr_json_array($panel['proxies'] ?? '');
                if (empty($proxies)) {
                    $missing[] = 'پروتکل‌ها/پروکسی‌های ساخت کاربر (Proxies) تنظیم نشده است';
                }
            }
        } elseif ($type === 'x-ui_single') {
            $ids = method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_parse_inbound_ids') ? \Faoxima\Services\Panels\Drivers\XuiSingle::xui_parse_inbound_ids($panel['inboundid'] ?? '') : array_filter(array_map('intval', explode(',', (string) ($panel['inboundid'] ?? ''))));
            if (empty($ids)) {
                $missing[] = 'شناسه اینباند تنظیم نشده است';
            }
        } elseif ($type === 'guard') {
            $ids = method_exists(\Faoxima\Services\Panels\Drivers\Guard::class, 'guardParseServiceIds') ? \Faoxima\Services\Panels\Drivers\Guard::guardParseServiceIds($panel['guard_service_ids'] ?? null) : self::rx_sr_json_array($panel['guard_service_ids'] ?? '');
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
                if (method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_is_uuid') ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_is_uuid($uuid) : (bool) preg_match('/^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$/', $uuid)) {
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
            return self::rx_sr_st('fail', implode('؛ ', $missing) . '.');
        }
        $delivery = [];
        if ($sub) {
            $delivery[] = 'لینک اشتراک';
        }
        if ($cfg) {
            $delivery[] = 'کانفیگ';
        }
        return self::rx_sr_st('ok', 'منابع ساخت کاربر تنظیم است؛ تحویل: ' . implode(' و ', $delivery) . '.' . (empty($notes) ? '' : ' ' . implode(' ', $notes)));
    }

    public static function rx_sr_cards(PDO $pdo)
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
            if (strlen($digits) !== 16 || $digits === '6037000000000000' || preg_match('/^(\d)\1+$/', $digits) || self::rx_sr_blank($row['namecard'])) {
                $result['invalid']++;
                continue;
            }
            $result['usable']++;
        }
        return $result;
    }

    public static function rx_sr_pay_values(PDO $pdo)
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

    public static function rx_sr_payment_methods(PDO $pdo)
    {
        $values = self::rx_sr_pay_values($pdo);
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
                    if (!self::rx_sr_blank($v($key))) {
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
                $cards = self::rx_sr_cards($pdo);
                if ($cards['usable'] > 0) {
                    $methods[] = ['name' => 'کارت به کارت', 's' => 'ok', 'm' => \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($cards['usable']) . ' کارت فعال و قابل مشاهده برای مشتری عادی ثبت شده است.', 'first_hidden' => $v('checkpaycartfirst') === 'onpayverify'];
                } else {
                    $why = [];
                    $labels = ['invalid' => 'شماره/نام نامعتبر یا پیش‌فرض', 'hidden' => 'نمایش شماره خاموش', 'inactive' => 'غیرفعال', 'restricted' => 'محدود به کاربران خاص'];
                    foreach ($labels as $key => $label) {
                        if ($cards[$key] > 0) {
                            $why[] = \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($cards[$key]) . ' کارت ' . $label;
                        }
                    }
                    $methods[] = ['name' => 'کارت به کارت', 's' => 'fail', 'm' => 'هیچ کارت فعال و قابل مشاهده‌ای برای مشتری عادی وجود ندارد' . (empty($why) ? ' (کارتی ثبت نشده).' : ' (' . implode('، ', $why) . ').'), 'first_hidden' => false];
                }
            }
        }
        $gateway('Plisio', $v('nowpaymentstatus') === 'onnowpayment', ['کلید API' => ['api_plisio', 'apinowpayment']]);
        $gateway('NowPayments', $v('statusnowpayment') === '1', ['کلید API' => ['api_nowpayment', 'marchent_tronseller']]);
        if ($v('digistatus') === 'ondigi') {
            $wallets = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallets') ? count(\Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallets()) : 0;
            $methods[] = $wallets > 0
                ? ['name' => 'واریز رمزارز', 's' => 'ok', 'm' => \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($wallets) . ' کیف پول فعال ثبت شده است.', 'first_hidden' => false]
                : ['name' => 'واریز رمزارز', 's' => 'fail', 'm' => 'فعال است اما هیچ کیف پول فعالی ثبت نشده است.', 'first_hidden' => false];
        }
        $gateway('ترونادو', $v('statustarnado') === 'onternado', ['کلید API' => ['apiternado'], 'آدرس کیف پول' => ['walletaddress'], 'کلید امضای IPN' => ['ipnsigningkeytronado']]);
        $gateway('تون‌پی', $v('statustonpay') === 'ontonpay', ['کلید API' => ['apitonpay']]);
        $gateway('ریالیت', $v('statusrialit') === 'onrialit', ['کلید API' => ['apirialit'], 'وب‌هوک' => ['rialit_webhook_url']]);
        $gateway('کیوب‌پی', $v('statuscubepay') === 'oncubepay', ['توکن API' => ['apicubepay']]);
        $gateway('بلوپال', $v('statusblupal') === 'onblupal', ['کلید API' => ['apiblupal']]);
        $gateway('واریزا', $v('statusvariza') === 'onvariza', ['توکن API' => ['apivariza'], 'کلید وب‌هوک' => ['variza_webhook_secret']]);
        if ($v('statusabangateway') === 'onabangateway') {
            $ready = method_exists(\Faoxima\Payments\Gateways\GatewayRuntime::class, 'abangatewayIsReady') ? \Faoxima\Payments\Gateways\GatewayRuntime::abangatewayIsReady() : (stripos(trim($v('urlabangateway')), 'https://') === 0 && !self::rx_sr_blank($v('apiabangateway')));
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

    public static function rx_sr_check_payment(PDO $pdo)
    {
        $methods = self::rx_sr_payment_methods($pdo);
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
            return array_merge(self::rx_sr_st('fail', 'هیچ روش پرداختی فعال نیست.'), ['lines' => $lines]);
        }
        if (!$readyAny) {
            return array_merge(self::rx_sr_st('fail', 'روش‌های پرداخت فعال هستند اما هیچ‌کدام تنظیمات کامل ندارند.'), ['lines' => $lines]);
        }
        if (!$readyForNew) {
            $lines[] = '⏳ گزینه «کارت به کارت پس از اولین پرداخت» روشن است؛ کارت به کارت برای مشتریانی که هنوز پرداخت موفق ندارند مخفی است و روش دیگری برای آن‌ها فعال نیست.';
            return array_merge(self::rx_sr_st('pending', 'روش آماده وجود دارد اما برای مشتری جدید نمایش داده نمی‌شود.'), ['lines' => $lines]);
        }
        $okCount = 0;
        foreach ($methods as $method) {
            if ($method['s'] === 'ok') {
                $okCount++;
            }
        }
        return array_merge(self::rx_sr_st('ok', \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($okCount) . ' روش پرداخت با تنظیمات کامل فعال است.'), ['lines' => $lines]);
    }

    public static function rx_sr_check_access(PDO $pdo, array $paths)
    {
        $lines = [];
        $row = [];
        try {
            $row = $pdo->query("SELECT Bot_Status, keyboardmain, get_number, iran_number FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return array_merge(self::rx_sr_st('pending', 'خواندن تنظیمات ربات ممکن نشد.'), ['lines' => []]);
        }
        $botOn = (string) ($row['Bot_Status'] ?? '') !== 'botstatusoff';
        $lines[] = ($botOn ? '✅' : '❌') . ' وضعیت ربات: ' . ($botOn ? 'روشن' : 'خاموش؛ مشتریان عادی پیام خاموشی می‌بینند.');
        $buttonOn = method_exists(\Faoxima\Telegram\Support\Transport::class, 'check_active_btn') ? \Faoxima\Telegram\Support\Transport::check_active_btn($row['keyboardmain'] ?? '', 'text_sell') : true;
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
            $lines[] = ($eligible > 0 ? '✅' : '❌') . ' پنل‌های دارای پیشنهاد قابل خرید برای مشتری عادی: ' . \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($eligible) . ' از ' . \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($availablePanels);
        }
        $authOn = (string) ($row['get_number'] ?? '') === 'onAuthenticationphone' || (string) ($row['iran_number'] ?? '') === 'onAuthenticationiran';
        if ($authOn) {
            $lines[] = 'ℹ️ احراز شماره موبایل برای مشتریان فعال است؛ مشتری قبل از خرید باید شماره خود را تایید کند.';
        }
        if (!$botOn) {
            return array_merge(self::rx_sr_st('fail', 'ربات خاموش است و مشتری عادی نمی‌تواند خرید کند.'), ['lines' => $lines]);
        }
        if (!$buttonOn) {
            return array_merge(self::rx_sr_st('fail', 'دکمه «خرید» در منوی مشتری غیرفعال است.'), ['lines' => $lines]);
        }
        if ($availablePanels === 0) {
            return array_merge(self::rx_sr_st('pending', 'تا زمانی که پنل فعالی وجود نداشته باشد، مسیر خرید مشتری قابل بررسی نیست.'), ['lines' => $lines]);
        }
        if ($eligible === 0) {
            return array_merge(self::rx_sr_st('fail', 'مشتری عادی به هیچ پیشنهاد قابل خریدی دسترسی ندارد.'), ['lines' => $lines]);
        }
        return array_merge(self::rx_sr_st('ok', 'ربات روشن است و مشتری عادی به ' . \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($eligible) . ' پنل دارای پیشنهاد قابل خرید دسترسی دارد.'), ['lines' => $lines]);
    }

    public static function rx_sr_aggregate_stage(array $paths, $k)
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

    public static function rx_sr_evaluate($live = true)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }
        $defs = self::rx_sr_defs();
        $result = ['time' => time(), 'live' => (bool) $live, 'checks' => [], 'paths' => [], 'registered' => 0, 'active' => 0, 'inactive_names' => []];
        $pdo = self::rx_sr_pdo();
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
            $path['stages'][1] = self::rx_sr_stage_panel($panel);
            $path['stages'][2] = self::rx_sr_stage_connection($pdo, $panel, $live, $deadline);
            $offers = self::rx_sr_evaluate_offers($pdo, $panel, 'f');
            $path['offer_count'] = count($offers['valid']) + ($offers['custom'] ? 1 : 0);
            $path['stages'][3] = self::rx_sr_offer_stage($offers, $panel);
            $path['stages'][4] = self::rx_sr_stage_delivery($panel, $offers);
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
            $status = self::rx_sr_aggregate_stage($paths, $k);
            $result['checks'][$k] = ['label' => $defs[$k]['label'], 's' => $status, 'm' => '', 'lines' => []];
        }
        if ($result['active'] === 0) {
            $result['checks'][1]['m'] = $result['registered'] === 0
                ? 'هیچ پنلی در ربات ثبت نشده است.'
                : 'هیچ پنل فعالی وجود ندارد؛ ' . \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($result['registered']) . ' پنل ثبت شده اما غیرفعال است.';
        } elseif ($result['checks'][1]['s'] === 'ok') {
            $availablePanels = 0;
            foreach ($paths as $path) {
                if ($path['stages'][1]['s'] === 'ok') {
                    $availablePanels++;
                }
            }
            $result['checks'][1]['m'] = \Faoxima\Admin\Telegram\SalesReadinessView::rx_sr_fa($availablePanels) . ' پنل فعال برای مشتری عادی در دسترس است.';
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
        $payment = self::rx_sr_check_payment($pdo);
        $result['checks'][5] = ['label' => $defs[5]['label'], 's' => $payment['s'], 'm' => $payment['m'], 'lines' => $payment['lines']];
        $access = self::rx_sr_check_access($pdo, $paths);
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

    public static function rx_sr_cache_file()
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'rx_sales_readiness_' . md5((dirname(__DIR__, 3))) . '.json';
    }

    public static function rx_sr_cache_write(array $result)
    {
        @file_put_contents(self::rx_sr_cache_file(), json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function rx_sr_cache_read($maxAge = 1800)
    {
        $file = self::rx_sr_cache_file();
        if (!is_file($file) || (time() - (int) @filemtime($file)) > $maxAge) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) && isset($data['checks']) ? $data : null;
    }
}
