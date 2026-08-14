<?php

/**
 * Isolated payment helpers for reseller wallet top-ups.
 *
 * These reuse the panel's existing gateway *credentials* (the PaySetting table)
 * and the Phase-2 outbound proxy, but they keep their own callback URLs and
 * their own pending-payment table (`reseller_payment`) so the reseller wallet
 * flow never collides with the customer (`user.Balance`) gateway callbacks.
 *
 * Currently wired: Zarinpal and AqayePardakht (both synchronous IRT gateways).
 * Additional gateways can follow the same request/verify pattern.
 */

require_once __DIR__ . '/lib.php';

if (!function_exists('reseller_pay_setting')) {
    function reseller_pay_setting($name)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return '';
        }
        try {
            $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = :n LIMIT 1");
            $stmt->execute([':n' => $name]);
            $v = $stmt->fetchColumn();
            return $v === false ? '' : (string) $v;
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('reseller_pay_origin')) {
    /** Public https origin of the install (no trailing slash). */
    function reseller_pay_origin()
    {
        $host = (string) ($GLOBALS['domainhosts'] ?? '');
        if ($host !== '') {
            return 'https://' . ltrim($host, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $h = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $h;
    }
}

if (!function_exists('reseller_pay_gateways')) {
    /** Gateways that are configured (have credentials) and available to resellers. */
    function reseller_pay_gateways()
    {
        $list = [];
        if (trim((string) reseller_pay_setting('merchant_zarinpal')) !== '') {
            $list['zarinpal'] = 'درگاه زرین‌پال';
        }
        if (trim((string) reseller_pay_setting('merchant_id_aqayepardakht')) !== '') {
            $list['aqayepardakht'] = 'درگاه آقای پرداخت';
        }
        return $list;
    }
}

if (!function_exists('reseller_pay_curl')) {
    function reseller_pay_curl($url, array $payload, $asJson = true)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $asJson ? json_encode($payload) : http_build_query($payload),
            CURLOPT_HTTPHEADER     => $asJson
                ? ['Content-Type: application/json', 'Accept: application/json']
                : ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        if (function_exists('hamoix_apply_curl_proxy')) {
            hamoix_apply_curl_proxy($ch, 'panel');
        }
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $res, true);
    }
}

if (!function_exists('reseller_payment_create')) {
    /**
     * Create a pending top-up + redirect URL. Returns ['ok'=>bool,'url'=>,'msg'=>].
     */
    function reseller_payment_create($resellerId, $gateway, $amount)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'msg' => 'no database'];
        }
        $amount = (int) $amount;
        if ($amount < 1000) {
            return ['ok' => false, 'msg' => 'حداقل مبلغ شارژ ۱۰۰۰ تومان است.'];
        }
        $orderId = 'rs' . $resellerId . '-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $origin = reseller_pay_origin();
        $callback = $origin . '/panel/reseller/pay_callback.php?gw=' . $gateway . '&order=' . urlencode($orderId);

        if ($gateway === 'zarinpal') {
            $merchant = trim((string) reseller_pay_setting('merchant_zarinpal'));
            if ($merchant === '') {
                return ['ok' => false, 'msg' => 'درگاه زرین‌پال پیکربندی نشده است.'];
            }
            $resp = reseller_pay_curl('https://api.zarinpal.com/pg/v4/payment/request.json', [
                'merchant_id' => $merchant,
                'currency'    => 'IRT',
                'amount'      => $amount,
                'callback_url' => $callback,
                'description' => 'شارژ کیف پول نماینده ' . $orderId,
            ]);
            $authority = $resp['data']['authority'] ?? null;
            $code = $resp['data']['code'] ?? null;
            if (empty($authority) || (int) $code !== 100) {
                return ['ok' => false, 'msg' => 'خطا در ایجاد تراکنش زرین‌پال.'];
            }
            reseller_payment_insert($resellerId, 'zarinpal', $orderId, $authority, $amount);
            return ['ok' => true, 'url' => 'https://www.zarinpal.com/pg/StartPay/' . $authority];
        }

        if ($gateway === 'aqayepardakht') {
            $pin = trim((string) reseller_pay_setting('merchant_id_aqayepardakht'));
            if ($pin === '') {
                return ['ok' => false, 'msg' => 'درگاه آقای پرداخت پیکربندی نشده است.'];
            }
            $resp = reseller_pay_curl('https://panel.aqayepardakht.ir/api/v2/create', [
                'pin'        => $pin,
                'amount'     => $amount,
                'callback'   => $callback,
                'invoice_id' => $orderId,
            ]);
            $transid = $resp['transid'] ?? null;
            if (($resp['status'] ?? '') !== 'success' || empty($transid)) {
                return ['ok' => false, 'msg' => 'خطا در ایجاد تراکنش آقای پرداخت.'];
            }
            reseller_payment_insert($resellerId, 'aqayepardakht', $orderId, (string) $transid, $amount);
            $startPath = ($pin === 'sandbox') ? 'startpay/sandbox/' : 'startpay/';
            return ['ok' => true, 'url' => 'https://panel.aqayepardakht.ir/' . $startPath . $transid];
        }

        return ['ok' => false, 'msg' => 'درگاه نامعتبر است.'];
    }
}

if (!function_exists('reseller_payment_insert')) {
    function reseller_payment_insert($resellerId, $gateway, $orderId, $authority, $amount)
    {
        $pdo = $GLOBALS['pdo'];
        $stmt = $pdo->prepare(
            "INSERT INTO reseller_payment (reseller_id, gateway, order_id, authority, amount, status, created_at)
             VALUES (:rid, :gw, :oid, :auth, :amt, 'pending', :ts)"
        );
        $stmt->execute([
            ':rid'  => (int) $resellerId,
            ':gw'   => $gateway,
            ':oid'  => $orderId,
            ':auth' => (string) $authority,
            ':amt'  => (int) $amount,
            ':ts'   => (string) time(),
        ]);
    }
}

if (!function_exists('reseller_payment_mark_paid')) {
    /**
     * Atomically flip a pending top-up to paid and credit the wallet exactly once.
     * Returns ['ok'=>bool,'amount'=>int,'already'=>bool,'msg'=>string].
     */
    function reseller_payment_mark_paid($orderId, $ref = '')
    {
        $pdo = $GLOBALS['pdo'];
        $upd = $pdo->prepare(
            "UPDATE reseller_payment SET status = 'paid', ref = :ref, paid_at = :ts
             WHERE order_id = :oid AND status <> 'paid'"
        );
        $upd->execute([':ref' => mb_substr((string) $ref, 0, 190), ':ts' => (string) time(), ':oid' => $orderId]);
        if ($upd->rowCount() < 1) {
            return ['ok' => true, 'amount' => 0, 'already' => true, 'msg' => 'قبلاً پردازش شده'];
        }
        $sel = $pdo->prepare("SELECT * FROM reseller_payment WHERE order_id = :oid LIMIT 1");
        $sel->execute([':oid' => $orderId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'amount' => 0, 'already' => false, 'msg' => 'تراکنش یافت نشد'];
        }
        $apply = reseller_wallet_apply(
            (int) $row['reseller_id'],
            'topup',
            (int) $row['amount'],
            'شارژ کیف پول از ' . $row['gateway'],
            $orderId
        );
        if (empty($apply['ok'])) {
            // واریز بعد از علامت‌گذاری «paid» شکست خورد؛ ردیف را به «pending» برمی‌گردانیم
            // تا فراخوانی بعدیِ کال‌بک (تأیید درگاه idempotent است) دوباره شارژ کند و پول گم نشود.
            $rb = $pdo->prepare("UPDATE reseller_payment SET status = 'pending', paid_at = '' WHERE order_id = :oid AND status = 'paid'");
            $rb->execute([':oid' => $orderId]);
            error_log('[reseller_payment_mark_paid] wallet credit failed for order ' . $orderId . '; reverted to pending: ' . (string) ($apply['msg'] ?? 'unknown'));
        }
        return ['ok' => $apply['ok'], 'amount' => (int) $row['amount'], 'already' => false, 'msg' => $apply['msg']];
    }
}

if (!function_exists('reseller_payment_verify')) {
    /** Confirm a pending payment with the gateway, then credit the wallet. */
    function reseller_payment_verify(array $payment)
    {
        $gateway = (string) $payment['gateway'];
        $amount = (int) $payment['amount'];

        if ($gateway === 'zarinpal') {
            $merchant = trim((string) reseller_pay_setting('merchant_zarinpal'));
            $resp = reseller_pay_curl('https://api.zarinpal.com/pg/v4/payment/verify.json', [
                'merchant_id' => $merchant,
                'amount'      => $amount,
                'authority'   => (string) $payment['authority'],
            ]);
            $msg = $resp['data']['message'] ?? '';
            $code = (int) ($resp['data']['code'] ?? 0);
            $refId = $resp['data']['ref_id'] ?? '';
            if ($code === 100 || $code === 101 || $msg === 'Verified' || $msg === 'Paid') {
                return reseller_payment_mark_paid((string) $payment['order_id'], (string) $refId);
            }
            return ['ok' => false, 'amount' => 0, 'already' => false, 'msg' => 'تأیید پرداخت ناموفق بود'];
        }

        if ($gateway === 'aqayepardakht') {
            $pin = trim((string) reseller_pay_setting('merchant_id_aqayepardakht'));
            $resp = reseller_pay_curl('https://panel.aqayepardakht.ir/api/v2/verify', [
                'pin'     => $pin,
                'amount'  => $amount,
                'transid' => (string) $payment['authority'],
            ]);
            $code = (string) ($resp['code'] ?? '');
            if ($code === '1' || $code === '2') {
                return reseller_payment_mark_paid((string) $payment['order_id'], (string) $payment['authority']);
            }
            return ['ok' => false, 'amount' => 0, 'already' => false, 'msg' => 'تأیید پرداخت ناموفق بود'];
        }

        return ['ok' => false, 'amount' => 0, 'already' => false, 'msg' => 'درگاه نامعتبر'];
    }
}

if (!function_exists('reseller_payment_find')) {
    function reseller_payment_find($orderId)
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM reseller_payment WHERE order_id = :oid LIMIT 1");
        $stmt->execute([':oid' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
