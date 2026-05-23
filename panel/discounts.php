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

$flash = ['ok' => '', 'err' => ''];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $code   = trim((string)($_POST['codeDiscount']  ?? ''));
        $type   = (string)($_POST['type']               ?? 'percent');
        $value  = (string)($_POST['price']              ?? '0');
        $limit  = (int)($_POST['limitDiscount']         ?? 0);
        $agent  = (string)($_POST['agent']              ?? 'all');
        $first  = !empty($_POST['usefirst']) ? '1' : '0';
        $oneper = !empty($_POST['useuser'])  ? '1' : '0';


        $errors = [];


        if ($code === '') {
            $errors[] = 'کد تخفیف نمی‌تواند خالی باشد.';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)) {
            $errors[] = 'کد تخفیف فقط شامل حروف انگلیسی، عدد، خط تیره و آندرلاین (۲ تا ۴۰ کاراکتر).';
        }


        if (!in_array($type, ['percent', 'amount', 'free'], true)) {
            $errors[] = 'نوع تخفیف نامعتبر است.';
        }


        $numericValue = (float)$value;
        if ($type === 'percent') {
            if ($numericValue < 0 || $numericValue > 100) {
                $errors[] = 'درصد تخفیف باید بین ۰ تا ۱۰۰ باشد.';
            }
        } elseif ($type === 'amount') {
            if ($numericValue < 0) {
                $errors[] = 'مبلغ تخفیف نمی‌تواند منفی باشد.';
            } elseif ($numericValue > 100000000) {
                $errors[] = 'مبلغ تخفیف بیش از حد بزرگ است.';
            }
        }


        if ($limit < 0) {
            $errors[] = 'سقف کل استفاده نمی‌تواند منفی باشد.';
        }


        if (!in_array($agent, ['all', 'f', 'n', 'n2'], true)) {
            $errors[] = 'گروه هدف نامعتبر است.';
        }


        if (empty($errors)) {
            try {
                $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c LIMIT 1");
                $dup->execute([':c' => $code]);
                if ($dup->fetchColumn()) {
                    $errors[] = 'این کد تخفیف از قبل ثبت شده است.';
                }
            } catch (\Throwable $e) {  }
        }

        if (!empty($errors)) {
            $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO DiscountSell
                     (codeDiscount, price, limitDiscount, agent, usefirst, useuser, code_product, code_panel, time, type, usedDiscount)
                     VALUES (:c, :p, :l, :a, :f, :u, '0', '0', :t, :ty, '0')"
                );
                $stmt->execute([
                    ':c' => $code,
                    ':p' => $value,
                    ':l' => (string)$limit,
                    ':a' => $agent,
                    ':f' => $first,
                    ':u' => $oneper,
                    ':t' => (string)time(),
                    ':ty'=> $type,
                ]);
                $flash['ok'] = 'کد تخفیف افزوده شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'خطا: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM DiscountSell WHERE id = :id")->execute([':id' => $id]);
                $flash['ok'] = 'کد تخفیف حذف شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'reset_usage') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("UPDATE DiscountSell SET usedDiscount = '0' WHERE id = :id")->execute([':id' => $id]);
                $flash['ok'] = 'شمارنده استفاده صفر شد.';
            } catch (\Throwable $e) {
                $flash['err'] = 'بازنشانی ناموفق: ' . $e->getMessage();
            }
        }
    }
}


$list = [];
try {
    $r = $pdo->query("SELECT * FROM DiscountSell ORDER BY id DESC");
    if ($r) $list = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $flash['err'] = 'بارگذاری ناموفق: ' . $e->getMessage();
}

function faoxima_d_label_type($t) {
    switch ($t) {
        case 'percent': return ['درصدی', 'badge-success'];
        case 'amount':  return ['مبلغی', 'badge-info'];
        case 'free':    return ['رایگان', 'badge-purple'];
        default:        return [htmlspecialchars((string)$t, ENT_QUOTES), 'badge-gray'];
    }
}
function faoxima_d_label_agent($a) {
    switch ($a) {
        case 'f':   return ['کاربر عادی', 'badge-info'];
        case 'n':   return ['نماینده', 'badge-purple'];
        case 'n2':  return ['نماینده+', 'badge-warning'];
        case 'all': return ['همه', 'badge-success'];
        default:    return [htmlspecialchars((string)$a, ENT_QUOTES), 'badge-gray'];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>کدهای تخفیف | پنل فاکسیما</title>
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
                        <?php echo icon('ticket', 'svg-icon svg-lg'); ?>
                        کدهای تخفیف
                    </div>
                    <div class="page-head__sub">مدیریت کدهای تخفیف و هدیه</div>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openModal('modal-add-discount')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کد جدید</span>
                    </button>
                </div>
            </div>

            <?php if ($flash['ok']): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="table-wrap">
                    <table id="discountsTable" class="display app-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>مقدار</th>
                                <th>سقف کل</th>
                                <th>تعداد استفاده</th>
                                <th>گروه هدف</th>
                                <th>محدودیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px 0; color:var(--text-muted);">
                                هنوز کدی ثبت نشده است.
                            </td></tr>
                        <?php else: foreach ($list as $d):
                            [$typeLabel, $typeBadge] = faoxima_d_label_type($d['type'] ?? '');
                            [$agentLabel, $agentBadge] = faoxima_d_label_agent($d['agent'] ?? '');
                            $valDisplay = htmlspecialchars((string)$d['price'], ENT_QUOTES);
                            if ($d['type'] === 'percent') $valDisplay .= '%';
                            elseif ($d['type'] === 'amount') $valDisplay = number_format((int)$d['price']) . ' <small>تومان</small>';
                            $limit = (int)$d['limitDiscount'];
                            $used  = (int)$d['usedDiscount'];
                            $remaining = $limit > 0 ? max(0, $limit - $used) : -1;
                        ?>
                            <tr>
                                <td data-label="کد"><code style="direction:ltr; background:var(--accent-soft); color:var(--accent); padding:4px 8px; border-radius:6px; font-weight:700;"><?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع"><span class="badge <?php echo $typeBadge; ?>"><?php echo $typeLabel; ?></span></td>
                                <td data-label="مقدار"><?php echo $valDisplay; ?></td>
                                <td data-label="سقف کل"><?php echo $limit > 0 ? $limit : '<span class="text-muted">نامحدود</span>'; ?></td>
                                <td data-label="تعداد استفاده">
                                    <b style="color:<?php echo ($remaining === 0) ? 'var(--color-danger)' : 'var(--color-success)'; ?>;"><?php echo $used; ?></b>
                                    <?php if ($limit > 0): ?>/ <?php echo $limit; ?><?php endif; ?>
                                </td>
                                <td data-label="گروه هدف"><span class="badge <?php echo $agentBadge; ?>"><?php echo $agentLabel; ?></span></td>
                                <td data-label="محدودیت">
                                    <?php if ($d['usefirst'] == '1'): ?>
                                        <span class="badge badge-warning">فقط خرید اول</span>
                                    <?php endif; ?>
                                    <?php if ($d['useuser'] == '1'): ?>
                                        <span class="badge badge-info">یک بار/کاربر</span>
                                    <?php endif; ?>
                                    <?php if ($d['usefirst'] != '1' && $d['useuser'] != '1'): ?>
                                        <span class="text-muted">نامحدود</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        <form method="POST" style="display:inline" onsubmit="return confirm('شمارنده استفاده این کد صفر شود؟');">
                                            <input type="hidden" name="_action" value="reset_usage">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="بازنشانی شمارنده استفاده">
                                                <?php echo icon('rotate-left', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('کد <?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?> حذف شود؟');">
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                            </button>
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


<div id="modal-add-discount" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کد تخفیف جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-discount')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <input type="hidden" name="_action" value="add">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد تخفیف</label>
                    <input type="text" name="codeDiscount" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace; text-transform:uppercase;" placeholder="FAOXIMA20" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع تخفیف</label>
                    <select name="type" class="form-control" required onchange="updateValueHint(this.value)">
                        <option value="percent">درصدی (٪)</option>
                        <option value="amount">مبلغی (تومان)</option>
                        <option value="free">رایگان (۱۰۰٪)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="valueLabel">مقدار تخفیف (٪)</label>
                    <input type="number" name="price" class="form-control" placeholder="20" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="limitDiscount" class="form-control" placeholder="100" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">گروه هدف</label>
                <select name="agent" class="form-control">
                    <option value="all">همه کاربران</option>
                    <option value="f">فقط کاربر عادی</option>
                    <option value="n">فقط نمایندگان</option>
                    <option value="n2">فقط نمایندگان پیشرفته</option>
                </select>
            </div>

            <div class="form-group" style="display:flex; flex-direction:column; gap:8px;">
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="usefirst" value="1">
                    فقط برای خرید اول کاربر قابل استفاده باشد
                </label>
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="useuser" value="1">
                    هر کاربر فقط یک بار بتواند استفاده کند
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                    افزودن کد
                </button>
            </div>
        </form>
    </div>
</div>

<script src="js/datatable.js" defer>

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    FaoximaDT.init('#discountsTable');
});

function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function updateValueHint(type) {
    var lbl = document.getElementById('valueLabel');
    if (type === 'percent') lbl.textContent = 'مقدار تخفیف (٪)';
    else if (type === 'amount') lbl.textContent = 'مقدار تخفیف (تومان)';
    else lbl.textContent = 'مقدار (استفاده‌نمی‌شود)';
}
</script>
</body>
</html>


