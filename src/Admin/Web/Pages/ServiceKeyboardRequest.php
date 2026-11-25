<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $ALLOWED_STYLES, $ALL_DB_KEYS, $CONFIG_COLUMNS, $CONFIG_ITEM_KEY_RE, $CONFIG_ITEM_SECTION, $EXPLICIT_STYLES, $MAIN_TOKEN_MAP, $MENUS, $STYLE_META, $query, $tonpayFactoryStyles, $userStylesRaw.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceKeyboardRequest
{
    public static function handle(
        &$ALLOWED_STYLES,
        &$ALL_DB_KEYS,
        &$CONFIG_COLUMNS,
        &$CONFIG_ITEM_KEY_RE,
        &$CONFIG_ITEM_SECTION,
        &$EXPLICIT_STYLES,
        &$MAIN_TOKEN_MAP,
        &$MENUS,
        &$STYLE_META,
        $pdo,
        &$query,
        &$tonpayFactoryStyles,
        &$userStylesRaw
    ) {
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
                    'rw_pick_50000' => '💵 مبلغ شارژ کیف پول ۵۰,۰۰۰ تومان',
                    'rw_pick_100000' => '💵 مبلغ شارژ کیف پول ۱۰۰,۰۰۰ تومان',
                    'rw_pick_200000' => '💵 مبلغ شارژ کیف پول ۲۰۰,۰۰۰ تومان',
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
                    'tetraminator'     => '💎 تترامیناتور',
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
                    'ticketnew'       => '➕ تیکت جدید',
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
                    'rcc_resubmit'         => '📨 ارسال مجدد به ادمین',
                    'rcc_view'             => '👁 مشاهده وضعیت',
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
                    'currency_pick_TRX' => '🟥 ترون (TRX)',
                    'currency_pick_TON' => '🟦 تون (TON)',
                    'currency_pick_USDT_TRC20' => '🟢 تتر روی ترون',
                    'currency_pick_USDT_TON' => '🟢 تتر روی تون',
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
                'buttons' => \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayButtonStyleLabels(),
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
        $ALL_DB_KEYS[] = 'button_items';
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

        $tonpayFactoryStyles = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayButtonStyleDefaults();

        $userStylesRaw = [];

        return true;
    }
}
