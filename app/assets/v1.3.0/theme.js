const THEMES = [
    { key: 'gold',   color: '#d4b878', bright: '#e2c98c' },
    { key: 'red',    color: '#e57373', bright: '#ef9a9a' },
    { key: 'blue',   color: '#64a8e8', bright: '#82bdf3' },
    { key: 'purple', color: '#7c5cff', bright: '#a98bff' },
    { key: 'yellow', color: '#f4d35e', bright: '#f8e285' },
    { key: 'green',  color: '#7fc987', bright: '#9bd5a3' },
    { key: 'orange', color: '#f0a868', bright: '#f5be8b' },
];

function normalizeHex(s) {
    const m = /^#?([0-9a-fA-F]{6})$/.exec(String(s == null ? '' : s).trim());
    return m ? ('#' + m[1].toLowerCase()) : null;
}
function hexToRgba(hex, alpha) {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}
// Soft-rgba convention (alpha 0.32) used for an icon's background box.
function softBackgroundFor(hex, alpha = 0.32) {
    return hexToRgba(hex, alpha);
}
function hexDarken(hex, f) {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    return `rgb(${Math.round(((n >> 16) & 255) * f)}, ${Math.round(((n >> 8) & 255) * f)}, ${Math.round((n & 255) * f)})`;
}
function hexLighten(hex, f) {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    let r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    r = Math.round(r + (255 - r) * f);
    g = Math.round(g + (255 - g) * f);
    b = Math.round(b + (255 - b) * f);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}
function onAccent(hex) {
    const m = /^#([0-9a-f]{6})$/i.exec(normalizeHex(hex) || '');
    if (!m) return '#ffffff';
    const n = parseInt(m[1], 16);
    const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    const L = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
    return L > 0.45 ? '#14121d' : '#ffffff';
}

// Theme-linked icons need their glyph re-tinted here every time the accent changes —
// a background-image data URI is static and can't reference var(--accent) itself, so
// this re-tints from the ORIGINAL, un-recolored SVG markup (edit-theme-icons.js) on
// every accent change instead of drifting further from the source with each re-tint.
let __fxThemeIcons = null;
let __fxThemeIconsPromise = null;

function fxRecolorSvgMarkup(markup, color) {
    const doc = new DOMParser().parseFromString(markup, 'image/svg+xml');
    const root = doc.documentElement;
    let touched = false;
    [root, ...root.querySelectorAll('*')].forEach((n) => {
        if (n.hasAttribute('fill') && n.getAttribute('fill') !== 'none') {
            n.setAttribute('fill', color);
            touched = true;
        }
        if (n.hasAttribute('stroke') && n.getAttribute('stroke') !== 'none') {
            n.setAttribute('stroke', color);
            touched = true;
        }
    });
    if (!touched) root.setAttribute('fill', color);
    return new XMLSerializer().serializeToString(root);
}

function fxSvgToDataUri(markup) {
    const base64 = btoa(unescape(encodeURIComponent(markup)));
    return `data:image/svg+xml;base64,${base64}`;
}

async function loadThemeIcons() {
    if (__fxThemeIconsPromise) return __fxThemeIconsPromise;
    __fxThemeIconsPromise = (async () => {
        try {
            const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
            const ver = (cfg.cacheBust || cfg.version || Date.now()).toString();
            const url = new URL('./edit-theme-icons.js', import.meta.url);
            url.searchParams.set('v', ver);
            const mod = await import(url.href);
            __fxThemeIcons = Array.isArray(mod.FX_THEME_ICONS) ? mod.FX_THEME_ICONS : [];
        } catch (_) {
            __fxThemeIcons = [];
        }
        return __fxThemeIcons;
    })();
    return __fxThemeIconsPromise;
}

/** Re-tints every icon whose glyph is linked to the theme accent, using whatever
 * `color` was just applied. Injects a small <style id="fx-theme-icons"> rule per icon
 * rather than touching inline styles, so it plays nicely with anything else that might
 * also target the same selector. Safe to call before loadThemeIcons() resolves — it's a
 * no-op until the list is available, and applyColor() below re-invokes it once it is. */
function applyThemeIconTints(color) {
    if (!__fxThemeIcons || !__fxThemeIcons.length) return;
    try {
        let styleTag = document.getElementById('fx-theme-icons');
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = 'fx-theme-icons';
            document.head.appendChild(styleTag);
        }
        const css = __fxThemeIcons.map(({ selector, originalSvg, boxSelector }) => {
            if (!selector || !originalSvg) return '';
            try {
                const recolored = fxRecolorSvgMarkup(originalSvg, color);
                const dataUrl = fxSvgToDataUri(recolored);
                let rule = `${selector} { background-image: url('${dataUrl}') !important; }`;
                if (boxSelector) {
                    rule += `\n${boxSelector} { background-color: ${softBackgroundFor(color)} !important; }`;
                }
                return rule;
            } catch (_) {
                return '';
            }
        }).filter(Boolean).join('\n');
        styleTag.textContent = css;
    } catch (_) {  }
}

export function applyColor(themeKeyOrHex) {
    const hex = normalizeHex(themeKeyOrHex);
    let color, bright, key;
    if (hex) {
        color = hex; bright = hexLighten(hex, 0.28); key = 'custom';
    } else {
        const theme = THEMES.find((t) => t.key === themeKeyOrHex) || THEMES.find((t) => t.key === 'purple');
        color = theme.color; bright = theme.bright; key = theme.key;
    }
    const root = document.documentElement;
    root.style.setProperty('--gold', color);
    root.style.setProperty('--gold-bright', bright);
    root.style.setProperty('--accent', color);
    root.style.setProperty('--accent-bright', bright);
    root.style.setProperty('--gold-soft',   hexToRgba(color, 0.10));
    root.style.setProperty('--gold-soft-2', hexToRgba(color, 0.18));
    root.style.setProperty('--accent-soft',   hexToRgba(color, 0.10));
    root.style.setProperty('--accent-soft-2', hexToRgba(color, 0.18));
    root.style.setProperty('--accent-glow',  hexToRgba(color, 0.40));
    root.style.setProperty('--accent-ink',   hexDarken(color, 0.5));
    root.style.setProperty('--border',        hexToRgba(color, 0.12));
    root.style.setProperty('--border-strong', hexToRgba(color, 0.28));
    root.style.setProperty('--on-accent', onAccent(color));
    root.dataset.color = key;

    // Re-tint any theme-linked icons with the color that was just applied. If the list
    // hasn't loaded yet (first call, right at boot), applyThemeIconTints() is a no-op for
    // now and loadThemeIcons() re-invokes it itself once the list actually arrives — so an
    // icon set on theme-follow still ends up correctly tinted even on a cold load.
    if (__fxThemeIcons) {
        applyThemeIconTints(color);
    } else {
        loadThemeIcons().then(() => applyThemeIconTints(color));
    }

    return color;
}

export function applyMode(mode) {
    const m = (mode === 'light') ? 'light' : 'dark';
    document.documentElement.dataset.theme = m;
    try {
        const mc = document.querySelector('meta[name="theme-color"]');
        if (mc) mc.setAttribute('content', m === 'light' ? '#eef2f8' : '#1b1b1d');
    } catch (_) {  }
    return m;
}

const MODE_KEY = 'faoxima.theme.mode';

export function getUserMode() {
    try {
        const raw = localStorage.getItem(MODE_KEY);
        if (raw === 'dark' || raw === 'light') {
            return raw;
        }
    } catch (_) {  }
    return null;
}

export function setUserMode(mode) {
    const m = applyMode(mode);
    try { localStorage.setItem(MODE_KEY, m); } catch (_) {  }
    return m;
}

export function toggleUserMode() {
    const current = document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
    const next = current === 'light' ? 'dark' : 'light';
    return setUserMode(next);
}

export { THEMES };
