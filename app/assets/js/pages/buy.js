import { call } from '../api.js';
import {
    escapeHtml, fmtPrice, fmtGb, fmtDays, skeletonList, emptyState, toast, fmtNumber,
} from '../utils.js';
import { hapticImpact, hapticNotify, showConfirm } from '../telegram.js';
import { getBuyDraft, setBuyDraft, clearBuyDraft } from '../state.js';

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


        if (step === 4) {
            delete draft.product;
            delete draft.customService;
        } else if (step === 3) {
            delete draft.timeDay;
        } else if (step === 2) {
            delete draft.categoryId;

            delete draft.timeDay;
        } else if (step === 1) {
            delete draft.panel;
            delete draft.categoryId;
            delete draft.timeDay;
        }
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
                <button class="plan" data-id="${escapeHtml(p.id)}" data-custom="${p.is_custom ? '1' : '0'}" data-username="${p.is_username ? '1' : '0'}" data-note="${p.is_note ? '1' : '0'}" data-name="${escapeHtml(p.name)}">
                    <div class="plan-info">
                        <div class="plan-title">${escapeHtml(p.name)}</div>
                        <div class="plan-meta">
                            ${p.is_custom ? `<span><span class="mono gold">~</span> حجم سفارشی</span>` : ''}
                            ${p.is_username ? `<span><span class="mono gold">@</span> نام کاربری دلخواه</span>` : ''}
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
                    <button class="chip is-active" data-mode="catalog"><span class="glyph">#</span> کاتالوگ</button>
                    <button class="chip" data-mode="custom"><span class="glyph">~</span> حجم سفارشی</button>
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
                                        <span><span class="glyph">~</span> ${escapeHtml(fmtGb(p.traffic_gb))}</span>
                                        <span><span class="glyph">/</span> ${escapeHtml(fmtDays(p.time_days))}</span>
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
                        <div class="kv mt-md"><span class="kv-label"><span class="glyph">$</span> قیمت لحظه‌ای</span><span class="kv-value gold" id="p-val">${escapeHtml(fmtPrice(0))}</span></div>
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
        const showNote = !!draft.panel?.is_note;

        host.innerHTML = `
            ${backRow('بازگشت')}
            <p class="section-title">جمع‌بندی خرید</p>
            <h2 class="section-headline">قبل از پرداخت بررسی کنید</h2>

            <div class="card" style="padding:16px">
                <div class="kv"><span class="kv-label"><span class="glyph">@</span> موقعیت</span><span class="kv-value">${escapeHtml(draft.panel.name)}</span></div>
                <div class="kv"><span class="kv-label"><span class="glyph">#</span> سرویس</span><span class="kv-value">${escapeHtml(p.name)}</span></div>
                <div class="kv"><span class="kv-label"><span class="glyph">~</span> حجم</span><span class="kv-value">${escapeHtml(fmtGb(p.traffic_gb))}</span></div>
                <div class="kv"><span class="kv-label"><span class="glyph">/</span> مدت زمان</span><span class="kv-value">${escapeHtml(fmtDays(p.time_days))}</span></div>
                <div class="kv"><span class="kv-label"><span class="glyph">$</span> مبلغ قابل پرداخت</span><span class="kv-value gold">${escapeHtml(fmtPrice(p.price))}</span></div>
            </div>

            ${showUsername ? `
                <div class="form-row mt-md">
                    <label for="custom-username">نام کاربری دلخواه (اختیاری)</label>
                    <input id="custom-username" type="text" placeholder="مثلا alireza" maxlength="40" />
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
        `;
        wireBackBtn(host);

        const $btn = host.querySelector('#do-purchase');
        $btn.addEventListener('click', async () => {
            const customUsername = host.querySelector('#custom-username')?.value?.trim() || '';
            const customNote = host.querySelector('#custom-note')?.value?.trim() || '';

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
                hapticNotify('success');
                clearBuyDraft();

                view.innerHTML = `
                    <article class="card card-window">
                        <header class="card-window-bar">
                            <span class="dots"><span></span><span></span><span></span></span>
                            <span class="window-url">faoxima/buy/success</span>
                        </header>
                        <div class="card-body" style="text-align:center">
                            <div style="font-size:48px;line-height:1;color:var(--green);margin:8px 0">✓</div>
                            <h2 style="margin:8px 0;font-size:20px">سرویس شما با موفقیت ساخته شد!</h2>
                            <p class="muted">جزئیات و کانفیگ سرویس برای شما در تلگرام ارسال شد.</p>
                            <div class="kv mt-md"><span class="kv-label">کد پیگیری</span><span class="kv-value mono gold">${escapeHtml(res.order_id || res.service?.id || '—')}</span></div>
                            <div class="kv"><span class="kv-label">نام کاربری</span><span class="kv-value mono">${escapeHtml(res.service?.username || '—')}</span></div>
                            <div class="row-spread mt-md">
                                <a href="#/services/${encodeURIComponent(res.service?.username || '')}" class="btn btn-primary">مشاهده سرویس</a>
                                <a href="#/" class="btn btn-ghost">خانه</a>
                            </div>
                        </div>
                    </article>
                `;
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در ساخت سرویس', 'error', 5000);
                $btn.disabled = false;
                $btn.innerHTML = `<span>پرداخت و ساخت سرویس</span><span class="arrow">→</span>`;
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
                <span class="glyph">!</span>
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

