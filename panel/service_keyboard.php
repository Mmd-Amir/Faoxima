<?php
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

$ALLOWED_STYLES = ['default', 'primary', 'success', 'danger'];


$STYLE_META = [
    'default' => ['label' => 'پیش‌فرض'],
    'primary' => ['label' => 'آبی'],
    'success' => ['label' => 'سبز'],
    'danger'  => ['label' => 'قرمز'],
];


$MENUS = [
    'service' => [
        'title' => 'منوی سرویس', 'icon' => 'gear', 'type' => 'inline',
        'desc'  => 'دکمه‌های مدیریت سرویس کاربر (پس از انتخاب سرویس).',
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
            'config_header'    => '🔐 سرتیتر کانفیگ اشتراک',
        ],
    ],
    'account' => [
        'title' => 'پنل اکانت', 'icon' => 'user', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی کیف‌پول / اکانت کاربر.',
        'buttons' => [
            'Discount'    => '🎁 کد تخفیف',
            'Add_Balance' => '💰 افزایش موجودی',
            'TransferBalance' => '🔄 انتقال موجودی',
            'MyTransactions'  => '📑 تراکنش‌های من',
            'transferbal_confirm' => '✅ تایید و انتقال',
            'transferbal_cancel'  => '❌ انصراف',
            'backuser'    => '◀️ بازگشت',
            'my_miniapp_pending' => '⏳ رسید تایید نشده از مینی‌اپ',
        ],
    ],
    'tx_stats' => [
        'title' => 'فیلتر زمانی تراکنش‌ها', 'icon' => 'clock', 'type' => 'inline',
        'desc'  => 'دکمه‌های بازه‌ی زمانی در صفحه‌ی «تراکنش‌های من».',
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
        ],
    ],
    'pay_receipt' => [
        'title' => 'رسید / ارسال پرداخت', 'icon' => 'receipt', 'type' => 'inline',
        'desc'  => 'دکمه‌های ارسال رسید کارت‌به‌کارت، تأیید پرداخت کریپتو و بازگشت از مرحله پرداخت. این دکمه‌ها Inline هستند و رنگ‌بندی در تلگرام نمایش داده می‌شود.',
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
        ],
    ],
    'user_subscription' => [
        'title' => 'منوی کاربر — اشتراک', 'icon' => 'cart-check', 'type' => 'inline',
        'desc'  => 'دکمه‌های نمایش جزئیات اشتراک کاربر (سرویس‌های من).',
        'buttons' => [
            'buy_service'      => '🛒 خرید سرویس',
            'helpbtn'          => '📚 آموزش (تک)',
            'support'          => '📞 پشتیبانی (تک)',
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
        'desc'  => 'رنگ پیش‌فرض دکمه‌های لیست‌های داینامیک سمت کاربر (لیست محصولات خرید، دسته‌بندی، انتخاب پنل، انتخاب زمان/حجم و...). همه دکمه‌های هر لیست با همون رنگ نمایش داده می‌شن.',
        'buttons' => [
            'product_buy'    => '🛒 لیست محصولات (انتخاب سرویس برای خرید)',
            'category_buy'   => '🗂 لیست دسته‌بندی‌ها (categorynames_*)',
            'time_buy'       => '⏳ لیست زمان (producttime_*)',
            'volume_buy'     => '🔋 لیست حجم (productvolume_*)',
            'panel_buy'      => '🖥 لیست پنل/لوکیشن خرید (paneluserbuy_, locationbuy_)',
            'helpsection'    => '📚 لیست آیتم‌های آموزش کاربر (helpsection_*)',
            'channel_join'   => '📯 لیست کانال‌های الزامی (channel_join)',
            'custom_volume'  => '✏️ دکمه حجم/زمان دلخواه',
            'product_back'   => '◀️ بازگشت از لیست محصولات',
            'category_back'  => '◀️ بازگشت از لیست دسته‌بندی‌ها',
            'panel_back'     => '◀️ بازگشت از لیست پنل‌ها',
            'service_actions'      => '🛠 پیش‌فرض کل لیست مدیریت سرویس (تنظیم تکی: تب «منوی سرویس»)',
            'user_services_list'   => '📋 لیست «سرویس‌های من» (quickview)',
            'paneluser_list'       => '🖥 انتخاب لوکیشن/پنل کاربر',
            'crypto_actions'       => '🪙 پیش‌فرض کل لیست پرداخت کریپتو (تنظیم تکی: تب «دکمه‌های پرداخت کریپتو»)',
            'crypto_manual_actions'=> '🔁 پیش‌فرض کل لیست بررسی دستی هش (تنظیم تکی: تب «بررسی مجدد هش کریپتو»)',
            'user_confirms'        => '✅ پیش‌فرض کل لیست تاییدهای کاربر (تنظیم تکی: تب «تایید / ناوبری»)',
            'extra_purchase'       => '➕ پیش‌فرض کل لیست خرید حجم/زمان اضافه (تنظیم تکی: تب «منوی سرویس»)',
            'ticket_list'          => '🎫 پیش‌فرض کل لیست تیکت‌ها (تنظیم تکی: تب‌های «اشتراک» و «ناوبری»)',
            'copy_card_num'        => '💳 کپی شماره کارت (کارت‌به‌کارت)',
            'copy_card_amount'     => '🪙 کپی مبلغ (کارت‌به‌کارت)',
            'copy_text_btn'        => '📋 سایر دکمه‌های کپی بدون کال‌بک (پیش‌فرض)',
            'auto_inline_btn'=> '🎨 ✱ پیش‌فرض همه دکمه‌های inline تبدیل‌شده از reply (apn:*)',
            'nmstock_actions'      => '📦 دکمه‌های تحویل کالای دستی (تمدید/بازگشت وجه/کانفیگ/لینک اشتراک)',
        ],
    ],
    'recheckcrypto_buttons' => [
        'title' => 'کاربر — بررسی مجدد هش کریپتو', 'icon' => 'rotate-cw', 'type' => 'inline',
        'desc'  => 'دکمه‌های فلوی بررسی دستی هش کریپتو در سمت کاربر و دکمه‌های تایید/رد در سمت ادمین.',
        'buttons' => [
            'recheckcrypto'                 => '🔁 بررسی مجدد هش کریپتو (ورود)',
            'rcc_pick_TRX'                  => '🟥 انتخاب ترون (TRX)',
            'rcc_pick_TON'                  => '🟦 انتخاب تون (TON)',
            'rcc_pick_USDT_TRC20'           => '🟢 انتخاب تتر روی ترون',
            'rcc_pick_USDT_TON'             => '🟢 انتخاب تتر روی تون',
            'rcc_skip_photo'                => '⏭ ارسال بدون عکس',
            'rcc_cancel'                    => '❌ انصراف',
        ],
    ],
    'invoice_copy_buttons' => [
        'title' => 'کاربر — دکمه‌های پرداخت کریپتو', 'icon' => 'coins', 'type' => 'inline',
        'desc'  => 'رنگ دکمه‌های مسیر کامل پرداخت ارز دیجیتال در سمت کاربر — از انتخاب ارز تا کپی‌کردن آدرس/مبلغ/ممو و پرداخت کردم.',
        'buttons' => [
            'currency_pick' => '💎 انتخاب ارز (TRX/TON/USDT)',
            'mode_external' => '🌍 کیف پول خارجی',
            'copy_wallet'   => '🔗 کپی آدرس کیف پول',
            'copy_amount'   => '🪙 کپی مبلغ',
            'copy_memo'     => '🏷 کپی ممو',
            'paid_submit'   => '✅ پرداخت کردم | ارسال هش',
            'invoice_back'  => '🔙 بازگشت',
            'cancel_hash_input' => '❌ انصراف از ورود هش',
        ],
    ],
    'home_menu' => [
        'title' => 'کاربر — منوی اصلی', 'icon' => 'house', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی اصلی کاربر (گردونه شانس، زیرمجموعه‌گیری، تمدید، پشتیبانی، آموزش، اکانت تست، پنل/درخواست نمایندگی).',
        'buttons' => [
            'home_buy'         => '🛒 خرید سرویس (منوی اصلی)',
            'home_myservices'  => '📦 سرویس‌های من / میزان مصرف (منوی اصلی)',
            'home_account'     => '💳 کیف پول (منوی اصلی)',
            'home_tariff'      => '💰 لیست تعرفه (منوی اصلی)',
            'wheel_luck'    => '🎡 گردونه شانس',
            'affiliatesbtn' => '🤝 زیرمجموعه‌گیری',
            'extendbtn'     => '🔄 تمدید سرویس (منوی اصلی)',
            'supportbtns'   => '📞 پشتیبانی (منوی اصلی)',
            'helpbtns'      => '📚 آموزش (منوی اصلی)',
            'usertestbtn'   => '🧪 اکانت تست (منوی اصلی)',
            'agentpanel'    => '🧑‍💼 پنل نمایندگی',
            'requestagent'  => '📝 درخواست نمایندگی',
        ],
    ],
    'misc_buttons' => [
        'title' => 'کاربر — سایر دکمه‌ها', 'icon' => 'more-horizontal', 'type' => 'inline',
        'desc'  => 'دکمه‌های پراکنده سمت کاربر که در تب‌های دیگر جای نمی‌گیرند (تایید قوانین، تایید پرداخت، تخفیف، بازگشت به خرید).',
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
        'desc'  => 'دکمه‌های ارسال‌شده در پیام‌های خودکار کرون‌جاب (اعلان حجم/زمان رو به اتمام، پیام‌های همگانی، یادآوری شروع ربات و...).',
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

];

$ALL_DB_KEYS = array_keys($MENUS);


$currentStyles      = [];
$userStylesRaw      = [];
$factoryDefaultsMap = [];
foreach ($ALL_DB_KEYS as $_k) {
    $currentStyles[$_k]      = [];
    $userStylesRaw[$_k]      = [];
    $factoryDefaultsMap[$_k] = [];
}


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
                $currentStyles[$m] = $decoded[$m];
            }
        }
    }
}







if (function_exists('rx_getKeyboardDefaultStyles')) {
    $rxPanelDefaults = rx_getKeyboardDefaultStyles();
    if (is_array($rxPanelDefaults)) {
        foreach ($rxPanelDefaults as $rxPanelSec => $rxPanelPairs) {
            if (!is_array($rxPanelPairs) || empty($rxPanelPairs)) { continue; }
            if (!isset($factoryDefaultsMap[$rxPanelSec])) {
                $factoryDefaultsMap[$rxPanelSec] = [];
            }
            foreach ($rxPanelPairs as $rxPanelKey => $rxPanelVal) {
                if (in_array($rxPanelVal, $ALLOWED_STYLES, true)) {
                    $factoryDefaultsMap[$rxPanelSec][$rxPanelKey] = $rxPanelVal;
                }
            }
        }
    }
    unset($rxPanelDefaults, $rxPanelSec, $rxPanelPairs, $rxPanelKey, $rxPanelVal);
}

if ($useBuiltinDefaults) {
    foreach ($factoryDefaultsMap as $rxSec => $rxPairs) {
        if (!isset($currentStyles[$rxSec]) || !is_array($currentStyles[$rxSec])) {
            $currentStyles[$rxSec] = [];
        }
        foreach ($rxPairs as $rxKey => $rxVal) {
            if (!array_key_exists($rxKey, $currentStyles[$rxSec])) {
                $currentStyles[$rxSec][$rxKey] = $rxVal;
            }
        }
    }
    unset($rxSec, $rxPairs, $rxKey, $rxVal);
} else {
    foreach ($currentStyles as $csMenu => $_) {
        $currentStyles[$csMenu] = isset($userStylesRaw[$csMenu]) ? $userStylesRaw[$csMenu] : [];
    }
    unset($csMenu);
}


$rxResolvePreviewStyle = function(string $menuKey, string $btnKey, string $btnLabel) use (&$currentStyles, $useBuiltinDefaults, $ALLOWED_STYLES): string {
    $userPick = $currentStyles[$menuKey][$btnKey] ?? 'default';
    if (in_array($userPick, $ALLOWED_STYLES, true) && $userPick !== 'default') {
        return $userPick;
    }
    if ($useBuiltinDefaults && function_exists('rx_kb_guess_style_from_text')) {
        $guessed = rx_kb_guess_style_from_text($btnLabel);
        if (is_string($guessed) && in_array($guessed, $ALLOWED_STYLES, true)) {
            return $guessed;
        }

        return 'primary';
    }
    return 'default';
};


if ($_SERVER['REQUEST_METHOD'] === 'GET' && (filter_input(INPUT_GET, 'action') === 'check_log')) {
    header('Content-Type: application/json; charset=utf-8');
    $dbRow  = select("setting", "keyboard_styles_all", null, null, "select", ['cache' => false]);
    $raw    = is_array($dbRow) ? ($dbRow['keyboard_styles_all'] ?? null) : null;
    $parsed = $raw ? json_decode($raw, true) : null;
    $menuSummary = [];
    if (is_array($parsed)) {
        foreach ($parsed as $mk => $mv) {
            $count = is_array($mv) ? count(array_filter($mv, function($s) { return $s !== 'default'; })) : 0;
            if ($count > 0) $menuSummary[$mk] = $mv;
        }
    }
    echo json_encode([
        'db_has_data'   => !empty($raw),
        'db_raw_len'    => $raw ? strlen($raw) : 0,
        'styled_menus'  => $menuSummary,
        'reply_note'    => 'All admin menus are inline_keyboard with native style support. Colors are passed through to Telegram via the style field. User-facing menus also support inline colors.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    header('Content-Type: application/json; charset=utf-8');
    if (!is_array($payload)) {
        error_log('[SKB_SAVE] ERROR: Invalid JSON payload. raw_len=' . strlen($raw));
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_payload']); exit;
    }
    $clean = [];
    foreach ($ALL_DB_KEYS as $menu) {
        $clean[$menu] = [];
        if (!isset($payload[$menu]) || !is_array($payload[$menu])) continue;
        foreach ($payload[$menu] as $key => $style) {
            $key = (string)$key; $style = (string)$style;
            if (isset($MENUS[$menu]['buttons'][$key]) && in_array($style, $ALLOWED_STYLES, true)) {
                $clean[$menu][$key] = $style;
            }
        }
    }

    if (array_key_exists('_use_defaults', $payload)) {
        $clean['_use_defaults'] = (bool) $payload['_use_defaults'];
    }

    if (!empty($settingRow['keyboard_styles_all'])) {
        $existing = json_decode($settingRow['keyboard_styles_all'], true) ?? [];
        foreach ($existing as $k => $v) {
            if (!array_key_exists($k, $clean)) $clean[$k] = $v;
        }
    }
    $jsonToSave = json_encode($clean, JSON_UNESCAPED_UNICODE);
    update("setting", "keyboard_styles_all", $jsonToSave, null, null);


    $verify = select("setting", "keyboard_styles_all", null, null, "select", ['cache' => false]);
    $savedOk = (is_array($verify) && isset($verify['keyboard_styles_all'])
                && $verify['keyboard_styles_all'] === $jsonToSave);
    if (!$savedOk) {
        $savedVal = is_array($verify) ? ($verify['keyboard_styles_all'] ?? 'NULL') : 'select_failed';
        error_log('[SKB_SAVE] VERIFY FAILED. wanted_len=' . strlen($jsonToSave)
                  . ' got_len=' . strlen((string)$savedVal)
                  . ' | wanted=' . substr($jsonToSave, 0, 200)
                  . ' | got=' . substr((string)$savedVal, 0, 200));
        echo json_encode(['ok' => false, 'error' => 'db_verify_failed',
                          'detail' => 'saved_len=' . strlen((string)$savedVal) . ' expected_len=' . strlen($jsonToSave)]);
        exit;
    }
    
    echo json_encode(['ok' => true]);
    exit;
}


$TAB_GROUPS = [
    'user'  => ['label' => '👤 بخش کاربر',  'menus' => ['service','account','tx_stats','payment','user_nav','pay_receipt','user_subscription','invoice_copy_buttons','recheckcrypto_buttons','user_dynamic_lists','misc_buttons','cron_notifications']],
];


function phoneRows(string $mKey, array $btns): array {
    $rows = [];
    for ($i = 0; $i < count($btns); $i += 2) {
        $rows[] = array_slice($btns, $i, 2);
    }
    return $rows;
}
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
        body { padding-top: 0 !important; }
        /* ── Topbar ── */
        .skb-top {
            position: fixed; top:0; left:0; right:0; z-index:1000;
            display:flex; align-items:center; gap:10px 14px; padding:11px 22px;
            background: var(--surface-1);
            border-bottom: 1px solid var(--border-soft);
            box-shadow: var(--shadow-1); flex-wrap:nowrap; justify-content:space-between;
        }
        .skb-actions { display:flex; align-items:center; gap:8px; flex-wrap:nowrap; justify-content:flex-start; flex:0 1 auto; min-width:0; overflow-x:auto; scrollbar-width:none; }
        .skb-actions::-webkit-scrollbar { display:none; }
        .skb-actions > * { flex:0 0 auto; }
        .skb-brand { display:flex; align-items:center; gap:10px; font-weight:800; color:var(--accent); font-size:16px; letter-spacing:-.2px; }
        .skb-brand .lm { width:30px; height:30px; display:grid; place-items:center; background:#fff; border-radius:50%; overflow:hidden; flex-shrink:0; box-shadow:0 1px 3px rgba(20,20,30,.18), 0 3px 8px rgba(20,20,30,.14); }
        [data-theme="dark"] .skb-brand .lm,
        :root:not([data-theme="light"]) .skb-brand .lm { box-shadow:none; }
        .skb-brand .lm img { width:100%; height:100%; object-fit:contain; object-position:center; transform:scale(1.35); display:block; }
        .skb-grow { flex:1 1 auto; }
        .sv-badge { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); transition:color .2s; }
        .sv-badge.saving { color:#f59e0b; }
        .sv-badge.saved  { color:#22c55e; }
        .sv-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }


        .sv-counter { display:flex; align-items:center; gap:6px; padding:6px 10px; background:var(--accent-soft); border:1px solid var(--accent-mid); border-radius:8px; font-size:12px; color:var(--text-muted); cursor:help; transition:background .2s; }
        .sv-counter:hover { background:rgba(99,102,241,.12); }
        .sv-counter-icon { font-size:14px; }
        .sv-counter-text { display:flex; align-items:center; gap:4px; }
        .sv-counter-text b { color:var(--text-strong, #fff); font-weight:700; min-width:14px; text-align:center; }
        .sv-counter-text .sv-cnt-sep { opacity:.5; }
        .sv-counter-text small { opacity:.8; margin-right:4px; }
        .sv-counter.all-default b { color:#f59e0b; }
        .sv-counter.no-default b { color:#22c55e; }

        .sv-reset-btn { color:#f87171 !important; border-color:rgba(248,113,113,.4) !important; }
        .sv-reset-btn:hover { background:rgba(248,113,113,.1) !important; border-color:rgba(248,113,113,.7) !important; }


        .sv-toggle-defaults { display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; padding:6px 10px; border:1px solid var(--border-soft); border-radius:8px; background:var(--surface-1); font-size:12px; color:var(--text-muted); transition:background .15s, border-color .15s, color .15s; }
        .sv-toggle-defaults:hover { background:var(--surface-2); color:var(--text-main); }
        .sv-toggle-defaults input { accent-color:var(--accent, #6366f1); width:14px; height:14px; cursor:pointer; }
        .sv-toggle-defaults:has(input:checked) { color:var(--text-main); border-color:var(--accent-mid); background:var(--accent-soft); }
        @media(max-width:600px){ .skb-top{padding:10px 12px; gap:8px 10px;} .skb-actions{gap:7px; flex-wrap:wrap; overflow:visible; justify-content:flex-end;} .sv-pill-back span { display:none; } .sv-counter-text small { display:none; } .sv-reset-btn, .sv-toggle-defaults, .sv-pill-keyboard { display:none; } .sv-more { display:block; } }

        /* Page */
        .skb-page { max-width:1000px; margin:0 auto; padding:82px 18px 60px; }

        /* Group tabs */
        .g-tabs { display:flex; gap:3px; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:12px 12px 0 0; padding:8px 12px 0; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
        .g-tabs::-webkit-scrollbar { display:none; }
        .g-tab { padding:8px 20px 10px; border:none; background:none; cursor:pointer; font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:700; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; border-radius:6px 6px 0 0; transition:all .15s; white-space:nowrap; flex-shrink:0; }
        .g-tab:hover { color:var(--text-main); background:var(--surface-2); }
        .g-tab.active { color:var(--accent); border-bottom-color:var(--accent); }

        /* Sub-tabs */
        .s-tabs-outer { position:relative; display:flex; align-items:stretch; background:var(--surface-1); border:1px solid var(--border-soft); border-top:none; border-bottom:1px solid var(--border-mid); }
        .s-tabs-wrap { flex:1 1 auto; min-width:0; overflow-x:auto; -webkit-overflow-scrolling:touch; padding:0 14px; }
        .s-tabs-wrap::-webkit-scrollbar { height:4px; }
        .s-tabs-wrap::-webkit-scrollbar-track { background:transparent; }
        .s-tabs-wrap::-webkit-scrollbar-thumb { background:var(--border-mid); border-radius:2px; }
        .s-tabs-wrap::-webkit-scrollbar-thumb:hover { background:var(--accent); }
        .s-tabs-wrap { scrollbar-width:thin; scrollbar-color:var(--border-mid) transparent; }
        .s-tabs { display:flex; gap:3px; min-width:max-content; }
        .s-tab { display:flex; align-items:center; gap:6px; padding:8px 13px 9px; border:none; background:none; cursor:pointer; font-family:'Vazirmatn',sans-serif; font-size:12px; font-weight:600; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; border-radius:4px 4px 0 0; transition:all .15s; white-space:nowrap; flex-shrink:0; }
        .s-tab:hover { color:var(--text-main); }
        .s-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
        .s-tab .svg-icon { width:13px; height:13px; flex-shrink:0; }

        /* Scroll arrow buttons — beside tabs, not overlapping */
        .s-tabs-arrow { flex:0 0 auto; width:40px; min-height:42px; display:flex; align-items:center; justify-content:center; background:var(--surface-1); border:none; border-left:1px solid var(--border-soft); color:var(--text-muted); cursor:pointer; transition:all .15s; padding:0; }
        .s-tabs-arrow.left  { border-left:1px solid var(--border-soft); border-right:none; }
        .s-tabs-arrow.right { border-right:1px solid var(--border-soft); border-left:none; order:-1; }
        .s-tabs-arrow:hover:not(:disabled) { background:var(--accent-soft); color:var(--accent); }
        .s-tabs-arrow:active:not(:disabled) { background:var(--accent); color:var(--accent-fg,#fff); }
        .s-tabs-arrow:disabled { opacity:.25; cursor:not-allowed; }
        .s-tabs-arrow svg { width:18px; height:18px; }
        .s-tabs-arrow.hidden { display:none; }

        /* Edge fade gradients to hint more content */
        .s-tabs-outer::before, .s-tabs-outer::after {
            content:''; position:absolute; top:0; bottom:0; width:24px; pointer-events:none; opacity:0; transition:opacity .2s; z-index:2;
        }
        .s-tabs-outer::before { left:40px;  background:linear-gradient(to right, var(--surface-1), transparent); }
        .s-tabs-outer::after  { right:40px; background:linear-gradient(to left,  var(--surface-1), transparent); }
        .s-tabs-outer.scroll-left::before  { opacity:1; }
        .s-tabs-outer.scroll-right::after  { opacity:1; }

        /* Panels */
        .g-panel { display:none; }
        .g-panel.active { display:block; }
        .m-panel { display:none; }
        .m-panel.active { display:block; }
        .m-body { background:var(--surface-1); border:1px solid var(--border-soft); border-top:none; border-radius:0 0 14px 14px; padding:22px 18px 28px; }
        .m-desc { font-size:12px; color:var(--text-muted); margin:0 0 20px; padding:10px 14px; background:var(--surface-2); border-radius:8px; border:1px solid var(--border-soft); line-height:1.8; }

        /* Layout */
        .m-layout { display:grid; grid-template-columns:1fr 215px; gap:20px; align-items:start; }

        /* Button cards */
        .btn-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; align-items:stretch; }
        .btn-card.span2 { grid-column:1 / -1; }
        .btn-card { background:var(--surface-2); border:1px solid var(--border-soft); border-radius:12px; padding:10px 11px 10px; transition:border-color .15s,transform .12s; display:flex; flex-direction:column; gap:8px; min-height:108px; }
        .btn-card:hover { border-color:var(--border-mid); transform:translateY(-1px); }
        .btn-key { font-size:10px; color:var(--text-muted); font-family:monospace; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex-shrink:0; }
        .btn-prev { display:flex; align-items:center; justify-content:center; padding:8px 10px; border-radius:9px; border:1px solid var(--border-mid); background:var(--surface-3); color:var(--text-main); font-size:12px; font-weight:600; font-family:'Vazirmatn',sans-serif; min-height:42px; text-align:center; line-height:1.35; transition:all .2s; cursor:default; flex:1 1 auto; word-break:break-word; }
        .btn-prev[data-style="primary"] { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border-color:#2563eb; }
        .btn-prev[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border-color:#16a34a; }
        .btn-prev[data-style="danger"]  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border-color:#dc2626; }


        .btn-card.is-auto .btn-prev { opacity:.88; border-style:dashed; }
        .btn-card.is-auto:hover .btn-prev { opacity:1; }
        .btn-auto-tag {
            display:inline-block; margin-inline-start:6px; padding:1px 6px;
            font-size:9px; font-weight:700; line-height:1.4;
            border-radius:4px; vertical-align:middle;
            background:linear-gradient(135deg, color-mix(in srgb,var(--accent) 22%,transparent), color-mix(in srgb,var(--accent) 8%,transparent));
            color:var(--accent); border:1px solid color-mix(in srgb,var(--accent) 35%,transparent);
            font-family:'Vazirmatn',sans-serif;
        }

        /* Pagination */
        .pagination { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:18px; padding:10px 0; }
        .pg-btn { background:var(--surface-2); border:1px solid var(--border-soft); color:var(--text-main); padding:8px 14px; border-radius:8px; cursor:pointer; font-family:'Vazirmatn',sans-serif; font-size:12px; font-weight:600; transition:all .15s; min-width:42px; display:inline-flex; align-items:center; justify-content:center; gap:4px; }
        .pg-btn:hover:not(:disabled) { border-color:var(--accent); color:var(--accent); }
        .pg-btn:disabled { opacity:.4; cursor:not-allowed; }
        .pg-btn.active { background:var(--accent); border-color:var(--accent); color:var(--accent-fg,#fff); }
        .pg-info { color:var(--text-muted); font-size:11px; margin:0 6px; font-family:monospace; }
        .pg-dots { color:var(--text-muted); padding:0 4px; }

        /* Swatches */
        .swatches { display:flex; gap:5px; margin-top:auto; justify-content:space-between; padding-top:2px; }
        .sw { width:28px; height:28px; border-radius:7px; border:2px solid transparent; cursor:pointer; transition:transform .12s,border-color .12s; position:relative; flex-shrink:0; touch-action:manipulation; flex:1 1 auto; max-width:42px; }
        .sw:hover { transform:scale(1.2); }
        .sw.sel { border-color:#fff!important; transform:scale(1.1); }
        .sw[data-style="default"] { background:var(--surface-3); border-color:color-mix(in srgb, var(--text-main) 24%, transparent); }
        [data-theme="light"] .sw { border-color:#6b7280; border-width:3px; }
        [data-theme="light"] .sw.sel { border-color:#1f2937 !important; }
        .sw[data-style="primary"] { background:linear-gradient(135deg,#3b82f6,#2563eb); }
        .sw[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); }
        .sw[data-style="danger"]  { background:linear-gradient(135deg,#ef4444,#dc2626); }
        .sw-tip { position:absolute; bottom:calc(100% + 5px); left:50%; transform:translateX(-50%); background:#1f2430; color:#fff; font-size:10px; white-space:nowrap; padding:2px 6px; border-radius:4px; border:1px solid rgba(255,255,255,.14); box-shadow:0 4px 12px -4px rgba(0,0,0,.5); pointer-events:none; opacity:0; transition:opacity .12s; z-index:10; }
        .sw:hover .sw-tip { opacity:1; }

        /* Phone preview */
        .phone-sticky { position:sticky; top:82px; }
        .phone { background:var(--surface-1); border:1px solid var(--border-soft); border-radius:15px; padding:13px 9px; box-shadow:none; }
        .ph-head { text-align:center; font-size:10px; color:rgba(255,255,255,.32); margin-bottom:9px; }
        .ph-btns { display:flex; flex-direction:column; gap:4px; }
        .ph-row { display:flex; gap:4px; }
        .ph-btn { flex:1; text-align:center; padding:7px 5px; border-radius:7px; font-size:11px; font-weight:600; font-family:'Vazirmatn',sans-serif; line-height:1.3; background:var(--surface-3); color:var(--text-main); border:1px solid var(--border-soft); transition:all .2s; }
        .ph-btn[data-style="primary"] { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border-color:#2563eb; }
        .ph-btn[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border-color:#16a34a; }
        .ph-btn[data-style="danger"]  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border-color:#dc2626; }

        /* Toast */
        .skb-toast { position:fixed; bottom:22px; left:50%; transform:translateX(-50%) translateY(14px); background:var(--surface-3); border:1px solid var(--border-mid); color:var(--text-main); padding:9px 18px; border-radius:9px; font-size:13px; opacity:0; pointer-events:none; transition:opacity .2s,transform .2s; z-index:2000; white-space:nowrap; }
        .skb-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
        .skb-toast.success { border-color:#22c55e; color:#22c55e; }
        .skb-toast.error   { border-color:#ef4444; color:#ef4444; }

        /* Sub-tab keyboard type badges */
        .s-tab-badge {
            display: inline-flex; align-items: center;
            font-size: 9px; font-weight: 700; padding: 1px 5px;
            border-radius: 4px; margin-right: 5px; letter-spacing: .3px;
            vertical-align: middle; flex-shrink: 0;
        }
        .reply-badge  { background: rgba(239,68,68,.18); color: #ef4444; border: 1px solid rgba(239,68,68,.3); }
        .inline-badge { background: rgba(34,197,94,.18);  color: #22c55e; border: 1px solid rgba(34,197,94,.3); }

        /* Reply keyboard warning banner */
        .reply-warn-banner {
            display: flex; align-items: flex-start; gap: 12px;
            background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25);
            border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;
            color: var(--text-main); font-size: 12px; line-height: 1.7;
        }
        .reply-warn-banner .svg-icon { color: #ef4444; flex-shrink:0; margin-top: 2px; }
        .reply-warn-banner b { color: #ef4444; }
        .reply-warn-banner u { color: #ef4444; }
        @media (max-width: 600px) {
            .reply-warn-banner { padding: 10px 11px; font-size: 11px; }
            .s-tab-badge { display: none; }
        }
        @media (max-width: 900px) {
            .m-layout { grid-template-columns: 1fr; }
            .phone-sticky { position:static; margin-top:18px; }
            .phone { max-width:320px; margin:0 auto; }
        }

        /* Mobile <= 600px */
        @media (max-width: 600px) {
            .skb-page { padding:70px 10px 36px; }
            .m-body { padding:14px 10px 20px; }
            .m-desc { font-size:11px; padding:8px 10px; margin-bottom:14px; }
            .g-tabs { padding:6px 8px 0; border-radius:8px 8px 0 0; }
            .g-tab { padding:7px 14px 9px; font-size:12px; }
            .s-tabs-wrap { padding:0 4px; }
            .s-tab { padding:6px 10px 8px; font-size:11px; gap:3px; }
            .s-tab .svg-icon { display:none; }
            /* Bigger touch-friendly arrows on mobile (WCAG 44x44 minimum) */
            .s-tabs-arrow { width:44px; min-height:44px; }
            .s-tabs-arrow svg { width:20px; height:20px; }
            /* Two-column cards on mobile */
            .btn-grid { grid-template-columns: 1fr 1fr; gap:8px; }
            .btn-card { padding:9px 9px 8px; border-radius:10px; min-height:auto; }
            .btn-key { font-size:9px; margin-bottom:5px; }
            .btn-prev { font-size:11px; padding:6px 6px; min-height:34px; border-radius:7px; }
            .sw { width:auto; height:32px; border-radius:9px; flex:1 1 0; min-width:0; max-width:none; }
            .swatches { gap:6px; margin-top:9px; flex-wrap:nowrap; }
            .sw-tip { display:none; }
            /* Hide badge and text from topbar buttons on mobile */
            .sv-badge { display:none; }
            .skb-top .btn span { display:none; }
            .skb-top .btn { padding:7px 9px; }
            /* Compact phone preview */
            .phone { padding:10px 7px; border-radius:12px; }
            .ph-btn { font-size:10px; padding:6px 4px; border-radius:6px; }
        }

        /* Small mobile <= 400px — single column */
        @media (max-width: 400px) {
            .btn-grid { grid-template-columns: 1fr; }
            .g-tab { padding:6px 11px 8px; font-size:11px; }
            .sw { height:34px; }
            .swatches { gap:7px; }
        }
            .s-tabs-outer,
        .g-tabs,
        .m-desc,
        .btn-card {
        }
        .m-desc,
        .btn-card {
            background:var(--surface-2);
            border:1px solid var(--border-soft);
            box-shadow:none;
        }
        .s-tabs-outer,
        .g-tabs { border-color:rgba(255,255,255,.34); }
        /* ── Light-mode compatibility ── */
        [data-theme="light"] .skb-top { background: var(--surface-1); }
        [data-theme="light"] .phone { background: var(--surface-1); border-color: var(--border-soft); box-shadow: none; }
        [data-theme="light"] .ph-head { color: var(--text-muted); }
        [data-theme="light"] .m-desc,
        [data-theme="light"] .btn-card { border-color: var(--border-soft); box-shadow: none; }
        [data-theme="light"] .s-tabs-outer,
        [data-theme="light"] .g-tabs { border-color: var(--border-soft); }

        /* ── upgraded topbar pills ── */
        .skb-top .btn { border-radius:100px; gap:7px; }
        .skb-top .btn:hover { border-color:color-mix(in srgb,var(--accent) 55%,transparent); transform:none; }
        .sv-reset-btn { color:#f87171 !important; border-color:rgba(248,113,113,.4) !important; background:rgba(248,113,113,.07) !important; }
        .sv-reset-btn:hover { background:rgba(248,113,113,.14) !important; border-color:rgba(248,113,113,.75) !important; }
        .sv-counter { border-radius:100px; }
        .sv-toggle-defaults { border-radius:100px; }
        [data-theme="light"] .skb-top { background: var(--surface-1) !important; border-bottom-color: var(--border-soft); }

        /* mobile "more" menu */
        .sv-more { display:none; position:relative; }
        @media (max-width:600px) { .sv-more { display:block; } }
        .sv-more-btn { display:flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:100px; border:1px solid var(--border-mid); background:rgba(255,255,255,.03); color:var(--text-main); cursor:pointer; }
        .sv-more-btn:hover { background:rgba(255,255,255,.06); }
        .sv-more-btn .svg-icon { width:18px; height:18px; }
        .sv-more.open .sv-more-btn { border-color:var(--accent); color:var(--accent); }
        .sv-more-menu { position:absolute; top:calc(100% + 8px); inset-inline-end:0; min-width:235px; background:var(--surface-2); border:1px solid var(--border-mid); border-radius:13px; box-shadow:0 16px 44px -14px rgba(0,0,0,.7); padding:6px; display:none; flex-direction:column; gap:2px; z-index:1100; }
        .sv-more.open .sv-more-menu { display:flex; }
        .sv-more-item { display:flex; align-items:center; gap:10px; padding:11px 12px; border-radius:9px; font-size:13px; font-weight:600; color:var(--text-main); background:none; border:none; cursor:pointer; text-decoration:none; font-family:'Vazirmatn',sans-serif; width:100%; text-align:start; }
        .sv-more-item:hover { background:var(--surface-3); }
        .sv-more-item .svg-icon { width:17px; height:17px; color:var(--text-muted); flex-shrink:0; }
        .sv-more-danger { color:#f87171; }
        .sv-more-danger .svg-icon { color:#f87171; }
        .sv-more-chk { margin-inline-start:auto; color:var(--accent); font-weight:800; font-size:14px; }
        .sv-more-item[aria-checked="false"] .sv-more-chk { visibility:hidden; }

        /* ── mobile dropdown nav ── */
        .s-mselect-wrap { display:none; background:var(--surface-1); border:1px solid var(--border-soft); border-top:none; padding:10px 12px; position:relative; }
        .s-mselect-wrap::after { content:''; position:absolute; left:24px; top:50%; width:8px; height:8px; border-right:2px solid var(--text-muted); border-bottom:2px solid var(--text-muted); transform:translateY(-65%) rotate(45deg); pointer-events:none; }
        .s-mselect { width:100%; appearance:none; -webkit-appearance:none; background:var(--surface-2); border:1px solid var(--border-mid); color:var(--text-main); font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:700; padding:11px 14px; border-radius:10px; cursor:pointer; direction:rtl; }
        .s-mselect:focus { outline:none; border-color:var(--accent); }

        /* ── landscape phones: 3-col grid ── */
        @media (max-width:760px) and (min-width:521px) { .btn-grid { grid-template-columns:repeat(2,1fr); } }
        /* ── mobile: tabs become dropdown ── */
        @media (max-width:640px) {
            .s-tabs-outer { display:none; }
            .s-mselect-wrap { display:block; }
            .btn-grid { grid-template-columns:repeat(2,1fr) !important; }
        }
    </style>
</head>
<body class="fx-service-keyboard">

<script type="application/json" id="skb-data">
<?php


$_userStylesForJs = [];
foreach ($userStylesRaw as $_mk => $_mv) {
    $_userStylesForJs[$_mk] = empty($_mv) ? new stdClass() : $_mv;
}
$_factoryForJs = [];
foreach ($factoryDefaultsMap as $_mk => $_mv) {
    $_factoryForJs[$_mk] = empty($_mv) ? new stdClass() : $_mv;
}
echo json_encode([
    'styles'           => $_userStylesForJs,
    'factoryDefaults'  => $_factoryForJs,
    'useDefaults'      => $useBuiltinDefaults,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
</script>


<div class="skb-top">
    <div class="skb-brand">
        <span class="lm"><img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="30" height="30"></span>
    </div>
    <div class="skb-actions">




    <div class="sv-counter" id="sv-counter" title="تعداد دکمه‌هایی که پیش‌فرض (بدون رنگ) هستن از کل دکمه‌ها">
        <span class="sv-counter-icon">🎨</span>
        <span class="sv-counter-text">
            <b id="sv-cnt-default">0</b><span class="sv-cnt-sep">/</span><span id="sv-cnt-total">0</span>
            <small>پیش‌فرض</small>
        </span>
    </div>

    <button type="button" class="btn btn-outline btn-sm sv-reset-btn" id="sv-reset-all" title="غیرفعال‌سازی همگانی رنگ‌ها — همه دکمه‌ها به حالت پیش‌فرض (بدون رنگ) برمی‌گردن">
        <svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        <span>غیرفعال‌سازی رنگ‌ها</span>
    </button>


    <label class="sv-toggle-defaults" title="اگه خاموش باشه، همه رنگ‌ها (خودکار و دستی) غیرفعال می‌شن. اگه روشن باشه، رنگ‌های پیش‌فرض کارخانه‌ای هم اعمال می‌شن.">
        <input type="checkbox" id="sv-toggle-defaults" <?php echo $useBuiltinDefaults ? 'checked' : ''; ?> />
        <span>اعمال پیش‌فرض‌های کارخانه‌ای</span>
    </label>

    <div class="sv-badge" id="sv-badge"><span class="sv-dot"></span><span id="sv-txt">ذخیره خودکار</span></div>

    <div class="sv-more" id="sv-more">
        <button type="button" class="sv-more-btn" id="sv-more-btn" aria-label="منوی بیشتر" aria-haspopup="true" aria-expanded="false">
            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/></svg>
        </button>
        <div class="sv-more-menu" id="sv-more-menu" role="menu">
            <a class="sv-more-item" href="keyboard.php" role="menuitem"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2.4"/><line x1="7" y1="17" x2="17" y2="17"/></svg><span>کیبورد اصلی</span></a>
            <button type="button" class="sv-more-item sv-more-danger" id="sv-more-reset" role="menuitem"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>غیرفعال‌سازی رنگ‌ها</span></button>
            <button type="button" class="sv-more-item" id="sv-more-defaults" role="menuitemcheckbox" aria-checked="true"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3L12 3z"/></svg><span>اعمال پیش‌فرض‌های کارخانه‌ای</span><span class="sv-more-chk" id="sv-more-chk">✓</span></button>
        </div>
    </div>
    <a class="btn btn-outline btn-sm sv-pill-keyboard" href="keyboard.php"><svg class="svg-icon svg-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2.4"/><line x1="6" y1="9" x2="6.01" y2="9"/><line x1="10" y1="9" x2="10.01" y2="9"/><line x1="14" y1="9" x2="14.01" y2="9"/><line x1="18" y1="9" x2="18.01" y2="9"/><line x1="6" y1="13" x2="6.01" y2="13"/><line x1="10" y1="13" x2="10.01" y2="13"/><line x1="14" y1="13" x2="14.01" y2="13"/><line x1="18" y1="13" x2="18.01" y2="13"/><line x1="7" y1="17" x2="17" y2="17"/></svg> <span>کیبورد اصلی</span></a>
    <a class="btn btn-outline btn-sm sv-pill-back" href="index.php"><?php echo icon('arrow-right','svg-icon svg-sm'); ?> <span>بازگشت</span></a>
    </div>
</div>

<div class="skb-page">

    
    <div class="g-tabs" role="tablist">
        <?php foreach ($TAB_GROUPS as $gk => $gd): ?>
        <button class="g-tab <?php echo $gk==='user'?'active':''; ?>" data-group="<?php echo $gk; ?>">
            <?php echo htmlspecialchars($gd['label']); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($TAB_GROUPS as $gk => $gd): ?>
    <div class="g-panel <?php echo $gk==='user'?'active':''; ?>" id="gp-<?php echo $gk; ?>">

        
        <div class="s-tabs-outer" data-group="<?php echo $gk; ?>">
            <button class="s-tabs-arrow right" data-scroll="right" data-group="<?php echo $gk; ?>" aria-label="بعدی" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="s-tabs-wrap">
                <div class="s-tabs">
                <?php foreach ($gd['menus'] as $i => $mk): $md=$MENUS[$mk]; ?>
                <button class="s-tab <?php echo $i===0?'active':''; ?>" data-group="<?php echo $gk; ?>" data-menu="<?php echo $mk; ?>">
                    <?php echo icon($md['icon'],'svg-icon'); ?>
                    <?php echo htmlspecialchars($md['title']); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
            <button class="s-tabs-arrow left" data-scroll="left" data-group="<?php echo $gk; ?>" aria-label="قبلی" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <div class="s-mselect-wrap">
            <select class="s-mselect" data-group="<?php echo $gk; ?>" aria-label="انتخاب بخش">
            <?php foreach ($gd['menus'] as $i => $mk): $md=$MENUS[$mk]; ?>
                <option value="<?php echo $mk; ?>" <?php echo $i===0?'selected':''; ?>><?php echo htmlspecialchars($md['title']); ?></option>
            <?php endforeach; ?>
            </select>
        </div>

        
        <?php foreach ($gd['menus'] as $i => $mk): $md=$MENUS[$mk]; $pRows=phoneRows($mk,array_keys($md['buttons'])); ?>
        <div class="m-panel <?php echo $i===0?'active':''; ?>" id="mp-<?php echo $mk; ?>" data-group="<?php echo $gk; ?>">
            <div class="m-body">
                <p class="m-desc">
                    <?php echo htmlspecialchars($md['desc']); ?>
                </p>
                <div class="m-layout">
                    
                    <div>
                    <div class="btn-grid" data-menu="<?php echo $mk; ?>">
                        <?php foreach ($md['buttons'] as $bk => $bl):
                            $cs = $currentStyles[$mk][$bk] ?? 'default';
                            $previewStyle = $rxResolvePreviewStyle($mk, $bk, (string)$bl);
                            $isAutoStyle  = ($cs === 'default' && $previewStyle !== 'default');
                        ?>
                        <div class="btn-card<?php echo $isAutoStyle ? ' is-auto' : ''; ?>"
                             data-menu-card="<?php echo $mk; ?>"
                             data-auto-style="<?php echo $isAutoStyle ? $previewStyle : ''; ?>"
                             id="card-<?php echo $mk; ?>-<?php echo htmlspecialchars($bk); ?>">
                            <div class="btn-key">
                                <?php echo htmlspecialchars($bk); ?>
                                <?php if ($isAutoStyle): ?>
                                <span class="btn-auto-tag" title="رنگ خودکار از روی متن دکمه (وقتی پیش‌فرض‌های کارخانه‌ای روشن باشه)">خودکار</span>
                                <?php endif; ?>
                            </div>
                            <div class="btn-prev" data-style="<?php echo $previewStyle; ?>"><?php echo htmlspecialchars($bl); ?></div>
                            <div class="swatches">
                                <?php foreach ($STYLE_META as $sn => $sm): ?>
                                <div class="sw <?php echo $cs===$sn?'sel':''; ?>" data-style="<?php echo $sn; ?>" data-menu="<?php echo $mk; ?>" data-key="<?php echo htmlspecialchars($bk); ?>" role="button" tabindex="0">
                                    <span class="sw-tip"><?php echo htmlspecialchars($sm['label']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="pagination" data-pagination-for="<?php echo $mk; ?>" style="display:none"></div>
                    </div>
                    
                    <div class="phone-sticky">
                        <div class="phone">
                            <div class="ph-head">پیش‌نمایش</div>
                            <div class="ph-btns" id="ph-<?php echo $mk; ?>">
                                <?php foreach ($pRows as $row): ?>
                                <div class="ph-row">
                                    <?php foreach ($row as $bk):
                                        $bl = $md['buttons'][$bk] ?? $bk;
                                        $bs = $rxResolvePreviewStyle($mk, $bk, (string)$bl);
                                    ?>
                                    <div class="ph-btn" data-style="<?php echo $bs; ?>" id="phb-<?php echo $mk; ?>-<?php echo htmlspecialchars($bk); ?>"><?php echo htmlspecialchars($bl); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

</div>

<div id="skb-toast" class="skb-toast" role="status" aria-live="polite"></div>

<script>
(function(){
    'use strict';
    var _skbData = JSON.parse(document.getElementById('skb-data').textContent||'{}');
    var styles = _skbData.styles || {};
    var factoryDefaults = _skbData.factoryDefaults || {};
    var isSaving = false;
    var pendingSave = false;
    var saveTimer = null;

    var _tdfEl = document.getElementById('sv-toggle-defaults');

    function effectiveStyleFor(menu, key) {
        var m = styles[menu];
        if (m && !Array.isArray(m) && Object.prototype.hasOwnProperty.call(m, key)) {
            var v = m[key];
            if (typeof v === 'string' && v !== '') return v;
        }
        var useDefaults = _tdfEl ? !!_tdfEl.checked : true;
        if (useDefaults) {
            var f = factoryDefaults[menu];
            if (f && !Array.isArray(f) && Object.prototype.hasOwnProperty.call(f, key)) {
                var fv = f[key];
                if (typeof fv === 'string' && fv !== '') return fv;
            }
            var c = document.getElementById('card-' + menu + '-' + key);
            if (c && c.dataset && typeof c.dataset.autoStyle === 'string' && c.dataset.autoStyle !== '') {
                return c.dataset.autoStyle;
            }
        }
        return 'default';
    }

    function rerenderAllSwatches() {
        document.querySelectorAll('.btn-card[id^="card-"]').forEach(function(card){
            var id = card.id.substring('card-'.length);
            var sepIdx = id.indexOf('-');
            if (sepIdx < 0) return;
            var menu = id.substring(0, sepIdx);
            var key  = id.substring(sepIdx + 1);
            var effective = effectiveStyleFor(menu, key);
            var up = (styles[menu] && !Array.isArray(styles[menu]) && typeof styles[menu][key] === 'string' && styles[menu][key] !== '') ? styles[menu][key] : '';
            card.classList.toggle('is-auto', (up === '' && effective !== 'default'));
            var prev = card.querySelector('.btn-prev');
            if (prev) prev.dataset.style = effective;
            var ph = document.getElementById('phb-' + menu + '-' + key);
            if (ph) ph.dataset.style = effective;
            var userPick = (styles[menu] && !Array.isArray(styles[menu])) ? styles[menu][key] : undefined;
            var selStyle = (typeof userPick === 'string' && userPick !== '') ? userPick : 'default';
            card.querySelectorAll('.sw').forEach(function(s){
                s.classList.toggle('sel', s.dataset.style === selStyle);
            });
        });
    }
    var badgeEl = document.getElementById('sv-badge');
    var badgeTxt = document.getElementById('sv-txt');
    var toastEl = document.getElementById('skb-toast');
    var toastT = null;

    // ── Group tabs ──
    document.querySelectorAll('.g-tab').forEach(function(t){
        t.addEventListener('click', function(){
            var g = this.dataset.group;
            document.querySelectorAll('.g-tab').forEach(function(x){ x.classList.remove('active'); });
            document.querySelectorAll('.g-panel').forEach(function(x){ x.classList.remove('active'); });
            this.classList.add('active');
            var p = document.getElementById('gp-' + g);
            if (p) p.classList.add('active');
        });
    });

    // ── Sub-tabs ──
    document.querySelectorAll('.s-tab').forEach(function(t){
        t.addEventListener('mousedown', function(e){ e.preventDefault(); });
        t.addEventListener('click', function(){
            var _keepY = window.scrollY;
            var g = this.dataset.group, m = this.dataset.menu;
            document.querySelectorAll('.s-tab[data-group="' + g + '"]').forEach(function(x){ x.classList.remove('active'); });
            document.querySelectorAll('.m-panel[data-group="' + g + '"]').forEach(function(x){ x.classList.remove('active'); });
            this.classList.add('active');
            var p = document.getElementById('mp-' + m);
            if (p) p.classList.add('active');
            var _sel = document.querySelector('.s-mselect[data-group="' + g + '"]');
            if (_sel && _sel.value !== m) _sel.value = m;
            var _wrap = this.closest('.s-tabs-wrap');
            if (_wrap) {
                var _tb = this.getBoundingClientRect(), _wb = _wrap.getBoundingClientRect(), _pad = 14;
                if (_tb.left < _wb.left + _pad) {
                    _wrap.scrollLeft -= (_wb.left + _pad) - _tb.left;
                } else if (_tb.right > _wb.right - _pad) {
                    _wrap.scrollLeft += _tb.right - (_wb.right - _pad);
                }
            }
            requestAnimationFrame(function(){
                var _max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
                var _target = Math.min(_keepY, _max);
                if (window.scrollY !== _target) window.scrollTo(0, _target);
            });
        });
    });

    document.querySelectorAll('.s-mselect').forEach(function(sel){
        sel.addEventListener('change', function(){
            var t = document.querySelector('.s-tab[data-group="' + this.dataset.group + '"][data-menu="' + this.value + '"]');
            if (t) t.click();
        });
    });

    // ── Tab scroll arrows ──
    function updateArrowState(group) {
        var outer = document.querySelector('.s-tabs-outer[data-group="' + group + '"]');
        if (!outer) return;
        var wrap = outer.querySelector('.s-tabs-wrap');
        var arrL = outer.querySelector('.s-tabs-arrow.left');
        var arrR = outer.querySelector('.s-tabs-arrow.right');
        if (!wrap || !arrL || !arrR) return;
        var maxScroll = wrap.scrollWidth - wrap.clientWidth;
        // Hide both arrows entirely if there's nothing to scroll (no overflow)
        if (maxScroll < 2) {
            arrL.classList.add('hidden');
            arrR.classList.add('hidden');
            outer.classList.remove('scroll-left', 'scroll-right');
            return;
        }
        arrL.classList.remove('hidden');
        arrR.classList.remove('hidden');
        // RTL scrollLeft can be 0..maxScroll (Chromium/FF modern), or negative, or reversed.
        // Use absolute distance to start/end.
        var sl = wrap.scrollLeft;
        var atStart = Math.abs(sl) < 2;
        var atEnd   = Math.abs(Math.abs(sl) - maxScroll) < 2;
        // In RTL: right arrow scrolls toward content-start (we mapped that in click handler)
        arrR.disabled = atStart;
        arrL.disabled = atEnd;
        outer.classList.toggle('scroll-right', !atStart);
        outer.classList.toggle('scroll-left',  !atEnd);
    }

    document.querySelectorAll('.s-tabs-arrow').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (this.disabled) return;
            var group = this.dataset.group;
            var dir = this.dataset.scroll;
            var wrap = document.querySelector('.s-tabs-outer[data-group="' + group + '"] .s-tabs-wrap');
            if (!wrap) return;
            var step = Math.max(180, wrap.clientWidth * 0.75);
            // In RTL: "right arrow" → reveal content further right (toward start) → decrease scrollLeft
            var delta = dir === 'right' ? -step : step;
            wrap.scrollBy({ left: delta, behavior: 'smooth' });
        });
    });

    document.querySelectorAll('.s-tabs-wrap').forEach(function(wrap){
        var outer = wrap.closest('.s-tabs-outer');
        if (!outer) return;
        var group = outer.dataset.group;
        wrap.addEventListener('scroll', function(){ updateArrowState(group); }, { passive: true });
    });

    // Init arrow state for all groups after layout
    function refreshAllArrows() {
        document.querySelectorAll('.s-tabs-outer').forEach(function(o){
            updateArrowState(o.dataset.group);
        });
    }
    setTimeout(refreshAllArrows, 80);
    window.addEventListener('resize', refreshAllArrows);
    window.addEventListener('load',   refreshAllArrows);
    // Re-evaluate arrows when a group tab is switched (tabs become visible)
    document.querySelectorAll('.g-tab').forEach(function(t){
        t.addEventListener('click', function(){
            setTimeout(refreshAllArrows, 30);
        });
    });

    // ── Swatch clicks ──
    document.addEventListener('click', function(ev){
        var sw = ev.target.closest('.sw');
        if (!sw) return;
        applyStyle(sw.dataset.menu, sw.dataset.key, sw.dataset.style);
        scheduleSave();
    });
    document.addEventListener('keydown', function(ev){
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var sw = ev.target.closest('.sw');
        if (!sw) return;
        ev.preventDefault();
        applyStyle(sw.dataset.menu, sw.dataset.key, sw.dataset.style);
        scheduleSave();
    });

    // ── Sanitise styles: convert any Array values back to plain objects ──
    function sanitiseStyles(s) {
        var out = {};
        Object.keys(s).forEach(function(menu) {
            if (Array.isArray(s[menu])) {
                out[menu] = {};
            } else {
                out[menu] = s[menu] || {};
            }
        });
        if (_tdfEl) { out._use_defaults = !!_tdfEl.checked; }
        return out;
    }


    (function bindDefaultsToggle(){
        var t = document.getElementById('sv-toggle-defaults');
        if (!t) return;
        t.addEventListener('change', function(){
            if (!t.checked) {
                Object.keys(styles).forEach(function(menu){
                    styles[menu] = {};
                });
            }
            rerenderAllSwatches();
            if (typeof updateDefaultCounter === 'function') { updateDefaultCounter(); }
            scheduleSave();
            showToast(
                t.checked
                    ? 'پیش‌فرض‌های کارخانه‌ای فعال شدن.'
                    : 'همه رنگ‌ها (خودکار و دستی) غیرفعال شدن.',
                'success'
            );
        });
    })();









    var cntDefaultEl = document.getElementById('sv-cnt-default');
    var cntTotalEl   = document.getElementById('sv-cnt-total');
    var cntWrapEl    = document.getElementById('sv-counter');

    var cardIndex   = null;
    var totalCards  = 0;
    var defaultsCount = 0;

    function buildCardIndex(){
        cardIndex = [];
        document.querySelectorAll('.btn-card[id^="card-"]').forEach(function(card){
            var id = card.id.substring('card-'.length);
            var sepIdx = id.indexOf('-');
            if (sepIdx < 0) return;
            cardIndex.push({ menu: id.substring(0, sepIdx), key: id.substring(sepIdx + 1) });
        });
        totalCards = cardIndex.length;
    }

    function paintCounter(){
        if (!cntDefaultEl || !cntTotalEl || !cntWrapEl) return;
        cntDefaultEl.textContent = defaultsCount;
        cntTotalEl.textContent   = totalCards;
        cntWrapEl.classList.remove('all-default','no-default');
        if (totalCards > 0) {
            if (defaultsCount === totalCards) cntWrapEl.classList.add('all-default');
            else if (defaultsCount === 0)     cntWrapEl.classList.add('no-default');
        }
    }

    function updateDefaultCounter(){
        if (!cardIndex) buildCardIndex();
        var d = 0;
        for (var i = 0; i < cardIndex.length; i++) {
            if (effectiveStyleFor(cardIndex[i].menu, cardIndex[i].key) === 'default') d++;
        }
        defaultsCount = d;
        paintCounter();
    }


    var resetBtn = document.getElementById('sv-reset-all');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(){
            if (!cardIndex) buildCardIndex();
            var coloredCount = 0;
            for (var i = 0; i < cardIndex.length; i++) {
                if (effectiveStyleFor(cardIndex[i].menu, cardIndex[i].key) !== 'default') coloredCount++;
            }
            if (coloredCount === 0) {
                showToast('همه دکمه‌ها از قبل پیش‌فرض هستن ✓', 'success');
                return;
            }
            var confirmMsg = '⚠️ آیا مطمئنی؟\n\n' +
                coloredCount + ' دکمه رنگی به حالت پیش‌فرض (بدون رنگ) برمی‌گردن.\n' +
                'این عمل قابل بازگشت نیست — رنگ‌ها رو دوباره باید دستی انتخاب کنی.';
            if (!confirm(confirmMsg)) return;

            Object.keys(styles).forEach(function(menu){
                styles[menu] = {};
            });
            if (_tdfEl) { _tdfEl.checked = false; }

            rerenderAllSwatches();
            updateDefaultCounter();
            showToast('در حال ذخیره ' + coloredCount + ' تغییر...', 'success');
            scheduleSave();
        });
    }

    // ── Pagination ──
    var PAGE_SIZE = 10;
    var pageState = {}; // menuKey → currentPage

    function balanceGrid(grid) {
        if (!grid) return;
        grid.querySelectorAll('.btn-card.span2').forEach(function(c){ c.classList.remove('span2'); });
        var vis = [].slice.call(grid.querySelectorAll('.btn-card')).filter(function(c){ return c.style.display !== 'none'; });
        if (vis.length % 2 === 1) vis[vis.length - 1].classList.add('span2');
    }

    function initPagination() {
        document.querySelectorAll('.btn-grid[data-menu]').forEach(function(grid) {
            var mk = grid.dataset.menu;
            var cards = grid.querySelectorAll('.btn-card');
            if (cards.length <= PAGE_SIZE) { balanceGrid(grid); return; }
            pageState[mk] = 0;
            renderPagination(mk);
            applyPagination(mk);
        });
    }

    function applyPagination(mk) {
        var grid = document.querySelector('.btn-grid[data-menu="' + mk + '"]');
        if (!grid) return;
        var cards = grid.querySelectorAll('.btn-card');
        var curPage = pageState[mk] || 0;
        cards.forEach(function(c, idx) {
            var cardPage = Math.floor(idx / PAGE_SIZE);
            c.style.display = (cardPage === curPage) ? '' : 'none';
        });
        balanceGrid(grid);
    }

    function renderPagination(mk) {
        var grid = document.querySelector('.btn-grid[data-menu="' + mk + '"]');
        var holder = document.querySelector('.pagination[data-pagination-for="' + mk + '"]');
        if (!grid || !holder) return;
        var total = grid.querySelectorAll('.btn-card').length;
        var totalPages = Math.ceil(total / PAGE_SIZE);
        if (totalPages <= 1) { holder.style.display = 'none'; return; }
        var cur = pageState[mk] || 0;
        holder.style.display = 'flex';
        holder.innerHTML = '';

        function addBtn(label, target, opts) {
            opts = opts || {};
            var b = document.createElement('button');
            b.className = 'pg-btn' + (opts.active ? ' active' : '');
            b.textContent = label;
            b.disabled = !!opts.disabled;
            if (!opts.disabled) {
                b.addEventListener('click', function() {
                    pageState[mk] = target;
                    applyPagination(mk);
                    renderPagination(mk);
                });
            }
            holder.appendChild(b);
        }

        addBtn('« قبلی', cur - 1, {disabled: cur === 0});

        // Page number buttons (with smart truncation)
        var maxShown = 7;
        var startPg = Math.max(0, cur - Math.floor(maxShown / 2));
        var endPg = Math.min(totalPages, startPg + maxShown);
        if (endPg - startPg < maxShown) startPg = Math.max(0, endPg - maxShown);

        if (startPg > 0) {
            addBtn('1', 0);
            if (startPg > 1) {
                var d = document.createElement('span');
                d.className = 'pg-dots';
                d.textContent = '...';
                holder.appendChild(d);
            }
        }
        for (var i = startPg; i < endPg; i++) {
            addBtn(String(i + 1), i, {active: i === cur});
        }
        if (endPg < totalPages) {
            if (endPg < totalPages - 1) {
                var d2 = document.createElement('span');
                d2.className = 'pg-dots';
                d2.textContent = '...';
                holder.appendChild(d2);
            }
            addBtn(String(totalPages), totalPages - 1);
        }

        addBtn('بعدی »', cur + 1, {disabled: cur === totalPages - 1});

        var info = document.createElement('span');
        info.className = 'pg-info';
        info.textContent = (cur + 1) + ' / ' + totalPages + ' (' + total + ' دکمه)';
        holder.appendChild(info);
    }

    initPagination();


    if (typeof updateDefaultCounter === 'function') { updateDefaultCounter(); }

    // ── Save on page hide / tab switch / navigation ──
    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'hidden') {
            sendBeaconSave();
        }
    });
    window.addEventListener('beforeunload', function(){
        sendBeaconSave();
    });
    function sendBeaconSave(){
        var url = 'service_keyboard.php';
        var blob = new Blob([JSON.stringify(sanitiseStyles(styles))], {type: 'application/json'});
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, blob);
        }
    }

    // ── Apply style to element + state ──
    function applyStyle(menu, key, style){
        /* Guard: if PHP sent [] (JSON array) instead of {} (JSON object),
           JS parsed it as an Array. Array named-props are dropped by JSON.stringify. */
        if (!styles[menu] || Array.isArray(styles[menu])) styles[menu] = {};
        var beforeEff = effectiveStyleFor(menu, key);
        if (style === 'default') {
            delete styles[menu][key];
        } else {
            styles[menu][key] = style;
        }

        var effective = effectiveStyleFor(menu, key);

        var card = document.getElementById('card-' + menu + '-' + key);
        var autoStyle = (card && card.dataset.autoStyle) ? card.dataset.autoStyle : '';
        var displayStyle = (effective === 'default' && autoStyle) ? autoStyle : effective;
        var isAuto = (effective === 'default' && !!autoStyle);

        try {
            var prev = card ? card.querySelector('.btn-prev') : null;
            if (prev) prev.dataset.style = displayStyle;
        } catch(e){}

        if (card) card.classList.toggle('is-auto', isAuto);

        var ph = document.getElementById('phb-' + menu + '-' + key);
        if (ph) ph.dataset.style = displayStyle;

        if (card) {
            card.querySelectorAll('.sw').forEach(function(s){
                s.classList.toggle('sel', s.dataset.style === style);
            });
        } else {
            document.querySelectorAll('.sw[data-menu="' + menu + '"][data-key="' + key + '"]').forEach(function(s){
                s.classList.toggle('sel', s.dataset.style === style);
            });
        }

        if (beforeEff === 'default' && effective !== 'default') defaultsCount--;
        else if (beforeEff !== 'default' && effective === 'default') defaultsCount++;
        paintCounter();
    }

    function scheduleSave(){
        setBadge('saving', 'در حال ذخیره...');
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function(){ saveTimer = null; doSave(); }, 550);
    }

    // ── Immediate save ──
    function doSave(){
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (isSaving) { pendingSave = true; return; }
        isSaving = true;
        setBadge('saving', 'در حال ذخیره...');
        var payload = sanitiseStyles(styles);
        fetch('service_keyboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(function(r){ if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(d){
            isSaving = false;
            if (d && d.ok) {
                setBadge('saved', 'ذخیره شد ✓');
                showToast('ذخیره شد', 'success');
            } else {
                var detail = (d && d.detail) ? d.detail : (d && d.error ? d.error : 'unknown');
                console.error('[SKB] save failed:', detail);
                throw new Error(detail);
            }
            if (pendingSave) { pendingSave = false; doSave(); }
        })
        .catch(function(err){
            isSaving = false;
            console.error('[SKB] save error:', err);
            setBadge('', 'خطا در ذخیره');
            showToast('خطا در ذخیره — جزئیات در Console مرورگر', 'error');
            if (pendingSave) { pendingSave = false; doSave(); }
        });
    }

    function setBadge(cls, txt){
        badgeEl.className = 'sv-badge' + (cls ? ' ' + cls : '');
        badgeTxt.textContent = txt;
    }
    function showToast(msg, kind){
        toastEl.className = 'skb-toast' + (kind ? ' ' + kind : '');
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        if (toastT) clearTimeout(toastT);
        toastT = setTimeout(function(){ toastEl.classList.remove('show'); }, 2200);
    }

    (function moreMenu() {
        var more = document.getElementById('sv-more');
        if (!more) return;
        var btn = document.getElementById('sv-more-btn');
        var resetItem = document.getElementById('sv-more-reset');
        var defItem = document.getElementById('sv-more-defaults');
        var tgl = document.getElementById('sv-toggle-defaults');
        function syncChk() { defItem.setAttribute('aria-checked', tgl.checked ? 'true' : 'false'); }
        syncChk();
        function closeMenu() { more.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (more.classList.contains('open')) { closeMenu(); }
            else { syncChk(); more.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
        });
        document.addEventListener('click', function (e) { if (more.classList.contains('open') && !more.contains(e.target)) closeMenu(); });
        resetItem.addEventListener('click', function () { closeMenu(); document.getElementById('sv-reset-all').click(); });
        defItem.addEventListener('click', function () { tgl.checked = !tgl.checked; tgl.dispatchEvent(new Event('change')); syncChk(); });
        tgl.addEventListener('change', syncChk);
    })();
})();
</script>
</body>
</html>
