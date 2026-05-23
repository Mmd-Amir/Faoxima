<?php

require_once 'config.php';


if (!isset($from_id))           { $from_id = 0; }
if (!isset($datain))            { $datain = ''; }
if (!isset($text))              { $text = ''; }
if (!isset($message_id))        { $message_id = 0; }
if (!isset($callback_query_id)) { $callback_query_id = ''; }
if (!isset($username))          { $username = ''; }
if (!isset($first_name))        { $first_name = ''; }

// Bail early if DB is not configured — prevents a cascade of null->prepare() fatals
// and lets the bot return cleanly instead of 500ing.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('DB_UNAVAILABLE', 'Keyboard layouts loaded with no PDO; skipping DB-dependent setup.', [
            'where' => 'layouts_1',
        ]);
    }
    return;
}

$setting = select("setting", "*", null, null,"select");
$textbotlang = languagechange(REFACTORED_LEGACY_ROOT.'/text.json');
if (!is_array($textbotlang)) {
    $textbotlang = [];
}
if (!isset($textbotlang['Admin']) || !is_array($textbotlang['Admin'])) {
    $textbotlang['Admin'] = [];
}
$textbotlang['Admin']['backadmin'] = $textbotlang['Admin']['backadmin'] ?? "🏠 بازگشت به منوی مدیریت";
$textbotlang['Admin']['backmenu'] = $textbotlang['Admin']['backmenu'] ?? "▶️ بازگشت به منوی قبل";
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}

if (!function_exists('rx_inlineButtonMapFile')) {
    function rx_inlineButtonMapFile() {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/').'/rx_inline_button_map.json';
    }
}
if (!function_exists('rx_storeInlineButtonText')) {
    function rx_storeInlineButtonText($text) {
        $code = 'rxb_' . substr(hash('sha256', (string)$text), 0, 24);
        $file = rx_inlineButtonMapFile();
        $map = [];
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            if (is_array($decoded)) $map = $decoded;
        }
        $map[$code] = (string)$text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveInlineButtonText')) {
    function rx_resolveInlineButtonText($code) {
        if (!is_string($code) || strpos($code, 'rxb_') !== 0) return null;
        $file = rx_inlineButtonMapFile();
        if (!is_file($file)) return null;
        $map = json_decode((string)@file_get_contents($file), true);
        return is_array($map) && isset($map[$code]) ? (string)$map[$code] : null;
    }
}
if (!function_exists('rx_safeCallbackData')) {
    function rx_safeCallbackData($callbackData, $buttonText = '') {
        $callbackData = (string)$callbackData;
        if ($callbackData === '') $callbackData = (string)$buttonText;
        return strlen($callbackData) <= 64 ? $callbackData : rx_storeInlineButtonText($buttonText !== '' ? $buttonText : $callbackData);
    }
}

if (!function_exists('rx_keyboardToInline')) {
    function rx_keyboardToInline($keyboardArray) {
        if (!is_array($keyboardArray)) return json_encode($keyboardArray);
        if (isset($keyboardArray['inline_keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!isset($keyboardArray['keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $inline = ['inline_keyboard' => []];
        foreach ($keyboardArray['keyboard'] as $row) {
            $newRow = [];
            foreach ($row as $btn) {
                if (isset($btn['text'])) {
                    $buttonText = (string) $btn['text'];
                    $callbackData = isset($btn['callback_data']) ? (string)$btn['callback_data'] : $buttonText;
                    if ($buttonText === ($GLOBALS['textbotlang']['users']['backbtn'] ?? '')) {
                        $callbackData = 'backuser';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backadmin'] ?? '')) {
                        $callbackData = 'admin';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backmenu'] ?? '')) {
                        $callbackData = 'backmenu';
                    }
                    $newRow[] = ['text' => $buttonText, 'callback_data' => rx_safeCallbackData($callbackData, $buttonText)];
                }
            }
            if (!empty($newRow)) $inline['inline_keyboard'][] = $newRow;
        }
        return json_encode($inline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}


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
    'aqayepardakht' => '',
    'zarinpey' => '',
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
    $textdatabot =  select("textbot", "*", null, null,"fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
$adminrulecheck = select("admin", "*", "id_admin", $from_id,"select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
$users = select("user", "*", "id", $from_id,"select");
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
$replacements = [
    'text_usertest' => $datatextbot['text_usertest'],
    'text_Purchased_services' => $datatextbot['text_Purchased_services'],
    'text_support' => $datatextbot['text_support'],
    'text_help' => $datatextbot['text_help'],
    'accountwallet' => $datatextbot['accountwallet'],
    'text_sell' => $datatextbot['text_sell'],
    'text_Tariff_list' => $datatextbot['text_Tariff_list'],
    'text_affiliates' => $datatextbot['text_affiliates'],
    'text_wheel_luck' => $datatextbot['text_wheel_luck'],
    'text_extend' => $datatextbot['text_extend']
];
$admin_idss = select("admin", "*", "id_admin", $from_id,"count");
$temp_addtional_key = [];
$keyboardLayout = json_decode($setting['keyboardmain'], true);
$keyboardRows = [];
if (is_array($keyboardLayout) && isset($keyboardLayout['keyboard']) && is_array($keyboardLayout['keyboard'])) {
    $keyboardRows = $keyboardLayout['keyboard'];
}

if ($setting['inlinebtnmain'] == "oninline" && !empty($keyboardRows)) {
    $trace_keyboard = $keyboardRows;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == "text_sell") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
            }
            if ($keyboard['text'] == "accountwallet") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "account";
            }
            if ($keyboard['text'] == "text_Tariff_list") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
            }
            if ($keyboard['text'] == "text_wheel_luck") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
            }
            if ($keyboard['text'] == "text_affiliates") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
            }
            if ($keyboard['text'] == "text_extend") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
            }
            if ($keyboard['text'] == "text_support") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
            }
            if ($keyboard['text'] == "text_Purchased_services") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "backorder";
            }
            if ($keyboard['text'] == "text_help") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpbtns";
            }
            if ($keyboard['text'] == "text_usertest") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
            }
        }
    }
    if ($admin_idss != 0) {
        $temp_addtional_key[] = ['text' => $textbotlang['Admin']['textpaneladmin'], 'callback_data' => "admin"];
    }
    if ($users['agent'] != "f") {
        $temp_addtional_key[] = ['text' => $datatextbot['textpanelagent'], 'callback_data' => "agentpanel"];
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $temp_addtional_key[] = ['text' => $datatextbot['textrequestagent'], 'callback_data' => "requestagent"];
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboardcustom = $trace_keyboard;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    $keyboardcustom[] = $temp_addtional_key;
    $keyboard['inline_keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard))
        : json_encode($keyboard);
} else {
    if ($admin_idss != 0) {
        $temp_addtional_key[] = ['text' => $textbotlang['Admin']['textpaneladmin']];
    }
    if ($users['agent'] != "f") {
        $temp_addtional_key[] = ['text' => $datatextbot['textpanelagent']];
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $temp_addtional_key[] = ['text' => $datatextbot['textrequestagent']];
    }
    $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
    $keyboardcustom = $keyboardRows;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    $keyboardcustom[] = $temp_addtional_key;
    $keyboard['keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard))
        : json_encode($keyboard);
}

$_rx_acc_styles  = [];
$_rx_pay_styles  = [];
$_rx_payrcpt_styles = []; // pay_receipt: receipt/wallet/card copy inline buttons
$_rx_adm_styles  = [];
$_rx_set_styles  = [];
$_rx_shp_styles  = [];
$_rx_nav_styles  = [];
$_rx_rol_styles  = [];
$_rx_gw_styles   = [];
$_rx_svc_styles  = []; // service management inline buttons
$_rx_feat_styles  = []; // admin features submenu
$_rx_chan_styles  = []; // admin channel submenu
$_rx_helpa_styles = []; // admin help submenu
$_rx_cat_styles   = []; // admin category submenu
$_rx_prod_styles  = []; // admin products submenu
$_rx_pedit_styles = []; // admin product edit reply keyboard (change_product)
if (!empty($setting['keyboard_styles_all'])) {
    $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
    if (is_array($_rx_all_kbs)) {
        if (!empty($_rx_all_kbs['account']))        $_rx_acc_styles = $_rx_all_kbs['account'];
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







if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
    $_rx_adm_styles   = $_rx_adm_styles   + rx_getKeyboardDefaultStyles('admin_main');
    $_rx_set_styles   = $_rx_set_styles   + rx_getKeyboardDefaultStyles('admin_settings');
    $_rx_shp_styles   = $_rx_shp_styles   + rx_getKeyboardDefaultStyles('admin_shop');
    $_rx_rol_styles   = $_rx_rol_styles   + rx_getKeyboardDefaultStyles('admin_roles');
    $_rx_gw_styles    = $_rx_gw_styles    + rx_getKeyboardDefaultStyles('admin_gateways');
    $_rx_feat_styles  = $_rx_feat_styles  + rx_getKeyboardDefaultStyles('admin_features');
    $_rx_chan_styles  = $_rx_chan_styles  + rx_getKeyboardDefaultStyles('admin_channel');
    $_rx_helpa_styles = $_rx_helpa_styles + rx_getKeyboardDefaultStyles('admin_help');
    $_rx_cat_styles   = $_rx_cat_styles   + rx_getKeyboardDefaultStyles('admin_category');
    $_rx_prod_styles  = $_rx_prod_styles  + rx_getKeyboardDefaultStyles('admin_products');
}
if (!function_exists('rx_kb_style')) {
    function rx_kb_style(array $btn, string $key, array $styles): array {
        if (!empty($styles[$key]) && $styles[$key] !== 'default') {
            $btn['style'] = $styles[$key];
            return $btn;
        }


        if (!isset($btn['style']) && function_exists('rx_kb_guess_style_from_text') && isset($btn['text'])
            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
            if ($rxGuessed !== null) {
                $btn['style'] = $rxGuessed;
            } else {

                $btn['style'] = 'primary';
            }
        }
        return $btn;
    }
}
if (!function_exists('rx_kb_encode')) {
    /**
     * Encode an admin/sub-menu keyboard following the user's `inlinebtnmain` setting:
     *
     *   inlinebtnmain == 'oninline'  → INLINE keyboard (glass buttons, colours + premium emoji icons)
     *   inlinebtnmain != 'oninline'  → REPLY keyboard (`processKeyboardStyles` strips inline-only
     *                                  fields; `applyPremiumEmojiToKeyboard` skips reply so the
     *                                  text emoji stays visible and `style` passes through.)
     *
     * Hardcoded `json_encode(['inline_keyboard' => ...])` keyboards in other files
     * (e.g. mini-app instruction, confirmation dialogs) are NOT affected — they
     * always stay inline regardless of this setting.
     *
     * Pass `$forceInline=true` to force inline even when glass mode is off.
     */
    function rx_kb_encode(array $rows, $forceInline = null): string {
        global $setting;
        $useInline = ($forceInline !== null)
            ? (bool)$forceInline
            : (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
        if ($useInline) {
            return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        }


        return json_encode(['keyboard' => $rows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    }
}
$_rx_acc_d  = rx_kb_style(['text' => $datatextbot['text_Discount'],    'callback_data' => "Discount"],    'Discount',    $_rx_acc_styles);
$_rx_acc_b  = rx_kb_style(['text' => $datatextbot['text_Add_Balance'], 'callback_data' => "Add_Balance"], 'Add_Balance', $_rx_acc_styles);
$_rx_acc_rc = rx_kb_style(['text' => '🔁 بررسی مجدد هش کریپتو',         'callback_data' => "recheckcrypto"], 'recheckcrypto', $_rx_acc_styles);
$_rx_acc_k  = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],    'backuser',    $_rx_acc_styles);
$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [$_rx_acc_d, $_rx_acc_b],
        [$_rx_acc_rc],
        [$_rx_acc_k],
    ],
    'resize_keyboard' => true
]);
if($adminrulecheck['rule'] == "administrator"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'admin_status'], 'admin_status', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'], 'callback_data' => 'admin_managepanel'], 'admin_managepanel', $_rx_adm_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'], 'callback_data' => 'admin_addpanel'], 'admin_addpanel', $_rx_adm_styles)
        ],
        [
            rx_kb_style(['text' => "⏳ تنظیم سریع قیمت زمان", 'callback_data' => 'admin_timeprice'], 'admin_timeprice', $_rx_adm_styles),
            rx_kb_style(['text' => "🔋 تنظیم سریع قیمت حجم", 'callback_data' => 'admin_volprice'], 'admin_volprice', $_rx_adm_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser'], 'callback_data' => 'admin_users'], 'admin_users', $_rx_adm_styles),
            rx_kb_style(['text' => "🏬 تنظیمات فروشگاه", 'callback_data' => 'admin_shop'], 'admin_shop', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "💎 مالی", 'callback_data' => 'admin_finance'], 'admin_finance', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🤙 بخش پشتیبانی", 'callback_data' => 'admin_support'], 'admin_support', $_rx_adm_styles),
            rx_kb_style(['text' => "📚 بخش آموزش", 'callback_data' => 'admin_help'], 'admin_help', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "🛠 قابلیت های پنل", 'callback_data' => 'admin_features'], 'admin_features', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "⚙️ تنظیمات عمومی", 'callback_data' => 'admin_settings'], 'admin_settings', $_rx_adm_styles),
            rx_kb_style(['text' => "💵 رسید های تایید نشده", 'callback_data' => 'admin_invoices'], 'admin_invoices', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'admin_back'], 'admin_back', $_rx_adm_styles)]
    ]);
}
if($adminrulecheck['rule'] == "Seller"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'seller_status'], 'seller_status', $_rx_rol_styles)],
        [rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'seller_users'], 'seller_users', $_rx_rol_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'seller_back'], 'seller_back', $_rx_rol_styles)]
    ]);
}
if($adminrulecheck['rule'] == "support"){
$keyboardadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'support_users'], 'support_users', $_rx_rol_styles),
            rx_kb_style(['text' => "👁‍🗨 جستجو کاربر", 'callback_data' => 'support_search'], 'support_search', $_rx_rol_styles)
        ],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'support_back'], 'support_back', $_rx_rol_styles)]
    ]);
}
$CartManage = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه کارت به کارت", 'callback_data' => 'cart_title'], 'cart_title', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "💳 تنظیم شماره کارت", 'callback_data' => 'cart_setnum'], 'cart_setnum', $_rx_gw_styles),
            rx_kb_style(['text' => "❌ حذف شماره کارت", 'callback_data' => 'cart_delnum'], 'cart_delnum', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "👤 آیدی پشتیبانی", 'callback_data' => 'cart_support'], 'cart_support', $_rx_gw_styles),
            rx_kb_style(['text' => "💳 درگاه آفلاین در پیوی", 'callback_data' => 'cart_pvmode'], 'cart_pvmode', $_rx_gw_styles)
        ],
        [['text' => "💰  غیرفعالسازی  نمایش شماره کارت", 'callback_data' => 'cart_hide_num'],['text' => "💰 فعالسازی نمایش شماره کارت", 'callback_data' => 'cart_show_num']],
        [['text' => "♻️ نمایش گروهی شماره کارت", 'callback_data' => 'cart_group_num']],
        [['text' => "📄 خروجی افراد شماره کارت فعال", 'callback_data' => 'cart_export_num']],
        [
            rx_kb_style(['text' => "♻️ تایید خودکار رسید", 'callback_data' => 'cart_autoconfirm'], 'cart_autoconfirm', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک کارت به کارت", 'callback_data' => 'cart_cashback'], 'cart_cashback', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "🔒 نمایش کارت به کارت پس از اولین پرداخت", 'callback_data' => 'cart_firstpay'], 'cart_firstpay', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ کارت به کارت", 'callback_data' => 'cart_min'], 'cart_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ کارت به کارت", 'callback_data' => 'cart_max'], 'cart_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش کارت به کارت", 'callback_data' => 'cart_edu'], 'cart_edu', $_rx_gw_styles)],
        [['text' => "🤖 تایید رسید  بدون بررسی", 'callback_data' => 'cart_autocheck']],
        [['text' => "💳 استثناء کردن کاربر از تایید خودکار", 'callback_data' => 'cart_except_user']],
        [['text' => "⏳ زمان تایید خودکار بدون بررسی", 'callback_data' => 'cart_autotime']],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'], 'cart_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$trnado = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ترنادو", 'callback_data' => 'trnado_name'], 'trnado_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key ترنادو", 'callback_data' => 'trnado_apikey'], 'trnado_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💼 ثبت آدرس ولت ترون (TRC20)", 'callback_data' => 'trnado_wallet'], 'trnado_wallet', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🌐 ثبت آدرس API ترنادو", 'callback_data' => 'trnado_apiurl'], 'trnado_apiurl', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک ارزی ریالی دوم", 'callback_data' => 'trnado_cashback'], 'trnado_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ ارزی ریالی دوم", 'callback_data' => 'trnado_min'], 'trnado_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ ارزی ریالی دوم", 'callback_data' => 'trnado_max'], 'trnado_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش ارزی ریالی  دوم", 'callback_data' => 'trnado_edu'], 'trnado_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'], 'trnado_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$keyboardzarinpal = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 نام درگاه زرین پال", 'callback_data' => 'zpal_name'], 'zpal_name', $_rx_gw_styles),
            rx_kb_style(['text' => "مرچنت زرین پال", 'callback_data' => 'zpal_merchant'], 'zpal_merchant', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش بک زرین پال", 'callback_data' => 'zpal_cashback'], 'zpal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ زرین پال", 'callback_data' => 'zpal_min'], 'zpal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ زرین پال", 'callback_data' => 'zpal_max'], 'zpal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش زرین پال", 'callback_data' => 'zpal_edu'], 'zpal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpal_back'], 'zpal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$keyboardzarinpey = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 نام درگاه زرین پی", 'callback_data' => 'zpey_name'], 'zpey_name', $_rx_gw_styles),
            rx_kb_style(['text' => "🔑 توکن زرین پی", 'callback_data' => 'zpey_token'], 'zpey_token', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش بک زرین پی", 'callback_data' => 'zpey_cashback'], 'zpey_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🧑🏼‍💻 اموزش اتصال", 'callback_data' => 'zpey_tutorial'], 'zpey_tutorial', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ زرین پی", 'callback_data' => 'zpey_min'], 'zpey_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ زرین پی", 'callback_data' => 'zpey_max'], 'zpey_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش زرین پی", 'callback_data' => 'zpey_edu'], 'zpey_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpey_back'], 'zpey_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$aqayepardakht = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه آقای پرداخت", 'callback_data' => 'aqaye_name'], 'aqaye_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "تنظیم مرچنت آقای پرداخت", 'callback_data' => 'aqaye_merchant'], 'aqaye_merchant', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک آقای پرداخت", 'callback_data' => 'aqaye_cashback'], 'aqaye_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ آقای پرداخت", 'callback_data' => 'aqaye_min'], 'aqaye_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ آقای پرداخت", 'callback_data' => 'aqaye_max'], 'aqaye_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش درگاه اقای پرداخت", 'callback_data' => 'aqaye_edu'], 'aqaye_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'aqaye_back'], 'aqaye_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$NowPaymentsManage = rx_kb_encode([
        [rx_kb_style(['text' => "🗂 نام درگاه   plisio", 'callback_data' => 'plisio_name'], 'plisio_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "🧩 api plisio", 'callback_data' => 'plisio_api'], 'plisio_api', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک plisio", 'callback_data' => 'plisio_cashback'], 'plisio_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ حداقل مبلغ plisio", 'callback_data' => 'plisio_min'], 'plisio_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ حداکثر مبلغ plisio", 'callback_data' => 'plisio_max'], 'plisio_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 تنظیم آموزش plisio", 'callback_data' => 'plisio_edu'], 'plisio_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'plisio_back'], 'plisio_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
        ]
    ]);
$mainAdminId = isset($adminnumber) ? trim((string) $adminnumber) : '';
$currentUserId = isset($from_id) ? trim((string) $from_id) : '';

$settingPanelRows = [
    [rx_kb_style(['text' => "⚙️ وضعیت قابلیت ها", 'callback_data' => 'set_features'], 'set_features', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "📣 گزارشات ربات", 'callback_data' => 'set_reports'], 'set_reports', $_rx_set_styles),
        rx_kb_style(['text' => "📯 تنظیمات کانال", 'callback_data' => 'set_channel'], 'set_channel', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "✅ فعالسازی پنل تحت وب", 'callback_data' => 'set_webpanel'], 'set_webpanel', $_rx_set_styles)],
    [rx_kb_style(['text' => "🗑 بهینه سازی ربات", 'callback_data' => 'set_optimize'], 'set_optimize', $_rx_set_styles)],
];

$settingPanelRows = array_merge($settingPanelRows, [
    [
        rx_kb_style(['text' => "📝 تنظیم متن ربات", 'callback_data' => 'set_text'], 'set_text', $_rx_set_styles),
        rx_kb_style(['text' => "👨‍🔧 بخش ادمین", 'callback_data' => 'set_adminmgr'], 'set_adminmgr', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "➕ محدودیت ساخت اکانت تست برای همه", 'callback_data' => 'set_testlimit'], 'set_testlimit', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "💰 مبلغ عضویت نمایندگی", 'callback_data' => 'set_agentprice'], 'set_agentprice', $_rx_set_styles),
        rx_kb_style(['text' => "🖼 پس زمینه کیوآرکد", 'callback_data' => 'set_qrbg'], 'set_qrbg', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "🔗 وبهوک مجدد ربات های نماینده", 'callback_data' => 'set_webhook'], 'set_webhook', $_rx_set_styles)],
    [
        rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'set_backadmin'], 'set_backadmin', $_rx_set_styles),
        rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'set_backmenu'], 'set_backmenu', $_rx_set_styles)
    ],
]);

$setting_panel = rx_kb_encode($settingPanelRows);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingaqayepardakht = getPaySettingValue("statusaqayepardakht");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$Swapino = getPaySettingValue("statusSwapWallet");
$trnadoo = getPaySettingValue("statustarnado");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$zarinpey = getPaySettingValue("zarinpeystatus");
$affilnecurrency = getPaySettingValue("digistatus");
$arzireyali3 = getPaySettingValue("statusiranpay3");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
    ];
   if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
        if($PaySettingpv == "oncardpv"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'], 'url' => "https://t.me/$usernamecart"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }else{
            $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'], 'callback_data' => "cart_to_offline"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }
    }
    if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textnowpayment'], 'callback_data' => "plisio"], 'plisio', $_rx_pay_styles)
    ];
    }
    if($payment_status_nowpayment == "1"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textsnowpayment'], 'callback_data' => "nowpayment"], 'nowpayment', $_rx_pay_styles)
    ];
    }
   if($affilnecurrency == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textnowpaymenttron'], 'callback_data' => "digitaltron"], 'digitaltron', $_rx_pay_styles)
    ];
    }
   if($Swapino == "onSwapinoBot"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay2'], 'callback_data' => "iranpay1"], 'iranpay1', $_rx_pay_styles)
    ];
    }
   if($trnadoo == "onternado"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay3'], 'callback_data' => "iranpay2"], 'iranpay2', $_rx_pay_styles)
    ];
    }
     if($arzireyali3 == "oniranpay3"  && $paymentexits >= 2){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay1'], 'callback_data' => "iranpay3"], 'iranpay3', $_rx_pay_styles)
    ];
    }
   if($PaySettingaqayepardakht == "onaqayepardakht"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['aqayepardakht'], 'callback_data' => "aqayepardakht"], 'aqayepardakht', $_rx_pay_styles)
    ];
    }
    if($zarinpal == "onzarinpal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['zarinpal'], 'callback_data' => "zarinpal"], 'zarinpal', $_rx_pay_styles)
    ];
    }
    if($zarinpey == "onzarinpey"){
        $zarinpeyLabel = trim($datatextbot['zarinpey'] ?? '');
        if($zarinpeyLabel === ''){
            $zarinpeyLabel = '🟠 زرین پی';
        }
        if($zarinpeyLabel !== ''){
            $step_payment['inline_keyboard'][] = [
                rx_kb_style(['text' => $zarinpeyLabel, 'callback_data' => "zarinpey"], 'zarinpey', $_rx_pay_styles)
        ];
        }
    }
    if($paymentstatussnotverify == "onverifypay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textpaymentnotverify'], 'callback_data' => "paymentnotverify"], 'paymentnotverify', $_rx_pay_styles)
    ];
    }
    if(intval($paymentsstartelegram) == 1){
     $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['text_star_telegram'], 'callback_data' => "startelegrams"], 'startelegrams', $_rx_pay_styles)
    ];
    }