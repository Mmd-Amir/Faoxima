import { call } from '../api.js';
import { escapeHtml, skeletonList, emptyState, toast, fmtNumber } from '../utils.js';
import { icon } from '../icons.js';

export async function services(view) {
    let page = 1;
    const limit = 10;
    let query = '';
    let totalPages = 1;
    let totalItems = 0;

    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/services</span>
            </header>
            <div class="card-body">
                <div class="input-row">
                    <label class="input-mono" style="flex:1">
                        <input id="services-search" type="text" placeholder="جستجو در نام سرویس..." inputmode="search" />
                    </label>
                    <button class="btn btn-primary" id="services-search-btn">
                        <span>جستجو</span>
                        ${icon('chevronLeft', 'class="ico ico-trailing"')}
                    </button>
                </div>

                <p class="section-title mt-md">${icon('fileText')} اشتراک‌های فعال</p>

                <div id="services-list" class="list mt-sm">
                    ${skeletonList(4)}
                </div>

                <div class="pager hidden" id="services-pager">
                    <button class="btn btn-ghost" id="services-prev">${icon('chevronRight', 'class="ico ico-leading"')}قبلی</button>
                    <span class="pager-info" id="services-pager-info"></span>
                    <button class="btn btn-ghost" id="services-next">بعدی${icon('chevronLeft', 'class="ico ico-trailing"')}</button>
                </div>
            </div>
        </article>
    `;

    const $list = view.querySelector('#services-list');
    const $pager = view.querySelector('#services-pager');
    const $info = view.querySelector('#services-pager-info');
    const $prev = view.querySelector('#services-prev');
    const $next = view.querySelector('#services-next');
    const $search = view.querySelector('#services-search');
    const $searchBtn = view.querySelector('#services-search-btn');

    async function load() {
        $list.innerHTML = skeletonList(4);
        $pager.classList.add('hidden');

        try {
            const params = { page: String(page), limit: String(limit) };
            if (query) params.q = query;
            const res = await call('invoices', { params });

            const items = res?.obj?.items || [];
            totalPages = Number(res?.obj?.total_pages) || 1;
            totalItems = Number(res?.obj?.total) || items.length;

            if (items.length === 0) {
                $list.innerHTML = `
                    <div class="empty">
                        ${icon('info', 'class="ico ico-xxl ico-muted"')}
                        <h3>${query ? 'نتیجه‌ای یافت نشد' : 'هنوز سرویسی ندارید'}</h3>
                        ${query ? '' : `<a href="#/buy" class="btn btn-primary mt-md">${icon('cart', 'class="ico ico-leading"')}<span>اولین سرویس را بخرید</span></a>`}
                    </div>
                `;
                return;
            }

            $list.innerHTML = items.map(renderItem).join('');

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $info.textContent = `صفحه ${fmtNumber(page)} از ${fmtNumber(totalPages)} (${fmtNumber(totalItems)} سرویس)`;
                $prev.disabled = page <= 1;
                $next.disabled = page >= totalPages;
            }
        } catch (err) {
            $list.innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>خطا در دریافت لیست</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>
            `;
        }
    }

    function renderItem(it) {
        const status = String(it.status || it.Status || '').toLowerCase();
        let badge = 'is-active';
        let badgeText = 'فعال';
        let badgeIcon = 'online';
        if (status === 'end_of_time')        { badge = 'is-warn';   badgeText = 'پایان زمان'; badgeIcon = 'clock'; }
        else if (status === 'end_of_volume') { badge = 'is-warn';   badgeText = 'پایان حجم';  badgeIcon = 'box'; }
        else if (status === 'sendedwarn')    { badge = 'is-warn';   badgeText = 'هشدار';     badgeIcon = 'warning'; }
        else if (status === 'send_on_hold')  { badge = 'is-danger'; badgeText = 'متوقف';     badgeIcon = 'powerOff'; }

        const username = it.username || '—';
        const productName = it.name_product || '—';
        const location = it.Service_location || '';

        return `
            <a href="#/services/${encodeURIComponent(username)}" class="list-item" data-username="${escapeHtml(username)}">
                <div class="li-main">
                    <div class="li-title">${escapeHtml(productName)}</div>
                    <div class="li-sub">${escapeHtml(username)}${location ? ' · ' + escapeHtml(location) : ''}</div>
                </div>
                <span class="badge ${badge}">${icon(badgeIcon, 'class="ico"')} ${escapeHtml(badgeText)}</span>
                <span class="li-action" aria-hidden="true">
                    ${icon('chevronLeft', 'class="ico"')}
                </span>
            </a>
        `;
    }

    $searchBtn.addEventListener('click', () => {
        query = ($search.value || '').trim();
        page = 1;
        load();
    });
    $search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            $searchBtn.click();
        }
    });
    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    load();
}

