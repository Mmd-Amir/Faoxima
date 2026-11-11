(function () {
    'use strict';

    var STYLES = ['none', 'primary', 'success', 'danger'];
    var rows = [];
    var catalog = {};
    var saveTimer = null;
    var dragSrc = null;
    var editing = null;

    var rowsEl, modalEl, modalSelectEl, selectWrapEl, condNoteEl, modalColorEls, toastEl, formEl,
        modalTitleText, previewEl, hiddenEl;

    document.addEventListener('DOMContentLoaded', function () {
        rowsEl         = document.getElementById('kb-rows');
        modalEl        = document.getElementById('kb-modal');
        modalSelectEl  = document.getElementById('kb-edit-select');
        selectWrapEl   = document.getElementById('kb-select-wrap');
        condNoteEl     = document.getElementById('kb-cond-note');
        toastEl        = document.getElementById('kb-toast');
        formEl         = document.getElementById('kb-edit-form');
        modalTitleText = document.getElementById('kb-modal-title-text');
        modalColorEls  = document.querySelectorAll('input[name="kb-color"]');
        previewEl      = document.getElementById('acc-preview');
        hiddenEl       = document.getElementById('acc-hidden');

        try { catalog = JSON.parse(document.getElementById('acc-buttons').textContent || '{}') || {}; } catch (e) { catalog = {}; }

        var seen = {};
        try {
            var initial = JSON.parse(document.getElementById('acc-initial').textContent || '{}');
            rows = (Array.isArray(initial.rows) ? initial.rows : []).map(function (row) {
                var out = [];
                (Array.isArray(row) ? row : []).forEach(function (id) {
                    if (typeof id === 'string' && catalog[id] && !seen[id]) {
                        seen[id] = true;
                        out.push({ id: id, style: catalog[id].style });
                    }
                });
                return out;
            });
        } catch (e) { rows = []; }
        if (!seen.backuser) rows.push([{ id: 'backuser', style: catalog.backuser ? catalog.backuser.style : 'default' }]);

        render();

        document.getElementById('kb-add-row').addEventListener('click', function () {
            rows.push([]);
            render();
            scheduleSave();
        });
        document.getElementById('acc-reset').addEventListener('click', resetDefaults);
        document.getElementById('kb-cancel').addEventListener('click', closeModal);
        document.getElementById('kb-save').addEventListener('click', function (ev) { ev.preventDefault(); commitEdit(); });
        modalEl.addEventListener('click', function (ev) { if (ev.target === modalEl) closeModal(); });
        formEl.addEventListener('submit', function (ev) { ev.preventDefault(); commitEdit(); });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && modalEl.classList.contains('open')) closeModal();
        });
        modalSelectEl.addEventListener('change', function () { showCondNote(modalSelectEl.value); });
    });

    function placedIds() {
        var map = {};
        rows.forEach(function (r) { r.forEach(function (b) { map[b.id] = true; }); });
        return map;
    }

    function hiddenIds() {
        var placed = placedIds();
        return Object.keys(catalog).filter(function (id) { return !placed[id]; });
    }

    function effectiveStyle(btn) {
        if (btn.style && btn.style !== 'default') return btn.style;
        var c = catalog[btn.id];
        return c && c.guess ? c.guess : 'default';
    }

    function render() {
        if (rows.length === 0) {
            rowsEl.innerHTML = '<div class="kb-empty"><div>هنوز هیچ ردیفی اضافه نشده.</div></div>';
        } else {
            var html = '';
            for (var i = 0; i < rows.length; i++) html += renderRow(i, rows[i]);
            rowsEl.innerHTML = html;
            wireRowEvents();
        }
        renderPreview();
        renderHidden();
    }

    function renderRow(rowIdx, row) {
        var btnsHtml = '';
        for (var j = 0; j < row.length; j++) btnsHtml += renderBtn(rowIdx, j, row[j]);
        btnsHtml += ''
            + '<button class="kb-add-btn" data-action="add-btn" data-row="' + rowIdx + '" type="button">'
            +   iconSvg('plus', 'svg-icon svg-sm')
            +   '<span>افزودن دکمه</span>'
            + '</button>';
        return ''
          + '<div class="kb-row" data-row="' + rowIdx + '" draggable="true">'
          +   '<div class="kb-row__handle" data-action="row-handle" title="کشیدن برای جابجایی ردیف">'
          +     iconSvg('grip-vertical', 'svg-icon svg-md')
          +   '</div>'
          +   '<div class="kb-row__btns">' + btnsHtml + '</div>'
          +   '<div class="kb-row__actions">'
          +     '<button class="kb-icon-btn danger" data-action="delete-row" data-row="' + rowIdx + '" type="button" title="حذف ردیف" aria-label="حذف ردیف">'
          +       iconSvg('trash', 'svg-icon svg-sm')
          +     '</button>'
          +   '</div>'
          + '</div>';
    }

    function renderBtn(rowIdx, btnIdx, btn) {
        var c = catalog[btn.id] || { label: btn.id, conditional: false, required: false, note: '' };
        var st = effectiveStyle(btn);
        var styleAttr = st !== 'default' ? ' data-style="' + st + '"' : '';
        var extra = '';
        if (c.conditional) extra += '<span class="kb-btn__cond" title="' + escapeHtml(c.note) + '">شرطی</span>';
        var tail = c.required
            ? '<span class="kb-btn__lock" title="این دکمه همیشه باید در منو باشد">' + iconSvg('lock', 'svg-icon svg-sm') + '</span>'
            : '<span class="kb-btn__remove" data-action="delete-btn" data-row="' + rowIdx + '" data-btn="' + btnIdx + '" role="button" tabindex="0" title="پنهان کردن">' + iconSvg('xmark', 'svg-icon') + '</span>';
        return ''
          + '<button class="kb-btn" data-action="edit-btn" data-row="' + rowIdx + '" data-btn="' + btnIdx + '" type="button" draggable="true"' + styleAttr + '>'
          +   '<span class="kb-btn__label">' + escapeHtml(c.label) + '</span>'
          +   extra + tail
          + '</button>';
    }

    function renderPreview() {
        var html = '';
        rows.forEach(function (row) {
            if (!row.length) return;
            html += '<div class="acc-pv__row">';
            row.forEach(function (b) {
                var c = catalog[b.id] || { label: b.id, conditional: false };
                html += '<div class="acc-pv__btn' + (c.conditional ? ' is-cond' : '') + '" data-style="' + effectiveStyle(b) + '">' + escapeHtml(c.label) + '</div>';
            });
            html += '</div>';
        });
        previewEl.innerHTML = html;
    }

    function renderHidden() {
        var ids = hiddenIds();
        if (!ids.length) {
            hiddenEl.innerHTML = '<span style="opacity:.7;font-size:13px;">همه دکمه‌ها در چیدمان قرار دارند.</span>';
            return;
        }
        hiddenEl.innerHTML = ids.map(function (id) {
            return '<button type="button" class="btn btn-outline btn-sm" data-restore="' + escapeHtml(id) + '">'
                + iconSvg('plus', 'svg-icon svg-sm') + '<span>' + escapeHtml(catalog[id].label) + '</span></button>';
        }).join('');
        hiddenEl.querySelectorAll('[data-restore]').forEach(function (el) {
            el.addEventListener('click', function () {
                var id = el.getAttribute('data-restore');
                if (!catalog[id] || placedIds()[id]) return;
                if (!rows.length) rows.push([]);
                var target = rows.length - 1;
                var backIdx = -1;
                rows.forEach(function (r, i) { r.forEach(function (b) { if (b.id === 'backuser') backIdx = i; }); });
                if (backIdx === rows.length - 1 && rows[backIdx].length === 1) {
                    rows.splice(backIdx, 0, [{ id: id, style: catalog[id].style }]);
                } else {
                    rows[target].push({ id: id, style: catalog[id].style });
                }
                render();
                scheduleSave();
            });
        });
    }

    function wireRowEvents() {
        rowsEl.onclick = function (ev) {
            var t = ev.target.closest('[data-action]');
            if (!t) return;
            var action = t.dataset.action;
            var rowIdx = t.dataset.row != null ? parseInt(t.dataset.row, 10) : null;
            var btnIdx = t.dataset.btn != null ? parseInt(t.dataset.btn, 10) : null;

            if (action === 'delete-btn') {
                ev.stopPropagation();
                if (rows[rowIdx][btnIdx].id === 'backuser') return;
                rows[rowIdx].splice(btnIdx, 1);
                render();
                scheduleSave();
            } else if (action === 'delete-row') {
                var hasBack = rows[rowIdx].some(function (b) { return b.id === 'backuser'; });
                if (hasBack) {
                    showToast('ردیف حاوی دکمه بازگشت قابل حذف نیست', 'error');
                    return;
                }
                if (rows[rowIdx].length === 0 || confirm('این ردیف حذف شود؟ دکمه‌های آن پنهان می‌شوند.')) {
                    rows.splice(rowIdx, 1);
                    render();
                    scheduleSave();
                }
            } else if (action === 'add-btn') {
                openModal(rowIdx, null);
            } else if (action === 'edit-btn') {
                openModal(rowIdx, btnIdx);
            }
        };

        rowsEl.querySelectorAll('.kb-row').forEach(function (el) {
            el.addEventListener('dragstart', function (ev) {
                if (ev.target.closest('.kb-btn')) return;
                dragSrc = { type: 'row', rowIdx: parseInt(el.dataset.row, 10) };
                el.classList.add('dragging');
                try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', el.dataset.row); } catch (e) {}
            });
            el.addEventListener('dragend', function () {
                el.classList.remove('dragging');
                rowsEl.querySelectorAll('.drag-over').forEach(function (e) { e.classList.remove('drag-over'); });
                dragSrc = null;
            });
            el.addEventListener('dragover', function (ev) {
                if (!dragSrc) return;
                ev.preventDefault();
                if (dragSrc.type === 'row') el.classList.add('drag-over');
            });
            el.addEventListener('dragleave', function () { el.classList.remove('drag-over'); });
            el.addEventListener('drop', function (ev) {
                ev.preventDefault();
                el.classList.remove('drag-over');
                if (!dragSrc) return;
                var targetIdx = parseInt(el.dataset.row, 10);
                if (dragSrc.type === 'row') moveRow(dragSrc.rowIdx, targetIdx);
                else if (dragSrc.type === 'btn') moveBtnToRowEnd(dragSrc.rowIdx, dragSrc.btnIdx, targetIdx);
            });
        });

        rowsEl.querySelectorAll('.kb-btn').forEach(function (b) {
            b.addEventListener('dragstart', function (ev) {
                ev.stopPropagation();
                dragSrc = { type: 'btn', rowIdx: parseInt(b.dataset.row, 10), btnIdx: parseInt(b.dataset.btn, 10) };
                b.classList.add('dragging');
                try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', 'btn'); } catch (e) {}
            });
            b.addEventListener('dragend', function () { b.classList.remove('dragging'); dragSrc = null; });
            b.addEventListener('dragover', function (ev) {
                if (!dragSrc || dragSrc.type !== 'btn') return;
                ev.preventDefault(); ev.stopPropagation();
            });
            b.addEventListener('drop', function (ev) {
                if (!dragSrc || dragSrc.type !== 'btn') return;
                ev.preventDefault(); ev.stopPropagation();
                moveBtnBefore(dragSrc.rowIdx, dragSrc.btnIdx, parseInt(b.dataset.row, 10), parseInt(b.dataset.btn, 10));
            });
        });

        bindPointerDrag();
    }

    function moveRow(from, to) {
        if (from === to) return;
        var moved = rows.splice(from, 1)[0];
        rows.splice(to, 0, moved);
        render();
        scheduleSave();
    }

    function moveBtnToRowEnd(fromRow, fromBtn, toRow) {
        if (fromRow === toRow) return;
        var moved = rows[fromRow].splice(fromBtn, 1)[0];
        rows[toRow].push(moved);
        render();
        scheduleSave();
    }

    function moveBtnBefore(fromRow, fromBtn, toRow, toBtn) {
        if (fromRow === toRow && fromBtn === toBtn) return;
        var moved = rows[fromRow].splice(fromBtn, 1)[0];
        var idx = toBtn;
        if (fromRow === toRow && fromBtn < toBtn) idx -= 1;
        rows[toRow].splice(idx, 0, moved);
        render();
        scheduleSave();
    }

    function bindPointerDrag() {
        rowsEl.querySelectorAll('.kb-btn').forEach(function (b) {
            b.addEventListener('pointerdown', function (ev) { startPointerDrag(ev, b, 'btn'); });
        });
        rowsEl.querySelectorAll('.kb-row__handle').forEach(function (h) {
            h.addEventListener('pointerdown', function (ev) { startPointerDrag(ev, h.closest('.kb-row'), 'row'); });
        });
    }

    function startPointerDrag(ev, el, type) {
        if (ev.pointerType === 'mouse') return;
        if (ev.target.closest('.kb-btn__remove')) return;
        if (!el) return;
        var startX = ev.clientX, startY = ev.clientY, pointerId = ev.pointerId;
        var active = false, ghost = null, lastTarget = null;
        var src = (type === 'row')
            ? { type: 'row', rowIdx: parseInt(el.dataset.row, 10) }
            : { type: 'btn', rowIdx: parseInt(el.dataset.row, 10), btnIdx: parseInt(el.dataset.btn, 10) };
        function cleanup() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            document.removeEventListener('pointercancel', onUp);
            el.classList.remove('dragging');
            if (ghost) { ghost.remove(); ghost = null; }
            if (lastTarget) { lastTarget.classList.remove('drag-over'); lastTarget = null; }
        }
        function begin() {
            if (active) return;
            active = true;
            el.classList.add('dragging');
            if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
            ghost = el.cloneNode(true);
            ghost.classList.add('kb-drag-ghost');
            ghost.style.cssText += ';position:fixed;z-index:5000;pointer-events:none;opacity:.92;margin:0;width:' + el.offsetWidth + 'px;box-shadow:0 12px 30px rgba(0,0,0,.4);transform:rotate(1.5deg);';
            document.body.appendChild(ghost);
            moveGhost(startX, startY);
        }
        function moveGhost(x, y) {
            if (!ghost) return;
            ghost.style.left = (x - ghost.offsetWidth / 2) + 'px';
            ghost.style.top = (y - 20) + 'px';
        }
        function highlight(x, y) {
            if (ghost) ghost.style.visibility = 'hidden';
            var under = document.elementFromPoint(x, y);
            if (ghost) ghost.style.visibility = '';
            if (lastTarget) { lastTarget.classList.remove('drag-over'); lastTarget = null; }
            if (!under) return;
            var t = (src.type === 'btn') ? (under.closest('.kb-btn') || under.closest('.kb-row')) : under.closest('.kb-row');
            if (t) { t.classList.add('drag-over'); lastTarget = t; }
        }
        function onMove(e) {
            if (e.pointerId !== pointerId) return;
            if (!active) {
                if (Math.abs(e.clientX - startX) + Math.abs(e.clientY - startY) <= 8) return;
                begin();
            }
            e.preventDefault();
            moveGhost(e.clientX, e.clientY);
            highlight(e.clientX, e.clientY);
        }
        function onUp(e) {
            if (e.pointerId !== pointerId) return;
            var target = lastTarget, wasActive = active;
            cleanup();
            if (wasActive) {
                var swallow = function (ce) { ce.stopPropagation(); ce.preventDefault(); document.removeEventListener('click', swallow, true); };
                document.addEventListener('click', swallow, true);
                setTimeout(function () { document.removeEventListener('click', swallow, true); }, 350);
            }
            if (!wasActive || !target) return;
            if (src.type === 'row') {
                var ti = parseInt(target.dataset.row, 10);
                if (!isNaN(ti)) moveRow(src.rowIdx, ti);
            } else if (target.classList.contains('kb-btn')) {
                moveBtnBefore(src.rowIdx, src.btnIdx, parseInt(target.dataset.row, 10), parseInt(target.dataset.btn, 10));
            } else if (target.dataset && target.dataset.row !== undefined) {
                moveBtnToRowEnd(src.rowIdx, src.btnIdx, parseInt(target.dataset.row, 10));
            }
        }
        document.addEventListener('pointermove', onMove, { passive: false });
        document.addEventListener('pointerup', onUp);
        document.addEventListener('pointercancel', onUp);
    }

    function showCondNote(id) {
        var c = catalog[id];
        if (c && c.conditional && c.note) {
            condNoteEl.style.display = 'block';
            condNoteEl.textContent = c.note;
        } else {
            condNoteEl.style.display = 'none';
            condNoteEl.textContent = '';
        }
    }

    function openModal(rowIdx, btnIdx) {
        editing = { rowIdx: rowIdx, btnIdx: btnIdx };
        var current = null;
        if (btnIdx !== null) {
            current = rows[rowIdx][btnIdx];
            modalTitleText.textContent = 'ویرایش رنگ دکمه';
            selectWrapEl.style.display = 'none';
            showCondNote(current.id);
        } else {
            var ids = hiddenIds();
            if (!ids.length) {
                editing = null;
                showToast('همه دکمه‌ها در چیدمان قرار دارند', 'error');
                return;
            }
            modalTitleText.textContent = 'افزودن دکمه';
            selectWrapEl.style.display = 'block';
            modalSelectEl.innerHTML = '';
            ids.forEach(function (id) {
                var o = document.createElement('option');
                o.value = id;
                o.textContent = catalog[id].label + (catalog[id].conditional ? '   (شرطی)' : '');
                modalSelectEl.appendChild(o);
            });
            current = { id: ids[0], style: catalog[ids[0]].style };
            showCondNote(ids[0]);
        }
        var cur = STYLES.indexOf(current.style) !== -1 ? current.style : 'none';
        modalColorEls.forEach(function (r) { r.checked = (r.value === cur); });
        modalEl.classList.add('open');
    }

    function closeModal() {
        modalEl.classList.remove('open');
        editing = null;
    }

    function commitEdit() {
        if (!editing) return;
        var style = 'none';
        modalColorEls.forEach(function (r) { if (r.checked) style = r.value; });
        if (STYLES.indexOf(style) === -1) style = 'none';
        if (editing.btnIdx !== null) {
            rows[editing.rowIdx][editing.btnIdx].style = style;
        } else {
            var id = modalSelectEl.value;
            if (!catalog[id] || placedIds()[id]) { closeModal(); return; }
            rows[editing.rowIdx].push({ id: id, style: style });
        }
        closeModal();
        render();
        scheduleSave();
    }

    function post(body) {
        return fetch('account_layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function (j) { if (!j || !j.ok) throw new Error('save'); });
    }

    function scheduleSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () { saveTimer = null; doSave(); }, 500);
    }

    function doSave() {
        showToast('در حال ذخیره...', '');
        post({ rows: rows })
            .then(function () { showToast('ذخیره شد', 'success'); })
            .catch(function () { showToast('خطا در ذخیره', 'error'); });
    }

    function resetDefaults() {
        if (!confirm('چیدمان و نمایش دکمه‌ها به حالت پیش‌فرض بازمی‌گردد (رنگ‌ها حفظ می‌شوند). ادامه می‌دهید؟')) return;
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        post({ action: 'reset' })
            .then(function () { window.location.reload(); })
            .catch(function () { showToast('خطا در بازگردانی', 'error'); });
    }

    var toastTimer = null;
    function showToast(msg, kind) {
        toastEl.className = 'kb-toast' + (kind ? ' ' + kind : '');
        var iconName = kind === 'success' ? 'circle-check' : kind === 'error' ? 'circle-exclamation' : 'sparkles';
        toastEl.innerHTML = iconSvg(iconName, 'svg-icon svg-sm') + '<span>' + escapeHtml(msg) + '</span>';
        toastEl.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 1800);
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    var SVG_PATHS = {
        'plus':         '<line x1="12" y1="5"  x2="12" y2="19"/><line x1="5"  y1="12" x2="19" y2="12"/>',
        'xmark':        '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6"  y1="6" x2="18" y2="18"/>',
        'trash':        '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>',
        'grip-vertical':'<circle cx="9" cy="6"  r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="6"  r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/>',
        'lock':         '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'sparkles':     '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3L12 3z"/>',
        'circle-check': '<circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/>',
        'circle-exclamation':'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
    };
    function iconSvg(name, cls) {
        var path = SVG_PATHS[name] || '<circle cx="12" cy="12" r="3"/>';
        return '<svg class="' + (cls || 'svg-icon') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
    }
})();
