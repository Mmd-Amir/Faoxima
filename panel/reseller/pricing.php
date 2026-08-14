<?php

/**
 * Reseller customer-facing sell prices.
 *
 * For each product the admin flagged sellable, the reseller can set the price
 * their own customers pay (stored in reseller_product_sell). When no sell
 * price is set, the product's default price is used. The reseller's own cost
 * (what the admin charges) is shown for reference so they can keep a margin.
 */

require_once __DIR__ . '/layout.php';

$reseller = reseller_require_login();
$pdo = $GLOBALS['pdo'];
$rid = (int) $reseller['id'];

$products = reseller_allowed_products($reseller);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reseller_csrf_check();
    $prices = $_POST['price'] ?? [];
    if (is_array($prices)) {
        $allowedCodes = array_map(static function ($p) {
            return (string) ($p['code_product'] ?? '');
        }, $products);
        $up = $pdo->prepare(
            "INSERT INTO reseller_product_sell (reseller_id, product_code, sell_price)
             VALUES (:r, :c, :p)
             ON DUPLICATE KEY UPDATE sell_price = VALUES(sell_price)"
        );
        $del = $pdo->prepare("DELETE FROM reseller_product_sell WHERE reseller_id = :r AND product_code = :c");
        foreach ($prices as $code => $val) {
            $code = (string) $code;
            if (!in_array($code, $allowedCodes, true)) {
                continue;
            }
            $val = (int) preg_replace('/[^0-9]/', '', (string) $val);
            if ($val > 0) {
                $up->execute([':r' => $rid, ':c' => $code, ':p' => $val]);
            } else {
                $del->execute([':r' => $rid, ':c' => $code]);
            }
        }
    }
    reseller_flash_set('success', 'قیمت‌های فروش ذخیره شد.');
    header('Location: pricing.php');
    exit;
}

// Current overrides keyed by product_code.
$overrides = [];
$rows = $pdo->prepare("SELECT product_code, sell_price FROM reseller_product_sell WHERE reseller_id = :r");
$rows->execute([':r' => $rid]);
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $overrides[(string) $r['product_code']] = (int) $r['sell_price'];
}

reseller_layout_head('قیمت فروش', 'pricing', $reseller);
?>
<div class="page-head">
    <div>
        <div class="page-head__title"><?php echo icon('money-bill', 'svg-icon svg-lg'); ?> قیمت فروش به مشتری</div>
        <div class="page-head__sub">قیمت فروش به مشتری و حاشیه سود خود را تعیین کنید</div>
    </div>
</div>

<?php reseller_flash_render(); ?>

<?php if (!$products): ?>
    <div class="alert alert-info">
        <?php echo icon('circle-info', 'svg-icon'); ?>
        <span>هنوز محصولی برای فروش توسط مدیر فعال نشده است.</span>
    </div>
<?php else: ?>
<form method="post" action="pricing.php">
    <?php echo reseller_csrf_field(); ?>
    <div class="card mt-1">
        <div class="table-wrap">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>محصول</th>
                        <th>حجم / مدت</th>
                        <th>قیمت تمام‌شده شما</th>
                        <th>قیمت فروش به مشتری (تومان)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $code = (string) ($p['code_product'] ?? '');
                        $cost = reseller_product_price($p);
                        $vol = (float) ($p['Volume_constraint'] ?? 0);
                        $days = (int) ($p['Service_time'] ?? 0);
                        $volTxt = $vol > 0 ? (rtrim(rtrim(number_format($vol, 2), '0'), '.') . ' گیگ') : 'نامحدود';
                        $daysTxt = $days > 0 ? ($days . ' روز') : 'نامحدود';
                        $cur = $overrides[$code] ?? '';
                        ?>
                        <tr>
                            <td><b><?php echo reseller_e((string) ($p['name_product'] ?? $code)); ?></b></td>
                            <td><?php echo reseller_e($volTxt . ' / ' . $daysTxt); ?></td>
                            <td><?php echo number_format($cost); ?> ت</td>
                            <td style="min-width:180px;">
                                <input type="number" name="price[<?php echo reseller_e($code); ?>]" min="0" step="1000"
                                       class="form-control" dir="ltr"
                                       value="<?php echo $cur === '' ? '' : (int) $cur; ?>"
                                       placeholder="پیش‌فرض: <?php echo number_format((int) round((float) ($p['price_product'] ?? 0))); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">
        <?php echo icon('save', 'svg-icon svg-sm'); ?> ذخیره قیمت‌ها
    </button>
</form>
<?php endif; ?>

<?php reseller_layout_foot(); ?>
