(function () {
    'use strict';

    var COLOR_KEY = 'faoxima_color';
    var THEME_KEY = 'faoxima_theme';
    var DEFAULT_COLOR = 'purple';
    var DEFAULT_THEME = 'dark';
    var ALLOWED_COLORS = ['red', 'blue', 'purple', 'yellow', 'orange', 'green'];

    var PRESET_HEX = { red:'#ef4444', blue:'#3b82f6', purple:'#a855f7', yellow:'#facc15', orange:'#f97316', green:'#22c55e' };
    var ACCENT_VARS = ['--accent', '--accent-soft', '--accent-mid', '--accent-glow', '--accent-ink', '--accent-ring'];

    function normHex(v) { var m = /^#?([0-9a-f]{6})$/i.exec(String(v == null ? '' : v).trim()); return m ? ('#' + m[1].toLowerCase()) : null; }
    function parts(hex) { var n = parseInt(hex.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    function contrastFg(hex) {
        var p = parts(hex);
        function lin(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
        var L = 0.2126 * lin(p[0]) + 0.7152 * lin(p[1]) + 0.0722 * lin(p[2]);
        return L > 0.45 ? '#14121d' : '#ffffff';
    }

    var INK_MIN = 4.5;
    var INK_SURFACE_FALLBACK = { light: [255, 255, 255], dark: [21, 22, 26] };
    function linCh(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
    function relLum(r, g, b) { return 0.2126 * linCh(r) + 0.7152 * linCh(g) + 0.0722 * linCh(b); }
    function ratioOn(r, g, b, bgL) { var L = relLum(r, g, b); var hi = L > bgL ? L : bgL, lo = L > bgL ? bgL : L; return (hi + 0.05) / (lo + 0.05); }
    function currentTheme() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
    function surfaceLum(theme) {
        var raw = '';
        try { raw = getComputedStyle(document.documentElement).getPropertyValue('--surface-1') || ''; } catch (e) { raw = ''; }
        var m = /^#?([0-9a-f]{6})$/i.exec(String(raw).trim());
        if (m) { var n = parseInt(m[1], 16); return relLum((n >> 16) & 255, (n >> 8) & 255, n & 255); }
        var f = INK_SURFACE_FALLBACK[theme];
        return relLum(f[0], f[1], f[2]);
    }
    function mixInk(r, g, b, k, theme) {
        if (theme === 'light') return [Math.round(r * (1 - k)), Math.round(g * (1 - k)), Math.round(b * (1 - k))];
        return [Math.round(r + (255 - r) * k), Math.round(g + (255 - g) * k), Math.round(b + (255 - b) * k)];
    }
    function hexOf(c) { function h(v) { var x = v.toString(16); return x.length < 2 ? '0' + x : x; } return '#' + h(c[0]) + h(c[1]) + h(c[2]); }
    function accentInk(hex, theme) {
        var p = parts(hex), r = p[0], g = p[1], b = p[2], bgL = surfaceLum(theme);
        if (ratioOn(r, g, b, bgL) >= INK_MIN) return hex;
        var lo = 0, hi = 1, i, mid, c;
        for (i = 0; i < 24; i++) {
            mid = (lo + hi) / 2;
            c = mixInk(r, g, b, mid, theme);
            if (ratioOn(c[0], c[1], c[2], bgL) >= INK_MIN) hi = mid; else lo = mid;
        }
        c = mixInk(r, g, b, hi, theme);
        var guard = 0;
        while (ratioOn(c[0], c[1], c[2], bgL) < INK_MIN && guard++ < 255) {
            hi = Math.min(1, hi + 0.004);
            c = mixInk(r, g, b, hi, theme);
        }
        return hexOf(c);
    }
    function applyCustomAccent(hex) {
        var s = document.documentElement.style;
        var theme = currentTheme();
        var p = parts(hex);
        s.setProperty('--accent-ink', accentInk(hex, theme));
        s.setProperty('--accent-ring', 'rgba(' + p[0] + ',' + p[1] + ',' + p[2] + ',' + (theme === 'light' ? '0.40' : '0.45') + ')');
    }
    function storedCustomHex() {
        var v = null;
        try { v = localStorage.getItem(COLOR_KEY); } catch (e) { v = null; }
        if (!v || PRESET_HEX[v]) return null;
        return normHex(v);
    }

    function applyColor(value) {
        var s = document.documentElement.style;
        var preset = PRESET_HEX[value] ? value : null;
        var hex = preset ? PRESET_HEX[preset] : normHex(value);
        if (!hex) { preset = DEFAULT_COLOR; hex = PRESET_HEX[DEFAULT_COLOR]; }

        if (preset) {
            for (var i = 0; i < ACCENT_VARS.length; i++) s.removeProperty(ACCENT_VARS[i]);
            document.documentElement.setAttribute('data-color', preset);
        } else {
            var p = parts(hex);
            s.setProperty('--accent', hex);
            s.setProperty('--accent-soft', 'rgba(' + p[0] + ',' + p[1] + ',' + p[2] + ',0.15)');
            s.setProperty('--accent-mid',  'rgba(' + p[0] + ',' + p[1] + ',' + p[2] + ',0.35)');
            s.setProperty('--accent-glow', 'rgba(' + p[0] + ',' + p[1] + ',' + p[2] + ',0.5)');
            applyCustomAccent(hex);
            document.documentElement.setAttribute('data-color', 'custom');
        }
        s.setProperty('--accent-fg', contrastFg(hex));

        try { localStorage.setItem(COLOR_KEY, preset ? preset : hex); } catch (e) {}

        var sw = document.querySelectorAll('.swatch, .ap-swatch');
        for (var k = 0; k < sw.length; k++) {
            var c = sw[k].getAttribute('data-color');
            var match = preset ? (c === preset) : (normHex(c) === hex);
            sw[k].classList.toggle('active', match);
        }

        try { document.dispatchEvent(new CustomEvent('faoxima:themechange', { detail: { color: preset ? preset : hex } })); } catch (e) {}
    }


    var SVG_MOON = '<svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    var SVG_SUN  = '<svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';

    function applyTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') theme = DEFAULT_THEME;
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        var customHex = storedCustomHex();
        if (customHex) applyCustomAccent(customHex);
        var icon = document.getElementById('theme-toggle-icon');
        var label = document.getElementById('theme-toggle-label');
        if (icon) {
            icon.innerHTML = (theme === 'light') ? SVG_SUN : SVG_MOON;
        }
        if (label) {
            label.textContent = theme === 'light' ? 'حالت شب' : 'حالت روز';
        }

        try {
            document.dispatchEvent(new CustomEvent('faoxima:themechange', { detail: { theme: theme } }));
        } catch (e) {}
    }


    var savedColor = DEFAULT_COLOR;
    var savedTheme = DEFAULT_THEME;
    try {
        savedColor = localStorage.getItem(COLOR_KEY) || DEFAULT_COLOR;
        var st = localStorage.getItem(THEME_KEY);
        savedTheme = (st === 'light' || st === 'dark') ? st : DEFAULT_THEME;
    } catch (e) {}
    document.documentElement.setAttribute('data-color', savedColor);
    document.documentElement.setAttribute('data-theme', savedTheme);


    window.FaoximaTheme = {
        setColor: applyColor,
        setTheme: applyTheme,
        toggleTheme: function () {
            var current = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
            applyTheme(current === 'light' ? 'dark' : 'light');
        }
    };


    function ready() {
        applyColor(savedColor);
        applyTheme(savedTheme);


        var swatches = document.querySelectorAll('.swatch');
        for (var i = 0; i < swatches.length; i++) {
            (function (sw) {
                sw.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    applyColor(sw.getAttribute('data-color'));
                });
            })(swatches[i]);
        }


        var profileWrap = document.querySelector('.profile-wrap');
        if (profileWrap) {
            var trigger = profileWrap.querySelector('.profile-trigger');
            if (trigger) {
                trigger.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    profileWrap.classList.toggle('open');
                });
            }
            document.addEventListener('click', function (ev) {
                if (!profileWrap.contains(ev.target)) profileWrap.classList.remove('open');
            });

            var menu = profileWrap.querySelector('.profile-menu');
            if (menu) {
                menu.addEventListener('click', function (ev) {
                    if (ev.target.closest('.swatch') || ev.target.closest('.no-close')) {
                        ev.stopPropagation();
                    }
                });
            }
        }


        function toggleSidebar() {
            if (window.innerWidth <= 992) {
                document.body.classList.toggle('sidebar-open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        }

        var toggleMenuBtn = document.getElementById('sidebar-toggle-btn');
        if (toggleMenuBtn) {
            toggleMenuBtn.addEventListener('click', toggleSidebar);
        }
        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function () {
                document.body.classList.remove('sidebar-open');
            });
        }


        var path = (location.pathname.split('/').pop() || 'index.php').toLowerCase();
        var links = document.querySelectorAll('.sidebar-menu a');
        for (var j = 0; j < links.length; j++) {
            var href = (links[j].getAttribute('href') || '').toLowerCase();
            if (!href) continue;
            if (path === href ||
                (path.indexOf('useredit') !== -1 && href.indexOf('users.php') !== -1) ||
                (path === 'user.php' && href === 'users.php') ||
                (path === 'productedit.php' && href === 'product.php') ||
                (path === 'logviewer.php' && href === 'miscsettings.php') ||
                (path === 'filenaming.php' && href === 'miscsettings.php')) {
                links[j].classList.add('active');
            }
        }


        var themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                window.FaoximaTheme.toggleTheme();
            });
        }


        var groups = document.querySelectorAll('.nav-group');
        for (var g = 0; g < groups.length; g++) {
            (function (grp) {
                var btn = grp.querySelector('.nav-group__btn');
                if (!btn) return;
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    grp.classList.toggle('open');
                });
                if (grp.querySelector('.sidebar-menu a.active')) {
                    grp.classList.add('open');
                }
            })(groups[g]);
        }


        var ddWraps = document.querySelectorAll('.hdr-dd-wrap');
        for (var d = 0; d < ddWraps.length; d++) {
            (function (wrap) {
                var t = wrap.querySelector('[data-dd-toggle]');
                if (t) {
                    t.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        for (var x = 0; x < ddWraps.length; x++) {
                            if (ddWraps[x] !== wrap) ddWraps[x].classList.remove('open');
                        }
                        wrap.classList.toggle('open');
                    });
                }
            })(ddWraps[d]);
        }
        document.addEventListener('click', function (ev) {
            for (var x = 0; x < ddWraps.length; x++) {
                if (!ddWraps[x].contains(ev.target)) ddWraps[x].classList.remove('open');
            }
        });


        var reduceMotion = false;
        try { reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

        var observeEls = document.querySelectorAll('.observe-in');
        if (reduceMotion || !('IntersectionObserver' in window)) {
            for (var oi = 0; oi < observeEls.length; oi++) {
                observeEls[oi].style.opacity = '1';
                observeEls[oi].classList.add('in-view');
            }
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) {
                        en.target.classList.add('in-view');
                        io.unobserve(en.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
            for (var oj = 0; oj < observeEls.length; oj++) io.observe(observeEls[oj]);
        }


        function faPersian(s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
        function animateCount(el, end, duration, suffix) {
            var start = null;
            function tick(now) {
                if (start === null) start = now;
                var p = Math.min((now - start) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = faPersian(Math.floor(end * eased).toLocaleString('en-US')) + suffix;
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }
        var counters = document.querySelectorAll('[data-count]');
        for (var ci = 0; ci < counters.length; ci++) {
            (function (el) {
                var target = parseInt(el.getAttribute('data-count'), 10) || 0;
                var suffix = el.getAttribute('data-suffix') || '';
                el.textContent = faPersian(target.toLocaleString('en-US')) + suffix;
            })(counters[ci]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
})();


window.openModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet();
    m.style.removeProperty('display');
    m.classList.add('active');
};
window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('active');
};
document.addEventListener('click', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('modal-overlay') && ev.target.id !== 'detail-sheet') {
        ev.target.classList.remove('active');
    }
});


(function () {
    var sheet = null, lastFocus = null, lockedScrollY = 0;

    function ensureSheet() {
        if (sheet) return sheet;
        sheet = document.createElement('div');
        sheet.id = 'detail-sheet';
        sheet.className = 'modal-overlay detail-sheet';
        sheet.setAttribute('role', 'dialog');
        sheet.setAttribute('aria-modal', 'true');
        sheet.innerHTML =
            '<div class="modal-box">' +
                '<div class="modal-head">' +
                    '<div class="modal-head__title" data-sheet-title></div>' +
                    '<button type="button" class="modal-close" data-sheet-close aria-label="بستن">&times;</button>' +
                '</div>' +
                '<div class="modal-body">' +
                    '<div data-sheet-fields></div>' +
                    '<div class="detail-sheet__actions" data-sheet-actions></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(sheet);
        return sheet;
    }

    function buildFields(row) {
        var cells = row.querySelectorAll('td[data-label]');
        var fieldsHtml = '';
        var actionsCell = row.querySelector('td.cell-actions');
        for (var i = 0; i < cells.length; i++) {
            var td = cells[i];
            if (td === actionsCell) continue;
            if (td.querySelector('input[type="checkbox"]')) continue;
            fieldsHtml +=
                '<div class="detail-sheet__field">' +
                    '<span class="detail-sheet__field-label">' + td.getAttribute('data-label') + '</span>' +
                    '<span class="detail-sheet__field-value">' + td.innerHTML + '</span>' +
                '</div>';
        }
        return { fieldsHtml: fieldsHtml, actionsCell: actionsCell };
    }

    function show(row) {
        var m = ensureSheet();
        m.style.display = '';
        var title = row.getAttribute('data-detail-title') || '';
        var built = buildFields(row);
        m.querySelector('[data-sheet-title]').textContent = title;
        m.querySelector('[data-sheet-fields]').innerHTML = built.fieldsHtml;
        var actionsWrap = m.querySelector('[data-sheet-actions]');
        actionsWrap.innerHTML = '';
        if (built.actionsCell) {
            actionsWrap.style.display = '';
            actionsWrap.innerHTML = built.actionsCell.innerHTML;
        } else {
            actionsWrap.style.display = 'none';
        }

        if (typeof window.faoximaInitCompactBadges === 'function') {
            window.faoximaInitCompactBadges(m);
        }

        lastFocus = document.activeElement;
        lockedScrollY = window.scrollY || window.pageYOffset || 0;
        m.classList.add('active');
        document.body.classList.add('lb-modal-open');
        document.documentElement.classList.add('lb-modal-open');
        if ((window.scrollY || window.pageYOffset || 0) !== lockedScrollY) window.scrollTo(0, lockedScrollY);
    }

    function hide() {
        if (!sheet || !sheet.classList.contains('active')) return;
        sheet.classList.remove('active');
        document.body.classList.remove('lb-modal-open');
        document.documentElement.classList.remove('lb-modal-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            try { lastFocus.focus({ preventScroll: true }); } catch (e) { lastFocus.focus(); }
        }
        lastFocus = null;
        window.scrollTo(0, lockedScrollY);
    }

    var mqSummary = window.matchMedia('(max-width: 768px), (max-width: 992px) and (pointer: coarse)');

    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-sheet-close]')) { ev.preventDefault(); hide(); return; }
        if (sheet && ev.target === sheet) { hide(); return; }
        if (!mqSummary.matches) return;
        if (ev.target.closest('a, button, input, label, [onclick]')) return;
        var row = ev.target.closest('tr[data-detail-row]');
        if (row) { show(row); }
    });

    document.addEventListener('keydown', function (ev) {
        if (!sheet || !sheet.classList.contains('active')) return;
        if (ev.key === 'Escape' || ev.key === 'Esc') { ev.preventDefault(); hide(); }
    });

    window.openDetailSheet = show;
    window.closeDetailSheet = hide;
})();


(function () {
    function buildBar(table) {
        var bar = document.createElement('div');
        bar.className = 'fx-mobile-select-all';
        bar.innerHTML =
            '<label class="fx-mobile-select-all__label">' +
                '<input type="checkbox" class="fx-mobile-select-all__checkbox">' +
                '<span data-fx-select-text>انتخاب همه</span>' +
            '</label>';
        var checkbox = bar.querySelector('.fx-mobile-select-all__checkbox');
        var text = bar.querySelector('[data-fx-select-text]');

        function boxes() {
            return Array.prototype.slice.call(table.querySelectorAll('tbody input[name="ids[]"]:not(:disabled)'));
        }

        function refresh() {
            var all = boxes();
            var checkedCount = all.filter(function (cb) { return cb.checked; }).length;
            checkbox.checked = all.length > 0 && checkedCount === all.length;
            checkbox.indeterminate = checkedCount > 0 && checkedCount < all.length;
            text.textContent = checkedCount > 0 ? ('انتخاب همه (' + checkedCount + ' از ' + all.length + ')') : 'انتخاب همه';
        }

        checkbox.addEventListener('change', function () {
            var target = checkbox.checked;
            boxes().forEach(function (cb) {
                if (cb.checked === target) return;
                cb.checked = target;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            refresh();
        });

        table.addEventListener('change', function (ev) {
            if (ev.target && ev.target.matches && ev.target.matches('input[name="ids[]"]')) refresh();
        });

        refresh();
        return bar;
    }

    function ensureBars() {
        document.querySelectorAll('table.app-table--summary').forEach(function (table) {
            if (table.dataset.fxSelectAllInit) return;
            if (!table.querySelector('tbody input[name="ids[]"]')) return;
            table.dataset.fxSelectAllInit = '1';
            var wrap = table.closest('.table-wrap') || table;
            wrap.parentNode.insertBefore(buildBar(table), wrap);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureBars);
    } else {
        ensureBars();
    }
})();

(function () {
    'use strict';
    var pickers = new Map();
    var active = null;
    var serial = 0;
    var scheduled = false;

    function normalize(value) {
        return String(value || '').toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک')
            .replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); });
    }

    function close(restoreFocus) {
        if (!active) return;
        var picker = active;
        active = null;
        picker.popup.hidden = true;
        picker.trigger.setAttribute('aria-expanded', 'false');
        picker.host.classList.remove('is-open');
        if (restoreFocus && picker.trigger.isConnected) picker.trigger.focus({ preventScroll: true });
    }

    function position(picker) {
        var rect = picker.trigger.getBoundingClientRect();
        if (!rect.width || !rect.height || !picker.trigger.getClientRects().length) { close(false); return; }
        var gap = 8;
        var margin = 12;
        var width = Math.min(Math.max(rect.width, 220), window.innerWidth - margin * 2);
        var below = window.innerHeight - rect.bottom - gap - margin;
        var above = rect.top - gap - margin;
        var upwards = below < 280 && above > below;
        var available = Math.max(80, upwards ? above : below);
        picker.popup.style.width = width + 'px';
        picker.popup.style.maxHeight = Math.min(360, available) + 'px';
        picker.popup.style.left = Math.max(margin, Math.min(rect.right - width, window.innerWidth - width - margin)) + 'px';
        picker.popup.style.top = Math.max(margin, upwards ? rect.top - gap - picker.popup.offsetHeight : Math.min(rect.bottom + gap, window.innerHeight - picker.popup.offsetHeight - margin)) + 'px';
    }

    function create(select) {
        var id = 'fx-select-' + (++serial);
        var host = document.createElement('div');
        host.className = 'fx-select';
        ['width', 'minWidth', 'maxWidth', 'flex'].forEach(function (property) {
            if (select.style[property]) host.style[property] = select.style[property];
        });
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'fx-select__trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', id);
        var value = document.createElement('span');
        value.className = 'fx-select__value';
        var arrow = document.createElement('span');
        arrow.className = 'fx-select__arrow';
        arrow.setAttribute('aria-hidden', 'true');
        trigger.appendChild(value);
        trigger.appendChild(arrow);
        var popup = document.createElement('div');
        popup.className = 'fx-select-popup';
        popup.dir = 'rtl';
        popup.hidden = true;
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'fx-select-popup__search';
        search.placeholder = 'جستجو در گزینه‌ها…';
        search.setAttribute('aria-label', 'جستجو در گزینه‌ها');
        search.autocomplete = 'off';
        var list = document.createElement('div');
        list.className = 'fx-select-popup__list';
        list.id = id;
        list.setAttribute('role', 'listbox');
        var empty = document.createElement('div');
        empty.className = 'fx-select-popup__empty';
        empty.textContent = 'گزینه‌ای پیدا نشد';
        empty.setAttribute('role', 'status');
        empty.hidden = true;
        popup.appendChild(search);
        popup.appendChild(list);
        popup.appendChild(empty);
        select.parentNode.insertBefore(host, select);
        host.appendChild(select);
        host.appendChild(trigger);
        select.classList.add('fx-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        document.body.appendChild(popup);
        var picker = { select: select, host: host, trigger: trigger, popup: popup, search: search, list: list, signature: '' };
        pickers.set(select, picker);

        function labelText() {
            var labelledBy = select.getAttribute('aria-labelledby');
            if (labelledBy) return labelledBy.split(/\s+/).map(function (labelId) {
                var label = document.getElementById(labelId);
                return label ? label.textContent.trim() : '';
            }).join(' ').trim();
            var label = select.labels && select.labels[0];
            var group = host.closest('.form-group, .kb-field, .setting-row, .fx-extra-filter__field');
            if (!label && group) label = group.querySelector('label, .form-label');
            return select.getAttribute('aria-label') || (label ? label.textContent.trim() : 'انتخاب گزینه');
        }

        function options() {
            return Array.prototype.filter.call(select.options, function (option) {
                return !option.hidden && !(option.parentElement.tagName === 'OPTGROUP' && option.parentElement.hidden);
            });
        }

        function render() {
            var terms = normalize(search.value).trim().split(/\s+/).filter(Boolean);
            list.textContent = '';
            var groupLabel = null;
            options().forEach(function (option) {
                if (!terms.every(function (term) { return normalize(option.textContent).indexOf(term) !== -1; })) return;
                var group = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
                if (group && group !== groupLabel) {
                    var heading = document.createElement('div');
                    heading.className = 'fx-select-popup__group';
                    heading.setAttribute('role', 'presentation');
                    heading.textContent = group.label;
                    list.appendChild(heading);
                }
                groupLabel = group;
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'fx-select-popup__option';
                item.tabIndex = -1;
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                item.disabled = option.disabled || !!(group && group.disabled);
                var title = document.createElement('span');
                title.textContent = option.textContent;
                item.appendChild(title);
                item.addEventListener('click', function () {
                    if (item.disabled || select.disabled || !option.isConnected) return;
                    var changed = select.selectedIndex !== option.index;
                    select.selectedIndex = option.index;
                    close(true);
                    picker.refresh();
                    if (changed) {
                        select.dispatchEvent(new Event('input', { bubbles: true }));
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    picker.refresh();
                });
                list.appendChild(item);
            });
            empty.hidden = !!list.querySelector('[role="option"]');
        }

        picker.refresh = function () {
            var selected = select.options[select.selectedIndex];
            var text = selected ? selected.textContent.trim() : 'انتخاب کنید';
            if (value.textContent !== text) value.textContent = text;
            var label = labelText();
            var name = label + '، ' + text;
            if (trigger.getAttribute('aria-label') !== name) trigger.setAttribute('aria-label', name);
            if (list.getAttribute('aria-label') !== label) list.setAttribute('aria-label', label);
            trigger.disabled = select.matches(':disabled');
            host.hidden = select.hidden || select.style.display === 'none';
            trigger.setAttribute('aria-required', select.required ? 'true' : 'false');
            trigger.setAttribute('aria-invalid', select.validity.valid ? 'false' : 'true');
            search.hidden = options().length < 9;
            var signature = Array.prototype.map.call(select.options, function (option) {
                return [option.textContent, option.value, option.selected, option.disabled, option.hidden, option.parentElement.label, option.parentElement.disabled, option.parentElement.hidden];
            });
            signature = JSON.stringify(signature);
            if (active === picker) {
                if (trigger.disabled || host.hidden || !trigger.getClientRects().length) close(false);
                else {
                    if (signature !== picker.signature) render();
                    position(picker);
                }
            }
            picker.signature = signature;
        };

        function open() {
            picker.refresh();
            if (trigger.disabled || host.hidden) return;
            close(false);
            active = picker;
            search.value = '';
            render();
            popup.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            host.classList.add('is-open');
            position(picker);
            var selected = list.querySelector('[aria-selected="true"]:not(:disabled)') || list.querySelector('button:not(:disabled)');
            if (!search.hidden) search.focus({ preventScroll: true });
            else if (selected) { selected.focus({ preventScroll: true }); selected.scrollIntoView({ block: 'nearest' }); }
            else trigger.focus({ preventScroll: true });
        }

        function keydown(event) {
            if (event.key === 'Escape' && active === picker) {
                event.preventDefault();
                event.stopPropagation();
                close(true);
                return;
            }
            if (event.key === 'Tab' && active === picker) { close(true); return; }
            if (active !== picker) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); open(); }
                return;
            }
            var items = Array.prototype.slice.call(list.querySelectorAll('button:not(:disabled)'));
            var index = items.indexOf(document.activeElement);
            if (event.key === 'Enter' && event.target === search && items.length) {
                event.preventDefault();
                items[0].click();
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || ((event.key === 'Home' || event.key === 'End') && event.target !== search)) {
                event.preventDefault();
                if (!items.length) return;
                var next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : index + (event.key === 'ArrowUp' ? -1 : 1);
                next = Math.max(0, Math.min(items.length - 1, next));
                items[next].focus({ preventScroll: true });
                items[next].scrollIntoView({ block: 'nearest' });
            }
        }

        trigger.addEventListener('click', function () { if (active === picker) close(false); else open(); });
        trigger.addEventListener('keydown', keydown);
        popup.addEventListener('keydown', keydown);
        search.addEventListener('input', function () { render(); position(picker); });
        select.addEventListener('change', picker.refresh);
        select.addEventListener('focus', function () { trigger.focus(); });
        select.addEventListener('invalid', function (event) { event.preventDefault(); if (!active) open(); });
        picker.refresh();
    }

    function refresh() {
        scheduled = false;
        pickers.forEach(function (picker, select) {
            if (!select.isConnected) {
                if (active === picker) close(false);
                picker.popup.remove();
                picker.host.remove();
                pickers.delete(select);
            } else picker.refresh();
        });
        document.querySelectorAll('select').forEach(function (select) {
            if (pickers.has(select) || select.hidden || select.multiple || select.size > 1 || select.matches('#fnPanel, #fnService, [data-native-select]')) return;
            if (select.closest('.fn-picker, .kb-combo')) return;
            create(select);
        });
    }

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(refresh);
    }

    function init() {
        refresh();
        new MutationObserver(function (records) {
            if (records.some(function (record) {
                var target = record.target.nodeType === 1 ? record.target : record.target.parentElement;
                return target && (!target.closest('.fx-select, .fx-select-popup') || target.closest('select'));
            })) schedule();
        }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class', 'style', 'hidden', 'disabled', 'selected', 'label', 'required'] });
        document.addEventListener('click', function (event) {
            if (active && !active.host.contains(event.target) && !active.popup.contains(event.target)) close(false);
            schedule();
        });
        document.addEventListener('focusin', function (event) {
            if (active && !active.host.contains(event.target) && !active.popup.contains(event.target)) close(false);
            schedule();
        });
        document.addEventListener('reset', function () { setTimeout(refresh, 0); });
        document.addEventListener('change', schedule);
        window.addEventListener('resize', function () { if (active) position(active); });
        document.addEventListener('scroll', function (event) {
            if (active && !active.popup.contains(event.target)) close(false);
        }, true);
        if (window.visualViewport) window.visualViewport.addEventListener('resize', function () { if (active) position(active); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

