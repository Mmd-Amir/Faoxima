import { home } from './pages/home.js?v=0.0.54';
import { services as servicesPage } from './pages/services.js?v=0.0.52';

import { buy as buyPage } from './pages/buy.js?v=0.0.54';
import { account as accountPage } from './pages/account.js?v=0.0.52';
import { settings as settingsPage } from './pages/settings.js?v=0.0.52';
import { recharge as rechargePage } from './pages/recharge.js?v=0.0.54';
import { icon } from './icons.js?v=0.0.52';
import { methodLabel } from './payment-ui.js?v=0.0.54';


let _watchModulePromise = null;
function loadWatchPage() {
    if (_watchModulePromise) return _watchModulePromise;
    _watchModulePromise = import('./pages/gateway-watch.js');
    return _watchModulePromise;
}


async function watchResumePage(view, encodedOrderId) {
    const orderId = decodeURIComponent(encodedOrderId || '');
    const mod = await loadWatchPage();
    if (!mod || typeof mod.startGatewayWatch !== 'function') {
        view.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>عملیات در دسترس نیست</h3>
                <a href="#/" class="btn btn-primary mt-md">${icon('home', 'class="ico ico-leading"')}<span>خانه</span></a>
            </div>`;
        return;
    }


    let expiresAtSec = 0;
    let methodStr = '';
    let flowStr = '';
    let gatewayUrl = '';
    let paymentStatus = '';
    let statusObj = {};
    try {
        const statusResp = await import('./api.js?v=0.0.52').then(m => m.call('payment_status', { params: { order_id: orderId } }));
        const obj = statusResp?.obj || {};
        statusObj = obj;
        methodStr = String(obj.method || '').toLowerCase();
        flowStr = String(obj.flow || '').toLowerCase();
        gatewayUrl = String(obj.gateway_url || '');
        paymentStatus = String(obj.payment_status || '');

        const WATCH_WINDOW_SEC = methodStr === 'cubepay' ? 3600 : (methodStr === 'atlaspay' ? 1200 : (methodStr === 'tetrapay' ? 600 : 1800));
        const nowSec = Math.floor(Date.now() / 1000);
        const hashAt = Number(obj.hash_at || 0);
        const expiresAtFromServer = Number(obj.expires_at || 0);

        if (hashAt > 0) {
            expiresAtSec = hashAt + WATCH_WINDOW_SEC;
        } else if (expiresAtFromServer > 0) {
            expiresAtSec = Math.min(expiresAtFromServer, nowSec + WATCH_WINDOW_SEC);
        } else {
            expiresAtSec = nowSec + WATCH_WINDOW_SEC;
        }
    } catch (_) {  }

    if (paymentStatus === 'reject' || paymentStatus === 'cancelled' || paymentStatus === 'expire') {
        const titleText = paymentStatus === 'cancelled'
            ? 'این فاکتور لغو شده است'
            : (paymentStatus === 'expire' ? 'فاکتور منقضی شده است' : 'پرداخت ناموفق بود');
        view.innerHTML = `
            <article class="card card-window">
                <header class="card-window-bar"><span class="dots"><span></span><span></span><span></span></span><span class="window-url">faoxima/recharge/fail</span></header>
                <div class="card-body" style="text-align:center;padding:22px">
                    <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">${icon('xCircle', 'class="ico ico-xxl ico-warn"')}</div>
                    <h2 style="margin:6px 0;font-size:18px">${titleText}</h2>
                    <p class="muted">این فاکتور دیگر قابل پیگیری نیست. برای تلاش مجدد یک فاکتور جدید ایجاد کنید.</p>
                    <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                        <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                        <a href="#/" class="btn btn-ghost btn-block" style="flex:1">خانه</a>
                    </div>
                </div>
            </article>`;
        return;
    }
    if (paymentStatus === 'paid' && !statusObj.is_service_ready && flowStr !== 'direct_buy') {
        view.innerHTML = `
            <article class="card card-window">
                <header class="card-window-bar"><span class="dots"><span></span><span></span><span></span></span><span class="window-url">faoxima/recharge/done</span></header>
                <div class="card-body" style="text-align:center;padding:22px">
                    <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">${icon('checkCircle', 'class="ico ico-xxl ico-success"')}</div>
                    <h2 style="margin:6px 0;font-size:18px">پرداخت با موفقیت تایید شد</h2>
                    <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${orderId}</span></div>
                    <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                        <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                        <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                    </div>
                </div>
            </article>`;
        return;
    }

    const isCryptoFlow = methodStr.includes('digital') || methodStr.includes('arze') || methodStr.includes('crypto');
    const isDirectBuy = flowStr === 'direct_buy';
    const isExternalBotGateway = methodStr === 'iranpay2' || methodStr === 'tonpay' || methodStr === 'cubepay' || methodStr === 'blupal' || methodStr === 'variza' || methodStr === 'abangateway' || methodStr === 'atlaspay' || methodStr === 'tetrapay';
    const resolvedMode = isCryptoFlow
        ? (isDirectBuy ? 'crypto_offline' : 'recharge')
        : 'recharge';
    const gatewayLabel = isCryptoFlow ? 'ارز آفلاین' : methodLabel(methodStr);
    return mod.startGatewayWatch(view, {
        orderId,
        title:    `بررسی پرداخت ${gatewayLabel}...`,
        subtitle: 'به مینی‌اپ بازگشتید — وضعیت پرداخت در حال بررسی است.',
        mode:     resolvedMode,
        isCrypto: isCryptoFlow,
        gatewayUrl,
        keepMiniAppOpen: isExternalBotGateway,
        expiresAtSec,
        timeoutSec: (methodStr === 'cubepay' || methodStr === 'variza') ? 3600 : (methodStr === 'atlaspay' ? 1200 : (methodStr === 'tetrapay' ? 600 : 1800)),
        pollEverySec: 5,
        onSuccess: (st) => {
            const amount = Number(st.amount || 0).toLocaleString('en-US');
            view.innerHTML = `
                <article class="card card-window">
                    <header class="card-window-bar"><span class="dots"><span></span><span></span><span></span></span><span class="window-url">faoxima/recharge/done</span></header>
                    <div class="card-body" style="text-align:center;padding:22px">
                        <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">${icon('checkCircle', 'class="ico ico-xxl ico-success"')}</div>
                        <h2 style="margin:6px 0;font-size:18px">شارژ کیف پول موفق</h2>
                        <p class="muted">مبلغ ${amount} تومان به حساب شما اضافه شد.</p>
                        <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${st.order_id || '—'}</span></div>
                        <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                            <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                            <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                        </div>
                    </div>
                </article>`;
        },
        onFail: (st) => {
            view.innerHTML = `
                <article class="card card-window">
                    <header class="card-window-bar"><span class="dots"><span></span><span></span><span></span></span><span class="window-url">faoxima/recharge/fail</span></header>
                    <div class="card-body" style="text-align:center;padding:22px">
                        <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">${icon('xCircle', 'class="ico ico-xxl ico-warn"')}</div>
                        <h2 style="margin:6px 0;font-size:18px">پرداخت ناموفق بود</h2>
                        <p class="muted">${(st && st.reason) || 'تراکنش تایید نشد'}</p>
                        <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                            <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                            <a href="#/" class="btn btn-ghost btn-block" style="flex:1">خانه</a>
                        </div>
                    </div>
                </article>`;
        },
        onCancel: () => { window.location.hash = '#/'; },
    });
}


let _serviceModulePromise = null;
function loadServicePage() {
    if (_serviceModulePromise) return _serviceModulePromise;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const hardR = (typeof location !== 'undefined') ? new URLSearchParams(location.search).get('_r') : null;
    const ver = (hardR || cfg.version || cfg.cacheBust || Date.now()).toString();


    const url = new URL('./pages/service.js', import.meta.url);
    url.searchParams.set('v', `${ver}-0.0.54`);
    _serviceModulePromise = import(url.href).catch((err) => {


        console.warn('[router] dynamic import of service.js with cache-bust failed, retrying plain', err);
        _serviceModulePromise = null;
        return import('./pages/service.js');
    });
    return _serviceModulePromise;
}


async function serviceDetailPage(view, ...params) {
    const mod = await loadServicePage();
    return mod.service(view, ...params);
}

let _usertestModulePromise = null;
function loadUsertestPage() {
    if (_usertestModulePromise) return _usertestModulePromise;
    _usertestModulePromise = import('./pages/usertest.js');
    return _usertestModulePromise;
}
async function usertestPage(view) {
    const mod = await loadUsertestPage();
    return mod.usertest(view);
}

let _ticketsModulePromise = null;
function loadTicketsPage() {
    if (_ticketsModulePromise) return _ticketsModulePromise;
    _ticketsModulePromise = import('./pages/tickets.js');
    return _ticketsModulePromise;
}
async function ticketsPage(view) {
    const mod = await loadTicketsPage();
    return mod.tickets(view);
}
async function ticketNewPage(view) {
    const mod = await loadTicketsPage();
    return mod.ticketNew(view);
}
async function ticketDetailPage(view, ...params) {
    const mod = await loadTicketsPage();
    return mod.ticketDetail(view, ...params);
}

let _historyModulePromise = null;
function loadHistoryPage() {
    if (_historyModulePromise) return _historyModulePromise;
    _historyModulePromise = import('./pages/history.js');
    return _historyModulePromise;
}
async function historyPage(view) {
    const mod = await loadHistoryPage();
    return mod.history(view);
}

let _transferModulePromise = null;
function loadTransferPage() {
    if (_transferModulePromise) return _transferModulePromise;
    _transferModulePromise = import('./pages/transfer.js');
    return _transferModulePromise;
}
async function transferPage(view) {
    const mod = await loadTransferPage();
    return mod.transfer(view);
}

const routes = [
    { pattern: /^\/?$/,                         render: home,              key: '/' },
    { pattern: /^\/services\/?$/,               render: servicesPage,      key: '/services' },
    { pattern: /^\/services\/([^/]+)\/?$/,      render: serviceDetailPage, key: '/services' },
    { pattern: /^\/buy\/?$/,                    render: buyPage,           key: '/buy' },
    { pattern: /^\/usertest\/?$/,                render: usertestPage,      key: '/buy' },
    { pattern: /^\/account\/?$/,                render: accountPage,       key: '/account' },
    { pattern: /^\/settings\/?$/,               render: settingsPage,      key: '/account' },
    { pattern: /^\/recharge\/?$/,               render: rechargePage,      key: '/account' },
    { pattern: /^\/tickets\/?$/,                render: ticketsPage,       key: '/account' },
    { pattern: /^\/tickets\/new\/?$/,           render: ticketNewPage,     key: '/account' },
    { pattern: /^\/tickets\/([^/]+)\/?$/,       render: ticketDetailPage,  key: '/account' },
    { pattern: /^\/watch\/([^/]+)\/?$/,         render: watchResumePage,   key: '/account' },
    { pattern: /^\/history\/?$/,                render: historyPage,       key: '/account' },
    { pattern: /^\/transfer\/?$/,               render: transferPage,      key: '/account' },
];

let currentCleanup = null;


function readPath() {
    let raw = window.location.hash || '';
    if (raw.startsWith('#')) raw = raw.slice(1);

    if (raw.includes('tgWebApp')) {
        const idx = raw.indexOf('tgWebApp');
        let cut = idx;
        while (cut > 0 && (raw[cut - 1] === '&' || raw[cut - 1] === '?')) cut--;
        raw = raw.slice(0, cut);
    }

    raw = raw.split('?')[0].split('&')[0];
    if (!raw || raw === '/') return '/';
    if (!raw.startsWith('/')) raw = '/' + raw;
    return raw;
}

function notFound(view) {
    view.innerHTML = `
        <div class="empty">
            ${icon('xCircle', 'class="ico ico-xxl ico-muted"')}
            <h3>صفحه پیدا نشد</h3>
            <p class="muted">آدرس درخواستی موجود نیست.</p>
            <a href="#/" class="btn btn-primary mt-md">${icon('home', 'class="ico ico-leading"')}<span>بازگشت به خانه</span></a>
        </div>
    `;
}

async function dispatch() {
    const view = document.getElementById('view');
    if (!view) return;

    const path = readPath();

    if (typeof currentCleanup === 'function') {
        try { currentCleanup(); } catch (_) {}
        currentCleanup = null;
    }

    let matched = null;
    let params = [];
    for (const r of routes) {
        const m = path.match(r.pattern);
        if (m) {
            matched = r;
            params = m.slice(1);
            break;
        }
    }

    document.querySelectorAll('.tab').forEach((t) => {
        const isActive = matched && t.dataset.route === matched.key;
        t.classList.toggle('is-active', !!isActive);
    });

    if (!matched) {
        notFound(view);
        return;
    }

    try {
        const cleanup = await matched.render(view, ...params);
        if (typeof cleanup === 'function') currentCleanup = cleanup;
    } catch (err) {
        console.error('[route] render failed:', err);
        view.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در بارگذاری صفحه</h3>
                <p class="muted">${escapeHtml(err && err.message ? err.message : 'unknown')}</p>
                <button class="btn btn-ghost mt-md" onclick="location.reload()">${icon('refresh', 'class="ico ico-leading"')}<span>تلاش مجدد</span></button>
            </div>
        `;
    }

    try { window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' }); } catch (_) {}
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

export function start() {
    window.addEventListener('hashchange', dispatch);
    dispatch();
}

export function navigate(path) {
    if (!path.startsWith('#')) path = '#' + (path.startsWith('/') ? path : '/' + path);
    if (window.location.hash !== path) {
        window.location.hash = path;
    } else {
        dispatch();
    }
}

export function refresh() {
    dispatch();
}
