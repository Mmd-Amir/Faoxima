<section class="view-panel is-active" id="step-install">
    <form id="installer-form" method="post" novalidate>
        <input type="hidden" name="rx_action" value="install">
        <input type="hidden" name="csrf_token" value="<?php echo rx_escape_html($csrfToken); ?>">

        <div class="form-stage is-active" data-form-stage="2">
            <div class="panel-heading">
                <span class="section-icon"><svg><use href="#i-telegram"/></svg></span>
                <div><span class="panel-kicker">مرحله دوم</span><h2>اتصال به تلگرام</h2><p>توکن ربات و شناسه مدیر اصلی را وارد کنید. توکن پس از ارسال دوباره نمایش داده نمی‌شود.</p></div>
            </div>
            <div class="field-grid">
                <div class="form-field">
                    <label for="admin_id">آیدی عددی مدیر</label>
                    <div class="input-shell"><svg><use href="#i-user"/></svg><input type="text" inputmode="numeric" autocomplete="off" id="admin_id" name="admin_id" placeholder="123456789" value="<?php echo rx_escape_html($formValues['admin_id'] ?? ''); ?>" pattern="[0-9]{6,12}" required></div>
                    <small>آیدی عددی را از <bdi>@userinfobot</bdi> دریافت کنید و با همین حساب ربات را Start کنید.</small>
                </div>
                <div class="form-field">
                    <label for="tg_bot_token">توکن ربات</label>
                    <div class="input-shell"><svg><use href="#i-key"/></svg><input type="password" autocomplete="new-password" id="tg_bot_token" name="tg_bot_token" placeholder="توکن دریافتی از BotFather" required><button type="button" class="icon-button password-toggle" data-password-toggle="tg_bot_token" aria-label="نمایش توکن"><svg><use href="#i-eye"/></svg></button></div>
                    <small>توکن از <bdi>@BotFather</bdi> دریافت می‌شود و در خلاصه نصب نمایش داده نخواهد شد.</small>
                </div>
            </div>
        </div>

        <div class="form-stage" data-form-stage="3">
            <div class="panel-heading">
                <span class="section-icon"><svg><use href="#i-database"/></svg></span>
                <div><span class="panel-kicker">مرحله سوم</span><h2>اتصال به دیتابیس</h2><p>اطلاعات دیتابیس و کاربری را که از قبل در cPanel ساخته‌اید وارد کنید. نام دیتابیس و نام کاربری را کامل و همراه با پیشوند حساب وارد کنید.</p></div>
            </div>
            <div class="field-grid two-columns">
                <div class="form-field"><label for="database_host">میزبان دیتابیس</label><div class="input-shell"><svg><use href="#i-server"/></svg><input type="text" id="database_host" name="database_host" value="<?php echo rx_escape_html($formValues['database_host'] ?? (getenv('DB_HOST') ?: 'localhost')); ?>" placeholder="localhost" required></div></div>
                <div class="form-field"><label for="database_name">نام دیتابیس</label><div class="input-shell"><svg><use href="#i-database"/></svg><input type="text" id="database_name" name="database_name" value="<?php echo rx_escape_html($formValues['database_name'] ?? ''); ?>" placeholder="faoxima" pattern="[A-Za-z0-9_-]{1,64}" required></div></div>
                <div class="form-field"><label for="database_username">نام کاربری دیتابیس</label><div class="input-shell"><svg><use href="#i-user"/></svg><input type="text" autocomplete="username" id="database_username" name="database_username" value="<?php echo rx_escape_html($formValues['database_username'] ?? ''); ?>" placeholder="database_user" required></div></div>
                <div class="form-field"><label for="database_password">رمز عبور دیتابیس</label><div class="input-shell"><svg><use href="#i-lock"/></svg><input type="password" autocomplete="new-password" id="database_password" name="database_password" placeholder="رمز عبور دیتابیس" required><button type="button" class="icon-button password-toggle" data-password-toggle="database_password" aria-label="نمایش رمز عبور"><svg><use href="#i-eye"/></svg></button></div></div>
            </div>
        </div>

        <div class="form-stage" data-form-stage="4">
            <div class="panel-heading">
                <span class="section-icon"><svg><use href="#i-rocket"/></svg></span>
                <div><span class="panel-kicker">مرحله چهارم</span><h2>مرور و شروع نصب</h2><p>اطلاعات غیرحساس را مرور کنید. عملیات بحرانی فقط پس از تأیید واقعی موفق اعلام می‌شوند.</p></div>
            </div>
            <div class="form-field webhook-field">
                <label for="bot_address_webhook">آدرس وب‌هوک ربات</label>
                <div class="input-shell"><svg><use href="#i-link"/></svg><input type="url" id="bot_address_webhook" name="bot_address_webhook" value="<?php echo rx_escape_html($formValues['bot_address_webhook'] ?? $defaultWebhookAddress); ?>" placeholder="https://example.com/bot/index.php" required></div>
                <small>این آدرس باید عمومی، دارای HTTPS معتبر و به فایل اصلی <bdi>index.php</bdi> ربات منتهی شود.</small>
            </div>
            <div class="review-list">
                <div><span>ربات تلگرام</span><strong id="review-bot">پس از اعتبارسنجی توسط سرور</strong></div>
                <div><span>مدیر</span><strong id="review-admin">—</strong></div>
                <div><span>دیتابیس</span><strong id="review-database">—</strong></div>
                <div><span>میزبان دیتابیس</span><strong id="review-host">—</strong></div>
                <div><span>وب‌هوک</span><strong id="review-webhook">—</strong></div>
                <div><span>اطلاعات حساس</span><strong class="masked-value">••••••••</strong></div>
            </div>
            <div class="security-note"><svg><use href="#i-shield-check"/></svg><span><strong>آماده نصب امن</strong><small>در صورت شکست هر مرحله، موفقیت گزارش نمی‌شود و فایل تنظیمات به نسخه قبل بازمی‌گردد.</small></span></div>
        </div>

        <div class="panel-actions wizard-actions">
            <button type="button" class="btn btn-secondary" id="install-prev-btn" hidden><svg><use href="#i-arrow-right"/></svg>مرحله قبل</button>
            <button type="button" class="btn btn-primary action-main" id="install-next-btn">ادامه<svg><use href="#i-arrow-left"/></svg></button>
            <button type="submit" class="btn btn-primary action-main" id="install-submit" hidden><svg><use href="#i-rocket"/></svg>شروع نصب</button>
        </div>
    </form>
</section>
