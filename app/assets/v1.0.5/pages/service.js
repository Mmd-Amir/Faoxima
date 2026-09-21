import { call, ApiError, configFileUrl } from '../api.js?v=0.0.52';
import { downloadFromUrl, downloadBlob, dataUriToBlob, decodeDataUriText, isTelegramApp, tgPlatform } from '../download.js?v=0.0.52';
import {
    escapeHtml, fmtPrice, fmtGb, fmtBytes, fmtIpLimit, skeletonList, copyToClipboard, toast, trafficPercent, serviceStatusBadge, blobToDataUrl,
} from '../utils.js?v=0.0.52';
import { hapticImpact, hapticNotify, showConfirm, openLink, supportsNativeDownload, downloadFileNative } from '../telegram.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { renderMethodCard, handleInitResult } from '../payment-ui.js?v=0.0.55';

const ALL_ACTIONS = [
    { id: 'renew',           label: 'تمدید سرویس',            ico: 'rotate',     cls: '', inline: true  },
    { id: 'extra_time',      label: 'خرید زمان اضافه',         ico: 'hourglass',  cls: '', inline: true  },
    { id: 'extra_volume',    label: 'خرید حجم اضافه',          ico: 'box',        cls: '', inline: true  },
    { id: 'changelink',      label: 'تغییر لینک',              ico: 'link',       cls: '', inline: false },
    { id: 'refund',          label: 'بازگشت وجه',              ico: 'diamond',    cls: 'is-danger', inline: false },
    { id: 'toggle_status',   label: 'خاموش / روشن کردن اکانت', ico: 'power',      cls: 'is-danger', inline: true  },
    { id: 'transfer',        label: 'انتقال به کاربر دیگر',    ico: 'transfer',   cls: '', inline: false },
    { id: 'change_location', label: 'تغییر موقعیت',            ico: 'pin',        cls: '', inline: false },
    { id: 'report_problem',  label: 'گزارش اختلال',            ico: 'alert',      cls: '', inline: true  },
    { id: 'note',            label: 'تغییر یادداشت',           ico: 'note',       cls: '', inline: true  },
    { id: 'update_info',     label: 'بروزرسانی اطلاعات',       ico: 'refresh',    cls: '', inline: true  },
    { id: 'subscription',    label: 'لینک اشتراک',             ico: 'book',       cls: '', inline: true  },
    { id: 'config',          label: 'دریافت کانفیگ',           ico: 'config',     cls: '', inline: true  },
];
const downloadSources = new Map();
let downloadSourceSeq = 0;

function storeDownloadSource(source) {
    downloadSourceSeq += 1;
    const id = 'download-' + downloadSourceSeq.toString(36);
    downloadSources.set(id, String(source || ''));
    return id;
}


function isQrEnabled() {
    return (window.__APP_CONFIG__ || {}).qrEnabled !== false;
}

function qrUrl(payload, size = 280) {
    const apiUrl = (window.__APP_CONFIG__ || {}).apiUrl || '';
    const enc = encodeURIComponent(String(payload || ''));

    const v = Math.floor(Date.now() / (60 * 60 * 1000));
    return `${apiUrl}/qr.php?s=${size}&d=${enc}&v=${v}`;
}

async function loadQrImage(img, payload, size = 280) {
    const res = await fetch(qrUrl(payload, size), { credentials: 'same-origin' });
    if (!res.ok) throw new Error('QR load failed');
    img.src = await blobToDataUrl(await res.blob());
}

/* qr.php کند؛ payload بلندتر از این حد یعنی QR ناقص/نامعتبر substr(0,2048) payload را به */
const QR_MAX_BYTES = 2048;

function qrPayloadBytes(payload) {
    const s = String(payload || '');
    try { return new TextEncoder().encode(s).length; } catch (_) { return s.length; }
}

function qrUnavailableHtml() {
    return `<div class="qr-block qr-unavailable">${icon('alert', 'class="ico"')}<span>ساخت QR کد برای این کانفیگ ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از دکمهٔ کپی استفاده کنید.</span></div>`;
}

function isStructuredConnectionInfo(c) {
    const s = String(c || '');
    return s.indexOf('\n') !== -1 && !/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//.test(s);
}

function isHttpDownloadLink(c) {
    return /^https?:\/\//i.test(String(c || ''));
}

function configPayload(c) {
    const s = String(c || '');
    if (isHttpDownloadLink(s)) {
        return s.split('#')[0];
    }
    return s;
}

function configName(c, i) {
    const s = String(c || '');
    if (isStructuredConnectionInfo(s)) {
        const firstLine = s.split('\n')[0].trim();
        return firstLine || ('کانفیگ ' + (i + 1));
    }
    const h = s.indexOf('#');
    if (h >= 0 && h < s.length - 1) {
        const raw = s.slice(h + 1);
        try { return decodeURIComponent(raw) || ('کانفیگ ' + (i + 1)); }
        catch (_) { return raw || ('کانفیگ ' + (i + 1)); }
    }
    return 'کانفیگ ' + (i + 1);
}

async function serverDownload(username, index, name) {
    const url = await configFileUrl(username, index);
    if (!url) {
        return false;
    }

    if (supportsNativeDownload()) {
        const res = await downloadFileNative(url, name);
        if (res.ok) {
            toast('دانلود آغاز شد', 'success');
            return true;
        }
        if (res.reason === 'declined') {
            return true;
        }
    }

    try {
        openLink(url);
        toast('فایل در مرورگر باز شد', 'success');
        return true;
    } catch (err) {
        return false;
    }
}

async function triggerBlobDownload(blob, name, fallbackText) {
    const steps = [];
    const t0 = Date.now();
    let ok = false;
    let url = null;
    try {
        steps.push('start');
        steps.push('blobSize=' + (blob && typeof blob.size === 'number' ? blob.size : 'n/a'));
        url = URL.createObjectURL(blob);
        steps.push('objectUrl=' + (url ? 'ok' : 'null'));
        const a = document.createElement('a');
        a.href = url;
        a.download = name;
        a.rel = 'noopener';
        steps.push('downloadAttrSupported=' + ('download' in a));
        steps.push('anchorDownloadValue=' + String(a.download || ''));
        document.body.appendChild(a);
        a.click();
        steps.push('clicked');
        document.body.removeChild(a);
        setTimeout(() => { try { URL.revokeObjectURL(url); } catch (_) {} }, 4000);
        ok = true;
    } catch (err) {
        steps.push('threw=' + (err && err.message ? String(err.message).slice(0, 200) : String(err).slice(0, 200)));
        ok = false;
    }

    let copied = null;
    if (!ok) {
        if (fallbackText) {
            copied = await copyToClipboard(fallbackText);
            steps.push('fallbackCopy=' + (copied ? 'ok' : 'fail'));
            toast(copied ? 'دانلود ممکن نبود — متن فایل کپی شد' : 'دانلود و کپی ناموفق بود', copied ? 'success' : 'error');
        } else {
            steps.push('fallbackCopy=noText');
            toast('دانلود در این مرورگر ممکن نیست', 'error');
        }
    }

    return ok;
}

function wgDecodeBase64(raw) {
    const clean = String(raw || '').replace(/\s+/g, '');
    if (!clean || !/^[A-Za-z0-9+/=_-]+$/.test(clean)) return null;
    let std = clean.replace(/-/g, '+').replace(/_/g, '/');
    const pad = std.length % 4;
    if (pad > 0) std += '='.repeat(4 - pad);
    try {
        const bin = atob(std);
        const bytes = Uint8Array.from(bin, (ch) => ch.charCodeAt(0));
        const out = new TextDecoder('utf-8').decode(bytes);
        return out || null;
    } catch (_) { return null; }
}

function wgConfProtocol(conf) {
    if (typeof conf !== 'string' || conf.toLowerCase().indexOf('[interface]') === -1) return null;
    if (/^\s*(Jc|Jmin|Jmax|S1|S2|S3|S4|H1|H2|H3|H4|I1|HeaderProtectionKey)\s*=/mi.test(conf)) return 'amneziawg';
    return 'wireguard';
}

function wgConfFromLink(link) {
    const s = String(link || '').trim();
    if (!s) return null;
    const m = /^([a-zA-Z][a-zA-Z0-9+.\-]*):\/\//.exec(s);
    if (!m) return null;
    const scheme = m[1].toLowerCase();

    if (scheme === 'vpn' || scheme === 'awg' || scheme === 'amneziawg') {
        const payload = s.slice(scheme.length + 3).split('#')[0];
        const conf = wgDecodeBase64(payload);
        const proto = conf ? wgConfProtocol(conf) : null;
        return proto ? { protocol: proto, conf } : null;
    }

    if (scheme !== 'wireguard' && scheme !== 'wg') return null;

    let body = s.slice(scheme.length + 3);
    let remark = '';
    const hash = body.indexOf('#');
    if (hash !== -1) {
        try { remark = decodeURIComponent(body.slice(hash + 1)); } catch (_) { remark = body.slice(hash + 1); }
        body = body.slice(0, hash);
    }
    const at = body.lastIndexOf('@');
    if (at === -1) return null;
    let privateKey = body.slice(0, at);
    try { privateKey = decodeURIComponent(privateKey); } catch (_) {}
    let rest = body.slice(at + 1);
    let query = '';
    const q = rest.indexOf('?');
    if (q !== -1) { query = rest.slice(q + 1); rest = rest.slice(0, q); }
    const endpoint = rest;
    if (!privateKey || !endpoint) return null;

    const params = {};
    query.split('&').forEach((pair) => {
        if (!pair) return;
        const eq = pair.indexOf('=');
        const k = (eq === -1 ? pair : pair.slice(0, eq)).toLowerCase();
        let v = eq === -1 ? '' : pair.slice(eq + 1);
        try { v = decodeURIComponent(v.replace(/\+/g, ' ')); } catch (_) {}
        params[k] = v;
    });

    const lines = ['[Interface]', `PrivateKey = ${privateKey}`];
    if (params.address) lines.push('Address = ' + params.address.replace(/,/g, ', '));
    if (params.dns) lines.push('DNS = ' + params.dns.replace(/,/g, ', '));
    if (params.mtu) lines.push('MTU = ' + params.mtu);
    const awgKeys = { jc: 'Jc', jmin: 'Jmin', jmax: 'Jmax', s1: 'S1', s2: 'S2', s3: 'S3', s4: 'S4', h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4', i1: 'I1' };
    let isAwg = false;
    Object.keys(awgKeys).forEach((k) => {
        if (params[k]) { lines.push(`${awgKeys[k]} = ${params[k]}`); isAwg = true; }
    });
    lines.push('');
    if (remark) lines.push('# ' + remark);
    lines.push('[Peer]');
    if (params.publickey) lines.push('PublicKey = ' + params.publickey);
    if (params.presharedkey) lines.push('PresharedKey = ' + params.presharedkey);
    lines.push('AllowedIPs = ' + (params.allowedips ? params.allowedips.replace(/,/g, ', ') : '0.0.0.0/0, ::/0'));
    lines.push(`Endpoint = ${endpoint}`);
    if (params.keepalive) lines.push('PersistentKeepalive = ' + params.keepalive);

    return { protocol: isAwg ? 'amneziawg' : 'wireguard', conf: lines.join('\n') + '\n' };
}

function wgConfFilename(link, protocol, index) {
    let remark = '';
    const s = String(link || '');
    const hash = s.indexOf('#');
    if (hash !== -1) {
        try { remark = decodeURIComponent(s.slice(hash + 1)); } catch (_) { remark = s.slice(hash + 1); }
    }
    if (!remark) {
        const info = wgConfFromLink(s);
        if (info) {
            const m = /^\s*#\s*(.+)$/m.exec(info.conf);
            if (m) remark = m[1].trim();
        }
    }
    let base = remark.replace(/_/g, '-').replace(/[\/:*?"<>|\s]+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    const prefix = protocol === 'amneziawg' ? 'amneziawg' : 'wireguard';
    if (!base || /^[0-9-]+$/.test(base)) {
        base = base ? `${prefix}-${base}` : `${prefix}-${(index || 0) + 1}`;
    }
    if (__cfgFilePrefix && base.toLowerCase() !== __cfgFilePrefix.toLowerCase() && !base.toLowerCase().startsWith(`${__cfgFilePrefix.toLowerCase()}-`)) {
        base = `${__cfgFilePrefix}-${base}`;
    }
    base = base.slice(0, 15).replace(/[-.]+$/g, '');
    return base + '.conf';
}

function configDownloadFilename(value, fallback, wireguard) {
    let name = String(fallback || 'config.conf');
    if (String(value || '')) {
        try {
            const parsed = new URL(String(value), window.location.href);
            const fromPath = decodeURIComponent(parsed.pathname.split('/').filter(Boolean).pop() || '');
            if (fromPath) name = fromPath;
        } catch (_) {}
    }
    const dot = name.lastIndexOf('.');
    let ext = dot > 0 ? name.slice(dot + 1).toLowerCase() : (wireguard ? 'conf' : 'bin');
    let stem = dot > 0 ? name.slice(0, dot) : name;
    stem = stem.replace(/_/g, '-').replace(/[^A-Za-z0-9.-]+/g, '-').replace(/-+/g, '-').replace(/^[-.]+|[-.]+$/g, '') || (wireguard ? 'wireguard' : 'config');
    if (__cfgFilePrefix && stem.toLowerCase() !== __cfgFilePrefix.toLowerCase() && !stem.toLowerCase().startsWith(`${__cfgFilePrefix.toLowerCase()}-`)) {
        stem = `${__cfgFilePrefix}-${stem}`;
    }
    if (wireguard) {
        stem = stem.slice(0, 15).replace(/[-.]+$/g, '');
        ext = 'conf';
    }
    return `${stem}.${ext}`;
}

function wgConfDataUri(conf) {
    const bytes = new TextEncoder().encode(conf);
    let bin = '';
    bytes.forEach((b) => { bin += String.fromCharCode(b); });
    return 'data:application/octet-stream;base64,' + btoa(bin);
}

const CONFIG_SCHEME_LABELS = {
    hysteria2: 'Hysteria2',
    ss: 'Shadowsocks',
    vless: 'VLESS',
    vmess: 'VMess',
    trojan: 'Trojan',
    tg: 'MTProto',
    xuifile: 'WireGuard',
    xuiawgfile: 'AmneziaWG',
    amneziawg: 'AmneziaWG',
    awg: 'AmneziaWG',
};

function configProtocolLabel(c) {
    const s = String(c || '');

    if (isStructuredConnectionInfo(s)) {
        const protoMatch = /^Protocol:\s*(.+)$/mi.exec(s);
        if (protoMatch) return protoMatch[1].trim();
        if (/^\s*(Jc|S1|S2|S3|S4|H1|H2|H3|H4)\s*=/mi.test(s)) return 'AmneziaWG';
        if (/^\s*\[Interface\]/mi.test(s)) return 'WireGuard';
        return null;
    }

    if (isHttpDownloadLink(s)) {
        let path = '';
        try { path = new URL(s.split('#')[0]).pathname; } catch (_) { path = s.split('#')[0]; }
        if (path.indexOf('/wg/') !== -1) return 'WireGuard';
        if (path.indexOf('/ov/') !== -1) return 'OpenVPN';
        return null;
    }

    const schemeMatch = /^([a-zA-Z][a-zA-Z0-9+.\-]*):\/\//.exec(s);
    if (schemeMatch) {
        const scheme = schemeMatch[1].toLowerCase();
        if (['wireguard', 'wg', 'vpn', 'awg', 'amneziawg'].indexOf(scheme) !== -1) {
            const info = wgConfFromLink(s);
            if (info) return info.protocol === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
        }
        if (scheme === 'wireguard') return 'WireGuard';
        if (CONFIG_SCHEME_LABELS[scheme]) return CONFIG_SCHEME_LABELS[scheme];
    }
    return null;
}

function isDownloadableFileLink(protocolLabel) {
    return protocolLabel === 'WireGuard' || protocolLabel === 'AmneziaWG' || protocolLabel === 'OpenVPN';
}

const EYE_PATH = '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>';
const EYE_OFF_PATH = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.4 18.4 0 0 1 4.22-5.06M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>';

function svgIcon(pathData, extraAttrs = '') {
    return `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ${extraAttrs}>${pathData}</svg>`;
}

/** Parse a "Label: value" multi-line structured block into an ordered field list, skipping the title/Protocol lines. */
function parseStructuredFields(c) {
    const lines = String(c || '').split('\n');
    const fields = [];
    let server = '';
    let port = '';
    for (let i = 1; i < lines.length; i++) {
        const m = /^([^:]+):\s*(.*)$/.exec(lines[i]);
        if (!m) continue;
        const label = m[1].trim();
        const value = m[2].trim();
        if (label === 'Protocol' || value === '') continue;
        if (label === 'Server') { server = value; continue; }
        if (label === 'Port') { port = value; continue; }
        fields.push({ label, value });
    }
    const ordered = [];
    if (server !== '') {
        ordered.push({ key: 'server', label: 'سرور', value: port !== '' ? `${server}:${port}` : server, secret: false });
    }
    fields.forEach((f) => {
        const key = f.label.toLowerCase();
        const isSecret = key === 'password';
        const faLabel = key === 'username' ? 'نام کاربری'
            : key === 'password' ? 'رمز عبور'
            : key === 'ipsec psk' ? 'IPsec PSK'
            : f.label;
        ordered.push({ key, label: faLabel, value: f.value, secret: isSecret });
    });
    return ordered;
}

function buildCleanCopyAllText(c, i) {
    const title = configName(c, i);
    const rows = parseStructuredFields(c);
    const lines = [title, ...rows.map((row) => `${row.label}: ${row.value}`)];
    return lines.join('\n');
}

function structuredInfoCardHtml(c, i) {
    const rows = parseStructuredFields(c);
    return `
        <div class="rx-info-card" data-i="${i}">
            ${rows.map((row, ri) => `
                <div class="rx-info-row" data-copy-row data-copy-value="${escapeHtml(row.value)}" data-copy-label="${escapeHtml(row.label)}">
                    <span class="rx-info-label muted">${escapeHtml(row.label)}</span>
                    <span class="rx-info-value ${row.secret ? 'is-secret' : ''}" data-info-value="${escapeHtml(row.value)}" data-row="${ri}">${row.secret ? '••••••••' : escapeHtml(row.value)}</span>
                    <span class="rx-info-actions">
                        ${row.secret ? `<button type="button" class="rx-icon-btn" data-reveal-toggle data-row="${ri}" aria-label="نمایش رمز">${svgIcon(EYE_PATH)}</button>` : ''}
                        <button type="button" class="rx-icon-btn" data-copy-icon aria-label="کپی ${escapeHtml(row.label)}">${icon('copy', 'class="ico ico-sm"')}</button>
                    </span>
                </div>
            `).join('')}
        </div>
    `;
}

let __cfgUsername = '';
let __cfgFilePrefix = '';

export function setConfigUsername(u, prefix) {
    __cfgUsername = String(u || '');
    if (prefix !== undefined) {
        __cfgFilePrefix = String(prefix || '').replace(/_/g, '-').replace(/[^A-Za-z0-9-]/g, '').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    }
}

function configListHtml(configs, isManual, offset = 0) {
    return '<div class="cfg-list" style="display:flex;flex-direction:column;gap:10px">' + configs.map((c, localI) => {
        const i = offset + localI;
        const structured = isStructuredConnectionInfo(c);
        const wgInfo = wgConfFromLink(c);
        const payload = wgInfo ? wgConfDataUri(wgInfo.conf) : configPayload(c);
        const protocolLabel = configProtocolLabel(c);
        const isFileDownload = !!wgInfo || isDownloadableFileLink(protocolLabel);
        const wgFileName = wgInfo ? wgConfFilename(c, wgInfo.protocol, i) : '';
        const remoteFileName = !wgInfo ? configDownloadFilename(payload, 'config.conf', false) : '';
        const downloadId = isFileDownload && !wgInfo ? storeDownloadSource(payload) : '';
        return `
        <div class="cfg-item">
            <button type="button" class="cfg-toggle btn btn-ghost btn-block" data-i="${i}" style="display:flex;justify-content:space-between;align-items:center;text-align:start;gap:8px">
                <span style="display:flex;align-items:center;gap:8px">
                    ${icon('config', 'class="ico ico-sm"')}
                    ${protocolLabel ? `<span class="muted" style="font-size:11px;white-space:nowrap;padding:2px 8px;border:1px solid var(--border);border-radius:999px">${escapeHtml(protocolLabel)}</span>` : ''}
                    ${escapeHtml(configName(c, i))}
                </span>
                <span class="muted" style="font-size:12px;white-space:nowrap">${icon('download', 'class="ico ico-sm"')} دریافت کانفیگ</span>
            </button>
            <div class="cfg-detail" data-i="${i}" style="display:none;margin:8px 0 12px">
                ${isFileDownload
                    ? (wgInfo
                        ? `<button type="button" class="btn btn-ghost btn-block" data-wg-download data-wg-name="${escapeHtml(wgFileName)}" data-wg-user="${escapeHtml(__cfgUsername)}" data-wg-index="${i}" data-wg-conf="${escapeHtml(wgInfo.conf)}">${icon('download', 'class="ico ico-sm"')} دانلود فایل کانفیگ (${escapeHtml(wgFileName)})</button>
                       <button type="button" class="btn btn-ghost btn-block copy-btn" data-copy="${escapeHtml(wgInfo.conf)}" style="margin-top:6px">${icon('copy', 'class="ico ico-sm"')} کپی متن کانفیگ</button>`
                        : `<button type="button" class="btn btn-ghost btn-block" data-url-download data-url-name="${escapeHtml(remoteFileName)}" data-download-id="${downloadId}">${icon('download', 'class="ico ico-sm"')} دانلود فایل کانفیگ</button>`)
                    : structured
                    ? `
                <div class="rx-info-card-wrap" style="position:relative">
                    <button type="button" class="rx-icon-btn rx-copy-all" data-copy-all data-copy-value="${escapeHtml(buildCleanCopyAllText(c, i))}" aria-label="کپی همه" style="position:absolute;top:8px;inset-inline-start:8px;z-index:1">
                        ${icon('copy', 'class="ico ico-sm"')} <span style="font-size:11px">کپی همه</span>
                    </button>
                    ${structuredInfoCardHtml(c, i)}
                </div>
                `
                    : `
                ${(!isManual && !structured && isQrEnabled()) ? (qrPayloadBytes(payload) > QR_MAX_BYTES ? qrUnavailableHtml() : `<div class="qr-block"><img class="qr-img" data-qr="${escapeHtml(payload)}" alt="QR" /></div>`) : ''}
                <div class="codeblock">${escapeHtml(payload)}<button class="copy-btn" data-copy="${escapeHtml(payload)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
                `}
            </div>
        </div>`;
    }).join('') + '</div>';
}

const CFG_PAGE_SIZE = 5;

function configListPaginatedHtml(configs, isManual) {
    const totalPages = Math.max(1, Math.ceil(configs.length / CFG_PAGE_SIZE));
    if (totalPages <= 1) {
        return configListHtml(configs, isManual);
    }
    const pages = [];
    for (let p = 0; p < totalPages; p++) {
        const offset = p * CFG_PAGE_SIZE;
        const pageConfigs = configs.slice(offset, offset + CFG_PAGE_SIZE);
        pages.push(`<div class="cfg-page" data-page="${p}" ${p === 0 ? '' : 'hidden'}>${configListHtml(pageConfigs, isManual, offset)}</div>`);
    }
    return `
        <div class="cfg-pages" data-total-pages="${totalPages}">${pages.join('')}</div>
        <div class="pager" id="cfg-pager-${Math.random().toString(36).slice(2, 8)}" data-cfg-pager data-page="0">
            <button type="button" class="btn btn-ghost" data-cfg-prev disabled>${icon('chevronRight', 'class="ico ico-leading"')}قبلی</button>
            <span class="pager-info" data-cfg-pager-info>صفحه 1 از ${totalPages}</span>
            <button type="button" class="btn btn-ghost" data-cfg-next>بعدی${icon('chevronLeft', 'class="ico ico-trailing"')}</button>
        </div>
    `;
}

async function flashCopyFeedback(iconEl, label) {
    if (iconEl) {
        const original = iconEl.innerHTML;
        iconEl.innerHTML = svgIcon('<path d="M20 6L9 17l-5-5"/>');
        iconEl.classList.add('is-copied');
        setTimeout(() => {
            iconEl.innerHTML = original;
            iconEl.classList.remove('is-copied');
        }, 1000);
    }
    try { hapticImpact('light'); } catch (_) {}
    toast(`${label} کپی شد`, 'success', 1400);
}

export function bindConfigList($scope) {
    if (!$scope) return;
    $scope.querySelectorAll('.cfg-toggle').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const item = btn.closest('.cfg-item');
            const detail = item ? item.querySelector('.cfg-detail') : null;
            if (!detail) return;
            const isOpen = detail.style.display !== 'none';
            document.querySelectorAll('.cfg-detail').forEach((d) => { d.style.display = 'none'; });
            if (isOpen) return;
            const img = detail.querySelector('.qr-img');
            if (img && !img.getAttribute('src') && img.dataset.qr) {
                const fail = () => {
                    const block = img.closest('.qr-block');
                    if (block) block.outerHTML = qrUnavailableHtml();
                };
                try {
                    await loadQrImage(img, img.dataset.qr);
                } catch (_) {
                    fail();
                }
            }
            detail.style.display = 'block';
            try { hapticImpact('light'); } catch (_) {}
        });
    });

    const wgBtns = $scope.querySelectorAll('[data-wg-download]');
    const fileBtns = $scope.querySelectorAll('[data-file-download]');
    if (wgBtns.length || fileBtns.length) {
    }
    wgBtns.forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const conf = btn.dataset.wgConf || '';
            const name = btn.dataset.wgName || 'config.conf';
            if (!conf) {
                return;
            }
            try { hapticImpact('light'); } catch (_) {}
            const user = btn.dataset.wgUser || '';
            const idx = parseInt(btn.dataset.wgIndex || '0', 10) || 0;
            const inTelegram = isTelegramApp();
            if (inTelegram && user) {
                const served = await serverDownload(user, idx, name);
                if (served) {
                    closeOpenConfigDetail(btn);
                    return;
                }
            }
            const ok = await triggerBlobDownload(new Blob([conf], { type: 'application/octet-stream' }), name, conf);
            if (ok) {
                closeOpenConfigDetail(btn);
            } else if (user) {
                const served2 = await serverDownload(user, idx, name);
                if (served2) closeOpenConfigDetail(btn);
            }
        });
    });

    $scope.querySelectorAll('[data-url-download]').forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const src = downloadSources.get(btn.dataset.downloadId || '') || '';
            const name = btn.dataset.urlName || 'config.conf';
            if (!src) return;
            const uok = await downloadFromUrl(src, name, { label: 'remote-file' });
            if (uok) closeOpenConfigDetail(btn);
        });
    });

    fileBtns.forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const src = btn.dataset.fileSrc || '';
            const rawText = btn.dataset.fileText || '';
            const name = btn.dataset.fileName || 'config.conf';
            if (!src && !rawText) {
                return;
            }
            try { hapticImpact('light'); } catch (_) {}
            const fileUser = btn.dataset.fileUser || '';
            if (isTelegramApp() && fileUser) {
                const served = await serverDownload(fileUser, 0, name);
                if (served) {
                    closeOpenConfigDetail(btn);
                    return;
                }
            }
            const blob = src
                ? dataUriToBlob(src)
                : new Blob([rawText], { type: 'application/octet-stream' });
            if (!blob) {
                toast('فایل نامعتبر است', 'error');
                return;
            }
            const fok = await downloadBlob(blob, name, { fallbackText: src ? decodeDataUriText(src) : rawText, label: 'inline-file' });
            if (fok) closeOpenConfigDetail(btn);
        });
    });

    $scope.querySelectorAll('[data-reveal-toggle]').forEach((btn) => {
        btn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            const row = btn.closest('.rx-info-row');
            const valueEl = row ? row.querySelector('[data-info-value]') : null;
            if (!valueEl) return;
            const revealed = valueEl.classList.toggle('is-revealed');
            valueEl.textContent = revealed ? valueEl.dataset.infoValue : '••••••••';
            btn.innerHTML = svgIcon(revealed ? EYE_OFF_PATH : EYE_PATH);
            try { hapticImpact('light'); } catch (_) {}
        });
    });

    $scope.querySelectorAll('[data-copy-row]').forEach((row) => {
        row.addEventListener('click', async (ev) => {
            if (ev.target.closest('[data-reveal-toggle]')) return;
            ev.stopPropagation();
            const iconEl = row.querySelector('[data-copy-icon]');
            const ok = await copyToClipboard(row.dataset.copyValue || '');
            if (ok) await flashCopyFeedback(iconEl, row.dataset.copyLabel || 'مقدار');
            else toast('خطا در کپی', 'error');
        });
    });

    $scope.querySelectorAll('[data-copy-all]').forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.stopPropagation();
            const ok = await copyToClipboard(btn.dataset.copyValue || '');
            if (ok) await flashCopyFeedback(btn.querySelector('svg') ? btn : null, 'اطلاعات کانفیگ');
            else toast('خطا در کپی', 'error');
        });
    });

    $scope.querySelectorAll('[data-cfg-pager]').forEach((pagerEl) => {
        const $pages = pagerEl.previousElementSibling;
        if (!$pages || !$pages.classList.contains('cfg-pages')) return;
        const totalPages = Number($pages.dataset.totalPages) || 1;
        const $prev = pagerEl.querySelector('[data-cfg-prev]');
        const $next = pagerEl.querySelector('[data-cfg-next]');
        const $info = pagerEl.querySelector('[data-cfg-pager-info]');

        function showPage(page) {
            pagerEl.dataset.page = String(page);
            $pages.querySelectorAll('.cfg-page').forEach((el) => {
                el.hidden = Number(el.dataset.page) !== page;
            });
            $info.textContent = `صفحه ${page + 1} از ${totalPages}`;
            $prev.disabled = page <= 0;
            $next.disabled = page >= totalPages - 1;
        }

        $prev.addEventListener('click', () => {
            const page = Number(pagerEl.dataset.page) || 0;
            if (page <= 0) return;
            try { hapticImpact('light'); } catch (_) {}
            showPage(page - 1);
            pagerEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        $next.addEventListener('click', () => {
            const page = Number(pagerEl.dataset.page) || 0;
            if (page >= totalPages - 1) return;
            try { hapticImpact('light'); } catch (_) {}
            showPage(page + 1);
            pagerEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('fa-IR');
}

export async function service(view, encodedUsername) {
    const username = decodeURIComponent(encodedUsername || '');

    view.innerHTML = `
        <a href="#/services" class="page-back">
            ${icon('chevronLeft', 'class="ico"')}
            <span>بازگشت</span>
        </a>

        <article class="card card-window">
            <header class="card-window-bar">
                <span class="dots"><span></span><span></span><span></span></span>
                <span class="window-url">faoxima/service/${escapeHtml(username)}</span>
            </header>
            <div class="card-body" id="service-body">
                ${skeletonList(4)}
            </div>
        </article>
    `;


    let info = null;
    async function loadAndRender() {
        const $body = view.querySelector('#service-body');
        $body.innerHTML = skeletonList(4);
        try {
            const res = await call('service', { params: { username } });
            info = res?.obj;
        } catch (err) {
            const _msg = String(err.message || '');
            const _isNational = _msg.indexOf('نت ملی') !== -1;
            $body.innerHTML = `
                <div class="empty">
                    ${icon(_isNational ? 'box' : 'alert', 'class="ico ico-xxl ' + (_isNational ? 'ico-muted' : 'ico-warn') + '"')}
                    <h3>${_isNational ? 'وضعیت نت ملی روشن است' : 'دریافت اطلاعات با خطا روبرو شد'}</h3>
                    <p class="muted">${escapeHtml(_msg)}</p>
                </div>
            `;
            return;
        }
        if (!info) {
            $body.innerHTML = `
                <div class="empty">
                    ${icon('info', 'class="ico ico-xxl ico-muted"')}
                    <h3>اطلاعاتی یافت نشد</h3>
                </div>
            `;
            return;
        }
        renderBody($body, info, username, loadAndRender);
    }

    await loadAndRender();
}

function renderBody($body, info, username, reload) {
    setConfigUsername(info && info.username ? info.username : username, info && info.file_prefix);


    const isStock = info.is_stock === true
        || info.total_traffic_gb === null
        || info.total_traffic_gb === undefined;

    const isManual = String(info.panel_type || '') === 'Manualsale';

    const used = Number(info.used_traffic_gb) || 0;
    const total = Number(info.total_traffic_gb) || 0;
    const remaining = Number(info.remaining_traffic_gb) || 0;
    const usedBytes = Number.isFinite(Number(info.used_traffic_bytes)) ? Number(info.used_traffic_bytes) : used * Math.pow(1024, 3);
    const totalBytes = Number.isFinite(Number(info.total_traffic_bytes)) ? Number(info.total_traffic_bytes) : total * Math.pow(1024, 3);
    const remainingBytes = Number.isFinite(Number(info.remaining_traffic_bytes)) ? Number(info.remaining_traffic_bytes) : remaining * Math.pow(1024, 3);
    const isUnlimited = total === 0;
    const percent = trafficPercent(used, total);
    let pctClass = '';
    if (percent >= 85) pctClass = 'is-danger';
    else if (percent >= 60) pctClass = 'is-warn';

    const onlineLabel = info.online_at || '—';
    const expiration = info.expiration_time || '—';
    const lastUpdate = info.last_subscription_update || '—';

    const st = serviceStatusBadge(info.status || info.invoice_status || 'active');
    const statusBadge = st.badge;
    const statusText = st.text;
    const statusIcon = st.icon;

    const disabled = Array.isArray(info.disabled_actions) ? new Set(info.disabled_actions) : new Set();


    const visibleActions = ALL_ACTIONS.filter((a) => !disabled.has(a.id));

    $body.innerHTML = `
        <div class="row-spread">
            <div>
                <div class="muted mono" style="font-size:12px">${escapeHtml(info.username || username)}</div>
                <h2 style="margin:4px 0 0;font-size:20px;font-weight:700">${escapeHtml(info.product_name || '—')}</h2>
            </div>
            <span class="badge ${statusBadge}">
                ${icon(statusIcon, 'class="ico"')}
                ${escapeHtml(statusText)}
            </span>
        </div>

        ${info.has_queued_renewal ? `
        <div class="queued-renewal-notice">
            <span class="dot"></span>
            <span>شما یک سرویس رزرو دارید که پس از پایان سرویس فعلی شما فعال می‌گردد.</span>
        </div>
        ` : ''}

        ${isManual ? '' : (isStock ? `
        <div class="card-section">
            <p class="muted center" style="font-size:13px">
                ${icon('box', 'class="ico ico-leading"')}
                این سرویس از انبار کانفیگ ارسال شده است و امکان مشاهده حجم به صورت آنی وجود دارد
            </p>
        </div>
        ` : `
        <div class="card-section">
            <div class="row-spread">
                <span class="kv-label">${icon('chart')} مصرف ترافیک</span>
                <span class="mono accent">${escapeHtml(fmtBytes(usedBytes))} / ${escapeHtml(fmtBytes(totalBytes))}</span>
            </div>
            <div class="progress"><span class="progress-fill ${pctClass}" style="width:${percent.toFixed(1)}%"></span></div>
            <div class="muted mono" style="font-size:12px">${icon('box', 'class="ico ico-sm"')} باقی‌مانده: ${escapeHtml(isUnlimited ? 'نامحدود' : fmtBytes(remainingBytes))}</div>
        </div>
        `)}

        <div class="card-section">
            <div class="kv">
                <span class="kv-label">${icon('calendar')} تاریخ انقضا</span>
                <span class="kv-value">${escapeHtml(expiration)}</span>
            </div>
            ${isStock ? '' : `
            <div class="kv">
                <span class="kv-label">${icon('online')} آخرین آنلاین</span>
                <span class="kv-value">${escapeHtml(onlineLabel)}</span>
            </div>
            ${info.allowed_users ? `
            <div class="kv">
                <span class="kv-label">${icon('users')} تعداد کاربر مجاز</span>
                <span class="kv-value">${escapeHtml(info.allowed_users)}</span>
            </div>
            ` : ''}
            ${info.ip_summary ? `
            <div class="kv">
                <span class="kv-label">${icon('globe')} تعداد IP مشاهده‌شده</span>
                <span class="kv-value">${escapeHtml(String(info.ip_summary.count))}</span>
            </div>
            ${(info.ip_summary.ips || []).map((ip) => `
            <div class="kv">
                <span class="kv-label">${icon('pin')} IP متصل</span>
                <span class="kv-value mono">${escapeHtml(ip)}</span>
            </div>
            `).join('')}
            ` : ''}
            ${info.hwid_info ? `
            <div class="kv">
                <span class="kv-label">${icon('mobileDevice')} دستگاه‌های متصل</span>
                <span class="kv-value">${escapeHtml(String(info.hwid_info.used))} از ${escapeHtml(String(info.hwid_info.limit))} دستگاه</span>
            </div>
            ${(info.hwid_info.devices || []).map((d) => `
            <div class="kv" style="flex-wrap:wrap;row-gap:4px">
                <span class="kv-label" style="flex:1 1 auto;min-width:0">${icon('mobileDevice')} ${escapeHtml(d.device_os ? (d.device_model ? `${d.device_os} (${d.device_model})` : d.device_os) : 'نامشخص')}</span>
                <span class="kv-value muted mono" style="font-size:12px;flex:0 0 auto;white-space:nowrap">${d.last_seen ? `آخرین اتصال: ${escapeHtml(d.last_seen)}` : '—'}</span>
            </div>
            `).join('')}
            ` : ''}
            <div class="kv">
                <span class="kv-label">${icon('refresh')} آخرین به‌روزرسانی</span>
                <span class="kv-value">${escapeHtml(lastUpdate)}</span>
            </div>
            `}
            ${info.note ? `
            <div class="kv">
                <span class="kv-label">${icon('note')} یادداشت</span>
                <span class="kv-value">${escapeHtml(info.note)}</span>
            </div>` : ''}
        </div>

        <div class="card-section" id="service-config-host"></div>

        ${visibleActions.length > 0 ? `
        <div class="card-section">
            <p class="section-title">${icon('settings')} مدیریت سرویس</p>
            <div class="action-grid" id="action-grid">
                ${visibleActions.map((a) => `
                    <button type="button" class="action-tile ${a.cls}" data-action="${a.id}">
                        ${icon(a.ico, 'class="ico ico-accent"')}
                        <span class="action-label">${escapeHtml(a.label)}</span>
                    </button>
                `).join('')}
            </div>
        </div>` : ''}

        <div id="action-panel-host"></div>
    `;

    const $cfgHost = $body.querySelector('#service-config-host');
    const outputs = Array.isArray(info.service_output) ? info.service_output : [];
    if (outputs.length === 0) {
        $cfgHost.innerHTML = `<div class="muted center mono" style="font-size:12px">کانفیگی برای نمایش وجود ندارد</div>`;
    } else {
        const shownOutputs = disabled.has('config')
            ? outputs.filter((o) => String(o?.type || '').toLowerCase() !== 'config')
            : outputs;
        $cfgHost.innerHTML = shownOutputs.length === 0
            ? `<div class="muted center mono" style="font-size:12px">کانفیگی برای نمایش وجود ندارد</div>`
            : shownOutputs.map((o) => renderOutput({ ...o, isManual: isManual || isStock })).join('');
    }

    bindConfigList($body);
    $body.querySelectorAll('.copy-btn').forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const txt = btn.dataset.copy || '';
            const ok = await copyToClipboard(txt);
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    });

    const $grid = $body.querySelector('#action-grid');
    if ($grid) {
        $grid.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn || btn.disabled) return;
            const actionId = btn.dataset.action;
            hapticImpact('light');
            handleAction(actionId, info, username, $body, reload, btn);
        });
    }
}


function handleAction(actionId, info, username, $body, reload, btn) {
    const $panel = $body.querySelector('#action-panel-host');
    if (!$panel) return;


    $body.querySelectorAll('.action-tile').forEach((t) => t.classList.remove('is-active'));
    btn.classList.add('is-active');

    switch (actionId) {
        case 'config':
            showConfigSection($body, info, username);
            $panel.innerHTML = '';
            return;
        case 'subscription':
            scrollToSubscription($body, info);
            $panel.innerHTML = '';
            return;
        case 'update_info':
            $panel.innerHTML = '';
            toast('در حال بروزرسانی…', 'info', 1200);
            reload();
            return;
        case 'note':
            renderNotePanel($panel, info, username, reload);
            return;
        case 'toggle_status':
            renderToggleStatusPanel($panel, info, username, reload);
            return;
        case 'report_problem':
            renderReportProblemPanel($panel, info, username);
            return;
        case 'renew':
            renderRenewPanel($panel, info, username, reload);
            return;
        case 'extra_time':
            renderExtraPanel($panel, info, username, 'time', reload);
            return;
        case 'extra_volume':
            renderExtraPanel($panel, info, username, 'volume', reload);
            return;
        case 'change_location':
            renderChangeLocationPanel($panel, username, reload);
            return;

        default:
            requestAdminAction($panel, actionId, username);
            return;
    }
}


function closeOpenConfigDetail(btn) {
    try {
        const detail = btn.closest('.cfg-detail');
        if (detail) {
            detail.style.display = 'none';
        }
    } catch (_) {}
}

function scrollToConfig($body) {
    const $cfg = $body.querySelector('#service-config-host');
    if ($cfg) {
        $cfg.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $cfg.classList.add('is-flash');
        setTimeout(() => $cfg.classList.remove('is-flash'), 1200);
    }
}

function scrollToSubscription($body, info) {


    scrollToConfig($body);
    if (!info.subscription_url) {
        toast('لینک اشتراک برای این سرویس موجود نیست', 'warn', 3000);
    }
}


async function showConfigSection($body, info, username) {
    setConfigUsername(username, info && info.file_prefix);
    const $cfg = $body.querySelector('#service-config-host');
    if (!$cfg) return;

    const outputs = Array.isArray(info.service_output) ? info.service_output : [];
    const hasInlineConfig = outputs.some((o) => {
        const t = String(o?.type || '').toLowerCase();
        return t === 'config' || t === 'password' || t === 'file';
    });


    if (hasInlineConfig) {
        scrollToConfig($body);
        return;
    }


    $cfg.innerHTML = `
        <p class="section-title">${icon('config')} کانفیگ‌ها</p>
        <div class="muted center mono" style="font-size:12px;padding:10px 0">در حال دریافت کانفیگ‌ها…</div>
    `;
    scrollToConfig($body);

    try {
        const res = await call('service_configs', { params: { username } });
        const obj = res?.obj || {};
        const configs = Array.isArray(obj.configs) ? obj.configs : [];

        if (configs.length === 0) {
            $cfg.innerHTML = `
                <p class="section-title">${icon('config')} کانفیگ‌ها</p>
                <div class="muted center" style="padding:10px 0">کانفیگی برای نمایش وجود ندارد</div>
            `;
            return;
        }

        const pageSize = 5;
        let page = 1;
        const totalPages = Math.max(1, Math.ceil(configs.length / pageSize));

        $cfg.innerHTML = `
            <p class="section-title">${icon('config')} کانفیگ‌ها (${configs.length})</p>
            <div id="cfg-list-host"></div>
            <div class="pager hidden" id="cfg-pager">
                <button class="btn btn-ghost" id="cfg-prev">${icon('chevronRight', 'class="ico ico-leading"')}قبلی</button>
                <span class="pager-info" id="cfg-pager-info"></span>
                <button class="btn btn-ghost" id="cfg-next">بعدی${icon('chevronLeft', 'class="ico ico-trailing"')}</button>
            </div>
        `;

        const $listHost = $cfg.querySelector('#cfg-list-host');
        const $pager = $cfg.querySelector('#cfg-pager');
        const $pagerInfo = $cfg.querySelector('#cfg-pager-info');
        const $prev = $cfg.querySelector('#cfg-prev');
        const $next = $cfg.querySelector('#cfg-next');

        function renderPage() {
            const offset = (page - 1) * pageSize;
            const pageConfigs = configs.slice(offset, offset + pageSize);
            $listHost.innerHTML = configListHtml(pageConfigs, undefined, offset);

            $listHost.querySelectorAll('.copy-btn').forEach((btn) => {
                btn.addEventListener('click', async (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    const txt = btn.dataset.copy || '';
                    const ok = await copyToClipboard(txt);
                    toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
                });
            });
            bindConfigList($listHost);

            if (totalPages > 1) {
                $pager.classList.remove('hidden');
                $pagerInfo.textContent = `صفحه ${page} از ${totalPages}`;
                $prev.disabled = page <= 1;
                $next.disabled = page >= totalPages;
            } else {
                $pager.classList.add('hidden');
            }
        }

        $prev.addEventListener('click', () => {
            if (page <= 1) return;
            page -= 1;
            hapticImpact('light');
            renderPage();
            scrollToConfig($body);
        });
        $next.addEventListener('click', () => {
            if (page >= totalPages) return;
            page += 1;
            hapticImpact('light');
            renderPage();
            scrollToConfig($body);
        });

        renderPage();
        scrollToConfig($body);
    } catch (err) {
        $cfg.innerHTML = `
            <p class="section-title">${icon('config')} کانفیگ‌ها</p>
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت کانفیگ‌ها</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
    }
}


function renderNotePanel($panel, info, username, reload) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('note')} یادداشت سرویس</p>
            <p class="muted" style="font-size:13px">می‌توانید یک یادداشت کوتاه برای این سرویس ذخیره کنید (حداکثر ۲۰۰ کاراکتر).</p>
            <div class="form-row mt-sm">
                <input id="note-input" type="text" maxlength="200"
                       placeholder="مثلاً: گوشی همسر"
                       value="${escapeHtml(info.note || '')}" />
            </div>
            <div class="row-spread mt-md" style="gap:8px">
                <button id="note-save" type="button" class="btn btn-primary" style="flex:1">
                    ${icon('check', 'class="ico ico-leading"')}
                    <span class="note-save-label">ذخیره یادداشت</span>
                </button>
                <button id="note-clear" type="button" class="btn btn-ghost">
                    ${icon('close', 'class="ico ico-leading"')}
                    <span>پاک کردن</span>
                </button>
            </div>
        </div>
    `;

    const $input = $panel.querySelector('#note-input');
    const $save = $panel.querySelector('#note-save');
    const $clear = $panel.querySelector('#note-clear');
    const $label = $save.querySelector('.note-save-label');

    async function submit(text) {
        const old = $label.textContent;
        $save.disabled = true;
        $clear.disabled = true;
        $label.textContent = 'در حال ذخیره…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'note', username, text },
            });
            const obj = res?.obj || {};
            toast(obj.message || 'انجام شد', 'success', 2500);
            hapticNotify('success');
            await reload();
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ذخیره یادداشت', 'error', 4000);
        } finally {
            $save.disabled = false;
            $clear.disabled = false;
            $label.textContent = old;
        }
    }

    $save.addEventListener('click', () => submit(($input.value || '').trim()));
    $clear.addEventListener('click', () => {
        $input.value = '';
        submit('');
    });
}


function renderToggleStatusPanel($panel, info, username, reload) {
    const isActive = String(info.status || '').toLowerCase() === 'active';
    const willBecome = isActive ? 'غیرفعال' : 'فعال';
    const cta = isActive ? 'تایید و خاموش کردن' : 'تایید و روشن کردن';
    const ico = isActive ? 'power' : 'online';

    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(ico)} تغییر وضعیت سرویس</p>
            <p>وضعیت فعلی سرویس: <span class="accent mono">${escapeHtml(isActive ? 'فعال' : 'غیرفعال')}</span></p>
            <p class="muted" style="font-size:13px">با تایید این عملیات، سرویس به وضعیت <b>${willBecome}</b> تغییر می‌کند.</p>
            <button id="toggle-confirm" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="toggle-label">${escapeHtml(cta)}</span>
            </button>
        </div>
    `;

    const $btn = $panel.querySelector('#toggle-confirm');
    const $label = $btn.querySelector('.toggle-label');
    $btn.addEventListener('click', async () => {
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'toggle_status', username },
            });
            const obj = res?.obj || {};
            toast(obj.message || 'انجام شد', 'success', 2500);
            hapticNotify('success');
            await reload();
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در تغییر وضعیت', 'error', 4000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


function renderReportProblemPanel($panel, info, username) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('alert')} ارسال گزارش اختلال</p>
            <p class="muted" style="font-size:13px">یک پیام کوتاه (اختیاری) برای ادمین بنویسید. ادمین پس از بررسی با شما در تماس خواهد بود.</p>
            <div class="form-row mt-sm">
                <textarea id="report-text" rows="3" maxlength="500"
                          placeholder="مثلاً: سرعت سرویس از ساعت ۲۰ پایین آمده."></textarea>
            </div>
            <button id="report-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('send', 'class="ico ico-leading"')}
                <span class="report-label">ارسال گزارش</span>
            </button>
        </div>
    `;

    const $btn = $panel.querySelector('#report-submit');
    const $txt = $panel.querySelector('#report-text');
    const $label = $btn.querySelector('.report-label');
    $btn.addEventListener('click', async () => {
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال ارسال…';
        try {
            const res = await call('service_simple_action', {
                method: 'POST',
                body: { action: 'report_problem', username, text: ($txt.value || '').trim() },
            });
            const obj = res?.obj || {};
            $panel.innerHTML = `
                <div class="card-section">
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>گزارش ارسال شد</h3>
                        <p class="muted">${escapeHtml(obj.message || 'ادمین به‌زودی بررسی می‌کند.')}</p>
                    </div>
                </div>`;
            hapticNotify('success');
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در ارسال گزارش', 'error', 4000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderRenewPanel($panel, info, username, reload) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('rotate')} تمدید سرویس</p>
            <div id="renew-list">${skeletonList(3)}</div>
            <div id="renew-confirm-host"></div>
            <div id="renew-pay-host"></div>
        </div>
    `;

    let opts = null;
    try {
        const res = await call('service_renew_options', { params: { username } });
        opts = res?.obj || {};
    } catch (err) {
        $panel.querySelector('#renew-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>دریافت پلن‌ها با خطا روبرو شد</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    const products = Array.isArray(opts.products) ? opts.products : [];
    const current = opts.current_plan || null;
    const showPrice = !!opts.show_price;
    const custom = opts.custom || {};
    const $list = $panel.querySelector('#renew-list');

    if (products.length === 0 && !custom.enabled) {
        $list.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>پلنی برای تمدید یافت نشد</h3>
                <p class="muted">برای این پنل، محصول یا تعرفه‌ی حجم دلخواه فعالی تنظیم نشده است. لطفاً با پشتیبانی در ارتباط باشید.</p>
            </div>`;
        return;
    }

    const planRow = (p, cls = '') => {
        const meta = [];
        if (Number(p.volume_gb) > 0) meta.push(`<span>${icon('box','class="ico ico-sm"')} ${escapeHtml(String(p.volume_gb))} گیگابایت</span>`);
        if (Number(p.time_days) > 0) meta.push(`<span>${icon('hourglass','class="ico ico-sm"')} ${escapeHtml(String(p.time_days))} روز</span>`);
        if (p.ip_limit_guard_active) meta.push(`<span>${icon('users','class="ico ico-sm"')} ${escapeHtml(fmtIpLimit(p.ip_limit))}</span>`);
        const priceTag = showPrice
            ? `<span class="amt mono">${fmtNum(p.price)} <span class="muted" style="font-weight:400">تومان</span></span>`
            : `<span class="amt">${icon('select', 'class="ico ico-lg"')}</span>`;
        return `
            <button class="plan ${cls}" data-code="${escapeHtml(p.code)}"
                    data-price="${escapeHtml(String(p.price))}"
                    data-name="${escapeHtml(p.name)}"
                    data-volume="${escapeHtml(String(p.volume_gb))}"
                    data-time="${escapeHtml(String(p.time_days))}">
                <div class="plan-info">
                    <div class="plan-title">${escapeHtml(p.name)}</div>
                    <div class="plan-meta">${meta.join('')}</div>
                </div>
                <div class="plan-price">${priceTag}</div>
            </button>
        `;
    };

    let listHtml = '';
    if (current) listHtml += planRow(current, 'plan-current');
    listHtml += products.filter((p) => !current || p.code !== current.code).map((p) => planRow(p)).join('');

    if (custom.enabled) {
        listHtml += `
            <button class="plan plan-custom" data-code="__custom__">
                <div class="plan-info">
                    <div class="plan-title">${icon('settings')} ساخت پلن سفارشی</div>
                    <div class="plan-meta">
                        <span>${fmtNum(custom.price_per_gb)} تومان/گیگ</span>
                        <span>${fmtNum(custom.price_per_day)} تومان/روز</span>
                    </div>
                </div>
                <div class="plan-price"><span class="amt">${icon('select', 'class="ico ico-lg"')}</span></div>
            </button>`;
    }

    $list.innerHTML = listHtml;

    $list.addEventListener('click', (e) => {
        const btn = e.target.closest('.plan');
        if (!btn) return;
        hapticImpact('light');
        if (btn.dataset.code === '__custom__') {
            renderRenewCustomForm($panel, opts, username, reload);
        } else {
            const choice = {
                code: btn.dataset.code,
                name: btn.dataset.name,
                price: Number(btn.dataset.price || 0),
                volume_gb: Number(btn.dataset.volume || 0),
                time_days: Number(btn.dataset.time || 0),
            };
            renderRenewConfirm($panel, opts, choice, username, reload);
        }
    });

    if (products.length === 0 && custom.enabled) {
        renderRenewCustomForm($panel, opts, username, reload);
    }
}

function renderRenewCustomForm($panel, opts, username, reload) {
    const c = opts.custom || {};
    const volFixed = Number(c.min_volume_gb) === Number(c.max_volume_gb) && Number(c.max_volume_gb) > 0;
    const timeFixed = Number(c.min_time_days) === Number(c.max_time_days) && Number(c.max_time_days) > 0;
    const $host = $panel.querySelector('#renew-confirm-host');
    $panel.querySelector('#renew-pay-host').innerHTML = '';
    $host.innerHTML = `
        <div class="card-section mt-md">
            <p class="section-title">${icon('settings')} پلن سفارشی</p>
            <p class="muted" style="font-size:13px">
                حجم: بین ${fmtNum(c.min_volume_gb)} و ${fmtNum(c.max_volume_gb)} گیگابایت ·
                زمان: ${timeFixed ? `${fmtNum(c.min_time_days)} روز` : `بین ${fmtNum(c.min_time_days)} و ${fmtNum(c.max_time_days)} روز`}
            </p>
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">حجم (گیگابایت)</label>
                <input id="cv-volume" type="number" inputmode="numeric"
                       min="${c.min_volume_gb}" max="${c.max_volume_gb}"
                       value="${volFixed ? c.min_volume_gb : ''}" ${volFixed ? 'readonly' : ''}
                       placeholder="${c.min_volume_gb}-${c.max_volume_gb}" />
            </div>
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">زمان (روز)</label>
                <input id="cv-time" type="number" inputmode="numeric"
                       min="${c.min_time_days}" max="${c.max_time_days}"
                       value="${timeFixed ? c.min_time_days : ''}" ${timeFixed ? 'readonly' : ''}
                       placeholder="${c.min_time_days}-${c.max_time_days}" />
            </div>
            <div class="kv mt-md">
                <span class="kv-label">${icon('coin')} مبلغ تخمینی</span>
                <span class="kv-value mono accent" id="cv-total">۰ تومان</span>
            </div>
            <button id="cv-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="cv-label">ادامه</span>
            </button>
        </div>
    `;

    const $vol = $host.querySelector('#cv-volume');
    const $time = $host.querySelector('#cv-time');
    const $total = $host.querySelector('#cv-total');
    const recompute = () => {
        const v = Number($vol.value || 0);
        const d = Number($time.value || 0);
        const t = (v * Number(c.price_per_gb || 0)) + (d * Number(c.price_per_day || 0));
        $total.textContent = `${fmtNum(t)} تومان`;
    };
    $vol.addEventListener('input', recompute);
    $time.addEventListener('input', recompute);
    recompute();

    const $submit = $host.querySelector('#cv-submit');
    const $label = $submit.querySelector('.cv-label');
    $submit.addEventListener('click', () => {
        const v = parseInt($vol.value || '0', 10);
        const d = parseInt($time.value || '0', 10);
        if (!v || !d) { toast('حجم و زمان را وارد کنید', 'warn'); return; }
        if (v < c.min_volume_gb || v > c.max_volume_gb) {
            toast(`حجم باید بین ${c.min_volume_gb} و ${c.max_volume_gb} گیگ باشد`, 'warn', 4000);
            return;
        }
        if (d < c.min_time_days || d > c.max_time_days) {
            toast(`زمان باید بین ${c.min_time_days} و ${c.max_time_days} روز باشد`, 'warn', 4000);
            return;
        }
        const price = (v * Number(c.price_per_gb || 0)) + (d * Number(c.price_per_day || 0));
        const choice = {
            code: '__custom__',
            name: '⚙️ سرویس دلخواه',
            price,
            volume_gb: v,
            time_days: d,
            custom: { volume_gb: v, time_days: d },
        };
        renderRenewConfirm($panel, opts, choice, username, reload);
    });
}

function renderRenewConfirm($panel, opts, choice, username, reload) {
    const balance = Number(opts.balance || 0);
    const $host = $panel.querySelector('#renew-confirm-host');
    $panel.querySelector('#renew-pay-host').innerHTML = '';

    const meta = [];
    if (choice.volume_gb > 0) meta.push(`${icon('box','class="ico ico-sm"')} حجم: ${fmtNum(choice.volume_gb)} گیگابایت`);
    if (choice.time_days > 0) meta.push(`${icon('hourglass','class="ico ico-sm"')} زمان: ${fmtNum(choice.time_days)} روز`);

    $host.innerHTML = `
        <div class="card-section mt-md">
            <p class="section-title">${icon('rotate')} تأیید تمدید</p>
            <div class="kv">
                <span class="kv-label">پلن انتخابی</span>
                <span class="kv-value">${escapeHtml(choice.name)}</span>
            </div>
            ${meta.map((m) => `
            <div class="kv">
                <span class="kv-label muted" style="font-size:12px">${m.split(' ')[0]}</span>
                <span class="kv-value mono">${escapeHtml(m.replace(/<[^>]+>/g,'').trim())}</span>
            </div>`).join('')}
            <div class="kv">
                <span class="kv-label">${icon('coin')} مبلغ</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700" id="rc-amount">${fmtNum(choice.price)} تومان</span>
            </div>
            <div class="kv">
                <span class="kv-label">${icon('wallet')} موجودی</span>
                <span class="kv-value mono">${fmtNum(balance)} تومان</span>
            </div>

            <div class="form-row mt-sm" id="rc-discount-row" style="display:flex;gap:8px">
                <input id="rc-discount" type="text" inputmode="text" autocomplete="off"
                       placeholder="کد تخفیف (اختیاری)" style="flex:1" />
                <button id="rc-discount-apply" type="button" class="btn btn-secondary">اعمال</button>
            </div>
            <p class="muted" id="rc-discount-msg" style="font-size:12px;display:none"></p>

            <button id="rc-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon('check', 'class="ico ico-leading"')}
                <span class="rc-label">پرداخت و تمدید</span>
            </button>
        </div>
    `;

    let appliedDiscount = '';
    const basePrice = Number(choice.price || 0);
    const $disc = $host.querySelector('#rc-discount');
    const $discApply = $host.querySelector('#rc-discount-apply');
    const $discMsg = $host.querySelector('#rc-discount-msg');
    const $amount = $host.querySelector('#rc-amount');

    if ($discApply && $disc) {
        $discApply.addEventListener('click', async () => {
            const code = String($disc.value || '').trim();
            if (!code) { toast('کد تخفیف را وارد کنید', 'warn'); return; }
            $discApply.disabled = true;
            try {
                const r = await call('discount_validate', {
                    method: 'POST',
                    body: { code, context: 'extend', username, base_price: basePrice },
                });
                const obj = r?.obj || {};
                appliedDiscount = code;
                if (obj.final_price != null && $amount) $amount.textContent = `${fmtNum(obj.final_price)} تومان`;
                $discMsg.style.display = 'block';
                $discMsg.textContent = obj.message || 'کد تخفیف اعمال شد';
                hapticNotify('success');
            } catch (err) {
                appliedDiscount = '';
                $discMsg.style.display = 'block';
                $discMsg.textContent = err.message || 'کد تخفیف نامعتبر است';
                if ($amount) $amount.textContent = `${fmtNum(basePrice)} تومان`;
                hapticNotify('error');
            } finally {
                $discApply.disabled = false;
            }
        });
    }

    const $btn = $host.querySelector('#rc-submit');
    const $label = $btn.querySelector('.rc-label');

    $btn.addEventListener('click', async () => {
        const shownAmount = $host.querySelector('#rc-amount')?.textContent || `${fmtNum(basePrice)} تومان`;
        const sure = await showConfirm(`آیا از تمدید سرویس «${choice.name}» با مبلغ ${shownAmount} مطمئن هستید؟`);
        if (!sure) return;
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const body = { username };
            if (choice.code === '__custom__') {
                body.custom = choice.custom;
            } else {
                body.product_code = choice.code;
            }
            if (appliedDiscount) {
                body.discount_code = appliedDiscount;
            }
            const res = await call('service_renew_confirm', { method: 'POST', body });
            const obj = res?.obj || {};
            if (obj.kind === 'done') {
                hapticNotify('success');
                $host.innerHTML = `
                    <div class="card-section mt-md">
                        <div class="empty">
                            ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                            <h3>سرویس تمدید شد</h3>
                            <p class="muted">${escapeHtml(obj.message || '')}</p>
                            ${obj.cashback ? `<p class="muted mt-sm">🎉 ${fmtNum(obj.cashback)} تومان کش‌بک به حساب شما اضافه شد.</p>` : ''}
                            <p class="muted mono mt-sm" style="font-size:12px">موجودی جدید: ${fmtNum(obj.balance_after)} تومان</p>
                        </div>
                    </div>`;
                $host.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => reload(), 2200);
                return;
            }
            if (obj.kind === 'requires_payment') {
                renderRenewInlinePayment($panel, obj, reload);
                return;
            }
            toast(obj.message || 'انجام شد', 'success');
        } catch (err) {
            hapticNotify('error');
            const msg = err.message || 'خطا در تمدید سرویس';
            toast(msg, 'error', 5000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderRenewInlinePayment($panel, pay, reload) {
    const $host = $panel.querySelector('#renew-pay-host');
    if (!$host) return;
    const amountDue = Number(pay.amount_due || 0);
    const username = String(pay.username || '');

    $host.innerHTML = `
        <div class="card-section mt-md">
            <div class="cart-banner">
                ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                <h3>پرداخت مبلغ کسری</h3>
                <p class="muted center" style="font-size:12px">
                    موجودی شما برای تمدید کافی نیست. مبلغ کسری را با یکی از روش‌های زیر پرداخت کنید؛ پس از تأیید ادمین، تمدید به‌صورت خودکار انجام خواهد شد.
                </p>
            </div>

            <div class="kv mt-md">
                <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
            </div>

            <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
            <div class="payment-methods" id="renew-pay-list">${skeletonList(3)}</div>

            <div id="pay-form-host"></div>
            <div id="pay-result-host"></div>
        </div>
    `;

    let methods = [];
    try {
        const r = await call('payment_methods');
        const data = r?.obj || {};
        methods = Array.isArray(data.methods) ? data.methods : [];
    } catch (err) {
        $host.querySelector('#renew-pay-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    if (!methods.length) {
        $host.querySelector('#renew-pay-list').innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
            </div>`;
        return;
    }

    const $list = $host.querySelector('#renew-pay-list');
    $list.innerHTML = methods.map(renderMethodCard).join('');
    $list.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-method]');
        if (!card) return;
        $list.querySelectorAll('.pay-card').forEach((c) => c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        hapticImpact('light');

        const method = methods.find((m) => m.id === card.dataset.method);
        if (!method) return;

        if (method.kind === 'url' && method.url) {
            const tg = window.Telegram && window.Telegram.WebApp;
            const u = String(method.url || '');
            const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
            if (tg && isTme && typeof tg.openTelegramLink === 'function') {
                tg.openTelegramLink(u);
            } else if (tg && typeof tg.openLink === 'function') {
                tg.openLink(u);
            } else {
                window.open(u, '_blank', 'noopener');
            }
            return;
        }

        const cryptoOfflineIds = ['crypto_offline', 'digitaltron'];
        if (method.kind === 'crypto_offline' || cryptoOfflineIds.includes(method.id)) {
            try {
                const mod = await import('./crypto-offline.js');
                if (mod && typeof mod.renderCryptoOffline === 'function') {
                    mod.renderCryptoOffline($panel, {
                        amount: amountDue,
                        pendingKind: 'renew',
                        pendingOne: `${username}%${pay.order_id}`,
                        onBack: () => reload(),
                    });
                    return;
                }
            } catch (loadErr) {
                console.warn('[service] crypto-offline import failed', loadErr);
            }
            toast('فلوی ارز آفلاین در دسترس نیست', 'error', 4000);
            return;
        }

        try {
            const r = await call('payment_init', {
                method: 'POST',
                body: {
                    method: method.id,
                    amount: amountDue,
                    renew_username: username,
                },
            });
            const obj = r?.obj || {};
            handleInitResult($host, method.id, obj, {
                successText: 'پس از تأیید ادمین، سرویس شما به‌صورت خودکار تمدید خواهد شد.',
                successCta:  { href: '#/services', label: 'بازگشت به سرویس‌ها' },
            });
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        }
    });
}


async function renderExtraPanel($panel, info, username, kind, reload) {
    const titleIcon = kind === 'time' ? 'hourglass' : 'box';
    const titleText = kind === 'time' ? 'خرید زمان اضافه' : 'خرید حجم اضافه';
    const unitLabel = kind === 'time' ? 'روز' : 'گیگابایت';

    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(titleIcon)} ${escapeHtml(titleText)}</p>
            <div id="extra-host">${skeletonList(2)}</div>
        </div>
    `;
    const $host = $panel.querySelector('#extra-host');

    let q = null;
    try {
        const res = await call('service_extra_quote', { params: { username, kind } });
        q = res?.obj || {};
    } catch (err) {
        $host.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    const minA = Number(q.min || 1);
    const maxA = Number(q.max || 9999);
    const ppu = Number(q.price_per_unit || 0);

    $host.innerHTML = `
        <p class="muted" style="font-size:13px">تعرفه هر ${unitLabel}: <span class="accent mono">${fmtNum(ppu)} تومان</span></p>
        <div class="form-row mt-sm">
            <label class="muted" style="font-size:12px">مقدار (${unitLabel})</label>
            <input id="extra-amount" type="number" inputmode="numeric" min="${minA}" max="${maxA}"
                   placeholder="بین ${minA} و ${maxA}" />
        </div>
        <div class="kv mt-sm">
            <span class="kv-label">${icon('coin')} مبلغ کل</span>
            <span class="kv-value mono accent" id="extra-total">۰ تومان</span>
        </div>
        <div class="kv">
            <span class="kv-label">${icon('wallet')} موجودی</span>
            <span class="kv-value mono">${fmtNum(q.balance || 0)} تومان</span>
        </div>
        <div class="form-row mt-sm" id="extra-discount-row" style="display:flex;gap:8px">
            <input id="extra-discount" type="text" autocomplete="off" placeholder="کد تخفیف (اختیاری)" style="flex:1" />
            <button id="extra-discount-apply" type="button" class="btn btn-secondary">اعمال</button>
        </div>
        <p class="muted" id="extra-discount-msg" style="font-size:12px;display:none"></p>
        <button id="extra-submit" type="button" class="btn btn-primary btn-block mt-md">
            ${icon('check', 'class="ico ico-leading"')}
            <span class="extra-label">پرداخت و افزودن</span>
        </button>
        <div id="extra-pay-host"></div>
    `;

    const $amount = $host.querySelector('#extra-amount');
    const $total = $host.querySelector('#extra-total');
    let appliedDiscount = '';
    let discountedTotal = null;
    const recompute = () => {
        const a = Number($amount.value || 0);
        appliedDiscount = '';
        discountedTotal = null;
        const $dmsg = $host.querySelector('#extra-discount-msg');
        if ($dmsg) $dmsg.style.display = 'none';
        $total.textContent = `${fmtNum(a * ppu)} تومان`;
    };
    $amount.addEventListener('input', recompute);

    const $disc = $host.querySelector('#extra-discount');
    const $discApply = $host.querySelector('#extra-discount-apply');
    const $discMsg = $host.querySelector('#extra-discount-msg');

    if ($discApply && $disc) {
        $discApply.addEventListener('click', async () => {
            const code = String($disc.value || '').trim();
            const a = parseInt($amount.value || '0', 10);
            if (!code) { toast('کد تخفیف را وارد کنید', 'warn'); return; }
            if (!a || a < minA || a > maxA) { toast(`ابتدا مقدار معتبر وارد کنید`, 'warn'); return; }
            $discApply.disabled = true;
            try {
                const r = await call('discount_validate', {
                    method: 'POST',
                    body: { code, context: kind === 'time' ? 'time' : 'volume', username, base_price: a * ppu },
                });
                const obj = r?.obj || {};
                appliedDiscount = code;
                if (obj.final_price != null) {
                    discountedTotal = Number(obj.final_price);
                    $total.textContent = `${fmtNum(discountedTotal)} تومان`;
                }
                $discMsg.style.display = 'block';
                $discMsg.textContent = obj.message || 'کد تخفیف اعمال شد';
                hapticNotify('success');
            } catch (err) {
                appliedDiscount = '';
                discountedTotal = null;
                $discMsg.style.display = 'block';
                $discMsg.textContent = err.message || 'کد تخفیف نامعتبر است';
                $total.textContent = `${fmtNum(a * ppu)} تومان`;
                hapticNotify('error');
            } finally {
                $discApply.disabled = false;
            }
        });
    }

    const $btn = $host.querySelector('#extra-submit');
    const $label = $btn.querySelector('.extra-label');
    $btn.addEventListener('click', async () => {
        const a = parseInt($amount.value || '0', 10);
        if (!a || a < minA || a > maxA) {
            toast(`مقدار باید بین ${minA} و ${maxA} ${unitLabel} باشد`, 'warn', 4000);
            return;
        }
        const sure = await showConfirm(`آیا از افزودن ${fmtNum(a)} ${unitLabel} با مبلغ ${$total.textContent} مطمئن هستید؟`);
        if (!sure) return;
        const old = $label.textContent;
        $btn.disabled = true;
        $label.textContent = 'در حال انجام…';
        try {
            const res = await call('service_extra_confirm', {
                method: 'POST',
                body: appliedDiscount
                    ? { username, kind, amount: a, discount_code: appliedDiscount }
                    : { username, kind, amount: a },
            });
            const obj = res?.obj || {};
            if (obj.kind === 'done') {
                hapticNotify('success');
                $host.innerHTML = `
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>${escapeHtml(obj.message || 'انجام شد')}</h3>
                        <p class="muted mono mt-sm" style="font-size:12px">موجودی جدید: ${fmtNum(obj.balance_after || 0)} تومان</p>
                    </div>`;
                $host.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => reload(), 2200);
                return;
            }
            if (obj.kind === 'requires_payment') {
                renderExtraInlinePayment($host.querySelector('#extra-pay-host'), obj, reload, $panel, kind);
                return;
            }
            toast(obj.message || 'انجام شد', 'success');
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در افزودن', 'error', 5000);
            $btn.disabled = false;
            $label.textContent = old;
        }
    });
}


async function renderExtraInlinePayment($host, pay, reload, $panel, kind) {
    const amountDue = Number(pay.amount_due || 0);
    const username = String(pay.username || '');

    $host.innerHTML = `
        <div class="card-section mt-md">
            <div class="cart-banner">
                ${icon('wallet', 'class="ico ico-xxl ico-accent"')}
                <h3>پرداخت مبلغ کسری</h3>
                <p class="muted center" style="font-size:12px">
                    موجودی کیف پول شما کافی نیست. مبلغ کسری را پرداخت کنید؛ پس از تأیید ادمین، خرید به‌صورت خودکار انجام خواهد شد.
                </p>
            </div>
            <div class="kv mt-md">
                <span class="kv-label">${icon('alert')} مبلغ قابل پرداخت</span>
                <span class="kv-value mono accent" style="font-size:18px;font-weight:700">${fmtNum(amountDue)} تومان</span>
            </div>
            <p class="section-title mt-md">${icon('creditCard')} انتخاب روش پرداخت</p>
            <div class="payment-methods" id="extra-pay-list">${skeletonList(3)}</div>
            <div id="pay-form-host"></div>
            <div id="pay-result-host"></div>
        </div>
    `;

    let methods = [];
    try {
        const r = await call('payment_methods');
        const data = r?.obj || {};
        methods = Array.isArray(data.methods) ? data.methods : [];
    } catch (err) {
        $host.querySelector('#extra-pay-list').innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت روش‌های پرداخت</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>`;
        return;
    }

    if (!methods.length) {
        $host.querySelector('#extra-pay-list').innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>روش پرداختی فعال نیست</h3>
            </div>`;
        return;
    }

    const $list = $host.querySelector('#extra-pay-list');
    $list.innerHTML = methods.map(renderMethodCard).join('');
    $list.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-method]');
        if (!card) return;
        $list.querySelectorAll('.pay-card').forEach((c) => c.classList.remove('is-selected'));
        card.classList.add('is-selected');
        hapticImpact('light');
        const method = methods.find((m) => m.id === card.dataset.method);
        if (!method) return;

        if (method.kind === 'url' && method.url) {
            const tg = window.Telegram && window.Telegram.WebApp;
            const u = String(method.url || '');
            const isTme = u.startsWith('https://t.me/') || u.startsWith('http://t.me/');
            if (tg && isTme && typeof tg.openTelegramLink === 'function') {
                tg.openTelegramLink(u);
            } else if (tg && typeof tg.openLink === 'function') {
                tg.openLink(u);
            } else {
                window.open(u, '_blank', 'noopener');
            }
            return;
        }

        const cryptoOfflineIds = ['crypto_offline', 'digitaltron'];
        if ((method.kind === 'crypto_offline' || cryptoOfflineIds.includes(method.id)) && $panel) {
            try {
                const mod = await import('./crypto-offline.js');
                if (mod && typeof mod.renderCryptoOffline === 'function') {
                    mod.renderCryptoOffline($panel, {
                        amount: amountDue,
                        pendingKind: kind === 'time' ? 'extra_time' : 'extra_volume',
                        pendingOne: `${username}%${pay.extra_amount}`,
                        onBack: () => reload(),
                    });
                    return;
                }
            } catch (loadErr) {
                console.warn('[service] crypto-offline import failed', loadErr);
            }
            toast('فلوی ارز آفلاین در دسترس نیست', 'error', 4000);
            return;
        }

        try {
            const r = await call('payment_init', {
                method: 'POST',
                body: {
                    method: method.id,
                    amount: amountDue,
                    renew_username: username,
                },
            });
            const obj = r?.obj || {};
            handleInitResult($host, method.id, obj, {
                successText: 'پس از تأیید ادمین، خرید شما به‌صورت خودکار اعمال خواهد شد.',
                successCta:  { href: '#/services', label: 'بازگشت به سرویس‌ها' },
            });
        } catch (err) {
            hapticNotify('error');
            toast(err.message || 'خطا در آغاز پرداخت', 'error', 4000);
        }
    });
}


async function renderChangeLocationPanel($panel, username, reload) {
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon('pin')} تغییر موقعیت سرویس</p>
            <p class="muted" style="font-size:13px">موقعیت جدید را انتخاب کنید. این عملیات بلافاصله انجام می‌شود.</p>
            <div id="cl-list" class="list mt-md">${skeletonList(3)}</div>
        </div>
    `;
    const $list = $panel.querySelector('#cl-list');

    let data = null;
    try {
        const res = await call('service_locations', { params: { username } });
        data = res?.obj || {};
    } catch (err) {
        $list.innerHTML = `<div class="empty">${icon('alert', 'class="ico ico-xxl ico-warn"')}<p class="muted">${escapeHtml(err.message || '')}</p></div>`;
        return;
    }

    const panels = Array.isArray(data.panels) ? data.panels : [];
    if (!panels.length) {
        $list.innerHTML = `<div class="empty">${icon('info', 'class="ico ico-xxl ico-muted"')}<h3>موقعیت دیگری موجود نیست</h3></div>`;
        return;
    }

    const isFree = !!data.is_free;
    $list.innerHTML = panels.map((p) => `
        <button class="plan" data-id="${escapeHtml(p.id)}" data-name="${escapeHtml(p.name)}">
            <div class="plan-info"><div class="plan-title">${escapeHtml(p.name)}</div></div>
            <div class="plan-price"><span class="amt">${isFree ? 'رایگان' : escapeHtml(fmtPrice(p.price))}</span></div>
        </button>
    `).join('');

    $list.querySelectorAll('.plan').forEach((btn) => {
        btn.addEventListener('click', async () => {
            hapticImpact('light');
            const targetName = btn.dataset.name;
            const ok = await showConfirm(`موقعیت سرویس به «${targetName}» تغییر کند؟`);
            if (!ok) return;

            $list.querySelectorAll('.plan').forEach((b) => { b.disabled = true; });
            btn.innerHTML = `<div class="plan-info"><div class="plan-title">در حال انتقال…</div></div>`;

            try {
                const res = await call('service_action', {
                    method: 'POST',
                    body: { action: 'change_location', username, payload: { target_panel: btn.dataset.id } },
                });
                const obj = res?.obj || {};
                hapticNotify('success');
                toast(obj.message || 'موقعیت سرویس تغییر کرد', 'success', 3500);
                reload();
            } catch (err) {
                hapticNotify('error');
                toast(err.message || 'خطا در تغییر موقعیت', 'error', 4500);
                $list.querySelectorAll('.plan').forEach((b) => { b.disabled = false; });
                renderChangeLocationPanel($panel, username, reload);
            }
        });
    });
}

function requestAdminAction($panel, actionId, username) {
    const meta = ACTION_REQUEST_META[actionId];
    if (!meta) {
        toast('عملیات نامعتبر است', 'error');
        return;
    }
    if (meta.fields && meta.fields.length > 0) {
        renderAdminRequestForm($panel, actionId, username, meta);
    } else {
        submitAdminRequest($panel, actionId, username, {});
    }
}

const ACTION_REQUEST_META = {
    transfer: {
        title:   '↪️ انتقال سرویس به کاربر دیگر',
        intro:   'شناسهٔ عددی کاربری که می‌خواهید سرویس به او منتقل شود را وارد کنید. ادمین درخواست را بررسی خواهد کرد.',
        ico:     'transfer',
        fields: [
            { id: 'target_user_id', label: 'شناسهٔ عددی کاربر مقصد', type: 'tel', placeholder: 'مثلاً 123456789', required: true,
              help: 'این شناسه را می‌توانید از پروفایل کاربر در تلگرام پیدا کنید.' },
        ],
    },
    refund: {
        title:   'درخواست بازگشت وجه',
        intro:   'با ارسال این درخواست، ادمین وضعیت سرویس و امکان بازگشت وجه را بررسی می‌کند. در صورت نیاز، توضیحات کوتاهی اضافه کنید.',
        ico:     'diamond',
        fields: [
            { id: 'reason', label: 'توضیحات (اختیاری)', type: 'textarea', placeholder: 'مثلاً: سرویس در دو روز اخیر کار نمی‌کند', required: false, maxlength: 500 },
        ],
    },
    changelink: {
        title:   'تغییر لینک',
        intro:   'با تایید، لینک کانفیگ شما فوراً تغییر می‌کند. توجه: لینک قبلی پس از تغییر دیگر معتبر نخواهد بود.',
        ico:     'link',
        fields: [],
        submitLabel: 'تغییر لینک',
        immediate: true,
    },
};

function renderAdminRequestForm($panel, actionId, username, meta) {
    const fieldsHtml = meta.fields.map((f) => {
        if (f.type === 'textarea') {
            return `
                <div class="form-row mt-sm">
                    <label class="muted" style="font-size:12px">${escapeHtml(f.label)}</label>
                    <textarea data-field="${escapeHtml(f.id)}" rows="3" maxlength="${f.maxlength || 500}"
                              placeholder="${escapeHtml(f.placeholder || '')}"></textarea>
                    ${f.help ? `<p class="muted" style="font-size:11px">${escapeHtml(f.help)}</p>` : ''}
                </div>`;
        }
        return `
            <div class="form-row mt-sm">
                <label class="muted" style="font-size:12px">${escapeHtml(f.label)}</label>
                <input data-field="${escapeHtml(f.id)}" type="${escapeHtml(f.type || 'text')}"
                       inputmode="${f.type === 'tel' ? 'numeric' : 'text'}"
                       maxlength="${f.maxlength || 100}"
                       placeholder="${escapeHtml(f.placeholder || '')}" />
                ${f.help ? `<p class="muted" style="font-size:11px">${escapeHtml(f.help)}</p>` : ''}
            </div>`;
    }).join('');

    const submitLabel = meta.submitLabel || 'ارسال درخواست برای ادمین';
    const submitIcon = meta.immediate ? 'refresh' : 'send';
    $panel.innerHTML = `
        <div class="card-section">
            <p class="section-title">${icon(meta.ico)} ${escapeHtml(meta.title)}</p>
            <p class="muted" style="font-size:13px">${escapeHtml(meta.intro)}</p>
            ${fieldsHtml}
            <button id="ar-submit" type="button" class="btn btn-primary btn-block mt-md">
                ${icon(submitIcon, 'class="ico ico-leading"')}
                <span class="ar-label">${escapeHtml(submitLabel)}</span>
            </button>
        </div>
    `;

    const $submit = $panel.querySelector('#ar-submit');
    const $label = $submit.querySelector('.ar-label');
    $submit.addEventListener('click', async () => {
        const payload = {};
        let firstError = null;
        for (const f of meta.fields) {
            const $el = $panel.querySelector(`[data-field="${f.id}"]`);
            const val = ($el?.value || '').trim();
            if (f.required && val === '') {
                firstError = firstError || `لطفاً «${f.label}» را وارد کنید`;
                continue;
            }
            if (f.type === 'tel' && val !== '' && !/^\d+$/.test(val)) {
                firstError = firstError || `«${f.label}» باید عدد باشد`;
                continue;
            }
            if (val !== '') payload[f.id] = val;
        }
        if (firstError) {
            toast(firstError, 'warn', 3500);
            return;
        }
        if (meta.immediate) {
            const ok = await showConfirm(meta.intro);
            if (!ok) return;
        }
        const old = $label.textContent;
        $submit.disabled = true;
        $label.textContent = meta.immediate ? 'در حال انجام…' : 'در حال ارسال…';
        submitAdminRequest($panel, actionId, username, payload).catch(() => {
            $submit.disabled = false;
            $label.textContent = old;
        });
    });
}

async function submitAdminRequest($panel, actionId, username, payload) {
    if (!$panel.querySelector('#ar-submit')) {
        $panel.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('send', 'class="ico ico-xxl ico-muted"')}
                    <p class="muted">در حال پردازش…</p>
                </div>
            </div>
        `;
    }
    try {
        const res = await call('service_action', {
            method: 'POST',
            body: { action: actionId, username, payload },
        });
        const obj = res?.obj || {};
        hapticNotify('success');


        if (obj.kind === 'changelink_done') {
            const newLink = String(obj.subscription_url || '');
            $panel.innerHTML = `
                <div class="card-section">
                    <div class="empty">
                        ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                        <h3>لینک سرویس با موفقیت تغییر کرد</h3>
                        <p class="muted">${escapeHtml(obj.message || 'لینک قبلی دیگر معتبر نیست.')}</p>
                    </div>
                    ${newLink ? `
                        <div class="sub-link-box mt-md">
                            <div class="label">${icon('link', 'class="ico ico-leading"')} <span>لینک جدید اشتراک</span></div>
                            <div class="sub-link-value mono" id="new-sub-link" title="برای کپی کلیک کنید">${escapeHtml(newLink)}</div>
                        </div>
                        <button id="copy-new-link" type="button" class="btn btn-primary btn-block mt-sm">
                            ${icon('copy', 'class="ico ico-leading"')}
                            <span>کپی لینک جدید</span>
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-ghost btn-block mt-sm" onclick="location.reload()">
                        ${icon('refresh', 'class="ico ico-leading"')}
                        <span>بازنشانی سرویس</span>
                    </button>
                </div>`;
            const $copyBtn = $panel.querySelector('#copy-new-link');
            const $linkVal = $panel.querySelector('#new-sub-link');
            const doCopy = async () => {
                const ok = await copyToClipboard(newLink);
                hapticImpact('light');
                toast(ok ? 'لینک کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
            };
            if ($copyBtn) $copyBtn.addEventListener('click', doCopy);
            if ($linkVal) $linkVal.addEventListener('click', doCopy);
            return;
        }


        $panel.innerHTML = `
            <div class="card-section">
                <div class="empty">
                    ${icon('checkCircle', 'class="ico ico-xxl ico-success"')}
                    <h3>درخواست شما برای ادمین ارسال شد</h3>
                    <p class="muted">${escapeHtml(obj.message || 'ادمین پس از بررسی با شما تماس می‌گیرد.')}</p>
                </div>
            </div>`;
    } catch (err) {
        hapticNotify('error');
        toast(err.message || 'خطا در ارسال درخواست', 'error', 4500);
        throw err;
    }
}


export function bindOutputCopy($scope) {
    if (!$scope) return;
    $scope.querySelectorAll('.copy-btn').forEach((btn) => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const txt = btn.dataset.copy || '';
            const ok = await copyToClipboard(txt);
            toast(ok ? 'کپی شد' : 'خطا در کپی', ok ? 'success' : 'error');
        });
    });
}

export function renderServiceOutputs($host, outputs, opts) {
    if (opts && opts.username) setConfigUsername(opts.username, opts.filePrefix || '');
    if (!$host) return false;
    const list = Array.isArray(outputs) ? outputs : [];
    const isManual = !!(opts && opts.isManual);
    const html = list.map((o) => renderOutput({ ...o, isManual })).filter(Boolean).join('');
    if (html === '') return false;
    $host.innerHTML = html;
    bindConfigList($host);
    bindOutputCopy($host);
    return true;
}

export function renderOutput(o) {
    const type = (o?.type || '').toLowerCase();

    if (type === 'link') {
        const url = String(o.value || '');
        if (url === '') return '';
        const showQr = (!o.isManual && isQrEnabled());
        return `
            <div class="sub-card">
                <p class="section-title">${icon('book')} لینک اشتراک</p>
                <div class="cfg-list" style="display:flex;flex-direction:column;gap:10px">
                    <div class="cfg-item">
                        <button type="button" class="cfg-toggle btn btn-ghost btn-block" data-i="0" style="display:flex;justify-content:space-between;align-items:center;text-align:start;gap:8px">
                            <span>${icon('book', 'class="ico ico-sm"')} لینک اشتراک</span>
                            <span class="muted" style="font-size:12px;white-space:nowrap">${icon('download', 'class="ico ico-sm"')} دریافت لینک اشتراک</span>
                        </button>
                        <div class="cfg-detail" data-i="0" style="display:none;margin:8px 0 12px">
                            ${showQr ? (qrPayloadBytes(url) > QR_MAX_BYTES ? qrUnavailableHtml() : `<div class="qr-block"><img class="qr-img" data-qr="${escapeHtml(url)}" alt="QR" /></div>`) : ''}
                            <div class="codeblock">${escapeHtml(url)}<button class="copy-btn" data-copy="${escapeHtml(url)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    if (type === 'config') {
        const items = Array.isArray(o.value) ? o.value : String(o.value || '').split('\n').filter(Boolean);
        if (items.length === 0) return '';
        return `
            <div class="sub-card">
                <p class="section-title">${icon('config')} کانفیگ‌ها (${items.length})</p>
                ${configListPaginatedHtml(items, o.isManual)}
            </div>
        `;
    }

    if (type === 'text') {
        const val = String(o.value || '');
        if (val === '') return '';
        return `
            <div class="sub-card">
                <p class="section-title">${icon('config')} کانفیگ</p>
                <div class="cfg-list" style="display:flex;flex-direction:column;gap:10px">
                    <div class="cfg-item">
                        <button type="button" class="cfg-toggle btn btn-ghost btn-block" data-i="0" style="display:flex;justify-content:space-between;align-items:center;text-align:start;gap:8px">
                            <span>${icon('config', 'class="ico ico-sm"')} کانفیگ</span>
                            <span class="muted" style="font-size:12px;white-space:nowrap">${icon('download', 'class="ico ico-sm"')} دریافت کانفیگ</span>
                        </button>
                        <div class="cfg-detail" data-i="0" style="display:none;margin:8px 0 12px">
                            <div class="codeblock codeblock--plain" style="white-space:pre-wrap;word-break:break-all">${escapeHtml(val)}<button class="copy-btn" data-copy="${escapeHtml(val)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }


    if (type === 'file') {
        const fileValue = String(o.value || '');
        const fileName = configDownloadFilename('', String(o.filename || 'config.conf'), /\.conf$/i.test(String(o.filename || '')));
        if (fileValue === '') return '';
        const isDataUri = /^data:/i.test(fileValue);
        const isRemoteUrl = /^(https?|blob):/i.test(fileValue);
        const fileText = isDataUri ? decodeDataUriText(fileValue) : (isRemoteUrl ? null : fileValue);
        const downloadId = isRemoteUrl ? storeDownloadSource(fileValue) : '';
        return `
            <div class="sub-card">
                <p class="section-title">${icon('fileText')} فایل کانفیگ</p>
                ${isRemoteUrl
                    ? `<button type="button" class="btn btn-ghost btn-block" data-url-download data-url-name="${escapeHtml(fileName)}" data-download-id="${downloadId}">${icon('download', 'class="ico ico-leading"')} دانلود ${escapeHtml(fileName)}</button>`
                    : `<button type="button" class="btn btn-ghost btn-block" data-file-download data-file-name="${escapeHtml(fileName)}" data-file-user="${escapeHtml(__cfgUsername)}" ${isDataUri ? `data-file-src="${escapeHtml(fileValue)}"` : `data-file-text="${escapeHtml(fileValue)}"`}>${icon('download', 'class="ico ico-leading"')} دانلود ${escapeHtml(fileName)}</button>
                       ${fileText ? `<button type="button" class="btn btn-ghost btn-block copy-btn" data-copy="${escapeHtml(fileText)}" style="margin-top:6px">${icon('copy', 'class="ico ico-sm"')} کپی متن فایل</button>` : ''}`}
            </div>
        `;
    }

    if (type === 'password') {
        const pw = String(o.value || '');
        if (pw === '') return '';
        const showQr = isQrEnabled();
        return `
            <div class="sub-card">
                <p class="section-title">${icon('config')} رمز عبور</p>
                <div class="cfg-list" style="display:flex;flex-direction:column;gap:10px">
                    <div class="cfg-item">
                        <button type="button" class="cfg-toggle btn btn-ghost btn-block" data-i="0" style="display:flex;justify-content:space-between;align-items:center;text-align:start;gap:8px">
                            <span>${icon('config', 'class="ico ico-sm"')} رمز عبور</span>
                            <span class="muted" style="font-size:12px;white-space:nowrap">${icon('download', 'class="ico ico-sm"')} دریافت رمز عبور</span>
                        </button>
                        <div class="cfg-detail" data-i="0" style="display:none;margin:8px 0 12px">
                            ${showQr ? (qrPayloadBytes(pw) > QR_MAX_BYTES ? qrUnavailableHtml() : `<div class="qr-block"><img class="qr-img" data-qr="${escapeHtml(pw)}" alt="QR" /></div>`) : ''}
                            <div class="codeblock">${escapeHtml(pw)}<button class="copy-btn" data-copy="${escapeHtml(pw)}">${icon('copy', 'class="ico ico-sm"')} کپی</button></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    return '';
}
