import { call, callForm, callBlob } from '../api.js?v=0.0.52';
import { escapeHtml, skeletonList, toast, fmtNumber, copyToClipboard, blobToDataUrl } from '../utils.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { showBackButton } from '../telegram.js?v=0.0.52';

const REACTION_EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
const mediaData = new WeakMap();

function statusMeta(status, open) {
    if (!open || status === 'close') return { text: 'بسته', cls: 'is-warn', ic: 'check' };
    if (status === 'Unseen' || status === 'Customerresponse') return { text: 'در انتظار پاسخ', cls: 'is-active', ic: 'clock' };
    if (status === 'Answered') return { text: 'پاسخ داده شد', cls: 'is-active', ic: 'check' };
    return { text: 'باز', cls: 'is-active', ic: 'info' };
}

function fmtJalaliParts(raw) {
    const s = String(raw || '').trim();
    if (!s) return { date: '', time: '' };
    const m = s.match(/^(\d{3,4})\/(\d{1,2})\/(\d{1,2})[ ](\d{2}):(\d{2})/);
    if (!m) return { date: s, time: '' };
    return { date: `${m[1]}/${m[2].padStart(2, '0')}/${m[3].padStart(2, '0')}`, time: `${m[4]}:${m[5]}` };
}

function loadMediaInto(view) {
    view.querySelectorAll('.tk-thumb[data-media-id]').forEach(async (el) => {
        const id = el.getAttribute('data-media-id');
        const idx = el.getAttribute('data-media-idx') || '0';
        const type = el.getAttribute('data-media-type');
        try {
            const blob = await callBlob('ticket_media', { params: { id, i: idx } });
            const dataUrl = await blobToDataUrl(blob);
            mediaData.set(el, dataUrl);
            if (type === 'video') {
                el.innerHTML = `<video src="${dataUrl}" preload="metadata" muted style="width:100%;height:100%;object-fit:cover"></video><span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;background:rgba(0,0,0,.32)">▶</span>`;
            } else {
                el.innerHTML = `<img src="${dataUrl}" alt="" style="width:100%;height:100%;object-fit:cover" />`;
            }
        } catch (_) {
            el.innerHTML = `<span class="muted" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px">⚠️</span>`;
        }
    });
}

function ensureLightbox() {
    let lb = document.getElementById('tk-lb');
    if (lb) return lb;
    lb = document.createElement('div');
    lb.id = 'tk-lb';
    lb.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:99999;padding:16px';
    lb.innerHTML = '<span id="tk-lb-x" style="position:absolute;top:8px;inset-inline-end:14px;color:#fff;font-size:30px;cursor:pointer">&times;</span><div id="tk-lb-body"></div>';
    document.body.appendChild(lb);
    lb.addEventListener('click', (e) => {
        if (e.target === lb || e.target.id === 'tk-lb-x') {
            lb.style.display = 'none';
            document.getElementById('tk-lb-body').innerHTML = '';
        }
    });
    return lb;
}

function openLightbox(url, type) {
    if (!url) return;
    const lb = ensureLightbox();
    document.getElementById('tk-lb-body').innerHTML = type === 'video'
        ? `<video src="${url}" controls autoplay playsinline style="max-width:94vw;max-height:88vh;border-radius:10px"></video>`
        : `<img src="${url}" alt="" style="max-width:94vw;max-height:88vh;border-radius:10px" />`;
    lb.style.display = 'flex';
}

function gatherFiles(root) {
    const out = [];
    if (!root) return out;
    root.querySelectorAll('.tk-file-input').forEach((i) => {
        if (i.files) { for (let k = 0; k < i.files.length; k++) out.push(i.files[k]); }
    });
    return out.slice(0, 5);
}

function fmtFileSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function createUploadSlot(file) {
    const slot = document.createElement('div');
    slot.className = 'tk-upload-slot';
    slot.innerHTML = `
        <input type="file" accept="image/*,video/*" class="tk-file-input" hidden />
        <div class="tk-upload-preview">
            <div class="tk-upload-thumb"></div>
            <div class="tk-upload-info">
                <div class="tk-upload-name"></div>
                <div class="tk-upload-meta muted"></div>
            </div>
            <button type="button" class="tk-upload-remove" aria-label="حذف فایل">
                ${icon('close', 'class="ico"')}
            </button>
        </div>
    `;

    const input = slot.querySelector('.tk-file-input');
    const thumb = slot.querySelector('.tk-upload-thumb');
    const nameEl = slot.querySelector('.tk-upload-name');
    const metaEl = slot.querySelector('.tk-upload-meta');
    const removeBtn = slot.querySelector('.tk-upload-remove');
    async function setFile(f) {
        const dt = new DataTransfer();
        dt.items.add(f);
        input.files = dt.files;

        const isVideo = f.type.startsWith('video/');
        if (isVideo) {
            thumb.innerHTML = icon('fileText', 'class="ico ico-lg"');
        } else {
            try {
                const dataUrl = await blobToDataUrl(f);
                thumb.innerHTML = `<img src="${dataUrl}" alt="" />`;
            } catch (_) {
                thumb.innerHTML = icon('fileText', 'class="ico ico-lg"');
            }
        }
        nameEl.textContent = f.name;
        metaEl.textContent = fmtFileSize(f.size);
    }

    removeBtn.addEventListener('click', () => {
        slot.dispatchEvent(new CustomEvent('tk-slot-remove', { bubbles: true }));
        slot.remove();
    });

    if (file) setFile(file);

    return slot;
}

function wireAddFile(wrapId, btnId) {
    const wrap = document.getElementById(wrapId);
    const btn = document.getElementById(btnId);
    if (!wrap || !btn) return;
    const label = btn.querySelector('.tk-add-file-label');

    function updateAddButton() {
        const count = wrap.querySelectorAll('.tk-file-input').length;
        btn.style.display = count >= 5 ? 'none' : '';
        if (label) label.textContent = count > 0 ? 'افزودن فایل دیگر' : 'انتخاب عکس یا ویدیو';
    }

    wrap.addEventListener('tk-slot-remove', updateAddButton);

    const picker = document.createElement('input');
    picker.type = 'file';
    picker.accept = 'image/*,video/*';
    picker.hidden = true;
    wrap.parentElement.appendChild(picker);

    function openPicker() {
        if (wrap.querySelectorAll('.tk-file-input').length >= 5) return;
        picker.value = '';
        picker.click();
    }

    picker.addEventListener('change', () => {
        const f = picker.files && picker.files[0];
        if (!f) return;
        wrap.appendChild(createUploadSlot(f));
        updateAddButton();
    });

    btn.addEventListener('click', openPicker);
    updateAddButton();
}

function ticketRowHtml(t) {
    const meta = statusMeta(t.status, t.open);
    const title = t.subject && t.subject !== '' ? t.subject : t.department;
    const { date, time } = fmtJalaliParts(t.last_time);
    return `
        <a href="#/tickets/${encodeURIComponent(t.tracking)}" class="list-item">
            <div class="li-main">
                <div class="li-title">${escapeHtml(title)}</div>
                <div class="li-sub li-sub-stacked">
                    <span class="li-sub-line">${escapeHtml(t.department)}</span>
                    ${time ? `<span class="li-sub-line">${escapeHtml(time)}</span>` : ''}
                    <span class="li-sub-line">${escapeHtml(date)}</span>
                </div>
            </div>
            <span class="badge ${meta.cls}">${icon(meta.ic, 'class="ico"')} ${escapeHtml(meta.text)}</span>
            <span class="li-action" aria-hidden="true">${icon('chevronLeft', 'class="ico"')}</span>
        </a>
    `;
}

export async function tickets(view) {
    let page = 1;
    const limit = 4;
    let totalPages = 1;

    view.innerHTML = `
        <article class="card card-window card-bare">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/tickets</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('send')} تیکت‌های پشتیبانی</p>
                <div id="tk-list" class="list mt-sm">${skeletonList(4)}</div>

                <div class="pager hidden" id="tk-pager">
                    <button class="btn btn-ghost" id="tk-prev">${icon('chevronRight', 'class="ico ico-leading"')}قبلی</button>
                    <span class="pager-info" id="tk-pager-info"></span>
                    <button class="btn btn-ghost" id="tk-next">بعدی${icon('chevronLeft', 'class="ico ico-trailing"')}</button>
                </div>

                <a href="#/tickets/new" class="btn btn-primary btn-block mt-md">
                    ${icon('plus', 'class="ico ico-leading"')}
                    <span>تیکت جدید</span>
                </a>
            </div>
        </article>
    `;

    const $list = view.querySelector('#tk-list');
    const $pager = view.querySelector('#tk-pager');
    const $info = view.querySelector('#tk-pager-info');
    const $prev = view.querySelector('#tk-prev');
    const $next = view.querySelector('#tk-next');

    async function load() {
        $list.innerHTML = skeletonList(4);
        $pager.classList.add('hidden');

        try {
            const res = await call('tickets', { params: { page: String(page), limit: String(limit) } });
            const items = res?.obj?.items || [];
            totalPages = Number(res?.obj?.total_pages) || 0;

            if (items.length === 0) {
                $list.innerHTML = `
                    <div class="empty">
                        ${icon('info', 'class="ico ico-xxl ico-muted"')}
                        <h3>تیکتی ندارید</h3>
                        <p class="muted">برای ارتباط با پشتیبانی یک تیکت جدید بسازید.</p>
                    </div>
                `;
                return;
            }

            $list.innerHTML = items.map(ticketRowHtml).join('');

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $info.textContent = `صفحه ${page} از ${totalPages}`;
                $prev.disabled = page <= 1;
                $next.disabled = page >= totalPages;
            }
        } catch (err) {
            $list.innerHTML = `
                <div class="empty">
                    ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                    <h3>خطا در دریافت تیکت‌ها</h3>
                    <p class="muted">${escapeHtml(err.message || '')}</p>
                </div>
            `;
        }
    }

    $prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $next.addEventListener('click', () => { if (page < totalPages) { page++; load(); } });

    await load();
}

export async function ticketNew(view) {
    const cleanup = showBackButton(() => { window.location.hash = '#/tickets'; });

    view.innerHTML = `
        <a href="#/tickets" class="page-back">${icon('chevronLeft', 'class="ico"')}<span>بازگشت</span></a>
        <article class="card card-window card-bare">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/tickets/new</span>
            </header>
            <div class="card-body">
                <p class="section-title">${icon('plus')} تیکت جدید</p>
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px;display:block;margin-bottom:4px">دپارتمان</label>
                    <select id="tk-dep" style="width:100%;background:var(--surface-2);color:var(--text);border:1px solid var(--border);border-radius:12px;padding:11px 12px;font:inherit"></select>
                </div>
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px;display:block;margin-bottom:4px">موضوع</label>
                    <input id="tk-subj" type="text" maxlength="120" placeholder="مثلاً: مشکل در اتصال" />
                </div>
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px;display:block;margin-bottom:4px">متن پیام</label>
                    <textarea id="tk-text" rows="4" maxlength="2000" placeholder="پیام خود را بنویسید..."></textarea>
                </div>
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px;display:block;margin-bottom:4px">پیوست (عکس یا ویدیو، تا ۵ مورد، اختیاری)</label>
                    <div id="tk-file-wrap" class="tk-upload-wrap"></div>
                    <button type="button" id="tk-add-file" class="btn btn-ghost btn-block mt-sm">${icon('plus', 'class="ico ico-leading"')}<span class="tk-add-file-label">انتخاب عکس یا ویدیو</span></button>
                </div>
                <button id="tk-submit" type="button" class="btn btn-primary btn-block mt-md">
                    ${icon('send', 'class="ico ico-leading"')}
                    <span class="tk-submit-label">ارسال تیکت</span>
                </button>
            </div>
        </article>
    `;

    const $dep = view.querySelector('#tk-dep');
    const $subj = view.querySelector('#tk-subj');
    const $text = view.querySelector('#tk-text');
    const $submit = view.querySelector('#tk-submit');
    const $label = $submit.querySelector('.tk-submit-label');
    wireAddFile('tk-file-wrap', 'tk-add-file');

    try {
        const res = await call('ticket_departments');
        const deps = res?.obj?.items || [];
        if (deps.length === 0) {
            $dep.innerHTML = `<option value="0">پشتیبانی</option>`;
        } else {
            $dep.innerHTML = deps.map((d) => `<option value="${escapeHtml(String(d.id))}">${escapeHtml(d.name)}</option>`).join('');
        }
    } catch (_) {
        $dep.innerHTML = `<option value="0">پشتیبانی</option>`;
    }

    $submit.addEventListener('click', async () => {
        const text = ($text.value || '').trim();
        const subject = ($subj.value || '').trim();
        const files = gatherFiles(document.getElementById('tk-file-wrap'));
        if (text === '' && files.length === 0) { toast('متن یا پیوست را وارد کنید', 'warn'); return; }

        const old = $label.textContent;
        $submit.disabled = true;
        $label.textContent = 'در حال ارسال…';
        try {
            const res = await callForm('ticket_create', {
                department_id: $dep.value || '0',
                subject,
                text,
            }, files);
            toast(res?.obj?.message || 'تیکت ثبت شد', 'success', 2500);
            const tracking = res?.obj?.tracking || '';
            window.location.hash = tracking ? `#/tickets/${encodeURIComponent(tracking)}` : '#/tickets';
        } catch (err) {
            toast(err.message || 'خطا در ثبت تیکت', 'error', 4000);
            $submit.disabled = false;
            $label.textContent = old;
        }
    });

    return cleanup;
}

export async function ticketDetail(view, encodedTracking) {
    const tracking = decodeURIComponent(encodedTracking || '');
    const cleanup = showBackButton(() => { window.location.hash = '#/tickets'; });

    view.innerHTML = `
        <a href="#/tickets" class="page-back">${icon('chevronLeft', 'class="ico"')}<span>بازگشت</span></a>
        <article class="card card-window card-bare">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/ticket</span>
            </header>
            <div class="card-body" id="tk-body">${skeletonList(4)}</div>
        </article>
    `;

    const $body = view.querySelector('#tk-body');
    let currentData = null;

    async function load() {
        $body.innerHTML = skeletonList(4);
        let data;
        try {
            const res = await call('ticket_thread', { params: { t: tracking } });
            data = res?.obj;
        } catch (err) {
            $body.innerHTML = `<div class="empty">${icon('alert', 'class="ico ico-xxl ico-warn"')}<h3>خطا</h3><p class="muted">${escapeHtml(err.message || '')}</p></div>`;
            return;
        }
        if (!data) {
            $body.innerHTML = `<div class="empty">${icon('info', 'class="ico ico-xxl ico-muted"')}<h3>تیکتی یافت نشد</h3></div>`;
            return;
        }
        currentData = data;
        render(data);
    }

    // Lightweight background poll so read receipts flip to "seen" live without a full re-render
    // (a full render() would re-wire click listeners and disrupt scroll/focus/open pickers).
    async function refreshReceipts() {
        if (!currentData) return;
        try {
            const res = await call('ticket_thread', { params: { t: tracking } });
            const fresh = res?.obj;
            if (!fresh || !Array.isArray(fresh.messages)) return;
            const freshById = {};
            fresh.messages.forEach((m) => { freshById[String(m.id)] = m; });
            (currentData.messages || []).forEach((m) => {
                const f = freshById[String(m.id)];
                if (!f) return;
                if (f.seen !== m.seen) {
                    m.seen = f.seen;
                    const row = $body.querySelector(`.tk-bubble-row[data-msg-id="${CSS.escape(String(m.id))}"]`);
                    const receipt = row ? row.querySelector('.tk-receipt') : null;
                    if (receipt) {
                        receipt.classList.toggle('is-seen', !!f.seen);
                        if (f.seen && !receipt.querySelector('.tk-receipt-2')) {
                            receipt.insertAdjacentHTML('beforeend', icon('check', 'class="ico tk-receipt-2"'));
                        } else if (!f.seen) {
                            const second = receipt.querySelector('.tk-receipt-2');
                            if (second) second.remove();
                        }
                    }
                }
                const oldR = JSON.stringify(m.reactions || []);
                const newR = JSON.stringify(f.reactions || []);
                if (oldR !== newR) {
                    m.reactions = f.reactions;
                    const row = $body.querySelector(`.tk-bubble-row[data-msg-id="${CSS.escape(String(m.id))}"]`);
                    const wrap = row ? row.querySelector('.tk-bubble-wrap') : null;
                    if (wrap) {
                        const old = wrap.querySelector('.tk-reactions');
                        if (old) old.remove();
                        const list = Array.isArray(f.reactions) ? f.reactions : [];
                        if (list.length) {
                            const box = document.createElement('div');
                            box.className = 'tk-reactions';
                            box.innerHTML = list.map((r) => `<span class="tk-reaction-chip${r.mine ? ' is-mine' : ''}" data-emoji="${escapeHtml(r.emoji)}">${escapeHtml(r.emoji)}</span>`).join('');
                            wrap.querySelector('.tk-bubble').insertAdjacentElement('afterend', box);
                        }
                    }
                }
            });
        } catch (_) {
            // silent — polling errors shouldn't interrupt the user
        }
    }
    const pollTimer = setInterval(refreshReceipts, 4000);

    let replyTarget = null;

    function render(data) {
        const meta = statusMeta(data.status, data.open);
        const msgs = Array.isArray(data.messages) ? data.messages : [];
        const bubbles = msgs.map((m) => {
            const isAdmin = m.sender === 'admin';
            const who = isAdmin ? '👨‍💻 پشتیبانی' : '👤 شما';
            const align = isAdmin ? 'flex-start' : 'flex-end';
            const bg = isAdmin ? 'var(--gold-soft)' : 'var(--surface-2)';
            const bd = isAdmin ? 'var(--border-strong)' : 'var(--border)';
            const mediaList = Array.isArray(m.media) ? m.media : [];
            const mediaHtml = mediaList.length
                ? `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">` + mediaList.map((mm, mi) =>
                    `<div class="tk-thumb" data-media-id="${escapeHtml(String(m.id))}" data-media-idx="${mi}" data-media-type="${escapeHtml(mm.type || 'photo')}" style="width:82px;height:82px;border-radius:10px;overflow:hidden;background:var(--surface-2);border:1px solid var(--border);position:relative;cursor:pointer;flex:0 0 auto"><span class="muted" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">${icon('clock', 'class="ico"')}</span></div>`
                ).join('') + `</div>`
                : '';
            const bodyHtml = m.body && m.body !== '' ? `<div style="word-break:break-word">${escapeHtml(m.body)}</div>` : '';

            const replyTo = m.reply_to;
            const replyQuoteHtml = replyTo
                ? `<div class="tk-quote">${escapeHtml((replyTo.body || '').slice(0, 120) || 'پیوست')}</div>`
                : '';

            const reactions = Array.isArray(m.reactions) ? m.reactions : [];
            const reactionsHtml = reactions.length
                ? `<div class="tk-reactions">` + reactions.map((r) =>
                    `<span class="tk-reaction-chip${r.mine ? ' is-mine' : ''}" data-emoji="${escapeHtml(r.emoji)}">${escapeHtml(r.emoji)}</span>`
                ).join('') + `</div>`
                : '';

            const receiptHtml = !isAdmin
                ? `<span class="tk-receipt ${m.seen ? 'is-seen' : ''}">${icon('check', 'class="ico"')}${m.seen ? icon('check', 'class="ico tk-receipt-2"') : ''}</span>`
                : '';

            const msgTimeParts = fmtJalaliParts(m.time);
            const msgTimeLabel = msgTimeParts.time ? `${msgTimeParts.time} ${msgTimeParts.date}` : (m.time || '');

            return `
                <div class="tk-bubble-row" style="display:flex;justify-content:${align}" data-msg-id="${escapeHtml(String(m.id))}">
                    <div class="tk-bubble-wrap" style="max-width:82%">
                        <div class="tk-bubble" style="background:${bg};border:1px solid ${bd};border-radius:14px;padding:9px 12px">
                            <div class="muted" style="font-size:11px;margin-bottom:4px">${who}</div>
                            ${replyQuoteHtml}
                            ${bodyHtml}
                            ${mediaHtml}
                            <div class="muted mono" style="font-size:10.5px;margin-top:6px;text-align:end;display:flex;align-items:center;justify-content:flex-end;gap:4px">
                                <span>${escapeHtml(msgTimeLabel)}</span>
                                ${receiptHtml}
                            </div>
                        </div>
                        ${reactionsHtml}
                        <div class="tk-msg-actions">
                            ${isAdmin ? `<button type="button" class="tk-msg-action-btn" data-act="react" title="واکنش">🙂</button>` : ''}
                            <button type="button" class="tk-msg-action-btn" data-act="reply" title="پاسخ">${icon('send', 'class="ico"')}</button>
                            <button type="button" class="tk-msg-action-btn" data-act="copy" title="کپی">${icon('copy', 'class="ico"')}</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const replyBox = data.open
            ? `
                <div class="card-section">
                    <p class="section-title">${icon('send')} پاسخ جدید</p>
                    <div id="tk-reply-preview" class="tk-reply-preview hidden"></div>
                    <div class="form-row mt-sm">
                        <textarea id="tk-reply" rows="3" maxlength="2000" placeholder="پاسخ خود را بنویسید..."></textarea>
                    </div>
                    <div class="form-row mt-sm">
                        <div id="tk-reply-file-wrap" class="tk-upload-wrap"></div>
                        <button type="button" id="tk-reply-add-file" class="btn btn-ghost btn-block mt-sm">${icon('plus', 'class="ico ico-leading"')}<span class="tk-add-file-label">انتخاب عکس یا ویدیو</span></button>
                    </div>
                    <div class="row-spread mt-sm gap-sm stack-on-mobile">
                        <button id="tk-reply-send" type="button" class="btn btn-primary btn-block" style="flex:1">
                            ${icon('send', 'class="ico ico-leading"')}<span class="tk-reply-label">ارسال</span>
                        </button>
                        <button id="tk-close" type="button" class="btn btn-ghost btn-block" style="flex:1">
                            ${icon('check', 'class="ico ico-leading"')}<span>بستن تیکت</span>
                        </button>
                    </div>
                </div>
            `
            : `<div class="card-section"><p class="muted center" style="font-size:13px">🔒 این تیکت بسته است. تا زمانی که پشتیبانی پاسخ ندهد یا آن را باز نکند نمی‌توانید پیام بفرستید.</p></div>`;

        $body.innerHTML = `
            <div class="row-spread">
                <div>
                    <div class="muted mono" style="font-size:12px">#${escapeHtml(data.tracking)}</div>
                    <h2 style="margin:4px 0 0;font-size:18px;font-weight:700">${escapeHtml(data.subject && data.subject !== '' ? data.subject : data.department)}</h2>
                    <div class="muted" style="font-size:12px;margin-top:2px">${escapeHtml(data.department)}</div>
                </div>
                <span class="badge ${meta.cls}">${icon(meta.ic, 'class="ico"')} ${escapeHtml(meta.text)}</span>
            </div>
            <div class="card-section">
                <div id="tk-msgs" style="max-height:55vh;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:2px">${bubbles || '<p class="muted center">پیامی نیست</p>'}</div>
            </div>
            ${replyBox}
        `;

        loadMediaInto($body);
        var msgsBox = $body.querySelector('#tk-msgs');
        if (msgsBox) msgsBox.scrollTop = msgsBox.scrollHeight;
        if (!$body.dataset.lbWired) {
            $body.dataset.lbWired = '1';
            $body.addEventListener('click', function (e) {
                var th = e.target.closest ? e.target.closest('.tk-thumb') : null;
                if (!th) return;
                openLightbox(mediaData.get(th), th.getAttribute('data-media-type'));
            });
        }

        const msgById = {};
        msgs.forEach((m) => { msgById[String(m.id)] = m; });

        function updateReplyPreview() {
            const $preview = $body.querySelector('#tk-reply-preview');
            if (!$preview) return;
            if (!replyTarget) {
                $preview.classList.add('hidden');
                $preview.innerHTML = '';
                return;
            }
            const snippet = (replyTarget.body || '').slice(0, 140) || 'پیوست';
            const who = replyTarget.sender === 'admin' ? 'پشتیبانی' : 'شما';
            $preview.classList.remove('hidden');
            $preview.innerHTML = `
                <div class="tk-reply-preview-bar">
                    <div class="tk-reply-preview-body">
                        <div class="tk-reply-preview-who">${escapeHtml(who)}</div>
                        <div class="tk-reply-preview-text">${escapeHtml(snippet)}</div>
                    </div>
                    <button type="button" id="tk-reply-cancel" class="tk-reply-cancel" aria-label="لغو پاسخ">${icon('close', 'class="ico"')}</button>
                </div>
            `;
            const $cancel = $preview.querySelector('#tk-reply-cancel');
            if ($cancel) $cancel.addEventListener('click', () => { replyTarget = null; updateReplyPreview(); });
        }
        updateReplyPreview();

        function closeReactionPickers() {
            document.querySelectorAll('.tk-reaction-picker').forEach((el) => el.remove());
        }

        function positionFloatingPicker(picker, anchorBtn) {
            const r = anchorBtn.getBoundingClientRect();
            let top = r.top - 44;
            if (top < 8) top = r.bottom + 6;
            picker.style.top = `${top}px`;
            let left = r.left;
            const maxLeft = window.innerWidth - picker.offsetWidth - 8;
            if (left > maxLeft) left = Math.max(8, maxLeft);
            picker.style.left = `${left}px`;
        }

        $body.addEventListener('click', async (e) => {
            const actBtn = e.target.closest ? e.target.closest('.tk-msg-action-btn') : null;
            if (actBtn) {
                const row = actBtn.closest('.tk-bubble-row');
                const mid = row ? row.getAttribute('data-msg-id') : null;
                const msg = mid ? msgById[mid] : null;
                if (!msg) return;
                const act = actBtn.getAttribute('data-act');

                if (act === 'copy') {
                    const ok = await copyToClipboard(msg.body || '');
                    toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error', 1800);
                    return;
                }

                if (act === 'reply') {
                    replyTarget = { id: msg.id, body: msg.body, sender: msg.sender };
                    updateReplyPreview();
                    const $reply = $body.querySelector('#tk-reply');
                    if ($reply) $reply.focus();
                    return;
                }

                if (act === 'react') {
                    closeReactionPickers();
                    const picker = document.createElement('div');
                    picker.className = 'tk-reaction-picker tk-reaction-picker-floating';
                    picker.innerHTML = REACTION_EMOJI.map((em) => `<button type="button" class="tk-reaction-opt" data-emoji="${escapeHtml(em)}">${em}</button>`).join('');
                    document.body.appendChild(picker);
                    positionFloatingPicker(picker, actBtn);
                    picker.addEventListener('click', async (ev) => {
                        const opt = ev.target.closest('.tk-reaction-opt');
                        if (!opt) return;
                        const emoji = opt.getAttribute('data-emoji');
                        const existingMine = (msg.reactions || []).find((r) => r.mine);
                        const toSend = existingMine && existingMine.emoji === emoji ? '' : emoji;
                        closeReactionPickers();
                        try {
                            const res = await call('ticket_react', { method: 'POST', body: { message_id: String(msg.id), emoji: toSend } });
                            msg.reactions = res?.obj?.reactions || [];
                            render(data);
                        } catch (err) {
                            toast(err.message || 'خطا در ثبت واکنش', 'error', 2500);
                        }
                    });
                    return;
                }
                return;
            }
            if (!e.target.closest('.tk-reaction-picker') && !e.target.closest('.tk-msg-action-btn')) {
                closeReactionPickers();
            }
        });

        if (!document.body.dataset.tkPickerScrollWired) {
            document.body.dataset.tkPickerScrollWired = '1';
            window.addEventListener('scroll', closeReactionPickers, true);
            window.addEventListener('resize', closeReactionPickers);
        }

        if (data.open) {
            const $reply = $body.querySelector('#tk-reply');
            const $send = $body.querySelector('#tk-reply-send');
            const $rlabel = $send.querySelector('.tk-reply-label');
            const $close = $body.querySelector('#tk-close');
            wireAddFile('tk-reply-file-wrap', 'tk-reply-add-file');

            $send.addEventListener('click', async () => {
                const text = ($reply.value || '').trim();
                const files = gatherFiles(document.getElementById('tk-reply-file-wrap'));
                if (text === '' && (!files || files.length === 0)) { toast('متن یا پیوست را وارد کنید', 'warn'); return; }
                const old = $rlabel.textContent;
                $send.disabled = true;
                $rlabel.textContent = 'در حال ارسال…';
                try {
                    const payload = { t: tracking, text };
                    if (replyTarget) payload.reply_to_id = String(replyTarget.id);
                    const res = await callForm('ticket_reply', payload, files);
                    toast(res?.obj?.message || 'ارسال شد', 'success', 2500);
                    replyTarget = null;
                    await load();
                } catch (err) {
                    toast(err.message || 'خطا در ارسال', 'error', 4000);
                    $send.disabled = false;
                    $rlabel.textContent = old;
                }
            });

            $close.addEventListener('click', async () => {
                $close.disabled = true;
                try {
                    const res = await callForm('ticket_close', { t: tracking }, null);
                    toast(res?.obj?.message || 'تیکت بسته شد', 'success', 2500);
                    await load();
                } catch (err) {
                    toast(err.message || 'خطا', 'error', 4000);
                    $close.disabled = false;
                }
            });
        }
    }

    await load();
    return () => {
        clearInterval(pollTimer);
        cleanup();
    };
}
