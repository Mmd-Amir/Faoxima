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

        var explicitSize = table.dataset.pageSize || opts.pageSize;
        var mqMobile = window.matchMedia('(max-width: 768px), (max-width: 992px) and (pointer: coarse)');
        function computePageSize() {
            if (explicitSize) return parseInt(explicitSize, 10);
            return mqMobile.matches ? 3 : 5;
        }
        var pageSize = computePageSize();
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
                '.mdt-search input{width:100%;padding-block:9px;padding-inline-start:38px;padding-inline-end:14px;border-radius:var(--radius-md);background:var(--surface-3,#1a1a22);border:1px solid var(--border-mid,#33333f);color:var(--text-main,#eee);font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;}' +
                '.mdt-search input:focus{border-color:var(--accent,#8b5cf6);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent,#8b5cf6) 22%,transparent);}' +
                '.mdt-filters{display:flex;gap:16px;flex-wrap:wrap;align-items:center;flex-basis:100%;margin-top:4px;}' +
                '.mdt-filters--collapsed{display:none;}' +
                '.mdt-filter-toggle{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:var(--radius-md);background:var(--surface-2,#1a1a22);border:1px solid var(--border-soft,#33333f);color:var(--text-main,#eee);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}' +
                '.mdt-filter-toggle:hover,.mdt-filter-toggle.active{border-color:var(--accent,#8b5cf6);color:var(--accent,#8b5cf6);}' +
                '.mdt-pillgroup{display:inline-flex;gap:7px;flex-wrap:wrap;align-items:center;}' +
                '.mdt-pillgroup__label{font-size:12px;color:var(--text-dim,#8a8a9a);margin-inline-end:2px;}' +
                '.mdt-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:9999px;background:var(--surface-2,#1a1a22);border:1px solid var(--border-soft,#33333f);color:var(--text-muted,#aaa);font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;}' +
                '.mdt-pill:hover{border-color:var(--accent,#8b5cf6);color:var(--text-main,#eee);}' +
                '.mdt-pill.active{background:var(--accent,#8b5cf6);border-color:var(--accent,#8b5cf6);color:#fff;}' +
                '.mdt-pill__count{font-size:11px;padding:1px 7px;border-radius:9999px;background:color-mix(in srgb,var(--text-main,#888) 14%,transparent);}' +
                '.mdt-pill.active .mdt-pill__count{background:rgba(255,255,255,0.22);color:#fff;}';
            document.head.appendChild(st);
        }

        var ctrls = document.createElement('div');
        ctrls.className = 'mdt-controls';
        ctrls.innerHTML =
            '<button type="button" class="mdt-filter-toggle" hidden>' +
                '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>' +
                'فیلتر' +
            '</button>' +
            '<div class="mdt-filters mdt-filters--collapsed"></div>';
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
        var filterToggle = ctrls.querySelector('.mdt-filter-toggle');
        if (filterToggle) {
            filterToggle.addEventListener('click', function () {
                filtersWrap.classList.toggle('mdt-filters--collapsed');
                filterToggle.classList.toggle('active');
            });
        }
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
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (searchTimer) clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    filter = searchInput.value.toLowerCase().trim();
                    currentPage = 0;
                    render();
                }, 150);
            });
        }


        rows.forEach(function (r) {
            r.dataset.searchText = r.textContent.toLowerCase();
        });

        // ----- Optional status/value filter dropdowns (request #5/#6) -----
        // Enable by adding data-mdt-filter="<colIndex>" (comma-separated for many)
        // to the <table>. A per-column <select> of distinct values is built.
        var colFilters = {};
        function mdtToFa(n) { return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); }
        (function buildColumnFilters() {
            var spec = (table.dataset.mdtFilter || '').trim();
            if (!spec) return;
            spec.split(',').forEach(function (part) {
                part = part.trim();
                if (part === '') return;
                var colIdx = parseInt(part, 10);
                if (isNaN(colIdx)) return;
                var seen = {}, values = [], counts = {};
                rows.forEach(function (r) {
                    var cell = r.children[colIdx];
                    if (!cell) return;
                    var v = (cell.dataset.filterValue || cell.textContent || '').trim();
                    if (v === '') return;
                    if (!seen[v]) { seen[v] = 1; values.push(v); counts[v] = 0; }
                    counts[v]++;
                });
                if (values.length === 0) return;
                values.sort(function (a, b) { return a.localeCompare(b, 'fa'); });
                var th = headers[colIdx];
                var label = th ? (th.dataset.filterLabel || th.textContent.trim()) : '';
                var group = document.createElement('div');
                group.className = 'mdt-pillgroup';
                if (label) {
                    var lab = document.createElement('span');
                    lab.className = 'mdt-pillgroup__label';
                    lab.textContent = label;
                    group.appendChild(lab);
                }
                colFilters[colIdx] = '';
                function mkPill(val, text, count) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'mdt-pill' + (val === '' ? ' active' : '');
                    b.innerHTML = text + ' <span class="mdt-pill__count">' + mdtToFa(count) + '</span>';
                    b.addEventListener('click', function () {
                        colFilters[colIdx] = val;
                        Array.prototype.forEach.call(group.querySelectorAll('.mdt-pill'), function (pl) { pl.classList.remove('active'); });
                        b.classList.add('active');
                        currentPage = 0;
                        render();
                    });
                    return b;
                }
                group.appendChild(mkPill('', 'همه', rows.length));
                values.forEach(function (v) { group.appendChild(mkPill(v, v, counts[v])); });
                filtersWrap.appendChild(group);
            });
        })();

        if (filterToggle) {
            if (filtersWrap.children.length > 0) { filterToggle.hidden = false; }
            else { ctrls.style.display = 'none'; }
        }

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

        function applyResponsivePageSize() {
            var ns = computePageSize();
            if (ns !== pageSize) { pageSize = ns; currentPage = 0; render(); }
        }
        if (mqMobile.addEventListener) mqMobile.addEventListener('change', applyResponsivePageSize);
        else if (mqMobile.addListener) mqMobile.addListener(applyResponsivePageSize);

        render();

        return { render: render };
    }


    function initList(containerSelector, opts) {
        opts = opts || {};
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return null;

        var itemSelector = opts.itemSelector || '.config-row, .ms-row';
        var getItems = function () {
            return Array.prototype.slice.call(container.querySelectorAll(itemSelector));
        };
        var items = getItems();
        if (items.length === 0) return null;

        var explicitSize = container.dataset.pageSize || opts.pageSize;
        var mqMobile = window.matchMedia('(max-width: 768px), (max-width: 992px) and (pointer: coarse)');
        function computePageSize() {
            if (explicitSize) return parseInt(explicitSize, 10);
            return mqMobile.matches ? 3 : 5;
        }

        var pageSize = computePageSize();
        var currentPage = 0;

        var pagerHolder = opts.pagerHolder
            ? (typeof opts.pagerHolder === 'string' ? document.querySelector(opts.pagerHolder) : opts.pagerHolder)
            : null;

        if (!pagerHolder) {
            pagerHolder = container.parentNode.querySelector('.cfg-pagination-js');
            if (!pagerHolder) {
                pagerHolder = document.createElement('div');
                pagerHolder.className = 'cfg-pagination cfg-pagination-js';
                container.parentNode.insertBefore(pagerHolder, container.nextSibling);
            }
        }

        function render() {
            items = getItems();
            var total = items.length;
            var pageCount = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage >= pageCount) currentPage = pageCount - 1;
            if (currentPage < 0) currentPage = 0;

            items.forEach(function (item, idx) {
                var itemPage = Math.floor(idx / pageSize);
                item.style.display = (itemPage === currentPage) ? '' : 'none';
            });

            if (total <= pageSize) {
                pagerHolder.style.display = 'none';
                return;
            }

            pagerHolder.style.display = 'flex';
            pagerHolder.innerHTML = '';

            // Previous Button
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'btn btn-sm btn-outline' + (currentPage === 0 ? ' is-disabled' : '');
            prevBtn.textContent = '‹ قبلی';
            if (currentPage > 0) {
                prevBtn.addEventListener('click', function () {
                    currentPage--;
                    render();
                });
            }
            pagerHolder.appendChild(prevBtn);

            // Pagination info label
            var info = document.createElement('span');
            info.className = 'cfg-pagination__info';
            var startNum = (currentPage * pageSize) + 1;
            var endNum = Math.min((currentPage + 1) * pageSize, total);
            var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
            info.innerHTML = 'صفحه <strong>' + fa(currentPage + 1) + '</strong> از <strong>' + fa(pageCount) + '</strong> — ' + fa(total) + ' مورد';
            pagerHolder.appendChild(info);

            // Next Button
            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'btn btn-sm btn-outline' + (currentPage >= pageCount - 1 ? ' is-disabled' : '');
            nextBtn.textContent = 'بعدی ›';
            if (currentPage < pageCount - 1) {
                nextBtn.addEventListener('click', function () {
                    currentPage++;
                    render();
                });
            }
            pagerHolder.appendChild(nextBtn);

            if (typeof opts.onPageChange === 'function') {
                opts.onPageChange(currentPage, pageCount);
            }
            try {
                container.dispatchEvent(new CustomEvent('faoxima:pagechange', { bubbles: true, detail: { page: currentPage, pageCount: pageCount } }));
            } catch (e) {}
        }

        function applyResponsivePageSize() {
            var ns = computePageSize();
            if (ns !== pageSize) {
                pageSize = ns;
                currentPage = 0;
                render();
            }
        }

        if (mqMobile.addEventListener) mqMobile.addEventListener('change', applyResponsivePageSize);
        else if (mqMobile.addListener) mqMobile.addListener(applyResponsivePageSize);

        render();

        return {
            render: render,
            setPage: function (p) { currentPage = p; render(); }
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tables = document.querySelectorAll('table[data-dt="1"]');
        tables.forEach(function (t) { init(t); });
    });

    global.FaoximaDT = { init: init };
    global.FaoximaListDT = { init: initList };
})(window);

