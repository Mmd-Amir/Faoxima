(function () {
    'use strict';

    var root = document.getElementById('saveMode');
    var form = document.getElementById('textForm');
    if (!root || !form) return;

    var csrfInput = form.querySelector('input[name="_csrf"]');
    var csrf = csrfInput ? csrfInput.value : '';
    var primary = document.getElementById('textSaveBtn');
    var els = {
        session: document.getElementById('smSession'),
        pill: document.getElementById('smPill'),
        owner: document.getElementById('smOwner'),
        remain: document.getElementById('smRemain'),
        remainShort: document.getElementById('smRemainShort'),
        expireAt: document.getElementById('smExpireAt'),
        rev: document.getElementById('smRev'),
        keys: document.getElementById('smKeys'),
        ring: document.getElementById('smRing'),
        bar: document.getElementById('smBar'),
        notice: document.getElementById('smNotice'),
        noticeText: document.getElementById('smNoticeText'),
        noticeAction: document.getElementById('smNoticeAction'),
        noticeClose: document.getElementById('smNoticeClose'),
        hint: document.getElementById('smHint'),
        seg: document.getElementById('smSeg'),
        primaryText: document.getElementById('smPrimaryText'),
        more: document.getElementById('smMore'),
        toggle: document.getElementById('smToggle'),
        toggleLabel: document.getElementById('smToggleLabel'),
        dur: document.getElementById('smDur'),
        durBtn: document.getElementById('smDurBtn'),
        durVal: document.getElementById('smDurVal'),
        durList: document.getElementById('smDurList'),
        customWrap: document.getElementById('smCustomWrap'),
        custom: document.getElementById('smCustom'),
        history: document.getElementById('smHistory'),
        undo: document.getElementById('smUndo'),
        redo: document.getElementById('smRedo'),
        acts: document.getElementById('smActs'),
        restore: document.getElementById('smRestore'),
        commit: document.getElementById('smCommit'),
        sessionField: document.getElementById('tempSessionField'),
        revField: document.getElementById('tempRevField'),
        dialog: document.getElementById('smDialog'),
        dialogTitle: document.getElementById('smDialogTitle'),
        dialogText: document.getElementById('smDialogText'),
        dialogOk: document.getElementById('smDialogOk'),
        dialogCancel: document.getElementById('smDialogCancel'),
        toasts: document.getElementById('smToasts')
    };

    var DURATIONS = [
        [60, '۱ دقیقه'], [300, '۵ دقیقه'], [600, '۱۰ دقیقه'], [900, '۱۵ دقیقه'], [1800, '۳۰ دقیقه'],
        [3600, '۱ ساعت'], [10800, '۳ ساعت'], [21600, '۶ ساعت'], [43200, '۱۲ ساعت'], [86400, '۲۴ ساعت'], ['custom', 'مدت دلخواه…']
    ];
    var RICH_PREFIX = 'richmsg.';
    var MIN_CUSTOM = 1;
    var MAX_CUSTOM = 4320;

    var status = window.FX_TEXTBOT_TEMP || { active: false };
    var ui = { mode: 'perm', duration: 600, custom: 10, busy: false, flash: null, open: false };
    var offset = 0;
    var tickTimer = null;
    var pollTimer = null;
    var refreshing = false;
    var dialogResolve = null;
    var lastFocus = null;

    function store(key, value) {
        try { if (value === undefined) return window.localStorage.getItem('fxsm_' + key); window.localStorage.setItem('fxsm_' + key, String(value)); } catch (e) { }
        return null;
    }
    function session(key, value) {
        try { if (value === undefined) return window.sessionStorage.getItem('fxsm_' + key); window.sessionStorage.setItem('fxsm_' + key, String(value)); } catch (e) { }
        return null;
    }
    function fa(value) {
        return String(value).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function clock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        return fa(h > 0 ? h + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s));
    }
    function shortClock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds >= 3600) return fa(Math.ceil(seconds / 3600)) + 'س';
        if (seconds >= 60) return fa(Math.ceil(seconds / 60)) + 'د';
        return fa(seconds) + 'ث';
    }
    function clockLabel(unix) {
        try {
            return fa(new Date(unix * 1000).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Tehran' }));
        } catch (e) {
            var d = new Date(unix * 1000);
            return fa(pad(d.getHours()) + ':' + pad(d.getMinutes()));
        }
    }
    function durationLabel(seconds) {
        for (var i = 0; i < DURATIONS.length; i++) {
            if (DURATIONS[i][0] === seconds) return DURATIONS[i][1];
        }
        if (seconds % 3600 === 0) return fa(seconds / 3600) + ' ساعت';
        return fa(Math.round(seconds / 60)) + ' دقیقه';
    }
    function nowServer() { return (Date.now() + offset) / 1000; }
    function remaining() {
        if (!status.active) return 0;
        return Math.max(0, status.expires_at - nowServer());
    }
    function isOwner() { return !!status.active && !!status.is_owner; }
    function foreign() { return !!status.active && !status.is_owner; }
    function changedCount() { return typeof window.fxTextChangedCount === 'function' ? window.fxTextChangedCount() : 0; }

    function toast(message, tone, ttl) {
        if (!els.toasts) return;
        var node = document.createElement('div');
        node.className = 'fx-toast';
        node.setAttribute('data-tone', tone || 'info');
        node.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        var paths = {
            ok: '<path d="m5 12 5 5 9-10"/>',
            error: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
            warn: '<path d="M12 4 3 20h18L12 4z"/><path d="M12 10v4M12 17h.01"/>',
            info: '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>'
        };
        node.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + (paths[tone] || paths.info) + '</svg><span></span>';
        node.lastChild.textContent = message;
        els.toasts.appendChild(node);
        while (els.toasts.children.length > 3) els.toasts.removeChild(els.toasts.firstChild);
        window.setTimeout(function () {
            if (node.parentNode) node.parentNode.removeChild(node);
        }, ttl || (tone === 'error' || tone === 'warn' ? 7000 : 4200));
    }

    function showNotice(message, tone, action) {
        els.noticeText.textContent = message;
        els.notice.setAttribute('data-tone', tone || 'info');
        if (action) {
            els.noticeAction.textContent = action.label;
            els.noticeAction.hidden = false;
            els.noticeAction.onclick = action.run;
        } else {
            els.noticeAction.hidden = true;
            els.noticeAction.onclick = null;
        }
        els.notice.hidden = false;
    }
    function hideNotice() {
        els.notice.hidden = true;
        els.noticeAction.onclick = null;
    }
    els.noticeClose.addEventListener('click', hideNotice);

    function confirmDialog(opts) {
        return new Promise(function (resolve) {
            dialogResolve = resolve;
            lastFocus = document.activeElement;
            els.dialog.setAttribute('data-tone', opts.tone || 'accent');
            els.dialogTitle.textContent = opts.title;
            els.dialogText.textContent = opts.text;
            els.dialogOk.textContent = opts.confirm || 'تأیید';
            els.dialogOk.className = 'btn ' + (opts.tone === 'danger' ? 'btn-danger' : 'btn-primary');
            els.dialog.classList.add('active');
            window.setTimeout(function () { els.dialogOk.focus(); }, 30);
        });
    }
    function closeDialog(result) {
        els.dialog.classList.remove('active');
        var resolve = dialogResolve;
        dialogResolve = null;
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        if (resolve) resolve(result);
    }
    els.dialogOk.addEventListener('click', function () { closeDialog(true); });
    els.dialogCancel.addEventListener('click', function () { closeDialog(false); });
    els.dialog.addEventListener('click', function (ev) { if (ev.target === els.dialog) closeDialog(false); });
    els.dialog.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { ev.preventDefault(); closeDialog(false); return; }
        if (ev.key === 'Tab') {
            var first = els.dialogCancel;
            var last = els.dialogOk;
            if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
            else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
        }
    });

    function buildDurationList() {
        var html = '';
        DURATIONS.forEach(function (item) {
            if (item[0] === 'custom') html += '<div class="fx-dur__sep" role="separator"></div>';
            html += '<button type="button" class="fx-dur__item" role="option" data-dur="' + item[0] + '" aria-selected="false">' + item[1] + '</button>';
        });
        els.durList.innerHTML = html;
    }
    function selectedSeconds() {
        if (ui.duration === 'custom') {
            var minutes = parseFloat(els.custom.value);
            if (!isFinite(minutes) || minutes < MIN_CUSTOM || minutes > MAX_CUSTOM) return null;
            return Math.round(minutes * 60);
        }
        return ui.duration;
    }
    function renderDuration() {
        var key = ui.duration;
        Array.prototype.forEach.call(els.durList.querySelectorAll('.fx-dur__item'), function (item) {
            item.setAttribute('aria-selected', String(item.getAttribute('data-dur')) === String(key) ? 'true' : 'false');
        });
        if (ui.duration === 'custom') {
            var minutes = parseFloat(els.custom.value);
            els.durVal.textContent = isFinite(minutes) && minutes >= 1 ? fa(Math.round(minutes)) + ' دقیقه' : 'دلخواه';
        } else {
            els.durVal.textContent = durationLabel(ui.duration);
        }
    }
    function openDuration(open) {
        els.durList.hidden = !open;
        els.dur.classList.toggle('is-open', open);
        els.durBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var selected = els.durList.querySelector('[aria-selected="true"]') || els.durList.querySelector('.fx-dur__item');
            if (selected) { selected.focus(); if (selected.scrollIntoView) selected.scrollIntoView({ block: 'nearest' }); }
        }
    }
    els.durBtn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        openDuration(els.durList.hidden);
    });
    els.durList.addEventListener('click', function (ev) {
        var item = ev.target.closest('.fx-dur__item');
        if (!item) return;
        var value = item.getAttribute('data-dur');
        ui.duration = value === 'custom' ? 'custom' : parseInt(value, 10);
        store('duration', value);
        openDuration(false);
        els.durBtn.focus();
        render();
        if (ui.duration === 'custom') els.custom.focus();
    });
    els.durList.addEventListener('keydown', function (ev) {
        var items = Array.prototype.slice.call(els.durList.querySelectorAll('.fx-dur__item'));
        var index = items.indexOf(document.activeElement);
        if (ev.key === 'ArrowDown') { ev.preventDefault(); items[Math.min(items.length - 1, index + 1)].focus(); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); items[Math.max(0, index - 1)].focus(); }
        else if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); openDuration(false); els.durBtn.focus(); }
        else if (ev.key === 'Tab') { openDuration(false); }
    });
    document.addEventListener('click', function (ev) {
        if (!els.durList.hidden && !els.dur.contains(ev.target)) openDuration(false);
    });
    els.custom.addEventListener('input', function () {
        var minutes = parseFloat(els.custom.value);
        var bad = !isFinite(minutes) || minutes < MIN_CUSTOM || minutes > MAX_CUSTOM;
        els.customWrap.classList.toggle('is-bad', bad);
        if (!bad) store('custom', Math.round(minutes));
        renderDuration();
    });

    function setMode(mode, persist) {
        ui.mode = mode === 'temp' ? 'temp' : 'perm';
        if (persist) store('mode', ui.mode);
        render();
    }
    Array.prototype.forEach.call(els.seg.querySelectorAll('[data-sm-mode]'), function (button) {
        button.addEventListener('click', function () { if (!ui.busy) setMode(button.getAttribute('data-sm-mode'), true); });
        button.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight' || ev.key === 'ArrowUp' || ev.key === 'ArrowDown') {
                ev.preventDefault();
                setMode(ui.mode === 'temp' ? 'perm' : 'temp', true);
                var next = els.seg.querySelector('[data-sm-mode="' + ui.mode + '"]');
                if (next) next.focus();
            }
        });
    });

    function setOpen(open) {
        ui.open = open;
        root.setAttribute('data-open', open ? '1' : '0');
        els.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    els.toggle.addEventListener('click', function () { setOpen(!ui.open); });

    function pillState() {
        if (ui.flash) return ui.flash;
        if (status.active) return remaining() <= Math.max(60, status.duration * 0.1) ? 'expiring' : 'active';
        if (ui.busy) return 'saving';
        if (changedCount() > 0) return 'draft';
        return 'idle';
    }
    var PILL_TEXT = {
        idle: 'بدون نشست موقت', draft: 'پیش‌نویس ذخیره‌نشده', saving: 'در حال ذخیره…', active: 'نسخهٔ موقت فعال',
        expiring: 'در آستانهٔ انقضا', expired: 'منقضی شد', restored: 'نسخهٔ دائمی بازگردانی شد', committed: 'به‌صورت دائمی ثبت شد', failed: 'ناموفق'
    };

    function render() {
        var active = !!status.active;
        var owner = isOwner();
        var other = foreign();
        var locked = other;

        Array.prototype.forEach.call(els.seg.querySelectorAll('[data-sm-mode]'), function (button) {
            var on = button.getAttribute('data-sm-mode') === ui.mode;
            button.classList.toggle('is-on', on);
            button.setAttribute('aria-checked', on ? 'true' : 'false');
            button.tabIndex = on ? 0 : -1;
            button.disabled = ui.busy || locked;
        });

        var showMore = ui.mode === 'temp' || (active && owner);
        els.more.hidden = !showMore;
        els.dur.hidden = ui.mode !== 'temp';
        els.customWrap.hidden = ui.mode !== 'temp' || ui.duration !== 'custom';
        els.history.hidden = !(active && owner);
        els.acts.hidden = !(active && owner);
        renderDuration();
        els.durBtn.disabled = ui.busy || locked;
        els.custom.disabled = ui.busy || locked;

        var canUndo = active && owner && !!status.can_undo && !ui.busy;
        var canRedo = active && owner && !!status.can_redo && !ui.busy;
        els.undo.disabled = !canUndo;
        els.redo.disabled = !canRedo;
        els.restore.disabled = ui.busy || !(active && owner);
        els.commit.disabled = ui.busy || !(active && owner);

        var label;
        if (ui.mode === 'temp') label = active && owner ? 'ذخیرهٔ موقت جدید' : 'ذخیرهٔ موقت';
        else label = active && owner ? 'ثبت دائمی' : 'ذخیرهٔ دائمی';
        els.primaryText.textContent = label;
        if (primary) {
            primary.disabled = ui.busy || locked;
            primary.title = locked ? 'یک نشست موقت متعلق به مدیر دیگری فعال است.' : '';
        }

        var hint;
        if (locked) hint = 'نشست موقت مدیر «' + status.owner + '» فعال است؛ تا پایان آن امکان ذخیره وجود ندارد.';
        else if (ui.mode === 'temp') hint = 'اعمال فوری در ربات و بازگشت خودکار به نسخهٔ دائمی پس از ' + (selectedSeconds() ? durationLabel(selectedSeconds()) : 'مدت انتخابی') + '.';
        else hint = active && owner ? 'نسخهٔ موقت فعلی به‌صورت دائمی ثبت و بازگشت خودکار لغو می‌شود.' : 'ثبت دائمی تغییرات بدون تاریخ انقضا.';
        els.hint.textContent = hint;

        var state = pillState();
        root.setAttribute('data-state', state);
        els.pill.setAttribute('data-state', state);
        els.pill.textContent = PILL_TEXT[state] || PILL_TEXT.idle;
        if (primary) root.setAttribute('aria-busy', ui.busy ? 'true' : 'false');

        els.session.hidden = !active;
        if (active) {
            els.owner.textContent = owner ? 'ایجاد شده توسط شما' : 'مدیر: ' + status.owner;
            els.expireAt.textContent = clockLabel(status.expires_at);
            els.rev.textContent = fa(status.revision) + ' از ' + fa(status.max_revision);
            els.keys.textContent = fa(status.keyCount || 0);
        }
        els.toggleLabel.textContent = ui.mode === 'temp'
            ? 'ذخیرهٔ موقت · ' + (selectedSeconds() ? durationLabel(selectedSeconds()) : 'مدت نامعتبر') + (active && owner ? ' · نشست فعال' : '')
            : (active && owner ? 'نشست موقت فعال · ابزارها' : 'تنظیمات ذخیره');
        els.toggle.setAttribute('aria-label', 'نمایش یا پنهان‌کردن ابزارهای ذخیره');

        els.sessionField.value = active && owner ? String(status.session_id) : '';
        els.revField.value = active && owner ? String(status.revision) : '';
        tick();
    }

    var RING = 2 * Math.PI * 22;
    function tick() {
        if (!status.active) {
            if (tickTimer) { window.clearInterval(tickTimer); tickTimer = null; }
            return;
        }
        if (!tickTimer) tickTimer = window.setInterval(tick, 1000);
        var left = remaining();
        els.remain.textContent = clock(left);
        els.remainShort.textContent = left >= 3600 ? shortClock(left) : clock(left);
        var fraction = status.duration > 0 ? Math.max(0, Math.min(1, left / status.duration)) : 0;
        els.ring.setAttribute('stroke-dasharray', String(RING));
        els.ring.setAttribute('stroke-dashoffset', String(RING * (1 - fraction)));
        els.bar.style.transform = 'scaleX(' + fraction + ')';
        var state = pillState();
        if (root.getAttribute('data-state') !== state && !ui.flash) {
            root.setAttribute('data-state', state);
            els.pill.setAttribute('data-state', state);
            els.pill.textContent = PILL_TEXT[state];
        }
        if (left <= 0) refresh();
    }

    function setStatus(next) {
        if (!next) return;
        status = next;
        status.keyCount = 0;
        offset = (next.server_time || Math.floor(Date.now() / 1000)) * 1000 - Date.now();
        var ids = next.item_ids || [];
        var keys = {};
        ids.forEach(function (id) {
            keys[id.indexOf(RICH_PREFIX) === 0 ? id.slice(RICH_PREFIX.length) : id] = true;
        });
        status.keyCount = Object.keys(keys).length;
        markRows(keys, mainKeys(status.active ? next.deleted_ids : []));
        render();
        schedulePoll();
        if (typeof window.fxTextRefreshState === 'function') window.fxTextRefreshState();
    }

    function markRows(keys, deleted) {
        Array.prototype.forEach.call(document.querySelectorAll('.text-row'), function (row) {
            var area = row.querySelector('textarea[data-key]');
            var key = area ? area.getAttribute('data-key') : '';
            var isDeleted = !!(status.active && key && deleted[key]);
            var on = !!(status.active && key && keys[key]);
            row.classList.toggle('has-temp', on);
            row.classList.toggle('is-temp-deleted', isDeleted);
            var holder = row.querySelector('.text-key');
            var badge = row.querySelector('.fx-temp-badge');
            if (on && holder && !badge) {
                badge = document.createElement('span');
                badge.className = 'fx-temp-badge';
                holder.appendChild(badge);
            } else if (!on && badge) {
                badge.parentNode.removeChild(badge);
                badge = null;
            }
            if (badge) badge.textContent = isDeleted ? 'حذف موقت' : 'موقت';
            var button = row.querySelector('.text-row__delete');
            if (isDeleted) {
                if (row.classList.contains('is-pending-delete')) {
                    row.classList.remove('is-pending-delete');
                    var pendingBadge = row.querySelector('.fx-del-badge');
                    if (pendingBadge) pendingBadge.parentNode.removeChild(pendingBadge);
                }
                if (button) {
                    button.classList.remove('is-active');
                    button.disabled = true;
                    button.title = 'حذف موقت است؛ با واگرد یا بازگردانی برمی‌گردد';
                }
            } else if (button && button.disabled) {
                button.disabled = false;
                button.title = 'حذف این کلید';
            }
        });
    }

    function schedulePoll() {
        if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
        if (status.active) pollTimer = window.setInterval(function () { if (!document.hidden) refresh(); }, 30000);
    }

    function request(action, fields) {
        var body = new FormData();
        body.append('_csrf', csrf);
        body.append('_action', action);
        if (status.active && status.is_owner) {
            body.append('_temp_session', String(status.session_id));
            body.append('_temp_rev', String(status.revision));
        }
        Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
        return fetch('textbot.php?ajax=1', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            return response.text().then(function (text) {
                var data = null;
                try { data = JSON.parse(text); } catch (e) { data = null; }
                if (!data) {
                    return { ok: false, http: response.status, data: { ok: false, error: 'bad_response', message: response.status === 403 ? 'نشست پنل منقضی شده یا توکن امنیتی نامعتبر است؛ صفحه را تازه کنید.' : 'پاسخ نامعتبر از سرور دریافت شد.' } };
                }
                return { ok: !!data.ok, http: response.status, data: data };
            });
        }).catch(function () {
            return { ok: false, http: 0, data: { ok: false, error: 'network', message: 'ارتباط با سرور برقرار نشد؛ هیچ تغییری تأیید نشد. دوباره تلاش کنید.' } };
        });
    }

    function flashState(name, ms) {
        ui.flash = name;
        render();
        window.setTimeout(function () {
            if (ui.flash === name) { ui.flash = null; render(); }
        }, ms || 3200);
    }

    function setBusy(busy) {
        ui.busy = busy;
        render();
    }

    function mainKeys(ids) {
        var keys = {};
        (ids || []).forEach(function (id) {
            if (id.indexOf(RICH_PREFIX) !== 0) keys[id] = true;
        });
        return keys;
    }

    function applyChanged(changed, nextStatus) {
        if (!changed) return;
        var deleted = mainKeys(nextStatus && nextStatus.active ? nextStatus.deleted_ids : []);
        Object.keys(changed).forEach(function (key) {
            var area = document.getElementById('ta-' + key);
            var companion = document.getElementById('rich-' + key);
            var value = changed[key];
            var row = area ? area.closest('.text-row') : null;
            if (value.exists === false && deleted[key]) return;
            if (value.exists === false && value.text === '') {
                if (row && row.parentNode) row.parentNode.removeChild(row);
                return;
            }
            if (area) {
                area.value = value.text;
                area.dataset.orig = value.text;
                if (value.ver) area.dataset.ver = value.ver;
            }
            if (companion) {
                companion.value = value.rich;
                companion.dataset.orig = value.rich;
            }
            if (row) row.setAttribute('data-search-text', value.text);
        });
        if (typeof window.fxTextRefreshState === 'function') window.fxTextRefreshState();
    }

    function rejectedKeys(map) {
        return map ? Object.keys(map) : [];
    }

    var REASON_FALLBACK = {
        conflict: 'این متن پس از بارگذاری صفحه تغییر کرده است.',
        unchanged: 'محتوای این متن با نسخهٔ فعلی یکسان است.',
        missing: 'این کلید دیگر وجود ندارد.',
        edge: 'فاصله یا نویسهٔ نامرئی در ابتدا یا انتهای این متن مجاز نیست.',
        rich_invalid: 'ساختار پرمیوم این متن معتبر نیست.',
        invalid: 'این متن معتبر نیست.'
    };

    function reasonOf(entry) {
        if (entry && typeof entry === 'object') return entry.message || REASON_FALLBACK[entry.code] || 'ذخیره نشد.';
        return typeof entry === 'string' && entry ? entry : 'ذخیره نشد.';
    }

    function clearRejectMarks() {
        Array.prototype.forEach.call(document.querySelectorAll('.text-row.has-reject'), function (row) {
            row.classList.remove('has-reject');
            var mark = row.querySelector('.fx-reject');
            if (mark && mark.parentNode) mark.parentNode.removeChild(mark);
        });
    }

    function markRejected(map) {
        clearRejectMarks();
        Object.keys(map || {}).forEach(function (key) {
            var area = document.getElementById('ta-' + key);
            var row = area ? area.closest('.text-row') : null;
            var holder = row ? row.querySelector('.text-key') : null;
            if (!row || !holder) return;
            row.classList.add('has-reject');
            var mark = document.createElement('span');
            mark.className = 'fx-reject';
            mark.setAttribute('role', 'note');
            mark.textContent = reasonOf(map[key]);
            holder.appendChild(mark);
        });
    }

    function rejectionSummary(map) {
        var keys = rejectedKeys(map);
        var lines = keys.slice(0, 3).map(function (key) { return '«' + key + '»: ' + reasonOf(map[key]); });
        if (keys.length > 3) lines.push('و ' + fa(keys.length - 3) + ' مورد دیگر');
        return lines.join('\n');
    }

    function handleFailure(result, fallback) {
        var data = result.data || {};
        if (data.status) setStatus(data.status);
        var message = data.message || fallback;
        var map = data.rejected || null;
        if (map && rejectedKeys(map).length) {
            markRejected(map);
            message += '\n' + rejectionSummary(map);
        } else if (data.keys && data.keys.length) {
            message += ' (' + data.keys.slice(0, 4).join('، ') + (data.keys.length > 4 ? '، …' : '') + ')';
        }
        toast(message, data.error === 'nothing_to_save' ? 'info' : 'error', 9000);
        flashState(data.error === 'nothing_to_save' ? null : 'failed', 3500);
        if (data.error === 'stale' || data.error === 'session_gone' || data.error === 'drift' || (map && rejectedKeys(map).some(function (key) { return map[key] && map[key].code === 'conflict'; }))) {
            showNotice('وضعیت برخی متن‌ها در سرور تغییر کرده است. برای دیدن آخرین مقادیر صفحه را دوباره بارگذاری کنید (تغییرات ذخیره‌نشدهٔ همین صفحه از بین می‌رود).', 'warn', { label: 'بارگذاری مجدد', run: function () { window.location.reload(); } });
        }
    }

    function saveTemporary() {
        if (ui.busy) return;
        if (typeof window.fxTextEdgeBlocking === 'function' && window.fxTextEdgeBlocking() > 0) {
            window.alert(window.TEXT_EDGE && window.TEXT_EDGE.message ? window.TEXT_EDGE.message : 'برخی متن‌ها نامعتبرند.');
            return;
        }
        var payload = window.fxBuildTextPayload ? window.fxBuildTextPayload() : null;
        if (!payload) { toast('تغییری برای ذخیرهٔ موقت وجود ندارد.', 'info'); return; }
        var seconds = selectedSeconds();
        if (!seconds) {
            els.customWrap.classList.add('is-bad');
            toast('مدت دلخواه باید بین ۱ تا ۴۳۲۰ دقیقه باشد.', 'error');
            els.custom.focus();
            return;
        }
        clearRejectMarks();
        setBusy(true);
        request('temp_save', {
            _text_payload: JSON.stringify(payload),
            _temp_duration: String(seconds),
            _temp_custom: ''
        }).then(function (result) {
            setBusy(false);
            if (!result.ok) { handleFailure(result, 'ذخیرهٔ موقت انجام نشد.'); return; }
            applyChanged(result.data.changed, result.data.status);
            setStatus(result.data.status);
            hideNotice();
            var rejected = result.data.rejected || {};
            toast('نسخهٔ موقت در ربات اعمال شد و تا ساعت ' + clockLabel(result.data.status.expires_at) + ' فعال می‌ماند.', 'ok');
            if (rejectedKeys(rejected).length) {
                markRejected(rejected);
                toast('برخی متن‌ها ذخیره نشدند:\n' + rejectionSummary(rejected), 'warn', 9000);
            }
        });
    }

    function commitPermanent() {
        if (ui.busy) return;
        if (typeof window.fxTextEdgeBlocking === 'function' && window.fxTextEdgeBlocking() > 0) {
            window.alert(window.TEXT_EDGE && window.TEXT_EDGE.message ? window.TEXT_EDGE.message : 'برخی متن‌ها نامعتبرند.');
            return;
        }
        var pending = changedCount();
        confirmDialog({
            title: 'ثبت دائمی نسخهٔ فعلی',
            text: 'نسخهٔ موقت فعال' + (pending > 0 ? ' و ' + fa(pending) + ' تغییر ذخیره‌نشدهٔ ویرایشگر' : '') + ' به‌عنوان نسخهٔ دائمی ثبت می‌شود، بازگشت خودکار لغو می‌شود و واگرد/انجام دوبارهٔ موقت غیرفعال خواهد شد.',
            confirm: 'ثبت دائمی',
            tone: 'accent'
        }).then(function (ok) {
            if (!ok) return;
            var payload = window.fxBuildTextPayload ? window.fxBuildTextPayload() : null;
            setBusy(true);
            request('temp_commit', payload ? { _text_payload: JSON.stringify(payload) } : {}).then(function (result) {
                setBusy(false);
                if (!result.ok) { handleFailure(result, 'ثبت دائمی انجام نشد.'); return; }
                applyChanged(result.data.changed, result.data.status);
                setStatus(result.data.status);
                session('dismissed', result.data.status.last_ended ? result.data.status.last_ended.ended_at : '');
                toast('نسخهٔ فعلی به‌صورت دائمی ثبت شد.', 'ok');
                flashState('committed', 3500);
            });
        });
    }

    function restorePermanent() {
        if (ui.busy) return;
        confirmDialog({
            title: 'بازگردانی نسخهٔ دائمی',
            text: 'نسخهٔ موقت فعال بلافاصله غیرفعال می‌شود و همهٔ متن‌های موقت به نسخهٔ دائمی قبلی برمی‌گردند. تغییرات موقت و تاریخچهٔ واگرد از بین می‌روند.',
            confirm: 'بازگردانی',
            tone: 'danger'
        }).then(function (ok) {
            if (!ok) return;
            setBusy(true);
            request('temp_restore', {}).then(function (result) {
                setBusy(false);
                if (!result.ok) { handleFailure(result, 'بازگردانی انجام نشد.'); return; }
                applyChanged(result.data.changed, result.data.status);
                setStatus(result.data.status);
                session('dismissed', result.data.status.last_ended ? result.data.status.last_ended.ended_at : '');
                var skipped = result.data.skipped || [];
                toast(skipped.length ? 'نسخهٔ دائمی بازگردانی شد؛ ' + fa(skipped.length) + ' متن به‌دلیل تغییر هم‌زمان دست‌نخورده ماند.' : 'نسخهٔ دائمی بازگردانی شد.', skipped.length ? 'warn' : 'ok');
                flashState('restored', 3500);
            });
        });
    }

    function moveHistory(action) {
        if (ui.busy) return;
        var proceed = changedCount() > 0
            ? confirmDialog({ title: 'تغییرات ذخیره‌نشده', text: 'ویرایشگر تغییرات ذخیره‌نشده دارد. متن‌هایی که با این عملیات تغییر کنند، مقدار ویرایشگرشان جایگزین می‌شود. ادامه می‌دهید؟', confirm: 'ادامه', tone: 'accent' })
            : Promise.resolve(true);
        proceed.then(function (ok) {
            if (!ok) return;
            setBusy(true);
            request(action, {}).then(function (result) {
                setBusy(false);
                if (!result.ok) { handleFailure(result, 'عملیات انجام نشد.'); return; }
                applyChanged(result.data.changed, result.data.status);
                setStatus(result.data.status);
                var count = (result.data.changed ? Object.keys(result.data.changed).length : 0);
                toast((action === 'temp_undo' ? 'واگرد انجام شد' : 'انجام دوباره انجام شد') + (count ? ' · ' + fa(count) + ' متن به‌روز شد' : ''), 'ok');
            });
        });
    }

    els.undo.addEventListener('click', function () { moveHistory('temp_undo'); });
    els.redo.addEventListener('click', function () { moveHistory('temp_redo'); });
    els.restore.addEventListener('click', restorePermanent);
    els.commit.addEventListener('click', commitPermanent);

    if (primary) {
        primary.addEventListener('click', function (ev) {
            if (ui.busy) { ev.preventDefault(); return; }
            if (ui.mode === 'temp') { ev.preventDefault(); saveTemporary(); return; }
            if (isOwner()) { ev.preventDefault(); commitPermanent(); }
        });
    }
    form.addEventListener('submit', function (ev) {
        if (ui.busy || foreign()) { ev.preventDefault(); return; }
        if (ui.mode === 'temp' || isOwner()) { ev.preventDefault(); return; }
        window.setTimeout(function () {
            if (ev.defaultPrevented) return;
            root.setAttribute('aria-busy', 'true');
            root.setAttribute('data-state', 'saving');
            if (primary) primary.disabled = true;
        }, 0);
    }, true);

    function endedMessage(last) {
        if (last.status === 'expired') return { text: 'مدت نسخهٔ موقت به پایان رسید و نسخهٔ دائمی به‌صورت خودکار بازگردانی شد.', tone: 'warn' };
        if (last.status === 'restored') return { text: 'نسخهٔ موقت توسط «' + (last.ended_by || last.owner) + '» غیرفعال و نسخهٔ دائمی بازگردانی شد.', tone: 'info' };
        return { text: 'نسخهٔ موقت توسط «' + (last.ended_by || last.owner) + '» به‌صورت دائمی ثبت شد.', tone: 'ok' };
    }

    function handleEnded(last) {
        var message = endedMessage(last);
        var clean = changedCount() === 0;
        showNotice(message.text + (clean ? ' متن‌ها در حال به‌روزرسانی هستند…' : ' برای دیدن مقادیر جدید، صفحه را دوباره بارگذاری کنید.'), message.tone, { label: 'بارگذاری مجدد', run: function () { window.location.reload(); } });
        flashState(last.status === 'expired' ? 'expired' : (last.status === 'committed' ? 'committed' : 'restored'), 6000);
        if (clean) window.setTimeout(function () { window.location.reload(); }, 1600);
    }

    function refresh() {
        if (refreshing) return Promise.resolve();
        refreshing = true;
        var before = !!status.active;
        return fetch('textbot.php?temp=status&_=' + Date.now(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.status) return;
                setStatus(data.status);
                if (before && !data.status.active) {
                    if (data.status.last_ended) handleEnded(data.status.last_ended);
                    else if (changedCount() === 0) window.location.reload();
                }
            })
            .catch(function () { })
            .then(function () { refreshing = false; });
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden && status.active) refresh(); });
    window.addEventListener('focus', function () { if (status.active) refresh(); });
    window.addEventListener('pageshow', function (ev) { if (ev.persisted) { ui.busy = false; refresh(); } });
    document.addEventListener('input', function (ev) {
        if (ev.target && ev.target.matches && ev.target.matches('#textForm textarea')) window.setTimeout(render, 0);
    });

    buildDurationList();
    var storedMode = store('mode');
    var storedDur = store('duration');
    var storedCustom = parseInt(store('custom'), 10);
    if (storedDur === 'custom') ui.duration = 'custom';
    else if (storedDur && DURATIONS.some(function (item) { return String(item[0]) === storedDur; })) ui.duration = parseInt(storedDur, 10);
    if (storedCustom >= MIN_CUSTOM && storedCustom <= MAX_CUSTOM) { ui.custom = storedCustom; els.custom.value = String(storedCustom); }
    ui.mode = storedMode === 'temp' ? 'temp' : 'perm';
    if (status.active && status.is_owner) ui.mode = 'temp';

    setStatus(status);

    var initialEnded = status.last_ended;
    if (!status.active && initialEnded && String(initialEnded.ended_at) !== session('dismissed')) {
        var message = endedMessage(initialEnded);
        showNotice(message.text, message.tone);
        session('dismissed', initialEnded.ended_at);
        flashState(initialEnded.status === 'expired' ? 'expired' : (initialEnded.status === 'committed' ? 'committed' : 'restored'), 6000);
    }
    if (foreign()) showNotice('یک نشست ذخیرهٔ موقت متعلق به مدیر «' + status.owner + '» فعال است؛ تا پایان آن فقط او می‌تواند تغییرات را ذخیره، واگرد یا ثبت کند.', 'warn');
})();
