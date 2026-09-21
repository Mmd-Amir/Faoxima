import { call } from '../api.js?v=0.0.52';
import { fmtPrice, escapeHtml, skeletonList, toast, serviceStatusBadge } from '../utils.js?v=0.0.52';
import { setUser } from '../state.js';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, showConfirm, getInitDataUnsafe } from '../telegram.js?v=0.0.52';
import { mountHomeBell } from '../notifications.js?v=0.0.52';
import { methodLabel } from '../payment-ui.js?v=0.0.54';

function greetingName() {
    try {
        const u = getInitDataUnsafe()?.user;
        if (u && u.first_name) return String(u.first_name);
    } catch (_) {  }
    return 'کاربر';
}

function brandMarkHtml() {
    const cfgBrand = ((typeof window !== 'undefined' && window.__APP_CONFIG__) || {}).brand;
    // Server-rendered config already resolved the avatar state atomically; trust it
    // exclusively so a stale cached default-logo URL can never flash before the
    // real fetch response overwrites it. Only fall back to the cache if the server
    // config is entirely unavailable.
    let source = cfgBrand;
    if (!source) {
        try { source = JSON.parse(localStorage.getItem('faoxima.brand') || '{}'); } catch (_) { source = {}; }
    }
    const state = String(source.avatar_state || (source.logo_url ? 'custom' : (source.mark ? 'initials' : ''))).trim();
    const mark = String(source.mark || '').trim() || 'F';
    const title = String(source.title || '').trim();
    const logoUrl = state === 'custom' ? String(source.logo_url || '').trim() : '';
    const markHtml = logoUrl
        ? `<span class="brand-mark" aria-hidden="true" style="background:transparent;padding:0;overflow:hidden"><img src="${escapeHtml(logoUrl)}" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" /></span>`
        : `<span class="brand-mark" aria-hidden="true">${escapeHtml(mark)}</span>`;
    const titleHtml = `<span class="brand-title"${title ? '' : ' hidden'}>${escapeHtml(title)}</span>`;
    return titleHtml + markHtml;
}

export async function home(view) {
    const name = greetingName();

    view.innerHTML = `
        <div class="home-header">
            <div>
                <p class="home-greeting">خوش اومدی</p>
                <p class="home-username">${escapeHtml(name)}</p>
            </div>
            <div class="header-actions" id="home-header-icons">
                ${brandMarkHtml()}
                <span id="home-admin-gear-host"></span>
                <button id="notif-bell-btn" class="icon-btn" aria-label="اعلان‌ها" title="اعلان‌ها">
                    ${icon('bell')}
                    <span class="bell-dot" id="notif-bell-dot" hidden></span>
                </button>
                <button id="reload-btn" class="icon-btn" aria-label="Reload" title="Reload (پاکسازی کش)"
                        onclick="window.__hardReload && window.__hardReload();">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                </button>
            </div>
        </div>

        <div id="home-pending-host"></div>

        <div class="wallet-hero" id="wallet-hero">
            <div class="wallet-hero-top">
                ${icon('wallet', 'class="ico"')}
                <span>موجودی کیف پول</span>
                <span class="dot-online" aria-hidden="true"></span>
            </div>
            <div class="wallet-hero-amount" id="wallet-hero-amount">
                <div class="skeleton skeleton-row" style="background:rgba(255,255,255,0.18)"></div>
            </div>
            <div class="wallet-hero-actions">
                <a href="#/recharge" class="wallet-hero-btn-primary">
                    ${icon('plus', 'class="ico"')}
                    <span>شارژ کیف پول</span>
                </a>
                <a href="#/history" class="wallet-hero-btn-ghost">
                    ${icon('clock', 'class="ico"')}
                    <span>تاریخچه</span>
                </a>
            </div>
        </div>

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
                ${icon('shield', 'class="ico ico-xl action-icon is-orange"')}
                <span>پشتیبانی</span>
            </a>
            <a href="#/account" class="action-tile">
                ${icon('user', 'class="ico ico-xl action-icon is-blue"')}
                <span>حساب کاربری</span>
            </a>
        </div>

        <div id="home-usertest-host"></div>

        <div class="section-header-row mt-md">
            <p class="section-title">${icon('box')} سرویس‌های فعال</p>
            <a href="#/services" class="section-link">مشاهده همه</a>
        </div>
        <div id="home-services-list" class="list mt-sm">
            ${skeletonList(2)}
        </div>
    `;

    mountHomeBell().catch(() => {});

    let info = null;
    try {
        const res = await call('user_info');
        info = res?.obj || null;
        if (info) setUser(info);
        if (info && info.is_admin) {
            const gearHost = view.querySelector('#home-admin-gear-host');
            if (gearHost) {
                gearHost.innerHTML = `
                    <button id="admin-settings-btn" class="icon-btn" aria-label="تنظیمات" title="تنظیمات">
                        ${icon('settings')}
                    </button>
                `;
                const gearBtn = gearHost.querySelector('#admin-settings-btn');
                if (gearBtn) {
                    gearBtn.addEventListener('click', () => { window.location.hash = '#/settings'; });
                }
            }
        }
    } catch (err) {
        const amount = view.querySelector('#wallet-hero-amount');
        if (amount) amount.innerHTML = `<span style="font-size:14px">${escapeHtml(err.message || 'خطا در دریافت موجودی')}</span>`;
        return;
    }

    const amount = view.querySelector('#wallet-hero-amount');
    if (amount && info) {
        amount.textContent = fmtPrice(info.balance);
    }

    loadActiveServices(view);
    loadUsertestBanner(view);

    const pendingCleanup = await loadPendingBanner(view).catch(() => () => {});
    return () => { try { pendingCleanup && pendingCleanup(); } catch (_) {} };
}


async function loadUsertestBanner(view) {
    const host = view.querySelector('#home-usertest-host');
    if (!host) return;
    try {
        const res = await call('test_account_info');
        const obj = res?.obj || {};
        if (!obj.available) return;
        host.innerHTML = `
            <a href="#/usertest" class="list-item mt-md">
                <div class="li-main">
                    <div class="li-title">${icon('gift', 'class="ico ico-leading"')} دریافت اکانت تست</div>
                    <div class="li-sub">سهمیه باقیمانده: ${escapeHtml(obj.limit_left ?? 0)}</div>
                </div>
                <span class="li-action" aria-hidden="true">${icon('chevronLeft', 'class="ico"')}</span>
            </a>
        `;
    } catch (_) {  }
}


async function loadActiveServices(view) {
    const host = view.querySelector('#home-services-list');
    if (!host) return;
    try {
        const res = await call('invoices', { params: { page: '1', limit: '3' } });
        const items = res?.obj?.items || [];
        if (!items.length) {
            host.innerHTML = `
                <div class="empty">
                    ${icon('info', 'class="ico ico-xxl ico-muted"')}
                    <h3>هنوز سرویسی ندارید</h3>
                    <a href="#/buy" class="btn btn-primary mt-md">
                        ${icon('cart', 'class="ico ico-leading"')}
                        <span>اولین سرویس را بخرید</span>
                    </a>
                </div>
            `;
            return;
        }
        host.innerHTML = items.map(renderServiceItem).join('');
    } catch (_) {
        host.innerHTML = '';
    }
}

function renderServiceItem(it) {
    const st = serviceStatusBadge(it.status || it.Status || '');
    const badge = st.badge;
    const badgeText = st.text;
    const badgeIcon = st.icon;

    const username = it.username || '—';
    const productName = it.name_product || '—';
    const location = it.Service_location || '';

    return `
        <a href="#/services/${encodeURIComponent(username)}" class="list-item" data-username="${escapeHtml(username)}">
            <div class="li-main">
                <div class="li-title">${escapeHtml(productName)}</div>
                <div class="li-sub">${escapeHtml(username)}${location ? ' · ' + escapeHtml(location) : ''}</div>
            </div>
            <span class="badge ${badge}">${icon(badgeIcon, 'class="ico"')} ${escapeHtml(badgeText)}</span>
            <span class="li-action" aria-hidden="true">
                ${icon('chevronLeft', 'class="ico"')}
            </span>
        </a>
    `;
}


async function loadPendingBanner(view) {
    const host = view.querySelector('#home-pending-host');
    if (!host) return () => {};

    let pending = [];
    try {
        const res = await call('pending_payments');
        const all = Array.isArray(res?.obj?.pending) ? res.obj.pending : [];
        pending = all.filter(p => p.method !== 'cart to cart' && p.method !== 'carttocart_pv');
    } catch (_) { return () => {}; }
    if (!pending.length) return () => {};

    const externalGatewayPending = pending.find(p => p.method === 'iranpay2' || p.method === 'tonpay' || p.method === 'cubepay' || p.method === 'blupal' || p.method === 'variza' || p.method === 'atlaspay' || p.method === 'tetrapay');
    if (externalGatewayPending) {
        const autoJumpKey = 'faoxima_extgw_autojump_' + externalGatewayPending.order_id;
        let alreadyJumped = false;
        try { alreadyJumped = sessionStorage.getItem(autoJumpKey) === '1'; } catch (_) {  }
        if (!alreadyJumped) {
            try { sessionStorage.setItem(autoJumpKey, '1'); } catch (_) {  }
            window.location.hash = '#/watch/' + encodeURIComponent(externalGatewayPending.order_id);
            return () => {};
        }
    }

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

    return startPendingTicker(host);
}


function fmtRemainMmSs(remainSec) {
    const s = Math.max(0, Math.floor(remainSec));
    if (s <= 0) return 'منقضی';
    const mm = Math.floor(s / 60);
    const ss = s % 60;
    return (mm < 10 ? '0' + mm : '' + mm) + ':' + (ss < 10 ? '0' + ss : '' + ss);
}


function startPendingTicker(host) {
    const nodes = host.querySelectorAll('.pending-remaining[data-expires-at]');
    if (!nodes.length) return () => {};
    function tick() {
        const nowSec = Math.floor(Date.now() / 1000);
        nodes.forEach((el) => {
            const exp = Number(el.getAttribute('data-expires-at')) || 0;
            if (exp <= 0) return;
            el.textContent = fmtRemainMmSs(exp - nowSec);
        });
    }
    tick();
    const t = setInterval(tick, 1000);
    return () => clearInterval(t);
}


function renderPendingCard(p) {
    const expiresAt = Number(p.expires_at) || 0;
    const initialRemain = expiresAt > 0
        ? fmtRemainMmSs(expiresAt - Math.floor(Date.now() / 1000))
        : fmtRemainMmSs(p.remaining_sec || 0);
    const methodFa = p.method_label || methodLabel(p.method);
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
                <div><span class="muted">باقی‌مانده</span><span class="mono pending-remaining" data-expires-at="${expiresAt}">${escapeHtml(initialRemain)}</span></div>
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

