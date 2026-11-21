<?php

namespace Faoxima\Admin\Web\Pages;

use stdClass;

/**
 * Handler stage. Inherited by value: $ALL_DB_KEYS, $CONFIG_ITEM_SECTION, $MENUS, $SCREENS, $addScreen, $adminWebPage, $factoryDefaultsMap, $mkBtn, $mkIndividual, $subRows, $userStylesRaw.
 * Inherited or exported by reference: $firstScreenId, $gateway, $gatewayInternalLabels, $gatewayInternalSamples, $key, $label, $role, $rxFactoryForJs, $rxStylesForJs.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceKeyboardServiceScreens
{
    public static function handle(
        $ALL_DB_KEYS,
        $CONFIG_ITEM_SECTION,
        $MENUS,
        $SCREENS,
        $addScreen,
        $adminWebPage,
        $factoryDefaultsMap,
        &$firstScreenId,
        &$gateway,
        &$gatewayInternalLabels,
        &$gatewayInternalSamples,
        &$key,
        &$label,
        $mkBtn,
        $mkIndividual,
        &$role,
        &$rxFactoryForJs,
        &$rxStylesForJs,
        $subRows,
        $userStylesRaw
    ) {
        if (($adminWebPage ?? null) !== 'service_keyboard.php') {
            return;
        }

        foreach (['username', 'Status', 'expirationDate', 'daysleft', 'LastTraffic', 'usedtraffic', 'RemainingVolume'] as $field) {
            $fieldLabel = $MENUS['user_subscription']['buttons'][$field];
            $subRows[] = [
                $mkIndividual(['_rxcolor' => 'subscription_field:' . $field . ':label'], 'عنوان · ' . $fieldLabel, 'user_subscription', $field),
                $mkIndividual(['_rxcolor' => 'subscription_field:' . $field . ':value'], 'مقدار · ' . $fieldLabel, 'user_subscription', $field),
            ];
        }
        $subRows = array_merge($subRows, [
            [$mkIndividual(['_rxcolor' => 'subscription_list:previous'], '◀️ صفحه قبلی سرویس‌ها', 'user_dynamic_lists', 'user_services_list'), $mkIndividual(['_rxcolor' => 'subscription_list:next'], '▶️ صفحه بعدی سرویس‌ها', 'user_dynamic_lists', 'user_services_list')],
            [$mkIndividual(['_rxcolor' => 'subscription_list:search'], '🔎 جستجوی سرویس', 'user_dynamic_lists', 'user_services_list')],
            [$mkIndividual(['_rxcolor' => 'subscription_list:renewal_previous'], '◀️ صفحه قبلی لیست تمدید', 'user_dynamic_lists', 'user_services_list'), $mkIndividual(['_rxcolor' => 'subscription_list:renewal_next'], '▶️ صفحه بعدی لیست تمدید', 'user_dynamic_lists', 'user_services_list')],
            [$mkIndividual(['_rxcolor' => 'subscription_action:extend'], '🔄 تمدید اشتراک', 'user_subscription', 'exntedagei'), $mkIndividual(['_rxcolor' => 'subscription_action:volume'], '➕ خرید حجم اشتراک', 'user_subscription', 'extravolunme')],
            [$mkBtn('user_subscription', 'buy_service')],
            [$mkBtn('user_subscription', 'helpbtn'), $mkBtn('user_subscription', 'notusernameme')],
        ]);
        $addScreen('subscription', 'اشتراک و سرویس‌های من', 'cart-check', 'جزئیات سرویس و دکمه‌های سرویس‌های من.', $subRows, [
            'note' => 'عنوان و مقدار هر فیلد، تمدید و خرید حجم رنگ مستقل دارند. مقادیر برای هر کاربر متفاوت است؛ رنگ انتخابی برای همان خانه اعمال می‌شود.',
        ]);

        $ticketRows = [
            [$mkIndividual(['_rxcolor' => 'navigation:ticket_back'], '↩️ بازگشت به تیکت‌ها', 'account', 'supporttickets')],
            [$mkBtn('user_subscription', 'ticketnew')],
            [$mkBtn('account', 'supporttickets')],
            [$mkBtn('user_nav', 'tk_media_yes'), $mkBtn('user_nav', 'tk_media_no')],
            [$mkBtn('user_nav', 'tk_cancel')],
        ];
        foreach (range(1, 5) as $mediaCount) {
            $ticketRows[] = [$mkIndividual(['callback_data' => 'tk_cnt_' . $mediaCount], 'تعداد رسانه: ' . $mediaCount, 'user_dynamic_lists', 'ticket_list')];
        }
        $addScreen('tickets', 'تیکت و پشتیبانی', 'headset', 'دکمه‌های تیکت جدید، لیست تیکت‌ها و انتخاب رسانه.', $ticketRows, [
            'note' => 'دکمه‌های بله/خیر رسانه فقط در مرحله ارسال تیکت نمایش داده می‌شوند.',
        ]);

        $listRows = [
            [$mkIndividual(['_rxcolor' => 'product_header:name'], 'سرتیتر نام محصول', 'user_dynamic_lists', 'product_buy'), $mkIndividual(['_rxcolor' => 'product_header:duration'], 'سرتیتر زمان محصول', 'user_dynamic_lists', 'product_buy'), $mkIndividual(['_rxcolor' => 'product_header:price'], 'سرتیتر قیمت محصول', 'user_dynamic_lists', 'product_buy')],
            [$mkIndividual(['callback_data' => 'customsellvolume'], 'حجم دلخواه خرید', 'user_dynamic_lists', 'custom_volume'), $mkIndividual(['callback_data' => 'customsellvolumeom'], 'حجم دلخواه خرید دیگران', 'user_dynamic_lists', 'custom_volume')],
            [$mkBtn('user_dynamic_lists', 'product_back'), $mkBtn('user_dynamic_lists', 'category_back')],
            [$mkBtn('user_dynamic_lists', 'panel_back')],
            [$mkIndividual(['_rxck' => 'card_num'], 'کپی کارت اول', 'user_dynamic_lists', 'copy_card_num'), $mkIndividual(['_rxck' => 'card_num_2'], 'کپی کارت دوم', 'user_dynamic_lists', 'copy_text_btn')],
            [$mkIndividual(['_rxck' => 'card_amount'], 'کپی مبلغ کارت', 'user_dynamic_lists', 'copy_card_amount')],
        ];
        $addScreen('lists', 'لیست‌های داینامیک', 'server-stack', 'رنگ دکمه‌های محصولات و نمونه‌های پیش‌فرض هر بخش.', $listRows, [
            'note' => 'محصولات خودکار نمایش داده می‌شوند. نمونه‌های هر بخش، رنگ پیش‌فرض همان نوع دکمه را برای آیتم‌های فعلی و جدید تعیین می‌کنند.',
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

        foreach (['tonpay' => 'تون‌پی', 'atlaspay' => 'اطلس‌پی'] as $gateway => $gatewayLabel) {
            $gatewayRows = [];
            foreach (\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayButtonStyleLabels() as $key => $label) {
                if ($gateway === 'atlaspay' && $key === 'change_card') { continue; }
                $gatewayRows[] = [$mkIndividual(['_rxcolor' => 'payment:' . $gateway . ':' . $key], $label, 'tonpay_payment', $key)];
            }
            $addScreen($gateway, 'پرداخت ' . $gatewayLabel, 'credit-card', 'رنگ مستقل دکمه‌های پرداخت ' . $gatewayLabel, $gatewayRows);
        }

        $gatewayInternalLabels = [
            'rialit' => 'ریالیت', 'zarinpal' => 'زرین‌پال', 'plisio' => 'Plisio', 'nowpayment' => 'NowPayments',
            'iranpay2' => 'ترونادو', 'blupal' => 'بلوپال', 'cubepay' => 'کیوب‌پی',
            'variza' => 'واریزا', 'abangateway' => 'آبان گیت‌وی',
            'tonpay' => 'تون‌پی — لینک و پیگیری', 'atlaspay' => 'اطلس‌پی — لینک و پیگیری',
            'startelegrams' => 'ستاره تلگرام', 'pending' => 'فهرست تراکنش‌های در انتظار',
        ];
        $gatewayCommonRoles = [
            'pay_link' => '💳 پرداخت', 'pending_cancel' => '♻️ لغو و دریافت فاکتور جدید',
            'retry' => '🔄 ساخت پرداخت جدید', 'back_main' => '🔙 بازگشت به منوی اصلی',
        ];
        $gatewayInternalSamples = [];
        foreach ($gatewayInternalLabels as $gateway => $gatewayLabel) {
            $roles = $gatewayCommonRoles;
            if ($gateway === 'rialit') {
                $roles = [
                    'pay_link' => '💳 پرداخت', 'continue_link' => '▶️ ادامه پرداخت', 'check_status' => '🔄 بررسی وضعیت',
                    'cancel_transaction' => '🚫 انصراف و پرداخت جدید', 'cancel_confirm_approve' => '✅ تایید انصراف',
                    'cancel_confirm_back' => '🔙 بازگشت از تایید انصراف', 'retry' => '💳 انتخاب روش پرداخت جدید',
                    'pending_list' => '⏳ تراکنش‌های در انتظار', 'list_view' => '💚 پیگیری ریالیت در فهرست تراکنش‌ها',
                ];
            } elseif (in_array($gateway, ['tonpay', 'atlaspay'], true)) {
                unset($roles['back_main']);
                $roles += [
                    'view_details' => '🔍 مشاهده جزئیات اعلان پرداخت', 'support' => '📞 پشتیبانی پرداخت',
                    'pending_list' => '⏳ تراکنش‌های در انتظار', 'list_view' => '🔍 جزئیات تراکنش در فهرست',
                    'list_cancel' => '❌ لغو تراکنش در فهرست',
                ];
                if ($gateway === 'tonpay') $roles['receipt_back'] = '🔙 بازگشت از ارسال رسید';
                else $roles['list_receipt'] = '📤 ارسال رسید از فهرست تراکنش‌ها';
            } elseif ($gateway === 'startelegrams') {
                $roles = ['pay_link' => '⭐ پرداخت با ستاره تلگرام'];
            } elseif ($gateway === 'pending') {
                $roles = ['back_account' => '🔙 بازگشت به حساب از فهرست تراکنش‌ها'];
            }
            $gatewayInternalSamples[$gateway] = [];
            foreach ($roles as $role => $label) {
                $gatewayInternalSamples[$gateway][] = $mkIndividual(
                    ['_rxcolor' => 'payment:' . $gateway . ':' . $role], $label,
                    'pay_receipt', rx_payment_color_fallback($role)
                ) + ['sample' => true];
            }
        }
        $addScreen('gateway_internals', 'دکمه‌های داخلی درگاه‌ها', 'credit-card', 'رنگ مستقل دکمه‌های پرداخت، پیگیری و لغو برای هر درگاه.', [], [
            'note' => 'با قبلی و بعدی درگاه را انتخاب کنید. از هر دکمه یک نمونه نمایش داده می‌شود و رنگ آن برای همان دکمه در همه فاکتورها اعمال می‌شود.',
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

        return true;
    }
}
