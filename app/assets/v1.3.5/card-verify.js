import { paymentResultHtml } from './payment-view.js?v=2';
import { escapeHtml, copyToClipboard, toast } from './utils.js?v=0.0.53';
import { hapticImpact, hapticNotify, showConfirm } from './telegram.js?v=0.0.52';
import { icon } from './icons.js?v=0.0.52';
import { call, callForm } from './api.js?v=0.0.53';

const POLL_MS = 8000;
const SETTLE_LIMIT_MS = 10 * 60 * 1000;
const AFTER_DEADLINE_SEC = 15 * 60;

export function verifyPendingFromError(err) {
    const obj = err && err.data && err.data.obj;
    if (err && err.status === 409 && obj && obj.code === 'verify_pending' && obj.pending && obj.pending.order_id) {
        return obj.pending;
    }
    return null;
}

export function renderVerifyPendingBanner(p, message) {
    const skew = Number(p.server_now || 0) > 0 ? Number(p.server_now) - Math.floor(Date.now() / 1000) : 0;
    const left = Math.max(0, Number(p.expires_at || 0) - (Math.floor(Date.now() / 1000) + skew));
    return `
        <div class="pending-banner" data-sbv-pending="${escapeHtml(p.order_id)}">
            <div class="pending-banner-head">
                ${icon('hourglass', 'class="ico ico-leading"')}
                <span>${escapeHtml(message || 'شما یک پرداخت ناتمام دارید. برای ایجاد پرداخت جدید، ابتدا پرداخت قبلی را تکمیل یا لغو کنید.')}</span>
            </div>
            <div class="pending-banner-grid">
                <div><span class="muted">کد پیگیری</span><span class="mono">${escapeHtml(p.order_id)}</span></div>
                <div><span class="muted">مبلغ قابل واریز</span><span class="mono accent">${fmt(p.payable_toman)} تومان</span></div>
                <div><span class="muted">باقی‌مانده</span><span class="mono">${p.resumable ? mmss(left) : '—'}</span></div>
            </div>
            <p class="muted" style="font-size:12px;margin:0 0 10px">${escapeHtml(p.status_label || '')}</p>
            <div class="pending-banner-actions">
                <button type="button" class="btn btn-primary btn-block" data-sbv-resume="${escapeHtml(p.order_id)}">
                    ${icon('arrowLeft', 'class="ico ico-leading"')}
                    <span>${p.resumable ? 'ادامه پرداخت قبلی' : 'مشاهده پرداخت قبلی'}</span>
                </button>
                ${p.cancellable ? `
                <button type="button" class="btn btn-ghost btn-block" data-sbv-cancel="${escapeHtml(p.order_id)}">
                    ${icon('close', 'class="ico ico-leading"')}
                    <span>لغو پرداخت قبلی</span>
                </button>` : ''}
            </div>
        </div>`;
}

export async function cancelVerifyPayment(orderId) {
    await callForm('payment_reset', { order_id: String(orderId || '') });
}

export function wireVerifyPendingBanner(root, opts = {}) {
    root.querySelectorAll('[data-sbv-resume]').forEach((btn) => {
        btn.addEventListener('click', () => {
            hapticImpact('light');
            window.location.hash = '#/watch/' + encodeURIComponent(btn.getAttribute('data-sbv-resume') || '');
        });
    });
    root.querySelectorAll('[data-sbv-cancel]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            hapticImpact('light');
            btn.disabled = true;
            try {
                await cancelVerifyPayment(btn.getAttribute('data-sbv-cancel'));
                hapticNotify('success');
                toast('پرداخت قبلی لغو شد. اکنون می‌توانید پرداخت جدید ایجاد کنید.', 'success', 4000);
                const banner = btn.closest('[data-sbv-pending]');
                if (banner) banner.remove();
                if (typeof opts.onCancelled === 'function') opts.onCancelled();
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'لغو پرداخت انجام نشد', 'error', 5000);
                btn.disabled = false;
            }
        });
    });
}

export function showVerifyPending(host, err, opts = {}) {
    const p = verifyPendingFromError(err);
    if (!p || !host) return false;
    const target = host.querySelector('#pay-result-host') || host;
    const wrap = document.createElement('div');
    wrap.innerHTML = renderVerifyPendingBanner(p, err.message);
    const old = target.querySelector('[data-sbv-pending]');
    if (old) old.remove();
    target.prepend(wrap.firstElementChild);
    wireVerifyPendingBanner(target, opts);
    hapticNotify('warning');
    return true;
}

export function isVerifyPayment(d) {
    return !!(d && d.verify && typeof d.verify === 'object' && Number(d.verify.payable_rial) > 0);
}

function fmt(n) {
    return Number(n || 0).toLocaleString('en-US');
}

function mmss(sec) {
    const s = Math.max(0, Math.floor(sec));
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${m}:${String(r).padStart(2, '0')}`;
}

export function verifyAmountHtml(d) {
    const v = d.verify;
    const rial = String(Math.round(Number(v.payable_rial) || 0));
    const canCancel = String(v.state || '') === 'open' && String(v.verify_status || '') === 'waiting';
    return `
        <section class="sbv-box sbv-compact">
            <div class="sbv-heading"><span class="sbv-live-dot"></span><span>تأیید خودکار واریز</span><span class="sbv-expiry" title="زمان باقی‌مانده تا انقضای پرداخت">${icon('clock')} مهلت <b data-sbv-left>—</b></span></div>
            <div class="sbv-payable"><span>دقیقاً این مبلغ را واریز کن</span><strong>${fmt(v.payable_rial)} <small>ریال</small></strong><span class="sbv-toman">${fmt(v.payable_toman)} تومان</span></div>
            <button type="button" class="btn btn-primary btn-block sbv-copy-rial" data-copy="${escapeHtml(rial)}">${icon('copy')} <span>کپی مبلغ به ریال</span></button>
            <p class="sbv-short-notice">${icon('info')} مبلغ را یکجا و دقیق واریز کن؛ واریز کمتر، بیشتر یا چندمرحله‌ای خودکار تأیید نمی‌شود.</p>
            <details class="sbv-breakdown"><summary>جزئیات مبلغ و کارمزد ${icon('chevronDown')}</summary><div class="sbv-breakdown-row"><span>مبلغ شارژ / سفارش</span><b>${fmt(v.principal_toman)} تومان</b></div><div class="sbv-breakdown-row"><span>کارمزد شناسایی</span><b>${fmt(v.fee_toman)} تومان</b></div><p>کارمزد شناسایی به موجودی یا مبلغ سفارش اضافه نمی‌شود و بازگردانده نمی‌شود.</p><div class="sbv-breakdown-row"><span>مهلت واریز</span><b>تا ساعت ${escapeHtml(v.expires_at_clock || '')}</b></div><div class="sbv-breakdown-row"><span>کد پیگیری</span><b dir="ltr">${escapeHtml(d.order_id || '')}</b></div></details>
            <p class="sbv-fee-note">شامل ${fmt(v.fee_toman)} تومان کارمزد شناساییِ غیرقابل‌برگشت</p>
            <div class="sbv-bottom"><span data-sbv-status>در انتظار واریز · بررسی خودکار فعال</span>${canCancel ? `<button type="button" class="sbv-cancel-pay" data-order="${escapeHtml(d.order_id || '')}">لغو پرداخت</button>` : ''}</div>
        </section>`;
}

export function verifyReceiptNoteHtml(d) {
    const v = d.verify;
    return `<p class="muted" style="font-size:13px" data-sbv-receipt-note>اگر پرداخت شما تا ساعت ${escapeHtml(v.receipt_after_clock || '')} به‌صورت خودکار تأیید نشد، می‌توانید رسید را ارسال کنید.</p>`;
}

let _successModulePromise = null;
function loadServiceSuccess() {
    if (_successModulePromise) return _successModulePromise;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const ver = (cfg.version || cfg.cacheBust || Date.now()).toString();
    try {
        const url = new URL('./pages/service-success.js', import.meta.url);
        url.searchParams.set('v', ver);
        _successModulePromise = import(url.href).catch(() => {
            _successModulePromise = null;
            return import('./pages/service-success.js');
        });
    } catch (e) {
        _successModulePromise = import('./pages/service-success.js');
    }
    return _successModulePromise;
}

export function evaluateVerifyStatus(obj, flowHint) {
    const st = String(obj.payment_status || '');
    const flow = String(obj.flow || flowHint || 'recharge');
    const f = obj.fulfillment && obj.fulfillment.outcome ? String(obj.fulfillment.outcome) : '';
    const amount = obj.fulfillment && obj.fulfillment.amount ? Number(obj.fulfillment.amount) : 0;
    if (f === 'refunded') return { kind: 'refunded', amount, flow };
    if (f === 'wallet_credit') return { kind: 'wallet_credit', amount, flow };
    if (f === 'failed') return { kind: 'failed', flow };
    if (st === 'paid' || f === 'completed') {
        if (flow === 'direct_buy') {
            return (obj.is_service_ready && obj.service) ? { kind: 'service_ready', flow } : { kind: 'provisioning', flow };
        }
        return { kind: 'success', flow };
    }
    if (st === 'reject' || st === 'cancelled' || st === 'expire' || f === 'closed') return { kind: 'closed', status: st, flow };
    if (f === 'processing' || st === 'processing' || st === 'reconciling' || (obj.verify && obj.verify.verify_status === 'detected')) {
        return { kind: 'processing', flow };
    }
    if (st === 'waiting' || st === 'pending') return { kind: 'receipt', flow };
    return { kind: 'waiting', flow };
}

function resultCard(iconName, iconClass, title, text, orderId, actions) {
    const tone = iconClass === 'ico-success' ? 'success' : iconName === 'xCircle' ? 'danger' : iconClass === 'ico-warn' ? 'warning' : 'pending';
    return paymentResultHtml({ tone, title, text, orderId, actions: actions || [], iconName });
}

function createTracker(view, orderId, opts, ui) {
    const root = opts.rootView instanceof HTMLElement ? opts.rootView : view;
    let stopped = false;
    let polling = false;
    let lastKind = '';
    let settleSince = 0;
    let successFired = false;
    let deadlineSec = Number(opts.expiresAt || 0);
    const flowHint = opts.purchaseUsername ? 'direct_buy' : (opts.flow || '');
    const successHref = opts.successHref || '#/account';
    const successLabel = opts.successLabel || 'بازگشت به حساب';

    const host = () => view.querySelector('#pay-result-host') || view;
    const fireSuccess = (obj) => {
        if (successFired) return;
        successFired = true;
        if (typeof opts.onSuccess === 'function') {
            try { opts.onSuccess(obj); } catch (_) {}
        }
    };
    const clearForm = () => {
        const $form = view.querySelector('#pay-form-host');
        if ($form) $form.innerHTML = '';
    };
    const wireRecheck = () => {
        host().querySelectorAll('[data-sbv-recheck]').forEach((b) => b.addEventListener('click', () => {
            settleSince = Date.now();
            lastKind = '';
            resume();
        }));
    };
    const show = (html) => {
        clearForm();
        host().innerHTML = html;
        wireRecheck();
    };

    function render(ev, obj) {
        const kind = ev.kind;
        if (kind === lastKind && kind !== 'provisioning' && kind !== 'processing') return;
        const changed = kind !== lastKind;
        lastKind = kind;
        if (kind === 'service_ready') {
            stop();
            hapticNotify('success');
            loadServiceSuccess().then((mod) => {
                if (mod && typeof mod.renderServiceSuccess === 'function') {
                    mod.renderServiceSuccess(root, { order_id: obj.order_id || orderId, service: obj.service }, {
                        productName: obj.service.product_name || '',
                        panelName: obj.service.panel_name || '',
                    });
                } else {
                    show(resultCard('checkCircle', 'ico-success', 'سرویس شما آماده است', 'پرداخت تأیید و سرویس ساخته شد.', orderId, [{ href: '#/services', label: 'سرویس‌های من' }]));
                }
                fireSuccess(obj);
            }).catch(() => {
                show(resultCard('checkCircle', 'ico-success', 'سرویس شما آماده است', 'پرداخت تأیید و سرویس ساخته شد.', orderId, [{ href: '#/services', label: 'سرویس‌های من' }]));
                fireSuccess(obj);
            });
            return;
        }
        if (kind === 'success') {
            stop();
            hapticNotify('success');
            const text = ev.flow === 'recharge'
                ? `واریز شما تأیید شد و مبلغ ${fmt(obj.amount)} تومان به کیف پول شما اضافه شد.`
                : 'واریز شما تأیید شد و درخواست شما انجام شد.';
            show(resultCard('checkCircle', 'ico-success', 'پرداخت تأیید شد', text, orderId, [
                { href: ev.flow === 'recharge' ? successHref : (opts.successHref || '#/services'), label: ev.flow === 'recharge' ? successLabel : (opts.successLabel || 'سرویس‌های من') },
            ]));
            fireSuccess(obj);
            return;
        }
        if (kind === 'wallet_credit' || kind === 'refunded') {
            stop();
            hapticNotify('warning');
            const amt = ev.amount > 0 ? `${fmt(ev.amount)} تومان` : 'اصل مبلغ';
            const text = kind === 'refunded'
                ? `پرداخت شما دریافت شد اما انجام سفارش ناموفق بود. ${amt} به کیف پول شما بازگردانده شد.`
                : `پرداخت شما دریافت شد اما سفارش تکمیل نشد. ${amt} به کیف پول شما اضافه شد.`;
            show(resultCard('alert', 'ico-warn', 'سفارش تکمیل نشد', text, orderId, [{ href: '#/account', label: 'حساب من' }, { href: '#/', label: 'خانه' }]));
            return;
        }
        if (kind === 'failed') {
            stop();
            hapticNotify('warning');
            show(resultCard('alert', 'ico-warn', 'نیازمند بررسی پشتیبانی', 'پرداخت شما ثبت شده اما تکمیل خودکار سفارش انجام نشد و برای بررسی ادمین نگه داشته شد. لطفاً با کد پیگیری با پشتیبانی در تماس باشید.', orderId, [{ href: '#/', label: 'خانه' }]));
            return;
        }
        if (kind === 'closed') {
            stop();
            const map = { expire: 'مهلت این پرداخت به پایان رسیده است.', cancelled: 'این پرداخت لغو شده است.', reject: 'این پرداخت رد شده است.' };
            const reason = obj.reason ? ` ${obj.reason}` : '';
            if (ui && ui.onClosed) {
                ui.onClosed(ev.status, (map[ev.status] || 'این پرداخت دیگر فعال نیست.') + reason);
                return;
            }
            show(resultCard('xCircle', 'ico-warn', 'پرداخت فعال نیست', (map[ev.status] || 'این پرداخت دیگر فعال نیست.') + reason, orderId, [{ href: '#/', label: 'خانه' }]));
            return;
        }
        if (kind === 'provisioning' || kind === 'processing') {
            if (changed) settleSince = Date.now();
            if (Date.now() - settleSince > SETTLE_LIMIT_MS) {
                stop();
                show(resultCard('clock', 'ico-warn', kind === 'provisioning' ? 'آماده‌سازی سرویس بیش از حد معمول طول کشیده است' : 'تکمیل پرداخت بیش از حد معمول طول کشیده است', 'پرداخت شما ثبت شده است. می‌توانید دوباره بررسی کنید یا بعداً از صفحه خانه همین پرداخت را پیگیری کنید.', orderId, [{ recheck: true, label: 'بررسی دوباره' }, { href: '#/', label: 'خانه' }]));
                return;
            }
            if (changed) {
                show(resultCard('hourglass', 'ico-accent', kind === 'provisioning' ? 'پرداخت دریافت شد — در حال ساخت سرویس…' : 'واریز شناسایی شد — در حال تکمیل…', 'لطفاً این صفحه را باز نگه دارید؛ نتیجه به‌صورت خودکار نمایش داده می‌شود.', orderId, []));
            }
            return;
        }
        if (ui && ui.onWaiting) ui.onWaiting(kind, obj);
    }

    async function poll() {
        if (polling || stopped) return;
        polling = true;
        try {
            const res = await call('payment_status', { params: { order_id: orderId } });
            const obj = res?.obj || {};
            if (obj.verify && Number(obj.verify.expires_at) > 0) deadlineSec = Number(obj.verify.expires_at);
            const ev = evaluateVerifyStatus(obj, flowHint);
            if ((ev.kind === 'waiting' || ev.kind === 'receipt') && deadlineSec > 0 && Math.floor(Date.now() / 1000) > deadlineSec + AFTER_DEADLINE_SEC) {
                stop();
                if (ui && ui.onTimeout) {
                    ui.onTimeout();
                } else {
                    show(resultCard('clock', 'ico-warn', 'پیگیری خودکار متوقف شد', 'مهلت این پرداخت گذشته است. در صورت واریز، پرداخت توسط ادمین بررسی می‌شود.', orderId, [{ recheck: true, label: 'بررسی دوباره' }, { href: '#/', label: 'خانه' }]));
                }
                return;
            }
            render(ev, obj);
        } catch (_) {
        } finally {
            polling = false;
        }
    }

    let timer = null;
    function resume() {
        stopped = false;
        if (timer) clearInterval(timer);
        timer = setInterval(poll, POLL_MS);
        window.addEventListener('hashchange', stop);
        void poll();
    }
    function stop() {
        stopped = true;
        if (timer) clearInterval(timer);
        timer = null;
        window.removeEventListener('hashchange', stop);
    }
    return { resume, stop, poll };
}

export function startVerifyTracker(view, orderId, opts = {}) {
    if (!view.querySelector('#pay-result-host')) {
        view.innerHTML = `
            <article class="verify-payment-page">

                <div class="card-body">
                    <div id="pay-form-host"></div>
                    <div id="pay-result-host">${resultCard('hourglass', 'ico-accent', 'در حال دریافت وضعیت پرداخت…', '', orderId, [])}</div>
                </div>
            </article>`;
    }
    const tracker = createTracker(view, orderId, opts, {
        onWaiting: (kind) => {
            const $h = view.querySelector('#pay-result-host');
            if ($h) {
                $h.innerHTML = resultCard('hourglass', 'ico-accent', kind === 'receipt' ? 'رسید شما ارسال شده است' : 'در انتظار واریز', kind === 'receipt' ? 'رسید در انتظار بررسی است و بررسی خودکار واریز همچنان فعال است.' : 'پس از واریز، پرداخت به‌صورت خودکار بررسی می‌شود.', orderId, [{ href: '#/', label: 'خانه' }]);
            }
        },
    });
    tracker.resume();
    return tracker.stop;
}

export function wireVerify(view, d, opts = {}) {
    const v = d.verify;
    const orderId = String(d.order_id || '').trim();
    const skew = Number(v.server_now || 0) > 0 ? Number(v.server_now) - Math.floor(Date.now() / 1000) : 0;
    const nowSrv = () => Math.floor(Date.now() / 1000) + skew;
    const $left = view.querySelector('[data-sbv-left]');
    const $status = view.querySelector('[data-sbv-status]');
    const $note = view.querySelector('[data-sbv-receipt-note]');
    const $pick = view.querySelector('#receipt-pick');
    const $submit = view.querySelector('#receipt-submit');
    const $cancel = view.querySelector('.sbv-cancel-pay');
    const receiptZone = $pick?.closest('.receipt-upload-zone');
    receiptZone?.classList.add('sbv-receipt-zone');
    let clockStopped = false;

    view.querySelectorAll('.sbv-copy-rial').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const copiedRial = String(btn.dataset.copy || '');
            const ok = await copyToClipboard(copiedRial);
            toast(ok ? 'مبلغ به ریال کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            hapticImpact('light');
        });
    });

    let uploadEnabled = null;
    const setUpload = (enabled, text) => {
        if (uploadEnabled !== enabled) {
            uploadEnabled = enabled;
            if (receiptZone) receiptZone.dataset.receiptReady = enabled ? 'true' : 'false';
            if ($pick) $pick.disabled = !enabled;
            if ($submit) $submit.disabled = !enabled;
        }
        if ($note && text && $note.textContent !== text) $note.textContent = text;
    };

    const tracker = createTracker(view, orderId, {
        rootView: opts.rootView,
        purchaseUsername: opts.purchaseUsername,
        onSuccess: opts.onSuccess,
        successHref: opts.successHref,
        successLabel: opts.successLabel,
        expiresAt: Number(v.expires_at),
    }, {
        onWaiting: (kind) => {
            if ($status) {
                $status.textContent = kind === 'receipt'
                    ? 'رسید دریافت شد · بررسی خودکار فعال'
                    : 'در انتظار واریز · بررسی خودکار فعال';
            }
            if (kind === 'receipt' && $cancel) $cancel.remove();
        },
        onClosed: (status, text) => {
            clockStopped = true;
            setUpload(false, 'این پرداخت دیگر فعال نیست.');
            if ($status) $status.textContent = text;
            if ($cancel) $cancel.remove();
        },
        onTimeout: () => {
            clockStopped = true;
            setUpload(false, 'مهلت این پرداخت به پایان رسیده است.');
            if ($status) $status.textContent = 'پایان مهلت — در صورت واریز، پرداخت توسط ادمین بررسی می‌شود.';
        },
    });

    const renderClock = () => {
        if (clockStopped) return;
        const now = nowSrv();
        const left = Number(v.expires_at) - now;
        if ($left) $left.textContent = left > 0 ? mmss(left) : 'پایان مهلت';
        if (left <= 0) {
            setUpload(false, 'مهلت این پرداخت به پایان رسیده است.');
            if ($cancel) $cancel.remove();
            return;
        }
        const wait = Number(v.receipt_after) - now;
        if (wait > 0) {
            setUpload(false, `ارسال رسید تا ${mmss(wait)} دیگر فعال می‌شود؛ بررسی خودکار در حال انجام است.`);
        } else {
            setUpload(true, 'اگر پرداخت شما هنوز تأیید نشده است، می‌توانید رسید را ارسال کنید.');
        }
    };
    const clock = setInterval(() => {
        if (!view.isConnected) { clearInterval(clock); return; }
        renderClock();
    }, 1000);
    window.addEventListener('hashchange', () => clearInterval(clock), { once: true });

    if ($cancel) {
        $cancel.addEventListener('click', async () => {
            hapticImpact('light');
            const ok = await showConfirm('این پرداخت لغو شود؟ اگر مبلغ را واریز کرده‌اید، لغو نکنید.');
            if (!ok) return;
            $cancel.disabled = true;
            try {
                await cancelVerifyPayment(orderId);
                tracker.stop();
                clockStopped = true;
                clearInterval(clock);
                hapticNotify('success');
                const $form = view.querySelector('#pay-form-host');
                if ($form) $form.innerHTML = '';
                const $h = view.querySelector('#pay-result-host');
                if ($h) {
                    $h.innerHTML = resultCard('xCircle', 'ico-warn', 'پرداخت لغو شد', 'این پرداخت لغو شد و دیگر قابل واریز نیست. اکنون می‌توانید پرداخت جدیدی ایجاد کنید.', orderId, [{ href: '#/recharge', label: 'شارژ کیف پول' }, { href: '#/buy', label: 'خرید سرویس' }]);
                }
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'لغو پرداخت انجام نشد', 'error', 5000);
                $cancel.disabled = false;
            }
        });
    }

    renderClock();
    tracker.resume();
    return () => { tracker.stop(); clearInterval(clock); };
}
