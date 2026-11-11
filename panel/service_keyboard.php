<?php
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../re/rx/function/tonpay_templates.php';
require_once __DIR__ . '/../re/rx/function/account_layout.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

$ALLOWED_STYLES = ['default', 'none', 'primary', 'success', 'danger'];
$EXPLICIT_STYLES = ['none', 'primary', 'success', 'danger'];

$STYLE_META = [
    'none' => ['label' => 'بدون رنگ', 'hint' => 'رنگ پیش‌فرض تلگرام؛ بدون رنگ خودکار'],
    'primary' => ['label' => 'اصلی', 'hint' => 'آبی'],
    'success' => ['label' => 'موفقیت', 'hint' => 'سبز'],
    'danger'  => ['label' => 'خطر', 'hint' => 'قرمز'],
];

$MENUS = [
    'service' => [
        'title' => 'منوی سرویس', 'icon' => 'gear', 'type' => 'inline',
        'desc'  => 'دکمه‌های مدیریت سرویس کاربر.',
        'buttons' => [
            'updateinfo'       => '♻️ بروزرسانی اطلاعات',
            'config'           => '📥 دریافت کانفیگ',
            'linksub'          => '🔗 لینک اشتراک',
            'extend'           => '🔄 تمدید سرویس',
            'Extra_volume'     => '➕ خرید حجم اضافه',
            'Extra_time'       => '⏳ خرید زمان اضافه',
            'changestatus'     => '❌ روشن / خاموش اکانت',
            'change-location'  => '🌍 تغییر لوکیشن',
            'transfor'         => '🚚 انتقال سرویس',
            'ekhtelal'         => '⚠️ ارسال گزارش اختلال',
            'removeservice'    => '🗑 حذف سرویس',
            'changelink'       => '🔄 تغییر لینک',
            'changenameconfig' => '📝 تغییر یادداشت',
            'backorder'        => '🏠 بازگشت به لیست سرویس‌ها',
            'discountextend'   => '🎁 تمدید با کد تخفیف',
            'productcheckdata' => '↩️ بازگشت به اطلاعات سرویس',
            'remna_usage'      => '📊 مصرف به‌تفکیک سرور',
            'remna_picker'     => '🌍 انتخاب سرور',
            'config_header'    => '🔐 سرتیتر کانفیگ اشتراک',
        ],
    ],
    'account' => [
        'title' => 'پنل اکانت', 'icon' => 'user', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی کیف‌پول / اکانت کاربر.',
        'buttons' => [
            'Discount'    => '🎁 کد هدیه',
            'Add_Balance' => '💰 افزایش موجودی',
            'TransferBalance' => '🔄 انتقال موجودی',
            'MyTransactions'  => '📑 تراکنش‌های من',
            'transferbal_confirm' => '✅ تایید و انتقال',
            'transferbal_cancel'  => '❌ انصراف',
            'backuser'    => '◀️ بازگشت',
            'backinfo'    => '↩️ بازگشت به اطلاعات',
            'rw_pick'     => '💵 دکمه‌های مبلغ شارژ کیف پول',
            'rw_custom'   => '✍️ رقم دلخواه شارژ کیف پول',
            'my_miniapp_pending' => '⏳ رسید تایید نشده از مینی‌اپ',
            'tpc_list'        => '⏳ تراکنش‌های در انتظار',
            'supporttickets'  => '🎫 تیکت‌های پشتیبانی',
            'recheckcrypto'   => '🔁 بررسی مجدد هش کریپتو',
        ],
    ],
    'tx_stats' => [
        'title' => 'فیلتر زمانی تراکنش‌ها', 'icon' => 'clock', 'type' => 'inline',
        'desc'  => 'دکمه‌های بازه‌ی زمانی در صفحه‌ی تراکنش‌های من.',
        'buttons' => [
            'tx_24h'  => '🕐 ۲۴ ساعت گذشته',
            'tx_3d'   => '📅 ۳ روز گذشته',
            'tx_7d'   => '🗓 ۷ روز گذشته',
            'tx_30d'  => '📆 ۳۰ روز گذشته',
            'tx_prev' => '◀️ قبلی',
            'tx_next' => '▶️ بعدی',
            'tx_back' => '◀️ بازگشت',
        ],
    ],
    'payment' => [
        'title' => 'درگاه‌های پرداخت', 'icon' => 'dollar-sign', 'type' => 'inline',
        'desc'  => 'دکمه‌های انتخاب روش پرداخت هنگام خرید.',
        'buttons' => [
            'cart_to_offline'  => '💳 کارت به کارت',
            'plisio'           => '🔵 Plisio',
            'nowpayment'       => '🟣 NowPayment',
            'digitaltron'      => '🟡 رمزارز Tron',
            'iranpay2'         => '🟠 ترونادو',
            'tonpay'           => '💠 تون‌پی',
            'rialit'           => '💚 ریالیت',
            'cubepay'          => '🟦 کیوب‌پی',
            'blupal'           => '💙 بلوپال',
            'variza'           => '💳 واریزا',
            'abangateway'      => '💳 آبان گیت‌وی',
            'atlaspay'         => '🌐 اطلس‌پی',
            'iranpay3'         => '🟢 ارزی ریالی ۳',
            'aqayepardakht'    => '💜 آقای پرداخت',
            'zarinpal'         => '🔷 زرین پال',
            'zarinpey'         => '🔶 زرین پی',
            'paymentnotverify' => '📋 پرداخت بدون تایید',
            'startelegrams'    => '⭐ ستاره تلگرام',
            'piroozpay'        => '💳 پیروزپی',
            'aptdc'            => '🎁 ثبت کد تخفیف (پرداخت)',
            'chargenodiscount' => '➡️ ادامه بدون تخفیف',
            'chargehasdiscount'=> '🎁 ادامه با تخفیف',
            'colselist'        => '❌ بستن لیست',
        ],
    ],
    'user_nav' => [
        'title' => 'تایید / ناوبری', 'icon' => 'circle-check', 'type' => 'inline',
        'desc'  => 'دکمه‌های تایید پرداخت، قوانین، بازگشت و درخواست شماره تلفن.',
        'buttons' => [
            'confirm_pay'      => '💰 پرداخت و دریافت سرویس',
            'confirm_discount' => '🎁 ثبت کد تخفیف',
            'confirm_back'     => '◀️ بازگشت (تایید پرداخت)',
            'rules_accept'     => '✅ قوانین را می‌پذیرم',
            'nav_back'         => '◀️ دکمه بازگشت عمومی',
            'contact_phone'    => '☎️ ارسال شماره تلفن',
            'contact_back'     => '◀️ بازگشت (فرم شماره)',
            'confirmandgetserviceDiscount' => '💰 پرداخت و دریافت (با تخفیف)',
            'tk_cancel'        => '🔙 انصراف (تیکت)',
            'tk_media_yes'     => '🖼 بله (رسانه تیکت)',
            'tk_media_no'      => '✏️ فقط متن (تیکت)',
            'confirmchannel'   => '📑 تایید عضویت کانال',
            'gen_random_uname' => '🎲 ساخت نام کاربری رندوم',
        ],
    ],
    'pay_receipt' => [
        'title' => 'رسید / ارسال پرداخت', 'icon' => 'receipt', 'type' => 'inline',
        'desc'  => 'دکمه‌های ارسال رسید و مرحله پرداخت کارت‌به‌کارت.',
        'buttons' => [
            'pay_sendreceipt'  => '📤 ارسال رسید پرداخت',
            'pay_done'         => '✅ پرداخت را انجام دادم',
            'pay_cancel'       => '❌ انصراف از پرداخت',
            'pay_back'         => '◀️ بازگشت (پرداخت)',
            'pay_wallet_copy'  => '📋 کپی آدرس ولت',
            'pay_card_copy'    => '💳 کپی شماره کارت',
            'pay_check'        => '🔍 بررسی وضعیت پرداخت',
            'cv_use'           => '💳 استفاده از کارت تاییدشده',
            'cv_new'           => '📷 پرداخت با کارت جدید',
            'pay_resume'       => '▶️ ادامه پرداخت قبلی',
        ],
    ],
    'user_subscription' => [
        'title' => 'منوی کاربر — اشتراک', 'icon' => 'cart-check', 'type' => 'inline',
        'desc'  => 'دکمه‌های نمایش جزئیات اشتراک کاربر.',
        'buttons' => [
            'buy_service'      => '🛒 خرید سرویس',
            'helpbtn'          => '📚 آموزش',
            'support'          => '📞 پشتیبانی',
            'Status'           => '📊 وضعیت سرویس',
            'LastTraffic'      => '📊 آخرین ترافیک',
            'usedtraffic'      => '📥 حجم مصرف‌شده',
            'RemainingVolume'  => '🔋 حجم باقیمانده',
            'expirationDate'   => '⏳ تاریخ انقضا',
            'daysleft'         => '📆 روز باقی‌مانده',
            'extravolunme'     => '➕ حجم اضافه',
            'exntedagei'       => '⏳ تمدید زمان',
            'iduser'           => '🆔 آیدی کاربر',
            'username'         => '👤 یوزرنیم',
            'notusernameme'    => '🚫 بدون یوزرنیم',
            'ticketnew'        => '➕ تیکت جدید',
            'supporttickets'   => '🎫 تیکت‌های پشتیبانی',
        ],
    ],
    'user_dynamic_lists' => [
        'title' => 'کاربر — لیست‌های داینامیک', 'icon' => 'server-stack', 'type' => 'inline',
        'desc'  => 'رنگ خانواده دکمه‌های لیست‌های داینامیک سمت کاربر.',
        'buttons' => [
            'product_buy'    => '🛒 لیست محصولات',
            'category_buy'   => '🗂 لیست دسته‌بندی‌ها',
            'time_buy'       => '⏳ لیست زمان',
            'volume_buy'     => '🔋 لیست حجم',
            'panel_buy'      => '🖥 لیست پنل/لوکیشن خرید',
            'helpsection'    => '📚 لیست آیتم‌های آموزش',
            'channel_join'   => '📯 لیست کانال‌های الزامی',
            'custom_volume'  => '✏️ دکمه حجم/زمان دلخواه',
            'product_back'   => '◀️ بازگشت از لیست محصولات',
            'category_back'  => '◀️ بازگشت از لیست دسته‌بندی‌ها',
            'panel_back'     => '◀️ بازگشت از لیست پنل‌ها',
            'service_actions'      => '🛠 اقدامات مدیریت سرویس',
            'user_services_list'   => '📋 لیست سرویس‌های من',
            'paneluser_list'       => '🖥 انتخاب لوکیشن/پنل کاربر',
            'crypto_actions'       => '🪙 اقدامات پرداخت کریپتو',
            'crypto_manual_actions'=> '🔁 اقدامات بررسی دستی هش',
            'user_confirms'        => '✅ تاییدهای کاربر',
            'extra_purchase'       => '➕ خرید حجم/زمان اضافه',
            'ticket_list'          => '🎫 لیست تیکت‌ها',
            'copy_card_num'        => '💳 کپی شماره کارت',
            'copy_card_amount'     => '🪙 کپی مبلغ کارت',
            'copy_text_btn'        => '📋 سایر دکمه‌های کپی',
            'auto_inline_btn'=> '🎨 دکمه‌های تبدیل‌شده از reply',
            'nmstock_actions'      => '📦 اقدامات تحویل کالای دستی',
        ],
    ],
    'recheckcrypto_buttons' => [
        'title' => 'بررسی مجدد هش کریپتو', 'icon' => 'rotate-cw', 'type' => 'inline',
        'desc'  => 'دکمه‌های فلوی بررسی دستی هش کریپتو.',
        'buttons' => [
            'recheckcrypto'                 => '🔁 بررسی مجدد هش کریپتو',
            'rcc_pick_TRX'                  => '🟥 انتخاب ترون',
            'rcc_pick_TON'                  => '🟦 انتخاب تون',
            'rcc_pick_USDT_TRC20'           => '🟢 تتر روی ترون',
            'rcc_pick_USDT_TON'             => '🟢 تتر روی تون',
            'rcc_skip_photo'                => '⏭ ارسال بدون عکس',
            'rcc_cancel'                    => '❌ انصراف',
        ],
    ],
    'invoice_copy_buttons' => [
        'title' => 'کاربر — دکمه‌های پرداخت کریپتو', 'icon' => 'coins', 'type' => 'inline',
        'desc'  => 'رنگ دکمه‌های مسیر پرداخت ارز دیجیتال.',
        'buttons' => [
            'currency_pick' => '💎 انتخاب ارز',
            'mode_external' => '🌍 کیف پول خارجی',
            'copy_wallet'   => '🔗 کپی آدرس کیف پول',
            'copy_amount'   => '🪙 کپی مبلغ',
            'copy_memo'     => '🏷 کپی ممو',
            'paid_submit'   => '✅ پرداخت کردم | ارسال هش',
            'invoice_back'  => '🔙 بازگشت',
            'cancel_hash_input' => '❌ انصراف از ورود هش',
        ],
    ],
    'wallet_checkout' => [
        'title' => 'پرداخت با کیف پول (چک‌اوت)', 'icon' => 'wallet', 'type' => 'inline',
        'desc'  => 'دکمه‌های مرحله چک‌اوت خرید.',
        'buttons' => [
            'ck_use_wallet'   => '💼 استفاده از کیف پول',
            'ck_pay_external' => '💳 پرداخت بدون کیف پول',
            'ck_confirm'      => '✅ تایید و ادامه',
            'ck_cancel'       => '❌ لغو خرید',
            'ck_change'       => '↩️ تغییر انتخاب',
        ],
    ],
    'home_menu' => [
        'title' => 'کاربر — منوی اصلی', 'icon' => 'house', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی اصلی کاربر.',
        'buttons' => [
            'home_buy'         => '🛒 خرید سرویس',
            'home_myservices'  => '📦 سرویس‌های من',
            'home_account'     => '💳 کیف پول',
            'home_tariff'      => '💰 لیست تعرفه',
            'wheel_luck'    => '🎡 گردونه شانس',
            'affiliatesbtn' => '🤝 زیرمجموعه‌گیری',
            'extendbtn'     => '🔄 تمدید سرویس',
            'supportbtns'   => '📞 پشتیبانی',
            'helpbtns'      => '📚 آموزش',
            'usertestbtn'   => '🧪 اکانت تست',
            'agentpanel'    => '🧑‍💼 پنل نمایندگی',
            'requestagent'  => '📝 درخواست نمایندگی',
        ],
    ],
    'misc_buttons' => [
        'title' => 'کاربر — سایر دکمه‌ها', 'icon' => 'more-horizontal', 'type' => 'inline',
        'desc'  => 'دکمه‌های پراکنده سمت کاربر.',
        'buttons' => [
            'acceptrule'            => '✅ قوانین را می‌پذیرم',
            'confirmpaid'           => '✅ پرداخت را تایید کردم',
            'confirmandgetservice'  => '💰 پرداخت و دریافت سرویس',
            'confirmserivce'        => '✅ تایید سرویس',
            'confirmserdiscount'    => '🎁 تایید با کد تخفیف',
            'buyback'               => '◀️ بازگشت به خرید',
        ],
    ],
    'cron_notifications' => [
        'title' => 'کاربر — دکمه‌های کرون‌جاب', 'icon' => 'clock', 'type' => 'inline',
        'desc'  => 'دکمه‌های پیام‌های خودکار کرون‌جاب.',
        'buttons' => [
            'cron_extend'       => '💊 تمدید سرویس (اعلان کرون)',
            'cron_buy_service'  => '🛒 خرید سرویس (پیام همگانی)',
            'cron_start_bot'    => '🚀 شروع ربات (پیام همگانی)',
            'cron_usertest'     => '🧪 اکانت تست (پیام همگانی)',
            'cron_help'         => '📚 آموزش (پیام همگانی)',
            'cron_affiliates'   => '🤝 زیرمجموعه‌گیری (پیام همگانی)',
            'cron_addbalance'   => '💰 افزایش موجودی (پیام همگانی)',
        ],
    ],
    'tonpay_payment' => [
        'title' => 'دکمه‌های پرداخت کارت‌به‌کارت', 'icon' => 'receipt', 'type' => 'inline',
        'desc'  => 'رنگ دکمه‌های صفحه پرداخت کارت‌به‌کارت تون‌پی و اطلس‌پی.',
        'buttons' => tonpayButtonStyleLabels(),
    ],
    'config_list' => [
        'title' => 'لیست کانفیگ (اطلاعات سرویس)', 'icon' => 'key-round', 'type' => 'inline',
        'desc'  => 'کنترل رنگ جدول انتخاب کانفیگ در صفحه اطلاعات سرویس.',
        'buttons' => [
            'cfg_download_one'      => '📥 دانلود یک کانفیگ',
            'cfg_download_all'      => '⚙️ دانلود همه کانفیگ‌ها',
            'cfg_name_cell'         => '✏️ خانه نام کانفیگ',
            'cfg_protocol_cell'     => '🔌 خانه پروتکل',
            'cfg_header_action'     => '⚙️ سرستون کانفیگ',
            'cfg_header_protocol'   => '🔌 سرستون پروتکل',
            'cfg_header_name'       => '✏️ سرستون نام کانفیگ',
            'cfg_page_prev'         => '◀️ صفحه قبلی',
            'cfg_page_next'         => 'بعدی ▶️',
            'cfg_page_indicator'    => '🔢 شماره صفحه',
            'cfg_back_service'      => '↩️ بازگشت به اطلاعات سرویس',
        ],
    ],
];

$ALL_DB_KEYS = array_keys($MENUS);
$CONFIG_ITEM_SECTION = 'config_items';
$CONFIG_ITEM_KEY_RE = '/^[a-f0-9]{16}:(download|name|protocol)$/';
$CONFIG_COLUMNS = [
    'cfg_header_action' => ['key' => 'cfg_download_one', 'role' => 'download'],
    'cfg_header_protocol' => ['key' => 'cfg_protocol_cell', 'role' => 'protocol'],
    'cfg_header_name' => ['key' => 'cfg_name_cell', 'role' => 'name'],
];

$MAIN_TOKEN_MAP = [
    'text_sell'               => ['home_buy', 'خرید سرویس'],
    'text_extend'             => ['extendbtn', 'تمدید سرویس'],
    'text_usertest'           => ['usertestbtn', 'اکانت تست'],
    'text_wheel_luck'         => ['wheel_luck', 'گردونه شانس'],
    'text_Purchased_services' => ['home_myservices', 'سرویس‌های من'],
    'accountwallet'           => ['home_account', 'کیف پول'],
    'text_affiliates'         => ['affiliatesbtn', 'زیرمجموعه‌گیری'],
    'text_Tariff_list'        => ['home_tariff', 'لیست تعرفه'],
    'text_support'            => ['supportbtns', 'پشتیبانی'],
    'text_help'               => ['helpbtns', 'آموزش'],
];

$tonpayFactoryStyles = tonpayButtonStyleDefaults();

$userStylesRaw = [];
foreach ($ALL_DB_KEYS as $_k) {
    $userStylesRaw[$_k] = [];
}
$userStylesRaw[$CONFIG_ITEM_SECTION] = [];

$useBuiltinDefaults = true;
$settingRow = select("setting", "keyboard_styles_all", null, null, "select");
if (is_array($settingRow) && !empty($settingRow['keyboard_styles_all'])) {
    $decoded = json_decode($settingRow['keyboard_styles_all'], true);
    if (is_array($decoded)) {
        if (array_key_exists('_use_defaults', $decoded)) {
            $useBuiltinDefaults = (bool) $decoded['_use_defaults'];
        }
        foreach ($ALL_DB_KEYS as $m) {
            if (!empty($decoded[$m]) && is_array($decoded[$m])) {
                $userStylesRaw[$m] = $decoded[$m];
            }
        }
        if (!empty($decoded[$CONFIG_ITEM_SECTION]) && is_array($decoded[$CONFIG_ITEM_SECTION])) {
            $userStylesRaw[$CONFIG_ITEM_SECTION] = $decoded[$CONFIG_ITEM_SECTION];
        }
    }
}

$factoryDefaultsMap = [];
foreach ($ALL_DB_KEYS as $_k) {
    $factoryDefaultsMap[$_k] = [];
}
if (function_exists('rx_getKeyboardDefaultStyles')) {
    $rxPanelDefaults = rx_getKeyboardDefaultStyles();
    if (is_array($rxPanelDefaults)) {
        foreach ($rxPanelDefaults as $rxPanelSec => $rxPanelPairs) {
            if (!is_array($rxPanelPairs) || empty($rxPanelPairs)) { continue; }
            if (!isset($factoryDefaultsMap[$rxPanelSec])) { $factoryDefaultsMap[$rxPanelSec] = []; }
            foreach ($rxPanelPairs as $rxPanelKey => $rxPanelVal) {
                if (in_array($rxPanelVal, $ALLOWED_STYLES, true)) {
                    $factoryDefaultsMap[$rxPanelSec][$rxPanelKey] = $rxPanelVal;
                }
            }
        }
    }
    unset($rxPanelDefaults, $rxPanelSec, $rxPanelPairs, $rxPanelKey, $rxPanelVal);
}
foreach ($tonpayFactoryStyles as $tonpayStyleKey => $tonpayStyleValue) {
    $factoryDefaultsMap['tonpay_payment'][$tonpayStyleKey] = $tonpayStyleValue;
}
unset($tonpayStyleKey, $tonpayStyleValue);

$rxAutoStyleFor = function (string $btnLabel) use ($ALLOWED_STYLES): string {
    $guessed = function_exists('rx_kb_guess_style_from_text') ? rx_kb_guess_style_from_text($btnLabel) : null;
    return is_string($guessed) && in_array($guessed, $ALLOWED_STYLES, true) ? $guessed : 'primary';
};

$rxFamilyStyle = function (string $menuKey, string $btnKey, string $btnLabel) use ($userStylesRaw, $factoryDefaultsMap, $useBuiltinDefaults, $EXPLICIT_STYLES, $rxAutoStyleFor): string {
    $saved = $userStylesRaw[$menuKey][$btnKey] ?? null;
    if (is_string($saved) && in_array($saved, $EXPLICIT_STYLES, true)) {
        return $saved;
    }
    if (!$useBuiltinDefaults) { return 'default'; }
    $factory = $factoryDefaultsMap[$menuKey][$btnKey] ?? null;
    if (is_string($factory) && in_array($factory, $EXPLICIT_STYLES, true)) { return $factory; }
    return $rxAutoStyleFor($btnLabel);
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (filter_input(INPUT_GET, 'action') === 'check_log')) {
    header('Content-Type: application/json; charset=utf-8');
    $parsed = json_decode((string) ($settingRow['keyboard_styles_all'] ?? ''), true);
    echo json_encode([
        'db_has_data' => !empty($settingRow['keyboard_styles_all']),
        'sections'    => is_array($parsed) ? array_keys($parsed) : [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    header('Content-Type: application/json; charset=utf-8');
    if (!is_array($payload)) {
        error_log('[SKB_SAVE] invalid payload. raw_len=' . strlen($raw));
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
        exit;
    }

    $changes = $payload['changes'] ?? null;
    $mainChanges = $payload['main_changes'] ?? null;
    $client = $payload['client'] ?? null;
    $revision = $payload['revision'] ?? null;
    $valid = ($payload['patch_version'] ?? null) === 1 && is_array($changes) && is_array($mainChanges)
        && is_string($client) && preg_match('/^[a-z0-9-]{16,80}$/', $client) === 1
        && is_int($revision) && $revision > 0;
    if ($valid) {
        foreach ($changes as $menu => $pairs) {
            if (!is_array($pairs) || (!isset($MENUS[$menu]) && $menu !== $CONFIG_ITEM_SECTION)) { $valid = false; break; }
            foreach ($pairs as $key => $style) {
                $keyAllowed = $menu === $CONFIG_ITEM_SECTION
                    ? preg_match($CONFIG_ITEM_KEY_RE, (string) $key) === 1 && $style === null
                    : isset($MENUS[$menu]['buttons'][$key]) && !($menu === 'misc_buttons' && $key === 'confirmpaid');
                if (!$keyAllowed || ($style !== null && (!is_string($style) || !in_array($style, $EXPLICIT_STYLES, true)))) { $valid = false; break 2; }
            }
        }
        foreach ($mainChanges as $token => $style) {
            if (!isset($MAIN_TOKEN_MAP[$token]) || ($style !== null && (!is_string($style) || !in_array($style, $EXPLICIT_STYLES, true)))) { $valid = false; break; }
        }
        if (array_key_exists('_use_defaults', $payload) && !is_bool($payload['_use_defaults'])) { $valid = false; }
    }
    if (!$valid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_patch']);
        exit;
    }

    $revisions = isset($_SESSION['skb_patch_revisions']) && is_array($_SESSION['skb_patch_revisions']) ? $_SESSION['skb_patch_revisions'] : [];
    if (($revisions[$client] ?? 0) >= $revision) {
        echo json_encode(['ok' => true]);
        exit;
    }

    try {
        if (!$pdo->beginTransaction()) { throw new RuntimeException('transaction_failed'); }
        $locked = $pdo->query('SELECT keyboard_styles_all, keyboardmain FROM setting LIMIT 1 FOR UPDATE');
        $latest = $locked ? $locked->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($latest)) { throw new RuntimeException('settings_missing'); }
        $rawStyles = (string) ($latest['keyboard_styles_all'] ?? '');
        $clean = $rawStyles !== '' ? json_decode($rawStyles, true) : [];
        if (!is_array($clean)) { throw new RuntimeException('invalid_saved_styles'); }
        $expected = [];
        foreach ($changes as $menu => $pairs) {
            if (!$pairs) { continue; }
            if (isset($clean[$menu]) && !is_array($clean[$menu])) { throw new RuntimeException('invalid_saved_section'); }
            if (!isset($clean[$menu])) { $clean[$menu] = []; }
            foreach ($pairs as $key => $style) {
                if ($style === null) { unset($clean[$menu][$key]); }
                else { $clean[$menu][$key] = $style; }
            }
        }
        foreach ($CONFIG_COLUMNS as $headerKey => $column) {
            if (!array_key_exists($headerKey, $changes['config_list'] ?? [])) { continue; }
            $columnStyle = $changes['config_list'][$headerKey];
            if ($columnStyle === null) { unset($clean['config_list'][$column['key']]); }
            else { $clean['config_list'][$column['key']] = $columnStyle; }
            if (isset($clean[$CONFIG_ITEM_SECTION]) && !is_array($clean[$CONFIG_ITEM_SECTION])) { throw new RuntimeException('invalid_saved_section'); }
            foreach (array_keys($clean[$CONFIG_ITEM_SECTION] ?? []) as $itemKey) {
                if (preg_match($CONFIG_ITEM_KEY_RE, (string) $itemKey) === 1 && substr($itemKey, strrpos($itemKey, ':') + 1) === $column['role']) {
                    unset($clean[$CONFIG_ITEM_SECTION][$itemKey]);
                }
            }
        }
        if (array_key_exists('_use_defaults', $payload)) { $clean['_use_defaults'] = $payload['_use_defaults']; }
        if ($changes || array_key_exists('_use_defaults', $payload)) {
            $jsonToSave = json_encode($clean, JSON_UNESCAPED_UNICODE);
            if ($jsonToSave === false) { throw new RuntimeException('styles_encoding_failed'); }
            $saveStyles = $pdo->prepare('UPDATE setting SET keyboard_styles_all = ?');
            if (!$saveStyles->execute([$jsonToSave])) { throw new RuntimeException('styles_save_failed'); }
            $expected['keyboard_styles_all'] = $jsonToSave;
        }
        if ($mainChanges) {
            $km = json_decode((string) ($latest['keyboardmain'] ?? ''), true);
            if (!is_array($km) || !isset($km['keyboard']) || !is_array($km['keyboard'])) { throw new RuntimeException('invalid_main_layout'); }
            $applied = [];
            foreach ($km['keyboard'] as &$row) {
                if (!is_array($row)) { continue; }
                foreach ($row as &$button) {
                    $token = is_array($button) ? ($button['text'] ?? '') : (is_string($button) ? $button : '');
                    if (!is_string($token) || !array_key_exists($token, $mainChanges)) { continue; }
                    if (!is_array($button)) { $button = ['text' => $token]; }
                    if ($mainChanges[$token] === null) { unset($button['style']); }
                    else { $button['style'] = $mainChanges[$token]; }
                    $applied[$token] = true;
                }
                unset($button);
            }
            unset($row);
            if (count($applied) !== count($mainChanges)) { throw new RuntimeException('main_layout_changed'); }
            $mainJson = json_encode($km, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($mainJson === false) { throw new RuntimeException('main_encoding_failed'); }
            $saveMain = $pdo->prepare('UPDATE setting SET keyboardmain = ?');
            if (!$saveMain->execute([$mainJson])) { throw new RuntimeException('main_save_failed'); }
            $expected['keyboardmain'] = $mainJson;
        }
        $verify = $pdo->query('SELECT keyboard_styles_all, keyboardmain FROM setting LIMIT 1');
        $saved = $verify ? $verify->fetch(PDO::FETCH_ASSOC) : false;
        foreach ($expected as $field => $value) {
            if (!is_array($saved) || ($saved[$field] ?? null) !== $value) { throw new RuntimeException('save_verification_failed'); }
        }
        if (!$pdo->commit()) { throw new RuntimeException('commit_failed'); }
        unset($revisions[$client]);
        $revisions[$client] = $revision;
        $_SESSION['skb_patch_revisions'] = array_slice($revisions, -64, null, true);
        if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('[SKB_SAVE] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'save_failed']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

$keyboardMain = [];
$kmRows = [];
$kmRow = select("setting", "keyboardmain", null, null, "select", ['cache' => false]);
$kmRaw = is_array($kmRow) ? (string) ($kmRow['keyboardmain'] ?? '') : '';
if ($kmRaw !== '') {
    $kmDecoded = json_decode($kmRaw, true);
    if (is_array($kmDecoded) && isset($kmDecoded['keyboard']) && is_array($kmDecoded['keyboard'])) {
        $kmRows = $kmDecoded['keyboard'];
    }
}
foreach ($kmRows as $kmRowEach) {
    if (!is_array($kmRowEach)) { continue; }
    foreach ($kmRowEach as $kmBtnEach) {
        if (!is_array($kmBtnEach)) { continue; }
        $token = (string) ($kmBtnEach['text'] ?? '');
        $style = (string) ($kmBtnEach['style'] ?? '');
        if ($token !== '' && in_array($style, $EXPLICIT_STYLES, true)) {
            $keyboardMain[$token] = $style;
        }
    }
}

$rxMainExact = function (string $token) use ($keyboardMain, $EXPLICIT_STYLES): ?string {
    $style = $keyboardMain[$token] ?? null;
    return (is_string($style) && in_array($style, $EXPLICIT_STYLES, true)) ? $style : null;
};

$textbotMap = [];
try {
    $textbotRows = select("textbot", "*", null, null, "fetchAll");
    if (is_array($textbotRows)) {
        foreach ($textbotRows as $row) {
            if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                $textbotMap[(string) $row['id_text']] = (string) $row['text'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('[panel/service_keyboard] textbot load failed: ' . $e->getMessage());
}

$paySettings = [];
try {
    $paySettingRows = select("PaySetting", "*", null, null, "fetchAll");
    if (is_array($paySettingRows)) {
        foreach ($paySettingRows as $paySettingRow) {
            if (isset($paySettingRow['NamePay'])) {
                $paySettings[(string) $paySettingRow['NamePay']] = (string) ($paySettingRow['ValuePay'] ?? '');
            }
        }
    }
} catch (Throwable $e) {
    error_log('[panel/service_keyboard] PaySetting load failed: ' . $e->getMessage());
}
$payValue = function (string $name) use ($paySettings): string {
    return $paySettings[$name] ?? '';
};

$accountRegistry = function_exists('rx_account_button_registry') ? rx_account_button_registry() : [];
$accountLayoutRaw = '';
try {
    $accountLayoutRow = select("setting", "account_keyboard_layout", null, null, "select", ['cache' => false]);
    $accountLayoutRaw = is_array($accountLayoutRow) ? trim((string) ($accountLayoutRow['account_keyboard_layout'] ?? '')) : '';
} catch (Throwable $e) {
    $accountLayoutRaw = '';
}
if ($accountLayoutRaw === '') {
    $accountLayout = function_exists('rx_account_default_layout') ? rx_account_default_layout() : [];
} else {
    $accountLayout = function_exists('rx_account_layout_normalize') ? rx_account_layout_normalize($accountLayoutRaw) : [];
}
$accountLabelFor = function (string $id) use ($accountRegistry, $textbotMap): string {
    $def = $accountRegistry[$id] ?? null;
    if (!is_array($def)) { return $id; }
    $textbotKey = $def['textbot'] ?? null;
    if (is_string($textbotKey) && isset($textbotMap[$textbotKey]) && trim($textbotMap[$textbotKey]) !== '') {
        return $textbotMap[$textbotKey];
    }
    return (string) ($def['label'] ?? $id);
};

$mkBtn = function (string $menu, string $key, array $opt = []) use ($MENUS, $rxAutoStyleFor, $rxFamilyStyle): array {
    $label = $opt['label'] ?? (string) ($MENUS[$menu]['buttons'][$key] ?? $key);
    $auto = $rxAutoStyleFor($label);
    $style = $rxFamilyStyle($menu, $key, $label);
    return array_merge([
        'kind' => 'family',
        'menu' => $menu,
        'key' => $key,
        'label' => $label,
        'auto' => $auto,
        'style' => $style,
        'scopes' => ['family'],
    ], $opt);
};

$mkMain = function (string $token, string $familyKey, string $label) use ($rxAutoStyleFor, $rxFamilyStyle, $rxMainExact): array {
    $auto = $rxAutoStyleFor($label);
    $exact = $rxMainExact($token);
    return [
        'kind' => 'main',
        'menu' => 'home_menu',
        'key' => $familyKey,
        'token' => $token,
        'label' => $label,
        'auto' => $auto,
        'style' => $exact !== null ? $exact : $rxFamilyStyle('home_menu', $familyKey, $label),
        'scopes' => ['exact', 'family'],
        'family_label' => 'رنگ پیش‌فرض نقش این دکمه',
    ];
};

$mkCfgHead = function (string $key, string $label) use ($rxAutoStyleFor, $rxFamilyStyle): array {
    $auto = $rxAutoStyleFor($label);
    return [
        'kind' => 'config_header',
        'menu' => 'config_list',
        'key' => $key,
        'label' => $label,
        'auto' => $auto,
        'style' => $rxFamilyStyle('config_list', $key, $label),
        'scopes' => [],
        'header' => true,
    ];
};

$SCREENS = [];
$addScreen = function (string $id, string $title, string $icon, string $desc, array $rows, array $opt = []) use (&$SCREENS): void {
    $SCREENS[$id] = array_merge([
        'id' => $id,
        'title' => $title,
        'icon' => $icon,
        'desc' => $desc,
        'rows' => $rows,
        'note' => '',
        'link' => null,
        'link_label' => '',
        'table' => false,
    ], $opt);
};

$svc = function (string $key, array $opt = []) use ($mkBtn): array {
    return $mkBtn('service', $key, $opt);
};
$serviceRows = [
    [$svc('updateinfo', ['cond' => 'همیشه در ابتدای منوی سرویس نمایش داده می‌شود.'])],
    [$svc('linksub', ['cond' => 'در پنل‌هایی که لینک اشتراک فعال دارند.']), $svc('config', ['cond' => 'بسته به نوع پنل و تنظیم نمایش دریافت کانفیگ.'])],
    [$svc('extend'), $svc('changelink', ['cond' => 'در پنل‌هایی که تغییر لینک فعال است.'])],
    [$svc('removeservice', ['cond' => 'اگر حذف سرویس توسط کاربر مجاز باشد.']), $svc('changenameconfig', ['cond' => 'اگر تغییر نام/یادداشت سرویس فعال باشد.'])],
    [$svc('Extra_volume', ['cond' => 'اگر خرید حجم اضافه برای این پنل فعال باشد.']), $svc('Extra_time', ['cond' => 'اگر خرید زمان اضافه فعال باشد.'])],
    [$svc('changestatus', ['cond' => 'اگر تغییر وضعیت سرویس مجاز باشد.']), $svc('transfor', ['cond' => 'برای سرویس تست و پنل رمنـاوِیو نمایش داده نمی‌شود.'])],
    [$svc('change-location', ['cond' => 'اگر تغییر لوکیشن در پنل فعال باشد.']), $svc('ekhtelal', ['cond' => 'اگر ارسال گزارش اختلال فعال باشد.'])],
    [$svc('backorder')],
    [$svc('remna_usage', ['cond' => 'فقط برای پنل‌های رمنـاوِیو.']), $svc('remna_picker', ['cond' => 'فقط برای پنل‌های رمنـاوِیو با انتخاب سرور فعال.'])],
    [$svc('discountextend', ['cond' => 'در صفحه تأیید تمدید با کد تخفیف.']), $svc('productcheckdata', ['cond' => 'در دکمه‌های بازگشت به اطلاعات سرویس.'])],
];
$addScreen('service', 'مدیریت سرویس', 'gear', 'کیبورد مدیریت یک سرویس، همان‌طور که کاربر می‌بیند.', $serviceRows, [
    'note' => 'بعضی دکمه‌ها بسته به نوع پنل و تنظیمات آن نمایش داده می‌شوند؛ رنگ انتخابی برای زمانی است که دکمه ظاهر شود.',
]);

$cfgRows = [];
$cfgRows[] = [
    $mkCfgHead('cfg_header_action', '⚙️ کانفیگ'),
    $mkCfgHead('cfg_header_protocol', '🔌 پروتکل'),
    $mkCfgHead('cfg_header_name', '✏️ نام کانفیگ'),
];
$cfgRows[] = [
    $mkBtn('config_list', 'cfg_page_prev', ['label' => '◀️ قبلی']),
    $mkBtn('config_list', 'cfg_page_indicator', ['label' => '۱ / ۲']),
    $mkBtn('config_list', 'cfg_page_next', ['label' => 'بعدی ▶️']),
];
$cfgRows[] = [$mkBtn('config_list', 'cfg_download_all', ['label' => '⚙️ دریافت همه کانفیگ‌ها'])];
$cfgRows[] = [$mkBtn('config_list', 'cfg_back_service', ['label' => '↩️ بازگشت'])];
$addScreen('config', 'لیست کانفیگ', 'key-round', 'جدول انتخاب کانفیگ در صفحه اطلاعات سرویس.', $cfgRows, [
    'note' => 'رنگ «نام کانفیگ»، «پروتکل» و «کانفیگ» روی سرستون و تمام دکمه‌های همان ستون اعمال می‌شود. با ذخیرهٔ رنگ ستون، رنگ‌های اختصاصی قبلی همان ستون پاک می‌شوند.',
    'table' => true,
]);

$accRows = [];
foreach ($accountLayout as $accRowIds) {
    if (!is_array($accRowIds)) { continue; }
    $outRow = [];
    foreach ($accRowIds as $accId) {
        if (!is_string($accId) || !isset($accountRegistry[$accId])) { continue; }
        $cond = (string) ($accountRegistry[$accId]['note'] ?? '');
        $outRow[] = $mkBtn('account', $accId, ['label' => $accountLabelFor($accId), 'cond' => $cond]);
    }
    if (!empty($outRow)) { $accRows[] = $outRow; }
}
if (empty($accRows)) {
    $accRows = [
        [$mkBtn('account', 'Discount'), $mkBtn('account', 'Add_Balance')],
        [$mkBtn('account', 'TransferBalance'), $mkBtn('account', 'MyTransactions')],
        [$mkBtn('account', 'supporttickets')],
        [$mkBtn('account', 'backuser')],
    ];
}
$accRows[] = [
    $mkBtn('user_nav', 'gen_random_uname', ['cond' => 'در فرم انتخاب نام کاربری سرویس.']),
];
$addScreen('account', 'کیف پول و حساب', 'user', 'همان چیدمان کیبورد کیف پول/حساب کاربر.', $accRows, [
    'note' => 'دکمه‌های شرطی فقط در شرایط مشخص‌شده نمایش داده می‌شوند. چیدمان این صفحه در «چیدمان حساب» تنظیم می‌شود.',
    'link' => 'account_layout.php',
    'link_label' => 'ویرایش چیدمان حساب',
]);

$txRows = [
    [$mkBtn('tx_stats', 'tx_24h'), $mkBtn('tx_stats', 'tx_3d')],
    [$mkBtn('tx_stats', 'tx_7d'), $mkBtn('tx_stats', 'tx_30d')],
    [$mkBtn('tx_stats', 'tx_prev'), $mkBtn('tx_stats', 'tx_next')],
    [$mkBtn('tx_stats', 'tx_back')],
];
$addScreen('transactions', 'تراکنش‌های من', 'clock', 'فیلتر بازه زمانی و صفحه‌بندی تراکنش‌های کاربر.', $txRows, [
    'note' => 'این ردیف‌ها فقط وقتی کاربر تراکنش ثبت‌شده دارد نمایش داده می‌شوند.',
]);

$walletRows = [
    [
        $mkBtn('account', 'rw_pick', ['label' => '۵۰,۰۰۰ تومان', 'family_label' => 'همه دکمه‌های مبلغ شارژ کیف پول']),
        $mkBtn('account', 'rw_pick', ['label' => '۱۰۰,۰۰۰ تومان', 'family_label' => 'همه دکمه‌های مبلغ شارژ کیف پول']),
    ],
    [
        $mkBtn('account', 'rw_pick', ['label' => '۲۰۰,۰۰۰ تومان', 'family_label' => 'همه دکمه‌های مبلغ شارژ کیف پول']),
    ],
    [$mkBtn('account', 'rw_custom')],
    [$mkBtn('account', 'transferbal_confirm'), $mkBtn('account', 'transferbal_cancel')],
    [$mkBtn('account', 'backinfo', ['cond' => 'در دکمه‌های بازگشت به اطلاعات سرویس و بازگشت از شارژ کیف پول.'])],
];
$addScreen('wallet', 'انتقال و شارژ کیف پول', 'wallet', 'مبالغ پیشنهادی شارژ، رقم دلخواه و تأیید انتقال موجودی.', $walletRows, [
    'note' => 'دکمه‌های مبلغ، یک خانواده هستند؛ تغییر رنگ هر کدام روی همه مبالغ پیشنهادی اعمال می‌شود.',
]);

$checkoutRows = [
    [$mkBtn('wallet_checkout', 'ck_use_wallet')],
    [$mkBtn('wallet_checkout', 'ck_pay_external')],
    [$mkBtn('wallet_checkout', 'ck_cancel')],
    [$mkBtn('wallet_checkout', 'ck_confirm'), $mkBtn('wallet_checkout', 'ck_change')],
];
$addScreen('checkout', 'چک‌اوت خرید', 'cart', 'انتخاب روش پرداخت و تأیید چک‌اوت.', $checkoutRows, [
    'note' => '«استفاده از کیف پول» فقط وقتی موجودی کافی باشد نمایش داده می‌شود. دکمه لغو در هر دو مرحله همین رنگ را دارد.',
]);

$confirmRows = [
    [$mkBtn('user_nav', 'confirm_pay')],
    [$mkBtn('user_nav', 'confirm_discount')],
    [$mkBtn('payment', 'chargehasdiscount'), $mkBtn('payment', 'chargenodiscount')],
    [$mkBtn('user_nav', 'confirmandgetserviceDiscount')],
    [$mkBtn('user_nav', 'rules_accept')],
    [$mkBtn('user_nav', 'contact_phone'), $mkBtn('user_nav', 'contact_back')],
    [$mkBtn('user_nav', 'confirmchannel')],
    [$mkBtn('misc_buttons', 'confirmserivce'), $mkBtn('misc_buttons', 'confirmserdiscount')],
    [$mkBtn('misc_buttons', 'buyback')],
];
$addScreen('confirm', 'تأیید پرداخت', 'circle-check', 'دکمه‌های تأیید، تخفیف، قوانین و شماره تلفن.', $confirmRows, [
    'note' => 'دکمه «پرداخت و دریافت سرویس» در این صفحه با دکمه هم‌نام منوی سرویس متفاوت است.',
]);

$gwRows = [];
$gwDefs = [
    ['cart_to_offline', '💳 کارت به کارت', 'Cartstatus', 'oncard', 'کارت‌به‌کارت', 'carttocart'],
    ['plisio', '🔵 Plisio', 'nowpaymentstatus', 'onnowpayment', 'Plisio', 'textnowpayment'],
    ['nowpayment', '🟣 NowPayment', 'statusnowpayment', '1', 'NowPayments', 'textsnowpayment'],
    ['digitaltron', '🟡 رمزارز ترون', 'digistatus', 'ondigi', 'پرداخت رمزارز ترون', 'textnowpaymenttron'],
    ['iranpay2', '🟠 ترونادو', 'statustarnado', 'onternado', 'ترونادو', 'iranpay3'],
    ['tonpay', '💠 تون‌پی', 'statustonpay', 'ontonpay', 'تون‌پی', 'tonpay'],
    ['rialit', '💚 ریالیت', 'statusrialit', 'onrialit', 'ریالیت', 'rialit'],
    ['cubepay', '🟦 کیوب‌پی', 'statuscubepay', 'oncubepay', 'کیوب‌پی', 'cubepay'],
    ['blupal', '💙 بلوپال', 'statusblupal', 'onblupal', 'بلوپال', 'blupal'],
    ['variza', '💳 واریزا', 'statusvariza', 'onvariza', 'واریزا', 'variza'],
    ['abangateway', '💳 آبان گیت‌وی', 'statusabangateway', 'onabangateway', 'آبان گیت‌وی', 'abangateway'],
    ['atlaspay', '🌐 اطلس‌پی', 'statusatlaspay', 'onatlaspay', 'اطلس‌پی', 'atlaspay'],
    ['zarinpal', '🔷 زرین پال', 'zarinpalstatus', 'onzarinpal', 'زرین‌پال', 'zarinpal'],
    ['paymentnotverify', '📋 پرداخت بدون تایید', 'paymentstatussnotverify', 'onverifypay', 'درگاه ریالی بدون تأیید', 'textpaymentnotverify'],
    ['startelegrams', '⭐ ستاره تلگرام', 'statusstar', '1', 'ستاره تلگرام', 'text_star_telegram'],
];
foreach ($gwDefs as $gwDef) {
    $active = $payValue($gwDef[2]) === $gwDef[3];
    if ($gwDef[0] === 'abangateway') {
        $active = $active && stripos(trim($payValue('urlabangateway')), 'https://') === 0 && trim($payValue('apiabangateway')) !== '' && trim($payValue('apiabangateway')) !== '0';
    }
    $note = $active
        ? 'این درگاه در تنظیمات پرداخت فعال است.'
        : 'این درگاه اکنون غیرفعال است؛ رنگ آن پس از فعال‌سازی اعمال می‌شود.';
    if ($gwDef[0] === 'cart_to_offline' && $payValue('Cartstatuspv') === 'oncardpv') {
        $note .= ' در حالت آفلاین پیوی، دکمه به‌صورت لینک نمایش داده می‌شود.';
    }
    $gwLabel = trim((string) ($textbotMap[$gwDef[5]] ?? '')) !== '' ? (string) $textbotMap[$gwDef[5]] : $gwDef[1];
    $gwRows[] = [
        $mkBtn('payment', $gwDef[0], [
            'label' => $gwLabel,
            'cond' => $note,
            'badge' => $active ? 'فعال' : 'غیرفعال',
            'badge_class' => $active ? 'is-on' : 'is-off',
        ]),
    ];
}
$addScreen('gateways', 'درگاه‌های پرداخت', 'dollar-sign', 'انتخاب روش پرداخت هنگام خرید.', $gwRows, [
    'note' => 'همه درگاه‌های پشتیبانی‌شده نمایش داده می‌شوند تا رنگ آن‌ها پیش از فعال‌سازی هم قابل تنظیم باشد. وضعیت فعال/غیرفعال از تنظیمات پرداخت خوانده می‌شود.',
]);

$receiptRows = [
    [$mkBtn('pay_receipt', 'pay_sendreceipt')],
    [$mkBtn('pay_receipt', 'cv_use'), $mkBtn('pay_receipt', 'cv_new')],
    [$mkBtn('pay_receipt', 'pay_resume')],
    [$mkBtn('pay_receipt', 'pay_cancel')],
];
$addScreen('receipt', 'رسید و پرداخت کارت', 'receipt', 'ارسال رسید، کارت تأییدشده و مدیریت پرداخت در انتظار.', $receiptRows, [
    'note' => 'دکمه‌های «ادامه پرداخت قبلی» و «لغو پرداخت» فقط وقتی پرداخت در انتظار وجود داشته باشد نمایش داده می‌شوند.',
]);

$cryptoRows = [
    [
        $mkBtn('invoice_copy_buttons', 'currency_pick', ['label' => '🟥 ترون (TRX)', 'family_label' => 'همه دکمه‌های انتخاب ارز']),
        $mkBtn('invoice_copy_buttons', 'currency_pick', ['label' => '🟦 تون (TON)', 'family_label' => 'همه دکمه‌های انتخاب ارز']),
    ],
    [
        $mkBtn('invoice_copy_buttons', 'currency_pick', ['label' => '🟢 تتر روی ترون', 'family_label' => 'همه دکمه‌های انتخاب ارز']),
        $mkBtn('invoice_copy_buttons', 'currency_pick', ['label' => '🟢 تتر روی تون', 'family_label' => 'همه دکمه‌های انتخاب ارز']),
    ],
    [$mkBtn('invoice_copy_buttons', 'copy_wallet'), $mkBtn('invoice_copy_buttons', 'copy_amount')],
    [$mkBtn('invoice_copy_buttons', 'copy_memo')],
    [$mkBtn('invoice_copy_buttons', 'paid_submit')],
    [$mkBtn('invoice_copy_buttons', 'invoice_back')],
    [$mkBtn('invoice_copy_buttons', 'cancel_hash_input')],
    [$mkBtn('payment', 'colselist')],
];
$addScreen('crypto', 'پرداخت کریپتو', 'coins', 'انتخاب ارز، کپی آدرس/مبلغ/ممو و ارسال هش پرداخت.', $cryptoRows, [
    'note' => 'دکمه‌های انتخاب ارز یک خانواده‌اند. دکمه «کپی ممو» فقط در شبکه تون نمایش داده می‌شود.',
]);

$manualRows = [
    [
        $mkBtn('user_dynamic_lists', 'crypto_manual_actions', ['label' => '📨 ارسال مجدد به ادمین', 'family_label' => 'همه اقدامات بررسی دستی هش']),
        $mkBtn('user_dynamic_lists', 'crypto_manual_actions', ['label' => '👁 مشاهده وضعیت', 'family_label' => 'همه اقدامات بررسی دستی هش']),
    ],
    [$mkBtn('recheckcrypto_buttons', 'rcc_skip_photo'), $mkBtn('recheckcrypto_buttons', 'rcc_cancel')],
];
$addScreen('crypto_manual', 'بررسی دستی هش', 'rotate-cw', 'دکمه‌های بررسی مجدد هش و اقدامات دستی.', $manualRows, [
    'note' => 'اقدامات بررسی دستی یک خانواده هستند و رنگ آن‌ها از تنظیم خانواده خوانده می‌شود. دکمه ورود به این بخش در صفحه «کیف پول و حساب» قابل ویرایش است.',
]);

$subRows = [
    [$mkBtn('user_subscription', 'username')],
    [$mkBtn('user_subscription', 'Status')],
    [$mkBtn('user_subscription', 'expirationDate')],
    [$mkBtn('user_subscription', 'daysleft')],
    [$mkBtn('user_subscription', 'LastTraffic')],
    [$mkBtn('user_subscription', 'usedtraffic')],
    [$mkBtn('user_subscription', 'RemainingVolume')],
    [$mkBtn('user_subscription', 'exntedagei'), $mkBtn('user_subscription', 'extravolunme')],
    [$mkBtn('user_subscription', 'buy_service')],
    [$mkBtn('user_subscription', 'helpbtn'), $mkBtn('user_subscription', 'notusernameme')],
];
$addScreen('subscription', 'اشتراک و سرویس‌های من', 'cart-check', 'جزئیات سرویس و دکمه‌های سرویس‌های من.', $subRows, [
    'note' => 'دکمه‌های «تمدید» و «خرید حجم» در لیست سرویس‌ها از خانواده «اقدامات مدیریت سرویس» رنگ می‌گیرند که در صفحه «لیست‌های داینامیک» قابل تنظیم است. مقادیر داخل دکمه‌ها برای هر کاربر متفاوت است.',
]);

$ticketRows = [
    [$mkBtn('user_subscription', 'ticketnew')],
    [$mkBtn('account', 'supporttickets')],
    [$mkBtn('user_nav', 'tk_media_yes'), $mkBtn('user_nav', 'tk_media_no')],
    [$mkBtn('user_nav', 'tk_cancel')],
    [$mkBtn('user_dynamic_lists', 'ticket_list', ['label' => '🎫 لیست تیکت‌ها', 'family_label' => 'همه دکمه‌های لیست تیکت'])],
];
$addScreen('tickets', 'تیکت و پشتیبانی', 'headset', 'دکمه‌های تیکت جدید، لیست تیکت‌ها و انتخاب رسانه.', $ticketRows, [
    'note' => 'دکمه‌های بله/خیر رسانه فقط در مرحله ارسال تیکت نمایش داده می‌شوند.',
]);

$listRows = [
    [$mkBtn('user_dynamic_lists', 'product_buy')],
    [$mkBtn('user_dynamic_lists', 'category_buy')],
    [$mkBtn('user_dynamic_lists', 'time_buy'), $mkBtn('user_dynamic_lists', 'volume_buy')],
    [$mkBtn('user_dynamic_lists', 'custom_volume')],
    [$mkBtn('user_dynamic_lists', 'product_back'), $mkBtn('user_dynamic_lists', 'category_back')],
    [$mkBtn('user_dynamic_lists', 'panel_back')],
    [$mkBtn('user_dynamic_lists', 'panel_buy'), $mkBtn('user_dynamic_lists', 'paneluser_list')],
    [$mkBtn('user_dynamic_lists', 'helpsection')],
    [$mkBtn('user_dynamic_lists', 'user_services_list')],
    [$mkBtn('user_dynamic_lists', 'service_actions')],
    [$mkBtn('user_dynamic_lists', 'extra_purchase')],
    [$mkBtn('user_dynamic_lists', 'crypto_actions')],
    [$mkBtn('user_dynamic_lists', 'user_confirms')],
    [$mkBtn('user_dynamic_lists', 'copy_card_num'), $mkBtn('user_dynamic_lists', 'copy_card_amount')],
    [$mkBtn('user_dynamic_lists', 'copy_text_btn'), $mkBtn('user_dynamic_lists', 'auto_inline_btn')],
    [$mkBtn('user_dynamic_lists', 'nmstock_actions')],
];
$addScreen('lists', 'لیست‌های داینامیک', 'server-stack', 'رنگ خانواده دکمه‌های لیست‌هایی که به‌صورت پویا ساخته می‌شوند.', $listRows, [
    'note' => 'هر کارت این صفحه یک خانواده است و رنگ همه دکمه‌های آن لیست را با هم تنظیم می‌کند؛ برای همین به‌جای آیتم‌های تکی نمایش داده شده است.',
]);

$cronRows = [
    [$mkBtn('cron_notifications', 'cron_extend')],
    [$mkBtn('cron_notifications', 'cron_buy_service')],
    [$mkBtn('cron_notifications', 'cron_start_bot')],
    [$mkBtn('cron_notifications', 'cron_usertest'), $mkBtn('cron_notifications', 'cron_help')],
    [$mkBtn('cron_notifications', 'cron_affiliates'), $mkBtn('cron_notifications', 'cron_addbalance')],
];
$addScreen('cron', 'اعلان‌های کرون', 'bell', 'دکمه‌های پیام‌های خودکار کرون‌جاب.', $cronRows, [
    'note' => 'این رنگ‌ها در پیام‌های اعلان حجم/زمان، پیام‌های همگانی و یادآوری‌ها استفاده می‌شوند.',
]);

$tonpayRows = [
    [$mkBtn('tonpay_payment', 'copy_card'), $mkBtn('tonpay_payment', 'copy_amount')],
    [$mkBtn('tonpay_payment', 'change_card')],
    [$mkBtn('tonpay_payment', 'check_status')],
    [$mkBtn('tonpay_payment', 'send_receipt')],
    [$mkBtn('tonpay_payment', 'cancel_transaction')],
    [$mkBtn('tonpay_payment', 'back_main')],
    [$mkBtn('tonpay_payment', 'cancel_confirm_approve')],
    [$mkBtn('tonpay_payment', 'cancel_confirm_back')],
];
$addScreen('tonpay', 'پرداخت کارت‌به‌کارت', 'credit-card', 'دکمه‌های پرداخت کارت‌به‌کارت تون‌پی و اطلس‌پی.', $tonpayRows, [
    'note' => 'این تنظیم‌ها روی صفحه پرداخت کارت‌به‌کارت تون‌پی و اطلس‌پی (درگاه‌های سفارشی) اعمال می‌شوند.',
]);

$rxStylesForJs = [];
foreach ($ALL_DB_KEYS as $_k) {
    $rxStylesForJs[$_k] = empty($userStylesRaw[$_k]) ? new stdClass() : $userStylesRaw[$_k];
}
$rxStylesForJs[$CONFIG_ITEM_SECTION] = empty($userStylesRaw[$CONFIG_ITEM_SECTION]) ? new stdClass() : $userStylesRaw[$CONFIG_ITEM_SECTION];

$rxFactoryForJs = [];
foreach ($ALL_DB_KEYS as $_k) {
    $rxFactoryForJs[$_k] = empty($factoryDefaultsMap[$_k]) ? new stdClass() : $factoryDefaultsMap[$_k];
}

$firstScreenId = array_key_first($SCREENS);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-color="blue">
<head>
    <script>
    (function(){try{var t=localStorage.getItem('faoxima_theme');
    if(t!=='light'&&t!=='dark')t='dark';
    document.documentElement.setAttribute('data-theme',t);
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>رنگ‌بندی دکمه‌ها — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        body { padding-top:0 !important; --skb-top-h:58px; }
        .skb-top {
            position:fixed; top:0; left:0; right:0; z-index:1000;
            display:flex; align-items:center; gap:10px 14px;
            padding:calc(11px + env(safe-area-inset-top)) calc(18px + env(safe-area-inset-right)) 11px calc(18px + env(safe-area-inset-left));
            background:var(--surface-1); border-bottom:1px solid var(--border-soft);
            box-shadow:var(--shadow-1); flex-wrap:wrap; justify-content:space-between;
        }
        .skb-brand { display:flex; align-items:center; gap:10px; font-weight:800; color:var(--accent); font-size:15px; letter-spacing:-.2px; min-width:0; }
        .skb-brand .lm { width:30px; height:30px; display:grid; place-items:center; background:#fff; border-radius:50%; overflow:hidden; flex-shrink:0; }
        .skb-brand .lm img { width:100%; height:100%; object-fit:contain; transform:scale(1.35); display:block; }
        .skb-top-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; min-width:0; }
        .skb-top-actions > * { flex:0 0 auto; }
        .skb-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:100px; background:var(--accent-soft); border:1px solid var(--accent-mid); color:var(--text-muted); font-size:12px; white-space:nowrap; }
        .skb-toggle { display:flex; align-items:center; gap:7px; padding:6px 11px; border:1px solid var(--border-soft); border-radius:100px; background:var(--surface-1); font-size:12px; color:var(--text-muted); cursor:pointer; user-select:none; }
        .skb-toggle input { accent-color:var(--accent); width:14px; height:14px; cursor:pointer; }
        .skb-badge { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
        .skb-badge .dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
        .skb-badge.saving { color:#f59e0b; }
        .skb-badge.saved { color:#22c55e; }
        .skb-top .btn { border-radius:100px; gap:6px; }
        .skb-shell { max-width:1420px; margin:0 auto; padding:calc(var(--skb-top-h) + 18px) 18px calc(56px + env(safe-area-inset-bottom)); }
        .skb-layout { display:grid; grid-template-columns:248px minmax(0,1fr); gap:18px; align-items:start; }
        .skb-nav {
            position:sticky; top:calc(var(--skb-top-h) + 14px);
            background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-lg);
            padding:8px; max-height:calc(100dvh - var(--skb-top-h) - 30px); overflow:auto;
        }
        .skb-nav-title { font-size:11px; font-weight:700; color:var(--text-dim); padding:8px 10px 6px; }
        .skb-nav-item {
            display:flex; align-items:center; gap:9px; width:100%; text-align:start;
            padding:10px 11px; border:0; border-radius:var(--radius-md); background:none; cursor:pointer;
            color:var(--text-muted); font-family:'Vazirmatn',sans-serif; font-size:12.5px; font-weight:600;
        }
        .skb-nav-item .svg-icon { width:16px; height:16px; flex-shrink:0; }
        .skb-nav-item:hover { background:var(--surface-2); color:var(--text-main); }
        .skb-nav-item.active { background:var(--accent-soft); color:var(--accent); box-shadow:inset 0 0 0 1px var(--accent-mid); }
        .skb-nav-item:focus-visible { outline:2px solid var(--text-main); outline-offset:1px; }
        .skb-mobile-nav { display:none; position:sticky; top:calc(var(--skb-top-h) - 1px); z-index:30; margin-bottom:12px; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-lg); padding:8px; box-shadow:var(--shadow-1); }
        .skb-mobile-nav label { display:block; font-size:11px; color:var(--text-dim); margin:0 4px 6px; }
        .skb-mobile-nav select { width:100%; padding:10px 12px; border-radius:var(--radius-md); border:1px solid var(--border-mid); background:var(--surface-2); color:var(--text-main); font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:700; direction:rtl; }
        .skb-section-trigger { display:flex; align-items:center; gap:10px; width:100%; min-height:48px; padding:10px 12px; border:1px solid var(--border-mid); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-main); font:inherit; font-size:13px; font-weight:700; text-align:start; cursor:pointer; }
        .skb-section-trigger > span { flex:1; min-width:0; overflow-wrap:anywhere; }
        .skb-section-trigger > svg { width:18px; height:18px; flex-shrink:0; color:var(--accent); transition:transform .15s; }
        .skb-section-trigger[aria-expanded="true"] { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .skb-section-trigger[aria-expanded="true"] > svg { transform:rotate(180deg); }
        .skb-section-trigger:focus-visible, .skb-section-option:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
        .skb-section-menu { position:fixed; z-index:1100; display:flex; flex-direction:column; box-sizing:border-box; padding:8px; border:1px solid var(--border-mid); border-radius:var(--radius-lg); background:var(--surface-1); color:var(--text-main); box-shadow:0 14px 42px rgba(0,0,0,.22); direction:rtl; overflow:hidden; }
        .skb-section-menu[hidden], .skb-section-option[hidden], .skb-section-empty[hidden] { display:none; }
        .skb-section-search { flex-shrink:0; box-sizing:border-box; width:100%; min-height:44px; padding:10px 12px; margin-bottom:7px; border:1px solid var(--border-soft); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-main); font:inherit; font-size:12px; outline:none; }
        .skb-section-search:focus { border-color:var(--accent); }
        .skb-section-list { min-height:0; overflow-y:auto; overscroll-behavior:contain; scrollbar-width:thin; padding:2px; }
        .skb-section-option { display:flex; align-items:center; gap:10px; width:100%; min-height:46px; padding:10px; border:0; border-radius:var(--radius-sm); background:transparent; color:var(--text-main); text-align:start; font:inherit; font-size:12.5px; cursor:pointer; }
        .skb-section-option > .svg-icon { width:18px; height:18px; flex-shrink:0; color:var(--text-muted); }
        .skb-section-option > span { flex:1; }
        .skb-section-option::after { content:'✓'; opacity:0; color:var(--accent); font-weight:800; }
        .skb-section-option:hover { background:var(--surface-2); }
        .skb-section-option[aria-selected="true"] { background:var(--accent-soft); color:var(--accent); font-weight:700; }
        .skb-section-option[aria-selected="true"]::after { opacity:1; }
        .skb-section-empty { margin:10px; text-align:center; color:var(--text-muted); font-size:12px; }
        .skb-main { min-width:0; }
        .skb-screen { display:none; max-width:880px; margin:0 auto; }
        .skb-screen.active { display:block; }
        .skb-screen-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .skb-screen-title { margin:0; font-size:17px; font-weight:800; color:var(--text-main); display:flex; align-items:center; gap:8px; }
        .skb-screen-title .svg-icon { color:var(--accent); width:20px; height:20px; }
        .skb-screen-desc { margin:4px 0 0; font-size:12.5px; color:var(--text-muted); line-height:1.8; }
        .skb-screen-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .skb-note { margin:12px 0; padding:10px 13px; border-radius:var(--radius-md); border:1px dashed var(--border-soft); background:var(--surface-2); color:var(--text-muted); font-size:11.5px; line-height:1.9; }
        .skb-kb { direction:ltr; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-2xl); padding:14px; box-shadow:var(--shadow-1); }
        .skb-row { display:flex; gap:8px; margin-bottom:8px; direction:ltr; }
        .skb-row:last-child { margin-bottom:0; }
        .skb-key {
            direction:rtl; flex:1 1 0; min-width:0; min-height:50px; padding:10px 12px;
            display:flex; align-items:center; justify-content:center; gap:7px; text-align:center;
            border-radius:var(--radius-md); border:1px solid var(--border-mid); background:var(--surface-3);
            color:var(--text-main); font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:600;
            cursor:pointer; transition:transform .12s,border-color .12s,filter .12s; flex-wrap:wrap;
        }
        .skb-key:hover { border-color:var(--accent); filter:brightness(1.03); }
        .skb-key:focus-visible { outline:2px solid var(--text-main); outline-offset:2px; }
        .skb-key__label { min-width:0; overflow-wrap:anywhere; line-height:1.4; }
        .skb-key[data-style="primary"] { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border-color:#2563eb; }
        .skb-key[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border-color:#16a34a; }
        .skb-key[data-style="danger"] { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border-color:#dc2626; }
        .skb-key--head { font-weight:800; }
        .kb-color-opt[data-color="none"] .kb-color-opt__swatch { background:var(--surface-3); border-color:color-mix(in srgb, var(--text-main) 26%, transparent); }
        .skb-key.is-static { cursor:default; opacity:.6; }
        .skb-key.is-static:hover { transform:none; border-color:var(--border-mid); filter:none; }
        .skb-key__badge { flex-shrink:0; font-size:9px; font-weight:700; padding:1px 6px; border-radius:100px; background:var(--surface-2); color:var(--text-muted); border:1px solid var(--border-soft); }
        .skb-key__badge.is-on { color:#22c55e; border-color:rgba(34,197,94,.4); background:rgba(34,197,94,.1); }
        .skb-key__badge.is-off { color:#f59e0b; border-color:rgba(245,158,11,.4); background:rgba(245,158,11,.1); }
        .skb-key[data-style="primary"] .skb-key__badge,
        .skb-key[data-style="success"] .skb-key__badge,
        .skb-key[data-style="danger"] .skb-key__badge { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.28); }
        .skb-key__cond { width:7px; height:7px; border-radius:50%; background:#f59e0b; flex-shrink:0; }
        .skb-key[data-style="primary"] .skb-key__cond,
        .skb-key[data-style="success"] .skb-key__cond,
        .skb-key[data-style="danger"] .skb-key__cond { background:#fff; opacity:.75; }
        .skb-kb--table .skb-kb-scroll { overflow-x:auto; overscroll-behavior-x:contain; padding-bottom:2px; }
        .skb-kb--table .skb-row { display:grid; grid-template-columns:1fr 1fr 1.15fr; min-width:520px; }
        .skb-legend { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin:10px 2px 0; font-size:11px; color:var(--text-dim); }
        .skb-legend span.item { display:inline-flex; align-items:center; gap:6px; }
        .skb-legend .dot { width:7px; height:7px; border-radius:50%; background:#f59e0b; display:inline-block; }
        .skb-legend .chip { font-size:9px; font-weight:700; padding:1px 6px; border-radius:100px; border:1px solid var(--border-soft); background:var(--surface-2); }
        .kb-modal__box { display:flex; flex-direction:column; max-height:min(92dvh,760px); }
        .kb-modal__box > form { display:flex; flex-direction:column; min-height:0; max-height:100%; }
        .kb-modal__body { flex:1 1 auto; min-height:0; overflow-y:auto; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; }
        .kb-modal__foot { position:sticky; bottom:0; padding-bottom:calc(14px + env(safe-area-inset-bottom)); }
        .skb-modal-target { font-size:13px; color:var(--text-muted); margin-bottom:6px; }
        .skb-modal-target b { color:var(--text-main); font-weight:700; }
        .skb-current { font-size:11.5px; color:var(--text-dim); margin-bottom:16px; line-height:1.8; }
        .skb-scope-picker { display:grid; gap:8px; }
        .skb-scope-opt { position:relative; display:block; cursor:pointer; }
        .skb-scope-opt input { position:absolute; opacity:0; pointer-events:none; }
        .skb-scope-opt__card { display:block; padding:10px 14px; border-radius:var(--radius-md); border:1.5px solid var(--border-soft); background:var(--surface-1); transition:border-color .15s,background .15s,box-shadow .15s; }
        .skb-scope-opt:hover .skb-scope-opt__card { border-color:var(--border-mid); }
        .skb-scope-opt input:checked + .skb-scope-opt__card { border-color:var(--accent); background:var(--accent-soft); box-shadow:0 0 0 2px var(--accent-glow); }
        .skb-scope-opt input:focus-visible + .skb-scope-opt__card { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
        .skb-scope-opt__title { display:block; font-size:13px; font-weight:700; color:var(--text-main); }
        .skb-scope-opt__hint { display:block; font-size:10.5px; color:var(--text-muted); margin-top:2px; }
        .skb-modal-help { margin:14px 0 0; font-size:11px; color:var(--text-dim); line-height:1.9; }
        body.skb-lock { overflow:hidden; }
        @media (max-width:1024px) {
            .skb-layout { grid-template-columns:minmax(0,1fr); }
            .skb-nav { display:none; }
            .skb-mobile-nav { display:block; }
        }
        @media (max-width:640px) {
            .skb-top { padding:calc(9px + env(safe-area-inset-top)) 12px 9px; gap:7px; }
            .skb-top .btn span, .skb-toggle span, .skb-badge #skb-badge-text { display:none; }
            .skb-top .btn, .skb-toggle { padding:7px 9px; }
            .skb-chip { padding:6px 9px; font-size:11px; }
            .skb-shell { padding:calc(var(--skb-top-h) + 12px) 10px calc(44px + env(safe-area-inset-bottom)); }
            .skb-kb { padding:10px; border-radius:14px; }
            .skb-row { gap:6px; margin-bottom:6px; }
            .skb-key { min-height:46px; padding:8px; font-size:12px; }
            .skb-kb--table .skb-row { min-width:460px; }
            .skb-screen-title { font-size:15.5px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .skb-key { transition:none; }
        }
    </style>
</head>
<body class="fx-service-keyboard">

<script type="application/json" id="skb-data">
<?php
echo json_encode([
    'styles'          => $rxStylesForJs,
    'factoryDefaults' => $rxFactoryForJs,
    'configColumns'   => $CONFIG_COLUMNS,
    'mainStyles'      => empty($keyboardMain) ? new stdClass() : $keyboardMain,
    'mainTokens'      => array_keys($MAIN_TOKEN_MAP),
    'useDefaults'     => $useBuiltinDefaults,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
</script>

<div class="skb-top">
    <div class="skb-brand">
        <span class="lm"><img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="30" height="30"></span>
        <span>رنگ‌بندی دکمه‌ها</span>
    </div>
    <div class="skb-top-actions">
        <span class="skb-chip" id="skb-count" title="تعداد دکمه‌های قابل ویرایش در این صفحه">۰ دکمه</span>
        <label class="skb-toggle" title="وقتی روشن باشد، رنگ‌های پیش‌فرض کارخانه‌ای و رنگ خودکار اعمال می‌شوند.">
            <input type="checkbox" id="skb-use-defaults" <?php echo $useBuiltinDefaults ? 'checked' : ''; ?>>
            <span>پیش‌فرض‌ها</span>
        </label>
        <div class="skb-badge" id="skb-badge"><span class="dot"></span><span id="skb-badge-text">ذخیره خودکار</span></div>
        <button type="button" class="btn btn-outline btn-sm" id="skb-reset-screen" title="پاک‌کردن رنگ‌های دستی همین صفحه">
            <?php echo icon('rotate-left', 'svg-icon svg-sm'); ?>
            <span>بازنشانی این صفحه</span>
        </button>
        <a class="btn btn-outline btn-sm" href="keyboard.php">
            <?php echo icon('keyboard', 'svg-icon svg-sm'); ?>
            <span>چیدمان کیبورد</span>
        </a>
        <a class="btn btn-outline btn-sm" href="index.php">
            <?php echo icon('arrow-right', 'svg-icon svg-sm'); ?>
            <span>بازگشت</span>
        </a>
    </div>
</div>

<div class="skb-shell">
    <div class="skb-layout">
        <aside class="skb-nav" aria-label="صفحه‌های کیبورد">
            <div class="skb-nav-title">صفحه‌های کیبورد کاربر</div>
            <?php foreach ($SCREENS as $sid => $screen): ?>
            <button type="button" class="skb-nav-item<?php echo $sid === $firstScreenId ? ' active' : ''; ?>" data-screen="<?php echo htmlspecialchars($sid); ?>" aria-current="<?php echo $sid === $firstScreenId ? 'page' : 'false'; ?>">
                <?php echo icon($screen['icon'], 'svg-icon'); ?>
                <span><?php echo htmlspecialchars($screen['title']); ?></span>
            </button>
            <?php endforeach; ?>
        </aside>

        <main class="skb-main">
            <div class="skb-mobile-nav">
                <label id="skb-section-label">بخش کیبورد</label>
                <select id="skb-screen-select" hidden aria-hidden="true" tabindex="-1">
                    <?php foreach ($SCREENS as $sid => $screen): ?>
                    <option value="<?php echo htmlspecialchars($sid); ?>"><?php echo htmlspecialchars($screen['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="skb-section-trigger" id="skb-section-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="skb-section-menu" aria-labelledby="skb-section-label skb-section-current">
                    <span id="skb-section-current"><?php echo htmlspecialchars($SCREENS[$firstScreenId]['title']); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="skb-section-menu" id="skb-section-menu" role="dialog" aria-label="انتخاب بخش کیبورد" hidden>
                    <input type="search" class="skb-section-search" id="skb-section-search" placeholder="جستجوی بخش" aria-label="جستجوی بخش کیبورد" autocomplete="off">
                    <div class="skb-section-list" id="skb-section-list" role="listbox" aria-label="بخش‌های کیبورد">
                        <?php foreach ($SCREENS as $sid => $screen): ?>
                        <button type="button" class="skb-section-option" role="option" data-screen-pick="<?php echo htmlspecialchars($sid); ?>" aria-selected="<?php echo $sid === $firstScreenId ? 'true' : 'false'; ?>">
                            <?php echo icon($screen['icon'], 'svg-icon'); ?>
                            <span><?php echo htmlspecialchars($screen['title']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="skb-section-empty" id="skb-section-empty" role="status" hidden>بخشی پیدا نشد</p>
                </div>
            </div>

            <?php foreach ($SCREENS as $sid => $screen): ?>
            <section class="skb-screen<?php echo $sid === $firstScreenId ? ' active' : ''; ?>" data-screen="<?php echo htmlspecialchars($sid); ?>" id="skb-screen-<?php echo htmlspecialchars($sid); ?>" aria-labelledby="skb-title-<?php echo htmlspecialchars($sid); ?>">
                <div class="skb-screen-head">
                    <div>
                        <h1 class="skb-screen-title" id="skb-title-<?php echo htmlspecialchars($sid); ?>">
                            <?php echo icon($screen['icon'], 'svg-icon'); ?>
                            <?php echo htmlspecialchars($screen['title']); ?>
                        </h1>
                        <p class="skb-screen-desc"><?php echo htmlspecialchars($screen['desc']); ?></p>
                    </div>
                    <div class="skb-screen-actions">
                        <?php if (!empty($screen['link'])): ?>
                        <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars($screen['link']); ?>">
                            <?php echo icon('pen-to-square', 'svg-icon svg-sm'); ?>
                            <span><?php echo htmlspecialchars($screen['link_label']); ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($screen['note'])): ?>
                <div class="skb-note"><?php echo htmlspecialchars($screen['note']); ?></div>
                <?php endif; ?>
                <div class="skb-kb<?php echo !empty($screen['table']) ? ' skb-kb--table' : ''; ?>" role="group" aria-label="<?php echo htmlspecialchars($screen['title']); ?>">
                    <?php if (!empty($screen['table'])): ?><div class="skb-kb-scroll"><?php endif; ?>
                    <?php foreach ($screen['rows'] as $row): ?>
                    <div class="skb-row">
                        <?php foreach ($row as $btn): ?>
                        <?php
                        $kind = (string) ($btn['kind'] ?? 'family');
                        if ($kind === 'static') {
                            echo '<div class="skb-key is-static"><span class="skb-key__label">' . htmlspecialchars((string) ($btn['label'] ?? '')) . '</span></div>';
                            continue;
                        }
                        $attrs = [
                            'type' => 'button',
                            'class' => 'skb-key' . (!empty($btn['header']) ? ' skb-key--head' : ''),
                            'data-kind' => $kind,
                            'data-menu' => (string) ($btn['menu'] ?? ''),
                            'data-key' => (string) ($btn['key'] ?? ''),
                            'data-auto' => (string) ($btn['auto'] ?? ''),
                            'data-style' => (string) ($btn['style'] ?? 'default'),
                            'data-scopes' => implode(',', (array) ($btn['scopes'] ?? [])),
                        ];
                        if (!empty($btn['token'])) { $attrs['data-token'] = (string) $btn['token']; }
                        if (!empty($btn['item'])) { $attrs['data-item'] = (string) $btn['item']; }
                        if (!empty($btn['role'])) { $attrs['data-role'] = (string) $btn['role']; }
                        if (!empty($btn['family_label'])) { $attrs['data-family-label'] = (string) $btn['family_label']; }
                        if (!empty($btn['cond'])) { $attrs['title'] = (string) $btn['cond']; }
                        $attrs['aria-label'] = 'ویرایش رنگ «' . (string) ($btn['label'] ?? '') . '»';
                        echo '<button';
                        foreach ($attrs as $attrName => $attrVal) {
                            echo ' ' . $attrName . '="' . htmlspecialchars((string) $attrVal, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        echo '>';
                        echo '<span class="skb-key__label">' . htmlspecialchars((string) ($btn['label'] ?? '')) . '</span>';
                        if (!empty($btn['badge'])) {
                            echo '<span class="skb-key__badge ' . htmlspecialchars((string) ($btn['badge_class'] ?? '')) . '">' . htmlspecialchars((string) $btn['badge']) . '</span>';
                        }
                        if (!empty($btn['cond'])) {
                            echo '<span class="skb-key__cond" aria-hidden="true"></span>';
                        }
                        echo '</button>';
                        ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!empty($screen['table'])): ?></div><?php endif; ?>
                </div>
                <div class="skb-legend">
                    <span class="item"><span class="dot"></span> دکمه شرطی</span>
                    <span class="item">برای ویرایش رنگ روی دکمه بزنید؛ دامنه اعمال در پنجره ویرایش انتخاب می‌شود.</span>
                </div>
            </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>

<div id="skb-toast" class="kb-toast" role="status" aria-live="polite"></div>

<div id="skb-modal" class="kb-modal" role="dialog" aria-modal="true" aria-labelledby="skb-modal-title">
    <div class="kb-modal__box">
        <form id="skb-edit-form">
            <div class="kb-modal__head" id="skb-modal-title">
                <?php echo icon('pen-to-square'); ?>
                <span>ویرایش رنگ دکمه</span>
            </div>
            <div class="kb-modal__body">
                <div class="skb-modal-target">دکمه: <b id="skb-modal-target">—</b></div>
                <div class="skb-current" id="skb-current"></div>

                <div class="kb-field">
                    <label>رنگ دکمه</label>
                    <div class="kb-color-picker">
                        <?php foreach ($STYLE_META as $styleKey => $styleMeta): ?>
                        <label class="kb-color-opt" data-color="<?php echo htmlspecialchars($styleKey); ?>">
                            <input type="radio" name="skb-color" value="<?php echo htmlspecialchars($styleKey); ?>" <?php echo $styleKey === 'none' ? 'checked' : ''; ?>>
                            <span class="kb-color-opt__card">
                                <span class="kb-color-opt__name"><?php echo htmlspecialchars($styleMeta['label']); ?></span>
                                <span class="kb-color-opt__hint"><?php echo htmlspecialchars($styleMeta['hint']); ?></span>
                                <span class="kb-color-opt__swatch"></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="kb-field" id="skb-scope-field">
                    <label>دامنه اعمال رنگ</label>
                    <div class="skb-scope-picker" id="skb-scopes"></div>
                </div>

                <p class="skb-modal-help">«بدون رنگ» رنگ دکمه را حذف می‌کند و جلوی رنگ خودکار را می‌گیرد. تغییر رنگ فقط روی دامنه انتخاب‌شده اثر دارد.</p>
            </div>
            <div class="kb-modal__foot">
                <button type="button" class="btn btn-outline btn-sm" id="skb-cancel">
                    <?php echo icon('xmark', 'svg-icon svg-sm'); ?>
                    <span>انصراف</span>
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="skb-save">
                    <?php echo icon('check', 'svg-icon svg-sm'); ?>
                    <span>ذخیره رنگ</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    'use strict';

    var dataEl = document.getElementById('skb-data');
    var data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { data = {}; }

    var styles = data.styles || {};
    var factoryDefaults = data.factoryDefaults || {};
    var mainStyles = data.mainStyles || {};
    var mainTokens = Array.isArray(data.mainTokens) ? data.mainTokens : [];
    var changes = {};
    var mainChanges = {};
    var defaultsChanged = false;
    var clientId = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + '-' + Math.random().toString(36).slice(2);
    var revision = 0;
    var useDefaults = !!data.useDefaults;

    if (!styles.config_items || Array.isArray(styles.config_items)) styles.config_items = {};

    var COLORS = ['none', 'primary', 'success', 'danger'];
    var ROLE_HINTS = {
        download: 'همه دکمه‌های دریافت کانفیگ',
        name: 'همه خانه‌های نام کانفیگ',
        protocol: 'همه خانه‌های پروتکل'
    };
    var SOURCE_TEXT = {
        exact: 'رنگ دستی همین دکمه',
        family: 'تنظیم خانواده همین دکمه',
        factory: 'رنگ پیش‌فرض کارخانه‌ای',
        auto: 'رنگ خودکار متن دکمه',
        'default': 'بدون رنگ (پیش‌فرض تلگرام)'
    };

    var modalEl = document.getElementById('skb-modal');
    var modalTargetEl = document.getElementById('skb-modal-target');
    var currentEl = document.getElementById('skb-current');
    var scopesEl = document.getElementById('skb-scopes');
    var scopeFieldEl = document.getElementById('skb-scope-field');
    var colorEls = document.querySelectorAll('input[name="skb-color"]');
    var formEl = document.getElementById('skb-edit-form');
    var toastEl = document.getElementById('skb-toast');
    var badgeEl = document.getElementById('skb-badge');
    var badgeTextEl = document.getElementById('skb-badge-text');
    var countEl = document.getElementById('skb-count');
    var navSelect = document.getElementById('skb-screen-select');
    var sectionTrigger = document.getElementById('skb-section-trigger');
    var sectionCurrent = document.getElementById('skb-section-current');
    var sectionMenu = document.getElementById('skb-section-menu');
    var sectionSearch = document.getElementById('skb-section-search');
    var sectionList = document.getElementById('skb-section-list');
    var sectionOptions = Array.from(sectionList.querySelectorAll('[data-screen-pick]'));

    var editingEl = null;
    var lastFocus = null;
    var saveTimer = null;
    var isSaving = false;
    var pendingSave = false;
    var toastTimer = null;
    var currentScreen = '<?php echo $firstScreenId; ?>';

    function validStyle(v) {
        return typeof v === 'string' && COLORS.indexOf(v) !== -1;
    }

    function familyStyle(menu, key, auto) {
        var menuStyles = styles[menu];
        if (menuStyles && !Array.isArray(menuStyles)) {
            var saved = menuStyles[key];
            if (validStyle(saved)) return saved;
        }
        if (!useDefaults) return 'default';
        var factory = factoryDefaults[menu];
        if (factory && !Array.isArray(factory)) {
            var fv = factory[key];
            if (validStyle(fv)) return fv;
        }
        return validStyle(auto) ? auto : 'default';
    }

    function styleFor(el) {
        var kind = el.dataset.kind;
        if (kind === 'main') {
            var token = el.dataset.token || '';
            if (validStyle(mainStyles[token])) return mainStyles[token];
        }
        if (kind === 'config_item') {
            var itemKey = (el.dataset.item || '') + ':' + (el.dataset.role || '');
            var exact = styles.config_items[itemKey];
            if (validStyle(exact)) return exact;
        }
        return familyStyle(el.dataset.menu, el.dataset.key, el.dataset.auto);
    }

    function sourceFor(el) {
        var kind = el.dataset.kind;
        if (kind === 'main' && validStyle(mainStyles[el.dataset.token || ''])) return 'exact';
        if (kind === 'config_item' && validStyle(styles.config_items[(el.dataset.item || '') + ':' + (el.dataset.role || '')])) return 'exact';
        var menuStyles = styles[el.dataset.menu];
        if (menuStyles && !Array.isArray(menuStyles) && validStyle(menuStyles[el.dataset.key])) return 'family';
        if (useDefaults) {
            var factory = factoryDefaults[el.dataset.menu];
            if (factory && !Array.isArray(factory) && validStyle(factory[el.dataset.key])) return 'factory';
            if (validStyle(el.dataset.auto)) return 'auto';
        }
        return 'default';
    }

    function refreshAll() {
        document.querySelectorAll('.skb-key[data-kind]').forEach(function(el) {
            el.dataset.style = styleFor(el);
        });
    }

    function setFamily(menu, key, style) {
        if (!styles[menu] || Array.isArray(styles[menu])) styles[menu] = {};
        if (style === 'default') delete styles[menu][key];
        else styles[menu][key] = style;
        if (!changes[menu]) changes[menu] = {};
        changes[menu][key] = style === 'default' ? null : style;
    }

    function setMain(token, style) {
        if (!mainStyles || Array.isArray(mainStyles)) mainStyles = {};
        if (style === 'default') {
            delete mainStyles[token];
        } else {
            mainStyles[token] = style;
        }
        mainChanges[token] = style === 'default' ? null : style;
    }

    function setExact(item, role, style) {
        var key = item + ':' + role;
        if (!styles.config_items || Array.isArray(styles.config_items)) styles.config_items = {};
        if (style === 'default') delete styles.config_items[key];
        else styles.config_items[key] = style;
        if (!changes.config_items) changes.config_items = {};
        changes.config_items[key] = style === 'default' ? null : style;
    }

    function scopeOptions(el) {
        var kind = el.dataset.kind;
        var scopes = (el.dataset.scopes || '').split(',').filter(Boolean);
        var options = [];
        scopes.forEach(function(scope) {
            if (scope === 'exact') {
                options.push({
                    id: 'exact',
                    title: kind === 'config_item' ? 'این دکمه (همین کانفیگ)' : 'این دکمه',
                    hint: kind === 'main' ? 'فقط همین دکمه در منوی اصلی' : 'فقط همین کانفیگ، بر اساس محتوای خود کانفیگ'
                });
            } else if (scope === 'family') {
                var title = 'همه دکمه‌های مشابه';
                var hint = el.dataset.familyLabel || 'کل خانواده این نقش';
                if (kind === 'config_header') {
                    title = 'این سرستون';
                    hint = 'فقط همین سرستون';
                } else if (kind === 'main') {
                    hint = 'رنگ پیش‌فرض نقش؛ اگر رنگ دستی همین دکمه تنظیم شده باشد، آن اولویت دارد.';
                } else if (kind === 'config_item') {
                    hint = (el.dataset.familyLabel || 'کل خانواده این نقش') + ' — اگر رنگ اختصاصی همین کانفیگ تنظیم شده باشد، آن اولویت دارد.';
                }
                options.push({ id: 'family', title: title, hint: hint });
            } else if (scope === 'row') {
                options.push({
                    id: 'row',
                    title: kind === 'config_header' ? 'کل ردیف سرستون' : 'این ردیف',
                    hint: kind === 'config_header' ? 'هر سه سرستون با هم' : 'دانلود، پروتکل و نام همین ردیف'
                });
            }
        });
        return options;
    }

    function openEditor(el) {
        editingEl = el;
        lastFocus = el;
        modalTargetEl.textContent = (el.querySelector('.skb-key__label') || {}).textContent || '—';

        var style = styleFor(el);
        var options = scopeOptions(el);
        var defaultScope = 'family';
        if (options.some(function(o) { return o.id === 'exact'; })) defaultScope = 'exact';
        else if (options.some(function(o) { return o.id === 'family'; })) defaultScope = 'family';
        else if (options.length) defaultScope = options[0].id;

        scopesEl.innerHTML = '';
        options.forEach(function(opt) {
            var label = document.createElement('label');
            label.className = 'skb-scope-opt';
            label.innerHTML = '<input type="radio" name="skb-scope" value="' + opt.id + '"' + (opt.id === defaultScope ? ' checked' : '') + '>'
                + '<span class="skb-scope-opt__card"><span class="skb-scope-opt__title"></span><span class="skb-scope-opt__hint"></span></span>';
            label.querySelector('.skb-scope-opt__title').textContent = opt.title;
            label.querySelector('.skb-scope-opt__hint').textContent = opt.hint;
            scopesEl.appendChild(label);
        });
        scopeFieldEl.style.display = options.length > 1 ? '' : 'none';

        colorEls.forEach(function(input) {
            input.checked = (input.value === (validStyle(style) ? style : 'none'));
        });

        currentEl.textContent = 'رنگ فعلی: ' + (SOURCE_TEXT[sourceFor(el)] || SOURCE_TEXT['default']) + '.';

        modalEl.classList.add('open');
        document.body.classList.add('skb-lock');
        var checked = document.querySelector('input[name="skb-color"]:checked') || colorEls[0];
        if (checked) setTimeout(function() { checked.focus(); }, 60);
    }

    function closeEditor() {
        modalEl.classList.remove('open');
        document.body.classList.remove('skb-lock');
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        editingEl = null;
        lastFocus = null;
    }

    function commitEdit() {
        if (!editingEl) return;
        var color = 'none';
        colorEls.forEach(function(input) { if (input.checked) color = input.value; });
        var scopeInput = document.querySelector('input[name="skb-scope"]:checked');
        var scope = scopeInput ? scopeInput.value : 'family';
        var el = editingEl;
        var kind = el.dataset.kind;
        var item = el.dataset.item || '';

        var column = kind === 'config_header' ? (data.configColumns || {})[el.dataset.key] : null;
        if (column) {
            setFamily('config_list', el.dataset.key, color);
            setFamily('config_list', column.key, color);
            Object.keys(styles.config_items).forEach(function(key) {
                if (!/^[a-f0-9]{16}:(download|name|protocol)$/.test(key)) return;
                var parts = key.split(':');
                if (parts[1] === column.role) setExact(parts[0], parts[1], 'default');
            });
        } else if (scope === 'family') {
            setFamily(el.dataset.menu, el.dataset.key, color);
        } else if (scope === 'exact') {
            if (kind === 'main') setMain(el.dataset.token || '', color);
            else if (kind === 'config_item') setExact(item, el.dataset.role || '', color);
        } else if (scope === 'row') {
            if (kind === 'config_item') {
                ['download', 'name', 'protocol'].forEach(function(role) {
                    setExact(item, role, color);
                });
            } else if (kind === 'config_header') {
                ['cfg_header_action', 'cfg_header_protocol', 'cfg_header_name'].forEach(function(key) {
                    setFamily('config_list', key, color);
                });
            }
        }

        refreshAll();
        closeEditor();
        scheduleSave();
        showToast('رنگ اعمال شد و در حال ذخیره است', 'success');
    }

    function deliverableStyles() {
        var out = { patch_version: 1, client: clientId, revision: ++revision, changes: changes, main_changes: mainChanges };
        if (defaultsChanged) out._use_defaults = !!useDefaults;
        return JSON.parse(JSON.stringify(out));
    }

    function hasChanges() {
        return defaultsChanged || Object.keys(mainChanges).length > 0 || Object.keys(changes).some(function(menu) { return Object.keys(changes[menu]).length > 0; });
    }

    function acknowledge(payload) {
        Object.keys(payload.changes).forEach(function(menu) {
            Object.keys(payload.changes[menu]).forEach(function(key) {
                if (changes[menu] && changes[menu][key] === payload.changes[menu][key]) delete changes[menu][key];
            });
            if (changes[menu] && !Object.keys(changes[menu]).length) delete changes[menu];
        });
        Object.keys(payload.main_changes).forEach(function(token) {
            if (mainChanges[token] === payload.main_changes[token]) delete mainChanges[token];
        });
        if (Object.prototype.hasOwnProperty.call(payload, '_use_defaults') && useDefaults === payload._use_defaults) defaultsChanged = false;
    }

    function scheduleSave() {
        setBadge('saving', 'در حال ذخیره...');
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            saveTimer = null;
            doSave();
        }, 450);
    }

    function doSave() {
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (isSaving) { pendingSave = true; return; }
        if (!hasChanges()) return;
        var payload = deliverableStyles();
        isSaving = true;
        setBadge('saving', 'در حال ذخیره...');
        fetch('service_keyboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true,
            credentials: 'same-origin'
        })
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(d) {
            isSaving = false;
            if (d && d.ok) {
                acknowledge(payload);
                if (!hasChanges()) setBadge('saved', 'ذخیره شد ✓');
            } else {
                throw new Error((d && d.error) ? d.error : 'unknown');
            }
            if (pendingSave || hasChanges()) { pendingSave = false; doSave(); }
        })
        .catch(function() {
            isSaving = false;
            setBadge('', 'خطا در ذخیره');
            showToast('خطا در ذخیره تنظیمات', 'error');
            if (pendingSave) { pendingSave = false; doSave(); }
        });
    }

    function sendBeaconSave() {
        if (!hasChanges()) return;
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (!navigator.sendBeacon) { doSave(); return; }
        var blob = new Blob([JSON.stringify(deliverableStyles())], { type: 'application/json' });
        if (!navigator.sendBeacon('service_keyboard.php', blob)) doSave();
    }

    function setBadge(kind, text) {
        badgeEl.className = 'skb-badge' + (kind ? ' ' + kind : '');
        badgeTextEl.textContent = text;
    }

    function showToast(message, kind) {
        toastEl.className = 'kb-toast' + (kind ? ' ' + kind : '');
        toastEl.textContent = message;
        toastEl.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toastEl.classList.remove('show'); }, 1900);
    }

    function updateCount() {
        var screen = document.querySelector('.skb-screen.active');
        var count = screen ? screen.querySelectorAll('.skb-key[data-kind]').length : 0;
        countEl.textContent = count + ' دکمه';
    }

    function closeSectionMenu(restoreFocus) {
        sectionMenu.hidden = true;
        sectionTrigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) sectionTrigger.focus();
    }

    function positionSectionMenu() {
        if (sectionMenu.hidden) return;
        if (!sectionTrigger.getClientRects().length) { closeSectionMenu(false); return; }
        var rect = sectionTrigger.getBoundingClientRect();
        var viewport = window.visualViewport;
        var top = viewport ? viewport.offsetTop : 0;
        var left = viewport ? viewport.offsetLeft : 0;
        var width = viewport ? viewport.width : window.innerWidth;
        var height = viewport ? viewport.height : window.innerHeight;
        var below = top + height - rect.bottom - 12;
        var above = rect.top - top - 12;
        var down = below >= Math.min(240, height / 2) || below >= above;
        var available = Math.max(0, down ? below : above);
        var menuWidth = Math.min(rect.width, Math.max(0, width - 16));
        sectionMenu.style.width = menuWidth + 'px';
        sectionMenu.style.maxHeight = Math.min(420, available) + 'px';
        sectionMenu.style.left = Math.max(left + 8, Math.min(rect.right - menuWidth, left + width - menuWidth - 8)) + 'px';
        sectionMenu.style.top = (down ? rect.bottom + 6 : Math.max(top + 8, rect.top - sectionMenu.offsetHeight - 6)) + 'px';
    }

    function filterSections() {
        function normalize(value) { return value.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\s]+/g, ' ').trim(); }
        var query = normalize(sectionSearch.value);
        var count = 0;
        sectionOptions.forEach(function(option) {
            option.hidden = normalize(option.textContent).indexOf(query) === -1;
            if (!option.hidden) count++;
        });
        document.getElementById('skb-section-empty').hidden = count !== 0;
        positionSectionMenu();
    }

    function openSectionMenu(focusOption) {
        sectionSearch.value = '';
        sectionMenu.hidden = false;
        sectionTrigger.setAttribute('aria-expanded', 'true');
        filterSections();
        var selected = sectionOptions.find(function(option) { return option.getAttribute('aria-selected') === 'true'; });
        if (selected) sectionList.scrollTop = Math.max(0, selected.offsetTop - sectionList.offsetTop - sectionList.clientHeight / 2);
        if (focusOption && selected) selected.focus({ preventScroll: true });
    }

    function activateScreen(id) {
        if (!document.getElementById('skb-screen-' + id)) id = '<?php echo $firstScreenId; ?>';
        currentScreen = id;
        document.querySelectorAll('.skb-screen').forEach(function(screen) {
            screen.classList.toggle('active', screen.dataset.screen === id);
        });
        document.querySelectorAll('.skb-nav-item').forEach(function(item) {
            var active = item.dataset.screen === id;
            item.classList.toggle('active', active);
            item.setAttribute('aria-current', active ? 'page' : 'false');
        });
        if (navSelect) navSelect.value = id;
        sectionOptions.forEach(function(option) {
            var active = option.dataset.screenPick === id;
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            option.tabIndex = active ? 0 : -1;
            if (active) sectionCurrent.textContent = option.querySelector('span').textContent;
        });
        closeSectionMenu(false);
        updateCount();
        try { localStorage.setItem('faoxima_skb_screen', id); } catch (e) {}
    }

    function resetActiveScreen() {
        var screen = document.querySelector('.skb-screen.active');
        if (!screen) return;
        var keys = screen.querySelectorAll('.skb-key[data-kind]');
        if (!keys.length) return;
        if (!confirm('رنگ‌های دستی دکمه‌های این صفحه پاک شود؟ اگر همین دکمه در صفحه دیگری هم نمایش داده شود، رنگ آنجا نیز تغییر می‌کند. تنظیمات مستقل سایر بخش‌ها و ادمین حفظ می‌شوند.')) return;

        keys.forEach(function(el) {
            var menu = el.dataset.menu;
            var key = el.dataset.key;
            if (menu && key && styles[menu] && !Array.isArray(styles[menu])) {
                setFamily(menu, key, 'default');
            }
            if (el.dataset.kind === 'main' && el.dataset.token) {
                setMain(el.dataset.token, 'default');
            }
            if (el.dataset.kind === 'config_item' && el.dataset.item) {
                ['download', 'name', 'protocol'].forEach(function(role) {
                    setExact(el.dataset.item, role, 'default');
                });
            }
        });
        if (screen.dataset.screen === 'config') {
            Object.keys(styles.config_items).forEach(function(key) {
                if (!/^[a-f0-9]{16}:(download|name|protocol)$/.test(key)) return;
                var parts = key.split(':');
                setExact(parts[0], parts[1], 'default');
            });
        }

        refreshAll();
        scheduleSave();
        showToast('رنگ‌های این صفحه بازنشانی شد', 'success');
    }

    document.addEventListener('DOMContentLoaded', function() {
        styles.config_items = styles.config_items || {};
        currentEl.textContent = '';
        refreshAll();
        updateCount();

        document.querySelectorAll('.skb-nav-item').forEach(function(item) {
            item.addEventListener('click', function() { activateScreen(this.dataset.screen); });
        });
        if (navSelect) navSelect.addEventListener('change', function() { activateScreen(this.value); });
        document.body.appendChild(sectionMenu);
        sectionTrigger.addEventListener('click', function() {
            if (sectionMenu.hidden) openSectionMenu(false);
            else closeSectionMenu(false);
        });
        sectionTrigger.addEventListener('keydown', function(ev) {
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                openSectionMenu(true);
            } else if (ev.key === 'Escape') closeSectionMenu(true);
        });
        sectionSearch.addEventListener('input', filterSections);
        sectionOptions.forEach(function(option) {
            option.addEventListener('click', function() {
                activateScreen(option.dataset.screenPick);
                closeSectionMenu(true);
            });
        });
        sectionMenu.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); closeSectionMenu(true); return; }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(ev.key) === -1) return;
            if (ev.target === sectionSearch && ev.key !== 'ArrowDown' && ev.key !== 'ArrowUp') return;
            var visible = sectionOptions.filter(function(option) { return !option.hidden; });
            if (!visible.length) return;
            ev.preventDefault();
            var index = visible.indexOf(document.activeElement);
            if (ev.key === 'Home') index = 0;
            else if (ev.key === 'End') index = visible.length - 1;
            else index = (index + (ev.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
            visible[index].focus({ preventScroll: true });
            visible[index].scrollIntoView({ block: 'nearest' });
        });
        document.addEventListener('pointerdown', function(ev) {
            if (!sectionMenu.contains(ev.target) && !sectionTrigger.contains(ev.target)) closeSectionMenu(false);
        });
        document.addEventListener('focusin', function(ev) {
            if (!sectionMenu.hidden && !sectionMenu.contains(ev.target) && !sectionTrigger.contains(ev.target)) closeSectionMenu(false);
        });
        window.addEventListener('resize', positionSectionMenu);
        document.addEventListener('scroll', positionSectionMenu, true);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', positionSectionMenu);
            window.visualViewport.addEventListener('scroll', positionSectionMenu);
        }

        document.querySelector('.skb-main').addEventListener('click', function(ev) {
            var key = ev.target.closest('.skb-key[data-kind]');
            if (!key) return;
            openEditor(key);
        });

        document.getElementById('skb-cancel').addEventListener('click', closeEditor);
        formEl.addEventListener('submit', function(ev) { ev.preventDefault(); commitEdit(); });
        modalEl.addEventListener('click', function(ev) { if (ev.target === modalEl) closeEditor(); });
        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape' && modalEl.classList.contains('open')) closeEditor();
        });

        var defaultsToggle = document.getElementById('skb-use-defaults');
        defaultsToggle.checked = !!useDefaults;
        defaultsToggle.addEventListener('change', function() {
            useDefaults = !!this.checked;
            defaultsChanged = true;
            refreshAll();
            scheduleSave();
            showToast(useDefaults ? 'رنگ‌های پیش‌فرض فعال شد' : 'رنگ‌های پیش‌فرض غیرفعال شد', 'success');
        });

        document.getElementById('skb-reset-screen').addEventListener('click', resetActiveScreen);

        document.addEventListener('visibilitychange', function() {
            if (hasChanges()) doSave();
        });
        window.addEventListener('beforeunload', sendBeaconSave);

        var savedScreen = '';
        try { savedScreen = localStorage.getItem('faoxima_skb_screen') || ''; } catch (e) {}
        activateScreen(savedScreen || '<?php echo $firstScreenId; ?>');

        measureTopbar();
        window.addEventListener('load', measureTopbar);
        window.addEventListener('resize', measureTopbar);
        if (typeof ResizeObserver !== 'undefined') {
            var topbar = document.querySelector('.skb-top');
            if (topbar) new ResizeObserver(measureTopbar).observe(topbar);
        }
    });

    function measureTopbar() {
        var topbar = document.querySelector('.skb-top');
        if (!topbar) return;
        document.body.style.setProperty('--skb-top-h', topbar.offsetHeight + 'px');
    }
})();
</script>
</body>
</html>
