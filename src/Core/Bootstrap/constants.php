<?php

if (!defined('RX_NAV_LOG_DIR')) {
    if (defined('REFACTORED_LOG_DIR')) {
        define('RX_NAV_LOG_DIR', REFACTORED_LOG_DIR);
    } else {
        define('RX_NAV_LOG_DIR', (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'logs');
    }
}

if (!defined('RX_AR_TTL')) {
    define('RX_AR_TTL', 1800);
}
if (!defined('RX_AR_NOTIFY_MAX_ATTEMPTS')) {
    define('RX_AR_NOTIFY_MAX_ATTEMPTS', 5);
}
if (!defined('RX_AR_NOTIFY_STALE_SECONDS')) {
    define('RX_AR_NOTIFY_STALE_SECONDS', 300);
}

const ATLASPAY_CUSTOM_DEADLINE_SECONDS = 1200;
const ATLASPAY_CUSTOM_DEADLINE_GRACE_SECONDS = 600;
const ATLASPAY_CUSTOM_LATE_RECONCILE_SECONDS = 172800;
const ATLASPAY_CUSTOM_POLL_THROTTLE_SECONDS = 8;
const ATLASPAY_CUSTOM_CARD_RETENTION_SECONDS = 10800;
const ATLASPAY_CUSTOM_RECHECK_SECONDS = 900;
const ATLASPAY_CUSTOM_CRON_INTERVAL_SECONDS = 30;
const ATLASPAY_CUSTOM_PENDING_VISIBLE_SECONDS = 7200;
const ATLASPAY_CUSTOM_DEFAULT_TITLE = 'پرداخت کارت‌به‌کارت (اطلس‌پی)';

define('QR_MAX_BYTES', 2048);

if (!defined('RX_CK_PAYMENT_TTL')) {
    define('RX_CK_PAYMENT_TTL', 3600);
}
if (!defined('RX_CK_CHOICE_TTL')) {
    define('RX_CK_CHOICE_TTL', 1800);
}

const ATLASPAY_BASE_URL = 'https://api.atlaspay.space/api/v1';
const BLUPAL_RIAL_PER_TOMAN = 10;

if (!defined('RX_PF_LEASE_SECONDS')) {
    define('RX_PF_LEASE_SECONDS', 300);
}
if (!defined('RX_PF_EXPIRE_TOLERANCE')) {
    define('RX_PF_EXPIRE_TOLERANCE', 120);
}
if (!defined('RX_PF_MAX_ATTEMPTS')) {
    define('RX_PF_MAX_ATTEMPTS', 12);
}
if (!defined('RX_PF_GIB')) {
    define('RX_PF_GIB', 1073741824);
}
if (!defined('RX_RENEWAL_LOG_DEDUP_SECONDS')) {
    define('RX_RENEWAL_LOG_DEDUP_SECONDS', 60);
}
if (!defined('RX_RENEWAL_LOG_MAX_BYTES')) {
    define('RX_RENEWAL_LOG_MAX_BYTES', 5242880);
}
if (!defined('RX_RN_USAGE_SLACK')) {
    define('RX_RN_USAGE_SLACK', 536870912);
}
if (!defined('RX_RN_TRAFFIC_TOLERANCE')) {
    define('RX_RN_TRAFFIC_TOLERANCE', 1048576);
}
if (!defined('RX_RN_FAR_FUTURE')) {
    define('RX_RN_FAR_FUTURE', 284014800);
}

const RX_PAYMENT_PROCESSING_VERSION = 2;
const TRONADO_V2_RECENT_SECONDS = 7200;
const TRONADO_V2_WINDOW_SECONDS = 172800;
const TRONADO_V2_POLL_RECENT_SECONDS = 60;
const TRONADO_V2_POLL_OLD_SECONDS = 900;
const TRONADO_V2_TRX_EPSILON = 0.000001;

if (!defined('FX_DEFAULT_PAIR')) {
    define('FX_DEFAULT_PAIR', 'USDT/IRT');
}
if (!defined('FX_QUOTE_TTL_SECONDS')) {
    define('FX_QUOTE_TTL_SECONDS', 600);
}
if (!defined('FX_SERVER_QUOTE_TTL_SECONDS')) {
    define('FX_SERVER_QUOTE_TTL_SECONDS', 86400);
}
if (!defined('FX_PENDING_TOLERANCE_PERCENT')) {
    define('FX_PENDING_TOLERANCE_PERCENT', 1.0);
}
if (!defined('FX_PENDING_CONFIRMATIONS')) {
    define('FX_PENDING_CONFIRMATIONS', 2);
}
if (!defined('FX_SWAPWALLET_ENDPOINT')) {
    define('FX_SWAPWALLET_ENDPOINT', 'https://swapwallet.app/api/v1/market/prices');
}

if (!defined('RX_SBV_FEE_MIN')) {
    define('RX_SBV_FEE_MIN', 1);
}
if (!defined('RX_SBV_FEE_MAX')) {
    define('RX_SBV_FEE_MAX', 999);
}
if (!defined('RX_SBV_RECEIPT_DELAY')) {
    define('RX_SBV_RECEIPT_DELAY', 600);
}
if (!defined('RX_SBV_LIFETIME_MIN_MINUTES')) {
    define('RX_SBV_LIFETIME_MIN_MINUTES', 30);
}
if (!defined('RX_SBV_LIFETIME_MAX_MINUTES')) {
    define('RX_SBV_LIFETIME_MAX_MINUTES', 1440);
}
if (!defined('RX_SBV_REUSE_COOLDOWN')) {
    define('RX_SBV_REUSE_COOLDOWN', 43200);
}
if (!defined('RX_SBV_EXPIRE_GRACE')) {
    define('RX_SBV_EXPIRE_GRACE', 180);
}
if (!defined('RX_SBV_DEVICE_SKEW')) {
    define('RX_SBV_DEVICE_SKEW', 600);
}
if (!defined('RX_SBV_SCOPE')) {
    define('RX_SBV_SCOPE', 'global');
}
if (!defined('RX_SBV_MAX_BODY')) {
    define('RX_SBV_MAX_BODY', 65536);
}
if (!defined('RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_DEFAULT_TOMAN', 1000);
}
if (!defined('RX_SBV_SUGGEST_RANGE_MIN_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_MIN_TOMAN', 0);
}
if (!defined('RX_SBV_SUGGEST_RANGE_MAX_TOMAN')) {
    define('RX_SBV_SUGGEST_RANGE_MAX_TOMAN', 10000000);
}

if (!defined('RX_SBV_MENU_LABEL')) {
    define('RX_SBV_MENU_LABEL', '🔐 تأیید رسید با بررسی');
}

if (!defined('RX_RN_LOCK_SECONDS')) {
    define('RX_RN_LOCK_SECONDS', 300);
}
if (!defined('RX_RN_MAX_ATTEMPTS')) {
    define('RX_RN_MAX_ATTEMPTS', 10);
}
if (!defined('RX_RN_BLOCKED_RECHECK')) {
    define('RX_RN_BLOCKED_RECHECK', 21600);
}

const RIALIT_API_BASE_URL = 'https://api.rialit.io/api/v1';
const RIALIT_PAY_BASE_URL = 'https://pay.rialit.io/v1/';
const RIALIT_MIN_AMOUNT = 50000;
const RIALIT_MAX_AMOUNT = 10000000;
const RIALIT_LINK_TTL_SECONDS = 1800;
const RIALIT_RECONCILE_SECONDS = 86400;
const RIALIT_LATE_RECONCILE_SECONDS = 172800;
const RIALIT_POLL_THROTTLE_SECONDS = 120;
const RIALIT_USER_CHECK_THROTTLE_SECONDS = 15;
const RIALIT_CREATE_ATTEMPTS = 3;
const RIALIT_STATUS_ATTEMPTS = 3;
const RIALIT_WEBHOOK_MAX_ATTEMPTS = 12;
const RIALIT_DISPLAY_NAME = '💚 ریالیت';

const TETRAMINATOR_API_BASE_URL = 'https://api.tetraminator.com/v1';
const TETRAMINATOR_MIN_AMOUNT = 50000;
const TETRAMINATOR_MAX_AMOUNT = 10000000;
const TETRAMINATOR_LINK_TTL_SECONDS = 3600;
const TETRAMINATOR_RECONCILE_SECONDS = 86400;
const TETRAMINATOR_POLL_THROTTLE_SECONDS = 60;
const TETRAMINATOR_USER_CHECK_THROTTLE_SECONDS = 15;
const TETRAMINATOR_CREATE_TIMEOUT_SECONDS = 20;
const TETRAMINATOR_WEBHOOK_TIMEOUT_SECONDS = 12;
const TETRAMINATOR_CRON_TIMEOUT_SECONDS = 20;
const TETRAMINATOR_DISPLAY_NAME = '💎 تترامیناتور';

if (!defined('RX_TEST_PRODUCT_NAME')) {
    define('RX_TEST_PRODUCT_NAME', 'سرویس تست');
}
if (!defined('RX_TEST_DEFAULT_HOURS')) {
    define('RX_TEST_DEFAULT_HOURS', 1);
}
if (!defined('RX_TEST_DEFAULT_MB')) {
    define('RX_TEST_DEFAULT_MB', 100);
}
if (!defined('RX_TEST_MAX_LIMIT')) {
    define('RX_TEST_MAX_LIMIT', 1000000);
}
if (!defined('RX_TEST_MAX_HOURS')) {
    define('RX_TEST_MAX_HOURS', 87600);
}
if (!defined('RX_TEST_MAX_MB')) {
    define('RX_TEST_MAX_MB', 10485760);
}
if (!defined('RX_TEST_AUDIENCE_ALL')) {
    define('RX_TEST_AUDIENCE_ALL', 'all_users');
}
if (!defined('RX_TEST_AUDIENCE_NEW')) {
    define('RX_TEST_AUDIENCE_NEW', 'new_users_24h');
}

const TONPAY_CUSTOM_BASE_URL = 'https://tonpays.online/api/custom/v1';
const TONPAY_CUSTOM_RECEIPT_WAIT_SECONDS = 600;
const TONPAY_CUSTOM_REVIEW_SECONDS = 86400;
const TONPAY_CUSTOM_UNPAID_LIFETIME_SECONDS = 86400;
const TONPAY_CUSTOM_LATE_RECONCILE_SECONDS = 1209600;
const TONPAY_CUSTOM_RECEIPT_MAX_BYTES = 10485760;
const TONPAY_CUSTOM_POLL_THROTTLE_SECONDS = 8;
const TONPAY_CUSTOM_CHANGE_CARD_COOLDOWN_SECONDS = 60;

if (!defined('RX_CRON_TASK_STATE_LOADED')) {
    define('RX_CRON_TASK_STATE_LOADED', true);
}
if (!defined('RX_CRON_HEALTH_LOADED')) {
    define('RX_CRON_HEALTH_LOADED', true);
}
if (!defined('XSYNC_BATCH_SIZE')) {
    define('XSYNC_BATCH_SIZE', 40);
}
if (!defined('XSYNC_DISCOVER_BATCH')) {
    define('XSYNC_DISCOVER_BATCH', 40);
}
if (!defined('XSYNC_EMPTY_CONFIRM_DELAY')) {
    define('XSYNC_EMPTY_CONFIRM_DELAY', 45);
}
if (!defined('XSYNC_API_TIMEOUT')) {
    define('XSYNC_API_TIMEOUT', 8);
}
if (!defined('XSYNC_STEP_RETRIES')) {
    define('XSYNC_STEP_RETRIES', 3);
}
if (!defined('XSYNC_TIME_BUDGET')) {
    define('XSYNC_TIME_BUDGET', 20);
}
if (!defined('XSYNC_BACKOFF_CAP')) {
    define('XSYNC_BACKOFF_CAP', 3600);
}
if (!defined('XSYNC_SLEEP_ENABLED')) {
    define('XSYNC_SLEEP_ENABLED', true);
}
if (!defined('INFOCARD_FONT_DIR')) {
    define('INFOCARD_FONT_DIR', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'infocard_fonts');
}
if (!defined('JOVAD_GIB')) {
    define('JOVAD_GIB', 1073741824);
}
if (!defined('XUI_SUB_LINK_SCHEMES')) {
    define('XUI_SUB_LINK_SCHEMES', '(vless|vmess|trojan|ss|hysteria2?|hy2|tuic|wireguard|wg|vpn|awg|amneziawg|tg)');
}
if (!defined('GUARD_CORE_BASE_URL')) {
    define('GUARD_CORE_BASE_URL', 'https://core.erfjab.com');
}
