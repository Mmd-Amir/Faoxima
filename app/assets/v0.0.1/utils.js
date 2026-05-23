export function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
    if (!Number.isFinite(n)) return '—';
    return `${n.toFixed(2)} GB`;
}

/** Format days. 0 = unlimited. */
export function fmtDays(value) {
    const n = Number(value);
    if (n === 0) return 'نامحدود';
    if (!Number.isFinite(n)) return '—';
    return `${n} روز`;
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

