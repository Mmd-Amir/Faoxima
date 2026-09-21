<?php

/**
 * AbanGateway callback — one door, two callers, neither trusted.
 *
 * The buyer's browser comes back here after paying, and AbanGateway's server
 * knocks here when the bank's SMS settles an invoice — often minutes after
 * the buyer has closed the tab, which for card to card is the ordinary case.
 * Either way the request only *names* an order. This file then asks the
 * gateway, server to server and with the connection key, whether that order
 * was paid, and credits it only when the answer carries the same order id and
 * at least the billed amount. A forged knock is a question about an invoice
 * the caller does not control, and is answered with "no".
 *
 * Settling goes through payment_confirm_paid(), which is atomic, so the
 * buyer's return and the gateway's push arriving together credit once.
 *
 * A browser gets a page (waiting / paid / not found); everything else gets JSON.
 */

ini_set('error_log', 'error_log');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../lib/PaymentConfirm.php';

function abangateway_callback_log(string $type, string $message, array $context = []): void
{
    if (function_exists('rx_log_event')) {
        rx_log_event($type, $message, $context);
        return;
    }
    error_log('[abangateway] ' . $type . ': ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
}

// The buyer's return carries ?order=…; the gateway's push carries ?order_id=….
$order = '';
foreach (['order', 'order_id'] as $abangatewayField) {
    if (isset($_REQUEST[$abangatewayField]) && is_string($_REQUEST[$abangatewayField])) {
        $order = $_REQUEST[$abangatewayField];
        break;
    }
}
$order = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $order), 0, 64);

$wantsPage = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false;

$state = 'missing';
$payment = null;
if ($order !== '' && function_exists('select')) {
    $row = select('Payment_report', '*', 'id_order', $order, 'select');
    if (is_array($row) && ($row['Payment_Method'] ?? '') === 'abangateway') {
        $payment = $row;
        $state = (($row['payment_Status'] ?? '') === 'paid') ? 'paid' : 'waiting';
    }
}

if ($state === 'waiting' && function_exists('abangatewayVerifyPayment')) {
    // The authority this bot stored when it made the link — never the one in
    // the request.
    $authority = trim((string) ($payment['dec_not_confirmed'] ?? ''));
    if (strpos($authority, 'abn_') !== 0) {
        $authority = '';
    }
    $verify = abangatewayVerifyPayment($authority, $order, (int) ($payment['price'] ?? 0));

    if (!empty($verify['paid'])) {
        try {
            $result = payment_confirm_paid($order, 'chashbackabangateway', [
                'method' => 'آبان گیت وی 💳',
                'extra_lines' => $authority !== '' ? ['🔗 شناسه پرداخت آبان گیت وی: ' . $authority] : [],
            ]);
            // `ok` is false when another request settled it a moment ago;
            // either way the order is paid now.
            $state = 'paid';
            if (empty($result['ok'])) {
                abangateway_callback_log('ABANGATEWAY_ALREADY_SETTLED', (string) ($result['reason'] ?? 'noop'), ['order_id' => $order]);
            }
        } catch (Throwable $e) {
            abangateway_callback_log('ABANGATEWAY_CONFIRM_FAILED', 'payment_confirm_paid threw', [
                'order_id' => $order,
                'error' => $e->getMessage(),
            ]);
            if (!$wantsPage) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'delivery failed'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif (in_array((string) ($verify['message'] ?? ''), ['order mismatch', 'amount mismatch'], true)) {
        abangateway_callback_log('ABANGATEWAY_VERIFY_MISMATCH', (string) $verify['message'], [
            'order_id' => $order,
            'billed_toman' => (int) ($payment['price'] ?? 0),
            'reported_rial' => (int) ($verify['amount_rial'] ?? 0),
        ]);
    }
}

if (!$wantsPage) {
    // The gateway retries on anything but a 2xx, so "not paid yet" is a 200
    // with an honest body, and only an unknown order is a 404.
    http_response_code($state === 'missing' ? 404 : 200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $state === 'missing' ? 'order not found' : $state], JSON_UNESCAPED_UNICODE);
    exit;
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

$isMiniapp = is_array($payment) && (string) ($payment['source'] ?? '') === 'miniapp';
$tgUrl = '';
if ($botUsername !== '') {
    $tgUrl = 'https://t.me/' . $botUsername;
    if ($isMiniapp) {
        $startParam = substr('abangatewaypaid_' . $order, 0, 64);
        $tgUrl .= '/' . $miniappName . '?startapp=' . rawurlencode($startParam);
    }
}

// While waiting, the page asks again by itself for a while: every load is a
// fresh verify, so an open tab settles the order even if the push is late.
$tries = isset($_GET['n']) ? max(0, min(999, (int) $_GET['n'])) : 0;
$refreshUrl = '';
if ($state === 'waiting' && $tries < 120) {
    $refreshUrl = '?order=' . rawurlencode($order) . '&n=' . ($tries + 1);
}

if ($state === 'paid') {
    $emoji = '✅';
    $titleFa = 'پرداخت موفق بود';
    $hintFa = 'واریز شما تایید شد. موجودی کیف پول یا سفارش شما خودکار به روز شده و از طریق ربات به شما خبر داده میشود.';
    $isFail = false;
} elseif ($state === 'waiting') {
    $emoji = '⏳';
    $titleFa = 'در انتظار تایید پرداخت';
    $hintFa = 'اگر کارت به کارت را انجام داده اید، تایید آن معمولا چند ثانیه تا چند دقیقه طول میکشد و خودکار انجام میشود. میتوانید این صفحه را ببندید؛ نتیجه در ربات به شما خبر داده میشود.';
    $isFail = false;
} else {
    $emoji = '❌';
    $titleFa = 'سفارش پیدا نشد';
    $hintFa = 'این سفارش مربوط به این درگاه نیست یا شناسه آن معتبر نیست. میتوانید به ربات برگردید و دوباره تلاش کنید.';
    $isFail = true;
}
?><!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="robots" content="noindex" />
<?php if ($refreshUrl !== ''): ?>
<meta http-equiv="refresh" content="10;url=<?php echo htmlspecialchars($refreshUrl, ENT_QUOTES); ?>" />
<?php endif; ?>
<title><?php echo htmlspecialchars($titleFa, ENT_QUOTES); ?></title>
<style>
:root {
    --bg: #0a0907;
    --surface: #14110d;
    --border: #2b2620;
    --text: #f5f5f5;
    --muted: #9a9388;
    --accent: #3b82f6;
    --accent-bright: #60a5fa;
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
    color: #0b1220;
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
    <p class="muted">آدرس ربات روی سرور تنظیم نشده است. لطفا از تلگرام وارد ربات شوید.</p>
    <?php endif; ?>

    <button class="btn btn-ghost" type="button" onclick="try{window.close();}catch(e){}">
        بستن این پنجره
    </button>

    <div class="brand-bar">
        پرداخت کارت به کارت با آبان گیت وی
    </div>
</div>
</body>
</html>
