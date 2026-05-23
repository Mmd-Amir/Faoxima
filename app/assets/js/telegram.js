function tg() {
    return (typeof window !== 'undefined' && window.Telegram && window.Telegram.WebApp) || null;
}

export function ready() {
    try {
        const w = tg();
        if (!w) return;
        if (typeof w.ready === 'function') w.ready();
        if (typeof w.expand === 'function') w.expand();
        if (typeof w.setHeaderColor === 'function') w.setHeaderColor('#0a0907');
        if (typeof w.setBackgroundColor === 'function') w.setBackgroundColor('#0a0907');
    } catch (_) {  }
}

export function getInitData() {
    const w = tg();
    return (w && w.initData) || '';
}

export function getInitDataUnsafe() {
    const w = tg();
    return (w && w.initDataUnsafe) || null;
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
            if (Date.now() - start >= timeoutMs) return resolve(getInitData() || '');
            setTimeout(tick, 50);
        };
        tick();
    });
}


export function diagnostics() {
    try {
        const w = tg();
        const unsafe = (w && w.initDataUnsafe) || null;
        return {
            hasTelegram:      typeof window !== 'undefined' && !!window.Telegram,
            hasWebApp:        !!w,
            platform:         (w && w.platform) || null,
            version:          (w && w.version) || null,
            colorScheme:      (w && w.colorScheme) || null,
            hasInitData:      !!(w && w.initData),
            initDataLength:   (w && w.initData) ? String(w.initData).length : 0,
            hasUnsafeUser:    !!(unsafe && unsafe.user && unsafe.user.id),
            unsafeUserId:     unsafe && unsafe.user ? unsafe.user.id : null,
            href:             (typeof location !== 'undefined') ? location.href : null,
            hash:             (typeof location !== 'undefined') ? location.hash : null,
        };
    } catch (e) {
        return { error: String((e && e.message) || e), hasWebApp: false };
    }
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
    try {
        const w = tg();
        if (w && w.HapticFeedback && typeof w.HapticFeedback.impactOccurred === 'function') {
            w.HapticFeedback.impactOccurred(type);
        }
    } catch (_) {  }
}
export function hapticNotify(type = 'success') {
    try {
        const w = tg();
        if (w && w.HapticFeedback && typeof w.HapticFeedback.notificationOccurred === 'function') {
            w.HapticFeedback.notificationOccurred(type);
        }
    } catch (_) {  }
}

export function close() {
    try { const w = tg(); if (w && typeof w.close === 'function') w.close(); } catch (_) {}
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
        try { alert(msg); } catch (_) {}
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
        let ok = false;
        try { ok = window.confirm(msg); } catch (_) {}
        resolve(ok);
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

