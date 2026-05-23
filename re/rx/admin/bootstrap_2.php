<?php

// ─────────────────────────────────────────────────────────────────────────────
if (in_array($text, $textadmin) || $datain == "admin") {
    if ($datain == "admin")
        deletemessage($from_id, $message_id);
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    activecron();
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
    if ($miniAppInstructionHidden !== '1') {
        $miniAppInstructionKeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'دیگر نمایش نده ⛓️‍💥', 'callback_data' => 'hide_mini_app_instruction'],
                ],
            ],
        ]);
        nm_adminInstantReply($from_id, $miniAppInstructionText, $miniAppInstructionKeyboard, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['backadmin']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    if (function_exists('nmResolvePanelNameForUser')) {
        $rawProcessing = (string)($user['Processing_value'] ?? '');
        if ($rawProcessing !== '' && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
            $resolvedName = nmResolvePanelNameForUser($user);
            if ($resolvedName !== '') {
                update("user", "Processing_value", $resolvedName, "id", $from_id);
            }
        }
        unset($rawProcessing, $resolvedName);
    }
    $version_mini_app = file_get_contents('app/version');
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "hide_mini_app_instruction") {
    if (!in_array($from_id, $admin_ids))
        return;
    if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
        update("user", "hide_mini_app_instruction", "1", "id", $from_id);
        $user['hide_mini_app_instruction'] = '1';
    }
    $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
    $confirmationText = $miniAppInstructionText . "\n\n✅ این پیام دیگر برای شما نمایش داده نخواهد شد.";
    Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
    return;
} elseif ($text == "🔙 بازگشت به انبار" && in_array($from_id, $admin_ids)) {
    step('home', $from_id);
    $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $keyboardadmin;
    nm_adminInstantReply($from_id, "📦 بازگشت به منوی انبارداری", $stockKb, 'HTML');
    return;
} elseif (($text == $textbotlang['Admin']['backmenu']) || ((isset($datain) ? (string)$datain : '') === 'backmenu')) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['activebottext'], $setting_panel, 'HTML');
        return;
    }
    $currentStep = isset($user['step']) ? (string) $user['step'] : '';
    step('home', $from_id);


    if (in_array($currentStep, ['premium_emoji_get_char', 'premium_emoji_get_id', 'premium_emoji_edit_id'], true)) {
        if (function_exists('rxRenderPremiumEmojiPanel')) {
            rxRenderPremiumEmojiPanel($from_id, 1);
        }
        return;
    }
    if (in_array($currentStep, ["updatetime", "val_usertest", "getlimitnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
        $panelNameBack = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
        if ($panelNameBack !== '') {
            update("user", "Processing_value", $panelNameBack, "id", $from_id);
        }
        $typepanel = select("marzban_panel", "*", "name_panel", $panelNameBack !== '' ? $panelNameBack : $user['Processing_value'], "select");
        outtypepanel(is_array($typepanel) ? $typepanel['type'] : '', $textbotlang['Admin']['Back-menu']);
    } else {
        $financialStepKeyboardMap = [
            'apiternado' => $trnado,
            'changecard' => $CartManage,
            'getnamecard' => $CartManage,
            'getcardremove' => $CartManage,
            'getnamecarttocart' => $CartManage,
            'getnamenowpayment' => $nowpayment_setting_keyboard,
            'getnamecarttopaynotverify' => $CartManage,
            'gettextnowpayment' => $NowPaymentsManage,
            'gettextnowpaymentTRON' => $tronnowpayments,
            'gettextiranpay2' => $Swapinokey,
            'gettextstartelegram' => $Swapinokey,
            'gettextiranpay3' => $trnado,
            'gettextiranpay1' => $iranpaykeyboard,
            'gettextaqayepardakht' => $aqayepardakht,
            'gettextzarinpal' => $keyboardzarinpal,
            'gettextzarinpey' => $keyboardzarinpey,
            'token_zarinpey' => $keyboardzarinpey,
            'merchant_id_aqayepardakht' => $aqayepardakht,
            'merchant_zarinpal' => $keyboardzarinpal,
            'apinowpayment' => $NowPaymentsManage,
            'nowpayment_ipn_secret' => $nowpayment_setting_keyboard,
            'marchent_tronseller' => $nowpayment_setting_keyboard,
            'getcashcart' => $CartManage,
            'getcashahaypar' => $CartManage,
            'getcashiranpay2' => $trnado,
            'getcashiranpay4' => $CartManage,
            'getcashiranpay1' => $Swapinokey,
            'getcashplisio' => $CartManage,
            'getcashnowpayment' => $nowpayment_setting_keyboard,
            'getcashzarinpal' => $keyboardzarinpal,
            'getcashzarinpey' => $keyboardzarinpey,
            'getmaincart' => $CartManage,
            'getmaxcart' => $CartManage,
            'getmainplisio' => $NowPaymentsManage,
            'getmaxplisio' => $NowPaymentsManage,
            'getmaindigitaltron' => $tronnowpayments,
            'getmaxdigitaltron' => $tronnowpayments,
            'getmainiranpay1' => $Swapinokey,
            'getmaaxiranpay1' => $Swapinokey,
            'getmainiranpay2' => $trnado,
            'getmaaxiranpay2' => $Swapinokey,
            'getmainaqayepardakht' => $aqayepardakht,
            'getmaaxaqayepardakht' => $aqayepardakht,
            'getmainaqzarinpal' => $aqayepardakht,
            'getmaaxzarinpal' => $aqayepardakht,
            'getmainzarinpey' => $keyboardzarinpey,
            'getmaaxzarinpey' => $keyboardzarinpey,
            'helpzarinpey' => $keyboardzarinpey,
            'gethelpcart' => $CartManage,
            'gethelpnowpayment' => $nowpayment_setting_keyboard,
            'gethelpperfect' => $CartManage,
            'gethelpplisio' => $CartManage,
            'gethelpiranpay1' => $CartManage,
            'getmainaqstar' => $Startelegram,
            'maxbalancestar' => $Startelegram,
            'getmainaqnowpayment' => $nowpayment_setting_keyboard,
            'maxbalancenowpayment' => $nowpayment_setting_keyboard,
            'gethelpstar' => $Startelegram,
            'chashbackstar' => $Startelegram,
        ];

        $productStepKeyboardMap = [
            'change_price' => $change_product,
            'change_note' => $change_product,
            'change_categroy' => $change_product,
            'change_name' => $change_product,
            'change_type_agent' => $change_product,
            'change_reset_data' => $change_product,
            'change_loc_data' => $change_product,
            'getlistpanel' => $change_product,
            'change_val' => $change_product,
            'change_time' => $change_product,
        ];

        if (in_array($currentStep, ['admin_nav_cart_settings'], true)) {
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        if (in_array($currentStep, ['admin_nav_cron_settings', 'admin_nav_cron_jobs'], true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            step('admin_nav_cron_settings', $from_id);
            return;
        }

        if ($currentStep === 'cronjob_set_value') {
            if (function_exists('buildCronJobsKeyboard')) {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], buildCronJobsKeyboard(), 'HTML');
                step('admin_nav_cron_jobs', $from_id);
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                step('admin_nav_cron_settings', $from_id);
            }
            return;
        }

        if ($currentStep === 'walletaddresssiranpay') {
            $processingData = [];
            if (isset($user['Processing_value'])) {
                $decodedProcessing = json_decode($user['Processing_value'], true);
                if (is_array($decodedProcessing)) {
                    $processingData = $decodedProcessing;
                }
            }
            $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
            $keyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboard, 'HTML');
            return;
        }

        if (isset($financialStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $financialStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        if (isset($productStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $productStepKeyboardMap[$currentStep];
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        // ── Finance steps ──────────────────────────────────────────────────────
        $financeSteps = [
            'marchent_tronseller', 'marchent_floypay', 'urlpaymenttron', 'cryptowallet_set',
            'admin_nav_finance', 'maxbalance', 'minbalance', 'CartDirect', 'showcardallusers',
            'apiiranpay', 'getpricecashback', 'getnameconfigm',
        ];
        if (in_array($currentStep, $financeSteps, true)) {
            step('home', $from_id);
            if (function_exists('sendAdminFinanceMenu')) {
                sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
            } else {
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            }
            return;
        }

        // ── Finance steps mapped to specific payment keyboard ─────────────────
        $financeSpecificMap = [
            'getmainiranpay3' => $trnado,    'getmaaxiranpay3' => $trnado,
            'gethelpiranpay3' => $trnado,    'getcashiranpay3' => $trnado,
            'getcashiranpay2'  => $trnado,   'getcashiranpay4'  => $Swapinokey,
            'getmainiranpay2'  => $trnado,   'getmaaxiranpay2'  => $Swapinokey,
            'getmainiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'getmaaxiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
            'gethelpiranpay2'  => $Swapinokey,
            'getagentbalancemax' => $shopkeyboard,
            'getagentbalancemin' => $shopkeyboard,
            'getmaindigitaltron2' => $tronnowpayments,
            'getmaxdigitaltron2'  => $tronnowpayments,
        ];
        if (isset($financeSpecificMap[$currentStep])) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $financeSpecificMap[$currentStep], 'HTML');
            return;
        }

        // ── Discount steps → shop menu ─────────────────────────────────────────
        $discountSteps = [
            'getdiscont', 'getfirstdiscount', 'getlimitcode', 'getlimitcodedis',
            'getlocdiscount', 'getproductdiscount', 'gettimediscount', 'gettypeagentoflist',
            'gettypecodeagent', 'getuseuser', 'getmaxbuyagent', 'getpercentuser',
            'setpercentage', 'remove-Discount', 'remove-Discountsell', 'get_price_code',
            'get_price_codesell', 'get_price_Negative', 'Negative_Balance',
            'getlimitedpanel', 'getagent', 'stependforaddorder', 'getnameproduct',
            'add_Balance_all', 'setbanner', 'show_info', 'reject-dec',
            'remove-product', 'get_number_limit', 'GetLocationEdit', 'PanelMenu',
        ];
        if (in_array($currentStep, $discountSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        }

        // ── Text/Help editing steps → textbot keyboard ─────────────────────────
        $textEditSteps = [
            'text_Add_Balance', 'text_Discount', 'text_Tariff_list', 'text_affiliates',
            'text_afterpaytext', 'text_afterpaytextibsng', 'text_aftertesttext',
            'text_cart', 'text_cart_auto', 'text_channel', 'text_crontest',
            'text_dec_Tariff_list', 'text_dec_fq', 'text_extend', 'text_fq',
            'text_help', 'text_pishinvoice', 'text_request_agent_dec', 'text_roll',
            'text_sell', 'text_support', 'text_textmanual', 'text_wgdashboard',
            'text_wheel_luck', 'textpanelagent', 'textrequestagent', 'textselectlocation',
            'changetextinfo', 'changetextstart', 'changetextusertest',
        ];
        if (in_array($currentStep, $textEditSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $textbot, 'HTML');
            return;
        }

        // ── Help menu steps → keyboardhelpadmin ────────────────────────────────
        $helpSteps = [
            'changecategoryhelp', 'changedeshelp', 'changemedia', 'changenamehelp',
            'add_name_help', 'getcatgoryhelp', 'remove_help', 'getconfigtext',
            'getservceid',
        ];
        if (in_array($currentStep, $helpSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardhelpadmin, 'HTML');
            return;
        }

        // ── Settings steps → setting_panel ─────────────────────────────────────
        $settingsSteps = [
            'getnamepanelconfig', 'getusernameconfig', 'addchannelid',
            'idsupportset', 'limit_usertest_allusers', 'get_codesell',
        ];
        if (in_array($currentStep, $settingsSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
            return;
        }

        // ── User management steps → keyboardadmin ──────────────────────────────
        $userSteps = [
            'accountwallet', 'add_dec', 'addbalancemanual', 'addbalanceuser',
            'addbalanceusercurrent', 'adddecriptionblock', 'getuserhide',
            'getuserhideforremove', 'addadmin', 'getrule', 'GetusernameconfigAndOrdedrs',
            'antispam_get_count', 'antispam_get_mute', 'antispam_get_seconds',
            'getbtnresponseforward', 'getmessageAsAdmin', 'getmessageforward',
            'sendmessagetext', 'sendmessagetid', 'getcountcreate', 'getagentpanel',
            'getinboundiid', 'getuuidadmin', 'getprotocoldisable', 'getprotocolx_ui',
            'getvolumesconfig', 'getlocoption', 'GeturlNewx', 'add_link_panel',
            'add_password_panel', 'add_username_panel', 'confirmremovepanel',
            'getInbounddisable', 'getusernameconfigcr', 'removeprotocol',
            'GetPriceExtratime', 'GetPricecustomvo', 'GetPricetimeextra',
            'GetmaineExtra', 'Getmaintime', 'GetmaxeExtra', 'Getmaxtime',
        ];
        if (in_array($currentStep, $userSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            return;
        }

        // ── Guard steps → guard settings ───────────────────────────────────────
        $guardSteps = [
            'guard_edit_api_key', 'guard_service_selection_edit', 'guard_service_selection_new',
            'guard_settings_auto_delete', 'guard_settings_auto_renew',
            'guard_settings_note', 'guard_settings_summary', 'add_guard_api_key',
        ];
        if (in_array($currentStep, $guardSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
            return;
        }

        // ── Stock/NM steps → stock management keyboard (not shop) ──────────────
        $nmSteps = [
            'nm_delete_stock_action', 'nm_delete_stock_select_shelf', 'nm_edit_shelf_select',
            'nm_select_emergency_panel', 'nm_shelf_category', 'nm_shelf_name',
            'nm_shelf_panel', 'nm_shelf_product', 'nm_stock_ask_has_sub',
            'nm_stock_bulk_import', 'nm_stock_paired_cfg', 'nm_stock_paired_sub',
            'nm_stock_select_shelf_import',
            'nm_stock_delete_by_id', 'nm_stock_delete_confirm',
            'nm_delete_shelf_select', 'nm_delete_shelf_confirm',
        ];
        if (in_array($currentStep, $nmSteps, true)) {
            $stockKb = function_exists('nmStockManageKeyboard') ? nmStockManageKeyboard() : $shopkeyboard;
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $stockKb, 'HTML');
            return;
        }

        $shopSteps = [
            "selectloc",
            "get_limit",
            "selectlocedite",
            "GetPriceExtra",
            "GetPriceexstratime",
            "GetPricecustomtime",
            "GetPricecustomvolume",
            "get_code",
            "get_codesell",
            "minbalancebulk",
            "get_agent",
            "get_location",
            "getcategory",
            "get_time",
            "get_price",
            "gettimereset",
            "getnote",
            "endstep",
            "gettypeextra",
            "gettypeextracustom",
            "gettypeextratime",
            "gettypeextratimecustom",
            "gettypeextramain",
            "gettypeextramax",
            "gettypeextramaintime",
            "gettypeextramaxtime",
        ];

        if (in_array($currentStep, $shopSteps, true)) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        } elseif (in_array($currentStep, ["addchannel", "removechannel"])) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
        }
    }
    return;
} elseif ($text == $textbotlang['Admin']['channel']['title'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $backadmin, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel") {
    savedata("clear", "link", $text);
    nm_adminInstantReply($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $backadmin, 'HTML');
    step('getremark', $from_id);
} elseif ($user['step'] == "getremark") {
    savedata("save", "remark", $text);
    nm_adminInstantReply($from_id, "📌 لینک عضویت را ارسال کنید", $backadmin, 'HTML');
    step('getlinkjoin', $from_id);
} elseif ($user['step'] == "getlinkjoin") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, "آدرس عضویت صحیح نمی باشد", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }

    $remark = isset($userdata['remark']) ? (string) $userdata['remark'] : '';
    $link = isset($userdata['link']) ? (string) $userdata['link'] : '';

    nm_adminInstantReply($from_id, "✅ کانال جوین اجباری با موفقیت ثبت گردید.", $channelkeyboard, 'HTML');
    step('home', $from_id);

    $insertChannel = function ($remarkValue) use ($pdo, $link, $text) {
        $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
        $stmt->bindValue(':remark', $remarkValue, PDO::PARAM_STR);
        $stmt->bindValue(':link', $link, PDO::PARAM_STR);
        $stmt->bindValue(':linkjoin', $text, PDO::PARAM_STR);
        $stmt->execute();
    };

    try {
        $insertChannel($remark);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            ensureTableUtf8mb4('channels');
            try {
                $insertChannel($remark);
            } catch (PDOException $retryException) {
                if (strpos($retryException->getMessage(), 'Incorrect string value') === false) {
                    throw $retryException;
                }

                $sanitisedRemark = is_string($remark) ? @iconv('UTF-8', 'UTF-8//IGNORE', $remark) : '';
                if ($sanitisedRemark === false) {
                    $sanitisedRemark = '';
                }
                $insertChannel($sanitisedRemark);
            }
        } else {
            throw $e;
        }
    }
} elseif ($text == $textbotlang['Admin']['channel']['removechannelbtn'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removechannel'], $list_channels_joins, 'HTML');
    step('removechannel', $from_id);
} elseif ($user['step'] == "removechannel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM channels WHERE link = :link");
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    $adminId = trim($text);
    if ($adminId === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $adminId, "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
    step('getrule', $from_id);
} elseif ($user['step'] == "getrule") {
    $rule = ['administrator', 'Seller', 'support'];
    if (!in_array($text, $rule)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $usernamepanel = "root";
    $randomString = bin2hex(random_bytes(5));
    $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, rule) VALUES (:id_admin, :username, :password, :rule)");
    $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
    $stmt->bindParam(':password', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
    $stmt->execute();
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
    $id_user_set = $text;
    step('home', $from_id);
    update("user", "limit_usertest", $text, "id", $user['Processing_value']);
} elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
    step('home', $from_id);
    update("user", "limit_usertest", $text);
    update("setting", "limit_usertest_all", $text);
} elseif ($text == "📯 تنظیمات کانال" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['description'], $channelkeyboard, 'HTML');
} elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
    $Balanceall = select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
    $statistics = select("user", "*", null, null, "count");
    $sumpanel = select("marzban_panel", "*", null, null, "count");
    $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
    $stmt1 = $pdo->query($sql1);
    $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
    $agentsumn = select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
    $agentsumn2 = select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt1 = $pdo->query($sql1);
    $invoiceactive = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sqlall = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE status != 'Unpaid' AND name_product != 'سرویس تست'";
    $sqlall = $pdo->query($sqlall);
    $invoice = $sqlall->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = $stmt2->fetch(PDO::FETCH_ASSOC)['total_price'];
    $sql33 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE status!= 'Unpaid' AND name_product != 'سرویس تست'";
    $sql33 = $pdo->query($sql33);
    $invoiceSumRow = $sql33->fetch(PDO::FETCH_ASSOC);
    $invoiceTotal = isset($invoiceSumRow['total_price']) ? (float) $invoiceSumRow['total_price'] : 0;
    $invoicesumall = number_format($invoiceTotal, 0);
    $sql3 = "SELECT SUM(price) AS total_extend FROM service_other WHERE type = 'extend_user'";
    $stmt3 = $pdo->query($sql3);
    $extendSumRow = $stmt3->fetch(PDO::FETCH_ASSOC);
    $extendsum = isset($extendSumRow['total_extend']) ? (float) $extendSumRow['total_extend'] : 0;
    $count_usertest = select("invoice", "*", "name_product", "سرویس تست", "count");
    $timeacc = jdate('H:i:s', time());
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as count FROM `invoice` WHERE Status != 'Unpaid'");
    $stmt2->execute();
    $statisticsorder = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $sqlsum = "SELECT SUM(price) AS sumpay , Payment_Method,COUNT(price) AS countpay FROM Payment_report WHERE payment_Status = 'paid' AND Payment_Method NOT IN ('add balance by admin','low balance by admin') GROUP BY  Payment_Method;";
    $stmt = $pdo->prepare($sqlsum);
    $stmt->execute();
    $statispay = $stmt->fetchAll();
    $date = date("Y-m-d");
    $timeacc = jdate('H:i:s', time());
    $start_time = date('d.m.Y', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('d.m.Y', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT SUM(price_product) FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR Status = 'send_on_hold' OR Status = 'sendedwarn') AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $suminvoiceday = $stmt->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'];
    $invoicesum = (float) ($invoicesum ?? 0);
    $extendsum = (float) ($extendsum ?? 0);
    $suminvoiceday = (float) ($suminvoiceday ?? 0);
    $statistics = (int) ($statistics ?? 0);
    $statisticsorder = (int) ($statisticsorder ?? 0);
    $paycount = "";
    $ratecustomer = round(safe_divide($statisticsorder * 100, $statistics, 0), 2);
    $averagePurchase = safe_divide($invoicesum, $statisticsorder, 0);
    $avgbuy_customer = $averagePurchase > 0 ? number_format($averagePurchase) : '0';
    $monthe_buy = number_format($suminvoiceday * 30);
    $percent_of_extend = round(safe_divide($extendsum * 100, $invoicesum, 0), 2);
    $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
    $extendsum = number_format($extendsum, 0);
    if (!empty($statispay)) {
        $statusLabels = [
            'cart to cart' => $datatextbot['carttocart'] ?? 'cart to cart',
            'aqayepardakht' => $datatextbot['aqayepardakht'] ?? 'aqayepardakht',
            'zarinpal' => $datatextbot['zarinpal'] ?? 'zarinpal',
            'zarinpey' => $datatextbot['zarinpey'] ?? 'zarinpey',
            'zarinpay' => $datatextbot['zarinpey'] ?? ($datatextbot['zarinpal'] ?? 'zarinpay'),
            'plisio' => $datatextbot['textnowpayment'] ?? 'plisio',
            'arze digital offline' => $datatextbot['textnowpaymenttron'] ?? 'arze digital offline',
            'Currency Rial 1' => $datatextbot['iranpay2'] ?? 'Currency Rial 1',
            'Currency Rial 2' => $datatextbot['iranpay3'] ?? 'Currency Rial 2',
            'Currency Rial 3' => $datatextbot['iranpay1'] ?? 'Currency Rial 3',
            'paymentnotverify' => $datatextbot['textpaymentnotverify'] ?? 'paymentnotverify',
            'Star Telegram' => $datatextbot['text_star_telegram'] ?? 'Star Telegram',
        ];

        foreach ($statispay as $tracepay) {
            $paymentMethod = $tracepay['Payment_Method'] ?? '';
            $status_var = $statusLabels[$paymentMethod] ?? $paymentMethod;
            $paycount .= "
📌 نام درگاه : <code>$status_var</code>
 - تعداد پرداخت موفق : <code>{$tracepay['countpay']}</code>
 - جمع پرداختی ها : <code>{$tracepay['sumpay']}</code>\n";
        }
    }
    $bot_ping = 'نامشخص';
    $ping_start_time = microtime(true);
    $ping_response = telegram('getMe');
    $ping_duration = (microtime(true) - $ping_start_time) * 1000;
    if (is_array($ping_response) && !empty($ping_response['ok'])) {
        $bot_ping = number_format(max($ping_duration, 0), 0) . ' میلی‌ثانیه';
    }

    $statisticsall = "📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>$statistics</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>$statisticsorder</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>$count_usertest</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>$Balanceall</code> تومان

🧾 <b>تعداد کل فروش:</b> <code>$invoice</code> عدد
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>$invoiceactive</code> عدد
💵 <b>جمع کل فروش :</b> <code>$invoicesumall</code> تومان
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>$invoicesum</code> تومان
🔄 <b>جمع کل تمدید:</b> <code>$extendsum</code> تومان
📈 <b>نرخ تبدیل به مشتری:</b> <code>$ratecustomer</code>٪
💳 <b>میانگین خرید هر مشتری:</b> <code>$avgbuy_customer</code> تومان
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>$monthe_buy</code> تومان
📊 <b>درصد تمدید از فروش:</b> <code>$percent_of_extend</code>٪


👨‍💼 <b>تعداد کل نمایندگان:</b> <code>$agentsum</code> نفر
🔹 <b>نمایندگان نوع N:</b> <code>$agentsumn</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>$agentsumn2</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>$sumpanel</code> عدد
📡 <b>پینگ ربات:</b> $bot_ping
$paycount
";
    if ($datain == "stat_all_bot") {
        Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
    } else {
        nm_adminInstantReply($from_id, $statisticsall, $keyboard_stat, 'HTML');
    }
} elseif ($datain == "close_stat") {
    deletemessage($from_id, $message_id);
} elseif ($datain == "hoursago_stat") {
    $desired_date_time_start = time() - 3600;
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $time_current = time();
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $extra_time_stat['count'];
    $sum_change_location = number_format($extra_time_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $countextendday = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ۱ ساعت گذشته</b>


🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countextendday نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "yesterday_stat") {
    $start_time = date('Y/m/d', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('Y/m/d', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "today_stat") {
    $start_time = date('Y/m/d') . " 00:00:00";
    $end_time = date('Y/m/d H:i:s');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_old_stat") {
    $firstDayLastMonth = new DateTime('first day of last month');
    $lastDayLastMonth = new DateTime('last day of last month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_current_stat") {
    $firstDayLastMonth = new DateTime('first day of this month');
    $lastDayLastMonth = new DateTime('last day of this month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "view_stat_time") {
    nm_adminInstantReply($from_id, sprintf($textbotlang['Admin']['getstats'], date('Y/m/d')), $backadmin, 'HTML');
    step("get_time_start", $from_id);
} elseif ($user['step'] == "get_time_start") {
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    savedata("clear", "start_time", $text);
    nm_adminInstantReply($from_id, "تاریخ پایان را ارسال کنید بطور مثال :  \n<code>2025/09/08</code>", $backadmin, 'HTML');
    step("get_time_end", $from_id);
} elseif ($user['step'] == "get_time_end") {
    if (!isValidDate($text)) {
        nm_adminInstantReply($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $start_time = $userdata['start_time'] . "00:00:00";
    $end_time = $text . "23:59:00";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND  Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار تاریخ انتخابی</b>

⏳ بازه تایم  : $start_time تا $end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    step('home', $from_id);
    nm_adminInstantReply($from_id, $statisticsall, $keyboardadmin, 'HTML');
} elseif ($datain == "settingaffiliatesf") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardtypepanel, 'HTML');
} elseif (preg_match('/typepanel#(.*)/', $datain, $dataget)) {
    $typepanel = $dataget[1];
    $rx_inline_mode = (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
    if ($rx_inline_mode) {
        $rx_addpanel_back_kb = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']]
            ],
        ]);
    } else {
        $rx_addpanel_back_kb = $backadmin;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $rx_addpanel_back_kb, 'HTML');
    step("add_name_panel", $from_id);
    savedata("clear", "type", $typepanel);
} elseif ($user['step'] == "add_name_panel") {
    if (in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    savedata("save", "namepanel", $text);
    if ($userdata['type'] == "Manualsale") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    }
    if ($userdata['type'] == "guard") {
        $defaultGuardUrl = guardGetBaseUrl();
        savedata("save", "url_panel", $defaultGuardUrl);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $normalizedPanelUrl = rtrim($text, '/');
    savedata("save", "url_panel", $normalizedPanelUrl);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['type'] == "hiddify") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "guard") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        savedata("save", "username", "null");
        return;
    } elseif ($userdata['type'] == "s_ui" || $userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username", "null");
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username", $text);
} elseif ($user['step'] == "add_guard_api_key") {
    $apiKey = trim($text);
    if ($apiKey === '') {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidapikey'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $guardBaseUrl = guardGetBaseUrl(isset($userdata['url_panel']) ? $userdata['url_panel'] : '');
    $connectionResult = guardTestConnection($guardBaseUrl, $apiKey);
    if ($connectionResult['status'] === false) {
        $errorMessage = $connectionResult['msg'] ?? $textbotlang['Admin']['managepanel']['invalidapikey'];
        $feedback = "❌ اتصال به گارد ناموفق بود:\n{$errorMessage}\n\n📌 لطفاً API Key را بررسی کرده و مجدداً ارسال کنید.";
        nm_adminInstantReply($from_id, $feedback, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $panelConfig = $connectionResult['panel_config'] ?? [
        'status' => true,
        'panel' => [
            'type' => 'guard',
            'url_panel' => $guardBaseUrl,
            'api_key' => $apiKey,
            'password_panel' => null,
        ],
        'api_key' => $apiKey,
    ];
    savedata("save", "api_key", $apiKey);
    savedata("save", "url_panel", $guardBaseUrl);
    $servicesResponse = guardGetServices($panelConfig);
    if ($servicesResponse['status'] === false) {
        $errorMessage = $servicesResponse['msg'] ?? 'خطای ناشناخته';
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, $errorMessage);
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $services = $servicesResponse['services'];
    if (!is_array($services) || empty($services)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'لیست سرویس Guard خالی است.');
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($availableIds)) {
        $failTextTemplate = $textbotlang['Admin']['managepanel']['guard']['service_fetch_failed'] ?? "❌ دریافت سرویس‌ها از Guard ناموفق بود: %s";
        $failText = sprintf($failTextTemplate, 'شناسه معتبر برای سرویس‌ها یافت نشد.');
        nm_adminInstantReply($from_id, $failText, $backadmin, 'HTML');
        step('add_guard_api_key', $from_id);
        return;
    }
    $statusText = guardFormatConnectionResult($connectionResult);
    if ($statusText !== '') {
        nm_adminInstantReply($from_id, "✅ اتصال به گارد برقرار شد.\n{$statusText}", $backadmin, 'HTML');
    }
    $selectionState = [
        'mode' => 'create',
        'panel' => null,
        'services' => $services,
        'selected_ids' => guardNormalizeSelectedServiceIds($availableIds, $availableIds, true),
        'select_all' => true,
        'manual_selected_ids' => [],
    ];
    savedata("save", "guard_services_cache", $services);
    savedata("save", "guard_service_selection", $selectionState);
    $message = guardBuildServiceSelectionMessage($services, $selectionState['selected_ids'], true);
    $keyboard = guardBuildServiceSelectionKeyboard($services, $selectionState['selected_ids'], 'create', true);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $selectionState['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $selectionState);
    }
    step('guard_service_selection_new', $from_id);
} elseif (preg_match('/^guardservice:(create|edit):(toggle|toggle_all|save|done|back|close)(?::(\d+))?$/', $datain, $matches)) {
    $mode = $matches[1];
    $action = $matches[2];
    $serviceId = isset($matches[3]) ? intval($matches[3]) : null;
    $userdata = json_decode($user['Processing_value'], true);
    $state = isset($userdata['guard_service_selection']) ? $userdata['guard_service_selection'] : null;
    if (!is_array($state) || ($state['mode'] ?? null) !== $mode || empty($state['services'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'اطلاعات سرویس Guard در دسترس نیست. لطفاً دوباره تلاش کنید.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $services = is_array($state['services']) ? $state['services'] : [];
    $availableIds = guardExtractServiceIdsFromList($services);
    if (empty($services) || empty($availableIds)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'لیست سرویس Guard در دسترس نیست.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $selectAll = !empty($state['select_all']);
    $selectedIds = guardNormalizeSelectedServiceIds($state['selected_ids'] ?? [], $availableIds, false);
    if ($selectAll) {
        $selectedIds = $availableIds;
    }
    if (!empty($message_id)) {
        $state['message_id'] = $message_id;
        savedata("save", "guard_service_selection", $state);
    }
    if ($action === 'toggle') {
        if ($selectAll) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'برای تغییر انتخاب‌ها، ابتدا حالت همه سرویس‌ها را خاموش کنید.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        if ($serviceId === null || !in_array($serviceId, $availableIds, true)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'شناسه سرویس نامعتبر است.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        if (in_array($serviceId, $selectedIds, true)) {
            $selectedIds = array_values(array_diff($selectedIds, [$serviceId]));
        } else {
            $selectedIds[] = $serviceId;
        }
        $state['selected_ids'] = $selectedIds;
        $state['manual_selected_ids'] = $selectedIds;
        $state['select_all'] = false;
        savedata("save", "guard_service_selection", $state);
        $message = guardBuildServiceSelectionMessage($services, $selectedIds, false);
        $keyboard = guardBuildServiceSelectionKeyboard($services, $selectedIds, $mode, false);
        Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'وضعیت سرویس به‌روزرسانی شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'toggle_all') {
        if ($selectAll) {
            $manualSelected = isset($state['manual_selected_ids']) && is_array($state['manual_selected_ids']) ? $state['manual_selected_ids'] : [];
            $selectedIds = guardNormalizeSelectedServiceIds($manualSelected, $availableIds, false);
            $state['select_all'] = false;
        } else {
            $state['manual_selected_ids'] = $selectedIds;
            $selectedIds = $availableIds;
            $state['select_all'] = true;
        }
        $state['selected_ids'] = $selectedIds;
        savedata("save", "guard_service_selection", $state);
        $message = guardBuildServiceSelectionMessage($services, $selectedIds, !empty($state['select_all']));
        $keyboard = guardBuildServiceSelectionKeyboard($services, $selectedIds, $mode, !empty($state['select_all']));
        Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => !empty($state['select_all']) ? 'همه سرویس‌ها فعال شد.' : 'حالت همه سرویس‌ها خاموش شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'back') {
        guardRestorePanelSelection($from_id, $state);
        savedata("save", "guard_service_selection", null);
        if ($mode === 'create') {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        } else {
            step('home', $from_id);
        }
        deletemessage($from_id, $message_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'عملیات لغو شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'close') {
        guardRestorePanelSelection($from_id, $state);
        savedata("save", "guard_service_selection", null);
        deletemessage($from_id, $message_id);
        if ($mode === 'create') {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getapikey'], $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        } else {
            step('home', $from_id);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منو بسته شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'done' || $action === 'save') {
        $effectiveSelected = $selectAll ? $availableIds : guardNormalizeSelectedServiceIds($selectedIds, $availableIds, false);
        if (empty($effectiveSelected)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '⚠️ حداقل یک سرویس را انتخاب کنید',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        $storageValue = guardEncodeServiceSelectionForStorage($effectiveSelected, $availableIds, $selectAll);
        if ($mode === 'create') {
            savedata("save", "guard_service_ids", $storageValue);
            savedata("save", "guard_service_selection", null);
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
            step('getlimitedpanel', $from_id);
            savedata("save", "password", "null");
        } else {
            $panelName = isset($state['panel']) ? $state['panel'] : ($user['Processing_value'] ?? null);
            if ($panelName === null) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'نام پنل Guard مشخص نیست.',
                    'show_alert' => true,
                    'cache_time' => 3,
                ]);
                return;
            }
            update("marzban_panel", "guard_service_ids", $storageValue, "name_panel", $panelName);
            $state['selected_ids'] = $selectAll ? $availableIds : $effectiveSelected;
            $state['select_all'] = $selectAll;
            $state['manual_selected_ids'] = $state['manual_selected_ids'] ?? $effectiveSelected;
            savedata("save", "guard_service_selection", $state);
            $message = guardBuildServiceSelectionMessage($services, $state['selected_ids'], $selectAll);
            $message .= "\n\n✅ تنظیمات سرویس Guard ذخیره شد.";
            $keyboard = guardBuildServiceSelectionKeyboard($services, $state['selected_ids'], $mode, $selectAll);
            Editmessagetext($from_id, $message_id, $message, $keyboard, 'HTML');
            guardRestorePanelSelection($from_id, $state);
            step('home', $from_id);
        }
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'ذخیره شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
} elseif ($user['step'] == "guard_service_selection_new" || $user['step'] == "guard_service_selection_edit") {
    $userdata = json_decode($user['Processing_value'], true);
    $state = isset($userdata['guard_service_selection']) ? $userdata['guard_service_selection'] : null;
    $mode = $state['mode'] ?? ($user['step'] == "guard_service_selection_edit" ? 'edit' : 'create');
    $inputText = isset($text) ? trim((string) $text) : '';
    if (!is_array($state) || empty($state['services'])) {
        if ($mode === 'edit') {
            outtypepanel("guard", "❌ لیست سرویس Guard در دسترس نیست. لطفاً دوباره تلاش کنید.");
            step('home', $from_id);
        } else {
            nm_adminInstantReply($from_id, "❌ لیست سرویس Guard در دسترس نیست. لطفاً مجدداً تلاش کنید.", $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
        }
        return;
    }
    if ($mode === 'edit' && $inputText !== '' && !empty($state['panel'])) {
        $panelForRefresh = select("marzban_panel", "*", "name_panel", $state['panel'], "select");
        if (is_array($panelForRefresh) && ($panelForRefresh['type'] ?? '') === "guard") {
            $servicesResponse = guardGetServices($panelForRefresh['name_panel']);
            if (!empty($servicesResponse['status']) && !empty($servicesResponse['services'])) {
                $state['services'] = $servicesResponse['services'];
                $availableRefreshed = guardExtractServiceIdsFromList($state['services']);
                $currentServices = guardParseServiceIds($panelForRefresh['guard_service_ids'] ?? null);
                $state['selected_ids'] = guardNormalizeSelectedServiceIds($currentServices, $availableRefreshed, true);
                $state['select_all'] = in_array('all', $currentServices, true) || in_array(0, $currentServices, true) || count($state['selected_ids']) === count($availableRefreshed);
                if (!empty($state['select_all'])) {
                    $state['selected_ids'] = $availableRefreshed;
                }
                $state['manual_selected_ids'] = guardNormalizeSelectedServiceIds($currentServices, $availableRefreshed, false);
            }
        }
    }
    $availableIds = guardExtractServiceIdsFromList($state['services']);
    $selectedIds = guardNormalizeSelectedServiceIds($state['selected_ids'] ?? [], $availableIds, true);
    $selectAll = !empty($state['select_all']) || (!empty($availableIds) && count($selectedIds) === count($availableIds));
    if ($selectAll) {
        $selectedIds = $availableIds;
    }
    $manualSelected = isset($state['manual_selected_ids']) && is_array($state['manual_selected_ids']) ? guardNormalizeSelectedServiceIds($state['manual_selected_ids'], $availableIds, false) : [];
    if (empty($manualSelected)) {
        $manualSelected = $selectedIds;
    }
    $state['select_all'] = $selectAll;
    $state['selected_ids'] = $selectedIds;
    $state['manual_selected_ids'] = $manualSelected;
    if (!empty($state['message_id'])) {
        deletemessage($from_id, $state['message_id']);
        $state['message_id'] = null;
    }
    savedata("save", "guard_service_selection", $state);
    $message = guardBuildServiceSelectionMessage($state['services'], $selectedIds, $selectAll);
    $keyboard = guardBuildServiceSelectionKeyboard($state['services'], $selectedIds, $mode, $selectAll);
    $messageResponse = sendmessage($from_id, $message, $keyboard, 'HTML');
    if (isset($messageResponse['result']['message_id'])) {
        $state['message_id'] = $messageResponse['result']['message_id'];
        savedata("save", "guard_service_selection", $state);
    }
    return;
} elseif (preg_match('/^guardsettings:(note|auto_delete|auto_renew|save|back)$/', $datain, $matches)) {
    $action = $matches[1];
    $panelName = guardResolveUserPanelName($user);
    $panel = $panelName ? select("marzban_panel", "*", "name_panel", $panelName, "select") : null;
    if (!is_array($panel) || ($panel['type'] ?? null) != "guard") {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این گزینه فقط برای پنل Guard در دسترس است.',
            'show_alert' => true,
            'cache_time' => 3,
        ]);
        return;
    }
    $currentUser = select("user", "*", "id", $from_id, "select");
    $state = guardLoadGuardSettingsState(is_array($currentUser) ? $currentUser : $user, $panel, $message_id);
    $state['message_id'] = $message_id;
    guardPersistGuardSettingsState($from_id, $state);
    if ($action === 'note') {
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, "🧑‍🏫 لطفاً توضیحات خود را ارسال کنید.\nدر صورتی که توضیحی ندارید عبارت « - » را ارسال نمایید.");
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_note', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'دریافت توضیحات',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'auto_delete') {
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, "👩‍🏫 مدت‌زمان حذف خودکار را مشخص کنید\n0 = غیرفعال (حذف خودکار انجام نمی‌شود)\nمثال: 7 → حذف خودکار پس از 7 روز");
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_auto_delete', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منتظر عدد روزها هستم',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'auto_renew') {
        $promptText = $textbotlang['Admin']['managepanel']['guard']['settings_auto_renew'];
        $promptMessageId = guardRenderGuardSettingsPrompt($from_id, $state, $promptText);
        $state['message_id'] = $promptMessageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_auto_renew', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منتظر تنظیم تمدید خودکار هستم',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'save') {
        $panelName = $state['panel'] ?? ($panel['name_panel'] ?? null);
        if ($panelName === null) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'نام پنل Guard مشخص نیست.',
                'show_alert' => true,
                'cache_time' => 3,
            ]);
            return;
        }
        $noteToSave = $state['note'] ?? '';
        $autoDeleteDays = max(0, intval($state['auto_delete_days'] ?? 0));
        $autoRenewals = $state['auto_renewals'] ?? [];
        update("marzban_panel", "guard_note", $noteToSave, "name_panel", $panelName);
        update("marzban_panel", "guard_auto_delete_days", $autoDeleteDays, "name_panel", $panelName);
        update("marzban_panel", "guard_auto_renewals", json_encode($autoRenewals, JSON_UNESCAPED_UNICODE), "name_panel", $panelName);
        $state['note'] = $noteToSave;
        $state['auto_delete_days'] = $autoDeleteDays;
        $state['auto_renewals'] = guardDecodeAutoRenewalsConfig($autoRenewals);
        $state['saved'] = guardExtractSavedGuardSettings([
            'guard_note' => $noteToSave,
            'guard_auto_delete_days' => $autoDeleteDays,
            'guard_auto_renewals' => $autoRenewals,
        ]);
        $state['pending_changes'] = false;
        $messageId = guardRenderGuardSettingsSummary($from_id, $state, true);
        $state['message_id'] = $messageId;
        guardPersistGuardSettingsState($from_id, $state);
        step('guard_settings_summary', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'ذخیره شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
    if ($action === 'back') {
        update("user", "Processing_value_one", "none", "id", $from_id);
        if (!empty($message_id)) {
            deletemessage($from_id, $message_id);
        }
        step('home', $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'منو بسته شد.',
            'show_alert' => false,
            'cache_time' => 3,
        ]);
        return;
    }
} elseif ($user['step'] == "add_password_panel") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
    savedata("save", "password", $text);
} elseif ($user['step'] == "getlimitedpanel") {
    savedata("save", "limitpanel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));


    $rx_panel_version_flag = '0';
    if (isset($userdata['type']) && $userdata['type'] === 'pasargard') {
        $rx_panel_version_flag = '1';
        $userdata['type'] = 'marzban';
        $stmt_fix = $connect->prepare("UPDATE user SET Processing_value = ? WHERE id = ?");
        $rx_userdata_json = json_encode($userdata, JSON_UNESCAPED_UNICODE);
        $stmt_fix->bind_param("ss", $rx_userdata_json, $from_id);
        $stmt_fix->execute();
        $stmt_fix->close();
    }
    if ($userdata['type'] == "x-ui_single" || $userdata['type'] == "alireza") {
        $marzbanprotocol = $randomString;
        $protocols = "vmess";
        $settingpanel = json_encode(array(
            'network' => 'ws',
            'security' => 'none',
            'externalProxy' => array(),
            'wsSettings' => array(
                'acceptProxyProtocol' => false,
                'path' => '/',
                'host' => '',
                'headers' => array()

            ),
        ));
    }
    $sublink = "onsublink";
    $configstatus = "offconfig";
    $MethodUsername = "آیدی عددی + حروف و عدد رندوم";
    $status = "active";
    $ONTestAccount = "ONTestAccount";
    $extendtextadd = "ریست حجم و زمان";
    $namecustoms = "none";
    $type = "marzban";
    $conecton = "offconecton";
    $inboundid = 1;
    $agent = "all";
    $time = "1";
    $valume = "100";
    $changeloc = "offchangeloc";
    $apiKey = isset($userdata['api_key']) ? $userdata['api_key'] : null;
    $guardServiceIds = isset($userdata['guard_service_ids']) ? $userdata['guard_service_ids'] : null;
    if ($userdata['type'] == "guard" && ($guardServiceIds === null || $guardServiceIds === '')) {
        $guardServiceIds = "all";
    }
    $guardNoteSetting = isset($userdata['guard_note']) ? $userdata['guard_note'] : '';
    $guardAutoDeleteDays = isset($userdata['guard_auto_delete_days']) ? intval($userdata['guard_auto_delete_days']) : 0;
    $guardAutoRenewalsSetting = isset($userdata['guard_auto_renewals']) ? json_encode($userdata['guard_auto_renewals'], JSON_UNESCAPED_UNICODE) : json_encode([]);
    if ($userdata['type'] != "guard") {
        $guardNoteSetting = null;
        $guardAutoDeleteDays = 0;
        $guardAutoRenewalsSetting = null;
    }
    if ($userdata['type'] == "guard") {
        $guardColumnsCheck = ensureGuardPanelColumnsReady($pdo);
        if ($guardColumnsCheck['status'] === false) {
            $missingList = implode(', ', $guardColumnsCheck['missing']);
            $warningMessage = "❌ امکان ثبت پنل Guard وجود ندارد. ستون‌های موردنیاز یافت نشدند: {$missingList}\nلطفاً یکبار دیگر صفحه را اجرا کنید تا مهاجرت خودکار انجام شود و سپس مجدداً تلاش نمایید.";
            nm_adminInstantReply($from_id, $warningMessage, $backadmin, 'HTML');
            step('add_guard_api_key', $from_id);
            return;
        }
    }
    $value = json_encode(array(
        'f' => "4000",
        'n' => "4000",
        'n2' => "4000"
    ));
    $valuemain = json_encode(array(
        'f' => "1",
        'n' => "1",
        'n2' => "1"
    ));
    $valuemax = json_encode(array(
        'f' => "1000",
        'n' => "1000",
        'n2' => "1000"
    ));
    $VALUE = json_encode(array(
        'f' => '0',
        'n' => '0',
        'n2' => '0'
    ));
    $valuestatusin = "offinbounddisable";
    $statusextend = "on_extend";
    $subvip = "offsubvip";
    $stauts_on_holed = "1";
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,api_key,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test,version_panel,guard_service_ids,guard_note,guard_auto_delete_days,guard_auto_renewals) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:api_key,:val_usertest,:time_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test,:version_panel,:guard_service_ids,:guard_note,:guard_auto_delete_days,:guard_auto_renewals)");
    $stmt->bindParam(':code_panel', $randomString);
    $stmt->bindParam(':name_panel', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->bindParam(':sublink', $sublink);
    $stmt->bindParam(':config', $configstatus);
    $stmt->bindParam(':MethodUsername', $MethodUsername);
    $stmt->bindParam(':TestAccount', $ONTestAccount);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':limit_panel', $text);
    $stmt->bindParam(':namecustom', $namecustoms);
    $stmt->bindParam(':Methodextend', $extendtextadd);
    $stmt->bindParam(':type', $userdata['type'], PDO::PARAM_STR);
    $stmt->bindParam(':conecton', $conecton);
    $stmt->bindParam(':inboundid', $inboundid);
    $stmt->bindParam(':agent', $agent);
    $stmt->bindParam(':inbound_deactive', $inboundid);
    $stmt->bindParam(':inboundstatus', $valuestatusin);
    $stmt->bindParam(':url_panel', $userdata['url_panel']);
    $stmt->bindParam(':linksubx', $userdata['url_panel']);
    $stmt->bindParam(':username_panel', $userdata['username']);
    $stmt->bindParam(':password_panel', $userdata['password']);
    $stmt->bindParam(':api_key', $apiKey);
    $stmt->bindParam(':val_usertest', $valume);
    $stmt->bindParam(':time_usertest', $time);
    $stmt->bindParam(':priceextravolume', $value);
    $stmt->bindParam(':priceextratime', $value);
    $stmt->bindParam(':pricecustomtime', $value);
    $stmt->bindParam(':pricecustomvolume', $value);
    $stmt->bindParam(':mainvolume', $valuemain);
    $stmt->bindParam(':maxvolume', $valuemax);
    $stmt->bindParam(':maintime', $valuemain);
    $stmt->bindParam(':maxtime', $valuemax);
    $stmt->bindParam(':status_extend', $statusextend);
    $stmt->bindParam(':subvip', $subvip);
    $stmt->bindParam(':changeloc', $changeloc);
    $stmt->bindParam(':customvolume', $VALUE);
    $stmt->bindParam(':on_hold_test', $stauts_on_holed);
    $stmt->bindParam(':version_panel', $rx_panel_version_flag, PDO::PARAM_STR);
    $stmt->bindParam(':guard_service_ids', $guardServiceIds);
    $stmt->bindParam(':guard_note', $guardNoteSetting);
    $stmt->bindParam(':guard_auto_delete_days', $guardAutoDeleteDays);
    $stmt->bindParam(':guard_auto_renewals', $guardAutoRenewalsSetting);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    nm_adminInstantReply($from_id, "🥳", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if ($userdata['type'] == "x-ui_single" or $userdata['type'] == "alireza_single") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم شناسه اینباند و دامنه لینک ساب را حتما تنظیم نمایید در غیراینصورت کانفیگ ساخته نخواهد شد", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
    } elseif ($userdata['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های
منوی تنظیم شناسه اینباند رفته و نام کانفیگ را تنظیم نمایید در غیراینصورت ربات هیچ کانفیگی نمیسازد", null, 'HTML');
    } elseif ($userdata['type'] == "ibsng") {
        nm_adminInstantReply($from_id, "❌ نکته :
برای فعالسازی باید از مدیریت پنل > تنظیم نام گروه یک نام پیشفرض گروه که در ibsng تعریف کردید در ربات بفرستید.", null, 'HTML');
    } elseif ($userdata['type'] == "mikrotik") {
        nm_adminInstantReply($from_id, "❌ نکته :
۱ - حتما باید پلاگین اکانتینگ در میکروتیک شما نصب باشد
۲ - در بخش ip » servies » http or https باید فعال باشد ( اگر ssl تهیه کردید https روشن باشد در غیراینصورت http)", null, 'HTML');
    } elseif ($userdata['type'] == "hiddify") {
        nm_adminInstantReply($from_id, "❌ نکته :
1 - از مدیریت پنل گزینه های زیر را تنظیم کنید

1 - uuid admin : uuid ادمین از پنل دریافت و ثبت کنید
2-  دامنه لینک ساب :‌ دامنه لینک ساب پنل هیدیفای را ارسال نمایید ", null, 'HTML');
    } elseif ($userdata['type'] == "s_ui") {
        nm_adminInstantReply($from_id, "❌ نکته :
1 - از مسیر مدیریت پنل > تنظیم ⚙️ تنظیم پروتکل و اینباند یک نام کاربری کانفیگ را ارسال نمایید.", null, 'HTML');
    }
}

elseif ($datain == "systemsms") {


    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard(),
            'HTML'
        );
        return;
    }


    if (!is_file('cronbot/users.json')) {
        @file_put_contents('cronbot/users.json', json_encode([]));
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage'],
            ],
            [
                ['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage'],
            ],
            [
                ['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage'],
            ],
            [
                ['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
} elseif ($datain == "broadcast_status_refresh") {


    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus === null) {
        Editmessagetext(
            $from_id,
            $message_id,
            "✅ عملیات ارسال پیامی در حال انجام نیست.\n\nبرای شروع یک ارسال جدید از منوی اصلی وارد بخش پیام‌رسانی شوید.",
            json_encode([
                'inline_keyboard' => [
                    [['text' => "📨 منوی پیام‌رسانی",  'callback_data' => 'systemsms']],
                    [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
                ]
            ]),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => 'عملیاتی در حال انجام نیست.',
                'show_alert'        => false,
                'cache_time'        => 1,
            ]);
        }
        return;
    }
    Editmessagetext(
        $from_id,
        $message_id,
        nm_buildBroadcastStatusText($broadcastStatus),
        nm_buildBroadcastStatusKeyboard(),
        'HTML'
    );
    if (!empty($callback_query_id)) {
        $toast = '🚀 ارسال‌شده: ' . number_format((int) $broadcastStatus['sent'])
               . ' | 📊 باقی‌مانده: ' . number_format((int) $broadcastStatus['remaining']);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text'              => $toast,
            'show_alert'        => false,
            'cache_time'        => 1,
        ]);
    }
    return;
} elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {


    $broadcastStatus = function_exists('nm_getBroadcastStatus') ? nm_getBroadcastStatus() : null;
    if ($broadcastStatus !== null) {
        Editmessagetext(
            $from_id,
            $message_id,
            nm_buildBroadcastStatusText($broadcastStatus),
            nm_buildBroadcastStatusKeyboard(),
            'HTML'
        );
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => '⏳ یک عملیات ارسال در حال انجام است.',
                'show_alert'        => true,
                'cache_time'        => 1,
            ]);
        }
        return;
    }
    $type = $dataget[1];
    savedata("clear", "typeservice", $type);
    if ($type == "unpinmessage") {
        deletemessage($from_id, $message_id);
        $typesend = [
            "unpinmessage" => "لغو پیام پین شده"
        ][$type];
        $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend";
        $startaction = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
                ],
            ]
        ]);
        nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
        nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeusermessage-all'],
            ],
            [
                ['text' => "مشتریانی که خرید داشتند", 'callback_data' => 'typeusermessage-customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typeusermessage-nonecustomer'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'systemsms'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای کدام گروه کاربری اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typeusermessage", $dataget[1]);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "کاربران گروه n", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "کاربران گروه n2", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای چه دسته از کاربران اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $type);
    if ($userdata['typeusermessage'] == "customer") {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
        $stmt->bindParam(':agent', $type);
        $stmt->execute();
        $list_panel = ['inline_keyboard' => []];
        $list_panel['inline_keyboard'][] = [['text' => "تمامی پنل ها", 'callback_data' => 'locationmessage_all']];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_panel['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
            ];
        }
        $list_panel['inline_keyboard'][] = [['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],];
        Editmessagetext($from_id, $message_id, "📌 پیام برای کدام کاربران موجود در پنل های زیر ارسال شود.", json_encode($list_panel));
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
    $typeoanel = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "selectpanel", $typeoanel);
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typepinmessage", $type);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
            ],
            [
                ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
            ],
            [
                ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
            ],
            [
                ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
            ],
        ]
    ]);
    if ($userdata['typeservice'] == "forwardmessage") {
        step("gettextSystemMessage", $from_id);
        nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
} elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $type = $dataget[1];
    savedata("save", "btntypemessage", $type);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        nm_adminInstantReply($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextday") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "daynoyuse", $text);
    step("gettextSystemMessage", $from_id);
    nm_adminInstantReply($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextSystemMessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "forwardmessage") {
        savedata("save", "message", $message_id);
    } elseif ($userdata['typeservice'] == "xdaynotmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش کاربرانی که به تعداد روز تعیین شده استفاده نکردند فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    } elseif ($userdata['typeservice'] == "sendmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            nm_adminInstantReply($from_id, "📌  در بخش ارسال همگانی فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    }
    $typesend = [
        "xdaynotmessage" => "کاربرانی که به تعداد روز تعیین شده استفاده نکردند",
        "sendmessage" => "ارسال همگانی",
        "forwardmessage" => "فوروارد همگانی",
        "unpinmessage" => "لغو پیام پین شده"
    ][$userdata['typeservice']];
    $typeservice = [
        "all" => "ارسال به همه کاربران",
        "customer" => "مشتریان",
        "nonecustomer" => "کسانی که خرید نداشتند",
    ][$userdata['typeusermessage']];
    if ($userdata['typeservice'] == "xdaynotmessage") {
        $textday = "تعداد روزی که کاربر پیام نداده است : {$userdata['daynoyuse']}";
    } else {
        $textday = "";
    }
    $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend
🎛 نوع سرویس : $typeservice
🗂 نوع کاربری : {$userdata['agent']}
$textday
";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, $textconfirm, $startaction, 'HTML');
    nm_adminInstantReply($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "startaction") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    $agent = $userdata['agent'];
    $typeservice = $userdata['typeservice'];
    $typeusermessage = $userdata['typeusermessage'];
    $text = $userdata['message'];
    $cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);


    @ini_set('memory_limit', '1G');
    @set_time_limit(300);


    if (!function_exists('nm_writeBroadcastQueueFromJson')) {
        function nm_writeBroadcastQueueFromJson($jsonStr) {
            $arr = is_string($jsonStr) ? json_decode($jsonStr) : $jsonStr;
            if (!is_array($arr)) {
                return 0;
            }
            $tmp = "cronbot/users.txt.new";
            $fh  = @fopen($tmp, 'w');
            if (!$fh) {
                return 0;
            }
            $count = 0;
            foreach ($arr as $row) {
                $id = null;
                if (is_object($row) && isset($row->id))     $id = $row->id;
                elseif (is_array($row) && isset($row['id']))$id = $row['id'];
                elseif (is_scalar($row))                    $id = $row;
                if ($id !== null && $id !== '' && is_numeric($id)) {
                    fwrite($fh, ((string) $id) . "\n");
                    $count++;
                }
            }
            fclose($fh);
            @unlink('cronbot/users.json');
            @unlink('cronbot/users.txt');
            @rename($tmp, 'cronbot/users.txt');


            $arr = null;
            unset($arr);
            return $count;
        }
    }

    if ($typeservice == "unpinmessage") {
        $userlist = json_encode(select("user", "id", null, null, "fetchAll"));
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $dataunpin = json_encode(array(
            "id_admin" => $from_id,
            'type' => "unpinmessage",
            "id_message" => $message_id['result']['message_id']
        ));
        nm_writeBroadcastQueueFromJson($userlist); $userlist = null;
        file_put_contents('cronbot/info', $dataunpin);
    } elseif ($typeservice == "sendmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "forwardmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "forwardmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
        ));
        nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "xdaynotmessage") {
        $timedaystamp = intval($userdata['daynoyuse']) * 86400;
        $timenouser = time() - $timedaystamp;
        if ($agent == "all") {
            $stmt = $pdo->prepare("SELECT id FROM user  WHERE last_message_time < $timenouser");
            $stmt->execute();
            $userslist = json_encode($stmt->fetchAll());
        } else {
            if ($typeusermessage == "all") {
                if ($typeusermessage == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "customer") {
                    if ($userdata['selectpanel'] == "all") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    } else {
                        $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                    }
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "nonecustomer") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                }
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "xdaynotmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        nm_writeBroadcastQueueFromJson($userslist); $userslist = null;
        file_put_contents('cronbot/info', $data);
    }
} elseif ($datain == "cancel_sendmessage") {
    @file_put_contents('users.json', json_encode(array()));
    @unlink('cronbot/users.json');
    @unlink('cronbot/users.txt');
    @unlink('cronbot/users.txt.new');
    @unlink('cronbot/users.txt.tail.tmp');
    @unlink('cronbot/info');
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "📌 ارسال پیام لغو گردید.", null, 'HTML');
}

elseif ($text == "📝 تنظیم متن ربات" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $textbot, 'HTML');
} elseif ($text == "تنظیم متن شروع" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_start']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "📌 متغییر های قابل استفاده

⚠️نام کاربری :
 <blockquote>{username}</blockquote>

⚠️نام اکانت :‌
<blockquote>{first_name}</blockquote>

⚠️نام خانوادگی اکانت :‌
<blockquote>{last_name}</blockquote>

⚠️زمان فعلی :
<blockquote>{time}</blockquote>

⚠️ نسخه فعلی ربات  :
<blockquote>{version}</blockquote>", null, "html");
    step('changetextstart', $from_id);
} elseif ($user['step'] == "changetextstart") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_start");
    step('home', $from_id);
} elseif ($text == "دکمه سرویس خریداری شده" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Purchased_services']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('changetextinfo', $from_id);
} elseif ($user['step'] == "changetextinfo") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Purchased_services");
    step('home', $from_id);
} elseif ($text == "دکمه اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_usertest']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('changetextusertest', $from_id);
} elseif ($user['step'] == "changetextusertest") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_usertest");
    step('home', $from_id);
} elseif ($text == "متن دکمه 📚 آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_help']}</code>", $backadmin, 'HTML');
    step('text_help', $from_id);
} elseif ($user['step'] == "text_help") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_help");
    step('home', $from_id);
} elseif ($text == "متن درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textrequestagent']}</code>", $backadmin, 'HTML');
    step('textrequestagent', $from_id);
} elseif ($user['step'] == "premium_emoji_get_char" && $adminrulecheck['rule'] == "administrator") {


    @error_log('[premium_emoji_get_char] handler entered, from_id=' . (int)$from_id);
    try {
        $rxPemRawText = is_string($text) ? trim($text) : '';
        $rxPemSticker = $update['message']['sticker'] ?? null;


        $rxPemBase = '';
        if ($rxPemRawText !== '') {
            $rxPemBase = $rxPemRawText;
        } elseif (is_array($rxPemSticker) && !empty($rxPemSticker['emoji'])) {
            $rxPemBase = (string)$rxPemSticker['emoji'];
        }

        if ($rxPemBase === '' || mb_strlen($rxPemBase, 'UTF-8') > 50) {
            nm_adminInstantReply($from_id, "❌ ایموجی نامعتبر است.\n\nلطفاً یک <b>ایموجی عادی</b> ارسال کنید (مثل ✅، ❌، 🔥، 💎).", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }


        update("user", "Processing_value", $rxPemBase, "id", $from_id);


        nm_adminInstantReply($from_id, "✅ <b>ایموجی پایه ذخیره شد:</b> {$rxPemBase}\n\n📌 حالا <b>ایموجی پرمیوم متناظر</b> را ارسال کنید.\n\nربات خودکار آیدی آن را تشخیص می‌دهد و این دو را به هم متصل می‌کند ✨\n\n💡 می‌توانید ایموجی پرمیوم را به هر شکلی بفرستید: متن، استیکر، Forward یا Reply.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('premium_emoji_get_id', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_char] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_get_id" && $adminrulecheck['rule'] == "administrator") {


    @error_log('[premium_emoji_get_id] handler entered, from_id=' . (int)$from_id);
    try {
        $rxPemBase = (string)($user['Processing_value'] ?? '');
        if ($rxPemBase === '') {
            nm_adminInstantReply($from_id, "❌ ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }


        $rxPemTableOk = true;
        try {
            $rxPemCheck = $pdo->query("SHOW TABLES LIKE 'premium_emojis'");
            $rxPemTableOk = ($rxPemCheck && $rxPemCheck->fetchColumn() !== false);
        } catch (\Throwable $rxPemTblErr) { $rxPemTableOk = false; }
        if (!$rxPemTableOk) {
            nm_adminInstantReply($from_id, "❌ <b>جدول دیتابیس آماده نیست</b>\n\nقبل از این، باید <code>table.php</code> را در مرورگر اجرا کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            step('home', $from_id);
            return;
        }


        $rxPemCid = '';


        $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
        if (is_array($rxPemEntities)) {
            foreach ($rxPemEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }


        if ($rxPemCid === '') {
            $rxPemSticker = $update['message']['sticker'] ?? null;
            if (is_array($rxPemSticker) && ($rxPemSticker['type'] ?? '') === 'custom_emoji'
                && !empty($rxPemSticker['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
            }
        }


        if ($rxPemCid === '') {
            $rxPemReply = $update['message']['reply_to_message'] ?? null;
            if (is_array($rxPemReply)) {
                $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
                if (is_array($rxPemReplyEntities)) {
                    foreach ($rxPemReplyEntities as $rxPemEnt) {
                        if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                            $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                            break;
                        }
                    }
                }
                if ($rxPemCid === '') {
                    $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
                    if (is_array($rxPemReplySticker)
                        && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
                        && !empty($rxPemReplySticker['custom_emoji_id'])) {
                        $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
                    }
                }
            }
        }


        if ($rxPemCid === '' && is_string($text)) {
            $rxPemCandidate = trim($text);
            if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
                $rxPemCid = $rxPemCandidate;
            }
        }

        if ($rxPemCid === '') {

            @error_log('[premium_emoji_get_id] no id found; message=' . substr(json_encode($update['message'] ?? null), 0, 1500));
            $rxPemDiag = '';
            $rxPemSentText = is_string($text) ? trim($text) : '';
            $rxPemStkType = is_array($update['message']['sticker'] ?? null) ? ($update['message']['sticker']['type'] ?? '') : '';
            if ($rxPemStkType !== '' && $rxPemStkType !== 'custom_emoji') {
                $rxPemDiag = "🔎 شما یک <b>استیکر معمولی</b> فرستادید (نوع آن custom_emoji نیست).";
            } elseif ($rxPemSentText !== '' && mb_strlen($rxPemSentText, 'UTF-8') <= 4) {
                $rxPemDiag = "🔎 شما یک <b>ایموجی عادی</b> فرستادید: <b>{$rxPemSentText}</b>\nاین فاقد متادیتای پرمیوم است.";
            } elseif (ctype_digit($rxPemSentText)) {
                $rxPemDiag = "🔎 آیدی عددی نامعتبر است (طول باید بین ۸ تا ۳۰ رقم باشد).";
            }
            if ($rxPemDiag !== '') { $rxPemDiag .= "\n\n"; }
            nm_adminInstantReply($from_id, "❌ ایموجی پرمیوم یافت نشد.\n\n{$rxPemDiag}📌 لطفاً <b>ایموجی پرمیوم</b> را برای ایموجی پایه «{$rxPemBase}» ارسال کنید.\n\n💡 یا اگر آیدی عددی پرمیوم را دارید، آن را پیست کنید.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }


        $rxPemNow = time();
        $rxPemAlreadyExists = false;
        try {
            $rxPemIns = $pdo->prepare("INSERT INTO premium_emojis (emoji, custom_emoji_id, created_at, updated_at) VALUES (:e, :c, :t1, :t2)");
            $rxPemIns->execute([':e' => $rxPemBase, ':c' => $rxPemCid, ':t1' => $rxPemNow, ':t2' => $rxPemNow]);
        } catch (\Throwable $rxPemInsErr) {

            if (stripos($rxPemInsErr->getMessage(), 'Duplicate') !== false || stripos($rxPemInsErr->getMessage(), '1062') !== false) {
                $rxPemAlreadyExists = true;
                try {
                    $rxPemTouch = $pdo->prepare("UPDATE premium_emojis SET updated_at = :t WHERE emoji = :e AND custom_emoji_id = :c");
                    $rxPemTouch->execute([':t' => $rxPemNow, ':e' => $rxPemBase, ':c' => $rxPemCid]);
                } catch (\Throwable $rxPemTouchErr) {  }
            } else {
                throw $rxPemInsErr;
            }
        }
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }

        if ($rxPemAlreadyExists) {
            $rxPemSuccessMsg = "ℹ️ <b>این ایموجی پرمیوم با همین آیدی قبلاً ثبت شده بود</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "هیچ ردیف تکراری اضافه نشد.";
        } else {
            $rxPemSuccessMsg = "✅ <b>ایموجی پرمیوم جدید با موفقیت اضافه شد!</b>\n\n"
                . "• ایموجی پایه: {$rxPemBase}\n"
                . "• 🆔 آیدی پرمیوم: <code>{$rxPemCid}</code>\n\n"
                . "✨ از این لحظه، در پیام‌های ربات «{$rxPemBase}» به نسخه پرمیوم تبدیل می‌شود.\n\n"
                . "💡 می‌توانید برای همین «{$rxPemBase}» آیدی‌های پرمیوم بیشتری هم اضافه کنید — جدیدترین آیدی به‌صورت فعال استفاده می‌شود و قبلی‌ها در لیست باقی می‌مانند.";
        }
        nm_adminInstantReply($from_id, $rxPemSuccessMsg, json_encode([
            'inline_keyboard' => [
                [['text' => "➕ افزودن ایموجی دیگر", 'callback_data' => "premium_emoji_add"]],
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
        step('home', $from_id);
    } catch (\Throwable $rxPemErr) {
        @error_log('[premium_emoji_get_id] EXCEPTION: ' . $rxPemErr->getMessage() . ' @ ' . $rxPemErr->getFile() . ':' . $rxPemErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ذخیره ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "premium_emoji_edit_id" && $adminrulecheck['rule'] == "administrator") {


    @error_log('[premium_emoji_edit_id] handler entered, from_id=' . (int)$from_id);
    try {
    $rxPemEmojiChar = (string)($user['Processing_value'] ?? '');
    if ($rxPemEmojiChar === '') {
        nm_adminInstantReply($from_id, "❌ خطا: ایموجی هدف یافت نشد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $rxPemCid = '';
    $rxPemEntities = $update['message']['entities'] ?? $update['message']['caption_entities'] ?? [];
    if (is_array($rxPemEntities)) {
        foreach ($rxPemEntities as $rxPemEnt) {
            if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                break;
            }
        }
    }

    $rxPemSticker = $update['message']['sticker'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemSticker)) {
        if (($rxPemSticker['type'] ?? '') === 'custom_emoji' && !empty($rxPemSticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemSticker['custom_emoji_id'];
        }
    }

    $rxPemReply = $update['message']['reply_to_message'] ?? null;
    if ($rxPemCid === '' && is_array($rxPemReply)) {
        $rxPemReplyEntities = $rxPemReply['entities'] ?? $rxPemReply['caption_entities'] ?? [];
        if (is_array($rxPemReplyEntities)) {
            foreach ($rxPemReplyEntities as $rxPemEnt) {
                if (($rxPemEnt['type'] ?? '') === 'custom_emoji' && !empty($rxPemEnt['custom_emoji_id'])) {
                    $rxPemCid = (string)$rxPemEnt['custom_emoji_id'];
                    break;
                }
            }
        }
        $rxPemReplySticker = $rxPemReply['sticker'] ?? null;
        if ($rxPemCid === '' && is_array($rxPemReplySticker)
            && ($rxPemReplySticker['type'] ?? '') === 'custom_emoji'
            && !empty($rxPemReplySticker['custom_emoji_id'])) {
            $rxPemCid = (string)$rxPemReplySticker['custom_emoji_id'];
        }
    }
    if ($rxPemCid === '' && is_string($text)) {
        $rxPemCandidate = trim($text);
        if (ctype_digit($rxPemCandidate) && strlen($rxPemCandidate) >= 8 && strlen($rxPemCandidate) <= 30) {
            $rxPemCid = $rxPemCandidate;
        }
    }
    if ($rxPemCid === '') {
        nm_adminInstantReply($from_id, "❌ آیدی ایموجی پرمیوم یافت نشد.\n\n📌 لطفاً <b>ایموجی پرمیوم</b> را ارسال کنید یا آیدی عددی را وارد نمایید.", null, 'HTML');
        return;
    }
    $rxPemNow = time();
    $rxPemStmt = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE emoji = :e");
    $rxPemStmt->execute([':e' => $rxPemEmojiChar, ':c' => $rxPemCid, ':t' => $rxPemNow]);
    if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    nm_adminInstantReply($from_id, "✅ ایموجی پرمیوم برای [{$rxPemEmojiChar}] به‌روزرسانی شد.\n\n🆔 <code>{$rxPemCid}</code>", json_encode([
        'inline_keyboard' => [
            [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
        ]
    ]), 'HTML');
    step('home', $from_id);
    } catch (\Throwable $rxPemEditErr) {
        @error_log('[premium_emoji_edit_id] EXCEPTION: ' . $rxPemEditErr->getMessage() . ' @ ' . $rxPemEditErr->getFile() . ':' . $rxPemEditErr->getLine());
        $rxPemErrMsg = htmlspecialchars($rxPemEditErr->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        nm_adminInstantReply($from_id, "⚠️ <b>خطای داخلی هنگام ویرایش ایموجی پرمیوم</b>\n\n<code>{$rxPemErrMsg}</code>", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
} elseif ($user['step'] == "textrequestagent") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textrequestagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه  نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textpanelagent']}</code>", $backadmin, 'HTML');
    step('textpanelagent', $from_id);
} elseif ($user['step'] == "textpanelagent") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textpanelagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه ☎️ پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_support']}</code>", $backadmin, 'HTML');
    step('text_support', $from_id);
} elseif ($user['step'] == "text_support") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_support");
    step('home', $from_id);
} elseif ($text == "دکمه سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_fq']}</code>", $backadmin, 'HTML');
    step('text_fq', $from_id);
} elseif ($user['step'] == "text_fq") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_fq']}</code>", $backadmin, 'HTML');
    step('text_dec_fq', $from_id);
} elseif ($user['step'] == "text_dec_fq") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات عضویت اجباری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_channel']}</code>", $backadmin, 'HTML');
    step('text_channel', $from_id);
} elseif ($user['step'] == "text_channel") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_channel");
    step('home', $from_id);
} elseif ($text == "متن دکمه کیف پول" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['accountwallet']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('accountwallet', $from_id);
} elseif ($user['step'] == "accountwallet") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "accountwallet");
    step('home', $from_id);
} elseif ($text == "متن دکمه کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Discount']}</code>";
    nm_adminInstantReply($from_id, $textstart, $backadmin, 'HTML');
    step('text_Discount', $from_id);
} elseif ($user['step'] == "text_Discount") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Discount");
    step('home', $from_id);
} elseif ($text == "دکمه افزایش موجودی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Add_Balance']}</code>", $backadmin, 'HTML');
    step('text_Add_Balance', $from_id);
} elseif ($user['step'] == "text_Add_Balance") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Add_Balance");
    step('home', $from_id);
} elseif ($text == "متن دکمه خرید اشتراک" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_sell']}</code>", $backadmin, 'HTML');
    step('text_sell', $from_id);
} elseif ($user['step'] == "text_sell") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_sell");
    step('home', $from_id);
} elseif ($text == "متن دکمه زیرمجموعه گیری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_affiliates']}</code>", $backadmin, 'HTML');
    step('text_affiliates', $from_id);
} elseif ($user['step'] == "text_affiliates") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_affiliates");
    step('home', $from_id);
} elseif ($text == "متن دکمه لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_Tariff_list', $from_id);
} elseif ($user['step'] == "text_Tariff_list") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن توضیحات لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_dec_Tariff_list', $from_id);
} elseif ($user['step'] == "text_dec_Tariff_list") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن انتخاب لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textselectlocation']}</code>", $backadmin, 'HTML');
    step('textselectlocation', $from_id);
} elseif ($user['step'] == "textselectlocation") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textselectlocation");
    step('home', $from_id);
} elseif ($text == "متن پیش فاکتور" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_pishinvoice']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_product : نام محصول
Service_time : زمان سرویس
price : قیمت سرویس
Volume : حجم سرویس
userBalance : موجودی کاربر
note : یادداشت

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_pishinvoice', $from_id);
} elseif ($user['step'] == "text_pishinvoice") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_pishinvoice");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpay']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytext', $from_id);
} elseif ($user['step'] == "text_afterpaytext") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpay");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید ibsng" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpayibsng']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytextibsng', $from_id);
} elseif ($user['step'] == "text_afterpaytextibsng") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpayibsng");
    step('home', $from_id);
} elseif ($text == "متن کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
price : مبلغ تراکنش
card_number : شماره کارت
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart', $from_id);
} elseif ($user['step'] == "text_cart") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart");
    step('home', $from_id);
} elseif ($text == "تنظیم متن کارت به کارت خودکار" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart_auto']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
price : مبلغ تراکنش
card_number : شماره کارت
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart_auto', $from_id);
} elseif ($user['step'] == "text_cart_auto") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart_auto");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textaftertext']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک اتصال
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_aftertesttext', $from_id);
} elseif ($user['step'] == "text_aftertesttext") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textaftertext");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($text == "متن کرون تست" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['crontest']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_crontest', $from_id);
} elseif ($user['step'] == "text_crontest") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "crontest");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($user['step'] == "text_textmanual") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textmanual");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت WGDashboard" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_wgdashboard']}</code>", $backadmin, 'HTML');
    nm_adminInstantReply($from_id, "نام های فارسی متغییر :
username : نام کاربری کانفیگ
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_wgdashboard', $from_id);
} elseif ($user['step'] == "text_wgdashboard") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wgdashboard");
    step('home', $from_id);
} elseif ($text == "دکمه تمدید" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_extend']}</code>", $backadmin, 'HTML');
    step('text_extend', $from_id);
} elseif ($user['step'] == "text_extend") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_extend");
    step('home', $from_id);
} elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "iduser", $iduser);
    nm_adminInstantReply($from_id, "📌 متن یا تصویر خود را ارسال نمایید", $backadmin, 'HTML');
    step('sendmessagetext', $from_id);
} elseif ($user['step'] == "sendmessagetext") {
    if ($photo) {
        savedata("save", "type", "photo");
        savedata("save", "photoid", $photoid);
        savedata("save", "text", $caption);
    } else {
        savedata("save", "text", $text);
        savedata("save", "type", "text");
    }
    $textb = "📌 کاربر بتواند پاسخ دهد یاخیر ؟
1 - بله  پاسخ دهد
2 - خیر پاسخ ندهد
پاسخ را به عدد ارسال کنید";
    nm_adminInstantReply($from_id, $textb, $backadmin, 'HTML');
    step('sendmessagetid', $from_id);
} elseif ($user['step'] == "sendmessagetid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $textsendadmin = "
👤 یک پیام از طرف ادمین ارسال شده است
متن پیام:

{$userdata['text']}";
    if (intval($text) == "1") {
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                ],
            ]
        ]);
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'reply_markup' => $Response,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
        }
    } else {
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
        }
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📤 فوروارد پیام برای یک کاربر") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
    step('getmessageforward', $from_id);
} elseif ($user['step'] == "getmessageforward") {
    savedata("clear", "messageid", $message_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
    step('getbtnresponseforward', $from_id);
} elseif ($user['step'] == "getbtnresponseforward") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    forwardMessage($from_id, $userdata['messageid'], $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 بخش آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
} elseif ($text == "📚 اضافه کردن آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
    step('add_name_help', $from_id);
} elseif ($user['step'] == "add_name_help") {
    if (strlen($text) >= 150) {
        nm_adminInstantReply($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    $helpexits = select("help", "*", "name_os", $text, "count");
    if ($helpexits != 0) {
        nm_adminInstantReply($from_id, "❌ نام آموزش وجود دارد از نام دیگری استفاده نمایید.", null, 'HTML');
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    update("user", "Processing_value", $text, "id", $from_id);
    if ($setting['categoryhelp'] == "0") {
        update("help", "category", "0", "name_os", $user['Processing_value']);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
        step('add_dec', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "📌 نام دسته بندی برای آموزش را ارسال نمایید", $backadmin, 'HTML');
    step('getcatgoryhelp', $from_id);
} elseif ($user['step'] == "getcatgoryhelp") {
    update("help", "category", $text, "name_os", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
    step('add_dec', $from_id);
} elseif ($user['step'] == "add_dec") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($text) {
        update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    } elseif ($document) {
        if (isset($fileid))
            update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف آموزش" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step('remove_help', $from_id);
} elseif ($user['step'] == "remove_help") {
    $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
    $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
} elseif ($user['step'] == "getmessageAsAdmin") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
    $Respuseronse = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
            ],
        ]
    ]);
    if ($text) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
        sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
    }
    if ($photo) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$caption";
        telegram('sendphoto', [
            'chat_id' => $user['Processing_value'],
            'photo' => $photoid,
            'reply_markup' => $Respuseronse,
            'caption' => $textSendAdminToUser,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
} elseif (
    ($text == "⚙️ وضعیت قابلیت ها" && $adminrulecheck['rule'] == "administrator")
    || (in_array((string)($datain ?? ''), ['featcat_main','featcat_bot','featcat_users','featcat_shop','featcat_lottery','featcat_crons','featcat_antispam'], true) && $adminrulecheck['rule'] == "administrator")
) {
    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $setting = select("setting", "*", null, null, "select");
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = $infocardStatusValue === '1'
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $premiumEmojiStatusValue = (string)($setting['premium_emoji_status'] ?? '0');
    $premiumEmojiStatusText = ($premiumEmojiStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $premiumEmojiCount = 0;
    try {
        $rxPemRow = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> ''")->fetch(PDO::FETCH_ASSOC);
        $premiumEmojiCount = (int)($rxPemRow['c'] ?? 0);
    } catch (\Throwable $rxPemErr) { $premiumEmojiCount = 0; }

    $rxAsStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsStatusText  = ($rxAsStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsMsgCountVal = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCountVal < 1)    { $rxAsMsgCountVal = 1; }
    if ($rxAsMsgCountVal > 1000) { $rxAsMsgCountVal = 1000; }
    $rxAsSecondsVal = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSecondsVal < 1)    { $rxAsSecondsVal = 1; }
    if ($rxAsSecondsVal > 3600) { $rxAsSecondsVal = 3600; }
    $rxAsMuteSecondsVal = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSecondsVal < 1)     { $rxAsMuteSecondsVal = 1; }
    if ($rxAsMuteSecondsVal > 86400) { $rxAsMuteSecondsVal = 86400; }


    $rxFeatView = 'main';
    $rxDatainStr = (string)($datain ?? '');
    if ($rxDatainStr === 'featcat_bot')       $rxFeatView = 'bot';
    elseif ($rxDatainStr === 'featcat_users') $rxFeatView = 'users';
    elseif ($rxDatainStr === 'featcat_shop')  $rxFeatView = 'shop';
    elseif ($rxDatainStr === 'featcat_lottery') $rxFeatView = 'lottery';
    elseif ($rxDatainStr === 'featcat_crons') $rxFeatView = 'crons';
    elseif ($rxDatainStr === 'featcat_antispam') $rxFeatView = 'antispam';


    $rxBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']];

    if ($rxFeatView === 'main') {

        $rxFeatKb = [
            [['text' => "🤖 آپشن‌های اصلی ربات",       'callback_data' => 'featcat_bot']],
            [['text' => "👥 کاربران و پشتیبانی",        'callback_data' => 'featcat_users']],
            [['text' => "🛍 فروش و خدمات",              'callback_data' => 'featcat_shop']],
            [['text' => "🎁 گردونه و قرعه‌کشی",          'callback_data' => 'featcat_lottery']],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",          'callback_data' => 'featcat_crons']],
            [['text' => "🌟 ایموجی پرمیوم ({$premiumEmojiCount})", 'callback_data' => 'premium_emoji_settings']],
            [['text' => "🛡 آنتی اسپم", 'callback_data' => 'featcat_antispam']],
            [['text' => "❌ بستن", 'callback_data' => 'close_stat']],
        ];
        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟\n\n💡 برای تنظیمات هر بخش، روی دکمه دسته بزنید.";
    } elseif ($rxFeatView === 'bot') {
        $rxFeatKb = [
            [['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
             ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"]],
            [['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
             ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"]],
            [['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
             ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"]],
            [['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"],
             ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"]],
            [['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
             ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"]],
            [['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
             ['text' => "🔒 احراز هویت", 'callback_data' => "verify"]],
            [['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
             ['text' => "🔑 احراز هویت با لینک", 'callback_data' => "verifybyuser"]],
            [['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
             ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>\n\nقابلیت‌های اصلی ربات را در اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'users') {
        $rxFeatKb = [
            [['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
             ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"]],
            [['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
             ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"]],
            [['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
             ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"]],
            [['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
             ['text' => "👤 پشتیبانی در پیوی", 'callback_data' => "statussupportpv"]],
            [['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
             ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"]],
            [['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
             ['text' => "📨 یادداشت کاربر عادی", 'callback_data' => "statusnamecustomf"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>\n\nقابلیت‌های مربوط به کاربران، اعلان‌ها و پشتیبانی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'shop') {
        $rxFeatKb = [
            [['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
             ['text' => "🛍 وضعیت خرید عمده", 'callback_data' => "bulkbuy"]],
            [['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
             ['text' => "📗 دسته بندی آموزش", 'callback_data' => "btn_status_category"]],
            [['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
             ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"]],
            [['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
             ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"]],
            [['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
             ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
             ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
             ['text' => "🌍 محدودیت تغییر لوکیشن", 'callback_data' => "changeloc"]],
            [['text' => "{$infocardColorEmoji} انتخاب رنگ", 'callback_data' => "infocard_color_menu"],
             ['text' => $infocardStatusText, 'callback_data' => "editstsuts-infocard-{$infocardStatusValue}"],
             ['text' => "📊 کارت مشخصات سرویس", 'callback_data' => "infocard_status"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "linkappsetting"],
             ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
             ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappstatus"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>\n\nقابلیت‌های فروشگاه، کانفیگ و خدمات جانبی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'lottery') {
        $rxFeatKb = [
            [['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
             ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
             ['text' => "🎲 گردونه شانس", 'callback_data' => "wheel_luck"]],
            [['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
             ['text' => "🎲 گردونه شانس خرید اول", 'callback_data' => "wheelagentfirst"]],
            [['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
             ['text' => "🎲 گردونه شانس نمایندگان", 'callback_data' => "wheelagent"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
             ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
             ['text' => "🎁 قرعه کشی شبانه", 'callback_data' => "score"]],
            [['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
             ['text' => "🎁 قرعه کشی نمایندگان", 'callback_data' => "Lotteryagent"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
             ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
             ['text' => "🎁 زیرمجموعه", 'callback_data' => "affiliatesstatus"]],
            [['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
             ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>\n\nقابلیت‌های گردونه شانس، قرعه‌کشی و زیرمجموعه را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'crons') {
        $rxFeatKb = [
            [['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
             ['text' => "🔓 کرون تست", 'callback_data' => "none"]],
            [['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
             ['text' => "🎛 آپتایم نود", 'callback_data' => "none"]],
            [['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
             ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
             ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
             ['text' => "🕚 کرون زمان", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان اولین اتصال", 'callback_data' => "setting_on_holdcron"],
             ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
             ['text' => "🕚 کرون اولین اتصال", 'callback_data' => "none"]],
            [['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
             ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
             ['text' => "🔋 کرون حجم", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
             ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
             ['text' => "❌ کرون حذف", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
             ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
             ['text' => "❌ کرون حذف حجم", 'callback_data' => "none"]],
            [['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
             ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
             ['text' => "زمان‌بندی کرون‌ها", 'callback_data' => "none"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>\n\nقابلیت‌های مربوط به کرون‌ها (cronjobs) و زمان‌بندی را اینجا تنظیم کنید.";
    } elseif ($rxFeatView === 'antispam') {


        $rxFeatKb = [
            [['text' => $rxAsStatusText, 'callback_data' => "antispam_toggle"],
             ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMsgCountVal, 'callback_data' => "antispam_set_count"],
             ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsSecondsVal, 'callback_data' => "antispam_set_seconds"],
             ['text' => "⏱ بازه زمانی (ثانیه)", 'callback_data' => "antispam_noop"]],
            [['text' => (string)$rxAsMuteSecondsVal, 'callback_data' => "antispam_set_mute"],
             ['text' => "🔇 مدت آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
            $rxBackRow,
        ];
        $rxFeatTitle = "🛡 <b>آنتی اسپم</b>\n\n"
            . "محدودیت ارسال پیام برای جلوگیری از اسپم.\n\n"
            . "📌 <b>تنظیمات فعلی:</b>\n"
            . "• وضعیت: {$rxAsStatusText}\n"
            . "• تعداد پیام مجاز: <b>{$rxAsMsgCountVal}</b> پیام\n"
            . "• بازه زمانی: هر <b>{$rxAsSecondsVal}</b> ثانیه\n"
            . "• مدت آف بودن پس از تخلف: <b>{$rxAsMuteSecondsVal}</b> ثانیه\n\n"
            . "💡 اگر کاربری بیشتر از {$rxAsMsgCountVal} پیام در {$rxAsSecondsVal} ثانیه ارسال کند، ربات برای {$rxAsMuteSecondsVal} ثانیه به او پاسخ نمی‌دهد.\n"
            . "پس از پایان این مدت، با ارسال مجدد /start کاربر مجدداً پاسخ می‌گیرد.\n\n"
            . "ℹ️ <b>توجه:</b> این محدودیت فقط برای کاربران معمولی اعمال می‌شود. ادمین‌ها هرگز محدود نمی‌شوند.";
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatKb]);
    nm_adminInstantReply($from_id, $rxFeatTitle, $Bot_Status, 'HTML');
} elseif ($datain == "antispam_noop" && $adminrulecheck['rule'] == "administrator") {


    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 1,
            ]);
        } catch (\Throwable $rxAsNoopErr) {  }
    }
} elseif ($datain == "antispam_toggle" && $adminrulecheck['rule'] == "administrator") {


    $rxAsCurrent = (string)($setting['antispam_status'] ?? '0');
    $rxAsNew = ($rxAsCurrent === '1') ? '0' : '1';
    update("setting", "antispam_status", $rxAsNew);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    if (!empty($callback_query_id)) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => ($rxAsNew === '1') ? '✅ آنتی اسپم فعال شد' : '⛔️ آنتی اسپم غیرفعال شد',
                'show_alert' => false,
                'cache_time' => 2,
            ]);
        } catch (\Throwable $rxAsTogErr) {  }
    }


    $setting = select("setting", "*", null, null, "select", ['cache' => false]);
    $rxAsRefreshedStatusValue = (string)($setting['antispam_status'] ?? '0');
    $rxAsRefreshedStatusText  = ($rxAsRefreshedStatusValue === '1')
        ? $textbotlang['Admin']['Status']['statuson']
        : $textbotlang['Admin']['Status']['statusoff'];
    $rxAsRefreshedMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    $rxAsRefreshedSeconds  = (int)($setting['antispam_seconds'] ?? 3);
    $rxAsRefreshedMute     = (int)($setting['antispam_mute_seconds'] ?? 5);
    $rxAsRefreshedKb = [
        [['text' => $rxAsRefreshedStatusText, 'callback_data' => "antispam_toggle"],
         ['text' => "🛡 وضعیت آنتی اسپم", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMsgCount, 'callback_data' => "antispam_set_count"],
         ['text' => "✉️ تعداد پیام مجاز", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedSeconds, 'callback_data' => "antispam_set_seconds"],
         ['text' => "⏱ بازه زمانی (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => (string)$rxAsRefreshedMute, 'callback_data' => "antispam_set_mute"],
         ['text' => "🔇 مدت آف بودن (ثانیه)", 'callback_data' => "antispam_noop"]],
        [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main']],
    ];
    $rxAsRefreshedTitle = "🛡 <b>آنتی اسپم</b>\n\n"
        . "📌 <b>تنظیمات فعلی:</b>\n"
        . "• وضعیت: {$rxAsRefreshedStatusText}\n"
        . "• تعداد پیام مجاز: <b>{$rxAsRefreshedMsgCount}</b> پیام\n"
        . "• بازه زمانی: هر <b>{$rxAsRefreshedSeconds}</b> ثانیه\n"
        . "• مدت آف بودن: <b>{$rxAsRefreshedMute}</b> ثانیه";
    if (isset($message_id)) {
        Editmessagetext($from_id, $message_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    } else {
        nm_adminInstantReply($from_id, $rxAsRefreshedTitle, json_encode(['inline_keyboard' => $rxAsRefreshedKb]), 'HTML');
    }
} elseif ($datain == "antispam_set_count" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_msg_count'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم تعداد پیام مجاز آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b>\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>1000</b> ارسال کنید.\n"
        . "این عدد بیشترین تعداد پیامی است که کاربر می‌تواند در بازه زمانی تعیین‌شده ارسال کند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_count', $from_id);
} elseif ($datain == "antispam_set_seconds" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_seconds'] ?? 3);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم بازه زمانی آنتی اسپم</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>3600</b> (یک ساعت) ارسال کنید.\n"
        . "این عدد طول بازه زمانی برای شمارش پیام‌ها است.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_seconds', $from_id);
} elseif ($datain == "antispam_set_mute" && $adminrulecheck['rule'] == "administrator") {

    $rxAsCur = (int)($setting['antispam_mute_seconds'] ?? 5);
    nm_adminInstantReply(
        $from_id,
        "📌 <b>تنظیم مدت آف بودن ربات</b>\n\n"
        . "مقدار فعلی: <b>{$rxAsCur}</b> ثانیه\n\n"
        . "لطفاً یک عدد بین <b>1</b> تا <b>86400</b> (یک روز) ارسال کنید.\n"
        . "این عدد مدت زمانی است که ربات پس از تخلف کاربر، به او پاسخ نمی‌دهد.\n\n"
        . "ℹ️ این محدودیت فقط برای کاربران اعمال می‌شود؛ ادمین‌ها هرگز محدود نمی‌شوند.",
        $backadmin,
        'HTML'
    );
    step('antispam_get_mute', $from_id);
} elseif ($user['step'] == "antispam_get_count" && $adminrulecheck['rule'] == "administrator") {


    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 1000) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>1000</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_msg_count", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ تعداد پیام مجاز آنتی اسپم روی <b>{$rxAsNum}</b> تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_seconds" && $adminrulecheck['rule'] == "administrator") {

    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 3600) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>3600</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ بازه زمانی آنتی اسپم روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif ($user['step'] == "antispam_get_mute" && $adminrulecheck['rule'] == "administrator") {

    $rxAsTrim = is_string($text) ? trim($text) : '';
    if (!ctype_digit($rxAsTrim)) {
        nm_adminInstantReply(
            $from_id,
            "❌ مقدار وارد شده نامعتبر است. لطفاً فقط <b>عدد صحیح مثبت</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    $rxAsNum = (int)$rxAsTrim;
    if ($rxAsNum < 1 || $rxAsNum > 86400) {
        nm_adminInstantReply(
            $from_id,
            "❌ عدد خارج از محدوده مجاز است. لطفاً عددی بین <b>1</b> تا <b>86400</b> ارسال کنید.",
            $backadmin,
            'HTML'
        );
        return;
    }
    update("setting", "antispam_mute_seconds", (string)$rxAsNum);
    if (function_exists('clearSelectCache')) { clearSelectCache('setting'); }
    nm_adminInstantReply(
        $from_id,
        "✅ مدت آف بودن ربات روی <b>{$rxAsNum}</b> ثانیه تنظیم شد.",
        $keyboardadmin,
        'HTML'
    );
    step('home', $from_id);
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "botstatuson") {
            $valuenew = "botstatusoff";
        } else {
            $valuenew = "botstatuson";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "usernamebtn") {
        if ($value == "onnotuser") {
            $valuenew = "offnotuser";
        } else {
            $valuenew = "onnotuser";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "notifnew") {
        if ($value == "onnewuser") {
            $valuenew = "offnewuser";
        } else {
            $valuenew = "onnewuser";
        }
        update("setting", "statusnewuser", $valuenew);
    } elseif ($type == "showagent") {
        if ($value == "onrequestagent") {
            $valuenew = "offrequestagent";
        } else {
            $valuenew = "onrequestagent";
        }
        update("setting", "statusagentrequest", $valuenew);
    } elseif ($type == "role") {
        if ($value == "rolleon") {
            $valuenew = "rolleoff";
        } else {
            $valuenew = "rolleon";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "Authenticationphone") {
        if ($value == "onAuthenticationphone") {
            $valuenew = "offAuthenticationphone";
        } else {
            $valuenew = "onAuthenticationphone";
        }
        update("setting", "get_number", $valuenew);
    } elseif ($type == "Authenticationiran") {
        if ($value == "onAuthenticationiran") {
            $valuenew = "offAuthenticationiran";
        } else {
            $valuenew = "onAuthenticationiran";
        }
        update("setting", "iran_number", $valuenew);
    } elseif ($type == "inlinebtnmain") {
        if ($value == "oninline") {
            $valuenew = "offinline";
        } else {
            $valuenew = "oninline";
        }
        update("setting", "inlinebtnmain", $valuenew);
    } elseif ($type == "verifystart") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifystart", $valuenew);
    } elseif ($type == "statussupportpv") {
        if ($value == "onpvsupport") {
            $valuenew = "offpvsupport";
        } else {
            $valuenew = "onpvsupport";
        }
        update("setting", "statussupportpv", $valuenew);
    } elseif ($type == "statusnamecustom") {
        if ($value == "onnamecustom") {
            $valuenew = "offnamecustom";
        } else {
            $valuenew = "onnamecustom";
        }
        update("setting", "statusnamecustom", $valuenew);
    } elseif ($type == "bulkbuy") {
        if ($value == "onbulk") {
            $valuenew = "offbulk";
        } else {
            $valuenew = "onbulk";
        }
        update("setting", "bulkbuy", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "wheelagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelagent", $valuenew);
    } elseif ($type == "keyconfig") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_keyboard_config", $valuenew);
    } elseif ($type == "Lotteryagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Lotteryagent", $valuenew);
    } elseif ($type == "compycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscopycart", $valuenew);
    } elseif ($type == "premiumemoji") {


        $rxPemCurrent = (string)($setting['premium_emoji_status'] ?? '0');
        $valuenew = ($rxPemCurrent === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);

        $setting['premium_emoji_status'] = $valuenew;
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }


        if (function_exists('rxRenderPremiumEmojiPanel')) {
            rxRenderPremiumEmojiPanel($from_id, 1);
        }
        return;
    } elseif ($type == "score") {
        if ($value == "1") {
            if (isShellExecAvailable()) {
                $crontabBinary = getCrontabBinary();
                if ($crontabBinary === null) {
                    error_log('Unable to locate crontab executable; cannot remove lottery cron job.');
                } else {
                    $currentCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
                    $jobToRemove = "*/1 * * * * curl https://$domainhosts/cronbot/lottery.php";
                    $newCronJobs = preg_replace('/' . preg_quote($jobToRemove, '/') . '/', '', (string) $currentCronJobs);
                    $tempCronFile = '/tmp/crontab.txt';
                    file_put_contents($tempCronFile, trim($newCronJobs) . PHP_EOL);
                    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($tempCronFile)));
                    if (file_exists($tempCronFile)) {
                        unlink($tempCronFile);
                    }
                }
            } else {
                error_log('Unable to remove lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "0";
        } else {
            $phpFilePath = "https://$domainhosts/cronbot/lottery.php";
            $cronCommand = "*/1 * * * * curl $phpFilePath";
            if (!addCronIfNotExists($cronCommand)) {
                error_log('Unable to register lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "1";
        }
        update("setting", "scorestatus", $valuenew);
    } elseif ($type == "wheel_luck") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelـluck", $valuenew);
    } elseif ($type == "affiliatesstatus") {
        if ($value == "onaffiliates") {
            $valuenew = "offaffiliates";
        } else {
            $valuenew = "onaffiliates";
        }
        update("setting", "affiliatesstatus", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "btn_status_category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "categoryhelp", $valuenew);
    } elseif ($type == "linkappstatus") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "linkappstatus", $valuenew);
    } elseif ($type == "btnstautslanguage") {
        if ($setting['languageru'] == "1") {
            nm_adminInstantReply($from_id, "زبان روسیه ای روشن است و نمی توانید زبان انگلیسی را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageen", $valuenew);
    } elseif ($type == "btnstautslanguageru") {
        if ($setting['languageen'] == "1") {
            nm_adminInstantReply($from_id, "زبان انگلیسی روشن است و نمی توانید زبان روسیه ای را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageru", $valuenew);
    } elseif ($type == "wheelagentfirst") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusfirstwheel", $valuenew);
    } elseif ($type == "changeloc") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuslimitchangeloc", $valuenew);
    } elseif ($type == "Debtsettlement") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Debtsettlement", $valuenew);
    } elseif ($type == "Dice") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Dice", $valuenew);
    } elseif ($type == "statusnamecustomf") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusnoteforf", $valuenew);
    } elseif ($type == "crontest") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['test'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronday") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['day'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronvolume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove_volume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['remove_volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_node") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_node'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_panel") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['uptime_panel'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "on_hold") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron = normalizeCronStatus($setting['cron_status'] ?? null);
        $status_cron['on_hold'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "infocard") {

        $valuenew = ($value === '1') ? '0' : '1';
        $existing = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
        if (is_array($existing) && isset($existing['Namevalue'])) {
            update("shopSetting", "value", $valuenew, "Namevalue", "infocard_status");
        } else {

            try {
                $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                $stmt->execute([':n' => 'infocard_status', ':v' => $valuenew]);
                if (function_exists('clearSelectCache')) clearSelectCache('shopSetting');
            } catch (\Throwable $e) {
                error_log('infocard_status insert failed: ' . $e->getMessage());
            }
        }
    } elseif ($type == "premiumemoji") {

        $valuenew = ($value === '1') ? '0' : '1';
        update("setting", "premium_emoji_status", $valuenew);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
    }
    $setting = select("setting", "*");
    $status_cron = normalizeCronStatus($setting['cron_status'] ?? null, true);
    $_rxOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_rxOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $name_status = [
        'botstatuson' => $_rxOn,
        'botstatusoff' => $_rxOff,
    ][$setting['Bot_Status']] ?? $_rxOff;
    $name_status_username = [
        'onnotuser' => $_rxOn,
        'offnotuser' => $_rxOff,
    ][$setting['NotUser']] ?? $_rxOff;
    $name_status_notifnewuser = [
        'onnewuser' => $_rxOn,
        'offnewuser' => $_rxOff,
    ][$setting['statusnewuser']] ?? $_rxOff;
    $name_status_showagent = [
        'onrequestagent' => $_rxOn,
        'offrequestagent' => $_rxOff,
    ][$setting['statusagentrequest']] ?? $_rxOff;
    $name_status_role = [
        'rolleon' => $_rxOn,
        'rolleoff' => $_rxOff,
    ][$setting['roll_Status']] ?? $_rxOff;
    $Authenticationphone = [
        'onAuthenticationphone' => $_rxOn,
        'offAuthenticationphone' => $_rxOff,
    ][$setting['get_number']] ?? $_rxOff;
    $Authenticationiran = [
        'onAuthenticationiran' => $_rxOn,
        'offAuthenticationiran' => $_rxOff,
    ][$setting['iran_number']] ?? $_rxOff;
    $statusinline = [
        'oninline' => $_rxOn,
        'offinline' => $_rxOff,
    ][$setting['inlinebtnmain']] ?? $_rxOff;
    $statusverify = [
        'onverify' => $_rxOn,
        'offverify' => $_rxOff,
    ][$setting['verifystart']] ?? $_rxOff;
    $statuspvsupport = [
        'onpvsupport' => $_rxOn,
        'offpvsupport' => $_rxOff,
    ][$setting['statussupportpv']] ?? $_rxOff;
    $statusnameconfig = [
        'onnamecustom' => $_rxOn,
        'offnamecustom' => $_rxOff,
    ][$setting['statusnamecustom']] ?? $_rxOff;
    $statusnamebulk = [
        'onbulk' => $_rxOn,
        'offbulk' => $_rxOff,
    ][$setting['bulkbuy']] ?? $_rxOff;
    $statusverifybyuser = [
        'onverify' => $_rxOn,
        'offverify' => $_rxOff,
    ][$setting['verifybucodeuser']] ?? $_rxOff;
    $score = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['scorestatus'] ?? '0')] ?? $_rxOff;
    $wheel_luck = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['wheelـluck'] ?? '0')] ?? $_rxOff;
    $refralstatus = [
        'onaffiliates' => $_rxOn,
        'offaffiliates' => $_rxOff,
    ][$setting['affiliatesstatus']] ?? $_rxOff;
    $btnstatuscategory = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['categoryhelp'] ?? '0')] ?? $_rxOff;
    $btnstatuslinkapp = [
        '1' => $_rxOn,
        '0' => $_rxOff,
    ][(string)($setting['linkappstatus'] ?? '0')] ?? $_rxOff;
    $cronteststatustext        = ($status_cron['test']           ?? false) ? $_rxOn : $_rxOff;
    $crondaystatustext         = ($status_cron['day']            ?? false) ? $_rxOn : $_rxOff;
    $cronvolumestatustext      = ($status_cron['volume']         ?? false) ? $_rxOn : $_rxOff;
    $cronremovestatustext      = ($status_cron['remove']         ?? false) ? $_rxOn : $_rxOff;
    $cronremovevolumestatustext= ($status_cron['remove_volume']  ?? false) ? $_rxOn : $_rxOff;
    $cronuptime_nodestatustext = ($status_cron['uptime_node']    ?? false) ? $_rxOn : $_rxOff;
    $cronuptime_panelstatustext= ($status_cron['uptime_panel']   ?? false) ? $_rxOn : $_rxOff;
    $cronon_holdtext           = ($status_cron['on_hold']        ?? false) ? $_rxOn : $_rxOff;
    $languagestatus = (((string)($setting['languageen'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $languagestatusru = (((string)($setting['languageru'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $wheelagent = (((string)($setting['wheelagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $Lotteryagent = (((string)($setting['Lotteryagent'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusfirstwheel = (((string)($setting['statusfirstwheel'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statuslimitchangeloc = (((string)($setting['statuslimitchangeloc'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusDebtsettlement = (((string)($setting['Debtsettlement'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusDice = (((string)($setting['Dice'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $statusnotef = (((string)($setting['statusnoteforf'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $status_copy_cart = (((string)($setting['statuscopycart'] ?? '0')) === '1') ? $_rxOn : $_rxOff;
    $keyboard_config_text = (((string)($setting['status_keyboard_config'] ?? '0')) === '1') ? $_rxOn : $_rxOff;

    $infocardStatusRow = select("shopSetting", "*", "Namevalue", "infocard_status", "select");
    $infocardStatusValue = (is_array($infocardStatusRow) && isset($infocardStatusRow['value']))
        ? (string)$infocardStatusRow['value'] : '0';
    $infocardColorRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $infocardColorValue = (is_array($infocardColorRow) && isset($infocardColorRow['value']))
        ? (string)$infocardColorRow['value'] : 'yellow';
    $infocardStatusText = ($infocardStatusValue === '1') ? $_rxOn : $_rxOff;
    $infocardColorEmojiMap = [
        'yellow' => '🟡', 'green' => '🟢', 'red' => '🔴',
        'blue'   => '🔵', 'purple' => '🟣', 'orange' => '🟠'
    ];
    $infocardColorEmoji = $infocardColorEmojiMap[$infocardColorValue] ?? '🟡';

    $premiumEmojiStatusValue = (string)($setting['premium_emoji_status'] ?? '0');
    $premiumEmojiStatusText = ($premiumEmojiStatusValue === '1') ? $_rxOn : $_rxOff;


    $rxFeatTypeCatMap = [

        'statusbot' => 'bot', 'role' => 'bot',
        'Authenticationphone' => 'bot', 'Authenticationiran' => 'bot',
        'verifystart' => 'bot', 'verifybyuser' => 'bot',
        'inlinebtnmain' => 'bot',

        'usernamebtn' => 'users', 'notifnew' => 'users', 'showagent' => 'users',
        'statussupportpv' => 'users', 'statusnamecustom' => 'users', 'statusnamecustomf' => 'users',

        'bulkbuy' => 'shop', 'btn_status_category' => 'shop', 'keyconfig' => 'shop',
        'compycart' => 'shop', 'Debtsettlement' => 'shop', 'changeloc' => 'shop',
        'infocard' => 'shop', 'linkappstatus' => 'shop',

        'wheelagent' => 'lottery', 'wheelagentfirst' => 'lottery', 'wheel_luck' => 'lottery',
        'Lotteryagent' => 'lottery', 'score' => 'lottery', 'affiliatesstatus' => 'lottery',
        'Dice' => 'lottery',

        'crontest' => 'crons', 'uptime_node' => 'crons', 'uptime_panel' => 'crons',
        'cronday' => 'crons', 'on_hold' => 'crons', 'cronvolume' => 'crons',
        'notifremove' => 'crons', 'notifremove_volume' => 'crons',
    ];
    $rxFeatTargetCat = $rxFeatTypeCatMap[$type] ?? 'main';
    $rxPostTglPemCount = 0;
    try {
        $rxPostTglPemStmt = $pdo->query("SELECT COUNT(*) AS c FROM premium_emojis");
        if ($rxPostTglPemStmt) {
            $rxPostTglPemRow = $rxPostTglPemStmt->fetch(PDO::FETCH_ASSOC);
            $rxPostTglPemCount = (int)($rxPostTglPemRow['c'] ?? 0);
        }
    } catch (\Throwable $rxPostTglPemErr) { $rxPostTglPemCount = 0; }
    $rxPostTglPremiumLabel = "🌟 ایموجی پرمیوم" . ($rxPostTglPemCount > 0 ? " ({$rxPostTglPemCount})" : "");


    $rxFeatTitle = "📋 <b>وضعیت قابلیت‌ها</b>";
    $rxFeatBackRow = [['text' => "🔙 بازگشت", 'callback_data' => 'featcat_main'], ['text' => "❌ بستن", 'callback_data' => 'close_stat']];

    if ($rxFeatTargetCat === 'bot') {
        $rxFeatTitle = "🤖 <b>آپشن‌های اصلی ربات</b>";
        $rxFeatRows = [
            [['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"], ['text' => (string)($textbotlang['Admin']['Status']['stautsbot'] ?? 'stautsbot'), 'callback_data' => "statusbot"]],
            [['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"], ['text' => (string)($textbotlang['Admin']['Status']['stautsrolee'] ?? 'stautsrolee'), 'callback_data' => "stautsrolee"]],
            [['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"], ['text' => (string)($textbotlang['Admin']['Status']['Authenticationphone'] ?? 'Authenticationphone'), 'callback_data' => "Authenticationphone"]],
            [['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"], ['text' => (string)($textbotlang['Admin']['Status']['Authenticationiran'] ?? 'Authenticationiran'), 'callback_data' => "Authenticationiran"]],
            [['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"], ['text' => "🔒 احراز هویت", 'callback_data' => "verify"]],
            [['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"], ['text' => "🔑 احراز هویت با لینک", 'callback_data' => "verifybyuser"]],
            [['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"], ['text' => (string)($textbotlang['Admin']['Status']['inlinebtns'] ?? 'inlinebtns'), 'callback_data' => "inlinebtnmain"]],
            $rxFeatBackRow,
        ];
    } elseif ($rxFeatTargetCat === 'users') {
        $rxFeatTitle = "👥 <b>کاربران و پشتیبانی</b>";
        $rxFeatRows = [
            [['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"], ['text' => (string)($textbotlang['Admin']['Status']['statususernamebtn'] ?? 'statususernamebtn'), 'callback_data' => "usernamebtn"]],
            [['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"], ['text' => (string)($textbotlang['Admin']['Status']['statusnotifnewuser'] ?? 'statusnotifnewuser'), 'callback_data' => "statusnewuser"]],
            [['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"], ['text' => (string)($textbotlang['Admin']['Status']['statusshowagent'] ?? 'statusshowagent'), 'callback_data' => "statusnewuser"]],
            [['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"], ['text' => "👤 پشتیبانی در پیوی", 'callback_data' => "statussupportpv"]],
            [['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"], ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"]],
            [['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"], ['text' => "📨 یادداشت کاربر عادی", 'callback_data' => "statusnamecustomf"]],
            $rxFeatBackRow,
        ];
    } elseif ($rxFeatTargetCat === 'shop') {
        $rxFeatTitle = "🛍 <b>فروش و خدمات</b>";
        $rxFeatRows = [
            [['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"], ['text' => "🛍 وضعیت خرید عمده", 'callback_data' => "bulkbuy"]],
            [['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"], ['text' => "📗دسته بندی آموزش", 'callback_data' => "btn_status_category"]],
            [['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"], ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"]],
            [['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"], ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"]],
            [['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"], ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"], ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"], ['text' => "🌍 محدودیت تغییر لوکیشن", 'callback_data' => "changeloc"]],
            [['text' => "{$infocardColorEmoji} انتخاب رنگ", 'callback_data' => "infocard_color_menu"], ['text' => $infocardStatusText, 'callback_data' => "editstsuts-infocard-{$infocardStatusValue}"], ['text' => "📊 کارت مشخصات سرویس", 'callback_data' => "infocard_status"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "linkappsetting"], ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"], ['text' => "🔗لینک دانلود برنامه", 'callback_data' => "linkappstatus"]],
            $rxFeatBackRow,
        ];
    } elseif ($rxFeatTargetCat === 'lottery') {
        $rxFeatTitle = "🎁 <b>گردونه و قرعه‌کشی</b>";
        $rxFeatRows = [
            [['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"], ['text' => "🎲 گردونه شانس  نمایندگان", 'callback_data' => "wheelagent"]],
            [['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"], ['text' => "🎲 گردونه شانس خرید اول", 'callback_data' => "wheelagentfirst"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"], ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"], ['text' => "🎲 گردونه شانس", 'callback_data' => "wheel_luck"]],
            [['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"], ['text' => "🎁 قرعه کشی نمایندگان", 'callback_data' => "Lotteryagent"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"], ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"], ['text' => "🎁 قرعه کشی شبانه", 'callback_data' => "score"]],
            [['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"], ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"], ['text' => "🎁زیرمجموعه", 'callback_data' => "affiliatesstatus"]],
            [['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"], ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"]],
            $rxFeatBackRow,
        ];
    } elseif ($rxFeatTargetCat === 'crons') {
        $rxFeatTitle = "⏱ <b>کرون‌ها و زمان‌بندی</b>";
        $rxFeatRows = [
            [['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"], ['text' => "🔓کرون تست", 'callback_data' => "none"]],
            [['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"], ['text' => "🎛 آپتایم نود", 'callback_data' => "none"]],
            [['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"], ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"], ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"], ['text' => "🕚 کرون زمان", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان اولین اتصال", 'callback_data' => "setting_on_holdcron"], ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"], ['text' => "🕚 کرون اولین اتصال", 'callback_data' => "none"]],
            [['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"], ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"], ['text' => "🔋 کرون حجم", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"], ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"], ['text' => "❌ کرون حذف", 'callback_data' => "none"]],
            [['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"], ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"], ['text' => "❌ کرون حذف حجم", 'callback_data' => "none"]],
            [['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"], ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"], ['text' => "زمان‌بندی کرون‌ها", 'callback_data' => "none"]],
            $rxFeatBackRow,
        ];
    } else {

        $rxFeatTitle = "📌 <b>وضعیت قابلیت‌ها</b>\n\n✅ تنظیم به‌روزرسانی شد.\n\nاز کدام دسته از قابلیت‌ها می‌خواهید استفاده کنید؟";
        $rxFeatRows = [
            [['text' => "🤖 آپشن‌های اصلی ربات",   'callback_data' => "featcat_bot"]],
            [['text' => "👥 کاربران و پشتیبانی",   'callback_data' => "featcat_users"]],
            [['text' => "🛍 فروش و خدمات",         'callback_data' => "featcat_shop"]],
            [['text' => "🎁 گردونه و قرعه‌کشی",    'callback_data' => "featcat_lottery"]],
            [['text' => "⏱ کرون‌ها و زمان‌بندی",   'callback_data' => "featcat_crons"]],
            [['text' => $rxPostTglPremiumLabel,    'callback_data' => "premium_emoji_settings"]],
            [['text' => "❌ بستن",                 'callback_data' => 'close_stat']],
        ];
    }
    $Bot_Status = json_encode(['inline_keyboard' => $rxFeatRows]);
    Editmessagetext($from_id, $message_id, $rxFeatTitle, $Bot_Status);
} elseif ($text == "⚖️ متن قانون" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_roll'], $backadmin, 'HTML');
    step('text_roll', $from_id);
} elseif ($user['step'] == "text_roll") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_roll");
    step('home', $from_id);
} elseif ($text == "📣 گزارشات ربات" && $adminrulecheck['rule'] == "administrator") {
    $textreports = "📣در این بخش میتوانید آیدی عددی گروه را برای ارسال اعلان ارسال نمایید
آموزش تنظیم گروه :
1 - ابتدا یک گروه  بسازید
2 - ربات  @myidbot را عضو گروه کنید و دستور /getgroupid@myidbot داخل گروه ارسال کنید
3 - حالت تاپیک یا انجمن گروه را از تنظیمات گروه روشن کنید4
4 - ربات خودتان را ادمین گروه کنید
5 - آیدی عددی ارسال شده را در ربات ارسال کنید.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
    nm_adminInstantReply($from_id, $textreports, $backadmin, 'HTML');
    step('addchannelid', $from_id);
} elseif ($user['step'] == "addchannelid") {
    $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
    if (empty($outputcheck['ok'])) {
        $errorDescription = 'نامشخص';
        if (is_array($outputcheck) && isset($outputcheck['description'])) {
            $errorDescription = $outputcheck['description'];
        } elseif (is_string($outputcheck) && $outputcheck !== '') {
            $errorDescription = $outputcheck;
        }
        $texterror = "❌ اتصال به گروه با موفقیت انجام نشد

خطای دریافتی :  {$errorDescription}";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($outputcheck['result']['chat']['is_forum'] == false) {
        $texterror = "❌ گروه انتخاب شده درحالت انجمن نیست ابتدا قابلیت تاپیک گروه را روشن کرده سپس آیدی عددی گروه را مجددا تنظیم نمایید";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🛍 گزارش های خرید"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($buyreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "buyreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "📌 گزارش خرید خدمات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherservice != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherservice");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🔑 گزارش اکانت تست"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($reporttest != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reporttest");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "⚙️ سایر گزارشات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "❌ گزارش خطا ها"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "errorreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "💰 گزارش مالی"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        nm_adminInstantReply($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($paymentreports != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "paymentreport");
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $setting_panel, 'HTML');
    update("setting", "Channel_Report", $text);
    step('home', $from_id);
} elseif ($text == "🏬 تنظیمات فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 اضافه کردن محصول" && $adminrulecheck['rule'] == "administrator") {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
    step('get_limit', $from_id);
} elseif ($user['step'] == "get_limit") {
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name_product", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['setagentproduct'], $backadmin, 'HTML');
    step('get_agent', $from_id);
} elseif ($user['step'] == "get_agent") {
    $agent = ["n", "f", "n2"];
    if (!in_array($text, $agent)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    $marzban_list[] = '/all';
    if (!in_array($text, $marzban_list)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی اشتباه است", null, 'HTML');
        return;
    }
    savedata("save", "Location", $text);
    if ($setting['statuscategorygenral'] == "oncategorys") {
        nm_adminInstantReply($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", KeyboardCategoryadmin(), 'HTML');
        step("getcategory", $from_id);
        return;
    }
    $panel = $text === '/all' ? null : select("marzban_panel", "*", "name_panel", $text, "select");
    if ($text !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "getcategory") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("save", "category", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Volume_constraint", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Service_time", $text);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('gettimereset', $from_id);
} elseif ($user['step'] == "gettimereset") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price_product", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $panelType = is_array($panel) ? ($panel['type'] ?? '') : '';
    if ($panelType == "marzban" || $panelType == "marzneshin") {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
        step('getnote', $from_id);
        return;
    }
    savedata("save", "data_limit_reset", "no_reset");
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید. این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "getnote") {
    savedata("save", "data_limit_reset", $text);
    nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید.این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    $varhide_panel = "{}";
    if (!isset($userdata['category']))
        $userdata['category'] = null;
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
    $stmt->bindParam(':name_product', $userdata['name_product']);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $userdata['price_product']);
    $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
    $stmt->bindParam(':Service_time', $userdata['Service_time']);
    $stmt->bindParam(':Location', $userdata['Location']);
    $stmt->bindParam(':agent', $userdata['agent']);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
    $stmt->bindParam(':note', $text, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "👨‍🔧 بخش ادمین" && $adminrulecheck['rule'] == "administrator") {
    $list_admin = select("admin", "*", null, null, "fetchAll");
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($list_admin as $admin) {
        $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
        if ($adminId === '') {
            continue;
        }
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
            ['text' => $adminId, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    nm_adminInstantReply($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($text == "⚙️ تنظیمات عمومی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "🤙 بخش پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ],
            [
                ['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']],
            ]
        ]
    ]);
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $countpay = $stmt->rowCount();
    $typepay = explode('|', $Payment_report['id_invoice']);
    if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
        nm_adminInstantReply($from_id, "⚠️ برای تأیید درخواست‌های کاربر، ابتدا رسیدهای خرید یا تمدید اشتراک را بررسی و تأیید کنید. سپس رسید شارژ کیف پول را تأیید کنید. ", null, 'HTML');
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی بعد از تایید : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }


    try {
        $atomicStmt = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :id_order AND payment_Status <> 'paid' AND payment_Status <> 'reject'"
        );
        $atomicStmt->bindValue(':id_order', $Payment_report['id_order'], PDO::PARAM_STR);
        $atomicStmt->execute();
        if ($atomicStmt->rowCount() === 0) {
            if (function_exists('rx_log_event')) {
                rx_log_event('ADMIN_CONFIRM_PAY_RACE', 'Confirm_pay raced with another admin; dropping duplicate', [
                    'id_order' => $Payment_report['id_order'],
                    'admin_id' => $from_id,
                ]);
            }
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }
    } catch (Throwable $atomicErr) {
        if (function_exists('rx_log_event')) {
            rx_log_event('ADMIN_CONFIRM_PAY_DB_ERROR', 'Atomic mark-as-paid failed', [
                'id_order' => $Payment_report['id_order'],
                'err' => $atomicErr->getMessage(),
            ]);
        }
        return;
    }
    DirectPayment($order_id);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcart", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;


        $stmtCashback = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
        $stmtCashback->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
        $stmtCashback->bindValue(':uid', $Balance_id['id'], PDO::PARAM_STR);
        $stmtCashback->execute();
        $pricecashback = number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 یک ادمین رسید پرداخت  را تایید کرد.

اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
💰 مبلغ پرداخت : {$Payment_report['price']}
👤 ایدی عددی کاربر : <code>{$Payment_report['id_user']}</code>
👤 نام کاربری کاربر : @{$Balance_id['username']}
        کد پیگیری پرداحت : $order_id";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    nm_adminInstantReply($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ یک ادمین رسید پرداخت را رد کرد.

اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
نام کاربری ادمین تایید کننده : @$username
💰 مبلغ پرداخت : {$Payment_report['price']}
دلیل رد کردن : $text
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^confirmcryptomanual_(\w+)$/', (string) $datain, $cmConf) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmConf[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] == 'paid' || $cmPayment['payment_Status'] == 'reject') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    $cmAutoIrr = (int) ($cmPayment['price'] ?? 0);
    $cmCoinAmtPick = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmtPick = rtrim(rtrim(number_format((float) $cmCoinAmtPick, 9, '.', ''), '0'), '.');
    $cmPickMsg = "🟢 <b>تایید درخواست بررسی دستی</b>\n\n"
               . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
               . "👤 کاربر: <code>{$cmPayment['id_user']}</code>\n"
               . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
               . "🪙 مقدار: <code>{$cmCoinAmtPick}</code>\n"
               . "💵 معادل خودکار: <b>" . number_format($cmAutoIrr) . " تومان</b>\n\n"
               . "👇 روش تایید را انتخاب کنید:";
    $cmPickKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⚡ تایید با همان مبلغ خودکار', 'callback_data' => 'cmauto_' . $cmOrderId]],
            [['text' => '✏️ ویرایش مبلغ و تایید',       'callback_data' => 'cmmanual_' . $cmOrderId]],
            [['text' => '🔙 بازگشت',                     'callback_data' => 'cmback_' . $cmOrderId]],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $cmPickMsg, $cmPickKb);
    } else {
        sendmessage($from_id, $cmPickMsg, $cmPickKb, 'HTML');
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif (preg_match('/^cmauto_(\w+)$/', (string) $datain, $cmAuto) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmAuto[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment || in_array($cmPayment['payment_Status'], ['paid', 'reject'], true)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'بررسی‌شده/یافت‌نشد', 'show_alert' => true]);
        return;
    }
    $cmFinalIrr = (int) ($cmPayment['price'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $message_id, $callback_query_id, $text_inline ?? '');
} elseif (preg_match('/^cmmanual_(\w+)$/', (string) $datain, $cmMan) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmMan[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment || in_array($cmPayment['payment_Status'], ['paid', 'reject'], true)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'بررسی‌شده/یافت‌نشد', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    $cmCoinAmt = (string) ($cmPayment['crypto_amount'] ?? '');
    $cmCoinAmt = rtrim(rtrim(number_format((float) $cmCoinAmt, 9, '.', ''), '0'), '.');
    $cmAutoIrrShow = (int) ($cmPayment['price'] ?? 0);
    nm_adminInstantReply(
        $from_id,
        "✏️ <b>وارد کردن مبلغ تومانی دستی</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "💎 ارز: " . htmlspecialchars((string) $cmPayment['crypto_currency']) . "\n"
        . "🪙 مقدار: <code>{$cmCoinAmt}</code>\n"
        . "💵 مبلغ خودکار: <b>" . number_format($cmAutoIrrShow) . " تومان</b>\n\n"
        . "💰 مبلغ تومانی نهایی را وارد کنید (فقط عدد):",
        $backadmin,
        'HTML'
    );
    step('cm_manual_irr_input', $from_id);
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif ($user['step'] == "cm_manual_irr_input" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    if ($cmOrderId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. دوباره تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmIrrRaw = trim(str_replace([',', '،'], ['', ''], (string) $text));
    if (!ctype_digit($cmIrrRaw) || (int) $cmIrrRaw <= 0) {
        nm_adminInstantReply($from_id, "❌ مبلغ باید عدد صحیح مثبت باشد. مثال: <code>50000</code>", null, 'HTML');
        return;
    }
    $cmFinalIrr = (int) $cmIrrRaw;
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        nm_adminInstantReply($from_id, "❌ تراکنش یافت نشد.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (in_array($cmPayment['payment_Status'], ['paid', 'reject'], true)) {
        nm_adminInstantReply($from_id, "❌ این درخواست قبلاً بررسی شده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "price", (string) $cmFinalIrr, "id_order", $cmOrderId);
    $cmPayment['price'] = (string) $cmFinalIrr;
    $cmPrevMsgId = (int) ($user['Processing_value_tow'] ?? 0);
    cm_apply_payment($cmOrderId, $cmFinalIrr, $cmPayment, $from_id, $username, $setting, $paymentreports, $cmPrevMsgId, null, '');
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ تایید شد با مبلغ " . number_format($cmFinalIrr) . " تومان.", $keyboardadmin, 'HTML');
} elseif (preg_match('/^cmback_(\w+)$/', (string) $datain, $cmBack) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmBack[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'تراکنش یافت نشد', 'show_alert' => true]);
        return;
    }
    $cmKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ کیف پول', 'callback_data' => 'confirmcryptomanual_' . $cmOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $cmOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        telegram('editMessageReplyMarkup', [
            'chat_id'      => $from_id,
            'message_id'   => $message_id,
            'reply_markup' => $cmKb,
        ]);
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
} elseif (preg_match('/^rejectcryptomanual_(\w+)$/', (string) $datain, $cmRej) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $cmOrderId = $cmRej[1];
    $cmPayment = select("Payment_report", "*", "id_order", $cmOrderId, "select");
    if (!$cmPayment) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تراکنش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if ($cmPayment['payment_Status'] == 'paid' || $cmPayment['payment_Status'] == 'reject') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این درخواست قبلاً بررسی شده است.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    update("user", "Processing_value", $cmPayment['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $cmOrderId, "id", $from_id);
    update("user", "Processing_value_tow", (string) ($message_id ?? 0), "id", $from_id);
    nm_adminInstantReply($from_id, "✍️ دلیل رد کردن این درخواست را وارد کنید:", $backadmin, 'HTML');
    step('reject_crypto_manual_reason', $from_id);
} elseif ($user['step'] == "reject_crypto_manual_reason" && empty($datain)) {
    $cmOrderId = (string) ($user['Processing_value_one'] ?? '');
    $cmUserId = (string) ($user['Processing_value'] ?? '');
    if ($cmOrderId === '' || $cmUserId === '') {
        nm_adminInstantReply($from_id, "❌ خطای داخلی. مجدداً تلاش کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $cmReason = trim((string) $text);
    if ($cmReason === '' || mb_strlen($cmReason) > 500) {
        nm_adminInstantReply($from_id, "❌ دلیل معتبر نیست. حداکثر ۵۰۰ کاراکتر.", null, 'HTML');
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $cmOrderId);
    update("Payment_report", "dec_not_confirmed", $cmReason, "id_order", $cmOrderId);
    update("Payment_report", "at_updated", date('Y/m/d H:i:s'), "id_order", $cmOrderId);

    sendmessage(
        $cmUserId,
        "❌ <b>درخواست بررسی دستی پرداخت کریپتوی شما رد شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
        . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n\n"
        . "در صورت اعتراض با پشتیبانی در ارتباط باشید.",
        null,
        'HTML'
    );
    nm_adminInstantReply($from_id, "✅ درخواست با موفقیت رد شد.", $keyboardadmin, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => "❌ بررسی دستی پرداخت کریپتو رد شد\n\n"
                . "🛒 کد پیگیری: <code>{$cmOrderId}</code>\n"
                . "👤 کاربر: <code>{$cmUserId}</code>\n"
                . "📝 دلیل: " . htmlspecialchars($cmReason) . "\n"
                . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
            'parse_mode' => 'HTML',
        ]);
    }
} elseif ($text == "❌ حذف محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    update("user", "Processing_value", $text, "id", $from_id);
    step('remove-product', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (Location= :Location or Location= '/all')");
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
} elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
    $location = $dataget[1];
    $location = $location == "all" ? "/all" : $location;
    update("user", "Processing_value_one", $location, "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "کاربر عادی", 'callback_data' => 'typeagenteditproduct_f'],
            ],
            [
                ['text' => "نماینده پیشرفته", 'callback_data' => 'typeagenteditproduct_n2'],
                ['text' => "نماینده عادی", 'callback_data' => 'typeagenteditproduct_n'],
            ],
            [
                ['text' => "بازگشت", 'callback_data' => "admin"]
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 نوع کاربری را انتخاب کنید", $Response);
} elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    update("user", "Processing_value_tow", $typeagent, "id", $from_id);
    $product = [];
    $escapedText = mysqli_real_escape_string($connect, $user['Processing_value_one']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc = $panel['name_panel'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent = ?");
    $_stmt->bind_param("ss", $_loc, $typeagent);
    $_stmt->execute();
    $getdataproduct = $_stmt->get_result();
    $_stmt->close();
    $list_product = [
        'inline_keyboard' => [],
    ];
    if (isset($getdataproduct)) {
        while ($row = mysqli_fetch_assoc($getdataproduct)) {
            $list_product['inline_keyboard'][] = [
                ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']]
            ];
        }
        $list_product['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به منوی قبل", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
        ];

        $json_list_product_list_admin = json_encode($list_product);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
} elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
    $id_product = $dataget[1];
    deletemessage($from_id, $message_id);
    update("user", "Processing_value", $id_product, "id", $from_id);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $_loc2 = $panel['name_panel']; $_pv2 = $user['Processing_value_tow'];
    $_stmt = $connect->prepare("SELECT * FROM product WHERE id = ? AND agent = ? AND (Location = ? OR Location = '/all') LIMIT 1");
    $_stmt->bind_param("sss", $id_product, $_pv2, $_loc2);
    $_stmt->execute();
    $info_product = $_stmt->get_result()->fetch_assoc();
    $_stmt->close();
    $count_invoice = select("invoice", "*", "name_product", $info_product['name_product'], "count");
    $infoproduct = "
📌 اطلاعات محصول در حال ویرایش:
نام محصول :  {$info_product['name_product']}
قیمت محصول : {$info_product['price_product']}
حجم محصول : {$info_product['Volume_constraint']}
موقعیت محصول : {$info_product['Location']}
زمان محصول : {$info_product['Service_time']}
نوع کاربری محصول : {$info_product['agent']}
ریست دوره ای حجم محصول : {$info_product['data_limit_reset']}
یادداشت محصول : {$info_product['note']}
دسته بندی محصول : {$info_product['category']}
تعداد محصول فروخته شده : $count_invoice عدد
    ";
    nm_adminInstantReply($from_id, $infoproduct, $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "قیمت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "قیمت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':price_product', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ قیمت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "یادداشت" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "یادداشت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_note', $from_id);
} elseif ($user['step'] == "change_note") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':notes', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ یادداشت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام دسته بندی جدید را انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step('change_categroy', $from_id);
} elseif ($user['step'] == "change_categroy") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی ُ دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':categroy', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ دسته بندی محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نام محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    if (strlen($text) > 150) {
        nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':name_products', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع کاربری" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع کاربری جدید را ارسال کنید :
نوع کاربری ها :f , n , n2", $backadmin, 'HTML');
    step('change_type_agent', $from_id);
} elseif ($user['step'] == "change_type_agent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر می باشد", null, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':agents', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع ریست حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "نوع ریست حجم را ارسال کنید", $keyboardtimereset, 'HTML');
    step('change_reset_data', $from_id);
} elseif ($user['step'] == "change_reset_data") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':data_limit_reset', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "موقعیت محصول" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 موقعیت جدید محصول را انتخاب کنید", $json_list_marzban_panel, 'HTML');
    step('change_loc_data', $from_id);
} elseif ($user['step'] == "change_loc_data") {
    if ($text == "/all") {
        nm_adminInstantReply($from_id, "❌ نمی توانید محصول تعریف شده را به نام موقعیت /all تغییر دهید.", $shopkeyboard, 'HTML');
        return;
    }
    $product = select("product", "*", "name_product", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Location2', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
    $stmt->bindParam(':Service_location', $text);
    $stmt->bindParam(':name_product', $product['name_product']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅موقعیت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "حجم" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "حجم جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $product = select("product", "*", "id", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Volume_constraint', $text);
    $stmt->bindParam(':name_product', $product['id']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "زمان" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Service_time', $text);
    $stmt->bindParam(':id_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "balanceaddall") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($user['step'] == "add_Balance_all") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    savedata("clear", "price", $text);
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typebalanceall_all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typebalanceall_f'],
                ['text' => "کاربران گروه n", 'callback_data' => 'typebalanceall_nl'],
                ['text' => "کاربران گروه n2", 'callback_data' => 'typebalanceall_n2'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 شارژ برای کدام یک از گروه کاربری زیر واریز شود.", $keyboardagent, 'HTML');
} elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    savedata("save", "agent", $typeagent);
    $keyboardtypeuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typecustomer_all'],
            ],
            [
                ['text' => "کاربرانی که خرید داشتند", 'callback_data' => 'typecustomer_customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typecustomer_notcustomer'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 چه کاربر شارژ همگانی ارسال شود", $keyboardtypeuser);
} elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
    $typecustomer = $dataget[1];
    savedata("save", "typecustomer", $typecustomer);
    nm_adminInstantReply($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟
بله : 1
خیر : 0", $backadmin, 'HTML');
    step("getmeesagestatus", $from_id);
} elseif ($user['step'] == "getmeesagestatus") {
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'], $keyboardadmin, 'HTML');
    $query_where = "";
    if ($userdata['agent'] == "all") {
        if ($userdata['typecustomer'] == "all") {
            $query_where = "";
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = "WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = "WHERE  NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    } else {
        if ($userdata['typecustomer'] == "all") {
            $query_where = null;
            ;
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    }
    $stmt = $pdo->prepare("SELECT u.id FROM user u " . $query_where);
    $stmt->execute();
    $Balance_user = $stmt->fetchAll();
    $stmt = $pdo->prepare("UPDATE user as u SET  Balance = Balance + {$userdata['price']} " . $query_where);
    $stmt->execute();
    step('home', $from_id);
    if ($text == "1") {
        $cancelmessage = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                ],
            ]
        ]);
        $textgift = "🎁 کاربر  عزیز مبلغ {$userdata['price']} تومان از طرف مدیریت به عنوان هدیه به کیف پول شما واریز گردید.";
        $message_id = sendmessage($from_id, "✅ عملیات ارسال پیام آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage, "html");
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $textgift,
            "pingmessage" => "no",
            "btnmessage" => "start"
        ));
        file_put_contents("cronbot/users.json", json_encode($Balance_user));
        file_put_contents('cronbot/info', $data);
    }
} elseif ($text == "⬇️ کم کردن موجودی") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
    step('Negative_Balance', $from_id);
} elseif ($user['step'] == "Negative_Balance") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_Negative") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) >= 100000000) {
        nm_adminInstantReply($from_id, "📌 حداکثر مقدار 100 میلیون ریال است.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');


    $stmtAtomic = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
    $stmtAtomic->bindValue(':delta', (int) $text, PDO::PARAM_INT);
    $stmtAtomic->bindValue(':uid', $user['Processing_value'], PDO::PARAM_STR);
    $stmtAtomic->execute();
    $balances1 = number_format($text, 0);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $textkam = "❌ کاربر عزیز مبلغ $balances1 تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :

🪪 اطلاعات ادمین کم کننده موجودی :
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchuser") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    if ($user['step'] == "show_info") {
        $id_user = $text;
    } elseif (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $dataget[1];
    }
    if (!userExists($id_user)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $_stmt = $connect->prepare("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $dayListSell = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = ? AND Payment_Method != 'low balance by admin'");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $balanceall = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ?");
    $_stmt->bind_param("s", $id_user); $_stmt->execute();
    $subbuyuser = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $invoicecount = select("invoice", '*', "id_user", $id_user, "count");
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $_stmt = $connect->prepare("SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND name_product != 'سرویس تست'");
        $_stmt->bind_param("s", $id_user); $_stmt->execute();
        $sumvolume = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $keyboardmanage = [
        'inline_keyboard' => [
            [['text' => "♻️  بروزرسانی اطلاعات", 'callback_data' => "updateinfouser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
            [['text' => "🎁 درصد تخفیف", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ ارسال پیام به کاربر", 'callback_data' => "sendmessageuser_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
            [['text' => "👥 زیرمجموعه های کاربر", 'callback_data' => "affiliates-" . $id_user]],
            [['text' => "🔄 خارج کردن از زیرمجموعه", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 حذف زیرمجموعه های کاربر", 'callback_data' => "removeaffiliateuser-" . $id_user]],
            [['text' => "💳 فعالسازی شماره کارت", 'callback_data' => "showcarduser-" . $id_user]],
            [['text' => "احراز هویت کاربر", 'callback_data' => "verify_" . $id_user], ['text' => "عدم احراز کاربر", 'callback_data' => "unverify-" . $id_user]],
            [['text' => "💳  غیرفعالسازی شماره کارت", 'callback_data' => "carduserhide-" . $id_user]],
            [['text' => "🛒 افزودن سفارش", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ محدودیت اکانت تست", 'callback_data' => "limitusertest_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "انتقال حساب کاربری ", 'callback_data' => "transferaccount_" . $id_user]],
            [['text' => "💡 خاموش کردن اکانت", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 روشن کردن اکانت", 'callback_data' => "activeconfig-" . $id_user]],
            [['text' => "📑 احراز عضویت کانال", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ صفر کردن موجودی", 'callback_data' => "zerobalance-" . $id_user]],
            [['text' => "🕚 وضعیت ارسال پیام های کرون", 'callback_data' => "statuscronuser-" . $id_user]],
        ]
    ];
    if ($user['agent'] == "n2")
        $keyboardmanage['inline_keyboard'][] = [['text' => "سقف خرید  نماینده", 'callback_data' => "maxbuyagent_" . $id_user]];
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🤖 فعالسازی ربات فروش", 'callback_data' => "createbot_" . $id_user],
            ['text' => "❌ حذف ربات فروش", 'callback_data' => "removebotsell_" . $id_user]
        ];
    }
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🔋 قیمت پایه حجم", 'callback_data' => "setvolumesrc_" . $id_user],
            ['text' => "⏳ قیمت پایه زمان", 'callback_data' => "settimepricesrc_" . $id_user]
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "❌ مخفی کردن یک پنل برای نماینده", 'callback_data' => "hidepanel_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🗑 نمایش پنل های مخفی شده", 'callback_data' => "removehide_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => "expireset_" . $id_user],
        ];
    }
    if (intval($setting['statuslimitchangeloc']) == 1) {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "محدودیت تغییر لوکیشن", 'callback_data' => "changeloclimitbyuser_" . $id_user]
        ];
    }
    $keyboardmanage['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    $userverify = [
        '0' => "احراز نشده",
        '1' => "احراز شده"
    ][$user['verify']];
    $showcart = [
        '0' => "مخفی",
        '1' => "نمایش داده می شود"
    ][$user['cardpayment']];
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    if ($user['agent'] != "f" && $user['expire'] != null) {
        $text_expie_agent = "⭕️ تاریخ پایان نمایندگی : " . jdate('Y/m/d H:i:s', $user['expire']);
    } else {
        $text_expie_agent = "";
    }
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$user['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$user['number']}</code>
⭕️ نوع کاربری : {$user['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$user['affiliatescount']}
⭕  معرف کاربر : {$user['affiliates']}
⭕  وضعیت احراز هویت: $userverify
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$user['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$user['Balance']}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 درصد تخفیف کاربر : {$user['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان

";
    if (is_string($datain) && isset($datain[0]) && $datain[0] == "u") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "اطلاعات بروزرسانی گردید",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } else {
        nm_adminInstantReply($from_id, $textinfouser, $keyboardmanage, 'HTML');
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['GetCode'], $backadmin, 'HTML');
    step('get_code', $from_id);
} elseif ($user['step'] == "get_code") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO Discount (code, limitused) VALUES (:code, :limitused)");
    $value = "0";
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->bindParam(':limitused', $value, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['PriceCode'], null, 'HTML');
    step('get_price_code', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_code") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['setlimituse'], $backadmin, 'HTML');
    update("Discount", "price", $text, "code", $user['Processing_value']);
    step('getlimitcodedis', $from_id);
} elseif ($user['step'] == "getlimitcodedis") {
    step("home", $from_id);
    update("Discount", "limituse", $text, "code", $user['Processing_value']);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['SaveCode'], $keyboardadmin, 'HTML');
} elseif ($text == "❌ حذف کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin, 'HTML');
    step('remove-Discount', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    if (!in_array($text, $code_Discount)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "🗑 حذف پروتکل" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
    step('removeprotocol', $from_id);
} elseif ($user['step'] == "removeprotocol") {
    if (!in_array($text, $protocoldata)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
    $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
    $stmt->execute();
    step('home', $from_id);
} elseif ($text == "💡 روش ساخت نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    $text_username = "⭕️ روش ساخت نام کاربری برای اکانت ها را از دکمه زیر انتخاب نمایید.

⚠️ در صورتی که کاربری نام کاربری نداشته باشه کلمه انتخابی توسط شما ثبت خواهد شد جای نام کاربری اعمال خواهد شد.

⚠️ در صورتی که نام کاربری وجود داشته باشه یک عدد رندوم به نام کاربری اضافه خواهد شد";
    nm_adminInstantReply($from_id, $text_username, $MethodUsername, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از منوی «مدیریت پنل ها» یک پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $allowedMethods = [
        "آیدی عددی + حروف و عدد رندوم",
        "نام کاربری + حروف و عدد رندوم",
        "نام کاربری دلخواه + عدد رندوم",
        "متن دلخواه + عدد رندوم",
        "متن دلخواه + عدد ترتیبی",
        "نام کاربری + عدد به ترتیب",
        "آیدی عددی+عدد ترتیبی",
        "متن دلخواه نماینده + عدد ترتیبی",
        "نام کاربری دلخواه",
    ];
    if (!in_array($text, $allowedMethods, true)) {
        nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است. لطفاً یکی از دکمه‌های زیر را انتخاب کنید.", $MethodUsername, 'HTML');
        return;
    }
    update("marzban_panel", "MethodUsername", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $backadmin, 'HTML');
        return;
    }
    if ($text == "نام کاربری + عدد به ترتیب") {
        step('getnamecustom', $from_id);
        nm_adminInstantReply($from_id, "📌 در صورتی که کاربر نام کاربری نداشت چه اسمی ثبت شود؟", $backadmin, 'HTML');
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
    step('home', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!preg_match('/^\w{3,32}$/', $text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
        return;
    }
    $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
    if ($panelName === '') {
        nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    update("marzban_panel", "namecustom", $text, "name_panel", $panelName);
    update("user", "Processing_value", $panelName, "id", $from_id);
    step('home', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
} elseif (($datain == "cartsetting" || $text == "▶️ بازگشت به منوی تظنیمات کارت") && $adminrulecheck['rule'] == "administrator") {
    step('admin_nav_cart_settings', $from_id);
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
} elseif ($text == "💳 تنظیم شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "💳 شماره کارت خود را ارسال کنید

⚠️ توجه داشته باشید شما می توانید چندین شماره کارت تعریف کنید در صورت تعریف چندین شماره کارت به کاربر یک شماره کارت از بین شماره کارت ها رندوم نشان خواهد داد";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif ($user['step'] == "changecard") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, "❌شماره کارت باید حتما عدد باشد.", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $listcard)) {
        nm_adminInstantReply($from_id, "❌ شماره کارت در دیتابیس وجود دارد.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['getnamecard'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('getnamecard', $from_id);
} elseif ($user['step'] == "getnamecard") {
    try {
        if (function_exists('ensureCardNumberTableSupportsUnicode')) {
            ensureCardNumberTableSupportsUnicode();
        }

        $stmt = $connect->prepare("INSERT INTO card_number (cardnumber,namecard) VALUES (?,?)");
        $stmt->bind_param("ss", $user['Processing_value'], $text);
        $stmt->execute();
        $stmt->close();
        nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['Savacard'], $CartManage, 'HTML');
        step('home', $from_id);
    } catch (\mysqli_sql_exception $e) {
        error_log('Failed to save card number: ' . $e->getMessage());
        if (stripos($e->getMessage(), 'Incorrect string value') !== false) {
            error_log('card_number insert failed due to charset mismatch. Please verify the table collation.');
        }
        nm_adminInstantReply($from_id, "❌ ثبت شماره کارت ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.", $backadmin, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
} elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_plisio");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $rowLegacy = select("PaySetting", "ValuePay", "NamePay", "apinowpayment");
        $PaySetting = is_array($rowLegacy) ? (string)($rowLegacy['ValuePay'] ?? '') : '';
    }
    $textcart = "⚙️ api سایت plisio.net.io را ارسال نمایید

        api plisio :$PaySetting";
    nm_adminInstantReply($from_id, $textcart, $backadmin, 'HTML');
    step('api_plisio', $from_id);
} elseif ($user['step'] == "api_plisio" || $user['step'] == "apinowpayment") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "api_plisio");
    step('home', $from_id);
} elseif ($datain == "iranpay1setting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Swapinokey, 'HTML');
} elseif ($text == "API NOWPAYMENT") {
    $row = select("PaySetting", "ValuePay", "NamePay", "api_nowpayment");
    $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    if ($PaySetting === '' || $PaySetting === '0') {
        $row = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller");
        $PaySetting = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    }
    $safeKey = htmlspecialchars($PaySetting, ENT_QUOTES, 'UTF-8');
    $displayKey = ($PaySetting !== '' && $PaySetting !== '0') ? "<code>$safeKey</code>" : '— تنظیم نشده';
    $texttronseller = "💳 API NowPayments خود را از داشبورد nowpayments.io دریافت و در این قسمت وارد کنید.\n\n"
                    . "🔑 کلید فعلی:\n$displayKey\n\n"
                    . "ℹ️ طول کلید فعلی: " . strlen($PaySetting) . " کاراکتر";
    nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
    step('marchent_tronseller', $from_id);
} elseif ($user['step'] == "marchent_tronseller") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');

    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
    update("PaySetting", "ValuePay", $text, "NamePay", "api_nowpayment");
    step('home', $from_id);
} elseif ($text == "🔐 IPN Secret nowpayment" && $adminrulecheck['rule'] == "administrator") {
    $row = select("PaySetting", "ValuePay", "NamePay", "nowpayment_ipn_secret");
    $currentSecret = is_array($row) ? (string)($row['ValuePay'] ?? '') : '';
    $masked = $currentSecret !== '' ? substr($currentSecret, 0, 4) . str_repeat('*', max(0, strlen($currentSecret) - 8)) . substr($currentSecret, -4) : '— تنظیم نشده';
    $textIpn = "🔐 IPN Secret درگاه NowPayments را از داشبورد NowPayments → Store Settings → IPN Secret دریافت و در این قسمت وارد کنید\n\n"
             . "🔑 مقدار فعلی : <code>$masked</code>\n\n"
             . "⚠️ این مقدار برای اعتبارسنجی امضای IPN استفاده می‌شود و باید با مقدار داخل داشبورد NowPayments دقیقاً یکی باشد.";
    nm_adminInstantReply($from_id, $textIpn, $backadmin, 'HTML');
    step('nowpayment_ipn_secret', $from_id);
} elseif ($user['step'] == "nowpayment_ipn_secret") {
    update("PaySetting", "ValuePay", trim($text), "NamePay", "nowpayment_ipn_secret");
    nm_adminInstantReply($from_id, "✅ IPN Secret با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($datain == "zarinpeysetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpey, 'HTML');
} elseif ($datain == "aqayepardakhtsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $aqayepardakht, 'HTML');
} elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpal, 'HTML');
} elseif ($text == "تنظیم مرچنت آقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را ازآقای پرداخت دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_id_aqayepardakht', $from_id);
} elseif ($user['step'] == "merchant_id_aqayepardakht") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $aqayepardakht, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_id_aqayepardakht");
    step('home', $from_id);
} elseif ($text == "مرچنت زرین پال" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را از زرین پال دریافت و در این قسمت وارد کنید

مرچنت کد فعلی شما : $PaySetting";
    nm_adminInstantReply($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_zarinpal', $from_id);
} elseif ($user['step'] == "merchant_zarinpal") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
    step('home', $from_id);
} elseif ($text == "🗂 نام درگاه زرین پی") {
    nm_adminInstantReply($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpey", $from_id);
} elseif ($user['step'] == "gettextzarinpey") {
    nm_adminInstantReply($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpey, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpey");
    step("home", $from_id);
} elseif ($text == "🔑 توکن زرین پی" && $adminrulecheck['rule'] == "administrator") {
    $token = getPaySettingValue('token_zarinpey', '0');
    $message = "🔑 توکن دسترسی زرین پی خود را ارسال کنید.\n\nتوکن فعلی شما: {$token}";
    nm_adminInstantReply($from_id, $message, $backadmin, 'HTML');
    step('token_zarinpey', $from_id);
} elseif ($user['step'] == "token_zarinpey") {
    update("PaySetting", "ValuePay", $text, "NamePay", "token_zarinpey");
    nm_adminInstantReply($from_id, "✅ توکن با موفقیت ذخیره شد.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰 کش بک زرین پی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpey", $from_id);
} elseif ($user['step'] == "getcashzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpey");
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "🧑🏼‍💻 اموزش اتصال") {
    $inlineKeyboard = json_encode([
        'inline_keyboard' => [
            [
                [
                    'text' => '📞 دریافت API  مشاوره',
                    'url' => 'https://t.me/MiladRajabi2002',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $message = "🚀 درگاه کارت‌به‌کارت خودکار\n\nدرگاه هوشمند ZarinPay اکنون در فاکسیما بات نسخه پرو فعال است!\nتراکنش‌ها با خواندن پیامک بانکی به‌صورت خودکار و لحظه‌ای تأیید می‌شوند ⚡\nبدون نیاز به تأیید دستی، سریع، دقیق و ایمن 💳";

    nm_adminInstantReply($from_id, $message, $inlineKeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ زرین پی") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainzarinpey", $from_id);
} elseif ($user['step'] == "getmainzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpey");
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬆️ حداکثر مبلغ زرین پی") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpey", $from_id);
} elseif ($user['step'] == "getmaaxzarinpey") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpey");
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش زرین پی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .\n۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید\n۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpey", $from_id);
} elseif ($user['step'] == "helpzarinpey") {
    if ($text) {
        if ((int) $text === 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpzarinpey");
        } else {
            $data = json_encode([
                'type' => 'text',
                'text' => $text,
            ], JSON_UNESCAPED_UNICODE);
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
        }
    } elseif ($photo) {
        $data = json_encode([
            'type' => 'photo',
            'text' => $caption,
            'photoid' => $photoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } elseif ($video) {
        $data = json_encode([
            'type' => 'video',
            'text' => $caption,
            'videoid' => $videoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
    update("user", "Processing_value", "0", "id", $from_id);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
    step('GetLocationEdit', $from_id);
} elseif ($user['step'] == "GetLocationEdit") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!is_array($marzban_list_get) || empty($marzban_list_get)) {
        $notFoundMessage = $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.";
        nm_adminInstantReply($from_id, $notFoundMessage, $json_list_marzban_panel, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('PanelMenu', $from_id);
    if ($marzban_list_get['type'] == "marzban") {
        $Check_token = token_panel($marzban_list_get['code_panel'], false);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Stats($text);
            if ((string)($marzban_list_get['version_panel'] ?? '0') === '1') {
                $active_users = $System_Stats['active_users']
                    ?? $System_Stats['users_active']
                    ?? $System_Stats['online_users']
                    ?? 0;
            } else {
                $active_users = $System_Stats['users_active']
                    ?? $System_Stats['active_users']
                    ?? $System_Stats['online_users']
                    ?? 0;
            }
            $total_user = $System_Stats['total_user'];
            $mem_total = formatBytes($System_Stats['mem_total']);
            $mem_used = formatBytes($System_Stats['mem_used']);
            $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
            $rx_listsell_count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSell = number_format((int)($rx_listsell_count['COUNT(*)'] ?? 0));
            $rx_listsell_sum = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSellSUM = number_format((int)($rx_listsell_sum['SUM(price_product)'] ?? 0));

            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
📡 نسخه پنل مرزبان :  {$System_Stats['version']}
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل مرزبان  : $mem_used
🌐 ترافیک کل مصرف شده  ( آپلود / دانلود) : $bandwidth
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $errorDetails = json_encode($Check_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($errorDetails) && $errorDetails !== 'null') {
                $text_marzban .= PHP_EOL . "علت خطا: {$errorDetails}";
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "guard") {
        $guardConfig = getGuardPanelConfig($marzban_list_get['name_panel']);
        if ($guardConfig['status'] === false) {
            $errorMsg = $guardConfig['msg'] ?? $textbotlang['Admin']['managepanel']['guard']['connection_error'];
            nm_adminInstantReply($from_id, "❌ {$errorMsg}", $optionGuard, 'HTML');
        } else {
            $testResult = guardTestConnection($guardConfig['panel']['url_panel'], $guardConfig['api_key']);
            $statusText = guardFormatConnectionResult($testResult);
            $text_marzban = "{$statusText}\nگروه کاربری :{$marzban_list_get['agent']}"
                . "\n\n⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionGuard, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "alireza_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionalireza_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "The username or password is incorrect") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionalireza_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
            }
            nm_adminInstantReply($from_id, $text_marzban, $optionalireza_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "hiddify") {
        $System_Stats = serverstatus($marzban_list_get['name_panel']);
        if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
            $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
            nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } elseif (!empty($System_Stats['error'])) {
            $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است  خطا : " . $System_Stats['error'];
            nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } else {
            $System_Stats = json_decode($System_Stats['body'], true);
            if (isset($System_Stats['stats'])) {
                $mem_total = round($System_Stats['stats']['system']['ram_total'], 2);
                $mem_used = round($System_Stats['stats']['system']['ram_used'], 2);
                $outgoingBandwidth = 0;
                $incomingBandwidth = 0;

                if (isset($System_Stats['outgoing_bandwidth']) || isset($System_Stats['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['incoming_bandwidth'] ?? 0);
                } elseif (isset($System_Stats['stats']['outgoing_bandwidth']) || isset($System_Stats['stats']['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['stats']['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['stats']['incoming_bandwidth'] ?? 0);
                }

                $bandwidth = formatBytes($outgoingBandwidth + $incomingBandwidth);
                $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل : ✅ پنل متصل است
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل   : $mem_used
گروه کاربری :{$marzban_list_get['agent']}
⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } elseif (isset($System_Stats['message']) && $System_Stats['message'] == "Unathorized") {
                $text_marzban = "❌  لینک پنل اشتباه ارسال شده است";
                nm_adminInstantReply($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } else {
                nm_adminInstantReply($from_id, "پنل متصل نیست", $optionhiddfy, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "Manualsale") {
        nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionManualsale, 'HTML');
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $Check_token = token_panelm($marzban_list_get['code_panel']);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Statsm($text);
            if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
                $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
                nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            } elseif (!empty($System_Stats['error'])) {
                $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است  خطا : " . $System_Stats['error'];
                nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            }
            $System_Stats = json_decode($System_Stats['body'], true);
            $active_users = $System_Stats['active'];
            $total_user = $System_Stats['total'];
            $rx_listsell_count2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSell = number_format((int)($rx_listsell_count2['COUNT(*)'] ?? 0));
            $rx_listsell_sum2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"));
            $ListSellSUM = number_format((int)($rx_listsell_sum2['SUM(price_product)'] ?? 0));
            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            nm_adminInstantReply($from_id, $text_marzban, $optionmarzneshin, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . json_encode($Check_token);
            nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "WGDashboard") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $optionwg, 'HTML');
    } elseif ($marzban_list_get['type'] == "s_ui") {
        nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $options_ui, 'HTML');
    } elseif ($marzban_list_get['type'] == "ibsng") {
        $result = loginIBsng($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if ($result) {
            nm_adminInstantReply($from_id, $result['msg'], $optionibsng, 'HTML');
        } else {
            nm_adminInstantReply($from_id, $result['msg'], $optionibsng, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $result = login_mikrotik($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($result['error'])) {
            nm_adminInstantReply($from_id, json_encode($result), $option_mikrotik, 'HTML');
        } else {
            $free_hdd_space = round($result['free-hdd-space'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $total_hdd_space = round($result['total-hdd-space'] / pow(1024, 3), 2);
            $total_memory = round($result['total-memory'] / pow(1024, 3), 2);
            nm_adminInstantReply($from_id, "<b>📡 اطلاعات سیستم MikroTik شما:</b>

<blockquote>
🖥 <b>پلتفرم:</b> {$result['platform']}
🏷 <b>نسخه:</b> {$result['version']}
🕰 <b>مدت زمان روشن بودن:</b> {$result['uptime']}
</blockquote>

<blockquote>
💽 <b>نام معماری:</b> {$result['architecture-name']}
📋 <b>مدل برد:</b> {$result['board-name']}
🏗 <b>زمان ساخت سیستم:</b> {$result['build-time']}
</blockquote>

<blockquote>
⚙️ <b>پردازنده:</b> {$result['cpu']}
🔢 <b>تعداد هسته‌ها:</b> {$result['cpu-count']}
🚀 <b>فرکانس CPU:</b> {$result['cpu-frequency']}
📊 <b>میزان بار CPU:</b> {$result['cpu-load']} %
</blockquote>

<blockquote>
💾 <b>فضای کل هارد:</b> $total_hdd_space گیگ
📂 <b>فضای آزاد هارد:</b> $free_hdd_space گیگ
🧠 <b>حافظه کل رم:</b> $total_memory گیگ
📉 <b>حافظه آزاد رم:</b> $free_memory گیگ
</blockquote>

<blockquote>
📝 <b>سکتورهای نوشته‌شده از زمان ریبوت:</b> {$result['write-sect-since-reboot']}
🧮 <b>مجموع سکتورهای نوشته‌شده:</b> {$result['write-sect-total']}
</blockquote>
", $option_mikrotik, 'HTML');
        }
    } else {
        nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionMarzban, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
}