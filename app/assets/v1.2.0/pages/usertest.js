import { call } from '../api.js?v=0.0.53';
import { escapeHtml, skeletonList, emptyState, copyToClipboard, toast } from '../utils.js?v=0.0.52';
import { hapticImpact, hapticNotify } from '../telegram.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { renderServiceOutputs } from './service.js?v=0.0.76';
import { downloadBlob, base64ToBlob } from '../download.js?v=0.0.52';

export async function usertest(view) {
    view.innerHTML = `<a href="#/" class="page-back">${icon('chevronRight')} بازگشت به خانه</a><article class="ut-page"><header class="ut-hero"><div><span class="ut-eyebrow">قبل از خرید، امتحان کن</span><h1>اکانت تست تو.</h1><p>موقعیت دلخواهت را انتخاب کن و سرویس تستت را بگیر.</p></div><span class="ut-hero-visual">${icon('gift')}<i>${icon('check')}</i></span></header><div id="ut-host">${skeletonList(3)}</div></article>`;

    const $host = view.querySelector('#ut-host');

    let info = null;
    try {
        const res = await call('test_account_info');
        info = res?.obj || {};
    } catch (err) {
        $host.innerHTML = errorBlock(err);
        $host.querySelector('[data-ut-retry]')?.addEventListener('click', () => usertest(view));
        return;
    }

    if (!info.available) {
        $host.innerHTML = `<div class="ut-unavailable">${emptyState(info.reason === 'limit_reached' ? 'سهمیهٔ تستت تمام شده' : 'اکانت تست فعلاً در دسترس نیست', info.reason_message ? String(info.reason_message) : unavailableText(info.reason))}<a href="#/buy" class="btn btn-primary btn-block">${icon('box')} مشاهدهٔ سرویس‌ها</a></div>`;
        return;
    }

    const panels = (Array.isArray(info.panels) ? info.panels : []).filter((p) => p && p.available !== false);

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

function unavailableText(reason) {
    if (reason === 'verify_phone') return 'برای دریافت اکانت تست ابتدا شماره موبایل خود را در ربات تأیید کنید.';
    if (reason === 'verify_verify') return 'حساب شما هنوز احراز هویت نشده است.';
    if (reason === 'audience_restricted') return 'دریافت اکانت تست فقط برای کاربرانی فعال است که در ۲۴ ساعت گذشته عضو شده‌اند.';
    if (reason === 'limit_reached') return 'سهمیه دریافت اکانت تست شما به پایان رسیده.';
    return 'سرویس تست در حال حاضر غیرفعال است یا سهمیه شما به پایان رسیده.';
}

function quotaLeft(panel, info) {
    if (panel && Object.prototype.hasOwnProperty.call(panel, 'limit_left')) {
        return panel.limit_left === null ? 'نامحدود' : panel.limit_left;
    }
    return info.limit_left === null ? 'نامحدود' : (info.limit_left ?? '—');
}

function renderPanelList($host, panels, info) {
    $host.innerHTML = `<div class="ut-list-heading"><h2>موقعیت‌های تست</h2><span>برای ادامه یک موقعیت انتخاب کن</span></div><div class="ut-location-list">${panels.map(p => `<button type="button" class="plan ut-location" data-id="${escapeHtml(p.id)}" data-name="${escapeHtml(p.name)}"><span class="ut-location-icon">${icon('globe')}</span><span class="ut-location-copy"><b>${escapeHtml(p.name)}</b><small>سهمیهٔ باقی‌مانده <strong>${escapeHtml(quotaLeft(p, info))}</strong></small></span><span class="ut-location-arrow">${icon('arrowLeft')}</span></button>`).join('')}</div>`;
    $host.querySelectorAll('.plan').forEach((btn) => {
        btn.addEventListener('click', () => {
            hapticImpact('light');
            const picked = panels.find((p) => String(p.id) === btn.dataset.id) || { id: btn.dataset.id, name: btn.dataset.name };
            renderCreateForm($host, picked, info);
        });
    });
}

function renderCreateForm($host, panel, info) {
    const panels = (Array.isArray(info.panels) ? info.panels : []).filter(p => p && p.available !== false);
    $host.innerHTML = `<section class="ut-create"><div class="ut-selected"><span>${icon('pin')}</span><div><small>موقعیت انتخابی</small><b>${escapeHtml(panel?.name || 'سرویس تست')}</b></div>${panels.length > 1 ? '<button type="button" class="ut-change" id="ut-change-location">تغییر</button>' : ''}</div><div class="ut-quota">${icon('gift')} سهمیهٔ باقی‌مانده <strong>${escapeHtml(quotaLeft(panel, info))}</strong></div><div class="form-row" id="ut-username-row" hidden><label for="ut-username">نام کاربری سرویس</label><input id="ut-username" type="text" maxlength="32" placeholder="مثلاً myuser" dir="ltr" autocomplete="off" /></div><button id="ut-submit" type="button" class="btn btn-primary btn-block">${icon('gift')}<span class="ut-label">ساخت اکانت تست</span>${icon('arrowLeft')}</button><div id="ut-error-host" aria-live="polite"></div></section>`;
    $host.querySelector('#ut-change-location')?.addEventListener('click', () => renderPanelList($host, panels, info));
    const $submit = $host.querySelector('#ut-submit');
    const $label = $submit.querySelector('.ut-label');
    const $usernameRow = $host.querySelector('#ut-username-row');
    const $username = $host.querySelector('#ut-username');

    $usernameRow.hidden = false;

    $submit.addEventListener('click', async () => {
        if ($submit.disabled) return;
        hapticImpact('light');
        $host.querySelector('#ut-error-host').innerHTML = '';
        $submit.disabled = true;
        const change = $host.querySelector('#ut-change-location');
        if (change) change.disabled = true;
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
            if (change) change.disabled = false;
            $host.querySelector('#ut-error-host').innerHTML = `<div class="ut-error" role="alert">${icon('alert')}<p>${escapeHtml(err.message || 'اکانت تست ساخته نشد؛ دوباره تلاش کن.')}</p></div>`;
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
        <div class="ut-success-heading" role="status"><span>${icon('check')}</span><div><small>تست شروع شد</small><h2>اکانت تست تو آماده‌ست!</h2><p>${obj.delivery_failed ? escapeHtml(obj.delivery_message || 'ارسال تلگرام انجام نشد؛ اطلاعات اتصال همین‌جا در دسترس است.') : 'اطلاعات اتصال در تلگرام هم برایت ارسال شد.'}</p></div></div>
        <div class="ut-service-name">${icon('user')} نام کاربری <strong dir="ltr">${escapeHtml(svc.username || '—')}</strong></div>
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
                    <div class="sub-link-value mono" id="ut-link-value" dir="ltr">${escapeHtml(linkValue)}</div>
                </div>
                <button id="ut-copy-link" type="button" class="btn btn-primary btn-block mt-sm">
                    ${icon('copy', 'class="ico ico-leading"')}
                    <span>کپی لینک</span>
                </button>
            ` : ''}
            ${configs.length ? `<div class="codeblock mt-md">${escapeHtml(configs.join('\n'))}</div>` : ''}
        `}
        <div class="ut-success-actions">
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
            <p class="muted">${escapeHtml(err.message || '')}</p><button type="button" class="btn btn-primary" data-ut-retry>تلاش دوباره</button>
        </div>
    `;
}
