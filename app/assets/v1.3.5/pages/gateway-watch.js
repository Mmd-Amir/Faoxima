import { call } from '../api.js?v=0.0.53';
import { escapeHtml, toast } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, hapticNotify, isDesktopPlatform, showConfirm } from '../telegram.js?v=0.0.52';

function openGatewayLink(url, opts) {
    const tg = window.Telegram && window.Telegram.WebApp;
    const u = String(url || '');
    if (!u) return;
    if (isDesktopPlatform()) {
        if (tg && typeof tg.openLink === 'function') {
            tg.openLink(u);
        } else {
            window.open(u, '_blank', 'noopener');
        }
        return;
    }
    const keepMiniAppOpen = !!(opts && opts.keepMiniAppOpen);
    const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
    if (!keepMiniAppOpen && tg && isTme && typeof tg.openTelegramLink === 'function') {
        tg.openTelegramLink(u);
    } else if (tg && typeof tg.openLink === 'function') {
        tg.openLink(u);
    } else {
        window.open(u, '_blank', 'noopener');
    }
}


function pad2(n) { return n < 10 ? '0' + n : String(n); }
function fmtMmSs(totalSec) {
    const s = Math.max(0, Math.floor(totalSec));
    return pad2(Math.floor(s / 60)) + ':' + pad2(s % 60);
}
function fmtHhMmSs(totalSec) {
    const s = Math.max(0, Math.floor(totalSec));
    const hours = Math.floor(s / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const secs = s % 60;
    return pad2(hours) + ':' + pad2(minutes) + ':' + pad2(secs);
}


function _gwGetActive() {
    return (typeof window !== 'undefined') ? window.__faoximaGatewayWatchCleanup : null;
}
function _gwSetActive(fn) {
    if (typeof window !== 'undefined') {
        window.__faoximaGatewayWatchCleanup = (typeof fn === 'function') ? fn : null;
    }
}


export function startGatewayWatch(view, opts) {
    const _prevCleanup = _gwGetActive();
    if (typeof _prevCleanup === 'function') {
        try { _prevCleanup(); } catch (_) {}
        _gwSetActive(null);
    }
    const {
        orderId,
        title = 'در انتظار تایید پرداخت…',
        subtitle = 'لطفاً صبر کنید. به‌محض تایید، صفحه آپدیت می‌شود.',
        gatewayUrl = '',
        keepMiniAppOpen = false,
        isCrypto = false,
        mode = 'direct_buy',
        method = '',
        timeoutSec = 1800,
        expiresAtSec = 0,
        pollEverySec = 5,
        onSuccess,
        onFail,
        onTimeout,
        onCancel,
    } = opts || {};

    const fmtCountdown = (String(method || '').toLowerCase() === 'tonpay') ? fmtHhMmSs : fmtMmSs;
    const isTetraminator = String(method || '').toLowerCase() === 'tetraminator';
    const isRialit = String(method || '').toLowerCase() === 'rialit' || isTetraminator;
    const linkMeta = (o) => (isTetraminator ? (o && o.tetraminator) : (o && o.rialit)) || null;
    const gatewayFaName = isTetraminator ? 'تترامیناتور' : 'ریالیت';
    const isAtlaspay = String(method || '').toLowerCase() === 'atlaspay';
    let currentGatewayUrl = gatewayUrl;
    let atlaspayReceiptUrl = '';
    let atlaspayReceiptExpiresAt = 0;


    const finalizingMessages = {
        direct_buy:      'پرداخت تایید شد — در حال ساخت سرویس…',
        recharge:        'پرداخت تایید شد — در حال شارژ کیف‌پول…',
        crypto_offline:  'پرداخت تایید شد — در حال نهایی‌سازی…',
    };
    const finalizingMsg = finalizingMessages[mode] || finalizingMessages.direct_buy;

    if (!orderId) {
        view.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>پارامتر نامعتبر</h3>
                <p class="muted">اطلاعات این پرداخت در دسترس نیست. از بخش حساب، پرداخت را دوباره باز کن.</p>
            </div>`;
        return () => {};
    }

    const startedAt = Date.now();
    const maxDeadline = startedAt + Number(timeoutSec) * 1000;
    const deadline = (Number(expiresAtSec) > 0)
        ? Math.min(Number(expiresAtSec) * 1000, maxDeadline)
        : maxDeadline;
    let pollTimer = null;
    let countdownTimer = null;
    let isPolling = false;
    let stopped = false;
    let serviceBuilding = false;
    let deadlineReached = (deadline <= startedAt);
    let finalCheckInFlight = false;

    function html() {
        const reopenBtn = gatewayUrl
            ? `<button type="button" class="btn btn-primary btn-block mt-sm" id="watch-reopen">
                    ${icon('arrowLeft', 'class="ico ico-leading"')}
                    <span>بازکردن مجدد صفحه پرداخت</span>
               </button>`
            : '';

        view.innerHTML = `
            <article class="payment-flow gateway-page watch-card">
                <div class="gateway-body">
                    <span class="gateway-live">${icon('refresh')} پیگیری خودکار پرداخت</span>
                    <div class="watch-spinner-wrap" aria-hidden="true">
                        <svg class="watch-spinner-ring" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" class="watch-spinner-track"/>
                            <circle cx="40" cy="40" r="34" class="watch-spinner-arc"/>
                        </svg>
                        <div class="watch-spinner-glyph">
                            ${icon('hourglass', 'class="ico"')}
                        </div>
                    </div>
                    <h2 style="margin:14px 0 4px;font-size:18px" id="watch-title">${escapeHtml(title)}</h2>
                    <p class="muted" id="watch-subtitle" style="font-size:13px">${escapeHtml(subtitle)}</p>

                    <div class="watch-countdown mt-md" id="watch-countdown">
                        <span class="glyph">${icon('hourglass', 'class="ico"')}</span>
                        <span class="mono" id="watch-time">${fmtCountdown(Math.max(0, Math.floor((deadline - Date.now()) / 1000)))}</span>
                        <span class="muted" style="font-size:12px">تا پایان مهلت پیگیری</span>
                    </div>

                    <p class="gateway-tracking">
                        کد پیگیری: <span class="mono gold">${escapeHtml(orderId)}</span>
                    </p>

                    <div class="gateway-actions">
                        <button type="button" class="btn btn-ghost btn-block is-locked" id="watch-check" style="flex:1">
                            ${icon('refresh', 'class="ico ico-leading"')}
                            <span class="watch-check-label">بررسی مجدد</span>
                        </button>
                        ${reopenBtn}
                    </div>

                    ${isAtlaspay ? `<div id="watch-atlaspay-receipt" class="mt-md" hidden>
                        <p class="muted" id="watch-atlaspay-receipt-note" style="font-size:12px;line-height:1.8;white-space:pre-line"></p>
                        <button type="button" class="btn btn-primary btn-block mt-sm" id="watch-send-receipt">📎 ارسال رسید</button>
                    </div>` : ''}
                    <button type="button" class="btn btn-ghost btn-block mt-sm" id="watch-cancel">
                        ${icon('xCircle', 'class="ico ico-leading"')}
                        <span>${isRialit ? 'انصراف و پرداخت جدید' : 'انصراف'}</span>
                    </button>
                </div>
            </article>`;
    }

    function updateAtlaspayReceipt(obj) {
        if (!isAtlaspay) return;
        const receipt = obj.atlaspay_receipt;
        const url = String(receipt?.url || '');
        const expiresAt = Number(receipt?.expires_at || 0);
        atlaspayReceiptUrl = /^https:\/\/(?:t\.me|telegram\.me)\/atlaspaybot\?start=r_[A-Za-z0-9_-]+$/i.test(url)
            && !['paid', 'reject', 'cancelled', 'expire'].includes(String(obj.payment_status || ''))
            && expiresAt * 1000 > Date.now() ? url : '';
        atlaspayReceiptExpiresAt = expiresAt;
        const host = view.querySelector('#watch-atlaspay-receipt');
        if (host) host.hidden = !atlaspayReceiptUrl;
        const note = view.querySelector('#watch-atlaspay-receipt-note');
        if (note) note.textContent = String(receipt?.message || '');
    }

    function updateCountdown() {
        if (isAtlaspay && atlaspayReceiptExpiresAt * 1000 <= Date.now()) {
            atlaspayReceiptUrl = '';
            const receiptHost = view.querySelector('#watch-atlaspay-receipt');
            if (receiptHost) receiptHost.hidden = true;
        }
        const $time = view.querySelector('#watch-time');
        if (!$time) return;
        const remain = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
        $time.textContent = fmtCountdown(remain);
        if (remain <= 0 && !deadlineReached) {
            enterDeadlineState();
        }
    }

    function enterDeadlineState() {
        deadlineReached = true;

        if (isRialit) {
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            const subR = view.querySelector('#watch-subtitle');
            if (subR) subR.textContent = 'مهلت ورود به پرداخت تمام شده؛ در انتظار نتیجه';
            const timeR = view.querySelector('#watch-time');
            if (timeR) timeR.textContent = fmtCountdown(0);
            const reopenR = view.querySelector('#watch-reopen');
            if (reopenR) reopenR.remove();
            const checkR = view.querySelector('#watch-check');
            if (checkR) {
                checkR.classList.remove('is-locked', 'btn-ghost');
                checkR.classList.add('btn-primary');
                const labelR = checkR.querySelector('.watch-check-label');
                if (labelR) labelR.textContent = 'بررسی وضعیت';
            }
            pollOnce();
            pollTimer = setInterval(pollOnce, 60000);
            return;
        }


        if (pollTimer)      { clearInterval(pollTimer);      pollTimer = null; }
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }


        const $sub = view.querySelector('#watch-subtitle');
        if ($sub) $sub.textContent = 'زمان انتظار تمام شد — برای دریافت نتیجه قطعی روی «بررسی نهایی» بزنید.';

        const $time = view.querySelector('#watch-time');
        if ($time) $time.textContent = fmtCountdown(0);


        const $check = view.querySelector('#watch-check');
        if ($check) {
            $check.classList.remove('is-locked', 'btn-ghost');
            $check.classList.add('btn-primary');
            const $label = $check.querySelector('.watch-check-label');
            if ($label) $label.textContent = 'بررسی نهایی';
        }
    }

    function setSubtitle(msg) {
        const $sub = view.querySelector('#watch-subtitle');
        if ($sub) $sub.textContent = msg;
    }

    async function pollOnce() {
        if (isPolling || stopped) return;
        isPolling = true;
        try {
            const res = await call('payment_status', { params: { order_id: orderId } });
            const obj = res?.obj || {};
            const status = String(obj.payment_status || '');
            updateAtlaspayReceipt(obj);
            const meta = linkMeta(obj);
            if (isRialit && meta && meta.link_available && obj.gateway_url) {
                currentGatewayUrl = String(obj.gateway_url);
                if (!view.querySelector('#watch-reopen')) {
                    const actions = view.querySelector('#watch-check')?.parentElement;
                    if (actions) {
                        const reopen = document.createElement('button');
                        reopen.type = 'button';
                        reopen.id = 'watch-reopen';
                        reopen.className = 'btn btn-primary btn-block mt-sm';
                        reopen.textContent = 'بازکردن مجدد صفحه پرداخت';
                        reopen.addEventListener('click', () => openGatewayLink(currentGatewayUrl, { keepMiniAppOpen }));
                        actions.appendChild(reopen);
                    }
                }
            }
            if (isRialit && meta && !meta.link_available) {
                const reopen = view.querySelector('#watch-reopen');
                if (reopen) reopen.remove();
                if (meta.awaiting_result && !deadlineReached) enterDeadlineState();
            }

            if (status === 'paid') {
                if (isRialit && meta && !meta.fulfillment_done) {
                    serviceBuilding = true;
                    setSubtitle(finalizingMsg);
                    return;
                }

                if (mode === 'recharge' || mode === 'pending_action') {
                    cleanup();
                    hapticNotify('success');
                    if (typeof onSuccess === 'function') onSuccess(obj);
                } else if (obj.is_service_ready) {
                    cleanup();
                    hapticNotify('success');
                    if (typeof onSuccess === 'function') onSuccess(obj);
                } else if (!serviceBuilding) {
                    serviceBuilding = true;
                    setSubtitle(finalizingMsg);
                }
            } else if (status === 'reject' || status === 'cancelled' || status === 'expire') {
                cleanup();
                hapticNotify('error');
                if (typeof onFail === 'function') onFail(obj);
            }
        } catch (err) {
            console.warn('[gateway-watch] poll failed', err);
        } finally {
            isPolling = false;
        }
    }


    async function runFinalCheck() {
        if (finalCheckInFlight || stopped) return;
        finalCheckInFlight = true;

        const $check = view.querySelector('#watch-check');
        const $label = $check ? $check.querySelector('.watch-check-label') : null;
        const oldLabel = $label ? $label.textContent : '';
        if ($check) $check.disabled = true;
        if ($label) $label.textContent = 'در حال بررسی…';

        try {
            const res = await call('payment_status', { params: { order_id: orderId } });
            const obj = res?.obj || {};
            const status = String(obj.payment_status || '');
            updateAtlaspayReceipt(obj);

            const isReady = (!isRialit || !linkMeta(obj) || !!linkMeta(obj).fulfillment_done)
                && ((mode === 'recharge' || mode === 'pending_action') ? true : !!obj.is_service_ready);
            if (status === 'paid' && isReady) {
                cleanup();
                hapticNotify('success');
                if (typeof onSuccess === 'function') onSuccess(obj);
                return;
            }

            if (status === 'paid' && !isReady) {
                finalCheckInFlight = false;
                if ($check) $check.disabled = false;
                if ($label) $label.textContent = oldLabel || 'بررسی نهایی';
                toast('پرداخت تایید شد ولی سرویس هنوز ساخته نشده — لطفاً با پشتیبانی تماس بگیرید.', 'warn', 5000);
                return;
            }

            if (status !== 'reject' && status !== 'cancelled' && status !== 'expire') {
                finalCheckInFlight = false;
                if ($check) $check.disabled = false;
                if ($label) $label.textContent = oldLabel || 'بررسی نهایی';
                toast('هنوز تایید نشده — ممکن است تایید نهایی از سمت درگاه کمی زمان ببرد. بعداً دوباره بررسی کنید.', 'warn', 5000);
                return;
            }


            const finalObj = Object.assign({}, obj, {
                reason: obj.reason || 'تراکنش تایید نشد',
                order_id: obj.order_id || orderId,
            });
            cleanup();
            hapticNotify('error');
            if (typeof onFail === 'function') onFail(finalObj);
        } catch (err) {
            console.warn('[gateway-watch] final check failed', err);
            hapticNotify('error');
            toast(err.message || 'خطا در بررسی نهایی', 'error', 4000);


            finalCheckInFlight = false;
            if ($check) $check.disabled = false;
            if ($label) $label.textContent = oldLabel || 'بررسی نهایی';
        }
    }

    function handleVisibility() {
        if (stopped) return;
        if (document.visibilityState === 'visible') {
            updateCountdown();
            pollOnce();
        }
    }

    function cleanup() {
        if (stopped) return;
        stopped = true;
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        document.removeEventListener('visibilitychange', handleVisibility);
        if (_gwGetActive() === cleanup) _gwSetActive(null);
    }

    _gwSetActive(cleanup);

    html();

    const $receipt = view.querySelector('#watch-send-receipt');
    if ($receipt) {
        $receipt.addEventListener('click', () => {
            if (stopped || !atlaspayReceiptUrl || atlaspayReceiptExpiresAt * 1000 <= Date.now()) return;
            hapticImpact('light');
            openGatewayLink(atlaspayReceiptUrl, { keepMiniAppOpen: false });
        });
    }

    if (deadlineReached) {
        deadlineReached = false;
        enterDeadlineState();
    }

    const $check = view.querySelector('#watch-check');
    if ($check) {
        $check.addEventListener('click', () => {
            hapticImpact('light');
            if (!deadlineReached) {
                const waitMinutes = Math.max(1, Math.round(Number(timeoutSec) / 60));
                toast(`لطفاً تا پایان زمان انتظار (${waitMinutes} دقیقه) صبر کنید، سپس روی «بررسی نهایی» بزنید.`, 'warn', 4500);
                return;
            }
            runFinalCheck();
        });
    }

    const $reopen = view.querySelector('#watch-reopen');
    if ($reopen) {
        $reopen.addEventListener('click', () => {
            hapticImpact('light');
            openGatewayLink(currentGatewayUrl, { keepMiniAppOpen });
        });
    }

    const $cancel = view.querySelector('#watch-cancel');
    if ($cancel) {
        let cancelling = false;
        $cancel.addEventListener('click', async () => {
            if (cancelling) return;
            cancelling = true;
            if (isRialit && !await showConfirm(`از این پرداخت انصراف می‌دهید؟ می‌توانید پرداخت جدید بسازید. لینک قبلی در ${gatewayFaName} باطل نمی‌شود؛ اگر بعداً تأیید شود، مبلغ سفارش به کیف پول اضافه می‌شود.`)) {
                cancelling = false;
                return;
            }
            hapticImpact('light');
            const $lbl = $cancel.querySelector('span');
            const oldLbl = $lbl ? $lbl.textContent : '';
            if ($lbl) $lbl.textContent = 'در حال لغو…';
            $cancel.disabled = true;

            try {
                await call('crypto_cancel_invoice', { method: 'POST', body: { order_id: orderId } });
            } catch (err) {
                cancelling = false;
                $cancel.disabled = false;
                if ($lbl) $lbl.textContent = oldLbl;
                toast(err.message || 'خطا در لغو فاکتور', 'error', 4000);
                return;
            }

            cleanup();
            if (typeof onCancel === 'function') {
                try { onCancel(); return; } catch (_) {  }
            }
            try { window.location.hash = '#/'; } catch (_) {}
        });
    }


    pollOnce();
    if (isRialit && deadlineReached) {
        return cleanup;
    }
    pollTimer = setInterval(pollOnce, Math.max(2, pollEverySec) * 1000);
    countdownTimer = setInterval(updateCountdown, 500);
    document.addEventListener('visibilitychange', handleVisibility);

    return cleanup;
}
