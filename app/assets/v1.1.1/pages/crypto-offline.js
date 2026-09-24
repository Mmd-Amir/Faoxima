import { call } from '../api.js?v=0.0.52';
import { escapeHtml, copyToClipboard, toast, blobToDataUrl } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { hapticImpact, hapticNotify, showConfirm } from '../telegram.js?v=0.0.52';
import { startGatewayWatch } from './gateway-watch.js';
import { getToken } from '../state.js';


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
    if (c === 'TRX') return '<svg viewBox="0 0 32 32" class="crypto-svg" aria-hidden="true"><circle fill="#EF0027" cx="16" cy="16" r="16"/><path d="M21.932 9.913L7.5 7.257l7.595 19.112 10.583-12.894-3.746-3.562zm-.232 1.17l2.208 2.099-6.038 1.093 3.83-3.192zm-5.142 2.973l-6.364-5.278 10.402 1.914-4.038 3.364zm-.453.934l-1.038 8.58L9.472 9.487l6.633 5.502zm.96.455l6.687-1.21-7.67 9.343.983-8.133z" fill="#FFF"/></svg>';
    if (c === 'TON') return '<svg viewBox="0 0 56 56" class="crypto-svg" aria-hidden="true"><circle fill="#0088CC" cx="28" cy="28" r="28"/><path fill="#FFF" d="M20.2 18.5h15.7c1.3 0 2.4.6 3 1.6.7 1.1.7 2.5.1 3.7l-9.9 17a1.3 1.3 0 0 1-2.2 0l-9.7-17c-.7-1.2-.8-2.4-.2-3.6.6-1.2 1.8-1.7 3.2-1.7zm6.6 2.4h-6.6c-.7 0-1 .1-1.2.5-.1.3 0 .6.3 1.1l7.5 13V20.9zm2.4 0v14.7l7.6-13.1c.3-.6.1-1.1-.2-1.3-.2-.2-.4-.3-.8-.3h-6.6z"/></svg>';
    if (c.startsWith('USDT')) return '<svg viewBox="0 0 32 32" class="crypto-svg" aria-hidden="true"><circle cx="16" cy="16" r="16" fill="#26A17B"/><path fill="#FFF" d="M17.922 17.383c-.11.008-.677.042-1.942.042-1.01 0-1.721-.03-1.971-.042-3.888-.171-6.79-.848-6.79-1.658 0-.809 2.902-1.486 6.79-1.66v2.644c.254.018.982.061 1.988.061 1.207 0 1.812-.05 1.925-.06v-2.643c3.88.173 6.775.85 6.775 1.658 0 .81-2.895 1.485-6.775 1.657m0-3.59v-2.366h5.414V7.819H8.595v3.608h5.414v2.365c-4.4.202-7.709 1.074-7.709 2.118 0 1.044 3.309 1.915 7.709 2.118v7.582h3.913v-7.584c4.393-.202 7.694-1.073 7.694-2.116 0-1.043-3.301-1.914-7.694-2.117"/></svg>';
    return '';
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
        pendingKind: opts.pendingKind || null,
        pendingOne: opts.pendingOne || null,
        currency: null,
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
                ? svgSrc
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
                if (state.currency) step3Invoice();
            });
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
        setupBack(() => step1Currencies());

        try {
            const body = {
                amount: state.amount,
                currency_code: state.currency.code,
            };
            if (state.purchaseUsername) body.purchase_username = state.purchaseUsername;
            if (state.pendingKind && state.pendingOne) {
                body.pending_kind = state.pendingKind;
                body.pending_one = state.pendingOne;
            }
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
            setupBack(() => step1Currencies());
            view.querySelector('#retry-invoice').addEventListener('click', () => step3Invoice());
        }
    }


    function renderInvoiceView() {
        const inv = state.invoice;
        const isManual = !!(state.currency && state.currency.is_manual);
        const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '/api';
        const qrPayload = inv.wallet_to;

        const v = Math.floor(Date.now() / (60 * 60 * 1000));
        const _qrOn = (window.__APP_CONFIG__ || {}).qrEnabled !== false;
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
                    <p class="section-title">${isManual ? icon('wrench', 'class="ico ico-accent"') : exchangeIcon('ico-accent')} پرداخت ارز آفلاین — ${escapeHtml(state.currency.name)}${isManual ? ' (بررسی دستی)' : ''}</p>
                    <p class="muted" style="font-size:13px">
                        مبلغ روبه‌رو را به آدرس زیر روی شبکه‌ی <b>${escapeHtml(inv.network)}</b> ارسال کنید.
                        مقدار دقیق رو با ۲ رقم اعشار از کیف‌پول یا صرافی ارسال کنید.
                    </p>

                    ${isManual ? `
                        <div class="callout mt-md" style="background:var(--gold-soft, rgba(212,184,120,.12));border-color:var(--gold)">
                            ${icon('alert', 'class="ico ico-leading"')}
                            <div>این شبکه به‌صورت خودکار بررسی نمی‌شود. بعد از ارسال هش، باید <b>عکس رسید تراکنش</b> را نیز آپلود کنید و تایید نهایی توسط ادمین انجام می‌شود.</div>
                        </div>` : ''}

                    ${_qrOn ? `<div class="qr-panel mt-md"><div class="qr-frame"><img id="crypto-qr-img" alt="QR" /></div></div>` : ''}

                    <div class="sub-link-box wallet-big-box mt-md">
                        <div class="label">${icon('wallet')} <span>آدرس کیف‌پول دریافت‌کننده</span></div>
                        <div class="wallet-big mono" id="copy-wallet" title="کلیک = کپی">${escapeHtml(inv.wallet_to)}</div>
                    </div>

                    <div class="kv mt-md">
                        <span class="kv-label">${icon('coin')} مبلغ قابل پرداخت</span>
                        <span class="kv-value mono gold">${escapeHtml(Number(inv.amount_toman_final || inv.amount_toman || 0).toLocaleString('en-US'))} تومان</span>
                    </div>

                    <div class="sub-link-box mt-sm">
                        <div class="label">${icon('coin')} <span>مبلغ دقیق ${escapeHtml(state.currency.code)}</span></div>
                        <div class="sub-link-value mono" id="copy-amount" style="font-size:18px;font-weight:700;color:var(--gold-bright);text-align:center" title="کلیک = کپی">${escapeHtml(inv.crypto_amount)}</div>
                        <div class="muted" style="font-size:12px;text-align:center;margin-top:4px">(معادل ${escapeHtml(Number(inv.amount_toman_final || inv.amount_toman || 0).toLocaleString('en-US'))} تومان)</div>
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
                        <span class="kv-label">${icon('hourglass')} ${isManual ? 'روش تایید' : 'پس از ارسال هش'}</span>
                        <span class="kv-value mono">${isManual ? 'بررسی دستی ادمین' : '۳۰ دقیقه برای تایید'}</span>
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
                step1Currencies();
            })();
        });

        const $qrImg = view.querySelector('#crypto-qr-img');
        if ($qrImg) {
            fetch(`${apiUrl}/qr.php?d=${encodeURIComponent(qrPayload)}&s=560&v=${v}`, { credentials: 'same-origin' })
                .then((res) => {
                    if (!res.ok) throw new Error('QR load failed');
                    return res.blob();
                })
                .then(blobToDataUrl)
                .then((dataUrl) => { $qrImg.src = dataUrl; })
                .catch(() => { const panel = $qrImg.closest('.qr-panel'); if (panel) panel.remove(); });
        }


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
        const isManual = !!(state.currency && state.currency.is_manual);
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
                    <p class="muted" style="font-size:13px">هش تراکنش (Transaction Hash) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.</p>

                    <div class="form-row mt-md">
                        <label class="muted" style="font-size:12px">هش یا لینک تراکنش</label>
                        <textarea id="hash-input" rows="3" placeholder="مثلاً: هش تراکنش یا لینک آن از اکسپلورر شبکه"></textarea>
                    </div>

                    <button type="button" class="btn btn-primary btn-block mt-md" id="hash-submit">
                        ${icon('check', 'class="ico ico-leading"')}
                        <span>ثبت ${isManual ? 'و ادامه' : 'و بررسی تراکنش'}</span>
                    </button>

                    <p class="muted mt-sm" style="font-size:11px;text-align:center">
                        ${isManual
                            ? 'پس از ثبت هش، باید عکس رسید تراکنش را نیز آپلود کنید — تایید نهایی توسط ادمین انجام می‌شود.'
                            : 'پس از ثبت، ربات هر ۱-۲ دقیقه یک‌بار شبکه را بررسی می‌کند.'}
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
                const res = await call('crypto_submit_hash', {
                    method: 'POST',
                    body: { order_id: inv.order_id, hash: val },
                });
                hapticNotify('success');
                const receiptRequired = !!(res && res.obj && res.obj.receipt_required);
                if (receiptRequired) {
                    step5bReceiptUpload();
                } else {
                    step5Watch();
                }
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در ثبت هش', 'error', 5000);
                $btn.disabled = false;
                $label.textContent = old;
            }
        });
    }


    function step5bReceiptUpload() {
        const inv = state.invoice;
        view.innerHTML = `
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/receipt</span>
                </header>
                <div class="card-body">
                    <p class="section-title">${icon('wrench', 'class="ico ico-accent"')} بررسی دستی — ارسال رسید</p>
                    <p class="muted" style="font-size:13px">این شبکه به‌صورت خودکار بررسی نمی‌شود. برای ثبت درخواست بررسی توسط ادمین، عکس رسید تراکنش را آپلود کنید.</p>

                    <div class="card-section mt-md receipt-upload-zone">
                        <input type="file" id="crypto-receipt-file" accept="image/*" capture="environment" style="display:none" />
                        <button type="button" id="crypto-receipt-pick" class="btn btn-primary btn-block mt-sm">
                            ${icon('download', 'class="ico ico-leading"')}
                            <span>انتخاب عکس رسید</span>
                        </button>

                        <div id="crypto-receipt-preview" class="hidden mt-sm">
                            <img id="crypto-receipt-preview-img" alt="پیش‌نمایش" class="receipt-preview-img" />
                            <button type="button" id="crypto-receipt-submit" class="btn btn-primary btn-block mt-sm">
                                ${icon('send', 'class="ico ico-leading"')}
                                <span class="crypto-receipt-submit-label">ارسال رسید برای ادمین</span>
                            </button>
                        </div>

                        <p class="muted mono mt-md" style="font-size:11px">کد پیگیری: ${escapeHtml(inv.order_id)}</p>
                    </div>
                </div>
            </article>`;

        const $file = view.querySelector('#crypto-receipt-file');
        const $pick = view.querySelector('#crypto-receipt-pick');
        const $preview = view.querySelector('#crypto-receipt-preview');
        const $previewImg = view.querySelector('#crypto-receipt-preview-img');
        const $submit = view.querySelector('#crypto-receipt-submit');

        $pick.addEventListener('click', () => $file.click());

        $file.addEventListener('change', async () => {
            const file = $file.files && $file.files[0];
            if (!file) return;
            if (file.size > 8 * 1024 * 1024) {
                toast('حجم فایل نباید بیشتر از 8 مگابایت باشد', 'error', 4000);
                $file.value = '';
                return;
            }
            try {
                $previewImg.src = await blobToDataUrl(file);
                $preview.classList.remove('hidden');
            } catch (_) {
                toast('نمایش پیش‌نمایش تصویر ممکن نیست', 'error', 3000);
            }
        });

        $submit.addEventListener('click', async () => {
            const file = $file.files && $file.files[0];
            if (!file) {
                toast('ابتدا عکس رسید را انتخاب کنید', 'warn');
                return;
            }

            const $label = $submit.querySelector('.crypto-receipt-submit-label');
            const oldLabel = $label.textContent;
            $submit.disabled = true;
            $pick.disabled = true;
            $label.textContent = 'در حال ارسال…';

            try {
                const fd = new FormData();
                fd.append('order_id', inv.order_id);
                fd.append('photo', file);

                const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
                const tok = getToken();
                const res = await fetch(`${apiUrl}/miniapp.php?actions=crypto_upload_receipt`, {
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
                const serverMsg = (envelope.obj && envelope.obj.message) || 'رسید شما برای ادمین ارسال شد. پس از تأیید، حساب شما شارژ می‌شود.';
                step6ManualPending(serverMsg);
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در ارسال رسید', 'error', 5000);
                $submit.disabled = false;
                $pick.disabled = false;
                $label.textContent = oldLabel;
            }
        });
    }


    function step6ManualPending(message) {
        const inv = state.invoice;
        view.innerHTML = `
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/crypto/pending</span>
                </header>
                <div class="card-body" style="text-align:center;padding:22px">
                    <div style="font-size:42px;line-height:1;color:var(--gold-bright);margin:4px 0 10px">
                        ${icon('checkCircle', 'class="ico ico-xxl"')}
                    </div>
                    <h2 style="margin:6px 0;font-size:18px">در انتظار تایید ادمین</h2>
                    <p class="muted">${escapeHtml(message)}</p>
                    <div class="kv mt-md"><span class="kv-label">کد فاکتور</span><span class="kv-value mono gold">${escapeHtml(inv.order_id)}</span></div>
                    <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                        <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                        <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                    </div>
                </div>
            </article>`;
    }


    function step5Watch() {
        const inv = state.invoice;
        if (state.cleanup) { try { state.cleanup(); } catch (_) {} state.cleanup = null; }

        const watchMode = state.purchaseUsername ? 'direct_buy' : (state.pendingKind ? 'pending_action' : 'recharge');

        state.cleanup = startGatewayWatch(view, {
            orderId: inv.order_id,
            title: 'در حال بررسی هش تراکنش…',
            subtitle: 'ربات هر ۱-۲ دقیقه یک‌بار شبکه را چک می‌کند. تا تایید شدن منتظر بمانید.',
            isCrypto: true,
            mode: watchMode,
            timeoutSec: 1800,
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

                const pendingTitles = {
                    renew: 'سرویس با موفقیت تمدید شد',
                    extra_time: 'زمان اضافه با موفقیت اعمال شد',
                    extra_volume: 'حجم اضافه با موفقیت اعمال شد',
                };
                const isPendingAction = !!state.pendingKind;
                const title = isPendingAction
                    ? (pendingTitles[state.pendingKind] || 'عملیات با موفقیت انجام شد')
                    : 'پرداخت ارزی تایید شد';
                const bodyMsg = isPendingAction
                    ? `مبلغ ${escapeHtml(Number(statusObj.amount || 0).toLocaleString('en-US'))} تومان پرداخت و اعمال شد.`
                    : `مبلغ ${escapeHtml(Number(statusObj.amount || 0).toLocaleString('en-US'))} تومان به حساب شما اضافه شد.`;
                const ctaHref = isPendingAction ? '#/services' : '#/buy';
                const ctaLabel = isPendingAction ? 'سرویس‌های من' : 'خرید سرویس';

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
                            <h2 style="margin:6px 0;font-size:18px">${escapeHtml(title)}</h2>
                            <p class="muted">${bodyMsg}</p>
                            <div class="kv mt-md"><span class="kv-label">کد فاکتور</span><span class="kv-value mono gold">${escapeHtml(statusObj.order_id)}</span></div>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                                <a href="${ctaHref}" class="btn btn-ghost btn-block" style="flex:1">${escapeHtml(ctaLabel)}</a>
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
                            <p class="muted">۳۰ دقیقه گذشت و تراکنش تایید نشد. اگر مبلغ از حساب شما کسر شده، با پشتیبانی تماس بگیرید.</p>
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
