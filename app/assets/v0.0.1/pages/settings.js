import { hapticImpact, hapticNotify } from '../telegram.js';
import { icon } from '../icons.js';
import { call } from '../api.js';
import { getToken } from '../state.js';
import { toast } from '../utils.js';
import { applyBrand } from '../brand.js';

const THEMES = [
    { key: 'gold',   name: 'طلایی (پیش‌فرض)', color: '#d4b878', bright: '#e2c98c' },
    { key: 'red',    name: 'قرمز',            color: '#e57373', bright: '#ef9a9a' },
    { key: 'blue',   name: 'آبی',             color: '#64a8e8', bright: '#82bdf3' },
    { key: 'purple', name: 'بنفش',            color: '#b48def', bright: '#c8a8f3' },
    { key: 'yellow', name: 'زرد',             color: '#f4d35e', bright: '#f8e285' },
    { key: 'green',  name: 'سبز',             color: '#7fc987', bright: '#9bd5a3' },
    { key: 'orange', name: 'نارنجی',           color: '#f0a868', bright: '#f5be8b' },
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

function escapeAttr(s) {
    return String(s == null ? '' : s).replace(/"/g, '&quot;');
}
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}

export async function settings(view) {
    const current = loadSavedTheme();

    view.innerHTML = `
        <a href="#/" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/settings</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('settings')} رنگ اصلی</p>
                <h2 class="section-headline">یک رنگ برای مینی‌اپ انتخاب کنید</h2>
                <p class="muted center mb-md" style="font-size:13px">انتخاب شما به صورت خودکار ذخیره می‌شود.</p>

                <div class="theme-grid" id="theme-grid">
                    ${THEMES.map((t) => `
                        <button class="theme-tile ${t.key === current ? 'is-active' : ''}" data-theme="${t.key}" type="button" aria-label="${escapeAttr(t.name)}">
                            <span class="theme-swatch" style="background:${t.color}"></span>
                            <span class="theme-name">${t.name}</span>
                            ${t.key === current ? icon('check', 'class="ico ico-accent"') : ''}
                        </button>
                    `).join('')}
                </div>

                <div id="admin-brand-host"></div>

                <div class="card-section">
                    <p class="muted mono center" style="font-size:11px">نسخه: ${escapeHtml((function(){var v=(((window.__APP_CONFIG__||{}).version)||'0.0.1').toString();return /^v/i.test(v)?v:('v'+v);})())}</p>
                </div>
            </div>
        </article>
    `;

    const grid = view.querySelector('#theme-grid');
    if (grid) {
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
                const oldCheck = b.querySelector('svg');
                if (oldCheck) oldCheck.remove();
                if (b.dataset.theme === key) {
                    b.insertAdjacentHTML('beforeend', icon('check', 'class="ico ico-accent"'));
                }
            });
        });
    }


    try {
        const res = await call('brand_info');
        const obj = res?.obj || {};
        if (obj.is_admin) {
            renderAdminBrandPanel(view.querySelector('#admin-brand-host'), obj);
        }
    } catch (_) {  }
}


function renderAdminBrandPanel(host, brand) {
    if (!host) return;
    host.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('settings')} برند مینی‌اپ (مخصوص ادمین)</p>
            <p class="muted" style="font-size:13px">می‌توانید نام و نشان نمایش‌داده‌شده در نوار بالای مینی‌اپ را تغییر دهید. تصویر آپلودی به‌صورت خودکار به اندازه‌ی مناسب درآورده می‌شود.</p>

            <div class="form-row mt-md">
                <label class="muted" style="font-size:12px" for="brand-name-input">نام برند</label>
                <input id="brand-name-input" type="text" maxlength="40" placeholder="Faoxima"
                       value="${escapeAttr(brand.name || '')}" />
            </div>
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px" for="brand-mark-input">حروف نشان (M, F یا 1-4 کاراکتر)</label>
                <input id="brand-mark-input" type="text" maxlength="4" placeholder="M"
                       value="${escapeAttr(brand.mark || '')}" />
            </div>
            <button id="brand-save-btn" type="button" class="btn btn-primary btn-block mt-sm">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="brand-save-label">ذخیره نام و نشان</span>
            </button>

            <p class="section-title mt-md">لوگو (اختیاری)</p>
            <div class="row-spread" style="gap:10px;align-items:center">
                <div id="brand-logo-preview" style="width:64px;height:64px;border-radius:14px;background:var(--accent);display:flex;align-items:center;justify-content:center;overflow:hidden;color:#0a0907;font-weight:700">
                    ${brand.logo_url
                        ? `<img src="${escapeAttr(brand.logo_url)}" alt="logo" style="width:100%;height:100%;object-fit:cover" />`
                        : escapeHtml(brand.mark || 'M')}
                </div>
                <div style="flex:1">
                    <input id="brand-logo-file" type="file" accept="image/*" style="display:none" />
                    <button id="brand-logo-pick" type="button" class="btn btn-ghost btn-block">
                        ${icon('download', 'class="ico ico-leading"')}
                        <span>انتخاب تصویر</span>
                    </button>
                    ${brand.logo_url ? `
                    <button id="brand-logo-clear" type="button" class="btn btn-ghost btn-block mt-sm">
                        ${icon('close', 'class="ico ico-leading"')}
                        <span>حذف لوگو</span>
                    </button>` : ''}
                </div>
            </div>
            <p class="muted mono mt-sm" style="font-size:11px">PNG/JPG/WebP — حداکثر ۴ مگابایت. اندازه نهایی ۲۵۶×۲۵۶ پیکسل.</p>
        </div>
    `;


    const $name = host.querySelector('#brand-name-input');
    const $mark = host.querySelector('#brand-mark-input');
    const $save = host.querySelector('#brand-save-btn');
    const $saveLabel = $save.querySelector('.brand-save-label');

    $save.addEventListener('click', async () => {
        const name = ($name.value || '').trim();
        const mark = ($mark.value || '').trim();
        const old = $saveLabel.textContent;
        $save.disabled = true;
        $saveLabel.textContent = 'در حال ذخیره…';
        try {
            const res = await call('brand_save', { method: 'POST', body: { name, mark } });
            const obj = res?.obj || {};
            applyBrand(obj);
            hapticNotify('success');
            toast(obj.message || 'برند ذخیره شد', 'success', 2500);
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ذخیره برند', 'error', 4000);
        } finally {
            $save.disabled = false;
            $saveLabel.textContent = old;
        }
    });


    const $file = host.querySelector('#brand-logo-file');
    const $pick = host.querySelector('#brand-logo-pick');
    $pick.addEventListener('click', () => $file.click());
    $file.addEventListener('change', async () => {
        const f = $file.files && $file.files[0];
        if (!f) return;
        if (f.size > 4 * 1024 * 1024) {
            toast('حجم فایل نباید بیشتر از ۴ مگابایت باشد', 'error', 4000);
            $file.value = '';
            return;
        }
        await uploadLogo(host, f);
        $file.value = '';
    });


    const $clear = host.querySelector('#brand-logo-clear');
    if ($clear) {
        $clear.addEventListener('click', async () => {
            try {
                const fd = new FormData();
                fd.append('clear', '1');
                const obj = await uploadBrandLogoForm(fd);
                applyBrand(obj);
                renderAdminBrandPanel(host, obj);
                toast('لوگو حذف شد', 'success', 2000);
            } catch (err) {
                toast(err.message || 'خطا در حذف لوگو', 'error', 4000);
            }
        });
    }
}


async function uploadLogo(host, file) {
    const $pick = host.querySelector('#brand-logo-pick');
    const $oldLabel = $pick && $pick.querySelector('span');
    const old = $oldLabel ? $oldLabel.textContent : '';
    if ($pick) { $pick.disabled = true; }
    if ($oldLabel) { $oldLabel.textContent = 'در حال آپلود…'; }
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const obj = await uploadBrandLogoForm(fd);
        applyBrand(obj);
        renderAdminBrandPanel(host, obj);
        hapticNotify('success');
        toast(obj.message || 'لوگو ذخیره شد', 'success', 2500);
    } catch (err) {
        hapticNotify('error');
        toast(err.message || 'خطا در آپلود لوگو', 'error', 4000);
        if ($pick) $pick.disabled = false;
        if ($oldLabel) $oldLabel.textContent = old;
    }
}


async function uploadBrandLogoForm(formData) {
    const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
    const tok = getToken();
    const res = await fetch(`${apiUrl}/miniapp.php?actions=brand_upload_logo`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + (tok || '') },
        body: formData,
    });
    let envelope = null;
    try { envelope = await res.json(); } catch (_) {}
    if (!res.ok || !envelope || !envelope.status) {
        const msg = (envelope && envelope.msg) || `Upload failed (${res.status})`;
        throw new Error(msg);
    }
    return envelope.obj || {};
}

