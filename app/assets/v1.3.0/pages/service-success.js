import { escapeHtml, fmtBytes, copyToClipboard, toast, blobToDataUrl } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact } from '../telegram.js?v=0.0.52';
import { renderServiceOutputs } from './service.js?v=0.0.76';

function getConnectionData(service) {
    const sub = String(service?.subscription_url || '').trim();
    if (sub) return sub;
    const configs = Array.isArray(service?.configs) ? service.configs : [];
    return configs.find(c => typeof c === 'string' && c.trim())?.trim() || '';
}

function detailRow(label, value, ico, copy = '') {
    return `<div class="ss-detail"><dt>${icon(ico)} ${escapeHtml(label)}</dt><dd><span>${escapeHtml(value)}</span>${copy ? `<button type="button" class="ss-copy-icon" data-success-copy="${escapeHtml(copy)}" aria-label="کپی ${escapeHtml(label)}">${icon('copy')}</button>` : ''}</dd></div>`;
}

export function renderServiceSuccess(view, res, opts = {}) {
    const service = res?.service || {};
    const username = String(service.username || res?.username || '—');
    const displayUsername = String(service.display_username || username);
    const orderId = String(res?.order_id || service.id || '—');
    const productName = String(service.product_name || opts.productName || '—');
    const panelName = String(service.panel_name || opts.panelName || '—');
    const totalGb = Number(service.total_traffic_gb ?? service.volume_gb) || 0;
    const usedGb = Number(service.used_traffic_gb) || 0;
    const totalBytes = Number.isFinite(Number(service.total_bytes)) ? Number(service.total_bytes) : totalGb * Math.pow(1024, 3);
    const usedBytes = Number.isFinite(Number(service.used_bytes)) ? Number(service.used_bytes) : usedGb * Math.pow(1024, 3);
    const unlimitedVolume = service.is_stock === true || service.unlimited_volume === true || totalBytes === 0;
    const expiration = String(service.expiration_time || 'نامحدود');
    const percent = !unlimitedVolume && totalBytes > 0 ? Math.max(0, Math.min(100, usedBytes / totalBytes * 100)) : 0;
    const isManual = String(service.panel_type || '') === 'Manualsale' || service.is_stock === true;
    const outputs = Array.isArray(service.service_output) ? service.service_output : [];
    const primaryLink = outputs.find(o => String(o?.type || '').toLowerCase() === 'link' && String(o.value || '').trim());
    const connectionData = outputs.length ? String(primaryLink?.value || '').trim() : getConnectionData(service);
    const remainingOutputs = outputs.filter(o => o !== primaryLink);
    const qrAllowed = connectionData && !isManual && (window.__APP_CONFIG__ || {}).qrEnabled !== false && new TextEncoder().encode(connectionData).length <= 2048;
    const headerTag = (window.__APP_CONFIG__ || {}).botUsername || '';

    view.innerHTML = `
        <article class="ss-page" aria-label="خرید موفق">
            <header class="ss-hero" role="status">
                <span class="ss-success-icon" aria-hidden="true">${icon('check')}</span>
                <div><span class="ss-eyebrow">خرید تکمیل شد</span><h1>سرویس تو آماده‌ست!</h1><p>از بخش اتصال، اطلاعات سرویس را دریافت کن.</p></div>
                <span class="ss-hero-spark" aria-hidden="true">${icon('checkCircle')}</span>
            </header>

            ${connectionData ? `
                <section class="ss-connect" aria-labelledby="ss-connect-title">
                    <div class="ss-section-heading"><span class="ss-section-icon">${icon('link')}</span><div><h2 id="ss-connect-title">${primaryLink || service.subscription_url ? 'لینک اشتراک تو' : 'لینک اتصال تو'}</h2><p>لینک را کپی کن و در برنامهٔ اتصال وارد کن.</p></div><span class="ss-ready">آمادهٔ اتصال</span></div>
                    <label class="ss-sr-only" for="ss-connection">لینک اتصال</label><textarea id="ss-connection" rows="2" readonly dir="ltr" spellcheck="false">${escapeHtml(connectionData)}</textarea>
                    <div class="ss-connect-actions"><button type="button" class="btn btn-primary" data-success-copy="${escapeHtml(connectionData)}">${icon('copy')} کپی لینک اتصال</button>${qrAllowed ? `<button type="button" class="btn btn-ghost" id="qr-toggle-btn" aria-expanded="false" aria-controls="qr-host">${icon('qrCode')} <span>نمایش QR</span></button>` : ''}</div>
                    <div id="qr-host" aria-live="polite"></div>
                </section>
            ` : ''}

            <section class="ss-overview" aria-labelledby="ss-overview-title">
                <div class="ss-overview-heading"><div><span class="ss-eyebrow">سرویس جدید تو</span><h2 id="ss-overview-title">${escapeHtml(productName)}</h2></div><span class="ss-status"><i></i> فعال</span></div>
                ${!isManual ? `<div class="ss-stats"><div>${icon('box')}<span>حجم باقی‌مانده</span><strong>${escapeHtml(unlimitedVolume ? 'نامحدود' : fmtBytes(Math.max(0, totalBytes - usedBytes)))}</strong></div><div>${icon('calendar')}<span>تاریخ انقضا</span><strong>${escapeHtml(expiration)}</strong></div></div><div class="ss-usage-heading"><span>مصرف سرویس</span><b dir="ltr">${escapeHtml(fmtBytes(usedBytes))} / ${escapeHtml(unlimitedVolume ? '∞' : fmtBytes(totalBytes))}</b></div><div class="ss-usage" ${unlimitedVolume ? '' : `role="progressbar" aria-label="مصرف سرویس" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent.toFixed(1)}"`}><span style="width:${percent}%"></span></div>` : ''}
                <dl class="ss-details">
                    ${detailRow('نام کاربری', displayUsername, 'user', username === '—' ? '' : username)}
                    ${detailRow('موقعیت', panelName, 'pin')}
                    ${isManual ? detailRow('تاریخ انقضا', expiration, 'calendar') : ''}
                    ${detailRow('کد پیگیری', orderId, 'fileText', orderId === '—' ? '' : orderId)}
                </dl>
                ${headerTag ? `<div class="ss-bot-tag" dir="ltr">@${escapeHtml(String(headerTag).replace(/^@/, ''))}</div>` : ''}
            </section>

            ${remainingOutputs.length ? `<section class="ss-outputs" aria-labelledby="ss-outputs-title"><div class="ss-list-heading"><h2 id="ss-outputs-title">${connectionData ? 'روش‌های دیگر اتصال' : 'اطلاعات اتصال'}</h2><span>برای دریافت، گزینهٔ دلخواه را باز کن</span></div><div id="service-output-host"></div></section>` : ''}
            ${!connectionData && !remainingOutputs.length ? `<div class="ss-empty">${icon('info')} اطلاعات اتصال را از بخش مدیریت سرویس بررسی کن.</div>` : ''}

            <footer class="ss-footer"><a href="#/services/${encodeURIComponent(username)}" class="btn btn-primary">${icon('settings')} مدیریت سرویس ${icon('arrowLeft')}</a><a href="#/" class="ss-home-link">${icon('home')} بازگشت به خانه</a></footer>
        </article>`;

    const outputHost = view.querySelector('#service-output-host');
    if (outputHost) renderServiceOutputs(outputHost, remainingOutputs, { isManual, username, filePrefix: service.file_prefix || '' });

    view.querySelectorAll('[data-success-copy]').forEach(button => {
        button.addEventListener('click', async () => {
            const ok = await copyToClipboard(button.dataset.successCopy || '');
            hapticImpact('light');
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    });

    const qrButton = view.querySelector('#qr-toggle-btn');
    const qrHost = view.querySelector('#qr-host');
    if (qrButton && qrHost) {
        qrButton.addEventListener('click', async () => {
            hapticImpact('light');
            if (qrButton.getAttribute('aria-expanded') === 'true') {
                qrHost.innerHTML = '';
                qrButton.setAttribute('aria-expanded', 'false');
                qrButton.querySelector('span').textContent = 'نمایش QR';
                return;
            }
            qrButton.disabled = true;
            qrButton.setAttribute('aria-busy', 'true');
            qrButton.querySelector('span').textContent = 'در حال دریافت…';
            try {
                const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '/api';
                const version = Math.floor(Date.now() / (60 * 60 * 1000));
                const response = await fetch(`${apiUrl}/qr.php?d=${encodeURIComponent(connectionData)}&s=480&v=${version}`, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('QR load failed');
                const src = await blobToDataUrl(await response.blob());
                qrHost.innerHTML = `<div class="ss-qr"><img src="${escapeHtml(src)}" alt="کد QR اتصال سرویس" /><p>کد را با برنامهٔ اتصال در دستگاه دیگر اسکن کن.</p></div>`;
                qrButton.setAttribute('aria-expanded', 'true');
                qrButton.querySelector('span').textContent = 'بستن QR';
            } catch (_) {
                qrButton.querySelector('span').textContent = 'نمایش QR';
                toast('دریافت QR ممکن نشد؛ دوباره تلاش کن.', 'error');
            } finally {
                qrButton.disabled = false;
                qrButton.removeAttribute('aria-busy');
            }
        });
    }
}
