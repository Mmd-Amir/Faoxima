<?php

require_once 'config.php';

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
    'tonpay' => '',
    'cubepay' => '',
    'blupal' => '',
    'abangateway' => '',
    'atlaspay' => '',
    'tetrapay' => '',
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
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_buy";
            }
            if ($keyboard['text'] == "accountwallet") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "account";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_account";
            }
            if ($keyboard['text'] == "text_Tariff_list") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_tariff";
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
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_myservices";
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
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin, 'callback_data' => "admin"];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent, 'callback_data' => "agentpanel"];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req, 'callback_data' => "requestagent"];
        }
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboardcustom = $trace_keyboard;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['inline_keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard), false)
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
} else {
    if ($admin_idss != 0) {
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req];
        }
    }
    $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
    $keyboardcustom = $keyboardRows;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard), false)
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
}
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
if (!function_exists('rx_kb_style')) {
    function rx_kb_style(array $btn, string $key, ?array $styles = []): array {
        $styles = is_array($styles) ? $styles : [];
        if (!empty($styles['__rxplain__' ])) { $btn['_rxap'] = 1; return $btn; }
        if (!empty($styles['__rxkeep__'])) { $btn['_rxkeep'] = 1; }
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
    function rx_kb_encode(array $rows, $forceInline = null): string {
        global $setting, $textbotlang;
        $useInline = ($forceInline !== null)
            ? (bool)$forceInline
            : (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
        if ($useInline) {
            $sanitized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $newRow = [];
                foreach ($row as $btn) {
                    if (is_string($btn)) {
                        $btn = ['text' => $btn];
                    }
                    if (!is_array($btn) || !isset($btn['text'])) {
                        continue;
                    }
                    $bText = (string)$btn['text'];
                    $hasAction = isset($btn['callback_data']) || isset($btn['url']) || isset($btn['web_app'])
                        || isset($btn['login_url']) || isset($btn['switch_inline_query'])
                        || isset($btn['switch_inline_query_current_chat']) || isset($btn['callback_game'])
                        || isset($btn['pay']);
                    if (!$hasAction) {
                        $cb = $bText;
                        if (isset($textbotlang['Admin']['backadmin']) && $bText === $textbotlang['Admin']['backadmin']) {
                            $cb = 'adm_hub_main';
                        } elseif (isset($textbotlang['Admin']['backmenu']) && $bText === $textbotlang['Admin']['backmenu']) {
                            $cb = 'adm_backmenu';
                        } elseif (isset($textbotlang['users']['backbtn']) && $bText === $textbotlang['users']['backbtn']) {
                            $cb = 'backuser';
                        }
                        $btn['callback_data'] = function_exists('rx_safeCallbackData')
                            ? rx_safeCallbackData($cb, $bText)
                            : (strlen($cb) <= 64 ? $cb : substr($cb, 0, 64));
                    }
                    $newRow[] = $btn;
                }
                if (!empty($newRow)) {
                    $sanitized[] = $newRow;
                }
            }
            return json_encode(['inline_keyboard' => $sanitized], JSON_UNESCAPED_UNICODE);
        }

        $replyRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $newRow = [];
            foreach ($row as $btn) {
                if (is_string($btn)) {
                    $newRow[] = ['text' => $btn];
                } elseif (is_array($btn) && isset($btn['text'])) {
                    $newRow[] = ['text' => (string)$btn['text']];
                }
            }
            if (!empty($newRow)) {
                $replyRows[] = $newRow;
            }
        }
        return json_encode(['keyboard' => $replyRows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    }
}
$_rx_acc_d  = rx_kb_style(['text' => $datatextbot['text_Discount'],    'callback_data' => "Discount"],    'Discount',    $_rx_acc_styles);
$_rx_acc_b  = rx_kb_style(['text' => $datatextbot['text_Add_Balance'], 'callback_data' => "Add_Balance"], 'Add_Balance', $_rx_acc_styles);
$_rx_acc_tr = rx_kb_style(['text' => '🔄 انتقال موجودی',                'callback_data' => "TransferBalance"], 'TransferBalance', $_rx_acc_styles);
$_rx_acc_mt = rx_kb_style(['text' => '📑 تراکنش‌های من',                'callback_data' => "MyTransactions"], 'MyTransactions', $_rx_acc_styles);
$_rx_acc_rc = rx_kb_style(['text' => '🔁 بررسی مجدد هش کریپتو',         'callback_data' => "recheckcrypto"], 'recheckcrypto', $_rx_acc_styles);
$_rx_acc_t  = rx_kb_style(['text' => '🎫 تیکت‌های پشتیبانی',           'callback_data' => "supporttickets"], 'supporttickets', $_rx_acc_styles);
$_rx_acc_k  = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],    'backuser',    $_rx_acc_styles);
$_rx_acc_hasCryptoWallet = function_exists('crypto_active_wallets') && !empty(crypto_active_wallets());
$_rx_acc_rows = [
    [$_rx_acc_d, $_rx_acc_b],
    [$_rx_acc_tr, $_rx_acc_mt],
    [$_rx_acc_t],
];
if ($_rx_acc_hasCryptoWallet) {
    $_rx_acc_rows[] = [$_rx_acc_rc];
}
$_rx_acc_rows[] = [$_rx_acc_k];
$keyboardPanel = json_encode([
    'inline_keyboard' => $_rx_acc_rows,
    'resize_keyboard' => true
]);
if($adminrulecheck['rule'] == "administrator"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'admin_status'], 'admin_status', $_rx_adm_styles)],
        [rx_kb_style(['text' => "📁 مدیریت پنل‌ها و سرورها", 'callback_data' => 'admin_panels'], 'admin_panels', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🏬 تنظیمات فروشگاه", 'callback_data' => 'admin_shop'], 'admin_shop', $_rx_adm_styles),
            rx_kb_style(['text' => "💎 مالی و گزارشات", 'callback_data' => 'admin_finance'], 'admin_finance', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "👥 مدیریت کاربران", 'callback_data' => 'admin_usershub'], 'admin_usershub', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🤙 بخش پشتیبانی", 'callback_data' => 'admin_support'], 'admin_support', $_rx_adm_styles),
            rx_kb_style(['text' => "📚 بخش آموزش", 'callback_data' => 'admin_help'], 'admin_help', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "📢 کانال و اطلاع‌رسانی", 'callback_data' => 'admin_channelhub'], 'admin_channelhub', $_rx_adm_styles)],
        [rx_kb_style(['text' => "⚙️ تنظیمات فنی و ربات", 'callback_data' => 'admin_settings'], 'admin_settings', $_rx_adm_styles)],
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
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کارت به کارت", 'callback_data' => 'cart_title'], 'cart_title', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💳 مدیریت شماره کارت", 'callback_data' => 'cart_manage_inline'], 'cart_manage_inline', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "👤 آیدی پشتیبانی", 'callback_data' => 'cart_support'], 'cart_support', $_rx_gw_styles),
            rx_kb_style(['text' => "💳 آفلاین در پیوی", 'callback_data' => 'cart_pvmode'], 'cart_pvmode', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش‌بک کارت", 'callback_data' => 'cart_cashback'], 'cart_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔒 کارت پس از اولین پرداخت", 'callback_data' => 'cart_firstpay'], 'cart_firstpay', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف کارت به کارت", 'callback_data' => 'cart_min'], 'cart_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف کارت به کارت", 'callback_data' => 'cart_max'], 'cart_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش کارت به کارت", 'callback_data' => 'cart_edu'], 'cart_edu', $_rx_gw_styles)],
        [['text' => "🔑 حداقل مبلغ احراز کارت", 'callback_data' => 'cart_cvmin']],
        [['text' => "🤖 تایید رسید بدون بررسی", 'callback_data' => 'cart_autocheck']],
        [['text' => "⚙️ تنظیمات تایید خودکار", 'callback_data' => 'cart_except_user']],
        [['text' => "⏳ زمان تایید خودکار", 'callback_data' => 'cart_autotime']],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'], 'cart_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cart_backmenu']
        ]
    ]);
$trnado = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ترونادو", 'callback_data' => 'trnado_name'], 'trnado_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key ترونادو", 'callback_data' => 'trnado_apikey'], 'trnado_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔏 ثبت کلید امضای IPN ترونادو", 'callback_data' => 'trnado_signingkey'], 'trnado_signingkey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💼 آدرس کیف پول ترونادو", 'callback_data' => 'trnado_wallet'], 'trnado_wallet', $_rx_gw_styles)],
        [rx_kb_style(['text' => "⚖️ درصد کارمزد کسب‌وکار", 'callback_data' => 'trnado_wage'], 'trnado_wage', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک ترونادو", 'callback_data' => 'trnado_cashback'], 'trnado_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف ترونادو", 'callback_data' => 'trnado_min'], 'trnado_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف ترونادو", 'callback_data' => 'trnado_max'], 'trnado_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش ترونادو", 'callback_data' => 'trnado_edu'], 'trnado_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'], 'trnado_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'trnado_backmenu']
        ]
    ]);
$tonpay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه تون‌پی", 'callback_data' => 'tonpay_name'], 'tonpay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key تون‌پی", 'callback_data' => 'tonpay_apikey'], 'tonpay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک تون‌پی", 'callback_data' => 'tonpay_cashback'], 'tonpay_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف تون‌پی", 'callback_data' => 'tonpay_min'], 'tonpay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف تون‌پی", 'callback_data' => 'tonpay_max'], 'tonpay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش تون‌پی", 'callback_data' => 'tonpay_edu'], 'tonpay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'tonpay_back'], 'tonpay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'tonpay_backmenu']
        ]
    ]);
$cubepay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کیوب‌پی", 'callback_data' => 'cubepay_name'], 'cubepay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت توکن API کیوب‌پی", 'callback_data' => 'cubepay_apikey'], 'cubepay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک کیوب‌پی", 'callback_data' => 'cubepay_cashback'], 'cubepay_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "⚖️ کارمزد کیوب‌پی", 'callback_data' => 'cubepay_fee'], 'cubepay_fee', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف کیوب‌پی", 'callback_data' => 'cubepay_min'], 'cubepay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف کیوب‌پی", 'callback_data' => 'cubepay_max'], 'cubepay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش کیوب‌پی", 'callback_data' => 'cubepay_edu'], 'cubepay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cubepay_back'], 'cubepay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cubepay_backmenu']
        ]
    ]);
$blupal = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه بلوپال", 'callback_data' => 'blupal_name'], 'blupal_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key بلوپال", 'callback_data' => 'blupal_apikey'], 'blupal_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک بلوپال", 'callback_data' => 'blupal_cashback'], 'blupal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف بلوپال", 'callback_data' => 'blupal_min'], 'blupal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف بلوپال", 'callback_data' => 'blupal_max'], 'blupal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش بلوپال", 'callback_data' => 'blupal_edu'], 'blupal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'blupal_back'], 'blupal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'blupal_backmenu']
        ]
    ]);
$variza = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه واریزا", 'callback_data' => 'variza_name'], 'variza_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت توکن API واریزا", 'callback_data' => 'variza_apikey'], 'variza_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔐 ثبت کلید وب‌هوک واریزا", 'callback_data' => 'variza_webhooksecret'], 'variza_webhooksecret', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک واریزا", 'callback_data' => 'variza_cashback'], 'variza_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف واریزا", 'callback_data' => 'variza_min'], 'variza_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف واریزا", 'callback_data' => 'variza_max'], 'variza_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش واریزا", 'callback_data' => 'variza_edu'], 'variza_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'variza_back'], 'variza_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'variza_backmenu']
        ]
    ]);
$abangateway = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه آبان گیت وی", 'callback_data' => 'abangateway_name'], 'abangateway_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔗 ثبت آدرس درگاه آبان گیت وی", 'callback_data' => 'abangateway_url'], 'abangateway_url', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت کلید اتصال آبان گیت وی", 'callback_data' => 'abangateway_apikey'], 'abangateway_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک آبان گیت وی", 'callback_data' => 'abangateway_cashback'], 'abangateway_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف آبان گیت وی", 'callback_data' => 'abangateway_min'], 'abangateway_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف آبان گیت وی", 'callback_data' => 'abangateway_max'], 'abangateway_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش آبان گیت وی", 'callback_data' => 'abangateway_edu'], 'abangateway_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'abangateway_back'], 'abangateway_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'abangateway_backmenu']
        ]
    ]);
$atlaspay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه اطلس‌پی", 'callback_data' => 'atlaspay_name'], 'atlaspay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key اطلس‌پی", 'callback_data' => 'atlaspay_apikey'], 'atlaspay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "📊 موجودی و اطلاعات حساب", 'callback_data' => 'atlaspay_account'], 'atlaspay_account', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک اطلس‌پی", 'callback_data' => 'atlaspay_cashback'], 'atlaspay_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف اطلس‌پی", 'callback_data' => 'atlaspay_min'], 'atlaspay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف اطلس‌پی", 'callback_data' => 'atlaspay_max'], 'atlaspay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش اطلس‌پی", 'callback_data' => 'atlaspay_edu'], 'atlaspay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'atlaspay_back'], 'atlaspay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'atlaspay_backmenu']
        ]
    ]);
$tetrapay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه تتراپی", 'callback_data' => 'tetrapay_name'], 'tetrapay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key تتراپی", 'callback_data' => 'tetrapay_apikey'], 'tetrapay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🌍 ثبت آدرس سرور API تتراپی", 'callback_data' => 'tetrapay_apiurl'], 'tetrapay_apiurl', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک تتراپی", 'callback_data' => 'tetrapay_cashback'], 'tetrapay_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف تتراپی", 'callback_data' => 'tetrapay_min'], 'tetrapay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف تتراپی", 'callback_data' => 'tetrapay_max'], 'tetrapay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش تتراپی", 'callback_data' => 'tetrapay_edu'], 'tetrapay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'tetrapay_back'], 'tetrapay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'tetrapay_backmenu']
        ]
    ]);
$keyboardzarinpal = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه زرین پال", 'callback_data' => 'zpal_name'], 'zpal_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "مرچنت زرین پال", 'callback_data' => 'zpal_merchant'], 'zpal_merchant', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک زرین پال", 'callback_data' => 'zpal_cashback'], 'zpal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف زرین پال", 'callback_data' => 'zpal_min'], 'zpal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف زرین پال", 'callback_data' => 'zpal_max'], 'zpal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش زرین پال", 'callback_data' => 'zpal_edu'], 'zpal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpal_back'], 'zpal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'zpal_backmenu']
        ]
    ]);
$NowPaymentsManage = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه plisio", 'callback_data' => 'plisio_name'], 'plisio_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "🧩 api plisio", 'callback_data' => 'plisio_api'], 'plisio_api', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک plisio", 'callback_data' => 'plisio_cashback'], 'plisio_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ کف plisio", 'callback_data' => 'plisio_min'], 'plisio_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف plisio", 'callback_data' => 'plisio_max'], 'plisio_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش plisio", 'callback_data' => 'plisio_edu'], 'plisio_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'plisio_back'], 'plisio_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'plisio_backmenu']
        ]
    ]);
$mainAdminId = isset($adminnumber) ? trim((string) $adminnumber) : '';
$currentUserId = isset($from_id) ? trim((string) $from_id) : '';

$settingPanelRows = [
    [rx_kb_style(['text' => "⚙️ وضعیت قابلیت ها", 'callback_data' => 'set_features'], 'set_features', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "🗑 بهینه سازی ربات", 'callback_data' => 'set_optimize'], 'set_optimize', $_rx_set_styles),
        rx_kb_style(['text' => "✅ پنل تحت وب", 'callback_data' => 'set_webpanel'], 'set_webpanel', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "📝 تنظیم متن ربات", 'callback_data' => 'set_text'], 'set_text', $_rx_set_styles)],
    [rx_kb_style(['text' => "📷 تنظیمات کیو آر کد", 'callback_data' => 'set_qrsettings'], 'set_qrsettings', $_rx_set_styles)],
    [rx_kb_style(['text' => "🔗 وبهوک ربات‌های نماینده", 'callback_data' => 'set_webhook'], 'set_webhook', $_rx_set_styles)],
    [
        rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'set_backadmin'], 'set_backadmin', $_rx_set_styles)
    ],
];

$setting_panel = rx_kb_encode($settingPanelRows);

$adminPanelsMenu = rx_kb_encode([
    [
        rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'], 'callback_data' => 'admin_managepanel'], 'admin_managepanel', $_rx_adm_styles),
        rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'], 'callback_data' => 'admin_addpanel'], 'admin_addpanel', $_rx_adm_styles)
    ],
    [rx_kb_style(['text' => "🛠 قابلیت های پنل", 'callback_data' => 'admin_features'], 'admin_features', $_rx_adm_styles)],
    [
        rx_kb_style(['text' => "⏳ قیمت سریع زمان", 'callback_data' => 'admin_timeprice'], 'admin_timeprice', $_rx_adm_styles),
        rx_kb_style(['text' => "🔋 قیمت سریع حجم", 'callback_data' => 'admin_volprice'], 'admin_volprice', $_rx_adm_styles)
    ],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'adm_hub_main'], 'panelshub_backmenu', $_rx_adm_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'panelshub_backadmin', $_rx_adm_styles)
    ],
]);

$adminChannelMenu = rx_kb_encode([
    [rx_kb_style(['text' => "📯 تنظیمات کانال", 'callback_data' => 'set_channel'], 'set_channel', $_rx_set_styles)],
    [rx_kb_style(['text' => "📣 گزارشات ربات", 'callback_data' => 'set_reports'], 'set_reports', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'adm_hub_main'], 'channelhub_backmenu', $_rx_set_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'channelhub_backadmin', $_rx_set_styles)
    ],
]);

$adminUsersMenu = rx_kb_encode([
    [rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser'], 'callback_data' => 'admin_users'], 'admin_users', $_rx_adm_styles)],
    [rx_kb_style(['text' => "👨‍🔧 بخش ادمین", 'callback_data' => 'set_adminmgr'], 'set_adminmgr', $_rx_set_styles)],
    [rx_kb_style(['text' => "➕ محدودیت تست برای همه", 'callback_data' => 'set_testlimit'], 'set_testlimit', $_rx_set_styles)],
    [rx_kb_style(['text' => "💵 رسید های تایید نشده", 'callback_data' => 'admin_invoices'], 'admin_invoices', $_rx_adm_styles)],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'adm_hub_main'], 'usershub_backmenu', $_rx_adm_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'usershub_backadmin', $_rx_adm_styles)
    ],
]);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$trnadoo = getPaySettingValue("statustarnado");
$tonpayStatus = getPaySettingValue("statustonpay");
$cubepayStatus = getPaySettingValue("statuscubepay");
$blupalStatus = getPaySettingValue("statusblupal");
$varizaStatus = getPaySettingValue("statusvariza");
$abangatewayStatus = getPaySettingValue("statusabangateway");
$abangatewayUrl = trim((string) getPaySettingValue("urlabangateway"));
$abangatewayKey = trim((string) getPaySettingValue("apiabangateway"));
$atlaspayStatus = getPaySettingValue("statusatlaspay");
$tetrapayStatus = getPaySettingValue("statustetrapay");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$affilnecurrency = getPaySettingValue("digistatus");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
    ];
   if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
        if($PaySettingpv == "oncardpv"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'url' => "https://t.me/$usernamecart"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }else{
            $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'callback_data' => "cart_to_offline"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }
    }
    if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textnowpayment'] ?: '💵 پرداخت ارزی (Plisio)', 'callback_data' => "plisio"], 'plisio', $_rx_pay_styles)
    ];
    }
    if($payment_status_nowpayment == "1"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textsnowpayment'] ?: 'NowPayments', 'callback_data' => "nowpayment"], 'nowpayment', $_rx_pay_styles)
    ];
    }
   if($affilnecurrency == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textnowpaymenttron'] ?: '💵 واریز رمزارز ترون', 'callback_data' => "digitaltron"], 'digitaltron', $_rx_pay_styles)
    ];
    }
   if($trnadoo == "onternado"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay3'] ?: 'ترونادو', 'callback_data' => "iranpay2"], 'iranpay2', $_rx_pay_styles)
    ];
    }
   if($tonpayStatus == "ontonpay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['tonpay'] ?: '💠 تون‌پی', 'callback_data' => "tonpay"], 'tonpay', $_rx_pay_styles)
    ];
    }
   if($cubepayStatus == "oncubepay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['cubepay'] ?: '🟦 کیوب‌پی', 'callback_data' => "cubepay"], 'cubepay', $_rx_pay_styles)
    ];
    }
   if($blupalStatus == "onblupal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['blupal'] ?: '💙 بلوپال', 'callback_data' => "blupal"], 'blupal', $_rx_pay_styles)
    ];
    }
   if($varizaStatus == "onvariza"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['variza'] ?: '💳 واریزا', 'callback_data' => "variza"], 'variza', $_rx_pay_styles)
    ];
    }
   // Offered only when it can take money: on, with an https address and a key.
   if($abangatewayStatus == "onabangateway" && stripos($abangatewayUrl, 'https://') === 0 && $abangatewayKey !== '' && $abangatewayKey !== '0'){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['abangateway'] ?: '💳 آبان گیت وی', 'callback_data' => "abangateway"], 'abangateway', $_rx_pay_styles)
    ];
    }
   if($atlaspayStatus == "onatlaspay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['atlaspay'] ?: '🌐 اطلس‌پی', 'callback_data' => "atlaspay"], 'atlaspay', $_rx_pay_styles)
    ];
    }
   if($tetrapayStatus == "ontetrapay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['tetrapay'] ?: '🔷 تتراپی', 'callback_data' => "tetrapay"], 'tetrapay', $_rx_pay_styles)
    ];
    }
    if($zarinpal == "onzarinpal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['zarinpal'] ?: '🟡 زرین پال', 'callback_data' => "zarinpal"], 'zarinpal', $_rx_pay_styles)
    ];
    }
    if($paymentstatussnotverify == "onverifypay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textpaymentnotverify'] ?: 'درگاه ریالی', 'callback_data' => "paymentnotverify"], 'paymentnotverify', $_rx_pay_styles)
    ];
    }
    if(intval($paymentsstartelegram) == 1){
     $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['text_star_telegram'] ?: '💫 Star Telegram', 'callback_data' => "startelegrams"], 'startelegrams', $_rx_pay_styles)
    ];
    }