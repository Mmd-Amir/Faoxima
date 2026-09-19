import { call } from '../api.js?v=0.0.52';
import { escapeHtml, skeletonList, emptyState, copyToClipboard, toast } from '../utils.js?v=0.0.52';
import { hapticImpact, hapticNotify } from '../telegram.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { renderServiceOutputs } from './service.js?v=0.0.52';
import { downloadBlob, base64ToBlob } from '../download.js?v=0.0.52';

export async function usertest(view) {
    view.innerHTML = `
        <article class="card card-window card-bare">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/usertest</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('gift')} دریافت اکانت تست</p>
                <div id="ut-host" class="mt-md">${skeletonList(3)}</div>
            </div>
        </article>
    `;

    const $host = view.querySelector('#ut-host');

    let info = null;
    try {
        const res = await call('test_account_info');
        info = res?.obj || {};
    } catch (err) {
        $host.innerHTML = errorBlock(err);
        return;
    }

    if (!info.available) {
        $host.innerHTML = emptyState('در دسترس نیست', 'سرویس تست در حال حاضر غیرفعال است یا سهمیه شما به پایان رسیده.');
        return;
    }

    const panels = Array.isArray(info.panels) ? info.panels : [];

    if (panels.length === 0) {
        renderCreateForm($host, null, info);
        return;
    }

    if (panels.length === 1) {
        renderCreateForm($host, panels[0], info);
        return;
    }

    renderPanelList($host, panels, info);
}

function renderPanelList($host, panels, info) {
    $host.innerHTML = `
        <p class="muted" style="font-size:13px">موقعیت سرویس تست را انتخاب کنید (سهمیه باقیمانده: <b>${escapeHtml(info.limit_left)}</b>)</p>
        <div class="list mt-sm">
            ${panels.map((p) => `
                <button class="plan" data-id="${escapeHtml(p.id)}" data-name="${escapeHtml(p.name)}">
                    <div class="plan-info"><div class="plan-title">${escapeHtml(p.name)}</div></div>
                    <div class="plan-price"><span class="amt">${icon('select', 'class="ico ico-lg"')}</span></div>
                </button>
            `).join('')}
        </div>
    `;
    $host.querySelectorAll('.plan').forEach((btn) => {
        btn.addEventListener('click', () => {
            hapticImpact('light');
            renderCreateForm($host, { id: btn.dataset.id, name: btn.dataset.name }, info);
        });
    });
}

function renderCreateForm($host, panel, info) {
    $host.innerHTML = `
        <p class="muted" style="font-size:13px">
            ${panel ? `موقعیت: <b>${escapeHtml(panel.name)}</b> — ` : ''}سهمیه باقیمانده: <b>${escapeHtml(info.limit_left)}</b>
        </p>
        <div class="form-row mt-sm" id="ut-username-row" hidden>
            <label class="muted" style="font-size:12px">نام کاربری دلخواه</label>
            <input id="ut-username" type="text" maxlength="32" placeholder="مثلاً myuser" />
        </div>
        <button id="ut-submit" type="button" class="btn btn-primary btn-block mt-md">
            ${icon('gift', 'class="ico ico-leading"')}
            <span class="ut-label">دریافت اکانت تست</span>
        </button>
    `;

    const $submit = $host.querySelector('#ut-submit');
    const $label = $submit.querySelector('.ut-label');
    const $usernameRow = $host.querySelector('#ut-username-row');
    const $username = $host.querySelector('#ut-username');

    $usernameRow.hidden = false;

    $submit.addEventListener('click', async () => {
        hapticImpact('light');
        $submit.disabled = true;
        const old = $label.textContent;
        $label.textContent = 'در حال ساخت…';

        try {
            const body = {};
            if (panel && panel.id) body.country_id = panel.id;
            const customUsername = ($username.value || '').trim();
            if (customUsername) body.custom_username = customUsername;

            const res = await call('test_account_create', { method: 'POST', body });
            const obj = res?.obj || {};
            hapticNotify('success');
            renderSuccess($host, obj);
        } catch (err) {
            hapticNotify('error');
            $submit.disabled = false;
            $label.textContent = old;
            const $err = document.createElement('p');
            $err.className = 'muted';
            $err.style.cssText = 'color:var(--red);font-size:13px;margin-top:8px';
            $err.textContent = err.message || 'خطا در دریافت اکانت تست';
            $host.appendChild($err);
        }
    });
}

function renderSuccess($host, obj) {
    const svc = obj.service || {};
    const deliveredAsFile = svc.delivered_as_file === true;
    const configs = deliveredAsFile ? [] : (Array.isArray(svc.configs) ? svc.configs : []);
    const rawLink = String(svc.subscription_url || (configs.length ? configs[0] : '') || '');
    const isLinkLike = /^(https?|vless|vmess|trojan|ss|ssr|hy2|hysteria2?|tuic|wireguard):\/\//i.test(rawLink.trim());
    const linkValue = isLinkLike ? rawLink : '';
    const fileName = String(svc.file_name || '');
    const fileB64 = String(svc.file_content_b64 || '');
    const canDownload = deliveredAsFile && fileName !== '' && fileB64 !== '';

    const outputs = Array.isArray(svc.service_output) ? svc.service_output : [];
    const hasOutputs = outputs.length > 0;
    const isManual = String(svc.panel_type || '') === 'Manualsale' || svc.is_stock === true;
    const outputsHaveFile = outputs.some((o) => String(o?.type || '').toLowerCase() === 'file');
    const showNativeDownload = canDownload && !outputsHaveFile;

    $host.innerHTML = `
        <div class="empty">
            ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
            <h3>اکانت تست شما ساخته شد</h3>
            <p class="muted">جزئیات کانفیگ برای شما در تلگرام ارسال شد.</p>
        </div>
        <div class="kv mt-md">
            <span class="kv-label">نام کاربری</span>
            <span class="kv-value mono">${escapeHtml(svc.username || '—')}</span>
        </div>
        ${showNativeDownload ? `
            <button id="ut-download" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('download', 'class="ico ico-leading"')}
                <span>دانلود فایل کانفیگ</span>
            </button>
        ` : ''}
        ${hasOutputs ? `
            <div id="ut-output-host" class="mt-md"></div>
        ` : `
            ${linkValue ? `
                <div class="sub-link-box mt-md">
                    <div class="label">${icon('link', 'class="ico ico-leading"')} <span>لینک اشتراک</span></div>
                    <div class="sub-link-value mono" id="ut-link-value" title="برای کپی کلیک کنید">${escapeHtml(linkValue)}</div>
                </div>
                <button id="ut-copy-link" type="button" class="btn btn-primary btn-block mt-sm">
                    ${icon('copy', 'class="ico ico-leading"')}
                    <span>کپی لینک</span>
                </button>
            ` : ''}
            ${configs.length ? `<div class="codeblock mt-md">${escapeHtml(configs.join('\n'))}</div>` : ''}
        `}
        <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
            <a href="#/services/${encodeURIComponent(svc.username || '')}" class="btn ${showNativeDownload || linkValue || hasOutputs ? 'btn-ghost' : 'btn-primary'} btn-block" style="flex:1">مشاهده سرویس</a>
            <a href="#/" class="btn btn-ghost btn-block" style="flex:1">خانه</a>
        </div>
    `;

    const $outputHost = $host.querySelector('#ut-output-host');
    if ($outputHost && hasOutputs) {
        renderServiceOutputs($outputHost, outputs, { isManual, username: (svc && svc.username) || '' });
    }

    const doCopy = async () => {
        const ok = await copyToClipboard(linkValue);
        hapticImpact('light');
        toast(ok ? 'لینک کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
    };

    const doDownload = async (ev) => {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        const blob = base64ToBlob(fileB64);
        if (!blob) {
            toast('خطا در دانلود فایل', 'error');
            return;
        }
        let text = '';
        try { text = new TextDecoder('utf-8').decode(Uint8Array.from(atob(fileB64), (ch) => ch.charCodeAt(0))); } catch (_e) { text = ''; }
        await downloadBlob(blob, fileName, { fallbackText: text, label: 'usertest' });
    };

    const $downloadBtn = $host.querySelector('#ut-download');
    const $copyBtn = $host.querySelector('#ut-copy-link');
    const $linkVal = $host.querySelector('#ut-link-value');
    if ($downloadBtn) $downloadBtn.addEventListener('click', doDownload);
    if ($copyBtn) $copyBtn.addEventListener('click', doCopy);
    if ($linkVal) $linkVal.addEventListener('click', doCopy);
}

function errorBlock(err) {
    return `
        <div class="empty">
            ${icon('alert', 'class="ico ico-xxl ico-warn"')}
            <h3>خطا در دریافت اطلاعات</h3>
            <p class="muted">${escapeHtml(err.message || '')}</p>
        </div>
    `;
}
