import { call, ApiError } from '../api.js?v=0.0.53';
import { escapeHtml, fmtPrice, toast, wireAmountInput, amountValue } from '../utils.js?v=0.0.52';
import { getUser, setUser } from '../state.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

export async function transfer(view) {
    const user = getUser();

    view.innerHTML = `
        <a href="#/account" class="page-back">${icon('chevronRight')} بازگشت به حساب</a>
        <article class="transfer-page">
            <header class="transfer-hero"><span class="transfer-hero-icon">${icon('transfer')}</span><div><span>از کیف پول تو</span><h1>انتقال موجودی</h1><p>موجودی کیف پولت را به کاربر دیگری منتقل کن.</p></div></header>
            <div class="transfer-balance"><span>${icon('wallet')} موجودی قابل انتقال</span><strong>${escapeHtml(fmtPrice(user?.balance ?? 0))}</strong></div>
            <div id="transfer-form-host" class="transfer-form">
                <div class="transfer-field"><label for="transfer-recipient">شناسهٔ عددی تلگرام گیرنده</label><div>${icon('user')}<input id="transfer-recipient" type="text" inputmode="numeric" autocomplete="off" placeholder="مثلاً 123456789" dir="ltr" /></div><small>شناسهٔ عددی را وارد کن؛ نام کاربری کافی نیست.</small></div>
                <div class="transfer-field"><label for="transfer-amount">مبلغ انتقال</label><div>${icon('coin')}<input id="transfer-amount" type="text" inputmode="numeric" autocomplete="off" placeholder="مبلغ دلخواه" /><span>تومان</span></div></div>
                <button id="transfer-submit" type="button" class="btn btn-primary btn-block">بررسی گیرنده و ادامه ${icon('arrowLeft')}</button>
            </div>
            <section id="transfer-confirm-host" class="transfer-confirm hidden" aria-label="تأیید انتقال">
                <div class="transfer-confirm-heading"><span>${icon('user')}</span><div><small>گیرندهٔ انتقال</small><strong id="confirm-recipient"></strong><span id="confirm-recipient-id" dir="ltr"></span></div>${icon('checkCircle')}</div>
                <div class="transfer-confirm-amount"><span>مبلغ انتقال</span><strong id="confirm-amount"></strong></div><p class="transfer-confirm-hint">نام گیرنده و مبلغ را بررسی کن؛ با تأیید، مبلغ از کیف پولت منتقل می‌شود.</p>
                <div class="transfer-confirm-actions"><button id="confirm-submit" type="button" class="btn btn-primary">${icon('transfer')} تأیید و انتقال</button><button id="confirm-cancel" type="button" class="btn btn-ghost">ویرایش</button></div>
            </section>
            <div id="transfer-feedback" aria-live="polite"></div>
        </article>`;

    const $formHost = view.querySelector('#transfer-form-host');
    const $recipient = view.querySelector('#transfer-recipient');
    const $amount = view.querySelector('#transfer-amount');
    const $submit = view.querySelector('#transfer-submit');
    const $confirmHost = view.querySelector('#transfer-confirm-host');
    const $confirmRecipient = view.querySelector('#confirm-recipient');
    const $confirmAmount = view.querySelector('#confirm-amount');
    const $confirmSubmit = view.querySelector('#confirm-submit');
    const $confirmCancel = view.querySelector('#confirm-cancel');

    const $feedback = view.querySelector('#transfer-feedback');
    wireAmountInput($amount);

    let pendingRecipientId = '';
    let pendingAmount = 0;
    let pendingOperationId = '';

    function newOperationId() {
        try {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
        } catch (e) {  }
        return 'op' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }

    $submit.addEventListener('click', async () => {
        const recipientId = $recipient.value.trim();
        const amount = parseInt(amountValue($amount) || '0', 10);

        if (!/^\d+$/.test(recipientId)) {
            toast('آیدی عددی نامعتبر است', 'error');
            return;
        }
        if (!Number.isFinite(amount) || amount <= 0) {
            toast('مبلغ نامعتبر است', 'error');
            return;
        }

        $feedback.innerHTML = '';
        $submit.disabled = true;
        $submit.textContent = 'در حال بررسی گیرنده…';
        try {
            const res = await call('wallet_transfer_quote', {
                method: 'POST',
                body: { recipient_id: recipientId, amount },
            });
            const obj = res?.obj || {};
            if (amount < Number(obj.min_amount || 0)) {
                toast(`حداقل مبلغ انتقال ${fmtPrice(obj.min_amount)} است`, 'error');
                return;
            }
            pendingRecipientId = recipientId;
            pendingAmount = amount;
            pendingOperationId = newOperationId();
            $confirmRecipient.textContent = obj.recipient_username || recipientId;
            $confirmAmount.textContent = fmtPrice(amount);
            view.querySelector('#confirm-recipient-id').textContent = recipientId;
            $formHost.classList.add('hidden');
            $confirmHost.classList.remove('hidden');
        } catch (err) {
            const msg = err instanceof ApiError ? err.message : 'خطا در استعلام گیرنده';
            $feedback.innerHTML = `<div class="transfer-error" role="alert">${icon('alert')}<div><b>گیرنده بررسی نشد</b><p>${escapeHtml(msg)}</p></div></div>`;
        } finally {
            $submit.disabled = false;
            $submit.innerHTML = `بررسی گیرنده و ادامه ${icon('arrowLeft')}`;
        }
    });

    $confirmCancel.addEventListener('click', () => {
        $feedback.innerHTML = '';
        $confirmHost.classList.add('hidden');
        $formHost.classList.remove('hidden');
    });

    $confirmSubmit.addEventListener('click', async () => {
        if ($confirmSubmit.disabled) return;
        $confirmSubmit.disabled = true;
        $confirmCancel.disabled = true;
        $feedback.innerHTML = '';
        $confirmSubmit.textContent = 'در حال انتقال…';
        try {
            const res = await call('wallet_transfer_confirm', {
                method: 'POST',
                body: { recipient_id: pendingRecipientId, amount: pendingAmount, operation_id: pendingOperationId },
            });
            const obj = res?.obj || {};
            if (user && obj.new_balance !== undefined) {
                setUser({ ...user, balance: obj.new_balance });
            }
            view.querySelector('.transfer-page').innerHTML = `<section class="payment-outcome transfer-success" data-outcome="success" role="status"><div class="payment-result-symbol">${icon('check')}</div><span class="payment-result-kicker">انتقال تکمیل شد</span><h2>موجودی با موفقیت منتقل شد</h2><p class="payment-result-message">${escapeHtml(fmtPrice(pendingAmount))} به ${escapeHtml($confirmRecipient.textContent || pendingRecipientId)} منتقل شد.</p>${obj.new_balance !== undefined ? `<div class="payment-result-tracking"><span>موجودی جدید کیف پول</span><b>${escapeHtml(fmtPrice(obj.new_balance))}</b></div>` : ''}<div class="payment-result-tracking"><span>شناسهٔ انتقال</span><b dir="ltr">${escapeHtml(obj.operation_id || pendingOperationId)}</b></div><div class="payment-result-actions"><a href="#/account" class="btn btn-primary">بازگشت به حساب</a><button type="button" class="btn btn-ghost" id="transfer-again">انتقال جدید</button></div></section>`;
            view.querySelector('#transfer-again').addEventListener('click', () => transfer(view));
        } catch (err) {
            const msg = err instanceof ApiError ? err.message : 'خطا در انجام انتقال';
            $feedback.innerHTML = `<div class="transfer-error" role="alert">${icon('alert')}<div><b>${err instanceof ApiError ? 'انتقال انجام نشد' : 'نتیجهٔ انتقال دریافت نشد'}</b><p>${escapeHtml(err instanceof ApiError ? msg : 'برای پیگیری همین درخواست، دوباره دکمهٔ تأیید را بزن.')}</p></div></div>`;
            $confirmSubmit.disabled = false;
            $confirmCancel.disabled = false;
            $confirmSubmit.innerHTML = `${icon('refresh')} بررسی و تلاش مجدد`;
        }
    });
}
