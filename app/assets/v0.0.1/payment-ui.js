import { escapeHtml, copyToClipboard, toast } from './utils.js';
import { hapticImpact, hapticNotify } from './telegram.js';
import { icon } from './icons.js';
import { getToken } from './state.js';


export function iconForMethod(m) {
    const id = String(m.id || '').toLowerCase();
    if (id.startsWith('carttocart'))   return 'creditCard';
    if (id.startsWith('iranpay'))      return 'flower';
    if (id === 'aqayepardakht')        return 'flower';
    if (id === 'zarinpal')             return 'coin';
    if (id === 'zarinpey')             return 'coin';
    if (id === 'plisio')               return 'exchange';
    if (id === 'nowpayment')           return 'exchange';
    if (id === 'digitaltron')          return 'exchange';
    if (id === 'paymentnotverify')     return 'checkCircle';
    if (id === 'startelegrams')        return 'star';
    return 'creditCard';
}


export function exchangeSvgIcon(extraClass = 'ico-accent') {
    return `<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" class="ico ${extraClass}" fill="currentColor" aria-hidden="true"><path d="M0 5a5.002 5.002 0 0 0 4.027 4.905 6.46 6.46 0 0 1 .544-2.073C3.695 7.536 3.132 6.864 3 5.91h-.5v-.426h.466V5.05c0-.046 0-.093.004-.135H2.5v-.427h.511C3.236 3.24 4.213 2.5 5.681 2.5c.316 0 .59.031.819.085v.733a3.46 3.46 0 0 0-.815-.082c-.919 0-1.538.466-1.734 1.252h1.917v.427h-1.98c-.003.046-.003.097-.003.147v.422h1.983v.427H3.93c.118.602.468 1.03 1.005 1.229a6.5 6.5 0 0 1 4.97-3.113A5.002 5.002 0 0 0 0 5zm16 5.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0zm-7.75 1.322c.069.835.746 1.485 1.964 1.562V14h.54v-.62c1.259-.086 1.996-.74 1.996-1.69 0-.865-.563-1.31-1.57-1.54l-.426-.1V8.374c.54.06.884.347.966.745h.948c-.07-.804-.779-1.433-1.914-1.502V7h-.54v.629c-1.076.103-1.808.732-1.808 1.622 0 .787.544 1.288 1.45 1.493l.358.085v1.78c-.554-.08-.92-.376-1.003-.787H8.25zm1.96-1.895c-.532-.12-.82-.364-.82-.732 0-.41.311-.719.824-.809v1.54h-.005zm.622 1.044c.645.145.943.38.943.796 0 .474-.37.8-1.02.86v-1.674l.077.018z"/></svg>`;
}

export function stripEmoji(s) {
    return String(s || '')
        .replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}\u{1F300}-\u{1F9FF}\s]+/u, '')
        .replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}\u{1F300}-\u{1F9FF}]+$/u, '')
        .trim();
}

export function fmtNum(n) {
    return Number(n || 0).toLocaleString('fa-IR');
}

export function openLink(url) {
    const tg = window.Telegram && window.Telegram.WebApp;
    const u = String(url || '');
    const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
    if (tg && isTme && typeof tg.openTelegramLink === 'function') {
        tg.openTelegramLink(u);
    } else if (tg && typeof tg.openLink === 'function') {
        tg.openLink(u);
    } else {
        window.open(u, '_blank', 'noopener');
    }
}


export function renderMethodCard(m) {
    const key = iconForMethod(m);
    const visual = (key === 'exchange')
        ? exchangeSvgIcon('ico-accent')
        : icon(key, 'class="ico ico-accent"');
    return `
        <button type="button" class="pay-card" data-method="${escapeHtml(m.id)}">
            ${visual}
            <span class="pay-label">${escapeHtml(stripEmoji(m.label))}</span>
            ${icon('chevronLeft', 'class="ico pay-arrow"')}
        </button>`;
}

export function renderCardToCard(d) {
    const cardNumber = String(d.card_number || '').replace(/\s+/g, '');
    const formatted = cardNumber.replace(/(\d{4})(?=\d)/g, '$1 ');
    const amount = Number(d.amount || 0);
    return `
        <div class="card-section cart-info-card">
            <div class="cart-banner">
                ${icon('creditCard', 'class="ico ico-xxl ico-accent"')}
                <h3>کارت‌به‌کارت — اطلاعات واریز</h3>
                <p class="muted center" style="font-size:12px">${escapeHtml(d.message || 'مبلغ را به کارت زیر واریز کنید.')}</p>
            </div>

            <div class="kv mt-md">
                <span class="kv-label">شماره کارت</span>
                <span class="kv-value mono accent" id="card-num-display" data-copy="${escapeHtml(cardNumber)}">${escapeHtml(formatted)}</span>
            </div>
            <button type="button" class="btn btn-ghost btn-block mt-sm copy-btn-cart" data-copy="${escapeHtml(cardNumber)}">
                ${icon('copy', 'class="ico ico-leading"')}
                <span>کپی شماره کارت</span>
            </button>

            <div class="kv mt-md">
                <span class="kv-label">به نام</span>
                <span class="kv-value">${escapeHtml(d.name_card || '—')}</span>
            </div>

            <div class="kv mt-sm">
                <span class="kv-label">مبلغ دقیق</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amount)} تومان</span>
            </div>
            <button type="button" class="btn btn-ghost btn-block mt-sm copy-btn-cart" data-copy="${amount}">
                ${icon('copy', 'class="ico ico-leading"')}
                <span>کپی مبلغ</span>
            </button>

            ${d.auto_confirm ? `
                <div class="callout mt-md">
                    ${icon('info', 'class="ico ico-leading"')}
                    <span>مبلغ دقیقاً همانطور که نمایش داده شده واریز شود — تأیید خودکار است.</span>
                </div>` : ''}

            <div class="card-section mt-md receipt-upload-zone">
                <p class="section-title">${icon('fileText')} ارسال رسید پرداخت</p>
                <p class="muted" style="font-size:13px">پس از واریز، عکس رسید را آپلود کنید تا برای ادمین ارسال شود.</p>

                <input type="file" id="receipt-file" accept="image/*" capture="environment" style="display:none" />
                <button type="button" id="receipt-pick" class="btn btn-primary btn-block mt-sm">
                    ${icon('download', 'class="ico ico-leading"')}
                    <span>انتخاب عکس رسید</span>
                </button>

                <div id="receipt-preview" class="hidden mt-sm">
                    <img id="receipt-preview-img" alt="پیش‌نمایش" class="receipt-preview-img" />
                    <button type="button" id="receipt-submit" class="btn btn-primary btn-block mt-sm">
                        ${icon('send', 'class="ico ico-leading"')}
                        <span class="receipt-submit-label">ارسال رسید برای ادمین</span>
                    </button>
                </div>

                <p class="muted mono mt-md" style="font-size:11px">کد پیگیری: ${escapeHtml(d.order_id)}</p>
            </div>
        </div>`;
}


export function wireCardToCard(view, d, opts = {}) {
    const successText = opts.successText
        || 'پس از تأیید ادمین، حساب شما شارژ می‌شود.';
    const successCta = opts.successCta || { href: '#/account', label: 'بازگشت به حساب' };
    const onSuccess = typeof opts.onSuccess === 'function' ? opts.onSuccess : null;

    view.querySelectorAll('.copy-btn-cart').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const txt = btn.dataset.copy || '';
            const ok = await copyToClipboard(txt);
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            hapticImpact('light');
        });
    });

    const $file = view.querySelector('#receipt-file');
    const $pick = view.querySelector('#receipt-pick');
    const $preview = view.querySelector('#receipt-preview');
    const $previewImg = view.querySelector('#receipt-preview-img');
    const $submit = view.querySelector('#receipt-submit');
    if (!$file || !$pick) return;

    $pick.addEventListener('click', () => $file.click());

    $file.addEventListener('change', () => {
        const file = $file.files && $file.files[0];
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) {
            toast('حجم فایل نباید بیشتر از 8 مگابایت باشد', 'error', 4000);
            $file.value = '';
            return;
        }
        const url = URL.createObjectURL(file);
        $previewImg.src = url;
        $preview.classList.remove('hidden');
    });

    $submit.addEventListener('click', async () => {
        const file = $file.files && $file.files[0];
        if (!file) {
            toast('ابتدا عکس رسید را انتخاب کنید', 'warn');
            return;
        }

        const $label = $submit.querySelector('.receipt-submit-label');
        const oldLabel = $label.textContent;
        $submit.disabled = true;
        $pick.disabled = true;
        $label.textContent = 'در حال ارسال…';

        try {
            const fd = new FormData();
            fd.append('order_id', d.order_id);
            fd.append('photo', file);


            const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
            const tok = getToken();
            const res = await fetch(`${apiUrl}/miniapp.php?actions=payment_receipt`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + (tok || '') },
                body: fd,
            });
            let envelope = null;
            try { envelope = await res.json(); } catch (_) {}
            if (!res.ok || !envelope || !envelope.status) {
                const msg = (envelope && envelope.msg) || `Upload failed (${res.status})`;
                throw new Error(msg);
            }

            hapticNotify('success');


            const serverMsg = (envelope.obj && envelope.obj.message) || '';
            const bodyText = serverMsg || successText;

            const $result = view.querySelector('#pay-result-host');
            if ($result) {
                $result.innerHTML = `
                    <div class="card-section">
                        <div class="empty">
                            ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                            <h3>رسید ارسال شد</h3>
                            <p class="muted">${escapeHtml(bodyText)}</p>
                            <p class="muted mono mt-sm" style="font-size:12px">کد پیگیری: ${escapeHtml(d.order_id)}</p>
                            <a href="${escapeHtml(successCta.href)}" class="btn btn-primary mt-md">
                                ${icon('user', 'class="ico ico-leading"')}
                                <span>${escapeHtml(successCta.label)}</span>
                            </a>
                        </div>
                    </div>`;


                const $form = view.querySelector('#pay-form-host');
                if ($form) $form.innerHTML = '';
            }
            if (onSuccess) {
                try { onSuccess(envelope); } catch (_) {  }
            }
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ارسال رسید', 'error', 5000);
            $submit.disabled = false;
            $pick.disabled = false;
            $label.textContent = oldLabel;
        }
    });
}


let _gatewayWatchModulePromise = null;
function loadGatewayWatch() {
    if (_gatewayWatchModulePromise) return _gatewayWatchModulePromise;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const ver = (cfg.version || cfg.cacheBust || Date.now()).toString();
    try {
        const url = new URL('./pages/gateway-watch.js', import.meta.url);
        url.searchParams.set('v', ver);
        _gatewayWatchModulePromise = import(url.href).catch(() => {
            _gatewayWatchModulePromise = null;
            return import('./pages/gateway-watch.js');
        });
    } catch (e) {
        _gatewayWatchModulePromise = import('./pages/gateway-watch.js');
    }
    return _gatewayWatchModulePromise;
}

let _successModulePromise = null;
function loadSuccessModule() {
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


function isAutoConfirmGateway(methodId) {
    const id = String(methodId || '').toLowerCase();
    return (
        id === 'plisio' || id === 'nowpayment' || id === 'digitaltron' ||
        id.startsWith('iranpay') ||
        id === 'aqayepardakht' || id === 'zarinpal' || id === 'zarinpey'
    );
}

function isCryptoMethod(methodId) {
    const id = String(methodId || '').toLowerCase();
    return id === 'plisio' || id === 'nowpayment' || id === 'digitaltron';
}


function renderGatewaySuccess(view, statusObj, opts) {
    const isDirectBuy = !!opts.purchaseUsername;
    if (isDirectBuy && statusObj.service) {

        loadSuccessModule().then((mod) => {
            if (mod && typeof mod.renderServiceSuccess === 'function') {
                mod.renderServiceSuccess(view, {
                    order_id: statusObj.order_id,
                    service: statusObj.service,
                }, {
                    productName: statusObj.service.product_name || '',
                    panelName: statusObj.service.panel_name || '',
                });
            } else {
                renderGenericSuccess(view, statusObj, opts);
            }
        }).catch(() => renderGenericSuccess(view, statusObj, opts));
        return;
    }

    renderGenericSuccess(view, statusObj, opts);
}

function renderGenericSuccess(view, statusObj, opts) {
    const amount = Number(statusObj.amount || 0).toLocaleString('en-US');
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/payment/success</span>
            </header>
            <div class="card-body" style="text-align:center;padding:22px">
                <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                </div>
                <h2 style="margin:6px 0;font-size:18px">پرداخت تایید شد</h2>
                <p class="muted">مبلغ ${escapeHtml(amount)} تومان به حساب شما اضافه شد.</p>
                <div class="kv mt-md">
                    <span class="kv-label">کد پیگیری</span>
                    <span class="kv-value mono gold">${escapeHtml(statusObj.order_id || '—')}</span>
                </div>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                    <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                </div>
            </div>
        </article>`;
}

function renderGatewayFail(view, statusObj, opts) {
    const reason = statusObj.reason || 'پرداخت تایید نشد';
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/payment/fail</span>
            </header>
            <div class="card-body" style="text-align:center;padding:22px">
                <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">
                    ${icon('xCircle', 'class="ico ico-xxl ico-warn"')}
                </div>
                <h2 style="margin:6px 0;font-size:18px">پرداخت ناموفق بود</h2>
                <p class="muted">${escapeHtml(reason)}</p>
                <p class="muted mt-sm" style="font-size:12px">
                    کد پیگیری: <span class="mono">${escapeHtml(statusObj.order_id || '—')}</span>
                </p>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                    <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                </div>
            </div>
        </article>`;
}

function renderGatewayTimeout(view, opts) {
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/payment/timeout</span>
            </header>
            <div class="card-body" style="text-align:center;padding:22px">
                <div style="font-size:42px;line-height:1;color:var(--yellow);margin:4px 0 10px">
                    ${icon('clock', 'class="ico ico-xxl ico-warn"')}
                </div>
                <h2 style="margin:6px 0;font-size:18px">زمان انتظار به پایان رسید</h2>
                <p class="muted">پرداخت در ۱۰ دقیقه‌ی گذشته تایید نشد. اگر پول از حساب شما کسر شده، با پشتیبانی تماس بگیرید.</p>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                    <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                </div>
            </div>
        </article>`;
}


export function startUrlGatewayFlow(rootView, methodId, obj, opts = {}) {
    const orderId = String(obj.order_id || '');
    const url = String(obj.url || '');
    if (url) openLink(url);


    const watchHost = rootView || (opts.host ? opts.host : null);
    if (!watchHost || !orderId) return;

    loadGatewayWatch().then((mod) => {
        if (!mod || typeof mod.startGatewayWatch !== 'function') return;
        const cleanup = mod.startGatewayWatch(watchHost, {
            orderId,
            title: isCryptoMethod(methodId) ? 'در انتظار تایید پرداخت ارزی…' : 'در انتظار تایید پرداخت…',
            subtitle: 'به‌محض تایید درگاه، صفحه به‌روزرسانی می‌شود.',
            gatewayUrl: url,
            isCrypto: isCryptoMethod(methodId),
            mode: opts.purchaseUsername ? 'direct_buy' : 'recharge',
            timeoutSec: 600,
            pollEverySec: 5,
            onSuccess: (statusObj) => renderGatewaySuccess(watchHost, statusObj, opts),
            onFail: (statusObj) => renderGatewayFail(watchHost, statusObj, opts),
            onTimeout: () => renderGatewayTimeout(watchHost, opts),
        });
        if (typeof cleanup === 'function' && opts.onCleanup) opts.onCleanup(cleanup);
    }).catch((err) => {
        console.warn('[payment-ui] gateway-watch load failed', err);
    });
}


export function handleInitResult(view, methodId, obj, opts = {}) {
    const $result = view.querySelector('#pay-result-host');
    const $form = view.querySelector('#pay-form-host');
    if (!$result) return;

    if (obj.kind === 'url' && obj.url) {

        if ($form) $form.innerHTML = '';


        const rootView = opts.rootView && opts.rootView instanceof HTMLElement ? opts.rootView : view;

        if (isAutoConfirmGateway(methodId)) {
            startUrlGatewayFlow(rootView, methodId, obj, opts);
            return;
        }


        openLink(obj.url);
        $result.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                    <h3>به درگاه پرداخت منتقل می‌شوید</h3>
                    <p class="muted">${escapeHtml(obj.message || 'برای تکمیل پرداخت، به درگاه منتقل می‌شوید.')}</p>
                    <a class="btn btn-primary mt-md" href="${escapeHtml(obj.url)}" target="_blank" rel="noopener">
                        ${icon('arrowLeft', 'class="ico ico-leading"')}
                        <span>باز کردن درگاه</span>
                    </a>
                </div>
            </div>`;
        return;
    }

    if (obj.kind === 'carttocart') {
        if ($form) $form.innerHTML = '';
        $result.innerHTML = renderCardToCard(obj);
        wireCardToCard(view, obj, opts);
        return;
    }

    if (obj.kind === 'manual') {
        $result.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                    <h3>درخواست شما ثبت شد</h3>
                    <p class="muted">${escapeHtml(obj.message || 'ادمین پس از بررسی، حساب شما را شارژ می‌کند.')}</p>
                    ${obj.order_id ? `<p class="muted mono mt-sm" style="font-size:12px">کد پیگیری: ${escapeHtml(obj.order_id)}</p>` : ''}
                </div>
            </div>`;
        return;
    }


    $result.innerHTML = `
        <div class="card-section">
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>${escapeHtml(obj.message || 'انجام شد')}</h3>
            </div>
        </div>`;
}


export async function renderInlinePayment(rootView, host, pay, opts = {}) {
    const callFn = opts.callFn;
    if (typeof callFn !== 'function') {
        console.error('[renderInlinePayment] opts.callFn (api call helper) is required');
        return;
    }

    const amountDue = Number(pay.amount_due || 0);
    const username  = String(pay.username || pay.purchase_username || '');
    const balance   = Number(pay.balance || 0);
    const price     = Number(pay.price || 0);
    const showBreakdown = opts.showBreakdown !== false;
    const bannerTitle = opts.bannerTitle || 'پرداخت مبلغ کسری';
    const bannerHint  = opts.bannerHint  || 'موجودی شما کافی نیست. مبلغ کسری را پرداخت کنید؛ پس از تأیید ادمین، عملیات به‌صورت خودکار انجام خواهد شد.';

    host.innerHTML = `
        <div class="card-section cart-info-card">
            <div class="cart-banner">
                ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                <h3>${escapeHtml(bannerTitle)}</h3>
                <p class="muted center" style="font-size:12px">${escapeHtml(bannerHint)}</p>
            </div>
            ${showBreakdown ? `
            <div class="kv mt-md">
                <span class="kv-label">${icon('wallet')} موجودی فعلی</span>
                <span class="kv-value mono">${escapeHtml(fmtNum(balance))} تومان</span>
            </div>
            <div class="kv">
                <span class="kv-label">${icon('coin')} قیمت</span>
                <span class="kv-value mono">${escapeHtml(fmtNum(price))} تومان</span>
            </div>` : ''}
            <div class="kv ${showBreakdown ? '' : 'mt-md'}">
                <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
            </div>
            ${username ? `
            <div class="kv">
                <span class="kv-label">${icon('user')} نام کاربری</span>
                <span class="kv-value mono">${escapeHtml(username)}</span>
            </div>` : ''}

            <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
            <div class="payment-methods" id="inline-pay-list"></div>
            <div id="pay-form-host"></div>
            <div id="pay-result-host"></div>
        </div>
    `;

    const $list = host.querySelector('#inline-pay-list');
    let methods = [];
    try {
        const r = await callFn('payment_methods');
        methods = Array.isArray(r?.obj?.methods) ? r.obj.methods : [];
    } catch (err) {
        $list.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    if (!methods.length) {
        $list.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
            </div>`;
        return;
    }

    $list.innerHTML = methods.map(renderMethodCard).join('');
    $list.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-method]');
        if (!card) return;
        $list.querySelectorAll('.pay-card').forEach((c) => c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        hapticImpact('light');

        const method = methods.find((m) => m.id === card.dataset.method);
        if (!method) return;

        if (method.kind === 'url' && method.url) {
            openLink(method.url);
            return;
        }

        try {
            const r = await callFn('payment_init', {
                method: 'POST',
                body: {
                    method: method.id,
                    amount: amountDue,
                    purchase_username: username,
                },
            });
            const obj = r?.obj || {};


            const $methods = host.querySelector('#inline-pay-list');
            if ($methods) $methods.style.display = 'none';
            const $title = $methods ? $methods.previousElementSibling : null;
            if ($title && $title.classList && $title.classList.contains('section-title')) {
                $title.style.display = 'none';
            }

            handleInitResult(host, method.id, obj, {
                rootView,
                purchaseUsername: username,
                successText: opts.successText || 'پس از تأیید پرداخت، عملیات به‌صورت خودکار انجام خواهد شد.',
                successCta:  opts.successCta || { href: '#/', label: 'بازگشت به خانه' },
                onSuccess:   opts.onSuccess,
            });
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        }
    });
}
