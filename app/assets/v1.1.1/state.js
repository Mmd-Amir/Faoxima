const TOKEN_KEY = 'faoxima.token';
const buyDraftKey = 'faoxima.buyDraft';

const memory = {
    user: null,
    settings: null,
};

export function getToken() {
    try { return sessionStorage.getItem(TOKEN_KEY) || ''; } catch (_) { return ''; }
}
export function setToken(token) {
    try { sessionStorage.setItem(TOKEN_KEY, token); } catch (_) {}
}
export function clearToken() {
    try { sessionStorage.removeItem(TOKEN_KEY); } catch (_) {}
}

export function getUser() { return memory.user; }
export function setUser(u) { memory.user = u; }


export function getBuyDraft() {
    try {
        const raw = sessionStorage.getItem(buyDraftKey);
        return raw ? JSON.parse(raw) : { step: 1 };
    } catch (_) { return { step: 1 }; }
}
export function setBuyDraft(draft) {
    try { sessionStorage.setItem(buyDraftKey, JSON.stringify(draft)); } catch (_) {}
}
export function clearBuyDraft() {
    try { sessionStorage.removeItem(buyDraftKey); } catch (_) {}
}

