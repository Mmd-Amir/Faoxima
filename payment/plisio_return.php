<?php

ini_set('error_log', 'error_log');

$kind = strtolower((string) ($_GET['kind'] ?? 'success'));
$order = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_GET['order'] ?? 'unknown'));
$order = $order !== '' ? $order : 'unknown';
$isFail = $kind === 'fail';
$title = $isFail ? 'پرداخت ناموفق بود' : 'پرداخت موفقیت‌آمیز بود';
$emoji = $isFail ? '❌' : '✅';
$hint = $isFail
    ? 'این تراکنش تأیید نشد. وضعیت پرداخت را از پنل وب بررسی کنید.'
    : 'تراکنش شما ثبت شد. برای مشاهده موجودی و گزارش‌ها وارد پنل وب شوید.';
?><!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
<style>
:root{--bg:#0a0907;--surface:#14110d;--border:#2b2620;--text:#f5f5f5;--muted:#9a9388;--accent:#b48def;--green:#6fce6f;--red:#e57373}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:Tahoma,system-ui,sans-serif}body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{width:100%;max-width:460px;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:32px 24px 24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5)}.icon{width:96px;height:96px;margin:0 auto 16px;border-radius:50%;display:grid;place-items:center;background:<?php echo $isFail?'rgba(229,115,115,.10)':'rgba(111,206,111,.10)'; ?>;border:2px solid <?php echo $isFail?'rgba(229,115,115,.35)':'rgba(111,206,111,.35)'; ?>;font-size:56px}.title{margin:0 0 8px;font-size:22px;color:<?php echo $isFail?'var(--red)':'var(--green)'; ?>}.muted{color:var(--muted);font-size:14px;line-height:1.9;margin:0 0 18px}.kv{display:flex;justify-content:space-between;background:rgba(255,255,255,.03);border:1px dashed var(--border);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px}.val{font-family:ui-monospace,monospace;color:var(--accent);word-break:break-all}.btn{display:inline-flex;align-items:center;justify-content:center;width:100%;padding:14px 18px;border-radius:12px;text-decoration:none;font-weight:700;margin-top:10px}.primary{background:linear-gradient(135deg,var(--accent),#c8a8f3);color:#1a1620}.ghost{border:1px solid var(--border);color:var(--muted)}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><?php echo $emoji; ?></div>
    <h1 class="title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="muted"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="kv"><span>کد فاکتور</span><span class="val"><?php echo htmlspecialchars($order, ENT_QUOTES, 'UTF-8'); ?></span></div>
    <a class="btn primary" href="../panel/index.php">بازگشت به پنل وب</a>
    <button class="btn ghost" type="button" onclick="window.close()">بستن این پنجره</button>
</div>
</body>
</html>
