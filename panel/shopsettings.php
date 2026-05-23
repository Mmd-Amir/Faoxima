<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
if (extension_loaded('zlib') && empty(ini_get('zlib.output_compression'))) {
    @ini_set('zlib.output_compression', '1');
}

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incoming = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $incoming)) {
        http_response_code(403);
        exit('درخواست نامعتبر — توکن CSRF اشتباه است');
    }
}


$tableMissing = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'shopSetting'");
    if ($check && $check->fetchColumn() === false) $tableMissing = true;
} catch (\Throwable $e) {
    $tableMissing = true;
}


$SHOP_GROUPS = [
    'capabilities' => [
        'title' => 'قابلیت‌های فروشگاه',
        'icon'  => 'package',
        'fields' => [
            ['type' => 'toggle', 'name' => 'statusextra',        'label' => 'فعال‌سازی خرید حجم اضافه',          'on' => 'onextra',         'off' => 'offextra'],
            ['type' => 'toggle', 'name' => 'statustimeextra',    'label' => 'فعال‌سازی خرید زمان اضافه',         'on' => 'ontimeextraa',    'off' => 'offtimeextraa'],
            ['type' => 'toggle', 'name' => 'statusdirectpabuy',  'label' => 'پرداخت مستقیم (بدون شارژ کیف پول)','on' => 'ondirectbuy',     'off' => 'offdirectbuy'],
            ['type' => 'toggle', 'name' => 'statusdisorder',     'label' => 'حالت غیرفعال‌سازی فروش (Disorder)', 'on' => 'ondisorder',      'off' => 'offdisorder'],
            ['type' => 'toggle', 'name' => 'statuschangeservice','label' => 'اجازه‌ی تغییر سرویس',                'on' => 'onstatus',        'off' => 'offstatus'],
            ['type' => 'toggle', 'name' => 'statusshowprice',    'label' => 'نمایش قیمت روی محصولات',            'on' => 'onshowprice',     'off' => 'offshowprice'],
            ['type' => 'toggle', 'name' => 'configshow',         'label' => 'نمایش کانفیگ به کاربر',              'on' => 'onconfig',        'off' => 'offconfig'],
            ['type' => 'toggle', 'name' => 'backserviecstatus',  'label' => 'دکمه بازگشت در منوی سرویس',          'on' => 'on',              'off' => 'off'],
        ],
    ],

    'bulk' => [
        'title' => 'خرید عمده',
        'icon'  => 'grid',
        'fields' => [
            ['type' => 'number', 'name' => 'minbalancebuybulk', 'label' => 'حداقل موجودی برای خرید عمده (تومان)', 'placeholder' => '0'],
        ],
    ],

    'custom' => [
        'title' => 'مقادیر دلخواه (حجم/زمان)',
        'icon'  => 'sliders',
        'fields' => [
            ['type' => 'number', 'name' => 'customvolmef',      'label' => 'قیمت هر گیگ دلخواه (کاربر عادی)',    'placeholder' => '4000'],
            ['type' => 'number', 'name' => 'customvolmen',      'label' => 'قیمت هر گیگ دلخواه (نماینده)',       'placeholder' => '4000'],
            ['type' => 'number', 'name' => 'customvolmen2',     'label' => 'قیمت هر گیگ دلخواه (نماینده+)',      'placeholder' => '4000'],
            ['type' => 'number', 'name' => 'customtimepricef',  'label' => 'قیمت هر روز دلخواه (کاربر عادی)',    'placeholder' => '4000'],
            ['type' => 'number', 'name' => 'customtimepricen',  'label' => 'قیمت هر روز دلخواه (نماینده)',       'placeholder' => '4000'],
            ['type' => 'number', 'name' => 'customtimepricen2', 'label' => 'قیمت هر روز دلخواه (نماینده+)',      'placeholder' => '4000'],
        ],
    ],
];


$shopRows = [];
if (!$tableMissing) {
    try {
        $r = $pdo->query("SELECT Namevalue, value FROM shopSetting");
        if ($r) {
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $shopRows[(string)$row['Namevalue']] = (string)$row['value'];
            }
        }
    } catch (\Throwable $e) {
        error_log('[panel/shopsettings] load failed: ' . $e->getMessage());
    }
}


$savedCount = 0;
if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    foreach ($SHOP_GROUPS as $group) {
        foreach ($group['fields'] as $f) {
            $key = $f['name'];
            $cur = $shopRows[$key] ?? '';

            if ($f['type'] === 'toggle') {
                $new = isset($_POST['f_' . $key]) ? $f['on'] : $f['off'];
            } else {
                if (!array_key_exists('f_' . $key, $_POST)) continue;
                $new = (string)$_POST['f_' . $key];
            }

            if ((string)$cur === (string)$new) continue;
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO shopSetting (Namevalue, value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)"
                );
                $stmt->execute([':k' => $key, ':v' => $new]);
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/shopsettings] save ' . $key . ' failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: shopsettings.php?saved=' . $savedCount);
    exit;
}

$showSaved = isset($_GET['saved']);
$savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

function faoxima_shop_is_on($cur, $on, $off) {
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>قابلیت‌های فروشگاه | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-extra.css">
    <script src="js/theme.js" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('package', 'svg-icon svg-lg'); ?>
                        قابلیت‌های فروشگاه
                    </div>
                    <div class="page-head__sub">قابلیت‌های نمایش و خرید فروشگاه — مطابق منوی «🛒 وضعیت قابلیت‌های فروشگاه» در ربات</div>
                </div>
            </div>

            <?php if ($tableMissing): ?>
                <div class="alert alert-warning">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span>جدول <code>shopSetting</code> در دیتابیس هنوز ساخته نشده. لطفاً یک‌بار از این صفحه خارج شده و دوباره وارد شوید — جدول به‌صورت خودکار ساخته می‌شود.</span>
                </div>
            <?php endif; ?>

            <?php if ($showSaved): ?>
                <div class="alert <?php echo $savedNum > 0 ? 'alert-success' : 'alert-warning'; ?>">
                    <?php echo icon($savedNum > 0 ? 'circle-check' : 'circle-exclamation', 'svg-icon'); ?>
                    <span>
                        <?php echo $savedNum > 0 ? ($savedNum . ' تغییر ذخیره شد.') : 'هیچ تغییری انجام نشد.'; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!$tableMissing): ?>
                <form method="POST" action="shopsettings.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_save" value="1">
                    <div class="setting-grid">
                        <?php foreach ($SHOP_GROUPS as $gKey => $group): ?>
                            <div class="card">
                                <div class="card__head">
                                    <div class="card__title">
                                        <?php echo icon($group['icon'], 'svg-icon svg-md'); ?>
                                        <span><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <?php foreach ($group['fields'] as $f):
                                    $key = $f['name'];
                                    $cur = $shopRows[$key] ?? '';
                                    $idAttr = 'f_' . htmlspecialchars($key, ENT_QUOTES);
                                ?>
                                    <div class="setting-row">
                                        <label for="<?php echo $idAttr; ?>" class="setting-row__label">
                                            <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                        <div class="setting-row__control">
                                            <?php if ($f['type'] === 'toggle'): ?>
                                                <label class="switch" title="<?php echo htmlspecialchars($key); ?>">
                                                    <input type="checkbox" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                        <?php echo faoxima_shop_is_on($cur, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                                    <span class="switch__slot"></span>
                                                </label>
                                            <?php else: ?>
                                                <input type="number" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                    value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                    placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                    style="direction:ltr; text-align:left;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="save-bar">
                        <button type="reset" class="btn btn-outline">
                            <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?>
                            <span>بازنشانی</span>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo icon('check', 'svg-icon svg-sm'); ?>
                            <span>ذخیره</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </section>
</section>

</body>
</html>


