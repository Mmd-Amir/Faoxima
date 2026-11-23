import { call } from '../api.js?v=0.0.53';
import { escapeHtml, skeletonList, fmtNumber, serviceStatusBadge } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

export async function services(view) {
    let page = 1;
    const limit = 10;
    let query = '';
    let totalPages = 1;
    let totalItems = 0;
    let loadSequence = 0;
    let allServices = false;

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
                    <input id="services-search" type="text" placeholder="نام کاربری، شناسهٔ حساب، UUID یا لینک اشتراک" aria-label="جستجوی سرویس" inputmode="search" maxlength="2048" autocomplete="off" autocapitalize="off" spellcheck="false" />
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
            const params = { page, limit };
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

            $list.innerHTML = items.map(renderItem).join('');

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

    function renderItem(it) {
        const st = serviceStatusBadge(it.status || it.Status || '');
        const badge = st.badge;
        const badgeText = st.text;
        const badgeIcon = st.icon;

        const username = it.username || '—';
        const displayUsername = it.display_username || username;
        const productName = it.name_product || '—';
        const location = it.Service_location || '';
        const accountId = it.panel_user_id ? ` · شناسهٔ حساب <bdi dir="ltr">${escapeHtml(it.panel_user_id)}</bdi>` : '';
        const detail = allServices ? ` · شمارهٔ سفارش <bdi dir="ltr">${escapeHtml(it.id_invoice)}</bdi>` : '';
        const href = `#/services/${encodeURIComponent(username)}/${encodeURIComponent(it.id_invoice)}`;
        const container = allServices ? 'div' : 'a';

        return `
            <${container} class="list-item"${allServices ? '' : ` href="${href}"`}>
                <div class="li-main">
                    <div class="li-title">${escapeHtml(productName)}</div>
                    <div class="li-sub li-sub-stacked">
                        <span style="overflow-wrap:anywhere"><bdi>${escapeHtml(displayUsername)}</bdi>${accountId}</span>
                        ${location || detail ? `<span class="li-sub-line">${escapeHtml(location)}${detail}</span>` : ''}
                    </div>
                </div>
                ${it.has_queued_renewal ? `<span class="badge is-active">${icon('online', 'class="ico"')} رزرو اشتراک</span>` : ''}
                <span class="badge ${badge}">${icon(badgeIcon, 'class="ico"')} ${escapeHtml(badgeText)}</span>
                ${allServices ? '' : `<span class="li-action" aria-hidden="true">${icon('chevronLeft', 'class="ico"')}</span>`}
            </${container}>
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
    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    load();
}
