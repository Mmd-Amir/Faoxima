<?php

namespace Faoxima\Admin\Keyboard;

/**
 * Handler stage. Inherited by value: $_rx_gw_styles, $textbotlang.
 * Inherited or exported by reference: $CartManage, $NowPaymentsManage, $abangateway, $atlaspay, $blupal, $cubepay, $keyboardzarinpal, $rialit, $tonpay, $trnado, $variza.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentGateways
{
    public static function handle(
        &$CartManage,
        &$NowPaymentsManage,
        $_rx_gw_styles,
        &$abangateway,
        &$atlaspay,
        &$blupal,
        &$cubepay,
        &$keyboardzarinpal,
        &$rialit,
        $textbotlang,
        &$tonpay,
        &$trnado,
        &$variza
    ) {
        $CartManage = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کارت به کارت", 'callback_data' => 'cart_title'], 'cart_title', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💳 مدیریت شماره کارت", 'callback_data' => 'cart_manage_inline'], 'cart_manage_inline', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "👤 آیدی پشتیبانی", 'callback_data' => 'cart_support'], 'cart_support', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💳 آفلاین در پیوی", 'callback_data' => 'cart_pvmode'], 'cart_pvmode', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش‌بک کارت", 'callback_data' => 'cart_cashback'], 'cart_cashback', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔒 کارت پس از اولین پرداخت", 'callback_data' => 'cart_firstpay'], 'cart_firstpay', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف کارت به کارت", 'callback_data' => 'cart_min'], 'cart_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف کارت به کارت", 'callback_data' => 'cart_max'], 'cart_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش کارت به کارت", 'callback_data' => 'cart_edu'], 'cart_edu', $_rx_gw_styles)],
                [['text' => "🔑 حداقل مبلغ احراز کارت", 'callback_data' => 'cart_cvmin']],
                [['text' => "🤖 تایید رسید بدون بررسی", 'callback_data' => 'cart_autocheck']],
                [['text' => "🔐 تأیید رسید با بررسی", 'callback_data' => 'cart_smsverify']],
                [['text' => "⚙️ تنظیمات تایید خودکار", 'callback_data' => 'cart_except_user']],
                [['text' => "⏳ زمان تایید خودکار", 'callback_data' => 'cart_autotime']],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'], 'cart_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cart_backmenu']
                ]
            ]);
        $trnado = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ترونادو", 'callback_data' => 'trnado_name'], 'trnado_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت API Key ترونادو", 'callback_data' => 'trnado_apikey'], 'trnado_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔏 ثبت کلید امضای IPN ترونادو", 'callback_data' => 'trnado_signingkey'], 'trnado_signingkey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💼 آدرس کیف پول ترونادو", 'callback_data' => 'trnado_wallet'], 'trnado_wallet', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⚖️ درصد کارمزد کسب‌وکار", 'callback_data' => 'trnado_wage'], 'trnado_wage', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک ترونادو", 'callback_data' => 'trnado_cashback'], 'trnado_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف ترونادو", 'callback_data' => 'trnado_min'], 'trnado_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف ترونادو", 'callback_data' => 'trnado_max'], 'trnado_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش ترونادو", 'callback_data' => 'trnado_edu'], 'trnado_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'], 'trnado_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'trnado_backmenu']
                ]
            ]);
        $tonpay = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه تون‌پی", 'callback_data' => 'tonpay_name'], 'tonpay_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت API Key تون‌پی", 'callback_data' => 'tonpay_apikey'], 'tonpay_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔐 ثبت API Key کاستوم تون‌پی", 'callback_data' => 'tonpay_customkey'], 'tonpay_customkey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔀 مقصد پرداخت تون‌پی", 'callback_data' => 'tonpay_paymode'], 'tonpay_paymode', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک تون‌پی", 'callback_data' => 'tonpay_cashback'], 'tonpay_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف تون‌پی", 'callback_data' => 'tonpay_min'], 'tonpay_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف تون‌پی", 'callback_data' => 'tonpay_max'], 'tonpay_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش تون‌پی", 'callback_data' => 'tonpay_edu'], 'tonpay_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'tonpay_back'], 'tonpay_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'tonpay_backmenu']
                ]
            ]);
        $rialit = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ریالیت", 'callback_data' => 'rialit_name'], 'rialit_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت API Key ریالیت", 'callback_data' => 'rialit_apikey'], 'rialit_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک ریالیت", 'callback_data' => 'rialit_cashback'], 'rialit_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف ریالیت", 'callback_data' => 'rialit_min'], 'rialit_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف ریالیت", 'callback_data' => 'rialit_max'], 'rialit_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🌐 آدرس وب‌هوک ریالیت", 'callback_data' => 'rialit_whurl'], 'rialit_whurl', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "✅ ثبت وب‌هوک ریالیت", 'callback_data' => 'rialit_whregister'], 'rialit_whregister', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔍 خواندن وب‌هوک تنظیم‌شده", 'callback_data' => 'rialit_whread'], 'rialit_whread', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📊 وضعیت وب‌هوک ریالیت", 'callback_data' => 'rialit_whstatus'], 'rialit_whstatus', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🛡 آدرس‌های IP مجاز وب‌هوک", 'callback_data' => 'rialit_trustips'], 'rialit_trustips', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🧩 پراکسی‌های مورد اعتماد", 'callback_data' => 'rialit_trustproxies'], 'rialit_trustproxies', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش ریالیت", 'callback_data' => 'rialit_edu'], 'rialit_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'rialit_back'], 'rialit_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'rialit_backmenu']
                ]
            ]);
        $cubepay = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کیوب‌پی", 'callback_data' => 'cubepay_name'], 'cubepay_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت توکن API کیوب‌پی", 'callback_data' => 'cubepay_apikey'], 'cubepay_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک کیوب‌پی", 'callback_data' => 'cubepay_cashback'], 'cubepay_cashback', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⚖️ کارمزد کیوب‌پی", 'callback_data' => 'cubepay_fee'], 'cubepay_fee', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف کیوب‌پی", 'callback_data' => 'cubepay_min'], 'cubepay_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف کیوب‌پی", 'callback_data' => 'cubepay_max'], 'cubepay_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش کیوب‌پی", 'callback_data' => 'cubepay_edu'], 'cubepay_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cubepay_back'], 'cubepay_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cubepay_backmenu']
                ]
            ]);
        $blupal = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه بلوپال", 'callback_data' => 'blupal_name'], 'blupal_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت API Key بلوپال", 'callback_data' => 'blupal_apikey'], 'blupal_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک بلوپال", 'callback_data' => 'blupal_cashback'], 'blupal_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف بلوپال", 'callback_data' => 'blupal_min'], 'blupal_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف بلوپال", 'callback_data' => 'blupal_max'], 'blupal_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش بلوپال", 'callback_data' => 'blupal_edu'], 'blupal_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'blupal_back'], 'blupal_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'blupal_backmenu']
                ]
            ]);
        $variza = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه واریزا", 'callback_data' => 'variza_name'], 'variza_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت توکن API واریزا", 'callback_data' => 'variza_apikey'], 'variza_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔐 ثبت کلید وب‌هوک واریزا", 'callback_data' => 'variza_webhooksecret'], 'variza_webhooksecret', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک واریزا", 'callback_data' => 'variza_cashback'], 'variza_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف واریزا", 'callback_data' => 'variza_min'], 'variza_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف واریزا", 'callback_data' => 'variza_max'], 'variza_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش واریزا", 'callback_data' => 'variza_edu'], 'variza_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'variza_back'], 'variza_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'variza_backmenu']
                ]
            ]);
        $abangateway = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه آبان گیت وی", 'callback_data' => 'abangateway_name'], 'abangateway_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔗 ثبت آدرس درگاه آبان گیت وی", 'callback_data' => 'abangateway_url'], 'abangateway_url', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت کلید اتصال آبان گیت وی", 'callback_data' => 'abangateway_apikey'], 'abangateway_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک آبان گیت وی", 'callback_data' => 'abangateway_cashback'], 'abangateway_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف آبان گیت وی", 'callback_data' => 'abangateway_min'], 'abangateway_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف آبان گیت وی", 'callback_data' => 'abangateway_max'], 'abangateway_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش آبان گیت وی", 'callback_data' => 'abangateway_edu'], 'abangateway_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'abangateway_back'], 'abangateway_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'abangateway_backmenu']
                ]
            ]);
        $atlaspay = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه اطلس‌پی", 'callback_data' => 'atlaspay_name'], 'atlaspay_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔑 ثبت API Key اطلس‌پی", 'callback_data' => 'atlaspay_apikey'], 'atlaspay_apikey', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔀 مقصد پرداخت اطلس‌پی", 'callback_data' => 'atlaspay_paymode'], 'atlaspay_paymode', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📊 موجودی و اطلاعات حساب", 'callback_data' => 'atlaspay_account'], 'atlaspay_account', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک اطلس‌پی", 'callback_data' => 'atlaspay_cashback'], 'atlaspay_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف اطلس‌پی", 'callback_data' => 'atlaspay_min'], 'atlaspay_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف اطلس‌پی", 'callback_data' => 'atlaspay_max'], 'atlaspay_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش اطلس‌پی", 'callback_data' => 'atlaspay_edu'], 'atlaspay_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'atlaspay_back'], 'atlaspay_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'atlaspay_backmenu']
                ]
            ]);
        $keyboardzarinpal = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه زرین پال", 'callback_data' => 'zpal_name'], 'zpal_name', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "مرچنت زرین پال", 'callback_data' => 'zpal_merchant'], 'zpal_merchant', $_rx_gw_styles)],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک زرین پال", 'callback_data' => 'zpal_cashback'], 'zpal_cashback', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف زرین پال", 'callback_data' => 'zpal_min'], 'zpal_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف زرین پال", 'callback_data' => 'zpal_max'], 'zpal_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش زرین پال", 'callback_data' => 'zpal_edu'], 'zpal_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpal_back'], 'zpal_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'zpal_backmenu']
                ]
            ]);
        $NowPaymentsManage = \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🏷️ نام نمایشی درگاه plisio", 'callback_data' => 'plisio_name'], 'plisio_name', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🧩 api plisio", 'callback_data' => 'plisio_api'], 'plisio_api', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💰 کش بک plisio", 'callback_data' => 'plisio_cashback'], 'plisio_cashback', $_rx_gw_styles)
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬇️ کف plisio", 'callback_data' => 'plisio_min'], 'plisio_min', $_rx_gw_styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⬆️ سقف plisio", 'callback_data' => 'plisio_max'], 'plisio_max', $_rx_gw_styles)
                ],
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 آموزش plisio", 'callback_data' => 'plisio_edu'], 'plisio_edu', $_rx_gw_styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'plisio_back'], 'plisio_back', $_rx_gw_styles),
                    ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'plisio_backmenu']
                ]
            ]);

        return 1;
    }
}
