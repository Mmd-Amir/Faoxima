<?php

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

$typeFilter = (string) ($_GET['type'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$ledgerLabels = [
    'topup'           => 'شارژ کیف پول',
    'purchase'        => 'خرید سرویس',
    'sale'            => 'فروش به مشتری',
    'withdraw'        => 'برداشت وجه',
    'withdraw_refund' => 'بازگشت برداشت',
    'admin_credit'    => 'افزایش توسط مدیر',
    'admin_debit'     => 'کسر توسط مدیر',
];

$where = "reseller_id = :rid";
$params = [':rid' => $rid];
if (isset($ledgerLabels[$typeFilter])) {
    $where .= " AND type = :type";
    $params[':type'] = $typeFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_ledger WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM reseller_ledger WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export of the current filtered ledger.
if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ledger.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['نوع', 'مبلغ', 'موجودی پس از تراکنش', 'توضیحات', 'مرجع', 'تاریخ']);
    $csvStmt = $pdo->prepare("SELECT * FROM reseller_ledger WHERE $where ORDER BY id DESC");
    $csvStmt->execute($params);
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $ledgerLabels[$r['type']] ?? $r['type'],
            (string) $r['amount'],
            (string) $r['balance_after'],
            (string) ($r['description'] ?? ''),
            (string) ($r['ref'] ?? ''),
            reseller_jdate($r['created_at']),
        ]);
    }
    fclose($out);
    exit;
}

// Lifetime summaries.
$sum = static function ($pdo, $rid, $type) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM reseller_ledger WHERE reseller_id = :id AND type = :t");
    $s->execute([':id' => $rid, ':t' => $type]);
    return (int) $s->fetchColumn();
};
$totalTopup = $sum($pdo, $rid, 'topup');
$totalSpent = abs($sum($pdo, $rid, 'purchase'));
$totalWithdraw = abs($sum($pdo, $rid, 'withdraw'));

reseller_layout_head('حسابداری', 'reports', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('chart-line', 'svg-icon svg-lg'); ?> حسابداری و گزارشات</div>
        <div class="page-head__sub">دفتر کل تراکنش‌های کیف پول</div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__icon icon-green"><?php echo icon('piggy-bank', 'svg-icon'); ?></div>
        <div class="stat-card__info"><span class="stat-card__value"><?php echo number_format($totalTopup); ?></span><span class="stat-card__label">مجموع شارژ</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-blue"><?php echo icon('cart-shopping', 'svg-icon'); ?></div>
        <div class="stat-card__info"><span class="stat-card__value"><?php echo number_format($totalSpent); ?></span><span class="stat-card__label">مجموع خرید</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-purple"><?php echo icon('coins', 'svg-icon'); ?></div>
        <div class="stat-card__info"><span class="stat-card__value"><?php echo number_format($totalWithdraw); ?></span><span class="stat-card__label">مجموع برداشت</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-rose"><?php echo icon('wallet', 'svg-icon'); ?></div>
        <div class="stat-card__info"><span class="stat-card__value"><?php echo number_format((int) $reseller['balance']); ?></span><span class="stat-card__label">موجودی فعلی</span></div>
    </div>
</div>

<div class="card mt-3">
    <div class="card__head">
        <div class="card__title"><?php echo icon('receipt', 'svg-icon svg-sm'); ?> دفتر کل</div>
        <a href="reports.php?type=<?php echo reseller_e($typeFilter); ?>&csv=1" class="btn btn-sm btn-outline">خروجی CSV</a>
        <div class="chip-row">
            <a href="reports.php" class="chip<?php echo $typeFilter === 'all' ? ' is-active' : ''; ?>"><span>همه</span></a>
            <a href="reports.php?type=topup" class="chip<?php echo $typeFilter === 'topup' ? ' is-active' : ''; ?>"><span>شارژ</span></a>
            <a href="reports.php?type=purchase" class="chip<?php echo $typeFilter === 'purchase' ? ' is-active' : ''; ?>"><span>خرید</span></a>
            <a href="reports.php?type=withdraw" class="chip<?php echo $typeFilter === 'withdraw' ? ' is-active' : ''; ?>"><span>برداشت</span></a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="app-table">
            <thead><tr><th>نوع</th><th>مبلغ</th><th>موجودی پس از تراکنش</th><th>توضیحات</th><th>مرجع</th><th>تاریخ</th></tr></thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align:center; padding:24px;">تراکنشی یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $amt = (int) $r['amount']; ?>
                        <tr>
                            <td><?php echo reseller_e($ledgerLabels[$r['type']] ?? $r['type']); ?></td>
                            <td style="direction:ltr; color:<?php echo $amt >= 0 ? 'var(--success,#22c55e)' : 'var(--danger,#ef4444)'; ?>;"><?php echo ($amt >= 0 ? '+' : '') . number_format($amt); ?></td>
                            <td style="direction:ltr;"><?php echo number_format((int) $r['balance_after']); ?></td>
                            <td><?php echo reseller_e($r['description'] ?? ''); ?></td>
                            <td style="direction:ltr;"><?php echo reseller_e($r['ref'] ?? '—'); ?></td>
                            <td><?php echo reseller_jdate($r['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="padding:14px; display:flex; gap:8px; justify-content:center;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>"
               href="reports.php?type=<?php echo reseller_e($typeFilter); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php
reseller_layout_foot();
