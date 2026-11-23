import { call } from '../api.js?v=0.0.53';
import { escapeHtml, skeletonList, fmtBytes, fmtNumber, serviceListIdentity, serviceStatusBadge } from '../utils.js?v=0.0.54';
import { icon } from '../icons.js?v=0.0.52';

const columns = [
    ['شناسهٔ سرویس', 'panel_user_id'],
    ['نام کاربری سرویس', 'username'],
    ['روز مانده', 'days_remaining'],
    ['حجم مانده', 'remaining_bytes'],
    ['تاریخ انقضا', 'expires_at'],
];
const expiryDate = new Intl.DateTimeFormat('fa-IR', { dateStyle: 'short', timeZone: 'Asia/Tehran' });

function statusBadge(it) {
    const status = serviceStatusBadge(it.status || it.Status || '');
    return `<span class="badge ${status.badge}">${icon(status.icon, 'class="ico"')} ${escapeHtml(status.text)}</span>`;
}

function renderServiceRow(it) {
    const username = it.username || '—';
    const href = `#/services/${encodeURIComponent(username)}/${encodeURIComponent(it.id_invoice)}`;
    const days = it.days_remaining == null ? '—' : it.days_remaining === -1 ? 'نامحدود' : fmtNumber(it.days_remaining);
    const traffic = it.remaining_bytes == null ? '—' : it.remaining_bytes === -1 ? 'نامحدود' : fmtBytes(it.remaining_bytes);
    const expires = it.expires_at == null ? '—' : it.expires_at === 0 ? 'نامحدود' : expiryDate.format(it.expires_at * 1000);

    return `<tr>
        <td>${statusBadge(it)}${it.has_queued_renewal ? `<span class="badge is-active">${icon('online', 'class="ico"')} رزرو اشتراک</span>` : ''}</td>
        <td><bdi dir="ltr">${escapeHtml(it.panel_user_id || '—')}</bdi></td>
        <th scope="row">
            <a href="${href}"><bdi dir="ltr">${escapeHtml(it.display_username || username)}</bdi></a>
            ${it.name_product ? `<div class="muted">${escapeHtml(it.name_product)}</div>` : ''}
        </th>
        <td>${escapeHtml(days)}</td>
        <td><bdi>${escapeHtml(traffic)}</bdi></td>
        <td>${escapeHtml(expires)}</td>
    </tr>`;
}

export async function services(view) {
    let page = 1;
    const limit = 10;
    let query = '';
    let totalPages = 1;
    let totalItems = 0;
    let loadSequence = 0;
    let allServices = false;
    let sort = 'recent';
    let direction = 'desc';

    view.innerHTML = `
        <article class="card card-window card-bare">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/services</span>
            </header>
            <div class="card-body">
                <div class="list-head">
                    <p class="section-title">${icon('fileText')} سرویس‌های من</p>
                    <span class="list-count hidden" id="services-count"></span>
                </div>

                <label id="services-scope" class="hidden mt-sm">
                    <input id="services-all" type="checkbox" /> جستجو در همهٔ سرویس‌ها
                </label>
                <label class="search-field mt-sm">
                    ${icon('search', 'class="ico search-field-ico"')}
                    <input id="services-search" type="text" placeholder="نام کاربری سرویس، شناسهٔ سرویس، UUID یا لینک اشتراک" aria-label="جستجوی سرویس" inputmode="search" maxlength="2048" autocomplete="off" autocapitalize="off" spellcheck="false" />
                    <button type="button" class="search-field-clear hidden" id="services-search-clear" aria-label="پاک کردن جستجو">
                        ${icon('close', 'class="ico"')}
                    </button>
                </label>

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
    const $searchClear = view.querySelector('#services-search-clear');
    const $count = view.querySelector('#services-count');
    const $scope = view.querySelector('#services-scope');
    const $all = view.querySelector('#services-all');

    async function load() {
        const request = ++loadSequence;
        $list.innerHTML = skeletonList(4);
        $pager.classList.add('hidden');

        try {
            if (allServices && !query) {
                $count.classList.add('hidden');
                $list.innerHTML = '<div class="empty">برای جستجو در همهٔ سرویس‌ها عبارتی وارد کنید.</div>';
                return;
            }
            const params = allServices ? { page, limit } : { page, limit, sort, direction };
            const res = query
                ? await call('invoices', { method: 'POST', body: { ...params, q: query, scope: allServices ? 'all' : 'mine' } })
                : await call('invoices', { params });
            if (request !== loadSequence) return;
            $scope.classList.toggle('hidden', res?.obj?.can_search_all !== true);

            const items = res?.obj?.items || [];
            totalPages = Number(res?.obj?.total_pages) || 1;
            totalItems = Number(res?.obj?.total) || items.length;

            $count.classList.toggle('hidden', totalItems === 0);
            $count.textContent = `${fmtNumber(totalItems)} سرویس`;

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

            $list.innerHTML = allServices ? items.map(renderAdminItem).join('') : renderTable(items);

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $info.textContent = `صفحه ${fmtNumber(page)} از ${fmtNumber(totalPages)} (${fmtNumber(totalItems)} سرویس)`;
                $prev.disabled = page <= 1;
                $next.disabled = page >= totalPages;
            }
        } catch (err) {
            if (request !== loadSequence) return;
            $count.classList.add('hidden');
            $list.innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>خطا در دریافت لیست</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>
            `;
        }
    }

    function renderTable(items) {
        const headings = columns.map(([label, key]) => {
            const order = key === sort ? (direction === 'asc' ? 'ascending' : 'descending') : '';
            const arrow = order ? (direction === 'asc' ? ' ↑' : ' ↓') : ' ↕';
            return `<th scope="col"${order ? ` aria-sort="${order}"` : ''}><button type="button" data-sort="${key}">${label}<span aria-hidden="true">${arrow}</span></button></th>`;
        }).join('');
        return `<div class="services-table-scroll" tabindex="0" role="region" aria-label="سرویس‌های من">
            <table class="services-table" aria-label="سرویس‌های من">
                <thead><tr><th scope="col">وضعیت</th>${headings}</tr></thead>
                <tbody>${items.map(renderServiceRow).join('')}</tbody>
            </table>
        </div>`;
    }

    function renderAdminItem(it) {
        return `
            <div class="list-item">
                <div class="li-main">
                    ${serviceListIdentity(it.display_username || it.username || '—', it.panel_user_id)}
                    <div class="li-sub">${escapeHtml(it.Service_location)} · شماره سفارش <bdi dir="ltr">${escapeHtml(it.id_invoice)}</bdi></div>
                </div>
                ${statusBadge(it)}
            </div>
        `;
    }

    let searchTimer = null;
    function applySearch() {
        const next = ($search.value || '').trim();
        $searchClear.classList.toggle('hidden', next === '');
        if (next === query) return;
        query = next;
        page = 1;
        load();
    }
    $search.addEventListener('input', () => {
        $searchClear.classList.toggle('hidden', ($search.value || '').trim() === '');
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applySearch, 400);
    });
    $search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            applySearch();
        }
    });
    $searchClear.addEventListener('click', () => {
        $search.value = '';
        clearTimeout(searchTimer);
        applySearch();
        $search.focus();
    });
    $all.addEventListener('change', () => {
        clearTimeout(searchTimer);
        allServices = $all.checked;
        query = $search.value.trim();
        page = 1;
        load();
    });
    $list.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-sort]');
        if (!button) return;
        const nextSort = button.dataset.sort;
        direction = sort === nextSort ? (direction === 'asc' ? 'desc' : 'asc') : (nextSort === 'username' ? 'asc' : 'desc');
        sort = nextSort;
        page = 1;
        await load();
        if (document.activeElement === document.body) {
            $list.querySelector(`button[data-sort="${nextSort}"]`)?.focus();
        }
    });
    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    load();
}
