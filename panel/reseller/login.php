<?php

require_once __DIR__ . '/lib.php';

// Already authenticated → straight to the dashboard.
if (reseller_current()) {
    header('Location: index.php');
    exit;
}

$error = '';
$systemEnabled = reseller_system_enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    if (!$systemEnabled) {
        $error = 'سیستم نمایندگان توسط مدیر غیرفعال شده است.';
    } elseif (!reseller_ip_allowed()) {
        $error = 'دسترسی از این IP مجاز نیست.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'نام کاربری یا رمز عبور خالی است.';
        } else {
            $row = reseller_find_by_username($username);
            if (!$row || !reseller_verify_password($password, (string) ($row['password'] ?? ''))) {
                $error = 'نام کاربری یا رمز عبور اشتباه است!';
            } elseif ((string) ($row['status'] ?? '') !== 'active') {
                $error = 'حساب نمایندگی شما غیرفعال است. با مدیر تماس بگیرید.';
            } else {
                session_regenerate_id(true);
                $_SESSION['reseller_id'] = (int) $row['id'];
                $_SESSION['reseller_username'] = (string) $row['username'];
                try {
                    $pdo = $GLOBALS['pdo'];
                    $upd = $pdo->prepare("UPDATE reseller SET last_login = :ts WHERE id = :id");
                    $upd->execute([':ts' => (string) time(), ':id' => (int) $row['id']]);
                } catch (\Throwable $e) {
                }
                header('Location: index.php');
                exit;
            }
        }
    }
}

$csrf = reseller_csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ورود نمایندگان | Hamoix</title>
    <link rel="stylesheet" href="../css/theme.css">
    <script src="../js/theme.js" defer></script>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-terminal-bar">
            <span class="terminal__lights"><i></i><i></i><i></i></span>
        </div>
        <div class="login-body">
            <h2>پنل نمایندگی Hamoix</h2>
            <p>برای ادامه، اطلاعات حساب نمایندگی خود را وارد کنید.</p>

            <?php if (!$systemEnabled): ?>
                <div class="alert alert-info">
                    <?php echo icon('circle-info', 'svg-icon'); ?>
                    <span>سیستم نمایندگان در حال حاضر غیرفعال است.</span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo reseller_e($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <input type="hidden" name="_csrf" value="<?php echo reseller_e($csrf); ?>">
                <div class="form-group">
                    <label class="form-label">نام کاربری</label>
                    <div class="input-icon-wrap">
                        <?php echo icon('user', 'svg-icon'); ?>
                        <input type="text" name="username" class="form-control" placeholder="نام کاربری..." required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <div class="input-icon-wrap">
                        <?php echo icon('lock', 'svg-icon'); ?>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="نمایش رمز">
                            <span id="eye-icon"><?php echo icon('eye', 'svg-icon'); ?></span>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-block mt-2">
                    <?php echo icon('arrow-left', 'svg-icon'); ?>
                    ورود به پنل
                </button>
            </form>
        </div>
    </div>

    <script>
    function togglePass() {
        var input = document.getElementById('passwordInput');
        var eye = document.getElementById('eye-icon');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    </script>
</body>
</html>
