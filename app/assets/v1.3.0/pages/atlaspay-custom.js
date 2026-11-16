import { call } from '../api.js?v=0.0.53';
import { escapeHtml, toast, copyToClipboard } from '../utils.js?v=0.0.53';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, hapticNotify } from '../telegram.js?v=0.0.52';

const POLL_INTERVAL_MS = 5000;
const SERVICE_READY_POLL_MS = 3000;
const SERVICE_READY_MAX_TRIES = 40;
const FAIL_PHASES = ['rejected', 'canceled', 'expired'];
const ACTION_BUTTON_STYLES = {
    primary: { cls: 'btn btn-primary', style: '' },
    success: { cls: 'btn', style: 'background:var(--green);color:#fff;border-color:var(--green)' },
    danger: { cls: 'btn', style: 'background:var(--red);color:#fff;border-color:var(--red)' },
    default: { cls: 'btn btn-ghost', style: '' },
};

function actionButton(id, styleKey, v, content, extraAttrs = '') {
    const configured = (v && v.button_styles ? v.button_styles[styleKey] : '') || 'default';
    const primary = styleKey === 'check_status' || styleKey === 'pay_link';
    const picked = ACTION_BUTTON_STYLES[configured === 'default' && primary ? 'primary' : configured] || ACTION_BUTTON_STYLES.default;
    return `<button type="button" class="${picked.cls} apc-action-${styleKey}" id="${id}" style="flex:1;${picked.style}" ${extraAttrs}>${content}</button>`;
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

function failTitle(phase) {
    if (phase === 'rejected') return 'پرداخت توسط اطلس‌پی رد شد';
    if (phase === 'canceled') return 'فاکتور لغو شد';
    return 'فاکتور منقضی شد';
}

function isOfficialLink(url) {
    return /^https:\/\/(t\.me|telegram\.me)\//i.test(String(url || ''));
}

function openOfficialApp(url) {
    const tg = window.Telegram && window.Telegram.WebApp;
    const target = String(url || '');
    if (!isOfficialLink(target)) return false;
    const platform = tg && tg.platform ? String(tg.platform).toLowerCase() : '';
    const desktop = platform === 'tdesktop' || platform === 'macos' || platform === 'weba' || platform === 'webk' || platform === 'web';
    if (tg && !desktop && typeof tg.openTelegramLink === 'function') {
        tg.openTelegramLink(target);
    } else if (tg && typeof tg.openLink === 'function') {
        tg.openLink(target);
    } else {
        window.open(target, '_blank', 'noopener');
    }
    return true;
}

export function startAtlaspayCustom(view, orderId, opts = {}) {
    const { mode = 'recharge', onSuccess, onFail, onCancel, initialView = null } = opts;

    if (typeof window !== 'undefined' && typeof window.__faoximaAtlaspayCustomCleanup === 'function') {
        try { window.__faoximaAtlaspayCustomCleanup(); } catch (_) {}
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
        if (typeof window !== 'undefined' && window.__faoximaAtlaspayCustomCleanup === cleanup) {
            window.__faoximaAtlaspayCustomCleanup = null;
        }
    }

    function frame(inner) {
        return `<article class="tpc-page apc-page"><div class="tpc-body">${inner}</div></article>`;
    }

    function amountRows(v) {
        return `<section class="tpc-amount-card"><span class="pay-eyebrow">مبلغ دقیق قابل پرداخت</span><strong>${fmtNum(v.payable_rial)} <small>ریال</small></strong><div class="tpc-request-amount"><span>مبلغ ${mode === 'direct_buy' ? 'خرید' : 'شارژ'}</span><b>${fmtNum(v.request_amount)} تومان</b></div>${v.phase === 'awaiting_payment' ? actionButton('apc-copy-amount', 'copy_amount', v, `${icon('copy')}<span>کپی مبلغ به ریال</span>`) : ''}</section>`;
    }

    function cardBlock(v) {
        if (v.phase !== 'awaiting_payment') return '';
        if (!v.card_complete) return `<div class="apc-incomplete-card">${icon('info')}<p>${v.payment_url ? 'برای مشاهدهٔ کارت مقصد و تکمیل پرداخت، دکمهٔ «مشاهده اطلاعات پرداخت» را بزن.' : 'اطلاعات کامل کارت در دسترس نیست؛ قبل از واریز با پشتیبانی تماس بگیر.'}</p></div>`;
        return `<section class="pay-bank-card"><div class="pay-bank-heading"><span>${icon('creditCard')} ${escapeHtml(v.bank_name || 'کارت مقصد')}</span><span class="pay-bank-chip" aria-hidden="true"></span></div><div class="pay-bank-number-row"><b dir="ltr" id="apc-card-number">${escapeHtml(v.card_number_display || v.card_number)}</b>${actionButton('apc-copy-card', 'copy_card', v, icon('copy'), 'aria-label="کپی شماره کارت"')}</div><div class="pay-bank-holder"><span>به نام</span><strong>${escapeHtml(v.card_name || '—')}</strong></div></section>`;
    }

    function actionsBlock(v) {
        const rows = [];
        const terminal = FAIL_PHASES.includes(v.phase) || v.phase === 'paid';
        if (v.phase === 'awaiting_payment' && !v.card_complete && v.payment_url) {
            rows.push(actionRow([
                actionButton('apc-payment', 'pay_link', v, `${icon('creditCard', 'class="ico ico-leading"')}<span>مشاهده اطلاعات پرداخت</span>`),
            ]));
        }
        rows.push(actionRow([
            !terminal && v.phase !== 'confirming' ? actionButton('apc-refresh', 'check_status', v, `${icon('refresh', 'class="ico ico-leading"')}<span>بررسی پرداخت</span>`) : '',
            v.receipt_url ? actionButton('apc-receipt', 'send_receipt', v, `${icon('camera', 'class="ico ico-leading"')}<span>ارسال رسید</span>`) : '',
        ]));
        if (v.can_cancel) {
            rows.push(actionRow([
                actionButton('apc-cancel', 'cancel_transaction', v, `${icon('xCircle', 'class="ico ico-leading"')}<span>لغو تراکنش</span>`),
            ]));
        }
        return `<div class="tpc-actions">${rows.join('')}</div>`;
    }

    function timerBlock(v) {
        if (v.phase !== 'awaiting_payment' || !(v.deadline_at > 0)) return '';
        return `<div class="tpc-timer-card">${icon('hourglass')}<div><b>مهلت پرداخت فاکتور</b><span>تا پایان فرصت واریز</span></div><strong id="apc-timer" dir="ltr" data-target="${v.deadline_at}">${fmtClock(remaining(v.deadline_at))}</strong></div>`;
    }

    function statusIcon(v) {
        if (v.phase === 'under_review' || v.phase === 'confirming') return icon('hourglass', 'class="ico ico-xxl ico-accent"');
        if (v.phase === 'underpaid_review' || v.phase === 'underpaid_remainder' || v.phase === 'manual_review' || v.phase === 'deadline_check') return icon('alert', 'class="ico ico-xxl ico-warn"');
        return icon('creditCard', 'class="ico ico-xxl ico-accent"');
    }

    function render() {
        if (stopped || !data) return;
        const v = data;
        view.innerHTML = frame(`
            <div class="tpc-heading">
                <span class="tpc-heading-icon">${statusIcon(v)}</span>
                <h2 style="margin:8px 0 4px;font-size:18px;overflow-wrap:anywhere">${escapeHtml(v.gateway_title || '')}</h2>
                <p class="muted" style="font-size:13px;white-space:pre-line" id="apc-status-text">${escapeHtml((v.messages && v.messages.status) || '')}</p>
            </div>
            ${amountRows(v)}
            ${cardBlock(v)}
            ${v.phase === 'awaiting_payment' ? `<div class="tpc-notices">${v.messages?.amount_warning ? `<p id="apc-amount-warning">${icon('alert')}<span>${escapeHtml(v.messages.amount_warning)}</span></p>` : ''}${v.messages?.card_only_warning ? `<p id="apc-card-only-warning">${icon('info')}<span>${escapeHtml(v.messages.card_only_warning)}</span></p>` : ''}</div>` : ''}
            ${timerBlock(v)}
            ${actionsBlock(v)}
            ${v.messages && v.messages.receipt ? `<p class="apc-receipt-note">${escapeHtml(v.messages.receipt)}</p>` : ''}
            <p class="tpc-tracking">شناسه پیگیری: <span class="mono gold" dir="ltr">${escapeHtml(v.tracking_code || orderId)}</span></p>`);
        bind();
    }

    function bind() {
        const $ = (id) => view.querySelector(id);

        const $copyAmount = $('#apc-copy-amount');
        if ($copyAmount) $copyAmount.addEventListener('click', async () => {
            hapticImpact('light');
            const ok = await copyToClipboard(String(data.amount_copy_value || ''));
            toast(ok ? 'مبلغ پرداخت کپی شد' : 'کپی انجام نشد', ok ? 'success' : 'error', 2000);
        });

        const $copyCard = $('#apc-copy-card');
        if ($copyCard) $copyCard.addEventListener('click', async () => {
            hapticImpact('light');
            const ok = await copyToClipboard(String(data.card_copy_value || ''));
            toast(ok ? 'شماره کارت کپی شد' : 'کپی انجام نشد', ok ? 'success' : 'error', 2000);
        });

        const $refresh = $('#apc-refresh');
        if ($refresh) $refresh.addEventListener('click', async () => {
            hapticImpact('light');
            $refresh.disabled = true;
            await poll();
            const $again = $('#apc-refresh');
            if ($again) $again.disabled = false;
        });

        const $payment = $('#apc-payment');
        if ($payment) $payment.addEventListener('click', () => {
            hapticImpact('light');
            if (!openOfficialApp(data.payment_url)) {
                toast('لینک پرداخت اطلس‌پی در دسترس نیست', 'error', 3500);
            }
        });

        const $receipt = $('#apc-receipt');
        if ($receipt) $receipt.addEventListener('click', () => {
            hapticImpact('light');
            if (!openOfficialApp(data.receipt_url)) {
                toast('لینک ربات اطلس‌پی در دسترس نیست', 'error', 3500);
            }
        });

        const $cancel = $('#apc-cancel');
        if ($cancel) $cancel.addEventListener('click', async () => {
            if (busy) return;
            if (!window.confirm('آیا از لغو این تراکنش اطمینان دارید؟ لغو فاکتور به‌معنی بازگشت وجه واریزشده نیست و اطلس‌پی فقط تا زمانی که هیچ پرداخت یا رسیدی ثبت نشده اجازه لغو می‌دهد.')) return;
            busy = true;
            $cancel.disabled = true;
            try {
                await call('atlaspay_custom_cancel', { method: 'POST', body: { order_id: orderId } });
                cleanup();
                if (typeof onCancel === 'function') {
                    onCancel();
                } else {
                    window.location.hash = '#/';
                }
            } catch (err) {
                toast(err.message || 'لغو فاکتور انجام نشد', 'error', 4000);
                $cancel.disabled = false;
                busy = false;
                await poll();
            }
        });
    }

    function signatureOf(v) {
        return [v.phase, v.card_number, v.payable_rial, v.can_cancel, v.card_complete, v.payment_url, v.receipt_url, JSON.stringify(v.button_styles || {})].join('|');
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
            const res = await call('atlaspay_custom_view', { params: { order_id: orderId } });
            if (res && res.obj && res.obj.view) applyView(res.obj.view, false);
        } catch (err) {
            console.warn('[atlaspay-custom] poll failed', err);
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
                <div class="kv mt-md"><span class="kv-label">شناسه پیگیری</span><span class="kv-value mono gold">${escapeHtml((data && data.tracking_code) || orderId)}</span></div>
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
                <p class="muted" style="font-size:13px">${mode === 'direct_buy' ? 'در حال ساخت سرویس…' : 'در حال نهایی‌سازی…'}</p>
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
        if (typeof onSuccess === 'function') {
            onSuccess(statusObj);
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
                <p class="muted mt-sm" style="font-size:12px">شناسه پیگیری: <span class="mono" dir="ltr">${escapeHtml(v.tracking_code || orderId)}</span></p>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                    <button type="button" class="btn btn-ghost btn-block" id="apc-fail-home" style="flex:1">خانه</button>
                </div>
            </div>`);
        const $failHome = view.querySelector('#apc-fail-home');
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
        const $timer = view.querySelector('#apc-timer');
        if ($timer) {
            const left = remaining(Number($timer.getAttribute('data-target')));
            $timer.textContent = fmtClock(left);
            if (left <= 0 && !refreshedAtZero) {
                refreshedAtZero = true;
                poll();
            }
        }
    }

    function onVisibility() {
        if (stopped) return;
        if (document.visibilityState === 'visible') poll();
    }

    if (typeof window !== 'undefined') window.__faoximaAtlaspayCustomCleanup = cleanup;

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
