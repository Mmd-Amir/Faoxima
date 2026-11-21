<?php

namespace Faoxima\Admin\Telegram\Conversation;

/**
 * Handler stage. Inherited by value: $adminExecution, $admin_ids, $datain, $from_id, $textbotlang.
 * Inherited or exported by reference: $rx_back_origin, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CallbackNormalization
{
    public static function handle(
        $adminExecution,
        $admin_ids,
        $datain,
        $from_id,
        &$rx_back_origin,
        &$text,
        $textbotlang
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if (!empty($datain) && in_array($from_id, $admin_ids ?? [])) {
            $_rx_adm_cb_map = [

                'admin_status'      => $textbotlang['Admin']['Status']['btn'],
                'admin_readiness'   => "🚦 آمادگی فروش",
                'admin_managepanel' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'],
                'admin_addpanel'    => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'],
                'admin_timeprice'   => "⏳ قیمت سریع زمان",
                'admin_volprice'    => "🔋 قیمت سریع حجم",
                'admin_users'       => $textbotlang['Admin']['btnkeyboardadmin']['managruser'],
                'admin_shop'        => "🏬 تنظیمات فروشگاه",
                'admin_finance'     => "💎 مالی و گزارشات",
                'admin_support'     => "🤙 بخش پشتیبانی",
                'admin_help'        => "📚 بخش آموزش",
                'admin_features'    => "🛠 قابلیت های پنل",
                'admin_settings'    => "⚙️ تنظیمات فنی و ربات",
                'admin_invoices'    => "💵 رسید های تایید نشده",
                'admin_back'        => $textbotlang['Admin']['backadmin'],

                'admin_panels'       => "📁 مدیریت پنل‌ها و سرورها",
                'admin_channelhub'   => "📢 کانال و اطلاع‌رسانی",
                'admin_usershub'     => "👥 مدیریت کاربران",
                'adm_hub_main'       => $textbotlang['Admin']['backadmin'],
                'panelshub_backmenu'  => $textbotlang['Admin']['backmenu'],
                'channelhub_backmenu' => $textbotlang['Admin']['backmenu'],
                'usershub_backmenu'   => $textbotlang['Admin']['backmenu'],

                'seller_status'     => $textbotlang['Admin']['Status']['btn'],
                'seller_users'      => "👤 مدیریت کاربر",
                'seller_back'       => $textbotlang['users']['backbtn'],
                'support_users'     => "👤 مدیریت کاربر",
                'support_search'    => "👁‍🗨 جستجو کاربر",
                'support_back'      => $textbotlang['users']['backbtn'],

                'set_features'   => "⚙️ وضعیت قابلیت ها",
                'set_reports'    => "📣 گزارشات ربات",
                'set_channel'    => "📯 تنظیمات کانال",
                'set_webpanel'   => "✅ پنل تحت وب",
                'set_optimize'   => "🗑 بهینه سازی ربات",
                'set_text'       => "📝 تنظیم متن ربات",
                'set_adminmgr'   => "👨‍🔧 بخش ادمین",
                'set_testlimit'  => "➕ محدودیت تست برای همه",
                'set_agentprice' => "💰 عضویت نمایندگی",
                'set_qrsettings' => "📷 تنظیمات کیو آر کد",
                'set_qrbg'       => "🖼 پس‌زمینه کیوآرکد",
                'set_qr_toggle'  => "🔄 وضعیت کیوآرکد",
                'set_webhook'    => "🔗 وبهوک ربات‌های نماینده",
                'set_backadmin'  => $textbotlang['Admin']['backadmin'],
                'set_backmenu'   => $textbotlang['Admin']['backmenu'],

                'shop_status'      => "🛒 وضعیت قابلیت های فروشگاه",
                'shop_category'    => "🗂 مدیریت دسته‌بندی",
                'shop_products'    => "🛍 مدیریت محصولات",
                'shop_giftadd'     => "🎁 ساخت کد هدیه",
                'shop_giftdel'     => "❌ حذف کد هدیه",
                'shop_discountadd' => "🎁 ساخت کد تخفیف",
                'shop_discountdel' => "❌ حذف کد تخفیف",
                'shop_minbulk'     => "⬇️ کف خرید عمده",
                'shop_renewcb'     => "🎁 کش بک تمدید",
                'shop_backadmin'   => $textbotlang['Admin']['backadmin'],
                'shop_backmenu'    => $textbotlang['Admin']['backmenu'],

                'cart_title'       => "🏷️ نام نمایشی درگاه کارت به کارت",
                'cart_setnum'      => "💳 شماره کارت",
                'cart_delnum'      => "❌ حذف شماره کارت",
                'cart_support'     => "👤 آیدی پشتیبانی",
                'cart_pvmode'      => "💳 آفلاین در پیوی",
                'cart_cashback'    => "💰 کش‌بک کارت",
                'cart_firstpay'    => "🔒 کارت پس از اولین پرداخت",
                'cart_min'         => "⬇️ کف کارت به کارت",
                'cart_max'         => "⬆️ سقف کارت به کارت",
                'cart_edu'         => "📚 آموزش کارت به کارت",
                'cart_cvmin'       => "🔑 حداقل مبلغ احراز کارت",
                'cart_hide_num'    => "💰  غیرفعالسازی  نمایش شماره کارت",
                'cart_show_num'    => "💰 فعالسازی نمایش شماره کارت",
                'cart_group_num'   => "♻️ نمایش گروهی شماره کارت",
                'cart_export_num'  => "📄 خروجی شماره کارت فعال",
                'cart_autocheck'   => "🤖 تایید رسید بدون بررسی",
                'cart_smsverify'   => "🔐 تأیید رسید با بررسی",
                'cart_except_user' => "⚙️ تنظیمات تایید خودکار",
                'cart_autotime'    => "⏳ زمان تایید خودکار",
                'cart_back'        => $textbotlang['Admin']['backadmin'],
                'cart_backmenu'    => $textbotlang['Admin']['backmenu'],
                'adm_backmenu'     => $textbotlang['Admin']['backmenu'],
                'panelmenu_back'   => "🔙 بازگشت به منوی پنل",

                'trnado_name'     => "🏷️ نام نمایشی درگاه ترونادو",
                'trnado_apikey'      => "🔑 ثبت API Key ترونادو",
                'trnado_signingkey'  => "🔏 ثبت کلید امضای IPN ترونادو",
                'trnado_wallet'      => "💼 آدرس کیف پول ترونادو",
                'trnado_wage'        => "⚖️ درصد کارمزد کسب‌وکار",
                'trnado_cashback' => "💰 کش بک ترونادو",
                'trnado_min'      => "⬇️ کف ترونادو",
                'trnado_max'      => "⬆️ سقف ترونادو",
                'trnado_edu'      => "📚 آموزش ترونادو",
                'trnado_back'     => $textbotlang['Admin']['backadmin'],
                'trnado_backmenu' => $textbotlang['Admin']['backmenu'],

                'tonpay_name'     => "🏷️ نام نمایشی درگاه تون‌پی",
                'tonpay_apikey'   => "🔑 ثبت API Key تون‌پی",
                'tonpay_customkey' => "🔐 ثبت API Key کاستوم تون‌پی",
                'tonpay_paymode'  => "🔀 مقصد پرداخت تون‌پی",
                'tonpay_cashback' => "💰 کش بک تون‌پی",
                'tonpay_min'      => "⬇️ کف تون‌پی",
                'tonpay_max'      => "⬆️ سقف تون‌پی",
                'tonpay_edu'      => "📚 آموزش تون‌پی",
                'tonpay_back'     => $textbotlang['Admin']['backadmin'],
                'tonpay_backmenu' => $textbotlang['Admin']['backmenu'],

                'rialit_name'        => "🏷️ نام نمایشی درگاه ریالیت",
                'rialit_apikey'      => "🔑 ثبت API Key ریالیت",
                'rialit_cashback'    => "💰 کش بک ریالیت",
                'rialit_min'         => "⬇️ کف ریالیت",
                'rialit_max'         => "⬆️ سقف ریالیت",
                'rialit_whurl'       => "🌐 آدرس وب‌هوک ریالیت",
                'rialit_whregister'  => "✅ ثبت وب‌هوک ریالیت",
                'rialit_whread'      => "🔍 خواندن وب‌هوک تنظیم‌شده",
                'rialit_whstatus'    => "📊 وضعیت وب‌هوک ریالیت",
                'rialit_trustips'    => "🛡 آدرس‌های IP مجاز وب‌هوک",
                'rialit_trustproxies'=> "🧩 پراکسی‌های مورد اعتماد",
                'rialit_edu'         => "📚 آموزش ریالیت",
                'rialit_back'        => $textbotlang['Admin']['backadmin'],
                'rialit_backmenu'    => $textbotlang['Admin']['backmenu'],

                'cubepay_name'     => "🏷️ نام نمایشی درگاه کیوب‌پی",
                'cubepay_apikey'   => "🔑 ثبت توکن API کیوب‌پی",
                'cubepay_cashback' => "💰 کش بک کیوب‌پی",
                'cubepay_fee'      => "⚖️ کارمزد کیوب‌پی",
                'cubepay_min'      => "⬇️ کف کیوب‌پی",
                'cubepay_max'      => "⬆️ سقف کیوب‌پی",
                'cubepay_edu'      => "📚 آموزش کیوب‌پی",
                'cubepay_back'     => $textbotlang['Admin']['backadmin'],
                'cubepay_backmenu' => $textbotlang['Admin']['backmenu'],

                'blupal_name'     => "🏷️ نام نمایشی درگاه بلوپال",
                'blupal_apikey'   => "🔑 ثبت API Key بلوپال",
                'blupal_cashback' => "💰 کش بک بلوپال",
                'blupal_min'      => "⬇️ کف بلوپال",
                'blupal_max'      => "⬆️ سقف بلوپال",
                'blupal_edu'      => "📚 آموزش بلوپال",
                'blupal_back'     => $textbotlang['Admin']['backadmin'],
                'blupal_backmenu' => $textbotlang['Admin']['backmenu'],

                'variza_name'         => "🏷️ نام نمایشی درگاه واریزا",
                'variza_apikey'       => "🔑 ثبت توکن API واریزا",
                'variza_webhooksecret' => "🔐 ثبت کلید وب‌هوک واریزا",
                'variza_cashback'     => "💰 کش بک واریزا",
                'variza_min'          => "⬇️ کف واریزا",
                'variza_max'          => "⬆️ سقف واریزا",
                'variza_edu'          => "📚 آموزش واریزا",
                'variza_back'         => $textbotlang['Admin']['backadmin'],
                'variza_backmenu'     => $textbotlang['Admin']['backmenu'],

                'abangateway_name'     => "🏷️ نام نمایشی درگاه آبان گیت وی",
                'abangateway_url'      => "🔗 ثبت آدرس درگاه آبان گیت وی",
                'abangateway_apikey'   => "🔑 ثبت کلید اتصال آبان گیت وی",
                'abangateway_cashback' => "💰 کش بک آبان گیت وی",
                'abangateway_min'      => "⬇️ کف آبان گیت وی",
                'abangateway_max'      => "⬆️ سقف آبان گیت وی",
                'abangateway_edu'      => "📚 آموزش آبان گیت وی",
                'abangateway_back'     => $textbotlang['Admin']['backadmin'],
                'abangateway_backmenu' => $textbotlang['Admin']['backmenu'],

                'atlaspay_name'     => "🏷️ نام نمایشی درگاه اطلس‌پی",
                'atlaspay_apikey'   => "🔑 ثبت API Key اطلس‌پی",
                'atlaspay_paymode'  => "🔀 مقصد پرداخت اطلس‌پی",
                'atlaspay_account'  => "📊 موجودی و اطلاعات حساب",
                'atlaspay_cashback' => "💰 کش بک اطلس‌پی",
                'atlaspay_min'      => "⬇️ کف اطلس‌پی",
                'atlaspay_max'      => "⬆️ سقف اطلس‌پی",
                'atlaspay_edu'      => "📚 آموزش اطلس‌پی",
                'atlaspay_back'     => $textbotlang['Admin']['backadmin'],
                'atlaspay_backmenu' => $textbotlang['Admin']['backmenu'],

                'zpal_name'     => "🏷️ نام نمایشی درگاه زرین پال",
                'zpal_merchant' => "مرچنت زرین پال",
                'zpal_cashback' => "💰 کش بک زرین پال",
                'zpal_min'      => "⬇️ کف زرین پال",
                'zpal_max'      => "⬆️ سقف زرین پال",
                'zpal_edu'      => "📚 آموزش زرین پال",
                'zpal_back'     => $textbotlang['Admin']['backadmin'],
                'zpal_backmenu' => $textbotlang['Admin']['backmenu'],

                'zpey_name'     => "🗂 درگاه زرین پی",
                'zpey_token'    => "🔑 توکن زرین پی",
                'zpey_cashback' => "💰 کش بک زرین پی",
                'zpey_tutorial' => "🧑🏼‍💻 اموزش اتصال",
                'zpey_min'      => "⬇️ کف زرین پی",
                'zpey_max'      => "⬆️ سقف زرین پی",
                'zpey_edu'      => "📚 آموزش زرین پی",
                'zpey_back'     => $textbotlang['Admin']['backadmin'],
                'zpey_backmenu' => $textbotlang['Admin']['backmenu'],

                'aqaye_name'     => "🗂 نام درگاه آقای پرداخت",
                'aqaye_merchant' => "مرچنت آقای پرداخت",
                'aqaye_cashback' => "💰 کش‌بک آقای‌پرداخت",
                'aqaye_min'      => "⬇️ کف آقای پرداخت",
                'aqaye_max'      => "⬆️ سقف آقای پرداخت",
                'aqaye_edu'      => "📚 آموزش درگاه اقای پرداخت",
                'aqaye_back'     => $textbotlang['Admin']['backadmin'],
                'aqaye_backmenu' => $textbotlang['Admin']['backmenu'],

                'plisio_name'     => "🏷️ نام نمایشی درگاه plisio",
                'plisio_api'      => "🧩 api plisio",
                'plisio_cashback' => "💰 کش بک plisio",
                'plisio_min'      => "⬇️ کف plisio",
                'plisio_max'      => "⬆️ سقف plisio",
                'plisio_edu'      => "📚 آموزش plisio",
                'plisio_back'     => $textbotlang['Admin']['backadmin'],
                'plisio_backmenu' => $textbotlang['Admin']['backmenu'],

                'help_add'      => "📚 افزودن آموزش",
                'help_del'      => "❌ حذف آموزش",
                'help_edit'     => "✏️ ویرایش آموزش",
                'help_load_default' => "📥 دریافت پیش‌فرض‌ها",
                'help_back'     => $textbotlang['Admin']['backadmin'],
                'help_backmenu' => $textbotlang['Admin']['backmenu'],

                'cat_add'  => "🛒 افزودن دسته‌بندی",
                'cat_del'  => "❌ حذف دسته بندی",
                'cat_edit' => "✏️ ویرایش دسته بندی",
                'cat_back' => "⬅️ بازگشت به منوی فروشگاه",

                'shopitem_add'      => "🛍 افزودن محصول",
                'shopitem_del'      => "❌ حذف محصول",
                'shopitem_edit'     => "✏️ ویرایش محصول",
                'shopitem_priceinc' => "⬆️ افزایش قیمت",
                'shopitem_pricedec' => "⬇️ کاهش گروهی قیمت",
                'shopitem_back'     => "⬅️ بازگشت به منوی فروشگاه",

                'feat_info'     => "⚙️ مشاهده اطلاعات اکانت",
                'feat_test'     => "🧪 اکانت تست",
                'feat_help'     => "📚 قابلیت آموزش",
                'feat_back'     => $textbotlang['Admin']['backadmin'],
                'feat_backmenu' => $textbotlang['Admin']['backmenu'],

                'ch_add'      => "اضافه کردن کانال",
                'ch_del'      => "حذف کانال",
                'ch_list'     => "📯 تنظیمات کانال",
                'ch_back'     => $textbotlang['Admin']['backadmin'],
                'ch_backmenu' => $textbotlang['Admin']['backmenu'],
                'wallet_backmenu' => $textbotlang['Admin']['backmenu'],
            ];
            if (isset($_rx_adm_cb_map[$datain])) {
                $text = $_rx_adm_cb_map[$datain];
            }

            $_rx_back_origin_map = [
                'cart_backmenu'   => 'finance',
                'trnado_backmenu' => 'finance',
                'tonpay_backmenu' => 'finance',
                'rialit_backmenu' => 'finance',
                'cubepay_backmenu' => 'finance',
                'blupal_backmenu' => 'finance',
                'variza_backmenu' => 'finance',
                'abangateway_backmenu' => 'finance',
                'atlaspay_backmenu' => 'finance',
                'zpal_backmenu'   => 'finance',
                'zpey_backmenu'   => 'finance',
                'aqaye_backmenu'  => 'finance',
                'plisio_backmenu' => 'finance',
                'wallet_backmenu' => 'finance',
                'shop_backmenu'   => 'home',
                'help_backmenu'   => 'home',
                'set_backmenu'    => 'home',
                'ch_backmenu'     => 'channelhub',
                'ch_back'         => 'home',
                'feat_backmenu'   => 'settings',
                'panelshub_backmenu'  => 'home',
                'channelhub_backmenu' => 'home',
                'usershub_backmenu'   => 'home',
                'adm_backmenu'    => null,
            ];
            if (array_key_exists((string) $datain, $_rx_back_origin_map)) {
                $rx_back_origin = $_rx_back_origin_map[$datain];
            }
            unset($_rx_back_origin_map);

            unset($_rx_adm_cb_map);
        }

        if (empty($datain) && in_array($from_id, $admin_ids ?? []) && isset($text) && is_string($text) && method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_normalizeAdminButtonText')) {
            $text = \Faoxima\Telegram\Support\AdminInput::rx_normalizeAdminButtonText($text);
        }

        return true;
    }
}
