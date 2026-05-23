import { call } from '../api.js';
import { escapeHtml, copyToClipboard, toast } from '../utils.js';
import { icon } from '../icons.js';
import { hapticImpact, hapticNotify, showConfirm } from '../telegram.js';
import { startGatewayWatch } from './gateway-watch.js';


function iconForCurrency(code) {
    const c = String(code || '').toUpperCase();
    if (c === 'TRX') return 'tron';
    if (c === 'TON') return 'ton';
    if (c.startsWith('USDT')) return 'tether';
    return 'coin';
}

function colorForCurrency(code) {
    const c = String(code || '').toUpperCase();
    if (c === 'TRX') return '#e53935';
    if (c === 'TON') return '#0098ea';
    if (c.startsWith('USDT')) return '#26a17b';
    return 'var(--gold)';
}


function svgForCurrency(code) {
    const c = String(code || '').toUpperCase();
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const prefix = cfg.assetPrefix || '/';
    const ver = encodeURIComponent(String(cfg.version || cfg.cacheBust || Date.now()));
    let file = null;
    if (c === 'TRX') file = 'trx.svg';
    else if (c === 'TON') file = 'ton.svg';
    else if (c.startsWith('USDT')) file = 'usdt.svg';
    if (!file) return '';
    return prefix + 'assets/branding/coins/' + file + '?v=' + ver;
}


function exchangeIcon(extraClass = '') {
    return `<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" class="ico ${extraClass}" fill="currentColor" aria-hidden="true"><path d="M0 5a5.002 5.002 0 0 0 4.027 4.905 6.46 6.46 0 0 1 .544-2.073C3.695 7.536 3.132 6.864 3 5.91h-.5v-.426h.466V5.05c0-.046 0-.093.004-.135H2.5v-.427h.511C3.236 3.24 4.213 2.5 5.681 2.5c.316 0 .59.031.819.085v.733a3.46 3.46 0 0 0-.815-.082c-.919 0-1.538.466-1.734 1.252h1.917v.427h-1.98c-.003.046-.003.097-.003.147v.422h1.983v.427H3.93c.118.602.468 1.03 1.005 1.229a6.5 6.5 0 0 1 4.97-3.113A5.002 5.002 0 0 0 0 5zm16 5.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0zm-7.75 1.322c.069.835.746 1.485 1.964 1.562V14h.54v-.62c1.259-.086 1.996-.74 1.996-1.69 0-.865-.563-1.31-1.57-1.54l-.426-.1V8.374c.54.06.884.347.966.745h.948c-.07-.804-.779-1.433-1.914-1.502V7h-.54v.629c-1.076.103-1.808.732-1.808 1.622 0 .787.544 1.288 1.45 1.493l.358.085v1.78c-.554-.08-.92-.376-1.003-.787H8.25zm1.96-1.895c-.532-.12-.82-.364-.82-.732 0-.41.311-.719.824-.809v1.54h-.005zm.622 1.044c.645.145.943.38.943.796 0 .474-.37.8-1.02.86v-1.674l.077.018z"/></svg>`;
}


let _successModulePromise = null;
function loadSuccessModule() {
    if (_successModulePromise) return _successModulePromise;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const ver = (cfg.version || cfg.cacheBust || Date.now()).toString();
    try {
        const url = new URL('./service-success.js', import.meta.url);
        url.searchParams.set('v', ver);
        _successModulePromise = import(url.href).catch(() => {
            _successModulePromise = null;
            return import('./service-success.js');
        });
    } catch (e) {
        _successModulePromise = import('./service-success.js');
    }
    return _successModulePromise;
}


export function renderCryptoOffline(view, opts = {}) {

    const state = {
        amount: Number(opts.amount || 0),
        purchaseUsername: opts.purchaseUsername || null,
        currency: null,
        iranianMode: null,
        invoice: null,
        cleanup: null,
    };

    function setupBack(onClick) {
        const $back = view.querySelector('#crypto-back');
        if ($back) $back.addEventListener('click', (e) => { e.preventDefault(); onClick(); });
    }


    async function step1Currencies() {
        view.innerHTML = `
            <a href="#" class="page-back" id="crypto-back">
                ${icon('chevronRight', 'class="ico"')}
                <span>بازگشت</span>
            </a>
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/currency</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${exchangeIcon('ico-accent')} ارز آفلاین (هش‌چکر)</p>
                    <h3 style="margin:4px 0 14px;font-size:17px">ارز موردنظر را انتخاب کنید</h3>
                    <p class="muted" style="font-size:13px">پس از انتخاب ارز و حالت، آدرس کیف‌پول و مبلغ دقیق نمایش داده می‌شود. بعد از پرداخت، هش تراکنش را برای تایید خودکار وارد کنید.</p>
                    <div id="crypto-list" class="list mt-md">
                        <div class="skeleton skeleton-row"></div>
                        <div class="skeleton skeleton-row"></div>
                        <div class="skeleton skeleton-row"></div>
                    </div>
                </div>
            </article>`;
        setupBack(() => opts.onBack && opts.onBack());

        let data;
        try {
            const res = await call('crypto_currencies');
            data = res?.obj || {};
        } catch (err) {
            view.querySelector('#crypto-list').innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>دریافت ارزها ناموفق بود</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>`;
            return;
        }

        const currencies = Array.isArray(data.currencies) ? data.currencies : [];
        const $list = view.querySelector('#crypto-list');
        if (!currencies.length) {
            $list.innerHTML = `
                <div class="empty">
                    ${icon('info', 'class="ico ico-xxl ico-muted"')}
                    <h3>ارزی فعال نیست</h3>
                    <p class="muted">در حال حاضر هیچ ارز آفلاینی روی این سرور فعال نیست.</p>
                </div>`;
            return;
        }

        $list.innerHTML = currencies.map((c) => {
            const iconKey = c.icon_key || iconForCurrency(c.code);
            const tint = c.color || colorForCurrency(c.code);
            const svgSrc = svgForCurrency(c.code);
            const visual = svgSrc
                ? `<img src="${escapeHtml(svgSrc)}" alt="${escapeHtml(c.code)}" class="crypto-svg" style="width:28px;height:28px;display:block" />`
                : icon(iconKey, 'class="ico ico-xl"');

            const codeUp = String(c.code || '').toUpperCase();
            const isUsdt = codeUp.startsWith('USDT');
            const net = String(c.network || '').trim();
            let netLabel = net;
            if (isUsdt && net !== '') {
                netLabel = 'USDT → ' + net;
            }

            return `
            <button type="button" class="pay-card" data-code="${escapeHtml(c.code)}">
                <span class="crypto-ico" style="color:${escapeHtml(tint)}">${visual}</span>
                <span class="pay-label">${escapeHtml(c.name || c.code)}</span>
                <span class="muted mono" style="font-size:11px">${escapeHtml(netLabel)}</span>
                ${icon('chevronLeft', 'class="ico pay-arrow"')}
            </button>`;
        }).join('');

        $list.querySelectorAll('.pay-card').forEach((btn) => {
            btn.addEventListener('click', () => {
                hapticImpact('light');
                const code = btn.dataset.code;
                state.currency = currencies.find((c) => c.code === code) || null;
                if (state.currency) step2Mode();
            });
        });
    }


    function step2Mode() {
        view.innerHTML = `
            <a href="#" class="page-back" id="crypto-back">
                ${icon('chevronRight', 'class="ico"')}
                <span>بازگشت</span>
            </a>
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/mode</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${icon('settings')} حالت پرداخت</p>
                    <h3 style="margin:4px 0 12px;font-size:17px">از کجا پرداخت می‌کنید؟</h3>
                    <p class="muted" style="font-size:13px">انتخاب درست باعث می‌شود مبلغ روی شبکه دقیقاً با مبلغ فاکتور بخواند.</p>

                    <button type="button" class="pay-card mt-md" id="mode-foreign">
                        <span class="crypto-ico" style="color:#5a9fff">${icon('earth', 'class="ico ico-xl"')}</span>
                        <span class="pay-label">کیف‌پول خارجی (Binance / Trust …)</span>
                        <span class="muted" style="font-size:11px">اعشار دقیق — مثل ۵.۱۲۴۷</span>
                        ${icon('chevronLeft', 'class="ico pay-arrow"')}
                    </button>
                    <button type="button" class="pay-card mt-sm" id="mode-iran">
                        <span class="crypto-ico"><img src="${escapeHtml(((window.__APP_CONFIG__||{}).assetPrefix || '/') + 'assets/branding/coins/iran.svg')}" alt="IR" class="crypto-svg" style="width:30px;height:30px" /></span>
                        <span class="pay-label">صرافی ایرانی (نوبیتکس / والکس …)</span>
                        <span class="muted" style="font-size:11px">گرد شده — مثل ۵.۲ (تلورانس ~۲٪)</span>
                        ${icon('chevronLeft', 'class="ico pay-arrow"')}
                    </button>
                </div>
            </article>`;
        setupBack(() => step1Currencies());

        view.querySelector('#mode-foreign').addEventListener('click', () => {
            hapticImpact('light');
            state.iranianMode = false;
            step3Invoice();
        });
        view.querySelector('#mode-iran').addEventListener('click', () => {
            hapticImpact('light');
            state.iranianMode = true;
            step3Invoice();
        });
    }


    async function step3Invoice() {
        view.innerHTML = `
            <a href="#" class="page-back" id="crypto-back">
                ${icon('chevronRight', 'class="ico"')}
                <span>بازگشت</span>
            </a>
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/invoice</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${icon('wallet')} ساخت فاکتور…</p>
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                </div>
            </article>`;
        setupBack(() => step2Mode());

        try {
            const body = {
                amount: state.amount,
                currency_code: state.currency.code,
                iranian_mode: state.iranianMode ? 1 : 0,
            };
            if (state.purchaseUsername) body.purchase_username = state.purchaseUsername;
            const res = await call('crypto_invoice_init', { method: 'POST', body });
            state.invoice = res?.obj || null;
            if (!state.invoice) throw new Error('پاسخ سرور خالی بود');
            renderInvoiceView();
        } catch (err) {
            view.innerHTML = `
                <a href="#" class="page-back" id="crypto-back">
                    ${icon('chevronRight', 'class="ico"')}
                    <span>بازگشت</span>
                </a>
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>ساخت فاکتور ناموفق بود</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                    <button class="btn btn-primary mt-md" id="retry-invoice">تلاش مجدد</button>
                </div>`;
            setupBack(() => step2Mode());
            view.querySelector('#retry-invoice').addEventListener('click', () => step3Invoice());
        }
    }


    function renderInvoiceView() {
        const inv = state.invoice;
        const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '/api';
        const qrPayload = inv.wallet_to;

        const v = Math.floor(Date.now() / (60 * 60 * 1000));
        const qrSrc = `${apiUrl}/qr.php?d=${encodeURIComponent(qrPayload)}&s=560&v=${v}`;
        const expSec = Math.max(0, (inv.expires_at || 0) - Math.floor(Date.now() / 1000));
        const expMin = Math.floor(expSec / 60);

        view.innerHTML = `
            <a href="#" class="page-back" id="crypto-back">
                ${icon('chevronRight', 'class="ico"')}
                <span>بازگشت</span>
            </a>
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/pay</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${exchangeIcon('ico-accent')} پرداخت ارز آفلاین — ${escapeHtml(state.currency.name)}</p>
                    <p class="muted" style="font-size:13px">
                        مبلغ روبه‌رو را به آدرس زیر روی شبکه‌ی <b>${escapeHtml(inv.network)}</b> ارسال کنید.
                        ${state.iranianMode ? 'حالت صرافی ایرانی: مبلغ گرد شده با تلورانس ~۲٪.' : 'حالت کیف‌پول خارجی: مبلغ دقیق با اعشار.'}
                    </p>

                    <div class="qr-panel mt-md">
                        <div class="qr-frame"><img src="${escapeHtml(qrSrc)}" alt="QR" /></div>
                    </div>

                    <div class="sub-link-box wallet-big-box mt-md">
                        <div class="label">${icon('wallet')} <span>آدرس کیف‌پول دریافت‌کننده</span></div>
                        <div class="wallet-big mono" id="copy-wallet" title="کلیک = کپی">${escapeHtml(inv.wallet_to)}</div>
                    </div>

                    <div class="sub-link-box mt-sm">
                        <div class="label">${icon('coin')} <span>مبلغ دقیق ${escapeHtml(state.currency.code)}</span></div>
                        <div class="sub-link-value mono" id="copy-amount" style="font-size:18px;font-weight:700;color:var(--gold-bright);text-align:center" title="کلیک = کپی">${escapeHtml(inv.crypto_amount)}</div>
                    </div>

                    ${inv.wallet_memo ? `
                        <div class="callout mt-sm" style="background:var(--red-soft);border-color:var(--red)">
                            ${icon('alert', 'class="ico ico-leading"')}
                            <div>
                                <b>توجه (memo):</b> برای شبکه‌ی ${escapeHtml(inv.network)}، حتماً <code>memo</code> زیر را در تراکنش بگنجانید — وگرنه تراکنش از دست می‌رود.
                                <div class="sub-link-value mono mt-sm" id="copy-memo" title="کلیک = کپی">${escapeHtml(inv.wallet_memo)}</div>
                            </div>
                        </div>` : ''}

                    <div class="kv mt-md">
                        <span class="kv-label">${icon('hourglass')} انقضای فاکتور</span>
                        <span class="kv-value mono">${expMin > 0 ? expMin + ' دقیقه دیگر' : 'منقضی شده'}</span>
                    </div>
                    <div class="kv">
                        <span class="kv-label">${icon('fileText')} کد فاکتور</span>
                        <span class="kv-value mono gold">${escapeHtml(inv.order_id)}</span>
                    </div>

                    <button type="button" class="btn btn-primary btn-block mt-md" id="goto-hash">
                        ${icon('check', 'class="ico ico-leading"')}
                        <span>پرداخت کردم — ارسال هش</span>
                    </button>
                </div>
            </article>`;
        setupBack(() => {
            (async () => {
                const ok = await showConfirm('فاکتور لغو شود؟ این فاکتور به‌صورت خودکار باطل می‌شود و نمی‌توانید با همان مبلغ مجدداً پرداخت کنید.');
                if (!ok) return;
                try {
                    await call('crypto_cancel_invoice', {
                        method: 'POST',
                        body: { order_id: inv.order_id },
                    });
                } catch (_) {  }
                step2Mode();
            })();
        });


        const copyHandlers = [
            ['#copy-wallet', inv.wallet_to],
            ['#copy-amount', inv.crypto_amount],
        ];
        if (inv.wallet_memo) copyHandlers.push(['#copy-memo', inv.wallet_memo]);

        copyHandlers.forEach(([sel, val]) => {
            const $el = view.querySelector(sel);
            if (!$el) return;
            $el.addEventListener('click', async () => {
                const ok = await copyToClipboard(String(val));
                hapticImpact('light');
                toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            });
        });

        view.querySelector('#goto-hash').addEventListener('click', () => step4Hash());
    }


    function step4Hash() {
        const inv = state.invoice;
        view.innerHTML = `
            <a href="#" class="page-back" id="crypto-back">
                ${icon('chevronRight', 'class="ico"')}
                <span>بازگشت</span>
            </a>
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/hash</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${icon('fileText')} ارسال هش تراکنش</p>
                    <p class="muted" style="font-size:13px">هش تراکنش (Transaction Hash) را پیست کنید — یا لینک کامل از Tronscan / Tonviewer / Tonscan هم قابل قبول است.</p>

                    <div class="form-row mt-md">
                        <label class="muted" style="font-size:12px">هش یا لینک تراکنش</label>
                        <textarea id="hash-input" rows="3" placeholder="مثلاً: a1b2c3...64char  یا  https://tronscan.org/#/transaction/..."></textarea>
                    </div>

                    <button type="button" class="btn btn-primary btn-block mt-md" id="hash-submit">
                        ${icon('check', 'class="ico ico-leading"')}
                        <span>ثبت و بررسی تراکنش</span>
                    </button>

                    <p class="muted mt-sm" style="font-size:11px;text-align:center">
                        پس از ثبت، ربات هر ۱-۲ دقیقه یک‌بار شبکه را بررسی می‌کند.
                    </p>
                </div>
            </article>`;
        setupBack(() => renderInvoiceView());

        const $input = view.querySelector('#hash-input');
        const $btn   = view.querySelector('#hash-submit');
        const $label = $btn.querySelector('span:last-child');

        $btn.addEventListener('click', async () => {
            const val = ($input.value || '').trim();
            if (val === '') {
                toast('لطفاً هش تراکنش را وارد کنید', 'warn');
                return;
            }
            const old = $label.textContent;
            $btn.disabled = true;
            $label.textContent = 'در حال ثبت…';

            try {
                await call('crypto_submit_hash', {
                    method: 'POST',
                    body: { order_id: inv.order_id, hash: val },
                });
                hapticNotify('success');
                step5Watch();
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در ثبت هش', 'error', 5000);
                $btn.disabled = false;
                $label.textContent = old;
            }
        });
    }


    function step5Watch() {
        const inv = state.invoice;
        if (state.cleanup) { try { state.cleanup(); } catch (_) {} state.cleanup = null; }

        const watchMode = state.purchaseUsername ? 'direct_buy' : 'recharge';

        state.cleanup = startGatewayWatch(view, {
            orderId: inv.order_id,
            title: 'در حال بررسی هش تراکنش…',
            subtitle: 'ربات هر ۱-۲ دقیقه یک‌بار شبکه را چک می‌کند. تا تایید شدن منتظر بمانید.',
            isCrypto: true,
            mode: watchMode,
            timeoutSec: 600,
            pollEverySec: 5,
            onSuccess: async (statusObj) => {

                if (state.purchaseUsername && statusObj.service) {
                    try {
                        const mod = await loadSuccessModule();
                        if (mod && typeof mod.renderServiceSuccess === 'function') {
                            mod.renderServiceSuccess(view, {
                                order_id: statusObj.order_id,
                                service: statusObj.service,
                            }, {
                                productName: statusObj.service.product_name || '',
                                panelName: statusObj.service.panel_name || '',
                            });
                            return;
                        }
                    } catch (_) {}
                }

                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/crypto/done</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">
                                ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">پرداخت ارزی تایید شد</h2>
                            <p class="muted">مبلغ ${escapeHtml(Number(statusObj.amount || 0).toLocaleString('en-US'))} تومان به حساب شما اضافه شد.</p>
                            <div class="kv mt-md"><span class="kv-label">کد فاکتور</span><span class="kv-value mono gold">${escapeHtml(statusObj.order_id)}</span></div>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                                <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                            </div>
                        </div>
                    </article>`;
            },
            onFail: (statusObj) => {
                const reason = statusObj.reason || 'تراکنش تایید نشد';
                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/crypto/fail</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">
                                ${icon('xCircle', 'class="ico ico-xxl ico-warn"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">تراکنش تایید نشد</h2>
                            <p class="muted">${escapeHtml(reason)}</p>
                            <p class="muted mt-sm" style="font-size:12px">
                                کد فاکتور: <span class="mono">${escapeHtml(statusObj.order_id)}</span>
                            </p>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <button type="button" class="btn btn-primary btn-block" id="retry-hash" style="flex:1">ارسال هش جدید</button>
                                <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                            </div>
                        </div>
                    </article>`;
                const $r = view.querySelector('#retry-hash');
                if ($r) $r.addEventListener('click', () => step4Hash());
            },
            onTimeout: () => {
                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/crypto/timeout</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--yellow);margin:4px 0 10px">
                                ${icon('clock', 'class="ico ico-xxl ico-warn"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">زمان انتظار تمام شد</h2>
                            <p class="muted">۱۰ دقیقه گذشت و تراکنش تایید نشد. ربات تا ۲۴ ساعت هم همچنان بررسی می‌کند؛ اگر تایید شد، در ربات تلگرام به شما اطلاع داده می‌شود.</p>
                            <p class="muted mt-sm" style="font-size:12px">کد فاکتور: <span class="mono">${escapeHtml(state.invoice.order_id)}</span></p>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                                <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                            </div>
                        </div>
                    </article>`;
            },
        });
    }


    if (state.amount <= 0) {
        view.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>مبلغ نامعتبر</h3>
                <p class="muted">لطفاً ابتدا مبلغ شارژ را وارد کنید.</p>
                <a href="#/recharge" class="btn btn-primary mt-md">بازگشت به شارژ</a>
            </div>`;
        return;
    }
    step1Currencies();
}
