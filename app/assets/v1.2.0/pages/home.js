import { call } from '../api.js?v=0.0.53';
import { fmtPrice, escapeHtml, skeletonList, toast, serviceStatusBadge } from '../utils.js?v=0.0.52';
import { setUser } from '../state.js';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, showConfirm, getInitDataUnsafe } from '../telegram.js?v=0.0.52';
import { mountHomeBell } from '../notifications.js?v=0.0.52';
import { methodLabel } from '../payment-ui.js?v=0.0.73';
import { cancelVerifyPayment } from '../card-verify.js?v=0.0.6';

function greetingName() {
    try {
        const u = getInitDataUnsafe()?.user;
        if (u && u.first_name) return String(u.first_name);
    } catch (_) {  }
    return 'کاربر';
}

function mountProfilePhoto(view) {
    const avatar = view.querySelector('.home-profile-avatar');
    if (!avatar) return;
    try {
        const photoUrl = getInitDataUnsafe()?.user?.photo_url;
        if (!photoUrl || new URL(photoUrl).protocol !== 'https:') return;
        const photo = document.createElement('img');
        photo.alt = '';
        photo.referrerPolicy = 'no-referrer';
        photo.decoding = 'async';
        photo.addEventListener('load', () => avatar.replaceChildren(photo), { once: true });
        // Keep the initials visible if Telegram supplies no photo or loading fails.
        photo.src = photoUrl;
    } catch (_) { }
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
    const title = String(source.title || source.name || 'Faoxima').trim();
    const logoUrl = state === 'custom' ? String(source.logo_url || '').trim() : '';
    const markHtml = logoUrl
        ? `<span class="brand-mark" aria-hidden="true" style="background:transparent;padding:0;overflow:hidden"><img src="${escapeHtml(logoUrl)}" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" /></span>`
        : `<span class="brand-mark" aria-hidden="true">${escapeHtml(mark)}</span>`;
    const titleHtml = `<span class="brand-title"${title ? '' : ' hidden'}>${escapeHtml(title)}</span>`;
    return { markHtml, titleHtml };
}

export async function home(view) {
    const { markHtml, titleHtml } = brandMarkHtml();

    view.innerHTML = `
        <header class="home-header home-header-v2" aria-label="منوی اصلی">
            <div class="home-header-main"><div class="home-identity">
                <div class="home-brand">
                    <div class="home-brand-avatar">${markHtml}</div>
                    <div class="home-brand-copy">${titleHtml}<small>پنل کاربری</small></div>
                </div>
            </div>
                <a href="#/account" class="home-header-account" aria-label="حساب کاربری" title="حساب کاربری">${icon('user')}</a>
            </div>
            <div class="header-actions" id="home-header-icons" role="group" aria-label="دسترسی سریع">
                <span id="home-admin-gear-host"></span>
                <button type="button" id="notif-bell-btn" class="icon-btn" aria-label="اعلان‌ها" title="اعلان‌ها">
                    ${icon('bell')}
                    <span class="home-action-label">اعلان‌ها</span>
                    <span class="bell-dot" id="notif-bell-dot" aria-hidden="true" hidden></span>
                </button>
                <button type="button" id="reload-btn" class="icon-btn" aria-label="به‌روزرسانی برنامه" title="به‌روزرسانی برنامه و پاکسازی کش"
                        onclick="window.__hardReload && window.__hardReload();">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                    <span class="home-action-label">به‌روزرسانی</span>
                </button>

            </div>
        </header>

        <div id="home-pending-host"></div>

        <div class="action-grid home-action-grid mt-md" role="navigation" aria-label="دسترسی به خدمات">
            <a href="#/buy" class="action-tile home-shortcut-buy">
                ${icon('cart', 'class="ico ico-xl action-icon is-green"')}
                <span class="home-shortcut-copy">
                    <span class="home-shortcut-eyebrow">یک اتصال تازه</span>
                    <span class="home-shortcut-title">خرید سرویس</span>
                    <span class="home-shortcut-description">پلن مناسب خودت را انتخاب کن</span>
                </span>
                <span class="home-shortcut-go" aria-hidden="true">${icon('arrowLeft')}</span>
            </a>
            <a href="#/services" class="action-tile home-shortcut-services">
                ${icon('fileText', 'class="ico ico-xl action-icon is-yellow"')}
                <span class="home-shortcut-copy">
                    <span class="home-shortcut-title">سرویس‌های من</span>
                    <span class="home-shortcut-description">مدیریت و تمدید سرویس‌ها</span>
                </span>
                <span class="home-shortcut-go" aria-hidden="true">${icon('chevronLeft')}</span>
            </a>
            <a href="#/tickets" class="action-tile home-shortcut-support">
                ${icon('shield', 'class="ico ico-xl action-icon is-orange"')}
                <span class="home-shortcut-copy">
                    <span class="home-shortcut-title">پشتیبانی</span>
                    <span class="home-shortcut-description">پیگیری درخواست‌ها</span>
                </span>
            </a>
            <a href="#/account" class="action-tile home-shortcut-account">
                ${icon('user', 'class="ico ico-xl action-icon is-blue"')}
                <span class="home-shortcut-copy">
                    <span class="home-shortcut-title">حساب کاربری</span>
                    <span class="home-shortcut-description">اطلاعات حساب</span>
                </span>
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

    mountProfilePhoto(view);
    mountHomeBell().catch(() => {});

    try {
        const res = await call('user_info');
        const info = res?.obj || null;
        if (info) setUser(info);
        if (info && info.is_admin) {
            const gearHost = view.querySelector('#home-admin-gear-host');
            if (gearHost) {
                gearHost.innerHTML = `
                    <button type="button" id="admin-settings-btn" class="icon-btn" aria-label="تنظیمات مدیریت" title="تنظیمات مدیریت">
                        ${icon('settings')}
                        <span class="home-action-label">مدیریت</span>
                    </button>
                `;
                const gearBtn = gearHost.querySelector('#admin-settings-btn');
                if (gearBtn) {
                    gearBtn.addEventListener('click', () => { window.location.hash = '#/settings'; });
                }
            }
        }
    } catch (err) {
        toast(err.message || 'خطا در دریافت اطلاعات کاربری', 'error');
    }

    loadActiveServices(view);
    loadUsertestBanner(view);

    const pendingCleanup = await loadPendingBanner(view).catch(() => () => {});
    return () => { try { pendingCleanup && pendingCleanup(); } catch (_) {} };
}


let _usertestBannerSeq = 0;

function usertestQuotaText(obj) {
    const summary = obj && typeof obj.quota_summary === 'object' ? obj.quota_summary : null;
    if (summary && summary.type === 'single_panel') {
        if (summary.unlimited === true || summary.remaining === null || summary.remaining === undefined) return 'سهمیه باقی‌مانده: نامحدود';
        return `سهمیه باقی‌مانده: ${summary.remaining}`;
    }
    if (summary && summary.type === 'per_panel') {
        return 'سهمیه برای هر پنل جداگانه محاسبه می‌شود';
    }
    const panels = Array.isArray(obj?.panels) ? obj.panels : [];
    if (panels.length === 1 && panels[0] && Object.prototype.hasOwnProperty.call(panels[0], 'limit_left')) {
        return panels[0].limit_left === null ? 'سهمیه باقی‌مانده: نامحدود' : `سهمیه باقی‌مانده: ${panels[0].limit_left}`;
    }
    if (panels.length > 1) {
        return 'سهمیه برای هر پنل جداگانه محاسبه می‌شود';
    }
    return `سهمیه باقی‌مانده: ${obj?.limit_left ?? 0}`;
}

async function loadUsertestBanner(view) {
    const host = view.querySelector('#home-usertest-host');
    if (!host) return;
    const seq = ++_usertestBannerSeq;
    host.innerHTML = '';
    try {
        const res = await call('test_account_info');
        if (seq !== _usertestBannerSeq) return;
        const obj = res?.obj || {};
        const panels = Array.isArray(obj.panels) ? obj.panels : [];
        const exhausted = !obj.available && obj.reason === 'limit_reached' && panels.length > 0;
        if (!obj.available && !exhausted) {
            host.innerHTML = '';
            return;
        }
        host.innerHTML = `
            <a href="#/usertest" class="list-item mt-md">
                <div class="li-main">
                    <div class="li-title">${icon('gift', 'class="ico ico-leading"')} دریافت اکانت تست</div>
                    <div class="li-sub">${escapeHtml(usertestQuotaText(obj))}</div>
                </div>
                <span class="li-action" aria-hidden="true">${icon('chevronLeft', 'class="ico"')}</span>
            </a>
        `;
    } catch (_) {
        if (seq === _usertestBannerSeq) host.innerHTML = '';
    }
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
    const displayUsername = it.display_username || username;
    const productName = it.name_product || '—';
    const location = it.Service_location || '';

    return `
        <a href="#/services/${encodeURIComponent(username)}" class="list-item" data-username="${escapeHtml(username)}">
            <div class="li-main">
                <div class="li-title">${escapeHtml(productName)}</div>
                <div class="li-sub">${escapeHtml(displayUsername)}${location ? ' · ' + escapeHtml(location) : ''}</div>
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
        pending = all.filter(p => (p.method !== 'cart to cart' && p.method !== 'carttocart_pv') || p.verify);
    } catch (_) { return () => {}; }
    if (!pending.length) return () => {};

    const externalGatewayPending = pending.find(p => p.method === 'iranpay2' || p.method === 'tonpay' || p.method === 'rialit' || p.method === 'cubepay' || p.method === 'blupal' || p.method === 'variza' || p.method === 'abangateway' || p.method === 'atlaspay');
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
            const ok = await showConfirm(btn.getAttribute('data-method') === 'rialit'
                ? 'از این پرداخت انصراف می‌دهید؟ می‌توانید پرداخت جدید بسازید. لینک قبلی در ریالیت باطل نمی‌شود؛ اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.'
                : 'این فاکتور لغو شود؟');
            if (!ok) return;
            try {
                if (btn.getAttribute('data-verify') === '1') {
                    await cancelVerifyPayment(order);
                } else {
                    await call('crypto_cancel_invoice', { method: 'POST', body: { order_id: order } });
                }
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

function fmtRemainHhMmSs(remainSec) {
    const s = Math.max(0, Math.floor(remainSec));
    if (s <= 0) return 'منقضی';
    const pad2 = (n) => n < 10 ? '0' + n : String(n);
    const hh = Math.floor(s / 3600);
    const mm = Math.floor((s % 3600) / 60);
    const ss = s % 60;
    return pad2(hh) + ':' + pad2(mm) + ':' + pad2(ss);
}


function startPendingTicker(host) {
    const nodes = host.querySelectorAll('.pending-remaining[data-expires-at]');
    if (!nodes.length) return () => {};
    function tick() {
        const nowSec = Math.floor(Date.now() / 1000);
        nodes.forEach((el) => {
            const exp = Number(el.getAttribute('data-expires-at')) || 0;
            if (exp <= 0) return;
            const fmt = el.getAttribute('data-method') === 'tonpay' ? fmtRemainHhMmSs : fmtRemainMmSs;
            if (el.getAttribute('data-method') === 'rialit' && (exp - nowSec) <= 0) {
                el.textContent = 'در انتظار نتیجه';
                const card = el.closest ? el.closest('.pending-banner') : null;
                const note = card ? card.querySelector('.pending-banner-note') : null;
                if (note) note.textContent = 'مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه';
                return;
            }
            el.textContent = fmt(exp - nowSec);
        });
    }
    tick();
    const t = setInterval(tick, 1000);
    return () => clearInterval(t);
}


function renderPendingCard(p) {
    const expiresAt = Number(p.expires_at) || 0;
    const isTonpay = String(p.method || '').toLowerCase() === 'tonpay';
    const isRialit = String(p.method || '').toLowerCase() === 'rialit';
    const cancelAllowed = p.cancel_eligible === true
        || (p.cancel_eligible !== false && (!p.verify || p.cancellable));
    const fmtRemain = isTonpay ? fmtRemainHhMmSs : fmtRemainMmSs;
    const rialitAwaitingResult = isRialit && ((p.rialit && p.rialit.awaiting_result) || (expiresAt > 0 && expiresAt <= Math.floor(Date.now() / 1000)));
    const initialRemain = rialitAwaitingResult
        ? 'در انتظار نتیجه'
        : (expiresAt > 0
            ? fmtRemain(expiresAt - Math.floor(Date.now() / 1000))
            : fmtRemain(p.remaining_sec || 0));
    const methodFa = p.method_label || methodLabel(p.method);
    const cur = p.currency_code ? ` (${escapeHtml(p.currency_code)})` : '';
    const rialitNote = isRialit
        ? `<p class="pending-banner-note muted" style="font-size:12px;margin:8px 0 0">${rialitAwaitingResult ? 'مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه' : ''}</p>`
        : '';
    return `
        <div class="pending-banner pending-payment">
            <div class="pending-banner-head">
                ${icon('hourglass', 'class="ico ico-leading"')}
                <div><span class="pending-payment-kicker">پرداخت در انتظار تأیید</span><strong>${escapeHtml(methodFa)}${cur}</strong></div>
            </div>
            <div class="pending-banner-grid">
                <div><span class="muted">کد فاکتور</span><span class="mono">${escapeHtml(p.order_id)}</span></div>
                <div><span class="muted">مبلغ</span><span class="mono accent">${escapeHtml(fmtPrice(p.amount))}</span></div>
                <div class="pending-payment-time"><span class="muted">زمان باقی‌مانده</span><span class="mono pending-remaining" data-expires-at="${expiresAt}" data-method="${escapeHtml(p.method || '')}">${escapeHtml(initialRemain)}</span></div>
            </div>
            ${rialitNote}
            <div class="pending-banner-actions">
                ${!p.verify || p.resumable || p.status !== 'Unpaid' ? `<button type="button" class="btn btn-primary btn-block" data-resume="${escapeHtml(p.order_id)}">
                    ${icon('arrowLeft', 'class="ico ico-leading"')}
                    <span>${p.verify && p.resumable ? 'ادامه پرداخت' : 'ادامه پیگیری'}</span>
                </button>` : ''}
                ${cancelAllowed ? `<button type="button" class="btn btn-ghost btn-block" data-cancel="${escapeHtml(p.order_id)}" data-method="${escapeHtml(p.method || '')}" data-verify="${p.verify ? '1' : '0'}">
                    ${icon('close', 'class="ico ico-leading"')}
                    <span>${isRialit ? 'انصراف و پرداخت جدید' : 'انصراف'}</span>
                </button>` : ''}
            </div>
        </div>
    `;
}

