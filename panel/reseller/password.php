<?php

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    $current = (string) ($_POST['current'] ?? '');
    $newPass = (string) ($_POST['newpass'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $err = '';
    if (!reseller_verify_password($current, (string) $reseller['password'])) {
        $err = 'رمز عبور فعلی اشتباه است.';
    } elseif (strlen($newPass) < 6) {
        $err = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
    } elseif ($newPass !== $confirm) {
        $err = 'تکرار رمز عبور با رمز جدید یکسان نیست.';
    }

    if ($err !== '') {
        reseller_flash_set('error', $err);
        header('Location: password.php');
        exit;
    }

    $upd = $pdo->prepare("UPDATE reseller SET password = :p WHERE id = :id");
    $upd->execute([':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $rid]);
    reseller_flash_set('success', 'رمز عبور با موفقیت تغییر کرد.');
    header('Location: password.php');
    exit;
}

reseller_layout_head('تغییر رمز عبور', 'password', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('lock', 'svg-icon svg-lg'); ?> تغییر رمز عبور</div>
        <div class="page-head__sub">به‌روزرسانی رمز عبور حساب نمایندگی</div>
    </div>
</div>

<?php reseller_flash_render(); ?>

<div class="card" style="max-width:520px;">
    <div class="card__head"><div class="card__title"><?php echo icon('lock', 'svg-icon svg-sm'); ?> رمز عبور جدید</div></div>
    <div style="padding:16px;">
        <form method="post" action="password.php">
            <?php echo reseller_csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">رمز عبور فعلی</label>
                <input type="password" name="current" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label">رمز عبور جدید</label>
                <input type="password" name="newpass" class="form-control" minlength="6" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">تکرار رمز عبور جدید</label>
                <input type="password" name="confirm" class="form-control" minlength="6" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary mt-2">
                <?php echo icon('save', 'svg-icon svg-sm'); ?> ذخیره رمز عبور
            </button>
        </form>
    </div>
</div>

<?php
reseller_layout_foot();
