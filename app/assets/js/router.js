import { home } from './pages/home.js';
import { services as servicesPage } from './pages/services.js';
import { service as serviceDetailPage } from './pages/service.js';
import { buy as buyPage } from './pages/buy.js';
import { account as accountPage } from './pages/account.js';
import { settings as settingsPage } from './pages/settings.js';

const routes = [
    { pattern: /^\/?$/,                         render: home,              key: '/' },
    { pattern: /^\/services\/?$/,               render: servicesPage,      key: '/services' },
    { pattern: /^\/services\/([^/]+)\/?$/,      render: serviceDetailPage, key: '/services' },
    { pattern: /^\/buy\/?$/,                    render: buyPage,           key: '/buy' },
    { pattern: /^\/account\/?$/,                render: accountPage,       key: '/account' },
    { pattern: /^\/settings\/?$/,               render: settingsPage,      key: '/account' },
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
            <span class="glyph">404</span>
            <h3>صفحه پیدا نشد</h3>
            <p class="muted">آدرس درخواستی موجود نیست.</p>
            <a href="#/" class="btn btn-primary mt-md">بازگشت به خانه</a>
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
                <span class="glyph">!</span>
                <h3>خطا در بارگذاری صفحه</h3>
                <p class="muted">${escapeHtml(err && err.message ? err.message : 'unknown')}</p>
                <button class="btn btn-ghost mt-md" onclick="location.reload()">تلاش مجدد</button>
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

