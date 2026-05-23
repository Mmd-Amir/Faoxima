<?php
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


if (isset($_GET['status']) and $_GET['status']) {
    if ($_GET['status'] == "block") {
        $textblok = "کاربر با آیدی عددی {$_GET['id']} در ربات مسدود گردید \n\nادمین مسدود کننده : پنل تحت وب\nنام کاربری : {$_SESSION['user']}";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id'           => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text'              => $textblok,
                'parse_mode'        => "HTML"
            ]);
        }
    } else {
        sendmessage($_GET['id'], "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید ", null, 'HTML');
    }
    update("user", "User_Status", $_GET['status'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['priceadd']) and $_GET['priceadd']) {
    $priceadd = number_format($_GET['priceadd'], 0);
    $textadd  = "💎 کاربر عزیز مبلغ {$priceadd} تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($_GET['id'], $textadd, null, 'HTML');
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب افزایش داده است :\n\n🪪 ادمین : {$_SESSION['user']}\n👤 کاربر : {$_GET['id']}\nمبلغ : $priceadd";
        telegram('sendmessage', [
            'chat_id'           => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text'              => $textaddbalance,
            'parse_mode'        => "HTML"
        ]);
    }
    $value = intval($user['Balance']) + intval($_GET['priceadd']);
    update("user", "Balance", $value, "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['pricelow']) and $_GET['pricelow']) {
    $pricelow = number_format($_GET['pricelow'], 0);
    if (strlen($setting['Channel_Report']) > 0) {
        $textlowbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب کسر کرده است :\n\n🪪 ادمین : {$_SESSION['user']}\n👤 کاربر : {$_GET['id']}\nمبلغ کسر شده : $pricelow";
        telegram('sendmessage', [
            'chat_id'           => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text'              => $textlowbalance,
            'parse_mode'        => "HTML"
        ]);
    }
    $value = intval($user['Balance']) - intval($_GET['pricelow']);
    update("user", "Balance", $value, "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['agent']) and $_GET['agent']) {
    update("user", "agent", $_GET['agent'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

if (isset($_GET['textmessage']) and $_GET['textmessage']) {
    $messagetext = "📥 یک پیام از مدیریت برای شما ارسال شد.\n\nمتن پیام : {$_GET['textmessage']}";
    sendmessage($_GET['id'], $messagetext, null, 'HTML');
    if (strlen($setting['Channel_Report']) > 0) {
        $textmsg = "📌 پیام مدیریت ارسال شد\n\n🪪 ادمین : {$_SESSION['user']}\n👤 گیرنده : {$_GET['id']}\nمتن : {$_GET['textmessage']}";
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
    <link rel="stylesheet" href="css/theme.css">
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
                        <span class="info-value text-accent"><?php echo number_format($user['Balance']); ?> تومان</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">شماره موبایل</span>
                        <span class="info-value"><?php echo $number_display; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">نوع کاربری</span>
                        <span class="info-value"><?php echo $agent_display; ?></span>
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

                    <a href="user.php?id=<?php echo $user['id']; ?>&agent=f&_csrf=<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-soft-danger btn-block"
                       onclick="return confirm('آیا مطمئن هستید؟')">
                        <?php echo icon('user-xmark', 'svg-icon'); ?> حذف نمایندگی
                    </a>

                    <button onclick="openModal('modal-send-msg')" class="btn btn-outline btn-block">
                        <?php echo icon('paper-plane', 'svg-icon'); ?> ارسال پیام
                    </button>
                </div>
            </div>

        </div>
    </section>
</section>


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
                <label class="form-label">مبلغ (تومان)</label>
                <input type="number" name="priceadd" class="form-control" placeholder="مثلا 50000" required>
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
                <label class="form-label">مبلغ کسر (تومان)</label>
                <input type="number" name="pricelow" class="form-control" placeholder="مثلا 10000" required>
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

</body>
</html>


