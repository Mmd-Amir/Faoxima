import { call } from '../api.js';
import { fmtPrice, fmtNumber, escapeHtml, skeletonList } from '../utils.js';
import { setUser } from '../state.js';

export async function home(view) {
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/account</span>
            </header>
            <div class="card-body" id="home-account-body">
                ${skeletonList(3)}
            </div>
        </article>

        <div class="row-spread mt-md gap-md">
            <a href="#/buy" class="btn btn-primary btn-block"><span>خرید سرویس جدید</span><span class="arrow">→</span></a>
        </div>
        <div class="row-spread mt-sm gap-sm">
            <a href="#/services" class="btn btn-ghost btn-block">سرویس‌های من</a>
            <a href="#/settings" class="btn btn-ghost btn-block">تنظیمات</a>
        </div>
    `;

    let info = null;
    try {
        const res = await call('user_info');
        info = res?.obj || null;
        if (info) setUser(info);
    } catch (err) {
        const target = view.querySelector('#home-account-body');
        if (target) {
            target.innerHTML = `
                <div class="empty">
                    <span class="glyph">!</span>
                    <h3>دریافت اطلاعات حساب با خطا روبرو شد</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>
            `;
        }
        return;
    }

    const target = view.querySelector('#home-account-body');
    if (!target || !info) return;

    target.innerHTML = `
        <div class="kv">
            <span class="kv-label"><span class="glyph">$</span> موجودی</span>
            <span class="kv-value accent">${escapeHtml(fmtPrice(info.balance))}</span>
        </div>
        <div class="kv">
            <span class="kv-label"><span class="glyph">#</span> سرویس‌های فعال</span>
            <span class="kv-value">${escapeHtml(fmtNumber(info.count_order))}</span>
        </div>
        <div class="kv">
            <span class="kv-label"><span class="glyph">~</span> نوع کاربری</span>
            <span class="kv-value">${escapeHtml(info.group_type || '—')}</span>
        </div>
        <div class="kv">
            <span class="kv-label"><span class="glyph">/</span> تاریخ عضویت</span>
            <span class="kv-value">${escapeHtml(info.time_join || '—')}</span>
        </div>
    `;
}

