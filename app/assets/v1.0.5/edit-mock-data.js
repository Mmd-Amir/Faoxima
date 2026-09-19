// Deterministic mock data for the live editor preview (edit_preview=1).
// Every miniapp API action gets a stable, realistic response so ALL screens —
// buy flow, wallet recharge, card-to-card, crypto offline, service detail,
// renew/extra, tickets, notifications, admin settings — render fully and stay
// stable while elements are being selected/edited in the editor.

const NOW = () => Math.floor(Date.now() / 1000);

const NOW_STR = (daysAgo = 0) => {
    const d = new Date(Date.now() - daysAgo * 86400000);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}/${pad(d.getMonth() + 1)}/${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const BALANCE = 250000;

const SERVICES_LIST = [
    { username: 'user_demo1', name_product: '50 گیگ 30 روزه',   Service_location: 'آلمان',  status: 'active' },
    { username: 'user_demo2', name_product: '100 گیگ 30 روزه',  Service_location: 'هلند',   status: 'end_of_time' },
    { username: 'user_demo3', name_product: 'نامحدود ماهانه',   Service_location: 'فرانسه', status: 'send_on_hold' },
];

const DEPARTMENTS = [
    { id: 1, name: 'پشتیبانی فنی' },
    { id: 2, name: 'مالی' },
    { id: 3, name: 'فروش' },
];

const TICKETS = [
    { tracking: 'TCK1001', subject: 'مشکل در اتصال سرویس', department: 'پشتیبانی فنی', status: 'Answered',         open: true,  last_time: '۲ ساعت پیش' },
    { tracking: 'TCK1002', subject: 'سوال درباره تمدید',   department: 'فروش',         status: 'Customerresponse', open: true,  last_time: '۵ ساعت پیش' },
    { tracking: 'TCK1003', subject: 'درخواست بازگشت وجه',  department: 'مالی',         status: 'close',            open: false, last_time: 'دیروز' },
];

// Card-to-card first as the default method, followed by the standard
// crypto/currency gateways the real bot supports.
const PAYMENT_METHODS = [
    { id: 'carttocart',     label: '💳 کارت به کارت',            kind: 'form',           min: 50000, max: 5000000 },
    { id: 'crypto_offline', label: '🪙 ارز آفلاین (هش‌چکر)',     kind: 'crypto_offline', min: 50000, max: 20000000 },
    { id: 'digitaltron',    label: 'پرداخت با ترون (TRX)',       kind: 'crypto_offline', min: 50000, max: 20000000 },
    { id: 'plisio',         label: 'پرداخت ارزی (Plisio)',       kind: 'form',           min: 100000, max: 50000000 },
    { id: 'nowpayment',     label: 'NowPayments',                kind: 'form',           min: 100000, max: 50000000 },
    { id: 'zarinpal',       label: '🟡 درگاه زرین‌پال',           kind: 'form',           min: 10000, max: 10000000 },
];

const CARDS = [
    { number: '6037998812345678', name: 'فروشگاه فاکسیما' },
    { number: '6219861045678901', name: 'فروشگاه فاکسیما' },
];

function serviceInfo(username) {
    return {
        username: username || 'user_demo1',
        product_name: '50 گیگ 30 روزه',
        status: 'active',
        is_stock: false,
        used_traffic_gb: 23.5,
        total_traffic_gb: 50,
        remaining_traffic_gb: 26.5,
        online_at: 'آنلاین',
        expiration_time: '2026-08-30 23:59',
        last_subscription_update: '۲ ساعت پیش',
        subscription_url: 'https://sub.example.com/abc123demo',
        note: 'سرویس کاری',
        allowed_users: 2,
        ip_summary: '۲ آی‌پی فعال',
        service_output: [],
        disabled_actions: [],
    };
}

function notification(id, message, seen) {
    return {
        id,
        message,
        seen: !!seen,
        created_at: NOW() - id * 3600,
        expires_at: NOW() + 86400,
    };
}

const MOCK_RESPONDERS = {
    user_info: () => ({
        balance: BALANCE,
        is_admin: true,
        name: 'کاربر تست',
        phone: '09121234567',
    }),

    invoices: () => ({
        items: SERVICES_LIST,
        total: SERVICES_LIST.length,
        total_pages: 1,
    }),

    pending_payments: () => ({
        pending: [{
            order_id: 'ORD100200',
            amount: 150000,
            method: 'cardtocard',
            expires_at: NOW() + 900,
        }],
    }),

    test_account_info: () => ({
        available: true,
        panels: [{ id: 'de1', name: 'آلمان ۱' }],
        limit_left: 2,
    }),
    test_account_create: () => ({
        success: true,
        order_id: 'ORDTEST01',
        service: {
            id: 'ORDTEST01',
            username: 'testuser_1',
            status: 'active',
            active: true,
            expire: NOW() + 3600,
            product_name: 'سرویس تست',
            panel_name: 'آلمان ۱',
            service_time_days: 1,
            days_left: 1,
            volume_gb: 1,
            total_bytes: 1073741824,
            subscription_url: 'https://example.com/sub/testuser_1',
            configs: [],
        },
    }),

    // ---- tickets -------------------------------------------------------
    tickets: () => ({ items: TICKETS }),
    ticket_departments: () => ({ items: DEPARTMENTS }),
    ticket_thread: (params) => ({
        tracking: params.t || 'TCK1001',
        subject: 'مشکل در اتصال سرویس',
        department: 'پشتیبانی فنی',
        status: 'Answered',
        open: true,
        messages: [
            { id: 1, sender: 'user',  body: 'سلام، از دیشب سرویس وصل نمی‌شود.',              time: '10:24', media: [], seen: true, reply_to: null, reactions: [] },
            { id: 2, sender: 'admin', body: 'سلام، لطفاً نام کاربری سرویس را ارسال کنید.',   time: '10:31', media: [], seen: true, reply_to: { id: 1, body: 'سلام، از دیشب سرویس وصل نمی‌شود.' }, reactions: [{ emoji: '👍', actor: 'user', mine: true }] },
            { id: 3, sender: 'user',  body: 'user_demo1',                                     time: '10:33', media: [], seen: false, reply_to: null, reactions: [] },
        ],
    }),
    ticket_create: () => ({ tracking: 'TCK1004' }),
    ticket_reply: () => ({ ok: true }),
    ticket_close: () => ({ ok: true }),
    ticket_react: () => ({ reactions: [] }),

    // ---- buy flow (array responses!) -----------------------------------
    countries: () => ([
        { id: '1', name: 'آلمان 🇩🇪',  is_custom: true,  is_username: true,  is_username_required: false, is_username_random: true, is_note: true },
        { id: '2', name: 'هلند 🇳🇱',   is_custom: false, is_username: false },
        { id: '3', name: 'فرانسه 🇫🇷', is_custom: false, is_username: true, is_username_required: true },
    ]),

    categories: () => ([
        { id: '1', name: 'حجمی' },
        { id: '2', name: 'نامحدود' },
        { id: '3', name: 'اقتصادی' },
        { id: '4', name: 'ویژه' },
    ]),

    time_ranges: () => ([
        { id: '1', day: 30,  name: '۳۰ روزه' },
        { id: '2', day: 60,  name: '۶۰ روزه' },
        { id: '3', day: 90,  name: '۹۰ روزه' },
        { id: '4', day: 180, name: '۶ ماهه' },
    ]),

    services: () => ([
        { id: '11', name: '10 گیگ 30 روزه (اقتصادی)', traffic_gb: 10,  time_days: 30,  price: 45000,  ip_limit: 1, ip_limit_guard_active: true },
        { id: '12', name: '20 گیگ 30 روزه',           traffic_gb: 20,  time_days: 30,  price: 80000,  ip_limit: 1, ip_limit_guard_active: true },
        { id: '13', name: '50 گیگ 30 روزه',           traffic_gb: 50,  time_days: 30,  price: 150000, ip_limit: 2, ip_limit_guard_active: true },
        { id: '14', name: '100 گیگ 30 روزه',          traffic_gb: 100, time_days: 30,  price: 260000, ip_limit: 0, ip_limit_guard_active: false },
        { id: '15', name: '100 گیگ 60 روزه',          traffic_gb: 100, time_days: 60,  price: 320000, ip_limit: 2, ip_limit_guard_active: true },
        { id: '16', name: 'نامحدود ماهانه',           traffic_gb: 0,   time_days: 30,  price: 400000, ip_limit: 3, ip_limit_guard_active: true },
        { id: '17', name: 'نامحدود ۳ ماهه (ویژه)',    traffic_gb: 0,   time_days: 90,  price: 990000, ip_limit: 3, ip_limit_guard_active: true },
        { id: '18', name: '500 گیگ ۶ ماهه',           traffic_gb: 500, time_days: 180, price: 1250000, ip_limit: 5, ip_limit_guard_active: true },
    ]),

    custom_price: (params) => {
        const gb = Number(params.traffic_gb || 0);
        const days = Number(params.time_days || 0);
        return {
            price: (gb * 3000) + (days * 1000),
            traffic_min: 10, traffic_max: 500,
            time_min: 30, time_max: 180,
        };
    },

    purchase: () => ({
        obj: {},
        __envelope: {
            order_id: 'ORD100001',
            service: {
                id: 'ORD100001',
                username: 'user_demo1',
                name_product: '50 گیگ 30 روزه',
                service_time_days: 30,
                days_left: 30,
                traffic_gb: 50,
                subscription_url: 'https://sub.example.com/abc123demo',
            },
        },
    }),

    // ---- wallet / payments ---------------------------------------------
    payment_methods: () => ({
        methods: PAYMENT_METHODS,
        limits: { min: 50000, max: 5000000 },
        balance: BALANCE,
        randomWallet: { enabled: true, amounts: [100000, 200000, 500000] },
    }),

    payment_init: (params) => {
        const method = String(params.method || '');
        const amount = Number(params.amount || 100000);
        if (method === 'carttocart' || method.startsWith('carttocart')) {
            return {
                kind: 'carttocart',
                order_id: 'ORD100201',
                amount,
                display_mode: 'direct',
                cards: CARDS,
                card_number: CARDS[0].number,
                name_card: CARDS[0].name,
                auto_confirm: true,
                card_verify_required: false,
                message: 'مبلغ را دقیقاً به یکی از کارت‌های زیر واریز کنید.',
            };
        }
        if (method === 'zarinpal' || method === 'plisio' || method === 'nowpayment' || method.startsWith('iranpay')) {
            return { kind: 'url', url: '#', order_id: 'ORD100202', message: 'برای تکمیل پرداخت، به درگاه منتقل می‌شوید.' };
        }
        return { kind: 'manual', order_id: 'ORD100203', message: 'ادمین پس از بررسی، حساب شما را شارژ می‌کند.' };
    },

    payment_status: (params) => ({
        payment_status: 'paid',
        order_id: params.order_id || 'ORD100202',
        amount: 150000,
        is_service_ready: true,
        method: 'zarinpal',
        flow: 'recharge',
        service: MOCK_RESPONDERS.purchase().__envelope.service,
    }),

    payment_reset: () => ({}),

    discount_validate: (params) => {
        const base = Number(params.base_price || 0);
        return {
            message: 'کد تخفیف ۱۰٪ اعمال شد',
            final_price: Math.max(0, Math.round(base * 0.9)),
        };
    },

    redeem_giftcode: () => ({
        message: 'کد هدیه اعمال شد',
        new_balance: BALANCE + 50000,
    }),

    // ---- crypto offline -------------------------------------------------
    crypto_currencies: () => ({
        // Same shape as CryptoCurrenciesHandler on the real API.
        currencies: [
            { code: 'USDT_TRC20', name: 'تتر روی ترون', label: 'USDT (TRC20)', icon_key: 'tether', color: '#26a17b', network: 'TRON', wallet_address: 'TVJ6njG5Fw3iCTLLDicMdJcTdemo1234', memo_required: false },
            { code: 'TRX',        name: 'ترون',         label: 'TRON (TRX)',   icon_key: 'tron',   color: '#e53935', network: 'TRON', wallet_address: 'TVJ6njG5Fw3iCTLLDicMdJcTdemo1234', memo_required: false },
            { code: 'TON',        name: 'تون',          label: 'Toncoin',      icon_key: 'ton',    color: '#0098ea', network: 'TON',  wallet_address: 'UQDemoTonWalletAddress1234567890abcd', memo_required: true },
            { code: 'USDT_TON',   name: 'تتر روی تون',  label: 'USDT (TON)',   icon_key: 'tether', color: '#26a17b', network: 'TON',  wallet_address: 'UQDemoTonWalletAddress1234567890abcd', memo_required: true },
        ],
        min_amount_toman: 50000,
        max_amount_toman: 20000000,
    }),

    crypto_invoice_init: (params) => {
        const code = String(params.currency_code || 'USDT_TRC20').toUpperCase();
        const onTon = code === 'TON' || code === 'USDT_TON';
        const amountToman = Number(params.amount || 0);
        return {
            order_id: 'CRY100300',
            wallet_to: onTon ? 'UQDemoTonWalletAddress1234567890abcd' : 'TVJ6njG5Fw3iCTLLDicMdJcTdemo1234',
            crypto_amount: code === 'TRX' ? '145.20' : '12.34',
            network: onTon ? 'TON' : 'TRON',
            wallet_memo: onTon ? '100300' : '',
            amount: amountToman,
            amount_toman: amountToman,
            amount_toman_final: amountToman + 354,
        };
    },

    crypto_submit_hash: () => ({ message: 'هش ثبت شد' }),
    crypto_cancel_invoice: () => ({ message: 'فاکتور لغو شد' }),

    // ---- service detail -------------------------------------------------
    service: (params) => serviceInfo(params.username),

    service_configs: () => ({
        configs: [
            'vless://demo-uuid-0001@de1.example.com:443?security=tls&type=ws#Faoxima-DE',
            'vmess://eyJ2IjoiMiIsInBzIjoiRmFveGltYS1OTCIsImFkZCI6Im5sMS5leGFtcGxlLmNvbSJ9',
        ],
    }),

    service_simple_action: () => ({ message: 'انجام شد (پیش‌نمایش)' }),

    service_action: (params) => {
        if (String(params.action || '') === 'changelink') {
            return {
                kind: 'changelink_done',
                subscription_url: 'https://sub.example.com/new456demo',
                message: 'لینک قبلی دیگر معتبر نیست.',
            };
        }
        if (String(params.action || '') === 'change_location') {
            return {
                kind: 'change_location_done',
                message: 'موقعیت سرویس شما با موفقیت تغییر کرد.',
                panel_name: 'هلند',
            };
        }
        return { message: 'انجام شد (پیش‌نمایش)' };
    },

    service_locations: () => ({
        panels: [
            { id: 'nl1', name: 'هلند', price: 0 },
            { id: 'de2', name: 'آلمان ۲', price: 15000 },
        ],
        is_free: true,
        limit_left: 3,
    }),

    service_renew_options: () => ({
        show_price: true,
        current_plan: { code: 'p1', name: '50 گیگ 30 روزه', volume_gb: 50, time_days: 30, price: 150000, ip_limit: 2, ip_limit_guard_active: true },
        products: [
            { code: 'p1', name: '50 گیگ 30 روزه',  volume_gb: 50,  time_days: 30, price: 150000, ip_limit: 2, ip_limit_guard_active: true },
            { code: 'p2', name: '100 گیگ 30 روزه', volume_gb: 100, time_days: 30, price: 260000, ip_limit: 0, ip_limit_guard_active: false },
            { code: 'p3', name: 'نامحدود ماهانه',  volume_gb: 0,   time_days: 30, price: 400000, ip_limit: 3, ip_limit_guard_active: true },
        ],
        custom: {
            enabled: true,
            price_per_gb: 3000,
            price_per_day: 1000,
            min_volume_gb: 10, max_volume_gb: 500,
            min_time_days: 30, max_time_days: 180,
        },
    }),

    service_renew_confirm: () => ({
        kind: 'done',
        message: 'سرویس با موفقیت تمدید شد (پیش‌نمایش)',
        balance_after: BALANCE - 150000,
        cashback: 5000,
    }),

    service_extra_quote: (params) => ({
        min: 1,
        max: 100,
        price_per_unit: String(params.kind || '') === 'extra_time' ? 5000 : 3000,
        balance: BALANCE,
    }),

    service_extra_confirm: () => ({
        kind: 'done',
        message: 'با موفقیت اضافه شد (پیش‌نمایش)',
        balance_after: BALANCE - 30000,
    }),

    // ---- brand / admin settings ----------------------------------------
    brand_info: () => ({
        is_admin: true,
        name: 'Faoxima',
        mark: 'M',
        logo_url: '',
        mode: 'dark',
    }),

    brand_save: () => ({}),

    // ---- notifications --------------------------------------------------
    notification_info: () => ({
        notification: notification(1, 'به‌روزرسانی جدید: سرورهای آلمان ارتقا یافتند 🚀', false),
    }),

    notification_recent: () => ({
        notifications: [
            notification(1, 'به‌روزرسانی جدید: سرورهای آلمان ارتقا یافتند 🚀', false),
            notification(2, 'تخفیف ۱۰٪ شارژ کیف پول تا پایان هفته', true),
            notification(3, 'سرویس‌های جدید هلند اضافه شدند', true),
        ],
    }),

    notification_save: () => ({
        message: 'اعلان با موفقیت ارسال شد',
        notification: notification(1, 'اعلان جدید شما (پیش‌نمایش)', false),
    }),

    notification_delete: () => ({}),
    notification_dismiss: () => ({}),

    transactions: () => ({
        items: [
            { id: 5, direction: 'credit', amount: 200000, balance_after: BALANCE, category: 'topup_card', category_label: 'شارژ کارت به کارت', description: 'cart to cart', order_id: 'ORD100199', created_at: NOW_STR(-1) },
            { id: 4, direction: 'debit', amount: 89000, balance_after: BALANCE - 200000, category: 'purchase', category_label: 'خرید سرویس', description: 'خرید سرویس', order_id: 'ORD100150', created_at: NOW_STR(-2) },
            { id: 3, direction: 'credit', amount: 15000, balance_after: BALANCE - 200000 + 89000, category: 'cashback', category_label: 'هدیه بازگشت وجه', description: 'هدیه بازگشت وجه کارت به کارت', order_id: 'ORD100150', created_at: NOW_STR(-2) },
            { id: 2, direction: 'credit', amount: 50000, balance_after: BALANCE - 200000 + 89000 - 15000, category: 'gift_code', category_label: 'کد هدیه', description: 'استفاده از کد هدیه', order_id: null, created_at: NOW_STR(-5) },
            { id: 1, direction: 'credit', amount: 100000, balance_after: BALANCE - 200000 + 89000 - 15000 - 50000, category: 'admin_credit', category_label: 'افزایش موجودی توسط ادمین', description: 'افزایش موجودی توسط ادمین', order_id: null, created_at: NOW_STR(-9) },
        ],
        next_before_id: null,
    }),
};

export async function mockCall(action, { params = {} } = {}) {
    await new Promise((r) => setTimeout(r, 90));
    const responder = MOCK_RESPONDERS[action];
    const result = responder ? responder(params) : {};
    // Some real endpoints (purchase) put extra keys on the envelope itself,
    // next to `obj` — responders opt into that via `__envelope`.
    if (result && result.__envelope) {
        return { status: true, obj: result.obj ?? null, ...result.__envelope };
    }
    return { status: true, obj: result };
}
