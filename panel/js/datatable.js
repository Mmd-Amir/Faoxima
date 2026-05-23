(function (global) {
    'use strict';

    var L = {
        search:        'جستجو:',
        empty:         'هیچ ردیفی یافت نشد',
        showing:       'نمایش _START_ تا _END_ از _TOTAL_ ردیف',
        showingZero:   'هیچ ردیفی برای نمایش نیست',
        prev:          'قبلی',
        next:          'بعدی',
        first:         'اول',
        last:          'آخر',
        page:          'صفحه',
    };

    function persianNum(n) {
        var ar = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        return String(n).replace(/\d/g, function (d) { return ar[d]; });
    }

    function init(selector, opts) {
        opts = opts || {};
        var table = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!table) return null;

        var tbody = table.querySelector('tbody');
        var thead = table.querySelector('thead');
        if (!tbody || !thead) return null;

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        if (rows.length === 0) return null;

        var pageSize = parseInt(table.dataset.pageSize || opts.pageSize || '25', 10);
        var currentPage = 0;
        var filter = '';
        var sortCol = null, sortDir = 1;


        var wrap = document.createElement('div');
        wrap.className = 'mdt-wrap';
        table.parentNode.insertBefore(wrap, table);

        // Inject the (once) styles for the enhanced search box + filter dropdowns.
        if (!document.getElementById('mdt-enhance-style')) {
            var st = document.createElement('style');
            st.id = 'mdt-enhance-style';
            st.textContent =
                '.mdt-controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;}' +
                '.mdt-search{position:relative;display:inline-flex;align-items:center;flex:1 1 240px;max-width:360px;}' +
                '.mdt-search__icon{position:absolute;inset-inline-start:12px;display:flex;color:var(--text-dim,#8a8a9a);pointer-events:none;}' +
                '.mdt-search input{width:100%;padding-block:9px;padding-inline-start:38px;padding-inline-end:14px;border-radius:10px;background:var(--surface-3,#1a1a22);border:1px solid var(--border-mid,#33333f);color:var(--text-main,#eee);font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;}' +
                '.mdt-search input:focus{border-color:var(--accent,#8b5cf6);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent,#8b5cf6) 22%,transparent);}' +
                '.mdt-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}' +
                '.mdt-filter{padding:9px 12px;border-radius:10px;background:var(--surface-3,#1a1a22);border:1px solid var(--border-mid,#33333f);color:var(--text-main,#eee);font-size:12.5px;cursor:pointer;}' +
                '.mdt-filter:focus{outline:none;border-color:var(--accent,#8b5cf6);}';
            document.head.appendChild(st);
        }

        var ctrls = document.createElement('div');
        ctrls.className = 'mdt-controls';
        ctrls.innerHTML =
            '<label class="mdt-search">' +
                '<span class="mdt-search__icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg></span>' +
                '<input type="search" placeholder="' + L.search.replace(/:$/, '') + ' ...' + '" aria-label="' + L.search + '">' +
            '</label>' +
            '<div class="mdt-filters"></div>';
        wrap.appendChild(ctrls);
        wrap.appendChild(table);

        var foot = document.createElement('div');
        foot.className = 'mdt-foot';
        foot.innerHTML =
            '<div class="mdt-info"></div>' +
            '<div class="mdt-pager"></div>';
        wrap.appendChild(foot);

        var searchInput = ctrls.querySelector('.mdt-search input');
        var filtersWrap = ctrls.querySelector('.mdt-filters');
        var info = foot.querySelector('.mdt-info');
        var pager = foot.querySelector('.mdt-pager');


        var headers = Array.prototype.slice.call(thead.querySelectorAll('th'));
        headers.forEach(function (th, idx) {
            if (th.dataset.noSort === '1') return;
            th.classList.add('mdt-sortable');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');
            th.addEventListener('click', function () { setSort(idx); });
            th.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSort(idx); }
            });
        });

        function setSort(col) {
            if (sortCol === col) sortDir = -sortDir; else { sortCol = col; sortDir = 1; }

            headers.forEach(function (th, i) {
                th.classList.remove('mdt-sort-asc', 'mdt-sort-desc');
                if (i === sortCol) th.classList.add(sortDir > 0 ? 'mdt-sort-asc' : 'mdt-sort-desc');
            });
            render();
        }


        var searchTimer = null;
        searchInput.addEventListener('input', function () {
            if (searchTimer) clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                filter = searchInput.value.toLowerCase().trim();
                currentPage = 0;
                render();
            }, 150);
        });


        rows.forEach(function (r) {
            r.dataset.searchText = r.textContent.toLowerCase();
        });

        // ----- Optional status/value filter dropdowns (request #5/#6) -----
        // Enable by adding data-mdt-filter="<colIndex>" (comma-separated for many)
        // to the <table>. A per-column <select> of distinct values is built.
        var colFilters = {};
        (function buildColumnFilters() {
            var spec = (table.dataset.mdtFilter || '').trim();
            if (!spec) return;
            spec.split(',').forEach(function (part) {
                part = part.trim();
                if (part === '') return;
                var colIdx = parseInt(part, 10);
                if (isNaN(colIdx)) return;
                var seen = {}, values = [];
                rows.forEach(function (r) {
                    var cell = r.children[colIdx];
                    if (!cell) return;
                    var v = (cell.dataset.filterValue || cell.textContent || '').trim();
                    if (v !== '' && !seen[v]) { seen[v] = 1; values.push(v); }
                });
                if (values.length === 0) return;
                values.sort(function (a, b) { return a.localeCompare(b, 'fa'); });
                var th = headers[colIdx];
                var label = th ? (th.dataset.filterLabel || th.textContent.trim()) : '';
                var sel = document.createElement('select');
                sel.className = 'mdt-filter';
                var optAll = document.createElement('option');
                optAll.value = '';
                optAll.textContent = label ? (label + ': همه') : 'همه';
                sel.appendChild(optAll);
                values.forEach(function (v) {
                    var o = document.createElement('option');
                    o.value = v; o.textContent = v;
                    sel.appendChild(o);
                });
                colFilters[colIdx] = '';
                sel.addEventListener('change', function () {
                    colFilters[colIdx] = sel.value;
                    currentPage = 0;
                    render();
                });
                filtersWrap.appendChild(sel);
            });
        })();

        function getCellValue(row, col) {
            var cell = row.children[col];
            if (!cell) return '';
            return cell.dataset.sortValue || cell.textContent.trim();
        }
        function compareRows(a, b) {
            var av = getCellValue(a, sortCol);
            var bv = getCellValue(b, sortCol);
            var an = parseFloat(av.replace(/[,٬٫]/g, '').replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }));
            var bn = parseFloat(bv.replace(/[,٬٫]/g, '').replace(/[۰-۹]/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }));
            if (!isNaN(an) && !isNaN(bn)) return (an - bn) * sortDir;
            return av.localeCompare(bv, 'fa') * sortDir;
        }


        function render() {
            var filtered = rows.filter(function (r) {
                if (filter && r.dataset.searchText.indexOf(filter) === -1) return false;
                for (var ci in colFilters) {
                    if (!colFilters[ci]) continue;
                    var cell = r.children[ci];
                    var v = cell ? (cell.dataset.filterValue || cell.textContent || '').trim() : '';
                    if (v !== colFilters[ci]) return false;
                }
                return true;
            });

            if (sortCol !== null) filtered.sort(compareRows);

            var total = filtered.length;
            var pageCount = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage >= pageCount) currentPage = pageCount - 1;


            var frag = document.createDocumentFragment();
            if (total === 0) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = headers.length;
                td.className = 'mdt-empty';
                td.textContent = L.empty;
                tr.appendChild(td);
                frag.appendChild(tr);
            } else {
                var start = currentPage * pageSize;
                var end = Math.min(start + pageSize, total);
                for (var i = start; i < end; i++) frag.appendChild(filtered[i]);
            }
            tbody.innerHTML = '';
            tbody.appendChild(frag);


            if (total === 0) {
                info.textContent = L.showingZero;
            } else {
                var s = (currentPage * pageSize) + 1;
                var e = Math.min((currentPage + 1) * pageSize, total);
                info.textContent = L.showing.replace('_START_', persianNum(s)).replace('_END_', persianNum(e)).replace('_TOTAL_', persianNum(total));
            }


            buildPager(pager, currentPage, pageCount);
        }

        function buildPager(el, page, count) {
            el.innerHTML = '';
            if (count <= 1) return;
            function btn(label, target, disabled, active) {
                var b = document.createElement('button');
                b.className = 'mdt-page' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
                b.type = 'button';
                b.textContent = label;
                if (!disabled && !active) {
                    b.addEventListener('click', function () { currentPage = target; render(); });
                }
                el.appendChild(b);
            }
            btn(L.prev, page - 1, page === 0, false);

            var maxBtns = 7;
            var startP = Math.max(0, page - Math.floor(maxBtns / 2));
            var endP   = Math.min(count, startP + maxBtns);
            startP     = Math.max(0, endP - maxBtns);
            if (startP > 0) btn(persianNum(1), 0, false, false);
            if (startP > 1) {
                var dots = document.createElement('span');
                dots.className = 'mdt-dots';
                dots.textContent = '...';
                el.appendChild(dots);
            }
            for (var p = startP; p < endP; p++) {
                btn(persianNum(p + 1), p, false, p === page);
            }
            if (endP < count - 1) {
                var dots2 = document.createElement('span');
                dots2.className = 'mdt-dots';
                dots2.textContent = '...';
                el.appendChild(dots2);
            }
            if (endP < count) btn(persianNum(count), count - 1, false, false);
            btn(L.next, page + 1, page === count - 1, false);
        }

        render();

        return { render: render };
    }


    document.addEventListener('DOMContentLoaded', function () {
        var tables = document.querySelectorAll('table[data-dt="1"]');
        tables.forEach(function (t) { init(t); });
    });

    global.FaoximaDT = { init: init };
})(window);

