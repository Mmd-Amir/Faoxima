<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $MENUS, $adminWebPage, $catalogSamples, $mkBtn, $mkCfgHead, $mkIndividual, $payValue, $serviceSampleActions, $textbotMap.
 * Inherited or exported by reference: $SCREENS, $addScreen, $label, $note, $role, $subRows.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceKeyboardScreenRegistry
{
    public static function handle(
        $MENUS,
        &$SCREENS,
        &$addScreen,
        $adminWebPage,
        $catalogSamples,
        &$label,
        $mkBtn,
        $mkCfgHead,
        $mkIndividual,
        &$note,
        $payValue,
        &$role,
        $serviceSampleActions,
        &$subRows,
        $textbotMap
    ) {
        if (($adminWebPage ?? null) !== 'service_keyboard.php') {
            return;
        }

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

        $svc = function (string $key, array $opt = []) use ($mkIndividual, $MENUS): array {
            return array_merge($mkIndividual(['_rxcolor' => 'service_template:' . $key], $opt['label'] ?? $MENUS['service']['buttons'][$key], 'service', $key), $opt);
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
        foreach ($serviceSampleActions as $serviceKey => [$serviceLabel, $fallbackMenu, $fallbackKey]) {
            $serviceRows[] = [$mkIndividual(['_rxcolor' => 'service_template:' . $serviceKey], $serviceLabel, $fallbackMenu, $fallbackKey)];
        }
        $addScreen('service', 'مدیریت سرویس', 'gear', 'یک نمونه از دکمه‌های مدیریت سرویس برای همه کاربران.', $serviceRows, [
            'note' => 'رنگ هر دکمه مستقل است و برای همان دکمه در سرویس‌های همه کاربران اعمال می‌شود. این نمونه به آیدی ادمین یا سرویس واقعی وابسته نیست.',
        ]);
        $serviceOperationLabels = [
            'confirm_transfer' => '✅ تایید انتقال سرویس', 'confirm_remove' => '✅ تایید حذف سرویس',
            'confirm_report' => '✅ تایید ارسال گزارش اختلال', 'confirm_location' => '✅ تایید تغییر لوکیشن',
            'confirm_volume' => '✅ تایید خرید حجم', 'confirm_volume_discount' => '✅ تایید حجم با تخفیف',
            'confirm_external_volume' => '✅ تایید خرید حجم برای سرویس دیگران',
            'confirm_time' => '✅ تایید خرید زمان', 'confirm_time_discount' => '✅ تایید زمان با تخفیف',
            'discount_volume' => '🎁 تخفیف خرید حجم', 'discount_time' => '🎁 تخفیف خرید زمان',
            'renewal_product' => '📦 نمونه محصول تمدید', 'extend_current' => '♻️ تمدید پلن فعلی',
            'stock_confirm' => '✅ تایید تمدید کالای دستی', 'squad_select' => '🌍 نمونه سرور رمنـاوِیو',
        ];
        $serviceOperationRows = [];
        foreach (array_chunk($serviceOperationLabels, 2, true) as $operationPair) {
            $operationRow = [];
            foreach ($operationPair as $role => $label) {
                $fallback = in_array($role, ['discount_volume', 'discount_time'], true) ? 'extra_purchase' : 'user_confirms';
                if ($role === 'renewal_product') $fallback = 'product_buy';
                if ($role === 'squad_select') $fallback = 'service_actions';
                if ($role === 'stock_confirm') $fallback = 'nmstock_actions';
                $operationRow[] = $mkIndividual(['_rxcolor' => 'service_template:' . $role], $label, 'user_dynamic_lists', $fallback) + ['sample' => true];
            }
            $serviceOperationRows[] = $operationRow;
        }
        $addScreen('service_operations', 'تأیید عملیات سرویس', 'circle-check', 'نمونه دکمه‌های تایید، تمدید و انتخاب سرور؛ رنگ هر نمونه برای همان عملیات اعمال می‌شود.', $serviceOperationRows);

        $cfgRows = [];
        $cfgRows[] = [
            $mkCfgHead('cfg_header_action', '⚙️ کانفیگ'),
            $mkCfgHead('cfg_header_protocol', '🔌 پروتکل'),
            $mkCfgHead('cfg_header_name', '✏️ نام کانفیگ'),
        ];
        $cfgRows[] = [
            $mkIndividual(['_rxcolor' => 'config_cell:download'], '📥 نمونه دکمه دریافت کانفیگ', 'config_list', 'cfg_download_one'),
            $mkIndividual(['_rxcolor' => 'config_cell:protocol'], '🔌 نمونه خانه پروتکل', 'config_list', 'cfg_protocol_cell'),
            $mkIndividual(['_rxcolor' => 'config_cell:name'], '✏️ نمونه خانه نام کانفیگ', 'config_list', 'cfg_name_cell'),
        ];
        $cfgRows[] = [
            $mkBtn('config_list', 'cfg_page_prev', ['label' => '◀️ قبلی']),
            $mkBtn('config_list', 'cfg_page_indicator', ['label' => '۱ / ۲']),
            $mkBtn('config_list', 'cfg_page_next', ['label' => 'بعدی ▶️']),
        ];
        $cfgRows[] = [$mkBtn('config_list', 'cfg_download_all', ['label' => '⚙️ دریافت همه کانفیگ‌ها'])];
        $cfgRows[] = [$mkBtn('config_list', 'cfg_back_service', ['label' => '↩️ بازگشت'])];
        $addScreen('config', 'لیست کانفیگ', 'key-round', 'جدول انتخاب کانفیگ در صفحه اطلاعات سرویس.', $cfgRows, [
            'note' => 'رنگ هر سرستون و هر دکمه مستقل است.',
            'table' => true,
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

        $rwAmounts = method_exists(\Faoxima\Wallet\Balance\ChargePrompts::class, 'rx_randomWalletAmounts') ? \Faoxima\Wallet\Balance\ChargePrompts::rx_randomWalletAmounts() : [];
        if (!is_array($rwAmounts) || empty($rwAmounts)) {
            $rwAmounts = [50000, 100000, 200000];
        }
        $rwAmounts = array_values($rwAmounts);
        $walletRows = [];
        for ($rwI = 0; $rwI < count($rwAmounts); $rwI += 2) {
            $rwRow = [
                $mkBtn('account', 'rw_pick_' . (int) $rwAmounts[$rwI], [
                    'label' => number_format((int) $rwAmounts[$rwI]) . ' تومان',
                    'fallback_key' => 'rw_pick',
                    'scopes' => ['button'],
                ]),
            ];
            if (isset($rwAmounts[$rwI + 1])) {
                $rwRow[] = $mkBtn('account', 'rw_pick_' . (int) $rwAmounts[$rwI + 1], [
                    'label' => number_format((int) $rwAmounts[$rwI + 1]) . ' تومان',
                    'fallback_key' => 'rw_pick',
                    'scopes' => ['button'],
                ]);
            }
            $walletRows[] = $rwRow;
        }
        $walletRows[] = [$mkBtn('account', 'rw_custom')];
        $walletRows[] = [$mkBtn('account', 'transferbal_confirm'), $mkBtn('account', 'transferbal_cancel')];
        $walletRows[] = [$mkBtn('account', 'backinfo', ['cond' => 'در دکمه‌های بازگشت به اطلاعات سرویس و بازگشت از شارژ کیف پول.'])];
        $addScreen('wallet', 'انتقال و شارژ کیف پول', 'wallet', 'مبالغ پیشنهادی شارژ، رقم دلخواه و تأیید انتقال موجودی.', $walletRows, [
            'note' => 'هر دکمه مبلغ شارژ رنگ مستقل خود را دارد و تغییر رنگ هر کدام روی بقیه مبالغ اثری ندارد.',
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
            [$mkIndividual(['_rxcolor' => 'navigation:confirm_back'], '↩️ بازگشت از تایید خرید', 'user_nav', 'confirm_back')],
            [$mkIndividual(['_rxcolor' => 'navigation:back'], '◀️ بازگشت عمومی', 'user_nav', 'nav_back'), $mkIndividual(['_rxcolor' => 'navigation:help_back'], '📚 بازگشت به آموزش‌ها', 'user_nav', 'nav_back')],
            [$mkIndividual(['_rxcolor' => 'navigation:account_back'], '↩️ بازگشت به حساب کاربری', 'account', 'backinfo')],
            [$mkBtn('user_nav', 'gen_random_uname')],
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
                $mkBtn('invoice_copy_buttons', 'currency_pick_TRX', ['label' => '🟥 ترون (TRX)', 'fallback_key' => 'currency_pick', 'scopes' => ['button']]),
                $mkBtn('invoice_copy_buttons', 'currency_pick_TON', ['label' => '🟦 تون (TON)', 'fallback_key' => 'currency_pick', 'scopes' => ['button']]),
            ],
            [
                $mkBtn('invoice_copy_buttons', 'currency_pick_USDT_TRC20', ['label' => '🟢 تتر روی ترون', 'fallback_key' => 'currency_pick', 'scopes' => ['button']]),
                $mkBtn('invoice_copy_buttons', 'currency_pick_USDT_TON', ['label' => '🟢 تتر روی تون', 'fallback_key' => 'currency_pick', 'scopes' => ['button']]),
            ],
            [$catalogSamples['crypto'][0], $catalogSamples['crypto'][1]],
            [$catalogSamples['crypto'][2]],
            [$catalogSamples['crypto'][3]],
            [$catalogSamples['crypto'][4]],
            [$mkBtn('invoice_copy_buttons', 'cancel_hash_input')],
            [$mkBtn('payment', 'colselist')],
        ];
        $addScreen('crypto', 'پرداخت کریپتو', 'coins', 'انتخاب ارز، کپی آدرس/مبلغ/ممو و ارسال هش پرداخت.', $cryptoRows, [
            'note' => 'هر دکمه انتخاب ارز رنگ مستقل خود را دارد و تغییر رنگ هر کدام روی بقیه ارزها اثری ندارد. دکمه «کپی ممو» فقط در شبکه تون نمایش داده می‌شود.',
        ]);

        $manualRows = [
            [$mkIndividual(['_rxcolor' => 'navigation:crypto_back'], '↩️ بازگشت به فهرست هش‌ها', 'recheckcrypto_buttons', 'recheckcrypto')],
            [$catalogSamples['crypto'][5], $catalogSamples['crypto'][6]],
            [$mkBtn('recheckcrypto_buttons', 'rcc_skip_photo'), $mkBtn('recheckcrypto_buttons', 'rcc_cancel')],
        ];
        $addScreen('crypto_manual', 'بررسی دستی هش', 'rotate-cw', 'دکمه‌های بررسی مجدد هش و اقدامات دستی.', $manualRows, [
            'note' => 'از هر اقدام یک نمونه نمایش داده شده است؛ رنگ همان اقدام برای همه تراکنش‌ها اعمال می‌شود.',
        ]);

        $subRows = [];

        return true;
    }
}
