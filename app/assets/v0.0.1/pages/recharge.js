import { call, ApiError } from '../api.js';
import { escapeHtml, fmtPrice, skeletonList, toast, copyToClipboard } from '../utils.js';
import { hapticImpact, hapticNotify } from '../telegram.js';
import { icon } from '../icons.js';

function iconForMethod(m) {
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


function exchangeSvgIcon(extraClass = 'ico-accent') {
    return `<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" class="ico ${extraClass}" fill="currentColor" aria-hidden="true"><path d="M0 5a5.002 5.002 0 0 0 4.027 4.905 6.46 6.46 0 0 1 .544-2.073C3.695 7.536 3.132 6.864 3 5.91h-.5v-.426h.466V5.05c0-.046 0-.093.004-.135H2.5v-.427h.511C3.236 3.24 4.213 2.5 5.681 2.5c.316 0 .59.031.819.085v.733a3.46 3.46 0 0 0-.815-.082c-.919 0-1.538.466-1.734 1.252h1.917v.427h-1.98c-.003.046-.003.097-.003.147v.422h1.983v.427H3.93c.118.602.468 1.03 1.005 1.229a6.5 6.5 0 0 1 4.97-3.113A5.002 5.002 0 0 0 0 5zm16 5.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0zm-7.75 1.322c.069.835.746 1.485 1.964 1.562V14h.54v-.62c1.259-.086 1.996-.74 1.996-1.69 0-.865-.563-1.31-1.57-1.54l-.426-.1V8.374c.54.06.884.347.966.745h.948c-.07-.804-.779-1.433-1.914-1.502V7h-.54v.629c-1.076.103-1.808.732-1.808 1.622 0 .787.544 1.288 1.45 1.493l.358.085v1.78c-.554-.08-.92-.376-1.003-.787H8.25zm1.96-1.895c-.532-.12-.82-.364-.82-.732 0-.41.311-.719.824-.809v1.54h-.005zm.622 1.044c.645.145.943.38.943.796 0 .474-.37.8-1.02.86v-1.674l.077.018z"/></svg>`;
}


function methodVisualHtml(m, extraClass = 'ico-accent') {
    const key = iconForMethod(m);
    return (key === 'exchange') ? exchangeSvgIcon(extraClass) : icon(key, `class="ico ${extraClass}"`);
}

function stripEmoji(s) {
    return String(s || '')
        .replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}\u{1F300}-\u{1F9FF}\s]+/u, '')
        .replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}\u{1F300}-\u{1F9FF}]+$/u, '')
        .trim();
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('fa-IR');
}

export async function recharge(view) {
    view.innerHTML = `
        <a href="#/account" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/recharge</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('wallet')} شارژ کیف پول</p>
                <h2 class="section-headline">یک روش پرداخت انتخاب کنید</h2>
                <p class="muted center mb-md" style="font-size:13px">روش‌هایی که در ربات فعال شده‌اند، اینجا نمایش داده می‌شوند.</p>

                <div id="recharge-host">
                    ${skeletonList(3)}
                </div>
            </div>
        </article>
    `;

    const $host = view.querySelector('#recharge-host');

    let data = null;
    try {
        const res = await call('payment_methods');
        data = res?.obj;
    } catch (err) {
        $host.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    const methods = Array.isArray(data?.methods) ? data.methods : [];
    if (methods.length === 0) {
        $host.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
                <p class="muted">برای فعال‌سازی روش‌های پرداخت، از پنل مدیریت ربات اقدام کنید.</p>
            </div>`;
        return;
    }

    const limits = data.limits || { min: 0, max: 0 };
    const balance = Number(data.balance || 0);

    $host.innerHTML = `
        <div class="kv">
            <span class="kv-label">${icon('wallet')} موجودی فعلی</span>
            <span class="kv-value accent">${escapeHtml(fmtPrice(balance))}</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('chart')} بازه مجاز شارژ</span>
            <span class="kv-value mono">${escapeHtml(fmtPrice(limits.min))} — ${escapeHtml(fmtPrice(limits.max))}</span>
        </div>

        <div class="payment-methods mt-md" id="pay-list">
            ${methods.map(renderMethodCard).join('')}
        </div>

        <div id="pay-form-host"></div>
        <div id="pay-result-host"></div>
    `;

    const $list = view.querySelector('#pay-list');
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


        const methodLimits = resolveMethodLimits(method, limits);

        const cryptoOfflineIds = ['crypto_offline', 'digitaltron'];
        if (method.kind === 'crypto_offline' || cryptoOfflineIds.includes(method.id)) {
            renderCryptoOfflineForm(view, methodLimits);
            return;
        }

        renderForm(view, method.id, method, methodLimits);
    });
}


function resolveMethodLimits(method, fallback) {
    const fb = fallback || { min: 0, max: 0 };
    const m = Number(method && method.min);
    const x = Number(method && method.max);
    if (m > 0 && x > 0) return { min: m, max: x };
    return { min: Number(fb.min || 0), max: Number(fb.max || 0) };
}


function renderCryptoOfflineForm(view, limits) {
    const $host = view.querySelector('#pay-form-host');
    const $result = view.querySelector('#pay-result-host');
    if (!$host) return;
    if ($result) $result.innerHTML = '';

    $host.innerHTML = `
        <div class="card-section">
            <p class="section-title">${exchangeSvgIcon('ico-accent')} ارز آفلاین (هش‌چکر)</p>
            <p class="muted" style="font-size:13px">ابتدا مبلغ شارژ (تومان) را وارد کنید — سپس ارز و حالت پرداخت را انتخاب می‌کنید.</p>

            <div class="form-row mt-sm">
                <label for="crypto-amount" class="muted" style="font-size:12px">مبلغ شارژ (تومان)</label>
                <input id="crypto-amount" type="number" inputmode="numeric" pattern="[0-9]*"
                       min="${limits.min || 0}" max="${limits.max || 0}"
                       step="1000" placeholder="مثال: ${fmtNum(Math.max(limits.min, 50000))}" />
            </div>

            <button id="crypto-start" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('arrowLeft', 'class="ico ico-leading"')}
                <span>ادامه و انتخاب ارز</span>
            </button>
        </div>`;

    const $amt = $host.querySelector('#crypto-amount');
    const $btn = $host.querySelector('#crypto-start');
    $btn.addEventListener('click', async () => {
        const amount = parseInt($amt.value || '0', 10);
        if (!amount || amount < limits.min || amount > limits.max) {
            toast(`مبلغ باید بین ${fmtNum(limits.min)} و ${fmtNum(limits.max)} باشد`, 'warn', 4000);
            return;
        }
        try {
            const mod = await import( './crypto-offline.js');
            if (mod && typeof mod.renderCryptoOffline === 'function') {
                mod.renderCryptoOffline(view, {
                    amount,
                    onBack: () => recharge(view),
                });
                return;
            }
        } catch (loadErr) {
            console.warn('[recharge] crypto-offline import failed', loadErr);
        }
        toast('فلوی ارز آفلاین در دسترس نیست', 'error', 4000);
    });
}

function renderMethodCard(m) {
    return `
        <button type="button" class="pay-card" data-method="${escapeHtml(m.id)}">
            ${methodVisualHtml(m, 'ico-accent')}
            <span class="pay-label">${escapeHtml(stripEmoji(m.label))}</span>
            ${icon('chevronLeft', 'class="ico pay-arrow"')}
        </button>`;
}

function renderForm(view, methodId, method, limits) {
    const $host = view.querySelector('#pay-form-host');
    const $result = view.querySelector('#pay-result-host');
    if (!$host) return;
    $result.innerHTML = '';

    $host.innerHTML = `
        <div class="card-section">
            <p class="section-title">${methodVisualHtml(method, 'ico-accent')} ${escapeHtml(stripEmoji(method.label))}</p>
            <p class="muted" style="font-size:13px">مبلغ مورد نظر را به تومان وارد کنید (بین <span class="mono accent">${fmtNum(limits.min)}</span> و <span class="mono accent">${fmtNum(limits.max)}</span>)</p>

            <div class="form-row mt-sm">
                <label for="pay-amount" class="muted" style="font-size:12px">مبلغ (تومان)</label>
                <input id="pay-amount" type="number" inputmode="numeric" pattern="[0-9]*"
                       min="${limits.min || 0}" max="${limits.max || 0}"
                       step="1000" placeholder="مثال: ${fmtNum(Math.max(limits.min, 50000))}" />
            </div>

            <button id="pay-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="pay-submit-label">تأیید و ادامه</span>
                ${icon('arrowLeft', 'class="ico ico-trailing"')}
            </button>
        </div>
    `;

    const $amt = $host.querySelector('#pay-amount');
    const $btn = $host.querySelector('#pay-submit');
    const $btnLabel = $btn.querySelector('.pay-submit-label');

    $btn.addEventListener('click', async () => {
        const amount = parseInt($amt.value || '0', 10);
        if (!amount || amount <= 0) {
            toast('مبلغ را وارد کنید', 'warn');
            $amt.focus();
            return;
        }
        if (amount < limits.min || amount > limits.max) {
            toast(`مبلغ باید بین ${fmtNum(limits.min)} و ${fmtNum(limits.max)} باشد`, 'warn', 4000);
            return;
        }

        $btn.disabled = true;
        const oldLabel = $btnLabel.textContent;
        $btnLabel.textContent = 'در حال آماده‌سازی…';

        try {
            const res = await call('payment_init', {
                method: 'POST',
                body: { method: methodId, amount },
            });
            const obj = res?.obj || {};
            handleInitResult(view, methodId, obj);
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        } finally {
            $btn.disabled = false;
            $btnLabel.textContent = oldLabel;
        }
    });
}


function isAutoConfirmGatewayId(id) {
    const x = String(id || '').toLowerCase();
    return (
        x === 'plisio' || x === 'nowpayment' || x === 'digitaltron' ||
        x.startsWith('iranpay') ||
        x === 'aqayepardakht' || x === 'zarinpal' || x === 'zarinpey'
    );
}

function handleInitResult(view, methodId, obj) {
    const $result = view.querySelector('#pay-result-host');
    const $form = view.querySelector('#pay-form-host');
    if (!$result) return;

    if (obj.kind === 'url' && obj.url) {

        if (isAutoConfirmGatewayId(methodId)) {
            startGatewayWatchForRecharge(view, methodId, obj);
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
        wireCardToCard(view, obj);
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

function renderCardToCard(d) {
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

function wireCardToCard(view, d) {
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
            const tok = (await import('../state.js')).getToken();
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
            const $result = view.querySelector('#pay-result-host');
            $result.innerHTML = `
                <div class="card-section">
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>رسید ارسال شد</h3>
                        <p class="muted">${escapeHtml((envelope.obj && envelope.obj.message) || 'پس از تأیید ادمین، حساب شما شارژ می‌شود.')}</p>
                        <p class="muted mono mt-sm" style="font-size:12px">کد پیگیری: ${escapeHtml(d.order_id)}</p>
                        <a href="#/account" class="btn btn-primary mt-md">
                            ${icon('user', 'class="ico ico-leading"')}
                            <span>بازگشت به حساب</span>
                        </a>
                    </div>
                </div>`;
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ارسال رسید', 'error', 5000);
            $submit.disabled = false;
            $pick.disabled = false;
            $label.textContent = oldLabel;
        }
    });
}

function openLink(url) {
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


async function startGatewayWatchForRecharge(view, methodId, obj) {
    if (obj.url) openLink(obj.url);
    try {
        const mod = await import( './gateway-watch.js');
        if (!mod || typeof mod.startGatewayWatch !== 'function') {
            const $r = view.querySelector('#pay-result-host');
            if ($r) {
                $r.innerHTML = `
                    <div class="card-section">
                        <div class="empty">
                            ${icon('clock', 'class="ico ico-xxl ico-warn"')}
                            <h3>به درگاه پرداخت منتقل شدید</h3>
                            <p class="muted">پس از پرداخت، صفحه را رفرش کنید تا وضعیت به‌روزرسانی شود.</p>
                        </div>
                    </div>`;
            }
            return;
        }

        const isCrypto = methodId === 'plisio' || methodId === 'nowpayment' || methodId === 'digitaltron';
        mod.startGatewayWatch(view, {
            orderId: obj.order_id,
            title: isCrypto ? 'در انتظار تایید پرداخت ارزی…' : 'در انتظار تایید پرداخت…',
            subtitle: 'به‌محض تایید درگاه، نتیجه نمایش داده می‌شود.',
            gatewayUrl: obj.url,
            isCrypto,
            mode: 'recharge',
            timeoutSec: 600,
            pollEverySec: 5,
            onSuccess: (st) => {
                const amount = Number(st.amount || 0).toLocaleString('en-US');
                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/recharge/done</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--green);margin:4px 0 10px">
                                ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">شارژ کیف پول موفق</h2>
                            <p class="muted">مبلغ ${escapeHtml(amount)} تومان به حساب شما اضافه شد.</p>
                            <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${escapeHtml(st.order_id || '—')}</span></div>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/account" class="btn btn-primary btn-block" style="flex:1">حساب من</a>
                                <a href="#/buy" class="btn btn-ghost btn-block" style="flex:1">خرید سرویس</a>
                            </div>
                        </div>
                    </article>`;
            },
            onFail: (st) => {
                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/recharge/fail</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--red);margin:4px 0 10px">
                                ${icon('xCircle', 'class="ico ico-xxl ico-warn"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">پرداخت ناموفق بود</h2>
                            <p class="muted">${escapeHtml(st.reason || 'تراکنش تایید نشد')}</p>
                            <p class="muted mt-sm" style="font-size:12px">کد فاکتور: <span class="mono">${escapeHtml(st.order_id || '—')}</span></p>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                                <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                            </div>
                        </div>
                    </article>`;
            },
            onTimeout: () => {
                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/recharge/timeout</span>
                        </header>
                        <div class="card-body" style="text-align:center;padding:22px">
                            <div style="font-size:42px;line-height:1;color:var(--yellow);margin:4px 0 10px">
                                ${icon('clock', 'class="ico ico-xxl ico-warn"')}
                            </div>
                            <h2 style="margin:6px 0;font-size:18px">زمان انتظار به پایان رسید</h2>
                            <p class="muted">۱۰ دقیقه گذشت و پرداخت تایید نشد. اگر مبلغ کسر شده، با پشتیبانی تماس بگیرید.</p>
                            <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                                <a href="#/recharge" class="btn btn-primary btn-block" style="flex:1">تلاش مجدد</a>
                                <a href="#/" class="btn btn-ghost btn-block" style="flex:1">بازگشت به خانه</a>
                            </div>
                        </div>
                    </article>`;
            },
        });
    } catch (err) {
        console.warn('[recharge] gateway-watch import failed', err);
    }
}

