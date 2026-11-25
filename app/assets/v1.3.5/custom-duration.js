import { escapeHtml, fmtNumber } from './utils.js?v=0.0.52';

export function durationCardsHtml(tariffs, selected) {
    const options = Object.entries(tariffs || {}).filter(([days, price]) => Number.isInteger(Number(days)) && Number(days) > 0 && price !== null && Number.isFinite(Number(price)) && Number(price) >= 0);
    return `<div class="custom-duration-options" role="radiogroup" aria-label="مدت سرویس">
        ${options.map(([days, price]) => `<button type="button" class="custom-duration-option${Number(days) === Number(selected) ? ' is-selected' : ''}" role="radio" aria-checked="${Number(days) === Number(selected)}" tabindex="${Number(days) === Number(selected) ? '0' : '-1'}" data-duration="${Number(days)}">
            <span class="custom-duration-heading"><strong>${escapeHtml(fmtNumber(days))} روز</strong><span class="custom-duration-check" aria-hidden="true">✓</span></span>
            <span class="custom-duration-tariff"><b>${escapeHtml(fmtNumber(price))}</b> تومان برای هر گیگابایت</span>
        </button>`).join('')}
    </div>`;
}

export function bindDurationCards(root, onChange) {
    const cards = Array.from(root.querySelectorAll('[data-duration]'));
    const select = (card) => {
        if (card.getAttribute('aria-checked') === 'true') return;
        cards.forEach((option) => {
            const active = option === card;
            option.classList.toggle('is-selected', active);
            option.setAttribute('aria-checked', String(active));
            option.tabIndex = active ? 0 : -1;
        });
        onChange(Number(card.dataset.duration));
    };
    cards.forEach((card, index) => {
        card.addEventListener('click', () => select(card));
        card.addEventListener('keydown', (event) => {
            let next;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') next = (index + 1) % cards.length;
            if (event.key === 'ArrowRight' || event.key === 'ArrowUp') next = (index - 1 + cards.length) % cards.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = cards.length - 1;
            if (next === undefined) return;
            event.preventDefault();
            cards[next].focus();
            select(cards[next]);
        });
    });
}

export function customPriceSummaryHtml() {
    return `<div class="custom-price-summary mt-md" data-price-summary aria-busy="true">
        <div class="kv"><span class="kv-label">حجم انتخاب‌شده</span><span class="kv-value" data-price-volume>—</span></div>
        <div class="kv"><span class="kv-label">مدت انتخاب‌شده</span><span class="kv-value" data-price-days>—</span></div>
        <div class="kv"><span class="kv-label">قیمت هر گیگابایت</span><span class="kv-value" data-price-tariff>—</span></div>
        <div class="kv custom-price-total"><span class="kv-label">مبلغ کل</span><strong class="kv-value" data-price-total aria-live="polite">—</strong></div>
        <p class="custom-price-status" data-price-status role="status" aria-live="polite"></p>
    </div>`;
}

export function updateCustomPriceSummary(root, { volume, days, tariff = null, total = null, state = 'loading', message = '' }) {
    root.setAttribute('aria-busy', String(state === 'loading'));
    root.dataset.state = state;
    root.querySelector('[data-price-volume]').textContent = Number.isFinite(volume) ? `${fmtNumber(volume)} گیگابایت` : '—';
    root.querySelector('[data-price-days]').textContent = Number.isFinite(days) ? `${fmtNumber(days)} روز` : '—';
    root.querySelector('[data-price-tariff]').textContent = tariff !== null ? `${fmtNumber(tariff)} تومان` : '—';
    root.querySelector('[data-price-total]').textContent = total !== null ? `${fmtNumber(total)} تومان` : '—';
    root.querySelector('[data-price-status]').textContent = message || (state === 'loading' ? 'در حال دریافت قیمت…' : '');
}
