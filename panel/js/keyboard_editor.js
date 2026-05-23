(function () {
    'use strict';


    var rows = [];
    var saveTimer = null;
    var dragSrc = null;
    var editing = null;

    var textbotMap = {};
    var primaryKeys = [];

    var rowsEl, modalEl, modalSelectEl, modalCustomWrapEl, modalCustomTextEl,
        modalColorEls, toastEl, formEl, modalTitleText;


    var CUSTOM_OPT = '__custom__';


    document.addEventListener('DOMContentLoaded', function () {
        rowsEl            = document.getElementById('kb-rows');
        modalEl           = document.getElementById('kb-modal');
        modalSelectEl     = document.getElementById('kb-edit-select');
        modalCustomWrapEl = document.getElementById('kb-custom-wrap');
        modalCustomTextEl = document.getElementById('kb-edit-text');
        toastEl           = document.getElementById('kb-toast');
        formEl            = document.getElementById('kb-edit-form');
        modalTitleText    = document.getElementById('kb-modal-title-text');
        modalColorEls     = document.querySelectorAll('input[name="kb-color"]');


        try {
            var initial = JSON.parse(document.getElementById('kb-initial').textContent || '{}');
            rows = Array.isArray(initial.keyboard) ? initial.keyboard : [];
            rows = rows.map(function (row) {
                if (!Array.isArray(row)) return [];
                return row.map(function (btn) {
                    if (typeof btn === 'string') return { text: btn };
                    if (btn && typeof btn === 'object') {
                        var out = { text: String(btn.text || '') };
                        if (btn.style && ['primary', 'success', 'danger'].indexOf(btn.style) !== -1) {
                            out.style = btn.style;
                        }
                        return out;
                    }
                    return { text: '' };
                });
            });
        } catch (e) { rows = []; }

        try {
            textbotMap = JSON.parse(document.getElementById('kb-textbot').textContent || '{}');
            if (typeof textbotMap !== 'object' || textbotMap === null) textbotMap = {};
        } catch (e) { textbotMap = {}; }

        try {
            primaryKeys = JSON.parse(document.getElementById('kb-primary-keys').textContent || '[]');
            if (!Array.isArray(primaryKeys)) primaryKeys = [];
        } catch (e) { primaryKeys = []; }

        populateSelect();
        render();


        document.getElementById('kb-add-row').addEventListener('click', function () {
            rows.push([]);
            render();
            scheduleSave();
        });


        document.getElementById('kb-cancel').addEventListener('click', closeModal);
        document.getElementById('kb-save').addEventListener('click', function (ev) {
            ev.preventDefault();
            commitEdit();
        });
        modalEl.addEventListener('click', function (ev) {
            if (ev.target === modalEl) closeModal();
        });
        formEl.addEventListener('submit', function (ev) {
            ev.preventDefault();
            commitEdit();
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && modalEl.classList.contains('open')) closeModal();
        });
        modalSelectEl.addEventListener('change', function () {

            modalCustomWrapEl.style.display = (modalSelectEl.value === CUSTOM_OPT) ? 'block' : 'none';
            if (modalSelectEl.value === CUSTOM_OPT) {
                setTimeout(function () { modalCustomTextEl.focus(); }, 50);
            }
        });
    });


    function populateSelect() {
        modalSelectEl.innerHTML = '';


        if (primaryKeys.length > 0) {
            var grpA = document.createElement('optgroup');
            grpA.label = 'دکمه‌های اصلی ربات';
            primaryKeys.forEach(function (k) {
                if (textbotMap[k]) grpA.appendChild(makeOption(k, textbotMap[k]));
            });
            if (grpA.children.length) modalSelectEl.appendChild(grpA);
        }
    }
    function makeOption(key, label) {
        var o = document.createElement('option');
        o.value = key;
        o.textContent = label + '   —   ' + key;
        return o;
    }


    function render() {
        if (rows.length === 0) {
            rowsEl.innerHTML = ''
                + '<div class="kb-empty">'
                + iconSvg('keyboard', 'svg-icon svg-2xl')
                + '<div>هنوز هیچ ردیفی اضافه نشده.</div>'
                + '<div style="font-size:12px;margin-top:4px;">روی «افزودن ردیف جدید» کلیک کنید تا شروع کنید.</div>'
                + '</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < rows.length; i++) {
            html += renderRow(i, rows[i]);
        }
        rowsEl.innerHTML = html;
        wireRowEvents();
    }

    function renderRow(rowIdx, row) {
        var btnsHtml = '';
        for (var j = 0; j < row.length; j++) {
            btnsHtml += renderBtn(rowIdx, j, row[j]);
        }
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
        var styleAttr = btn.style ? ' data-style="' + btn.style + '"' : '';
        var label = resolveLabel(btn.text);
        return ''
          + '<button class="kb-btn" data-action="edit-btn" data-row="' + rowIdx + '" data-btn="' + btnIdx + '" type="button" draggable="true"' + styleAttr + '>'
          +   '<span class="kb-btn__label">' + escapeHtml(label) + '</span>'
          +   '<span class="kb-btn__remove" data-action="delete-btn" data-row="' + rowIdx + '" data-btn="' + btnIdx + '" role="button" tabindex="0" title="حذف">'
          +     iconSvg('xmark', 'svg-icon')
          +   '</span>'
          + '</button>';
    }

    function resolveLabel(text) {
        if (!text) return '(خالی)';
        if (Object.prototype.hasOwnProperty.call(textbotMap, text)) {
            return textbotMap[text];
        }
        return text;
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
                rows[rowIdx].splice(btnIdx, 1);
                render();
                scheduleSave();
            } else if (action === 'delete-row') {
                if (rows[rowIdx].length === 0 || confirm('این ردیف و دکمه‌هایش حذف شود؟')) {
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


        var rowEls = rowsEl.querySelectorAll('.kb-row');
        rowEls.forEach(function (el) {
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
                if (dragSrc.type === 'row' && dragSrc.rowIdx !== targetIdx) {
                    var moved = rows.splice(dragSrc.rowIdx, 1)[0];
                    rows.splice(targetIdx, 0, moved);
                    render();
                    scheduleSave();
                } else if (dragSrc.type === 'btn') {
                    var btn = rows[dragSrc.rowIdx].splice(dragSrc.btnIdx, 1)[0];
                    rows[targetIdx].push(btn);
                    render();
                    scheduleSave();
                }
            });
        });


        var btnEls = rowsEl.querySelectorAll('.kb-btn');
        btnEls.forEach(function (b) {
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
                var dr = parseInt(b.dataset.row, 10);
                var dbi = parseInt(b.dataset.btn, 10);
                if (dragSrc.rowIdx === dr && dragSrc.btnIdx === dbi) return;
                var moved = rows[dragSrc.rowIdx].splice(dragSrc.btnIdx, 1)[0];
                var targetIdx = dbi;
                if (dragSrc.rowIdx === dr && dragSrc.btnIdx < dbi) targetIdx -= 1;
                rows[dr].splice(targetIdx, 0, moved);
                render();
                scheduleSave();
            });
        });
    }


    function openModal(rowIdx, btnIdx) {
        editing = { rowIdx: rowIdx, btnIdx: btnIdx };
        modalTitleText.textContent = btnIdx !== null ? 'ویرایش دکمه' : 'افزودن دکمه';

        var current = btnIdx !== null ? rows[rowIdx][btnIdx] : { text: '', style: undefined };


        var key = current.text || '';
        if (key && Object.prototype.hasOwnProperty.call(textbotMap, key)) {
            modalSelectEl.value = key;
            modalCustomWrapEl.style.display = 'none';
            modalCustomTextEl.value = '';
        } else {

            if (primaryKeys.length > 0 && textbotMap[primaryKeys[0]]) {
                modalSelectEl.value = primaryKeys[0];
            }
            modalCustomWrapEl.style.display = 'none';
            modalCustomTextEl.value = '';
        }

        var curStyle = current.style || 'default';
        modalColorEls.forEach(function (r) { r.checked = (r.value === curStyle); });
        modalEl.classList.add('open');
        setTimeout(function () {
            if (modalSelectEl.value === CUSTOM_OPT) {
                modalCustomTextEl.focus();
                modalCustomTextEl.select();
            } else {
                modalSelectEl.focus();
            }
        }, 100);
    }

    function closeModal() {
        modalEl.classList.remove('open');
        editing = null;
    }

    function commitEdit() {
        if (!editing) return;
        var selVal = modalSelectEl.value;
        var text = selVal;

        var style = 'default';
        modalColorEls.forEach(function (r) { if (r.checked) style = r.value; });

        var btn = { text: text };
        if (style !== 'default') btn.style = style;

        if (editing.btnIdx !== null) {
            rows[editing.rowIdx][editing.btnIdx] = btn;
        } else {
            rows[editing.rowIdx].push(btn);
        }
        closeModal();
        render();
        scheduleSave();
    }


    function scheduleSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            saveTimer = null;
            doSave();
        }, 500);
    }
    function doSave() {
        showToast('در حال ذخیره...', '');
        fetch('keyboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(rows),
            credentials: 'same-origin'
        })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); })
        .then(function () { showToast('ذخیره شد', 'success'); })
        .catch(function () { showToast('خطا در ذخیره', 'error'); });
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
        'keyboard':     '<rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="6"  y1="8" x2="6.01" y2="8"/><line x1="10" y1="8" x2="10.01" y2="8"/><line x1="14" y1="8" x2="14.01" y2="8"/><line x1="18" y1="8" x2="18.01" y2="8"/><line x1="7"  y1="16" x2="17" y2="16"/>',
        'sparkles':     '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3L12 3z"/>',
        'circle-check': '<circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/>',
        'circle-exclamation':'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    };
    function iconSvg(name, cls) {
        var path = SVG_PATHS[name] || '<circle cx="12" cy="12" r="3"/>';
        return '<svg class="' + (cls || 'svg-icon') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
    }
})();

