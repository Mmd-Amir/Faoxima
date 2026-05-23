(function (global) {
    'use strict';

    function persianNum(n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }
    function fmtShort(v) {
        var s = Math.abs(v);
        if (s >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
        if (s >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
        if (s >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
        return String(v);
    }
    function getCssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function render(selector, options) {
        var host = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!host) return;

        var labels = options.labels || [];
        var data   = options.data   || [];
        var n      = data.length;
        if (n === 0) {
            host.innerHTML = '<div style="display:flex;height:100%;align-items:center;justify-content:center;color:var(--text-muted);font-size:14px;">داده‌ای برای نمایش نیست</div>';
            return;
        }

        var accent     = getCssVar('--accent',      '#3b82f6');
        var textMuted  = getCssVar('--chart-axis',  getCssVar('--text-muted', '#94a3b8'));
        var textMain   = getCssVar('--text-main',   '#f1f3f8');
        var gridColor  = getCssVar('--chart-grid',  'rgba(255,255,255,0.06)');
        var valueFmt   = options.valueFormatter || function (v) { return persianNum(v.toLocaleString('en-US')); };
        var labelFmt   = options.labelFormatter || function (l) { return l; };

        var W = host.clientWidth  || 800;
        var H = host.clientHeight || 380;
        var padL = 56, padR = 20, padT = 20, padB = 40;

        var maxV = Math.max.apply(null, data);
        var minV = 0;
        if (maxV === minV) maxV = minV + 1;


        var step = Math.pow(10, Math.floor(Math.log10(maxV - minV)));
        var rounded = Math.ceil(maxV / step) * step;
        if (rounded - maxV < 0.1 * step) rounded += step;
        maxV = rounded;

        var plotW = W - padL - padR;
        var plotH = H - padT - padB;
        var xStep = n > 1 ? plotW / (n - 1) : 0;

        function x(i) { return padL + i * xStep; }
        function y(v) { return padT + plotH - ((v - minV) / (maxV - minV)) * plotH; }


        var linePath = '';
        var areaPath = '';
        for (var i = 0; i < n; i++) {
            var px = x(i), py = y(data[i]);
            if (i === 0) {
                linePath += 'M' + px.toFixed(2) + ',' + py.toFixed(2);
                areaPath = 'M' + px.toFixed(2) + ',' + (padT + plotH).toFixed(2) + ' L' + px.toFixed(2) + ',' + py.toFixed(2);
            } else {

                var prevX = x(i - 1), prevY = y(data[i - 1]);
                var midX = (prevX + px) / 2;
                linePath += ' C' + midX.toFixed(2) + ',' + prevY.toFixed(2) + ' ' + midX.toFixed(2) + ',' + py.toFixed(2) + ' ' + px.toFixed(2) + ',' + py.toFixed(2);
                areaPath += ' C' + midX.toFixed(2) + ',' + prevY.toFixed(2) + ' ' + midX.toFixed(2) + ',' + py.toFixed(2) + ' ' + px.toFixed(2) + ',' + py.toFixed(2);
            }
        }
        areaPath += ' L' + x(n - 1).toFixed(2) + ',' + (padT + plotH).toFixed(2) + ' Z';


        var gridLines = [];
        var yLabels = [];
        for (var g = 0; g <= 4; g++) {
            var gy = padT + plotH - (g / 4) * plotH;
            var gv = minV + (g / 4) * (maxV - minV);
            gridLines.push('<line x1="' + padL + '" y1="' + gy.toFixed(1) + '" x2="' + (W - padR) + '" y2="' + gy.toFixed(1) + '" stroke="' + gridColor + '" stroke-width="1" stroke-dasharray="2,4"/>');
            yLabels.push('<text x="' + (padL - 8) + '" y="' + (gy + 4).toFixed(1) + '" fill="' + textMuted + '" font-size="11" text-anchor="end" font-family="Arad, sans-serif">' + fmtShort(Math.round(gv)) + '</text>');
        }


        var xLabels = [];
        var labelEvery = Math.max(1, Math.ceil(n / 8));
        for (var k = 0; k < n; k++) {
            if (k % labelEvery !== 0 && k !== n - 1) continue;
            xLabels.push('<text x="' + x(k).toFixed(1) + '" y="' + (H - padB + 22) + '" fill="' + textMuted + '" font-size="11" text-anchor="middle" font-family="Arad, sans-serif">' + labelFmt(labels[k] || '') + '</text>');
        }


        var hitRects = [];
        var halfW = xStep / 2;
        for (var hi = 0; hi < n; hi++) {
            var rx = (x(hi) - halfW).toFixed(1);
            var rw = xStep.toFixed(1);
            hitRects.push('<rect class="mc-hit" data-idx="' + hi + '" x="' + rx + '" y="' + padT + '" width="' + rw + '" height="' + plotH + '" fill="transparent"/>');
        }


        var dotBorder = getCssVar('--surface-1', '#ffffff');

        var dotBorderSolid = getCssVar('--bg-grad-top', '#0d0d14');
        var dots = [];
        for (var di = 0; di < n; di++) {
            dots.push('<circle cx="' + x(di).toFixed(2) + '" cy="' + y(data[di]).toFixed(2) + '" r="3.5" fill="' + accent + '" stroke="' + dotBorderSolid + '" stroke-width="2"/>');
        }


        var uid = 'mcg' + Math.floor(Math.random() * 1e6);
        var svg = ''
          + '<svg viewBox="0 0 ' + W + ' ' + H + '" width="100%" height="100%" preserveAspectRatio="none" style="overflow: visible;">'
          + '  <defs>'
          + '    <linearGradient id="' + uid + '" x1="0" y1="0" x2="0" y2="1">'
          + '      <stop offset="0%"  stop-color="' + accent + '" stop-opacity="0.4"/>'
          + '      <stop offset="100%" stop-color="' + accent + '" stop-opacity="0"/>'
          + '    </linearGradient>'
          + '  </defs>'
          +    gridLines.join('')
          +    yLabels.join('')
          +    xLabels.join('')
          + '  <path d="' + areaPath + '" fill="url(#' + uid + ')"/>'
          + '  <path d="' + linePath + '" fill="none" stroke="' + accent + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>'
          +    dots.join('')
          +    hitRects.join('')
          + '</svg>'
          + '<div class="mc-tooltip" style="display:none;"></div>';

        host.innerHTML = svg;
        host.style.position = 'relative';


        var tt = host.querySelector('.mc-tooltip');
        var hits = host.querySelectorAll('.mc-hit');
        hits.forEach(function (h) {
            h.addEventListener('mouseenter', function () {
                var idx = parseInt(h.dataset.idx, 10);
                tt.innerHTML = '<b>' + (labels[idx] || '') + '</b><br>' + valueFmt(data[idx]);
                tt.style.display = 'block';
                var hostRect = host.getBoundingClientRect();
                var px = x(idx);
                var py = y(data[idx]);

                tt.style.left = (px / W * 100) + '%';
                tt.style.top  = ((py - 12) / H * 100) + '%';
                tt.style.transform = 'translate(-50%, -100%)';
            });
            h.addEventListener('mouseleave', function () { tt.style.display = 'none'; });
        });


        var resizeTimer = null;
        if (!host.__mcResizeBound) {
            host.__mcResizeBound = true;
            window.addEventListener('resize', function () {
                if (resizeTimer) clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () { render(host, options); }, 150);
            });
        }
    }

    global.FaoximaChart = { render: render };
})(window);

