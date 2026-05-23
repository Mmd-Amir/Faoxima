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


$SETTING_GROUPS = [
    'general' => [
        'title' => 'تنظیمات عمومی ربات',
        'icon'  => 'sliders',
        'fields' => [
            ['type' => 'toggle', 'col' => 'Bot_Status',        'label' => 'وضعیت ربات (روشن/خاموش)',         'on' => 'botstatuson',         'off' => 'botstatusoff'],
            ['type' => 'toggle', 'col' => 'roll_Status',       'label' => 'قوانین استفاده',                  'on' => 'rolleon',             'off' => 'rolleoff'],
            ['type' => 'toggle', 'col' => 'get_number',        'label' => 'الزام احراز شماره موبایل',         'on' => 'onAuthenticationphone','off' => 'offAuthenticationphone'],
            ['type' => 'toggle', 'col' => 'iran_number',       'label' => 'فقط شماره‌های ایرانی',             'on' => 'onAuthenticationiran','off' => 'offAuthenticationiran'],
            ['type' => 'toggle', 'col' => 'NotUser',           'label' => 'مسدودسازی در صورت نقض قانون',      'on' => 'onnotuser',           'off' => 'offnotuser'],
            ['type' => 'toggle', 'col' => 'statusnewuser',     'label' => 'گزارش کاربر جدید به کانال',        'on' => 'onnewuser',           'off' => 'offnewuser'],
            ['type' => 'text',   'col' => 'Channel_Report',    'label' => 'آیدی کانال گزارش',                'placeholder' => '@channel یا -100…'],
            ['type' => 'toggle', 'col' => 'verifystart',       'label' => 'تایید عضویت در کانال هنگام شروع',  'on' => 'onverify',            'off' => 'offverify'],
            ['type' => 'toggle', 'col' => 'verifybucodeuser',  'label' => 'تایید با کد یکبارمصرف برای خرید',  'on' => 'onverify',            'off' => 'offverify'],
            ['type' => 'toggle', 'col' => 'showcard',          'label' => 'نمایش لیست محصولات',               'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'statuscategory',    'label' => 'دسته‌بندی محصولات',                'on' => 'oncategory',          'off' => 'offcategory'],
            ['type' => 'toggle', 'col' => 'statuscategorygenral','label'=>'دسته‌بندی عمومی',                  'on' => 'oncategorys',         'off' => 'offcategorys'],
            ['type' => 'toggle', 'col' => 'inlinebtnmain',     'label' => 'استفاده از دکمه inline در منو اصلی','on' => 'oninline',          'off' => 'offinline'],
            ['type' => 'toggle', 'col' => 'statusnamecustom',  'label' => 'نام دلخواه برای سرویس',            'on' => 'onnamecustom',        'off' => 'offnamecustom'],
            ['type' => 'toggle', 'col' => 'bulkbuy',           'label' => 'خرید چندتایی (Bulk)',              'on' => 'onbulk',              'off' => 'offbulk'],
        ],
    ],

    'support' => [
        'title' => 'پشتیبانی',
        'icon'  => 'message',
        'fields' => [
            ['type' => 'toggle', 'col' => 'statussupportpv',  'label' => 'پشتیبانی از طریق پی‌وی',          'on' => 'onpvsupport',         'off' => 'offpvsupport'],
            ['type' => 'text',   'col' => 'id_support',       'label' => 'آیدی عددی ادمین پشتیبانی',         'placeholder' => '123456789',
             'hint'  => 'وقتی «پشتیبانی پی‌وی» روشن است، پیام‌های پشتیبانی به این آیدی تلگرام ارسال می‌شوند. عدد آیدی (نه یوزرنیم) را وارد کنید.'],
            ['type' => 'toggle', 'col' => 'categoryhelp',     'label' => 'دسته‌بندی متن‌های آموزش',          'on' => '1',                   'off' => '0'],
        ],
    ],

    'agent' => [
        'title' => 'نمایندگی و زیرمجموعه',
        'icon'  => 'users',
        'fields' => [
            ['type' => 'toggle', 'col' => 'affiliatesstatus',  'label' => 'فعال‌سازی زیرمجموعه‌گیری',        'on' => 'onaffiliates',        'off' => 'offaffiliates'],
            ['type' => 'toggle', 'col' => 'statusagentrequest','label' => 'پذیرش درخواست نمایندگی',           'on' => 'onrequestagent',      'off' => 'offrequestagent'],
            ['type' => 'number', 'col' => 'affiliatespercentage','label'=>'درصد پورسانت زیرمجموعه (٪)',       'placeholder' => '0'],
            ['type' => 'number', 'col' => 'agentreqprice',     'label' => 'حداقل پرداختی ثبت درخواست نمایندگی (تومان)', 'placeholder' => '0'],
            ['type' => 'toggle', 'col' => 'wheelagent',        'label' => 'گردونه شانس برای نماینده',          'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'Lotteryagent',      'label' => 'قرعه‌کشی برای نماینده',             'on' => '1',                   'off' => '0'],
        ],
    ],

    'wheel' => [
        'title' => 'گردونه شانس و امتیاز',
        'icon'  => 'gift',
        'fields' => [
            ['type' => 'toggle', 'col' => 'wheelـluck',        'label' => 'فعال‌سازی گردونه شانس',           'on' => '1',                   'off' => '0'],
            ['type' => 'number', 'col' => 'wheelـluck_price',  'label' => 'قیمت هر چرخش (تومان)',             'placeholder' => '0'],
            ['type' => 'toggle', 'col' => 'statusfirstwheel',  'label' => 'یک‌بار گردونه رایگان (کاربر جدید)','on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'scorestatus',       'label' => 'سیستم امتیاز',                     'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'Dice',              'label' => 'تاس شانس',                         'on' => '1',                   'off' => '0'],
        ],
    ],

    'service' => [
        'title' => 'تنظیمات سرویس',
        'icon'  => 'package',
        'fields' => [
            ['type' => 'number', 'col' => 'limit_usertest_all','label' => 'محدودیت اکانت تست هر کاربر',      'placeholder' => '1'],
            ['type' => 'number', 'col' => 'volumewarn',        'label' => 'هشدار اتمام حجم در (گیگ)',         'placeholder' => '2'],
            ['type' => 'number', 'col' => 'daywarn',           'label' => 'هشدار اتمام زمان در (روز)',        'placeholder' => '2'],
            ['type' => 'number', 'col' => 'removedayc',        'label' => 'حذف سرویس منقضی بعد از (روز)',     'placeholder' => '1'],
            ['type' => 'number', 'col' => 'on_hold_day',       'label' => 'نگه‌داری سرویس قبل از حذف (روز)',  'placeholder' => '4'],
            ['type' => 'number', 'col' => 'cronvolumere',      'label' => 'هر چند ساعت کرون حجم',             'placeholder' => '5'],
            ['type' => 'number', 'col' => 'timeauto_not_verify','label'=>'حذف خودکار سفارش تأییدنشده (دقیقه)','placeholder' => '4'],
            ['type' => 'toggle', 'col' => 'statuslimitchangeloc','label'=>'محدودیت تغییر لوکیشن',             'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'Debtsettlement',    'label' => 'تسویه بدهی (debt settlement)',     'on' => '1',                   'off' => '0'],
        ],
    ],

    'ux' => [
        'title' => 'تجربه کاربری',
        'icon'  => 'sparkles',
        'fields' => [
            ['type' => 'toggle', 'col' => 'statusnoteforf',   'label' => 'الزام نوشتن یادداشت در سفارش',     'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'statuscopycart',   'label' => 'دکمه کپی شماره کارت',              'on' => '1',                   'off' => '0'],
            ['type' => 'toggle', 'col' => 'linkappstatus',    'label' => 'نمایش لینک نصب اپلیکیشن‌ها',       'on' => '1',                   'off' => '0'],
        ],
    ],

    'antispam' => [
        'title' => 'ضد اسپم و امنیت',
        'icon'  => 'shield',
        'fields' => [
            ['type' => 'toggle', 'col' => 'antispam_status',     'label' => 'فعال‌سازی ضد اسپم',             'on' => '1',                   'off' => '0'],
            ['type' => 'number', 'col' => 'antispam_msg_count', 'label' => 'حداکثر پیام مجاز در بازه',       'placeholder' => '5'],
            ['type' => 'number', 'col' => 'antispam_seconds',    'label' => 'بازه زمانی (ثانیه)',            'placeholder' => '10'],
            ['type' => 'number', 'col' => 'antispam_mute_seconds','label'=>'مدت سکوت ربات (ثانیه)',          'placeholder' => '60'],
            ['type' => 'toggle', 'col' => 'premium_emoji_status','label' => 'ایموجی پریمیوم تلگرام',        'on' => '1',                   'off' => '0'],
        ],
    ],
];


$settingRow = [];
try {
    $stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (is_array($row)) $settingRow = $row;
} catch (\Throwable $e) {
    error_log('[panel/settings] load failed: ' . $e->getMessage());
}


$savedCount = 0;
$pvWarning  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    foreach ($SETTING_GROUPS as $group) {
        foreach ($group['fields'] as $f) {
            $col = $f['col'];
            $cur = $settingRow[$col] ?? null;
            if ($f['type'] === 'toggle') {
                $new = isset($_POST['f_' . $col]) ? $f['on'] : $f['off'];
                if ((string)$cur !== (string)$new) {
                    try {
                        $sanCol = preg_replace('/[^A-Za-z0-9_ـ]/u', '', $col);
                        $upd = $pdo->prepare("UPDATE setting SET `{$sanCol}` = :v");
                        $upd->bindValue(':v', $new, PDO::PARAM_STR);
                        $upd->execute();
                        $savedCount++;
                    } catch (\Throwable $e) {
                        error_log('[panel/settings] toggle ' . $col . ' failed: ' . $e->getMessage());
                    }
                }
            } else {
                if (array_key_exists('f_' . $col, $_POST)) {
                    $new = (string)$_POST['f_' . $col];


                    if ($f['type'] === 'number') {
                        if ($new !== '' && !is_numeric($new)) {
                            error_log('[panel/settings] non-numeric value for ' . $col . ': ' . $new);
                            continue;
                        }
                        if ($new !== '' && (float)$new < 0) {
                            $new = '0';
                        }

                        if ($col === 'affiliatespercentage' && (float)$new > 100) $new = '100';
                    }

                    if ($col === 'id_support' && $new !== '' && !preg_match('/^-?\d{1,20}$/', $new)) {
                        error_log('[panel/settings] id_support rejected (not numeric): ' . $new);
                        continue;
                    }

                    if ($f['type'] === 'text' && mb_strlen($new) > 500) {
                        $new = mb_substr($new, 0, 500);
                    }

                    if ((string)$cur !== $new) {
                        try {
                            $sanCol = preg_replace('/[^A-Za-z0-9_ـ]/u', '', $col);
                            $upd = $pdo->prepare("UPDATE setting SET `{$sanCol}` = :v");
                            $upd->bindValue(':v', $new, PDO::PARAM_STR);
                            $upd->execute();
                            $savedCount++;
                        } catch (\Throwable $e) {
                            error_log('[panel/settings] text ' . $col . ' failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }


    $pvOn  = isset($_POST['f_statussupportpv']);
    $pvId  = trim((string)($_POST['f_id_support'] ?? ''));
    if ($pvOn && $pvId === '') {
        $pvWarning = '1';
    }

    header('Location: settings.php?saved=' . $savedCount . ($pvWarning ? '&pvwarn=1' : ''));
    exit;
}

$showSaved = isset($_GET['saved']);
$savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$pvWarn    = isset($_GET['pvwarn']);

function faoxima_is_toggle_on($cur, $on, $off) {
    if ($cur === null) return false;
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
    <title>تنظیمات ربات | پنل فاکسیما</title>
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
                        <?php echo icon('sliders', 'svg-icon svg-lg'); ?>
                        تنظیمات ربات
                    </div>
                    <div class="page-head__sub">پیکربندی‌های اصلی ربات — هر فیلد دقیقاً همان‌چیزی است که در منوی ادمین تلگرام تنظیم می‌کنید</div>
                </div>
            </div>

            <?php if ($showSaved): ?>
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span>
                        <?php if ($savedNum > 0): ?>
                            تغییرات ذخیره شد. (<?php echo $savedNum; ?> فیلد به‌روزرسانی شد)
                        <?php else: ?>
                            هیچ تغییری انجام نشد.
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($pvWarn): ?>
                <div class="alert alert-warning">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><b>هشدار:</b> «پشتیبانی پی‌وی» روشن است اما <b>آیدی عددی ادمین پشتیبانی</b> خالی است. پیام‌های پشتیبانی به جایی ارسال نمی‌شوند تا این فیلد را پر کنید.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="settings.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_save" value="1">

                <div class="setting-grid">
                    <?php foreach ($SETTING_GROUPS as $gKey => $group): ?>
                        <div class="card">
                            <div class="card__head">
                                <div class="card__title">
                                    <?php echo icon($group['icon'], 'svg-icon svg-md'); ?>
                                    <span><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <?php foreach ($group['fields'] as $f):
                                $col = $f['col'];
                                $val = $settingRow[$col] ?? null;
                                $idAttr = 'f_' . htmlspecialchars($col, ENT_QUOTES);
                            ?>
                                <div class="setting-row">
                                    <label for="<?php echo $idAttr; ?>" class="setting-row__label">
                                        <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($f['hint'])): ?>
                                            <small class="setting-row__hint"><?php echo htmlspecialchars($f['hint'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </label>
                                    <div class="setting-row__control">
                                        <?php if ($f['type'] === 'toggle'): ?>
                                            <label class="switch" title="<?php echo htmlspecialchars($col); ?>">
                                                <input type="checkbox" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                    <?php echo faoxima_is_toggle_on($val, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                                <span class="switch__slot"></span>
                                            </label>
                                        <?php elseif ($f['type'] === 'number'): ?>
                                            <input type="number" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>">
                                        <?php else: ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?>"
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
                        <span>ذخیره تغییرات</span>
                    </button>
                </div>
            </form>

        </div>
    </section>
</section>

</body>
</html>


