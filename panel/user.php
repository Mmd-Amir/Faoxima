<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';


$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}


$query = $pdo->prepare("SELECT * FROM user WHERE id=:id");
$query->bindParam("id", $_GET["id"], PDO::PARAM_STR);
$query->execute();
$user = $query->fetch(PDO::FETCH_ASSOC);

$setting        = select("setting", "*", null, null);
$otherservice   = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $targetUid = (string)($_GET['id'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    $act = (string)$_POST['_action'];
    if ($act === 'user_discount_add' && $targetUid !== '') {
        $code    = trim((string)($_POST['codeDiscount'] ?? ''));
        $vtype   = (string)($_POST['value_type'] ?? 'percent');
        $section = (string)($_POST['section'] ?? 'all');
        $value   = (string)($_POST['price'] ?? '0');
        $limit   = (int)($_POST['limitDiscount'] ?? 0);
        if (preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)
            && in_array($vtype, ['percent', 'amount', 'free'], true)
            && in_array($section, ['all', 'buy', 'extend', 'volume', 'time', 'charge'], true)) {
            if ($vtype === 'free') $value = '0';
            try {
                $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c LIMIT 1");
                $dup->execute([':c' => $code]);
                if (!$dup->fetchColumn()) {
                    $st = $pdo->prepare(
                        "INSERT INTO DiscountSell
                         (codeDiscount, price, limitDiscount, agent, usefirst, useuser, code_product, code_panel, time, type, usedDiscount, section, value_type, target_user, status)
                         VALUES (:c, :p, :l, 'allusers', '0', '0', 'all', '/all', '0', :sec, '0', :sec2, :vt, :tg, 'active')"
                    );
                    $st->execute([':c' => $code, ':p' => $value, ':l' => (string)$limit, ':sec' => $section, ':sec2' => $section, ':vt' => $vtype, ':tg' => $targetUid]);
                }
            } catch (\Throwable $e) {  }
        }
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    if ($act === 'user_discount_edit' && $targetUid !== '') {
        $id      = (int)($_POST['id'] ?? 0);
        $code    = trim((string)($_POST['codeDiscount'] ?? ''));
        $vtype   = (string)($_POST['value_type'] ?? 'percent');
        $section = (string)($_POST['section'] ?? 'all');
        $value   = (string)($_POST['price'] ?? '0');
        $limit   = (int)($_POST['limitDiscount'] ?? 0);
        if ($id > 0
            && preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)
            && in_array($vtype, ['percent', 'amount', 'free'], true)
            && in_array($section, ['all', 'buy', 'extend', 'volume', 'time', 'charge'], true)) {
            if ($vtype === 'free') $value = '0';
            try {
                $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c AND id <> :id LIMIT 1");
                $dup->execute([':c' => $code, ':id' => $id]);
                if (!$dup->fetchColumn()) {
                    $st = $pdo->prepare(
                        "UPDATE DiscountSell
                            SET codeDiscount = :c, price = :p, limitDiscount = :l,
                                section = :sec, type = :sec2, value_type = :vt
                          WHERE id = :id AND target_user = :tg"
                    );
                    $st->execute([':c' => $code, ':p' => $value, ':l' => (string)$limit, ':sec' => $section, ':sec2' => $section, ':vt' => $vtype, ':id' => $id, ':tg' => $targetUid]);
                }
            } catch (\Throwable $e) {  }
        }
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    if ($act === 'user_gift_add' && $targetUid !== '') {
        $code  = trim((string)($_POST['code'] ?? ''));
        $price = (int)($_POST['gift_price'] ?? 0);
        $limit = (int)($_POST['gift_limit'] ?? 0);
        if (preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code) && $price > 0) {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c LIMIT 1");
                $dup->execute([':c' => $code]);
                if (!$dup->fetchColumn()) {
                    $st = $pdo->prepare(
                        "INSERT INTO Discount (code, price, limituse, limitused, target_user, status)
                         VALUES (:c, :p, :l, '0', :tg, 'active')"
                    );
                    $st->execute([':c' => $code, ':p' => (string)$price, ':l' => (string)$limit, ':tg' => $targetUid]);
                }
            } catch (\Throwable $e) {  }
        }
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    if ($act === 'user_gift_edit' && $targetUid !== '') {
        $id    = (int)($_POST['id'] ?? 0);
        $code  = trim((string)($_POST['code'] ?? ''));
        $price = (int)($_POST['gift_price'] ?? 0);
        $limit = (int)($_POST['gift_limit'] ?? 0);
        if ($id > 0 && preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code) && $price > 0) {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c AND id <> :id LIMIT 1");
                $dup->execute([':c' => $code, ':id' => $id]);
                if (!$dup->fetchColumn()) {
                    $st = $pdo->prepare(
                        "UPDATE Discount SET code = :c, price = :p, limituse = :l WHERE id = :id AND target_user = :tg"
                    );
                    $st->execute([':c' => $code, ':p' => (string)$price, ':l' => (string)$limit, ':id' => $id, ':tg' => $targetUid]);
                }
            } catch (\Throwable $e) {  }
        }
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    if ($act === 'user_code_delete' && $targetUid !== '') {
        $id   = (int)($_POST['id'] ?? 0);
        $kind = (string)($_POST['kind'] ?? '');
        if ($id > 0) {
            try {
                if ($kind === 'gift') {
                    $pdo->prepare("DELETE FROM Discount WHERE id = :id AND target_user = :u")->execute([':id' => $id, ':u' => $targetUid]);
                } else {
                    $pdo->prepare("DELETE FROM DiscountSell WHERE id = :id AND target_user = :u")->execute([':id' => $id, ':u' => $targetUid]);
                }
            } catch (\Throwable $e) {  }
        }
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
    if ($act === 'user_set_discount' && $targetUid !== '') {
        $pd = (int)($_POST['pricediscount'] ?? 0);
        if ($pd < 0)   $pd = 0;
        if ($pd > 100) $pd = 100;
        update("user", "pricediscount", $pd, "id", $targetUid);
        header("Location: user.php?id=" . urlencode($targetUid));
        exit;
    }
}

$userCodes = [];
$userGiftCodes = [];
try {
    $st = $pdo->prepare("SELECT * FROM DiscountSell WHERE target_user = :u ORDER BY id DESC");
    $st->execute([':u' => (string)($_GET['id'] ?? '')]);
    $userCodes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {  }
try {
    $st = $pdo->prepare("SELECT * FROM Discount WHERE target_user = :u ORDER BY id DESC");
    $st->execute([':u' => (string)($_GET['id'] ?? '')]);
    $userGiftCodes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {  }


if (isset($_GET['status']) and $_GET['status']) {
    if ($_GET['status'] == "block") {
        $textblok = "کاربر با آیدی عددی {$_GET['id']} در ربات مسدود گردید \n\n<blockquote>ادمین مسدود کننده : پنل تحت وب</blockquote>\n<blockquote>نام کاربری : {$_SESSION['user']}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id'           => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text'              => $textblok,
                'parse_mode'        => "HTML"
            ]);
        }
    } else {
        sendmessage($_GET['id'], faoxima_textbot_get('dyn_panel_user_unblocked_notice', "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید "), null, 'HTML');
    }
    update("user", "User_Status", $_GET['status'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['priceadd']) and $_GET['priceadd']) {
    $priceadd = number_format($_GET['priceadd'], 0);
    $textadd  = faoxima_render_text(faoxima_textbot_get('dyn_panel_user_balance_added_tpl', '💎 کاربر عزیز مبلغ {amount} T به موجودی کیف پول تان اضافه گردید.'), ['amount' => $priceadd]);
    sendmessage($_GET['id'], $textadd, null, 'HTML');
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب افزایش داده است :\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 کاربر : {$_GET['id']}</blockquote>\n<blockquote>مبلغ : $priceadd</blockquote>";
        telegram('sendmessage', [
            'chat_id'           => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text'              => $textaddbalance,
            'parse_mode'        => "HTML"
        ]);
    }
    if (function_exists('balance_atomic_credit')) {
        balance_atomic_credit($_GET['id'], intval($_GET['priceadd']));
    } else {
        $value = intval($user['Balance']) + intval($_GET['priceadd']);
        update("user", "Balance", $value, "id", $_GET['id']);
    }
    if (function_exists('wallet_ledger_record')) {
        wallet_ledger_record($_GET['id'], 'credit', $_GET['priceadd'], 'admin_credit', 'افزایش موجودی از پنل تحت وب');
    }
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['pricelow']) and $_GET['pricelow']) {
    $pricelow = number_format($_GET['pricelow'], 0);
    if (strlen($setting['Channel_Report']) > 0) {
        $textlowbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب کسر کرده است :\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 کاربر : {$_GET['id']}</blockquote>\n<blockquote>مبلغ کسر شده : $pricelow</blockquote>";
        telegram('sendmessage', [
            'chat_id'           => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text'              => $textlowbalance,
            'parse_mode'        => "HTML"
        ]);
    }
    if (function_exists('balance_atomic_charge')) {
        $__adminDebitOk = balance_atomic_charge($_GET['id'], intval($_GET['pricelow']), 0);
        $__adminDebitOk = !empty($__adminDebitOk['ok']);
    } else {
        $value = intval($user['Balance']) - intval($_GET['pricelow']);
        update("user", "Balance", $value, "id", $_GET['id']);
        $__adminDebitOk = true;
    }
    if ($__adminDebitOk && function_exists('wallet_ledger_record')) {
        wallet_ledger_record($_GET['id'], 'debit', $_GET['pricelow'], 'admin_debit', 'کاهش موجودی از پنل تحت وب');
    }
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['agent']) and $_GET['agent']) {
    update("user", "agent", $_GET['agent'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['textmessage']) and $_GET['textmessage']) {
    $messagetext = faoxima_render_text(faoxima_textbot_get('dyn_panel_user_admin_message_tpl', "📥 یک پیام از مدیریت برای شما ارسال شد.\n\nمتن پیام : {message}"), ['message' => $_GET['textmessage']]);
    sendmessage($_GET['id'], $messagetext, null, 'HTML');
    if (strlen($setting['Channel_Report']) > 0) {
        $textmsg = "📌 پیام مدیریت ارسال شد\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 گیرنده : {$_GET['id']}</blockquote>\n<blockquote>متن : {$_GET['textmessage']}</blockquote>";
        telegram('sendmessage', [
            'chat_id'           => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text'              => $textmsg,
            'parse_mode'        => "HTML"
        ]);
    }
    header("Location: user.php?id={$_GET['id']}");
    exit;
}


$status_label   = ($user['User_Status'] == 'block') ? 'مسدود' : 'فعال';
$status_class   = ($user['User_Status'] == 'block') ? 'badge-danger' : 'badge-success';
$number_display = ($user['number'] == "none") ? 'ثبت نشده' : htmlspecialchars($user['number'], ENT_QUOTES, 'UTF-8');


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];


$_has_action = isset($_GET['status']) || isset($_GET['priceadd']) ||
               isset($_GET['pricelow']) || isset($_GET['agent']) ||
               isset($_GET['textmessage']);
if ($_has_action) {
    $incoming_csrf = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $incoming_csrf)) {
        http_response_code(403);
        exit('درخواست نامعتبر — توکن CSRF اشتباه است');
    }
}


$agent_types = [
    'f'  => 'کاربر عادی',
    'n'  => 'نماینده معمولی',
    'n2' => 'نماینده پیشرفته'
];
$agent_display = isset($agent_types[$user['agent']]) ? $agent_types[$user['agent']] : 'نامشخص';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت کاربر <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
<script src="js/money-input.js?v=fx1" defer></script>
<script src="js/theme.js?v=flat5" defer>

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
                        <?php echo icon('user', 'svg-icon svg-lg'); ?>
                        پروفایل کاربر
                    </div>
                    <div class="page-head__sub">
                        <a href="users.php" class="text-link">
                            <?php echo icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست
                        </a>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="avatar-circle">
                    <?php echo icon('user', 'svg-icon'); ?>
                </div>
                <div>
                    <h1 style="direction:ltr;"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p>شناسه عددی: <?php echo $user['id']; ?></p>
                </div>
                <a href="https://t.me/<?php echo urlencode($user['username']); ?>" target="_blank" class="tg-btn">
                    <?php echo icon('paper-plane', 'svg-icon'); ?>
                    مشاهده در تلگرام
                </a>
            </div>

            <div class="info-grid">
                <div class="card">
                    <div class="card__head">
                        <div class="card__title"><?php echo icon('circle-info', 'svg-icon svg-md'); ?><span>مشخصات حساب</span></div>
                    </div>
                    <div class="info-row">
                        <span class="info-label">وضعیت حساب</span>
                        <span class="info-value"><span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">موجودی کیف پول</span>
                        <span class="info-value text-accent"><?php echo number_format($user['Balance']); ?> T</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">شماره موبایل</span>
                        <span class="info-value"><?php echo $number_display; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">نوع کاربری</span>
                        <span class="info-value"><?php echo $agent_display; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">درصد تخفیف اختصاصی</span>
                        <span class="info-value text-accent"><?php echo (int)($user['pricediscount'] ?? 0); ?>٪</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card__head">
                        <div class="card__title"><?php echo icon('chart-line', 'svg-icon svg-md'); ?><span>آمار فعالیت</span></div>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تعداد زیرمجموعه</span>
                        <span class="info-value"><?php echo (int)$user['affiliatescount']; ?> نفر</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">معرف (بالاسری)</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['affiliates'] ?: '---', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">محدودیت تست</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['limit_usertest'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <div class="card__title"><?php echo icon('sliders', 'svg-icon svg-md'); ?><span>عملیات مدیریت</span></div>
                </div>
                <div class="actions-grid">
                    <?php if ($user['User_Status'] == 'block'): ?>
                        <a href="user.php?id=<?php echo $user['id']; ?>&status=active&_csrf=<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-soft-success btn-block">
                            <?php echo icon('check', 'svg-icon'); ?> رفع مسدودی
                        </a>
                    <?php else: ?>
                        <a href="user.php?id=<?php echo $user['id']; ?>&status=block&_csrf=<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-soft-danger btn-block"
                           onclick="return confirm('کاربر مسدود شود؟')">
                            <?php echo icon('ban', 'svg-icon'); ?> مسدود کردن
                        </a>
                    <?php endif; ?>

                    <button onclick="openModal('modal-add-balance')" class="btn btn-soft-info btn-block">
                        <?php echo icon('plus', 'svg-icon'); ?> افزایش موجودی
                    </button>

                    <button onclick="openModal('modal-low-balance')" class="btn btn-soft-warning btn-block">
                        <?php echo icon('minus', 'svg-icon'); ?> کسر موجودی
                    </button>

                    <button onclick="openModal('modal-change-agent')" class="btn btn-soft-purple btn-block">
                        <?php echo icon('user-tag', 'svg-icon'); ?> تغییر نوع کاربر
                    </button>

                    <button onclick="openModal('modal-user-discount-percent')" class="btn btn-soft-purple btn-block">
                        <?php echo icon('gift', 'svg-icon'); ?> درصد تخفیف اختصاصی
                    </button>

                    <a href="user.php?id=<?php echo $user['id']; ?>&agent=f&_csrf=<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-soft-danger btn-block"
                       onclick="return confirm('آیا مطمئن هستید؟')">
                        <?php echo icon('user-xmark', 'svg-icon'); ?> حذف نمایندگی
                    </a>

                    <button onclick="openModal('modal-send-msg')" class="btn btn-outline btn-block">
                        <?php echo icon('paper-plane', 'svg-icon'); ?> ارسال پیام
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <div class="card__title"><?php echo icon('ticket', 'svg-icon svg-md'); ?><span>کدهای اختصاصی این کاربر</span></div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
                    <button onclick="openModal('modal-user-discount')" class="btn btn-soft-info btn-sm">
                        <?php echo icon('plus', 'svg-icon'); ?> کد تخفیف اختصاصی
                    </button>
                    <button onclick="openModal('modal-user-gift')" class="btn btn-soft-success btn-sm">
                        <?php echo icon('plus', 'svg-icon'); ?> کد هدیه اختصاصی
                    </button>
                </div>
                <div class="table-wrap">
                    <table class="app-table" style="width:100%">
                        <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>بخش/کاربرد</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php
                        $__sectionFa = ['all'=>'همه','buy'=>'خرید','extend'=>'تمدید','volume'=>'حجم','time'=>'زمان','charge'=>'شارژ'];
                        $__vtFa = ['percent'=>'درصدی','amount'=>'مبلغی','free'=>'رایگان'];
                        if (empty($userCodes) && empty($userGiftCodes)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:22px 0; color:var(--text-muted);">کد اختصاصی‌ای برای این کاربر ثبت نشده است.</td></tr>
                        <?php else:
                            foreach ($userCodes as $c):
                                $vt = $c['value_type'] ?? 'percent';
                                if (!isset($__vtFa[$vt])) $vt = 'percent';
                                $sec = $c['section'] ?? 'all';
                                if (!isset($__sectionFa[$sec])) $sec = 'all';
                                $val = $vt === 'free' ? 'رایگان' : ($vt === 'amount' ? number_format((int)$c['price']) . ' T' : ((string)$c['price'] . '٪'));
                        ?>
                            <tr>
                                <td data-label="کد"><code style="direction:ltr"><?php echo htmlspecialchars($c['codeDiscount'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع"><span class="badge badge-info">تخفیف · <?php echo $__vtFa[$vt]; ?></span></td>
                                <td data-label="مقدار"><?php echo $val; ?></td>
                                <td data-label="بخش/کاربرد"><?php echo $__sectionFa[$sec]; ?></td>
                                <td data-label="عملیات">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$c['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($c['codeDiscount'], ENT_QUOTES); ?>"
                                            data-vt="<?php echo htmlspecialchars($vt, ENT_QUOTES); ?>"
                                            data-section="<?php echo htmlspecialchars($sec, ENT_QUOTES); ?>"
                                            data-price="<?php echo htmlspecialchars((string)$c['price'], ENT_QUOTES); ?>"
                                            data-limit="<?php echo (int)($c['limitDiscount'] ?? 0); ?>"
                                            onclick="editUserDiscount(this)">
                                            <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('این کد حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="_action" value="user_code_delete">
                                            <input type="hidden" name="kind" value="discount">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                            foreach ($userGiftCodes as $g): ?>
                            <tr>
                                <td data-label="کد"><code style="direction:ltr"><?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع"><span class="badge badge-success">هدیه</span></td>
                                <td data-label="مقدار"><?php echo number_format((int)($g['price'] ?? 0)); ?> T</td>
                                <td data-label="بخش/کاربرد">شارژ کیف پول</td>
                                <td data-label="عملیات">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$g['id']; ?>"
                                            data-code="<?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>"
                                            data-price="<?php echo (int)($g['price'] ?? 0); ?>"
                                            data-limit="<?php echo (int)($g['limituse'] ?? 0); ?>"
                                            onclick="editUserGift(this)">
                                            <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('این کد حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="_action" value="user_code_delete">
                                            <input type="hidden" name="kind" value="gift">
                                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>


<div id="modal-user-discount" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کد تخفیف اختصاصی برای کاربر <?php echo htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES); ?></span>
            <button class="modal-close" onclick="closeModal('modal-user-discount')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_discount_add">
            <div class="form-group">
                <label class="form-label">کد تخفیف</label>
                <input type="text" name="codeDiscount" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع تخفیف</label>
                <select name="value_type" id="add_d_vt" class="form-control" onchange="faoximaToggleDiscountMoney('add_d_price', this.value)">
                    <option value="percent">درصدی</option>
                    <option value="amount">مبلغی (T)</option>
                    <option value="free">رایگان</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">بخش کد</label>
                <select name="section" class="form-control">
                    <option value="all">همه بخش‌ها</option>
                    <option value="buy">خرید</option>
                    <option value="extend">تمدید</option>
                    <option value="volume">حجم اضافه</option>
                    <option value="time">زمان اضافه</option>
                    <option value="charge">شارژ کیف پول</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مقدار</label>
                <input type="text" name="price" id="add_d_price" class="form-control" value="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                <input type="number" name="limitDiscount" class="form-control" value="0" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ثبت کد</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-user-gift" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کد هدیه اختصاصی برای کاربر <?php echo htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES); ?></span>
            <button class="modal-close" onclick="closeModal('modal-user-gift')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_gift_add">
            <div class="form-group">
                <label class="form-label">کد هدیه</label>
                <input type="text" name="code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">مبلغ (T)</label>
                <input type="text" name="gift_price" class="form-control" data-money min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                <input type="number" name="gift_limit" class="form-control" value="0" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ثبت کد</button>
            </div>
        </form>
    </div>
</div>


<div id="modal-add-balance" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">افزایش موجودی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-add-balance')">&times;</button>
        </div>
        <form action="user.php" method="GET">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <div class="form-group">
                <label class="form-label">مبلغ (T)</label>
                <input type="text" name="priceadd" class="form-control" data-money placeholder="مثلا 50000" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">افزایش موجودی</button>
        </form>
    </div>
</div>

<div id="modal-low-balance" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کسر موجودی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-low-balance')">&times;</button>
        </div>
        <form action="user.php" method="GET">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <div class="form-group">
                <label class="form-label">مبلغ کسر (T)</label>
                <input type="text" name="pricelow" class="form-control" data-money placeholder="مثلا 10000" required>
            </div>
            <button type="submit" class="btn btn-soft-warning btn-block">کسر موجودی</button>
        </form>
    </div>
</div>

<div id="modal-change-agent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">تغییر سطح کاربری</span>
            <button class="modal-close" onclick="closeModal('modal-change-agent')">&times;</button>
        </div>
        <form action="user.php" method="GET">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <div class="form-group">
                <label class="form-label">انتخاب سطح</label>
                <select name="agent" class="form-control">
                    <option value="f"  <?php if ($user['agent']=='f')  echo 'selected'; ?>>کاربر عادی</option>
                    <option value="n"  <?php if ($user['agent']=='n')  echo 'selected'; ?>>نماینده معمولی</option>
                    <option value="n2" <?php if ($user['agent']=='n2') echo 'selected'; ?>>نماینده پیشرفته</option>
                </select>
            </div>
            <button type="submit" class="btn btn-soft-purple btn-block">تغییر سطح</button>
        </form>
    </div>
</div>

<div id="modal-user-discount-percent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">درصد تخفیف اختصاصی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-user-discount-percent')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_set_discount">
            <div class="form-group">
                <label class="form-label">درصد تخفیف (۰ تا ۱۰۰)</label>
                <input type="number" name="pricediscount" min="0" max="100" value="<?php echo (int)($user['pricediscount'] ?? 0); ?>" class="form-control" style="direction:ltr" required>
                <small style="opacity:.75">روی تمام خریدها و تمدیدهای کاربر اعمال می‌شود (به‌جز شارژ کیف پول). با مقدار بزرگ‌تر از صفر، کاربر نمی‌تواند از کد تخفیف استفاده کند.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block">ذخیره</button>
        </form>
    </div>
</div>

<div id="modal-send-msg" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ارسال پیام خصوصی</span>
            <button class="modal-close" onclick="closeModal('modal-send-msg')">&times;</button>
        </div>
        <form action="user.php" method="GET">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <div class="form-group">
                <label class="form-label">متن پیام</label>
                <textarea name="textmessage" class="form-control" rows="4" placeholder="پیام خود را بنویسید..." required></textarea>
            </div>
            <button type="submit" class="btn btn-outline btn-block">ارسال پیام</button>
        </form>
    </div>
</div>

<div id="modal-user-discount-edit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد تخفیف اختصاصی</span>
            <button class="modal-close" onclick="closeModal('modal-user-discount-edit')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_discount_edit">
            <input type="hidden" name="id" id="eu_d_id">
            <div class="form-group">
                <label class="form-label">کد تخفیف</label>
                <input type="text" name="codeDiscount" id="eu_d_code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع تخفیف</label>
                <select name="value_type" id="eu_d_vt" class="form-control" onchange="faoximaToggleDiscountMoney('eu_d_price', this.value)">
                    <option value="percent">درصدی</option>
                    <option value="amount">مبلغی (T)</option>
                    <option value="free">رایگان</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">بخش کد</label>
                <select name="section" id="eu_d_section" class="form-control">
                    <option value="all">همه بخش‌ها</option>
                    <option value="buy">خرید</option>
                    <option value="extend">تمدید</option>
                    <option value="volume">حجم اضافه</option>
                    <option value="time">زمان اضافه</option>
                    <option value="charge">شارژ کیف پول</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مقدار</label>
                <input type="text" name="price" id="eu_d_price" class="form-control" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                <input type="number" name="limitDiscount" id="eu_d_limit" class="form-control" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-discount-edit')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-user-gift-edit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد هدیه اختصاصی</span>
            <button class="modal-close" onclick="closeModal('modal-user-gift-edit')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_gift_edit">
            <input type="hidden" name="id" id="eu_g_id">
            <div class="form-group">
                <label class="form-label">کد هدیه</label>
                <input type="text" name="code" id="eu_g_code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">مبلغ (T)</label>
                <input type="text" name="gift_price" id="eu_g_price" class="form-control" data-money min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                <input type="number" name="gift_limit" id="eu_g_limit" class="form-control" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-gift-edit')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
<script>
function faoximaToggleDiscountMoney(inputId, type) {
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById(inputId), type === 'amount');
    }
}
function editUserDiscount(btn) {
    document.getElementById('eu_d_id').value      = btn.getAttribute('data-id');
    document.getElementById('eu_d_code').value    = btn.getAttribute('data-code');
    document.getElementById('eu_d_vt').value      = btn.getAttribute('data-vt');
    document.getElementById('eu_d_section').value = btn.getAttribute('data-section');
    document.getElementById('eu_d_price').value   = btn.getAttribute('data-price');
    document.getElementById('eu_d_limit').value   = btn.getAttribute('data-limit');
    faoximaToggleDiscountMoney('eu_d_price', btn.getAttribute('data-vt'));
    openModal('modal-user-discount-edit');
}
function editUserGift(btn) {
    document.getElementById('eu_g_id').value    = btn.getAttribute('data-id');
    document.getElementById('eu_g_code').value  = btn.getAttribute('data-code');
    document.getElementById('eu_g_price').value = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(btn.getAttribute('data-price')) : btn.getAttribute('data-price');
    document.getElementById('eu_g_limit').value = btn.getAttribute('data-limit');
    openModal('modal-user-gift-edit');
}
</script>
</body>
</html>


