import { call } from '../api.js';
import { escapeHtml, fmtPrice, fmtNumber, skeletonList } from '../utils.js';
import { setUser } from '../state.js';
import { icon } from '../icons.js';

export async function account(view) {
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/account</span>
            </header>
            <div class="card-body" id="account-body">
                ${skeletonList(4)}
            </div>
        </article>
    `;

    const $body = view.querySelector('#account-body');

    let info = null;
    try {
        const res = await call('user_info');
        info = res?.obj;
        if (info) setUser(info);
    } catch (err) {
        $body.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت اطلاعات</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
        return;
    }

    if (!info) {
        $body.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>اطلاعاتی یافت نشد</h3>
            </div>
        `;
        return;
    }

    $body.innerHTML = `
        <p class="section-title">${icon('user')} حساب کاربری</p>
        <div class="stat-grid mt-sm">
            <div class="stat">
                <div class="stat-label">${icon('wallet')} موجودی کیف پول</div>
                <div class="stat-value accent">${escapeHtml(fmtPrice(info.balance))}</div>
            </div>
            <div class="stat">
                <div class="stat-label">${icon('crown')} نوع کاربری</div>
                <div class="stat-value">${escapeHtml(info.group_type || '—')}</div>
            </div>
            <div class="stat">
                <div class="stat-label">${icon('box')} سرویس‌های فعال</div>
                <div class="stat-value">${escapeHtml(fmtNumber(info.count_order))}</div>
            </div>
            <div class="stat">
                <div class="stat-label">${icon('creditCard')} پرداخت‌ها</div>
                <div class="stat-value">${escapeHtml(fmtNumber(info.count_payment))}</div>
            </div>
        </div>

        <div class="card-section">
            <div class="kv">
                <span class="kv-label">${icon('calendar')} تاریخ عضویت</span>
                <span class="kv-value">${escapeHtml(info.time_join || '—')}</span>
            </div>
            <div class="kv">
                <span class="kv-label">${icon('phone')} شماره تلفن</span>
                <span class="kv-value">${escapeHtml(info.phone || '—')}</span>
            </div>
        </div>

        <div class="row-spread mt-md gap-sm">
            <a href="#/recharge" class="btn btn-primary btn-block">
                ${icon('wallet', 'class="ico ico-leading"')}
                <span>شارژ کیف پول</span>
                ${icon('arrowLeft', 'class="ico ico-trailing"')}
            </a>
        </div>
        <div class="row-spread mt-sm gap-sm stack-on-mobile">
            <a href="#/buy" class="btn btn-ghost btn-block">
                ${icon('cart', 'class="ico ico-leading"')}
                <span>خرید سرویس</span>
            </a>
            <a href="#/services" class="btn btn-ghost btn-block">
                ${icon('fileText', 'class="ico ico-leading"')}
                <span>سرویس‌های من</span>
            </a>
        </div>
        <div class="row-spread mt-sm gap-sm">
            <a href="#/settings" class="btn btn-ghost btn-block">
                ${icon('settings', 'class="ico ico-leading"')}
                <span>تنظیمات</span>
            </a>
        </div>
    `;
}

