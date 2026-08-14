<?php

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
date_default_timezone_set('Asia/Tehran');
@putenv('TZ=Asia/Tehran');

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    http_response_code(500);
    echo 'cannot resolve root';
    exit;
}
require_once $root . '/config.php';

// نقطه‌ی پینگ برای تست لوپ‌بک (سبک، فقط برای تست اتصال؛ خروجی بی‌ضرر)
if (isset($_GET['ping'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'pong';
    exit;
}

// احراز هویت ساده: ?key= باید با secrettoken (یا APIKEY) در config برابر باشد
$provided = (string) ($_GET['key'] ?? '');
$expected = (string) ($GLOBALS['secrettoken'] ?? '');
if ($expected === '') {
    $expected = (string) ($GLOBALS['APIKEY'] ?? '');
}
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    echo "این صفحه با کلید محافظت می‌شود. آدرس را به این شکل باز کنید:\n";
    echo "  /cron/diag.php?key=YOUR_KEY\n";
    echo "مقدار key همان \$secrettoken (یا اگر خالی بود \$APIKEY) داخل config.php است.\n";
    exit;
}

function rx_diag_curl(string $url, ?string $resolveTo = null): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 0, 'err' => 'curl extension not available'];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'code' => 0, 'err' => 'curl_init failed'];
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_NOSIGNAL       => true,
    ];
    if ($resolveTo !== null && $resolveTo !== '') {
        $h = parse_url($url, PHP_URL_HOST);
        if (is_string($h) && $h !== '') {
            $opts[CURLOPT_RESOLVE] = [$h . ':443:' . $resolveTo, $h . ':80:' . $resolveTo];
        }
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [
        'ok'   => ($body !== false && $err === ''),
        'code' => $code,
        'err'  => $err,
        'body' => is_string($body) ? substr($body, 0, 60) : '',
    ];
}

function rx_diag_badge(bool $ok, string $okText = 'OK', string $badText = 'مشکل'): string
{
    $color = $ok ? '#16a34a' : '#dc2626';
    $txt   = $ok ? $okText : $badText;
    return '<b style="color:' . $color . '">' . htmlspecialchars($txt, ENT_QUOTES) . '</b>';
}

// ---- جمع‌آوری اطلاعات ----
$phpVersion   = PHP_VERSION;
$sapi         = PHP_SAPI;
$hasCurl      = function_exists('curl_init');
$hasCurlMulti = function_exists('curl_multi_init');
$allowUrlFopen = (bool) ini_get('allow_url_fopen');
$disabled     = (string) ini_get('disable_functions');

$domainhosts = (string) ($GLOBALS['domainhosts'] ?? '');
$hostOnly = $domainhosts !== '' ? $domainhosts : (string) ($_SERVER['HTTP_HOST'] ?? '');
$hostOnly = preg_replace('#^https?://#i', '', $hostOnly);
$hostOnly = rtrim((string) $hostOnly, '/');
$basePart = '';
if (strpos($hostOnly, '/') !== false) {
    $segments = explode('/', $hostOnly, 2);
    $hostOnly = $segments[0];
    $basePart = '/' . rtrim($segments[1], '/');
}

$pdo     = $GLOBALS['pdo'] ?? null;
$connect = $GLOBALS['connect'] ?? null;
$dbOk    = ($pdo instanceof PDO) || ($connect !== null);

// تست لوپ‌بک
$pingUrl = 'https://' . $hostOnly . $basePart . '/cron/diag.php?ping=1';
$testPublic = $hostOnly !== '' ? rx_diag_curl($pingUrl, null) : ['ok' => false, 'code' => 0, 'err' => 'domainhosts خالی است'];
$testLoop   = $hostOnly !== '' ? rx_diag_curl($pingUrl, '127.0.0.1') : ['ok' => false, 'code' => 0, 'err' => 'domainhosts خالی است'];

// قابل‌نوشتن بودن مسیرها
$cronDir = $root . '/cron';
$logsDir    = $root . '/logs';
$cronWritable = is_dir($cronDir) && is_writable($cronDir);
$logsWritable    = is_dir($logsDir) ? is_writable($logsDir) : null;

// قفل‌ها و فلگ‌ها
$now = time();
$locks = [];
foreach (array_merge((array) glob($cronDir . '/*.lock'), (array) glob($root . '/cron/*.lock')) as $lf) {
    $locks[] = ['file' => basename(dirname($lf)) . '/' . basename($lf), 'age' => $now - (int) @filemtime($lf)];
}
$flags = [];
foreach ((array) glob($cronDir . '/_*.flag') as $ff) {
    $flags[] = ['file' => basename($ff), 'age' => $now - (int) @filemtime($ff)];
}

// cron_runtime_state
$runtimeRows = [];
$runtimeErr  = '';
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT job_key, last_run, unit, value, enabled FROM cron_runtime_state ORDER BY job_key");
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lr = (int) ($r['last_run'] ?? 0);
                $runtimeRows[] = [
                    'job'     => (string) ($r['job_key'] ?? ''),
                    'last'    => $lr,
                    'age'     => $lr > 0 ? ($now - $lr) : -1,
                    'sched'   => ($r['unit'] ?? '?') . '/' . ($r['value'] ?? '?'),
                    'enabled' => (int) ($r['enabled'] ?? 1),
                ];
            }
        }
    } catch (Throwable $e) {
        $runtimeErr = $e->getMessage();
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cron Diagnostics</title>
<style>
    body { font-family: Tahoma, Arial, sans-serif; background:#0f172a; color:#e2e8f0; padding:18px; line-height:1.8; }
    h1 { font-size:20px; color:#38bdf8; }
    h2 { font-size:16px; color:#fbbf24; margin-top:22px; border-bottom:1px solid #334155; padding-bottom:6px; }
    table { border-collapse:collapse; width:100%; margin-top:8px; font-size:13px; }
    td, th { border:1px solid #334155; padding:6px 10px; text-align:right; }
    th { background:#1e293b; }
    code { background:#1e293b; padding:2px 6px; border-radius:4px; color:#a5f3fc; word-break:break-all; }
    .box { background:#1e293b; border:1px solid #334155; border-radius:8px; padding:12px 16px; margin-top:10px; }
    .hint { color:#94a3b8; font-size:12px; }
</style>
</head>
<body>
<h1>🔧 تشخیص کرون‌جاب</h1>
<p class="hint">زمان سرور: <code><?php echo htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES); ?></code> (Asia/Tehran) — این صفحه فقط می‌خواند و چیزی روی دیسک نمی‌نویسد.</p>

<h2>محیط PHP</h2>
<table>
    <tr><th>نسخه PHP</th><td><code><?php echo htmlspecialchars($phpVersion, ENT_QUOTES); ?></code> (SAPI: <?php echo htmlspecialchars($sapi, ENT_QUOTES); ?>)</td></tr>
    <tr><th>اکستنشن curl</th><td><?php echo rx_diag_badge($hasCurl); ?></td></tr>
    <tr><th>curl_multi</th><td><?php echo rx_diag_badge($hasCurlMulti); ?></td></tr>
    <tr><th>allow_url_fopen</th><td><?php echo rx_diag_badge($allowUrlFopen, 'روشن', 'خاموش'); ?></td></tr>
    <tr><th>disable_functions</th><td><code><?php echo htmlspecialchars($disabled !== '' ? $disabled : '—', ENT_QUOTES); ?></code></td></tr>
</table>

<h2>دیتابیس و دامنه</h2>
<table>
    <tr><th>اتصال دیتابیس</th><td><?php echo rx_diag_badge($dbOk, 'وصل است', 'وصل نیست — config.php را بررسی کنید'); ?></td></tr>
    <tr><th>$domainhosts</th><td><code><?php echo htmlspecialchars($domainhosts !== '' ? $domainhosts : '(خالی!)', ENT_QUOTES); ?></code></td></tr>
    <tr><th>هاست استخراج‌شده</th><td><code><?php echo htmlspecialchars($hostOnly . $basePart, ENT_QUOTES); ?></code></td></tr>
</table>

<h2>تست لوپ‌بک (تعیین‌کننده)</h2>
<div class="box">
    <p>آدرس تست: <code><?php echo htmlspecialchars($pingUrl, ENT_QUOTES); ?></code></p>
    <table>
        <tr><th>مسیر</th><th>نتیجه</th><th>HTTP code</th><th>خطا</th></tr>
        <tr>
            <td>از طریق DNS عمومی (دامنه)</td>
            <td><?php echo rx_diag_badge($testPublic['ok']); ?></td>
            <td><?php echo (int) $testPublic['code']; ?></td>
            <td><code><?php echo htmlspecialchars($testPublic['err'] !== '' ? $testPublic['err'] : '—', ENT_QUOTES); ?></code></td>
        </tr>
        <tr>
            <td>اجباری به 127.0.0.1 (لوپ‌بک)</td>
            <td><?php echo rx_diag_badge($testLoop['ok']); ?></td>
            <td><?php echo (int) $testLoop['code']; ?></td>
            <td><code><?php echo htmlspecialchars($testLoop['err'] !== '' ? $testLoop['err'] : '—', ENT_QUOTES); ?></code></td>
        </tr>
    </table>
    <p class="hint">
        اگر «DNS عمومی» <b style="color:#dc2626">مشکل</b> ولی «127.0.0.1» <b style="color:#16a34a">OK</b> بود ⇒
        علت همان فایروال/hairpin است و اصلاح <code>CURLOPT_RESOLVE</code> در cron.php مشکل fan-out را حل می‌کند.
        در این حالت <b>حتماً</b> دستور کرون سی‌پنل را هم به PHP CLI یا curl لوپ‌بک تغییر دهید.
    </p>
</div>

<h2>مجوز نوشتن مسیرها</h2>
<table>
    <tr><th>cron/ قابل نوشتن</th><td><?php echo rx_diag_badge($cronWritable); ?></td></tr>
    <tr><th>logs/ قابل نوشتن</th><td><?php echo $logsWritable === null ? '<span class="hint">وجود ندارد</span>' : rx_diag_badge($logsWritable); ?></td></tr>
</table>

<h2>قفل‌ها و فلگ‌ها</h2>
<?php if (empty($locks) && empty($flags)): ?>
    <p class="hint">هیچ قفل یا فلگی موجود نیست.</p>
<?php else: ?>
    <table>
        <tr><th>فایل</th><th>سن (ثانیه)</th></tr>
        <?php foreach (array_merge($locks, $flags) as $row): ?>
            <tr><td><code><?php echo htmlspecialchars($row['file'], ENT_QUOTES); ?></code></td><td><?php echo (int) $row['age']; ?></td></tr>
        <?php endforeach; ?>
    </table>
    <p class="hint">قفل کهنه‌ای که آزاد نشده می‌تواند جاب را تا پایان maxAge مسدود کند.</p>
<?php endif; ?>

<h2>cron_runtime_state (آخرین اجرای جاب‌ها)</h2>
<?php if ($runtimeErr !== ''): ?>
    <p class="hint">خطا/جدول موجود نیست: <code><?php echo htmlspecialchars($runtimeErr, ENT_QUOTES); ?></code></p>
<?php elseif (empty($runtimeRows)): ?>
    <p class="hint">جدول خالی است یا هنوز هیچ جابی اجرا نشده.</p>
<?php else: ?>
    <table>
        <tr><th>جاب</th><th>آخرین اجرا (ثانیه پیش)</th><th>زمان‌بندی</th><th>فعال</th></tr>
        <?php foreach ($runtimeRows as $row): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($row['job'], ENT_QUOTES); ?></code></td>
                <td><?php echo $row['age'] < 0 ? '—' : (int) $row['age']; ?></td>
                <td><?php echo htmlspecialchars($row['sched'], ENT_QUOTES); ?></td>
                <td><?php echo $row['enabled'] ? '✅' : '⛔'; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p class="hint">اگر «آخرین اجرا» برای جاب‌ها مدام بالا می‌رود یعنی دیسپچر اجرا نمی‌شود (دستور کرون به cron.php نمی‌رسد).</p>
<?php endif; ?>

<h2>دستور پیشنهادی کرون سی‌پنل</h2>
<div class="box">
    <p>۱) بهترین — PHP CLI:</p>
    <p><code>/usr/local/bin/php <?php echo htmlspecialchars($root . '/cron/cron.php', ENT_QUOTES); ?> &gt;/dev/null 2&gt;&amp;1</code></p>
    <p>۲) یا curl لوپ‌بک (دور زدن فایروال):</p>
    <p><code>curl -s -k -H "Host: <?php echo htmlspecialchars($hostOnly, ENT_QUOTES); ?>" "https://127.0.0.1<?php echo htmlspecialchars($basePart, ENT_QUOTES); ?>/cron/cron.php" &gt;/dev/null 2&gt;&amp;1</code></p>
    <p class="hint">هر دقیقه یک‌بار اجرا شود: <code>* * * * *</code></p>
</div>

</body>
</html>
