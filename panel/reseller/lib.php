<?php

/**
 * Shared bootstrap + helpers for the reseller panel (Phase 3).
 *
 * The reseller panel is a separate, self-service area that lives alongside the
 * admin panel. Resellers authenticate with their own credentials, top up a
 * wallet through the existing payment gateways, create/manage VPN services on
 * the panels the admin allows, and request USDT/TRON payouts.
 *
 * All money is stored as an integer amount of Toman on `reseller.balance`, and
 * every balance change is mirrored into the `reseller_ledger` accounting table.
 */

if (!defined('HAMOIX_SKIP_BOTAPI_ROUTER')) {
    define('HAMOIX_SKIP_BOTAPI_ROUTER', true);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../lib/icons.php';
if (is_file(__DIR__ . '/../../jdf.php')) {
    require_once __DIR__ . '/../../jdf.php';
}

/* --------------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_settings_row')) {
    function reseller_settings_row()
    {
        static $row = null;
        if ($row !== null) {
            return $row;
        }
        $row = [];
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
                $r = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($r)) {
                    $row = $r;
                }
            } catch (\Throwable $e) {
            }
        }
        return $row;
    }
}

if (!function_exists('reseller_setting')) {
    function reseller_setting($key, $default = '')
    {
        $row = reseller_settings_row();
        return array_key_exists($key, $row) ? $row[$key] : $default;
    }
}

if (!function_exists('reseller_system_enabled')) {
    function reseller_system_enabled()
    {
        return (string) reseller_setting('reseller_system_status', '0') === '1';
    }
}

if (!function_exists('reseller_ip_allowed')) {
    /** Inherit the admin panel's IP allow-list (setting.iplogin). */
    function reseller_ip_allowed()
    {
        $raw = trim((string) reseller_setting('iplogin', ''));
        if ($raw === '' || $raw === '0' || $raw === '*' || $raw === 'all' || $raw === 'unlimited') {
            return true;
        }
        $list = [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (in_array('*', $decoded, true) || in_array('all', $decoded, true) || in_array('unlimited', $decoded, true)) {
                return true;
            }
            $list = $decoded;
        } elseif (filter_var($raw, FILTER_VALIDATE_IP)) {
            $list = [$raw];
        }
        if (!$list) {
            return true;
        }
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', $list, true);
    }
}

/* --------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_csrf_token')) {
    function reseller_csrf_token()
    {
        if (empty($_SESSION['reseller_csrf'])) {
            $_SESSION['reseller_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['reseller_csrf'];
    }
}

if (!function_exists('reseller_csrf_check')) {
    function reseller_csrf_check()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $incoming = $_POST['_csrf'] ?? '';
        if (!is_string($incoming) || !hash_equals((string) ($_SESSION['reseller_csrf'] ?? ''), $incoming)) {
            http_response_code(403);
            exit('درخواست نامعتبر — توکن CSRF اشتباه است');
        }
    }
}

/* --------------------------------------------------------------------------
 * Authentication
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_find_by_username')) {
    function reseller_find_by_username($username)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM reseller WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('reseller_find_by_id')) {
    function reseller_find_by_id($id)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM reseller WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('reseller_current')) {
    /** Returns the logged-in reseller row (fresh from DB) or null. */
    function reseller_current()
    {
        if (empty($_SESSION['reseller_id'])) {
            return null;
        }
        $row = reseller_find_by_id($_SESSION['reseller_id']);
        if (!$row || (string) ($row['status'] ?? '') !== 'active') {
            return null;
        }
        return $row;
    }
}

if (!function_exists('reseller_require_login')) {
    function reseller_require_login()
    {
        $r = reseller_current();
        if (!$r) {
            header('Location: login.php');
            exit;
        }
        if (!reseller_ip_allowed()) {
            $_SESSION = [];
            header('Location: login.php');
            exit;
        }
        return $r;
    }
}

if (!function_exists('reseller_verify_password')) {
    function reseller_verify_password($plain, $hash)
    {
        $plain = (string) $plain;
        $hash = (string) $hash;
        if ($hash === '') {
            return false;
        }
        // Stored as password_hash() output; tolerate a legacy plaintext value.
        if (password_verify($plain, $hash)) {
            return true;
        }
        if (!preg_match('/^\$(2y|argon)/', $hash) && hash_equals($hash, $plain)) {
            return true;
        }
        return false;
    }
}

/* --------------------------------------------------------------------------
 * Wallet + accounting ledger
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_wallet_apply')) {
    /**
     * Atomically change a reseller's balance and record a ledger entry.
     *
     * @param int    $resellerId
     * @param string $type   topup|purchase|withdraw|withdraw_refund|admin_credit|admin_debit
     * @param int    $amount Signed Toman (positive = credit, negative = debit).
     * @param string $description
     * @param string $ref
     * @param bool   $allowNegative Permit the balance to drop below zero.
     * @return array ['ok'=>bool,'balance'=>int,'msg'=>string]
     */
    function reseller_wallet_apply($resellerId, $type, $amount, $description = '', $ref = '', $allowNegative = false)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'balance' => 0, 'msg' => 'no database'];
        }
        $resellerId = (int) $resellerId;
        $amount = (int) round((float) $amount);
        try {
            $pdo->beginTransaction();
            $sel = $pdo->prepare("SELECT balance FROM reseller WHERE id = :id FOR UPDATE");
            $sel->execute([':id' => $resellerId]);
            $cur = $sel->fetchColumn();
            if ($cur === false) {
                $pdo->rollBack();
                return ['ok' => false, 'balance' => 0, 'msg' => 'reseller not found'];
            }
            $cur = (int) $cur;
            $new = $cur + $amount;
            if ($new < 0 && !$allowNegative) {
                $pdo->rollBack();
                return ['ok' => false, 'balance' => $cur, 'msg' => 'موجودی کافی نیست'];
            }
            $upd = $pdo->prepare("UPDATE reseller SET balance = :b WHERE id = :id");
            $upd->execute([':b' => $new, ':id' => $resellerId]);
            $ins = $pdo->prepare(
                "INSERT INTO reseller_ledger (reseller_id, type, amount, balance_after, description, ref, created_at)
                 VALUES (:rid, :type, :amount, :bal, :desc, :ref, :ts)"
            );
            $ins->execute([
                ':rid'    => $resellerId,
                ':type'   => $type,
                ':amount' => $amount,
                ':bal'    => $new,
                ':desc'   => mb_substr((string) $description, 0, 490),
                ':ref'    => mb_substr((string) $ref, 0, 180),
                ':ts'     => (string) time(),
            ]);
            $pdo->commit();
            return ['ok' => true, 'balance' => $new, 'msg' => ''];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[reseller_wallet_apply] ' . $e->getMessage());
            return ['ok' => false, 'balance' => 0, 'msg' => 'خطای پایگاه داده'];
        }
    }
}

/* --------------------------------------------------------------------------
 * Products available to a reseller
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_allowed_products')) {
    /**
     * Products a reseller may sell: admin-flagged (reseller_status='1') AND, when
     * the reseller has an explicit allowed_products whitelist, restricted to it.
     */
    function reseller_allowed_products(array $reseller)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $stmt = $pdo->query("SELECT * FROM product WHERE reseller_status = '1' ORDER BY id DESC");
        $all = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $whitelist = [];
        $raw = trim((string) ($reseller['allowed_products'] ?? ''));
        if ($raw !== '' && $raw !== '[]') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $whitelist = array_map('strval', $decoded);
            }
        }
        if (!$whitelist) {
            return $all;
        }
        return array_values(array_filter($all, static function ($p) use ($whitelist) {
            return in_array((string) ($p['code_product'] ?? ''), $whitelist, true);
        }));
    }
}

if (!function_exists('reseller_product_price')) {
    /** Price charged to the reseller wallet for a product (reseller_price overrides). */
    function reseller_product_price(array $product)
    {
        $rp = trim((string) ($product['reseller_price'] ?? ''));
        if ($rp !== '' && is_numeric($rp)) {
            return (int) round((float) $rp);
        }
        return (int) round((float) ($product['price_product'] ?? 0));
    }
}

if (!function_exists('reseller_min_withdraw')) {
    function reseller_min_withdraw(array $reseller)
    {
        $perReseller = trim((string) ($reseller['min_withdraw'] ?? ''));
        if ($perReseller !== '' && is_numeric($perReseller)) {
            return (int) $perReseller;
        }
        $global = trim((string) reseller_setting('reseller_min_withdraw', '500000'));
        return is_numeric($global) ? (int) $global : 500000;
    }
}

/* --------------------------------------------------------------------------
 * Presentation helpers
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_money')) {
    function reseller_money($amount)
    {
        return number_format((float) $amount) . ' تومان';
    }
}

if (!function_exists('reseller_jdate')) {
    function reseller_jdate($timestamp, $format = 'Y/m/d H:i')
    {
        $ts = (int) $timestamp;
        if ($ts <= 0) {
            return '—';
        }
        if (function_exists('jdate')) {
            return jdate($format, $ts);
        }
        return date($format, $ts);
    }
}

if (!function_exists('reseller_e')) {
    function reseller_e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('reseller_count_active_services')) {
    function reseller_count_active_services($resellerId)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return 0;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_service WHERE reseller_id = :id AND status = 'active'");
        $stmt->execute([':id' => (int) $resellerId]);
        return (int) $stmt->fetchColumn();
    }
}

/* --------------------------------------------------------------------------
 * Reseller-bot customers (Phase 4): per-reseller customer wallets
 * ------------------------------------------------------------------------ */

if (!function_exists('reseller_customer_get')) {
    /** Fetch (and lazily create) a customer row for a reseller's bot. */
    function reseller_customer_get($resellerId, $chatId, $firstName = '', $username = '')
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $resellerId = (int) $resellerId;
        $chatId = (int) $chatId;
        $sel = $pdo->prepare("SELECT * FROM reseller_customer WHERE reseller_id = :r AND chat_id = :c LIMIT 1");
        $sel->execute([':r' => $resellerId, ':c' => $chatId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // فقط فیلدهای واقعاً ارائه‌شده را به‌روزرسانی کن؛ در غیر این صورت فراخوانی‌هایی که نام/یوزرنیم
            // ندارند (مثل کال‌بک پرداخت) مقادیر ذخیره‌شده را با رشته‌ی خالی بازنویسی می‌کردند.
            $updFields = 'last_seen = :ts';
            $updParams = [':ts' => (string) time(), ':id' => (int) $row['id']];
            if ($firstName !== '') {
                $updFields .= ', first_name = :fn';
                $updParams[':fn'] = mb_substr((string) $firstName, 0, 190);
            }
            if ($username !== '') {
                $updFields .= ', username = :un';
                $updParams[':un'] = mb_substr((string) $username, 0, 190);
            }
            $pdo->prepare("UPDATE reseller_customer SET {$updFields} WHERE id = :id")
                ->execute($updParams);
            return $row;
        }
        $ins = $pdo->prepare(
            "INSERT INTO reseller_customer (reseller_id, chat_id, first_name, username, balance, step, created_at, last_seen)
             VALUES (:r, :c, :fn, :un, 0, '', :ts, :ts)"
        );
        $ins->execute([':r' => $resellerId, ':c' => $chatId, ':fn' => mb_substr((string) $firstName, 0, 190), ':un' => mb_substr((string) $username, 0, 190), ':ts' => (string) time()]);
        $sel->execute([':r' => $resellerId, ':c' => $chatId]);
        return $sel->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reseller_customer_set_step')) {
    function reseller_customer_set_step($customerId, $step, $stepData = '')
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return;
        }
        $pdo->prepare("UPDATE reseller_customer SET step = :s, step_data = :d WHERE id = :id")
            ->execute([':s' => (string) $step, ':d' => (string) $stepData, ':id' => (int) $customerId]);
    }
}

if (!function_exists('reseller_customer_wallet_apply')) {
    /**
     * Atomically change a customer's wallet (with a given reseller).
     * @return array ['ok'=>bool,'balance'=>int,'msg'=>string]
     */
    function reseller_customer_wallet_apply($customerId, $amount, $allowNegative = false)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'balance' => 0, 'msg' => 'no database'];
        }
        $customerId = (int) $customerId;
        $amount = (int) round((float) $amount);
        try {
            $pdo->beginTransaction();
            $sel = $pdo->prepare("SELECT balance FROM reseller_customer WHERE id = :id FOR UPDATE");
            $sel->execute([':id' => $customerId]);
            $cur = $sel->fetchColumn();
            if ($cur === false) {
                $pdo->rollBack();
                return ['ok' => false, 'balance' => 0, 'msg' => 'customer not found'];
            }
            $cur = (int) $cur;
            $new = $cur + $amount;
            if ($new < 0 && !$allowNegative) {
                $pdo->rollBack();
                return ['ok' => false, 'balance' => $cur, 'msg' => 'موجودی کافی نیست'];
            }
            $pdo->prepare("UPDATE reseller_customer SET balance = :b WHERE id = :id")
                ->execute([':b' => $new, ':id' => $customerId]);
            $pdo->commit();
            return ['ok' => true, 'balance' => $new, 'msg' => ''];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[reseller_customer_wallet_apply] ' . $e->getMessage());
            return ['ok' => false, 'balance' => 0, 'msg' => 'خطای پایگاه داده'];
        }
    }
}

if (!function_exists('reseller_sell_price')) {
    /**
     * Customer-facing price for a product sold by a reseller's bot.
     * Falls back to the product's normal price when the reseller set none.
     */
    function reseller_sell_price($resellerId, array $product)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT sell_price FROM reseller_product_sell WHERE reseller_id = :r AND product_code = :c LIMIT 1");
            $stmt->execute([':r' => (int) $resellerId, ':c' => (string) ($product['code_product'] ?? '')]);
            $v = $stmt->fetchColumn();
            if ($v !== false && (int) $v > 0) {
                return (int) $v;
            }
        }
        return (int) round((float) ($product['price_product'] ?? 0));
    }
}

if (!function_exists('reseller_find_product')) {
    /** Find an allowed product for a reseller by its code_product. */
    function reseller_find_product(array $reseller, $code)
    {
        foreach (reseller_allowed_products($reseller) as $p) {
            if ((string) ($p['code_product'] ?? '') === (string) $code) {
                return $p;
            }
        }
        return null;
    }
}

if (!function_exists('reseller_available_panels')) {
    /** Names of enabled panels usable for "/all" location products. */
    function reseller_available_panels()
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $rows = $pdo->query("SELECT name_panel FROM marzban_panel ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_filter(array_map(static function ($p) {
            return (string) ($p['name_panel'] ?? '');
        }, $rows), static function ($n) {
            return $n !== '';
        }));
    }
}

if (!function_exists('reseller_resolve_panel')) {
    /** Resolve the destination panel for a product (its Location, or a chosen one). */
    function reseller_resolve_panel(array $product, $panelChoice = '')
    {
        $loc = trim((string) ($product['Location'] ?? ''));
        if ($loc !== '' && $loc !== '/all') {
            return $loc;
        }
        $panels = reseller_available_panels();
        if ($panelChoice !== '' && in_array($panelChoice, $panels, true)) {
            return $panelChoice;
        }
        if (count($panels) === 1) {
            return $panels[0];
        }
        return '';
    }
}

if (!function_exists('reseller_provision_service')) {
    /**
     * Provision a VPN service on a panel and record it in reseller_service.
     * Wallet charging is intentionally left to the caller (panel vs. bot flows
     * have different financial models). Requires a loaded ManagePanel instance.
     *
     * @return array ['ok'=>bool,'msg'=>string,'sub_token'=>string,'sub_link'=>string,'service_id'=>int]
     */
    function reseller_provision_service(array $reseller, array $product, $panelName, $usernameC, $ManagePanel, array $opts = [])
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'msg' => 'no database'];
        }
        $rid = (int) $reseller['id'];
        $usernameC = preg_replace('/[^A-Za-z0-9_]/', '', (string) $usernameC);
        if (strlen($usernameC) < 3) {
            return ['ok' => false, 'msg' => 'نام کاربری باید حداقل ۳ کاراکتر انگلیسی باشد.'];
        }

        $days = (int) ($product['Service_time'] ?? 0);
        $volumeGb = (float) ($product['Volume_constraint'] ?? 0);
        $expire = $days > 0 ? strtotime('+' . $days . ' days') : 0;
        $dataLimit = $volumeGb > 0 ? (int) round($volumeGb * pow(1024, 3)) : 0;

        $datac = [
            'expire'     => $expire,
            'data_limit' => $dataLimit,
            'from_id'    => 'reseller:' . $rid,
            'username'   => (string) $reseller['username'],
            'type'       => 'reseller',
        ];

        try {
            $out = $ManagePanel->createUser($panelName, (string) $product['code_product'], $usernameC, $datac);
        } catch (\Throwable $e) {
            error_log('[reseller_provision_service] createUser: ' . $e->getMessage());
            $out = ['status' => 'Unsuccessful', 'msg' => 'خطای داخلی در ساخت سرویس'];
        }

        if (!is_array($out) || empty($out['username']) || (($out['status'] ?? '') !== 'successful' && empty($out['subscription_url']))) {
            $msg = is_array($out) && isset($out['msg'])
                ? (is_string($out['msg']) ? $out['msg'] : json_encode($out['msg'], JSON_UNESCAPED_UNICODE))
                : 'نامشخص';
            return ['ok' => false, 'msg' => 'ساخت سرویس در پنل ناموفق بود: ' . $msg];
        }

        $subToken = bin2hex(random_bytes(16));
        $configs = $out['configs'] ?? [];
        $ins = $pdo->prepare(
            "INSERT INTO reseller_service
                (reseller_id, product_code, panel_name, username, uuid, sub_token, sub_link, customer_name, customer_chat_id, volume_gb, days, price, sell_price, sold_via, status, created_at, expire_at)
             VALUES
                (:rid, :code, :panel, :uname, :uuid, :tok, :sub, :cname, :cchat, :vol, :days, :price, :sell, :sold, 'active', :ts, :exp)"
        );
        $ins->execute([
            ':rid'   => $rid,
            ':code'  => (string) $product['code_product'],
            ':panel' => (string) $panelName,
            ':uname' => (string) $out['username'],
            ':uuid'  => is_array($configs) ? json_encode($configs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $configs,
            ':tok'   => $subToken,
            ':sub'   => (string) ($out['subscription_url'] ?? ''),
            ':cname' => mb_substr((string) ($opts['customer_name'] ?? ''), 0, 190),
            ':cchat' => preg_replace('/[^0-9]/', '', (string) ($opts['customer_chat'] ?? '')),
            ':vol'   => (string) $volumeGb,
            ':days'  => (string) $days,
            ':price' => (int) round((float) ($opts['price'] ?? reseller_product_price($product))),
            ':sell'  => (string) (int) round((float) ($opts['sell_price'] ?? 0)),
            ':sold'  => (string) ($opts['sold_via'] ?? 'panel'),
            ':ts'    => (string) time(),
            ':exp'   => $expire > 0 ? (string) $expire : '',
        ]);

        return [
            'ok'         => true,
            'msg'        => '',
            'sub_token'  => $subToken,
            'sub_link'   => (string) ($out['subscription_url'] ?? ''),
            'service_id' => (int) $pdo->lastInsertId(),
        ];
    }
}
