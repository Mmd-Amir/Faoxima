import { call } from '../api.js';
import {
    escapeHtml, fmtPrice, fmtGb, fmtDays, skeletonList, emptyState, toast, fmtNumber,
} from '../utils.js';
import { hapticImpact, hapticNotify, showConfirm } from '../telegram.js';
import { getBuyDraft, setBuyDraft, clearBuyDraft } from '../state.js';
import { icon } from '../icons.js';
import {
    fmtNum, renderMethodCard, handleInitResult,
} from '../payment-ui.js';


let _successModulePromise = null;
function loadSuccessModule() {
    if (_successModulePromise) return _successModulePromise;
    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const ver = (cfg.version || cfg.cacheBust || Date.now()).toString();
    try {
        const url = new URL('./service-success.js', import.meta.url);
        url.searchParams.set('v', ver);
        _successModulePromise = import(url.href).catch((err) => {
            console.warn('[buy] dynamic import of service-success.js with cache-bust failed, retrying plain', err);
            _successModulePromise = null;
            return import('./service-success.js');
        });
    } catch (e) {
        _successModulePromise = import('./service-success.js');
    }
    return _successModulePromise;
}


function renderFallbackSuccess(view, res) {
    const u = String(res?.service?.username || '—');
    const id = String(res?.order_id || res?.service?.id || '—');
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/buy/success</span>
            </header>
            <div class="card-body" style="text-align:center">
                <div style="font-size:48px;line-height:1;color:var(--green);margin:8px 0">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                </div>
                <h2 style="margin:8px 0;font-size:20px">سرویس شما با موفقیت ساخته شد</h2>
                <p class="muted">جزئیات و کانفیگ سرویس برای شما در تلگرام ارسال شد.</p>
                <div class="kv mt-md">
                    <span class="kv-label">کد پیگیری</span>
                    <span class="kv-value mono gold">${escapeHtml(id)}</span>
                </div>
                <div class="kv">
                    <span class="kv-label">نام کاربری</span>
                    <span class="kv-value mono">${escapeHtml(u)}</span>
                </div>
                <div class="row-spread mt-md stack-on-mobile" style="gap:10px">
                    <a href="#/services/${encodeURIComponent(u)}" class="btn btn-primary btn-block" style="flex:1">مشاهده سرویس</a>
                    <a href="#/" class="btn btn-ghost btn-block" style="flex:1">خانه</a>
                </div>
            </div>
        </article>`;
}

const STEPS = [
    { key: 'panel',     label: '۱ موقعیت' },
    { key: 'category',  label: '۲ دسته‌بندی' },
    { key: 'time',      label: '۳ زمان' },
    { key: 'product',   label: '۴ سرویس' },
    { key: 'confirm',   label: '۵ تایید' },
];

export async function buy(view) {
    const draft = getBuyDraft();

    let step = computeStep(draft);

    function render() {
        view.innerHTML = `
            <article class="card card-window">
                <header class="card-window-bar">
                    <span class="dots"><span></span><span></span><span></span></span>
                    <span class="window-url">faoxima/buy</span>
                </header>
                <div class="card-body">
                    <div class="stepper">
                        ${STEPS.map((s, i) => {
                            const isActive = i === step;
                            const isDone = i < step;
                            return `
                                <span class="step ${isActive ? 'is-active' : ''} ${isDone ? 'is-done' : ''}">
                                    <span class="dot">${i + 1}</span>
                                    <span>${escapeHtml(s.label.replace(/^[\d۰-۹]+\s+/, ''))}</span>
                                </span>
                            `;
                        }).join('')}
                    </div>
                    <div id="buy-step-host"></div>
                </div>
            </article>
        `;

        const host = view.querySelector('#buy-step-host');

        if (step === 0) renderPanelStep(host);
        else if (step === 1) renderCategoryStep(host);
        else if (step === 2) renderTimeStep(host);
        else if (step === 3) renderProductStep(host);
        else if (step === 4) renderConfirmStep(host);
    }

    function back() {
        if (step === 0) return;


        let target = step - 1;


        while (target > 0) {
            if (target === 2 && draft.timeDay === '') {
                target--;
                continue;
            }
            if (target === 1 && draft.categoryId === '') {
                target--;
                continue;
            }
            break;
        }


        if (target <= 3) {
            delete draft.product;
            delete draft.customService;
        }
        if (target <= 2) delete draft.timeDay;
        if (target <= 1) delete draft.categoryId;
        if (target <= 0) delete draft.panel;

        setBuyDraft(draft);
        step = computeStep(draft);
        render();
    }

    function persistAndNext() {
        setBuyDraft(draft);
        step = computeStep(draft);
        render();
    }


    async function renderPanelStep(host) {
        host.innerHTML = `
            <p class="section-title">انتخاب موقعیت</p>
            <h2 class="section-headline">سرویس شما کجا ارائه شود؟</h2>
            <div id="panel-list" class="list mt-md">${skeletonList(4)}</div>
        `;
        try {
            const res = await call('countries');
            const list = Array.isArray(res?.obj) ? res.obj : [];
            const $list = host.querySelector('#panel-list');
            if (!list.length) {
                $list.innerHTML = emptyState('پنلی موجود نیست', 'در حال حاضر هیچ موقعیتی برای خرید فعال نیست.');
                return;
            }
            $list.innerHTML = list.map((p) => `
                <button class="plan" data-id="${escapeHtml(p.id)}" data-custom="${p.is_custom ? '1' : '0'}" data-username="${p.is_username ? '1' : '0'}" data-username-required="${p.is_username_required ? '1' : '0'}" data-note="${p.is_note ? '1' : '0'}" data-name="${escapeHtml(p.name)}">
                    <div class="plan-info">
                        <div class="plan-title">${escapeHtml(p.name)}</div>
                        <div class="plan-meta">
                            ${p.is_custom ? `<span><span class="mono gold">~</span> حجم سفارشی</span>` : ''}
                            ${p.is_username ? `<span><span class="mono gold">@</span> نام کاربری دلخواه${p.is_username_required ? ' (ضروری)' : ''}</span>` : ''}
                        </div>
                    </div>
                    <div class="plan-price">
                        <span class="amt">انتخاب →</span>
                    </div>
                </button>
            `).join('');

            $list.querySelectorAll('.plan').forEach((btn) => {
                btn.addEventListener('click', () => {
                    hapticImpact('light');
                    draft.panel = {
                        id: btn.dataset.id,
                        name: btn.dataset.name,
                        is_custom: btn.dataset.custom === '1',
                        is_username: btn.dataset.username === '1',
                        is_username_required: btn.dataset.usernameRequired === '1',
                        is_note: btn.dataset.note === '1',
                    };
                    persistAndNext();
                });
            });
        } catch (err) {
            host.querySelector('#panel-list').innerHTML = errorBlock(err);
        }
    }

    async function renderCategoryStep(host) {
        host.innerHTML = `
            ${backRow('بازگشت')}
            <p class="section-title">دسته‌بندی</p>
            <h2 class="section-headline">یک دسته‌بندی انتخاب کنید</h2>
            <div id="cat-list" class="list mt-md">${skeletonList(3)}</div>
        `;
        wireBackBtn(host);

        try {
            const res = await call('categories', { params: { country_id: draft.panel.id } });
            const list = Array.isArray(res?.obj) ? res.obj : [];
            const $list = host.querySelector('#cat-list');


            if (!list.length) {
                draft.categoryId = '';
                persistAndNext();
                return;
            }


            $list.innerHTML = `
                <button class="plan" data-id=""><div class="plan-info"><div class="plan-title">همه دسته‌ها</div><div class="plan-meta">بدون فیلتر</div></div><div class="plan-price"><span class="amt">→</span></div></button>
                ${list.map((c) => `
                    <button class="plan" data-id="${escapeHtml(c.id)}">
                        <div class="plan-info"><div class="plan-title">${escapeHtml(c.name)}</div></div>
                        <div class="plan-price"><span class="amt">→</span></div>
                    </button>
                `).join('')}
            `;
            $list.querySelectorAll('.plan').forEach((btn) => {
                btn.addEventListener('click', () => {
                    hapticImpact('light');
                    draft.categoryId = btn.dataset.id || '';
                    persistAndNext();
                });
            });
        } catch (err) {
            host.querySelector('#cat-list').innerHTML = errorBlock(err);
        }
    }

    async function renderTimeStep(host) {
        host.innerHTML = `
            ${backRow('بازگشت')}
            <p class="section-title">مدت زمان</p>
            <h2 class="section-headline">مدت زمان سرویس را انتخاب کنید</h2>
            <div id="time-list" class="list mt-md">${skeletonList(3)}</div>
        `;
        wireBackBtn(host);

        try {
            const res = await call('time_ranges', { params: { country_id: draft.panel.id } });
            const list = Array.isArray(res?.obj) ? res.obj : [];
            const $list = host.querySelector('#time-list');

            if (!list.length) {
                draft.timeDay = '';
                persistAndNext();
                return;
            }

            $list.innerHTML = `
                <button class="plan" data-day=""><div class="plan-info"><div class="plan-title">همه مدت‌ها</div><div class="plan-meta">بدون فیلتر</div></div><div class="plan-price"><span class="amt">→</span></div></button>
                ${list.map((t) => `
                    <button class="plan" data-day="${escapeHtml(t.day)}">
                        <div class="plan-info"><div class="plan-title">${escapeHtml(t.name)}</div></div>
                        <div class="plan-price"><span class="amt">→</span></div>
                    </button>
                `).join('')}
            `;
            $list.querySelectorAll('.plan').forEach((btn) => {
                btn.addEventListener('click', () => {
                    hapticImpact('light');
                    draft.timeDay = btn.dataset.day || '';
                    persistAndNext();
                });
            });
        } catch (err) {
            host.querySelector('#time-list').innerHTML = errorBlock(err);
        }
    }

    async function renderProductStep(host) {
        host.innerHTML = `
            ${backRow('بازگشت')}
            <p class="section-title">انتخاب سرویس</p>
            <h2 class="section-headline">یکی از سرویس‌های زیر را انتخاب کنید</h2>
            ${draft.panel.is_custom ? `
                <div class="row-spread mt-md mb-md">
                    <button class="chip is-active" data-mode="catalog">${icon('fileText')} کاتالوگ</button>
                    <button class="chip" data-mode="custom">${icon('box')} حجم سفارشی</button>
                </div>
            ` : ''}
            <div id="prod-host"></div>
        `;
        wireBackBtn(host);

        const $prodHost = host.querySelector('#prod-host');

        let mode = 'catalog';
        if (draft.panel.is_custom) {
            host.querySelectorAll('[data-mode]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    host.querySelectorAll('[data-mode]').forEach((b) => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    mode = btn.dataset.mode;
                    if (mode === 'catalog') renderCatalog($prodHost);
                    else renderCustom($prodHost);
                });
            });
        }

        renderCatalog($prodHost);

        async function renderCatalog(target) {
            target.innerHTML = `<div class="list mt-sm">${skeletonList(4)}</div>`;
            try {
                const params = { country_id: draft.panel.id };
                if (draft.categoryId) params.category_id = draft.categoryId;
                if (draft.timeDay)    params.time_range_day = draft.timeDay;
                const res = await call('services', { params });
                const list = Array.isArray(res?.obj) ? res.obj : [];
                if (!list.length) {
                    target.innerHTML = emptyState('سرویسی یافت نشد', 'با این تنظیمات هیچ سرویسی موجود نیست.');
                    return;
                }
                target.innerHTML = `
                    <div class="list mt-sm">
                        ${list.map((p) => `
                            <button class="plan" data-id="${escapeHtml(p.id)}" data-name="${escapeHtml(p.name)}" data-traffic="${escapeHtml(p.traffic_gb)}" data-time="${escapeHtml(p.time_days)}" data-price="${escapeHtml(p.price)}">
                                <div class="plan-info">
                                    <div class="plan-title">${escapeHtml(p.name)}</div>
                                    <div class="plan-meta">
                                        <span>${icon('box')} ${escapeHtml(fmtGb(p.traffic_gb))}</span>
                                        <span>${icon('calendar')} ${escapeHtml(fmtDays(p.time_days))}</span>
                                    </div>
                                </div>
                                <div class="plan-price">
                                    <span class="amt">${escapeHtml(fmtPrice(p.price))}</span>
                                </div>
                            </button>
                        `).join('')}
                    </div>
                `;
                target.querySelectorAll('.plan').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        hapticImpact('medium');
                        draft.product = {
                            id: btn.dataset.id,
                            name: btn.dataset.name,
                            traffic_gb: Number(btn.dataset.traffic),
                            time_days: Number(btn.dataset.time),
                            price: Number(btn.dataset.price),
                            custom: false,
                        };
                        delete draft.customService;
                        persistAndNext();
                    });
                });
            } catch (err) {
                target.innerHTML = errorBlock(err);
            }
        }

        async function renderCustom(target) {
            target.innerHTML = `<div class="card" style="padding:14px">${skeletonList(2)}</div>`;
            try {
                const res = await call('custom_price', {
                    params: { country_id: draft.panel.id, traffic_gb: '0', time_days: '0' },
                });
                const obj = res?.obj || {};
                const tMin = Number(obj.traffic_min || 0);
                const tMax = Number(obj.traffic_max || tMin || 1);
                const dMin = Number(obj.time_min || 0);
                const dMax = Number(obj.time_max || dMin || 1);

                if (tMax <= 0 || dMax <= 0 || obj.price === false) {
                    target.innerHTML = emptyState('حجم سفارشی فعال نیست', 'این موقعیت امکان حجم سفارشی ندارد.');
                    return;
                }

                let trafficVal = tMin;
                let timeVal = dMin;

                target.innerHTML = `
                    <div class="card" style="padding:16px">
                        <div class="range-row">
                            <label>حجم (گیگابایت) <span class="v" id="t-val">${fmtNumber(trafficVal)}</span></label>
                            <input id="t-range" class="range-input" type="range" min="${tMin}" max="${tMax}" step="1" value="${trafficVal}" />
                            <div class="muted mono" style="font-size:11px">حداقل: ${fmtNumber(tMin)} GB · حداکثر: ${fmtNumber(tMax)} GB</div>
                        </div>
                        <div class="range-row mt-md">
                            <label>مدت زمان (روز) <span class="v" id="d-val">${fmtNumber(timeVal)}</span></label>
                            <input id="d-range" class="range-input" type="range" min="${dMin}" max="${dMax}" step="1" value="${timeVal}" />
                            <div class="muted mono" style="font-size:11px">حداقل: ${fmtNumber(dMin)} روز · حداکثر: ${fmtNumber(dMax)} روز</div>
                        </div>
                        <div class="kv mt-md"><span class="kv-label">${icon('wallet')} قیمت لحظه‌ای</span><span class="kv-value gold" id="p-val">${escapeHtml(fmtPrice(0))}</span></div>
                        <button class="btn btn-primary btn-block mt-md" id="pick-custom"><span>تایید و ادامه</span><span class="arrow">→</span></button>
                    </div>
                `;
                const $tRange = target.querySelector('#t-range');
                const $dRange = target.querySelector('#d-range');
                const $tVal = target.querySelector('#t-val');
                const $dVal = target.querySelector('#d-val');
                const $pVal = target.querySelector('#p-val');
                const $pick = target.querySelector('#pick-custom');

                let priceFetchSeq = 0;
                async function recalc() {
                    trafficVal = Number($tRange.value);
                    timeVal = Number($dRange.value);
                    $tVal.textContent = fmtNumber(trafficVal);
                    $dVal.textContent = fmtNumber(timeVal);
                    const seq = ++priceFetchSeq;
                    try {
                        const r = await call('custom_price', {
                            params: { country_id: draft.panel.id, traffic_gb: String(trafficVal), time_days: String(timeVal) },
                        });
                        if (seq !== priceFetchSeq) return;
                        const p = r?.obj?.price;
                        $pVal.textContent = fmtPrice(p || 0);
                    } catch (_) {  }
                }
                $tRange.addEventListener('input', recalc);
                $dRange.addEventListener('input', recalc);
                recalc();

                $pick.addEventListener('click', async () => {
                    hapticImpact('medium');

                    try {
                        const r = await call('custom_price', {
                            params: { country_id: draft.panel.id, traffic_gb: String(trafficVal), time_days: String(timeVal) },
                        });
                        const price = Number(r?.obj?.price || 0);
                        draft.product = {
                            id: 'customvolume',
                            name: 'سرویس سفارشی',
                            traffic_gb: trafficVal,
                            time_days: timeVal,
                            price,
                            custom: true,
                        };
                        draft.customService = { traffic_gb: trafficVal, time_days: timeVal };
                        persistAndNext();
                    } catch (err) {
                        toast(err.message || 'خطا در محاسبه قیمت', 'error');
                    }
                });
            } catch (err) {
                target.innerHTML = errorBlock(err);
            }
        }
    }

    async function renderConfirmStep(host) {
        const p = draft.product || {};
        const showUsername = !!draft.panel?.is_username;
        const usernameRequired = !!draft.panel?.is_username_required;
        const showNote = !!draft.panel?.is_note;

        host.innerHTML = `
            ${backRow('بازگشت')}
            <p class="section-title">جمع‌بندی خرید</p>
            <h2 class="section-headline">قبل از پرداخت بررسی کنید</h2>

            <div class="card" style="padding:16px">
                <div class="kv"><span class="kv-label">${icon('pin')} موقعیت</span><span class="kv-value">${escapeHtml(draft.panel.name)}</span></div>
                <div class="kv"><span class="kv-label">${icon('fileText')} سرویس</span><span class="kv-value">${escapeHtml(p.name)}</span></div>
                <div class="kv"><span class="kv-label">${icon('box')} حجم</span><span class="kv-value">${escapeHtml(fmtGb(p.traffic_gb))}</span></div>
                <div class="kv"><span class="kv-label">${icon('calendar')} مدت زمان</span><span class="kv-value">${escapeHtml(fmtDays(p.time_days))}</span></div>
                <div class="kv"><span class="kv-label">${icon('wallet')} مبلغ قابل پرداخت</span><span class="kv-value gold">${escapeHtml(fmtPrice(p.price))}</span></div>
            </div>

            ${showUsername ? `
                <div class="form-row mt-md">
                    <label for="custom-username">نام کاربری دلخواه ${usernameRequired ? '<span style="color:#e74c3c">(ضروری)</span>' : '(اختیاری)'}</label>
                    <input id="custom-username" type="text" placeholder="مثلا alireza" maxlength="40" ${usernameRequired ? 'required' : ''} />
                    ${usernameRequired ? '<small class="muted" style="display:block;margin-top:4px;font-size:11px">⚠️ برای این پنل، انتخاب نام کاربری اجباری است و سرویس بدون نام ساخته نمی‌شود.</small>' : ''}
                </div>
            ` : ''}
            ${showNote ? `
                <div class="form-row">
                    <label for="custom-note">یادداشت (اختیاری)</label>
                    <input id="custom-note" type="text" placeholder="یادداشت کوتاه..." maxlength="120" />
                </div>
            ` : ''}

            <button class="btn btn-primary btn-block mt-md" id="do-purchase">
                <span>پرداخت و ساخت سرویس</span>
                <span class="arrow">→</span>
            </button>
            <p class="muted mono mt-sm" style="font-size:11px;text-align:center">با کلیک روی دکمه بالا، مبلغ از کیف پول شما کسر خواهد شد.</p>

            <!-- Host containers used by the inline payment branch when
                 the wallet balance is short of the service price. They
                 stay empty until the server returns requires_payment=true. -->
            <div id="inline-pay-host" class="mt-md"></div>
        `;
        wireBackBtn(host);

        const $btn = host.querySelector('#do-purchase');
        $btn.addEventListener('click', async () => {
            const customUsername = host.querySelector('#custom-username')?.value?.trim() || '';
            const customNote = host.querySelector('#custom-note')?.value?.trim() || '';

            if (usernameRequired && !customUsername) {
                toast('برای این پنل نام کاربری دلخواه ضروری است؛ لطفاً نام کاربری انتخاب کنید.', 'error');
                host.querySelector('#custom-username')?.focus();
                return;
            }
            if (usernameRequired && !/^[A-Za-z0-9_.-]{3,40}$/.test(customUsername)) {
                toast('نام کاربری معتبر نیست. فقط حروف انگلیسی، عدد، _ . - مجاز است (۳ تا ۴۰ کاراکتر).', 'error');
                host.querySelector('#custom-username')?.focus();
                return;
            }

            const ok = await showConfirm(`آیا از خرید «${p.name}» با مبلغ ${fmtPrice(p.price)} مطمئن هستید؟`);
            if (!ok) return;

            $btn.disabled = true;
            $btn.innerHTML = `<span class="spinner"></span><span>در حال پردازش...</span>`;

            try {
                const body = {
                    country_id: draft.panel.id,
                };
                if (p.custom) {
                    body.custom_service = draft.customService;
                } else {
                    body.service_id = p.id;
                }
                if (customUsername) body.custom_username = customUsername;
                if (customNote)     body.custom_note     = customNote;

                const res = await call('purchase', { method: 'POST', body });


                if (res && res.requires_payment) {
                    hapticImpact('medium');
                    renderInlinePayment(host, res);


                    $btn.disabled = true;
                    $btn.innerHTML = `<span>در انتظار پرداخت کسری</span>`;
                    return;
                }


                hapticNotify('success');
                clearBuyDraft();

                try {
                    const mod = await loadSuccessModule();
                    if (mod && typeof mod.renderServiceSuccess === 'function') {
                        mod.renderServiceSuccess(view, res, {
                            productName: p.name,
                            panelName: draft.panel?.name || '',
                        });
                    } else {
                        renderFallbackSuccess(view, res);
                    }
                } catch (loadErr) {
                    console.warn('[buy] success module load failed, using fallback', loadErr);
                    renderFallbackSuccess(view, res);
                }
            } catch (err) {
                hapticNotify('error');
                const msg = String(err.message || '');
                const data = err.data || {};


                if (data && (data.requires_payment || (data.obj && data.obj.requires_payment))) {
                    hapticImpact('medium');
                    const payload = data.requires_payment ? data : data.obj;
                    renderInlinePayment(host, payload);
                    $btn.disabled = true;
                    $btn.innerHTML = `<span>در انتظار پرداخت کسری</span>`;
                    return;
                }

                const isInsufficient = err.status === 402 ||
                    msg.includes('موجود') || msg.includes('کافی') ||
                    msg.includes('insufficient') || msg.includes('Balance');

                if (isInsufficient) {


                    toast('موجودی شما کافی نیست — یک روش پرداخت انتخاب کنید', 'warn', 5000);
                    setTimeout(() => { window.location.hash = '#/recharge'; }, 800);
                    $btn.disabled = false;
                    $btn.innerHTML = `<span>پرداخت و ساخت سرویس</span><span class="arrow">→</span>`;
                    return;
                }

                toast(err.message || 'خطا در ساخت سرویس', 'error', 5000);
                $btn.disabled = false;
                $btn.innerHTML = `<span>پرداخت و ساخت سرویس</span><span class="arrow">→</span>`;
            }
        });
    }


    async function renderInlinePayment(host, pay) {
        const $inline = host.querySelector('#inline-pay-host');
        if (!$inline) return;

        const amountDue = Number(pay.amount_due || 0);
        const username = String(pay.username || '');
        const balance = Number(pay.balance || 0);
        const price = Number(pay.price || 0);

        $inline.innerHTML = `
            <div class="card-section cart-info-card">
                <div class="cart-banner">
                    ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                    <h3>پرداخت مبلغ کسری</h3>
                    <p class="muted center" style="font-size:12px">
                        موجودی شما برای خرید کامل کافی نیست. مبلغ کسری را با یکی از روش‌های زیر پرداخت کنید؛ پس از تأیید ادمین، سرویس به‌صورت خودکار ساخته خواهد شد.
                    </p>
                </div>

                <div class="kv mt-md">
                    <span class="kv-label">${icon('wallet')} موجودی فعلی</span>
                    <span class="kv-value mono">${escapeHtml(fmtPrice(balance))}</span>
                </div>
                <div class="kv">
                    <span class="kv-label">${icon('coin')} قیمت سرویس</span>
                    <span class="kv-value mono">${escapeHtml(fmtPrice(price))}</span>
                </div>
                <div class="kv">
                    <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                    <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
                </div>
                ${username ? `
                <div class="kv">
                    <span class="kv-label">${icon('user')} نام کاربری سرویس</span>
                    <span class="kv-value mono">${escapeHtml(username)}</span>
                </div>` : ''}

                <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
                <div class="payment-methods" id="inline-pay-list">
                    ${skeletonList(3)}
                </div>

                <div id="pay-form-host"></div>
                <div id="pay-result-host"></div>
            </div>
        `;


        let methods = [];
        try {
            const r = await call('payment_methods');
            const data = r?.obj || {};
            methods = Array.isArray(data.methods) ? data.methods : [];
        } catch (err) {
            $inline.querySelector('#inline-pay-list').innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>خطا در دریافت روش‌های پرداخت</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>`;
            return;
        }

        if (!methods.length) {
            $inline.querySelector('#inline-pay-list').innerHTML = `
                <div class="empty">
                    ${icon('info', 'class="ico ico-xxl ico-muted"')}
                    <h3>روش پرداختی فعال نیست</h3>
                    <p class="muted">برای فعال‌سازی روش‌های پرداخت، با ادمین در تماس باشید.</p>
                </div>`;
            return;
        }

        const $list = $inline.querySelector('#inline-pay-list');
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
                const tg = window.Telegram && window.Telegram.WebApp;
                const u = String(method.url || '');
                const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
                if (tg && isTme && typeof tg.openTelegramLink === 'function') {
                    tg.openTelegramLink(u);
                } else if (tg && typeof tg.openLink === 'function') {
                    tg.openLink(u);
                } else {
                    window.open(u, '_blank', 'noopener');
                }
                return;
            }


            const cryptoOfflineIds = ['crypto_offline', 'digitaltron'];
            if (method.kind === 'crypto_offline' || cryptoOfflineIds.includes(method.id)) {
                try {
                    const mod = await import( './crypto-offline.js');
                    if (mod && typeof mod.renderCryptoOffline === 'function') {
                        mod.renderCryptoOffline(view, {
                            amount: amountDue,
                            purchaseUsername: username,
                            onBack: () => render(),
                        });
                        return;
                    }
                } catch (loadErr) {
                    console.warn('[buy] crypto-offline import failed', loadErr);
                }
                toast('فلوی ارز آفلاین در دسترس نیست', 'error', 4000);
                return;
            }


            try {
                const r = await call('payment_init', {
                    method: 'POST',
                    body: {
                        method: method.id,
                        amount: amountDue,
                        purchase_username: username,
                    },
                });
                const obj = r?.obj || {};
                handleInitResult($inline, method.id, obj, {
                    rootView: view,
                    purchaseUsername: username,
                    successText: 'پس از تأیید پرداخت، سرویس شما به‌صورت خودکار ساخته می‌شود.',
                    successCta:  { href: '#/', label: 'بازگشت به خانه' },
                    onSuccess:   () => { clearBuyDraft(); },
                });
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
            }
        });
    }

    function backRow(label = 'بازگشت') {
        return `
            <button class="page-back" id="back-btn" type="button">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                ${escapeHtml(label)}
            </button>
        `;
    }
    function wireBackBtn(host) {
        const b = host.querySelector('#back-btn');
        if (b) b.addEventListener('click', back);
    }

    function errorBlock(err) {
        return `
            <div class="empty">
                ${icon('alert')}
                <h3>خطا در دریافت اطلاعات</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
    }

    render();

    return () => {

    };
}

function computeStep(d) {
    if (!d.panel) return 0;
    if (d.categoryId === undefined) return 1;
    if (d.timeDay === undefined) return 2;
    if (!d.product) return 3;
    return 4;
}

