import { escapeHtml, fmtPrice, rialCopyValue } from './utils.js?v=0.0.53';
import { icon } from './icons.js?v=0.0.52';

export function bankDestinationHtml(number, holder, copyValue, label = 'کارت مقصد', numberId = '') {
    return `<div class="pay-bank-card"><div class="pay-bank-heading"><span>${icon('creditCard')} ${escapeHtml(label)}</span><span class="pay-bank-chip" aria-hidden="true"></span></div><div class="pay-bank-number-row"><b dir="ltr" ${numberId ? `id="${numberId}"` : ''} data-copy="${escapeHtml(copyValue)}">${escapeHtml(number)}</b><button type="button" class="pay-icon-copy copy-btn-cart" data-copy="${escapeHtml(copyValue)}" aria-label="کپی شماره کارت">${icon('copy')}</button></div><div class="pay-bank-holder"><span>به نام</span><strong>${escapeHtml(holder || '—')}</strong></div></div>`;
}

export function paymentAmountHtml(data) {
    return `<div class="pay-exact-amount"><span>${icon('wallet')} مبلغ دقیق واریز</span><strong>${escapeHtml(fmtPrice(Number(data.amount || 0)))}</strong><button type="button" class="btn btn-ghost copy-btn-amount" data-copy="${escapeHtml(rialCopyValue(data))}">${icon('copy')} <span>کپی مبلغ به ریال</span></button></div>`;
}

export function paymentResultHtml({ tone = 'pending', title, text = '', orderId = '', actions = [], iconName = '' }) {
    const states = { success: ['check', 'پرداخت تکمیل شد'], pending: ['hourglass', 'در انتظار تأیید'], receipt: ['send', 'رسید ثبت شد'], warning: ['alert', 'نیاز به پیگیری'], danger: ['xCircle', 'پرداخت تکمیل نشد'] };
    const state = states[tone] || states.pending;
    const links = actions.map((action, index) => action.recheck
        ? `<button type="button" class="btn ${index === 0 ? 'btn-primary' : 'btn-ghost'}" data-sbv-recheck>${escapeHtml(action.label)}</button>`
        : `<a href="${escapeHtml(action.href)}" class="btn ${index === 0 ? 'btn-primary' : 'btn-ghost'}">${escapeHtml(action.label)}</a>`).join('');
    return `<section class="payment-outcome" data-outcome="${states[tone] ? tone : 'pending'}" role="status"><div class="payment-result-symbol">${icon(iconName || state[0])}</div><span class="payment-result-kicker">${state[1]}</span><h2>${escapeHtml(title)}</h2>${text ? `<p class="payment-result-message">${escapeHtml(text)}</p>` : ''}${tone === 'receipt' ? '<div class="payment-receipt-status"><span></span>رسید دریافت شد<span></span>بررسی و تأیید پرداخت</div>' : ''}${orderId ? `<div class="payment-result-tracking"><span>کد پیگیری</span><b dir="ltr">${escapeHtml(orderId)}</b></div>` : ''}${links ? `<div class="payment-result-actions">${links}</div>` : ''}</section>`;
}
