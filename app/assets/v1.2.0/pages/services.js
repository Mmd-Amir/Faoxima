import { call } from '../api.js?v=0.0.53';
import { escapeHtml, skeletonList, fmtBytes, fmtNumber, serviceListIdentity, serviceStatusBadge } from '../utils.js?v=0.0.54';
import { icon } from '../icons.js?v=0.0.52';

const sortOptions = [
    ['زمان خرید', 'recent'],
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

function renderService(it) {
    const username = it.username || '—';
    const href = `#/services/${encodeURIComponent(username)}/${encodeURIComponent(it.id_invoice)}`;
    const days = it.days_remaining == null ? '—' : it.days_remaining === -1 ? 'نامحدود' : fmtNumber(it.days_remaining);
    const traffic = it.remaining_bytes == null ? '—' : it.remaining_bytes === -1 ? 'نامحدود' : fmtBytes(it.remaining_bytes);
    const expires = it.expires_at == null ? '—' : it.expires_at === 0 ? 'نامحدود' : expiryDate.format(it.expires_at * 1000);

    return `<article class="service-summary">
        <div class="service-summary-head">
            <div class="service-summary-identity">
                <h2><a href="${href}"><bdi>${escapeHtml(it.display_username || username)}</bdi>${icon('chevronLeft', 'class="service-open-icon" aria-hidden="true"')}</a></h2>
                <div class="service-summary-meta">
                    <span>شناسهٔ سرویس <bdi dir="ltr">${escapeHtml(it.panel_user_id || '—')}</bdi></span>
                    ${it.name_product ? `<span class="service-summary-plan">${escapeHtml(it.name_product)}</span>` : ''}
                </div>
            </div>
            <div class="service-summary-badges">
                ${statusBadge(it)}
                ${it.has_queued_renewal ? '<span class="badge is-gold">رزرو اشتراک</span>' : ''}
            </div>
        </div>
        <dl class="service-summary-metrics">
            <div><dt>روز مانده</dt><dd><bdi>${escapeHtml(days)}</bdi></dd></div>
            <div><dt>حجم مانده</dt><dd><bdi>${escapeHtml(traffic)}</bdi></dd></div>
            <div><dt>تاریخ انقضا</dt><dd><bdi>${escapeHtml(expires)}</bdi></dd></div>
        </dl>
    </article>`;
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
        <section class="services-page" aria-labelledby="services-heading">
            <div class="services-heading">
                <h1 id="services-heading">سرویس‌های من</h1>
                <span class="list-count hidden" id="services-count" aria-live="polite"></span>
            </div>

            <div class="services-tools">
                <div class="search-field services-search">
                    ${icon('search', 'class="ico search-field-ico"')}
                    <input id="services-search" type="text" dir="auto" placeholder="جستجو در سرویس‌ها" aria-label="جستجوی سرویس" enterkeyhint="search" inputmode="search" maxlength="2048" autocomplete="off" autocapitalize="off" spellcheck="false" />
                    <button type="button" class="search-field-clear hidden" id="services-search-clear" aria-label="پاک کردن جستجو">
                        ${icon('close', 'class="ico"')}
                    </button>
                </div>
                <details class="services-search-help">
                    <summary>راهنمای جستجو</summary>
                    <p>نام کاربری، شناسهٔ سرویس، UUID یا لینک اشتراک</p>
                </details>
                <label id="services-scope" class="services-scope hidden">
                    <input id="services-all" type="checkbox" /> جستجو در همهٔ سرویس‌ها
                </label>
                <div class="services-sort" id="services-sort">
                    <label for="services-sort-field">مرتب‌سازی</label>
                    <select id="services-sort-field">
                        ${sortOptions.map(([label, key]) => `<option value="${key}">${label}</option>`).join('')}
                    </select>
                    <button class="btn btn-ghost" type="button" id="services-sort-direction" aria-label="تغییر جهت مرتب‌سازی به صعودی">↓ نزولی</button>
                </div>
            </div>

            <div id="services-list" class="list" aria-busy="true">
                ${skeletonList(4)}
            </div>

            <div class="pager hidden" id="services-pager">
                <button class="btn btn-ghost" id="services-prev"><span aria-hidden="true">›</span> قبلی</button>
                <span class="pager-info" id="services-pager-info"></span>
                <button class="btn btn-ghost" id="services-next">بعدی <span aria-hidden="true">‹</span></button>
            </div>
        </section>
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
    const $sort = view.querySelector('#services-sort');
    const $sortField = view.querySelector('#services-sort-field');
    const $sortDirection = view.querySelector('#services-sort-direction');

    async function load() {
        const request = ++loadSequence;
        $list.setAttribute('aria-busy', 'true');
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

            $list.innerHTML = items.map(allServices ? renderAdminItem : renderService).join('');

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $info.textContent = `صفحه ${fmtNumber(page)} از ${fmtNumber(totalPages)}`;
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
        } finally {
            if (request === loadSequence) $list.setAttribute('aria-busy', 'false');
        }
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
        $sort.classList.toggle('hidden', allServices);
        query = $search.value.trim();
        $searchClear.classList.toggle('hidden', query === '');
        page = 1;
        load();
    });
    function applySort() {
        const ascending = direction === 'asc';
        $sortDirection.textContent = ascending ? '↑ صعودی' : '↓ نزولی';
        $sortDirection.setAttribute('aria-label', ascending ? 'تغییر جهت مرتب‌سازی به نزولی' : 'تغییر جهت مرتب‌سازی به صعودی');
        page = 1;
        load();
    }

    $sortField.addEventListener('change', () => {
        sort = $sortField.value;
        direction = sort === 'username' ? 'asc' : 'desc';
        applySort();
    });
    $sortDirection.addEventListener('click', () => {
        direction = direction === 'asc' ? 'desc' : 'asc';
        applySort();
    });
    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    load();
}
