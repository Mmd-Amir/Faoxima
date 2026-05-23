<?php
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
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
        ],
    ],
    'account' => [
        'title' => 'پنل اکانت', 'icon' => 'user', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی کیف‌پول / اکانت کاربر.',
        'buttons' => [
            'Discount'    => '🎁 کد تخفیف',
            'Add_Balance' => '💰 افزایش موجودی',
            'backuser'    => '◀️ بازگشت',
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
            'iranpay1'         => '🔴 پرداخت ارزی (Swapino)',
            'iranpay2'         => '🟠 ترنادو',
            'iranpay3'         => '🟢 ارزی ریالی ۳',
            'aqayepardakht'    => '💜 آقای پرداخت',
            'zarinpal'         => '🔷 زرین پال',
            'zarinpey'         => '🔶 زرین پی',
            'paymentnotverify' => '📋 پرداخت بدون تایید',
            'startelegrams'    => '⭐ ستاره تلگرام',
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
        ],
    ],
    'admin_main' => [
        'title' => 'ادمین — منوی اصلی', 'icon' => 'shield', 'type' => 'inline',
        'desc'  => 'منوی اصلی که ادمین پس از ورود به پنل مدیریت مشاهده می‌کند.',
        'buttons' => [
            'admin_status'      => '📊 وضعیت ربات',
            'admin_managepanel' => '🖥 مدیریت پنل',
            'admin_addpanel'    => '➕ اضافه کردن پنل',
            'admin_timeprice'   => '⏳ تنظیم سریع قیمت زمان',
            'admin_volprice'    => '🔋 تنظیم سریع قیمت حجم',
            'admin_users'       => '👤 مدیریت کاربر',
            'admin_shop'        => '🏬 تنظیمات فروشگاه',
            'admin_finance'     => '💎 مالی',
            'admin_support'     => '🤙 بخش پشتیبانی',
            'admin_help'        => '📚 بخش آموزش',
            'admin_features'    => '🛠 قابلیت‌های پنل',
            'admin_settings'    => '⚙️ تنظیمات عمومی',
            'admin_invoices'    => '💵 رسیدهای تایید نشده',
            'admin_back'        => '◀️ بازگشت',
        ],
    ],
    'admin_settings' => [
        'title' => 'ادمین — تنظیمات', 'icon' => 'sliders', 'type' => 'inline',
        'desc'  => 'منوی پنل تنظیمات عمومی ربات.',
        'buttons' => [
            'set_features'   => '⚙️ وضعیت قابلیت‌ها',
            'set_reports'    => '📣 گزارشات ربات',
            'set_channel'    => '📯 تنظیمات کانال',
            'set_webpanel'   => '✅ فعالسازی پنل تحت وب',
            'set_optimize'   => '🗑 بهینه‌سازی ربات',
            'set_text'       => '📝 تنظیم متن ربات',
            'set_adminmgr'   => '👨‍🔧 بخش ادمین',
            'set_testlimit'  => '➕ محدودیت اکانت تست',
            'set_agentprice' => '💰 مبلغ عضویت نمایندگی',
            'set_qrbg'       => '🖼 پس‌زمینه کیوآرکد',
            'set_webhook'    => '🔗 وبهوک مجدد ربات‌های نماینده',
            'set_backadmin'  => '🏠 بازگشت به منوی ادمین',
            'set_backmenu'   => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'admin_shop' => [
        'title' => 'ادمین — فروشگاه', 'icon' => 'package', 'type' => 'inline',
        'desc'  => 'منوی تنظیمات فروشگاه و محصولات.',
        'buttons' => [
            'shop_status'      => '🛒 وضعیت قابلیت‌های فروشگاه',
            'shop_category'    => '🗂 مدیریت دسته‌بندی',
            'shop_products'    => '🛍 مدیریت محصولات',
            'shop_giftadd'     => '🎁 ساخت کد هدیه',
            'shop_giftdel'     => '❌ حذف کد هدیه',
            'shop_discountadd' => '🎁 ساخت کد تخفیف',
            'shop_discountdel' => '❌ حذف کد تخفیف',
            'shop_minbulk'     => '⬇️ حداقل موجودی خرید عمده',
            'shop_renewcb'     => '🎁 کش‌بک تمدید',
            'shop_backadmin'   => '🏠 بازگشت به منوی ادمین',
            'shop_backmenu'    => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'admin_roles' => [
        'title' => 'ادمین — نقش‌ها', 'icon' => 'users', 'type' => 'inline',
        'desc'  => 'منوی ادمین برای نقش‌های Seller (فروشنده) و Support (پشتیبان).',
        'buttons' => [
            
            'seller_status'   => '📊 وضعیت ربات (Seller)',
            'seller_users'    => '👤 مدیریت کاربر (Seller)',
            'seller_back'     => '◀️ بازگشت (Seller)',
            
            'support_users'   => '👤 مدیریت کاربر (Support)',
            'support_search'  => '👁‍🗨 جستجو کاربر (Support)',
            'support_back'    => '◀️ بازگشت (Support)',
        ],
    ],
    'admin_gateways' => [
        'title' => 'ادمین — تنظیمات درگاه‌ها', 'icon' => 'wallet', 'type' => 'inline',
        'desc'  => 'منوهای تنظیمات تمام درگاه‌های پرداخت (کارت‌به‌کارت، ترنادو، زرین‌پال، زرین‌پی، آقای پرداخت، Plisio).',
        'buttons' => [
            
            'cart_title'       => '🗂 نام درگاه کارت‌به‌کارت',
            'cart_setnum'      => '💳 تنظیم شماره کارت',
            'cart_delnum'      => '❌ حذف شماره کارت',
            'cart_support'     => '👤 آیدی پشتیبانی',
            'cart_pvmode'      => '💳 درگاه آفلاین در پیوی',
            'cart_autoconfirm' => '♻️ تایید خودکار رسید',
            'cart_cashback'    => '💰 کش‌بک کارت‌به‌کارت',
            'cart_firstpay'    => '🔒 نمایش پس از اولین پرداخت',
            'cart_min'         => '⬇️ حداقل مبلغ کارت‌به‌کارت',
            'cart_max'         => '⬆️ حداکثر مبلغ کارت‌به‌کارت',
            'cart_edu'         => '📚 تنظیم آموزش کارت‌به‌کارت',
            'cart_back'        => '◀️ بازگشت (کارت‌به‌کارت)',
            
            'trnado_name'      => '🏷️ نام درگاه ترنادو',
            'trnado_apikey'    => '🔑 API Key ترنادو',
            'trnado_wallet'    => '💼 آدرس ولت ترون',
            'trnado_apiurl'    => '🌐 آدرس API ترنادو',
            'trnado_cashback'  => '💰 کش‌بک ترنادو',
            'trnado_min'       => '⬇️ حداقل مبلغ ترنادو',
            'trnado_max'       => '⬆️ حداکثر مبلغ ترنادو',
            'trnado_edu'       => '📚 تنظیم آموزش ترنادو',
            'trnado_back'      => '◀️ بازگشت (ترنادو)',
            
            'zpal_name'        => '🗂 نام درگاه زرین‌پال',
            'zpal_merchant'    => '🔑 مرچنت زرین‌پال',
            'zpal_cashback'    => '💰 کش‌بک زرین‌پال',
            'zpal_min'         => '⬇️ حداقل مبلغ زرین‌پال',
            'zpal_max'         => '⬆️ حداکثر مبلغ زرین‌پال',
            'zpal_edu'         => '📚 تنظیم آموزش زرین‌پال',
            'zpal_back'        => '◀️ بازگشت (زرین‌پال)',
            
            'zpey_name'        => '🗂 نام درگاه زرین‌پی',
            'zpey_token'       => '🔑 توکن زرین‌پی',
            'zpey_cashback'    => '💰 کش‌بک زرین‌پی',
            'zpey_tutorial'    => '🧑🏼‍💻 آموزش اتصال زرین‌پی',
            'zpey_min'         => '⬇️ حداقل مبلغ زرین‌پی',
            'zpey_max'         => '⬆️ حداکثر مبلغ زرین‌پی',
            'zpey_edu'         => '📚 تنظیم آموزش زرین‌پی',
            'zpey_back'        => '◀️ بازگشت (زرین‌پی)',
            
            'aqaye_name'       => '🗂 نام درگاه آقای پرداخت',
            'aqaye_merchant'   => '🔑 مرچنت آقای پرداخت',
            'aqaye_cashback'   => '💰 کش‌بک آقای پرداخت',
            'aqaye_min'        => '⬇️ حداقل مبلغ آقای پرداخت',
            'aqaye_max'        => '⬆️ حداکثر مبلغ آقای پرداخت',
            'aqaye_edu'        => '📚 تنظیم آموزش آقای پرداخت',
            'aqaye_back'       => '◀️ بازگشت (آقای پرداخت)',
            
            'plisio_name'      => '🗂 نام درگاه Plisio',
            'plisio_api'       => '🧩 API Key Plisio',
            'plisio_cashback'  => '💰 کش‌بک Plisio',
            'plisio_min'       => '⬇️ حداقل مبلغ Plisio',
            'plisio_max'       => '⬆️ حداکثر مبلغ Plisio',
            'plisio_edu'       => '📚 تنظیم آموزش Plisio',
            'plisio_back'      => '◀️ بازگشت (Plisio)',
        ],
    ],
    'admin_features' => [
        'title' => 'ادمین — قابلیت‌ها', 'icon' => 'wrench', 'type' => 'inline',
        'desc'  => 'منوی فعال/غیرفعال کردن قابلیت‌ها (اطلاعات اکانت، اکانت تست، آموزش).',
        'buttons' => [
            'feat_info'       => '⚙️ قابلیت مشاهده اطلاعات اکانت',
            'feat_test'       => '🧪 قابلیت اکانت تست',
            'feat_help'       => '📚 قابلیت آموزش',
            'feat_back'       => '🏠 بازگشت به منوی ادمین',
            'feat_backmenu'   => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'admin_channel' => [
        'title' => 'ادمین — کانال', 'icon' => 'megaphone', 'type' => 'inline',
        'desc'  => 'منوی مدیریت کانال‌های اجباری.',
        'buttons' => [
            'ch_add'         => '➕ اضافه کردن کانال',
            'ch_del'         => '❌ حذف کانال',
            'ch_back'        => '🏠 بازگشت به منوی ادمین',
            'ch_backmenu'    => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'admin_help' => [
        'title' => 'ادمین — آموزش', 'icon' => 'book-open', 'type' => 'inline',
        'desc'  => 'منوی مدیریت بخش آموزش (افزودن/حذف/ویرایش).',
        'buttons' => [
            'help_add'        => '📚 اضافه کردن آموزش',
            'help_del'        => '❌ حذف آموزش',
            'help_edit'       => '✏️ ویرایش آموزش',
            'help_back'       => '🏠 بازگشت به منوی ادمین',
            'help_backmenu'   => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'admin_category' => [
        'title' => 'ادمین — دسته‌بندی فروشگاه', 'icon' => 'folder-tree', 'type' => 'inline',
        'desc'  => 'منوی مدیریت دسته‌بندی محصولات فروشگاه.',
        'buttons' => [
            'cat_add'   => '🛒 اضافه کردن دسته بندی',
            'cat_del'   => '❌ حذف دسته بندی',
            'cat_edit'  => '✏️ ویرایش دسته بندی',
            'cat_back'  => '⬅️ بازگشت به منوی فروشگاه',
        ],
    ],
    'admin_products' => [
        'title' => 'ادمین — محصولات فروشگاه', 'icon' => 'shopping-bag', 'type' => 'inline',
        'desc'  => 'منوی مدیریت محصولات فروشگاه (افزودن، حذف، ویرایش، تغییر قیمت گروهی).',
        'buttons' => [
            'shopitem_add'      => '🛍 اضافه کردن محصول',
            'shopitem_del'      => '❌ حذف محصول',
            'shopitem_edit'     => '✏️ ویرایش محصول',
            'shopitem_priceinc' => '⬆️ افزایش گروهی قیمت',
            'shopitem_pricedec' => '⬇️ کاهش گروهی قیمت',
            'shopitem_back'     => '⬅️ بازگشت به منوی فروشگاه',
        ],
    ],
    'admin_product_edit' => [
        'title' => 'ادمین — منوی ویرایش محصول', 'icon' => 'edit', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش هر محصول (تغییر قیمت، حجم، زمان، نام محصول و ...). کلید هر دکمه = متن دقیق آن دکمه در ربات.',
        'buttons' => [
            'قیمت'                  => '💰 قیمت',
            'حجم'                   => '🔋 حجم',
            'زمان'                  => '⏳ زمان',
            'نام محصول'             => '📝 نام محصول',
            'نوع کاربری'            => '👤 نوع کاربری',
            'نوع ریست حجم'          => '♻️ نوع ریست حجم',
            'یادداشت'               => '🗒 یادداشت',
            'موقعیت محصول'          => '🌍 موقعیت محصول',
            'دسته بندی'             => '🗂 دسته بندی',
            '🎛 تنظیم اینباند'      => '🎛 تنظیم اینباند',
            'نمایش برای خرید اول'   => '🛒 نمایش برای خرید اول',
            'مخفی کردن پنل'         => '🫣 مخفی کردن پنل',
            'حذف کلی پنل های مخفی'  => '❌ حذف کلی پنل های مخفی',
            'backadmin'             => '🏠 بازگشت به منوی مدیریت',
            'backmenu'              => '▶️ بازگشت به منوی قبل',
        ],
    ],
    'features_bot' => [
        'title' => 'قابلیت‌ها — آپشن اصلی', 'icon' => 'bot', 'type' => 'inline',
        'desc'  => 'دکمه‌های عنوان منوی «🤖 آپشن‌های اصلی ربات» (وضعیت قابلیت‌ها › آپشن اصلی).',
        'buttons' => [
            'subject'            => '📡 موضوع ربات',
            'subjectde'          => '📝 توضیحات موضوع',
            'statusbot'          => '📡 وضعیت ربات',
            'stautsrolee'        => '♨️ قوانین',
            'Authenticationphone'=> '☎️ احراز هویت شماره تماس',
            'Authenticationiran' => '🇮🇷 تایید شماره ایرانی',
            'verify'             => '🔒 احراز هویت',
            'verifybyuser'       => '🔑 احراز هویت با لینک',
            'inlinebtnmain'      => '🛡 شیشه‌ای بودن دکمه ربات',
        ],
    ],
    'features_users' => [
        'title' => 'قابلیت‌ها — کاربران/پشتیبانی', 'icon' => 'users', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی «👥 کاربران و پشتیبانی».',
        'buttons' => [
            'usernamebtn'         => '👤 یوزرنیم',
            'statusnewuser'       => '🆕 اعلان کاربر جدید',
            'statussupportpv'     => '👤 پشتیبانی در پیوی',
            'statusnamecustom'    => '📨 یادداشت کانفیگ',
            'statusnamecustomf'   => '📨 یادداشت کاربر عادی',
        ],
    ],
    'features_shop' => [
        'title' => 'قابلیت‌ها — فروش/خدمات', 'icon' => 'shopping-cart', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی «🛍 فروش و خدمات».',
        'buttons' => [
            'bulkbuy'              => '🛍 خرید عمده',
            'btn_status_category'  => '📗 دسته‌بندی آموزش',
            'keyconfig'            => '🔗 کیبورد کانفیگی',
            'copycart'             => '💳 کپی شماره کارت',
            'Debtsettlement'       => '💎 تسویه بدهی',
            'changeloc'            => '🌍 محدودیت تغییر لوکیشن',
            'changeloclimit'       => '⚙️ تنظیمات تغییر لوکیشن',
            'infocard_status'      => '📊 کارت مشخصات سرویس',
            'infocard_color_menu'  => '🎨 انتخاب رنگ کارت',
            'linkappstatus'        => '🔗 لینک دانلود برنامه',
            'linkappsetting'       => '⚙️ تنظیمات لینک برنامه',
        ],
    ],
    'features_lottery' => [
        'title' => 'قابلیت‌ها — گردونه/قرعه‌کشی', 'icon' => 'gift', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی «🎁 گردونه و قرعه‌کشی».',
        'buttons' => [
            'wheel_luck'         => '🎲 گردونه شانس',
            'gradonhshans'       => '⚙️ تنظیمات گردونه',
            'wheelagentfirst'    => '🎲 گردونه خرید اول',
            'wheelagent'         => '🎲 گردونه نمایندگان',
            'score'              => '🎁 قرعه‌کشی شبانه',
            'scoresetting'       => '⚙️ تنظیمات قرعه‌کشی',
            'Lotteryagent'       => '🎁 قرعه‌کشی نمایندگان',
            'affiliatesstatus'   => '🎁 زیرمجموعه',
            'settingaffiliatesf' => '⚙️ تنظیمات زیرمجموعه',
            'Dice'               => '🎰 نمایش تاس',
        ],
    ],
    'features_crons' => [
        'title' => 'قابلیت‌ها — کرون/زمان', 'icon' => 'clock', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی «⏱ کرون‌ها و زمان‌بندی».',
        'buttons' => [
            'cronday'                  => '🕚 کرون زمان',
            'settimecornday'           => '⚙️ تنظیم زمان هشدار',
            'on_hold'                  => '🕚 کرون اولین اتصال',
            'setting_on_holdcron'      => '⚙️ زمان اولین اتصال',
            'cronvolume'               => '🔋 کرون حجم',
            'settimecornvolume'        => '⚙️ تنظیم حجم هشدار',
            'notifremove'              => '❌ کرون حذف',
            'settimecornremove'        => '⚙️ زمان حذف',
            'notifremove_volume'       => '❌ کرون حذف حجم',
            'settimecornremovevolume'  => '⚙️ زمان حذف حجم',
            'cronjobs_settings'        => '⏱ مدیریت کرون‌ها',
        ],
    ],
    'features_antispam' => [
        'title' => 'قابلیت‌ها — آنتی اسپم', 'icon' => 'shield', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی «🛡 آنتی اسپم».',
        'buttons' => [
            'antispam_toggle'       => '🛡 تغییر وضعیت آنتی اسپم',
            'antispam_set_count'    => '✉️ تعداد پیام مجاز',
            'antispam_set_seconds'  => '⏱ بازه زمانی',
            'antispam_set_mute'     => '🔇 مدت آف بودن',
        ],
    ],
    'features_nav' => [
        'title' => 'قابلیت‌ها — منوی دسته', 'icon' => 'list-tree', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی اصلی «📌 وضعیت قابلیت‌ها» (دسته‌بندی).',
        'buttons' => [
            'featcat_bot'              => '🤖 دسته آپشن اصلی',
            'featcat_users'            => '👥 دسته کاربران',
            'featcat_shop'             => '🛍 دسته فروش',
            'featcat_lottery'          => '🎁 دسته گردونه',
            'featcat_crons'            => '⏱ دسته کرون‌ها',
            'featcat_antispam'         => '🛡 دسته آنتی‌اسپم',
            'premium_emoji_settings'   => '🌟 ایموجی پرمیوم',
            'featcat_main'             => '🔙 بازگشت به منوی دسته',
            'close_stat'               => '❌ بستن منو',
        ],
    ],
    'admin_pagination' => [
        'title' => 'صفحه‌بندی', 'icon' => 'pagination', 'type' => 'inline',
        'desc'  => 'دکمه‌های «صفحه بعدی / صفحه قبلی» در همه لیست‌های ربات.',
        'buttons' => [
            'next_page'               => '➡️ صفحه بعدی',
            'previous_page'           => '⬅️ صفحه قبلی',
            'next_page_extends'       => '➡️ صفحه بعدی (تمدید)',
            'previous_page_extends'   => '⬅️ صفحه قبلی (تمدید)',
            'next_pageuser'           => '➡️ صفحه بعدی (کاربران)',
            'previous_pageuser'       => '⬅️ صفحه قبلی (کاربران)',
            'next_pageuserbalance'    => '➡️ صفحه بعدی (موجودی)',
            'previous_pageuserbalance'=> '⬅️ صفحه قبلی (موجودی)',
            'next_pageusercart'       => '➡️ صفحه بعدی (کارت)',
            'previous_pageusercart'   => '⬅️ صفحه قبلی (کارت)',
            'next_pageuserrefral'     => '➡️ صفحه بعدی (زیرمجموعه)',
            'previous_pageuserrefral' => '⬅️ صفحه قبلی (زیرمجموعه)',
            'next_pageuserzero'       => '➡️ صفحه بعدی (موجودی صفر)',
            'previous_pageuserzero'   => '⬅️ صفحه قبلی (موجودی صفر)',
        ],
    ],
    'admin_stats' => [
        'title' => 'گزارشات / آمار', 'icon' => 'chart-bar', 'type' => 'inline',
        'desc'  => 'دکمه‌های گزارش و آمار ربات (امروز، دیروز، ماه و ...).',
        'buttons' => [
            'today_stat'        => '☀️ آمار امروز',
            'yesterday_stat'    => '🌙 آمار دیروز',
            'hoursago_stat'     => '⏰ آمار ساعات گذشته',
            'view_stat_time'    => '🕐 مشاهده با زمان',
            'month_current_stat'=> '📊 آمار ماه جاری',
            'month_old_stat'    => '📅 آمار ماه قبل',
            'stat_all_bot'      => '📈 آمار کلی ربات',
            'status_var'        => '📋 وضعیت متغیر',
            'showprice'         => '💰 نمایش قیمت',
        ],
    ],
    'admin_search' => [
        'title' => 'جستجو', 'icon' => 'search', 'type' => 'inline',
        'desc'  => 'دکمه‌های جستجو در ربات (کاربر، سرویس، سفارش).',
        'buttons' => [
            'searchorder'   => '🔍 جستجوی سفارش',
            'searchservice' => '🔍 جستجوی سرویس',
            'searchuser'    => '🔍 جستجوی کاربر',
            'selectname'    => '🏷️ انتخاب نام',
        ],
    ],
    'admin_user_lists' => [
        'title' => 'لیست‌های کاربر', 'icon' => 'list-checks', 'type' => 'inline',
        'desc'  => 'دکمه‌های فیلتر و نمایش لیست‌های مختلف کاربران.',
        'buttons' => [
            'alllistusers'    => '👥 همه کاربران',
            'agentlistusers'  => '🤝 لیست نمایندگان',
            'balanceuserlist' => '💰 لیست موجودی',
            'cartuserlist'    => '💳 لیست کارت‌ها',
            'adminlist'       => '👨‍💼 لیست ادمین‌ها',
            'listrefral'      => '🎁 لیست زیرمجموعه',
            'zerobalance'     => '0️⃣ موجودی صفر',
            'balanceaddall'   => '➕ افزایش موجودی همه',
        ],
    ],
    'admin_filters' => [
        'title' => 'فیلترها', 'icon' => 'filter', 'type' => 'inline',
        'desc'  => 'دکمه‌های فیلتر نوع کاربر/نماینده/قیمت/تخفیف/کش‌بک.',
        'buttons' => [
            
            'typecustomer_all'         => '👤 همه (نوع کاربر)',
            'typecustomer_customer'    => '👤 مشتری',
            'typecustomer_notcustomer' => '👤 غیر مشتری',
            
            'typebalanceall_all' => '💰 موجودی همه',
            'typebalanceall_f'   => '💰 موجودی نوع F',
            'typebalanceall_n2'  => '💰 موجودی نوع N2',
            'typebalanceall_nl'  => '💰 موجودی نوع NL',
            
            'typeaddprice_percent' => '📊 درصدی',
            'typeaddprice_static'  => '🔢 مبلغ ثابت',
            
            'typeagenteditproduct_f'  => '🛠 ویرایش محصول نوع F',
            'typeagenteditproduct_n'  => '🛠 ویرایش محصول نوع N',
            'typeagenteditproduct_n2' => '🛠 ویرایش محصول نوع N2',
            
            'discounttype_all'    => '🎁 همه تخفیف‌ها',
            'discounttype_buy'    => '🎁 فقط خرید',
            'discounttype_extend' => '🎁 فقط تمدید',
            'discountlimitbuy_0'  => '🚫 بدون محدودیت خرید',
            'discountlimitbuy_1'  => '✅ با محدودیت خرید',
            
            'typegift_day'    => '🎁 هدیه روزانه',
            'typegift_volume' => '🎁 هدیه حجمی',
            
            'voloume_or_day_all' => '📦 حجم یا روز - همه',
            
            'agenttypshowlist_all' => '🤝 همه نمایندگان',
            'agenttypshowlist_n'   => '🤝 نمایندگان N',
            'agenttypshowlist_n2'  => '🤝 نمایندگان N2',
        ],
    ],
    'admin_node' => [
        'title' => 'مدیریت نود', 'icon' => 'server', 'type' => 'inline',
        'desc'  => 'دکمه‌های مدیریت سرور (نود) — نام، آی‌پی، اضافه، حذف و ...',
        'buttons' => [
            'namenode'         => '🏷️ نام نود',
            'changenamenode'   => '✏️ تغییر نام نود',
            'changeipnode'     => '✏️ تغییر آی‌پی نود',
            'addiplogin'       => '➕ افزودن آی‌پی لاگین',
            'iploginset'       => '⚙️ تنظیم آی‌پی لاگین',
            'removenode'       => '❌ حذف نود',
            'reconnectnode'    => '♻️ اتصال مجدد نود',
            'bakcnode'         => '🔙 بازگشت نود',
            'actionnode'       => '⚙️ اقدامات نود',
        ],
    ],
    'admin_gateway_extra' => [
        'title' => 'تنظیمات درگاه‌ها — تکمیلی', 'icon' => 'credit-card', 'type' => 'inline',
        'desc'  => 'دکمه‌های تکمیلی هر درگاه (settings, کش‌بک، کاربران و ...).',
        'buttons' => [
            'cartsetting'              => '⚙️ تنظیمات کارت‌به‌کارت',
            'carttocart'               => '💳 درگاه کارت‌به‌کارت',
            'aqayepardakhtsetting'     => '⚙️ تنظیمات آقای پرداخت',
            'zarinpalsetting'          => '⚙️ تنظیمات زرین‌پال',
            'zarinpeysetting'          => '⚙️ تنظیمات زرین‌پی',
            'plisiosetting'            => '⚙️ تنظیمات Plisio',
            'nowpaymentsetting'        => '⚙️ تنظیمات NowPayment',
            'iranpay1setting'          => '⚙️ تنظیمات ایران‌پی ۱',
            'iranpay2setting'          => '⚙️ تنظیمات ایران‌پی ۲ (ترنادو)',
            'iranpay3setting'          => '⚙️ تنظیمات ایران‌پی ۳',
            'affilnecurrency'          => '💰 ارزی ریالی',
            'affilnecurrencysetting'   => '⚙️ تنظیمات ارزی ریالی',
            'arzireyali1'              => '💵 ارزی ریالی ۱',
            'arzireyali2'              => '💵 ارزی ریالی ۲',
            'oniranpay3'               => '🟢 ارزی ریالی ۳',
        ],
    ],
    'admin_cart_advanced' => [
        'title' => 'کارت‌به‌کارت — پیشرفته', 'icon' => 'credit-card-cog', 'type' => 'inline',
        'desc'  => 'تنظیمات پیشرفته درگاه کارت‌به‌کارت.',
        'buttons' => [
            'cart_autocheck'   => '🤖 تایید رسید بدون بررسی',
            'cart_autotime'    => '⏳ زمان تایید خودکار',
            'cart_except_user' => '💳 استثناء کاربر',
            'cart_export_num'  => '📄 خروجی شماره‌کارت‌ها',
            'cart_group_num'   => '♻️ نمایش گروهی شماره‌کارت',
            'cart_hide_num'    => '🚫 مخفی کردن شماره',
            'cart_show_num'    => '👁️ نمایش شماره',
            'checkpay'         => '✅ بررسی پرداخت',
            'paydirect'        => '💳 پرداخت مستقیم',
        ],
    ],
    'admin_crypto' => [
        'title' => 'کیف پول کریپتو', 'icon' => 'wallet', 'type' => 'inline',
        'desc'  => 'انتخاب کیف پول کریپتو + گفت‌و‌گوی ممو (TON, TRX, USDT).',
        'buttons' => [
            'cryptowallet_TON'        => '🟦 تون (TON)',
            'cryptowallet_TRX'        => '🟩 ترون (TRX)',
            'cryptowallet_USDT_TON'   => '💵 USDT-TON',
            'cryptowallet_USDT_TRC20' => '💵 USDT-TRC20',
            'walletaddress'           => '📋 آدرس کیف پول',
            'cryptomemo_yes_TON'      => '✅ دارم (ممو TON)',
            'cryptomemo_no_TON'       => '❌ ندارم (ممو TON)',
            'cryptomemo_yes_USDT_TON' => '✅ دارم (ممو USDT-TON)',
            'cryptomemo_no_USDT_TON'  => '❌ ندارم (ممو USDT-TON)',
        ],
    ],
    'admin_iplogin' => [
        'title' => 'ادمین — مدیریت آی‌پی ورود', 'icon' => 'shield-halved', 'type' => 'inline',
        'desc'  => 'دکمه‌های منوی تنظیم آی‌پی ورود به پنل وب.',
        'buttons' => [
            'addiplogin'        => '➕ افزودن آیپی',
            'iploginunlim_on'   => '♾️ فعال‌سازی حالت نامحدود',
            'iploginunlim_off'  => '🔒 غیرفعال‌سازی حالت نامحدود',
            'iploginset'        => '🛡 مدیریت آیپی‌ها',
        ],
    ],
    'admin_infocard' => [
        'title' => 'کارت مشخصات — رنگ', 'icon' => 'palette', 'type' => 'inline',
        'desc'  => 'انتخاب رنگ کارت مشخصات سرویس.',
        'buttons' => [
            'infocard_setcolor_red'    => '🔴 قرمز',
            'infocard_setcolor_green'  => '🟢 سبز',
            'infocard_setcolor_blue'   => '🔵 آبی',
            'infocard_setcolor_orange' => '🟠 نارنجی',
            'infocard_setcolor_purple' => '🟣 بنفش',
            'infocard_setcolor_yellow' => '🟡 زرد',
        ],
    ],
    'admin_discount_settings' => [
        'title' => 'تخفیف / هدیه', 'icon' => 'gift', 'type' => 'inline',
        'desc'  => 'دکمه‌های مدیریت کدهای تخفیف و هدیه.',
        'buttons' => [
            'discountextend'    => '🎁 تخفیف تمدید',
            'startgift'         => '🎁 شروع هدیه',
            'get_gift_start'    => '🎁 دریافت هدیه (شروع)',
            'statuscategorytime'=> '📊 وضعیت دسته زمان',
            'statustimeextra'   => '⏳ وضعیت زمان اضافه',
        ],
    ],
    'admin_misc_actions' => [
        'title' => 'اقدامات متفرقه ادمین', 'icon' => 'wand-2', 'type' => 'inline',
        'desc'  => 'دکمه‌های متفرقه ادمین (افزودن ادمین، کش، بهینه‌سازی و ...).',
        'buttons' => [
            'addnewadmin'           => '➕ افزودن ادمین جدید',
            'customsellvolume'      => '💰 فروش حجم سفارشی',
            'changecoefficient'     => '🔢 تغییر ضریب',
            'changgestatus'         => '🔁 تغییر وضعیت',
            'categroygenral'        => '📂 دسته عمومی',
            'changenote'            => '📝 تغییر یادداشت',
            'optimizebot'           => '🗑 بهینه‌سازی ربات',
            'removeresid'           => '❌ حذف رسید',
            'productcheckdata'      => '🔍 بررسی محصول',
            'mainbalanceaccount'    => '💎 موجودی اصلی',
            'maxbalanceaccount'     => '🔝 حداکثر موجودی',
            'kharidanbuh'           => '🛒 خرید عمده',
            'systemsms'             => '📲 سیستم پیامک',
            'linkappdownlod'        => '🔗 لینک دانلود برنامه',
            'fqQuestions'           => '❓ سوالات متداول',
            'disorderss'            => '⚠️ گزارش اختلال',
            'reasetchangeloc'       => '♻️ ریست تغییر لوکیشن',
            'serviceextendselect_pre'=> '🔄 پیش‌انتخاب تمدید',
            'removeservicebackbtn'  => '🔙 بازگشت حذف سرویس',
            'startelegram'          => '⭐ ستاره تلگرام',
        ],
    ],
    'admin_premium_stock' => [
        'title' => 'پرمیوم / استاک', 'icon' => 'star', 'type' => 'inline',
        'desc'  => 'مدیریت ایموجی پرمیوم و موجودی استاک.',
        'buttons' => [
            'premium_emoji_add'   => '🌟 افزودن ایموجی پرمیوم',
            'premium_emoji_noop'  => '🌟 (placeholder)',
            'nm_del_all_stock'    => '❌ حذف کل موجودی',
            'nm_del_one_stock'    => '❌ حذف یک موجودی',
            'antispam_noop'       => '🛡 (placeholder آنتی‌اسپم)',
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
            'RemainingVolume'  => '🔋 حجم باقیمانده',
            'expirationDate'   => '⏳ تاریخ انقضا',
            'extravolunme'     => '➕ حجم اضافه',
            'exntedagei'       => '⏳ تمدید زمان',
            'Responseuser'     => '💬 پاسخ به کاربر',
            'requestagent'     => '🤝 درخواست نمایندگی',
            'iduser'           => '🆔 آیدی کاربر',
            'username'         => '👤 یوزرنیم',
            'notusernameme'    => '🚫 بدون یوزرنیم',
        ],
    ],
    'admin_lists' => [
        'title' => 'ادمین — لیست‌های داینامیک', 'icon' => 'list', 'type' => 'inline',
        'desc'  => 'رنگ پیش‌فرض دکمه‌های لیست‌های داینامیک (لیست پنل‌ها، کاربران، محصولات، کانال‌ها و...). همه دکمه‌های هر لیست با همون رنگ نمایش داده می‌شن.',
        'buttons' => [
            'panel_list'    => '🖥 لیست پنل‌ها (مدیریت پنل)',
            'paneluser_list'=> '👤 لیست پنل‌ها (جستجوی کاربر)',
            'usertest_list' => '🧪 لیست پنل‌ها (اکانت تست)',
            'changeloc_list'=> '🌍 لیست لوکیشن‌ها (تغییر مکان)',
            'product_list'  => '🛍 لیست محصولات',
            'user_services_list' => '🛒 لیست سرویس‌های کاربر (سرویس‌های من)',
            'discount_list' => '🎁 لیست کدهای تخفیف',
            'inbound_list'  => '🔌 لیست اینباندها',
            'help_list'     => '📚 لیست آیتم‌های آموزش',
            'channel_list'  => '📯 لیست کانال‌ها',
            'card_list'     => '💳 لیست شماره‌کارت‌ها',
            'protocol_list' => '🔗 لیست پروتکل‌ها',
            'category_list' => '🗂 لیست دسته‌بندی‌ها',
            'user_list'     => '👥 لیست کاربران',
            'agent_list'    => '🤝 لیست نمایندگان',
            'feature_toggle'   => '🔘 دکمه‌های وضعیت روشن/خاموش (editstsuts-)',
            'support_response' => '💬 دکمه‌های پاسخ پشتیبانی (Response_*)',
            'extra_purchase'   => '➕ دکمه‌های خرید اضافی (Extra_time_, Extra_volume_, ...)',
            'btnmsg_settings'  => '📝 تنظیمات نوع پیام منو (btntypemessage-*)',
            'user_confirms'    => '✅ دکمه‌های تایید (confirmaccountdisable_, confirmaextra-, ...)',
            'admin_removes'    => '🗑 دکمه‌های حذف ادمین (removeadmin_, removeagent_, ...)',
            'crypto_actions'   => '₿ دکمه‌های انتخاب کیف کریپتو (crypto_pay_, cryptowallet_)',
            'service_actions'  => '🔧 اقدامات سرویس (config_, changelink_, changestatus_, ...)',
            'pagination_btns'  => '◀▶ دکمه‌های صفحه‌بندی (previous_page, next_page)',
            'broadcast_actions'=> '📢 دکمه‌های ارسال پیام انبوه',
            'affiliate_actions'=> '🤝 دکمه‌های زیرمجموعه (affiliates-*)',
            'shop_edit_actions'=> '✏️ ویرایش‌های فروشگاه (editshops-*)',
            'node_actions'     => '🖧 اقدامات نود (changeipnode, changenamenode, ...)',
            'admin_iplogin_dyn'=> '🛡 دکمه‌های حذف آی‌پی ورود (deliplogin_*)',
            'crypto_manual_actions' => '🔁 دکمه‌های بررسی دستی کریپتو (confirmcryptomanual_, rejectcryptomanual_, rcc_pick_)',
            'fallback_inline'  => '🌐 ✱ پیش‌فرض همه دکمه‌های دیگر inline (بدون رنگ خاص)',
        ],
    ],
    'user_dynamic_lists' => [
        'title' => 'کاربر — لیست‌های داینامیک', 'icon' => 'shopping-cart', 'type' => 'inline',
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
            'auto_inline_btn'=> '🎨 ✱ پیش‌فرض همه دکمه‌های inline تبدیل‌شده از reply (apn:*)',
        ],
    ],
    'cron_notifications' => [
        'title' => 'کرون‌ها و گروه گزارش — دکمه اطلاعیه‌ها', 'icon' => 'bell', 'type' => 'inline',
        'desc'  => 'رنگ دکمه‌های اطلاعیه‌های کرون (هشدار اتمام سرویس، حذف اکانت تست، گزارش‌های گروه ادمین و ...). همه‌ی دکمه‌های دینامیک با همین کلیدها نمایش داده می‌شوند.',
        'buttons' => [
            'cron_extend'        => '💊 تمدید سرویس (هشدار اتمام، NoticationsService/webhooks)',
            'cron_manage_user'   => '👤 مدیریت کاربر جدید (گزارش گروه ادمین)',
            'cron_manage_panel'  => '🖥 مدیریت پنل (گزارش گروه ادمین)',
            'cron_action_btn'    => '⚙️ سایر اقدامات کرون (cronnotify_*)',
            'cron_buy_service'   => '🛒 خرید سرویس (حذف اکانت تست، broadcast)',
            'cron_start_bot'     => '🚀 شروع ربات (broadcast)',
            'cron_usertest'      => '🧪 اکانت تست (broadcast)',
            'cron_help'          => '📚 آموزش (broadcast)',
            'cron_affiliates'    => '👥 زیرمجموعه (broadcast)',
            'cron_addbalance'    => '💰 افزایش موجودی (broadcast)',
            'cron_cancel'        => '❌ انصراف (broadcast)',
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
    'recheckcrypto_admin_buttons' => [
        'title' => 'ادمین — تایید/رد بررسی دستی کریپتو', 'icon' => 'shield-check', 'type' => 'inline',
        'desc'  => 'دکمه‌های تایید و رد درخواست بررسی دستی پرداخت کریپتو در پیام ادمین (پیوی + گروه گزارش).',
        'buttons' => [
            'confirmcryptomanual'   => '✅ تایید و شارژ کیف پول',
            'rejectcryptomanual'    => '❌ رد درخواست',
            'cmauto'                => '⚡ تایید با همان مبلغ خودکار',
            'cmmanual'              => '✏️ ویرایش مبلغ و تایید',
            'cmback'                => '🔙 بازگشت',
        ],
    ],
    'invoice_copy_buttons' => [
        'title' => 'کاربر — دکمه‌های پرداخت کریپتو', 'icon' => 'copy', 'type' => 'inline',
        'desc'  => 'رنگ دکمه‌های مسیر کامل پرداخت ارز دیجیتال در سمت کاربر — از انتخاب ارز تا کپی‌کردن آدرس/مبلغ/ممو و پرداخت کردم.',
        'buttons' => [
            'currency_pick' => '💎 انتخاب ارز (TRX/TON/USDT)',
            'mode_external' => '🌍 کیف پول خارجی',
            'mode_iranian'  => '🇮🇷 صرافی ایرانی',
            'copy_wallet'   => '🔗 کپی آدرس کیف پول',
            'copy_amount'   => '🪙 کپی مبلغ',
            'copy_memo'     => '🏷 کپی ممو',
            'paid_submit'   => '✅ پرداخت کردم | ارسال هش',
            'invoice_back'  => '🔙 بازگشت',
        ],
    ],
    'misc_buttons' => [
        'title' => 'ادمین — دکمه‌های متفرقه', 'icon' => 'square-plus', 'type' => 'inline',
        'desc'  => 'دکمه‌های تک منوها (دکمه بازگشت ادمین، پذیرش قوانین، انصراف و ...).',
        'buttons' => [
            'adm_backmenu'      => '▶️ بازگشت به منوی قبل (ادمین)',
            'adm_backadmin'     => '🏠 بازگشت به منوی ادمین',
            'hide_mini_app'     => '⛓️‍💥 دیگر نمایش نده (Mini App)',
            'backproductadmin'  => '🔙 بازگشت محصول (ادمین)',
            'backadmin'         => '🏠 بازگشت (Reply back ادمین)',
            'backlistuser'      => '🔙 بازگشت به لیست کاربر',
            'buyback'           => '◀️ بازگشت خرید',
            'cancel_gift'       => '❌ انصراف کد هدیه',
            'cancel_hash_input' => '❌ انصراف ورود هش',
            'cancel_sendmessage'=> '❌ انصراف ارسال پیام',
            'close_listusers'   => '❌ بستن لیست کاربران',
            'close_stat'        => '❌ بستن منوی قابلیت‌ها',
            'cronjobs_back_settings'=> '🔙 بازگشت تنظیمات کرون',
            'resetbot_cancel'   => '❌ انصراف ریست ربات',
            'resetbot_confirm'  => '✅ تایید ریست ربات',
            'broadcast_status_refresh' => '🔄 بروزرسانی وضعیت پیام انبوه',
            'reject'            => '❌ رد',
            'accept'            => '✅ پذیرش',
            'acceptrule'        => '✅ پذیرش قوانین',
            'confirmpaid'       => '✅ تایید پرداخت',
            'confirmandgetservice' => '✅ تایید و دریافت سرویس',
            'confirmandgetserviceDiscount' => '🎁 تایید با تخفیف',
            'confirmchannel'    => '✅ تایید کانال',
            'confirmserdiscount'=> '🎁 تایید سرویس تخفیف',
            'confirmserivce'    => '✅ تایید سرویس',
            'agentpanel'        => '🤝 پنل نماینده',
            'cronjob_display'   => '⏱ نمایش کرون‌ها',
            'startaction'       => '▶️ شروع اقدام',
            'locationedit_all'  => '🌍 ویرایش همه لوکیشن‌ها',
            'locationmessage_all'=> '📢 پیام به همه لوکیشن‌ها',
            'backuser'          => '👤 بازگشت کاربر (backuser)',
            'nav_back'          => '◀️ بازگشت عمومی کاربر (nav_back)',
            'backorder'         => '🏠 بازگشت به لیست سرویس‌ها (backorder)',
            'colselist'         => '❌ بستن لیست (colselist)',
            'supportbtns'       => '🤙 دکمه پشتیبانی (supportbtns)',
            'helpbtns'          => '📚 دکمه آموزش (helpbtns)',
            'usertestbtn'       => '🧪 دکمه اکانت تست (usertestbtn)',
            'inert_label'       => '🏷️ دکمه‌های لیبل بدون عملکرد (callback=none)',
        ],
    ],
    'admin_panel_marzban' => [
        'title' => 'ادمین — ویرایش پنل مرزبان', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل مرزبان (پس از انتخاب پنل توسط ادمین). هر دکمه می‌تواند رنگ مجزا داشته باشد. کلید هر دکمه = متن دقیق آن دکمه در ربات.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل"     => '⚙️ وضعیت قابلیت‌ها پنل',
            "✍️ نام پنل"                 => '✍️ نام پنل',
            "❌ حذف پنل"                 => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور"         => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری"        => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل"         => '🔗 ویرایش آدرس پنل',
            "⚙️ تنظیم پروتکل و اینباند" => '⚙️ تنظیم پروتکل و اینباند',
            "🔋 روش تمدید سرویس"          => '🔋 روش تمدید سرویس',
            "💡 روش ساخت نام کاربری"      => '💡 روش ساخت نام کاربری',
            "🚨 محدودیت ساخت اکانت"       => '🚨 محدودیت ساخت اکانت',
            "📍 تغییر گروه کاربری"        => '📍 تغییر گروه کاربری',
            "⏳ زمان سرویس تست"          => '⏳ زمان سرویس تست',
            "💾 حجم اکانت تست"           => '💾 حجم اکانت تست',
            "⚙️ قیمت حجم سرویس دلخواه"  => '⚙️ قیمت حجم سرویس دلخواه',
            "➕ قیمت حجم اضافه"           => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه"          => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه"         => '⏳ قیمت زمان دلخواه',
            "🌍 قیمت تغییر لوکیشن"        => '🌍 قیمت تغییر لوکیشن',
            "📍 حداقل حجم دلخواه"         => '📍 حداقل حجم دلخواه',
            "📍 حداکثر حجم دلخواه"        => '📍 حداکثر حجم دلخواه',
            "📍 حداقل زمان دلخواه"        => '📍 حداقل زمان دلخواه',
            "📍 حداکثر زمان دلخواه"       => '📍 حداکثر زمان دلخواه',
            "⚙️  اینباند اکانت غیرفعال"  => '⚙️ اینباند اکانت غیرفعال',
            "📦 انبار شبکه ملی"           => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری"          => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری"              => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی"            => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل برای کاربر',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_guard' => [
        'title' => 'ادمین — ویرایش پنل Guard', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل Guard.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش کلید" => '🔐 ویرایش کلید',
            "⁉️ وضعیت اتصال به پنل" => '⁉️ وضعیت اتصال',
            "⚙️ تنظیم سرویس ها" => '⚙️ تنظیم سرویس‌ها',
            "🎛️ تنظیمات سرویس" => '🎛️ تنظیمات سرویس',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "⚙️  اینباند اکانت غیرفعال" => '⚙️ اینباند غیرفعال',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_ibsng' => [
        'title' => 'ادمین — ویرایش پنل IBSng', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل IBSng.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            '🎛 تنظیم نام گروه' => '🎛 تنظیم نام گروه',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_mikrotik' => [
        'title' => 'ادمین — ویرایش پنل Mikrotik', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل Mikrotik.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            '🎛 تنظیم نام گروه' => '🎛 تنظیم نام گروه',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_s_ui' => [
        'title' => 'ادمین — ویرایش پنل S-UI', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل S-UI.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "⚙️ تنظیم پروتکل و اینباند" => '⚙️ پروتکل و اینباند',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "⚙️  اینباند اکانت غیرفعال" => '⚙️ اینباند غیرفعال',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_wg' => [
        'title' => 'ادمین — ویرایش پنل WGDashboard', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل WGDashboard.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "💎 تنظیم شناسه اینباند" => '💎 شناسه اینباند',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "⚙️  اینباند اکانت غیرفعال" => '⚙️ اینباند غیرفعال',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_marzneshin' => [
        'title' => 'ادمین — ویرایش پنل Marzneshin', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل Marzneshin.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "⚙️ تنظیمات سرویس" => '⚙️ تنظیمات سرویس',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_manualsale' => [
        'title' => 'ادمین — ویرایش پنل فروش دستی', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل فروش دستی (Manualsale).',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "➕ اضافه کردن کانفیگ" => '➕ افزودن کانفیگ',
            "❌ حذف کانفیگ " => '❌ حذف کانفیگ',
            "✏️ ویرایش کانفیگ" => '✏️ ویرایش کانفیگ',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_x_ui_single' => [
        'title' => 'ادمین — ویرایش پنل X-UI تک‌پورت', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل X-UI تک‌پورت.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💎 تنظیم شناسه اینباند" => '💎 شناسه اینباند',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            '🔗 دامنه لینک ساب' => '🔗 دامنه لینک ساب',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_alireza_single' => [
        'title' => 'ادمین — ویرایش پنل علیرضا تک‌پورت', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل علیرضا تک‌پورت.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔐 ویرایش رمز عبور" => '🔐 ویرایش رمز عبور',
            "👤 ویرایش نام کاربری" => '👤 ویرایش نام کاربری',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "💎 تنظیم شناسه اینباند" => '💎 شناسه اینباند',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            '🔗 دامنه لینک ساب' => '🔗 دامنه لینک ساب',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_hiddify' => [
        'title' => 'ادمین — ویرایش پنل Hiddify', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی ویرایش پنل Hiddify.',
        'buttons' => [
            "⚙️ وضعیت قابلیت ها پنل" => '⚙️ وضعیت قابلیت‌ها',
            "✍️ نام پنل" => '✍️ نام پنل',
            "❌ حذف پنل" => '❌ حذف پنل',
            "🔗 ویرایش آدرس پنل" => '🔗 ویرایش آدرس',
            "🔋 روش تمدید سرویس" => '🔋 روش تمدید',
            "📍 تغییر گروه کاربری" => '📍 تغییر گروه',
            "💡 روش ساخت نام کاربری" => '💡 روش ساخت نام',
            '🔗 دامنه لینک ساب' => '🔗 دامنه لینک ساب',
            "🚨 محدودیت ساخت اکانت" => '🚨 محدودیت ساخت',
            "🔗 uuid admin" => '🔗 UUID Admin',
            "⏳ زمان سرویس تست" => '⏳ زمان تست',
            "💾 حجم اکانت تست" => '💾 حجم تست',
            "🌍 قیمت تغییر لوکیشن" => '🌍 قیمت لوکیشن',
            "➕ قیمت حجم اضافه" => '➕ قیمت حجم اضافه',
            "⏳ قیمت زمان اضافه" => '⏳ قیمت زمان اضافه',
            "⚙️ قیمت حجم سرویس دلخواه" => '⚙️ قیمت حجم دلخواه',
            "⏳ قیمت زمان دلخواه" => '⏳ قیمت زمان دلخواه',
            "📍 حداقل حجم دلخواه" => '📍 حداقل حجم',
            "📍 حداکثر حجم دلخواه" => '📍 حداکثر حجم',
            "📍 حداقل زمان دلخواه" => '📍 حداقل زمان',
            "📍 حداکثر زمان دلخواه" => '📍 حداکثر زمان',
            "📦 انبار شبکه ملی" => '📦 انبار شبکه ملی',
            "📌 ثبت پنل اضطراری" => '📌 ثبت پنل اضطراری',
            "🚨 پنل اضطراری" => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی" => '🌐 وضعیت نت ملی',
            "🫣 مخفی کردن پنل برای یک کاربر" => '🫣 مخفی کردن پنل',
            "❌  حذف کاربر از لیست مخفی شدگان" => '❌ حذف کاربر مخفی',
        ],
    ],
    'admin_panel_athmarzban' => [
        'title' => 'ادمین — احراز هویت Marzban', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی احراز هویت Marzban (ATH).',
        'buttons' => [
            "🔧 ساخت کانفیگ دستی" => '🔧 ساخت کانفیگ دستی',
            "🖥 مدیریت نود ها"     => '🖥 مدیریت نودها',
        ],
    ],
    'admin_panel_athx_ui' => [
        'title' => 'ادمین — احراز هویت X-UI', 'icon' => 'gear', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی احراز هویت X-UI (ATH).',
        'buttons' => [
            "🔧 ساخت کانفیگ دستی" => '🔧 ساخت کانفیگ دستی',
        ],
    ],
    'admin_panel_stock_manage' => [
        'title' => 'ادمین — مدیریت انبار شبکه ملی', 'icon' => 'package', 'type' => 'reply',
        'desc'  => 'دکمه‌های منوی «📦 انبار شبکه ملی» (افزودن/ویرایش/حذف انبار و کانفیگ).',
        'buttons' => [
            "➕ افزودن انبار مدنظر"             => '➕ افزودن انبار',
            "➕ وارد کردن دسته‌ای انبار"        => '➕ ورود دسته‌ای',
            "➕ افزودن کانفیگ تکی انبار"        => '➕ کانفیگ تکی',
            "✏️ ویرایش انبار"                  => '✏️ ویرایش انبار',
            "❌ حذف کانفیگ انبار"              => '❌ حذف کانفیگ',
            "🗑 حذف کامل انبار"                => '🗑 حذف کامل انبار',
            "📊 گزارش موجودی انبار"             => '📊 گزارش موجودی',
            "🔄 همگام‌سازی محصولات انبار"      => '🔄 همگام‌سازی',
            "🚨 پنل اضطراری"                   => '🚨 پنل اضطراری',
            "🌐 وضعیت نت ملی"                 => '🌐 وضعیت نت ملی',
            "🔙 بازگشت به انبار"                => '🔙 بازگشت به انبار',
            "🔢 حذف کانفیگ با آیدی"             => '🔢 حذف با آیدی',
            "📋 نمایش لیست کانفیگ‌ها"           => '📋 لیست کانفیگ‌ها',
            "🗑 حذف همه کانفیگ‌های فعال این انبار" => '🗑 حذف همه کانفیگ‌ها',
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

// One-time normalisation: drop user picks that exactly match a factory default,
// so that toggling defaults off later actually removes them from the visible UI.
// (Legacy data may contain merged-in defaults persisted as user picks.)
foreach ($factoryDefaultsMap as $rxSec => $rxPairs) {
    if (empty($userStylesRaw[$rxSec]) || !is_array($userStylesRaw[$rxSec])) continue;
    foreach ($rxPairs as $rxKey => $rxVal) {
        if (isset($userStylesRaw[$rxSec][$rxKey]) && $userStylesRaw[$rxSec][$rxKey] === $rxVal) {
            unset($userStylesRaw[$rxSec][$rxKey]);
        }
    }
}
unset($rxSec, $rxPairs, $rxKey, $rxVal);

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
    // Keep currentStyles in sync with normalised userStylesRaw so initial render
    // doesn't show stale "default" placeholders that were removed above.
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
    'user'  => ['label' => '👤 بخش کاربر',  'menus' => ['service','account','payment','user_nav','pay_receipt','user_subscription','invoice_copy_buttons','recheckcrypto_buttons','user_dynamic_lists']],
    'admin' => ['label' => '🛡 بخش ادمین', 'menus' => ['admin_main','admin_settings','admin_shop','admin_roles','admin_gateways','admin_features','admin_channel','admin_help','admin_category','admin_products','admin_product_edit','features_nav','features_bot','features_users','features_shop','features_lottery','features_crons','features_antispam','admin_pagination','admin_stats','admin_search','admin_user_lists','admin_filters','admin_node','admin_gateway_extra','admin_cart_advanced','admin_crypto','admin_iplogin','admin_infocard','admin_discount_settings','admin_misc_actions','admin_premium_stock','admin_lists','cron_notifications','admin_panel_marzban','admin_panel_guard','admin_panel_ibsng','admin_panel_mikrotik','admin_panel_s_ui','admin_panel_wg','admin_panel_marzneshin','admin_panel_manualsale','admin_panel_x_ui_single','admin_panel_alireza_single','admin_panel_hiddify','admin_panel_athmarzban','admin_panel_athx_ui','recheckcrypto_admin_buttons','misc_buttons']],
];


function phoneRows(string $mKey, array $btns): array {
    if ($mKey === 'service') {
        $rest = array_values(array_filter($btns, function($b) { return $b !== 'updateinfo' && $b !== 'backorder'; }));
        $rows = [['updateinfo']];
        while (count($rest) >= 2) $rows[] = [array_shift($rest), array_shift($rest)];
        if ($rest) $rows[] = $rest;
        $rows[] = ['backorder'];
        return $rows;
    }
    if ($mKey === 'account')       return [['Discount','Add_Balance'],['backuser']];
    if ($mKey === 'admin_main')    return [['admin_status'],['admin_managepanel','admin_addpanel'],['admin_timeprice','admin_volprice'],['admin_users','admin_shop'],['admin_finance'],['admin_support','admin_help'],['admin_features'],['admin_settings','admin_invoices'],['admin_back']];
    if ($mKey === 'admin_settings')return [['set_features'],['set_reports','set_channel'],['set_webpanel'],['set_optimize'],['set_text','set_adminmgr'],['set_testlimit'],['set_agentprice','set_qrbg'],['set_webhook'],['set_backadmin','set_backmenu']];
    if ($mKey === 'admin_shop')    return [['shop_status'],['shop_category','shop_products'],['shop_giftadd','shop_giftdel'],['shop_discountadd','shop_discountdel'],['shop_minbulk','shop_renewcb'],['shop_backadmin','shop_backmenu']];
    if ($mKey === 'user_nav')      return [['confirm_pay'],['confirm_discount'],['confirm_back'],['rules_accept'],['nav_back'],['contact_phone','contact_back']];
    if ($mKey === 'pay_receipt')   return [['pay_done'],['pay_sendreceipt'],['pay_wallet_copy','pay_card_copy'],['pay_check'],['pay_cancel','pay_back']];
    if ($mKey === 'admin_roles')   return [['seller_status'],['seller_users'],['seller_back'],['support_users','support_search'],['support_back']];
    if ($mKey === 'admin_features') return [['feat_info'],['feat_test','feat_help'],['feat_back','feat_backmenu']];
    if ($mKey === 'admin_channel')  return [['ch_add','ch_del'],['ch_back','ch_backmenu']];
    if ($mKey === 'admin_help')     return [['help_add','help_del'],['help_edit'],['help_back','help_backmenu']];
    if ($mKey === 'admin_category') return [['cat_add','cat_del'],['cat_edit'],['cat_back']];
    if ($mKey === 'admin_products') return [['shopitem_add','shopitem_del'],['shopitem_edit'],['shopitem_priceinc','shopitem_pricedec'],['shopitem_back']];
    if ($mKey === 'features_nav')   return [['featcat_bot','featcat_users'],['featcat_shop','featcat_lottery'],['featcat_crons','featcat_antispam'],['premium_emoji_settings'],['featcat_main','close_stat']];
    if ($mKey === 'features_bot')   return [['subject','subjectde'],['statusbot'],['stautsrolee'],['Authenticationphone'],['Authenticationiran'],['verify'],['verifybyuser'],['inlinebtnmain']];
    if ($mKey === 'features_users') return [['usernamebtn'],['statusnewuser'],['statussupportpv'],['statusnamecustom'],['statusnamecustomf']];
    if ($mKey === 'features_shop')  return [['bulkbuy'],['btn_status_category'],['keyconfig'],['copycart'],['Debtsettlement'],['changeloc','changeloclimit'],['infocard_status','infocard_color_menu'],['linkappstatus','linkappsetting']];
    if ($mKey === 'features_lottery')return[['wheel_luck','gradonhshans'],['wheelagentfirst'],['wheelagent'],['score','scoresetting'],['Lotteryagent'],['affiliatesstatus','settingaffiliatesf'],['Dice']];
    if ($mKey === 'features_crons') return [['cronday','settimecornday'],['on_hold','setting_on_holdcron'],['cronvolume','settimecornvolume'],['notifremove','settimecornremove'],['notifremove_volume','settimecornremovevolume'],['cronjobs_settings']];
    if ($mKey === 'features_antispam')return[['antispam_toggle'],['antispam_set_count'],['antispam_set_seconds'],['antispam_set_mute']];
    if ($mKey === 'admin_pagination')      return [['next_page','previous_page'],['next_pageuser','previous_pageuser'],['next_pageuserbalance','previous_pageuserbalance']];
    if ($mKey === 'admin_stats')            return [['today_stat','yesterday_stat'],['hoursago_stat','view_stat_time'],['month_current_stat','month_old_stat'],['stat_all_bot']];
    if ($mKey === 'admin_search')           return [['searchorder'],['searchservice','searchuser'],['selectname']];
    if ($mKey === 'admin_user_lists')       return [['alllistusers','agentlistusers'],['balanceuserlist','cartuserlist'],['adminlist','listrefral'],['zerobalance','balanceaddall']];
    if ($mKey === 'admin_filters')          return [['typecustomer_all'],['typecustomer_customer','typecustomer_notcustomer'],['typebalanceall_all'],['typebalanceall_f','typebalanceall_n2'],['typeaddprice_percent','typeaddprice_static']];
    if ($mKey === 'admin_node')             return [['namenode','changenamenode'],['changeipnode','addiplogin'],['removenode','reconnectnode'],['bakcnode','actionnode']];
    if ($mKey === 'admin_gateway_extra')    return [['cartsetting','carttocart'],['aqayepardakhtsetting','zarinpalsetting'],['zarinpeysetting','plisiosetting'],['nowpaymentsetting'],['iranpay1setting','iranpay2setting'],['iranpay3setting']];
    if ($mKey === 'admin_cart_advanced')    return [['cart_autocheck'],['cart_autotime'],['cart_except_user'],['cart_export_num'],['cart_show_num','cart_hide_num'],['cart_group_num'],['checkpay','paydirect']];
    if ($mKey === 'admin_crypto')           return [['cryptowallet_TON','cryptowallet_TRX'],['cryptowallet_USDT_TON','cryptowallet_USDT_TRC20'],['walletaddress']];
    if ($mKey === 'admin_infocard')         return [['infocard_setcolor_red','infocard_setcolor_green'],['infocard_setcolor_blue','infocard_setcolor_orange'],['infocard_setcolor_purple','infocard_setcolor_yellow']];
    if ($mKey === 'admin_discount_settings')return [['discountextend'],['startgift','get_gift_start'],['statuscategorytime','statustimeextra']];
    if ($mKey === 'admin_misc_actions')     return [['addnewadmin'],['customsellvolume','changecoefficient'],['changgestatus','categroygenral'],['optimizebot','removeresid'],['productcheckdata','mainbalanceaccount'],['kharidanbuh','systemsms'],['linkappdownlod','fqQuestions'],['disorderss','startelegram']];
    if ($mKey === 'admin_premium_stock')    return [['premium_emoji_add'],['nm_del_all_stock','nm_del_one_stock']];
    if ($mKey === 'user_subscription')      return [['buy_service'],['Status','LastTraffic'],['RemainingVolume','expirationDate'],['extravolunme','exntedagei'],['helpbtn','support'],['Responseuser','requestagent'],['iduser','username']];
    if ($mKey === 'invoice_copy_buttons') return [['copy_wallet','copy_amount'],['copy_memo','paid_submit'],['invoice_back']];
    if ($mKey === 'admin_lists')    return [['panel_list'],['user_list'],['product_list'],['user_services_list'],['discount_list'],['inbound_list'],['help_list'],['channel_list'],['card_list']];
    if ($mKey === 'misc_buttons')   return [['adm_backmenu','adm_backadmin'],['hide_mini_app','backproductadmin'],['backadmin','backlistuser'],['buyback','cancel_gift'],['cancel_hash_input','cancel_sendmessage'],['close_listusers','close_stat'],['cronjobs_back_settings'],['resetbot_cancel','resetbot_confirm'],['broadcast_status_refresh'],['reject','accept'],['acceptrule','confirmpaid'],['confirmandgetservice','confirmandgetserviceDiscount'],['confirmchannel','confirmserdiscount','confirmserivce']];
    if ($mKey === 'admin_gateways')return [['cart_title'],['cart_setnum','cart_delnum'],['cart_autoconfirm','cart_cashback'],['cart_min','cart_max'],['cart_back'],['trnado_name'],['trnado_apikey'],['trnado_back'],['zpal_name','zpal_merchant'],['zpal_back'],['zpey_name','zpey_token'],['zpey_back'],['aqaye_name'],['aqaye_back'],['plisio_name','plisio_api'],['plisio_back']];
    
    return array_map(function($b) { return [$b]; }, $btns);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>رنگ‌بندی دکمه‌ها — پنل فاکسیما</title>
    <link rel="preload" href="fonts/Arad-BoldDots2.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <style>
        body { padding-top: 0 !important; }
        /* ── Topbar ── */
        .skb-top {
            position: fixed; top:0; left:0; right:0; z-index:1000;
            display:flex; align-items:center; gap:10px; padding:12px 20px;
            background:rgba(13,16,22,.94); backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
            border-bottom:1px solid var(--border-soft); flex-wrap:wrap;
        }
        .skb-brand { display:flex; align-items:center; gap:9px; font-weight:700; color:var(--accent); font-size:14px; }
        .skb-brand .lm { width:28px; height:28px; display:grid; place-items:center; background:var(--accent-soft); color:var(--accent); border-radius:6px; font-family:monospace; font-weight:800; border:1px solid var(--accent-mid); }
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
        @media(max-width:600px){ .skb-top{padding:10px 12px;} .skb-brand span:not(.lm){display:none;} .sv-counter-text small { display:none; } .sv-toggle-defaults span { display:none; } }

        /* Page */
        .skb-page { max-width:1000px; margin:0 auto; padding:82px 18px 60px; }

        /* Group tabs */
        .g-tabs { display:flex; gap:3px; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:12px 12px 0 0; padding:8px 12px 0; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
        .g-tabs::-webkit-scrollbar { display:none; }
        .g-tab { padding:8px 20px 10px; border:none; background:none; cursor:pointer; font-family:'Arad',sans-serif; font-size:13px; font-weight:700; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; border-radius:6px 6px 0 0; transition:all .15s; white-space:nowrap; flex-shrink:0; }
        .g-tab:hover { color:var(--text-main); background:var(--surface-2); }
        .g-tab.active { color:var(--accent); border-bottom-color:var(--accent); }

        /* Sub-tabs */
        .s-tabs-outer { position:relative; display:flex; align-items:stretch; background:var(--surface-1); border:1px solid var(--border-soft); border-top:none; border-bottom:1px solid var(--border-mid); }
        .s-tabs-wrap { flex:1 1 auto; min-width:0; overflow-x:auto; -webkit-overflow-scrolling:touch; scroll-behavior:smooth; padding:0 6px; }
        .s-tabs-wrap::-webkit-scrollbar { height:4px; }
        .s-tabs-wrap::-webkit-scrollbar-track { background:transparent; }
        .s-tabs-wrap::-webkit-scrollbar-thumb { background:var(--border-mid); border-radius:2px; }
        .s-tabs-wrap::-webkit-scrollbar-thumb:hover { background:var(--accent); }
        .s-tabs-wrap { scrollbar-width:thin; scrollbar-color:var(--border-mid) transparent; }
        .s-tabs { display:flex; gap:3px; min-width:max-content; }
        .s-tab { display:flex; align-items:center; gap:6px; padding:8px 13px 9px; border:none; background:none; cursor:pointer; font-family:'Arad',sans-serif; font-size:12px; font-weight:600; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; border-radius:4px 4px 0 0; transition:all .15s; white-space:nowrap; flex-shrink:0; }
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
        .btn-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(195px,1fr)); gap:10px; align-items:stretch; }
        .btn-card { background:var(--surface-2); border:1px solid var(--border-soft); border-radius:12px; padding:10px 11px 10px; transition:border-color .15s,transform .12s; display:flex; flex-direction:column; gap:8px; min-height:108px; }
        .btn-card:hover { border-color:var(--border-mid); transform:translateY(-1px); }
        .btn-key { font-size:10px; color:var(--text-muted); font-family:monospace; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex-shrink:0; }
        .btn-prev { display:flex; align-items:center; justify-content:center; padding:8px 10px; border-radius:9px; border:1px solid var(--border-mid); background:var(--surface-3); color:var(--text-main); font-size:12px; font-weight:600; font-family:'Arad',sans-serif; min-height:42px; text-align:center; line-height:1.35; transition:all .2s; cursor:default; flex:1 1 auto; word-break:break-word; }
        .btn-prev[data-style="primary"] { background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 75%,#000)); color:var(--accent-fg,#fff); border-color:var(--accent); }
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
            font-family:'Arad',sans-serif;
        }

        /* Pagination */
        .pagination { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:18px; padding:10px 0; }
        .pg-btn { background:var(--surface-2); border:1px solid var(--border-soft); color:var(--text-main); padding:8px 14px; border-radius:8px; cursor:pointer; font-family:'Arad',sans-serif; font-size:12px; font-weight:600; transition:all .15s; min-width:42px; display:inline-flex; align-items:center; justify-content:center; gap:4px; }
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
        .sw[data-style="default"] { background:var(--surface-3); border-color:var(--border-mid); }
        .sw[data-style="primary"] { background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 75%,#000)); }
        .sw[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); }
        .sw[data-style="danger"]  { background:linear-gradient(135deg,#ef4444,#dc2626); }
        .sw-tip { position:absolute; bottom:calc(100% + 5px); left:50%; transform:translateX(-50%); background:var(--surface-4,#1e2330); color:var(--text-main); font-size:10px; white-space:nowrap; padding:2px 6px; border-radius:4px; border:1px solid var(--border-soft); pointer-events:none; opacity:0; transition:opacity .12s; z-index:10; }
        .sw:hover .sw-tip { opacity:1; }

        /* Phone preview */
        .phone-sticky { position:sticky; top:82px; }
        .phone { background:#17212b; border:2px solid var(--border-mid); border-radius:15px; padding:13px 9px; box-shadow:0 8px 32px rgba(0,0,0,.5); }
        .ph-head { text-align:center; font-size:10px; color:rgba(255,255,255,.32); margin-bottom:9px; }
        .ph-btns { display:flex; flex-direction:column; gap:4px; }
        .ph-row { display:flex; gap:4px; }
        .ph-btn { flex:1; text-align:center; padding:7px 5px; border-radius:7px; font-size:11px; font-weight:600; font-family:'Arad',sans-serif; line-height:1.3; background:rgba(255,255,255,.07); color:rgba(255,255,255,.8); border:1px solid rgba(255,255,255,.1); transition:all .2s; }
        .ph-btn[data-style="primary"] { background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 75%,#000)); color:var(--accent-fg,#fff); border-color:var(--accent); }
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
            .btn-card { padding:9px 9px 8px; border-radius:10px; }
            .btn-key { font-size:9px; margin-bottom:5px; }
            .btn-prev { font-size:11px; padding:6px 6px; min-height:34px; border-radius:7px; }
            /* Bigger touch-friendly swatches */
            .sw { width:34px; height:34px; border-radius:9px; border-width:2.5px; }
            .swatches { gap:7px; margin-top:9px; flex-wrap:wrap; }
            .sw-tip { display:none; } /* hide tooltips on touch */
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
            .sw { width:38px; height:38px; }
            .swatches { gap:9px; }
        }
    </style>
</head>
<body>

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
        <span class="lm">F</span>
        <span>رنگ‌بندی دکمه‌های ربات</span>
    </div>
    <div class="skb-grow"></div>




    <div class="sv-counter" id="sv-counter" title="تعداد دکمه‌هایی که پیش‌فرض (بدون رنگ) هستن از کل دکمه‌ها">
        <span class="sv-counter-icon">🎨</span>
        <span class="sv-counter-text">
            <b id="sv-cnt-default">0</b><span class="sv-cnt-sep">/</span><span id="sv-cnt-total">0</span>
            <small>پیش‌فرض</small>
        </span>
    </div>

    <button type="button" class="btn btn-outline btn-sm sv-reset-btn" id="sv-reset-all" title="غیرفعال‌سازی همگانی رنگ‌ها — همه دکمه‌ها به حالت پیش‌فرض (بدون رنگ) برمی‌گردن">
        <?php echo icon('trash','svg-icon svg-sm'); ?>
        <span>غیرفعال‌سازی رنگ‌ها</span>
    </button>


    <label class="sv-toggle-defaults" title="اگه خاموش باشه، فقط رنگ‌هایی که خودت اینجا انتخاب کردی روی ربات اعمال می‌شه. اگه روشن باشه، رنگ‌های پیش‌فرض کارخانه‌ای هم اعمال می‌شن.">
        <input type="checkbox" id="sv-toggle-defaults" <?php echo $useBuiltinDefaults ? 'checked' : ''; ?> />
        <span>اعمال پیش‌فرض‌های کارخانه‌ای</span>
    </label>

    <div class="sv-badge" id="sv-badge"><span class="sv-dot"></span><span id="sv-txt">ذخیره خودکار</span></div>
    <a class="btn btn-outline btn-sm" href="keyboard.php"><?php echo icon('keyboard','svg-icon svg-sm'); ?> <span>کیبورد اصلی</span></a>
    <a class="btn btn-outline btn-sm" href="index.php"><?php echo icon('arrow-right','svg-icon svg-sm'); ?> <span>بازگشت</span></a>
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

    function effectiveStyleFor(menu, key) {
        var m = styles[menu];
        if (m && !Array.isArray(m) && Object.prototype.hasOwnProperty.call(m, key)) {
            var v = m[key];
            if (typeof v === 'string' && v !== '') return v;
        }
        var tdf = document.getElementById('sv-toggle-defaults');
        var useDefaults = tdf ? !!tdf.checked : true;
        if (useDefaults) {
            var f = factoryDefaults[menu];
            if (f && !Array.isArray(f) && Object.prototype.hasOwnProperty.call(f, key)) {
                var fv = f[key];
                if (typeof fv === 'string' && fv !== '') return fv;
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
            var prev = card.querySelector('.btn-prev');
            if (prev) prev.dataset.style = effective;
            var ph = document.getElementById('phb-' + menu + '-' + key);
            if (ph) ph.dataset.style = effective;
            document.querySelectorAll('.sw[data-menu="' + menu + '"][data-key="' + key + '"]').forEach(function(s){
                var userPick = (styles[menu] && !Array.isArray(styles[menu])) ? styles[menu][key] : undefined;
                var selStyle = (typeof userPick === 'string' && userPick !== '') ? userPick : 'default';
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
        t.addEventListener('click', function(){
            var g = this.dataset.group, m = this.dataset.menu;
            document.querySelectorAll('.s-tab[data-group="' + g + '"]').forEach(function(x){ x.classList.remove('active'); });
            document.querySelectorAll('.m-panel[data-group="' + g + '"]').forEach(function(x){ x.classList.remove('active'); });
            this.classList.add('active');
            var p = document.getElementById('mp-' + m);
            if (p) p.classList.add('active');
            // Auto-scroll active tab into view
            try {
                this.scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });
            } catch(e) {}
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
        doSave(); // immediate save
    });
    document.addEventListener('keydown', function(ev){
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var sw = ev.target.closest('.sw');
        if (!sw) return;
        ev.preventDefault();
        applyStyle(sw.dataset.menu, sw.dataset.key, sw.dataset.style);
        doSave();
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
        var tdf = document.getElementById('sv-toggle-defaults');
        if (tdf) { out._use_defaults = !!tdf.checked; }
        return out;
    }


    (function bindDefaultsToggle(){
        var t = document.getElementById('sv-toggle-defaults');
        if (!t) return;
        t.addEventListener('change', function(){
            rerenderAllSwatches();
            if (typeof updateDefaultCounter === 'function') { updateDefaultCounter(); }
            if (typeof doSave === 'function') { doSave(); }
            showToast(
                t.checked
                    ? 'پیش‌فرض‌های کارخانه‌ای فعال شدن.'
                    : 'پیش‌فرض‌ها خاموش شدن — فقط رنگ‌های انتخابی خودت روی ربات اعمال می‌شن.',
                'success'
            );
        });
    })();









    var cntDefaultEl = document.getElementById('sv-cnt-default');
    var cntTotalEl   = document.getElementById('sv-cnt-total');
    var cntWrapEl    = document.getElementById('sv-counter');

    function updateDefaultCounter(){
        if (!cntDefaultEl || !cntTotalEl || !cntWrapEl) return;
        var total = 0, defaults = 0;
        document.querySelectorAll('.btn-card[id^="card-"]').forEach(function(card){
            var id = card.id.substring('card-'.length);
            var sepIdx = id.indexOf('-');
            if (sepIdx < 0) return;
            var menu = id.substring(0, sepIdx);
            var key  = id.substring(sepIdx + 1);
            total++;
            if (effectiveStyleFor(menu, key) === 'default') defaults++;
        });
        cntDefaultEl.textContent = defaults;
        cntTotalEl.textContent   = total;
        cntWrapEl.classList.remove('all-default','no-default');
        if (total > 0) {
            if (defaults === total)     cntWrapEl.classList.add('all-default');
            else if (defaults === 0)    cntWrapEl.classList.add('no-default');
        }
    }


    var resetBtn = document.getElementById('sv-reset-all');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(){
            var coloredCount = 0;
            Object.keys(styles).forEach(function(menu){
                var m = styles[menu];
                if (!m || Array.isArray(m)) return;
                Object.keys(m).forEach(function(btn){
                    if (m[btn] && m[btn] !== 'default') coloredCount++;
                });
            });
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

            rerenderAllSwatches();
            updateDefaultCounter();
            showToast('در حال ذخیره ' + coloredCount + ' تغییر...', 'success');
            doSave();
        });
    }

    // ── Pagination ──
    var PAGE_SIZE = 9; // cards per page
    var pageState = {}; // menuKey → currentPage

    function initPagination() {
        document.querySelectorAll('.btn-grid[data-menu]').forEach(function(grid) {
            var mk = grid.dataset.menu;
            var cards = grid.querySelectorAll('.btn-card');
            if (cards.length <= PAGE_SIZE) return; // no pagination needed
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

        document.querySelectorAll('.sw[data-menu="' + menu + '"][data-key="' + key + '"]').forEach(function(s){
            s.classList.toggle('sel', s.dataset.style === style);
        });

        if (typeof updateDefaultCounter === 'function') { updateDefaultCounter(); }
    }

    // ── Immediate save ──
    function doSave(){
        if (isSaving) { pendingSave = true; return; }
        isSaving = true;
        setBadge('saving', 'در حال ذخیره...');
        var payload = sanitiseStyles(styles);
        console.log('[SKB] saving payload:', payload);
        fetch('service_keyboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(function(r){ if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(d){
            isSaving = false;
            console.log('[SKB] save response:', d);
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
})();
</script>
</body>
</html>
