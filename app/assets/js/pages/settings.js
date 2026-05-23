import { hapticImpact } from '../telegram.js';

const THEMES = [
    { key: 'gold',   name: 'طلایی (پیش‌فرض)', emoji: '🟡', color: '#d4b878', bright: '#e2c98c' },
    { key: 'red',    name: 'قرمز',            emoji: '🔴', color: '#e57373', bright: '#ef9a9a' },
    { key: 'blue',   name: 'آبی',             emoji: '🔵', color: '#64a8e8', bright: '#82bdf3' },
    { key: 'purple', name: 'بنفش',            emoji: '🟣', color: '#b48def', bright: '#c8a8f3' },
    { key: 'yellow', name: 'زرد',             emoji: '🟡', color: '#f4d35e', bright: '#f8e285' },
    { key: 'green',  name: 'سبز',             emoji: '🟢', color: '#7fc987', bright: '#9bd5a3' },
    { key: 'orange', name: 'نارنجی',           emoji: '🟠', color: '#f0a868', bright: '#f5be8b' },
];

const STORAGE_KEY = 'faoxima.theme.accent';


export function applyTheme(themeKey) {
    const theme = THEMES.find((t) => t.key === themeKey) || THEMES[0];
    const root = document.documentElement;
    root.style.setProperty('--gold', theme.color);
    root.style.setProperty('--gold-bright', theme.bright);
    root.style.setProperty('--accent', theme.color);
    root.style.setProperty('--accent-bright', theme.bright);

    root.style.setProperty('--gold-soft',   hexToRgba(theme.color, 0.10));
    root.style.setProperty('--gold-soft-2', hexToRgba(theme.color, 0.18));
    root.style.setProperty('--accent-soft',   hexToRgba(theme.color, 0.10));
    root.style.setProperty('--accent-soft-2', hexToRgba(theme.color, 0.18));
    root.style.setProperty('--border',         hexToRgba(theme.color, 0.12));
    root.style.setProperty('--border-strong',  hexToRgba(theme.color, 0.28));
    root.dataset.theme = theme.key;
}

export function loadSavedTheme() {
    let key = 'gold';
    try {
        key = localStorage.getItem(STORAGE_KEY) || 'gold';
    } catch (_) {  }
    applyTheme(key);
    return key;
}

function saveTheme(key) {
    try { localStorage.setItem(STORAGE_KEY, key); } catch (_) {}
}

function hexToRgba(hex, alpha) {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export async function settings(view) {
    const current = loadSavedTheme();

    view.innerHTML = `
        <a href="#/" class="page-back">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            بازگشت
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/settings</span>
            </header>
            <div class="card-body">
                <p class="section-title">رنگ اصلی</p>
                <h2 class="section-headline">یک رنگ برای مینی‌اپ انتخاب کنید</h2>
                <p class="muted center mb-md" style="font-size:13px">انتخاب شما به صورت خودکار ذخیره می‌شود.</p>

                <div class="theme-grid" id="theme-grid">
                    ${THEMES.map((t) => `
                        <button class="theme-tile ${t.key === current ? 'is-active' : ''}" data-theme="${t.key}" type="button" aria-label="${escapeAttr(t.name)}">
                            <span class="theme-swatch" style="background:${t.color}"></span>
                            <span class="theme-name">${t.name}</span>
                        </button>
                    `).join('')}
                </div>

                <div class="card-section">
                    <p class="muted mono center" style="font-size:11px">نسخه: ${escapeHtml((function(){var v=(((window.__APP_CONFIG__||{}).version)||'0.0.1').toString();return /^v/i.test(v)?v:('v'+v);})())}</p>
                </div>
            </div>
        </article>
    `;

    const grid = view.querySelector('#theme-grid');
    if (!grid) return;

    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-theme]');
        if (!btn) return;
        const key = btn.dataset.theme;
        if (!key) return;

        applyTheme(key);
        saveTheme(key);
        hapticImpact('light');

        grid.querySelectorAll('[data-theme]').forEach((b) => {
            b.classList.toggle('is-active', b.dataset.theme === key);
        });
    });
}

function escapeAttr(s) {
    return String(s == null ? '' : s).replace(/"/g, '&quot;');
}
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}

