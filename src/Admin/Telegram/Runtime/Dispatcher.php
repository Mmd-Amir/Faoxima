<?php

namespace Faoxima\Admin\Telegram\Runtime;

use Throwable;

/**
 * Telegram administration dispatcher: the ordered routing chain for administrator senders.
 * It runs only for administrator senders, after the user pipeline, from admin.php at global scope.
 */
final class Dispatcher
{
    /**
     * Authorises the sender and runs the administration routing chain.
     */
    public static function run()
    {
        // Global-scope bridge: this chain was written for the file scope of the webhook request, where config.php, botapi.php and
        // function.php create the request variables. Each name below is an alias of the global of the same name, so reads, writes
        // and by-reference stage arguments behave exactly as they did at file scope.
        $APIKEY = &$GLOBALS['APIKEY'];
        $CartManage = &$GLOBALS['CartManage'];
        $Exception_auto_cart_keyboard = &$GLOBALS['Exception_auto_cart_keyboard'];
        $Feature_status = &$GLOBALS['Feature_status'];
        $ManagePanel = &$GLOBALS['ManagePanel'];
        $MethodUsername = &$GLOBALS['MethodUsername'];
        $MethodUsernameRemna = &$GLOBALS['MethodUsernameRemna'];
        $Methodextend = &$GLOBALS['Methodextend'];
        $NowPaymentsManage = &$GLOBALS['NowPaymentsManage'];
        $Percent = &$GLOBALS['Percent'];
        $Response = &$GLOBALS['Response'];
        $SellDiscount = &$GLOBALS['SellDiscount'];
        $Startelegram = &$GLOBALS['Startelegram'];
        $Trust_auto_cart_keyboard = &$GLOBALS['Trust_auto_cart_keyboard'];
        $_toggle_notice = &$GLOBALS['_toggle_notice'];
        $abangateway = &$GLOBALS['abangateway'];
        $activeUsers = &$GLOBALS['activeUsers'];
        $active_users = &$GLOBALS['active_users'];
        $adminAction = &$GLOBALS['adminAction'];
        $adminChannelMenu = &$GLOBALS['adminChannelMenu'];
        $adminError = &$GLOBALS['adminError'];
        $adminExecution = &$GLOBALS['adminExecution'];
        $adminModuleRoot = &$GLOBALS['adminModuleRoot'];
        $adminPanelsMenu = &$GLOBALS['adminPanelsMenu'];
        $adminRouteMatched = &$GLOBALS['adminRouteMatched'];
        $adminStage = &$GLOBALS['adminStage'];
        $adminUsersMenu = &$GLOBALS['adminUsersMenu'];
        $admin_ids = &$GLOBALS['admin_ids'];
        $adminnumber = &$GLOBALS['adminnumber'];
        $adminrule = &$GLOBALS['adminrule'];
        $adminrulecheck = &$GLOBALS['adminrulecheck'];
        $affiliates = &$GLOBALS['affiliates'];
        $affiliatesAntiFraud = &$GLOBALS['affiliatesAntiFraud'];
        $atlaspay = &$GLOBALS['atlaspay'];
        $atlaspayMode = &$GLOBALS['atlaspayMode'];
        $autoconfirm_advanced_keyboard = &$GLOBALS['autoconfirm_advanced_keyboard'];
        $backadmin = &$GLOBALS['backadmin'];
        $backuser = &$GLOBALS['backuser'];
        $baseUrl = &$GLOBALS['baseUrl'];
        $blupal = &$GLOBALS['blupal'];
        $button = &$GLOBALS['button'];
        $buyreport = &$GLOBALS['buyreport'];
        $callback_query_id = &$GLOBALS['callback_query_id'];
        $caption = &$GLOBALS['caption'];
        $change_product = &$GLOBALS['change_product'];
        $channelkeyboard = &$GLOBALS['channelkeyboard'];
        $cmm = &$GLOBALS['cmm'];
        $code_panel = &$GLOBALS['code_panel'];
        $code_product = &$GLOBALS['code_product'];
        $codeproduct = &$GLOBALS['codeproduct'];
        $configedit = &$GLOBALS['configedit'];
        $connect = &$GLOBALS['connect'];
        $connectionResult = &$GLOBALS['connectionResult'];
        $cubepay = &$GLOBALS['cubepay'];
        $cur = &$GLOBALS['cur'];
        $cwd = &$GLOBALS['cwd'];
        $cwm = &$GLOBALS['cwm'];
        $data = &$GLOBALS['data'];
        $dataget = &$GLOBALS['dataget'];
        $datagetr = &$GLOBALS['datagetr'];
        $datain = &$GLOBALS['datain'];
        $dataoutput = &$GLOBALS['dataoutput'];
        $datatextbot = &$GLOBALS['datatextbot'];
        $datatime = &$GLOBALS['datatime'];
        $day = &$GLOBALS['day'];
        $departemanslist = &$GLOBALS['departemanslist'];
        $destination = &$GLOBALS['destination'];
        $document = &$GLOBALS['document'];
        $domainhosts = &$GLOBALS['domainhosts'];
        $domainhostsEscaped = &$GLOBALS['domainhostsEscaped'];
        $doneTxt = &$GLOBALS['doneTxt'];
        $editLinkMatches = &$GLOBALS['editLinkMatches'];
        $errorreport = &$GLOBALS['errorreport'];
        $expire = &$GLOBALS['expire'];
        $extend = &$GLOBALS['extend'];
        $fileid = &$GLOBALS['fileid'];
        $filename = &$GLOBALS['filename'];
        $first_name = &$GLOBALS['first_name'];
        $from_id = &$GLOBALS['from_id'];
        $getNameCustomKb = &$GLOBALS['getNameCustomKb'];
        $grp = &$GLOBALS['grp'];
        $helpedit = &$GLOBALS['helpedit'];
        $hideuserid = &$GLOBALS['hideuserid'];
        $id_invoice = &$GLOBALS['id_invoice'];
        $id_product = &$GLOBALS['id_product'];
        $id_user = &$GLOBALS['id_user'];
        $info_product = &$GLOBALS['info_product'];
        $inline_query_id = &$GLOBALS['inline_query_id'];
        $invoice = &$GLOBALS['invoice'];
        $ip_list = &$GLOBALS['ip_list'];
        $iplogin_unlimited = &$GLOBALS['iplogin_unlimited'];
        $iranpaykeyboard = &$GLOBALS['iranpaykeyboard'];
        $json_list_helpkey = &$GLOBALS['json_list_helpkey'];
        $json_list_marzban_panel = &$GLOBALS['json_list_marzban_panel'];
        $json_list_product_list_admin = &$GLOBALS['json_list_product_list_admin'];
        $kb = &$GLOBALS['kb'];
        $key = &$GLOBALS['key'];
        $keyboard = &$GLOBALS['keyboard'];
        $keyboard_Category_manage = &$GLOBALS['keyboard_Category_manage'];
        $keyboard_shop_manage = &$GLOBALS['keyboard_shop_manage'];
        $keyboard_stat = &$GLOBALS['keyboard_stat'];
        $keyboardadmin = &$GLOBALS['keyboardadmin'];
        $keyboardagent = &$GLOBALS['keyboardagent'];
        $keyboardchangelimit = &$GLOBALS['keyboardchangelimit'];
        $keyboardexportdata = &$GLOBALS['keyboardexportdata'];
        $keyboardhelpadmin = &$GLOBALS['keyboardhelpadmin'];
        $keyboardlistsnode = &$GLOBALS['keyboardlistsnode'];
        $keyboardprotocol = &$GLOBALS['keyboardprotocol'];
        $keyboardprotocollist = &$GLOBALS['keyboardprotocollist'];
        $keyboardtimereset = &$GLOBALS['keyboardtimereset'];
        $keyboardzarinpal = &$GLOBALS['keyboardzarinpal'];
        $label = &$GLOBALS['label'];
        $lastmessage = &$GLOBALS['lastmessage'];
        $lastupdate = &$GLOBALS['lastupdate'];
        $list = &$GLOBALS['list'];
        $list_card_remove = &$GLOBALS['list_card_remove'];
        $list_marzban_panel_edit_product = &$GLOBALS['list_marzban_panel_edit_product'];
        $listusers = &$GLOBALS['listusers'];
        $liveInvoice = &$GLOBALS['liveInvoice'];
        $location = &$GLOBALS['location'];
        $lottery = &$GLOBALS['lottery'];
        $marzban_list = &$GLOBALS['marzban_list'];
        $marzban_list_get = &$GLOBALS['marzban_list_get'];
        $message_id = &$GLOBALS['message_id'];
        $miniAppInstructionText = &$GLOBALS['miniAppInstructionText'];
        $name_product = &$GLOBALS['name_product'];
        $nameloc = &$GLOBALS['nameloc'];
        $namestatus = &$GLOBALS['namestatus'];
        $next_page = &$GLOBALS['next_page'];
        $nowpayment_setting_keyboard = &$GLOBALS['nowpayment_setting_keyboard'];
        $ok = &$GLOBALS['ok'];
        $okMsg = &$GLOBALS['okMsg'];
        $optionGuard = &$GLOBALS['optionGuard'];
        $optionJovad = &$GLOBALS['optionJovad'];
        $optionManualsale = &$GLOBALS['optionManualsale'];
        $optionMarzban = &$GLOBALS['optionMarzban'];
        $optionPasarGuard = &$GLOBALS['optionPasarGuard'];
        $optionRebecca = &$GLOBALS['optionRebecca'];
        $optionX_ui_single = &$GLOBALS['optionX_ui_single'];
        $option_remnawave = &$GLOBALS['option_remnawave'];
        $optionathmarzban = &$GLOBALS['optionathmarzban'];
        $optionathx_ui = &$GLOBALS['optionathx_ui'];
        $optionwg = &$GLOBALS['optionwg'];
        $otherreport = &$GLOBALS['otherreport'];
        $output = &$GLOBALS['output'];
        $page = &$GLOBALS['page'];
        $panel = &$GLOBALS['panel'];
        $pasarguardCredsMissing = &$GLOBALS['pasarguardCredsMissing'];
        $pasarguardTestRes = &$GLOBALS['pasarguardTestRes'];
        $payment = &$GLOBALS['payment'];
        $paymentreports = &$GLOBALS['paymentreports'];
        $pdo = &$GLOBALS['pdo'];
        $photo = &$GLOBALS['photo'];
        $photoid = &$GLOBALS['photoid'];
        $pricelast = &$GLOBALS['pricelast'];
        $product = &$GLOBALS['product'];
        $products = &$GLOBALS['products'];
        $protocoldata = &$GLOBALS['protocoldata'];
        $query = &$GLOBALS['query'];
        $randomString = &$GLOBALS['randomString'];
        $rawProcessing = &$GLOBALS['rawProcessing'];
        $rebeccaServiceLine = &$GLOBALS['rebeccaServiceLine'];
        $resolvedName = &$GLOBALS['resolvedName'];
        $response = &$GLOBALS['response'];
        $result = &$GLOBALS['result'];
        $results = &$GLOBALS['results'];
        $rialit = &$GLOBALS['rialit'];
        $row = &$GLOBALS['row'];
        $rows = &$GLOBALS['rows'];
        $rwSlotMatch = &$GLOBALS['rwSlotMatch'];
        $rxConfirmAlert = &$GLOBALS['rxConfirmAlert'];
        $rxGatewayLimitRoute = &$GLOBALS['rxGatewayLimitRoute'];
        $rxGiftBatch = &$GLOBALS['rxGiftBatch'];
        $rxK = &$GLOBALS['rxK'];
        $rxOptOrphanedBatch = &$GLOBALS['rxOptOrphanedBatch'];
        $rxOptScanCount = &$GLOBALS['rxOptScanCount'];
        $rxOptScanStmt = &$GLOBALS['rxOptScanStmt'];
        $rxOptTicketCount = &$GLOBALS['rxOptTicketCount'];
        $rxOptTicketStmt = &$GLOBALS['rxOptTicketStmt'];
        $rxPanelAlreadyLoadedForEditpanel = &$GLOBALS['rxPanelAlreadyLoadedForEditpanel'];
        $rxPemBase = &$GLOBALS['rxPemBase'];
        $rxPemButtons = &$GLOBALS['rxPemButtons'];
        $rxPemMode = &$GLOBALS['rxPemMode'];
        $rxPemRow = &$GLOBALS['rxPemRow'];
        $rxPemSettingsCb = &$GLOBALS['rxPemSettingsCb'];
        $rxPemSuccessMsg = &$GLOBALS['rxPemSuccessMsg'];
        $rxPemTotal = &$GLOBALS['rxPemTotal'];
        $rxRedisTestText = &$GLOBALS['rxRedisTestText'];
        $rxV = &$GLOBALS['rxV'];
        $rx_back_origin = &$GLOBALS['rx_back_origin'];
        $rx_hardcoded_nav = &$GLOBALS['rx_hardcoded_nav'];
        $rx_kb_json = &$GLOBALS['rx_kb_json'];
        $rx_kb_label = &$GLOBALS['rx_kb_label'];
        $rx_kb_value = &$GLOBALS['rx_kb_value'];
        $rx_kb_var = &$GLOBALS['rx_kb_var'];
        $rx_nav_key = &$GLOBALS['rx_nav_key'];
        $rx_pem_entry_step = &$GLOBALS['rx_pem_entry_step'];
        $rx_stripped_label = &$GLOBALS['rx_stripped_label'];
        $scanResult = &$GLOBALS['scanResult'];
        $serverStatusBlock = &$GLOBALS['serverStatusBlock'];
        $setting = &$GLOBALS['setting'];
        $setting_panel = &$GLOBALS['setting_panel'];
        $shopkeyboard = &$GLOBALS['shopkeyboard'];
        $status = &$GLOBALS['status'];
        $stmt = &$GLOBALS['stmt'];
        $sumvolume = &$GLOBALS['sumvolume'];
        $supportcenter = &$GLOBALS['supportcenter'];
        $tableName = &$GLOBALS['tableName'];
        $text = &$GLOBALS['text'];
        $text_expie_agent = &$GLOBALS['text_expie_agent'];
        $text_inline = &$GLOBALS['text_inline'];
        $text_panel_admin_login_template = &$GLOBALS['text_panel_admin_login_template'];
        $textadmin = &$GLOBALS['textadmin'];
        $textbot = &$GLOBALS['textbot'];
        $textbotlang = &$GLOBALS['textbotlang'];
        $textcart = &$GLOBALS['textcart'];
        $textday = &$GLOBALS['textday'];
        $textsetprotocol = &$GLOBALS['textsetprotocol'];
        $toggle_msg = &$GLOBALS['toggle_msg'];
        $token = &$GLOBALS['token'];
        $tonpay = &$GLOBALS['tonpay'];
        $tonpayMode = &$GLOBALS['tonpayMode'];
        $totalUsers = &$GLOBALS['totalUsers'];
        $trnado = &$GLOBALS['trnado'];
        $tronnowpayments = &$GLOBALS['tronnowpayments'];
        $update = &$GLOBALS['update'];
        $url = &$GLOBALS['url'];
        $user = &$GLOBALS['user'];
        $userdate = &$GLOBALS['userdate'];
        $userjoin = &$GLOBALS['userjoin'];
        $username = &$GLOBALS['username'];
        $usernamebot = &$GLOBALS['usernamebot'];
        $usernameconfig = &$GLOBALS['usernameconfig'];
        $userslist = &$GLOBALS['userslist'];
        $value = &$GLOBALS['value'];
        $valuenew = &$GLOBALS['valuenew'];
        $values = &$GLOBALS['values'];
        $variza = &$GLOBALS['variza'];
        $version = &$GLOBALS['version'];
        $video = &$GLOBALS['video'];
        $videoid = &$GLOBALS['videoid'];
        $wheelkeyboard = &$GLOBALS['wheelkeyboard'];
        $wl_text = &$GLOBALS['wl_text'];

        $adminExecution = \Faoxima\Admin\Security\TelegramAccess::forSender($from_id ?? null, $admin_ids ?? null);
        if ($adminExecution === null) {
            return;
        }

        $adminModuleRoot = REFACTORED_LEGACY_ROOT . '/src/Admin';
        try {
            \Faoxima\Admin\Telegram\Conversation\Navigation::handle(
                CartManage: $CartManage ?? null,
                Feature_status: $Feature_status ?? null,
                NowPaymentsManage: $NowPaymentsManage ?? null,
                Startelegram: $Startelegram ?? null,
                abangateway: $abangateway ?? null,
                adminChannelMenu: $adminChannelMenu ?? null,
                adminExecution: $adminExecution ?? null,
                adminPanelsMenu: $adminPanelsMenu ?? null,
                adminUsersMenu: $adminUsersMenu ?? null,
                admin_ids: $admin_ids ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                callback_query_id: $callback_query_id ?? null,
                channelkeyboard: $channelkeyboard ?? null,
                cubepay: $cubepay ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                iranpaykeyboard: $iranpaykeyboard ?? null,
                keyboard_Category_manage: $keyboard_Category_manage ?? null,
                keyboard_shop_manage: $keyboard_shop_manage ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardhelpadmin: $keyboardhelpadmin ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                pdo: $pdo ?? null,
                rialit: $rialit ?? null,
                rx_hardcoded_nav: $rx_hardcoded_nav ?? null,
                rx_kb_json: $rx_kb_json ?? null,
                rx_kb_label: $rx_kb_label ?? null,
                rx_kb_value: $rx_kb_value ?? null,
                rx_kb_var: $rx_kb_var ?? null,
                rx_nav_key: $rx_nav_key ?? null,
                rx_pem_entry_step: $rx_pem_entry_step,
                rx_stripped_label: $rx_stripped_label ?? null,
                setting_panel: $setting_panel ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                supportcenter: $supportcenter ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                trnado: $trnado ?? null,
                tronnowpayments: $tronnowpayments ?? null,
                user: $user,
                variza: $variza ?? null
            );
            \Faoxima\Core\ProtectedRuntime\Bootstrap::run();

            $domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (\Faoxima\Admin\Telegram\Conversation\CallbackNormalization::handle(
                adminExecution: $adminExecution ?? null,
                admin_ids: $admin_ids ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                rx_back_origin: $rx_back_origin,
                text: $text,
                textbotlang: $textbotlang ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Bootstrap\PreDispatch::handle(
                adminExecution: $adminExecution ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                rx_pem_entry_step: $rx_pem_entry_step,
                text: $text ?? null,
                update: $update ?? null,
                user: $user
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\DashboardAndNavigation::handle(
                CartManage: $CartManage ?? null,
                NowPaymentsManage: $NowPaymentsManage ?? null,
                Startelegram: $Startelegram ?? null,
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminChannelMenu: $adminChannelMenu ?? null,
                adminExecution: $adminExecution ?? null,
                adminPanelsMenu: $adminPanelsMenu ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminUsersMenu: $adminUsersMenu ?? null,
                admin_ids: $admin_ids ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                change_product: $change_product ?? null,
                channelkeyboard: $channelkeyboard,
                cubepay: $cubepay ?? null,
                datain: $datain ?? null,
                domainhostsEscaped: $domainhostsEscaped ?? null,
                from_id: $from_id ?? null,
                iranpaykeyboard: $iranpaykeyboard ?? null,
                keyboard_Category_manage: $keyboard_Category_manage ?? null,
                keyboard_shop_manage: $keyboard_shop_manage ?? null,
                keyboard_stat: $keyboard_stat ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardhelpadmin: $keyboardhelpadmin ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                message_id: $message_id ?? null,
                miniAppInstructionText: $miniAppInstructionText ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                rawProcessing: $rawProcessing,
                resolvedName: $resolvedName ?? null,
                rialit: $rialit ?? null,
                rx_back_origin: $rx_back_origin ?? null,
                rx_pem_entry_step: $rx_pem_entry_step ?? null,
                setting: $setting ?? null,
                setting_panel: $setting_panel ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                text: $text ?? null,
                text_panel_admin_login_template: $text_panel_admin_login_template ?? null,
                textadmin: $textadmin ?? null,
                textbot: $textbot ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                trnado: $trnado ?? null,
                tronnowpayments: $tronnowpayments ?? null,
                user: $user,
                variza: $variza ?? null,
                version: $version ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ChannelManagement::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                channelkeyboard: $channelkeyboard,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                kb: $kb,
                pdo: $pdo ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\SettingsAndReports::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrule: $adminrule ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                channelkeyboard: $channelkeyboard,
                dataget: $dataget,
                datain: $datain ?? null,
                datatextbot: $datatextbot ?? null,
                editLinkMatches: $editLinkMatches ?? null,
                keyboard_stat: $keyboard_stat ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                otherreport: $otherreport ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReportsAndTicketDepartments::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboard_stat: $keyboard_stat ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReferralAndPanelSetup::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                affiliates: $affiliates ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                connectionResult: $connectionResult,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderCredentials::handle(
                MethodUsername: $MethodUsername,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                connectionResult: $connectionResult,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                randomString: $randomString,
                status: $status,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user,
                value: $value
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderTypeSelection::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminPanelsMenu: $adminPanelsMenu ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                connectionResult: $connectionResult,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderTypeConfirmation::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                stmt: $stmt,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BroadcastAudience::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                result: $result,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BroadcastQueue::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                data: $data,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id,
                panel: $panel,
                pdo: $pdo ?? null,
                stmt: $stmt,
                text: $text,
                textday: $textday ?? null,
                user: $user ?? null,
                userslist: $userslist ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\TextAndHelpSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                datain: $datain ?? null,
                datatextbot: $datatextbot ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                rxPemBase: $rxPemBase ?? null,
                rxPemButtons: $rxPemButtons ?? null,
                rxPemMode: $rxPemMode ?? null,
                rxPemRow: $rxPemRow,
                rxPemSettingsCb: $rxPemSettingsCb ?? null,
                rxPemSuccessMsg: $rxPemSuccessMsg ?? null,
                text: $text ?? null,
                textbot: $textbot ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\PremiumEmojiAndHelp::handle(
                Response: $Response,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                caption: $caption ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                document: $document ?? null,
                fileid: $fileid ?? null,
                from_id: $from_id ?? null,
                json_list_helpkey: $json_list_helpkey ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardhelpadmin: $keyboardhelpadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\TicketRepliesAndDiagnostics::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                caption: $caption ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                rxRedisTestText: $rxRedisTestText ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AntiSpamSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id,
                pdo: $pdo,
                setting: $setting,
                text: $text ?? null,
                textbotlang: $textbotlang,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReportRouting::handle(
                adminAction: $adminAction,
                adminChannelMenu: $adminChannelMenu ?? null,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboard_shop_manage: $keyboard_shop_manage ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardtimereset: $keyboardtimereset ?? null,
                name_product: $name_product ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                user: $user
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\UserNotesAndReceipts::handle(
                adminAction: $adminAction,
                adminChannelMenu: $adminChannelMenu ?? null,
                adminExecution: $adminExecution ?? null,
                adminPanelsMenu: $adminPanelsMenu ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminUsersMenu: $adminUsersMenu ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                randomString: $randomString,
                rxConfirmAlert: $rxConfirmAlert ?? null,
                setting_panel: $setting_panel ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                supportcenter: $supportcenter ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReceiptRejectionAndInventory::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                paymentreports: $paymentreports ?? null,
                setting: $setting ?? null,
                text: $text ?? null,
                text_inline: $text_inline ?? null,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ManualInventoryAndProducts::handle(
                Response: $Response,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                change_product: $change_product ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_product: $id_product,
                info_product: $info_product,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                json_list_product_list_admin: $json_list_product_list_admin,
                list_marzban_panel_edit_product: $list_marzban_panel_edit_product ?? null,
                location: $location,
                message_id: $message_id ?? null,
                name_product: $name_product ?? null,
                panel: $panel,
                paymentreports: $paymentreports ?? null,
                pdo: $pdo ?? null,
                product: $product,
                row: $row,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProductAudiencesAndUserControls::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                data: $data,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                grp: $grp,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardagent: $keyboardagent,
                keyboardtimereset: $keyboardtimereset ?? null,
                message_id: $message_id,
                panel: $panel,
                pdo: $pdo ?? null,
                product: $product,
                rxGiftBatch: $rxGiftBatch ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BalanceAndCatalogManagement::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                lastmessage: $lastmessage ?? null,
                message_id: $message_id ?? null,
                paymentreports: $paymentreports ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                sumvolume: $sumvolume ?? null,
                text: $text ?? null,
                text_expie_agent: $text_expie_agent ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user,
                userjoin: $userjoin,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\GiftCodesAndProviderSettings::handle(
                CartManage: $CartManage ?? null,
                MethodUsername: $MethodUsername ?? null,
                MethodUsernameRemna: $MethodUsernameRemna ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                getNameCustomKb: $getNameCustomKb ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardprotocollist: $keyboardprotocollist ?? null,
                optionJovad: $optionJovad ?? null,
                optionMarzban: $optionMarzban ?? null,
                pdo: $pdo ?? null,
                protocoldata: $protocoldata ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                status: $status,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\PaymentCards::handle(
                CartManage: $CartManage ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                pdo: $pdo ?? null,
                stmt: $stmt,
                text: $text ?? null,
                update: $update ?? null,
                user: $user ?? null,
                wl_text: $wl_text ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CardEditingAndPanelOptions::handle(
                NowPaymentsManage: $NowPaymentsManage ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                pdo: $pdo ?? null,
                row: $row,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                textcart: $textcart,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderAdministrationCompletion::handle(
                activeUsers: $activeUsers ?? null,
                active_users: $active_users ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                from_id: $from_id ?? null,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                marzban_list_get: $marzban_list_get,
                optionGuard: $optionGuard ?? null,
                optionJovad: $optionJovad ?? null,
                optionManualsale: $optionManualsale ?? null,
                optionMarzban: $optionMarzban ?? null,
                optionPasarGuard: $optionPasarGuard ?? null,
                optionRebecca: $optionRebecca ?? null,
                optionX_ui_single: $optionX_ui_single ?? null,
                option_remnawave: $option_remnawave ?? null,
                optionwg: $optionwg ?? null,
                pasarguardCredsMissing: $pasarguardCredsMissing ?? null,
                pasarguardTestRes: $pasarguardTestRes ?? null,
                pdo: $pdo ?? null,
                rebeccaServiceLine: $rebeccaServiceLine ?? null,
                serverStatusBlock: $serverStatusBlock ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                totalUsers: $totalUsers,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Conversation\PanelState::handle(
                adminExecution: $adminExecution ?? null,
                rawProcessing: $rawProcessing,
                rxK: $rxK ?? null,
                rxV: $rxV ?? null,
                user: $user
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\PanelIdentityAndProviderOptions::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                data: $data,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                list: $list,
                marzban_list: $marzban_list ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BulkRenewalAndProviderCapacity::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\InboundAndProviderAccess::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                baseUrl: $baseUrl,
                from_id: $from_id ?? null,
                optionX_ui_single: $optionX_ui_single ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\GuardProviderManagement::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                baseUrl: $baseUrl,
                callback_query_id: $callback_query_id ?? null,
                connectionResult: $connectionResult,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardprotocol: $keyboardprotocol ?? null,
                message_id: $message_id ?? null,
                panel: $panel,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderApiModeAndUserSearch::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminPanelsMenu: $adminPanelsMenu ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                next_page: $next_page,
                page: $page,
                pdo: $pdo ?? null,
                result: $result,
                row: $row,
                rows: $rows,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\UserDirectoryAndCredits::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                invoice: $invoice,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                next_page: $next_page,
                page: $page,
                paymentreports: $paymentreports ?? null,
                pdo: $pdo ?? null,
                randomString: $randomString,
                result: $result,
                row: $row,
                rows: $rows,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BalanceDebitsAndUserStatus::handle(
                Response: $Response,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                invoice: $invoice,
                keyboard: $keyboard ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                paymentreports: $paymentreports ?? null,
                pdo: $pdo ?? null,
                randomString: $randomString,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AccountFlagsAndDiscountStart::handle(
                ManagePanel: $ManagePanel ?? null,
                Response: $Response,
                _toggle_notice: $_toggle_notice ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                info_product: $info_product,
                keyboard: $keyboard ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                text: $text,
                text_inline: $text_inline ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null,
                username: $username
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\DiscountsAndReferralConfiguration::handle(
                CartManage: $CartManage ?? null,
                SellDiscount: $SellDiscount ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                affiliates: $affiliates ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                code_product: $code_product,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                datatextbot: $datatextbot ?? null,
                from_id: $from_id ?? null,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                json_list_product_list_admin: $json_list_product_list_admin ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                optionMarzban: $optionMarzban ?? null,
                panel: $panel,
                paymentreports: $paymentreports ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                product: $product,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text ?? null,
                text_inline: $text_inline ?? null,
                textbotlang: $textbotlang ?? null,
                textcart: $textcart,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null,
                usernamebot: $usernamebot ?? null,
                values: $values
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AffiliatePayoutAndReports::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                affiliates: $affiliates ?? null,
                affiliatesAntiFraud: $affiliatesAntiFraud ?? null,
                backadmin: $backadmin ?? null,
                datain: $datain ?? null,
                datatextbot: $datatextbot ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\WalletAndFinanceSettings::handle(
                ManagePanel: $ManagePanel ?? null,
                Percent: $Percent,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                codeproduct: $codeproduct,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                datatime: $datatime,
                day: $day,
                extend: $extend,
                from_id: $from_id ?? null,
                grp: $grp,
                id_user: $id_user,
                keyboardadmin: $keyboardadmin ?? null,
                lastupdate: $lastupdate,
                message_id: $message_id ?? null,
                namestatus: $namestatus,
                output: $output,
                pdo: $pdo ?? null,
                row: $row,
                status: $status,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                usernameconfig: $usernameconfig
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ShopFeaturesAndPaymentLimits::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                setting: $setting,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                value: $value,
                valuenew: $valuenew
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ExportsAndOperationalSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                backuser: $backuser ?? null,
                callback_query_id: $callback_query_id ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                filename: $filename,
                from_id: $from_id ?? null,
                id_invoice: $id_invoice,
                invoice: $invoice,
                keyboardexportdata: $keyboardexportdata ?? null,
                result: $result,
                row: $row,
                setting: $setting ?? null,
                setting_panel: $setting_panel ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null,
                value: $value
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AgentRequestsAndServiceAdministration::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_invoice: $id_invoice,
                invoice: $invoice,
                keyboardadmin: $keyboardadmin ?? null,
                marzban_list_get: $marzban_list_get,
                nameloc: $nameloc,
                otherreport: $otherreport ?? null,
                pdo: $pdo ?? null,
                pricelast: $pricelast,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ServiceRefunds::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                otherreport: $otherreport ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\RefundCompletionAndTimeouts::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                otherreport: $otherreport ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                setting_panel: $setting_panel ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Conversation\CustomDuration::handle($text, $datain, $user, $from_id, $adminrulecheck, $callback_query_id, $update, $pdo)) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Support\OptimizerFunctions::handle(
                adminExecution: $adminExecution ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\WorkerSettingsAndCatalogControls::handle(
                CartManage: $CartManage ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                admin_ids: $admin_ids ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                helpedit: $helpedit ?? null,
                json_list_helpkey: $json_list_helpkey ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                setting: $setting ?? null,
                setting_panel: $setting_panel ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\RenewalOptionsAndWebManagers::handle(
                Methodextend: $Methodextend ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminnumber: $adminnumber ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                datain: $datain ?? null,
                domainhosts: $domainhosts ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                token: $token,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\WebAccessAndReceiptReview::handle(
                CartManage: $CartManage ?? null,
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                backuser: $backuser ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                datatextbot: $datatextbot,
                errorreport: $errorreport ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user,
                info_product: $info_product,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                json_list_product_list_admin: $json_list_product_list_admin ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                list_card_remove: $list_card_remove ?? null,
                marzban_list_get: $marzban_list_get,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                randomString: $randomString,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReceiptDecisionAndUserManagement::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                callback_query_id: $callback_query_id ?? null,
                datagetr: $datagetr,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user,
                keyboardadmin: $keyboardadmin ?? null,
                label: $label,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                textbotlang: $textbotlang ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AgentStatusAndPaymentSettings::handle(
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                callback_query_id: $callback_query_id ?? null,
                cubepay: $cubepay ?? null,
                datagetr: $datagetr,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user,
                label: $label,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                rialit: $rialit ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                tonpayMode: $tonpayMode ?? null,
                trnado: $trnado ?? null,
                user: $user ?? null,
                variza: $variza ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\RialitAndGatewayCredentials::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                atlaspayMode: $atlaspayMode ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                caption: $caption ?? null,
                data: $data,
                datain: $datain ?? null,
                domainhosts: $domainhosts ?? null,
                from_id: $from_id ?? null,
                list: $list,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                result: $result,
                rialit: $rialit ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                url: $url,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\TetraminatorSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                caption: $caption ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ProviderFeaturesAndOwnerAccess::handle(
                CartManage: $CartManage ?? null,
                NowPaymentsManage: $NowPaymentsManage ?? null,
                Response: $Response,
                Startelegram: $Startelegram ?? null,
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminnumber: $adminnumber ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                button: $button,
                callback_query_id: $callback_query_id ?? null,
                cubepay: $cubepay ?? null,
                datain: $datain ?? null,
                domainhosts: $domainhosts ?? null,
                from_id: $from_id ?? null,
                iranpaykeyboard: $iranpaykeyboard ?? null,
                keyboardprotocol: $keyboardprotocol ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                message_id: $message_id ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                optionMarzban: $optionMarzban ?? null,
                pdo: $pdo ?? null,
                tableName: $tableName,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                trnado: $trnado ?? null,
                tronnowpayments: $tronnowpayments ?? null,
                update: $update ?? null,
                user: $user ?? null,
                variza: $variza ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\OptimizerAndCronReports::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                liveInvoice: $liveInvoice ?? null,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                rxOptOrphanedBatch: $rxOptOrphanedBatch ?? null,
                rxOptScanCount: $rxOptScanCount ?? null,
                rxOptScanStmt: $rxOptScanStmt ?? null,
                rxOptTicketCount: $rxOptTicketCount ?? null,
                rxOptTicketStmt: $rxOptTicketStmt ?? null,
                scanResult: $scanResult ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CronAndServiceLimits::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminnumber: $adminnumber ?? null,
                backadmin: $backadmin ?? null,
                buyreport: $buyreport ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                dataoutput: $dataoutput,
                datatextbot: $datatextbot,
                errorreport: $errorreport ?? null,
                expire: $expire,
                from_id: $from_id ?? null,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboardlistsnode: $keyboardlistsnode ?? null,
                message_id: $message_id ?? null,
                optionGuard: $optionGuard ?? null,
                optionJovad: $optionJovad ?? null,
                optionManualsale: $optionManualsale ?? null,
                optionPasarGuard: $optionPasarGuard ?? null,
                optionRebecca: $optionRebecca ?? null,
                option_remnawave: $option_remnawave ?? null,
                optionathmarzban: $optionathmarzban ?? null,
                optionathx_ui: $optionathx_ui ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                randomString: $randomString,
                result: $result,
                setting: $setting ?? null,
                setting_panel: $setting_panel ?? null,
                sumvolume: $sumvolume,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                username: $username ?? null,
                usernameconfig: $usernameconfig
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CoefficientsAndWalletDefaults::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                value: $value,
                valuenew: $valuenew
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CashbackSettings::handle(
                CartManage: $CartManage ?? null,
                NowPaymentsManage: $NowPaymentsManage ?? null,
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                callback_query_id: $callback_query_id ?? null,
                cubepay: $cubepay ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                message_id: $message_id ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                rialit: $rialit ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                trnado: $trnado ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\NetworkAndServiceSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                button: $button,
                from_id: $from_id ?? null,
                json_list_product_list_admin: $json_list_product_list_admin,
                keyboardadmin: $keyboardadmin ?? null,
                optionManualsale: $optionManualsale ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                product: $product,
                row: $row,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AdditionalTimeAndGatewayBounds::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                configedit: $configedit ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                grp: $grp,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                optionManualsale: $optionManualsale ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                product: $product,
                products: $products,
                result: $result,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CardAndCryptoSettings::handle(
                CartManage: $CartManage ?? null,
                NowPaymentsManage: $NowPaymentsManage ?? null,
                Startelegram: $Startelegram ?? null,
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                callback_query_id: $callback_query_id ?? null,
                cmm: $cmm,
                cubepay: $cubepay ?? null,
                cur: $cur,
                cwd: $cwd,
                cwm: $cwm,
                datain: $datain ?? null,
                doneTxt: $doneTxt ?? null,
                from_id: $from_id ?? null,
                iranpaykeyboard: $iranpaykeyboard ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardzarinpal: $keyboardzarinpal ?? null,
                label: $label,
                message_id: $message_id ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                ok: $ok,
                okMsg: $okMsg,
                row: $row,
                rxGatewayLimitRoute: $rxGatewayLimitRoute,
                setting: $setting ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                trnado: $trnado ?? null,
                tronnowpayments: $tronnowpayments ?? null,
                user: $user ?? null,
                variza: $variza ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CryptoWalletConfiguration::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                backuser: $backuser ?? null,
                cur: $cur,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                iranpaykeyboard: $iranpaykeyboard ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                label: $label,
                message_id: $message_id ?? null,
                ok: $ok,
                okMsg: $okMsg ?? null,
                row: $row,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                trnado: $trnado ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ExtraServicesAndHelpSettings::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                backuser: $backuser ?? null,
                departemanslist: $departemanslist ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                supportcenter: $supportcenter ?? null,
                text: $text,
                textbotlang: $textbotlang ?? null,
                textcart: $textcart,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\SupportAndGatewayGuides::handle(
                CartManage: $CartManage ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                caption: $caption ?? null,
                data: $data,
                from_id: $from_id ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                supportcenter: $supportcenter ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                tonpay: $tonpay ?? null,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\GatewayGuidesAndPricing::handle(
                CartManage: $CartManage ?? null,
                abangateway: $abangateway ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                atlaspay: $atlaspay ?? null,
                backadmin: $backadmin ?? null,
                blupal: $blupal ?? null,
                caption: $caption ?? null,
                cubepay: $cubepay ?? null,
                data: $data,
                from_id: $from_id ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                text: $text ?? null,
                user: $user ?? null,
                variza: $variza ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\AgentSettingsAndServerMaintenance::handle(
                APIKEY: $APIKEY ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                key: $key,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                optionMarzban: $optionMarzban ?? null,
                optionPasarGuard: $optionPasarGuard ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                response: $response,
                setting_panel: $setting_panel ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                textsetprotocol: $textsetprotocol ?? null,
                update: $update ?? null,
                user: $user ?? null,
                value: $value
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\RenewalAndPanelVisibility::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminnumber: $adminnumber ?? null,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                data: $data,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                hideuserid: $hideuserid ?? null,
                key: $key,
                keyboardadmin: $keyboardadmin ?? null,
                lottery: $lottery ?? null,
                message_id: $message_id ?? null,
                payment: $payment,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                wheelkeyboard: $wheelkeyboard ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\ReceiptMaintenance::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                ip_list: $ip_list ?? null,
                iplogin_unlimited: $iplogin_unlimited ?? null,
                key: $key,
                marzban_list_get: $marzban_list_get,
                message_id: $message_id ?? null,
                pdo: $pdo ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                toggle_msg: $toggle_msg ?? null,
                update: $update ?? null,
                user: $user ?? null,
                value: $value
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\RenewalAdminAndDeletion::handle(
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                admin_ids: $admin_ids ?? null,
                callback_query_id: $callback_query_id ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_invoice: $id_invoice,
                pdo: $pdo ?? null,
                stmt: $stmt,
                text: $text ?? null,
                update: $update ?? null,
                user: $user ?? null,
                usernameconfig: $usernameconfig
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\InlineInvoiceAndResellerTools::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                datagetr: $datagetr,
                datain: $datain ?? null,
                datatime: $datatime,
                destination: $destination,
                from_id: $from_id ?? null,
                id_user: $id_user,
                inline_query_id: $inline_query_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                next_page: $next_page,
                page: $page,
                pdo: $pdo ?? null,
                query: $query ?? null,
                result: $result,
                results: $results,
                row: $row,
                rows: $rows,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                userdate: $userdate
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\WarningsInfoCardsAndProviderFeatures::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                code_panel: $code_panel,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                message_id: $message_id ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                rxPanelAlreadyLoadedForEditpanel: $rxPanelAlreadyLoadedForEditpanel ?? null,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                value: $value,
                valuenew: $valuenew
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\PremiumEmojiPacks::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                pdo: $pdo ?? null,
                rxPemBase: $rxPemBase,
                rxPemTotal: $rxPemTotal ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\PremiumEmojiEditingAndStars::handle(
                Startelegram: $Startelegram ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                caption: $caption ?? null,
                data: $data,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                pdo: $pdo ?? null,
                photo: $photo ?? null,
                photoid: $photoid ?? null,
                rxPemRow: $rxPemRow ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null,
                video: $video ?? null,
                videoid: $videoid ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\LocationLimitsAndBulkGifts::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                backadmin: $backadmin ?? null,
                datagetr: $datagetr,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboardadmin: $keyboardadmin ?? null,
                keyboardchangelimit: $keyboardchangelimit ?? null,
                message_id: $message_id ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                rwSlotMatch: $rwSlotMatch,
                setting: $setting ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                userdate: $userdate ?? null,
                userslist: $userslist,
                value: $value
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\BulkGiftExecutionAndExports::handle(
                CartManage: $CartManage ?? null,
                ManagePanel: $ManagePanel ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                dataget: $dataget,
                datagetr: $datagetr,
                datain: $datain ?? null,
                dataoutput: $dataoutput,
                filename: $filename,
                first_name: $first_name ?? null,
                from_id: $from_id ?? null,
                id_invoice: $id_invoice,
                id_user: $id_user,
                keyboardadmin: $keyboardadmin ?? null,
                list: $list,
                listusers: $listusers,
                marzban_list_get: $marzban_list_get,
                message_id: $message_id,
                nameloc: $nameloc,
                otherreport: $otherreport ?? null,
                pdo: $pdo ?? null,
                setting: $setting ?? null,
                shopkeyboard: $shopkeyboard ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null,
                userdate: $userdate,
                userslist: $userslist
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\CategoriesAndServiceMaintenance::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                connect: $connect ?? null,
                dataget: $dataget,
                datain: $datain ?? null,
                domainhosts: $domainhosts ?? null,
                from_id: $from_id ?? null,
                id_user: $id_user,
                json_list_marzban_panel: $json_list_marzban_panel ?? null,
                keyboard_Category_manage: $keyboard_Category_manage ?? null,
                keyboard_shop_manage: $keyboard_shop_manage ?? null,
                message_id: $message_id ?? null,
                next_page: $next_page,
                page: $page,
                pdo: $pdo ?? null,
                result: $result,
                row: $row,
                shopkeyboard: $shopkeyboard ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\NowPaymentsAndCardAutoApproval::handle(
                CartManage: $CartManage ?? null,
                Exception_auto_cart_keyboard: $Exception_auto_cart_keyboard ?? null,
                Response: $Response,
                Trust_auto_cart_keyboard: $Trust_auto_cart_keyboard ?? null,
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                autoconfirm_advanced_keyboard: $autoconfirm_advanced_keyboard ?? null,
                backadmin: $backadmin ?? null,
                code_product: $code_product,
                dataget: $dataget,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                message_id: $message_id ?? null,
                nowpayment_setting_keyboard: $nowpayment_setting_keyboard ?? null,
                panel: $panel,
                pdo: $pdo ?? null,
                product: $product,
                setting: $setting ?? null,
                stmt: $stmt,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
            if (\Faoxima\Admin\Telegram\Routing\FxPricing::handle(
                adminAction: $adminAction,
                adminExecution: $adminExecution ?? null,
                adminRouteMatched: $adminRouteMatched,
                adminrulecheck: $adminrulecheck ?? null,
                backadmin: $backadmin ?? null,
                callback_query_id: $callback_query_id ?? null,
                datain: $datain ?? null,
                from_id: $from_id ?? null,
                text: $text ?? null,
                textbotlang: $textbotlang ?? null,
                update: $update ?? null,
                user: $user ?? null
            ) !== true) {
                return;
            }
        } catch (Throwable $adminError) {
            \Faoxima\Core\Logging\RuntimeLog::throwable('admin', $adminError);
            throw $adminError;
        } finally {
            unset($adminStage, $adminModuleRoot, $adminRouteMatched, $adminAction, $adminExecution, $GLOBALS['adminStage'], $GLOBALS['adminModuleRoot'], $GLOBALS['adminRouteMatched'], $GLOBALS['adminAction'], $GLOBALS['adminExecution']);
        }
    }
}
