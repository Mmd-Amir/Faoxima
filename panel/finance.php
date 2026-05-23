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


$FIN_GROUPS = [
    'cart' => [
        'title' => 'کارت به کارت',
        'icon'  => 'wallet',
        'fields' => [
            ['type' => 'toggle', 'name' => 'Cartstatus',          'label' => 'فعال‌سازی کارت به کارت',     'on' => 'oncard',        'off' => 'offcard'],
            ['type' => 'toggle', 'name' => 'Cartstatuspv',        'label' => 'دریافت رسید در پی‌وی',       'on' => 'oncardpv',      'off' => 'offcardpv'],
            ['type' => 'text',   'name' => 'cardnumber',          'label' => 'شماره کارت',                  'placeholder' => '6037-XXXX-XXXX-XXXX'],
            ['type' => 'text',   'name' => 'namecard',            'label' => 'نام صاحب کارت',               'placeholder' => 'علی محمدی'],
            ['type' => 'text',   'name' => 'CartDirect',          'label' => 'پل ارتباطی کارت',             'placeholder' => '@username'],
            ['type' => 'toggle', 'name' => 'statuscardautoconfirm','label' => 'تایید خودکار رسید کارت',     'on' => 'onautoconfirm', 'off' => 'offautoconfirm'],
            ['type' => 'toggle', 'name' => 'autoconfirmcart',     'label' => 'تایید خودکار سفارش (بدون تایید رسید)', 'on' => 'onauto', 'off' => 'offauto'],
            ['type' => 'toggle', 'name' => 'checkpaycartfirst',   'label' => 'بررسی پرداخت اولیه',          'on' => 'onpayverify',   'off' => 'offpayverify'],
            ['type' => 'number', 'name' => 'minbalancecart',      'label' => 'حداقل مبلغ شارژ (تومان)',     'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalancecart',      'label' => 'حداکثر مبلغ شارژ (تومان)',    'placeholder' => '1000000'],
            ['type' => 'number', 'name' => 'helpcart',            'label' => 'توضیحات راهنما (شناسه پیام)', 'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackcart',       'label' => 'درصد کش‌بک (٪)',              'placeholder' => '0'],
        ],
    ],

    'tron' => [
        'title' => 'پنل ترنادو (Ternado API)',
        'icon'  => 'coins',
        'fields' => [
            ['type' => 'toggle', 'name' => 'statustarnado',       'label' => 'فعال‌سازی ترنادو',            'on' => 'onternado',     'off' => 'offternado'],
            ['type' => 'text',   'name' => 'urlpaymenttron',      'label' => 'آدرس API ترنادو',              'placeholder' => 'https://bot.tronado.cloud/api/v1/...'],
            ['type' => 'secret', 'name' => 'apiternado',          'label' => 'API Key ترنادو',              'placeholder' => 'API key'],
            ['type' => 'secret', 'name' => 'marchent_tronseller', 'label' => 'مرچنت TronSeller',            'placeholder' => 'کد مرچنت'],
        ],
    ],

    'offline_crypto' => [
        'title' => '💵 درگاه ارزی آفلاین (کیف پول ترون / USDT / تتر / ...)',
        'icon'  => 'wallet',


        'fields' => [
            ['type' => 'toggle', 'name' => 'digistatus',             'label' => 'فعال‌سازی درگاه ارزی آفلاین',     'on' => 'ondigi', 'off' => 'offdigi'],
            ['type' => 'textbot','name' => 'textnowpaymenttron',     'label' => '🗂 نام نمایشی درگاه (به کاربر نشان داده می‌شود)', 'placeholder' => 'پرداخت ارز دیجیتال آفلاین'],
            ['type' => 'text',   'name' => 'walletaddress',          'label' => '💼 آدرس کیف پول (TRX / USDT-TRC20 / تتر / ...)', 'placeholder' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
            ['type' => 'number', 'name' => 'minbalancedigitaltron',  'label' => '⬇️ حداقل مبلغ واریزی (تومان)',  'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalancedigitaltron',  'label' => '⬆️ حداکثر مبلغ واریزی (تومان)', 'placeholder' => '1000000'],
            ['type' => 'number', 'name' => 'helpofflinearze',        'label' => '📚 شناسه پیام راهنما (۲ = بدون راهنما)', 'placeholder' => '2'],
        ],
    ],

    'rial_gateways' => [
        'title' => 'درگاه‌های ریالی',
        'icon'  => 'dollar-sign',
        'fields' => [

            ['type' => 'toggle', 'name' => 'zarinpalstatus',      'label' => 'فعال‌سازی زرین‌پال',           'on' => 'onzarinpal',    'off' => 'offzarinpal'],
            ['type' => 'secret', 'name' => 'merchant_zarinpal',   'label' => 'مرچنت زرین‌پال',               'placeholder' => 'مرچنت زرین‌پال'],
            ['type' => 'number', 'name' => 'helpzarinpal',        'label' => 'راهنما زرین‌پال (شناسه پیام)', 'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackzarinpal',   'label' => 'کش‌بک زرین‌پال (٪)',           'placeholder' => '0'],


            ['type' => 'toggle', 'name' => 'zarinpeystatus',      'label' => 'فعال‌سازی زرین‌پی',            'on' => 'onzarinpey',    'off' => 'offzarinpey'],
            ['type' => 'secret', 'name' => 'token_zarinpey',      'label' => 'توکن زرین‌پی',                  'placeholder' => 'توکن'],
            ['type' => 'number', 'name' => 'helpzarinpey',        'label' => 'راهنما زرین‌پی (شناسه پیام)',  'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackzarinpey',   'label' => 'کش‌بک زرین‌پی (٪)',            'placeholder' => '0'],


            ['type' => 'toggle', 'name' => 'statusaqayepardakht', 'label' => 'فعال‌سازی آقای پرداخت',         'on' => 'onaqayepardakht','off' => 'offaqayepardakht'],
            ['type' => 'secret', 'name' => 'merchant_id_aqayepardakht', 'label' => 'مرچنت آقای پرداخت',      'placeholder' => 'مرچنت'],
            ['type' => 'number', 'name' => 'helpaqayepardakht',   'label' => 'راهنما (شناسه پیام)',          'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackaqaypardokht','label' => 'کش‌بک آقای پرداخت (٪)',        'placeholder' => '0'],


            ['type' => 'toggle', 'name' => 'statusiranpay3',      'label' => 'فعال‌سازی IRanpay۳',           'on' => 'oniranpay3',    'off' => 'offiranpay3'],
            ['type' => 'secret', 'name' => 'apiiranpay',          'label' => 'API IRanpay',                  'placeholder' => 'API key'],
            ['type' => 'number', 'name' => 'chashbackiranpay1',   'label' => 'کش‌بک IRanpay۱ (٪)',           'placeholder' => '0'],
            ['type' => 'number', 'name' => 'chashbackiranpay2',   'label' => 'کش‌بک IRanpay۲ (٪)',           'placeholder' => '0'],
            ['type' => 'number', 'name' => 'chashbackiranpay3',   'label' => 'کش‌بک IRanpay۳ (٪)',           'placeholder' => '0'],
        ],
    ],

    'crypto_gateways' => [
        'title' => 'درگاه‌های ارزی / کریپتو',
        'icon'  => 'globe',
        'fields' => [

            ['type' => 'toggle', 'name' => 'nowpaymentstatus',    'label' => 'فعال‌سازی NowPayment',          'on' => 'onnowpayment',  'off' => 'offnowpayment'],
            ['type' => 'secret', 'name' => 'api_nowpayment',      'label' => 'API NowPayment',                'placeholder' => 'API key'],
            ['type' => 'secret', 'name' => 'nowpayment_ipn_secret','label' => 'IPN Secret NowPayment',        'placeholder' => 'IPN secret (اختیاری ولی توصیه‌شده)'],
            ['type' => 'number', 'name' => 'helpnowpayment',      'label' => 'راهنما (شناسه پیام)',          'placeholder' => '2'],
            ['type' => 'number', 'name' => 'cashbacknowpayment',  'label' => 'کش‌بک (٪)',                     'placeholder' => '0'],
            ['type' => 'number', 'name' => 'minbalancenowpayment','label' => 'حداقل مبلغ (تومان)',           'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalancenowpayment','label' => 'حداکثر مبلغ (تومان)',          'placeholder' => '1000000'],


            ['type' => 'secret', 'name' => 'api_plisio',          'label' => 'API Plisio',                    'placeholder' => 'Plisio secret key'],
            ['type' => 'number', 'name' => 'helpplisio',          'label' => 'راهنما Plisio',                 'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackplisio',     'label' => 'کش‌بک Plisio (٪)',              'placeholder' => '0'],
            ['type' => 'number', 'name' => 'minbalanceplisio',    'label' => 'حداقل Plisio (تومان)',          'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalanceplisio',    'label' => 'حداکثر Plisio (تومان)',         'placeholder' => '1000000'],


            ['type' => 'number', 'name' => 'helpperfectmony',     'label' => 'راهنما پرفکت مانی',            'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackperfect',    'label' => 'کش‌بک پرفکت (٪)',               'placeholder' => '0'],
            ['type' => 'number', 'name' => 'minbalanceperfect',   'label' => 'حداقل پرفکت (تومان)',          'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalanceperfect',   'label' => 'حداکثر پرفکت (تومان)',         'placeholder' => '1000000'],


            ['type' => 'toggle', 'name' => 'statusSwapWallet',    'label' => 'فعال‌سازی Swap Wallet',         'on' => 'onSwapinoBot',  'off' => 'offnSolutions'],


            ['type' => 'toggle', 'name' => 'statusstar',          'label' => 'فعال‌سازی Star Telegram',       'on' => '1',             'off' => '0'],
            ['type' => 'number', 'name' => 'helpstar',            'label' => 'راهنما Star (شناسه پیام)',     'placeholder' => '2'],
            ['type' => 'number', 'name' => 'chashbackstar',       'label' => 'کش‌بک Star (٪)',                'placeholder' => '0'],
            ['type' => 'number', 'name' => 'minbalancestar',      'label' => 'حداقل Star (تومان)',           'placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalancestar',      'label' => 'حداکثر Star (تومان)',          'placeholder' => '1000000'],


            ['type' => 'secret', 'name' => 'marchent_floypay',    'label' => 'مرچنت FloyPay',                 'placeholder' => 'مرچنت'],
        ],
    ],

    'limits' => [
        'title' => 'حدود کلی شارژ',
        'icon'  => 'sliders',
        'fields' => [
            ['type' => 'number', 'name' => 'minbalance',          'label' => 'حداقل کلی شارژ کیف پول (تومان)','placeholder' => '20000'],
            ['type' => 'number', 'name' => 'maxbalance',          'label' => 'حداکثر کلی شارژ کیف پول (تومان)','placeholder' => '1000000'],
        ],
    ],
];


$paySettings = [];
try {
    $r = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting");
    if ($r) {
        foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $paySettings[(string)$row['NamePay']] = (string)$row['ValuePay'];
        }
    }
} catch (\Throwable $e) {
    error_log('[panel/finance] load failed: ' . $e->getMessage());
}


$textbotRows = [];
try {
    $textbotKeys = [];
    foreach ($FIN_GROUPS as $g) {
        foreach ($g['fields'] as $f) {
            if (($f['type'] ?? '') === 'textbot') $textbotKeys[] = $f['name'];
        }
    }
    if (!empty($textbotKeys)) {
        $placeholders = implode(',', array_fill(0, count($textbotKeys), '?'));
        $stmt = $pdo->prepare("SELECT id_text, text FROM textbot WHERE id_text IN ($placeholders)");
        $stmt->execute($textbotKeys);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $textbotRows[(string)$row['id_text']] = (string)$row['text'];
        }
    }
} catch (\Throwable $e) {
    error_log('[panel/finance] textbot load failed: ' . $e->getMessage());
}


$savedCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_save'])) {
    foreach ($FIN_GROUPS as $group) {
        foreach ($group['fields'] as $f) {
            $key = $f['name'];
            $fieldType = $f['type'] ?? 'text';


            if ($fieldType === 'textbot') {
                if (!array_key_exists('f_' . $key, $_POST)) continue;
                $new = (string)$_POST['f_' . $key];
                $cur = $textbotRows[$key] ?? '';
                if ((string)$cur === $new) continue;
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO textbot (id_text, text) VALUES (:k, :v)
                         ON DUPLICATE KEY UPDATE text = VALUES(text)"
                    );
                    $stmt->execute([':k' => $key, ':v' => $new]);
                    $savedCount++;
                } catch (\Throwable $e) {
                    error_log('[panel/finance] save textbot ' . $key . ' failed: ' . $e->getMessage());
                }
                continue;
            }


            $cur = $paySettings[$key] ?? '';
            if ($fieldType === 'toggle') {
                $new = isset($_POST['f_' . $key]) ? $f['on'] : $f['off'];
            } else {
                if (!array_key_exists('f_' . $key, $_POST)) continue;
                $new = (string)$_POST['f_' . $key];

                if ($fieldType === 'secret' && $new === '') continue;
            }

            if ((string)$cur === (string)$new) continue;

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO PaySetting (NamePay, ValuePay) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)"
                );
                $stmt->execute([':k' => $key, ':v' => $new]);
                $savedCount++;
            } catch (\Throwable $e) {
                error_log('[panel/finance] save ' . $key . ' failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: finance.php?saved=' . $savedCount);
    exit;
}

$showSaved = isset($_GET['saved']);
$savedNum  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;


$CRYPTO_LABELS = [
    'TRX'        => '🟥 ترون (TRX)',
    'TON'        => '🔷 تون (TON)',
    'USDT_TRC20' => '💵 تتر روی ترون (USDT-TRC20)',
    'USDT_TON'   => '💵 تتر روی تون (USDT-TON)',
];

$cryptoTableExists = false;
try {
    $r = $pdo->query("SHOW TABLES LIKE 'crypto_wallets'");
    if ($r && $r->fetchColumn() !== false) $cryptoTableExists = true;
} catch (\Throwable $e) {
    $cryptoTableExists = false;
}

$cryptoWallets = [];
if ($cryptoTableExists) {
    try {
        $r = $pdo->query("SELECT * FROM crypto_wallets");
        if ($r) {
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cryptoWallets[(string)$row['currency']] = $row;
            }
        }
    } catch (\Throwable $e) {
        error_log('[panel/finance] crypto load failed: ' . $e->getMessage());
    }
}


$cryptoSaved = -1;
if ($cryptoTableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_crypto_save'])) {
    $cryptoSaved = 0;
    foreach ($CRYPTO_LABELS as $currency => $label) {
        $sfx     = 'cw_' . $currency . '_';
        $addr    = trim((string)($_POST[$sfx . 'address']  ?? ''));
        $enabled = !empty($_POST[$sfx . 'enabled']) ? 1 : 0;
        $min     = max(0, (int)($_POST[$sfx . 'min']     ?? 20000));
        $max     = max(0, (int)($_POST[$sfx . 'max']     ?? 1000000));
        $cb      = (float)($_POST[$sfx . 'cashback']     ?? 0);

        // Memo (TON network only): saved when the admin enabled it AND it's non-empty.
        $isTonNetwork = (strpos($currency, 'TON') !== false);
        $memo        = trim((string)($_POST[$sfx . 'memo'] ?? ''));
        $memoEnabled = !empty($_POST[$sfx . 'memo_enabled']);
        $walletMemo  = ($isTonNetwork && $memoEnabled) ? $memo : '';


        if ($enabled && $addr === '') {
            error_log('[panel/finance] crypto ' . $currency . ' enabled but address empty — disabling.');
            $enabled = 0;
        }

        if ($addr !== '' && (mb_strlen($addr) < 20 || mb_strlen($addr) > 100)) {
            error_log('[panel/finance] crypto ' . $currency . ' address has unusual length (' . mb_strlen($addr) . ').');

        }

        if ($cb < 0) $cb = 0;
        if ($cb > 100) $cb = 100;

        if ($max < $min) [$min, $max] = [$max, $min];

        try {

            $network = (strpos($currency, 'TON') !== false) ? 'TON' : 'TRON';

            $stmt = $pdo->prepare(
                "INSERT INTO crypto_wallets (currency, network, wallet_address, label, enabled, min_irt, max_irt, cashback_percent, wallet_memo)
                 VALUES (:c, :n, :w, :l, :en, :mn, :mx, :cb, :memo)
                 ON DUPLICATE KEY UPDATE
                    wallet_address   = VALUES(wallet_address),
                    enabled          = VALUES(enabled),
                    min_irt          = VALUES(min_irt),
                    max_irt          = VALUES(max_irt),
                    cashback_percent = VALUES(cashback_percent),
                    wallet_memo      = VALUES(wallet_memo)"
            );
            $stmt->execute([
                ':c'  => $currency,
                ':n'  => $network,
                ':w'  => $addr,
                ':l'  => $label,
                ':en' => $enabled,
                ':mn' => $min,
                ':mx' => $max,
                ':cb' => $cb,
                ':memo' => $walletMemo,
            ]);
            $cryptoSaved++;
        } catch (\Throwable $e) {
            error_log('[panel/finance] crypto save failed (' . $currency . '): ' . $e->getMessage());
        }
    }
    header('Location: finance.php?crypto_saved=' . $cryptoSaved);
    exit;
}
$cryptoSavedFlag = isset($_GET['crypto_saved']);
$cryptoSavedNum  = isset($_GET['crypto_saved']) ? (int)$_GET['crypto_saved'] : 0;

function faoxima_fin_is_on($cur, $on, $off) {
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}
function faoxima_fin_mask_secret($v) {
    $v = (string)$v;
    $n = strlen($v);
    if ($n === 0) return '';
    if ($n <= 4) return str_repeat('•', $n);
    return '••••' . substr($v, -4);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تنظیمات مالی | پنل فاکسیما</title>
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
                        <?php echo icon('money-bill', 'svg-icon svg-lg'); ?>
                        تنظیمات مالی
                    </div>
                    <div class="page-head__sub">شماره کارت، والت ترون، درگاه‌های پرداخت، کش‌بک و حدود مبلغ</div>
                </div>
            </div>

            <?php if ($showSaved): ?>
                <div class="alert <?php echo $savedNum > 0 ? 'alert-success' : 'alert-warning'; ?>">
                    <?php echo icon($savedNum > 0 ? 'circle-check' : 'circle-exclamation', 'svg-icon'); ?>
                    <span>
                        <?php echo $savedNum > 0 ? ($savedNum . ' فیلد ذخیره شد.') : 'هیچ تغییری انجام نشد.'; ?>
                    </span>
                </div>
            <?php endif; ?>

            <form method="POST" action="finance.php" autocomplete="off">
                <input type="hidden" name="_save" value="1">

                <div class="setting-grid">
                    <?php foreach ($FIN_GROUPS as $gKey => $group): ?>
                        <div class="card">
                            <div class="card__head">
                                <div class="card__title">
                                    <?php echo icon($group['icon'], 'svg-icon svg-md'); ?>
                                    <span><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <?php foreach ($group['fields'] as $f):
                                $key = $f['name'];

                                if (($f['type'] ?? '') === 'textbot') {
                                    $cur = $textbotRows[$key] ?? '';
                                } else {
                                    $cur = $paySettings[$key] ?? '';
                                }
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
                                                    <?php echo faoxima_fin_is_on($cur, $f['on'], $f['off']) ? 'checked' : ''; ?>>
                                                <span class="switch__slot"></span>
                                            </label>
                                        <?php elseif ($f['type'] === 'number'): ?>
                                            <input type="number" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left;">
                                        <?php elseif ($f['type'] === 'secret'): ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value=""
                                                placeholder="<?php echo htmlspecialchars(faoxima_fin_mask_secret($cur) ?: ($f['placeholder'] ?? ''), ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left; min-width: 180px;"
                                                autocomplete="off">
                                        <?php elseif ($f['type'] === 'textbot'): ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES, 'UTF-8'); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="min-width: 220px;"
                                                title="این مقدار به جدول textbot نوشته می‌شود (برای نمایش به کاربر)">
                                        <?php else: ?>
                                            <input type="text" id="<?php echo $idAttr; ?>" name="<?php echo $idAttr; ?>"
                                                value="<?php echo htmlspecialchars((string)$cur, ENT_QUOTES); ?>"
                                                placeholder="<?php echo htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES); ?>"
                                                style="direction:ltr; text-align:left; min-width: 180px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert" style="background: var(--accent-soft); border: 1px solid var(--accent-mid); color: var(--text-main); margin-top: 16px;">
                    <?php echo icon('circle-info', 'svg-icon'); ?>
                    <span>فیلدهای مرچنت/کلید API به‌صورت <code>••••XXXX</code> نمایش داده می‌شوند. برای تغییر، مقدار جدید را تایپ کنید — اگر خالی بماند، تغییری اعمال نمی‌شود.</span>
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

            
            <div class="page-head" style="margin-top: 32px;">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('money-bill', 'svg-icon svg-lg'); ?>
                        کیف پول‌های ارز دیجیتال (آفلاین)
                    </div>
                    <div class="page-head__sub">آدرس کیف پول هر شبکه را جداگانه ثبت کنید — کاربر هش پرداخت می‌فرستد و ربات از Tronscan / TonAPI تایید می‌کند.</div>
                </div>
            </div>

            <?php if (!$cryptoTableExists): ?>
                <div class="alert alert-warning">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span>جدول <code>crypto_wallets</code> در دیتابیس وجود ندارد. ابتدا یک‌بار از طریق ربات وارد منوی ارز دیجیتال آفلاین شوید (یا اسکریپت <code>table.php</code> را اجرا کنید) تا جدول ساخته شود.</span>
                </div>
            <?php else: ?>
                <?php if ($cryptoSavedFlag): ?>
                    <div class="alert <?php echo $cryptoSavedNum > 0 ? 'alert-success' : 'alert-warning'; ?>">
                        <?php echo icon($cryptoSavedNum > 0 ? 'circle-check' : 'circle-exclamation', 'svg-icon'); ?>
                        <span>
                            <?php echo $cryptoSavedNum > 0 ? ($cryptoSavedNum . ' کیف پول ذخیره شد.') : 'تغییری اعمال نشد.'; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="finance.php" autocomplete="off">
                    <input type="hidden" name="_crypto_save" value="1">

                    <div class="setting-grid">
                        <?php foreach ($CRYPTO_LABELS as $currency => $label):
                            $w        = $cryptoWallets[$currency] ?? [];
                            $addr     = (string)($w['wallet_address']   ?? '');
                            $enabled  = (int)($w['enabled']             ?? 0) === 1;
                            $min      = (int)($w['min_irt']             ?? 20000);
                            $max      = (int)($w['max_irt']             ?? 1000000);
                            $cashback = (float)($w['cashback_percent']  ?? 0);
                            $walletMemo = (string)($w['wallet_memo']    ?? '');
                            $memoEnabled = trim($walletMemo) !== '';
                            $isTon       = (strpos($currency, 'TON') !== false);
                            $sfx      = 'cw_' . $currency . '_';
                        ?>
                            <div class="card">
                                <div class="card__head">
                                    <div class="card__title">
                                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <label class="switch" title="فعال/غیرفعال">
                                        <input type="checkbox" name="<?php echo $sfx; ?>enabled" <?php echo $enabled ? 'checked' : ''; ?>>
                                        <span class="switch__slot"></span>
                                    </label>
                                </div>
                                <div class="setting-row" style="border-bottom: 1px dashed var(--border-soft);">
                                    <label class="setting-row__label" style="flex:1 1 100%;">
                                        آدرس کیف پول
                                        <small class="setting-row__hint">
                                            <?php
                                                if ($currency === 'TRX')        echo 'آدرس شروع‌شده با T (شبکه ترون)';
                                                elseif ($currency === 'TON')    echo 'آدرس شبکه TON (با UQ یا EQ شروع می‌شود)';
                                                elseif ($currency === 'USDT_TRC20') echo 'آدرس USDT روی شبکه ترون (با T شروع می‌شود)';
                                                elseif ($currency === 'USDT_TON')   echo 'آدرس USDT روی شبکه TON';
                                            ?>
                                        </small>
                                    </label>
                                </div>
                                <div style="padding: 8px 0;">
                                    <input type="text" name="<?php echo $sfx; ?>address"
                                        value="<?php echo htmlspecialchars($addr, ENT_QUOTES); ?>"
                                        placeholder="<?php echo $currency === 'TRX' ? 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' : 'wallet address'; ?>"
                                        style="width: 100%; padding: 9px 12px; border-radius: 8px; background: var(--surface-3); border: 1px solid var(--border-mid); color: var(--text-main); direction: ltr; font-family: 'JetBrains Mono', monospace; font-size: 12px;">
                                </div>
                                <div class="setting-row">
                                    <label class="setting-row__label">حداقل واریز (تومان)</label>
                                    <div class="setting-row__control">
                                        <input type="number" name="<?php echo $sfx; ?>min" value="<?php echo $min; ?>" min="0">
                                    </div>
                                </div>
                                <div class="setting-row">
                                    <label class="setting-row__label">حداکثر واریز (تومان)</label>
                                    <div class="setting-row__control">
                                        <input type="number" name="<?php echo $sfx; ?>max" value="<?php echo $max; ?>" min="0">
                                    </div>
                                </div>
                                <div class="setting-row">
                                    <label class="setting-row__label">کش‌بک (٪)</label>
                                    <div class="setting-row__control">
                                        <input type="number" name="<?php echo $sfx; ?>cashback" value="<?php echo $cashback; ?>" min="0" step="0.01">
                                    </div>
                                </div>
                                <?php if ($isTon): ?>
                                <div class="setting-row" style="border-top:1px dashed var(--border-soft);">
                                    <label class="setting-row__label">
                                        🏷 ممو (Memo / Comment)
                                        <small class="setting-row__hint">اگر کیف پول شما ممو دارد فعال کنید؛ در غیر این صورت خاموش بماند (مثل ربات).</small>
                                    </label>
                                    <label class="switch" title="فعال/غیرفعال ممو">
                                        <input type="checkbox" class="cw-memo-toggle" data-target="<?php echo $sfx; ?>memo" name="<?php echo $sfx; ?>memo_enabled" <?php echo $memoEnabled ? 'checked' : ''; ?>>
                                        <span class="switch__slot"></span>
                                    </label>
                                </div>
                                <div style="padding: 8px 0;">
                                    <input type="text" id="<?php echo $sfx; ?>memo" name="<?php echo $sfx; ?>memo"
                                        value="<?php echo htmlspecialchars($walletMemo, ENT_QUOTES); ?>"
                                        placeholder="مثلاً: 1234567 یا متن ممو"
                                        <?php echo $memoEnabled ? '' : 'disabled'; ?>
                                        style="width: 100%; padding: 9px 12px; border-radius: 8px; background: var(--surface-3); border: 1px solid var(--border-mid); color: var(--text-main); direction: ltr; font-family: 'JetBrains Mono', monospace; font-size: 12px; opacity: <?php echo $memoEnabled ? '1' : '0.5'; ?>;">
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn btn-primary">
                            <?php echo icon('check', 'svg-icon svg-sm'); ?>
                            <span>ذخیره کیف پول‌ها</span>
                        </button>
                    </div>
                </form>
                <script>
                (function(){
                    document.querySelectorAll('.cw-memo-toggle').forEach(function(t){
                        t.addEventListener('change', function(){
                            var inp = document.getElementById(this.dataset.target);
                            if (!inp) return;
                            inp.disabled = !this.checked;
                            inp.style.opacity = this.checked ? '1' : '0.5';
                            if (this.checked) inp.focus();
                        });
                    });
                })();
                </script>
            <?php endif; ?>

        </div>
    </section>
</section>

</body>
</html>


