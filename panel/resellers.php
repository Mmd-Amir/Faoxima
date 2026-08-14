<?php

if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    return;
}

function reseller_admin_redirect($msg = '', $type = 'success')
{
    if ($msg !== '') {
        $_SESSION['reseller_admin_flash'] = ['type' => $type, 'msg' => $msg];
    }
    header('Location: resellers.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['_csrf'] ?? ''))) {
        reseller_admin_redirect('درخواست نامعتبر (CSRF).', 'error');
    }
    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled';
        $limitBalance = preg_replace('/[^0-9]/', '', (string) ($_POST['limit_balance'] ?? ''));
        $limitServices = preg_replace('/[^0-9]/', '', (string) ($_POST['limit_services'] ?? ''));
        $minWithdraw = preg_replace('/[^0-9]/', '', (string) ($_POST['min_withdraw'] ?? ''));
        $allowed = $_POST['allowed_products'] ?? [];
        $allowedJson = is_array($allowed) && $allowed ? json_encode(array_map('strval', $allowed), JSON_UNESCAPED_UNICODE) : '';

        if ($username === '' || strlen($username) < 3) {
            reseller_admin_redirect('نام کاربری باید حداقل ۳ کاراکتر باشد.', 'error');
        }

        if ($action === 'create') {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM reseller WHERE username = :u");
            $exists->execute([':u' => $username]);
            if ((int) $exists->fetchColumn() > 0) {
                reseller_admin_redirect('این نام کاربری قبلاً ثبت شده است.', 'error');
            }
            if (strlen($password) < 4) {
                reseller_admin_redirect('رمز عبور باید حداقل ۴ کاراکتر باشد.', 'error');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare(
                "INSERT INTO reseller (username, password, name, phone, status, balance, limit_balance, limit_services, allowed_products, min_withdraw, created_at)
                 VALUES (:u, :p, :n, :ph, :st, 0, :lb, :ls, :ap, :mw, :ts)"
            );
            $ins->execute([                ':u' => $username, ':p' => $hash, ':n' => $name, ':ph' => $phone,
 ':st' => $status, ':lb' => $limitBalance, ':ls' => $limitServices,
                ':ap' => $allowedJson, ':mw' => $minWithdraw, ':ts' => (string) time(),
            ]);
            reseller_admin_redirect('نماینده با موفقیت ایجاد شد.');
        } else {
            if ($id <= 0) {
                reseller_admin_redirect('شناسه نماینده نامعتبر است.', 'error');
            }
            $exists = $pdo->prepare("SELECT COUNT(*) FROM reseller WHERE username = :u AND id <> :id");
            $exists->execute([':u' => $username, ':id' => $id]);
            if ((int) $exists->fetchColumn() > 0) {
                reseller_admin_redirect('این نام کاربری توسط نماینده دیگری استفاده شده است.', 'error');
            }
            $sets = "username=:u, name=:n, phone=:ph, status=:st, limit_balance=:lb, limit_services=:ls, allowed_products=:ap, min_withdraw=:mw";
            $params = [
                ':u' => $username, ':n' => $name, ':ph' => $phone,
                ':st' => $status, ':lb' => $limitBalance, ':ls' => $limitServices, ':ap' => $allowedJson,
                ':mw' => $minWithdraw, ':id' => $id,
            ];
            if ($password !== '') {
                $sets .= ", password=:p";
                $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $upd = $pdo->prepare("UPDATE reseller SET $sets WHERE id=:id");
            $upd->execute($params);
            reseller_admin_redirect('اطلاعات نماینده به‌روزرسانی شد.');
        }
    }

    if ($action === 'adjust') {
        $id = (int) ($_POST['id'] ?? 0);
        $amount = (int) preg_replace('/[^0-9]/', '', (string) ($_POST['amount'] ?? '0'));
        $direction = ($_POST['direction'] ?? 'credit') === 'debit' ? 'debit' : 'credit';
        if ($id <= 0) {
            reseller_admin_redirect('شناسه نماینده نامعتبر است.', 'error');
        }
        if ($amount <= 0) {
            reseller_admin_redirect('مبلغ نامعتبر است.', 'error');
        }
        $signed = $direction === 'credit' ? $amount : -$amount;
        $type = $direction === 'credit' ? 'admin_credit' : 'admin_debit';
        $note = $direction === 'credit' ? 'افزایش موجودی توسط مدیر' : 'کسر موجودی توسط مدیر';
        require_once __DIR__ . '/reseller/lib.php';
        $res = reseller_wallet_apply($id, $type, $signed, $note, 'admin:' . $adminRow['username'], true);
        reseller_admin_redirect($res['ok'] ? 'موجودی نماینده به‌روزرسانی شد.' : ('خطا: ' . $res['msg']), $res['ok'] ? 'success' : 'error');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            reseller_admin_redirect('شناسه نماینده نامعتبر است.', 'error');
        }
        $pdo->prepare("DELETE FROM reseller WHERE id = :id")->execute([':id' => $id]);
        reseller_admin_redirect('نماینده حذف شد.');
    }

    if ($action === 'withdraw_decision') {
        $wid = (int) ($_POST['withdraw_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
        $txid = trim((string) ($_POST['txid'] ?? ''));

        $sel = $pdo->prepare("SELECT * FROM reseller_withdraw WHERE id = :id LIMIT 1");
        $sel->execute([':id' => $wid]);
        $w = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$w || $w['status'] !== 'pending') {
            reseller_admin_redirect('این درخواست قبلاً پردازش شده یا یافت نشد.', 'error');
        }
        require_once __DIR__ . '/reseller/lib.php';
        if ($decision === 'approve') {
            // فلیپ اتمیک با شرط status='pending' تا تصمیم دقیقاً یک‌بار اعمال شود.
            $upd = $pdo->prepare("UPDATE reseller_withdraw SET status='approved', admin_note=:n, txid=:t, updated_at=:ts WHERE id=:id AND status='pending'");
            $upd->execute([':n' => $adminNote, ':t' => $txid, ':ts' => (string) time(), ':id' => $wid]);
            reseller_admin_redirect($upd->rowCount() === 1 ? 'برداشت تأیید شد.' : 'این درخواست قبلاً پردازش شده است.', $upd->rowCount() === 1 ? 'success' : 'error');
        } elseif ($decision === 'reject') {
            // ابتدا وضعیت را به‌صورت اتمیک رد می‌کنیم؛ فقط در صورتی که این درخواست واقعاً
            // همین حالا از pending به rejected تبدیل شد، وجه بازگردانده می‌شود. در غیر این صورت
            // (مثلاً دابل‌کلیک یا دو ادمین هم‌زمان) از بازگشتِ مضاعف وجه جلوگیری می‌شود.
            $upd = $pdo->prepare("UPDATE reseller_withdraw SET status='rejected', admin_note=:n, updated_at=:ts WHERE id=:id AND status='pending'");
            $upd->execute([':n' => $adminNote, ':ts' => (string) time(), ':id' => $wid]);
            if ($upd->rowCount() === 1) {
                reseller_wallet_apply((int) $w['reseller_id'], 'withdraw_refund', (int) $w['amount'], 'بازگشت وجه برداشت ردشده', 'withdraw:' . $wid, true);
                reseller_admin_redirect('برداشت رد شد و وجه به کیف پول نماینده بازگشت.');
            }
            reseller_admin_redirect('این درخواست قبلاً پردازش شده است.', 'error');
        }
        reseller_admin_redirect('عملیات نامعتبر.', 'error');
    }
}

$resellers = $pdo->query("SELECT * FROM reseller ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Mini-stats per reseller: active services + lifetime top-up.
$svcStats = [];
$svcRows = $pdo->query("SELECT reseller_id, COUNT(*) AS cnt FROM reseller_service WHERE status = 'active' GROUP BY reseller_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($svcRows as $sr) {
    $svcStats[(int) $sr['reseller_id']] = (int) $sr['cnt'];
}
$topupStats = [];
$topupRows = $pdo->query("SELECT reseller_id, SUM(amount) AS total FROM reseller_ledger WHERE type = 'topup' GROUP BY reseller_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($topupRows as $tr) {
    $topupStats[(int) $tr['reseller_id']] = (int) $tr['total'];
}
$products = $pdo->query("SELECT code_product, name_product, reseller_status FROM product ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$pendingWithdraws = $pdo->query(
    "SELECT w.*, r.username AS reseller_username FROM reseller_withdraw w
     LEFT JOIN reseller r ON r.id = w.reseller_id
     WHERE w.status = 'pending' ORDER BY w.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['reseller_admin_flash'] ?? null;
unset($_SESSION['reseller_admin_flash']);

function res_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت نمایندگان | فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>
<body>
<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title"><?php echo icon('user-tag', 'svg-icon svg-lg'); ?> مدیریت نمایندگان</div>
                    <div class="page-head__sub">ایجاد و مدیریت نمایندگان فروش، موجودی و برداشت‌ها</div>
                </div>
                <div class="chip-row">
                    <a href="reseller/login.php" target="_blank" class="chip"><?php echo icon('arrow-left', 'svg-icon svg-sm'); ?><span>ورود به پنل نماینده</span></a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert <?php echo $flash['type'] === 'error' ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo icon($flash['type'] === 'error' ? 'circle-exclamation' : 'circle-check', 'svg-icon'); ?>
                    <span><?php echo res_e($flash['msg']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($pendingWithdraws): ?>
            <div class="card mb-3">
                <div class="card__head"><div class="card__title"><?php echo icon('coins', 'svg-icon svg-sm'); ?> درخواست‌های برداشت در انتظار</div></div>
                <div class="table-wrap">
                    <table class="app-table">
                        <thead><tr><th>نماینده</th><th>مبلغ</th><th>شبکه</th><th>آدرس</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingWithdraws as $w): ?>
                            <tr>
                                <td><?php echo res_e($w['reseller_username']); ?></td>
                                <td style="direction:ltr;"><?php echo number_format((int) $w['amount']); ?></td>
                                <td style="direction:ltr;"><?php echo res_e($w['network']); ?></td>
                                <td style="direction:ltr; max-width:220px; word-break:break-all;"><?php echo res_e($w['address']); ?></td>
                                <td>
                                    <form method="post" action="resellers.php" style="display:flex; flex-direction:column; gap:6px; min-width:240px;">
                                        <input type="hidden" name="_csrf" value="<?php echo res_e($_csrf); ?>">
                                        <input type="hidden" name="action" value="withdraw_decision">
                                        <input type="hidden" name="withdraw_id" value="<?php echo (int) $w['id']; ?>">
                                        <input type="text" name="txid" class="form-control" placeholder="کد تراکنش (txid)" style="direction:ltr;">
                                        <input type="text" name="admin_note" class="form-control" placeholder="یادداشت (اختیاری)">
                                        <div style="display:flex; gap:6px;">
                                            <button name="decision" value="approve" class="btn btn-sm btn-success">تأیید پرداخت</button>
                                            <button name="decision" value="reject" class="btn btn-sm btn-soft-danger" onclick="return confirm('رد درخواست و بازگشت وجه؟');">رد</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card__head"><div class="card__title"><?php echo icon('user-plus', 'svg-icon svg-sm'); ?> ایجاد نماینده جدید</div></div>
                <div style="padding:16px;">
                    <form method="post" action="resellers.php">
                        <input type="hidden" name="_csrf" value="<?php echo res_e($_csrf); ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="form-row" style="display:flex; gap:14px; flex-wrap:wrap;">
                            <div class="form-group" style="flex:1; min-width:180px;"><label class="form-label">نام کاربری</label><input type="text" name="username" class="form-control" required></div>
                            <div class="form-group" style="flex:1; min-width:180px;"><label class="form-label">رمز عبور</label><input type="text" name="password" class="form-control" required></div>
                            <div class="form-group" style="flex:1; min-width:180px;"><label class="form-label">نام نمایش</label><input type="text" name="name" class="form-control"></div>
                        </div>
                        <div class="form-row" style="display:flex; gap:14px; flex-wrap:wrap;">
                            <div class="form-group" style="flex:1; min-width:160px;"><label class="form-label">تلفن</label><input type="text" name="phone" class="form-control"></div>
                        </div>
                        <div class="form-row" style="display:flex; gap:14px; flex-wrap:wrap;">
                            <div class="form-group" style="flex:1; min-width:150px;"><label class="form-label">سقف کیف پول (خالی=نامحدود)</label><input type="text" name="limit_balance" class="form-control" style="direction:ltr;"></div>
                            <div class="form-group" style="flex:1; min-width:150px;"><label class="form-label">سقف ساخت سرویس (خالی=نامحدود)</label><input type="text" name="limit_services" class="form-control" style="direction:ltr;"></div>
                            <div class="form-group" style="flex:1; min-width:150px;"><label class="form-label">حداقل برداشت (خالی=پیش‌فرض)</label><input type="text" name="min_withdraw" class="form-control" style="direction:ltr;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">محصولات مجاز (هیچ‌کدام = همه‌ی محصولات فعال‌شده برای نماینده)</label>
                            <select name="allowed_products[]" class="form-control" multiple size="4">
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo res_e($p['code_product']); ?>"><?php echo res_e($p['name_product'] ?? $p['code_product']); ?><?php echo ((string)($p['reseller_status'] ?? '0') === '1') ? '' : ' (غیرفعال برای نماینده)'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo icon('save', 'svg-icon svg-sm'); ?> ایجاد نماینده</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card__head"><div class="card__title"><?php echo icon('users', 'svg-icon svg-sm'); ?> لیست نمایندگان</div></div>
                <div class="table-wrap">
                    <table class="app-table">
                        <thead><tr><th>#</th><th>نام کاربری</th><th>نام</th><th>موجودی</th><th>سرویس‌های فعال</th><th>مجموع شارژ</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                        <tbody>
                            <?php if (!$resellers): ?>
                                <tr><td colspan="6" style="text-align:center; padding:24px;">هنوز نماینده‌ای ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($resellers as $r): ?>
                                    <?php $allowedArr = json_decode((string) ($r['allowed_products'] ?? ''), true) ?: []; ?>
                                    <tr>
                                        <td><?php echo (int) $r['id']; ?></td>
                                        <td style="direction:ltr;"><?php echo res_e($r['username']); ?></td>
                                        <td><?php echo res_e($r['name'] ?? '—'); ?></td>
                                        <td style="direction:ltr;"><?php echo number_format((int) $r['balance']); ?></td>
                                        <td><?php echo number_format((int) ($svcStats[(int) $r['id']] ?? 0)); ?></td>
                                        <td style="direction:ltr;"><?php echo number_format((int) ($topupStats[(int) $r['id']] ?? 0)); ?></td>
                                        <td><span class="badge <?php echo $r['status'] === 'active' ? 'badge-success' : 'badge-gray'; ?>"><?php echo $r['status'] === 'active' ? 'فعال' : 'غیرفعال'; ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-soft-info" onclick="document.getElementById('edit-<?php echo (int) $r['id']; ?>').style.display='block'"><?php echo icon('pen', 'svg-icon svg-xs'); ?> ویرایش</button>
                                        </td>
                                    </tr>
                                    <tr id="edit-<?php echo (int) $r['id']; ?>" style="display:none;">
                                        <td colspan="8" style="background:var(--surface-2,rgba(255,255,255,0.03));">
                                            <div style="display:flex; gap:18px; flex-wrap:wrap; padding:10px 0;">
                                                <form method="post" action="resellers.php" style="flex:2; min-width:320px;">
                                                    <input type="hidden" name="_csrf" value="<?php echo res_e($_csrf); ?>">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                    <div class="form-row" style="display:flex; gap:10px; flex-wrap:wrap;">
                                                        <div class="form-group" style="flex:1; min-width:140px;"><label class="form-label">نام کاربری</label><input type="text" name="username" class="form-control" value="<?php echo res_e($r['username']); ?>"></div>
                                                        <div class="form-group" style="flex:1; min-width:140px;"><label class="form-label">رمز جدید (خالی=بدون تغییر)</label><input type="text" name="password" class="form-control"></div>
                                                        <div class="form-group" style="flex:1; min-width:140px;"><label class="form-label">نام نمایش</label><input type="text" name="name" class="form-control" value="<?php echo res_e($r['name']); ?>"></div>
                                                    </div>
                                                    <div class="form-row" style="display:flex; gap:10px; flex-wrap:wrap;">
                                                        <div class="form-group" style="flex:1; min-width:120px;"><label class="form-label">تلفن</label><input type="text" name="phone" class="form-control" value="<?php echo res_e($r['phone']); ?>"></div>
                                                    </div>
                                                    <div class="form-row" style="display:flex; gap:10px; flex-wrap:wrap;">
                                                        <div class="form-group" style="flex:1; min-width:110px;"><label class="form-label">سقف کیف پول</label><input type="text" name="limit_balance" class="form-control" style="direction:ltr;" value="<?php echo res_e($r['limit_balance']); ?>"></div>
                                                        <div class="form-group" style="flex:1; min-width:110px;"><label class="form-label">سقف ساخت</label><input type="text" name="limit_services" class="form-control" style="direction:ltr;" value="<?php echo res_e($r['limit_services']); ?>"></div>
                                                        <div class="form-group" style="flex:1; min-width:110px;"><label class="form-label">حداقل برداشت</label><input type="text" name="min_withdraw" class="form-control" style="direction:ltr;" value="<?php echo res_e($r['min_withdraw']); ?>"></div>
                                                        <div class="form-group" style="flex:1; min-width:110px;"><label class="form-label">وضعیت</label>
                                                            <select name="status" class="form-control">
                                                                <option value="active" <?php echo $r['status'] === 'active' ? 'selected' : ''; ?>>فعال</option>
                                                                <option value="disabled" <?php echo $r['status'] !== 'active' ? 'selected' : ''; ?>>غیرفعال</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">محصولات مجاز</label>
                                                        <select name="allowed_products[]" class="form-control" multiple size="3">
                                                            <?php foreach ($products as $p): ?>
                                                                <option value="<?php echo res_e($p['code_product']); ?>" <?php echo in_array((string) $p['code_product'], array_map('strval', $allowedArr), true) ? 'selected' : ''; ?>><?php echo res_e($p['name_product'] ?? $p['code_product']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="btn btn-sm btn-primary"><?php echo icon('save', 'svg-icon svg-xs'); ?> ذخیره</button>
                                                </form>

                                                <div style="flex:1; min-width:220px; display:flex; flex-direction:column; gap:14px;">
                                                    <form method="post" action="resellers.php">
                                                        <input type="hidden" name="_csrf" value="<?php echo res_e($_csrf); ?>">
                                                        <input type="hidden" name="action" value="adjust">
                                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                        <label class="form-label">تنظیم موجودی</label>
                                                        <input type="text" name="amount" class="form-control" placeholder="مبلغ (تومان)" style="direction:ltr;">
                                                        <div style="display:flex; gap:6px; margin-top:6px;">
                                                            <button name="direction" value="credit" class="btn btn-sm btn-success">افزایش</button>
                                                            <button name="direction" value="debit" class="btn btn-sm btn-soft-warning">کسر</button>
                                                        </div>
                                                    </form>
                                                    <form method="post" action="resellers.php" onsubmit="return confirm('حذف نماینده؟ این عملیات قابل بازگشت نیست.');">
                                                        <input type="hidden" name="_csrf" value="<?php echo res_e($_csrf); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-soft-danger btn-block"><?php echo icon('trash', 'svg-icon svg-xs'); ?> حذف نماینده</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>
</body>
</html>
