import { call } from './api.js?v=0.0.52';
import { applyMode, getUserMode } from './theme.js?v=0.0.52';


export function applyBrand(brand) {
    if (!brand || typeof brand !== 'object') return;

    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const prefix = cfg.assetPrefix || '/';

    const $mark = document.querySelector('.brand-mark');
    const $title = document.querySelector('.brand-title');

    const name = String(brand.name || '').trim() || 'Faoxima';
    const mark = String(brand.mark || '').trim() || (name.charAt(0) || 'F');
    const title = String(brand.title || '').trim();
    const state = String(brand.avatar_state || '').trim() || (brand.logo_url ? 'custom' : 'initials');
    let logoUrl = state === 'custom' ? String(brand.logo_url || '').trim() : '';

    if (logoUrl !== '' && !/^(?:https?:|data:image\/)/i.test(logoUrl) && !logoUrl.startsWith(prefix)) {
        logoUrl = prefix + logoUrl.replace(/^\/+/, '');
    }

    if ($mark) {
        if (logoUrl !== '') {
            $mark.innerHTML = `<img src="${logoUrl}" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" />`;
            $mark.style.background = 'transparent';
            $mark.style.padding = '0';
            $mark.style.overflow = 'hidden';
        } else {
            $mark.textContent = mark;
            $mark.style.background = '';
            $mark.style.padding = '';
            $mark.style.overflow = '';
        }
    }

    if ($title) {
        $title.textContent = title;
        $title.hidden = title === '';
    }

    try {
        var ac = (typeof brand.accent === 'string') ? brand.accent.trim() : '';
        if (/^#?[0-9a-fA-F]{6}$/.test(ac) && typeof window.__applyAccent === 'function') {
            window.__applyAccent(ac.charAt(0) === '#' ? ac : ('#' + ac));
        }
    } catch (_) {  }

    try {
        const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
        const forceDark = !!cfg.forceDark;
        const userMode = getUserMode();
        if (forceDark) {
            applyMode('dark');
        } else if (typeof brand.mode === 'string') {
            if (!userMode) {
                applyMode(brand.mode);
            }
        }
    } catch (_) {  }

    try {
        document.title = name + ' — ' + name;
    } catch (_) {  }


    try {
        const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
        const persistMode = cfg.forceDark ? 'dark' : (brand.mode || '');
        localStorage.setItem('faoxima.brand', JSON.stringify({
            name: brand.name || '',
            mark: brand.mark || '',
            title: brand.title || '',
            logo_url: brand.logo_url || '',
            avatar_state: state,
            is_default_logo: !!brand.is_default_logo,
            accent: brand.accent || '',
            mode: persistMode,
        }));
    } catch (_) {  }
}


export async function loadBrandFromServer() {

    try {
        const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
        if (cfg.brand && (cfg.brand.name || cfg.brand.mark || cfg.brand.logo_url)) {
            applyBrand(cfg.brand);
        } else {
            const cachedRaw = localStorage.getItem('faoxima.brand');
            if (cachedRaw) {
                try { applyBrand(JSON.parse(cachedRaw)); } catch (_) {  }
            }
        }
    } catch (_) {  }

    try {
        const res = await call('brand_info');
        const obj = res?.obj || {};
        applyBrand(obj);
        return obj;
    } catch (_) {
        return null;
    }
}
