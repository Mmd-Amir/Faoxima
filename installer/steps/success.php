<section class="view-panel success-panel is-active" data-cleanup-url="index.php" data-cleanup-token="<?php echo rx_escape_html($csrfToken); ?>">
    <div class="success-mark"><svg><use href="#i-check"/></svg></div>
    <span class="panel-kicker">راه‌اندازی کامل شد</span>
    <h2>نصب با موفقیت تکمیل شد</h2>
    <p><?php echo $botUsername !== '' ? '@' . rx_escape_html($botUsername) . ' آماده استفاده است.' : 'ربات شما آماده استفاده است.'; ?></p>
    <div class="result-list">
        <?php foreach ($successMessages as $message): ?>
            <div><span><svg><use href="#i-check"/></svg></span><?php echo rx_escape_html($message); ?></div>
        <?php endforeach; ?>
    </div>
    <div class="cleanup-state" id="cleanup-state"><svg><use href="#i-cog"/></svg><span>در حال پاک‌سازی امن فایل‌های اینستالر…</span></div>
    <?php if ($botUsername !== ''): ?>
        <a class="btn btn-primary success-action" href="https://t.me/<?php echo rawurlencode($botUsername); ?>" rel="noopener"><svg><use href="#i-telegram"/></svg>باز کردن ربات</a>
    <?php endif; ?>
</section>
