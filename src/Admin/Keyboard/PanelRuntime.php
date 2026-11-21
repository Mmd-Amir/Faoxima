<?php

namespace Faoxima\Admin\Keyboard;

/**
 * Administration keyboard construction: provider, support, payment, gateway and report keyboards the administration stages read.
 * Methods operate on the includer's variable scope, passed by keyboard.php as an array.
 */
final class PanelRuntime
{
    /** Names of the includer-scope variables these methods read or write. */
    public const SCOPE = [
        'Exception_auto_cart_keyboard',
        'Methodextend',
        'Startelegram',
        'Trust_auto_cart_keyboard',
        'adminrule',
        'affiliates',
        'affiliatesAntiFraud',
        'autoconfirm_advanced_keyboard',
        'button',
        'configedit',
        'datatextbot',
        'departemanslist',
        'helpedit',
        'iranpaykeyboard',
        'keyboard_buy',
        'keyboard_stat',
        'keyboardagent',
        'keyboardchangelimit',
        'keyboardexportdata',
        'keyboardpanelcategory',
        'keyboardtimereset',
        'keyboardtypepanel',
        'keyboardtypepanelpublic',
        'keyboardtypepanelsubscription',
        'list_departman',
        'lottery',
        'nowpayment_setting_keyboard',
        'optionGuard',
        'optionJovad',
        'optionManualsale',
        'optionPasarGuard',
        'optionRebecca',
        'optionX_ui_single',
        'option_remnawave',
        'optionathmarzban',
        'optionathx_ui',
        'optionwg',
        'pdo',
        'result',
        'row',
        'rxAdminPanelBtn',
        'rxFxPricingRow',
        'setting',
        'stmt',
        'supportcenter',
        'supportoption',
        'table_exists',
        'textbotlang',
        'tronnowpayments',
        'wheelkeyboard',
    ];

    /**
     * Builds the provider, support and payment administration keyboards.
     */
    public static function providerPanels(array &$__fxScope)
    {
        // Scope bridge: keyboard.php runs in the variable scope of whoever includes it (the global scope for the payment entries, a
        // function scope inside the Cron payment context). It captures that scope into $__fxScope and imports it back afterwards; each
        // name below is an alias of the entry of the same name, so reads, writes and by-reference stage arguments behave as they did
        // when this code ran as the body of the included file.
        $Methodextend = &$__fxScope['Methodextend'];
        $adminrule = &$__fxScope['adminrule'];
        $affiliates = &$__fxScope['affiliates'];
        $affiliatesAntiFraud = &$__fxScope['affiliatesAntiFraud'];
        $button = &$__fxScope['button'];
        $configedit = &$__fxScope['configedit'];
        $datatextbot = &$__fxScope['datatextbot'];
        $departemanslist = &$__fxScope['departemanslist'];
        $helpedit = &$__fxScope['helpedit'];
        $iranpaykeyboard = &$__fxScope['iranpaykeyboard'];
        $keyboardagent = &$__fxScope['keyboardagent'];
        $keyboardexportdata = &$__fxScope['keyboardexportdata'];
        $keyboardpanelcategory = &$__fxScope['keyboardpanelcategory'];
        $keyboardtimereset = &$__fxScope['keyboardtimereset'];
        $keyboardtypepanel = &$__fxScope['keyboardtypepanel'];
        $keyboardtypepanelpublic = &$__fxScope['keyboardtypepanelpublic'];
        $keyboardtypepanelsubscription = &$__fxScope['keyboardtypepanelsubscription'];
        $list_departman = &$__fxScope['list_departman'];
        $lottery = &$__fxScope['lottery'];
        $optionGuard = &$__fxScope['optionGuard'];
        $optionJovad = &$__fxScope['optionJovad'];
        $optionManualsale = &$__fxScope['optionManualsale'];
        $optionPasarGuard = &$__fxScope['optionPasarGuard'];
        $optionRebecca = &$__fxScope['optionRebecca'];
        $optionX_ui_single = &$__fxScope['optionX_ui_single'];
        $option_remnawave = &$__fxScope['option_remnawave'];
        $optionathmarzban = &$__fxScope['optionathmarzban'];
        $optionathx_ui = &$__fxScope['optionathx_ui'];
        $optionwg = &$__fxScope['optionwg'];
        $pdo = &$__fxScope['pdo'];
        $result = &$__fxScope['result'];
        $row = &$__fxScope['row'];
        $rxAdminPanelBtn = &$__fxScope['rxAdminPanelBtn'];
        $rxFxPricingRow = &$__fxScope['rxFxPricingRow'];
        $setting = &$__fxScope['setting'];
        $stmt = &$__fxScope['stmt'];
        $supportcenter = &$__fxScope['supportcenter'];
        $supportoption = &$__fxScope['supportoption'];
        $table_exists = &$__fxScope['table_exists'];
        $textbotlang = &$__fxScope['textbotlang'];
        $tronnowpayments = &$__fxScope['tronnowpayments'];
        $wheelkeyboard = &$__fxScope['wheelkeyboard'];

        \Faoxima\Admin\Keyboard\ProviderPanels::handle(
            optionGuard: $optionGuard,
            optionJovad: $optionJovad,
            optionManualsale: $optionManualsale,
            optionPasarGuard: $optionPasarGuard,
            optionRebecca: $optionRebecca,
            optionX_ui_single: $optionX_ui_single,
            optionwg: $optionwg,
            rxAdminPanelBtn: $rxAdminPanelBtn ?? null,
            rxFxPricingRow: $rxFxPricingRow ?? null,
            textbotlang: $textbotlang ?? null
        );
        \Faoxima\Admin\Keyboard\SupportRoleAndReferrals::handle(
            adminrule: $adminrule,
            affiliates: $affiliates,
            affiliatesAntiFraud: $affiliatesAntiFraud,
            datatextbot: $datatextbot ?? null,
            helpedit: $helpedit,
            keyboardexportdata: $keyboardexportdata,
            setting: $setting ?? null,
            supportoption: $supportoption,
            textbotlang: $textbotlang ?? null
        );
        [$Methodextend, $keyboardtimereset, $keyboardtypepanel, $keyboardpanelcategory, $keyboardtypepanelpublic, $keyboardtypepanelsubscription] = \Faoxima\Admin\Keyboard\ProviderSettingsMenus::provider($textbotlang);
        $keyboardagent = \Faoxima\Telegram\Keyboard\CommerceLayouts::agent(
            $textbotlang['users']['backbtn'],
            $setting['inlinebtnmain'] == 'oninline',
            \Faoxima\Core\Database\Db::select("marzban_panel", "*", "MethodUsername", "متن دلخواه نماینده + عدد ترتیبی", "count") != 0,
        );

        \Faoxima\Admin\Keyboard\ProviderPaymentAndSupport::handle(
            button: $button,
            configedit: $configedit,
            departemanslist: $departemanslist,
            iranpaykeyboard: $iranpaykeyboard,
            optionathmarzban: $optionathmarzban,
            optionathx_ui: $optionathx_ui,
            pdo: $pdo ?? null,
            result: $result,
            row: $row,
            rxAdminPanelBtn: $rxAdminPanelBtn ?? null,
            stmt: $stmt,
            supportcenter: $supportcenter,
            table_exists: $table_exists,
            textbotlang: $textbotlang ?? null,
            tronnowpayments: $tronnowpayments
        );
        \Faoxima\Telegram\Keyboard\TicketDepartments::handle(
            list_departman: $list_departman,
            pdo: $pdo ?? null,
            result: $result,
            stmt: $stmt,
            table_exists: $table_exists ?? null,
            textbotlang: $textbotlang ?? null
        );
        \Faoxima\Admin\Keyboard\ActivationAndPrizes::handle(
            lottery: $lottery,
            option_remnawave: $option_remnawave,
            rxAdminPanelBtn: $rxAdminPanelBtn ?? null,
            rxFxPricingRow: $rxFxPricingRow ?? null,
            textbotlang: $textbotlang ?? null,
            wheelkeyboard: $wheelkeyboard
        );
    }

    /**
     * Builds the gateway and report administration keyboards.
     */
    public static function gatewayPanels(array &$__fxScope)
    {
        // Scope bridge: keyboard.php runs in the variable scope of whoever includes it (the global scope for the payment entries, a
        // function scope inside the Cron payment context). It captures that scope into $__fxScope and imports it back afterwards; each
        // name below is an alias of the entry of the same name, so reads, writes and by-reference stage arguments behave as they did
        // when this code ran as the body of the included file.
        $Exception_auto_cart_keyboard = &$__fxScope['Exception_auto_cart_keyboard'];
        $Startelegram = &$__fxScope['Startelegram'];
        $Trust_auto_cart_keyboard = &$__fxScope['Trust_auto_cart_keyboard'];
        $autoconfirm_advanced_keyboard = &$__fxScope['autoconfirm_advanced_keyboard'];
        $keyboard_buy = &$__fxScope['keyboard_buy'];
        $keyboard_stat = &$__fxScope['keyboard_stat'];
        $keyboardchangelimit = &$__fxScope['keyboardchangelimit'];
        $nowpayment_setting_keyboard = &$__fxScope['nowpayment_setting_keyboard'];
        $textbotlang = &$__fxScope['textbotlang'];

        \Faoxima\Admin\Keyboard\SharedCatalogLookup::handle();
        [$Startelegram, $keyboardchangelimit, $nowpayment_setting_keyboard, $autoconfirm_advanced_keyboard, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard] = \Faoxima\Admin\Keyboard\GatewayMenus::build($textbotlang);
        \Faoxima\Telegram\Keyboard\PurchaseButton::handle(
            keyboard_buy: $keyboard_buy
        );
        $keyboard_stat = \Faoxima\Admin\Keyboard\GatewayMenus::reports();
        [$Startelegram, $keyboardchangelimit, $nowpayment_setting_keyboard, $autoconfirm_advanced_keyboard, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard] = \Faoxima\Admin\Keyboard\GatewayMenus::finalize([$Startelegram, $keyboardchangelimit, $nowpayment_setting_keyboard, $autoconfirm_advanced_keyboard, $Exception_auto_cart_keyboard, $Trust_auto_cart_keyboard]);
    }
}
