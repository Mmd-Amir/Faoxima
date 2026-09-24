import { call } from '../api.js?v=0.0.52';
import { escapeHtml, fmtPrice, fmtNumber, skeletonList } from '../utils.js?v=0.0.52';
import { setUser } from '../state.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

function renderPendingBanner(pendingList) {
    const gateways = (pendingList || []).filter(p =>
        p.method !== 'cart to cart' && p.method !== 'carttocart_pv'
    );
    if (!gateways.length) return '';
    return gateways.map(p => `
        <div class="callout mt-md" style="background:rgba(255,165,0,.09);border-color:rgba(255,165,0,.35);gap:10px">
            ${icon('clock', 'style="width:20px;height:20px;color:#f59e0b;flex-shrink:0"')}
            <div style="flex:1;min-width:0">
                <p style="margin:0;font-weight:700;font-size:13px;color:#f59e0b">⏳ پرداخت در انتظار تأیید</p>
                <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted)">
                    کد پیگیری: <span style="font-family:monospace;font-weight:600">${escapeHtml(p.order_id)}</span>
                </p>
                <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted)">
                    مبلغ: <b>${escapeHtml(fmtPrice(p.amount))}</b> تومان — در انتظار بررسی ادمین
                </p>
            </div>
        </div>
    `).join('');
}

export async function account(view) {
    view.innerHTML = `
        <article class="card card-window card-bare">
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
    let pendingList = [];
    try {
        const [infoRes, pendingRes] = await Promise.all([
            call('user_info'),
            call('pending_payments').catch(() => null),
        ]);
        info = infoRes?.obj;
        if (info) setUser(info);
        pendingList = pendingRes?.obj?.pending || [];
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

        <div class="wallet-hero mt-sm">
            <div class="wallet-hero-top">
                ${icon('wallet', 'class="ico"')}
                <span>موجودی کیف پول</span>
                <span class="dot-online" aria-hidden="true"></span>
            </div>
            <div class="wallet-hero-amount">${escapeHtml(fmtPrice(info.balance))}</div>
            <div class="wallet-hero-actions">
                <a href="#/recharge" class="wallet-hero-btn-primary">
                    ${icon('plus', 'class="ico"')}
                    <span>شارژ کیف پول</span>
                </a>
                <a href="#/transfer" class="wallet-hero-btn-ghost">
                    ${icon('transfer', 'class="ico"')}
                    <span>انتقال موجودی</span>
                </a>
            </div>
        </div>

        <div class="stat-grid mt-md">
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
            <div class="stat">
                <div class="stat-label">${icon('calendar')} تاریخ عضویت</div>
                <div class="stat-value">${escapeHtml(info.time_join || '—')}</div>
            </div>
        </div>

        <div class="list mt-md">
            <div class="list-item">
                <div class="li-main">
                    <div class="li-title">شماره تلفن</div>
                    <div class="li-sub">${escapeHtml(info.phone || '—')}</div>
                </div>
                <span class="li-action" aria-hidden="true">${icon('phone', 'class="ico"')}</span>
            </div>
        </div>

        ${renderPendingBanner(pendingList)}

        <div class="action-grid mt-md">
            <a href="#/buy" class="action-tile">
                ${icon('cart', 'class="ico ico-xl action-icon is-green"')}
                <span>خرید سرویس</span>
            </a>
            <a href="#/services" class="action-tile">
                ${icon('fileText', 'class="ico ico-xl action-icon is-yellow"')}
                <span>سرویس‌های من</span>
            </a>
            <a href="#/tickets" class="action-tile">
                ${icon('send', 'class="ico ico-xl action-icon is-orange"')}
                <span>تیکت‌های پشتیبانی</span>
            </a>
        </div>
    `;
}

