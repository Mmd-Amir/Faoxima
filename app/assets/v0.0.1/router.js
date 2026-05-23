import { home } from './pages/home.js';
import { services as servicesPage } from './pages/services.js';

import { buy as buyPage } from './pages/buy.js';
import { account as accountPage } from './pages/account.js';
import { settings as settingsPage } from './pages/settings.js';
import { recharge as rechargePage } from './pages/recharge.js';
import { icon } from './icons.js';


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
    try {
        const statusResp = await import('./api.js').then(m => m.call('payment_status', { params: { order_id: orderId } }));
        const obj = statusResp?.obj || {};
        methodStr = String(obj.method || '').toLowerCase();
        flowStr = String(obj.flow || '').toLowerCase();

        const expiresAtFromServer = Number(obj.expires_at || 0);
        if (expiresAtFromServer > 0) {
            expiresAtSec = expiresAtFromServer;
        } else {
            const hashAt = Number(obj.hash_at || 0);
            if (hashAt > 0) {
                expiresAtSec = hashAt + 600;
            }
        }
    } catch (_) {  }

    const isCryptoFlow = methodStr.includes('digital') || methodStr.includes('arze') || methodStr.includes('crypto');
    const isDirectBuy = flowStr === 'direct_buy';
    const resolvedMode = isCryptoFlow
        ? (isDirectBuy ? 'crypto_offline' : 'recharge')
        : 'recharge';
    return mod.startGatewayWatch(view, {
        orderId,
        title:    isCryptoFlow ? 'بررسی پرداخت ارز آفلاین...' : 'بررسی پرداخت Plisio...',
        subtitle: 'به مینی‌اپ بازگشتید — وضعیت پرداخت در حال بررسی است.',
        mode:     resolvedMode,
        isCrypto: isCryptoFlow,
        expiresAtSec,
        timeoutSec: 600,
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
    const ver = (cfg.version || cfg.cacheBust || Date.now()).toString();


    const url = new URL('./pages/service.js', import.meta.url);
    url.searchParams.set('v', ver);
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

const routes = [
    { pattern: /^\/?$/,                         render: home,              key: '/' },
    { pattern: /^\/services\/?$/,               render: servicesPage,      key: '/services' },
    { pattern: /^\/services\/([^/]+)\/?$/,      render: serviceDetailPage, key: '/services' },
    { pattern: /^\/buy\/?$/,                    render: buyPage,           key: '/buy' },
    { pattern: /^\/account\/?$/,                render: accountPage,       key: '/account' },
    { pattern: /^\/settings\/?$/,               render: settingsPage,      key: '/account' },
    { pattern: /^\/recharge\/?$/,               render: rechargePage,      key: '/account' },
    { pattern: /^\/watch\/([^/]+)\/?$/,         render: watchResumePage,   key: '/account' },
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

