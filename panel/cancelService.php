<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

/* ---------- helpers ---------- */
function fx_table_has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
        $st->execute([':t' => $table, ':c' => $col]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) { return false; }
}
/* normalized status -> [label, badgeClass, filterKey] */
function fx_req_status($raw): array {
    $r = strtolower(trim((string)$raw));
    $raws = (string)$raw;
    if (in_array($r, ['unpaid','waiting','awaitinghash','pending'], true) || strpos($r,'wait')!==false || mb_strpos($raws,'در انتظار')!==false) return ['در انتظار', 'badge-warning', 'wait'];
    if ($r === 'paid' || $r === 'success' || mb_strpos($raws,'تایید')!==false || mb_strpos($raws,'موفق')!==false) return ['تاییدشده', 'badge-active', 'ok'];
    if ($r === 'reject' || $r === 'rejected' || mb_strpos($raws,'رد')!==false || mb_strpos($raws,'لغو')!==false) return ['ردشده', 'badge-block', 'reject'];
    if ($r === 'expire' || $r === 'expired' || mb_strpos($raws,'منقضی')!==false) return ['منقضی', 'badge-gray', 'expire'];
    return [$raws !== '' ? $raws : '—', 'badge-gray', 'other'];
}
/* request "type" label from id_order + method */
function fx_req_type($idOrder, $method): string {
    $io = (string)$idOrder; $m = strtolower(trim((string)$method));
    if ($m === 'cart to cart' || $m === 'carttocart_pv') return '💳 رسید بانکی';
    if ($m === 'add balance by admin' || $m === 'low balance by admin') return '🛠 تنظیم کیف پول (ادمین)';
    if (in_array($m, ['plisio','nowpayment','digitaltron','arze digital offline'], true)) return '🪙 ارز دیجیتال';
    if ($m === 'star telegram') return '⭐ استارز تلگرام';
    if (strpos($io,'Add_Balance')!==false) return '💰 افزایش موجودی';
    if (strpos($io,'getconfigafterpay')!==false) return '🛒 خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return '♻️ تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return '📶 حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return '⏳ زمان اضافه';
    if ($m !== '') return '💼 پرداخت (' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ')';
    return '💼 پرداخت';
}
function fx_purpose($idOrder): string {
    $io = (string)$idOrder;
    if (strpos($io,'Add_Balance')!==false) return 'افزایش موجودی کیف پول';
    if (strpos($io,'getconfigafterpay')!==false) return 'خرید سرویس';
    if (strpos($io,'getextenduser')!==false) return 'تمدید سرویس';
    if (strpos($io,'getextravolumeuser')!==false) return 'حجم اضافه';
    if (strpos($io,'getextratimeuser')!==false) return 'زمان اضافه';
    return '';
}

$flash = ['ok' => '', 'err' => ''];

/* ---------- delete actions ---------- */
// legacy: delete a cancel_service row
if (isset($_GET['removeid']) && $_GET['removeid'] !== '') {
    try {
        $stmt = $pdo->prepare("DELETE FROM cancel_service WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['removeid']]);
    } catch (\Throwable $e) {}
    header("Location: cancelService.php");
    exit;
}
// delete a resolved (reject/expire) payment request record (cleanup only)
if (isset($_GET['delpr']) && $_GET['delpr'] !== '') {
    try {
        $cur = $pdo->prepare("SELECT payment_Status FROM Payment_report WHERE id = :id LIMIT 1");
        $cur->execute([':id' => (int)$_GET['delpr']]);
        $st = strtolower((string)$cur->fetchColumn());
        if (in_array($st, ['reject','rejected','expire','expired'], true)) {
            $pdo->prepare("DELETE FROM Payment_report WHERE id = :id")->execute([':id' => (int)$_GET['delpr']]);
        }
    } catch (\Throwable $e) {}
    header("Location: cancelService.php");
    exit;
}

/* ---------- aggregate all requests ---------- */
$prHasSource = fx_table_has_col($pdo, 'Payment_report', 'source');
$rows = [];

// 1) Payment_report — admin-actionable money requests (bank receipts, wallet charges, crypto, gateway)
try {
    $st = $pdo->query("SELECT * FROM Payment_report ORDER BY id DESC LIMIT 1000");
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $method = (string)($r['Payment_Method'] ?? '');
        // skip purely automatic/empty rows with no actionable method
        [$stLabel, $stClass, $stKey] = fx_req_status($r['payment_Status'] ?? '');
        $src = $prHasSource ? (string)($r['source'] ?? '') : '';
        $purpose = fx_purpose($r['id_order'] ?? '');
        $details = [];
        if ($method !== '') $details[] = 'روش: ' . $method;
        if ($purpose !== '') $details[] = $purpose;
        if (!empty($r['id_invoice'])) $details[] = 'فاکتور: ' . $r['id_invoice'];
        if (!empty($r['dec_not_confirmed'])) $details[] = (string)$r['dec_not_confirmed'];
        $rows[] = [
            'sortid'  => 2000000000 + (int)($r['id'] ?? 0),
            'idlabel' => '#' . (int)($r['id'] ?? 0),
            'type'    => fx_req_type($r['id_order'] ?? '', $method),
            'source'  => ($src === 'miniapp') ? 'مینی‌اپ' : 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => implode(' — ', $details),
            'amount'  => (int)($r['price'] ?? 0),
            'date'    => (string)($r['time'] ?? ''),
            'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
            'del'     => in_array($stKey, ['reject','expire'], true) ? ('cancelService.php?delpr=' . (int)($r['id'] ?? 0)) : '',
        ];
    }
} catch (\Throwable $e) { $flash['err'] = 'بارگذاری درخواست‌های پرداخت ناموفق: ' . $e->getMessage(); }

// 2) cancel_service — service cancel requests
try {
    $st = $pdo->query("SELECT * FROM cancel_service ORDER BY id DESC");
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        [$stLabel, $stClass, $stKey] = fx_req_status($r['status'] ?? '');
        $rows[] = [
            'sortid'  => 1000000000 + (int)($r['id'] ?? 0),
            'idlabel' => 'C' . (int)($r['id'] ?? 0),
            'type'    => '🚫 لغو سرویس',
            'source'  => 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => 'سرویس: ' . (string)($r['username'] ?? '') . (trim((string)($r['description'] ?? '')) !== '' ? ' — ' . (string)$r['description'] : ''),
            'amount'  => 0,
            'date'    => '',
            'stLabel' => $stLabel, 'stClass' => $stClass, 'stKey' => $stKey,
            'del'     => 'cancelService.php?removeid=' . (int)($r['id'] ?? 0),
        ];
    }
} catch (\Throwable $e) {}

// 3) national-net refund requests (invoice waiting for admin approval)
try {
    $st = $pdo->query("SELECT id_invoice, id_user, username, name_product, price_product FROM invoice WHERE Status = 'nm_refund_pending' ORDER BY id DESC LIMIT 500");
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $rows[] = [
            'sortid'  => 1500000000,
            'idlabel' => (string)($r['id_invoice'] ?? ''),
            'type'    => '↩️ بازگشت وجه (نت ملی)',
            'source'  => 'ربات',
            'user'    => (string)($r['id_user'] ?? ''),
            'details' => 'سرویس: ' . (string)($r['username'] ?? '') . ' — ' . (string)($r['name_product'] ?? ''),
            'amount'  => (int)($r['price_product'] ?? 0),
            'date'    => '',
            'stLabel' => 'در انتظار', 'stClass' => 'badge-warning', 'stKey' => 'wait',
            'del'     => '',
        ];
    }
} catch (\Throwable $e) {}

// newest first
usort($rows, function ($a, $b) { return $b['sortid'] <=> $a['sortid']; });
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>لیست درخواست‌ها | ربات فاکسیما</title>
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
                    <div class="page-head__title">
                        <?php echo icon('list', 'svg-icon svg-lg'); ?>
                        لیست درخواست‌ها
                    </div>
                    <div class="page-head__sub">همه‌ی درخواست‌های کاربران (رسید بانکی، افزایش موجودی، ارز دیجیتال، تمدید، حجم/زمان اضافه، لغو سرویس و بازگشت وجه) از ربات و مینی‌اپ</div>
                </div>
            </div>

            <?php if ($flash['err']): ?>
                <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="table-wrap">
                    <table id="requestsTable" class="display app-table" style="width:100%" data-mdt-filter="1,2,7">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نوع درخواست</th>
                                <th>منبع</th>
                                <th>آیدی کاربر</th>
                                <th>جزئیات</th>
                                <th>مبلغ (تومان)</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td data-label="شناسه" style="direction:ltr; text-align:right;"><?php echo htmlspecialchars($row['idlabel'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="نوع درخواست" data-filter-value="<?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="منبع" data-filter-value="<?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?>"><span class="badge badge-info"><?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="آیدی کاربر">
                                    <?php if ($row['user'] !== ''): ?>
                                        <a href="user.php?id=<?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?>" class="text-link"><?php echo htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td data-label="جزئیات" style="max-width:340px;"><?php echo htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="مبلغ (تومان)"><?php echo $row['amount'] > 0 ? number_format($row['amount']) : '—'; ?></td>
                                <td data-label="تاریخ" style="direction:ltr; text-align:right; font-size:11.5px;"><?php echo $row['date'] !== '' ? htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="وضعیت" data-filter-value="<?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?>"><span class="badge <?php echo $row['stClass']; ?>"><?php echo htmlspecialchars($row['stLabel'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <?php if ($row['del'] !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($row['del'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-soft-danger" onclick="return confirm('این درخواست حذف شود؟')">
                                            <?php echo icon('trash', 'svg-icon'); ?> حذف
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="page-head__sub" style="margin-top:12px;">
                    💡 تأیید/رد نهاییِ پرداخت‌ها و افزایش/کاهش موجودی همچنان از داخل ربات انجام می‌شود (تا حساب‌داری دوبار اعمال نشود)؛ این صفحه همه‌ی درخواست‌ها را برای مشاهده، جست‌وجو و فیلتر یک‌جا نمایش می‌دهد.
                </div>
            </div>

        </div>
    </section>
</section>
<script src="js/datatable.js" defer></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    FaoximaDT.init("#requestsTable");
  });
</script>
</body>
</html>
