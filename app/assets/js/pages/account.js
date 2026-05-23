import { call } from '../api.js';
import { escapeHtml, fmtPrice, fmtNumber, skeletonList } from '../utils.js';
import { setUser } from '../state.js';

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
                <span class="glyph">!</span>
                <h3>خطا در دریافت اطلاعات</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
        return;
    }

    if (!info) {
        $body.innerHTML = `<div class="empty"><span class="glyph">∅</span><h3>اطلاعاتی یافت نشد</h3></div>`;
        return;
    }

    $body.innerHTML = `
        <p class="section-title">حساب کاربری</p>
        <div class="stat-grid mt-sm">
            <div class="stat">
                <div class="stat-label"><span class="glyph">$</span> موجودی کیف پول</div>
                <div class="stat-value accent">${escapeHtml(fmtPrice(info.balance))}</div>
            </div>
            <div class="stat">
                <div class="stat-label"><span class="glyph">~</span> نوع کاربری</div>
                <div class="stat-value">${escapeHtml(info.group_type || '—')}</div>
            </div>
            <div class="stat">
                <div class="stat-label"><span class="glyph">#</span> سرویس‌های فعال</div>
                <div class="stat-value">${escapeHtml(fmtNumber(info.count_order))}</div>
            </div>
            <div class="stat">
                <div class="stat-label"><span class="glyph">@</span> پرداخت‌ها</div>
                <div class="stat-value">${escapeHtml(fmtNumber(info.count_payment))}</div>
            </div>
        </div>

        <div class="card-section">
            <div class="kv">
                <span class="kv-label"><span class="glyph">/</span> تاریخ عضویت</span>
                <span class="kv-value">${escapeHtml(info.time_join || '—')}</span>
            </div>
            <div class="kv">
                <span class="kv-label"><span class="glyph">:</span> شماره تلفن</span>
                <span class="kv-value">${escapeHtml(info.phone || '—')}</span>
            </div>
        </div>

        <div class="row-spread mt-md gap-sm">
            <a href="#/services" class="btn btn-ghost btn-block">سرویس‌های من</a>
            <a href="#/settings" class="btn btn-ghost btn-block">تنظیمات</a>
        </div>
        <div class="row-spread mt-sm">
            <a href="#/buy" class="btn btn-primary btn-block"><span>خرید سرویس</span><span class="arrow">→</span></a>
        </div>
    `;
}

