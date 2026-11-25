import { call } from '../api.js?v=0.0.53';
import { escapeHtml, toast, copyToClipboard } from '../utils.js?v=0.0.53';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, hapticNotify } from '../telegram.js?v=0.0.52';
import { getToken } from '../state.js';

const POLL_INTERVAL_MS = 5000;
const SERVICE_READY_POLL_MS = 3000;
const SERVICE_READY_MAX_TRIES = 40;
const MAX_RECEIPT_BYTES = 10 * 1024 * 1024;
const OPEN_PHASES = ['awaiting_payment', 'receipt_available'];
const LESS_PHASES = ['action_required_less'];
const MORE_PHASES = ['action_required_more'];
const REVIEW_PHASES = ['receipt_submitted', 'receipt_rejected'];
const FAIL_PHASES = ['rejected', 'canceled', 'expired', 'review_timeout'];
const ACTION_BUTTON_STYLES = {
    primary: { cls: 'btn btn-primary', style: '' },
    success: { cls: 'btn', style: 'background:var(--green);color:#fff;border-color:var(--green)' },
    danger: { cls: 'btn', style: 'background:var(--red);color:#fff;border-color:var(--red)' },
    default: { cls: 'btn btn-ghost', style: '' },
};

function actionButton(id, styleKey, v, content, extraAttrs = '') {
    const configured = (v && v.button_styles ? v.button_styles[styleKey] : '') || 'default';
    const primary = styleKey === 'send_receipt' || (styleKey === 'check_status' && !v?.can_submit_receipt);
    const picked = ACTION_BUTTON_STYLES[configured === 'default' && primary ? 'primary' : configured] || ACTION_BUTTON_STYLES.default;
    return `<button type="button" class="${picked.cls} tpc-action tpc-action-${styleKey}" id="${id}" style="flex:1;${picked.style}" ${extraAttrs}>${content}</button>`;
}

function actionRow(buttons) {
    const filtered = buttons.filter(Boolean);
    if (!filtered.length) return '';
    return `<div class="tpc-action-row">${filtered.join('')}</div>`;
}

function pad2(n) { return n < 10 ? '0' + n : String(n); }

function fmtClock(totalSec) {
    const s = Math.max(0, Math.floor(totalSec));
    const hours = Math.floor(s / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const secs = s % 60;
    return hours > 0 ? pad2(hours) + ':' + pad2(minutes) + ':' + pad2(secs) : pad2(minutes) + ':' + pad2(secs);
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('en-US');
}

function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

function waitMessage(totalSec) {
    const s = Math.max(1, Math.ceil(totalSec));
    const minutes = Math.floor(s / 60);
    const rest = s % 60;
    let label;
    if (minutes > 0 && rest > 0) label = `${minutes} دقیقه و ${rest} ثانیه`;
    else if (minutes > 0) label = `${minutes} دقیقه`;
    else label = `${rest} ثانیه`;
    return `${faDigits(label)} دیگر می‌توانید رسید پرداخت را ارسال کنید.`;
}

function failTitle(phase) {
    if (phase === 'rejected') return 'رسید پرداخت رد شد';
    if (phase === 'receipt_rejected') return 'رسید در بررسی اولیه رد شد';
    if (phase === 'canceled') return 'فاکتور لغو شد';
    if (phase === 'review_timeout') return 'مهلت بررسی به پایان رسید';
    return 'فاکتور منقضی شد';
}

export function startTonpayCustom(view, orderId, opts = {}) {
    const { mode = 'recharge', onSuccess, onFail, onCancel, initialView = null } = opts;

    if (typeof window !== 'undefined' && typeof window.__faoximaTonpayCustomCleanup === 'function') {
        try { window.__faoximaTonpayCustomCleanup(); } catch (_) {}
    }

    let data = initialView;
    let offsetSec = 0;
    let signature = '';
    let pollTimer = null;
    let tickTimer = null;
    let stopped = false;
    let polling = false;
    let busy = false;
    let finishing = false;
    let refreshedAtZero = false;

    function serverNow() {
        return Math.floor(Date.now() / 1000) + offsetSec;
    }

    function remaining(target) {
        return Math.max(0, Number(target || 0) - serverNow());
    }

    function cleanup() {
        if (stopped) return;
        stopped = true;
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
        document.removeEventListener('visibilitychange', onVisibility);
        if (typeof window !== 'undefined' && window.__faoximaTonpayCustomCleanup === cleanup) {
            window.__faoximaTonpayCustomCleanup = null;
        }
    }

    function frame(inner) {
        return `<article class="tpc-page"><div class="tpc-body">${inner}</div></article>`;
    }

    function amountRows(v) {
        const isLess = LESS_PHASES.includes(v.phase);
        const isMore = MORE_PHASES.includes(v.phase);
        const amount = isLess ? v.remaining_payable_rial : isMore ? v.refund_amount : v.payable_rial;
        const title = isLess ? 'مبلغ باقی‌ماندهٔ قابل پرداخت' : isMore ? 'مبلغ مازاد قابل عودت' : 'مبلغ دقیق قابل پرداخت';
        const copy = OPEN_PHASES.includes(v.phase) || isLess;
        return `<section class="tpc-amount-card"><span class="pay-eyebrow">${escapeHtml(title)}</span><strong>${fmtNum(amount)} <small>${isMore ? 'تومان' : 'ریال'}</small></strong><div class="tpc-request-amount"><span>مبلغ ${mode === 'direct_buy' ? 'خرید' : 'شارژ'}</span><b>${fmtNum(v.request_amount)} تومان</b></div>${isLess ? `<p class="muted">معادل ${fmtNum(v.remaining_amount)} تومان</p>` : ''}${copy ? actionButton('tpc-copy-amount', 'copy_amount', v, `${icon('copy')}<span>کپی مبلغ به ریال</span>`) : ''}</section>`;
    }

    function cardBlock(v) {
        const isLess = LESS_PHASES.includes(v.phase);
        if (!OPEN_PHASES.includes(v.phase) && !isLess) return '';
        const cardNumber = isLess ? (v.remaining_card_number || '') : (v.card_number || '');
        const complete = isLess ? !!v.remaining_card_complete : !!v.card_complete;
        const holder = isLess ? (v.remaining_card_holder || '') : (v.card_name || '');
        const number = complete ? String(isLess ? (v.remaining_card_number_display || cardNumber) : (v.card_number_display || cardNumber)) : String(cardNumber);
        return `<section class="pay-bank-card"><div class="pay-bank-heading"><span>${icon('creditCard')} ${isLess ? 'کارت مقصد مبلغ باقی‌مانده' : 'کارت مقصد'}</span><span class="pay-bank-chip" aria-hidden="true"></span></div><div class="pay-bank-number-row"><b dir="ltr" id="tpc-card-number">${escapeHtml(number || '—')}</b>${complete ? actionButton('tpc-copy-card', 'copy_card', v, icon('copy'), 'aria-label="کپی شماره کارت"') : ''}</div><div class="pay-bank-holder"><span>به نام</span><strong>${escapeHtml(holder || '—')}</strong></div>${!complete ? '<p class="tpc-incomplete-card">شماره کارت کامل نیست؛ قبل از واریز با پشتیبانی تماس بگیر.</p>' : ''}</section>`;
    }

    function refundBlock(v) {
        if (!MORE_PHASES.includes(v.phase)) return '';
        if (v.refund_card_submitted) {
            return `
            <div class="card-section mt-md">
                <p class="section-title">${icon('creditCard')} کارت عودت مبلغ مازاد</p>
                <p class="muted" style="font-size:12px;white-space:pre-line">کارت مقصد عودت قبلاً ثبت شده است و مبلغ مازاد پس از بررسی تون‌پی واریز می‌شود.</p>
            </div>`;
        }
        if (!v.can_refund) return '';
        return `
            <div class="card-section mt-md">
                <p class="section-title">${icon('creditCard')} ثبت کارت عودت مبلغ مازاد</p>
                <p class="muted" style="font-size:12px;white-space:pre-line">مبلغ مازاد پس از بررسی تون‌پی به کارت زیر واریز می‌شود. ثبت کارت به‌معنی واریز فوری نیست.</p>
                <input type="text" id="tpc-refund-card" inputmode="numeric" autocomplete="off" placeholder="شماره کارت ۱۶ رقمی" dir="ltr" class="mono" style="width:100%;margin-top:8px;padding:10px;border-radius:10px;border:1px solid var(--border);background:transparent;color:inherit">
                <input type="text" id="tpc-refund-holder" autocomplete="off" placeholder="نام و نام خانوادگی صاحب کارت" style="width:100%;margin-top:8px;padding:10px;border-radius:10px;border:1px solid var(--border);background:transparent;color:inherit">
                <button type="button" class="btn btn-primary mt-sm" id="tpc-refund-submit" style="width:100%">ثبت کارت عودت</button>
            </div>`;
    }

    function actionsBlock(v) {
        const open = OPEN_PHASES.includes(v.phase);
        const changeLabel = v.change_card_exhausted
            ? 'کارت دیگری باقی نمانده'
            : (v.show_change_card === 0 && !v.can_change_card ? 'تعویض کارت در دسترس نیست' : (v.can_change_card ? 'تعویض کارت' : `تعویض کارت (${v.change_card_wait} ثانیه)`));
        const rows = [];
        rows.push(actionRow([
            actionButton('tpc-refresh', 'check_status', v, `${icon('refresh')}<span>بررسی پرداخت</span>`),
        ]));
        if (open) rows.push(actionRow([
            actionButton('tpc-change-card', 'change_card', v, `${icon('refresh')}<span id="tpc-change-label">${escapeHtml(changeLabel)}</span>`, v.can_change_card ? '' : 'disabled'),
        ]));
        if (v.can_cancel) rows.push(actionRow([
            actionButton('tpc-cancel', 'cancel_transaction', v, `${icon('xCircle')}<span>لغو تراکنش</span>`),
        ]));
        return `<div class="tpc-actions">${rows.join('')}</div>`;
    }

    function timerBlock(v) {
        const waiting = v.phase === 'awaiting_payment';
        const reviewing = REVIEW_PHASES.includes(v.phase) && v.review_deadline_at > 0;
        if (!waiting && !reviewing) return '';
        const target = waiting ? v.receipt_available_at : v.review_deadline_at;
        return `<div class="tpc-timer-card">${icon('hourglass')}<div><b>${waiting ? 'بررسی خودکار پرداخت فعال است' : 'رسید در حال بررسی است'}</b><span>${waiting ? 'تا فعال‌شدن ارسال رسید' : 'تا پایان مهلت بررسی'}</span></div><strong id="tpc-timer" dir="ltr" data-target="${target}" data-kind="${waiting ? 'wait' : 'review'}">${fmtClock(remaining(target))}</strong></div>`;
    }

    function expiryTimerBlock(v) {
        if (!(Number(v.expires_at) > 0) || v.terminal || REVIEW_PHASES.includes(v.phase)) return '';
        return `<div class="tpc-timer-card tpc-expiry-card">${icon('clock')}<div><b>مهلت تراکنش</b><span>تا منقضی‌شدن تراکنش</span></div><strong id="tpc-expiry-timer" dir="ltr" data-target="${Number(v.expires_at)}">${fmtClock(remaining(v.expires_at))}</strong></div>`;
    }

    function receiptBlock(v) {
        if (!OPEN_PHASES.includes(v.phase) && !LESS_PHASES.includes(v.phase)) return '';
        if (LESS_PHASES.includes(v.phase) && v.remaining_receipt_received) {
            return `
            <div class="card-section mt-md">
                <p class="muted mt-sm" style="font-size:12px;text-align:center">رسید مبلغ باقی‌مانده ارسال شده و در انتظار بررسی تون‌پی است.</p>
            </div>`;
        }
        return `
            <div class="${v.can_submit_receipt ? 'tpc-receipt-ready' : 'tpc-receipt-help'}">
                <input type="file" id="tpc-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                ${v.can_submit_receipt ? `<h3>${icon('camera')} ارسال رسید فعال است</h3><p>اگر واریز کرده‌ای و هنوز تأیید نشده، تصویر رسید را ارسال کن.</p>${actionButton('tpc-receipt', 'send_receipt', v, `${icon('download')}<span id="tpc-receipt-label">${LESS_PHASES.includes(v.phase) ? 'انتخاب رسید مبلغ باقی‌مانده' : 'انتخاب و ارسال رسید'}</span>`)}` : ''}
                <p class="muted mt-sm" style="font-size:12px;text-align:center">تصویر رسید (JPG، PNG، WEBP یا GIF) تا ۱۰ مگابایت</p>
            </div>`;
    }

    function statusIcon(v) {
        if (v.phase === 'receipt_submitted' || v.phase === 'confirming') return icon('hourglass', 'class="ico ico-xxl ico-accent"');
        if (v.phase === 'action_required_less' || v.phase === 'action_required_more' || v.phase === 'receipt_rejected' || v.phase === 'unknown') return icon('alert', 'class="ico ico-xxl ico-warn"');
        return icon('creditCard', 'class="ico ico-xxl ico-accent"');
    }

    function render() {
        if (stopped || !data) return;
        const v = data;
        view.innerHTML = frame(`
            <div class="tpc-heading">
                <span class="tpc-heading-icon">${statusIcon(v)}</span>
                <h2 style="margin:8px 0 4px;font-size:18px;overflow-wrap:anywhere">${escapeHtml(v.gateway_title || '')}</h2>
                <p class="muted" style="font-size:13px;white-space:pre-line" id="tpc-status-text">${escapeHtml((v.messages && v.messages.status) || '')}</p>
            </div>
            ${expiryTimerBlock(v)}
            ${amountRows(v)}
            ${cardBlock(v)}
            ${OPEN_PHASES.includes(v.phase) ? `<div class="tpc-notices">${v.messages?.amount_warning ? `<p id="tpc-amount-warning">${icon('alert')}<span>${escapeHtml(v.messages.amount_warning)}</span></p>` : ''}${v.messages?.card_only_warning ? `<p id="tpc-card-only-warning">${icon('info')}<span>${escapeHtml(v.messages.card_only_warning)}</span></p>` : ''}</div>` : ''}
            ${refundBlock(v)}
            ${timerBlock(v)}
            ${receiptBlock(v)}
            ${actionsBlock(v)}
            <p class="tpc-tracking">شناسه فاکتور: <span class="mono gold" dir="ltr">${escapeHtml(v.invoice_id || '')}</span></p>
            <p class="tpc-tracking">کد پیگیری: <span class="mono gold">${escapeHtml(orderId)}</span></p>`);
        bind();
    }

    function bind() {
        const $ = (id) => view.querySelector(id);

        const $copyAmount = $('#tpc-copy-amount');
        if ($copyAmount) $copyAmount.addEventListener('click', async () => {
            hapticImpact('light');
            const isLess = LESS_PHASES.includes(data.phase);
            const ok = await copyToClipboard(String(isLess ? (data.remaining_amount_copy_value || '') : (data.amount_copy_value || '')));
            toast(ok ? 'مبلغ پرداخت کپی شد' : 'کپی انجام نشد', ok ? 'success' : 'error', 2000);
        });

        const $copyCard = $('#tpc-copy-card');
        if ($copyCard) $copyCard.addEventListener('click', async () => {
            hapticImpact('light');
            const isLess = LESS_PHASES.includes(data.phase);
            const ok = await copyToClipboard(String(isLess ? (data.remaining_card_copy_value || '') : (data.card_copy_value || '')));
            toast(ok ? 'شماره کارت کپی شد' : 'کپی انجام نشد', ok ? 'success' : 'error', 2000);
        });

        const $change = $('#tpc-change-card');
        if ($change) $change.addEventListener('click', async () => {
            if (busy || !data.can_change_card) return;
            busy = true;
            hapticImpact('light');
            $change.disabled = true;
            try {
                const res = await call('tonpay_custom_change_card', { method: 'POST', body: { order_id: orderId } });
                if (res && res.obj && res.obj.view) applyView(res.obj.view, true);
                toast('کارت تعویض شد', 'success', 2500);
            } catch (err) {
                toast(err.message || 'تعویض کارت انجام نشد', 'error', 4000);
                await poll();
            } finally {
                busy = false;
            }
        });

        const $refresh = $('#tpc-refresh');
        if ($refresh) $refresh.addEventListener('click', async () => {
            hapticImpact('light');
            $refresh.disabled = true;
            await poll();
            const $again = $('#tpc-refresh');
            if ($again) $again.disabled = false;
        });

        const $cancel = $('#tpc-cancel');
        if ($cancel) $cancel.addEventListener('click', async () => {
            if (busy) return;
            if (!window.confirm('آیا از لغو این تراکنش اطمینان دارید؟ لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست؛ اگر مبلغ را واریز کرده‌اید و تون‌پی آن را تأیید کند، پرداخت شما همچنان ثبت می‌شود.')) return;
            busy = true;
            $cancel.disabled = true;
            try {
                await call('tonpay_custom_cancel', { method: 'POST', body: { order_id: orderId } });
                cleanup();
                if (typeof onCancel === 'function') {
                    onCancel();
                } else {
                    window.location.hash = '#/';
                }
            } catch (err) {
                toast(err.message || 'لغو فاکتور انجام نشد', 'error', 4000);
                busy = false;
                await poll();
                const $againCancel = $('#tpc-cancel');
                if ($againCancel) $againCancel.disabled = false;
            }
        });

        const $file = $('#tpc-file');
        const $receipt = $('#tpc-receipt');
        if ($file && $receipt) {
            $receipt.addEventListener('click', () => {
                if (busy) return;
                hapticImpact('light');
                if (!data.can_submit_receipt) {
                    toast(waitMessage(remaining(data.receipt_available_at)), 'warn', 4000);
                    return;
                }
                $file.value = '';
                $file.click();
            });
            $file.addEventListener('change', () => {
                const f = $file.files && $file.files[0];
                if (!f) return;
                if (!/^image\/(jpeg|png|webp|gif)$/.test(f.type)) {
                    toast('فقط تصویر JPG، PNG، WEBP یا GIF مجاز است', 'error', 3500);
                    return;
                }
                if (f.size > MAX_RECEIPT_BYTES) {
                    toast('حجم تصویر نباید بیشتر از ۱۰ مگابایت باشد', 'error', 3500);
                    return;
                }
                submitReceipt(f);
            });
        }

        const $refundSubmit = $('#tpc-refund-submit');
        if ($refundSubmit) $refundSubmit.addEventListener('click', async () => {
            if (busy) return;
            const cardNumber = String(($('#tpc-refund-card') || {}).value || '').replace(/[\s-]/g, '');
            const cardHolder = String(($('#tpc-refund-holder') || {}).value || '').trim();
            if (!/^\d{16}$/.test(cardNumber)) {
                toast('شماره کارت باید ۱۶ رقم باشد', 'error', 3500);
                return;
            }
            if (cardHolder.length < 3) {
                toast('نام صاحب کارت را کامل وارد کنید', 'error', 3500);
                return;
            }
            busy = true;
            hapticImpact('medium');
            $refundSubmit.disabled = true;
            try {
                const res = await call('tonpay_custom_refund', { method: 'POST', body: { order_id: orderId, card_number: cardNumber, card_holder: cardHolder } });
                toast((res && res.obj && res.obj.message) || 'کارت عودت ثبت شد', 'success', 5000);
                if (res && res.obj && res.obj.view) applyView(res.obj.view, true);
                else await poll();
            } catch (err) {
                toast(err.message || 'ثبت کارت عودت انجام نشد', 'error', 5000);
                await poll();
            } finally {
                busy = false;
                const $again = $('#tpc-refund-submit');
                if ($again) $again.disabled = false;
            }
        });
    }

    async function submitReceipt(file) {
        if (busy || !file) return;
        busy = true;
        hapticImpact('medium');
        const $button = view.querySelector('#tpc-receipt');
        const $label = view.querySelector('#tpc-receipt-label');
        const oldLabel = $label ? $label.textContent : '';
        if ($button) $button.disabled = true;
        if ($label) $label.textContent = 'در حال ارسال…';
        try {
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('file', file, file.name);
            const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
            const res = await fetch(`${apiUrl}/miniapp.php?actions=tonpay_custom_receipt`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + (getToken() || ''), 'Accept': 'application/json' },
                body: fd,
            });
            let envelope = null;
            try { envelope = await res.json(); } catch (_) {}
            if (!res.ok || !envelope || !envelope.status) {
                throw new Error((envelope && envelope.msg) || `ارسال ناموفق بود (${res.status})`);
            }
            hapticNotify('success');
            toast((envelope.obj && envelope.obj.message) || 'رسید با موفقیت ارسال شد', 'success', 4500);
            if (envelope.obj && envelope.obj.view) applyView(envelope.obj.view, true);
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'ارسال رسید ناموفق بود', 'error', 5000);
            if ($button) $button.disabled = false;
            if ($label) $label.textContent = oldLabel;
            await poll();
        } finally {
            busy = false;
        }
    }

    function signatureOf(v) {
        return [v.phase, v.expires_at, v.receipt_available_at, v.review_deadline_at, v.card_number, v.payable_rial, v.can_change_card, v.change_card_wait, v.change_card_exhausted, v.show_change_card, v.can_submit_receipt, v.can_cancel, v.card_complete, v.remaining_amount, v.remaining_card_number, v.remaining_card_holder, v.remaining_receipt_received, v.refund_amount, v.refund_pending, v.refund_card_submitted, v.can_refund, JSON.stringify(v.button_styles || {})].join('|');
    }

    function applyView(v, force) {
        if (!v || stopped) return;
        const prevPhase = data ? data.phase : '';
        offsetSec = Number(v.now || 0) - Math.floor(Date.now() / 1000);
        data = v;
        refreshedAtZero = false;
        if (v.phase === 'paid') {
            finishPaid();
            return;
        }
        if (FAIL_PHASES.includes(v.phase)) {
            finishFail(v);
            return;
        }
        const next = signatureOf(v);
        if (force || next !== signature || prevPhase !== v.phase) {
            signature = next;
            render();
        }
    }

    async function poll() {
        if (polling || stopped) return;
        polling = true;
        try {
            const res = await call('tonpay_custom_view', { params: { order_id: orderId } });
            if (res && res.obj && res.obj.view) applyView(res.obj.view, false);
        } catch (err) {
            console.warn('[tonpay-custom] poll failed', err);
        } finally {
            polling = false;
        }
    }

    function renderDone(statusObj) {
        const credited = fmtNum((statusObj && statusObj.amount) || (data && data.request_amount) || 0);
        view.innerHTML = frame(`
            <div class="payment-outcome" data-outcome="success">
                <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">${icon('checkCircle', 'class="ico ico-xxl ico-success"')}</div>
                <h2 style="margin:6px 0;font-size:18px">پرداخت با موفقیت تأیید شد</h2>
                <p class="muted">${mode === 'direct_buy' ? 'سرویس شما فعال شد.' : `مبلغ ${credited} تومان به کیف پول شما اضافه شد.`}</p>
                <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${escapeHtml(orderId)}</span></div>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                    <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                </div>
            </div>`);
    }

    function renderPendingFulfillment(statusObj) {
        const credited = fmtNum((statusObj && statusObj.amount) || (data && data.request_amount) || 0);
        view.innerHTML = frame(`
            <div style="text-align:center;padding:10px 0">
                <div style="font-size:42px;line-height:1;color:var(--yellow);margin:4px 0 10px">${icon('hourglass', 'class="ico ico-xxl ico-warn"')}</div>
                <h2 style="margin:6px 0;font-size:18px">پرداخت تأیید شد</h2>
                <p class="muted">${mode === 'direct_buy' ? 'سرویس شما در حال آماده‌سازی است و هنوز فعال‌شدن آن تأیید نشده است. نتیجه نهایی در بخش سفارش‌های شما نمایش داده می‌شود.' : `مبلغ ${credited} تومان به کیف پول شما اضافه شد.`}</p>
                <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${escapeHtml(orderId)}</span></div>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                    <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                </div>
            </div>`);
    }

    async function finishPaid() {
        if (finishing) return;
        finishing = true;
        cleanup();
        hapticNotify('success');
        view.innerHTML = frame(`
            <div style="text-align:center;padding:14px 0">
                ${icon('hourglass', 'class="ico ico-xxl ico-accent"')}
                <h2 style="margin:10px 0 4px;font-size:18px">پرداخت شما تأیید شد</h2>
                <p class="muted" style="font-size:13px">${mode === 'direct_buy' ? 'در حال بررسی آماده‌سازی سرویس…' : 'در حال نهایی‌سازی…'}</p>
            </div>`);
        let statusObj = {};
        for (let i = 0; i < SERVICE_READY_MAX_TRIES; i++) {
            try {
                const res = await call('payment_status', { params: { order_id: orderId } });
                statusObj = (res && res.obj) || {};
            } catch (_) {}
            if (mode !== 'direct_buy' || statusObj.is_service_ready) break;
            await new Promise((r) => setTimeout(r, SERVICE_READY_POLL_MS));
        }
        const serviceReady = mode !== 'direct_buy' || !!statusObj.is_service_ready;
        if (typeof onSuccess === 'function') {
            onSuccess(statusObj, { serviceReady });
            return;
        }
        if (mode === 'direct_buy' && (!statusObj.is_service_ready || !statusObj.service)) {
            renderPendingFulfillment(statusObj);
            return;
        }
        if (statusObj.is_service_ready && statusObj.service) {
            try {
                const mod = await import('./service-success.js');
                if (mod && typeof mod.renderServiceSuccess === 'function') {
                    mod.renderServiceSuccess(view, { order_id: statusObj.order_id, service: statusObj.service }, {
                        productName: statusObj.service.product_name || '',
                        panelName: statusObj.service.panel_name || '',
                    });
                    return;
                }
            } catch (_) {}
        }
        if (!serviceReady) {
            renderPendingFulfillment(statusObj);
            return;
        }
        renderDone(statusObj);
    }

    function finishFail(v) {
        if (finishing) return;
        finishing = true;
        cleanup();
        hapticNotify('error');
        if (typeof onFail === 'function') {
            onFail({ order_id: orderId, reason: (v.messages && v.messages.status) || failTitle(v.phase), phase: v.phase });
            return;
        }
        view.innerHTML = frame(`
            <div class="payment-outcome" data-outcome="danger">
                <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">${icon('xCircle', 'class="ico ico-xxl ico-warn"')}</div>
                <h2 style="margin:6px 0;font-size:18px">${escapeHtml(failTitle(v.phase))}</h2>
                <p class="muted" style="white-space:pre-line">${escapeHtml((v.messages && v.messages.status) || '')}</p>
                <p class="muted mt-sm" style="font-size:12px">شناسه فاکتور: <span class="mono" dir="ltr">${escapeHtml(v.invoice_id || '')}</span></p>
                <p class="muted" style="font-size:12px">کد پیگیری: <span class="mono">${escapeHtml(orderId)}</span></p>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                    <button type="button" class="btn btn-ghost btn-block" id="tpc-fail-home" style="flex:1">خانه</button>
                </div>
            </div>`);
        const $failHome = view.querySelector('#tpc-fail-home');
        if ($failHome) $failHome.addEventListener('click', goHome);
    }

    function goHome() {
        const hash = window.location.hash;
        if (hash === '' || hash === '#' || hash === '#/') {
            window.dispatchEvent(new HashChangeEvent('hashchange'));
            return;
        }
        window.location.hash = '#/';
    }

    function tick() {
        if (stopped || !data) return;
        view.querySelectorAll('#tpc-timer, #tpc-expiry-timer').forEach($timer => {
            const left = remaining(Number($timer.getAttribute('data-target')));
            $timer.textContent = fmtClock(left);
            if (left <= 0 && !refreshedAtZero) {
                refreshedAtZero = true;
                poll();
            }
        });
        const $label = view.querySelector('#tpc-change-label');
        if ($label && data.change_card_wait > 0 && !data.can_change_card && !data.change_card_exhausted && data.show_change_card !== 0) {
            const wait = Math.max(0, Number(data.change_card_wait) - (serverNow() - Number(data.now)));
            if (wait <= 0) {
                data = Object.assign({}, data, { can_change_card: true, change_card_wait: 0 });
                signature = '';
                render();
            } else {
                $label.textContent = `تعویض کارت (${Math.ceil(wait)} ثانیه)`;
            }
        }
    }

    function onVisibility() {
        if (stopped) return;
        if (document.visibilityState === 'visible') poll();
    }

    if (typeof window !== 'undefined') window.__faoximaTonpayCustomCleanup = cleanup;

    if (data) {
        applyView(data, true);
    } else {
        view.innerHTML = frame('<div class="empty"><p class="muted">در حال دریافت اطلاعات پرداخت…</p></div>');
    }
    poll();
    pollTimer = setInterval(poll, POLL_INTERVAL_MS);
    tickTimer = setInterval(tick, 1000);
    document.addEventListener('visibilitychange', onVisibility);

    return cleanup;
}
