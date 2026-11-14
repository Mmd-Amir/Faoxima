import { call } from '../api.js?v=0.0.53';
import { escapeHtml, fmtPrice, skeletonList, emptyState, copyToClipboard, toast } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

function dateParts(raw) {
    const value = String(raw || '').trim();
    const match = value.match(/^(\d{4})[-/](\d{2})[-/](\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (!match) return { date: value || 'تاریخ نامشخص', time: '' };
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]), Number(match[6]));
    if (isNaN(date.getTime())) return { date: value, time: '' };
    return { date: date.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }), time: date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', hour12: false }) };
}

function rowHtml(item) {
    const credit = item.direction === 'credit';
    const category = String(item.category || '');
    const visual = category.includes('purchase') ? 'box' : category.includes('transfer') ? 'transfer' : category.includes('gift') || category.includes('cashback') ? 'diamond' : 'wallet';
    const { time } = dateParts(item.created_at);
    const label = item.category_label || category || 'تراکنش کیف پول';
    return `<details class="history-entry ${credit ? 'is-credit' : 'is-debit'}"><summary><span class="history-entry-icon">${icon(visual)}</span><span class="history-entry-copy"><b>${escapeHtml(label)}</b><small>${time ? `<span dir="ltr">${escapeHtml(time)}</span><i></i>` : ''}${credit ? 'افزایش موجودی' : 'کاهش موجودی'}</small></span><span class="history-entry-amount"><strong dir="ltr">${credit ? '+' : '−'}${escapeHtml(Math.abs(Number(item.amount) || 0).toLocaleString('fa-IR'))}</strong><small>تومان</small></span>${icon('chevronDown', 'class="ico history-entry-chevron"')}</summary><div class="history-entry-detail">${item.description ? `<p>${escapeHtml(item.description)}</p>` : ''}${item.balance_after !== null && item.balance_after !== undefined ? `<div><span>موجودی پس از تراکنش</span><b>${escapeHtml(fmtPrice(item.balance_after))}</b></div>` : ''}${item.order_id ? `<div><span>کد پیگیری</span><span class="history-reference"><b dir="ltr">${escapeHtml(item.order_id)}</b><button type="button" data-history-copy="${escapeHtml(item.order_id)}" aria-label="کپی کد پیگیری">${icon('copy')}</button></span></div>` : ''}${!item.description && (item.balance_after === null || item.balance_after === undefined) && !item.order_id ? '<p>جزئیات بیشتری برای این تراکنش ثبت نشده است.</p>' : ''}</div></details>`;
}

export async function history(view) {
    let page = 1;
    let days = 30;
    let selectedFilter = '30';
    const limit = 10;
    let totalPages = 1;
    let loading = false;
    view.innerHTML = `<a href="#/account" class="page-back">${icon('chevronRight')} بازگشت به حساب</a><article class="history-page"><header class="history-hero"><span class="history-hero-icon">${icon('clock')}</span><div><span>گردش کیف پول</span><h1>تاریخچهٔ تراکنش‌ها</h1><p><span id="history-range">جزئیات تغییرات موجودی در ۳۰ روز گذشته</span></p></div></header><section class="history-filters" aria-label="بازهٔ زمانی تراکنش‌ها"><div class="history-filter-options"><button type="button" data-history-days="1" aria-pressed="false">۲۴ ساعت</button><button type="button" data-history-days="3" aria-pressed="false">۳ روز</button><button type="button" data-history-days="30" class="is-selected" aria-pressed="true">۳۰ روز</button><button type="button" id="history-custom-toggle" aria-expanded="false" aria-controls="history-custom-form">${icon('calendar')} دلخواه</button></div><form id="history-custom-form" hidden><label for="history-custom-days">تعداد روز گذشته</label><div><input type="text" inputmode="numeric" id="history-custom-days" autocomplete="off" placeholder="۱ تا ۳۶۵" aria-describedby="history-days-error" /><span>روز</span><button type="submit" id="history-apply-days">نمایش</button></div><p id="history-days-error" role="alert"></p></form></section><div class="history-list-heading"><h2>تراکنش‌های تو <span id="history-count"></span></h2><div class="history-legend"><span>افزایش</span><span>کاهش</span></div></div><div id="history-host" aria-busy="true">${skeletonList(4)}</div><nav class="history-pager hidden" id="history-pager" aria-label="صفحه‌بندی تراکنش‌ها"><button type="button" class="btn btn-ghost" id="history-prev">${icon('chevronRight')} قبلی</button><span id="history-pager-info" aria-live="polite"></span><button type="button" class="btn btn-ghost" id="history-next">بعدی ${icon('chevronLeft')}</button></nav></article>`;
    const host = view.querySelector('#history-host');
    const pager = view.querySelector('#history-pager');
    const info = view.querySelector('#history-pager-info');
    const prev = view.querySelector('#history-prev');
    const next = view.querySelector('#history-next');

    const filterButtons = [...view.querySelectorAll('[data-history-days]'), view.querySelector('#history-custom-toggle')];
    const customForm = view.querySelector('#history-custom-form');
    const customInput = view.querySelector('#history-custom-days');
    const filterError = view.querySelector('#history-days-error');
    function updateFilter() {
        filterButtons.forEach(button => {
            const active = (button.dataset.historyDays || 'custom') === selectedFilter;
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-pressed', String(active));
        });
        view.querySelector('#history-range').textContent = days === 1 ? 'جزئیات تغییرات موجودی در ۲۴ ساعت گذشته' : `جزئیات تغییرات موجودی در ${days.toLocaleString('fa-IR')} روز گذشته`;
    }
    filterButtons.forEach(button => button.addEventListener('click', () => {
        if (loading) return;
        filterError.textContent = '';
        if (!button.dataset.historyDays) {
            customForm.hidden = !customForm.hidden;
            button.setAttribute('aria-expanded', String(!customForm.hidden));
            if (!customForm.hidden) customInput.focus();
            return;
        }
        days = Number(button.dataset.historyDays);
        selectedFilter = button.dataset.historyDays;
        page = 1;
        customForm.hidden = true;
        view.querySelector('#history-custom-toggle').setAttribute('aria-expanded', 'false');
        updateFilter();
        load();
    }));
    customForm.addEventListener('submit', event => {
        event.preventDefault();
        if (loading) return;
        const raw = customInput.value.trim().replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))).replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
        const value = Number(raw);
        if (!/^\d+$/.test(raw) || !Number.isInteger(value) || value < 1 || value > 365) {
            filterError.textContent = 'تعداد روز را از ۱ تا ۳۶۵ وارد کن.';
            customInput.setAttribute('aria-invalid', 'true');
            return;
        }
        filterError.textContent = '';
        customInput.removeAttribute('aria-invalid');
        days = value;
        selectedFilter = 'custom';
        page = 1;
        updateFilter();
        load();
    });

    async function load() {
        if (loading) return;
        loading = true;
        filterButtons.forEach(button => { button.disabled = true; });
        view.querySelector('#history-apply-days').disabled = true;
        host.setAttribute('aria-busy', 'true');
        host.innerHTML = skeletonList(4);
        prev.disabled = next.disabled = true;
        try {
            const res = await call('transactions', { params: { page: String(page), limit: String(limit), days: String(days) } });
            const obj = res?.obj || {};
            const items = Array.isArray(obj.items) ? obj.items : [];
            totalPages = Math.max(1, Number(obj.total_pages) || 1);
            view.querySelector('#history-count').textContent = Number.isFinite(Number(obj.total)) && obj.total !== null ? Number(obj.total).toLocaleString('fa-IR') : '';
            if (!items.length) {
                host.innerHTML = `<div class="history-empty">${emptyState('هنوز تراکنشی ثبت نشده', `در ${days === 1 ? '۲۴ ساعت' : days.toLocaleString('fa-IR') + ' روز'} گذشته تراکنشی ثبت نشده است.`, 'clock')}</div>`;
                pager.classList.add('hidden');
                return;
            }
            let lastDate = '';
            host.innerHTML = items.map(item => {
                const { date } = dateParts(item.created_at);
                const heading = date !== lastDate ? `<div class="history-date">${icon('calendar')}<span>${escapeHtml(date)}</span><i></i></div>` : '';
                lastDate = date;
                return heading + rowHtml(item);
            }).join('');
            pager.classList.toggle('hidden', totalPages <= 1);
            info.textContent = `${page.toLocaleString('fa-IR')} از ${totalPages.toLocaleString('fa-IR')}`;
        } catch (err) {
            pager.classList.add('hidden');
            host.innerHTML = `<div class="history-empty history-error">${icon('alert')}<h3>تاریخچه دریافت نشد</h3><p>${escapeHtml(err?.message || 'دوباره تلاش کن.')}</p><button type="button" class="btn btn-primary" data-history-retry>تلاش دوباره</button></div>`;
        } finally {
            loading = false;
            filterButtons.forEach(button => { button.disabled = false; });
            view.querySelector('#history-apply-days').disabled = false;
            host.setAttribute('aria-busy', 'false');
            prev.disabled = page <= 1;
            next.disabled = page >= totalPages;
        }
    }
    host.addEventListener('click', async event => {
        const copy = event.target.closest('[data-history-copy]');
        if (copy) {
            const ok = await copyToClipboard(copy.dataset.historyCopy || '');
            toast(ok ? 'کد پیگیری کپی شد' : 'کپی انجام نشد', ok ? 'success' : 'error');
        }
        if (event.target.closest('[data-history-retry]')) load();
    });
    prev.addEventListener('click', () => { if (!loading && page > 1) { page--; load(); } });
    next.addEventListener('click', () => { if (!loading && page < totalPages) { page++; load(); } });
    await load();
}
