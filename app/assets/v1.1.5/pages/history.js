import { call } from '../api.js?v=0.0.52';
import { escapeHtml, fmtPrice, skeletonList, emptyState } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

function fmtDate(raw) {
    const s = String(raw || '').trim();
    if (!s) return '—';
    const m = s.match(/^(\d{4})[-\/](\d{2})[-\/](\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (!m) return s;
    const d = new Date(Date.UTC(0, 0) + 0);
    d.setFullYear(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    d.setHours(Number(m[4]), Number(m[5]), Number(m[6]), 0);
    if (isNaN(d.getTime())) return s;
    return d.toLocaleString('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function fmtDateParts(raw) {
    const full = fmtDate(raw);
    const m = /^(.*)\s+(\d{1,2}:\d{2})$/.exec(full);
    return m ? { date: m[1], time: m[2] } : { date: full, time: '' };
}

function rowHtml(item) {
    const isCredit = item.direction === 'credit';
    const sign = isCredit ? '+' : '−';
    const badge = isCredit ? 'is-active' : 'is-danger';
    const badgeIcon = isCredit ? 'plus' : 'minus';
    const { date, time } = fmtDateParts(item.created_at);
    return `
        <div class="list-item">
            <div class="li-main">
                <div class="li-title">${escapeHtml(item.category_label || item.category)}</div>
                <div class="li-sub li-sub-stacked">
                    ${time ? `<span class="li-sub-line">${escapeHtml(time)}</span>` : ''}
                    <span class="li-sub-line">${escapeHtml(date)}</span>
                    ${item.order_id ? `<span class="li-sub-line mono">${escapeHtml(item.order_id)}</span>` : ''}
                </div>
            </div>
            <span class="badge ${badge}">${icon(badgeIcon, 'class="ico"')} ${sign}${escapeHtml(fmtPrice(item.amount))}</span>
        </div>
    `;
}

export async function history(view) {
    let page = 1;
    const limit = 4;
    let totalPages = 1;

    view.innerHTML = `
        <a href="#/" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/history</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('clock')} تاریخچه تراکنش‌ها</p>
                <h2 class="section-headline">همه تغییرات کیف پول شما</h2>

                <div id="history-host" class="list mt-md">
                    ${skeletonList(4)}
                </div>

                <div class="pager hidden" id="history-pager">
                    <button class="btn btn-ghost" id="history-prev">${icon('chevronRight', 'class="ico ico-leading"')}قبلی</button>
                    <span class="pager-info" id="history-pager-info"></span>
                    <button class="btn btn-ghost" id="history-next">بعدی${icon('chevronLeft', 'class="ico ico-trailing"')}</button>
                </div>
            </div>
        </article>
    `;

    const $host = view.querySelector('#history-host');
    const $pager = view.querySelector('#history-pager');
    const $info = view.querySelector('#history-pager-info');
    const $prev = view.querySelector('#history-prev');
    const $next = view.querySelector('#history-next');

    async function load() {
        $host.innerHTML = skeletonList(4);
        $pager.classList.add('hidden');

        try {
            const res = await call('transactions', { params: { page: String(page), limit: String(limit) } });
            const obj = res?.obj || {};
            const items = Array.isArray(obj.items) ? obj.items : [];
            totalPages = Number(obj.total_pages) || 0;

            if (items.length === 0) {
                $host.innerHTML = emptyState('تراکنشی ثبت نشده', 'هنوز هیچ تغییری در کیف پول شما ثبت نشده است.', 'clock');
                return;
            }

            $host.innerHTML = items.map(rowHtml).join('');

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $info.textContent = `صفحه ${page} از ${totalPages}`;
                $prev.disabled = page <= 1;
                $next.disabled = page >= totalPages;
            }
        } catch (err) {
            $host.innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>خطا در دریافت تاریخچه</h3>
                    <p class="muted">${escapeHtml(err && err.message ? err.message : '')}</p>
                </div>`;
        }
    }

    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    await load();
}
