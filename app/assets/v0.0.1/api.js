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

async function readEnvelope(res) {
    let text = '';
    try {
        text = await res.text();
    } catch (_) {
        return { body: null, text: '', parseError: null };
    }
    if (!text) {
        return { body: null, text: '', parseError: null };
    }
    try {
        const body = JSON.parse(text);
        return { body, text, parseError: null };
    } catch (err) {
        return { body: null, text, parseError: err };
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

    const { body, text, parseError } = await readEnvelope(res);

    if (res.ok && body && body.status === true && body.token) {
        setToken(body.token);
        return body.token;
    }

    let msg;
    let code = null;

    if (body && typeof body.msg === 'string' && body.msg !== '') {
        msg = body.msg;
    } else if (parseError) {
        msg = `پاسخ سرور قابل خواندن نبود (HTTP ${res.status}).`;
        code = 'BAD_JSON';
    } else if (res.ok && !text) {
        msg = `پاسخ سرور خالی بود (HTTP ${res.status}).`;
        code = 'EMPTY_BODY';
    } else if (body && body.status === true && !body.token) {
        msg = `سرور توکن صادر نکرد (HTTP ${res.status}).`;
        code = 'NO_TOKEN';
    } else {
        msg = `Verification failed (${res.status})`;
    }

    const data = body !== null
        ? body
        : (text ? { rawBody: text.length > 500 ? text.slice(0, 500) + '…' : text } : null);

    throw new ApiError(msg, { status: res.status, code, data });
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

    const { body: envelope, text, parseError } = await readEnvelope(res);

    if (res.status === 401 || res.status === 403) {
        if (retry) {
            clearToken();
            await verify();
            return call(action, { method, params, body, retry: false });
        }
    }

    if (!res.ok) {
        const msg = (envelope && envelope.msg) || `Request failed (${res.status})`;
        throw new ApiError(msg, {
            status: res.status,
            data: envelope !== null
                ? envelope
                : (text ? { rawBody: text.length > 500 ? text.slice(0, 500) + '…' : text } : null),
        });
    }

    if (parseError) {
        throw new ApiError(
            `پاسخ سرور قابل خواندن نبود (HTTP ${res.status}).`,
            {
                status: res.status,
                code: 'BAD_JSON',
                data: text ? { rawBody: text.length > 500 ? text.slice(0, 500) + '…' : text } : null,
            }
        );
    }

    if (envelope && envelope.status === false) {
        throw new ApiError(envelope.msg || 'Operation failed', { status: res.status, data: envelope });
    }

    return envelope || { status: true, obj: null };
}

export { ApiError };

