import { call } from '../api.js';
import { escapeHtml, fmtGb, skeletonList, copyToClipboard, toast, trafficPercent } from '../utils.js';

export async function service(view, encodedUsername) {
    const username = decodeURIComponent(encodedUsername || '');

    view.innerHTML = `
        <a href="#/services" class="page-back">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            بازگشت
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

    const $body = view.querySelector('#service-body');

    let info = null;
    try {
        const res = await call('service', { params: { username } });
        info = res?.obj;
    } catch (err) {
        $body.innerHTML = `
            <div class="empty">
                <span class="glyph">!</span>
                <h3>دریافت اطلاعات با خطا روبرو شد</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
        return;
    }

    if (!info) {
        $body.innerHTML = `<div class="empty"><span class="glyph">∅</span><h3>اطلاعاتی یافت نشد</h3></div>`;
        return;
    }

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
    else if (s === 'on_hold' || s === 'send_on_hold')  { statusBadge = 'is-warn'; statusText = 'متوقف'; }
    else if (s === 'expired')                          { statusBadge = 'is-warn'; statusText = 'منقضی'; }
    else if (s === 'active')                           { statusText = 'فعال'; }

    $body.innerHTML = `
        <div class="row-spread">
            <div>
                <div class="muted mono" style="font-size:12px">${escapeHtml(info.username || username)}</div>
                <h2 style="margin:4px 0 0;font-size:20px;font-weight:700">${escapeHtml(info.product_name || '—')}</h2>
            </div>
            <span class="badge ${statusBadge}">${escapeHtml(statusText)}</span>
        </div>

        <div class="card-section">
            <div class="row-spread">
                <span class="kv-label"><span class="glyph">~</span> مصرف ترافیک</span>
                <span class="mono gold">${escapeHtml(fmtGb(used))} / ${escapeHtml(fmtGb(total))}</span>
            </div>
            <div class="progress"><span class="progress-fill ${pctClass}" style="width:${percent.toFixed(1)}%"></span></div>
            <div class="muted mono" style="font-size:12px">باقی‌مانده: ${escapeHtml(fmtGb(remaining))}</div>
        </div>

        <div class="card-section">
            <div class="kv">
                <span class="kv-label"><span class="glyph">/</span> تاریخ انقضا</span>
                <span class="kv-value">${escapeHtml(expiration)}</span>
            </div>
            <div class="kv">
                <span class="kv-label"><span class="glyph">@</span> آخرین آنلاین</span>
                <span class="kv-value">${escapeHtml(onlineLabel)}</span>
            </div>
            <div class="kv">
                <span class="kv-label"><span class="glyph">#</span> آخرین به‌روزرسانی</span>
                <span class="kv-value">${escapeHtml(lastUpdate)}</span>
            </div>
        </div>

        <div class="card-section" id="service-config-host"></div>
    `;


    const $cfgHost = view.querySelector('#service-config-host');
    const outputs = Array.isArray(info.service_output) ? info.service_output : [];
    if (outputs.length === 0) {
        $cfgHost.innerHTML = `<div class="muted center mono" style="font-size:12px">کانفیگی برای نمایش وجود ندارد</div>`;
    } else {
        $cfgHost.innerHTML = outputs.map((o, i) => renderOutput(o, i)).join('');
    }


    view.querySelectorAll('.copy-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const txt = btn.dataset.copy || '';
            const ok = await copyToClipboard(txt);
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    });
}

function renderOutput(o, i) {
    const type = (o?.type || '').toLowerCase();
    if (type === 'link') {
        return `
            <p class="section-title">لینک اشتراک</p>
            <div class="codeblock">${escapeHtml(o.value || '')}<button class="copy-btn" data-copy="${escapeHtml(o.value || '')}">کپی</button></div>
        `;
    }
    if (type === 'config') {
        const items = Array.isArray(o.value) ? o.value : String(o.value || '').split('\n');
        return `
            <p class="section-title">کانفیگ‌ها</p>
            ${items.map((c) => `
                <div class="codeblock" style="margin-bottom:8px">${escapeHtml(c)}<button class="copy-btn" data-copy="${escapeHtml(c)}">کپی</button></div>
            `).join('')}
        `;
    }
    if (type === 'file') {
        return `
            <p class="section-title">فایل کانفیگ</p>
            <a class="btn btn-ghost btn-block" href="${escapeHtml(o.value || '#')}" download="${escapeHtml(o.filename || 'config.conf')}" target="_blank" rel="noopener">دانلود ${escapeHtml(o.filename || 'config.conf')}</a>
        `;
    }
    if (type === 'password') {
        return `
            <p class="section-title">رمز عبور</p>
            <div class="codeblock">${escapeHtml(o.value || '')}<button class="copy-btn" data-copy="${escapeHtml(o.value || '')}">کپی</button></div>
        `;
    }
    return '';
}

