(function () {
    window.__FAOXIMA_BOOT_STARTED__ = true;

    function fail(stage, err) {
        var msg = (err && err.message) || String(err || 'unknown');
        var stack = (err && err.stack) || '';
        if (window.__FAOXIMA__) {
            window.__FAOXIMA__.postLog('error', '[boot:' + stage + '] ' + msg, 'boot.js', stack);
            window.__FAOXIMA__.renderError('بارگذاری اپ ناموفق بود', msg, stack);
        } else {

            try {
                document.getElementById('view').innerHTML =
                    '<div class="empty"><span class="glyph">!</span><h3>بارگذاری اپ ناموفق بود</h3><p class="muted">' +
                    String(msg).replace(/[<>&]/g, '') +
                    '</p></div>';
            } catch (e) {  }
        }
    }


    var supportsModules = ('noModule' in HTMLScriptElement.prototype);
    if (!supportsModules) {
        fail('no-module-support',
            new Error('این مرورگر/WebView از ES modules پشتیبانی نمی‌کند. لطفاً تلگرام را به روز کنید.'));
        return;
    }

    var cfg = window.__APP_CONFIG__ || {};
    var assetPrefix = cfg.assetPrefix || '/';
    var version     = cfg.version || '0';
    var build       = (window.__FAOXIMA__ && window.__FAOXIMA__.version) || version;


    var stamp = '';
    try {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].getAttribute('src') || '';
            var m = src.match(/[?&]v=([^&]+)/);
            if (m && src.indexOf('boot.js') !== -1) { stamp = m[1]; break; }
        }
    } catch (e) {  }
    if (!stamp) stamp = String(version) + '.' + Date.now();

    var appUrl = assetPrefix + 'assets/js/app.js?v=' + encodeURIComponent(stamp);


    try {
        var importer = new Function('u', 'return import(u);');
        importer(appUrl).catch(function (err) { fail('import-app', err); });
    } catch (err) {
        fail('importer-syntax', err);
    }
})();

