import { call, ApiError } from '../api.js?v=0.0.52';
import { escapeHtml, fmtPrice, toast, wireAmountInput, amountValue } from '../utils.js?v=0.0.52';
import { getUser, setUser } from '../state.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';

export async function transfer(view) {
    const user = getUser();

    view.innerHTML = `
        <a href="#/account" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/transfer</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('transfer')} انتقال موجودی</p>
                <h2 class="section-headline">انتقال موجودی به کاربر دیگر</h2>

                <div class="kv mt-sm">
                    <span class="kv-label">${icon('wallet')} موجودی کیف پول</span>
                    <span class="kv-value mono accent">${escapeHtml(fmtPrice(user?.balance ?? 0))} تومان</span>
                </div>

                <div id="transfer-form-host" class="mt-md">
                    <div class="form-row">
                        <label class="muted" style="font-size:12px">آیدی عددی تلگرام گیرنده</label>
                        <input id="transfer-recipient" type="text" inputmode="numeric" placeholder="مثلا 123456789" />
                    </div>
                    <div class="form-row mt-sm">
                        <label class="muted" style="font-size:12px">مبلغ (تومان)</label>
                        <input id="transfer-amount" type="text" inputmode="numeric" placeholder="مبلغ را وارد کنید" />
                    </div>
                    <button id="transfer-submit" type="button" class="btn btn-primary btn-block mt-md">ادامه</button>
                </div>

                <div id="transfer-confirm-host" class="mt-md hidden">
                    <div class="kv">
                        <span class="kv-label">${icon('user')} گیرنده</span>
                        <span class="kv-value mono" id="confirm-recipient"></span>
                    </div>
                    <div class="kv">
                        <span class="kv-label">${icon('coin')} مبلغ</span>
                        <span class="kv-value mono accent" id="confirm-amount"></span>
                    </div>
                    <div style="display:flex;gap:10px" class="mt-md">
                        <button id="confirm-cancel" type="button" class="btn btn-secondary" style="flex:1">انصراف</button>
                        <button id="confirm-submit" type="button" class="btn btn-primary" style="flex:1">تایید و انتقال</button>
                    </div>
                </div>
            </div>
        </article>
    `;

    const $formHost = view.querySelector('#transfer-form-host');
    const $recipient = view.querySelector('#transfer-recipient');
    const $amount = view.querySelector('#transfer-amount');
    const $submit = view.querySelector('#transfer-submit');
    const $confirmHost = view.querySelector('#transfer-confirm-host');
    const $confirmRecipient = view.querySelector('#confirm-recipient');
    const $confirmAmount = view.querySelector('#confirm-amount');
    const $confirmSubmit = view.querySelector('#confirm-submit');
    const $confirmCancel = view.querySelector('#confirm-cancel');

    wireAmountInput($amount);

    let pendingRecipientId = '';
    let pendingAmount = 0;

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

        $submit.disabled = true;
        try {
            const res = await call('wallet_transfer_quote', {
                method: 'POST',
                body: { recipient_id: recipientId, amount },
            });
            const obj = res?.obj || {};
            if (amount < Number(obj.min_amount || 0)) {
                toast(`حداقل مبلغ انتقال ${fmtPrice(obj.min_amount)} تومان است`, 'error');
                return;
            }
            pendingRecipientId = recipientId;
            pendingAmount = amount;
            $confirmRecipient.textContent = obj.recipient_username || recipientId;
            $confirmAmount.textContent = `${fmtPrice(amount)} تومان`;
            $formHost.classList.add('hidden');
            $confirmHost.classList.remove('hidden');
        } catch (err) {
            const msg = err instanceof ApiError ? err.message : 'خطا در استعلام گیرنده';
            toast(msg, 'error');
        } finally {
            $submit.disabled = false;
        }
    });

    $confirmCancel.addEventListener('click', () => {
        $confirmHost.classList.add('hidden');
        $formHost.classList.remove('hidden');
    });

    $confirmSubmit.addEventListener('click', async () => {
        $confirmSubmit.disabled = true;
        try {
            const res = await call('wallet_transfer_confirm', {
                method: 'POST',
                body: { recipient_id: pendingRecipientId, amount: pendingAmount },
            });
            const obj = res?.obj || {};
            if (user && obj.new_balance !== undefined) {
                setUser({ ...user, balance: obj.new_balance });
            }
            toast('انتقال موجودی با موفقیت انجام شد', 'success');
            window.location.hash = '#/account';
        } catch (err) {
            const msg = err instanceof ApiError ? err.message : 'خطا در انجام انتقال';
            toast(msg, 'error');
            $confirmSubmit.disabled = false;
        }
    });
}
