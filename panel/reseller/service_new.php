<?php

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

// Panel provisioning stack (Telegram bot removed).
require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/../../panels.php';
$ManagePanel = new ManagePanel();

$products = reseller_allowed_products($reseller);

// Optional admin-imposed service-count limit.
$limitServices = trim((string) ($reseller['limit_services'] ?? ''));
$activeCount = reseller_count_active_services($rid);
$limitReached = ($limitServices !== '' && is_numeric($limitServices) && $activeCount >= (int) $limitServices);

// Available panels (for products whose Location is "/all").
$panelRows = $pdo->query("SELECT name_panel, status FROM marzban_panel ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$panels = array_values(array_filter(array_map(static function ($p) {
    return (string) $p['name_panel'];
}, $panelRows), static function ($n) {
    return $n !== '';
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    $code = (string) ($_POST['product'] ?? '');
    $panelChoice = (string) ($_POST['panel'] ?? '');
    $usernameC = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_POST['username'] ?? ''));
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $customerChat = preg_replace('/[^0-9]/', '', (string) ($_POST['customer_chat'] ?? ''));

    $product = null;
    foreach ($products as $p) {
        if ((string) $p['code_product'] === $code) {
            $product = $p;
            break;
        }
    }

    $err = '';
    if ($limitReached) {
        $err = 'به سقف مجاز ساخت سرویس رسیده‌اید.';
    } elseif (!$product) {
        $err = 'محصول انتخابی معتبر نیست یا برای فروش نماینده فعال نشده است.';
    } elseif (strlen($usernameC) < 3) {
        $err = 'نام کاربری باید حداقل ۳ کاراکتر و فقط شامل حروف/اعداد انگلیسی باشد.';
    }

    $panelName = '';
    if ($err === '') {
        $loc = trim((string) ($product['Location'] ?? ''));
        if ($loc !== '' && $loc !== '/all') {
            $panelName = $loc;
        } elseif ($panelChoice !== '' && in_array($panelChoice, $panels, true)) {
            $panelName = $panelChoice;
        } elseif (count($panels) === 1) {
            $panelName = $panels[0];
        } else {
            $err = 'لطفاً پنل مقصد را انتخاب کنید.';
        }
    }

    $price = $product ? reseller_product_price($product) : 0;
    if ($err === '' && (int) $reseller['balance'] < $price) {
        $err = 'موجودی کیف پول برای ساخت این سرویس کافی نیست.';
    }

    if ($err !== '') {
        reseller_flash_set('error', $err);
        header('Location: service_new.php');
        exit;
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
        error_log('[reseller service_new] createUser: ' . $e->getMessage());
        $out = ['status' => 'Unsuccessful', 'msg' => 'خطای داخلی در ساخت سرویس'];
    }

    if (!is_array($out) || empty($out['username']) || (($out['status'] ?? '') !== 'successful' && empty($out['subscription_url']))) {
        $msg = is_array($out) && isset($out['msg'])
            ? (is_string($out['msg']) ? $out['msg'] : json_encode($out['msg'], JSON_UNESCAPED_UNICODE))
            : 'نامشخص';
        reseller_flash_set('error', 'ساخت سرویس در پنل ناموفق بود: ' . $msg);
        header('Location: service_new.php');
        exit;
    }

    // Charge the wallet only after the panel confirmed the service.
    $charge = reseller_wallet_apply($rid, 'purchase', -$price, 'خرید سرویس ' . ($product['name_product'] ?? $product['code_product']), $usernameC);
    if (!$charge['ok']) {
        reseller_flash_set('error', 'سرویس ساخته شد اما کسر از کیف پول ناموفق بود؛ با مدیر تماس بگیرید.');
        header('Location: service_new.php');
        exit;
    }

    $subToken = bin2hex(random_bytes(16));
    $configs = $out['configs'] ?? [];
    $ins = $pdo->prepare(
        "INSERT INTO reseller_service
            (reseller_id, product_code, panel_name, username, uuid, sub_token, sub_link, customer_name, customer_chat_id, volume_gb, days, price, status, created_at, expire_at)
         VALUES
            (:rid, :code, :panel, :uname, :uuid, :tok, :sub, :cname, :cchat, :vol, :days, :price, 'active', :ts, :exp)"
    );
    $ins->execute([
        ':rid'   => $rid,
        ':code'  => (string) $product['code_product'],
        ':panel' => $panelName,
        ':uname' => (string) $out['username'],
        ':uuid'  => is_array($configs) ? json_encode($configs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $configs,
        ':tok'   => $subToken,
        ':sub'   => (string) ($out['subscription_url'] ?? ''),
        ':cname' => mb_substr($customerName, 0, 190),
        ':cchat' => $customerChat,
        ':vol'   => (string) $volumeGb,
        ':days'  => (string) $days,
        ':price' => $price,
        ':ts'    => (string) time(),
        ':exp'   => $expire > 0 ? (string) $expire : '',
    ]);

    reseller_flash_set('success', 'سرویس با موفقیت ساخته شد و از کیف پول شما کسر گردید.');
    header('Location: subscription.php?token=' . $subToken);
    exit;
}

reseller_layout_head('ساخت سرویس', 'new', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('cart-shopping', 'svg-icon svg-lg'); ?> ساخت سرویس جدید</div>
        <div class="page-head__sub">از محصولات تعیین‌شده توسط مدیر سرویس بسازید</div>
    </div>
</div>

<?php reseller_flash_render(); ?>

<?php if ($limitReached): ?>
    <div class="alert alert-error"><?php echo icon('circle-exclamation', 'svg-icon'); ?><span>به سقف مجاز ساخت سرویس (<?php echo (int) $limitServices; ?>) رسیده‌اید.</span></div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="alert alert-info">
        <?php echo icon('circle-info', 'svg-icon'); ?>
        <span>هنوز محصولی برای فروش نماینده توسط مدیر فعال نشده است.</span>
    </div>
<?php else: ?>
<div class="card">
    <div class="card__head"><div class="card__title">مشخصات سرویس</div></div>
    <div style="padding:16px;">
        <form method="post" action="service_new.php">
            <?php echo reseller_csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">محصول</label>
                <select name="product" id="productSelect" class="form-control" required>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $price = reseller_product_price($p);
                        $vol = trim((string) ($p['Volume_constraint'] ?? ''));
                        $dys = trim((string) ($p['Service_time'] ?? ''));
                        $loc = trim((string) ($p['Location'] ?? ''));
                        $label = ($p['name_product'] ?? $p['code_product']) . ' — ' . number_format($price) . ' تومان';
                        if ($vol !== '' || $dys !== '') $label .= " ({$vol}GB / {$dys} روز)";
                        ?>
                        <option value="<?php echo reseller_e($p['code_product']); ?>" data-loc="<?php echo reseller_e($loc); ?>">
                            <?php echo reseller_e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="panelGroup">
                <label class="form-label">پنل مقصد (برای محصولات همه‌ی پنل‌ها)</label>
                <select name="panel" class="form-control">
                    <option value="">— انتخاب خودکار —</option>
                    <?php foreach ($panels as $pn): ?>
                        <option value="<?php echo reseller_e($pn); ?>"><?php echo reseller_e($pn); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">نام کاربری سرویس (انگلیسی)</label>
                <input type="text" name="username" class="form-control" placeholder="مثلاً ali_1234" pattern="[A-Za-z0-9_]{3,}" required>
            </div>

            <div class="form-row" style="display:flex; gap:14px; flex-wrap:wrap;">
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label class="form-label">نام مشتری (اختیاری)</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="نام مشتری">
                </div>
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label class="form-label">شناسه / کد مشتری (اختیاری)</label>
                    <input type="text" name="customer_chat" class="form-control" placeholder="کد مشتری یا شماره تماس">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-2" <?php echo $limitReached ? 'disabled' : ''; ?>>
                <?php echo icon('plus', 'svg-icon svg-sm'); ?> ساخت سرویس و کسر از کیف پول
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('productSelect');
    var pg = document.getElementById('panelGroup');
    function refresh() {
        var opt = sel.options[sel.selectedIndex];
        var loc = opt ? (opt.getAttribute('data-loc') || '') : '';
        pg.style.display = (loc === '' || loc === '/all') ? '' : 'none';
    }
    sel.addEventListener('change', refresh);
    refresh();
})();
</script>
<?php endif; ?>

<?php
reseller_layout_foot();
