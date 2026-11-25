<?php

namespace Faoxima\Telegram\Runtime;

/**
 * Telegram webhook request pipeline: the ordered stage chain for one update.
 * run() returns true when every stage let the request continue and anything else when a stage ended it (the old `return`).
 * The configuration, transport and function libraries are loaded by index.php at global scope before this runs.
 */
final class Webhook
{
    /**
     * Runs the Telegram stage chain for the current update.
     */
    public static function run()
    {
        // Global-scope bridge: this chain was written for the file scope of the webhook request, where config.php, botapi.php and
        // function.php create the request variables. Each name below is an alias of the global of the same name, so reads, writes
        // and by-reference stage arguments behave exactly as they did at file scope.
        $Chat_type = &$GLOBALS['Chat_type'];
        $ManagePanel = &$GLOBALS['ManagePanel'];
        $Percent = &$GLOBALS['Percent'];
        $Response = &$GLOBALS['Response'];
        $_cc_insert_err = &$GLOBALS['_cc_insert_err'];
        $_rx_acc_styles = &$GLOBALS['_rx_acc_styles'];
        $_rx_nav_styles = &$GLOBALS['_rx_nav_styles'];
        $_rx_payrcpt_styles = &$GLOBALS['_rx_payrcpt_styles'];
        $_rx_svc_styles = &$GLOBALS['_rx_svc_styles'];
        $admin_ids = &$GLOBALS['admin_ids'];
        $admin_ids_str = &$GLOBALS['admin_ids_str'];
        $adminrulecheck = &$GLOBALS['adminrulecheck'];
        $affiliates = &$GLOBALS['affiliates'];
        $apcMatch = &$GLOBALS['apcMatch'];
        $back = &$GLOBALS['back'];
        $backuser = &$GLOBALS['backuser'];
        $before = &$GLOBALS['before'];
        $btn = &$GLOBALS['btn'];
        $buyreport = &$GLOBALS['buyreport'];
        $callback_query_id = &$GLOBALS['callback_query_id'];
        $caption = &$GLOBALS['caption'];
        $chat_member = &$GLOBALS['chat_member'];
        $codeproduct = &$GLOBALS['codeproduct'];
        $configDelivery = &$GLOBALS['configDelivery'];
        $configs = &$GLOBALS['configs'];
        $confrimrolls = &$GLOBALS['confrimrolls'];
        $connect = &$GLOBALS['connect'];
        $contact_id = &$GLOBALS['contact_id'];
        $cur = &$GLOBALS['cur'];
        $data = &$GLOBALS['data'];
        $data_limit = &$GLOBALS['data_limit'];
        $dataget = &$GLOBALS['dataget'];
        $datain = &$GLOBALS['datain'];
        $datakeyboard = &$GLOBALS['datakeyboard'];
        $dataoutput = &$GLOBALS['dataoutput'];
        $datatextbot = &$GLOBALS['datatextbot'];
        $day = &$GLOBALS['day'];
        $diceResponse = &$GLOBALS['diceResponse'];
        $domainhosts = &$GLOBALS['domainhosts'];
        $error = &$GLOBALS['error'];
        $errorreport = &$GLOBALS['errorreport'];
        $explorerHint = &$GLOBALS['explorerHint'];
        $extend = &$GLOBALS['extend'];
        $first_name = &$GLOBALS['first_name'];
        $from_id = &$GLOBALS['from_id'];
        $fund = &$GLOBALS['fund'];
        $hashHint = &$GLOBALS['hashHint'];
        $id_invoice = &$GLOBALS['id_invoice'];
        $info_product = &$GLOBALS['info_product'];
        $invoice = &$GLOBALS['invoice'];
        $invoiceMsg = &$GLOBALS['invoiceMsg'];
        $is_bot = &$GLOBALS['is_bot'];
        $json_list_helpـcategory = &$GLOBALS['json_list_helpـcategory'];
        $keyboard = &$GLOBALS['keyboard'];
        $keyboard_buy = &$GLOBALS['keyboard_buy'];
        $keyboardagent = &$GLOBALS['keyboardagent'];
        $label = &$GLOBALS['label'];
        $last_name = &$GLOBALS['last_name'];
        $lastupdate = &$GLOBALS['lastupdate'];
        $list_departman = &$GLOBALS['list_departman'];
        $list_marzban_panel_user = &$GLOBALS['list_marzban_panel_user'];
        $list_marzban_panel_userom = &$GLOBALS['list_marzban_panel_userom'];
        $list_marzban_panel_userschange = &$GLOBALS['list_marzban_panel_userschange'];
        $loc = &$GLOBALS['loc'];
        $location = &$GLOBALS['location'];
        $manualDeliveryResult = &$GLOBALS['manualDeliveryResult'];
        $marzban_list_get = &$GLOBALS['marzban_list_get'];
        $message_id = &$GLOBALS['message_id'];
        $mrow = &$GLOBALS['mrow'];
        $namekeyboard = &$GLOBALS['namekeyboard'];
        $nameloc = &$GLOBALS['nameloc'];
        $namestatus = &$GLOBALS['namestatus'];
        $next_page = &$GLOBALS['next_page'];
        $numberphone = &$GLOBALS['numberphone'];
        $otherreport = &$GLOBALS['otherreport'];
        $otherservice = &$GLOBALS['otherservice'];
        $output = &$GLOBALS['output'];
        $page = &$GLOBALS['page'];
        $pages = &$GLOBALS['pages'];
        $payload = &$GLOBALS['payload'];
        $payment = &$GLOBALS['payment'];
        $paymentom = &$GLOBALS['paymentom'];
        $paymentreports = &$GLOBALS['paymentreports'];
        $pdo = &$GLOBALS['pdo'];
        $photo = &$GLOBALS['photo'];
        $photoid = &$GLOBALS['photoid'];
        $porsantreport = &$GLOBALS['porsantreport'];
        $previous_page = &$GLOBALS['previous_page'];
        $pricelast = &$GLOBALS['pricelast'];
        $pricelastextend = &$GLOBALS['pricelastextend'];
        $priceproduct = &$GLOBALS['priceproduct'];
        $prodcut = &$GLOBALS['prodcut'];
        $product = &$GLOBALS['product'];
        $query = &$GLOBALS['query'];
        $queryParams = &$GLOBALS['queryParams'];
        $r = &$GLOBALS['r'];
        $randomString = &$GLOBALS['randomString'];
        $rccNetwork = &$GLOBALS['rccNetwork'];
        $rccPick = &$GLOBALS['rccPick'];
        $reason = &$GLOBALS['reason'];
        $receiptreport = &$GLOBALS['receiptreport'];
        $reportbackup = &$GLOBALS['reportbackup'];
        $reportcron = &$GLOBALS['reportcron'];
        $reportnight = &$GLOBALS['reportnight'];
        $reporttest = &$GLOBALS['reporttest'];
        $request_contact = &$GLOBALS['request_contact'];
        $result = &$GLOBALS['result'];
        $rialitActionMatch = &$GLOBALS['rialitActionMatch'];
        $rialitViewMatch = &$GLOBALS['rialitViewMatch'];
        $row = &$GLOBALS['row'];
        $rows = &$GLOBALS['rows'];
        $rwPickMatch = &$GLOBALS['rwPickMatch'];
        $rwm = &$GLOBALS['rwm'];
        $rxAsGateMutedUntil = &$GLOBALS['rxAsGateMutedUntil'];
        $rxCfgQrDelivery = &$GLOBALS['rxCfgQrDelivery'];
        $rxQrDelivery = &$GLOBALS['rxQrDelivery'];
        $rxQvDelivery = &$GLOBALS['rxQvDelivery'];
        $rxSubQrDelivery = &$GLOBALS['rxSubQrDelivery'];
        $sendresidcart = &$GLOBALS['sendresidcart'];
        $setting = &$GLOBALS['setting'];
        $status = &$GLOBALS['status'];
        $statuscustom = &$GLOBALS['statuscustom'];
        $statusnote = &$GLOBALS['statusnote'];
        $step_payment = &$GLOBALS['step_payment'];
        $stmt = &$GLOBALS['stmt'];
        $supportoption = &$GLOBALS['supportoption'];
        $telegramHandler = &$GLOBALS['telegramHandler'];
        $telegramRoot = &$GLOBALS['telegramRoot'];
        $telegramRoute = &$GLOBALS['telegramRoute'];
        $telegramStage = &$GLOBALS['telegramStage'];
        $text = &$GLOBALS['text'];
        $text_inline = &$GLOBALS['text_inline'];
        $textbotlang = &$GLOBALS['textbotlang'];
        $textcart = &$GLOBALS['textcart'];
        $textconnect = &$GLOBALS['textconnect'];
        $textextend = &$GLOBALS['textextend'];
        $textinfo = &$GLOBALS['textinfo'];
        $textscore = &$GLOBALS['textscore'];
        $textsendrasid = &$GLOBALS['textsendrasid'];
        $textsub = &$GLOBALS['textsub'];
        $timeDiffday = &$GLOBALS['timeDiffday'];
        $tpcMatch = &$GLOBALS['tpcMatch'];
        $txRangeMatch = &$GLOBALS['txRangeMatch'];
        $txText = &$GLOBALS['txText'];
        $update = &$GLOBALS['update'];
        $user = &$GLOBALS['user'];
        $user_phone = &$GLOBALS['user_phone'];
        $userdate = &$GLOBALS['userdate'];
        $userjoin = &$GLOBALS['userjoin'];
        $username = &$GLOBALS['username'];
        $usernamePromptKb = &$GLOBALS['usernamePromptKb'];
        $username_ac = &$GLOBALS['username_ac'];
        $usernamebot = &$GLOBALS['usernamebot'];
        $usernameconfig = &$GLOBALS['usernameconfig'];
        $users = &$GLOBALS['users'];
        $value = &$GLOBALS['value'];
        $valueverify = &$GLOBALS['valueverify'];
        $valurcashbackextend = &$GLOBALS['valurcashbackextend'];
        $version = &$GLOBALS['version'];
        $video = &$GLOBALS['video'];
        $videoid = &$GLOBALS['videoid'];
        $xuiIpLimitConfigured = &$GLOBALS['xuiIpLimitConfigured'];

        if (\Faoxima\Telegram\Bootstrap\Initialize::handle(
            Chat_type: $Chat_type ?? null,
            ManagePanel: $ManagePanel,
            callback_query_id: $callback_query_id ?? null,
            chat_member: $chat_member ?? null,
            first_name: $first_name,
            from_id: $from_id,
            is_bot: $is_bot ?? null,
            setting: $setting,
            status: $status,
            textbotlang: $textbotlang,
            update: $update ?? null,
            user: $user
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Bootstrap\User::handle(
            Response: $Response,
            admin_ids: $admin_ids,
            admin_ids_str: $admin_ids_str,
            callback_query_id: $callback_query_id ?? null,
            domainhosts: $domainhosts ?? null,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            otherreport: $otherreport,
            pdo: $pdo ?? null,
            randomString: $randomString,
            rxAsGateMutedUntil: $rxAsGateMutedUntil ?? null,
            setting: $setting ?? null,
            stmt: $stmt,
            textbotlang: $textbotlang ?? null,
            user: $user,
            username: $username ?? null,
            valueverify: $valueverify ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Bootstrap\Localization::handle(
            buyreport: $buyreport,
            datatextbot: $datatextbot,
            errorreport: $errorreport,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            last_name: $last_name ?? null,
            otherservice: $otherservice,
            paymentreports: $paymentreports,
            pdo: $pdo ?? null,
            porsantreport: $porsantreport,
            receiptreport: $receiptreport,
            reportbackup: $reportbackup,
            reportcron: $reportcron,
            reportnight: $reportnight,
            reporttest: $reporttest,
            setting: $setting ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null,
            username: $username ?? null,
            version: $version ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Conversation\Normalize::handle(
            admin_ids: $admin_ids ?? null,
            admin_ids_str: $admin_ids_str ?? null,
            callback_query_id: $callback_query_id ?? null,
            datain: $datain,
            from_id: $from_id ?? null,
            keyboard: $keyboard ?? null,
            otherservice: $otherservice ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            user: $user,
            username: $username ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Conversation\Onboarding::handle(
            Chat_type: $Chat_type ?? null,
            admin_ids: $admin_ids ?? null,
            callback_query_id: $callback_query_id ?? null,
            confrimrolls: $confrimrolls ?? null,
            connect: $connect,
            datain: $datain,
            datatextbot: $datatextbot ?? null,
            domainhosts: $domainhosts ?? null,
            from_id: $from_id ?? null,
            keyboard: $keyboard ?? null,
            message_id: $message_id ?? null,
            pdo: $pdo ?? null,
            setting: $setting ?? null,
            text: $text,
            textbotlang: $textbotlang ?? null,
            user: $user,
            username: $username ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Routing\AccountAndServices::handle(
            ManagePanel: $ManagePanel ?? null,
            Percent: $Percent ?? null,
            Response: $Response ?? null,
            _rx_nav_styles: $_rx_nav_styles ?? null,
            _rx_svc_styles: $_rx_svc_styles ?? null,
            admin_ids: $admin_ids ?? null,
            adminrulecheck: $adminrulecheck ?? null,
            backuser: $backuser ?? null,
            before: $before ?? null,
            callback_query_id: $callback_query_id ?? null,
            codeproduct: $codeproduct ?? null,
            configDelivery: $configDelivery ?? null,
            configs: $configs ?? null,
            connect: $connect ?? null,
            contact_id: $contact_id ?? null,
            data: $data ?? null,
            data_limit: $data_limit ?? null,
            dataget: $dataget,
            datain: $datain ?? null,
            dataoutput: $dataoutput ?? null,
            datatextbot: $datatextbot ?? null,
            day: $day ?? null,
            error: $error ?? null,
            errorreport: $errorreport ?? null,
            extend: $extend ?? null,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice ?? null,
            info_product: $info_product ?? null,
            keyboard: $keyboard ?? null,
            keyboard_buy: $keyboard_buy ?? null,
            lastupdate: $lastupdate ?? null,
            list_marzban_panel_user: $list_marzban_panel_user ?? null,
            list_marzban_panel_userschange: $list_marzban_panel_userschange ?? null,
            manualDeliveryResult: $manualDeliveryResult ?? null,
            marzban_list_get: $marzban_list_get ?? null,
            message_id: $message_id,
            namekeyboard: $namekeyboard ?? null,
            nameloc: $nameloc ?? null,
            namestatus: $namestatus ?? null,
            otherreport: $otherreport ?? null,
            otherservice: $otherservice ?? null,
            output: $output ?? null,
            page: $page ?? null,
            pages: $pages ?? null,
            pdo: $pdo ?? null,
            pricelastextend: $pricelastextend ?? null,
            prodcut: $prodcut ?? null,
            product: $product ?? null,
            randomString: $randomString ?? null,
            request_contact: $request_contact ?? null,
            result: $result ?? null,
            row: $row ?? null,
            rwm: $rwm ?? null,
            rxCfgQrDelivery: $rxCfgQrDelivery ?? null,
            rxQrDelivery: $rxQrDelivery ?? null,
            rxQvDelivery: $rxQvDelivery ?? null,
            rxSubQrDelivery: $rxSubQrDelivery ?? null,
            setting: $setting ?? null,
            status: $status ?? null,
            step_payment: $step_payment ?? null,
            stmt: $stmt ?? null,
            telegramHandler: $telegramHandler,
            telegramRoute: $telegramRoute,
            text: $text ?? null,
            text_inline: $text_inline ?? null,
            textbotlang: $textbotlang ?? null,
            textconnect: $textconnect ?? null,
            textextend: $textextend ?? null,
            textinfo: $textinfo ?? null,
            textsub: $textsub ?? null,
            timeDiffday: $timeDiffday ?? null,
            update: $update ?? null,
            user: $user ?? null,
            user_phone: $user_phone ?? null,
            userdate: $userdate ?? null,
            username: $username ?? null,
            usernameconfig: $usernameconfig ?? null,
            value: $value ?? null,
            valurcashbackextend: $valurcashbackextend ?? null,
            version: $version ?? null,
            xuiIpLimitConfigured: $xuiIpLimitConfigured ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Conversation\Initialize::handle() !== true) {
            return;
        }
        if (\Faoxima\Telegram\Routing\Conversations::handle(
            ManagePanel: $ManagePanel ?? null,
            Response: $Response ?? null,
            _cc_insert_err: $_cc_insert_err ?? null,
            _rx_acc_styles: $_rx_acc_styles ?? null,
            _rx_nav_styles: $_rx_nav_styles ?? null,
            _rx_payrcpt_styles: $_rx_payrcpt_styles ?? null,
            admin_ids: $admin_ids ?? null,
            apcMatch: $apcMatch ?? null,
            back: $back ?? null,
            backuser: $backuser ?? null,
            btn: $btn ?? null,
            buyreport: $buyreport ?? null,
            callback_query_id: $callback_query_id ?? null,
            caption: $caption ?? null,
            configs: $configs ?? null,
            connect: $connect ?? null,
            cur: $cur ?? null,
            data: $data ?? null,
            dataget: $dataget,
            datain: $datain ?? null,
            datakeyboard: $datakeyboard ?? null,
            dataoutput: $dataoutput ?? null,
            datatextbot: $datatextbot ?? null,
            domainhosts: $domainhosts ?? null,
            error: $error ?? null,
            errorreport: $errorreport ?? null,
            explorerHint: $explorerHint ?? null,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            fund: $fund ?? null,
            hashHint: $hashHint ?? null,
            info_product: $info_product ?? null,
            invoice: $invoice ?? null,
            invoiceMsg: $invoiceMsg ?? null,
            json_list_helpـcategory: $json_list_helpـcategory ?? null,
            keyboard: $keyboard ?? null,
            keyboard_buy: $keyboard_buy ?? null,
            label: $label ?? null,
            list_departman: $list_departman ?? null,
            list_marzban_panel_user: $list_marzban_panel_user ?? null,
            list_marzban_panel_userom: $list_marzban_panel_userom ?? null,
            loc: $loc ?? null,
            location: $location ?? null,
            marzban_list_get: $marzban_list_get ?? null,
            message_id: $message_id ?? null,
            mrow: $mrow ?? null,
            numberphone: $numberphone ?? null,
            payload: $payload ?? null,
            payment: $payment ?? null,
            paymentom: $paymentom ?? null,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            porsantreport: $porsantreport ?? null,
            priceproduct: $priceproduct ?? null,
            prodcut: $prodcut ?? null,
            query: $query ?? null,
            queryParams: $queryParams ?? null,
            r: $r ?? null,
            randomString: $randomString ?? null,
            rccNetwork: $rccNetwork ?? null,
            rccPick: $rccPick ?? null,
            reason: $reason ?? null,
            reporttest: $reporttest ?? null,
            request_contact: $request_contact ?? null,
            result: $result ?? null,
            rialitActionMatch: $rialitActionMatch ?? null,
            rialitViewMatch: $rialitViewMatch ?? null,
            rows: $rows ?? null,
            rwPickMatch: $rwPickMatch ?? null,
            sendresidcart: $sendresidcart ?? null,
            setting: $setting ?? null,
            statuscustom: $statuscustom ?? null,
            statusnote: $statusnote ?? null,
            step_payment: $step_payment ?? null,
            stmt: $stmt ?? null,
            supportoption: $supportoption ?? null,
            telegramHandler: $telegramHandler,
            telegramRoute: $telegramRoute,
            text: $text ?? null,
            text_inline: $text_inline ?? null,
            textbotlang: $textbotlang ?? null,
            textcart: $textcart ?? null,
            textscore: $textscore ?? null,
            tpcMatch: $tpcMatch ?? null,
            txRangeMatch: $txRangeMatch ?? null,
            txText: $txText ?? null,
            update: $update ?? null,
            user: $user ?? null,
            userdate: $userdate ?? null,
            userjoin: $userjoin ?? null,
            username: $username ?? null,
            usernamePromptKb: $usernamePromptKb ?? null,
            username_ac: $username_ac ?? null,
            usernamebot: $usernamebot ?? null,
            value: $value ?? null,
            video: $video ?? null,
            videoid: $videoid ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Routing\PaymentCallbacks::handle(
            callback_query_id: $callback_query_id ?? null,
            connect: $connect ?? null,
            dataget: $dataget,
            datain: $datain ?? null,
            from_id: $from_id ?? null,
            message_id: $message_id ?? null,
            paymentreports: $paymentreports ?? null,
            pdo: $pdo ?? null,
            result: $result,
            setting: $setting ?? null,
            textbotlang: $textbotlang ?? null,
            user: $user ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Routing\Followup::handle(
            ManagePanel: $ManagePanel ?? null,
            Response: $Response ?? null,
            _rx_nav_styles: $_rx_nav_styles ?? null,
            _rx_payrcpt_styles: $_rx_payrcpt_styles ?? null,
            admin_ids: $admin_ids ?? null,
            adminrulecheck: $adminrulecheck ?? null,
            affiliates: $affiliates ?? null,
            back: $back ?? null,
            backuser: $backuser ?? null,
            before: $before ?? null,
            callback_query_id: $callback_query_id ?? null,
            caption: $caption ?? null,
            codeproduct: $codeproduct ?? null,
            connect: $connect ?? null,
            data: $data ?? null,
            dataget: $dataget,
            datain: $datain ?? null,
            datatextbot: $datatextbot,
            diceResponse: $diceResponse ?? null,
            error: $error ?? null,
            errorreport: $errorreport ?? null,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            id_invoice: $id_invoice ?? null,
            invoice: $invoice ?? null,
            keyboard: $keyboard ?? null,
            keyboardagent: $keyboardagent ?? null,
            location: $location ?? null,
            marzban_list_get: $marzban_list_get ?? null,
            message_id: $message_id ?? null,
            nameloc: $nameloc ?? null,
            next_page: $next_page ?? null,
            otherreport: $otherreport ?? null,
            otherservice: $otherservice ?? null,
            page: $page ?? null,
            pages: $pages ?? null,
            pdo: $pdo ?? null,
            photo: $photo ?? null,
            photoid: $photoid ?? null,
            previous_page: $previous_page ?? null,
            pricelast: $pricelast ?? null,
            prodcut: $prodcut ?? null,
            r: $r ?? null,
            receiptreport: $receiptreport ?? null,
            result: $result ?? null,
            row: $row ?? null,
            sendresidcart: $sendresidcart ?? null,
            setting: $setting ?? null,
            status: $status ?? null,
            statusnote: $statusnote ?? null,
            step_payment: $step_payment ?? null,
            stmt: $stmt ?? null,
            telegramHandler: $telegramHandler,
            telegramRoute: $telegramRoute,
            text: $text ?? null,
            text_inline: $text_inline ?? null,
            textbotlang: $textbotlang ?? null,
            textcart: $textcart ?? null,
            textsendrasid: $textsendrasid ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null,
            usernamebot: $usernamebot ?? null,
            users: $users ?? null
        ) !== true) {
            return;
        }
        if (\Faoxima\Telegram\Routing\Completion::handle(
            ManagePanel: $ManagePanel ?? null,
            codeproduct: $codeproduct ?? null,
            dataget: $dataget,
            datain: $datain ?? null,
            datakeyboard: $datakeyboard ?? null,
            datatextbot: $datatextbot ?? null,
            errorreport: $errorreport ?? null,
            extend: $extend ?? null,
            first_name: $first_name ?? null,
            from_id: $from_id ?? null,
            keyboard: $keyboard ?? null,
            location: $location ?? null,
            marzban_list_get: $marzban_list_get ?? null,
            message_id: $message_id ?? null,
            nameloc: $nameloc ?? null,
            otherservice: $otherservice ?? null,
            pdo: $pdo ?? null,
            prodcut: $prodcut ?? null,
            query: $query ?? null,
            queryParams: $queryParams ?? null,
            result: $result ?? null,
            setting: $setting ?? null,
            statuscustom: $statuscustom ?? null,
            step_payment: $step_payment ?? null,
            stmt: $stmt ?? null,
            telegramHandler: $telegramHandler,
            telegramRoute: $telegramRoute,
            textbotlang: $textbotlang ?? null,
            textextend: $textextend ?? null,
            update: $update ?? null,
            user: $user ?? null,
            username: $username ?? null,
            value: $value ?? null
        ) !== true) {
            return;
        }
        unset($telegramRoot, $telegramStage, $GLOBALS['telegramRoot'], $GLOBALS['telegramStage']);
        return true;
    }
}
