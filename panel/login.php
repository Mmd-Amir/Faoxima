<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;

    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars(
        $err['message'] . ' @ ' . basename((string)$err['file']) . ':' . (int)$err['line'],
        ENT_QUOTES, 'UTF-8'
    );
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8">'
       . '<title>خطای سرور</title>'
       . '<body style="font-family:sans-serif;background:#0a0a0f;color:#f1f3f8;padding:32px;">'
       . '<h2>خطای داخلی سرور</h2><pre style="white-space:pre-wrap">' . $msg . '</pre>'
       . '</body></html>';
});

ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$allowed_ips = select("setting","*",null,null,"select");
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");

$_raw_iplogin = $allowed_ips['iplogin'] ?? '';
$_ip_list = [];
$_iplogin_unlimited = false;
if ($_raw_iplogin === '*' || $_raw_iplogin === 'all' || $_raw_iplogin === 'unlimited') {
    $_iplogin_unlimited = true;
} elseif (!empty($_raw_iplogin) && $_raw_iplogin !== '0') {
    $_decoded = json_decode($_raw_iplogin, true);
    if (is_array($_decoded)) {
        if (in_array('*', $_decoded, true) || in_array('all', $_decoded, true) || in_array('unlimited', $_decoded, true)) {
            $_iplogin_unlimited = true;
        } else {
            $_ip_list = $_decoded;
        }
    } elseif (filter_var($_raw_iplogin, FILTER_VALIDATE_IP)) {
        $_ip_list = [$_raw_iplogin];
    }
}
$check_ip = $_iplogin_unlimited || (!empty($_ip_list) && in_array($user_ip, $_ip_list, true));
$texterrr = "";

if (isset($_POST['login'])) {


    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username !== '' && $password !== '') {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $query->bindValue(':username', $username, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $texterrr = 'نام کاربری یا رمزعبور وارد شده اشتباه است!';
        } elseif ((string)$password !== (string)($result["password"] ?? '')) {
            $texterrr = 'رمز صحیح نمی باشد';
        } else {


            session_regenerate_id(true);
            $_SESSION["user"] = $result["username"];


            session_write_close();


            header('Location: index.php', true, 302);


            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                if (!headers_sent()) {
                    @header('Connection: close');
                    @header('Content-Length: 0');
                }
                while (ob_get_level() > 0) { @ob_end_flush(); }
                @flush();
            }


            try {
                if (is_array($admin_ids)) {
                    foreach ($admin_ids as $admin) {
                        $texts = "کاربر با نام کاربری " . $username . " وارد پنل تحت وب شد";
                        @sendmessage($admin, $texts, null, 'html');
                    }
                }
            } catch (\Throwable $e) {
                @error_log('Login notify failed: ' . $e->getMessage());
            }
            exit;
        }
    } else {
        $texterrr = 'نام کاربری یا رمز عبور خالی است.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ورود به پنل مدیریت | فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer>

</script>
</head>
<body class="login-page">

<?php if (!$check_ip): ?>
    <div class="ip-card">
        <span style="font-size:48px; color: var(--accent);"><?php echo icon('shield-halved', 'svg-icon'); ?></span>
        <h2>دسترسی محدود شده</h2>
        <p>برای ورود به سیستم، آی‌پی زیر را در تنظیمات ربات ثبت کنید.</p>
        <div class="ip-box"><?php echo htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php else: ?>

    <div class="login-card">
        <div class="login-terminal-bar">
            <span class="terminal__lights"><i></i><i></i><i></i></span>
        </div>

        <div class="login-body">
            <h2>پنل مدیریت فاکسیما</h2>
            <p>برای ادامه، اطلاعات حساب خود را وارد کنید.</p>

            <?php if (!empty($texterrr)): ?>
                <div class="alert alert-error">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($texterrr, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
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

            <p class="text-muted mt-2" style="font-size:11px;">
                <?php echo icon('circle-info', 'svg-icon'); ?>
                IP شما: <span style="direction:ltr;"><?php echo htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8'); ?></span>
            </p>

            
            <div class="login-social" style="display:flex; gap:10px; justify-content:center; margin-top:18px; padding-top:14px; border-top:1px solid var(--border, rgba(255,255,255,0.08));">
                <a href="https://t.me/faoxima" target="_blank" rel="noopener noreferrer"
                   aria-label="کانال تلگرام فاکسیما"
                   title="کانال تلگرام فاکسیما"
                   style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; background:var(--accent-soft, rgba(59,130,246,0.12)); color:var(--accent, #3b82f6); transition:transform .15s ease, background .15s ease;"
                   onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='translateY(0)';">
                    <?php echo icon('telegram', 'svg-icon'); ?>
                </a>
                <a href="https://github.com/Mmd-Amir/Faoxima" target="_blank" rel="noopener noreferrer"
                   aria-label="مخزن گیت‌هاب فاکسیما"
                   title="مخزن گیت‌هاب فاکسیما"
                   style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; background:var(--accent-soft, rgba(59,130,246,0.12)); color:var(--accent, #3b82f6); transition:transform .15s ease, background .15s ease;"
                   onmouseover="this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.transform='translateY(0)';">
                    <?php echo icon('github', 'svg-icon'); ?>
                </a>
            </div>

            <p class="text-muted" style="text-align:center; font-size:11px; margin-top:14px; direction:ltr; font-family:'JetBrains Mono',monospace;">
                <?php
                    $__loginVer = trim((string)@file_get_contents(__DIR__ . '/../version'));
                    if ($__loginVer === '') $__loginVer = '0.0.1';
                    echo 'v' . htmlspecialchars(ltrim($__loginVer, 'vV'), ENT_QUOTES, 'UTF-8');
                ?>
            </p>
        </div>
    </div>

<?php endif; ?>

<script>
    var SVG_EYE       = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var SVG_EYE_SLASH = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    function togglePass() {
        var inp = document.getElementById('passwordInput');
        var icn = document.getElementById('eye-icon');
        if (!inp || !icn) return;
        if (inp.type === 'password') {
            inp.type = 'text';
            icn.innerHTML = SVG_EYE_SLASH;
        } else {
            inp.type = 'password';
            icn.innerHTML = SVG_EYE;
        }
    }
</script>
</body>
</html>


