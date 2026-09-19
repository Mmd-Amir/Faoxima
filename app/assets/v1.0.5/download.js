import { toast, copyToClipboard } from './utils.js?v=0.0.52';
import { hapticImpact, supportsNativeDownload, downloadFileNative, openLink } from './telegram.js?v=0.0.52';

export function isTelegramApp() {
    try {
        const tg = window.Telegram && window.Telegram.WebApp;
        const platform = tg ? String(tg.platform || '').toLowerCase() : '';
        if (platform && platform !== 'unknown' && platform !== 'web') return true;
        return /Telegram-(Android|iOS)/i.test(navigator.userAgent || '');
    } catch (_) { return false; }
}

export function tgPlatform() {
    try {
        const tg = window.Telegram && window.Telegram.WebApp;
        return tg ? String(tg.platform || '') : '';
    } catch (_) { return ''; }
}

export function dataUriToBlob(uri) {
    const m = /^data:([^;,]*)(;base64)?,([\s\S]*)$/i.exec(String(uri || ''));
    if (!m) return null;
    const mime = m[1] || 'application/octet-stream';
    try {
        if (m[2]) {
            const bin = atob(m[3]);
            return new Blob([Uint8Array.from(bin, (ch) => ch.charCodeAt(0))], { type: mime });
        }
        return new Blob([decodeURIComponent(m[3])], { type: mime });
    } catch (_) { return null; }
}

export function decodeDataUriText(uri) {
    const m = /^data:([^;,]*)(;base64)?,([\s\S]*)$/i.exec(String(uri || ''));
    if (!m) return null;
    try {
        const raw = m[2]
            ? new TextDecoder('utf-8').decode(Uint8Array.from(atob(m[3]), (ch) => ch.charCodeAt(0)))
            : decodeURIComponent(m[3]);
        if (!raw || /[\u0000-\u0008\u000E-\u001F]/.test(raw)) return null;
        return raw;
    } catch (_) { return null; }
}

export function base64ToBlob(b64, mime = 'application/octet-stream') {
    try {
        const bin = atob(String(b64 || ''));
        return new Blob([Uint8Array.from(bin, (ch) => ch.charCodeAt(0))], { type: mime });
    } catch (_) { return null; }
}

async function blobFallback(blob, name, fallbackText, label) {
    let ok = false;
    try {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = name;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 4000);
        ok = true;
    } catch (_) { ok = false; }

    if (!ok) {
        if (fallbackText) {
            const copied = await copyToClipboard(fallbackText);
            toast(copied ? 'دانلود ممکن نبود — متن فایل کپی شد' : 'دانلود و کپی ناموفق بود', copied ? 'success' : 'error');
        } else {
            toast('دانلود در این مرورگر ممکن نیست', 'error');
        }
    }
    return ok;
}

export async function downloadFromUrl(url, fileName, { label = '' } = {}) {
    if (!url) return false;
    try { hapticImpact('light'); } catch (_) {}

    if (isTelegramApp() && supportsNativeDownload()) {
        const res = await downloadFileNative(url, fileName);
        if (res.ok) {
            toast('دانلود آغاز شد', 'success');
            return true;
        }
        if (res.reason === 'declined') return true;
    }

    try {
        openLink(url);
        toast('فایل در مرورگر باز شد', 'success');
        return true;
    } catch (err) {
        return false;
    }
}

export async function downloadBlob(blob, fileName, { fallbackText = '', label = '' } = {}) {
    if (!blob) {
        toast('فایل نامعتبر است', 'error');
        return false;
    }
    try { hapticImpact('light'); } catch (_) {}

    if (isTelegramApp() && supportsNativeDownload()) {
        let objUrl = null;
        try {
            objUrl = URL.createObjectURL(blob);
            const res = await downloadFileNative(objUrl, fileName);
            setTimeout(() => { try { URL.revokeObjectURL(objUrl); } catch (_) {} }, 30000);
            if (res.ok) {
                toast('دانلود آغاز شد', 'success');
                return true;
            }
            if (res.reason === 'declined') return true;
        } catch (_) {
            if (objUrl) { try { URL.revokeObjectURL(objUrl); } catch (__) {} }
        }
    }

    return blobFallback(blob, fileName, fallbackText, label);
}
