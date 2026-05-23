import { escapeHtml, fmtGb, fmtDays, copyToClipboard, toast } from '../utils.js';
import { icon } from '../icons.js';
import { hapticImpact } from '../telegram.js';


function bytesToGb(bytes) {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return 0;
    return n / Math.pow(1024, 3);
}

function fmtBytesGb(bytes) {
    return fmtGb(bytesToGb(bytes));
}

function gaugeSvg(percent, opts = {}) {
    const r = 50;
    const c = 2 * Math.PI * r;
    const safePct = Math.max(0, Math.min(100, Number(percent) || 0));
    const offset = c * (1 - safePct / 100);
    const stroke = opts.strokeWidth || 9;
    return `
        <svg viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="${r}" class="info-gauge-track" stroke-width="${stroke}"/>
            <circle cx="60" cy="60" r="${r}" class="info-gauge-fill"
                stroke-width="${stroke}"
                stroke-dasharray="${c.toFixed(2)}"
                stroke-dashoffset="${offset.toFixed(2)}"/>
        </svg>`;
}


function getConnectionData(service) {
    const sub = String(service?.subscription_url || '').trim();
    if (sub) return sub;
    const configs = Array.isArray(service?.configs) ? service.configs : [];
    for (const c of configs) {
        if (typeof c === 'string' && c.trim() !== '') return c.trim();
    }
    return '';
}


function renderQrPanel(data) {
    if (!data) return '';
    const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '/api';

    const v = Math.floor(Date.now() / (60 * 60 * 1000));
    const src = `${apiUrl}/qr.php?d=${encodeURIComponent(data)}&s=480&v=${v}`;
    return `
        <div class="qr-panel" id="qr-panel">
            <div class="qr-frame">
                <img src="${escapeHtml(src)}" alt="QR Code" />
            </div>
            <p class="qr-hint">دستگاه دیگر را به QR Code نزدیک کنید تا لینک اتصال خوانده شود.</p>
        </div>`;
}


export function renderServiceSuccess(view, res, opts = {}) {
    const service = res?.service || {};
    const usernameAc = String(service.username || res?.username || '—');
    const orderId = String(res?.order_id || service.id || '—');
    const productName = String(service.product_name || opts.productName || '—');
    const panelName = String(service.panel_name || opts.panelName || '—');
    const serviceDays = Number(service.service_time_days ?? service.days_left ?? 0);
    const unlimitedVolume = !!service.unlimited_volume;
    const unlimitedTime = !!service.unlimited_time;
    const totalBytes = Number(service.total_bytes || 0);
    const usedBytes = Number(service.used_bytes || 0);
    const percent = (!unlimitedVolume && totalBytes > 0)
        ? Math.max(0, Math.min(100, (usedBytes / totalBytes) * 100))
        : 0;

    const connectionData = getConnectionData(service);
    const volumeGb = Number(service.volume_gb || 0);

    const headerTag = (window.__APP_CONFIG__ || {}).botUsername
        ? `@${(window.__APP_CONFIG__ || {}).botUsername}`
        : '';

    view.innerHTML = `
        <article class="card" style="background:transparent;border:none;box-shadow:none;padding:0">
            <div class="card-body" style="text-align:center;padding:8px 4px 16px">
                <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 8px">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                </div>
                <h2 style="margin:6px 0 4px;font-size:18px">سرویس با موفقیت ایجاد شد</h2>
                <p class="muted" style="font-size:13px">جزئیات و کانفیگ سرویس برای شما ارسال شد.</p>
            </div>

            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">service/info</span>
                </header>
                <div class="card-body info-card">
                    <div class="info-card-title-row">
                        <div class="info-card-title">
                            <span class="arrow">&gt;</span><span>کانفیگ: ${escapeHtml(usernameAc)}</span>
                        </div>
                        <span class="info-card-status is-active">فعال</span>
                    </div>

                    <div class="info-card-body">
                        <div class="info-gauge">
                            ${unlimitedVolume ? `
                                ${gaugeSvg(0)}
                                <div class="info-gauge-text">
                                    <span class="info-gauge-unl">نامحدود</span>
                                </div>
                            ` : `
                                ${gaugeSvg(percent)}
                                <div class="info-gauge-text">
                                    <span class="info-gauge-percent">${percent.toFixed(1)}%</span>
                                    <span class="info-gauge-label">مصرف</span>
                                </div>
                            `}
                        </div>

                        <div class="info-stats">
                            <div>
                                <div class="info-stat-label"><span class="glyph">$</span><span>حجم مصرفی</span></div>
                                <div class="info-stat-value">
                                    ${unlimitedVolume
                                        ? '<span class="fa">نامحدود</span>'
                                        : `${fmtBytesGb(usedBytes)} <span style="color:var(--text-dim)">/</span> ${fmtBytesGb(totalBytes)}`}
                                </div>
                            </div>
                            <div>
                                <div class="info-stat-label"><span class="glyph">#</span><span>زمان باقی‌مانده</span></div>
                                <div class="info-stat-value">
                                    ${unlimitedTime
                                        ? '<span class="fa">نامحدود</span>'
                                        : (serviceDays <= 0
                                            ? '<span class="fa">منقضی شده</span>'
                                            : `${serviceDays} <span class="fa" style="font-size:14px;font-weight:500">روز</span>`)}
                                </div>
                            </div>
                        </div>
                    </div>

                    ${headerTag ? `<div class="info-card-footer">${escapeHtml(headerTag)}</div>` : ''}
                </div>
            </article>

            <div class="service-detail-list">
                <div class="service-detail-row">
                    ${icon('user', 'class="ico ico-leading"')}
                    <span class="service-detail-label">نام کاربری</span>
                    <span class="service-detail-value mono">${escapeHtml(usernameAc)}</span>
                </div>
                <div class="service-detail-row">
                    ${icon('fileText', 'class="ico ico-leading"')}
                    <span class="service-detail-label">نام سرویس</span>
                    <span class="service-detail-value">${escapeHtml(productName)}</span>
                </div>
                <div class="service-detail-row">
                    ${icon('pin', 'class="ico ico-leading"')}
                    <span class="service-detail-label">لوکیشن</span>
                    <span class="service-detail-value">${escapeHtml(panelName)}</span>
                </div>
                <div class="service-detail-row">
                    ${icon('hourglass', 'class="ico ico-leading"')}
                    <span class="service-detail-label">مدت زمان</span>
                    <span class="service-detail-value">${escapeHtml(unlimitedTime ? 'نامحدود' : fmtDays(serviceDays))}</span>
                </div>
                <div class="service-detail-row">
                    ${icon('box', 'class="ico ico-leading"')}
                    <span class="service-detail-label">حجم سرویس</span>
                    <span class="service-detail-value">${escapeHtml(unlimitedVolume ? 'نامحدود' : fmtGb(volumeGb))}</span>
                </div>
                <div class="service-detail-row">
                    ${icon('copy', 'class="ico ico-leading"')}
                    <span class="service-detail-label">کد پیگیری</span>
                    <span class="service-detail-value mono" style="color:var(--gold-bright)">${escapeHtml(orderId)}</span>
                </div>
            </div>

            ${connectionData ? `
                <div class="sub-link-box">
                    <div class="label">${icon('link', 'class="ico ico-leading"')} <span>لینک اتصال</span></div>
                    <div class="sub-link-value mono" id="sub-link-value" title="برای کپی کلیک کنید">${escapeHtml(connectionData)}</div>
                </div>
            ` : ''}

            <div id="qr-host"></div>

            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                ${connectionData ? `
                    <button type="button" class="btn btn-primary btn-block" id="qr-toggle-btn" style="flex:1">
                        ${icon('qrCode', 'class="ico ico-leading"')}
                        <span>دریافت QR Code</span>
                    </button>` : ''}
                <a href="#/services/${encodeURIComponent(usernameAc)}" class="btn btn-ghost btn-block" style="flex:1">
                    ${icon('user', 'class="ico ico-leading"')}
                    <span>مشاهده سرویس</span>
                </a>
            </div>
            <a href="#/" class="btn btn-ghost btn-block mt-sm">
                ${icon('home', 'class="ico ico-leading"')}
                <span>بازگشت به خانه</span>
            </a>
        </article>
    `;


    const $linkBox = view.querySelector('#sub-link-value');
    if ($linkBox) {
        $linkBox.addEventListener('click', async () => {
            const ok = await copyToClipboard($linkBox.textContent || '');
            hapticImpact('light');
            toast(ok ? 'لینک کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    }


    const $qrBtn = view.querySelector('#qr-toggle-btn');
    const $qrHost = view.querySelector('#qr-host');
    if ($qrBtn && $qrHost) {
        let open = false;
        $qrBtn.addEventListener('click', () => {
            hapticImpact('light');
            open = !open;
            if (open) {
                $qrHost.innerHTML = renderQrPanel(connectionData);
                $qrBtn.querySelector('span:last-child').textContent = 'بستن QR Code';
                $qrHost.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                $qrHost.innerHTML = '';
                $qrBtn.querySelector('span:last-child').textContent = 'دریافت QR Code';
            }
        });
    }
}
