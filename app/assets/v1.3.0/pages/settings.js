import { hapticImpact, hapticNotify } from '../telegram.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { call } from '../api.js?v=0.0.53';
import { getToken } from '../state.js';
import { toast } from '../utils.js?v=0.0.52';
import { applyBrand } from '../brand.js?v=0.0.52';
import { applyColor, applyMode, getUserMode, THEMES as ACCENT_THEMES } from '../theme.js?v=0.0.52';

const THEME_NAMES = {
    gold: 'طلایی', red: 'قرمز', blue: 'آبی', purple: 'بنفش',
    yellow: 'زرد', green: 'سبز', orange: 'نارنجی',
};
const THEMES = ACCENT_THEMES.map((t) => ({ ...t, name: THEME_NAMES[t.key] || t.key }));

function normalizeHex(s) {
    const m = /^#?([0-9a-fA-F]{6})$/.exec(String(s == null ? '' : s).trim());
    return m ? ('#' + m[1].toLowerCase()) : null;
}

function globalMode() {
    try {
        const cfg = (window.__APP_CONFIG__ || {});
        if (cfg.forceDark) {
            return 'dark';
        }
    } catch (_) {  }
    const userMode = getUserMode();
    if (userMode) {
        return userMode;
    }
    try {
        const raw = localStorage.getItem('faoxima.brand');
        if (raw) { const o = JSON.parse(raw); if (o && (o.mode === 'light' || o.mode === 'dark')) {
            return o.mode;
        } }
    } catch (_) {  }
    try {
        const f = (window.__FAOXIMA__ || {});
        if (f.brand && (f.brand.mode === 'light' || f.brand.mode === 'dark')) {
            return f.brand.mode;
        }
    } catch (_) {  }
    try {
        const c = (window.__APP_CONFIG__ || {});
        if (c.brand && (c.brand.mode === 'light' || c.brand.mode === 'dark')) {
            return c.brand.mode;
        }
    } catch (_) {  }
    return 'dark';
}

function globalAccent() {
    try {
        const raw = localStorage.getItem('faoxima.brand');
        if (raw) { const o = JSON.parse(raw); if (o && o.accent) return o.accent; }
    } catch (_) {  }
    try {
        const f = (window.__FAOXIMA__ || {});
        if (f.brand && f.brand.accent) return f.brand.accent;
    } catch (_) {  }
    try {
        const c = (window.__APP_CONFIG__ || {});
        if (c.brand && c.brand.accent) return c.brand.accent;
    } catch (_) {  }
    return 'purple';
}

export function loadSavedTheme() {
    const accent = globalAccent();
    applyColor(accent);
    const mode = globalMode();
    applyMode(mode);
    try { window.__applyAccent = applyColor; } catch (_) {  }
    return accent;
}

try { window.__applyAccent = applyColor; } catch (_) {  }

function escapeAttr(s) {
    return String(s == null ? '' : s).replace(/"/g, '&quot;');
}
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}

export async function settings(view) {
    loadSavedTheme();

    view.innerHTML = `
        <a href="#/" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="settings-page">
            <div id="settings-body">
                <div class="empty">
                    ${icon('settings', 'class="ico ico-xxl ico-accent"')}
                    <h3>تنظیمات</h3>
                    <p class="muted">در حال بارگذاری…</p>
                </div>
            </div>
        </article>
    `;

    let obj = {};
    try {
        const res = await call('brand_info');
        obj = res?.obj || {};
    } catch (_) {  }

    const body = view.querySelector('#settings-body');
    if (!body) return;

    if (!obj.is_admin) {
        body.innerHTML = `
            <div class="empty">
                ${icon('settings', 'class="ico ico-xxl"')}
                <h3>این بخش مخصوص ادمین است</h3>
                <p class="muted">ظاهرِ مینی‌اپ توسط ادمین تنظیم می‌شود.</p>
                <a href="#/" class="btn btn-primary mt-md">
                    ${icon('chevronLeft', 'class="ico ico-leading"')}
                    <span>بازگشت به خانه</span>
                </a>
            </div>
        `;
        return;
    }

    renderAdminPanel(body, obj);
}

function renderAdminPanel(host, brand) {
    const accent = globalAccent();
    const curHex = normalizeHex(accent) || THEMES.find(t => t.key === accent)?.color || normalizeHex(brand.accent) || THEMES.find(t => t.key === brand.accent)?.color || THEMES.find(t => t.key === 'purple').color;

    const tiles = THEMES.map((t) => `
        <button class="theme-tile ${normalizeHex(t.color) === normalizeHex(curHex) ? 'is-active' : ''}" data-accent="${t.color}" type="button" aria-label="${escapeAttr(t.name)}" aria-pressed="${normalizeHex(t.color) === normalizeHex(curHex)}">
            <span class="theme-swatch" style="background:${t.color}"></span>
            <span class="theme-name">${t.name}</span>
            ${normalizeHex(t.color) === normalizeHex(curHex) ? icon('check', 'class="ico ico-accent"') : ''}
        </button>
    `).join('');

    host.innerHTML = `
        <header class="settings-hero"><span>${icon('settings')}</span><div><small>مدیریت ظاهر مینی‌اپ</small><h1>رنگ و نشان، به سلیقهٔ تو.</h1><p>تغییرات این صفحه برای همهٔ کاربران ذخیره می‌شود.</p></div></header>
        <section class="settings-color-card"><div class="settings-section-heading"><div><h2>رنگ اصلی</h2><p>یک رنگ انتخاب کن و نتیجه را همین‌جا ببین.</p></div><span class="settings-scope">سراسری</span></div>
        <div class="settings-preview" aria-label="پیش‌نمایش رنگ"><div class="settings-preview-top"><span>${icon('wallet')}</span><div><b>پیش‌نمایش ظاهر</b><small>رنگ دکمه‌ها، آیکون‌ها و حاشیه‌ها</small></div><span class="settings-preview-pill">نمونه</span></div><div class="settings-preview-bottom"><span class="settings-preview-bar"></span><span class="settings-preview-button">${icon('check')} ادامه</span></div></div>
        <div class="theme-grid" id="accent-grid">${tiles}</div>
        <div class="settings-custom-color"><label for="accent-hex">رنگ دلخواه <span>کد HEX</span></label><div><input id="accent-native" type="color" value="${escapeAttr(curHex)}" aria-label="انتخاب رنگ دلخواه" /><input id="accent-hex" type="text" maxlength="7" placeholder="#7c5cff" value="${escapeAttr(curHex)}" class="input-mono" dir="ltr" spellcheck="false" aria-describedby="accent-status" /></div></div>
        <div class="settings-save-row"><span id="accent-status" role="status">رنگ فعلی مینی‌اپ</span><button id="accent-save" type="button" class="btn btn-primary">${icon('check')}<span class="accent-save-label">ذخیره رنگ</span></button></div></section>
        <details class="settings-accordion"><summary><span>${icon('bell')}</span><div><b>اعلان به کاربران</b><small>ارسال و مدیریت پیام‌های عمومی</small></div>${icon('chevronDown')}</summary><div id="admin-notification-host" class="settings-accordion-body"></div></details>
        <details class="settings-accordion"><summary><span>${icon('user')}</span><div><b>نام و نشان مینی‌اپ</b><small>عنوان، حروف نشان و تصویر لوگو</small></div>${icon('chevronDown')}</summary><div id="admin-brand-host" class="settings-accordion-body"></div></details>
    `;

    const grid = host.querySelector('#accent-grid');
    const $hex = host.querySelector('#accent-hex');
    const $native = host.querySelector('#accent-native');
    const $save = host.querySelector('#accent-save');
    const $saveLabel = $save.querySelector('.accent-save-label');
    let savedHex = curHex;
    const $status = host.querySelector('#accent-status');

    function setPending(hex) {
        const norm = normalizeHex(hex);
        if (!norm) return;
        $status.textContent = norm === savedHex ? 'رنگ فعلی مینی‌اپ' : 'پیش‌نمایش؛ هنوز ذخیره نشده';
        $hex.removeAttribute('aria-invalid');
        applyColor(norm);
        $hex.value = norm;
        try { $native.value = norm; } catch (_) {  }
        grid.querySelectorAll('[data-accent]').forEach((b) => {
            const match = normalizeHex(b.dataset.accent) === norm;
            b.classList.toggle('is-active', match);
            b.setAttribute('aria-pressed', String(match));
            const c = b.querySelector('svg'); if (c) c.remove();
            if (match) b.insertAdjacentHTML('beforeend', icon('check', 'class="ico ico-accent"'));
        });
    }

    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-accent]');
        if (!btn) return;
        hapticImpact('light');
        setPending(btn.dataset.accent);
    });
    $native.addEventListener('input', () => setPending($native.value));
    $hex.addEventListener('input', () => { const n = normalizeHex($hex.value); if (n) setPending(n); });

    $save.addEventListener('click', async () => {
        if ($save.disabled) return;
        const norm = normalizeHex($hex.value);
        if (!norm) { $hex.setAttribute('aria-invalid', 'true'); $status.textContent = 'کد رنگ شش‌رقمی معتبر وارد کن؛ مثلاً #7c5cff'; $hex.focus(); return; }
        const old = $saveLabel.textContent;
        $save.disabled = true;
        $saveLabel.textContent = 'در حال ذخیره…';
        try {
            const res = await call('brand_save', { method: 'POST', body: { accent: norm }, timeoutMs: 15000 });
            const o = res?.obj || {};
            const saved = normalizeHex(o.accent) || norm;
            savedHex = saved;
            setPending(saved);
            $status.textContent = 'برای همهٔ کاربران ذخیره شد';
            try {
                const raw = localStorage.getItem('faoxima.brand');
                const cache = raw ? JSON.parse(raw) : {};
                cache.accent = saved;
                localStorage.setItem('faoxima.brand', JSON.stringify(cache));
            } catch (_) {  }
            try {
                const c = (window.__APP_CONFIG__ || {});
                if (c.brand) c.brand.accent = saved;
            } catch (_) {  }
            hapticNotify('success');
            toast('رنگ برای همهٔ کاربران ذخیره شد', 'success', 2800);
        } catch (err) {
            hapticNotify('error');
            $status.textContent = err.message || 'ذخیره انجام نشد؛ دوباره تلاش کن.';
            toast(err.message || 'خطا در ذخیره رنگ', 'error', 4000);
        } finally {
            $save.disabled = false;
            $saveLabel.textContent = old;
        }
    });

    renderNotificationSection(host.querySelector('#admin-notification-host'));
    renderBrandFields(host.querySelector('#admin-brand-host'), brand);
}

function fmtRemainingHours(expiresAt) {
    const secs = Number(expiresAt) - Math.floor(Date.now() / 1000);
    if (secs <= 0) return null;
    const hrs = Math.ceil(secs / 3600);
    return hrs;
}

async function renderNotificationSection(host) {
    if (!host) return;

    host.innerHTML = `
        <p class="section-title">${icon('bell')} اعلان به کاربران</p>
        <p class="muted" style="font-size:13px">این پیام یک‌بار به‌صورت شناور برای همهٔ کاربران نمایش داده می‌شود و تا انقضا با آیکون زنگ قابل مشاهدهٔ مجدد است.</p>
        <div id="notif-current"></div>
        <div class="form-row mt-md">
            <label class="muted" style="font-size:12px" for="notif-message">متن اعلان</label>
            <textarea id="notif-message" maxlength="500" rows="3" placeholder="متن اعلان..."></textarea>
        </div>
        <div class="seg mt-sm" id="notif-duration-seg">
            <button type="button" class="seg-btn is-active" data-duration="24">۲۴ ساعت</button>
            <button type="button" class="seg-btn" data-duration="48">۴۸ ساعت</button>
            <button type="button" class="seg-btn" data-duration="custom">مدت دلخواه</button>
        </div>
        <div class="form-row mt-sm" id="notif-custom-row" style="display:none">
            <label class="muted" style="font-size:12px" for="notif-custom-minutes">مدت به دقیقه</label>
            <input id="notif-custom-minutes" type="number" inputmode="numeric" min="1" step="1" placeholder="مثلاً ۹۰" />
        </div>
        <button id="notif-save" type="button" class="btn btn-primary btn-block mt-sm">
            ${icon('bell', 'class="ico ico-leading"')}
            <span class="notif-save-label">ارسال اعلان</span>
        </button>
        <div class="row-spread mt-md" style="align-items:center">
            <p class="muted" style="font-size:12px;margin:0">اعلان‌های اخیر</p>
            <button id="notif-manage-refresh" type="button" class="btn btn-ghost" style="padding:4px 10px;font-size:12px">
                ${icon('refresh', 'class="ico ico-leading"')}
                <span>به‌روزرسانی</span>
            </button>
        </div>
        <div id="notif-manage-list" class="mt-sm"></div>
    `;

    const $current = host.querySelector('#notif-current');
    const $message = host.querySelector('#notif-message');
    const $seg = host.querySelector('#notif-duration-seg');
    const $save = host.querySelector('#notif-save');
    const $saveLabel = $save.querySelector('.notif-save-label');
    const $manageList = host.querySelector('#notif-manage-list');
    const $manageRefresh = host.querySelector('#notif-manage-refresh');
    const $customRow = host.querySelector('#notif-custom-row');
    const $customMinutes = host.querySelector('#notif-custom-minutes');
    let duration = 24;

    function renderCurrent(notif) {
        if (!notif) {
            $current.innerHTML = `<p class="muted" style="font-size:12px">اعلان فعالی وجود ندارد.</p>`;
            return;
        }
        const hrs = fmtRemainingHours(notif.expires_at);
        $current.innerHTML = `
            <div class="callout mt-sm" style="gap:10px">
                ${icon('bell', 'style="width:18px;height:18px;flex-shrink:0"')}
                <div style="flex:1;min-width:0">
                    <p style="margin:0;font-size:12px;color:var(--text-muted)">اعلان فعال — ${hrs ? `تا ${hrs} ساعت دیگر` : 'در حال انقضا'}</p>
                    <p style="margin:4px 0 0;font-size:13px">${escapeHtml(notif.message)}</p>
                </div>
            </div>
        `;
    }

    function fmtNotifDate(unixSeconds) {
        try {
            return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(unixSeconds * 1000));
        } catch (_) {
            return '';
        }
    }

    function renderManageList(notifications) {
        if (!notifications.length) {
            $manageList.innerHTML = `<p class="muted" style="font-size:12px">اعلانی برای نمایش وجود ندارد.</p>`;
            return;
        }
        $manageList.innerHTML = notifications.map((n) => `
            <div class="callout mt-sm" style="gap:10px;align-items:center" data-notif-id="${n.id}">
                <div style="flex:1;min-width:0">
                    <p style="margin:0;font-size:11px;color:var(--text-muted);direction:rtl;text-align:right;unicode-bidi:isolate">${escapeHtml(fmtNotifDate(n.created_at))}</p>
                    <p style="margin:4px 0 0;font-size:13px;white-space:pre-wrap">${escapeHtml(n.message)}</p>
                </div>
                <button type="button" class="notif-delete-btn" data-id="${n.id}" aria-label="حذف اعلان">
                    ${icon('trash')}
                </button>
            </div>
        `).join('');
    }

    async function loadManageList() {
        $manageList.innerHTML = `<p class="muted" style="font-size:12px">در حال بارگذاری…</p>`;
        try {
            const res = await call('notification_recent');
            renderManageList(res?.obj?.notifications || []);
        } catch (err) {
            $manageList.innerHTML = `<p class="muted" style="font-size:12px">خطا در بارگذاری اعلان‌ها.</p>`;
        }
    }

    $manageList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.notif-delete-btn');
        if (!btn || btn.disabled) return;
        const id = Number(btn.dataset.id);
        if (!id) return;

        btn.disabled = true;
        try {
            await call('notification_delete', { method: 'POST', body: { id }, timeoutMs: 15000 });
            hapticNotify('success');
            toast('اعلان حذف شد', 'success', 2200);
            const row = btn.closest('[data-notif-id]');
            if (row) row.remove();
            if (!$manageList.querySelector('[data-notif-id]')) {
                renderManageList([]);
            }
            try {
                const res = await call('notification_info');
                renderCurrent(res?.obj?.notification || null);
            } catch (_) {  }
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در حذف اعلان', 'error', 3500);
            btn.disabled = false;
        }
    });

    $manageRefresh.addEventListener('click', () => {
        hapticImpact('light');
        loadManageList();
    });

    try {
        const res = await call('notification_info');
        renderCurrent(res?.obj?.notification || null);
    } catch (_) {
        renderCurrent(null);
    }

    loadManageList();

    $seg.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-duration]');
        if (!btn) return;
        hapticImpact('light');
        duration = btn.dataset.duration === 'custom' ? 'custom' : (Number(btn.dataset.duration) || 24);
        $seg.querySelectorAll('[data-duration]').forEach((b) => b.classList.toggle('is-active', b === btn));
        $customRow.style.display = duration === 'custom' ? '' : 'none';
    });

    $save.addEventListener('click', async () => {
        if ($save.disabled) return;
        const message = ($message.value || '').trim();
        if (!message) { toast('متن اعلان را وارد کنید', 'error', 3000); return; }

        const body = { message };
        if (duration === 'custom') {
            const minutes = Math.floor(Number($customMinutes.value));
            if (!Number.isFinite(minutes) || minutes <= 0) {
                toast('مدت دلخواه باید یک عدد صحیح مثبت باشد', 'error', 3000);
                return;
            }
            body.duration_minutes = minutes;
        } else {
            body.duration = duration;
        }

        const old = $saveLabel.textContent;
        $save.disabled = true;
        $saveLabel.textContent = 'در حال ارسال…';
        try {
            const res = await call('notification_save', { method: 'POST', body, timeoutMs: 15000 });
            const o = res?.obj || {};
            renderCurrent(o.notification || null);
            $message.value = '';
            hapticNotify('success');
            toast(o.message || 'اعلان با موفقیت ارسال شد', 'success', 2800);
            loadManageList();
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ارسال اعلان', 'error', 4000);
        } finally {
            $save.disabled = false;
            $saveLabel.textContent = old;
        }
    });
}

function renderBrandFields(host, brand) {
    if (!host) return;
    host.innerHTML = `
        <p class="section-title">${icon('settings')} برند مینی‌اپ</p>
        <p class="muted" style="font-size:13px">نشانِ نوار بالای مینی‌اپ. تصویر آپلودی به‌صورت خودکار به اندازه‌ی مناسب درآورده می‌شود.</p>

        <div class="row-spread mt-md" style="gap:10px;align-items:center">
            <div id="brand-logo-preview" style="width:64px;height:64px;border-radius:14px;background:var(--accent);display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--on-accent);font-weight:700;flex-shrink:0">
                ${brand.avatar_state === 'custom'
                    ? `<img src="${escapeAttr(brand.logo_url)}" alt="logo" style="width:100%;height:100%;object-fit:cover" />`
                    : `<span id="brand-mark-preview-text">${escapeHtml(brand.mark || 'M')}</span>`}
            </div>
            <div class="form-row" style="flex:1;margin:0">
                <label class="muted" style="font-size:12px" for="brand-mark-input">حروف نشان (۱ تا ۲ کاراکتر)</label>
                <input id="brand-mark-input" type="text" maxlength="2" placeholder="M" value="${escapeAttr(brand.mark || '')}" />
            </div>
        </div>
        <div class="form-row mt-sm">
            <label class="muted" style="font-size:12px" for="brand-title-input">عنوان برند در کنار نشان (۱ تا ۱۰ کاراکتر)</label>
            <input id="brand-title-input" type="text" maxlength="10" placeholder="مثلاً Faoxima" value="${escapeAttr(brand.title || '')}" />
        </div>
        <button id="brand-save-btn" type="button" class="btn btn-primary btn-block mt-sm">
            ${icon('check', 'class="ico ico-leading"')}
            <span class="brand-save-label">ذخیره نشان</span>
        </button>

        <p class="section-title mt-md">لوگو (اختیاری)</p>
        <div class="row-spread" style="gap:10px;align-items:center">
            <input id="brand-logo-file" type="file" accept="image/*" style="display:none" />
            <button id="brand-logo-pick" type="button" class="btn btn-ghost btn-block">
                ${icon('download', 'class="ico ico-leading"')}
                <span>انتخاب تصویر</span>
            </button>
            ${brand.avatar_state === 'custom' || brand.avatar_state === 'default' ? `
            <button id="brand-logo-clear" type="button" class="btn btn-ghost btn-block">
                ${icon('close', 'class="ico ico-leading"')}
                <span>حذف لوگو</span>
            </button>` : ''}
        </div>
        <p class="muted mono mt-sm" style="font-size:11px">PNG/JPG/WebP — حداکثر ۴ مگابایت. اندازه نهایی ۲۵۶×۲۵۶ پیکسل.</p>
    `;

    const $mark = host.querySelector('#brand-mark-input');
    const $title = host.querySelector('#brand-title-input');
    const $preview = host.querySelector('#brand-logo-preview');
    const $save = host.querySelector('#brand-save-btn');
    const $saveLabel = $save.querySelector('.brand-save-label');

    function updateMarkPreview(value) {
        if (brand.avatar_state === 'custom') return;
        $preview.innerHTML = `<span id="brand-mark-preview-text">${escapeHtml(value || 'M')}</span>`;
    }

    $mark.addEventListener('input', () => updateMarkPreview($mark.value.trim()));

    $save.addEventListener('click', async () => {
        if ($save.disabled) return;
        const mark = ($mark.value || '').trim();
        const title = ($title.value || '').trim();
        if (title.length < 1 || title.length > 10) {
            toast('عنوان برند باید بین ۱ تا ۱۰ کاراکتر باشد', 'error', 3000);
            return;
        }
        const old = $saveLabel.textContent;
        $save.disabled = true;
        $saveLabel.textContent = 'در حال ذخیره…';
        try {
            const res = await call('brand_save', { method: 'POST', body: { mark, title }, timeoutMs: 15000 });
            const obj = res?.obj || {};
            brand.mark = obj.mark || mark;
            brand.title = obj.title || title;
            applyBrand(obj);
            updateMarkPreview(brand.mark);
            hapticNotify('success');
            toast(obj.message || 'نشان ذخیره شد', 'success', 2500);
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ذخیره نشان', 'error', 4000);
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
        await uploadLogo(host, f, brand);
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
                renderBrandFields(host, obj);
                toast('لوگو حذف شد', 'success', 2000);
            } catch (err) {
                toast(err.message || 'خطا در حذف لوگو', 'error', 4000);
            }
        });
    }
}

async function uploadLogo(host, file, brand) {
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
        renderBrandFields(host, obj);
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
