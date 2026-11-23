<?php

require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/admin_ip.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/TelegramInbox.php';

fx_session_start();
header('Cache-Control: no-store');
if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit;
}
$user = $_SESSION['user'] ?? null;
if (!is_string($user) || $user === '') {
    header('Location: login.php');
    exit;
}
$admin = $pdo->prepare('SELECT id_admin, iplogin FROM admin WHERE username = ? LIMIT 1');
$admin->execute([$user]);
$account = $admin->fetch(PDO::FETCH_ASSOC);
if (!$account || (string) $adminnumber === '' || (string) $account['id_admin'] !== (string) $adminnumber
    || !fx_admin_ip_allowed($account['iplogin'], $_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lib/ad_boot.php';

$message = null;
$error = null;
$counts = [];
$held = [];
$settings = null;
try {
    $inbox = new FaoximaTelegramInbox($pdo, FaoximaTelegramInbox::botId((string) $APIKEY));
    $settings = FaoximaTelegramTransport::settings($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        fx_csrf_guard();
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $input = $_POST['proxy_url'] ?? '';
            if (!is_string($input)) {
                throw new InvalidArgumentException();
            }
            $entered = trim($input);
            $remove = isset($_POST['remove_proxy']);
            if ($remove && $entered !== '') {
                throw new InvalidArgumentException();
            }
            $proxy = $remove ? '' : ($entered === '' ? ($settings['proxy_url'] ?? '') : $entered);
            $polling = isset($_POST['polling']);
            FaoximaTelegramTransport::validateProxy($proxy);
            $inbox->install();
            $inbox->configure($proxy, $polling);
            header('Location: telegram.php?saved=1', true, 303);
            exit;
        }
        if ($action === 'test' && !empty($settings['proxy_url'])) {
            $started = hrtime(true);
            $result = FaoximaTelegramTransport::control((string) $APIKEY, 'getMe', [], 10, null, $settings['proxy_url']);
            $ms = (int) ((hrtime(true) - $started) / 1000000);
            if (($result['body']['ok'] ?? false) === true) {
                $message = "ارتباط از طریق پراکسی برقرار شد ({$ms} میلی‌ثانیه).";
            } else {
                $error = "آزمون اتصال ناموفق بود (HTTP {$result['code']}، cURL {$result['errno']}).";
            }
        }
    }
    if ($settings !== null) {
        $counts = $inbox->counts();
        $held = $inbox->held();
    }
} catch (InvalidArgumentException $exception) {
    $error = 'توکن ربات یا آدرس پراکسی معتبر نیست. برای دریافت با لانگ‌پولینگ، آدرس پراکسی الزامی است.';
} catch (Throwable $exception) {
    $error = 'دسترسی به تنظیمات یا صف تلگرام ناموفق بود. وضعیت دیتابیس و ماژول cURL را بررسی کنید.';
}
$status = match (true) {
    empty($settings['polling']) => 'وب‌هوک',
    (int) $settings['last_poll_ok'] >= time() - 60 => 'لانگ‌پولینگ متصل',
    ($settings['poll_state'] ?? '') === 'conflict' => 'تداخل با وب‌هوک یا دریافت‌کنندهٔ دیگر',
    ($settings['poll_state'] ?? '') === 'rate_limited' => 'محدودیت موقت تلگرام',
    ($settings['poll_state'] ?? '') === 'token_error' => 'خطای توکن',
    ($settings['poll_state'] ?? '') === 'unreachable' => 'ارتباط با تلگرام برقرار نیست',
    default => 'در انتظار سرویس دریافت',
};
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اتصال تلگرام | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="css/admin-extra.css?v=flat34">
    <script src="js/theme.js?v=flat50" defer></script>
</head>
<body>
<section id="container">
    <?php include __DIR__ . '/header.php'; ?>
    <section id="main-content">
        <div class="wrapper fx-page-settings">
            <h1>اتصال تلگرام</h1>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($message !== null || isset($_GET['saved'])): ?>
                <div class="alert alert-success" role="status"><?= htmlspecialchars($message ?? 'تنظیمات ذخیره شد.', ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="card">
                <form method="post" autocomplete="off">
                    <?= fx_csrf_field() ?>
                    <p><label for="telegram-proxy">آدرس پراکسی تلگرام</label></p>
                    <input id="telegram-proxy" name="proxy_url" type="password" dir="ltr" maxlength="1024"
                           autocomplete="new-password" placeholder="socks5h://127.0.0.1:1070">
                    <p>قالب‌های پشتیبانی‌شده <bdi>socks5h، socks5، http، https</bdi>. آدرس باید پورت داشته باشد.</p>
                    <?php if (!empty($settings['proxy_url'])): ?>
                        <p>پراکسی ذخیره شده است. برای نگه‌داشتن آن، فیلد آدرس را خالی بگذارید.</p>
                        <p><label><input type="checkbox" name="remove_proxy"> حذف پراکسی ذخیره‌شده</label></p>
                    <?php endif; ?>
                    <p><label><input type="checkbox" name="polling" <?= !empty($settings['polling']) ? 'checked' : '' ?>>
                        دریافت با لانگ‌پولینگ</label></p>
                    <p>لانگ‌پولینگ به دو سرویس خط فرمان نیاز دارد. هنگام تغییر حالت، وب‌هوک را طبق راهنمای نصب جدا کنید.</p>
                    <button type="submit" class="btn btn-primary" name="action" value="save">ذخیره</button>
                    <?php if (!empty($settings['proxy_url'])): ?>
                        <button type="submit" class="btn btn-outline" name="action" value="test">آزمون پراکسی ذخیره‌شده</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card">
                <h2>وضعیت دریافت</h2>
                <p><?= $status ?></p>
                <p><?= (int) ($counts['pending'] ?? 0) ?> در صف، <?= (int) ($counts['running'] ?? 0) ?> در حال پردازش،
                    <?= (int) ($counts['held'] ?? 0) ?> نیازمند بررسی</p>
                <?php if ($held !== []): ?>
                    <p>شناسهٔ ردیف‌های نیازمند بررسی (حداکثر ۱۰۰ مورد)</p>
                    <p><bdi><?= implode('، ', array_map('intval', array_column($held, 'seq'))) ?></bdi></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</section>
</body>
</html>
