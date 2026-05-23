import { waitForSDK, waitForInitData, getInitData, diagnostics } from './telegram.js';
import { getToken, setToken, clearToken } from './state.js';

const cfg = window.__APP_CONFIG__ || {};
const API_URL = cfg.apiUrl || '/api';

class ApiError extends Error {
    constructor(message, { status = 0, data = null, code = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
        this.code = code;
    }
}


export async function verify() {


    const sdkLoaded = await waitForSDK(4000);
    if (!sdkLoaded) {
        const diag = diagnostics();
        console.error('[verify] Telegram SDK never loaded', diag);
        throw new ApiError('Telegram SDK failed to load.', {
            status: 0,
            code: 'NO_SDK',
            data: { diag },
        });
    }


    let initData = await waitForInitData(1500);
    if (!initData) initData = getInitData();

    if (!initData) {
        const diag = diagnostics();
        console.error('[verify] initData empty', diag);
        throw new ApiError('initData unavailable. Open this page from Telegram.', {
            status: 0,
            code: 'NO_INIT_DATA',
            data: { diag },
        });
    }

    const url = `${API_URL}/verify.php`;
    let res;
    try {
        res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Telegram-Init-Data': initData,
            },
            body: JSON.stringify({ initData }),
        });
    } catch (err) {
        console.error('[verify] network error', err);
        throw new ApiError('اتصال به سرور برقرار نشد.', { status: 0, code: 'NETWORK' });
    }

    let body = null;
    try { body = await res.json(); } catch (_) {  }

    if (!res.ok || !body || !body.status) {
        const msg = (body && body.msg) || `Verification failed (${res.status})`;
        throw new ApiError(msg, { status: res.status, data: body });
    }

    setToken(body.token);
    return body.token;
}


export async function call(action, { method = 'GET', params = {}, body = null, retry = true } = {}) {
    const token = getToken();
    if (!token) {

        await verify();
        return call(action, { method, params, body, retry: false });
    }

    const qs = new URLSearchParams({ actions: action, ...params });
    const url = `${API_URL}/miniapp.php?${qs.toString()}`;

    const init = {
        method,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
    };

    if (method !== 'GET' && method !== 'HEAD') {
        const payload = { actions: action, ...(body || {}) };
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(payload);
    }

    let res;
    try {
        res = await fetch(url, init);
    } catch (err) {
        console.error(`[api:${action}] network error`, err);
        throw new ApiError('اتصال به سرور برقرار نشد.', { status: 0, code: 'NETWORK' });
    }

    let envelope = null;
    try { envelope = await res.json(); } catch (_) {  }

    if (res.status === 401 || res.status === 403) {

        if (retry) {
            clearToken();
            await verify();
            return call(action, { method, params, body, retry: false });
        }
    }

    if (!res.ok) {
        const msg = (envelope && envelope.msg) || `Request failed (${res.status})`;
        throw new ApiError(msg, { status: res.status, data: envelope });
    }

    if (envelope && envelope.status === false) {
        throw new ApiError(envelope.msg || 'Operation failed', { status: res.status, data: envelope });
    }

    return envelope || { status: true, obj: null };
}

export { ApiError };

