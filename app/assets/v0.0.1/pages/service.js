import { call, ApiError } from '../api.js';
import {
    escapeHtml, fmtPrice, fmtGb, skeletonList, copyToClipboard, toast, trafficPercent,
} from '../utils.js';
import { hapticImpact, hapticNotify, showConfirm } from '../telegram.js';
import { icon } from '../icons.js';
import { renderMethodCard, handleInitResult } from '../payment-ui.js';

const ALL_ACTIONS = [
    { id: 'renew',           label: 'تمدید سرویس',            ico: 'rotate',     cls: '', inline: true  },
    { id: 'extra_time',      label: 'خرید زمان اضافه',         ico: 'hourglass',  cls: '', inline: true  },
    { id: 'extra_volume',    label: 'خرید حجم اضافه',          ico: 'box',        cls: '', inline: true  },
    { id: 'changelink',      label: 'تغییر لینک',              ico: 'link',       cls: '', inline: false },
    { id: 'refund',          label: 'بازگشت وجه',              ico: 'diamond',    cls: 'is-danger', inline: false },
    { id: 'toggle_status',   label: 'خاموش / روشن کردن اکانت', ico: 'power',      cls: 'is-danger', inline: true  },
    { id: 'transfer',        label: 'انتقال به کاربر دیگر',    ico: 'transfer',   cls: '', inline: false },
    { id: 'change_location', label: 'تغییر موقعیت',            ico: 'pin',        cls: '', inline: false },
    { id: 'report_problem',  label: 'گزارش اختلال',            ico: 'alert',      cls: '', inline: true  },
    { id: 'note',            label: 'تغییر یادداشت',           ico: 'note',       cls: '', inline: true  },
    { id: 'update_info',     label: 'بروزرسانی اطلاعات',       ico: 'refresh',    cls: '', inline: true  },
    { id: 'subscription',    label: 'لینک اشتراک',             ico: 'book',       cls: '', inline: true  },
    { id: 'config',          label: 'دریافت کانفیگ',           ico: 'config',     cls: '', inline: true  },
];


function qrUrl(payload, size = 280) {
    const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
    const enc = encodeURIComponent(String(payload || ''));

    const v = Math.floor(Date.now() / (60 * 60 * 1000));
    return `${apiUrl}/qr.php?s=${size}&d=${enc}&v=${v}`;
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('fa-IR');
}

export async function service(view, encodedUsername) {
    const username = decodeURIComponent(encodedUsername || '');

    view.innerHTML = `
        <a href="#/services" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/service/${escapeHtml(username)}</span>
            </header>
            <div class="card-body" id="service-body">
                ${skeletonList(4)}
            </div>
        </article>
    `;


    let info = null;
    async function loadAndRender() {
        const $body = view.querySelector('#service-body');
        $body.innerHTML = skeletonList(4);
        try {
            const res = await call('service', { params: { username } });
            info = res?.obj;
        } catch (err) {
            $body.innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>دریافت اطلاعات با خطا روبرو شد</h3>
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
        renderBody($body, info, username, loadAndRender);
    }

    await loadAndRender();
}

function renderBody($body, info, username, reload) {


    const isStock = info.is_stock === true
        || info.total_traffic_gb === null
        || info.total_traffic_gb === undefined;

    const used = Number(info.used_traffic_gb) || 0;
    const total = Number(info.total_traffic_gb) || 0;
    const remaining = Number(info.remaining_traffic_gb) || 0;
    const percent = trafficPercent(used, total);
    let pctClass = '';
    if (percent >= 85) pctClass = 'is-danger';
    else if (percent >= 60) pctClass = 'is-warn';

    const onlineLabel = info.online_at || '—';
    const expiration = info.expiration_time || '—';
    const lastUpdate = info.last_subscription_update || '—';

    let statusBadge = 'is-active', statusText = info.status || 'active';
    const s = String(info.status || '').toLowerCase();
    if (s.includes('disabled') || s.includes('limit')) { statusBadge = 'is-danger'; statusText = 'غیرفعال'; }
    else if (s === 'on_hold' || s === 'send_on_hold')  { statusBadge = 'is-warn';   statusText = 'متوقف'; }
    else if (s === 'expired')                          { statusBadge = 'is-warn';   statusText = 'منقضی'; }
    else if (s === 'active')                           { statusText = 'فعال'; }

    const disabled = Array.isArray(info.disabled_actions) ? new Set(info.disabled_actions) : new Set();


    const visibleActions = ALL_ACTIONS.filter((a) => !disabled.has(a.id));

    $body.innerHTML = `
        <div class="row-spread">
            <div>
                <div class="muted mono" style="font-size:12px">${escapeHtml(info.username || username)}</div>
                <h2 style="margin:4px 0 0;font-size:20px;font-weight:700">${escapeHtml(info.product_name || '—')}</h2>
            </div>
            <span class="badge ${statusBadge}">
                ${statusBadge === 'is-active' ? icon('online', 'class="ico"') : icon('warning', 'class="ico"')}
                ${escapeHtml(statusText)}
            </span>
        </div>

        ${isStock ? `
        <div class="card-section">
            <p class="muted center" style="font-size:13px">
                ${icon('box', 'class="ico ico-leading"')}
                این سرویس از انبار کانفیگ ارسال شده است و دارای محدودیت حجم/زمان مشخص نیست.
            </p>
        </div>
        ` : `
        <div class="card-section">
            <div class="row-spread">
                <span class="kv-label">${icon('chart')} مصرف ترافیک</span>
                <span class="mono accent">${escapeHtml(fmtGb(used))} / ${escapeHtml(fmtGb(total))}</span>
            </div>
            <div class="progress"><span class="progress-fill ${pctClass}" style="width:${percent.toFixed(1)}%"></span></div>
            <div class="muted mono" style="font-size:12px">${icon('box', 'class="ico ico-sm"')} باقی‌مانده: ${escapeHtml(fmtGb(remaining))}</div>
        </div>
        `}

        <div class="card-section">
            <div class="kv">
                <span class="kv-label">${icon('calendar')} تاریخ انقضا</span>
                <span class="kv-value">${escapeHtml(expiration)}</span>
            </div>
            ${isStock ? '' : `
            <div class="kv">
                <span class="kv-label">${icon('online')} آخرین آنلاین</span>
                <span class="kv-value">${escapeHtml(onlineLabel)}</span>
            </div>
            <div class="kv">
                <span class="kv-label">${icon('refresh')} آخرین به‌روزرسانی</span>
                <span class="kv-value">${escapeHtml(lastUpdate)}</span>
            </div>
            `}
            ${info.note ? `
            <div class="kv">
                <span class="kv-label">${icon('note')} یادداشت</span>
                <span class="kv-value">${escapeHtml(info.note)}</span>
            </div>` : ''}
        </div>

        <div class="card-section" id="service-config-host"></div>

        ${visibleActions.length > 0 ? `
        <div class="card-section">
            <p class="section-title">${icon('settings')} مدیریت سرویس</p>
            <div class="action-grid" id="action-grid">
                ${visibleActions.map((a) => `
                    <button type="button" class="action-tile ${a.cls}" data-action="${a.id}">
                        ${icon(a.ico, 'class="ico ico-accent"')}
                        <span class="action-label">${escapeHtml(a.label)}</span>
                    </button>
                `).join('')}
            </div>
        </div>` : ''}

        <div id="action-panel-host"></div>
    `;

    const $cfgHost = $body.querySelector('#service-config-host');
    const outputs = Array.isArray(info.service_output) ? info.service_output : [];
    if (outputs.length === 0) {
        $cfgHost.innerHTML = `<div class="muted center mono" style="font-size:12px">کانفیگی برای نمایش وجود ندارد</div>`;
    } else {
        $cfgHost.innerHTML = outputs.map((o) => renderOutput(o)).join('');
    }

    $body.querySelectorAll('.copy-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const txt = btn.dataset.copy || '';
            const ok = await copyToClipboard(txt);
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    });

    const $grid = $body.querySelector('#action-grid');
    if ($grid) {
        $grid.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn || btn.disabled) return;
            const actionId = btn.dataset.action;
            hapticImpact('light');
            handleAction(actionId, info, username, $body, reload, btn);
        });
    }
}


function handleAction(actionId, info, username, $body, reload, btn) {
    const $panel = $body.querySelector('#action-panel-host');
    if (!$panel) return;


    $body.querySelectorAll('.action-tile').forEach((t) => t.classList.remove('is-active'));
    btn.classList.add('is-active');

    switch (actionId) {
        case 'config':
            showConfigSection($body, info, username);
            $panel.innerHTML = '';
            return;
        case 'subscription':
            scrollToSubscription($body, info);
            $panel.innerHTML = '';
            return;
        case 'update_info':
            $panel.innerHTML = '';
            toast('در حال بروزرسانی…', 'info', 1200);
            reload();
            return;
        case 'note':
            renderNotePanel($panel, info, username, reload);
            return;
        case 'toggle_status':
            renderToggleStatusPanel($panel, info, username, reload);
            return;
        case 'report_problem':
            renderReportProblemPanel($panel, info, username);
            return;
        case 'renew':
            renderRenewPanel($panel, info, username, reload);
            return;
        case 'extra_time':
            renderExtraPanel($panel, info, username, 'time', reload);
            return;
        case 'extra_volume':
            renderExtraPanel($panel, info, username, 'volume', reload);
            return;

        default:
            requestAdminAction($panel, actionId, username);
            return;
    }
}


function scrollToConfig($body) {
    const $cfg = $body.querySelector('#service-config-host');
    if ($cfg) {
        $cfg.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $cfg.classList.add('is-flash');
        setTimeout(() => $cfg.classList.remove('is-flash'), 1200);
    }
}

function scrollToSubscription($body, info) {


    scrollToConfig($body);
    if (!info.subscription_url) {
        toast('لینک اشتراک برای این سرویس موجود نیست', 'warn', 3000);
    }
}


async function showConfigSection($body, info, username) {
    const $cfg = $body.querySelector('#service-config-host');
    if (!$cfg) return;

    const outputs = Array.isArray(info.service_output) ? info.service_output : [];
    const hasInlineConfig = outputs.some((o) => {
        const t = String(o?.type || '').toLowerCase();
        return t === 'config' || t === 'password' || t === 'file';
    });


    if (hasInlineConfig) {
        scrollToConfig($body);
        return;
    }


    $cfg.innerHTML = `
        <p class="section-title">${icon('config')} کانفیگ‌ها</p>
        <div class="muted center mono" style="font-size:12px;padding:10px 0">در حال دریافت کانفیگ‌ها…</div>
    `;
    scrollToConfig($body);

    try {
        const res = await call('service_configs', { params: { username } });
        const obj = res?.obj || {};
        const configs = Array.isArray(obj.configs) ? obj.configs : [];

        if (configs.length === 0) {
            $cfg.innerHTML = `
                <p class="section-title">${icon('config')} کانفیگ‌ها</p>
                <div class="muted center" style="padding:10px 0">کانفیگی برای نمایش وجود ندارد</div>
            `;
            return;
        }

        const firstConfig = configs[0] || '';
        $cfg.innerHTML = `
            <p class="section-title">${icon('config')} کانفیگ‌ها (${configs.length})</p>
            ${firstConfig ? `<div class="qr-block">
                <img class="qr-img" src="${escapeHtml(qrUrl(firstConfig))}" alt="QR" loading="lazy" />
                <p class="muted center mono" style="font-size:11px;margin-top:6px">QR کانفیگ اول</p>
            </div>` : ''}
            ${configs.map((c) => `
                <div class="codeblock" style="margin-bottom:8px">${escapeHtml(c)}<button class="copy-btn" data-copy="${escapeHtml(c)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
            `).join('')}
        `;


        $cfg.querySelectorAll('.copy-btn').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const txt = btn.dataset.copy || '';
                const ok = await copyToClipboard(txt);
                toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            });
        });

        scrollToConfig($body);
    } catch (err) {
        $cfg.innerHTML = `
            <p class="section-title">${icon('config')} کانفیگ‌ها</p>
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت کانفیگ‌ها</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
    }
}


function renderNotePanel($panel, info, username, reload) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('note')} یادداشت سرویس</p>
            <p class="muted" style="font-size:13px">می‌توانید یک یادداشت کوتاه برای این سرویس ذخیره کنید (حداکثر ۲۰۰ کاراکتر).</p>
            <div class="form-row mt-sm">
                <input id="note-input" type="text" maxlength="200"
                       placeholder="مثلاً: گوشی همسر"
                       value="${escapeHtml(info.note || '')}" />
            </div>
            <div class="row-spread mt-md" style="gap:8px">
                <button id="note-save" type="button" class="btn btn-primary" style="flex:1">
                    ${icon('check', 'class="ico ico-leading"')}
                    <span class="note-save-label">ذخیره یادداشت</span>
                </button>
                <button id="note-clear" type="button" class="btn btn-ghost">
                    ${icon('close', 'class="ico ico-leading"')}
                    <span>پاک کردن</span>
                </button>
            </div>
        </div>
    `;

    const $input = $panel.querySelector('#note-input');
    const $save = $panel.querySelector('#note-save');
    const $clear = $panel.querySelector('#note-clear');
    const $label = $save.querySelector('.note-save-label');

    async function submit(text) {
        const old = $label.textContent;
        $save.disabled = true;
        $clear.disabled = true;
        $label.textContent = 'در حال ذخیره…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'note', username, text },
            });
            const obj = res?.obj || {};
            toast(obj.message || 'انجام شد', 'success', 2500);
            hapticNotify('success');
            await reload();
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ذخیره یادداشت', 'error', 4000);
        } finally {
            $save.disabled = false;
            $clear.disabled = false;
            $label.textContent = old;
        }
    }

    $save.addEventListener('click', () => submit(($input.value || '').trim()));
    $clear.addEventListener('click', () => {
        $input.value = '';
        submit('');
    });
}


function renderToggleStatusPanel($panel, info, username, reload) {
    const isActive = String(info.status || '').toLowerCase() === 'active';
    const willBecome = isActive ? 'غیرفعال' : 'فعال';
    const cta = isActive ? 'تایید و خاموش کردن' : 'تایید و روشن کردن';
    const ico = isActive ? 'power' : 'online';

    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(ico)} تغییر وضعیت سرویس</p>
            <p>وضعیت فعلی سرویس: <span class="accent mono">${escapeHtml(isActive ? 'فعال' : 'غیرفعال')}</span></p>
            <p class="muted" style="font-size:13px">با تایید این عملیات، سرویس به وضعیت <b>${willBecome}</b> تغییر می‌کند.</p>
            <button id="toggle-confirm" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="toggle-label">${escapeHtml(cta)}</span>
            </button>
        </div>
    `;

    const $btn = $panel.querySelector('#toggle-confirm');
    const $label = $btn.querySelector('.toggle-label');
    $btn.addEventListener('click', async () => {
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'toggle_status', username },
            });
            const obj = res?.obj || {};
            toast(obj.message || 'انجام شد', 'success', 2500);
            hapticNotify('success');
            await reload();
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در تغییر وضعیت', 'error', 4000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


function renderReportProblemPanel($panel, info, username) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('alert')} ارسال گزارش اختلال</p>
            <p class="muted" style="font-size:13px">یک پیام کوتاه (اختیاری) برای ادمین بنویسید. ادمین پس از بررسی با شما در تماس خواهد بود.</p>
            <div class="form-row mt-sm">
                <textarea id="report-text" rows="3" maxlength="500"
                          placeholder="مثلاً: سرعت سرویس از ساعت ۲۰ پایین آمده."></textarea>
            </div>
            <button id="report-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('send', 'class="ico ico-leading"')}
                <span class="report-label">ارسال گزارش</span>
            </button>
        </div>
    `;

    const $btn = $panel.querySelector('#report-submit');
    const $txt = $panel.querySelector('#report-text');
    const $label = $btn.querySelector('.report-label');
    $btn.addEventListener('click', async () => {
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال ارسال…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'report_problem', username, text: ($txt.value || '').trim() },
            });
            const obj = res?.obj || {};
            $panel.innerHTML = `
                <div class="card-section">
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>گزارش ارسال شد</h3>
                        <p class="muted">${escapeHtml(obj.message || 'ادمین به‌زودی بررسی می‌کند.')}</p>
                    </div>
                </div>`;
            hapticNotify('success');
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ارسال گزارش', 'error', 4000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderRenewPanel($panel, info, username, reload) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('rotate')} تمدید سرویس</p>
            <div id="renew-list">${skeletonList(3)}</div>
            <div id="renew-confirm-host"></div>
            <div id="renew-pay-host"></div>
        </div>
    `;

    let opts = null;
    try {
        const res = await call('service_renew_options', { params: { username } });
        opts = res?.obj || {};
    } catch (err) {
        $panel.querySelector('#renew-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>دریافت پلن‌ها با خطا روبرو شد</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    const products = Array.isArray(opts.products) ? opts.products : [];
    const current = opts.current_plan || null;
    const showPrice = !!opts.show_price;
    const custom = opts.custom || {};
    const customForce = !!custom.force;
    const $list = $panel.querySelector('#renew-list');

    if (products.length === 0 && !customForce && !custom.enabled) {
        $list.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>پلنی برای تمدید یافت نشد</h3>
                <p class="muted">برای این پنل، محصولی فعال نیست.</p>
            </div>`;
        return;
    }

    const planRow = (p, cls = '') => {
        const meta = [];
        if (Number(p.volume_gb) > 0) meta.push(`<span>${icon('box','class="ico ico-sm"')} ${escapeHtml(String(p.volume_gb))} گیگابایت</span>`);
        if (Number(p.time_days) > 0) meta.push(`<span>${icon('hourglass','class="ico ico-sm"')} ${escapeHtml(String(p.time_days))} روز</span>`);
        const priceTag = showPrice
            ? `<span class="amt mono">${fmtNum(p.price)} <span class="muted" style="font-weight:400">تومان</span></span>`
            : `<span class="amt mono">انتخاب →</span>`;
        return `
            <button class="plan ${cls}" data-code="${escapeHtml(p.code)}"
                    data-price="${escapeHtml(String(p.price))}"
                    data-name="${escapeHtml(p.name)}"
                    data-volume="${escapeHtml(String(p.volume_gb))}"
                    data-time="${escapeHtml(String(p.time_days))}">
                <div class="plan-info">
                    <div class="plan-title">${escapeHtml(p.name)}</div>
                    <div class="plan-meta">${meta.join('')}</div>
                </div>
                <div class="plan-price">${priceTag}</div>
            </button>
        `;
    };

    let listHtml = '';
    if (current) listHtml += planRow(current, 'plan-current');
    listHtml += products.filter((p) => !current || p.code !== current.code).map((p) => planRow(p)).join('');

    if (custom.enabled) {
        listHtml += `
            <button class="plan plan-custom" data-code="__custom__">
                <div class="plan-info">
                    <div class="plan-title">${icon('settings')} ساخت پلن سفارشی</div>
                    <div class="plan-meta">
                        <span>${fmtNum(custom.price_per_gb)} تومان/گیگ</span>
                        <span>${fmtNum(custom.price_per_day)} تومان/روز</span>
                    </div>
                </div>
                <div class="plan-price"><span class="amt">→</span></div>
            </button>`;
    }

    $list.innerHTML = listHtml;

    $list.addEventListener('click', (e) => {
        const btn = e.target.closest('.plan');
        if (!btn) return;
        hapticImpact('light');
        if (btn.dataset.code === '__custom__') {
            renderRenewCustomForm($panel, opts, username, reload);
        } else {
            const choice = {
                code: btn.dataset.code,
                name: btn.dataset.name,
                price: Number(btn.dataset.price || 0),
                volume_gb: Number(btn.dataset.volume || 0),
                time_days: Number(btn.dataset.time || 0),
            };
            renderRenewConfirm($panel, opts, choice, username, reload);
        }
    });
}

function renderRenewCustomForm($panel, opts, username, reload) {
    const c = opts.custom || {};
    const $host = $panel.querySelector('#renew-confirm-host');
    $panel.querySelector('#renew-pay-host').innerHTML = '';
    $host.innerHTML = `
        <div class="card-section mt-md">
            <p class="section-title">${icon('settings')} پلن سفارشی</p>
            <p class="muted" style="font-size:13px">
                حجم: بین ${fmtNum(c.min_volume_gb)} و ${fmtNum(c.max_volume_gb)} گیگابایت ·
                زمان: بین ${fmtNum(c.min_time_days)} و ${fmtNum(c.max_time_days)} روز
            </p>
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">حجم (گیگابایت)</label>
                <input id="cv-volume" type="number" inputmode="numeric"
                       min="${c.min_volume_gb}" max="${c.max_volume_gb}"
                       placeholder="${c.min_volume_gb}-${c.max_volume_gb}" />
            </div>
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">زمان (روز)</label>
                <input id="cv-time" type="number" inputmode="numeric"
                       min="${c.min_time_days}" max="${c.max_time_days}"
                       placeholder="${c.min_time_days}-${c.max_time_days}" />
            </div>
            <div class="kv mt-md">
                <span class="kv-label">${icon('coin')} مبلغ تخمینی</span>
                <span class="kv-value mono accent" id="cv-total">۰ تومان</span>
            </div>
            <button id="cv-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="cv-label">ادامه</span>
            </button>
        </div>
    `;

    const $vol = $host.querySelector('#cv-volume');
    const $time = $host.querySelector('#cv-time');
    const $total = $host.querySelector('#cv-total');
    const recompute = () => {
        const v = Number($vol.value || 0);
        const d = Number($time.value || 0);
        const t = (v * Number(c.price_per_gb || 0)) + (d * Number(c.price_per_day || 0));
        $total.textContent = `${fmtNum(t)} تومان`;
    };
    $vol.addEventListener('input', recompute);
    $time.addEventListener('input', recompute);

    const $submit = $host.querySelector('#cv-submit');
    const $label = $submit.querySelector('.cv-label');
    $submit.addEventListener('click', () => {
        const v = parseInt($vol.value || '0', 10);
        const d = parseInt($time.value || '0', 10);
        if (!v || !d) { toast('حجم و زمان را وارد کنید', 'warn'); return; }
        if (v < c.min_volume_gb || v > c.max_volume_gb) {
            toast(`حجم باید بین ${c.min_volume_gb} و ${c.max_volume_gb} گیگ باشد`, 'warn', 4000);
            return;
        }
        if (d < c.min_time_days || d > c.max_time_days) {
            toast(`زمان باید بین ${c.min_time_days} و ${c.max_time_days} روز باشد`, 'warn', 4000);
            return;
        }
        const price = (v * Number(c.price_per_gb || 0)) + (d * Number(c.price_per_day || 0));
        const choice = {
            code: '__custom__',
            name: '⚙️ سرویس دلخواه',
            price,
            volume_gb: v,
            time_days: d,
            custom: { volume_gb: v, time_days: d },
        };
        renderRenewConfirm($panel, opts, choice, username, reload);
    });
}

function renderRenewConfirm($panel, opts, choice, username, reload) {
    const balance = Number(opts.balance || 0);
    const $host = $panel.querySelector('#renew-confirm-host');
    $panel.querySelector('#renew-pay-host').innerHTML = '';

    const meta = [];
    if (choice.volume_gb > 0) meta.push(`${icon('box','class="ico ico-sm"')} حجم: ${fmtNum(choice.volume_gb)} گیگابایت`);
    if (choice.time_days > 0) meta.push(`${icon('hourglass','class="ico ico-sm"')} زمان: ${fmtNum(choice.time_days)} روز`);

    $host.innerHTML = `
        <div class="card-section mt-md">
            <p class="section-title">${icon('rotate')} تأیید تمدید</p>
            <div class="kv">
                <span class="kv-label">پلن انتخابی</span>
                <span class="kv-value">${escapeHtml(choice.name)}</span>
            </div>
            ${meta.map((m) => `
            <div class="kv">
                <span class="kv-label muted" style="font-size:12px">${m.split(' ')[0]}</span>
                <span class="kv-value mono">${escapeHtml(m.replace(/<[^>]+>/g,'').trim())}</span>
            </div>`).join('')}
            <div class="kv">
                <span class="kv-label">${icon('coin')} مبلغ</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(choice.price)} تومان</span>
            </div>
            <div class="kv">
                <span class="kv-label">${icon('wallet')} موجودی</span>
                <span class="kv-value mono">${fmtNum(balance)} تومان</span>
            </div>

            <button id="rc-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="rc-label">پرداخت و تمدید</span>
            </button>
        </div>
    `;

    const $btn = $host.querySelector('#rc-submit');
    const $label = $btn.querySelector('.rc-label');

    $btn.addEventListener('click', async () => {
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const body = { username };
            if (choice.code === '__custom__') {
                body.custom = choice.custom;
            } else {
                body.product_code = choice.code;
            }
            const res = await call('service_renew_confirm', { method: 'POST', body });
            const obj = res?.obj || {};
            if (obj.kind === 'done') {
                hapticNotify('success');
                $host.innerHTML = `
                    <div class="card-section mt-md">
                        <div class="empty">
                            ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                            <h3>سرویس تمدید شد</h3>
                            <p class="muted">${escapeHtml(obj.message || '')}</p>
                            ${obj.cashback ? `<p class="muted mt-sm">🎉 ${fmtNum(obj.cashback)} تومان کش‌بک به حساب شما اضافه شد.</p>` : ''}
                            <p class="muted mono mt-sm" style="font-size:12px">موجودی جدید: ${fmtNum(obj.balance_after)} تومان</p>
                        </div>
                    </div>`;
                setTimeout(() => reload(), 800);
                return;
            }
            if (obj.kind === 'requires_payment') {
                renderRenewInlinePayment($panel, obj, reload);
                return;
            }
            toast(obj.message || 'انجام شد', 'success');
        } catch (err) {
            hapticNotify('error');
            const msg = err.message || 'خطا در تمدید سرویس';
            toast(msg, 'error', 5000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderRenewInlinePayment($panel, pay, reload) {
    const $host = $panel.querySelector('#renew-pay-host');
    if (!$host) return;
    const amountDue = Number(pay.amount_due || 0);
    const username = String(pay.username || '');

    $host.innerHTML = `
        <div class="card-section mt-md">
            <div class="cart-banner">
                ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                <h3>پرداخت مبلغ کسری</h3>
                <p class="muted center" style="font-size:12px">
                    موجودی شما برای تمدید کافی نیست. مبلغ کسری را با یکی از روش‌های زیر پرداخت کنید؛ پس از تأیید ادمین، تمدید به‌صورت خودکار انجام خواهد شد.
                </p>
            </div>

            <div class="kv mt-md">
                <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
            </div>

            <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
            <div class="payment-methods" id="renew-pay-list">${skeletonList(3)}</div>

            <div id="pay-form-host"></div>
            <div id="pay-result-host"></div>
        </div>
    `;

    let methods = [];
    try {
        const r = await call('payment_methods');
        const data = r?.obj || {};
        methods = Array.isArray(data.methods) ? data.methods : [];
    } catch (err) {
        $host.querySelector('#renew-pay-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    if (!methods.length) {
        $host.querySelector('#renew-pay-list').innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
            </div>`;
        return;
    }

    const $list = $host.querySelector('#renew-pay-list');
    $list.innerHTML = methods.map(renderMethodCard).join('');
    $list.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-method]');
        if (!card) return;
        $list.querySelectorAll('.pay-card').forEach((c) => c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        hapticImpact('light');

        const method = methods.find((m) => m.id === card.dataset.method);
        if (!method) return;

        if (method.kind === 'url' && method.url) {
            const tg = window.Telegram && window.Telegram.WebApp;
            const u = String(method.url || '');
            const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
            if (tg && isTme && typeof tg.openTelegramLink === 'function') {
                tg.openTelegramLink(u);
            } else if (tg && typeof tg.openLink === 'function') {
                tg.openLink(u);
            } else {
                window.open(u, '_blank', 'noopener');
            }
            return;
        }

        try {
            const r = await call('payment_init', {
                method: 'POST',
                body: {
                    method: method.id,
                    amount: amountDue,
                    purchase_username: username,
                },
            });
            const obj = r?.obj || {};
            handleInitResult($host, method.id, obj, {
                successText: 'پس از تأیید ادمین، سرویس شما به‌صورت خودکار تمدید خواهد شد.',
                successCta:  { href: '#/services', label: 'بازگشت به سرویس‌ها' },
            });
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        }
    });
}


async function renderExtraPanel($panel, info, username, kind, reload) {
    const titleIcon = kind === 'time' ? 'hourglass' : 'box';
    const titleText = kind === 'time' ? 'خرید زمان اضافه' : 'خرید حجم اضافه';
    const unitLabel = kind === 'time' ? 'روز' : 'گیگابایت';

    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(titleIcon)} ${escapeHtml(titleText)}</p>
            <div id="extra-host">${skeletonList(2)}</div>
        </div>
    `;
    const $host = $panel.querySelector('#extra-host');

    let q = null;
    try {
        const res = await call('service_extra_quote', { params: { username, kind } });
        q = res?.obj || {};
    } catch (err) {
        $host.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    const minA = Number(q.min || 1);
    const maxA = Number(q.max || 9999);
    const ppu = Number(q.price_per_unit || 0);

    $host.innerHTML = `
        <p class="muted" style="font-size:13px">تعرفه هر ${unitLabel}: <span class="accent mono">${fmtNum(ppu)} تومان</span></p>
        <div class="form-row mt-sm">
            <label class="muted" style="font-size:12px">مقدار (${unitLabel})</label>
            <input id="extra-amount" type="number" inputmode="numeric" min="${minA}" max="${maxA}"
                   placeholder="بین ${minA} و ${maxA}" />
        </div>
        <div class="kv mt-sm">
            <span class="kv-label">${icon('coin')} مبلغ کل</span>
            <span class="kv-value mono accent" id="extra-total">۰ تومان</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('wallet')} موجودی</span>
            <span class="kv-value mono">${fmtNum(q.balance || 0)} تومان</span>
        </div>
        <button id="extra-submit" type="button" class="btn btn-primary btn-block mt-md">
            ${icon('check', 'class="ico ico-leading"')}
            <span class="extra-label">پرداخت و افزودن</span>
        </button>
        <div id="extra-pay-host"></div>
    `;

    const $amount = $host.querySelector('#extra-amount');
    const $total = $host.querySelector('#extra-total');
    const recompute = () => {
        const a = Number($amount.value || 0);
        $total.textContent = `${fmtNum(a * ppu)} تومان`;
    };
    $amount.addEventListener('input', recompute);

    const $btn = $host.querySelector('#extra-submit');
    const $label = $btn.querySelector('.extra-label');
    $btn.addEventListener('click', async () => {
        const a = parseInt($amount.value || '0', 10);
        if (!a || a < minA || a > maxA) {
            toast(`مقدار باید بین ${minA} و ${maxA} ${unitLabel} باشد`, 'warn', 4000);
            return;
        }
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const res = await call('service_extra_confirm', {
                method: 'POST',
                body: { username, kind, amount: a },
            });
            const obj = res?.obj || {};
            if (obj.kind === 'done') {
                hapticNotify('success');
                $host.innerHTML = `
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>${escapeHtml(obj.message || 'انجام شد')}</h3>
                        <p class="muted mono mt-sm" style="font-size:12px">موجودی جدید: ${fmtNum(obj.balance_after || 0)} تومان</p>
                    </div>`;
                setTimeout(() => reload(), 800);
                return;
            }
            if (obj.kind === 'requires_payment') {
                renderExtraInlinePayment($host.querySelector('#extra-pay-host'), obj, reload);
                return;
            }
            toast(obj.message || 'انجام شد', 'success');
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در افزودن', 'error', 5000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderExtraInlinePayment($host, pay, reload) {
    const amountDue = Number(pay.amount_due || 0);
    const username = String(pay.username || '');

    $host.innerHTML = `
        <div class="card-section mt-md">
            <div class="cart-banner">
                ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                <h3>پرداخت مبلغ کسری</h3>
                <p class="muted center" style="font-size:12px">
                    موجودی کیف پول شما کافی نیست. مبلغ کسری را پرداخت کنید؛ پس از تأیید ادمین، خرید به‌صورت خودکار انجام خواهد شد.
                </p>
            </div>
            <div class="kv mt-md">
                <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
            </div>
            <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
            <div class="payment-methods" id="extra-pay-list">${skeletonList(3)}</div>
            <div id="pay-form-host"></div>
            <div id="pay-result-host"></div>
        </div>
    `;

    let methods = [];
    try {
        const r = await call('payment_methods');
        const data = r?.obj || {};
        methods = Array.isArray(data.methods) ? data.methods : [];
    } catch (err) {
        $host.querySelector('#extra-pay-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    if (!methods.length) {
        $host.querySelector('#extra-pay-list').innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
            </div>`;
        return;
    }

    const $list = $host.querySelector('#extra-pay-list');
    $list.innerHTML = methods.map(renderMethodCard).join('');
    $list.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-method]');
        if (!card) return;
        $list.querySelectorAll('.pay-card').forEach((c) => c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        hapticImpact('light');
        const method = methods.find((m) => m.id === card.dataset.method);
        if (!method) return;

        if (method.kind === 'url' && method.url) {
            const tg = window.Telegram && window.Telegram.WebApp;
            const u = String(method.url || '');
            const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
            if (tg && isTme && typeof tg.openTelegramLink === 'function') {
                tg.openTelegramLink(u);
            } else if (tg && typeof tg.openLink === 'function') {
                tg.openLink(u);
            } else {
                window.open(u, '_blank', 'noopener');
            }
            return;
        }

        try {
            const r = await call('payment_init', {
                method: 'POST',
                body: {
                    method: method.id,
                    amount: amountDue,
                    purchase_username: username,
                },
            });
            const obj = r?.obj || {};
            handleInitResult($host, method.id, obj, {
                successText: 'پس از تأیید ادمین، خرید شما به‌صورت خودکار اعمال خواهد شد.',
                successCta:  { href: '#/services', label: 'بازگشت به سرویس‌ها' },
            });
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        }
    });
}


function requestAdminAction($panel, actionId, username) {
    const meta = ACTION_REQUEST_META[actionId];
    if (!meta) {
        toast('عملیات نامعتبر است', 'error');
        return;
    }
    if (meta.fields && meta.fields.length > 0) {
        renderAdminRequestForm($panel, actionId, username, meta);
    } else {
        submitAdminRequest($panel, actionId, username, {});
    }
}

const ACTION_REQUEST_META = {
    transfer: {
        title:   '↪️ انتقال سرویس به کاربر دیگر',
        intro:   'شناسهٔ عددی کاربری که می‌خواهید سرویس به او منتقل شود را وارد کنید. ادمین درخواست را بررسی خواهد کرد.',
        ico:     'transfer',
        fields: [
            { id: 'target_user_id', label: 'شناسهٔ عددی کاربر مقصد', type: 'tel', placeholder: 'مثلاً 123456789', required: true,
              help: 'این شناسه را می‌توانید از پروفایل کاربر در تلگرام پیدا کنید.' },
        ],
    },
    change_location: {
        title:   '📍 درخواست تغییر موقعیت',
        intro:   'موقعیت/پنل پیشنهادی خود را وارد کنید (مثلاً «آلمان ۱» یا «هلند»). ادمین پس از بررسی، موقعیت سرویس شما را تغییر می‌دهد.',
        ico:     'pin',
        fields: [
            { id: 'target_panel', label: 'موقعیت پیشنهادی', type: 'text', placeholder: 'مثلاً «آلمان ۲»', required: true, maxlength: 200 },
        ],
    },
    refund: {
        title:   'درخواست بازگشت وجه',
        intro:   'با ارسال این درخواست، ادمین وضعیت سرویس و امکان بازگشت وجه را بررسی می‌کند. در صورت نیاز، توضیحات کوتاهی اضافه کنید.',
        ico:     'diamond',
        fields: [
            { id: 'reason', label: 'توضیحات (اختیاری)', type: 'textarea', placeholder: 'مثلاً: سرویس در دو روز اخیر کار نمی‌کند', required: false, maxlength: 500 },
        ],
    },
    changelink: {
        title:   'تغییر لینک',
        intro:   'با تایید، لینک کانفیگ شما فوراً تغییر می‌کند. توجه: لینک قبلی پس از تغییر دیگر معتبر نخواهد بود.',
        ico:     'link',
        fields: [],
        submitLabel: 'تغییر لینک',
        immediate: true,
    },
};

function renderAdminRequestForm($panel, actionId, username, meta) {
    const fieldsHtml = meta.fields.map((f) => {
        if (f.type === 'textarea') {
            return `
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px">${escapeHtml(f.label)}</label>
                    <textarea data-field="${escapeHtml(f.id)}" rows="3" maxlength="${f.maxlength || 500}"
                              placeholder="${escapeHtml(f.placeholder || '')}"></textarea>
                    ${f.help ? `<p class="muted" style="font-size:11px">${escapeHtml(f.help)}</p>` : ''}
                </div>`;
        }
        return `
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">${escapeHtml(f.label)}</label>
                <input data-field="${escapeHtml(f.id)}" type="${escapeHtml(f.type || 'text')}"
                       inputmode="${f.type === 'tel' ? 'numeric' : 'text'}"
                       maxlength="${f.maxlength || 100}"
                       placeholder="${escapeHtml(f.placeholder || '')}" />
                ${f.help ? `<p class="muted" style="font-size:11px">${escapeHtml(f.help)}</p>` : ''}
            </div>`;
    }).join('');

    const submitLabel = meta.submitLabel || 'ارسال درخواست برای ادمین';
    const submitIcon = meta.immediate ? 'refresh' : 'send';
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(meta.ico)} ${escapeHtml(meta.title)}</p>
            <p class="muted" style="font-size:13px">${escapeHtml(meta.intro)}</p>
            ${fieldsHtml}
            <button id="ar-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon(submitIcon, 'class="ico ico-leading"')}
                <span class="ar-label">${escapeHtml(submitLabel)}</span>
            </button>
        </div>
    `;

    const $submit = $panel.querySelector('#ar-submit');
    const $label = $submit.querySelector('.ar-label');
    $submit.addEventListener('click', async () => {
        const payload = {};
        let firstError = null;
        for (const f of meta.fields) {
            const $el = $panel.querySelector(`[data-field="${f.id}"]`);
            const val = ($el?.value || '').trim();
            if (f.required && val === '') {
                firstError = firstError || `لطفاً «${f.label}» را وارد کنید`;
                continue;
            }
            if (f.type === 'tel' && val !== '' && !/^\d+$/.test(val)) {
                firstError = firstError || `«${f.label}» باید عدد باشد`;
                continue;
            }
            if (val !== '') payload[f.id] = val;
        }
        if (firstError) {
            toast(firstError, 'warn', 3500);
            return;
        }
        if (meta.immediate) {
            const ok = await showConfirm(meta.intro);
            if (!ok) return;
        }
        const old = $label.textContent;
        $submit.disabled = true;
        $label.textContent = meta.immediate ? 'در حال انجام…' : 'در حال ارسال…';
        submitAdminRequest($panel, actionId, username, payload).catch(() => {
            $submit.disabled = false;
            $label.textContent = old;
        });
    });
}

async function submitAdminRequest($panel, actionId, username, payload) {
    if (!$panel.querySelector('#ar-submit')) {
        $panel.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('send', 'class="ico ico-xxl ico-muted"')}
                    <p class="muted">در حال پردازش…</p>
                </div>
            </div>
        `;
    }
    try {
        const res = await call('service_action', {
            method: 'POST',
            body: { action: actionId, username, payload },
        });
        const obj = res?.obj || {};
        hapticNotify('success');


        if (obj.kind === 'changelink_done') {
            const newLink = String(obj.subscription_url || '');
            $panel.innerHTML = `
                <div class="card-section">
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>لینک سرویس با موفقیت تغییر کرد</h3>
                        <p class="muted">${escapeHtml(obj.message || 'لینک قبلی دیگر معتبر نیست.')}</p>
                    </div>
                    ${newLink ? `
                        <div class="sub-link-box mt-md">
                            <div class="label">${icon('link', 'class="ico ico-leading"')} <span>لینک جدید اشتراک</span></div>
                            <div class="sub-link-value mono" id="new-sub-link" title="برای کپی کلیک کنید">${escapeHtml(newLink)}</div>
                        </div>
                        <button id="copy-new-link" type="button" class="btn btn-primary btn-block mt-sm">
                            ${icon('copy', 'class="ico ico-leading"')}
                            <span>کپی لینک جدید</span>
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-ghost btn-block mt-sm" onclick="location.reload()">
                        ${icon('refresh', 'class="ico ico-leading"')}
                        <span>بازنشانی سرویس</span>
                    </button>
                </div>`;
            const $copyBtn = $panel.querySelector('#copy-new-link');
            const $linkVal = $panel.querySelector('#new-sub-link');
            const doCopy = async () => {
                const ok = await copyToClipboard(newLink);
                hapticImpact('light');
                toast(ok ? 'لینک کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            };
            if ($copyBtn) $copyBtn.addEventListener('click', doCopy);
            if ($linkVal) $linkVal.addEventListener('click', doCopy);
            return;
        }


        $panel.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                    <h3>درخواست شما برای ادمین ارسال شد</h3>
                    <p class="muted">${escapeHtml(obj.message || 'ادمین پس از بررسی با شما تماس می‌گیرد.')}</p>
                </div>
            </div>`;
    } catch (err) {
        hapticNotify('error');
        toast(err.message || 'خطا در ارسال درخواست', 'error', 4500);
        throw err;
    }
}


function renderOutput(o) {
    const type = (o?.type || '').toLowerCase();

    if (type === 'link') {
        const url = String(o.value || '');
        return `
            <p class="section-title">${icon('book')} لینک اشتراک</p>
            <div class="qr-block">
                <img class="qr-img" src="${escapeHtml(qrUrl(url))}" alt="QR" loading="lazy" />
            </div>
            <div class="codeblock">${escapeHtml(url)}<button class="copy-btn" data-copy="${escapeHtml(url)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
        `;
    }

    if (type === 'config') {
        const items = Array.isArray(o.value) ? o.value : String(o.value || '').split('\n').filter(Boolean);


        const firstConfig = items[0] || '';
        return `
            <p class="section-title">${icon('config')} کانفیگ‌ها</p>
            ${firstConfig ? `<div class="qr-block">
                <img class="qr-img" src="${escapeHtml(qrUrl(firstConfig))}" alt="QR" loading="lazy" />
                <p class="muted center mono" style="font-size:11px;margin-top:6px">QR کانفیگ اول</p>
            </div>` : ''}
            ${items.map((c) => `
                <div class="codeblock" style="margin-bottom:8px">${escapeHtml(c)}<button class="copy-btn" data-copy="${escapeHtml(c)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
            `).join('')}
        `;
    }

    if (type === 'file') {
        return `
            <p class="section-title">${icon('fileText')} فایل کانفیگ</p>
            <a class="btn btn-ghost btn-block" href="${escapeHtml(o.value || '#')}" download="${escapeHtml(o.filename || 'config.conf')}" target="_blank" rel="noopener">${icon('download', 'class="ico ico-leading"')} دانلود ${escapeHtml(o.filename || 'config.conf')}</a>
        `;
    }

    if (type === 'password') {
        const pw = String(o.value || '');
        return `
            <p class="section-title">${icon('config')} رمز عبور</p>
            ${pw ? `<div class="qr-block">
                <img class="qr-img" src="${escapeHtml(qrUrl(pw))}" alt="QR" loading="lazy" />
            </div>` : ''}
            <div class="codeblock">${escapeHtml(pw)}<button class="copy-btn" data-copy="${escapeHtml(pw)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
        `;
    }

    return '';
}

