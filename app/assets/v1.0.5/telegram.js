function tg() {
    return (typeof window !== 'undefined' && window.Telegram && window.Telegram.WebApp) || null;
}

const INIT_DATA_KEY = 'faoxima.initData';

function readPersistedInitData() {
    try {
        const v = localStorage.getItem(INIT_DATA_KEY);
        return typeof v === 'string' ? v : '';
    } catch (_) { return ''; }
}

export function persistInitData(value) {
    if (typeof value !== 'string' || value === '') return;
    try { localStorage.setItem(INIT_DATA_KEY, value); } catch (_) {}
}

export function clearPersistedInitData() {
    try { localStorage.removeItem(INIT_DATA_KEY); } catch (_) {}
}

function applySafeAreaVars() {
    try {
        const w = tg();
        if (!w) return;
        const root = document.documentElement.style;
        const outer = w.safeAreaInset || {};
        const content = w.contentSafeAreaInset || {};
        const top = Math.max(Number(outer.top) || 0, Number(content.top) || 0);
        const bottom = Math.max(Number(outer.bottom) || 0, Number(content.bottom) || 0);
        const left = Math.max(Number(outer.left) || 0, Number(content.left) || 0);
        const right = Math.max(Number(outer.right) || 0, Number(content.right) || 0);
        root.setProperty('--tg-safe-area-top', top + 'px');
        root.setProperty('--tg-safe-area-bottom', bottom + 'px');
        root.setProperty('--tg-safe-area-left', left + 'px');
        root.setProperty('--tg-safe-area-right', right + 'px');
    } catch (_) {  }
}

export function syncSafeArea() {
    try {
        const w = tg();
        if (!w) return;
        applySafeAreaVars();
        if (typeof w.onEvent === 'function') {
            w.onEvent('safeAreaChanged', applySafeAreaVars);
            w.onEvent('contentSafeAreaChanged', applySafeAreaVars);
            w.onEvent('viewportChanged', applySafeAreaVars);
        }
    } catch (_) {  }
}

export function ready() {
    try {
        const w = tg();
        if (!w) return;
        if (typeof w.ready === 'function') w.ready();
        if (typeof w.expand === 'function') w.expand();
        if (typeof w.disableVerticalSwipes === 'function') w.disableVerticalSwipes();

        var __chrome = '#0a0a14';
        if (typeof w.setHeaderColor === 'function') w.setHeaderColor(__chrome);
        if (typeof w.setBackgroundColor === 'function') w.setBackgroundColor(__chrome);

        syncSafeArea();
    } catch (_) {  }
}

export function getInitData() {
    const w = tg();
    const live = (w && w.initData) || '';
    if (live) {

        persistInitData(live);
        return live;
    }

    return readPersistedInitData();
}

const INIT_DATA_UNSAFE_KEY = 'faoxima.initDataUnsafe';

function readPersistedInitDataUnsafe() {
    try {
        const v = localStorage.getItem(INIT_DATA_UNSAFE_KEY);
        return v ? JSON.parse(v) : null;
    } catch (_) { return null; }
}

function persistInitDataUnsafe(value) {
    try { localStorage.setItem(INIT_DATA_UNSAFE_KEY, JSON.stringify(value)); } catch (_) {}
}

function parseInitDataString(raw) {
    if (typeof raw !== 'string' || raw === '') return null;
    try {
        const params = new URLSearchParams(raw);
        const out = {};
        params.forEach((value, key) => {
            if (key === 'user' || key === 'receiver' || key === 'chat') {
                try { out[key] = JSON.parse(value); return; } catch (_) {}
            }
            out[key] = value;
        });
        return (out.user && out.user.id) ? out : null;
    } catch (_) { return null; }
}

export function getInitDataUnsafe() {
    const w = tg();
    const live = (w && w.initDataUnsafe) || null;
    if (live && live.user) {
        persistInitDataUnsafe(live);
        return live;
    }
    const persisted = readPersistedInitDataUnsafe();
    if (persisted && persisted.user) return persisted;
    const derived = parseInitDataString(readPersistedInitData());
    if (derived) {
        persistInitDataUnsafe(derived);
        return derived;
    }
    return persisted || live;
}

export function waitForSDK(timeoutMs = 4000) {
    if (tg()) return Promise.resolve(true);
    return new Promise((resolve) => {
        const start = Date.now();
        const tick = () => {
            if (tg()) return resolve(true);
            if (Date.now() - start >= timeoutMs) return resolve(!!tg());
            setTimeout(tick, 50);
        };
        tick();
    });
}

export function waitForInitData(timeoutMs = 1500) {
    const immediate = getInitData();
    if (immediate) return Promise.resolve(immediate);
    return new Promise((resolve) => {
        const start = Date.now();
        const tick = () => {
            const v = getInitData();
            if (v) return resolve(v);
            if (Date.now() - start >= timeoutMs) {

                resolve(getInitData() || readPersistedInitData() || '');
                return;
            }
            setTimeout(tick, 50);
        };
        tick();
    });
}

export function diagnostics() {
    const w = tg();
    const unsafe = (w && w.initDataUnsafe) || null;
    const persisted = readPersistedInitData();
    return {
        hasTelegram:           typeof window !== 'undefined' && !!window.Telegram,
        hasWebApp:             !!w,
        platform:              (w && w.platform) || null,
        version:               (w && w.version) || null,
        colorScheme:           (w && w.colorScheme) || null,
        hasInitData:           !!(w && w.initData),
        initDataLength:        (w && w.initData) ? w.initData.length : 0,
        hasPersistedInitData:  !!persisted,
        hasUnsafeUser:         !!(unsafe && unsafe.user && unsafe.user.id),
        unsafeUserId:          unsafe && unsafe.user ? unsafe.user.id : null,
    };
}

export function showBackButton(handler) {
    const w = tg();
    if (!w || !w.BackButton) return () => {};
    try { w.BackButton.show(); } catch (_) {}
    try { w.BackButton.onClick(handler); } catch (_) {}
    return () => {
        try { w.BackButton.offClick(handler); } catch (_) {}
        try { w.BackButton.hide(); } catch (_) {}
    };
}

export function hapticImpact(type = 'light') {
    try { tg()?.HapticFeedback?.impactOccurred(type); } catch (_) {  }
}
export function hapticNotify(type = 'success') {
    try { tg()?.HapticFeedback?.notificationOccurred(type); } catch (_) {  }
}

export function close() {
    try { tg()?.close(); } catch (_) {}
}

export function showAlert(msg) {
    return new Promise((resolve) => {
        try {
            const w = tg();
            if (w && typeof w.showAlert === 'function') {
                w.showAlert(msg, () => resolve());
                return;
            }
        } catch (_) {  }
        alert(msg);
        resolve();
    });
}

export function showConfirm(msg) {
    return new Promise((resolve) => {
        try {
            const w = tg();
            if (w && typeof w.showConfirm === 'function') {
                w.showConfirm(msg, (ok) => resolve(!!ok));
                return;
            }
        } catch (_) {  }
        resolve(window.confirm(msg));
    });
}

export function openBot(botUsername) {
    if (!botUsername) return;
    const w = tg();
    const url = `https://t.me/${botUsername}`;
    try {
        if (w && typeof w.openTelegramLink === 'function') {
            w.openTelegramLink(url);
            return;
        }
    } catch (_) {  }
    try { window.location.href = url; } catch (_) {}
}

export function openChannel(url) {
    const u = String(url || '');
    if (!u) return;
    const w = tg();
    const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
    try {
        if (w && isTme && typeof w.openTelegramLink === 'function') {
            w.openTelegramLink(u);
            return;
        }
        if (w && typeof w.openLink === 'function') {
            w.openLink(u);
            return;
        }
    } catch (_) {  }
    try { window.open(u, '_blank', 'noopener'); } catch (_) {
        try { window.location.href = u; } catch (__) {}
    }
}

export function supportsNativeDownload() {
    try {
        const w = tg();
        return !!(w && typeof w.downloadFile === 'function');
    } catch (_) { return false; }
}

export function downloadFileNative(url, fileName) {
    return new Promise((resolve) => {
        let settled = false;
        const done = (v) => { if (!settled) { settled = true; resolve(v); } };
        try {
            const w = tg();
            if (!w || typeof w.downloadFile !== 'function') {
                done({ ok: false, reason: 'unsupported' });
                return;
            }
            const timer = setTimeout(() => done({ ok: false, reason: 'timeout' }), 15000);
            w.downloadFile({ url: String(url || ''), file_name: String(fileName || 'config.conf') }, (accepted) => {
                clearTimeout(timer);
                done({ ok: !!accepted, reason: accepted ? 'accepted' : 'declined' });
            });
        } catch (err) {
            done({ ok: false, reason: 'threw', error: err && err.message ? String(err.message) : String(err) });
        }
    });
}

export function isDesktopPlatform() {
    try {
        const w = tg();
        const platform = w ? String(w.platform || '').toLowerCase() : '';
        return platform === 'tdesktop' || platform === 'macos' || platform === 'web' || platform === 'unigram';
    } catch (_) { return false; }
}

export function openLink(url, { external = true } = {}) {
    const u = String(url || '');
    if (!u) return false;
    const w = tg();
    try {
        if (w && typeof w.openLink === 'function') {
            w.openLink(u, external ? { try_instant_view: false } : undefined);
            return true;
        }
    } catch (_) {}
    try {
        const win = window.open(u, '_blank', 'noopener');
        if (win) return true;
    } catch (_) {}
    try {
        window.location.href = u;
        return true;
    } catch (_) {}
    return false;
}

export function getBotUsername() {
    try {
        const cfg = window.__APP_CONFIG__ || {};
        if (cfg.botUsername) return String(cfg.botUsername);
    } catch (_) {  }
    try {
        const w = tg();
        const u = w && w.initDataUnsafe ? w.initDataUnsafe : null;
        if (u && u.bot && u.bot.username) return String(u.bot.username);
    } catch (_) {  }
    return '';
}

export function supportsContactRequest() {
    const w = tg();
    if (!w) return false;
    if (typeof w.requestContact !== 'function') return false;
    if (typeof w.isVersionAtLeast === 'function') {
        try { return !!w.isVersionAtLeast('6.9'); } catch (_) { return true; }
    }
    return true;
}

export function requestContact() {
    return new Promise((resolve, reject) => {
        const w = tg();
        if (!w || typeof w.requestContact !== 'function') {
            reject(new Error('UNSUPPORTED'));
            return;
        }
        let settled = false;
        const finish = (fn, arg) => {
            if (settled) return;
            settled = true;
            fn(arg);
        };
        try {
            w.requestContact((sent, event) => {
                if (!sent) {
                    finish(reject, new Error('CANCELLED'));
                    return;
                }
                const response = (event && typeof event.response === 'string') ? event.response : '';
                if (!response) {
                    finish(reject, new Error('NO_RESPONSE'));
                    return;
                }
                finish(resolve, response);
            });
        } catch (err) {
            const msg = (err && err.message) ? String(err.message) : String(err);
            if (msg.indexOf('Unsupported') !== -1) {
                finish(reject, new Error('UNSUPPORTED'));
            } else {
                finish(reject, err instanceof Error ? err : new Error(msg));
            }
        }
    });
}

