<?php

$allPassed = rx_requirement_checks_passed($requirementChecks);
$readyCount = count(array_filter($requirementChecks, static fn($check) => !empty($check['ok'])));
$totalCount = count($requirementChecks);
$readyPercent = $totalCount > 0 ? (int) round(($readyCount / $totalCount) * 100) : 0;
?>
<section class="view-panel is-active" id="step-requirements">
    <div class="panel-heading">
        <span class="section-icon"><svg><use href="#i-shield-check"/></svg></span>
        <div><span class="panel-kicker">مرحله اول</span><h2>بررسی پیش‌نیازهای سیستم</h2><p>سلامت محیط اجرا و دسترسی‌های لازم پیش از نصب بررسی می‌شود.</p></div>
    </div>
    <div class="readiness-summary">
        <div><strong><?php echo $readyCount; ?> از <?php echo $totalCount; ?> مورد آماده است</strong><span><?php echo $allPassed ? 'سرور برای نصب آماده است.' : 'موارد ناموفق را رفع و دوباره بررسی کنید.'; ?></span></div>
        <bdi><?php echo $readyPercent; ?>%</bdi>
    </div>
    <div class="readiness-track"><span style="width:<?php echo $readyPercent; ?>%"></span></div>
    <div class="requirements-list">
        <?php foreach ($requirementChecks as $check): ?>
            <div class="requirement-row <?php echo !empty($check['ok']) ? 'is-ok' : 'is-fail'; ?>">
                <span class="requirement-status"><svg><use href="<?php echo !empty($check['ok']) ? '#i-check' : '#i-x'; ?>"/></svg></span>
                <span class="requirement-copy"><strong><?php echo rx_escape_html($check['label']); ?></strong><small><?php echo rx_escape_html($check['detail']); ?></small></span>
                <span class="status-pill"><?php echo !empty($check['ok']) ? 'آماده' : 'نیازمند بررسی'; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="panel-actions">
        <form method="post"><input type="hidden" name="csrf_token" value="<?php echo rx_escape_html($csrfToken); ?>"><input type="hidden" name="rx_action" value="rescan"><button type="submit" class="btn btn-secondary"><svg><use href="#i-refresh"/></svg>اسکن مجدد</button></form>
        <form method="post" class="action-main"><input type="hidden" name="csrf_token" value="<?php echo rx_escape_html($csrfToken); ?>"><input type="hidden" name="rx_action" value="proceed"><button type="submit" class="btn btn-primary" <?php echo $allPassed ? '' : 'disabled'; ?>>ادامه نصب<svg><use href="#i-arrow-left"/></svg></button></form>
    </div>
</section>
