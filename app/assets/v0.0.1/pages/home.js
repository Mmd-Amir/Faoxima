import { call } from '../api.js';
import { fmtPrice, fmtNumber, escapeHtml, skeletonList, toast } from '../utils.js';
import { setUser } from '../state.js';
import { icon } from '../icons.js';
import { hapticImpact, showConfirm } from '../telegram.js';

export async function home(view) {
    view.innerHTML = `
        <div id="home-pending-host"></div>

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
            <a href="#/buy" class="btn btn-primary btn-block">
                ${icon('cart', 'class="ico ico-leading"')}
                <span>خرید سرویس جدید</span>
                ${icon('arrowLeft', 'class="ico ico-trailing"')}
            </a>
        </div>
        <div class="row-spread mt-sm gap-sm stack-on-mobile">
            <a href="#/recharge" class="btn btn-accent btn-block">
                ${icon('wallet', 'class="ico ico-leading"')}
                <span>شارژ کیف پول</span>
            </a>
            <a href="#/services" class="btn btn-ghost btn-block">
                ${icon('fileText', 'class="ico ico-leading"')}
                <span>سرویس‌های من</span>
            </a>
        </div>
        <div class="row-spread mt-sm gap-sm stack-on-mobile">
            <a href="#/account" class="btn btn-ghost btn-block">
                ${icon('user', 'class="ico ico-leading"')}
                <span>حساب من</span>
            </a>
            <a href="#/settings" class="btn btn-ghost btn-block">
                ${icon('settings', 'class="ico ico-leading"')}
                <span>تنظیمات</span>
            </a>
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
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
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
            <span class="kv-label">${icon('wallet')} موجودی</span>
            <span class="kv-value accent">${escapeHtml(fmtPrice(info.balance))}</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('box')} سرویس‌های فعال</span>
            <span class="kv-value">${escapeHtml(fmtNumber(info.count_order))}</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('crown')} نوع کاربری</span>
            <span class="kv-value">${escapeHtml(info.group_type || '—')}</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('calendar')} تاریخ عضویت</span>
            <span class="kv-value">${escapeHtml(info.time_join || '—')}</span>
        </div>
    `;

    loadPendingBanner(view).catch(() => {});
}


async function loadPendingBanner(view) {
    const host = view.querySelector('#home-pending-host');
    if (!host) return;

    let pending = [];
    try {
        const res = await call('pending_payments');
        pending = Array.isArray(res?.obj?.pending) ? res.obj.pending : [];
    } catch (_) { return; }
    if (!pending.length) return;

    host.innerHTML = pending.map(renderPendingCard).join('');

    host.querySelectorAll('[data-resume]').forEach((btn) => {
        btn.addEventListener('click', () => {
            hapticImpact('light');
            const order = btn.getAttribute('data-resume');
            if (order) window.location.hash = '#/watch/' + encodeURIComponent(order);
        });
    });

    host.querySelectorAll('[data-cancel]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            hapticImpact('light');
            const order = btn.getAttribute('data-cancel');
            if (!order) return;
            const ok = await showConfirm('این فاکتور لغو شود؟');
            if (!ok) return;
            try {
                await call('crypto_cancel_invoice', { method: 'POST', body: { order_id: order } });
                toast('فاکتور لغو شد', 'success', 2500);
                home(view);
            } catch (err) {
                toast(err.message || 'خطا در لغو فاکتور', 'error', 4000);
            }
        });
    });
}


function renderPendingCard(p) {
    const min = Math.max(0, Math.floor((p.remaining_sec || 0) / 60));
    const sec = Math.max(0, (p.remaining_sec || 0) % 60);
    const remain = min > 0 ? min + ' دقیقه دیگر' : (sec > 0 ? sec + ' ثانیه دیگر' : 'منقضی');
    const methodFa = methodLabel(p.method);
    const cur = p.currency_code ? ` (${escapeHtml(p.currency_code)})` : '';
    return `
        <div class="pending-banner">
            <div class="pending-banner-head">
                ${icon('hourglass', 'class="ico ico-leading"')}
                <span>پرداخت در انتظار تأیید — ${escapeHtml(methodFa)}${cur}</span>
            </div>
            <div class="pending-banner-grid">
                <div><span class="muted">کد فاکتور</span><span class="mono">${escapeHtml(p.order_id)}</span></div>
                <div><span class="muted">مبلغ</span><span class="mono accent">${escapeHtml(fmtPrice(p.amount))}</span></div>
                <div><span class="muted">باقی‌مانده</span><span class="mono">${escapeHtml(remain)}</span></div>
            </div>
            <div class="pending-banner-actions">
                <button type="button" class="btn btn-primary btn-block" data-resume="${escapeHtml(p.order_id)}">
                    ${icon('arrowLeft', 'class="ico ico-leading"')}
                    <span>ادامه پیگیری</span>
                </button>
                <button type="button" class="btn btn-ghost btn-block" data-cancel="${escapeHtml(p.order_id)}">
                    ${icon('close', 'class="ico ico-leading"')}
                    <span>انصراف</span>
                </button>
            </div>
        </div>
    `;
}


function methodLabel(m) {
    const s = String(m || '').toLowerCase();
    if (s === 'plisio') return 'Plisio';
    if (s === 'nowpayment') return 'NowPayments';
    if (s === 'digitaltron') return 'هش‌چکر';
    if (s === 'arze digital offline') return 'هش‌چکر';
    if (s === 'cart to cart' || s === 'carttocart_pv') return 'کارت‌به‌کارت';
    return m || 'پرداخت';
}
