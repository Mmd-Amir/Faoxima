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


$tableMissing = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'app'");
    if ($check && $check->fetchColumn() === false) $tableMissing = true;
} catch (\Throwable $e) {
    $tableMissing = true;
}

$flash = ['ok' => '', 'err' => ''];

if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $link = trim((string)($_POST['link'] ?? ''));


        $errors = [];

        if ($name === '') {
            $errors[] = 'نام برنامه نمی‌تواند خالی باشد.';
        } elseif (mb_strlen($name, 'UTF-8') > 200) {
            $errors[] = 'نام برنامه نباید بیش از ۲۰۰ کاراکتر باشد.';
        }

        if ($link === '') {
            $errors[] = 'لینک نمی‌تواند خالی باشد.';
        } elseif (!preg_match('~^https?://~i', $link)) {
            $errors[] = 'لینک باید با http:// یا https:// شروع شود.';
        } elseif (!filter_var($link, FILTER_VALIDATE_URL)) {
            $errors[] = 'فرمت لینک نامعتبر است.';
        } elseif (mb_strlen($link) > 500) {
            $errors[] = 'لینک بیش از حد طولانی است (حداکثر ۵۰۰ کاراکتر).';
        }


        if (empty($errors)) {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM app WHERE name = :n LIMIT 1");
                $dup->execute([':n' => $name]);
                if ($dup->fetchColumn()) {
                    $errors[] = 'برنامه‌ای با این نام از قبل ثبت شده است.';
                }
            } catch (\Throwable $e) {  }
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO app (name, link) VALUES (:n, :l)");
                $stmt->execute([':n' => $name, ':l' => $link]);
                $flash['ok'] = 'برنامه افزوده شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM app WHERE id = :id")->execute([':id' => $id]);
                $flash['ok'] = 'برنامه حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
}

$apps = [];
if (!$tableMissing) {
    try {
        $r = $pdo->query("SELECT * FROM app ORDER BY id DESC");
        if ($r) $apps = $r->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $flash['err'] = $flash['err'] ?: 'بارگذاری لیست ناموفق: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>لینک‌های برنامه | پنل فاکسیما</title>
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
                        <?php echo icon('grid', 'svg-icon svg-lg'); ?>
                        لینک‌های نصب برنامه
                    </div>
                    <div class="page-head__sub">مدیریت دکمه‌های نصب اپلیکیشن که در ربات به کاربران نمایش داده می‌شود</div>
                </div>
                <?php if (!$tableMissing): ?>
                <div>
                    <button class="btn btn-primary" onclick="openModal('modal-add-app')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن برنامه</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($tableMissing): ?>
                <div class="alert alert-warning">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span>جدول <code>app</code> در دیتابیس هنوز ساخته نشده. لطفاً یک‌بار از این صفحه خارج شده و دوباره وارد شوید — جدول به‌صورت خودکار ساخته می‌شود.</span>
                </div>
            <?php endif; ?>

            <?php if ($flash['ok']): ?>
                <div class="alert alert-success">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert alert-danger">
                    <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($flash['err'], ENT_QUOTES); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$tableMissing): ?>
                <div class="card">
                    <div class="table-wrap">
                        <table id="appsTable" class="display app-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام برنامه / متن دکمه</th>
                                    <th>لینک</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($apps)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:30px 0; color:var(--text-muted);">
                                    هنوز برنامه‌ای ثبت نشده. روی «افزودن برنامه» کلیک کنید.
                                </td></tr>
                            <?php else: foreach ($apps as $app): ?>
                                <tr>
                                    <td data-label="شناسه"><?php echo (int)$app['id']; ?></td>
                                    <td data-label="نام"><b><?php echo htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8'); ?></b></td>
                                    <td data-label="لینک" style="direction:ltr; text-align:right; font-family:'JetBrains Mono', monospace; font-size:12px;">
                                        <a href="<?php echo htmlspecialchars($app['link'], ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--accent);">
                                            <?php echo htmlspecialchars(mb_strimwidth($app['link'], 0, 60, '…', 'UTF-8'), ENT_QUOTES); ?>
                                        </a>
                                    </td>
                                    <td data-label="عملیات" class="cell-actions">
                                        <form method="POST" style="display:inline" onsubmit="return confirm('برنامه «<?php echo htmlspecialchars($app['name'], ENT_QUOTES); ?>» حذف شود؟');">
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$app['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                                حذف
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert" style="background: var(--accent-soft); border: 1px solid var(--accent-mid); color: var(--text-main); margin-top: 14px;">
                    <?php echo icon('circle-info', 'svg-icon'); ?>
                    <span>برای نمایش این لینک‌ها در ربات، توگل «نمایش لینک نصب اپلیکیشن‌ها» (<code>linkappstatus</code>) را در <a href="settings.php">تنظیمات ربات → تجربه کاربری</a> روشن کنید.</span>
                </div>
            <?php endif; ?>

        </div>
    </section>
</section>

<?php if (!$tableMissing): ?>

<div id="modal-add-app" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">افزودن برنامه</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-app')">&times;</button>
        </div>
        <form method="POST" action="applinks.php">
            <input type="hidden" name="_action" value="add">
            <div class="form-group">
                <label class="form-label">نام برنامه / متن دکمه</label>
                <input type="text" name="name" class="form-control" placeholder="مثلاً: V2Box (iOS)" maxlength="200" required>
            </div>
            <div class="form-group">
                <label class="form-label">لینک نصب</label>
                <input type="url" name="link" class="form-control" placeholder="https://apps.apple.com/..." style="direction:ltr" required>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-app')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">افزودن</button>
            </div>
        </form>
    </div>
</div>

<script src="js/datatable.js" defer>

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    FaoximaDT.init('#appsTable');
});
function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }
</script>
<?php endif; ?>

</body>
</html>


