<?php

ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';


$kind  = isset($_GET['kind']) ? strtolower((string)$_GET['kind']) : 'success';
$order = isset($_GET['order']) ? (string)$_GET['order'] : '';
$order = preg_replace('/[^A-Za-z0-9_\-]/', '', $order);
if ($order === '') {
    $order = 'unknown';
}


$botUsername = '';
if (isset($usernamebot) && is_string($usernamebot) && trim($usernamebot) !== '') {
    $botUsername = ltrim(trim($usernamebot), '@');
}
if ($botUsername === '') {
    @require_once __DIR__ . '/../function.php';
    if (function_exists('select')) {
        $row = select('setting', 'usernamebot', null, null, 'select');
        if (is_array($row) && !empty($row['usernamebot'])) {
            $botUsername = ltrim(trim((string)$row['usernamebot']), '@');
        }
    }
}


$miniappName = 'miniapp';
if (function_exists('select')) {
    $miniRow = select('shopSetting', '*', 'Namevalue', 'miniapp_short_name', 'select');
    if (is_array($miniRow) && !empty($miniRow['value'])) {
        $miniappName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$miniRow['value']);
        if ($miniappName === '') $miniappName = 'miniapp';
    }
}


$startParam = ($kind === 'fail' ? 'plisiofail_' : 'plisiopaid_') . $order;
$startParam = substr($startParam, 0, 64);


$tgUrl = '';
if ($botUsername !== '') {
    $tgUrl = 'https://t.me/' . $botUsername . '/' . $miniappName . '?startapp=' . rawurlencode($startParam);
}

$isFail = ($kind === 'fail');
$titleFa = $isFail ? 'پرداخت ناموفق بود' : 'پرداخت موفقیت‌آمیز بود';
$emoji   = $isFail ? '❌' : '✅';
$hintFa  = $isFail
    ? 'این تراکنش از سمت درگاه تأیید نشد. می‌توانید به ربات بازگردید و دوباره تلاش کنید.'
    : 'تراکنش شما در شبکه ثبت شد. برای پیگیری شارژ کیف پول، به ربات بازگردید.';

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
        <span class="val"><?php echo htmlspecialchars($order, ENT_QUOTES); ?></span>
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
        Faoxima · Plisio gateway
    </div>
</div>
</body>
</html>
