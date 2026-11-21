<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminAction, $adminChannelMenu, $adminExecution, $adminPanelsMenu, $adminUsersMenu, $admin_ids, $adminrulecheck, $atlaspay, $backadmin, $blupal, $change_product, $cubepay, $datain, $domainhostsEscaped, $from_id, $iranpaykeyboard, $keyboard_Category_manage, $keyboard_shop_manage, $keyboard_stat, $keyboardadmin, $keyboardhelpadmin, $keyboardzarinpal, $message_id, $miniAppInstructionText, $nowpayment_setting_keyboard, $resolvedName, $rialit, $rx_back_origin, $rx_pem_entry_step, $setting, $setting_panel, $shopkeyboard, $text_panel_admin_login_template, $textbot, $textbotlang, $tonpay, $trnado, $tronnowpayments, $variza, $version.
 * Inherited or exported by reference: $channelkeyboard, $rawProcessing, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DashboardAndNavigation
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $Startelegram,
        $abangateway,
        $adminAction,
        $adminChannelMenu,
        $adminExecution,
        $adminPanelsMenu,
        $adminUsersMenu,
        $admin_ids,
        $adminrulecheck,
        $atlaspay,
        $backadmin,
        $blupal,
        $change_product,
        &$channelkeyboard,
        $cubepay,
        $datain,
        $domainhostsEscaped,
        $from_id,
        $iranpaykeyboard,
        $keyboard_Category_manage,
        $keyboard_shop_manage,
        $keyboard_stat,
        $keyboardadmin,
        $keyboardhelpadmin,
        $keyboardzarinpal,
        $message_id,
        $miniAppInstructionText,
        $nowpayment_setting_keyboard,
        &$rawProcessing,
        $resolvedName,
        $rialit,
        $rx_back_origin,
        $rx_pem_entry_step,
        $setting,
        $setting_panel,
        $shopkeyboard,
        $text_panel_admin_login_template,
        $textbot,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        &$user,
        $variza,
        $version
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            if (\Faoxima\Services\Panels\PanelSetup::rxAddPanelIsStep($rx_pem_entry_step ?? ($user['step'] ?? ''))) \Faoxima\Services\Panels\PanelSetup::rxAddPanelResetDraft($from_id);
            if ($datain == "admin")
                deletemessage($from_id, $message_id);
            if (\Faoxima\Admin\Telegram\ChannelNavigation::rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck)) {
                \Faoxima\Admin\Telegram\ChannelNavigation::rxAdminPanelEntryForceChannelReportSetup($from_id, $setting, $textbotlang, $backadmin);
                if (isset($user) && is_array($user)) { $user['step'] = 'addchannelid'; }
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            if (isset($user) && is_array($user)) { $user['step'] = 'home'; }
            if (function_exists('removeReplyKeyboardOnStartIfNeeded')) {
                removeReplyKeyboardOnStartIfNeeded($from_id, true);
            }
            $version_mini_app = file_get_contents('app/version');
            $rxCronAutoStatus = method_exists(\Faoxima\Core\Cron\Crontab::class, 'activecronStatus') ? \Faoxima\Core\Cron\Crontab::activecronStatus() : ['status' => 'error', 'user' => null];
            if (method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'rxBuildMiniAppInstructionText')) {
                $miniAppInstructionText = \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::rxBuildMiniAppInstructionText($domainhostsEscaped, $rxCronAutoStatus);
            }
            $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
            $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
            if ($miniAppInstructionHidden !== '1') {
                $miniAppInstructionKeyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'دیگر نمایش نده ⛓️‍💥', 'callback_data' => 'hide_mini_app_instruction'],
                        ],
                    ],
                ]);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $miniAppInstructionText, $miniAppInstructionKeyboard, 'HTML');
            }
            return;
            break;
        case 2:
            if (\Faoxima\Services\Panels\PanelSetup::rxAddPanelIsStep($rx_pem_entry_step ?? ($user['step'] ?? ''))) \Faoxima\Services\Panels\PanelSetup::rxAddPanelResetDraft($from_id);
            if (\Faoxima\Admin\Telegram\ChannelNavigation::rxAdminPanelEntryRequiresChannelReport($setting, $adminrulecheck)) {
                \Faoxima\Admin\Telegram\ChannelNavigation::rxAdminPanelEntryForceChannelReportSetup($from_id, $setting, $textbotlang, $backadmin);
                if (isset($user) && is_array($user)) { $user['step'] = 'addchannelid'; }
                return;
            }
            if (is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser'])) {
                $rawProcessing = (string)($user['Processing_value'] ?? '');
                if ($rawProcessing !== '' && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
                    $resolvedName = \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user);
                    if ($resolvedName !== '') {
                        \Faoxima\Core\Database\Db::update("user", "Processing_value", $resolvedName, "id", $from_id);
                    }
                }
                unset($rawProcessing, $resolvedName);
            }
            $version_mini_app = file_get_contents('app/version');
            $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_admin, $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            return;
            break;
        case 3:
            if (!in_array($from_id, $admin_ids))
                return;
            if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
                \Faoxima\Core\Database\Db::update("user", "hide_mini_app_instruction", "1", "id", $from_id);
                $user['hide_mini_app_instruction'] = '1';
            }
            $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
            if (method_exists(\Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::class, 'rxBuildMiniAppInstructionText')) {
                $rxHideCronStatus = method_exists(\Faoxima\Core\Cron\Crontab::class, 'activecronStatus') ? \Faoxima\Core\Cron\Crontab::activecronStatus() : ['status' => 'error', 'user' => null];
                $miniAppInstructionText = \Faoxima\Admin\Telegram\Bootstrap\RuntimeHelpers::rxBuildMiniAppInstructionText($domainhostsEscaped, $rxHideCronStatus);
            }
            $confirmationText = $miniAppInstructionText . "\n\n✅ این پیام دیگر برای شما نمایش داده نخواهد شد.";
            Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
            return;
            break;
        case 4:
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            $panelNameBack = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            if ($panelNameBack !== '' && $panelNameBack !== '0') {
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelNameBack, "select");
                if (is_array($typepanel) && !empty($typepanel)) {
                    \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
                    return;
                }
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
            return;
            break;
        case 5:
            $currentStep = isset($user['step']) ? (string) $user['step'] : '';
            if (\Faoxima\Services\Panels\PanelSetup::rxAddPanelIsStep($currentStep)) {
                $draft = json_decode((string) ($user['Processing_value'] ?? ''), true);
                $panelTypeBack = is_array($draft) ? (string) ($draft['type'] ?? '') : '';
                if ($currentStep !== 'add_name_panel') {
                    $previousPanelStep = method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'stepBack') ? \Faoxima\Telegram\Navigation\Steps::stepBack($from_id) : null;
                    if ($previousPanelStep !== null && \Faoxima\Services\Panels\PanelSetup::rxAddPanelIsStep($previousPanelStep)) {
                        \Faoxima\Telegram\Navigation\Steps::step($previousPanelStep, $from_id);
                        $user['step'] = $previousPanelStep;
                        if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'rxRenderWizardStep') && \Faoxima\Telegram\Navigation\Steps::rxRenderWizardStep($previousPanelStep, $from_id)) return;
                    }
                }
                \Faoxima\Services\Panels\PanelSetup::rxAddPanelShowCategory($from_id, $panelTypeBack);
                return;
            }

            if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'rxWizardStepFlow') && \Faoxima\Telegram\Navigation\Steps::rxWizardStepFlow($currentStep) !== null) {
                $rx_prev_step = method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'stepBack') ? \Faoxima\Telegram\Navigation\Steps::stepBack($from_id) : null;
                if ($rx_prev_step !== null && method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'rxRenderWizardStep') && \Faoxima\Telegram\Navigation\Steps::rxRenderWizardStep($rx_prev_step, $from_id)) {
                    return;
                }
                if ($rx_prev_step === null && method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'rxWizardExitFirstStep') && \Faoxima\Telegram\Navigation\Steps::rxWizardExitFirstStep($currentStep, $from_id)) {
                    return;
                }
            }

            if (($currentStep === 'get_time_start' || $currentStep === 'get_time_end') && isset($keyboard_stat)) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📊 منوی آمار ربات", $keyboard_stat, 'HTML');
                return;
            }
            if ($currentStep === 'gettextday' || $currentStep === 'gettextSystemMessage') {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $rx_broadcastMenu = json_encode(['inline_keyboard' => [
                    [['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage']],
                    [['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage']],
                    [['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage']],
                    [['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage']],
                    [['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser']],
                ]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $rx_broadcastMenu, 'HTML');
                return;
            }

            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavBack') && \Faoxima\Telegram\Navigation\NavParents::rxNavBack($from_id, isset($rx_back_origin) ? $rx_back_origin : null, $currentStep)) {
                return;
            }

            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);

            $adminPanelFlowSteps = [
                'GetLocationEdit', 'PanelMenu',
                'add_link_panel', 'add_username_panel', 'add_password_panel',
                'add_remna_token_setup', 'getlimitedpanel', 'add_guard_version', 'add_guard_api_key',
                'guard_svc_edit', 'guard_edit_api_key',
                'add_rebecca_api_key', 'rebecca_edit_api_key',
                'add_pasarguard_api_key', 'pasarguard_edit_api_key',
                'add_pasarguard_auth_method', 'add_pasarguard_username', 'add_pasarguard_password',
                'pasarguard_edit_username', 'pasarguard_edit_password',
                'confirmremovepanel', 'add_link_panel_edit', 'getlocoption',
                'switchtype_pick', 'switchtype_link_panel', 'switchtype_username_panel',
                'switchtype_password_panel', 'switchtype_guard_version', 'switchtype_guard_api_key', 'switchtype_remna_token',
                'switchtype_rebecca_api_key', 'switchtype_pasarguard_api_key',
                'switchtype_pasarguard_auth_method', 'switchtype_pasarguard_username', 'switchtype_pasarguard_password',
                'switchtype_xui_api_mode', 'switchtype_xui_token', 'switchtype_confirm',
            ];
            if (in_array($currentStep, $adminPanelFlowSteps, true)) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
                return;
            }

            if ($currentStep === 'remna_panel_back') {
                $panelNameBack = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
                if ($panelNameBack !== '' && $panelNameBack !== '0') {
                    $typepanelBack = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelNameBack, "select");
                    if (is_array($typepanelBack) && !empty($typepanelBack) && method_exists(\Faoxima\Services\Panels\PanelSetup::class, 'outtypepanel')) {
                        \Faoxima\Services\Panels\PanelSetup::outtypepanel($typepanelBack['type'], $textbotlang['Admin']['Back-menu']);
                        return;
                    }
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
                return;
            }

            $adminProductFlowSteps = [
                'get_limit', 'get_agent', 'get_location', 'getcategory', 'get_time',
                'get_price', 'gettimereset', 'getnote', 'endstep', 'selectloc',
                'remove-product',
            ];
            if (in_array($currentStep, $adminProductFlowSteps, true) && isset($keyboard_shop_manage)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
                return;
            }

            $adminCategoryFlowSteps = [
                'getremarkcategory', 'removecategory', 'editcategory_name', 'get_name_new_category',
            ];
            if (in_array($currentStep, $adminCategoryFlowSteps, true) && isset($keyboard_Category_manage)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
                return;
            }

            $adminQuickPriceFlowSteps = [
                'getpricef', 'getpricnn', 'getpricnn2',
                'getpriceftime', 'getpricnntime', 'getpricnn2time',
            ];
            if (in_array($currentStep, $adminQuickPriceFlowSteps, true) && isset($adminPanelsMenu)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $adminPanelsMenu, 'HTML');
                return;
            }

            if (strpos($currentStep, 'get_remna_') === 0 || in_array($currentStep, ["updatetime", "val_usertest", "tset_limit", "getlimitnew", "panellimit_getnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
                $panelNameBack = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
                if ($panelNameBack !== '') {
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelNameBack, "id", $from_id);
                }
                $typepanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $panelNameBack !== '' ? $panelNameBack : $user['Processing_value'], "select");
                \Faoxima\Services\Panels\PanelSetup::outtypepanel(is_array($typepanel) ? $typepanel['type'] : '', $textbotlang['Admin']['Back-menu']);
            } else {
                $financialStepKeyboardMap = [
                    'helpiranpay2'        => $trnado,
                    'helpofflinearze'     => $tronnowpayments,
                    'helpzarinpal'        => $keyboardzarinpal,
                    'minbalanceiranpay'   => $iranpaykeyboard,
                    'maxbalanceiranpay'   => $iranpaykeyboard,
                    'api_plisio'          => $NowPaymentsManage,
                    'gettimeauto'         => $CartManage,
                    'getidExceptio'       => $CartManage,
                    'getidExceptioremove' => $CartManage,
                    'getidTrustAdd'       => $CartManage,
                    'getidTrustRemove'    => $CartManage,
                    'apiternado' => $trnado,
                    'ipnsigningkeytronado' => $trnado,
                    'wageFromBusinessPercentageTronado' => $trnado,
                    'apitonpay' => $tonpay,
                    'apitonpaycustom' => $tonpay,
                    'getcashtonpay' => $tonpay,
                    'getmaintonpay' => $tonpay,
                    'getmaxtonpay' => $tonpay,
                    'helptonpay' => $tonpay,
                    'apirialit' => $rialit,
                    'getcashrialit' => $rialit,
                    'getmainrialit' => $rialit,
                    'getmaxrialit' => $rialit,
                    'helprialit' => $rialit,
                    'gettextrialit' => $rialit,
                    'rialit_trustips' => $rialit,
                    'rialit_trustproxies' => $rialit,
                    'apicubepay' => $cubepay,
                    'getcashcubepay' => $cubepay,
                    'getfeecubepay' => $cubepay,
                    'getmaincubepay' => $cubepay,
                    'getmaxcubepay' => $cubepay,
                    'helpcubepay' => $cubepay,
                    'apiblupal' => $blupal,
                    'getcashblupal' => $blupal,
                    'getmainblupal' => $blupal,
                    'getmaxblupal' => $blupal,
                    'helpblupal' => $blupal,
                    'apivariza' => $variza,
                    'variza_webhook_secret' => $variza,
                    'getcashvariza' => $variza,
                    'getmainvariza' => $variza,
                    'getmaxvariza' => $variza,
                    'helpvariza' => $variza,
                    'urlabangateway' => $abangateway,
                    'apiabangateway' => $abangateway,
                    'getcashabangateway' => $abangateway,
                    'getmainabangateway' => $abangateway,
                    'getmaxabangateway' => $abangateway,
                    'helpabangateway' => $abangateway,
                    'apiatlaspay' => $atlaspay,
                    'getcashatlaspay' => $atlaspay,
                    'getmainatlaspay' => $atlaspay,
                    'getmaxatlaspay' => $atlaspay,
                    'helpatlaspay' => $atlaspay,
                    'changecard' => $CartManage,
                    'getnamecard' => $CartManage,
                    'getcardremove' => $CartManage,
                    'getnamecarttocart' => $CartManage,
                    'getnamenowpayment' => $nowpayment_setting_keyboard,
                    'getnamecarttopaynotverify' => $CartManage,
                    'gettextnowpayment' => $NowPaymentsManage,
                    'gettextnowpaymentTRON' => $tronnowpayments,
                    'gettextstartelegram' => $Startelegram,
                    'gettextiranpay3' => $trnado,
                    'gettexttonpay' => $tonpay,
                    'gettextcubepay' => $cubepay,
                    'gettextblupal' => $blupal,
                    'gettextvariza' => $variza,
                    'gettextabangateway' => $abangateway,
                    'gettextatlaspay' => $atlaspay,
                    'gettextiranpay1' => $iranpaykeyboard,
                    'gettextzarinpal' => $keyboardzarinpal,
                    'merchant_zarinpal' => $keyboardzarinpal,
                    'apinowpayment' => $NowPaymentsManage,
                    'nowpayment_ipn_secret' => $nowpayment_setting_keyboard,
                    'marchent_tronseller' => $nowpayment_setting_keyboard,
                    'getcashcart' => $CartManage,
                    'getcashiranpay2' => $trnado,
                    'getcashplisio' => $CartManage,
                    'getcashnowpayment' => $nowpayment_setting_keyboard,
                    'getcashzarinpal' => $keyboardzarinpal,
                    'getmaincart' => $CartManage,
                    'getmaxcart' => $CartManage,
                    'getmainplisio' => $NowPaymentsManage,
                    'getmaxplisio' => $NowPaymentsManage,
                    'getmaindigitaltron' => $tronnowpayments,
                    'getmaxdigitaltron' => $tronnowpayments,
                    'getmainiranpay2' => $trnado,
                    'getmaaxiranpay2' => $trnado,
                    'getmainaqzarinpal' => $keyboardzarinpal,
                    'getmaaxzarinpal' => $keyboardzarinpal,
                    'gethelpcart' => $CartManage,
                    'gethelpnowpayment' => $nowpayment_setting_keyboard,
                    'gethelpperfect' => $CartManage,
                    'gethelpplisio' => $CartManage,
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
                    if (method_exists(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::class, 'sendAdminFinanceMenu')) {
                        \Faoxima\Admin\Keyboard\AdminBuildersHelpers::sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
                    } else {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
                    }
                    return;
                }

                if (in_array($currentStep, ['admin_nav_cron_settings', 'admin_nav_cron_jobs'], true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_settings', $from_id);
                    return;
                }

                if ($currentStep === 'cronjob_set_value') {
                    if (method_exists(\Faoxima\Core\Cron\Schedules::class, 'buildCronJobsKeyboard')) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], \Faoxima\Core\Cron\Schedules::buildCronJobsKeyboard(), 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_jobs', $from_id);
                    } else {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                        \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_settings', $from_id);
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
                    if ($walletOrigin === 'trnado') {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $trnado, 'HTML');
                    } elseif (method_exists(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::class, 'sendAdminFinanceMenu')) {
                        \Faoxima\Admin\Keyboard\AdminBuildersHelpers::sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
                    } else {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
                    }
                    return;
                }

                if (isset($financialStepKeyboardMap[$currentStep])) {
                    $targetKeyboard = $financialStepKeyboardMap[$currentStep];
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
                    return;
                }

                if (isset($productStepKeyboardMap[$currentStep])) {
                    $targetKeyboard = $productStepKeyboardMap[$currentStep];
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
                    return;
                }

                $financeSteps = [
                    'marchent_tronseller', 'cryptowallet_set',
                    'admin_nav_finance', 'maxbalance', 'minbalance', 'CartDirect', 'showcardallusers',
                    'apiiranpay', 'getnameconfigm',
                ];
                if (in_array($currentStep, $financeSteps, true)) {
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    if (method_exists(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::class, 'sendAdminFinanceMenu')) {
                        \Faoxima\Admin\Keyboard\AdminBuildersHelpers::sendAdminFinanceMenu($from_id, $textbotlang['Admin']['Back-menu'] ?? 'بازگشت به منوی مالی');
                    } else {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
                    }
                    return;
                }

                $financeSpecificMap = [
                    'getmainiranpay3' => $trnado,    'getmaaxiranpay3' => $trnado,
                    'gethelpiranpay3' => $trnado,    'getcashiranpay3' => $trnado,
                    'getcashiranpay2'  => $trnado,
                    'getmainiranpay2'  => $trnado,   'getmaaxiranpay2'  => $trnado,
                    'getmainiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
                    'getmaaxiranpay1'  => $iranpaykeyboard ?? $keyboardadmin,
                    'gethelpiranpay2'  => $trnado,
                    'getagentbalancemax' => $shopkeyboard,
                    'getagentbalancemin' => $shopkeyboard,
                    'getmaindigitaltron2' => $tronnowpayments,
                    'getmaxdigitaltron2'  => $tronnowpayments,
                ];
                if (isset($financeSpecificMap[$currentStep])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $financeSpecificMap[$currentStep], 'HTML');
                    return;
                }

                $discountSteps = [
                    'getdiscont', 'getfirstdiscount', 'getlimitcode', 'getlimitcodedis',
                    'getlocdiscount', 'getproductdiscount', 'gettimediscount', 'gettypeagentoflist',
                    'gettypecodeagent', 'getuseuser', 'getmaxbuyagent', 'getpercentuser',
                    'setpercentage', 'remove-Discount', 'remove-Discountsell', 'get_price_code',
                    'get_price_codesell', 'get_price_Negative', 'Negative_Balance',
                    'getagent', 'getpricecashback', 'stependforaddorder',
                    'getnameproduct', 'add_Balance_all', 'setbanner', 'show_info', 'reject-dec',
                    'get_number_limit',
                ];
                if (in_array($currentStep, $discountSteps, true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
                    return;
                }

                $textEditSteps = [
                    'text_Add_Balance', 'text_Discount', 'text_Tariff_list', 'text_affiliates',
                    'text_afterpaytext', 'text_aftertesttext',
                    'text_cart', 'text_channel', 'text_crontest',
                    'text_dec_Tariff_list', 'text_dec_fq', 'text_extend', 'text_fq',
                    'text_help', 'text_pishinvoice', 'text_request_agent_dec', 'text_roll',
                    'text_sell', 'text_support', 'text_textmanual', 'text_wgdashboard',
                    'text_wheel_luck', 'textpanelagent', 'textrequestagent', 'textselectlocation',
                    'changetextinfo', 'changetextstart', 'changetextusertest',
                ];
                if (in_array($currentStep, $textEditSteps, true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $textbot, 'HTML');
                    return;
                }

                $helpSteps = [
                    'changecategoryhelp', 'changedeshelp', 'changemedia', 'changenamehelp',
                    'add_name_help', 'getcatgoryhelp', 'remove_help', 'getconfigtext',
                    'getservceid',
                ];
                if (in_array($currentStep, $helpSteps, true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardhelpadmin, 'HTML');
                    return;
                }

                if ($currentStep === 'limit_usertest_allusers') {
                    $usersHubKb = isset($adminUsersMenu) ? $adminUsersMenu : $keyboardadmin;
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $usersHubKb, 'HTML');
                    return;
                }

                $settingsSteps = [
                    'getnamepanelconfig', 'getusernameconfig',
                    'idsupportset', 'get_codesell',
                ];
                if (in_array($currentStep, $settingsSteps, true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $setting_panel, 'HTML');
                    return;
                }

                $userSteps = [
                    'accountwallet', 'add_dec', 'addbalancemanual', 'addbalanceuser',
                    'addbalanceusercurrent', 'adddecriptionblock', 'getuserhide',
                    'getuserhideforremove', 'addadmin', 'getrule', 'GetusernameconfigAndOrdedrs',
                    'antispam_get_count', 'antispam_get_mute', 'antispam_get_seconds',
                    'getbtnresponseforward', 'getmessageAsAdmin', 'getmessageforward',
                    'sendmessagetext', 'sendmessagetid', 'getcountcreate', 'getagentpanel',
                    'getinboundiid', 'getuuidadmin', 'getprotocoldisable', 'getprotocolx_ui',
                    'getvolumesconfig', 'GeturlNewx',
                    'getInbounddisable', 'getusernameconfigcr', 'removeprotocol',
                    'GetPriceExtratime', 'GetPricecustomvo', 'GetPricetimeextra',
                    'GetmaineExtra', 'Getmaintime', 'GetmaxeExtra', 'Getmaxtime',
                ];
                if (in_array($currentStep, $userSteps, true)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $keyboardadmin, 'HTML');
                    return;
                }

                $shopSteps = [
                    "selectlocedite",
                    "GetPriceExtra",
                    "GetPriceexstratime",
                    "GetPricecustomtime",
                    "GetPricecustomvolume",
                    "get_code",
                    "get_codesell",
                    "minbalancebulk",
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
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
                    return;
                } elseif (in_array($currentStep, ["ch_edit_remark", "ch_edit_linkjoin", "ch_edit_link"], true)) {
                    $chanId = (int)($user['Processing_value'] ?? 0);
                    if ($chanId > 0 && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
                        \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id);
                        return;
                    }
                    $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                    \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
                    return;
                } elseif ($currentStep === "channel_manage") {
                    $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                    \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
                    return;
                } elseif ($currentStep === "channel") {
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxRenderMenuState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxRenderMenuState('channelhub', $from_id);
                        return;
                    }
                    \Faoxima\Telegram\Navigation\Steps::step('channelhub', $from_id);
                    if (isset($user) && is_array($user)) {
                        $user['step'] = 'channelhub';
                    }
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channelhub');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
                    return;
                } elseif ($currentStep === "channelhub") {
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxRenderMenuState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxRenderMenuState('home', $from_id);
                        return;
                    }
                    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                    if (isset($user) && is_array($user)) {
                        $user['step'] = 'home';
                    }
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'home');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
                    return;
                } elseif ($currentStep === "addchannelid") {
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxRenderMenuState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxRenderMenuState('channelhub', $from_id);
                        return;
                    }
                    \Faoxima\Telegram\Navigation\Steps::step('channelhub', $from_id);
                    if (isset($user) && is_array($user)) {
                        $user['step'] = 'channelhub';
                    }
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channelhub');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
                    return;
                } elseif (in_array($currentStep, ["addchannel", "removechannel", "getremark", "getlinkjoin"], true)) {
                    $channelkeyboard = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : $channelkeyboard;
                    \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                    if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                        \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channel');
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
                    return;
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
                }
            }
            return;
            break;
        }

        return true;
    }
}
