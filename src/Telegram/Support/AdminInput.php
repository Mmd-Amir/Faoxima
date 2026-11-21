<?php

namespace Faoxima\Telegram\Support;

use PDO;
use Throwable;

final class AdminInput
{
    public static function nmNormalizeText($value)
    {
        $value = trim((string)$value);
        $value = preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\x{202a}-\x{202e}]/u', '', $value);
        $value = str_replace(["ي", "ك", "ة", "ۀ"], ["ی", "ک", "ه", "ه"], $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    public static function rx_cleanEmojiAndSymbols($str): string {
        $str = (string)$str;
        $str = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2000}-\x{32FF}\x{FE00}-\x{FE0F}\x{200D}\x{200C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $str);
        $str = str_replace(["ي", "ك", "ة", "ۀ"], ["ی", "ک", "ه", "ه"], $str);
        $str = preg_replace('/\s+/u', ' ', $str);
        $str = trim($str);
        return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
    }

    public static function rx_resolvePanelFromInput($input, $pdo = null) {
        if (!is_string($input) || trim($input) === '') {
            return null;
        }
        $rawInput = trim($input);
        if ($rawInput === '/all') {
            return ['code_panel' => '/all', 'name_panel' => '/all', 'type' => 'all'];
        }
        if (function_exists('rx_restorePremiumReplyText')) {
            $restored = rx_restorePremiumReplyText($rawInput);
            if ($restored !== '' && $restored !== $rawInput) {
                if (\Faoxima\Core\Database\Db::runtimeReady()) {
                    $direct = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $restored, "select");
                    if (is_array($direct) && !empty($direct)) {
                        return $direct;
                    }
                }
            }
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $direct = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rawInput, "select");
            if (is_array($direct) && !empty($direct)) {
                return $direct;
            }
        }
        if ($pdo === null) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (!($pdo instanceof PDO)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1");
            $stmt->execute([$rawInput]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row)) {
                return $row;
            }
            $stmt = $pdo->query("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> ''");
            $allPanels = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!is_array($allPanels) || empty($allPanels)) {
                return null;
            }
            return \Faoxima\Orders\Catalog\CatalogLookups::nmMatchingPanelRow($allPanels, self::rx_cleanEmojiAndSymbols($rawInput));
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function rx_normalizeAdminButtonText($text) {
        if (!is_string($text) || trim($text) === '') {
            return $text;
        }
        $raw = trim($text);
        if (function_exists('rx_restorePremiumReplyText')) {
            $restored = rx_restorePremiumReplyText($raw);
            if ($restored !== '' && $restored !== $raw) {
                $raw = trim($restored);
            }
        }
        global $textbotlang;
        $canonicalBackAdmin = $textbotlang['Admin']['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
        $canonicalBackMenu  = $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل';

        $clean = self::rx_cleanEmojiAndSymbols($raw);
        if ($clean === '') {
            return $raw;
        }

        if (in_array($clean, [
            'بازگشت به منوی قبل', 'بازگشت به منو قبل', 'بازگشت به قبل', 'منوی قبل', 'بازگشت', 'منو قبل'
        ], true)) {
            return $canonicalBackMenu;
        }
        if (in_array($clean, [
            'بازگشت به منوی مدیریت', 'بازگشت به منوی اصلی', 'بازگشت به منو اصلی', 'بازگشت به خانه',
            'بازگشت به ادمین', 'بازگشت به پنل مدیریت', 'بازگشت به پنل ادمین', 'منوی مدیریت', 'منوی اصلی', 'خانه'
        ], true)) {
            return $canonicalBackAdmin;
        }

        static $adminMap = null;
        if ($adminMap === null) {
            $targets = [
                'admin_status'          => $textbotlang['Admin']['Status']['btn'] ?? "📊 وضعیت سرور",
                'admin_readiness'       => "🚦 آمادگی فروش",
                'admin_managepanel'     => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] ?? "مدیریت پنل",
                'admin_addpanel'        => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] ?? "افزودن پنل",
                'admin_timeprice'       => "⏳ قیمت سریع زمان",
                'admin_volprice'        => "🔋 قیمت سریع حجم",
                'admin_users'           => $textbotlang['Admin']['btnkeyboardadmin']['managruser'] ?? "مدیریت کاربر",
                'admin_shop'            => "🏬 تنظیمات فروشگاه",
                'admin_finance'         => "💎 مالی و گزارشات",
                'admin_support'         => "🤙 بخش پشتیبانی",
                'admin_help'            => "📚 بخش آموزش",
                'admin_features'        => "🛠 قابلیت های پنل",
                'admin_settings'        => "⚙️ تنظیمات فنی و ربات",
                'admin_invoices'        => "💵 رسید های تایید نشده",
                'admin_panels'          => "📁 مدیریت پنل‌ها و سرورها",
                'admin_channelhub'      => "📢 کانال و اطلاع‌رسانی",
                'admin_usershub'        => "👥 مدیریت کاربران",
                'set_features'          => "⚙️ وضعیت قابلیت ها",
                'set_reports'           => "📣 گزارشات ربات",
                'set_channel'           => "📯 تنظیمات کانال",
                'set_webpanel'          => "✅ پنل تحت وب",
                'set_optimize'          => "🗑 بهینه سازی ربات",
                'set_text'              => "📝 تنظیم متن ربات",
                'set_adminmgr'          => "👨‍🔧 بخش ادمین",
                'set_testlimit'         => "➕ محدودیت تست برای همه",
                'set_agentprice'        => "💰 عضویت نمایندگی",
                'set_qrsettings'        => "📷 تنظیمات کیو آر کد",
                'set_qrbg'              => "🖼 پس‌زمینه کیوآرکد",
                'set_qr_toggle'         => "🔄 وضعیت کیوآرکد",
                'set_webhook'           => "🔗 وبهوک ربات‌های نماینده",
                'shop_status'           => "🛒 وضعیت قابلیت های فروشگاه",
                'shop_category'         => "🗂 مدیریت دسته‌بندی",
                'shop_products'         => "🛍 مدیریت محصولات",
                'shop_giftadd'          => "🎁 ساخت کد هدیه",
                'shop_giftdel'          => "❌ حذف کد هدیه",
                'shop_discountadd'      => "🎁 ساخت کد تخفیف",
                'shop_discountdel'      => "❌ حذف کد تخفیف",
                'shop_minbulk'          => "⬇️ کف خرید عمده",
                'shop_renewcb'          => "🎁 کش بک تمدید",
                'cat_add'               => "🛒 افزودن دسته‌بندی",
                'cat_del'               => "❌ حذف دسته بندی",
                'cat_edit'              => "✏️ ویرایش دسته بندی",
                'cat_back'              => "⬅️ بازگشت به منوی فروشگاه",
                'shopitem_add'          => "🛍 افزودن محصول",
                'shopitem_del'          => "❌ حذف محصول",
                'shopitem_edit'         => "✏️ ویرایش محصول",
                'shopitem_priceinc'     => "⬆️ افزایش قیمت",
                'shopitem_pricedec'     => "⬇️ کاهش گروهی قیمت",
                'shopitem_back'         => "⬅️ بازگشت به منوی فروشگاه",
                'cart_title'            => "🏷️ نام نمایشی درگاه کارت به کارت",
                'cart_setnum'           => "💳 شماره کارت",
                'cart_delnum'           => "❌ حذف شماره کارت",
                'cart_support'          => "👤 آیدی پشتیبانی",
                'cart_pvmode'           => "💳 آفلاین در پیوی",
                'cart_cashback'         => "💰 کش‌بک کارت",
                'cart_firstpay'         => "🔒 کارت پس از اولین پرداخت",
                'cart_min'              => "⬇️ کف کارت به کارت",
                'cart_max'              => "⬆️ سقف کارت به کارت",
                'cart_edu'              => "📚 آموزش کارت به کارت",
                'cart_cvmin'            => "🔑 حداقل مبلغ احراز کارت",
                'cart_hide_num'         => "💰  غیرفعالسازی  نمایش شماره کارت",
                'cart_show_num'         => "💰 فعالسازی نمایش شماره کارت",
                'cart_manage'           => "💳 مدیریت شماره کارت",
                'atlaspay_name'         => "🏷️ نام نمایشی درگاه اطلس‌پی",
                'atlaspay_apikey'       => "🔑 ثبت API Key اطلس‌پی",
                'atlaspay_account'      => "📊 موجودی و اطلاعات حساب",
                'atlaspay_cashback'     => "💰 کش بک اطلس‌پی",
                'atlaspay_min'          => "⬇️ کف اطلس‌پی",
                'atlaspay_max'          => "⬆️ سقف اطلس‌پی",
                'atlaspay_edu'          => "📚 آموزش اطلس‌پی",
                'zpal_name'             => "🏷️ نام نمایشی درگاه زرین پال",
                'zpal_merchant'         => "مرچنت زرین پال",
                'zpal_cashback'         => "💰 کش بک زرین پال",
                'zpal_min'              => "⬇️ کف زرین پال",
                'zpal_max'              => "⬆️ سقف زرین پال",
                'zpal_edu'              => "📚 آموزش زرین پال",
                'zpey_name'             => "🗂 درگاه زرین پی",
                'zpey_token'            => "🔑 توکن زرین پی",
                'zpey_cashback'         => "💰 کش بک زرین پی",
                'zpey_tutorial'         => "🧑🏼‍💻 اموزش اتصال",
                'zpey_min'              => "⬇️ کف زرین پی",
                'zpey_max'              => "⬆️ سقف زرین پی",
                'zpey_edu'              => "📚 آموزش زرین پی",
                'aqaye_name'            => "🗂 نام درگاه آقای پرداخت",
                'aqaye_merchant'        => "مرچنت آقای پرداخت",
                'aqaye_cashback'        => "💰 کش‌بک آقای‌پرداخت",
                'aqaye_min'             => "⬇️ کف آقای پرداخت",
                'aqaye_max'             => "⬆️ سقف آقای پرداخت",
                'aqaye_edu'             => "📚 آموزش درگاه اقای پرداخت",
                'plisio_name'           => "🏷️ نام نمایشی درگاه plisio",
                'plisio_api'            => "🧩 api plisio",
                'plisio_cashback'       => "💰 کش بک plisio",
                'plisio_min'            => "⬇️ کف plisio",
                'plisio_max'            => "⬆️ سقف plisio",
                'plisio_edu'            => "📚 آموزش plisio",
                'help_add'              => "📚 افزودن آموزش",
                'help_del'              => "❌ حذف آموزش",
                'help_edit'             => "✏️ ویرایش آموزش",
                'ch_add'                => "اضافه کردن کانال",
                'ch_del'                => "حذف کانال",
                'ch_list'               => "📯 تنظیمات کانال",
                'p_status'              => "⚙️ وضعیت قابلیت ها پنل",
                'p_type'                => "🔄 تغییر نوع پنل",
                'p_api_mode'            => "🔄 تغییر حالت API",
                'p_name'                => "✍️ نام پنل",
                'p_del'                 => "❌ حذف پنل",
                'p_key'                 => "🔐 ویرایش کلید",
                'p_pass'                => "🔐 ویرایش رمز عبور",
                'p_uname'               => "👤 ویرایش نام",
                'p_conn'                => "⁉️ اتصال به پنل",
                'p_url'                 => "🔗 ویرایش آدرس پنل",
                'p_inbound_proto'       => "⚙️ پروتکل اینباند",
                'p_inbound_id'          => "💎 شناسه اینباند",
                'p_services'            => "⚙️ تنظیم سرویس ها",
                'p_def_svc'             => "⚙️ سرویس پیش‌فرض",
                'p_renew_method'        => "🔋 روش تمدید سرویس",
                'p_gen_uname'           => "💡 ساخت نام کاربری",
                'p_subdomain'           => "🔗 دامنه لینک ساب",
                'p_limit'               => "🚨 محدودیت اکانت",
                'p_group'               => "📍 تغییر گروه",
                'p_test_time'           => "⏳ زمان سرویس تست",
                'p_test_vol'            => "💾 حجم اکانت تست",
                'p_test_settings'       => "🧪 تنظیمات تست",
                'p_cvol_price'          => "⚙️ قیمت حجم دلخواه",
                'p_xvol_price'          => "➕ قیمت حجم اضافه",
                'p_xtime_price'         => "⏳ قیمت زمان اضافه",
                'p_ctime_price'         => "⏳ قیمت زمان دلخواه",
                'p_chloc_price'         => "🌍 قیمت تغییر مکان",
                'p_min_cvol'            => "📍 کف حجم دلخواه",
                'p_max_cvol'            => "📍 سقف حجم دلخواه",
                'p_min_ctime'           => "📍 کف زمان دلخواه",
                'p_max_ctime'           => "📍 سقف زمان دلخواه",
                'p_inbound_deact'       => "⚙️  اینباند اکانت غیرفعال",
                'p_netmelli'            => "🌐 وضعیت نت ملی",
                'p_hide_panel'          => "🫣 مخفی پنل برای کاربر",
                'p_unhide_panel'        => "❌ حذف از لیست مخفی",
                'p_channel_back'        => "🔙 بازگشت به لیست کانال‌ها",
                'star_title'            => "🏷️ نام نمایشی درگاه استار",
                'star_cb'               => "💰 کش بک استار",
                'star_edu'              => "📚 آموزش استار",
                'star_min'              => "⬇️ کف استار",
                'star_max'              => "⬆️ سقف استار",
                'nowp_title'            => "🏷️ نام نمایشی درگاه nowpayment",
                'nowp_cb'               => "💰 کش بک nowpayment",
                'nowp_edu'              => "📚 آموزش nowpayment",
                'nowp_min'              => "⬇️ کف nowpayment",
                'nowp_max'              => "⬆️ سقف nowpayment",
                'nowp_api'              => "API NOWPAYMENT",
                'nowp_ipn'              => "🔐 IPN Secret nowpayment",
                'limit_free'            => "🆓 محدودیت رایگان",
                'limit_all'             => "↙️ محدودیت کلی",
                'limit_reset'           => "🔄 ریست محدودیت کل کاربران",
                'auto_cart_back'        => "◀️ بازگشت به تنظیمات پیشرفته",
                'seller_users'          => "👤 مدیریت کاربر",
                'support_search'        => "👁‍🗨 جستجو کاربر",
                'feat_info'             => "قابلیت مشاهده اطلاعات اکانت",
                'feat_test'             => "قابلیت اکانت تست",
                'feat_help'             => "قابلیت آموزش",
            ];
            $adminMap = [];
            foreach ($targets as $k => $canonicalStr) {
                $cleaned = self::rx_cleanEmojiAndSymbols($canonicalStr);
                if ($cleaned !== '') {
                    $adminMap[$cleaned] = $canonicalStr;
                }
            }
        }

        if (isset($adminMap[$clean])) {
            return $adminMap[$clean];
        }
        return $raw;
    }
}
