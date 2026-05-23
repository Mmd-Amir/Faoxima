import { call } from './api.js';


export function applyBrand(brand) {
    if (!brand || typeof brand !== 'object') return;

    const cfg = (typeof window !== 'undefined' && window.__APP_CONFIG__) || {};
    const prefix = cfg.assetPrefix || '/';

    const $mark = document.querySelector('.brand-mark');
    const $name = document.querySelector('.brand-name');

    const name = String(brand.name || '').trim() || 'Faoxima';
    const mark = String(brand.mark || '').trim() || (name.charAt(0) || 'F');
    let logoUrl = String(brand.logo_url || '').trim();

    if (logoUrl !== '' && !/^https?:/i.test(logoUrl) && !logoUrl.startsWith(prefix)) {
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
    if ($name) {
        $name.textContent = name;
    }

    try {
        document.title = name + ' — ' + name;
    } catch (_) {  }


    try {
        localStorage.setItem('faoxima.brand', JSON.stringify({
            name: brand.name || '',
            mark: brand.mark || '',
            logo_url: brand.logo_url || '',
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
        try {
            localStorage.setItem('faoxima.brand', JSON.stringify({
                name: obj.name, mark: obj.mark, logo_url: obj.logo_url,
            }));
        } catch (_) {  }
        return obj;
    } catch (_) {
        return null;
    }
}
