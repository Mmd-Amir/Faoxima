<?php


if (!function_exists('rx_host_profile_cache_path')) {
    function rx_host_profile_cache_path()
    {
        $root = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT
              : (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : dirname(__DIR__, 3));
        $cronbot = $root . DIRECTORY_SEPARATOR . 'cronbot';
        $runtime = $cronbot . DIRECTORY_SEPARATOR . '.runtime';
        if (@is_dir($runtime) || @mkdir($runtime, 0775, true) || @is_dir($runtime)) {
            return $runtime . DIRECTORY_SEPARATOR . 'host_profile.json';
        }
        if (@is_dir($cronbot)) {
            return $cronbot . DIRECTORY_SEPARATOR . 'host_profile.json';
        }
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_host_profile_' . md5((string) $root) . '.json';
    }
}

if (!function_exists('rx_exec_disabled')) {
    function rx_exec_disabled()
    {
        foreach (['exec', 'shell_exec'] as $fn) {
            if (!function_exists($fn)) {
                return true;
            }
        }
        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return false;
        }
        foreach (['exec', 'shell_exec'] as $fn) {
            if (preg_match('/(^|,)\s*' . $fn . '\s*(,|$)/', $disabled)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('rx_putenv_disabled')) {
    function rx_putenv_disabled()
    {
        if (!function_exists('putenv')) {
            return true;
        }
        $disabled = strtolower((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return false;
        }
        return (bool) preg_match('/(^|,)\s*putenv\s*(,|$)/', $disabled);
    }
}

if (!function_exists('rx_mysql_show_value')) {
    function rx_mysql_show_value($pdo, $kind, $name)
    {
        try {
            if (!($pdo instanceof PDO)) {
                return 0;
            }
            $kind = ($kind === 'STATUS') ? 'STATUS' : 'VARIABLES';
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
            if ($name === '') {
                return 0;
            }
            $stmt = $pdo->query("SHOW {$kind} LIKE '{$name}'");
            if ($stmt === false) {
                return 0;
            }
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return (is_array($row) && isset($row[1])) ? $row[1] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('rx_resize_for_profile')) {
    function rx_resize_for_profile($maxConnections, $profile)
    {
        $maxConnections = (int) $maxConnections;
        if ($profile === 'vps') {
            return [
                'cron_db_budget'    => ($maxConnections > 0) ? max(20, (int) floor($maxConnections * 0.20)) : 20,
                'cron_time_budget'  => 120,
                'broadcast_workers' => 4,
                'payment_workers'   => 4,
            ];
        }
        return [
            'cron_db_budget'    => ($maxConnections > 0) ? max(4, min(8, (int) floor($maxConnections * 0.06))) : 6,
            'cron_time_budget'  => 22,
            'broadcast_workers' => 1,
            'payment_workers'   => 1,
        ];
    }
}

if (!function_exists('rx_detect_host_profile')) {
    function rx_detect_host_profile()
    {
        $maxConn = 0;
        $maxUsed = 0;
        $maxUser = -1;
        try {
            $pdo = function_exists('getDatabaseConnection') ? getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
            if ($pdo instanceof PDO) {
                $maxConn = (int) rx_mysql_show_value($pdo, 'VARIABLES', 'max_connections');
                $maxUser = (int) rx_mysql_show_value($pdo, 'VARIABLES', 'max_user_connections');
                $maxUsed = (int) rx_mysql_show_value($pdo, 'STATUS', 'Max_used_connections');
            }
        } catch (\Throwable $e) {
        }

        $execDisabled = rx_exec_disabled();
        $cpanel       = @is_dir('/usr/local/cpanel');

        $isShared = $cpanel
            || $execDisabled
            || ($maxUser > 0 && $maxUser <= 50);
        $profile = $isShared ? 'shared' : 'vps';

        return array_merge([
            'profile'              => $profile,
            'max_connections'      => $maxConn,
            'max_used_connections' => $maxUsed,
            'max_user_connections' => $maxUser,
            'exec_disabled'        => $execDisabled ? 1 : 0,
            'cpanel'               => $cpanel ? 1 : 0,
            'detected_at'          => time(),
        ], rx_resize_for_profile($maxConn, $profile));
    }
}

if (!function_exists('rx_host_profile')) {
    function rx_host_profile()
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $path     = rx_host_profile_cache_path();
        $resolved = null;

        if ($path !== '' && @is_file($path)) {
            $age = time() - (int) @filemtime($path);
            if ($age >= 0 && $age < 604800) {
                $raw     = @file_get_contents($path);
                $decoded = ($raw !== false) ? json_decode($raw, true) : null;
                if (is_array($decoded) && isset($decoded['profile'])) {
                    $resolved = $decoded;
                }
            }
        }

        if ($resolved === null) {
            try {
                $resolved = rx_detect_host_profile();
            } catch (\Throwable $e) {
                $resolved = array_merge(['profile' => 'shared', 'max_connections' => 0], rx_resize_for_profile(0, 'shared'));
            }
        }

        try {
            $s = function_exists('select') ? select('setting', '*') : null;
            if (is_array($s)) {
                $override = isset($s['host_profile']) ? strtolower(trim((string) $s['host_profile'])) : '';
                if (($override === 'shared' || $override === 'vps') && $override !== ($resolved['profile'] ?? '')) {
                    $resolved = array_merge($resolved, ['profile' => $override], rx_resize_for_profile((int) ($resolved['max_connections'] ?? 0), $override));
                }
                if (isset($s['cron_db_budget']) && (int) $s['cron_db_budget'] > 0) {
                    $resolved['cron_db_budget'] = (int) $s['cron_db_budget'];
                }
                if (isset($s['cron_time_budget']) && (int) $s['cron_time_budget'] > 0) {
                    $resolved['cron_time_budget'] = (int) $s['cron_time_budget'];
                }
            }
        } catch (\Throwable $e) {
        }

        if ($path !== '') {
            $new     = json_encode($resolved, JSON_UNESCAPED_UNICODE);
            $current = (@is_file($path)) ? (string) @file_get_contents($path) : '';
            if ($new !== false && $new !== $current) {
                @file_put_contents($path, $new, LOCK_EX);
            }
        }

        $cache = $resolved;
        return $cache;
    }
}

if (!function_exists('rx_cron_db_budget')) {
    function rx_cron_db_budget()
    {
        $p = rx_host_profile();
        return max(1, (int) ($p['cron_db_budget'] ?? 6));
    }
}

if (!function_exists('rx_cron_time_budget')) {
    function rx_cron_time_budget()
    {
        $p = rx_host_profile();
        return max(5, (int) ($p['cron_time_budget'] ?? 22));
    }
}

if (!function_exists('rx_release_unpaid_discount')) {

    function rx_release_unpaid_discount($userId, $discountCode = null, $referenceTime = null) {
        global $pdo;
        $userId = trim((string)$userId);
        if ($userId === '' || !isset($pdo)) return false;
        try {
            if ($referenceTime !== null && (int)$referenceTime > 0) {
                $low = (string)((int)$referenceTime - 900);
                $high = (string)((int)$referenceTime + 900);
            } else {
                $low = (string)(time() - 1800);
                $high = (string)(time() + 60);
            }
            $params = [':u' => $userId, ':lo' => $low, ':hi' => $high];
            $codeClause = '';
            if ($discountCode !== null && trim((string)$discountCode) !== '') {
                $codeClause = ' AND code = :c';
                $params[':c'] = trim((string)$discountCode);
            }
            $stmt = $pdo->prepare(
                "SELECT id, code FROM Giftcodeconsumed
                  WHERE id_user = :u
                    AND kind = 'sell'
                    AND (released IS NULL OR released = 0)
                    AND consumed_at <> ''
                    AND CAST(consumed_at AS UNSIGNED) BETWEEN :lo AND :hi" . $codeClause . "
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || empty($row['code'])) return false;

            $code = (string)$row['code'];
            $rowId = (int)$row['id'];

            $marked = $pdo->prepare('UPDATE Giftcodeconsumed SET released = 1 WHERE id = :id AND (released IS NULL OR released = 0)');
            $marked->execute([':id' => $rowId]);
            if ($marked->rowCount() < 1) return false;

            $ds = $pdo->prepare('SELECT usedDiscount FROM DiscountSell WHERE codeDiscount = :c LIMIT 1');
            $ds->execute([':c' => $code]);
            $dsRow = $ds->fetch(PDO::FETCH_ASSOC);
            if (is_array($dsRow)) {
                $used = (int)($dsRow['usedDiscount'] ?? 0) - 1;
                if ($used < 0) $used = 0;
                $pdo->prepare('UPDATE DiscountSell SET usedDiscount = :v WHERE codeDiscount = :c')
                    ->execute([':v' => (string)$used, ':c' => $code]);
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}


if (!function_exists('balance_atomic_charge')) {

    function balance_atomic_charge($userId, $delta, $allowNegativeUpTo = 0) {
        global $pdo;
        $delta = (float) $delta;
        if ($delta <= 0) return ['ok' => false, 'reason' => 'invalid-delta', 'new_balance' => null];
        $allowNegativeUpTo = max(0.0, (float) $allowNegativeUpTo);


        $minBalance = $delta - $allowNegativeUpTo;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance - :d WHERE id = :u AND Balance >= :m");
            $stmt->execute([':d' => $delta, ':u' => $userId, ':m' => $minBalance]);
            if ($stmt->rowCount() < 1) {
                return ['ok' => false, 'reason' => 'insufficient-or-stale', 'new_balance' => null];
            }
            $sel = $pdo->prepare("SELECT Balance FROM user WHERE id = :u");
            $sel->execute([':u' => $userId]);
            $newBal = $sel->fetchColumn();
            return ['ok' => true, 'reason' => 'charged', 'new_balance' => (float) $newBal];
        } catch (Throwable $e) {
            error_log('balance_atomic_charge failed: ' . $e->getMessage());
            return ['ok' => false, 'reason' => 'db-error', 'new_balance' => null];
        }
    }
}


if (!function_exists('nm_validateSellDiscount')) {
    function nm_validateSellDiscount($code, $section, $codeProduct, $codePanel, $user, $from_id)
    {
        global $pdo;
        $code = trim((string)$code);
        $sections = ['buy', 'extend', 'volume', 'time', 'charge', 'all'];
        $section = in_array($section, $sections, true) ? $section : 'all';
        $res = ['ok' => false, 'reason' => '', 'row' => null, 'value_type' => 'percent', 'value' => 0.0, 'label' => ''];

        if ($code === '') {
            $res['reason'] = '❌ کد تخفیف را وارد کنید.';
            return $res;
        }

        if ($section !== 'charge' && intval($user['pricediscount'] ?? 0) != 0) {
            $res['reason'] = '❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.';
            return $res;
        }

        $agent       = (string)($user['agent'] ?? 'f');
        $codeProduct = ($codeProduct === '' || $codeProduct === null) ? 'all' : $codeProduct;
        $codePanel   = ($codePanel === '' || $codePanel === null) ? '/all' : $codePanel;

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM DiscountSell
                  WHERE codeDiscount = :code
                    AND (code_product = :cp OR code_product = 'all')
                    AND (code_panel = :cpan OR code_panel = '/all')
                    AND (agent = :agent OR agent = 'allusers' OR agent = 'all')
                    AND (COALESCE(NULLIF(section, ''), type, 'all') = :section
                         OR COALESCE(NULLIF(section, ''), type, 'all') = 'all')
                    AND (status IS NULL OR status = '' OR status = 'active')
                    AND (target_user IS NULL OR target_user = '' OR target_user = :uid)
                  LIMIT 1"
            );
            $stmt->execute([
                ':code'   => $code,
                ':cp'     => $codeProduct,
                ':cpan'   => $codePanel,
                ':agent'  => $agent,
                ':section' => $section,
                ':uid'    => (string)$from_id,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('nm_validateSellDiscount query failed: ' . $e->getMessage());
            $res['reason'] = '❌ خطا در بررسی کد تخفیف.';
            return $res;
        }

        if (!$row) {
            $res['reason'] = '❌ کد تخفیف نامعتبر است یا برای این بخش فعال نیست.';
            return $res;
        }

        if (intval($row['time']) != 0 && time() >= intval($row['time'])) {
            $res['reason'] = '❌ زمان کد تخفیف به پایان رسیده است.';
            return $res;
        }

        if (intval($row['limitDiscount']) > 0 && intval($row['usedDiscount']) >= intval($row['limitDiscount'])) {
            $res['reason'] = '❌ ظرفیت استفاده از این کد تخفیف به پایان رسیده است.';
            return $res;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Giftcodeconsumed WHERE id_user = :u AND code = :c");
            $stmt->execute([':u' => (string)$from_id, ':c' => $code]);
            $usedByUser = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $usedByUser = 0;
        }
        $useUser = intval($row['useuser']);
        if ($useUser > 0 && $usedByUser >= $useUser) {
            $res['reason'] = '⭕️ سقف استفاده شما از این کد تخفیف پر شده است.';
            return $res;
        }

        if ((string)($row['usefirst'] ?? '') === '1') {
            $invoiceCount = MiniDiscount::completedPurchaseCount((string)$from_id);
            if (intval($invoiceCount) != 0) {
                $res['reason'] = '❌ این کد تخفیف فقط برای اولین خرید قابل استفاده است.';
                return $res;
            }
        }

        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)$row['price'];
        if ($vt === 'percent' && ($val <= 0 || $val > 100)) {
            $res['reason'] = '❌ درصد کد تخفیف نامعتبر است.';
            return $res;
        }
        if ($vt === 'amount' && $val <= 0) {
            $res['reason'] = '❌ مبلغ کد تخفیف نامعتبر است.';
            return $res;
        }

        $label = $vt === 'free'
            ? 'رایگان'
            : ($vt === 'amount' ? number_format($val) . ' تومان' : (string)$row['price'] . ' درصد');

        $res['ok']         = true;
        $res['row']        = $row;
        $res['value_type'] = $vt;
        $res['value']      = $val;
        $res['label']      = $label;
        return $res;
    }
}

if (!function_exists('nm_applySellDiscountToPrice')) {
    function nm_applySellDiscountToPrice($row, $price)
    {
        $price = (float)$price;
        $vt = strtolower(trim((string)($row['value_type'] ?? '')));
        if (!in_array($vt, ['percent', 'amount', 'free'], true)) {
            $tcol = strtolower(trim((string)($row['type'] ?? '')));
            $vt = in_array($tcol, ['percent', 'amount', 'free'], true) ? $tcol : 'percent';
        }
        $val = (float)($row['price'] ?? 0);
        if ($vt === 'free')   return 0.0;
        if ($vt === 'amount') return max(0.0, $price - $val);
        return max(0.0, $price - ($price * $val / 100));
    }
}

if (!function_exists('nm_markSellDiscountUsed')) {
    function nm_markSellDiscountUsed($code, $from_id, $username = '', $reportContext = '')
    {
        global $connect, $setting, $otherreport;
        $code = trim((string)$code);
        if ($code === '') return;

        try {
            $row = select("DiscountSell", "*", "codeDiscount", $code, "select");
            if ($row != false) {
                $value = intval($row['usedDiscount']) + 1;
                update("DiscountSell", "usedDiscount", $value, "codeDiscount", $code);
            }
        } catch (Throwable $e) {
            error_log('nm_markSellDiscountUsed update failed: ' . $e->getMessage());
        }

        try {
            $now  = (string)time();
            $kind = 'sell';
            $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code, kind, consumed_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $from_id, $code, $kind, $now);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            try {
                $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)");
                $stmt->bind_param("ss", $from_id, $code);
                $stmt->execute();
                $stmt->close();
            } catch (Throwable $e2) {
            }
        }

        if (isset($setting['Channel_Report']) && strlen((string)$setting['Channel_Report']) > 0) {
            $uname = $username !== '' ? "@{$username} " : '';
            $ctx   = $reportContext !== '' ? " (بخش: {$reportContext})" : '';
            $text_report = "⭕️ کاربر {$uname}با آیدی عددی {$from_id} از کد تخفیف {$code}{$ctx} استفاده کرد.";
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport ?? null,
                'text' => $text_report,
                'parse_mode' => "HTML",
            ]);
        }
    }
}

if (!function_exists('nm_pending_charge_bonus')) {
    function nm_pending_charge_bonus($user)
    {
        $pv4 = (string)($user['Processing_value_four'] ?? '');
        if (strpos($pv4, 'chg|') !== 0) return 0;
        $parts = explode('|', $pv4);
        $bonus = isset($parts[1]) ? intval($parts[1]) : 0;
        return max(0, $bonus);
    }
}

if (!function_exists('balance_atomic_credit')) {

    function balance_atomic_credit($userId, $delta) {
        global $pdo;
        $delta = (float) $delta;
        if ($delta <= 0) return false;
        try {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :u");
            $stmt->execute([':d' => $delta, ':u' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('balance_atomic_credit failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('wallet_ledger_record')) {

    function wallet_ledger_record($userId, string $direction, $amount, string $category, ?string $description = null, ?string $idOrder = null, ?string $refTable = null, ?string $refId = null): bool {
        global $pdo;
        $amount = (int) round((float) $amount);
        if ($amount <= 0 || !in_array($direction, ['credit', 'debit'], true)) return false;
        try {
            $balanceAfter = null;
            $sel = $pdo->prepare("SELECT Balance FROM user WHERE id = :u");
            $sel->execute([':u' => $userId]);
            $bal = $sel->fetchColumn();
            if ($bal !== false) {
                $balanceAfter = (int) $bal;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO wallet_ledger (id_user, direction, amount, balance_after, category, description, id_order, ref_table, ref_id) "
                . "VALUES (:id_user, :direction, :amount, :balance_after, :category, :description, :id_order, :ref_table, :ref_id)"
            );
            $stmt->execute([
                ':id_user' => $userId,
                ':direction' => $direction,
                ':amount' => $amount,
                ':balance_after' => $balanceAfter,
                ':category' => $category,
                ':description' => $description,
                ':id_order' => $idOrder,
                ':ref_table' => $refTable,
                ':ref_id' => $refId,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('wallet_ledger_record failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('affiliate_should_pay_commission')) {

    function affiliate_should_pay_commission(array $affiliatesSettings, int $paidInvoiceCount): bool {
        if (($affiliatesSettings['status_commission'] ?? '') !== 'oncommission') return false;
        if (($affiliatesSettings['porsant_one_buy'] ?? '') === 'on_buy_porsant') {
            return $paidInvoiceCount <= 1;
        }
        return true;
    }
}

if (!function_exists('affiliate_paid_invoice_count')) {

    function affiliate_paid_invoice_count($buyerId): int {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE name_product != 'سرویس تست' AND id_user = :id_user AND Status != 'Unpaid'");
        $stmt->bindValue(':id_user', $buyerId);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('payAffiliateCommissionForPurchase')) {

    function payAffiliateCommissionForPurchase($buyerId, $referrerId, float $purchasePrice, ?int $paidInvoiceCount = null): ?float {
        global $pdo, $setting;
        $affiliatesSettings = select('affiliates', '*', null, null, 'select');
        if (!is_array($affiliatesSettings)) return null;
        if (empty($referrerId) || (int) $referrerId === 0) return null;

        if ($paidInvoiceCount === null) {
            $paidInvoiceCount = affiliate_paid_invoice_count($buyerId);
        }

        if (!affiliate_should_pay_commission($affiliatesSettings, $paidInvoiceCount)) {
            return null;
        }

        $rate = (float) ($setting['affiliatespercentage'] ?? 0);
        $rate = min(100, max(0, $rate));
        $commission = ($purchasePrice * $rate) / 100;
        if ($commission <= 0) return null;

        $referrer = select('user', '*', 'id', $referrerId, 'select');
        if (empty($referrer)) return null;

        if (function_exists('balance_atomic_credit')) {
            balance_atomic_credit($referrerId, $commission);
        } else {
            $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :d WHERE id = :u");
            $stmt->execute([':d' => $commission, ':u' => $referrerId]);
        }
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($referrerId, 'credit', $commission, 'affiliate_commission', 'پورسانت خرید زیرمجموعه', null, 'user', (string) $buyerId);
        }

        if ((int) ($setting['scorestatus'] ?? 0) === 1 && function_exists('sendmessage')) {
            $admin_ids = $GLOBALS['admin_ids'] ?? [];
            if (!in_array($referrerId, $admin_ids)) {
                sendmessage($referrerId, "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                update('user', 'score', (int) $referrer['score'] + 2, 'id', $referrerId);
            }
        }

        return $commission;
    }
}


function StatusPayment($paymentid)
{
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment", "select");
    $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    if ($apinowpayments === '' || $apinowpayments === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller", "select");
        $apinowpayments = is_array($row) ? trim((string)($row['ValuePay'] ?? '')) : '';
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
}
function channel(array $id_channel)
{
    global $from_id;
    $channel_link = [];
    foreach ($id_channel as $channel) {
        $channel = trim((string) $channel);
        if ($channel === '') {
            continue;
        }
        $response = telegram('getChatMember', [
            'chat_id' => $channel,
            'user_id' => $from_id
        ]);
        $isMember = false;
        if (isset($response['ok']) && $response['ok']) {
            $status = $response['result']['status'] ?? '';
            if (in_array($status, ['member', 'creator', 'administrator'], true)) {
                $isMember = true;
            } elseif ($status === 'restricted' && !empty($response['result']['is_member'])) {
                $isMember = true;
            }
        }
        if (!$isMember) {
            $channel_link[] = $channel;
        }
    }
    return $channel_link;
}
function isValidDate($date)
{
    return (strtotime($date) != false);
}
function rxGatewayTruthy($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'success', 'successful', 'ok', 'yes'], true);
    }
    return false;
}

function tronadoExtractPaymentToken($payment)
{
    if (!is_array($payment)) {
        return '';
    }
    $candidate = $payment['Token'] ?? ($payment['Data']['Token'] ?? '');
    return trim((string) $candidate);
}

function tronadoNormalizeOrderTokenResponse($decoded)
{
    if (!is_array($decoded)) {
        return $decoded;
    }
    if (array_key_exists('Token', $decoded)) {
        return $decoded;
    }
    if (isset($decoded['Data']) && is_array($decoded['Data'])) {
        $normalized = $decoded['Data'];
        if (!array_key_exists('ErrorMessage', $normalized) && isset($decoded['Message'])) {
            $normalized['ErrorMessage'] = $decoded['Message'];
        }
        return $normalized;
    }

    // A rejected GetOrderToken comes back as
    //   {"IsSuccessful":false,"Code":-31,"Message":"Please specify your Tron Wallet Address.","Data":null}
    // Data is null there, so isset() skips the branch above and the reason was dropped: the
    // seller only ever saw the generic "پاسخ نامعتبر از سرویس ترونادو" with nothing to act on.
    if (!array_key_exists('ErrorMessage', $decoded)
        && isset($decoded['Message'])
        && trim((string) $decoded['Message']) !== '') {
        $decoded['ErrorMessage'] = (string) $decoded['Message'];
    }

    return $decoded;
}

// Base58Check TRON address: 25 bytes, version byte 0x41, double-SHA-256 checksum - the rule
// Tronado's GetOrderToken enforces. A shape regex cannot catch a typo or a re-cased address.
// Pure PHP (no gmp/bcmath).
function tronadoIsValidTronAddress($address)
{
    if (!is_string($address)) {
        return false;
    }
    $address = trim($address);
    if (strlen($address) !== 34 || $address[0] !== 'T') {
        return false;
    }

    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $bytes = [];
    for ($i = 0; $i < 34; $i++) {
        $carry = strpos($alphabet, $address[$i]);
        if ($carry === false) {
            return false;
        }
        for ($j = 0, $n = count($bytes); $j < $n; $j++) {
            $carry += $bytes[$j] * 58;
            $bytes[$j] = $carry & 0xFF;
            $carry >>= 8;
        }
        while ($carry > 0) {
            $bytes[] = $carry & 0xFF;
            $carry >>= 8;
        }
    }
    $bytes = array_reverse($bytes);
    if (count($bytes) !== 25 || $bytes[0] !== 0x41) {
        return false;
    }

    $raw = '';
    foreach ($bytes as $byte) {
        $raw .= chr($byte);
    }

    return hash_equals(substr(hash('sha256', hash('sha256', substr($raw, 0, 21), true), true), 0, 4), substr($raw, 21, 4));
}

// The offline hash-checker matches a payment's recipient against its TRX wallet case-insensitively
// and does not know Tronado's payout transactions. If Tronado paid into that same wallet, one Tronado
// payout could be submitted again as proof for an offline TRX invoice and credited twice - so the two
// wallets must never be the same address.
function tronadoWalletsCollide($tronadoWallet, $offlineTrxWallet)
{
    $a = trim((string) $tronadoWallet);
    $b = trim((string) $offlineTrxWallet);
    return $a !== '' && $b !== '' && strcasecmp($a, $b) === 0;
}

function tronadoResolveWalletAddress()
{
    // Tronado pays into its own wallet only. There is deliberately no fallback to the crypto
    // wallets section: that TRX wallet is also watched by the offline hash-checker, which does not
    // know Tronado's payout transactions, so a buyer could submit a Tronado payout hash as proof
    // for an offline invoice and be credited twice for one payment.
    $walletSetting = select("PaySetting", "*", "NamePay", "walletaddress", "select");
    return trim((string) ($walletSetting['ValuePay'] ?? ''));
}

function tronadoCurlJson($endpoint, array $payload, $apiKey = null)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'x-api-key: ' . $apiKey;
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('Tronado request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('Tronado invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    return $decoded;
}

function tronadoGetTronPriceToman()
{
    $config = defined('TRONADO_API_CONFIGURATION') ? TRONADO_API_CONFIGURATION : [];
    $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://bot.tronado.cloud'), '/');
    $path = '/' . ltrim((string) ($config['price_toman_path'] ?? '/Tron/GetPriceToToman'), '/');

    $decoded = tronadoCurlJson($baseUrl . $path, []);
    if (!is_array($decoded) || !isset($decoded['TronPriceToman']) || !is_numeric($decoded['TronPriceToman'])) {
        return null;
    }

    return (float) $decoded['TronPriceToman'];
}

function trnado($order_id, $price)
{
    global $domainhosts;

    $apitronseller = trim((string) select("PaySetting", "*", "NamePay", "apiternado", "select")['ValuePay']);
    $walletaddress = tronadoResolveWalletAddress();
    $wageFromBusinessPercentage = (int) select("PaySetting", "*", "NamePay", "wageFromBusinessPercentageTronado", "select")['ValuePay'];

    if ($apitronseller === '') {
        return [
            'success' => false,
            'error' => 'کلید API ترونادو تنظیم نشده است',
        ];
    }

    if ($walletaddress === '') {
        return [
            'success' => false,
            'error' => 'آدرس کیف پول ترونادو تنظیم نشده است. از منوی ترونادو «💼 آدرس کیف پول ترونادو» آن را ثبت کنید.',
        ];
    }

    $tronPriceToman = tronadoGetTronPriceToman();
    if ($tronPriceToman === null || $tronPriceToman <= 0) {
        return [
            'success' => false,
            'error' => 'دریافت قیمت ترون از ترونادو با خطا مواجه شد',
        ];
    }

    $tronAmount = round(((float) $price) / $tronPriceToman, 6);

    $endpoints = defined('TRONADO_ORDER_TOKEN_ENDPOINTS') ? TRONADO_ORDER_TOKEN_ENDPOINTS : [];
    if (empty($endpoints)) {
        $endpoints = ['https://bot.tronado.cloud/api/v5/GetOrderToken'];
    }

    $callbackUrl = 'https://' . $domainhosts . '/payment/tronado.php';
    $requestPayload = [
        'PaymentID' => (string) $order_id,
        'WalletAddress' => $walletaddress,
        'TronAmount' => $tronAmount,
        'CallbackUrl' => $callbackUrl,
    ];

    $endpoint = $endpoints[0] . '?wageFromBusinessPercentage=' . $wageFromBusinessPercentage;

    $rawDecodedResponse = tronadoCurlJson($endpoint, $requestPayload, $apitronseller);
    $decodedResponse = tronadoNormalizeOrderTokenResponse($rawDecodedResponse);

    if (!is_array($decodedResponse) || !array_key_exists('Token', $decodedResponse) || trim((string) $decodedResponse['Token']) === '') {
        $errorText = is_array($decodedResponse) ? (string) ($decodedResponse['ErrorMessage'] ?? 'پاسخ نامعتبر از سرویس ترونادو') : 'پاسخ نامعتبر از سرویس ترونادو';
        if (!tronadoIsValidTronAddress($walletaddress)) {
            // Most often an address saved upper-cased by an older version; Base58 is case sensitive.
            $errorText .= ' | آدرس کیف پول ترونادو ذخیره‌شده معتبر نیست؛ از منوی ترونادو «💼 آدرس کیف پول ترونادو» آن را دوباره ثبت کنید.';
        }
        $errorPayload = [
            'success' => false,
            'error' => $errorText,
            'url' => $endpoint,
        ];

        error_log('Tronado GetOrderToken failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $errorPayload;
    }

    return $decodedResponse;
}

function tonpayCurlJson($method, $endpoint, array $payload, $apiKey)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('TonPay request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('TonPay invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded['status_code'] = $statusCode;
    return $decoded;
}

function tonpayApiKey()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apitonpay", "select")['ValuePay']);
}

function tonpayCreateInvoice($order_id, $amount)
{
    global $domainhosts;

    $apiKey = tonpayApiKey();
    if ($apiKey === '') {
        return [
            'success' => false,
            'error' => 'کلید API تون‌پی تنظیم نشده است',
        ];
    }

    $callbackUrl = 'https://' . $domainhosts . '/payment/tonpay.php';
    $requestPayload = [
        'amount' => (int) $amount,
        'order_id' => (string) $order_id,
        'callback_url' => $callbackUrl,
    ];

    $endpoint = 'https://tonpays.online/api/v1/invoices/create';
    $decoded = tonpayCurlJson('POST', $endpoint, $requestPayload, $apiKey);

    if (!is_array($decoded) || empty($decoded['invoice_id']) || empty($decoded['invoice_url'])) {
        $errorPayload = [
            'success' => false,
            'error' => is_array($decoded) ? ($decoded['detail'] ?? 'پاسخ نامعتبر از سرویس تون‌پی') : 'پاسخ نامعتبر از سرویس تون‌پی',
            'raw' => $decoded,
        ];
        error_log('TonPay create invoice failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $errorPayload;
    }

    return $decoded;
}

function tonpayCheckInvoice($invoice_id)
{
    $apiKey = tonpayApiKey();
    if ($apiKey === '') {
        return null;
    }

    $endpoint = 'https://tonpays.online/api/v1/invoices/check/' . rawurlencode((string) $invoice_id);
    return tonpayCurlJson('GET', $endpoint, [], $apiKey);
}

const ATLASPAY_BASE_URL = 'https://api.atlaspay.space/api/v1';

function atlaspayApiKey()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apiatlaspay", "select")['ValuePay']);
}

function atlaspayCurlJson($method, $endpoint, array $payload, $apiKey)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('AtlasPay request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('AtlasPay invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded['status_code'] = $statusCode;
    return $decoded;
}

function atlaspayCreateOrder($order_id, $amount, $telegramId = null)
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return [
            'success' => false,
            'error' => 'کلید API اطلس‌پی تنظیم نشده است',
        ];
    }

    $requestPayload = [
        'merchantOrderRef' => (string) $order_id,
        'baseAmountToman' => (int) $amount,
    ];
    if ($telegramId !== null) {
        $requestPayload['customerTelegramId'] = (int) $telegramId;
    }

    $endpoint = ATLASPAY_BASE_URL . '/orders';
    $decoded = atlaspayCurlJson('POST', $endpoint, $requestPayload, $apiKey);

    if (!is_array($decoded) || empty($decoded['orderId']) || empty($decoded['customerStartLink'])) {
        $errorPayload = [
            'success' => false,
            'error' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس اطلس‌پی') : 'پاسخ نامعتبر از سرویس اطلس‌پی',
            'raw' => $decoded,
        ];
        error_log('AtlasPay create order failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $errorPayload;
    }

    return $decoded;
}

function atlaspayCheckOrder($atlaspayOrderId)
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return null;
    }

    $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId);
    return atlaspayCurlJson('GET', $endpoint, [], $apiKey);
}

function atlaspayVerifyOrder($atlaspayOrderId)
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return null;
    }

    $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId) . '/verify';
    return atlaspayCurlJson('POST', $endpoint, [], $apiKey);
}

function atlaspayCancelOrder($atlaspayOrderId)
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return null;
    }

    $endpoint = ATLASPAY_BASE_URL . '/orders/' . rawurlencode((string) $atlaspayOrderId) . '/cancel';
    return atlaspayCurlJson('POST', $endpoint, [], $apiKey);
}

function atlaspayBalance()
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return null;
    }

    return atlaspayCurlJson('GET', ATLASPAY_BASE_URL . '/balance', [], $apiKey);
}

function atlaspayAccount()
{
    $apiKey = atlaspayApiKey();
    if ($apiKey === '') {
        return null;
    }

    return atlaspayCurlJson('GET', ATLASPAY_BASE_URL . '/account', [], $apiKey);
}

function tetrapayApiKey()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apitetrapay", "select")['ValuePay']);
}

function tetrapayBaseUrl()
{
    $url = trim((string) select("PaySetting", "*", "NamePay", "apiurltetrapay", "select")['ValuePay']);
    return $url !== '' ? rtrim($url, '/') . '/api/v1' : '';
}

function tetrapayCurlJson($method, $endpoint, array $payload, $apiKey)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('TetraPay request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('TetraPay invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded['status_code'] = $statusCode;
    return $decoded;
}

function tetrapayCreatePaymentLink($amount)
{
    $apiKey = tetrapayApiKey();
    if ($apiKey === '') {
        return [
            'ok' => false,
            'error' => 'کلید API تتراپی تنظیم نشده است',
        ];
    }
    $baseUrl = tetrapayBaseUrl();
    if ($baseUrl === '') {
        return [
            'ok' => false,
            'error' => 'آدرس سرور API تتراپی تنظیم نشده است',
        ];
    }

    $requestPayload = [
        'amount' => (int) $amount,
    ];

    $endpoint = $baseUrl . '/payment-link';
    $decoded = tetrapayCurlJson('POST', $endpoint, $requestPayload, $apiKey);

    if (!is_array($decoded) || empty($decoded['ok']) || empty($decoded['token']) || empty($decoded['link'])) {
        $errorPayload = [
            'ok' => false,
            'error' => is_array($decoded) ? ($decoded['error'] ?? 'پاسخ نامعتبر از سرویس تتراپی') : 'پاسخ نامعتبر از سرویس تتراپی',
            'raw' => $decoded,
        ];
        error_log('TetraPay create payment-link failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $errorPayload;
    }

    return $decoded;
}

function tetrapayCheckStatus($token)
{
    $apiKey = tetrapayApiKey();
    $baseUrl = tetrapayBaseUrl();
    if ($apiKey === '' || $baseUrl === '') {
        return null;
    }

    $endpoint = $baseUrl . '/payment-status/' . rawurlencode((string) $token);
    return tetrapayCurlJson('GET', $endpoint, [], $apiKey);
}

const BLUPAL_RIAL_PER_TOMAN = 10;

function blupalCurlJson($method, $endpoint, array $payload, $apiKey)
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== null) {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'Faoxima-BluPal-Client/1.0',
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('BluPal request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('BluPal invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded['status_code'] = $statusCode;
    return $decoded;
}

function blupalApiKey()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apiblupal", "select")['ValuePay']);
}

function blupalTomanToRial($tomanAmount)
{
    return (int) round(((float) $tomanAmount) * BLUPAL_RIAL_PER_TOMAN);
}

function blupalRialToToman($rialAmount)
{
    return (int) round(((float) $rialAmount) / BLUPAL_RIAL_PER_TOMAN);
}

function blupalCreateInvoice($order_id, $amountToman)
{
    global $domainhosts;

    $apiKey = blupalApiKey();
    if ($apiKey === '') {
        return [
            'success' => false,
            'error' => 'کلید API بلوپال تنظیم نشده است',
        ];
    }

    $requestPayload = [
        'amount' => blupalTomanToRial($amountToman),
    ];

    $endpoint = 'https://blupal.net/api/v1/invoices/create';
    $decoded = blupalCurlJson('POST', $endpoint, $requestPayload, $apiKey);

    if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['invoice_id']) || empty($decoded['payment_link'])) {
        $errorPayload = [
            'success' => false,
            'error' => is_array($decoded) ? ($decoded['message'] ?? ($decoded['error'] ?? 'پاسخ نامعتبر از سرویس بلوپال')) : 'پاسخ نامعتبر از سرویس بلوپال',
            'raw' => $decoded,
        ];
        error_log('BluPal create invoice failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $errorPayload;
    }

    return $decoded;
}

function blupalCheckInvoice($invoice_id)
{
    $apiKey = blupalApiKey();
    if ($apiKey === '') {
        return null;
    }

    $endpoint = 'https://blupal.net/api/v1/invoices/' . rawurlencode((string) $invoice_id);
    return blupalCurlJson('GET', $endpoint, [], $apiKey);
}

function cubepayCurlJson($method, $endpoint, array $payload, $apiToken)
{
    $headers = ['Content-Type: application/json'];
    if ($apiToken !== null && $apiToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiToken;
    }

    $curl = curl_init();
    $opts = array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($curl, $opts);

    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    $statusCode = $curlInfo['http_code'] ?? null;
    curl_close($curl);

    if ($response === false) {
        error_log('CubePay request failed: ' . json_encode([
            'url' => $endpoint,
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('CubePay invalid response: ' . json_encode([
            'url' => $endpoint,
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return null;
    }

    $decoded['status_code'] = $statusCode;
    return $decoded;
}

function cubepayApiToken()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apicubepay", "select")['ValuePay']);
}

/**
 * کارمزد کیوب‌پی از کیف پول فروشنده کم می‌شود. این تابع اجازه می‌دهد آن هزینه
 * روی فاکتور گذاشته شود تا کاربر پرداختش کند:
 *
 *   عدد 0 تا 100  → درصدی که به مبلغ فاکتور اضافه می‌شود (اعشار مجاز، مثلاً 9.9)
 *   عدد بالای 100 → مبلغ ثابت به تومان
 *   صفر (پیش‌فرض) → غیرفعال؛ کارمزد را خود فروشنده می‌پردازد
 *
 * فقط مبلغ فاکتور درگاه بزرگ‌تر می‌شود. اعتباری که به کاربر داده می‌شود عوض
 * نمی‌شود، چون payment/cubepay.php با Payment_report.price کار می‌کند که همان
 * مبلغ درخواستی کاربر است.
 */
function cubepayPayableAmount($amount_toman)
{
    $amount = (int) $amount_toman;
    $raw = str_replace([',', '،'], '', (string) getPaySettingValue('feecubepay', '0'));
    $fee = is_numeric($raw) ? (float) $raw : 0.0;

    if ($fee <= 0 || $amount <= 0) {
        return $amount;
    }

    return $fee <= 100
        ? (int) ceil($amount * (1 + $fee / 100))
        : $amount + (int) round($fee);
}

function cubepayCreatePayment($order_id, $amount_toman, $customer_user_id = null, $description = '')
{
    global $domainhosts;

    $apiToken = cubepayApiToken();
    if ($apiToken === '') {
        return [
            'success' => false,
            'message' => 'توکن API کیوب‌پی تنظیم نشده است',
        ];
    }

    $callbackUrl = 'https://' . $domainhosts . '/payment/cubepay.php';
    $requestPayload = [
        'order_id' => (string) $order_id,
        'price_amount' => cubepayPayableAmount($amount_toman),
        'callback_url' => $callbackUrl,
        'redirect_after_payment' => false,
    ];

    $endpoint = 'https://cubevps.ir/pay/create-order.php';
    $decoded = cubepayCurlJson('POST', $endpoint, $requestPayload, $apiToken);

    if (!is_array($decoded) || empty($decoded['success']) || empty($decoded['pay_page_url'])) {
        $errorPayload = [
            'success' => false,
            'message' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس کیوب‌پی') : 'پاسخ نامعتبر از سرویس کیوب‌پی',
            'raw' => $decoded,
        ];
        error_log('CubePay create payment failed: ' . json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $errorPayload;
    }

    $method = (string) ($decoded['method'] ?? 'choice');
    $normalized = [
        'success' => true,
        'method' => $method,
        'authority' => (string) ($decoded['authority'] ?? ''),
        'payment_link' => (string) $decoded['pay_page_url'],
    ];

    // مسیرِ کارتی: کیوب‌پی اطلاعات کارتِ همین فاکتور و تنظیمِ show_card_in_bot
    // فروشنده را هم برمی‌گرداند. اگر هم کارت و هم ارز دیجیتال فعال باشد، در این
    // لحظه هنوز کارتی اختصاص نیافته (مشتری انتخاب نکرده) و این بلوک رد می‌شود.
    if (!empty($decoded['card']['number'])) {
        $normalized['card'] = $decoded['card'];
        $normalized['pay_amount_toman'] = (int) ($decoded['pay_amount_toman'] ?? 0);
        $normalized['expires_in_minutes'] = (int) ($decoded['expires_in_minutes'] ?? 30);
        $normalized['show_card_in_bot'] = !empty($decoded['show_card_in_bot']);
    }

    return $normalized;
}

function cubepayCheckOrderStatus($order_id)
{
    $apiToken = cubepayApiToken();
    if ($apiToken === '') {
        return null;
    }

    $endpoint = 'https://cubevps.ir/pay/check-order-status.php?order_id=' . rawurlencode((string) $order_id);
    return cubepayCurlJson('GET', $endpoint, [], $apiToken);
}

function cubepayVerifyPayment($authority)
{
    $apiToken = cubepayApiToken();
    if ($apiToken === '') {
        return null;
    }

    $endpoint = 'https://cubevps.ir/smspay/api/verify-payment.php';
    return cubepayCurlJson('POST', $endpoint, ['authority' => (string) $authority], $apiToken);
}

function cubepayResolveOrderStatus($order_id, $authority = '')
{
    $orderId = (string) $order_id;
    $authority = trim((string) $authority);

    $check = cubepayCheckOrderStatus($orderId);
    if (is_array($check) && !empty($check['success'])) {
        return $check;
    }

    if ($authority === '') {
        return $check;
    }

    $verify = cubepayVerifyPayment($authority);
    if (!is_array($verify)) {
        return $check;
    }

    return [
        'success' => !empty($verify['success']),
        'method' => 'card',
        'status' => !empty($verify['success']) ? 'verified' : (string) ($verify['message'] ?? 'pending'),
        'order_id' => (string) ($verify['order_id'] ?? $orderId),
        'amount' => $verify['amount'] ?? null,
    ];
}

function cubepayVerifyCryptoCallbackSignature($orderId, $status, $amount, $sig)
{
    $apiToken = cubepayApiToken();
    if ($apiToken === '') {
        return false;
    }
    $expectedSig = hash_hmac('sha256', $orderId . '|' . $status . '|' . $amount, $apiToken);
    return hash_equals($expectedSig, (string) $sig);
}

/**
 * Variza — automated card-to-card gateway (https://variza.ir).
 *
 * Same role as cubepayCreatePayment(): create a payment link for an order and
 * return a normalized array. Verification is push-only (payment/variza_webhook.php),
 * so there is no verify/status counterpart here.
 */
function varizaApiToken()
{
    return trim((string) select("PaySetting", "*", "NamePay", "apivariza", "select")['ValuePay']);
}

function varizaCreatePayment($order_id, $amount_toman)
{
    global $domainhosts;

    $apiToken = varizaApiToken();
    if ($apiToken === '' || $apiToken === '0') {
        return [
            'success' => false,
            'message' => 'توکن API واریزا تنظیم نشده است',
        ];
    }

    $payload = [
        'amount' => (int) $amount_toman,
        'return_url' => 'https://' . $domainhosts . '/payment/variza_return.php?order=' . $order_id,
        'title' => 'Faoxima order ' . $order_id,
        'expires_in' => '1h',
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://variza.ir/api/v1/pay',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiToken,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $response = curl_exec($curl);
    $curlErrno = curl_errno($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE) ?? 0);
    curl_close($curl);

    if ($response === false) {
        error_log('Variza create payment failed: ' . json_encode([
            'error' => $curlError,
            'errno' => $curlErrno,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'success' => false,
            'message' => 'خطا در ارتباط با سرویس واریزا',
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $statusCode < 200 || $statusCode >= 300) {
        error_log('Variza invalid response: ' . json_encode([
            'status_code' => $statusCode,
            'raw_response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'success' => false,
            'message' => is_array($decoded) ? ($decoded['message'] ?? 'پاسخ نامعتبر از سرویس واریزا') : 'پاسخ نامعتبر از سرویس واریزا',
            'raw' => $decoded,
        ];
    }

    $slug = trim((string) ($decoded['slug'] ?? ''));
    $payUrl = trim((string) ($decoded['pay_url'] ?? ''));
    if ($slug === '' || $payUrl === '') {
        error_log('Variza missing slug/pay_url: ' . json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از سرویس واریزا',
            'raw' => $decoded,
        ];
    }

    return [
        'success' => true,
        'slug' => $slug,
        'pay_url' => $payUrl,
    ];
}
function formatBytes($bytes, $precision = 2): string
{
    $base = log($bytes, 1024);
    $power = $bytes > 0 ? floor($base) : 0;
    $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
function rxInvoiceVolumeUnit(array $invoice): string
{
    $unit = strtoupper(trim((string) ($invoice['Volume_unit'] ?? '')));
    if ($unit === 'MB' || $unit === 'GB') {
        return $unit;
    }
    return (($invoice['name_product'] ?? '') === 'سرویس تست') ? 'MB' : 'GB';
}
function rxVolumeToBytes($value, string $unit): float
{
    $amount = is_numeric($value) ? (float) $value : 0.0;
    return $amount * ($unit === 'MB' ? pow(1024, 2) : pow(1024, 3));
}
function formatInvoiceVolume(array $invoice, $precision = 2): string
{
    $value = is_numeric($invoice['Volume'] ?? null) ? (float) $invoice['Volume'] : 0.0;
    if ($value == 0.0) {
        return 'نامحدود';
    }
    return formatBytes(rxVolumeToBytes($value, rxInvoiceVolumeUnit($invoice)), $precision);
}
function rxServiceListStatusSuffix(array $invoice): string
{
    return in_array(strtolower((string) ($invoice['Status'] ?? '')), ['disabled', 'disablebyadmin'], true) ? ' | غیرفعال' : '';
}
function formatOnlineAtLabel($onlineAt, $isOnline = null)
{
    if ($isOnline === true && (empty($onlineAt) || $onlineAt === null)) {
        return "Online (بدون زمان)";
    }

    if ($onlineAt === null || $onlineAt === '') {
        return "—";
    }

    if (is_string($onlineAt)) {
        $onlineAt = trim($onlineAt);
        if ($onlineAt === '') {
            return "—";
        }
        $lowered = strtolower($onlineAt);
        if ($lowered === 'online') {
            return 'آنلاین';
        }
        if ($lowered === 'offline') {
            return 'آفلاین';
        }
    }

    try {
        if (is_numeric($onlineAt)) {
            $dateTime = new DateTime('@' . intval($onlineAt));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        } else {
            $dateTime = new DateTime((string) $onlineAt, new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        }
        return jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    } catch (Exception $e) {
        return (string) $onlineAt;
    }
}
function rx_build_smart_random_username($telegramUsername, $telegramId)
{
    $hasUsername = is_string($telegramUsername) && $telegramUsername !== '' && !in_array(strtolower($telegramUsername), ['not_username', 'none'], true);
    if ($hasUsername) {
        $suffix = str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);
        $base = substr(str_replace('_', '-', strtolower($telegramUsername)), 0, 28);
        return $base . '-' . $suffix;
    }
    $letters = '';
    for ($i = 0; $i < 3; $i++) {
        $letters .= chr(rand(97, 122));
    }
    return 'u' . $telegramId . '-' . $letters;
}

function generateUsername($from_id, $Metode, $username, $randomString, $text, $namecustome, $usernamecustom)
{
    $username = str_replace('_', '-', (string) $username);
    $text = str_replace('_', '-', (string) $text);
    $namecustome = str_replace('_', '-', (string) $namecustome);
    $usernamecustom = str_replace('_', '-', (string) $usernamecustom);
    $setting = select("setting", "*", null, null, "select");
    $user = select("user", "*", "id", $from_id, "select");
    if ($user == false) {
        $user = array();
        $user = array(
            'number_username' => '',
        );
    }
    $telegramUsernameMissing = trim($username) === '' || in_array(strtolower($username), ['not_username', 'not-username', 'none'], true);
    $telegramUsernameBase = $telegramUsernameMissing ? $namecustome : $username;
    if ($telegramUsernameBase === '' || strtolower($telegramUsernameBase) === 'none') {
        $telegramUsernameBase = 'u' . $from_id;
    }
    $panelCustomBase = trim($namecustome) !== '' && strtolower($namecustome) !== 'none' ? $namecustome : 'u' . $from_id;
    $requestedBase = trim($text) !== '' ? $text : $telegramUsernameBase;
    if ($Metode == "آیدی عددی + حروف و عدد رندوم") {
        return $from_id . "-" . $randomString;
    } elseif ($Metode == "نام کاربری + حروف و عدد رندوم") {
        return $telegramUsernameBase . "-" . $randomString;
    } elseif ($Metode == "نام کاربری + عدد به ترتیب") {
        return $telegramUsernameBase . "-" . $user['number_username'];
    } elseif ($Metode == "نام کاربری دلخواه")
        return $requestedBase;
    elseif ($Metode == "نام کاربری دلخواه + عدد رندوم") {
        $random_number = rand(1000000, 9999999);
        return $requestedBase . "-" . $random_number;
    } elseif ($Metode == "متن دلخواه کاربر + رندوم") {
        return $requestedBase;
    } elseif ($Metode == "متن دلخواه + عدد رندوم") {
        return $panelCustomBase . "-" . $randomString;
    } elseif ($Metode == "متن دلخواه + عدد ترتیبی") {
        return $panelCustomBase . "-" . $setting['numbercount'];
    } elseif ($Metode == "آیدی عددی+عدد ترتیبی") {
        return $from_id . "-" . $user['number_username'];
    } elseif ($Metode == "آیدی عددی") {
        return (string) $from_id;
    } elseif ($Metode == "متن دلخواه نماینده + عدد ترتیبی") {
        if (trim($usernamecustom) === '' || strtolower($usernamecustom) === "none") {
            return $panelCustomBase . "-" . $setting['numbercount'];
        }
        return $usernamecustom . "-" . $user['number_username'];
    }
    return $from_id . "-" . $randomString;
}
function outputlunk($text)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $text);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 6000);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        return null;
    } else {
        return $response;
    }

    curl_close($ch);
}
function outputlunksub($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/info");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    $headers = array();
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    return $result;
    curl_close($ch);
}
function normalizeServiceConfigs($configs, $subscriptionUrl = null)
{
    $normalized = [];

    if (is_array($configs)) {
        foreach ($configs as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $normalized[] = $item;
        }
    } elseif (is_string($configs)) {
        $parts = preg_split("/\r\n|\n|\r/", $configs);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $normalized[] = $part;
            }
        }
    }

    $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
    if (empty($normalized) && $subscriptionUrl !== '') {
        if (preg_match('/^https?:/i', $subscriptionUrl)) {
            $fetched = outputlunk($subscriptionUrl);
            if (is_string($fetched) && $fetched !== '') {
                if (isBase64($fetched)) {
                    $fetched = base64_decode($fetched);
                }
                $parts = preg_split("/\r\n|\n|\r/", $fetched);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part === '') {
                            continue;
                        }
                        $normalized[] = $part;
                    }
                }
            }
        } else {
            $normalized[] = $subscriptionUrl;
        }
    }

    return array_values($normalized);
}
function DirectPayment($order_id, $image = 'images.jpg')
{
    global $pdo, $ManagePanel, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot, $update;
    $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
    $admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
    $otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
    $errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
    $porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $_receipt_report_chat = trim((string)($Payment_report['report_chat_id'] ?? ''));
    $_receipt_report_msg = (int)($Payment_report['report_message_id'] ?? 0);
    $_receipt_report_thread = (int)($Payment_report['report_thread_id'] ?? 0);
    if ($_receipt_report_chat !== '' && $_receipt_report_msg > 0) {
        $_receipt_chat_id = $_receipt_report_chat;
        $message_id = $_receipt_report_msg;
    } else {
        $_receipt_chat_id = $update['callback_query']['message']['chat']['id'] ?? $from_id;
        $_receipt_report_thread = (int)($update['callback_query']['message']['message_thread_id'] ?? 0);
    }
    $paymentNote = formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null);
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $steppay = explode("|", $Payment_report['id_invoice']);
    update("user", "Processing_value", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
    if ($steppay[0] == "getconfigafterpay") {
        // [invoice lookup with fallbacks] گاهی به‌خاطر race/cleanup/timing بین crypto-pay و DirectPayment،
        // فاکتور با username + Status='unpaid' پیدا نمیشه. چندتا fallback می‌گذاریم تا قبل از refund همه گزینه‌ها تست بشن.
        $__invUsername = isset($steppay[1]) ? trim((string)$steppay[1]) : '';
        $get_invoice = false;
        if ($__invUsername !== '') {
            try {
                // 1) دقیقا مثل قبل: username + Status='unpaid'
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :u AND Status = 'unpaid' ORDER BY id_invoice DESC LIMIT 1");
                $stmt->execute([':u' => $__invUsername]);
                $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $__e) { $get_invoice = false; }
            if (!$get_invoice) {
                try {
                    // 2) بدون فیلتر Status (در صورت تفاوت case یا تغییر status توسط cron دیگه)
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :u ORDER BY id_invoice DESC LIMIT 1");
                    $stmt->execute([':u' => $__invUsername]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $__e) { $get_invoice = false; }
            }
            if (!$get_invoice) {
                try {
                    // 3) case-insensitive روی username — اگه collation داره فرق می‌کنه
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE LOWER(username) = LOWER(:u) ORDER BY id_invoice DESC LIMIT 1");
                    $stmt->execute([':u' => $__invUsername]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $__e) { $get_invoice = false; }
            }
        }
        if (!$get_invoice) {
            try {
                // 4) آخرین چاره: آخرین فاکتور unpaid این کاربر که usernameش با user_id شروع میشه (پترن مرسوم)
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND (Status = 'unpaid' OR Status = 'Unpaid') AND username LIKE :prefix ORDER BY time_sell DESC LIMIT 1");
                $stmt->execute([':uid' => (string)$Balance_id['id'], ':prefix' => $Balance_id['id'] . '_%']);
                $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$get_invoice) {
                    // اگه پیدا نشد، بدون prefix filter
                    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :uid AND (Status = 'unpaid' OR Status = 'Unpaid') ORDER BY time_sell DESC LIMIT 1");
                    $stmt->execute([':uid' => (string)$Balance_id['id']]);
                    $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                // [NOTE] قبلاً اینجا username رو به مقدار اصلی (`$__invUsername`) برمی‌گردوندیم،
                // ولی این باعث می‌شد cycle شکست-retry بی‌نهایت بشه: هر retry با username اصلی duplicate می‌خورد،
                // یوزرنیم تازه می‌ساخت تو پنل (zombie)، بعد restore دوباره به اصلی، دوباره duplicate، الی آخر.
                // الان username رو همون که DB داره نگه می‌داریم. اگه retry قبلی یوزر تو پنل ساخته، zombie-rescue
                // اون رو پیدا می‌کنه و استفاده می‌کنه؛ اگه نساخته، duplicate-retry با random جدید موفق میشه.
            } catch (Throwable $__e) { $get_invoice = false; }
        }
        // اگه با هیچ روشی پیدا نشد، قبل از اینکه به refund برسیم به ادمین گزارش بدیم و مستقیم برگردیم
        if (!$get_invoice) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] invoice NOT FOUND for order={$order_id} user={$Balance_id['id']} steppay[1]={$__invUsername} — aborting WITHOUT refund (so cryptocheck stuck-refund can handle it cleanly)");
            }
            $__setting = function_exists('select') ? select('setting', '*', null, null, 'select') : [];
            $__errReport = function_exists('select') ? (select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt = "⚠️ <b>فاکتور پیدا نشد برای ساخت سرویس</b>\n"
                   . "<blockquote>🛒 کد سفارش: <code>{$order_id}</code></blockquote>\n"
                   . "<blockquote>👤 کاربر: <code>{$Balance_id['id']}</code></blockquote>\n"
                   . "<blockquote>🔎 username موردنظر: <code>" . htmlspecialchars($__invUsername) . "</code></blockquote>\n"
                   . "ℹ️ DirectPayment بدون refund برگشت — لطفاً دستی بررسی کنید.";
            if (!empty($__setting['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting['Channel_Report'],
                    'message_thread_id' => $__errReport,
                    'text' => $__txt,
                    'parse_mode' => 'HTML',
                ]);
            }
            return;
        }
        $userAgent = $Balance_id['agent'] ?? 'f';
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($get_invoice['name_product'] == "🛍 حجم دلخواه" || $get_invoice['name_product'] == "⚙️ سرویس دلخواه") {
            $info_product['data_limit_reset'] = "no_reset";
            $info_product['Volume_constraint'] = $get_invoice['Volume'];
            $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
            $info_product['code_product'] = "customvolume";
            $info_product['Service_time'] = $get_invoice['Service_time'];
            $info_product['price_product'] = $get_invoice['price_product'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $stmt->execute([':name' => $get_invoice['name_product'], ':loc' => $get_invoice['Service_location'], ':agent' => $userAgent]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $username_ac = $get_invoice['username'];
        $randomString = bin2hex(random_bytes(2));
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");

        // [panel-missing guard] اگه پنل پیدا نشد، اصلاً نباید بریم سراغ createUser چون قطعاً "Panel Not Found" برمی‌گردونه.
        // قبل از refund، به ادمین گزارش بدیم تا دلیل واقعی (مثلاً پنل حذف شده، نام عوض شده، Service_location خراب) مشخص بشه.
        if (!is_array($marzban_list_get) || empty($marzban_list_get['name_panel'])) {
            if (function_exists('error_log')) {
                @error_log("[DirectPayment] panel missing for order={$order_id} user={$Balance_id['id']} location='" . (string)($get_invoice['Service_location'] ?? '') . "' invoice={$get_invoice['id_invoice']} — aborting WITHOUT refund");
            }
            $__setting2 = function_exists('select') ? select('setting', '*', null, null, 'select') : [];
            $__errReport2 = function_exists('select') ? (select('topicid', 'idreport', 'report', 'errorreport', 'select')['idreport'] ?? null) : null;
            $__txt2 = "⚠️ <b>پنل برای ساخت سرویس پیدا نشد</b>\n"
                    . "<blockquote>🛒 کد سفارش: <code>{$order_id}</code></blockquote>\n"
                    . "<blockquote>👤 کاربر: <code>{$Balance_id['id']}</code></blockquote>\n"
                    . "<blockquote>📍 لوکیشن ذخیره‌شده در فاکتور: <code>" . htmlspecialchars((string)($get_invoice['Service_location'] ?? '')) . "</code></blockquote>\n"
                    . "<blockquote>🧾 شناسه فاکتور: <code>{$get_invoice['id_invoice']}</code></blockquote>\n"
                    . "ℹ️ DirectPayment بدون refund برگشت — لطفاً پنل را در دیتابیس بررسی کنید.";
            if (!empty($__setting2['Channel_Report']) && function_exists('telegram')) {
                @telegram('sendmessage', [
                    'chat_id' => $__setting2['Channel_Report'],
                    'message_thread_id' => $__errReport2,
                    'text' => $__txt2,
                    'parse_mode' => 'HTML',
                ]);
            }
            return;
        }

        // [idempotent-refund guard] اگر این فاکتور قبلاً refund خورده (نشانه auto-refund تو dec_not_confirmed)،
        // دیگه نباید دوباره کیف پول رو شارژ کنیم — همینجا برمی‌گردیم و فقط لاگ می‌زنیم.
        // این جلوی double/triple-credit موقع retry گیر کردن سرویس رو می‌گیره.
        $__alreadyRefunded = false;
        try {
            $__pr = select("Payment_report", "dec_not_confirmed", "id_order", $order_id, "select");
            $__decNote = is_array($__pr) ? (string)($__pr['dec_not_confirmed'] ?? '') : '';
            if ($__decNote !== '' && stripos($__decNote, 'auto-refund') !== false) {
                $__alreadyRefunded = true;
            }
        } catch (Throwable $__e) { $__alreadyRefunded = false; }
        if ($__alreadyRefunded) {
            return;
        }

        // [username normalize] اگر یوزرنیم فاکتور خالی/کوتاه‌تر از 3 کاراکتر بود، یکی معتبر بساز.
        // این از خطای پنل "Username must be at least 3 characters long" جلوگیری می‌کنه.
        if (!is_string($username_ac) || trim($username_ac) === '' || strlen(trim($username_ac)) < 3) {
            $username_ac = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$Balance_id['id'])) . '-' . bin2hex(random_bytes(4));
            if (strlen($username_ac) < 3) $username_ac = 'u' . bin2hex(random_bytes(4));
            // فقط وقتی id_invoice معتبره update کن (جلوی خطای "Column id_invoice cannot be null" گرفته میشه)
            if (!empty($get_invoice['id_invoice'])) {
                try { update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']); } catch (Throwable $__e) { /* fail-open */ }
            }
        }

        // [duplicate-username guard - REMOVED]
        // پچ قبلی یک حلقه pre-check با DataUser داشت که باعث می‌شد:
        //   - چندین API call به panel قبل از createUser → کند و گاهی timeout
        //   - اگر createUser قبلی نیمه‌کاره موفق بوده (yوزر ساخته شده ولی bot جواب نگرفته)، DataUser می‌گفت "exists" و یوزرنیم عوض می‌شد
        //   - هر retry یوزرنیم جدید → چندین یوزر زامبی توی پنل + سرویس نهایی هیچ‌وقت تحویل نشد
        // الان فقط روی duplicate-error واقعی از createUser (در پایین) retry می‌کنیم، که هم سریع‌تر و هم دقیق‌تره.
        // این رفتار همون چیزیه که در wallet-payment موفق عمل می‌کنه.
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
            'from_id' => $Balance_id['id'],
            'username' => $Balance_id['username'],
            'type' => 'buy'
        );
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            if (is_array($info_product) && nmStockCompleteBuyFromInventory($Balance_id['id'], $Balance_id, $marzban_list_get, $info_product, $get_invoice['id_invoice'], $username_ac, false, 'paid_national_buy')) {
                sendmessage($Balance_id['id'], $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                if (function_exists('update')) {
                    update("Payment_report", "direct_payment_done", 1, "id_order", $order_id);
                }
                return;
            }
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            $__refundOkNs = balance_atomic_credit($Balance_id['id'], $Payment_report['price']);
            if ($__refundOkNs && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($Balance_id['id'], 'credit', $Payment_report['price'], 'refund', 'بازگشت وجه - موجودی انبار ملی تمام شده', (string)$order_id, 'invoice', (string)$get_invoice['id_invoice']);
            }
            try {
                $__nationalNote = '[auto-refund: national stock empty at ' . date('Y-m-d H:i:s') . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__nationalNote, ':n2' => $__nationalNote, ':o' => $order_id]);
            } catch (Throwable $__e) {}
            sendmessage($Balance_id['id'], "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. مبلغ پرداختی به کیف پول برگشت خورد.", $keyboard, 'HTML');
            return;
        }
        // [zombie-rescue] قبل از تلاش جدید، اگه قبلاً تو panel یوزری برای این کاربر ساخته شده (zombie)،
        // اول بررسی کن: شاید createUser تو call قبلی موفق بوده فقط response نرسیده. اگه پیدا کردیم،
        // از همون استفاده کن (بدون ساختن یوزر جدید) — این جلوی تولید بیشتر zombie رو می‌گیره.
        $__isRetryCall = false;
        try {
            $__pr2 = select("Payment_report", "crypto_check_count", "id_order", $order_id, "select");
            $__cnt = is_array($__pr2) ? (int)($__pr2['crypto_check_count'] ?? 0) : 0;
            if ($__cnt >= 1) $__isRetryCall = true;
        } catch (Throwable $__e) { /* ignore */ }

        $dataoutput = null;
        if ($__isRetryCall) {
            // در حالت retry، اول چک کن یوزر در پنل وجود داره یا نه (با همون username فاکتور)
            try {
                $__zombieCheck = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
                if (is_array($__zombieCheck) && !empty($__zombieCheck['username']) && (string)$__zombieCheck['username'] === (string)$username_ac) {
                    // یوزر تو پنل هست! یعنی createUser قبلی واقعاً موفق بوده، فقط bot جواب نگرفته.
                    // از همون استفاده کن.
                    $dataoutput = $__zombieCheck;
                    $dataoutput['status'] = 'successful';
                    if (empty($dataoutput['configs']) && !empty($dataoutput['links'])) {
                        $dataoutput['configs'] = is_array($dataoutput['links']) ? $dataoutput['links'] : explode("\n", (string)$dataoutput['links']);
                    }
                }
            } catch (Throwable $__e) { /* fail-open */ }
        }

        if (empty($dataoutput) || empty($dataoutput['username'])) {
            $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
        }

        // [duplicate retry — حداکثر 1 بار] اگه createUser duplicate برگردوند، فقط یک بار با random جدید retry می‌کنیم.
        try {
            if (empty($dataoutput['username'])) {
                $__msgRaw = is_array($dataoutput) ? ($dataoutput['msg'] ?? '') : '';
                $__msgStr = is_string($__msgRaw) ? $__msgRaw : json_encode($__msgRaw);
                if (stripos($__msgStr, 'duplicate') !== false || stripos($__msgStr, 'already exist') !== false || stripos($__msgStr, 'exists') !== false) {
                    $username_ac = preg_replace('/[^A-Za-z0-9-]/', '', str_replace('_', '-', (string)$Balance_id['id'])) . '-' . bin2hex(random_bytes(4));
                    if (strlen($username_ac) < 3) $username_ac = 'u' . bin2hex(random_bytes(4));
                    if (!empty($get_invoice['id_invoice'])) {
                        try { update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']); } catch (Throwable $__e2) { /* fail-open */ }
                    }
                    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
                }
            }
        } catch (Throwable $__e) { /* fail-open */ }

        // [CRITICAL: mark invoice active EARLY]
        // اگه createUser موفق بوده، همین الان قبل از هر sendmessage/QR code generation/الخ که ممکنه hang کنه،
        // invoice رو active علامت بزن. این جلوی retry بعدی توسط cron رو می‌گیره حتی اگه پیام تلگرام hang کنه.
        if (!empty($dataoutput['username']) && !empty($get_invoice['id_invoice'])) {
            try {
                $__early = $pdo->prepare("UPDATE invoice SET Status = 'active' WHERE id_invoice = :i");
                $__early->execute([':i' => $get_invoice['id_invoice']]);
            } catch (Throwable $__e) { /* fail-open */ }
            // و dec_not_confirmed را با علامت موفقیت بگذار تا retry cron این رو پیدا نکنه
            try {
                $__doneNote = '[service-created at ' . date('Y-m-d H:i:s') . ' username=' . $dataoutput['username'] . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__doneNote, ':n2' => $__doneNote, ':o' => $order_id]);
            } catch (Throwable $__e) { /* fail-open */ }
        }
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            $__refundOkCu = balance_atomic_credit($Balance_id['id'], $Payment_report['price']);
            if ($__refundOkCu && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($Balance_id['id'], 'credit', $Payment_report['price'], 'refund', 'بازگشت وجه - خطا در ساخت سرویس', (string)$order_id, 'invoice', (string)$get_invoice['id_invoice']);
            }
            // [refund-marker] برای جلوگیری از double-refund توسط retry — حتماً قبل از sendmessageها مارک کن
            try {
                $__failNote = '[auto-refund: service creation failed at ' . date('Y-m-d H:i:s') . ']';
                $__mk = $pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = CASE WHEN dec_not_confirmed IS NULL OR dec_not_confirmed = '' THEN :n1 ELSE CONCAT(dec_not_confirmed, ' | ', :n2) END WHERE id_order = :o");
                $__mk->execute([':n1' => $__failNote, ':n2' => $__failNote, ':o' => $order_id]);
            } catch (Throwable $__e) { /* fail-open */ }
            // پیام UI fallback اگه textbotlang در دسترس نباشه (مثلاً وقتی از cron صدا زده میشه)
            $__uiErr = isset($textbotlang['users']['sell']['ErrorConfig']) && is_string($textbotlang['users']['sell']['ErrorConfig']) && trim($textbotlang['users']['sell']['ErrorConfig']) !== ''
                ? $textbotlang['users']['sell']['ErrorConfig']
                : "❌ متاسفانه ساخت سرویس با خطا مواجه شد. مبلغ پرداختی به کیف پول شما برگشت داده شد.";
            sendmessage($Balance_id['id'], $__uiErr, $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ " . rxFormatToman($balance) . " تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت کانفیگ
<blockquote>✍️ دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : {$Balance_id['id']}</blockquote>
<blockquote>نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $Shoppinginfo = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"],
                ]
            ]
        ]);
        $output_config_link = "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        if (intval($get_invoice['Service_time']) == 0)
            $get_invoice['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
        if (intval($get_invoice['Volume']) == 0)
            $get_invoice['Volume'] = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $get_invoice['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $get_invoice['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $get_invoice['Volume'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($marzban_list_get['type'] == "Manualsale") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $get_invoice['id_invoice']);
        }
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $get_invoice['id_invoice'], $get_invoice['id_user'], $image);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'parse_mode' => 'HTML',
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $affiliateCommissionPaid = payAffiliateCommissionForPurchase($Balance_id['id'], $Balance_id['affiliates'] ?? null, (float) $Payment_report['price']);
        if ($affiliateCommissionPaid !== null) {
            $result = number_format($affiliateCommissionPaid);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
            $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
        }
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($setting['numbercount']) + 1;
                update("setting", "numbercount", $value);
            }
        }
        $__walletPortion = (float)$get_invoice['price_product'] - (float)($Payment_report['price'] ?? 0);
        if ($__walletPortion < 0) {
            $__walletPortion = 0;
        }
        $Balance_prims = (float)$Balance_id['Balance'] - $__walletPortion;
        if ($Balance_prims <= 0) {
            $Balance_prims = 0;
        }
        $__stmtWalletDeduct = $pdo->prepare("UPDATE user SET Balance = GREATEST(Balance - :d, 0) WHERE id = :u");
        $__stmtWalletDeduct->execute([':d' => $__walletPortion, ':u' => $Balance_id['id']]);
        $balanceformatsell = select("user", "Balance", "id", $get_invoice['id_user'], "select")['Balance'];
        $balanceformatsell = number_format($balanceformatsell, 0);
        $balancebefore = number_format($Balance_id['Balance'], 0);
        $rxFmtInvoicePriceProduct = rxFormatToman($get_invoice['price_product']);
        $rxFmtPaymentReportPrice = rxFormatToman($Payment_report['price']);
        $timejalali = jdate('Y/m/d H:i:s');
        $textonebuy = "";
        if ($countinvoice == 1) {
            $textonebuy = "📌 خرید اول کاربر";
        }
        // [fallback] اگه textbotlang در cron context کامل لود نشده، text رو با مقدار default پر کن تا تلگرام reject نکنه
        $__mngBtnText = '👤 مدیریت کاربر';
        if (isset($textbotlang['Admin']['ManageUser']['mangebtnuser']) && is_string($textbotlang['Admin']['ManageUser']['mangebtnuser']) && trim($textbotlang['Admin']['ManageUser']['mangebtnuser']) !== '') {
            $__mngBtnText = $textbotlang['Admin']['ManageUser']['mangebtnuser'];
        }
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $__mngBtnText, 'callback_data' => 'manageuser_' . $Balance_id['id']],
                ],
            ]
        ]);
        $text_report = "📣 جزئیات ساخت اکانت در ربات بعد پرداخت ثبت شد .

$textonebuy
<blockquote>▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@{$Balance_id['username']}</blockquote>
<blockquote>▫️نام کاربری کانفیگ :$username_ac</blockquote>
<blockquote>▫️لوکیشن سرویس : {$get_invoice['Service_location']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$get_invoice['Service_time']} روز</blockquote>
<blockquote>▫️نام محصول خریداری شده :{$get_invoice['name_product']}</blockquote>
<blockquote>▫️حجم خریداری شده : {$get_invoice['Volume']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balancebefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: {$get_invoice['id_invoice']}</blockquote>
<blockquote>▫️نوع کاربر : {$Balance_id['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$Balance_id['number']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtInvoicePriceProduct} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtPaymentReportPrice} تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $text_report,
                'parse_mode' => "HTML",
                'reply_markup' => $Response
            ]);
        }
        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('new_sub', [
                'user_id'    => $Balance_id['id'],
                'amount'     => $get_invoice['name_product'],
                'price'      => number_format((float)$Payment_report['price']),
                'panel_name' => $marzban_list_get['name_panel'] ?? '',
                'category'   => $info_product['category'] ?? '',
            ], $setting);
        }
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        update("invoice", "Status", "active", "username", $get_invoice['username']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
            $rxFmtBalanceBeforeBuy = rxFormatToman($Balance_id['Balance']);
            $textconfrom = "✅ پرداخت تایید شده
🛍خرید سرویس
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل خرید  : {$rxFmtBalanceBeforeBuy}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($_receipt_chat_id, $message_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_report_thread > 0 ? $_receipt_report_thread : null);
        }
    } elseif ($steppay[0] == "getextenduser") {
        $balanceformatsell = number_format(select("user", "Balance", "id", $Balance_id['id'], "select")['Balance'], 0);
        $partsdic = explode("%", $steppay[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $data_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $service_other = $data_order;
        if ($service_other == false) {
            sendmessage($Balance_id['id'], '❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $codeproduct = $service_other['code_product'];
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = $data_order['price'];
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = '{$nameloc['Service_location']}' OR Location = '/all') AND (agent = '{$Balance_id['agent']}' OR agent = 'all') AND code_product = '$codeproduct'");
            $stmt->execute();
            $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($prodcut)) {
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            $__refundOkPr = balance_atomic_credit($Balance_id['id'], $Payment_report['price']);
            if ($__refundOkPr && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($Balance_id['id'], 'credit', $Payment_report['price'], 'refund', 'بازگشت وجه - محصول تمدید در دسترس نیست', (string)$Payment_report['id_order'], 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
            sendmessage($Balance_id['id'], "❌ محصول این تمدید دیگر در دسترس نیست؛ مبلغ پرداختی به کیف پول شما بازگردانده شد.", $keyboard, 'HTML');
            return;
        }
        if ($nameloc['name_product'] == "سرویس تست") {
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        }
        $dateacc = date('Y/m/d H:i:s');
        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            $stockNew = function_exists('nmStockReserveForProduct') ? nmStockReserveForProduct($marzban_list_get, $prodcut, $Balance_id['id'], $nameloc['id_invoice'], 'paid_extend_national_stock') : false;
            if (!is_array($stockNew) || (string)($stockNew['content'] ?? '') === '') {
                if (is_array($stockNew) && function_exists('nmStockReleaseReservation')) nmStockReleaseReservation($stockNew);
                $balance = $Balance_id['Balance'] + $Payment_report['price'];
                $__refundOkSt = balance_atomic_credit($Balance_id['id'], $Payment_report['price']);
                if ($__refundOkSt && function_exists('wallet_ledger_record')) {
                    wallet_ledger_record($Balance_id['id'], 'credit', $Payment_report['price'], 'refund', 'بازگشت وجه - موجودی انبار تمام شده', (string)$Payment_report['id_order'], 'invoice', (string)($nameloc['id_invoice'] ?? ''));
                }
                sendmessage($Balance_id['id'], "❌ موجودی انبار برای این محصول تمام شده است؛ مبلغ پرداختی به کیف پول شما بازگردانده شد.", $keyboard, 'HTML');
                return;
            }
            update("user", "Balance", 0, "id", $Balance_id['id']);
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
            update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
            update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
            try { update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
            $invoiceNew = array_merge((array)$nameloc, ['name_product' => $prodcut['name_product'], 'price_product' => $prodcut['price_product'], 'Volume' => $prodcut['Volume_constraint'], 'Service_time' => $prodcut['Service_time'], 'time_sell' => time(), 'user_info' => $stockNew['content'], 'source_panel_code' => $marzban_list_get['code_panel']]);
            if (function_exists('nmStockDeliverConfig')) nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
            $extend = ['status' => true, 'stock' => true];
        } else {
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $Balance_Low_user = 0;
            update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
            $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
        if ($extend['status'] == false) {
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            $__refundOkEx = balance_atomic_credit($Balance_id['id'], $Payment_report['price']);
            if ($__refundOkEx && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($Balance_id['id'], 'credit', $Payment_report['price'], 'refund', 'بازگشت وجه - خطا در تمدید سرویس', (string)$Payment_report['id_order'], 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ " . rxFormatToman($balance) . " تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $extend['msg'] = json_encode($extend['msg']);
            $textreports = "
        خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
            $rxTopupExtendMsg = "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید";
            if (($extend['code'] ?? '') === 'manual_stock_empty') {
                $rxTopupExtendMsg = "❌ موجودی انبار برای این محصول تمام شده است.";
            } elseif (($extend['code'] ?? '') === 'queued_renewal_exists') {
                $rxTopupExtendMsg = "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است.";
            }
            sendmessage($nameloc['id_user'], $rxTopupExtendMsg, null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        }

        MiniDiscount::logSale([
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'renewal',
            'name_product' => $prodcut['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        update("service_other", "output", json_encode($extend), "id", $data_order['id']);
        update("service_other", "status", "paid", "id", $data_order['id']);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'parse_mode' => 'HTML',
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $keyboardextendfnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        if ($Balance_id['agent'] == "f") {
            $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
        } else {
            $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$Balance_id['agenr']];
        }
        $renewCashbackEligible = !function_exists('rx_shopCashbackEligible')
            || rx_shopCashbackEligible("chashbackextend", $Balance_id['register'] ?? null, "getextenduser", $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
        if ($renewCashbackEligible && intval($valurcashbackextend) != 0) {
            $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
            $__renewCashbackOk = balance_atomic_credit($Balance_id['id'], $result);
            if ($__renewCashbackOk && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($Balance_id['id'], 'credit', $result, 'cashback', 'هدیه بازگشت وجه تمدید سرویس', (string)($Payment_report['id_order'] ?? ''), 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
            sendmessage($Balance_id['id'], "تبریک 🎉
📌 به عنوان هدیه تمدید مبلغ " . rxFormatToman($result) . " تومان حساب شما شارژ گردید", null, 'HTML');
        }
        $priceproductformat = number_format($prodcut['price_product']);
        if (!empty($extend['queued'])) {
            $rxQueuedSuccessTpl = $datatextbot['dyn_renewconfirm_queued_success'] ?? '✅ سرویس خریداری شده رزرو شد و به محض پایان سرویس فعلی فعال می‌گردد.';
            $textextend = "$rxQueuedSuccessTpl

▫️نام سرویس : $usernamepanel
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        } else {
            $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamepanel
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        }
        sendmessage($Balance_id['id'], $textextend, $keyboardextendfnished, 'HTML');
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 2;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $timejalali = jdate('Y/m/d H:i:s');
        $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

<blockquote>▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code></blockquote>
<blockquote>▫️نام کاربری کاربر : @{$Balance_id['username']}</blockquote>
<blockquote>▫️نام کاربری کانفیگ :$usernamepanel</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}</blockquote>
<blockquote>▫️نام محصول : {$prodcut['name_product']}</blockquote>
<blockquote>▫️حجم محصول : {$prodcut['Volume_constraint']}</blockquote>
<blockquote>▫️زمان محصول : {$prodcut['Service_time']}</blockquote>
<blockquote>▫️مبلغ تمدید : $priceproductformat تومان</blockquote>
<blockquote>▫️موجودی قبل از خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('renewal', [
                'user_id'    => $Balance_id['id'],
                'amount'     => $prodcut['name_product'],
                'price'      => $priceproductformat,
                'panel_name' => $marzban_list_get['name_panel'] ?? '',
                'category'   => $prodcut['category'] ?? '',
            ], $setting);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {

            $rxFmtBalanceBeforeExtend = rxFormatToman($Balance_id['Balance']);
            $textconfrom = "✅ پرداخت تایید شده
🔋 تمدید سرویس
🪪 نام کاربری کانفیگ : $usernamepanel
🛍 نام محصول : {$prodcut['name_product']}
🌏 نام لوکیشن : {$nameloc['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل تمدید  : {$rxFmtBalanceBeforeExtend}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($_receipt_chat_id, $message_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_report_thread > 0 ? $_receipt_report_thread : null);
        }
    } elseif ($steppay[0] == "getextravolumeuser") {
        $steppay = explode("%", $steppay[1]);
        $volume = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != null) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'volume_value' => $volume,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_user";
        $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $volume);
        if ($extra_volume['status'] == false) {
            $extra_volume['msg'] = json_encode($extra_volume['msg']);
            $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
            sendmessage($nameloc['id_user'], "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        MiniDiscount::logSale([
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'volume',
            'name_product' => $nameloc['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_volume));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price'], 0);
        $rxFmtBalanceBeforeVolume = rxFormatToman($Balance_id['Balance']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس  : {$steppay[0]}
▫️حجم اضافه : $volume گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textvolume, $keyboardextrafnished, 'HTML');
        $volumes = $volume;
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید حجم اضافه
🛍 حجم خریداری شده  : $volumes گیگ
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeVolume}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($_receipt_chat_id, $message_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_report_thread > 0 ? $_receipt_report_thread : null);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر حجم اضافه خریده است

<blockquote>🪪 آیدی عددی : {$Balance_id['id']}</blockquote>
<blockquote>🛍 حجم خریداری شده  : $volumes گیگ</blockquote>
<blockquote>💰 مبلغ پرداختی : " . rxFormatToman($Payment_report['price']) . " تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ {$steppay[0]}</blockquote>
<blockquote>موجودی کاربر قبل خرید : {$rxFmtBalanceBeforeVolume}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('volume_topup', [
                'user_id' => $Balance_id['id'],
                'amount'  => $volumes,
                'price'   => $volumesformat,
            ], $setting);
        }
    } elseif ($steppay[0] == "getextratimeuser") {
        $steppay = explode("%", $steppay[1]);
        $tmieextra = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != false) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $nameloc['id_user']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'day' => $tmieextra,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_time_user";
        $timeservice = $DataUserOut['expire'] - time();
        $day = floor($timeservice / 86400);
        $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $tmieextra);
        if ($extra_time['status'] == false) {
            $extra_time['msg'] = json_encode($extra_time['msg']);
            $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_time['msg']}</blockquote>";
            sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        MiniDiscount::logSale([
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'time',
            'name_product' => $nameloc['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_time));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price']);
        $rxFmtBalanceBeforeTime = rxFormatToman($Balance_id['Balance']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$steppay[0]}
▫️زمان اضافه : $tmieextra روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textextratime, $keyboardextrafnished, 'HTML');
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $volumes = $tmieextra;
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید زمان اضافه
🛍 زمان خریداری شده  : $volumes روز
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeTime}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($_receipt_chat_id, $message_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_report_thread > 0 ? $_receipt_report_thread : null);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر زمان اضافه خریده است

<blockquote>🪪 آیدی عددی : {$Balance_id['id']}</blockquote>
<blockquote>🛍 زمان خریداری شده  : $volumes روز</blockquote>
<blockquote>💰 مبلغ پرداختی : " . rxFormatToman($Payment_report['price']) . " تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ {$steppay[0]}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'parse_mode' => 'HTML',
                'message_thread_id' => $otherservice,
                'text' => $text_report,
            ]);
        }
        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('time_extra', [
                'user_id' => $Balance_id['id'],
                'amount'  => $tmieextra,
                'price'   => $volumesformat,
            ], $setting);
        }
    } else {
        $__chargeBonus = isset($Payment_report['charge_bonus']) ? intval($Payment_report['charge_bonus']) : 0;
        $__paidAmount = intval($Payment_report['price']);
        $__creditAmount = $__paidAmount + $__chargeBonus;
        $Balance_confrim = intval($Balance_id['Balance']) + $__creditAmount;
        $rxClaimCharge = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', direct_payment_done = 1 WHERE id_order = :o AND direct_payment_done IS NULL");
        $rxClaimCharge->execute([':o' => $Payment_report['id_order']]);
        if ($rxClaimCharge->rowCount() < 1) {
            return;
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        balance_atomic_credit($Payment_report['id_user'], $__creditAmount);
        update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $Payment_report['id_order']);
        update("user", "Processing_value_four", "", "id", $Payment_report['id_user']);

        $__chargeDiscountCode = trim((string)($Payment_report['discount_code'] ?? ''));
        $__chargeDiscountAlreadyConsumed = (string)($Payment_report['discount_consumed'] ?? '') === '1';
        if ($__chargeDiscountCode !== '' && !$__chargeDiscountAlreadyConsumed && class_exists('MiniDiscount')) {
            update("Payment_report", "discount_consumed", "1", "id_order", $Payment_report['id_order']);
            MiniDiscount::markSellUsed($__chargeDiscountCode, $Balance_id);
            MiniDiscount::logOrderDiscount([
                'id_user' => $Payment_report['id_user'],
                'code' => $__chargeDiscountCode,
                'kind' => 'sell',
                'value_type' => null,
                'value_raw' => null,
                'price_before' => (float)($Payment_report['price_before_discount'] ?? 0),
                'discount_amount' => (float)($Payment_report['discount_amount'] ?? 0),
                'price_after' => $__paidAmount,
                'section' => 'charge',
            ]);
        }
        if (function_exists('wallet_ledger_record')) {
            $__pm = (string) ($Payment_report['Payment_Method'] ?? '');
            if (stripos($__pm, 'crypto') !== false || stripos($__pm, 'arze digital') !== false || stripos($__pm, 'plisio') !== false || stripos($__pm, 'nowpayment') !== false || stripos($__pm, 'digitaltron') !== false) {
                $__wlCategory = 'topup_crypto';
            } elseif (stripos($__pm, 'cart to cart') !== false || stripos($__pm, 'carttocart') !== false) {
                $__wlCategory = 'topup_card';
            } else {
                $__wlCategory = 'topup_gateway';
            }
            wallet_ledger_record($Payment_report['id_user'], 'credit', $__creditAmount, $__wlCategory, $__pm, $Payment_report['id_order']);
        }
        $format_price_cart = number_format($__paidAmount, 0);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $rxFmtBalanceBeforeTopup = rxFormatToman($Balance_id['Balance']);
            $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeTopup}
✍️ توضیحات : {$paymentNote}";
            Editmessagetext($_receipt_chat_id, $message_id, $textconfrom, $Confirm_pay, 'HTML', $_receipt_report_thread > 0 ? $_receipt_report_thread : null);
        }
        $__creditFmt = number_format($__creditAmount, 0);
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$__creditFmt} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.

🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
        if (function_exists('faoxima_public_purchase_log_event')) {
            faoxima_public_purchase_log_event('wallet_deposit', [
                'user_id' => $Payment_report['id_user'],
                'amount'  => $__creditFmt,
                'price'   => $__creditFmt,
            ], $setting);
        }
    }
    if (function_exists('update')) {
        update("Payment_report", "direct_payment_done", 1, "id_order", $order_id);
    }
}
