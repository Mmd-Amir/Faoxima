<?php

/**
 * Where a buyer lands after paying through Variza (return_url).
 *
 * Variza verifies card-to-card via SMS, often minutes after the buyer has
 * closed the tab, so this page never trusts what it sees — it only reports
 * what this bot knows. The real settlement is the push to
 * variza_webhook.php; this page is a waiting room that turns into a receipt
 * if the push has already arrived.
 */

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';

$order = isset($_GET['order']) ? (string) $_GET['order'] : '';
$order = preg_replace('/[^A-Za-z0-9_\-]/', '', $order);

$state = 'missing';
if ($order !== '' && function_exists('select')) {
    $payment = select('Payment_report', '*', 'id_order', $order, 'select');
    if (is_array($payment) && ($payment['Payment_Method'] ?? '') === 'variza') {
        $state = (($payment['payment_Status'] ?? '') === 'paid') ? 'paid' : 'waiting';
    }
}

$botUsername = '';
if (isset($usernamebot) && is_string($usernamebot) && trim($usernamebot) !== '') {
    $botUsername = ltrim(trim((string) $usernamebot), '@');
}
if ($botUsername === '' && function_exists('select')) {
    $row = select('setting', 'usernamebot', null, null, 'select');
    if (is_array($row) && !empty($row['usernamebot'])) {
        $botUsername = ltrim(trim((string) $row['usernamebot']), '@');
    }
}

$miniappName = 'miniapp';
if (function_exists('select')) {
    $miniRow = select('shopSetting', '*', 'Namevalue', 'miniapp_short_name', 'select');
    if (is_array($miniRow) && !empty($miniRow['value'])) {
        $miniappName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $miniRow['value']);
        if ($miniappName === '') $miniappName = 'miniapp';
    }
}

$tgUrl = '';
if ($botUsername !== '') {
    $startParam = substr('varizapaid_' . $order, 0, 64);
    $tgUrl = 'https://t.me/' . $botUsername . '/' . $miniappName . '?startapp=' . rawurlencode($startParam);
}

if ($state === 'paid') {
    $emoji = '✅';
    $titleFa = 'پرداخت موفقیت‌آمیز بود';
    $hintFa = 'پرداخت شما توسط واریزا تأیید شد. موجودی کیف‌پول یا سفارش شما به‌صورت خودکار به‌روزرسانی شده و از طریق ربات به شما اطلاع داده خواهد شد.';
    $isFail = false;
} elseif ($state === 'waiting') {
    $emoji = '⏳';
    $titleFa = 'در انتظار تأیید پرداخت';
    $hintFa = 'واریز شما دریافت شد و در انتظار تأیید خودکار واریزا است. پس از تأیید، سرویس به‌صورت خودکار فعال خواهد شد. نیازی به اقدام دیگری نیست.';
    $isFail = false;
} else {
    $emoji = '❌';
    $titleFa = 'سفارش پیدا نشد';
    $hintFa = 'این سفارش مربوط به واریزا نیست یا شناسه آن معتبر نمی‌باشد. می‌توانید به ربات بازگردید و دوباره تلاش کنید.';
    $isFail = true;
}
?><!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?php echo htmlspecialchars($titleFa, ENT_QUOTES); ?></title>
<style>
:root {
    --bg: #0a0907;
    --surface: #14110d;
    --border: #2b2620;
    --text: #f5f5f5;
    --muted: #9a9388;
    --accent: #b48def;
    --accent-bright: #c8a8f3;
    --green: #6fce6f;
    --red: #e57373;
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: var(--bg); color: var(--text); font-family: Tahoma, system-ui, "Segoe UI", sans-serif; }
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.card {
    width: 100%;
    max-width: 460px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 32px 24px 24px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.icon-wrap {
    width: 96px;
    height: 96px;
    margin: 0 auto 16px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: <?php echo $isFail ? 'rgba(229,115,115,0.10)' : 'rgba(111,206,111,0.10)'; ?>;
    border: 2px solid <?php echo $isFail ? 'rgba(229,115,115,0.35)' : 'rgba(111,206,111,0.35)'; ?>;
    font-size: 56px;
    line-height: 1;
}
h1 {
    margin: 0 0 8px;
    font-size: 22px;
    color: <?php echo $isFail ? 'var(--red)' : 'var(--green)'; ?>;
}
.muted { color: var(--muted); font-size: 14px; line-height: 1.9; margin: 0 0 18px; }
.kv {
    display: flex;
    justify-content: space-between;
    background: rgba(255,255,255,0.03);
    border: 1px dashed var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    margin: 0 0 10px;
    font-size: 13px;
}
.kv .lbl { color: var(--muted); }
.kv .val { font-family: ui-monospace, "JetBrains Mono", monospace; color: var(--accent-bright); word-break: break-all; }
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 18px;
    border-radius: 12px;
    border: none;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.08s ease, background 0.15s ease;
    margin-top: 12px;
}
.btn-primary {
    background: linear-gradient(135deg, var(--accent), var(--accent-bright));
    color: #1a1620;
}
.btn-primary:hover  { background: var(--accent-bright); }
.btn-primary:active { transform: scale(0.99); }
.btn-ghost {
    background: transparent;
    color: var(--muted);
    border: 1px solid var(--border);
    font-weight: 500;
    font-size: 13px;
    padding: 10px 14px;
}
.btn-ghost:hover { color: var(--text); border-color: #444; }
.brand-bar {
    margin-top: 18px;
    color: var(--muted);
    font-size: 11px;
    font-family: ui-monospace, monospace;
}
</style>
</head>
<body>
<div class="card">
    <div class="icon-wrap"><?php echo $emoji; ?></div>
    <h1><?php echo htmlspecialchars($titleFa, ENT_QUOTES); ?></h1>
    <p class="muted"><?php echo htmlspecialchars($hintFa, ENT_QUOTES); ?></p>

    <div class="kv">
        <span class="lbl">کد فاکتور</span>
        <span class="val"><?php echo htmlspecialchars($order !== '' ? $order : 'unknown', ENT_QUOTES); ?></span>
    </div>

    <?php if ($tgUrl !== ''): ?>
    <a class="btn btn-primary" href="<?php echo htmlspecialchars($tgUrl, ENT_QUOTES); ?>">
        🤖 بازکردن تلگرام و بازگشت به ربات
    </a>
    <?php else: ?>
    <p class="muted">آدرس ربات روی سرور تنظیم نشده — لطفاً از پنل ادمین وارد ربات شوید.</p>
    <?php endif; ?>

    <button class="btn btn-ghost" type="button" onclick="try{window.close();}catch(e){}">
        بستن این پنجره
    </button>

    <div class="brand-bar">
        پرداخت امن با واریزا
    </div>
</div>
</body>
</html>
