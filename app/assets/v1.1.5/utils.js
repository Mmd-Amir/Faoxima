export function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(reader.error || new Error('media read failed'));
        reader.readAsDataURL(blob);
    });
}

/** Render an HTML template literal helper that auto-escapes interpolations. */
export function html(strings, ...values) {
    let out = '';
    strings.forEach((s, i) => {
        out += s;
        if (i < values.length) {
            const v = values[i];
            if (v === null || v === undefined) return;
            if (Array.isArray(v)) {
                out += v.join('');
            } else if (v && v._raw) {
                out += v._raw;
            } else {
                out += escapeHtml(v);
            }
        }
    });
    return out;
}

/** Mark a string as already-trusted HTML (used inside html`` templates). */
export function raw(value) {
    return { _raw: String(value ?? '') };
}

/** Format a price as Persian-style "1,234,567 تومان". */
export function fmtPrice(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('en-US') + ' تومان';
}

/** Format a number with thousands separators, no unit. */
export function fmtNumber(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value ?? '');
    return n.toLocaleString('en-US');
}

/** Format GB with up to 2 decimal places. */
export function fmtGb(value) {
    const n = Number(value);
    if (n === 0) return 'نامحدود';
    if (!Number.isFinite(n)) return '—';
    return `${n.toFixed(2)} GB`;
}

export function fmtBytes(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    if (n === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const power = Math.min(Math.floor(Math.log(Math.abs(n)) / Math.log(1024)), units.length - 1);
    const amount = n / Math.pow(1024, Math.max(0, power));
    const digits = Number.isInteger(amount) ? 0 : 2;
    return `${amount.toFixed(digits)} ${units[Math.max(0, power)]}`;
}

/** Format days. 0 = unlimited. */
export function fmtDays(value) {
    const n = Number(value);
    if (n === 0) return 'نامحدود';
    if (!Number.isFinite(n)) return '—';
    return `${n} روز`;
}

/** Format allowed simultaneous users. 0 = unlimited. */
export function fmtIpLimit(value) {
    const n = Number(value);
    if (!Number.isFinite(n) || n <= 0) return 'نامحدود';
    return `${n} کاربر`;
}

/**
 * Resolves the "allowed users" label to show, giving the symbolic (admin-set,
 * panel-agnostic) "Limiet" priority over the real 3x-ui/fail2ban ip_limit.
 * Returns null when neither applies.
 */
export function fmtSymbolicLimit(plan) {
    if (plan && plan.symbolic_limit_enabled) {
        const n = Number(plan.symbolic_limit_users);
        return Number.isFinite(n) && n > 0 ? `${n} کاربر` : null;
    }
    return null;
}

/** Toast notification. */
export function toast(message, kind = 'info', timeout = 3000) {
    const host = document.getElementById('toast-host');
    if (!host) return;
    const el = document.createElement('div');
    el.className = `toast is-${kind}`;
    el.textContent = String(message);
    host.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
        el.style.transition = 'all 0.25s ease';
        setTimeout(() => el.remove(), 250);
    }, timeout);
}

/** Render a skeleton placeholder for `count` rows. */
export function skeletonList(count = 4) {
    return Array.from({ length: count }, () => `<div class="skeleton skeleton-row"></div>`).join('');
}

/** Render an empty state.
 *  Pass an icon name (from icons.js) as the third arg to customise the
 *  icon shown above the title; defaults to a simple info circle.
 */
export function emptyState(title, subtitle = '', iconName = 'info') {
    // Lazy import the icon helper to avoid a circular dep.
    let svg = '';
    try {
        // Inline a simple info svg as fallback (matches icons.js path).
        svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="ico ico-xxl ico-muted"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8v.01"/></svg>';
    } catch (_) {}
    return `
        <div class="empty">
            ${svg}
            <h3>${escapeHtml(title)}</h3>
            ${subtitle ? `<p class="muted">${escapeHtml(subtitle)}</p>` : ''}
        </div>
    `;
}

/** Copy text to the clipboard with graceful fallback. */
export async function copyToClipboard(text) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch (_) { /* ignore */ }
    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        ta.remove();
        return ok;
    } catch (_) {
        return false;
    }
}

/** Wire up data-route links so they integrate with the hash router. */
export function wireRouteLinks(root) {
    const links = root.querySelectorAll('[data-route]');
    links.forEach((a) => {
        // Plain hash navigation works natively, nothing else to do.
    });
}

/** Find traffic progress percentage with cap. */
export function trafficPercent(used, total) {
    const u = Number(used);
    const t = Number(total);
    if (!Number.isFinite(t) || t <= 0) return 0;
    return Math.min(100, Math.max(0, (u / t) * 100));
}

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

function normalizeDigits(value) {
    let out = '';
    for (const ch of String(value ?? '')) {
        const p = PERSIAN_DIGITS.indexOf(ch);
        const a = ARABIC_DIGITS.indexOf(ch);
        out += p !== -1 ? String(p) : (a !== -1 ? String(a) : ch);
    }
    return out;
}

/** Strip everything but digits, normalizing Persian/Arabic digits first. */
export function rawDigits(value) {
    return normalizeDigits(value).replace(/[^\d]/g, '');
}

/** Group a digit string with thousands separators. */
export function groupDigits(digits) {
    return String(digits ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** Format any raw value (possibly Persian digits/commas) into a comma-grouped digit string. */
export function formatAmount(value) {
    return groupDigits(rawDigits(value));
}

/**
 * Wire a text input for live comma-formatted amount entry: normalizes Persian/Arabic
 * digits, groups with commas as the user types, and preserves caret position.
 */
export function wireAmountInput(el) {
    if (!el || el.dataset.fxMoneyWired) return;
    el.dataset.fxMoneyWired = '1';
    el.type = 'text';
    el.setAttribute('inputmode', 'numeric');
    if (el.value) el.value = formatAmount(el.value);

    el.addEventListener('input', () => {
        const before = el.value;
        const caret = el.selectionStart == null ? before.length : el.selectionStart;
        const digitsBeforeCaret = rawDigits(before.slice(0, caret)).length;

        const formatted = formatAmount(before);
        el.value = formatted;

        let pos = 0, seen = 0;
        while (pos < formatted.length && seen < digitsBeforeCaret) {
            if (/\d/.test(formatted[pos])) seen++;
            pos++;
        }
        try { el.setSelectionRange(pos, pos); } catch (_) {}
    });
}

/** Read the raw numeric value (commas stripped, digits normalized) from a wired amount input. */
export function amountValue(el) {
    return rawDigits(el ? el.value : '');
}

export function serviceStatusBadge(rawStatus) {
    const s = String(rawStatus || '').toLowerCase();
    if (s === 'expired' || s === 'end_of_time') {
        return { badge: 'is-warn', text: 'منقضی', icon: 'warning' };
    }
    if (s === 'limited' || s === 'end_of_volume' || s.includes('limit')) {
        return { badge: 'is-warn', text: 'پایان حجم', icon: 'box' };
    }
    if (s === 'sendedwarn') {
        return { badge: 'is-warn', text: 'هشدار', icon: 'warning' };
    }
    if (s === 'on_hold' || s === 'send_on_hold') {
        return { badge: 'is-warn', text: 'متوقف', icon: 'powerOff' };
    }
    if (s.includes('disabled') || s === 'disablebyadmin' || s === 'disabledn' || s === 'deactivev') {
        return { badge: 'is-danger', text: 'غیرفعال', icon: 'powerOff' };
    }
    return { badge: 'is-active', text: 'فعال', icon: 'online' };
}
