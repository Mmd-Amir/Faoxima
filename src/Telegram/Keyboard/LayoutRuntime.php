<?php

namespace Faoxima\Telegram\Keyboard;

use PDO;

/**
 * Keyboard layout construction: loads the texts, settings and style maps, then builds the main menu and the user and catalogue
 * keyboards the stages read. Methods operate on the includer's variable scope, passed by keyboard.php as an array.
 * main() returns true when construction completed; anything else stops keyboard.php.
 */
final class LayoutRuntime
{
    /** Names of the includer-scope variables these methods read or write. */
    public const SCOPE = [
        'CartManage',
        'Feature_status',
        'MethodUsername',
        'MethodUsernameRemna',
        'NowPaymentsManage',
        '__rxDecoded',
        '_rxStyleRef',
        '_rx_all_kbs',
        'abangateway',
        'adminChannelMenu',
        'adminPanelsMenu',
        'adminUsersMenu',
        'admin_idss',
        'adminrulecheck',
        'atlaspay',
        'backadmin',
        'backuser',
        'blupal',
        'button',
        'callback_query_id',
        'change_product',
        'channelkeyboard',
        'confrimrolls',
        'cubepay',
        'dashboardMenu',
        'data_text_bot',
        'datain',
        'datatextbot',
        'domainhosts',
        'first_name',
        'from_id',
        'getNameCustomKb',
        'item',
        'json_list_helpkey',
        'json_list_helpـcategory',
        'json_list_marzban_panel',
        'json_list_product_list_admin',
        'keyboard',
        'keyboard_Category_manage',
        'keyboard_shop_manage',
        'keyboardadmin',
        'keyboardhelpadmin',
        'keyboardprotocol',
        'keyboardprotocollist',
        'keyboardzarinpal',
        'list_card_remove',
        'list_marzban_panel_edit_product',
        'list_marzban_panel_user',
        'list_marzban_panel_userom',
        'list_marzban_panel_userschange',
        'list_marzban_usertest',
        'message_id',
        'optionMarzban',
        'payment',
        'paymentom',
        'pdo',
        'product',
        'request_contact',
        'result',
        'rialit',
        'row',
        'rxAdminPanelBtn',
        'rxFxPricingRow',
        'setting',
        'setting_panel',
        'shopkeyboard',
        'statusnote',
        'step_payment',
        'stmt',
        'table_exists',
        'text',
        'textbot',
        'textbotlang',
        'textdatabot',
        'tonpay',
        'trnado',
        'username',
        'usernamePromptKb',
        'users',
        'variza',
    ];

    /**
     * Builds the localisation, style maps and main menu.
     */
    public static function main(array &$__fxScope)
    {
        // Scope bridge: keyboard.php runs in the variable scope of whoever includes it (the global scope for the payment entries, a
        // function scope inside the Cron payment context). It captures that scope into $__fxScope and imports it back afterwards; each
        // name below is an alias of the entry of the same name, so reads, writes and by-reference stage arguments behave as they did
        // when this code ran as the body of the included file.
        global $_rx_acc_styles, $_rx_adm_styles, $_rx_cat_styles, $_rx_chan_styles, $_rx_feat_styles, $_rx_gw_styles, $_rx_helpa_styles, $_rx_nav_styles, $_rx_pay_styles, $_rx_payrcpt_styles, $_rx_pedit_styles, $_rx_prod_styles, $_rx_rol_styles, $_rx_set_styles, $_rx_shp_styles, $_rx_svc_styles, $_rx_tx_styles;
        $CartManage = &$__fxScope['CartManage'];
        $NowPaymentsManage = &$__fxScope['NowPaymentsManage'];
        $_rxStyleRef = &$__fxScope['_rxStyleRef'];
        $_rx_all_kbs = &$__fxScope['_rx_all_kbs'];
        $abangateway = &$__fxScope['abangateway'];
        $adminChannelMenu = &$__fxScope['adminChannelMenu'];
        $adminPanelsMenu = &$__fxScope['adminPanelsMenu'];
        $adminUsersMenu = &$__fxScope['adminUsersMenu'];
        $admin_idss = &$__fxScope['admin_idss'];
        $adminrulecheck = &$__fxScope['adminrulecheck'];
        $atlaspay = &$__fxScope['atlaspay'];
        $blupal = &$__fxScope['blupal'];
        $callback_query_id = &$__fxScope['callback_query_id'];
        $cubepay = &$__fxScope['cubepay'];
        $dashboardMenu = &$__fxScope['dashboardMenu'];
        $data_text_bot = &$__fxScope['data_text_bot'];
        $datain = &$__fxScope['datain'];
        $datatextbot = &$__fxScope['datatextbot'];
        $domainhosts = &$__fxScope['domainhosts'];
        $first_name = &$__fxScope['first_name'];
        $from_id = &$__fxScope['from_id'];
        $item = &$__fxScope['item'];
        $keyboard = &$__fxScope['keyboard'];
        $keyboardadmin = &$__fxScope['keyboardadmin'];
        $keyboardzarinpal = &$__fxScope['keyboardzarinpal'];
        $message_id = &$__fxScope['message_id'];
        $pdo = &$__fxScope['pdo'];
        $result = &$__fxScope['result'];
        $rialit = &$__fxScope['rialit'];
        $row = &$__fxScope['row'];
        $setting = &$__fxScope['setting'];
        $setting_panel = &$__fxScope['setting_panel'];
        $step_payment = &$__fxScope['step_payment'];
        $stmt = &$__fxScope['stmt'];
        $table_exists = &$__fxScope['table_exists'];
        $text = &$__fxScope['text'];
        $textbotlang = &$__fxScope['textbotlang'];
        $textdatabot = &$__fxScope['textdatabot'];
        $tonpay = &$__fxScope['tonpay'];
        $trnado = &$__fxScope['trnado'];
        $username = &$__fxScope['username'];
        $users = &$__fxScope['users'];
        $variza = &$__fxScope['variza'];

        if (!isset($from_id))           { $from_id = 0; }
        if (!isset($datain))            { $datain = ''; }
        if (!isset($text))              { $text = ''; }
        if (!isset($message_id))        { $message_id = 0; }
        if (!isset($callback_query_id)) { $callback_query_id = ''; }
        if (!isset($username))          { $username = ''; }
        if (!isset($first_name))        { $first_name = ''; }

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('DB_UNAVAILABLE', 'Keyboard layouts loaded with no PDO; skipping DB-dependent setup.', [
                    'where' => 'layouts_1',
                ]);
            }
            return;
        }

        $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null,"select");
        $textbotlang = \Faoxima\Telegram\Conversation\ConversationData::languagechange(REFACTORED_LEGACY_ROOT.'/text.json');
        if (!is_array($textbotlang)) {
            $textbotlang = [];
        }
        if (!isset($textbotlang['Admin']) || !is_array($textbotlang['Admin'])) {
            $textbotlang['Admin'] = [];
        }
        $textbotlang['Admin']['backadmin'] = $textbotlang['Admin']['backadmin'] ?? "🏠 بازگشت به منوی مدیریت";
        $textbotlang['Admin']['backmenu'] = $textbotlang['Admin']['backmenu'] ?? "▶️ بازگشت به منوی قبل";









        $stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        $datatextbot = array(
            'text_usertest' => '',
            'text_Purchased_services' => '',
            'text_support' => '',
            'text_help' => '',
            'text_start' => '',
            'text_bot_off' => '',
            'text_dec_info' => '',
            'text_dec_usertest' => '',
            'text_fq' => '',
            'accountwallet' => '',
            'text_sell' => '',
            'text_Add_Balance' => '',
            'text_Discount' => '',
            'text_Tariff_list' => '',
            'text_affiliates' => '',
            'carttocart' => '',
            'textnowpayment' => '',
            'textnowpaymenttron' => '',
            'iranpay1' => '',
            'iranpay2' => '',
            'iranpay3' => '',
            'tonpay' => '',
            'rialit' => '',
            'tetraminator' => '',
            'cubepay' => '',
            'blupal' => '',
            'abangateway' => '',
            'atlaspay' => '',
            'zarinpal' => '',
            'text_fq' => '',
            'textpaymentnotverify' =>"",
            'textrequestagent' => '',
            'textpanelagent' => '',
            'text_wheel_luck' => '',
            'text_star_telegram' => "",
            'text_extend' => '',
            'textsnowpayment' => ''

        );
        if ($table_exists) {
            $textdatabot =  \Faoxima\Core\Database\Db::select("textbot", "*", null, null,"fetchAll");
            $data_text_bot = array();
            foreach ($textdatabot as $row) {
                $data_text_bot[] = array(
                    'id_text' => $row['id_text'],
                    'text' => $row['text']
                );
            }
            foreach ($data_text_bot as $item) {
                if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
                    $datatextbot[$item['id_text']] = $item['text'];
                }
            }
        }
        $adminrulecheck = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $from_id,"select");
        if (!$adminrulecheck) {
            $adminrulecheck = array(
                'rule' => '',
            );
        }
        $users = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id,"select");
        if ($users == false) {
            $users = array();
            $users = array(
                'step' => '',
                'agent' => '',
                'limit_usertest' => '',
                'Processing_value' => '',
                'Processing_value_four' => '',
                'cardpayment' => ""
            );
        }
        $admin_idss = \Faoxima\Core\Database\Db::select("admin", "*", "id_admin", $from_id,"count");
        $keyboard = \Faoxima\Telegram\Keyboard\MainMenu::build(
            $setting,
            $datatextbot,
            $textbotlang,
            $users,
            $admin_idss,
            (string) ($domainhosts ?? ($GLOBALS['domainhosts'] ?? '')),
            method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_richSectionActive') ? [\Faoxima\Telegram\Presentation\Sections::class, 'rx_richSectionActive'] : null,
            method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb') ? [\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb'] : null,
            function_exists('rx_sanitizeKeyboardButtons') ? 'rx_sanitizeKeyboardButtons' : null,
            method_exists(\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_mark_keyboard') ? [\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_mark_keyboard'] : null,
            function_exists('rx_log_event') ? 'rx_log_event' : null,
        );

        global $_rx_acc_styles, $_rx_tx_styles, $_rx_pay_styles, $_rx_payrcpt_styles, $_rx_adm_styles, $_rx_set_styles, $_rx_shp_styles, $_rx_nav_styles, $_rx_rol_styles, $_rx_gw_styles, $_rx_svc_styles, $_rx_feat_styles, $_rx_chan_styles, $_rx_helpa_styles, $_rx_cat_styles, $_rx_prod_styles, $_rx_pedit_styles;

        $_rx_acc_styles  = [];
        $_rx_tx_styles   = [];
        $_rx_pay_styles  = [];
        $_rx_payrcpt_styles = [];
        $_rx_adm_styles  = [];
        $_rx_set_styles  = [];
        $_rx_shp_styles  = [];
        $_rx_nav_styles  = [];
        $_rx_rol_styles  = [];
        $_rx_gw_styles   = [];
        $_rx_svc_styles  = [];
        $_rx_feat_styles  = [];
        $_rx_chan_styles  = [];
        $_rx_helpa_styles = [];
        $_rx_cat_styles   = [];
        $_rx_prod_styles  = [];
        $_rx_pedit_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbs)) {
                if (!empty($_rx_all_kbs['account']))        $_rx_acc_styles = $_rx_all_kbs['account'];
                if (!empty($_rx_all_kbs['tx_stats']))        $_rx_tx_styles  = $_rx_all_kbs['tx_stats'];
                if (!empty($_rx_all_kbs['payment']))        $_rx_pay_styles = $_rx_all_kbs['payment'];
                if (!empty($_rx_all_kbs['pay_receipt']))    $_rx_payrcpt_styles = $_rx_all_kbs['pay_receipt'];
                if (!empty($_rx_all_kbs['admin_main']))     $_rx_adm_styles = $_rx_all_kbs['admin_main'];
                if (!empty($_rx_all_kbs['admin_settings'])) $_rx_set_styles = $_rx_all_kbs['admin_settings'];
                if (!empty($_rx_all_kbs['admin_shop']))     $_rx_shp_styles = $_rx_all_kbs['admin_shop'];
                if (!empty($_rx_all_kbs['user_nav']))       $_rx_nav_styles = $_rx_all_kbs['user_nav'];
                if (!empty($_rx_all_kbs['admin_roles']))    $_rx_rol_styles = $_rx_all_kbs['admin_roles'];
                if (!empty($_rx_all_kbs['admin_gateways'])) $_rx_gw_styles  = $_rx_all_kbs['admin_gateways'];
                if (!empty($_rx_all_kbs['service']))        $_rx_svc_styles = $_rx_all_kbs['service'];
                if (!empty($_rx_all_kbs['admin_features']))   $_rx_feat_styles  = $_rx_all_kbs['admin_features'];
                if (!empty($_rx_all_kbs['admin_channel']))    $_rx_chan_styles  = $_rx_all_kbs['admin_channel'];
                if (!empty($_rx_all_kbs['admin_help']))       $_rx_helpa_styles = $_rx_all_kbs['admin_help'];
                if (!empty($_rx_all_kbs['admin_category']))   $_rx_cat_styles   = $_rx_all_kbs['admin_category'];
                if (!empty($_rx_all_kbs['admin_products']))   $_rx_prod_styles  = $_rx_all_kbs['admin_products'];
                if (!empty($_rx_all_kbs['admin_product_edit'])) $_rx_pedit_styles = $_rx_all_kbs['admin_product_edit'];
            }
        }

        foreach ([&$_rx_acc_styles, &$_rx_tx_styles, &$_rx_pay_styles, &$_rx_payrcpt_styles, &$_rx_nav_styles, &$_rx_svc_styles] as &$_rxStyleRef) {
            if (is_array($_rxStyleRef)) {
                $_rxStyleRef = array_filter($_rxStyleRef, static function ($v) { return $v !== 'default'; });
            }
        }
        unset($_rxStyleRef);

        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_acc_styles   = $_rx_acc_styles   + rx_getKeyboardDefaultStyles('account');
            $_rx_tx_styles    = $_rx_tx_styles    + rx_getKeyboardDefaultStyles('tx_stats');
            $_rx_pay_styles   = $_rx_pay_styles   + rx_getKeyboardDefaultStyles('payment');
            $_rx_nav_styles   = $_rx_nav_styles   + rx_getKeyboardDefaultStyles('user_nav');
        }
        $_rx_adm_styles = $_rx_set_styles = $_rx_shp_styles = $_rx_rol_styles = $_rx_gw_styles = $_rx_feat_styles = $_rx_chan_styles = $_rx_helpa_styles = $_rx_cat_styles = $_rx_prod_styles = $_rx_pedit_styles = ['__rxplain__' => 1];
        $_rx_svc_styles['__rxkeep__'] = $_rx_acc_styles['__rxkeep__'] = $_rx_pay_styles['__rxkeep__'] = $_rx_payrcpt_styles['__rxkeep__'] = $_rx_nav_styles['__rxkeep__'] = $_rx_tx_styles['__rxkeep__'] = 1;
        $GLOBALS['_rx_acc_styles'] = $_rx_acc_styles;
        $GLOBALS['_rx_tx_styles'] = $_rx_tx_styles;
        $GLOBALS['_rx_pay_styles'] = $_rx_pay_styles;
        $GLOBALS['_rx_payrcpt_styles'] = $_rx_payrcpt_styles;
        $GLOBALS['_rx_adm_styles'] = $_rx_adm_styles;
        $GLOBALS['_rx_set_styles'] = $_rx_set_styles;
        $GLOBALS['_rx_shp_styles'] = $_rx_shp_styles;
        $GLOBALS['_rx_nav_styles'] = $_rx_nav_styles;
        $GLOBALS['_rx_rol_styles'] = $_rx_rol_styles;
        $GLOBALS['_rx_gw_styles'] = $_rx_gw_styles;
        $GLOBALS['_rx_svc_styles'] = $_rx_svc_styles;
        $GLOBALS['_rx_feat_styles'] = $_rx_feat_styles;
        $GLOBALS['_rx_chan_styles'] = $_rx_chan_styles;
        $GLOBALS['_rx_helpa_styles'] = $_rx_helpa_styles;
        $GLOBALS['_rx_cat_styles'] = $_rx_cat_styles;
        $GLOBALS['_rx_prod_styles'] = $_rx_prod_styles;
        $GLOBALS['_rx_pedit_styles'] = $_rx_pedit_styles;


        $dashboardMenu = \Faoxima\Admin\Keyboard\AdminDashboardMenu::build($adminrulecheck['rule'] ?? null, $textbotlang, $_rx_adm_styles, $_rx_rol_styles);
        if ($dashboardMenu !== null) {
            $keyboardadmin = $dashboardMenu;
        }
        unset($dashboardMenu);
        \Faoxima\Admin\Keyboard\PaymentGateways::handle(
            CartManage: $CartManage,
            NowPaymentsManage: $NowPaymentsManage,
            _rx_gw_styles: $_rx_gw_styles ?? null,
            abangateway: $abangateway,
            atlaspay: $atlaspay,
            blupal: $blupal,
            cubepay: $cubepay,
            keyboardzarinpal: $keyboardzarinpal,
            rialit: $rialit,
            textbotlang: $textbotlang ?? null,
            tonpay: $tonpay,
            trnado: $trnado,
            variza: $variza
        );
        [$setting_panel, $adminPanelsMenu, $adminChannelMenu, $adminUsersMenu] = \Faoxima\Admin\Keyboard\SettingsMenus::build($textbotlang, $_rx_set_styles, $_rx_adm_styles);
        \Faoxima\Telegram\Keyboard\PaymentMethods::handle(
            _rx_pay_styles: $_rx_pay_styles ?? null,
            datatextbot: $datatextbot ?? null,
            from_id: $from_id ?? null,
            pdo: $pdo ?? null,
            step_payment: $step_payment,
            stmt: $stmt,
            users: $users ?? null
        );

        return true;
    }

    /**
     * Builds the navigation, catalogue and payment keyboards.
     */
    public static function menus(array &$__fxScope)
    {
        // Scope bridge: keyboard.php runs in the variable scope of whoever includes it (the global scope for the payment entries, a
        // function scope inside the Cron payment context). It captures that scope into $__fxScope and imports it back afterwards; each
        // name below is an alias of the entry of the same name, so reads, writes and by-reference stage arguments behave as they did
        // when this code ran as the body of the included file.
        global $_rx_acc_styles, $_rx_cat_styles, $_rx_chan_styles, $_rx_feat_styles, $_rx_helpa_styles, $_rx_nav_styles, $_rx_pedit_styles, $_rx_prod_styles, $_rx_shp_styles;
        $Feature_status = &$__fxScope['Feature_status'];
        $MethodUsername = &$__fxScope['MethodUsername'];
        $MethodUsernameRemna = &$__fxScope['MethodUsernameRemna'];
        $__rxDecoded = &$__fxScope['__rxDecoded'];
        $backadmin = &$__fxScope['backadmin'];
        $backuser = &$__fxScope['backuser'];
        $button = &$__fxScope['button'];
        $change_product = &$__fxScope['change_product'];
        $channelkeyboard = &$__fxScope['channelkeyboard'];
        $confrimrolls = &$__fxScope['confrimrolls'];
        $from_id = &$__fxScope['from_id'];
        $getNameCustomKb = &$__fxScope['getNameCustomKb'];
        $json_list_helpkey = &$__fxScope['json_list_helpkey'];
        $json_list_helpـcategory = &$__fxScope['json_list_helpـcategory'];
        $json_list_marzban_panel = &$__fxScope['json_list_marzban_panel'];
        $json_list_product_list_admin = &$__fxScope['json_list_product_list_admin'];
        $keyboard_Category_manage = &$__fxScope['keyboard_Category_manage'];
        $keyboard_shop_manage = &$__fxScope['keyboard_shop_manage'];
        $keyboardhelpadmin = &$__fxScope['keyboardhelpadmin'];
        $keyboardprotocol = &$__fxScope['keyboardprotocol'];
        $keyboardprotocollist = &$__fxScope['keyboardprotocollist'];
        $list_card_remove = &$__fxScope['list_card_remove'];
        $list_marzban_panel_edit_product = &$__fxScope['list_marzban_panel_edit_product'];
        $list_marzban_panel_user = &$__fxScope['list_marzban_panel_user'];
        $list_marzban_panel_userom = &$__fxScope['list_marzban_panel_userom'];
        $list_marzban_panel_userschange = &$__fxScope['list_marzban_panel_userschange'];
        $list_marzban_usertest = &$__fxScope['list_marzban_usertest'];
        $optionMarzban = &$__fxScope['optionMarzban'];
        $payment = &$__fxScope['payment'];
        $paymentom = &$__fxScope['paymentom'];
        $pdo = &$__fxScope['pdo'];
        $product = &$__fxScope['product'];
        $request_contact = &$__fxScope['request_contact'];
        $result = &$__fxScope['result'];
        $row = &$__fxScope['row'];
        $rxAdminPanelBtn = &$__fxScope['rxAdminPanelBtn'];
        $rxFxPricingRow = &$__fxScope['rxFxPricingRow'];
        $setting = &$__fxScope['setting'];
        $shopkeyboard = &$__fxScope['shopkeyboard'];
        $statusnote = &$__fxScope['statusnote'];
        $stmt = &$__fxScope['stmt'];
        $table_exists = &$__fxScope['table_exists'];
        $text = &$__fxScope['text'];
        $textbot = &$__fxScope['textbot'];
        $textbotlang = &$__fxScope['textbotlang'];
        $usernamePromptKb = &$__fxScope['usernamePromptKb'];
        $users = &$__fxScope['users'];

        $keyboardhelpadmin = \Faoxima\Admin\Keyboard\ShopMenus::help($textbotlang, $_rx_helpa_styles);
        $shopkeyboard = \Faoxima\Admin\Keyboard\ShopMenus::shop($textbotlang, $_rx_shp_styles);
        $keyboard_Category_manage = \Faoxima\Admin\Keyboard\ShopMenus::categories($_rx_cat_styles);
        $keyboard_shop_manage = \Faoxima\Admin\Keyboard\ShopMenus::products($_rx_prod_styles);
        [$confrimrolls, $request_contact] = \Faoxima\Telegram\Keyboard\NavigationLayouts::onboarding($textbotlang['users']['backbtn'], $_rx_nav_styles, [\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_kb_style']);
        $Feature_status = \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::features($textbotlang, $_rx_feat_styles);
        $channelkeyboard = \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::channels($pdo, $textbotlang, $_rx_chan_styles ?? null);
        [$backuser, $usernamePromptKb] = \Faoxima\Telegram\Keyboard\NavigationLayouts::username($textbotlang['users']['backbtn'], $_rx_acc_styles, $_rx_nav_styles, [\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_kb_style']);
        $backadmin = \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::back($textbotlang, $_rx_chan_styles ?? null);
        $getNameCustomKb = \Faoxima\Telegram\Keyboard\NavigationLayouts::customUsername($textbotlang['Admin']['backadmin'], $textbotlang['Admin']['backmenu']);
        \Faoxima\Admin\Keyboard\ProviderChannelAndCardLists::handle(
            button: $button,
            json_list_helpkey: $json_list_helpkey,
            json_list_marzban_panel: $json_list_marzban_panel,
            list_card_remove: $list_card_remove,
            list_marzban_panel_edit_product: $list_marzban_panel_edit_product,
            pdo: $pdo ?? null,
            result: $result,
            row: $row,
            stmt: $stmt,
            table_exists: $table_exists,
            textbotlang: $textbotlang ?? null
        );
        $statusnote = \Faoxima\Telegram\Bootstrap\LocalizationHelpers::purchaseNotesEnabled($setting, $users);
        $json_list_helpـcategory = \Faoxima\Telegram\Keyboard\CatalogMenus::help($pdo, $textbotlang['users']['backbtn']);
        $list_marzban_panel_user = \Faoxima\Telegram\Keyboard\CatalogMenus::purchase($pdo, $users, $from_id, $textbotlang['users']['backbtn'], $statusnote);
        $list_marzban_panel_userom = \Faoxima\Telegram\Keyboard\CatalogMenus::alternate($pdo, $users, $from_id, $textbotlang['users']['backbtn']);
        $list_marzban_panel_userschange = \Faoxima\Telegram\Keyboard\CatalogMenus::changeLocation($pdo, $users, $from_id, $textbotlang['users']['backbtn']);
        $list_marzban_usertest = \Faoxima\Telegram\Keyboard\CatalogMenus::trial($pdo, $users, $from_id, $textbotlang['users']['backbtn']);
        \Faoxima\Admin\Keyboard\TableAndDiscountLists::handle(
            button: $button,
            json_list_product_list_admin: $json_list_product_list_admin,
            keyboardprotocollist: $keyboardprotocollist,
            pdo: $pdo ?? null,
            product: $product,
            result: $result,
            row: $row,
            stmt: $stmt,
            table_exists: $table_exists,
            text: $text ?? null,
            textbot: $textbot,
            textbotlang: $textbotlang ?? null,
            users: $users ?? null
        );
        [$payment, $paymentom] = \Faoxima\Telegram\Keyboard\CommerceLayouts::purchase($textbotlang['users']['backbtn'], $_rx_nav_styles, [\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_kb_style']);
        [$change_product, $keyboardprotocol, $MethodUsername, $MethodUsernameRemna] = \Faoxima\Admin\Keyboard\ProviderSettingsMenus::catalog($textbotlang, $_rx_pedit_styles);
        \Faoxima\Admin\Keyboard\ProviderMenuRendering::handle(
            __rxDecoded: $__rxDecoded ?? null,
            optionMarzban: $optionMarzban,
            rxAdminPanelBtn: $rxAdminPanelBtn,
            rxFxPricingRow: $rxFxPricingRow,
            textbotlang: $textbotlang ?? null
        );
    }
}
