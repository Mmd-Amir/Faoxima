<?php

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

$activeServices = reseller_count_active_services($rid);
$totalServices = (int) $pdo->query("SELECT COUNT(*) FROM reseller_service WHERE reseller_id = " . $rid)->fetchColumn();
$pendingWithdraw = (int) $pdo->query("SELECT COUNT(*) FROM reseller_withdraw WHERE reseller_id = " . $rid . " AND status = 'pending'")->fetchColumn();
$allowedCount = count(reseller_allowed_products($reseller));

// Lifetime totals from the ledger.
$totTop = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM reseller_ledger WHERE reseller_id = :id AND type = 'topup'");
$totTop->execute([':id' => $rid]);
$lifetimeTopup = (int) $totTop->fetchColumn();

$totBuy = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM reseller_ledger WHERE reseller_id = :id AND type = 'purchase'");
$totBuy->execute([':id' => $rid]);
$lifetimeSpent = abs((int) $totBuy->fetchColumn());

$recent = $pdo->prepare("SELECT * FROM reseller_ledger WHERE reseller_id = :id ORDER BY id DESC LIMIT 8");
$recent->execute([':id' => $rid]);
$recentRows = $recent->fetchAll(PDO::FETCH_ASSOC);

// Weekly purchase chart (last 7 days, Jalali day labels).
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $dayStart = strtotime('today -' . $i . ' days');
    $dayEnd = $dayStart + 86400;
    $weekDays[] = ['start' => $dayStart, 'end' => $dayEnd, 'count' => 0, 'sum' => 0];
}
$weekStmt = $pdo->prepare("SELECT amount, created_at FROM reseller_ledger WHERE reseller_id = :id AND type = 'purchase' AND created_at >= :from");
$weekStmt->execute([':id' => $rid, ':from' => (string) $weekDays[0]['start']]);
while ($w = $weekStmt->fetch(PDO::FETCH_ASSOC)) {
    $ts = (int) ($w['created_at'] ?? 0);
    foreach ($weekDays as &$d) {
        if ($ts >= $d['start'] && $ts < $d['end']) {
            $d['count']++;
            $d['sum'] += (int) $w['amount'];
            break;
        }
    }
}
unset($d);
$maxCount = max(1, max(array_column($weekDays, 'count')));

$ledgerLabels = [
    'topup'           => 'شارژ کیف پول',
    'purchase'        => 'خرید سرویس',
    'withdraw'        => 'برداشت وجه',
    'withdraw_refund' => 'بازگشت برداشت',
    'admin_credit'    => 'افزایش توسط مدیر',
    'admin_debit'     => 'کسر توسط مدیر',
];

reseller_layout_head('داشبورد', 'index', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('home', 'svg-icon svg-lg'); ?> داشبورد نمایندگی</div>
        <div class="page-head__sub">خلاصه وضعیت کیف پول و فروش شما</div>
    </div>
    <div class="chip-row">
        <a href="service_new.php" class="chip"><?php echo icon('cart-shopping', 'svg-icon svg-sm'); ?><span>ساخت سرویس</span></a>
        <a href="wallet.php" class="chip"><?php echo icon('wallet', 'svg-icon svg-sm'); ?><span>شارژ کیف پول</span></a>
    </div>
</div>

<?php reseller_flash_render(); ?>

<div class="welcome-banner">
    <div class="welcome-banner__copy">
        <div class="welcome-banner__eyebrow"><?php echo icon('sparkles', 'svg-icon svg-xs'); ?> فضای اختصاصی نمایندگی</div>
        <h2>فروش سرویس‌ها را با یک نگاه مدیریت کنید</h2>
        <p>موجودی، سفارش‌ها و اشتراک‌های مشتریان شما همیشه در دسترس است.</p>
    </div>
    <a href="service_new.php" class="btn welcome-banner__action"><?php echo icon('arrow-left', 'svg-icon svg-sm'); ?> ساخت سرویس جدید</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__icon icon-green"><?php echo icon('wallet', 'svg-icon'); ?></div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format((float) $reseller['balance']); ?></span>
            <span class="stat-card__label">موجودی کیف پول (تومان)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-blue"><?php echo icon('package', 'svg-icon'); ?></div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($activeServices); ?></span>
            <span class="stat-card__label">سرویس‌های فعال</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-purple"><?php echo icon('chart-line', 'svg-icon'); ?></div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($lifetimeSpent); ?></span>
            <span class="stat-card__label">مجموع خرید (تومان)</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon icon-rose"><?php echo icon('coins', 'svg-icon'); ?></div>
        <div class="stat-card__info">
            <span class="stat-card__value"><?php echo number_format($pendingWithdraw); ?></span>
            <span class="stat-card__label">برداشت‌های در انتظار</span>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card__head">
        <div class="card__title"><?php echo icon('chart-bar', 'svg-icon svg-sm'); ?> فروش ۷ روز اخیر</div>
        <span class="chip"><?php echo array_sum(array_column($weekDays, 'count')); ?> سرویس</span>
    </div>
    <div style="padding:16px 20px;">
        <div style="display:flex; align-items:flex-end; gap:10px; height:150px;">
            <?php foreach ($weekDays as $d): ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; height:100%; justify-content:flex-end;">
                    <span style="font-size:11px; color:var(--text-muted,#8a8a9a);"><?php echo $d['count'] > 0 ? $d['count'] : ''; ?></span>
                    <div style="width:100%; max-width:44px; height:<?php echo max(6, (int) round($d['count'] / $maxCount * 100)); ?>%; background:linear-gradient(180deg,var(--accent,#3b82f6),color-mix(in srgb,var(--accent,#3b82f6) 45%,transparent)); border-radius:6px 6px 2px 2px;"></div>
                    <span style="font-size:11px; color:var(--text-muted,#8a8a9a);"><?php echo reseller_jdate($d['start'], 'd/m'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card__head">
        <div class="card__title"><?php echo icon('receipt', 'svg-icon svg-sm'); ?> آخرین تراکنش‌ها</div>
        <a href="reports.php" class="btn btn-sm btn-outline">مشاهده همه</a>
    </div>
    <div class="table-wrap">
        <table class="app-table">
            <thead>
                <tr>
                    <th>نوع</th>
                    <th>مبلغ</th>
                    <th>موجودی پس از تراکنش</th>
                    <th>توضیحات</th>
                    <th>تاریخ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recentRows): ?>
                    <tr><td colspan="5" style="text-align:center; padding:24px;">هنوز تراکنشی ثبت نشده است.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentRows as $row): ?>
                        <?php $amt = (int) $row['amount']; ?>
                        <tr>
                            <td><?php echo reseller_e($ledgerLabels[$row['type']] ?? $row['type']); ?></td>
                            <td style="direction:ltr; color:<?php echo $amt >= 0 ? 'var(--success,#22c55e)' : 'var(--danger,#ef4444)'; ?>;">
                                <?php echo ($amt >= 0 ? '+' : '') . number_format($amt); ?>
                            </td>
                            <td style="direction:ltr;"><?php echo number_format((int) $row['balance_after']); ?></td>
                            <td><?php echo reseller_e($row['description'] ?? ''); ?></td>
                            <td><?php echo reseller_jdate($row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="info-grid mt-3" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
    <div class="card"><div class="card__head"><div class="card__title">محصولات قابل فروش</div></div><div style="padding:16px; font-size:24px; font-weight:700;"><?php echo number_format($allowedCount); ?></div></div>
    <div class="card"><div class="card__head"><div class="card__title">کل سرویس‌ها</div></div><div style="padding:16px; font-size:24px; font-weight:700;"><?php echo number_format($totalServices); ?></div></div>
    <div class="card"><div class="card__head"><div class="card__title">مجموع شارژ</div></div><div style="padding:16px; font-size:24px; font-weight:700;"><?php echo number_format($lifetimeTopup); ?></div></div>
</div>

<?php
reseller_layout_foot();
