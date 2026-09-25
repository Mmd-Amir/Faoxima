window.__FAOXIMA_APP_STARTED__ = true;

import * as Telegram from './telegram.js?v=0.0.52';
import { verify, submitPhone } from './api.js?v=0.0.52';
import { start as startRouter } from './router.js?v=0.0.59';
import { getToken, clearToken } from './state.js?v=0.0.52';
import { loadSavedTheme } from './pages/settings.js?v=0.0.52';
import { loadBrandFromServer } from './brand.js?v=0.0.52';
import { icon } from './icons.js?v=0.0.52';
import { initNotifications } from './notifications.js?v=0.0.52';

window.__FAOXIMA_MODULES_OK__ = true;

document.addEventListener('contextmenu', (event) => {
    const target = event.target instanceof Element ? event.target.closest('#app') : null;
    if (target) event.preventDefault();
}, true);

document.addEventListener('dragstart', (event) => {
    const target = event.target instanceof Element ? event.target.closest('#app img,#app video,#app audio,#app a') : null;
    if (target) event.preventDefault();
}, true);

try { loadSavedTheme(); } catch (_) {  }

function postLog(level, msg, where, stack, extra) {
    try {
        const cfg = window.__APP_CONFIG__ || {};
        const apiUrl = cfg.apiUrl || '/api';
        const base = /^https?:\/\//i.test(apiUrl) ? apiUrl : `${location.origin}${apiUrl}`;
        fetch(`${base}/clientlog.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ level, msg, where, stack, extra }),
            keepalive: true,
        }).catch(() => {});
    } catch (_) {  }
}

window.__FAOXIMA__ = Object.assign({ postLog }, window.__FAOXIMA__ || {});
const M = window.__FAOXIMA__;

let __fxContentOverrides = [];
let __fxApplyingContentOverrides = false;

function applyFxContentOverrides() {
    if (!__fxContentOverrides.length) return;
    __fxApplyingContentOverrides = true;
    __fxContentOverrides.forEach(({ selector, html }) => {
        try {
            const target = document.querySelector(selector);
            if (target && target.innerHTML !== html) target.innerHTML = html;
        } catch (_) {  }
    });
    __fxApplyingContentOverrides = false;
}

let __fxContentObserverStarted = false;
async function loadContentOverrides() {
    try {
        const cfg = window.__APP_CONFIG__ || {};
        const ver = (cfg.cacheBust || cfg.version || Date.now()).toString();
        const url = new URL('./edit-content-overrides.js', import.meta.url);
        url.searchParams.set('v', ver);
        const mod = await import(url.href);
        __fxContentOverrides = Array.isArray(mod.FX_CONTENT_OVERRIDES) ? mod.FX_CONTENT_OVERRIDES : [];
    } catch (_) {
        __fxContentOverrides = [];
    }
    if (!__fxContentOverrides.length) return;

    applyFxContentOverrides();

    if (!__fxContentObserverStarted && window.MutationObserver) {
        __fxContentObserverStarted = true;
        const view = document.getElementById('view');
        const observeTarget = view || document.body;
        const mo = new MutationObserver(() => {
            if (__fxApplyingContentOverrides) return;
            applyFxContentOverrides();
        });
        mo.observe(observeTarget, { childList: true, subtree: true });
    }
}

function safeDiagnostics() {
    try {
        if (Telegram && typeof Telegram.diagnostics === 'function') {
            return Telegram.diagnostics();
        }
    } catch (_) {  }
    if (M && typeof M.diag === 'function') {
        try { return M.diag(); } catch (_) {}
    }
    try {
        const w = (window.Telegram && window.Telegram.WebApp) || null;
        return {
            hasTelegram: !!window.Telegram,
            hasWebApp:   !!w,
            platform:    w ? w.platform : null,
            initDataLen: w && w.initData ? w.initData.length : 0,
            href:        location.href,
            note:        'fallback diagnostics',
        };
    } catch (e) {
        return { error: String((e && e.message) || e) };
    }
}

function logErr(msg, where, stack, extra) {
    try { console.error('[' + where + ']', msg, stack); } catch (_) {}
    if (M && M.postLog) {
        try { M.postLog('error', String(msg), String(where || ''), String(stack || ''), extra || null); } catch (_) {}
    }
}

window.addEventListener('error', (e) => {
    logErr((e.error && e.error.message) || e.message || 'Script error',
           (e.filename || '?') + ':' + (e.lineno || '?'),
           (e.error && e.error.stack) || '');
});
window.addEventListener('unhandledrejection', (e) => {
    const r = e.reason;
    logErr((r && r.message) || String(r || 'Unhandled rejection'),
           'unhandledrejection',
           (r && r.stack) || '');
});

function showInitialSkeleton() {
    const view = document.getElementById('view');
    if (!view) return;
    view.innerHTML = `
        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/loading</span>
            </header>
            <div class="card-body">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
                <p class="muted center mono mt-md" style="font-size:11px">در حال آماده‌سازی…</p>
            </div>
        </article>
    `;
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}

function showAccessError(err) {
    try {
        const view = document.getElementById('view');
        if (!view) return;

        const diag = safeDiagnostics();
        const code = err && err.code;
        const status = err && err.status;
        const data = err && err.data;

        let title = 'دسترسی برقرار نشد';
        let body  = (err && err.message) ? err.message : 'لطفا از داخل تلگرام باز کنید.';
        let hint  = '';

        if (code === 'NETWORK') {
            title = 'خطای شبکه';
            body  = 'اتصال به سرور برقرار نشد. لطفا اینترنت خود را بررسی کنید.';
        } else if (code === 'NO_SDK') {
            title = 'SDK تلگرام بارگذاری نشد';
            body  = 'فایل telegram-web-app.js نه از سرور و نه از CDN قابل دانلود نبود.';
            hint  = 'مدیر سرور باید فایل <code>app/js/telegram-web-app.js</code> را روی هاست بازیابی کند.';
        } else if (code === 'NO_INIT_DATA') {
            if (!diag.hasWebApp) {
                title = 'این صفحه را داخل تلگرام باز کنید';
                body  = 'این مینی‌اپ تنها از طریق ربات تلگرام قابل استفاده است.';
            } else if (diag.hasUnsafeUser && !diag.hasInitData) {
                title = 'پیکربندی ربات نیاز به اصلاح دارد';
                body  = 'مینی‌اپ از طریق دکمه‌ی نادرستی باز شده و امضای امنیتی همراه ندارد.';
                hint  = 'دکمه‌ی Mini App باید از نوع <code>web_app</code> باشد، نه <code>url</code>.';
            }
        } else if (status === 401 || status === 403) {
            title = 'احراز هویت ناموفق';
            body  = (err && err.message) || 'سرور توکن را نپذیرفت. لطفاً مینی‌اپ را ببندید و دوباره از داخل ربات باز کنید.';
        } else if (status === 404) {
            title = 'سرور پیدا نشد';
            body  = 'آدرس API در دسترس نیست. مدیر سرور باید مسیر api/ را بررسی کند.';
            hint  = 'بررسی کنید که پوشه‌ی <code>api/</code> در کنار <code>app/</code> روی سرور موجود باشد و فایل <code>verify.php</code> در دسترس باشد.';
        } else if (status >= 500) {
            title = 'خطای سرور';
            body  = (err && err.message) || ('سرور با کد ' + status + ' پاسخ داد.');
        }

        logErr(title + ' — ' + body, 'access-error', err && err.stack || '', { code, status, data });

        view.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>${escapeHtml(title)}</h3>
                <p class="muted">${escapeHtml(body)}</p>
                ${hint ? `<p class="muted mono mt-sm" style="font-size:11px;line-height:1.7">${hint}</p>` : ''}
                <button class="btn btn-primary mt-md" id="retry-btn">تلاش مجدد</button>
                <details class="mt-md" style="text-align:start">
                    <summary class="muted mono" style="font-size:11px;cursor:pointer">جزئیات تشخیصی</summary>
                    <pre class="codeblock" style="margin-top:8px;direction:ltr;text-align:start;font-size:11px">${escapeHtml(JSON.stringify({ code, status, server: data, diag }, null, 2))}</pre>
                </details>
            </div>
        `;

        const retry = document.getElementById('retry-btn');
        if (retry) retry.addEventListener('click', () => {
            if (typeof window.__hardReload === 'function') {
                window.__hardReload();
                return;
            }
            try { sessionStorage.clear(); } catch (_) {}
            try {
                const u = new URL(location.href);
                u.searchParams.set('_r', Date.now().toString(36));
                location.replace(u.toString());
            } catch (_) {
                location.reload();
            }
        });
    } catch (renderErr) {
        logErr('Error UI itself crashed: ' + (renderErr.message || renderErr),
               'showAccessError-internal',
               renderErr.stack || '');
    }
}

function applyStartParam() {
    try {
        const w = (window.Telegram && window.Telegram.WebApp) || null;
        const sp = w && w.initDataUnsafe && w.initDataUnsafe.start_param
            ? String(w.initDataUnsafe.start_param)
            : '';
        if (!sp) return;

        if (sp.startsWith('plisiopaid_') || sp.startsWith('plisiofail_') || sp.startsWith('abangatewaypaid_')) {
            const order = sp.replace(/^(plisio(paid|fail)|abangatewaypaid)_/, '');
            if (order) {
                const want = '#/watch/' + encodeURIComponent(order);
                if (window.location.hash !== want) {
                    window.location.hash = want;
                }
            }
        }
    } catch (_) {  }
}

function showPhonePrompt({ title, body, primaryLabel, onPrimary, secondaryLabel, onSecondary }) {
    const view = document.getElementById('view');
    if (!view) return;
    view.innerHTML = `
        <div class="empty">
            ${icon('phone', 'class="ico ico-xxl"')}
            <h3>${escapeHtml(title)}</h3>
            <p class="muted">${escapeHtml(body)}</p>
            <button class="btn btn-primary mt-md" id="phone-primary-btn">${escapeHtml(primaryLabel)}</button>
            ${secondaryLabel ? `<button class="btn mt-sm" id="phone-secondary-btn">${escapeHtml(secondaryLabel)}</button>` : ''}
        </div>
    `;
    const primary = document.getElementById('phone-primary-btn');
    if (primary && typeof onPrimary === 'function') {
        primary.addEventListener('click', onPrimary);
    }
    const secondary = document.getElementById('phone-secondary-btn');
    if (secondary && typeof onSecondary === 'function') {
        secondary.addEventListener('click', onSecondary);
    }
}

function handleForceJoinGate(gateErr) {
    const gate = (gateErr && gateErr.data) || {};
    const channels = Array.isArray(gate.channels) ? gate.channels : [];
    const headMsg = gate.msg || 'برای استفاده از مینی‌اپ، ابتدا در کانال‌های زیر عضو شوید.';

    const view = document.getElementById('view');
    if (!view) return;

    const itemsHtml = channels.map((ch, i) => {
        const title = escapeHtml(String((ch && ch.title) || ('کانال ' + (i + 1))));
        return `
            <button class="btn btn-block mt-sm join-channel-btn" data-idx="${i}">
                ${icon('send', 'class="ico"')}
                <span>${title}</span>
            </button>
        `;
    }).join('');

    view.innerHTML = `
        <div class="empty">
            ${icon('users', 'class="ico ico-xxl"')}
            <h3>عضویت اجباری</h3>
            <p class="muted">${escapeHtml(headMsg)}</p>
            <div class="mt-md" style="width:100%">${itemsHtml}</div>
            <button class="btn btn-primary mt-md" id="join-recheck-btn">عضو شدم، بررسی مجدد</button>
        </div>
    `;

    const btns = view.querySelectorAll('.join-channel-btn');
    for (let i = 0; i < btns.length; i++) {
        btns[i].addEventListener('click', () => {
            const idx = parseInt(btns[i].getAttribute('data-idx'), 10);
            const ch = channels[idx];
            if (ch && ch.link) {
                Telegram.openChannel(ch.link);
            }
        });
    }

    const recheck = document.getElementById('join-recheck-btn');
    if (recheck) {
        recheck.addEventListener('click', () => {
            showInitialSkeleton();
            bootstrap().catch((e) => {
                logErr((e && e.message) || String(e), 'force-join-recheck', (e && e.stack) || '');
                showAccessError(e);
            });
        });
    }
}
window.__handleForceJoinGate = handleForceJoinGate;

async function handlePhoneGate(gateErr) {
    const gate = (gateErr && gateErr.data) || {};
    const wantIran = !!gate.iran;

    if (!Telegram.supportsContactRequest()) {
        const botUsername = Telegram.getBotUsername();
        showPhonePrompt({
            title: 'احراز هویت الزامی است',
            body: botUsername
                ? 'نسخهٔ تلگرام شما از تأیید شماره داخل مینی‌اپ پشتیبانی نمی‌کند. لطفاً از داخل ربات شمارهٔ خود را تأیید کنید.'
                : 'نسخهٔ تلگرام شما از تأیید شماره داخل مینی‌اپ پشتیبانی نمی‌کند. لطفاً تلگرام را به‌روزرسانی کنید یا از داخل ربات شمارهٔ خود را تأیید کنید.',
            primaryLabel: botUsername ? 'باز کردن ربات' : 'تلاش مجدد',
            onPrimary: () => {
                if (botUsername) {
                    Telegram.openBot(botUsername + '?start=verify');
                } else if (typeof window.__hardReload === 'function') {
                    window.__hardReload();
                } else {
                    location.reload();
                }
            },
        });
        return false;
    }

    let contactResponse;
    try {
        contactResponse = await Telegram.requestContact();
    } catch (err) {
        const reason = (err && err.message) || '';
        if (reason === 'UNSUPPORTED') {
            return handlePhoneGate(gateErr);
        }
        showPhonePrompt({
            title: 'احراز هویت الزامی است',
            body: wantIran
                ? 'برای دسترسی به خدمات این مینی‌اپ، باید شمارهٔ موبایل ایران خود را تأیید کنید.'
                : 'برای دسترسی به خدمات این مینی‌اپ، باید شمارهٔ موبایل خود را تأیید کنید.',
            primaryLabel: 'تأیید شماره موبایل',
            onPrimary: () => {
                showInitialSkeleton();
                bootstrap().catch((e) => {
                    logErr((e && e.message) || String(e), 'phone-gate-retry', (e && e.stack) || '');
                    showAccessError(e);
                });
            },
        });
        return false;
    }

    try {
        await submitPhone(contactResponse);
    } catch (err) {
        logErr((err && err.message) || 'phone submit failed', 'phone-gate-submit', (err && err.stack) || '', {
            code: err && err.code, status: err && err.status, data: err && err.data,
        });
        const msg = (err && err.message) || 'تأیید شماره ناموفق بود.';
        await Telegram.showAlert(msg);
        showPhonePrompt({
            title: 'تأیید شماره ناموفق بود',
            body: msg,
            primaryLabel: 'تلاش مجدد',
            onPrimary: () => {
                showInitialSkeleton();
                bootstrap().catch((e) => {
                    logErr((e && e.message) || String(e), 'phone-gate-retry', (e && e.stack) || '');
                    showAccessError(e);
                });
            },
        });
        return false;
    }

    Telegram.hapticNotify('success');
    return true;
}

async function bootstrap() {
    showInitialSkeleton();

    try {
        await Telegram.waitForSDK(4000);
        Telegram.ready();
    } catch (err) {
        logErr(err.message || 'SDK wait failed', 'bootstrap-sdk', err.stack || '');
        showAccessError(err);
        return;
    }

    applyStartParam();

    try {
        await verify();
    } catch (err) {
        if (err && err.code === 'FORCE_JOIN') {
            handleForceJoinGate(err);
            return;
        } else if (err && err.code === 'PHONE_REQUIRED') {
            const passed = await handlePhoneGate(err);
            if (!passed) {
                return;
            }
        } else {
            logErr((err && err.message) || 'verify failed', 'bootstrap-verify', (err && err.stack) || '', {
                code: err && err.code, status: err && err.status, data: err && err.data,
            });
            showAccessError(err);
            return;
        }
    }

    try {
        startRouter();
        loadContentOverrides().catch(() => {});
    } catch (err) {
        logErr(err.message || 'router start failed', 'bootstrap-start', err.stack || '');
        showAccessError(err);
    }

    loadBrandFromServer().catch(() => {});
    initNotifications().catch(() => {});
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootstrap().catch((err) => {
            logErr((err && err.message) || String(err), 'bootstrap-unhandled', (err && err.stack) || '');
            showAccessError(err);
        });
    }, { once: true });
} else {
    bootstrap().catch((err) => {
        logErr((err && err.message) || String(err), 'bootstrap-unhandled', (err && err.stack) || '');
        showAccessError(err);
    });
}

window.addEventListener('unhandledrejection', (e) => {
    const r = e.reason;
    if (r && r.code === 'FORCE_JOIN') {
        handleForceJoinGate(r);
    }
});

(function(){
  function clearPanel(){
    var h=document.getElementById('action-panel-host');
    if(!h||!h.querySelector('.ico-success'))return;
    h.innerHTML='';
    var a=document.querySelectorAll('.action-tile.is-active');
    for(var i=0;i<a.length;i++)a[i].classList.remove('is-active');
  }
  document.addEventListener('click',function(e){
    var h=document.getElementById('action-panel-host');
    if(h&&h.querySelector('.ico-success')&&e.target===h)clearPanel();
  });
  document.addEventListener('keydown',function(e){
    if(e.key==='Escape')clearPanel();
  });
})();

(function(){
  var SEL='.btn,.action-tile,.list-item,.plan,.pay-card,.theme-tile,.chip,.icon-btn';
  document.addEventListener('pointerdown',function(e){
    var el=e.target;
    if(!el||!el.closest)return;
    var t=el.closest(SEL);
    if(!t)return;
    if(t.disabled||t.classList.contains('is-disabled')||t.classList.contains('is-locked'))return;
    try{ var tg=window.Telegram&&window.Telegram.WebApp; if(tg&&tg.HapticFeedback)tg.HapticFeedback.impactOccurred('light'); }catch(_){}
  },true);
})();

(function(){
  var INT='a,button,.btn,.action-tile,.list-item,.plan,.pay-card,.theme-tile,.chip,.icon-btn,.tab,.tab-cta,[role="button"]';
  document.addEventListener('contextmenu',function(e){
    var el=e.target;
    if(el && el.closest){
      if(el.closest('.selectable')) return;
      if(el.closest(INT)) e.preventDefault();
    }
  },false);
  function neutralize(a){
    if(a.getAttribute('data-fx-route')!=null)return;
    var href=a.getAttribute('href');
    if(href==null)return;
    if(href.charAt(0)!=='#'||href.length<2)return;
    a.setAttribute('data-fx-route',href);
    a.removeAttribute('href');
    if(a.getAttribute('role')==null)a.setAttribute('role','button');
    if(!a.hasAttribute('tabindex'))a.setAttribute('tabindex','0');
  }
  function scan(root){
    var list=(root||document).querySelectorAll('a[href^="#"]');
    for(var i=0;i<list.length;i++)neutralize(list[i]);
  }
  function go(a){ var h=a.getAttribute('data-fx-route'); if(h)location.hash=h; }
  document.addEventListener('click',function(e){
    var a=e.target.closest&&e.target.closest('a[data-fx-route]');
    if(a){ e.preventDefault(); e.stopPropagation(); go(a); }
  },true);
  document.addEventListener('keydown',function(e){
    if(e.key==='Enter'||e.key===' '){
      var a=e.target.closest&&e.target.closest('a[data-fx-route]');
      if(a){ e.preventDefault(); go(a); }
    }
  });
  function start(){
    scan(document);
    if(window.MutationObserver){
      var mo=new MutationObserver(function(m){
        for(var i=0;i<m.length;i++){
          var n=m[i].addedNodes;
          for(var j=0;j<n.length;j++){
            var node=n[j];
            if(node.nodeType!==1)continue;
            if(node.matches&&node.matches('a[href^="#"]'))neutralize(node);
            if(node.querySelectorAll)scan(node);
          }
        }
      });
      mo.observe(document.body||document.documentElement,{childList:true,subtree:true});
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);
  else start();
})();
