<?php
require_once __DIR__ . '/config.php';
function telegram($method, $datas = [], $token = null)
{
    global $APIKEY, $telegramCurlTimeout;

    $token = $token === null ? $APIKEY : $token;
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;

    if (!is_array($datas)) {
        $datas = [];
    }

    if (isset($datas['message_thread_id']) && intval($datas['message_thread_id']) <= 0) {
        unset($datas['message_thread_id']);
    }









    $rxAlreadyTransformed = !empty($datas['_rx_already_transformed']);
    unset($datas['_rx_already_transformed']);

    $rxAutoTransformMethods = ['sendmessage', 'sendMessage', 'editmessagetext', 'editMessageText', 'sendphoto', 'sendPhoto', 'sendvideo', 'sendVideo', 'sendDocument', 'senddocument', 'editmessagecaption', 'editMessageCaption'];
    if (!$rxAlreadyTransformed && in_array(strtolower($method), array_map('strtolower', $rxAutoTransformMethods), true)) {

        $rxTextField = null;
        if (isset($datas['text']))         { $rxTextField = 'text'; }
        elseif (isset($datas['caption']))  { $rxTextField = 'caption'; }
        if ($rxTextField !== null && is_string($datas[$rxTextField]) && function_exists('applyPremiumEmojiTransform')) {
            $rxParseMode = $datas['parse_mode'] ?? null;
            $datas[$rxTextField] = applyPremiumEmojiTransform($datas[$rxTextField], $rxParseMode);
        }

        if (isset($datas['reply_markup']) && $datas['reply_markup'] !== '' && $datas['reply_markup'] !== null) {
            if (function_exists('processKeyboardStyles')) {
                $datas['reply_markup'] = processKeyboardStyles($datas['reply_markup']);
            }
            if (function_exists('applyPremiumEmojiToKeyboard')) {
                $datas['reply_markup'] = applyPremiumEmojiToKeyboard($datas['reply_markup']);
            }
        }
    }

    $preparedPayload = prepareTelegramRequestPayload($datas);

    $ch = curl_init($url);
    if ($ch === false) {
        error_log('Unable to initialise cURL for Telegram request.');
        return [
            'ok' => false,
            'description' => 'Unable to initialise cURL for Telegram request.'
        ];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $timeout = isset($telegramCurlTimeout) && is_numeric($telegramCurlTimeout) ? (int)$telegramCurlTimeout : 10;
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
    curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);
    if (!empty($preparedPayload['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $preparedPayload['headers']);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $preparedPayload['body']);

    $requestStartedAt = microtime(true);
    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        $curlErrorNumber = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $logError = $curlError !== '' ? $curlError : 'Unknown cURL error';
        error_log(sprintf('Telegram request failed (errno: %d, url: %s): %s', $curlErrorNumber, $url, $logError));

        return [
            'ok' => false,
            'description' => ($curlError !== '' ? $curlError : 'Telegram request failed.') . ' اتصال به تلگرام در مهلت مقرر برقرار نشد؛ فایروال یا پراکسی خروجی را بررسی کنید.'
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = microtime(true) - $requestStartedAt;
    curl_close($ch);

    if ($duration >= 5.0) {
        error_log(sprintf('Slow Telegram response detected (method: %s, http_code: %d, duration: %.3fs)', $method, $httpCode, $duration));
    }

    $decodedResponse = json_decode($rawResponse, true);
    if (!is_array($decodedResponse)) {
        $logSnippet = substr($rawResponse, 0, 200);
        error_log(sprintf('Invalid response from Telegram API (HTTP %d): %s', $httpCode, $logSnippet));

        return [
            'ok' => false,
            'error_code' => $httpCode,
            'description' => 'Invalid response received from Telegram.'
        ];
    }

    if (isset($decodedResponse['ok']) && !$decodedResponse['ok']) {


        $rxBenignDescriptions = [
            'message to delete not found',
            'message to edit not found',
            'message is not modified',
            "message can't be deleted",
            'message can\'t be deleted for everyone',
            'query is too old',
            'MESSAGE_ID_INVALID',
            'replied message not found',
        ];
        $rxDesc = (string) ($decodedResponse['description'] ?? '');
        $rxIsBenign = false;
        foreach ($rxBenignDescriptions as $rxNeedle) {
            if (stripos($rxDesc, $rxNeedle) !== false) {
                $rxIsBenign = true;
                break;
            }
        }
        if (!$rxIsBenign) {
            error_log(json_encode($decodedResponse));
        }
    }

    return $decodedResponse;
}
function prepareTelegramRequestPayload(array $datas)
{
    $normalised = [];
    foreach ($datas as $key => $value) {
        if ($value === null) {
            continue;
        }

        $normalised[$key] = normaliseTelegramValue($value);
    }

    $containsFile = false;
    foreach ($normalised as $value) {
        if ($value instanceof CURLFile) {
            $containsFile = true;
            break;
        }
    }

    if ($containsFile) {
        return [
            'body' => $normalised,
            'headers' => ['Expect:']
        ];
    }

    $stringBody = http_build_query($normalised, '', '&', PHP_QUERY_RFC3986);

    return [
        'body' => $stringBody,
        'headers' => ['Expect:', 'Content-Type: application/x-www-form-urlencoded']
    ];
}

function normaliseTelegramValue($value)
{
    if ($value instanceof CURLFile) {
        return $value;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = json_encode($value);
        }

        return $encoded === false ? '' : $encoded;
    }

    return $value;
}


function getPremiumEmojiMap($forceReload = false) {
    static $cache = null;
    if ($forceReload) { $cache = null; }
    if ($cache !== null) { return $cache; }
    $cache = [];
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) { return $cache; }
    try {
        $stmt = $pdo->query("SELECT premium_emoji_status FROM setting LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $isOn = (is_array($row) && (string)($row['premium_emoji_status'] ?? '0') === '1');
        if (!$isOn) { return $cache; }
    } catch (\Throwable $e) { return $cache; }
    try {
        $stmt = $pdo->query("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE custom_emoji_id IS NOT NULL AND custom_emoji_id <> ''");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $emoji = (string)($row['emoji'] ?? '');
                $cid   = (string)($row['custom_emoji_id'] ?? '');
                if ($emoji !== '' && $cid !== '') { $cache[$emoji] = $cid; }
            }
        }
    } catch (\Throwable $e) {  }


    uksort($cache, function($a, $b) { return strlen($b) - strlen($a); });
    return $cache;
}
function applyPremiumEmojiTransform($text, $parseMode) {
    if (!is_string($text) || $text === '') { return $text; }
    $pm = strtolower((string)$parseMode);

    if ($pm !== 'html') { return $text; }
    $map = getPremiumEmojiMap();
    if (empty($map)) { return $text; }

    if (stripos($text, '<tg-emoji') !== false) { return $text; }
    foreach ($map as $emoji => $cid) {
        if ($emoji === '' || $cid === '') { continue; }
        if (strpos($text, $emoji) === false) { continue; }
        $cidEscaped = htmlspecialchars($cid, ENT_QUOTES);
        $tagged = '<tg-emoji emoji-id="' . $cidEscaped . '">' . $emoji . '</tg-emoji>';
        $text = str_replace($emoji, $tagged, $text);
    }
    return $text;
}


if (!defined('REPLY_STYLE_EMOJI_MAP')) {
    define('REPLY_STYLE_EMOJI_MAP', serialize([
        'success' => '🟢',
        'danger'  => '🔴',
        'primary' => '🔵',
    ]));
}


function stripReplyStyleEmoji(string $text): string {
    if ($text === '') return $text;
    foreach (unserialize(REPLY_STYLE_EMOJI_MAP) as $dot) {
        $prefix = $dot . ' ';
        $len    = mb_strlen($prefix, 'UTF-8');
        if (mb_substr($text, 0, $len, 'UTF-8') === $prefix) {
            return mb_substr($text, $len, null, 'UTF-8');
        }
    }
    return $text;
}




if (!function_exists('rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!function_exists('rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {
        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;
        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        $file = rx_adminPanelCallbackMapFile();
        $map = [];
        if (is_file($file)) {
            $dec = json_decode((string) @file_get_contents($file), true);
            if (is_array($dec)) $map = $dec;
        }
        $map[$code] = $text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $file = rx_adminPanelCallbackMapFile();
            if (!is_file($file)) return null;
            $map = json_decode((string) @file_get_contents($file), true);
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}
if (!function_exists('rx_finalizeInlineAdminKb')) {
    function rx_finalizeInlineAdminKb(string $json): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;
        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;




        $rxGlassMode = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';
        if (!$rxGlassMode && isset($kb['keyboard'])) {
            return $json;
        }

        $hasContactRequest = false;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($row as $btn) {
                if (is_array($btn) && (isset($btn['request_contact']) || isset($btn['request_location']) || isset($btn['request_poll']) || isset($btn['request_users']) || isset($btn['request_chat']))) {
                    $hasContactRequest = true;
                    break 2;
                }
            }
        }
        if ($hasContactRequest) {

            return $json;
        }

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;
                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = rx_makeAdminPanelCallback((string)$btn['text']);
                }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}


function processKeyboardStyles($keyboard) {
    $styleMap = unserialize(REPLY_STYLE_EMOJI_MAP);
    $wasString = is_string($keyboard);
    if ($wasString) {
        if ($keyboard === '') return $keyboard;
        $kb = json_decode($keyboard, true);
        if (!is_array($kb)) return $keyboard;
    } elseif (is_array($keyboard)) {
        $kb = $keyboard;
    } else {
        return $keyboard;
    }


    
    
    
    
    
    static $_rx_kb_list_styles = null;
    if ($_rx_kb_list_styles === null) {
        $_rx_kb_list_styles = ['lists' => [], 'misc' => [], 'features' => []];
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (is_array($row) && !empty($row['keyboard_styles_all'])) {
                    $all = json_decode($row['keyboard_styles_all'], true);
                    if (is_array($all)) {
                        if (!empty($all['admin_lists']))  $_rx_kb_list_styles['lists'] = $all['admin_lists'];
                        if (!empty($all['user_dynamic_lists']) && is_array($all['user_dynamic_lists'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $all['user_dynamic_lists'];
                        }
                        if (!empty($all['cron_notifications']) && is_array($all['cron_notifications'])) {

                            $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $all['cron_notifications'];
                        }
                        if (!empty($all['misc_buttons'])) $_rx_kb_list_styles['misc']  = $all['misc_buttons'];
                        
                        $featMenus = [
                            'features_nav','features_bot','features_users','features_shop',
                            'features_lottery','features_crons','features_antispam',
                            'admin_pagination','admin_stats','admin_search','admin_user_lists',
                            'admin_filters','admin_node','admin_gateway_extra',
                            'admin_cart_advanced','admin_crypto','admin_iplogin','admin_infocard',
                            'admin_discount_settings','admin_misc_actions','admin_premium_stock',
                            'user_subscription','recheckcrypto_buttons','recheckcrypto_admin_buttons',


                            'admin_main','admin_settings','admin_shop','admin_roles','admin_gateways',
                            'admin_features','admin_channel','admin_help','admin_category','admin_products',


                            'service','account','payment','user_nav','pay_receipt','invoice_copy_buttons',
                        ];
                        foreach ($featMenus as $fm) {
                            if (!empty($all[$fm]) && is_array($all[$fm])) {
                                $_rx_kb_list_styles['features'] = array_merge(
                                    $_rx_kb_list_styles['features'], $all[$fm]
                                );
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}




        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rx_kb_all_defaults = rx_getKeyboardDefaultStyles();
            if (is_array($rx_kb_all_defaults)) {
                foreach ($rx_kb_all_defaults as $rx_kb_sec => $rx_kb_def_pairs) {
                    if (!is_array($rx_kb_def_pairs)) { continue; }


                    if (strpos($rx_kb_sec, 'admin_panel_') === 0) { continue; }

                    if ($rx_kb_sec === 'misc_buttons') {

                        $_rx_kb_list_styles['misc'] = $_rx_kb_list_styles['misc'] + $rx_kb_def_pairs;
                    } elseif ($rx_kb_sec === 'admin_lists' || $rx_kb_sec === 'user_dynamic_lists' || $rx_kb_sec === 'cron_notifications') {

                        $_rx_kb_list_styles['lists'] = $_rx_kb_list_styles['lists'] + $rx_kb_def_pairs;
                    } else {

                        $_rx_kb_list_styles['features'] = $_rx_kb_list_styles['features'] + $rx_kb_def_pairs;
                    }
                }
            }
        }
    }
    $listPrefixMap = [
        
        'editpanel_'             => 'panel_list',
        'locationedit_'          => 'panel_list',
        'location_'              => 'paneluser_list',
        'locationnotuser_'       => 'paneluser_list',
        'locationom_'            => 'paneluser_list',
        'locationtest_'          => 'usertest_list',
        'testpanel_'             => 'usertest_list',
        'changeloc_'             => 'changeloc_list',
        'changelocselectlo-'     => 'changeloc_list',
        'changeloclimitbyuser_'  => 'changeloc_list',
        'confirmchangeloccha_'   => 'changeloc_list',
        
        'productedit_'           => 'product_list',
        'productdel_'            => 'product_list',
        'product_'               => 'product_list',
        
        'quickview_'             => 'user_services_list',
        'categorynames_'         => 'category_list',
        'category_'              => 'category_list',
        'discountedit_'          => 'discount_list',
        'discountdel_'           => 'discount_list',
        'discount_'              => 'discount_list',
        'inboundedit_'           => 'inbound_list',
        'inbound_'               => 'inbound_list',
        
        'helpedit_'              => 'help_list',
        'helpdel_'               => 'help_list',
        'helpctgory_'            => 'help_list',
        'channeldel_'            => 'channel_list',
        'confirmchannel-'        => 'channel_list',
        'carduserhide-'          => 'card_list',
        'cardremove_'            => 'card_list',
        
        'banuserlist_'           => 'user_list',
        'blockuserfake_'         => 'user_list',
        'addbalanceuser_'        => 'user_list',
        'addbalamceuser_'        => 'user_list',
        'agenttypshowlist_'      => 'agent_list',
        'addagent_'              => 'agent_list',
        'addagentrequest_'       => 'agent_list',
        'setagenttype_'          => 'agent_list',
        'expireset_'             => 'agent_list',
        'maxbuyagent_'           => 'agent_list',
        'setvolumesrc_'          => 'agent_list',
        'settimepricesrc_'       => 'agent_list',
        
        'hidepanel_'             => 'panel_list',
        'removehide_'            => 'panel_list',
        
        'deliplogin_'            => 'admin_iplogin_dyn',
        'confirmcryptomanual_'   => 'crypto_manual_actions',
        'rejectcryptomanual_'    => 'crypto_manual_actions',
        'cmauto_'                => 'crypto_manual_actions',
        'cmmanual_'              => 'crypto_manual_actions',
        'cmback_'                => 'crypto_manual_actions',
        'rcc_pick_'              => 'crypto_manual_actions',
        
        'editstsuts-'            => 'feature_toggle',
        
        
        'Response_'              => 'support_response',
        'Responsesupport_'       => 'support_response',
        'Responsesusera_'        => 'support_response',
        'Responseuser'           => 'support_response',
        
        'Extra_time_'            => 'extra_purchase',
        'Extra_volume_'          => 'extra_purchase',
        'Extra_volumes_'         => 'extra_purchase',
        'Percentlow_'            => 'extra_purchase',
        
        'btntypemessage-'        => 'btnmsg_settings',
        
        'confirmaccountdisable_'    => 'user_confirms',
        'confirmaccountdisableadmin_'=> 'user_confirms',
        'confirmaextra-'         => 'user_confirms',
        'confirmaextras_'        => 'user_confirms',
        'confirmaextratime-'     => 'user_confirms',
        'confirmchange_'         => 'user_confirms',
        'confirmdisorders-'      => 'user_confirms',
        'confirmnumber_'         => 'user_confirms',
        'confirmremovefulls-'    => 'user_confirms',
        'confirmremoveservices-' => 'user_confirms',
        'confirmserivceadmin-'   => 'user_confirms',
        'confirmserivces-'       => 'user_confirms',
        
        'removeadmin_'           => 'admin_removes',
        'removeaffiliate-'       => 'admin_removes',
        'removeaffiliateuser-'   => 'admin_removes',
        'removeagent_'           => 'admin_removes',
        'removeauto-'            => 'admin_removes',
        'removebotsell_'         => 'admin_removes',
        'rejectrequesta_'        => 'admin_removes',
        
        'crypto_pay_'            => 'crypto_actions',
        'crypto_submit_'         => 'crypto_actions',
        'cryptowallet_'          => 'crypto_actions',
        
        'config_'                => 'service_actions',
        'configget_'             => 'service_actions',
        'activeconfig-'          => 'service_actions',
        'changelink_'            => 'service_actions',
        'changenote_'            => 'service_actions',
        'changestatus_'          => 'service_actions',
        'changestatusadmin_'     => 'service_actions',
        
        'previous_page'          => 'pagination_btns',
        'next_page'              => 'pagination_btns',
        
        'broadcast_'             => 'broadcast_actions',
        'sendmsg_'               => 'broadcast_actions',
        'accept_broadcast_'      => 'broadcast_actions',
        
        'affiliates-'            => 'affiliate_actions',
        
        'editshops-'             => 'shop_edit_actions',
        'changeipnode'           => 'node_actions',
        'changenamenode'         => 'node_actions',
        'changecoefficient'      => 'node_actions',
        'bakcnode'               => 'node_actions',
        'actionnode'             => 'node_actions',
        'deletelist-'            => 'admin_removes',


        'prodcutservice_'        => 'product_buy',
        'prodcutservices_'       => 'product_buy',
        'prodcutserviceom_'      => 'product_buy',
        'prodcutservicesom_'     => 'product_buy',
        'serviceextendselects-'  => 'product_buy',
        'serviceextendselect-'   => 'product_buy',
        'serviceextend-'         => 'product_buy',
        'producttime_'           => 'time_buy',
        'productvolume_'         => 'volume_buy',
        'categorynames_'         => 'category_buy',
        'helpsection_'           => 'helpsection',
        'paneluserbuy_'          => 'panel_buy',
        'locationbuy_'           => 'panel_buy',


        'apn:'                   => 'auto_inline_btn',
        'apnh:'                  => 'auto_inline_btn',


        'extend_'                => 'cron_extend',
        'manageuser_'            => 'cron_manage_user',
        'managepanel_'           => 'cron_manage_panel',
        'cronnotify_'            => 'cron_action_btn',
    ];
    $miscMap = [
        'adm_backmenu'      => 'adm_backmenu',
        'adm_backadmin'     => 'adm_backadmin',
        'hide_mini_app_instruction' => 'hide_mini_app',
        'backproductadmin'  => 'backproductadmin',
        'backadmin'         => 'backadmin',
        'backlistuser'      => 'backlistuser',
        'buyback'           => 'buyback',
        'cancel_gift'       => 'cancel_gift',
        'cancel_hash_input' => 'cancel_hash_input',
        'cancel_sendmessage'=> 'cancel_sendmessage',
        'close_listusers'   => 'close_listusers',
        'close_stat'        => 'close_stat',
        'cronjobs_back_settings'=> 'cronjobs_back_settings',
        'resetbot_cancel'   => 'resetbot_cancel',
        'resetbot_confirm'  => 'resetbot_confirm',
        'broadcast_status_refresh' => 'broadcast_status_refresh',
        'reject'            => 'reject',
        'accept'            => 'accept',
        'acceptrule'        => 'acceptrule',
        'confirmpaid'       => 'confirmpaid',
        'confirmandgetservice' => 'confirmandgetservice',
        'confirmandgetserviceDiscount' => 'confirmandgetserviceDiscount',
        'confirmchannel'    => 'confirmchannel',


        'backuser'          => 'backuser',
        'nav_back'          => 'nav_back',
        'backorder'         => 'backorder',
        'colselist'         => 'colselist',
        'supportbtns'       => 'supportbtns',
        'helpbtns'          => 'helpbtns',
        'usertestbtn'       => 'usertestbtn',
        'none'              => 'inert_label',
        'confirmserdiscount'=> 'confirmserdiscount',
        'confirmserivce'    => 'confirmserivce',
    ];











    $rxGlassOn = isset($GLOBALS['setting']['inlinebtnmain']) && $GLOBALS['setting']['inlinebtnmain'] === 'oninline';

    if ($rxGlassOn && isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxHasReplyFeature = false;
        foreach ($kb['keyboard'] as $rxRChk) {
            if (!is_array($rxRChk)) continue;
            foreach ($rxRChk as $rxBChk) {
                if (is_array($rxBChk) && (
                    isset($rxBChk['request_contact']) ||
                    isset($rxBChk['request_location']) ||
                    isset($rxBChk['request_poll']) ||
                    isset($rxBChk['request_users']) ||
                    isset($rxBChk['request_chat'])
                )) {
                    $rxHasReplyFeature = true;
                    break 2;
                }
            }
        }

        if (!$rxHasReplyFeature && function_exists('rx_makeAdminPanelCallback')) {
            $rxInlineRows = [];
            foreach ($kb['keyboard'] as $rxR) {
                if (!is_array($rxR)) continue;
                $rxNewRow = [];
                foreach ($rxR as $rxB) {
                    if (!is_array($rxB) || !isset($rxB['text'])) continue;
                    if (!isset($rxB['callback_data']) && !isset($rxB['url'])
                        && !isset($rxB['login_url']) && !isset($rxB['switch_inline_query'])
                        && !isset($rxB['switch_inline_query_current_chat'])) {
                        $rxB['callback_data'] = rx_makeAdminPanelCallback((string)$rxB['text']);
                    }
                    $rxNewRow[] = $rxB;
                }
                if (!empty($rxNewRow)) $rxInlineRows[] = $rxNewRow;
            }
            unset(
                $kb['keyboard'], $kb['resize_keyboard'], $kb['input_field_placeholder'],
                $kb['one_time_keyboard'], $kb['selective'], $kb['is_persistent']
            );
            $kb['inline_keyboard'] = $rxInlineRows;
        }
    }

    if (isset($kb['keyboard']) && is_array($kb['keyboard']) && function_exists('rx_makeAdminPanelCallback')) {
        foreach ($kb['keyboard'] as &$rxReplyRow) {
            if (!is_array($rxReplyRow)) continue;
            foreach ($rxReplyRow as &$rxReplyBtn) {
                if (!is_array($rxReplyBtn) || !isset($rxReplyBtn['text'])) continue;
                if (isset($rxReplyBtn['request_contact']) || isset($rxReplyBtn['request_location'])
                    || isset($rxReplyBtn['request_poll']) || isset($rxReplyBtn['request_users'])
                    || isset($rxReplyBtn['request_chat'])) {
                    continue;
                }
                if (!isset($rxReplyBtn['callback_data'])) {
                    $rxReplyBtn['callback_data'] = rx_makeAdminPanelCallback((string)$rxReplyBtn['text']);
                }
            }
            unset($rxReplyBtn);
        }
        unset($rxReplyRow);
    }




    $rxStyleTargetKey = null;
    if (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
        $rxStyleTargetKey = 'inline_keyboard';
    } elseif (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxStyleTargetKey = 'keyboard';
    }

    if ($rxStyleTargetKey !== null) {
        foreach ($kb[$rxStyleTargetKey] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$btn) {
                if (!is_array($btn) || !isset($btn['callback_data'])) continue;
                if (isset($btn['style'])) continue;
                $cb = (string)$btn['callback_data'];
                
                if (isset($miscMap[$cb])) {
                    $st = $_rx_kb_list_styles['misc'][$miscMap[$cb]] ?? null;
                    if (!empty($st) && $st !== 'default') { $btn['style'] = $st; continue; }
                }

                if (isset($_rx_kb_list_styles['features'][$cb])) {
                    $st = $_rx_kb_list_styles['features'][$cb];
                    if (!empty($st) && $st !== 'default') { $btn['style'] = $st; continue; }
                }

                $matched = false;
                foreach ($listPrefixMap as $prefix => $listKey) {
                    if (strpos($cb, $prefix) === 0) {
                        $st = $_rx_kb_list_styles['lists'][$listKey] ?? null;
                        if (!empty($st) && $st !== 'default') {
                            $btn['style'] = $st;
                        }
                        $matched = true;
                        break;
                    }
                }

                if (!$matched && !isset($btn['style'])) {
                    $fb = $_rx_kb_list_styles['lists']['fallback_inline'] ?? null;
                    if (!empty($fb) && $fb !== 'default') {
                        $btn['style'] = $fb;
                    }
                }


                if (!isset($btn['style']) && isset($btn['text'])
                    && function_exists('rx_kb_guess_style_from_text')
                    && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                    $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
                    if ($rxGuessed !== null) {
                        $btn['style'] = $rxGuessed;
                    }
                }
            }
            unset($btn);
        }
        unset($row);
    }
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {

        foreach ($kb['keyboard'] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$btn) {
                if (!is_array($btn)) continue;

                unset($btn['url']);
                unset($btn['switch_inline_query']);
                unset($btn['switch_inline_query_current_chat']);
                unset($btn['login_url']);
                unset($btn['callback_game']);
                unset($btn['pay']);
                unset($btn['copy_text']);
            }
            unset($btn);
        }
        unset($row);
    }


    return $wasString ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
}

function applyPremiumEmojiToKeyboard($keyboard) {

    $wasString = is_string($keyboard);
    if ($wasString) {
        if ($keyboard === '') { return $keyboard; }
        $kb = json_decode($keyboard, true);
        if (!is_array($kb)) { return $keyboard; }
    } elseif (is_array($keyboard)) {
        $kb = $keyboard;
    } else {
        return $keyboard;
    }
    $map = getPremiumEmojiMap();
    if (empty($map)) {

        return $keyboard;
    }


    $sortedEmojis = array_keys($map);
    $stripPrefixIfMatch = function ($text) use ($map, $sortedEmojis) {

        if (!is_string($text) || $text === '') { return null; }
        foreach ($sortedEmojis as $emoji) {
            $emojiByteLen = strlen($emoji);
            if ($emojiByteLen === 0) { continue; }
            if (strncmp($text, $emoji, $emojiByteLen) === 0) {
                $rest = ltrim((string)substr($text, $emojiByteLen));
                return [$rest, $map[$emoji]];
            }
        }
        return null;
    };

    if (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
        foreach ($kb['inline_keyboard'] as &$row) {
            if (!is_array($row)) { continue; }
            foreach ($row as &$btn) {
                if (!is_array($btn)) { continue; }
                if (isset($btn['icon_custom_emoji_id'])) { continue; }
                if (!isset($btn['text']) || !is_string($btn['text'])) { continue; }
                $hit = $stripPrefixIfMatch($btn['text']);
                if ($hit !== null) {
                    $btn['text'] = $hit[0] !== '' ? $hit[0] : ' ';
                    $btn['icon_custom_emoji_id'] = (string)$hit[1];
                }
            }
            unset($btn);
        }
        unset($row);
    }

    
    
    
    
    if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
        $rxReplyEntries = [];
        foreach ($kb['keyboard'] as &$row) {
            if (!is_array($row)) { continue; }
            foreach ($row as &$btn) {
                if (!is_array($btn)) { continue; }
                if (isset($btn['icon_custom_emoji_id'])) { continue; }
                if (!isset($btn['text']) || !is_string($btn['text'])) { continue; }
                $hit = $stripPrefixIfMatch($btn['text']);
                if ($hit !== null) {
                    $origText    = $btn['text'];
                    $strippedTxt = $hit[0] !== '' ? $hit[0] : ' ';
                    
                    $rxReplyEntries[$strippedTxt] = $origText;
                    $btn['text']              = $strippedTxt;
                    $btn['icon_custom_emoji_id'] = (string)$hit[1];
                }
            }
            unset($btn);
        }
        unset($row);
        
        if (!empty($rxReplyEntries)) {
            $rxRbmFile = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
                       . '/rx_reply_button_map.json';
            $rxRbmExisting = [];
            if (is_file($rxRbmFile)) {
                $rxRbmRaw = json_decode((string)@file_get_contents($rxRbmFile), true);
                if (is_array($rxRbmRaw)) { $rxRbmExisting = $rxRbmRaw; }
            }
            $rxRbmMerged = array_merge($rxRbmExisting, $rxReplyEntries);
            @file_put_contents(
                $rxRbmFile,
                json_encode($rxRbmMerged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        }
    }

    return $wasString ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
}


function rx_restorePremiumReplyText(string $text): string {
    if ($text === '') { return $text; }
    $rxRbmFile = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
               . '/rx_reply_button_map.json';
    if (!is_file($rxRbmFile)) { return $text; }
    $rxRbmMap = json_decode((string)@file_get_contents($rxRbmFile), true);
    if (!is_array($rxRbmMap)) { return $text; }
    return isset($rxRbmMap[$text]) ? (string)$rxRbmMap[$text] : $text;
}

if (!function_exists('rx_getKeyboardDefaultStyles')) {





    function rx_getKeyboardDefaultStyles($section = null) {
        static $cache = null;
        if ($cache === null) {
            $cache = [
                'admin_main' => [
                    'admin_status'      => 'primary',
                    'admin_managepanel' => 'success',
                    'admin_addpanel'    => 'success',
                    'admin_timeprice'   => 'success',
                    'admin_volprice'    => 'success',
                    'admin_users'       => 'primary',
                    'admin_shop'        => 'success',
                    'admin_finance'     => 'success',
                    'admin_support'     => 'primary',
                    'admin_help'        => 'primary',
                    'admin_features'    => 'success',
                    'admin_settings'    => 'success',
                    'admin_invoices'    => 'success',
                    'admin_back'        => 'danger',
                ],
                'admin_settings' => [
                    'set_features'   => 'success',
                    'set_reports'    => 'primary',
                    'set_channel'    => 'primary',
                    'set_webpanel'   => 'success',
                    'set_optimize'   => 'success',
                    'set_text'       => 'primary',
                    'set_adminmgr'   => 'primary',
                    'set_testlimit'  => 'success',
                    'set_agentprice' => 'success',
                    'set_qrbg'       => 'primary',
                    'set_webhook'    => 'primary',
                    'set_backadmin'  => 'danger',
                    'set_backmenu'   => 'danger',
                ],
                'admin_shop' => [
                    'shop_status'      => 'success',
                    'shop_category'    => 'primary',
                    'shop_products'    => 'primary',
                    'shop_giftadd'     => 'success',
                    'shop_giftdel'     => 'danger',
                    'shop_discountadd' => 'success',
                    'shop_discountdel' => 'danger',
                    'shop_minbulk'     => 'primary',
                    'shop_renewcb'     => 'primary',
                    'shop_backadmin'   => 'danger',
                    'shop_backmenu'    => 'danger',
                ],
                'admin_roles' => [
                    'seller_status'  => 'primary',
                    'seller_users'   => 'success',
                    'seller_back'    => 'danger',
                    'support_users'  => 'success',
                    'support_search' => 'primary',
                    'support_back'   => 'danger',
                ],
                'admin_gateways' => [

                    'cart_title'       => 'primary',
                    'cart_setnum'      => 'success',
                    'cart_delnum'      => 'danger',
                    'cart_support'     => 'primary',
                    'cart_pvmode'      => 'primary',
                    'cart_autoconfirm' => 'success',
                    'cart_cashback'    => 'success',
                    'cart_firstpay'    => 'primary',
                    'cart_min'         => 'primary',
                    'cart_max'         => 'primary',
                    'cart_edu'         => 'primary',
                    'cart_back'        => 'danger',

                    'trnado_name'      => 'primary',
                    'trnado_apikey'    => 'success',
                    'trnado_wallet'    => 'success',
                    'trnado_apiurl'    => 'success',
                    'trnado_cashback'  => 'success',
                    'trnado_min'       => 'primary',
                    'trnado_max'       => 'primary',
                    'trnado_edu'       => 'primary',
                    'trnado_back'      => 'danger',

                    'zpal_name'        => 'primary',
                    'zpal_merchant'    => 'success',
                    'zpal_cashback'    => 'success',
                    'zpal_min'         => 'primary',
                    'zpal_max'         => 'primary',
                    'zpal_edu'         => 'primary',
                    'zpal_back'        => 'danger',

                    'zpey_name'        => 'primary',
                    'zpey_token'       => 'success',
                    'zpey_cashback'    => 'success',
                    'zpey_tutorial'    => 'primary',
                    'zpey_min'         => 'primary',
                    'zpey_max'         => 'primary',
                    'zpey_edu'         => 'primary',
                    'zpey_back'        => 'danger',

                    'aqaye_name'       => 'primary',
                    'aqaye_merchant'   => 'success',
                    'aqaye_cashback'   => 'success',
                    'aqaye_min'        => 'primary',
                    'aqaye_max'        => 'primary',
                    'aqaye_edu'        => 'primary',
                    'aqaye_back'       => 'danger',

                    'plisio_name'      => 'primary',
                    'plisio_api'       => 'success',
                    'plisio_cashback'  => 'success',
                    'plisio_min'       => 'primary',
                    'plisio_max'       => 'primary',
                    'plisio_edu'       => 'primary',
                    'plisio_back'      => 'danger',
                ],
                'admin_features' => [
                    'feat_info'     => 'success',
                    'feat_test'     => 'success',
                    'feat_help'     => 'success',
                    'feat_back'     => 'danger',
                    'feat_backmenu' => 'danger',
                ],
                'admin_channel' => [
                    'ch_add'      => 'success',
                    'ch_del'      => 'danger',
                    'ch_back'     => 'danger',
                    'ch_backmenu' => 'danger',
                ],
                'admin_help' => [
                    'help_add'      => 'success',
                    'help_del'      => 'danger',
                    'help_edit'     => 'primary',
                    'help_back'     => 'danger',
                    'help_backmenu' => 'danger',
                ],
                'admin_category' => [
                    'cat_add'  => 'success',
                    'cat_del'  => 'danger',
                    'cat_edit' => 'primary',
                    'cat_back' => 'danger',
                ],
                'admin_products' => [
                    'shopitem_add'      => 'success',
                    'shopitem_del'      => 'danger',
                    'shopitem_edit'     => 'primary',
                    'shopitem_priceinc' => 'success',
                    'shopitem_pricedec' => 'danger',
                    'shopitem_back'     => 'danger',
                ],
                'admin_product_edit' => [
                    'قیمت'                  => 'success',
                    'حجم'                   => 'success',
                    'زمان'                  => 'success',
                    'نام محصول'             => 'primary',
                    'نوع کاربری'            => 'primary',
                    'نوع ریست حجم'          => 'primary',
                    'یادداشت'               => 'primary',
                    'موقعیت محصول'          => 'primary',
                    'دسته بندی'             => 'primary',
                    '🎛 تنظیم اینباند'      => 'success',
                    'نمایش برای خرید اول'   => 'success',
                    'مخفی کردن پنل'         => 'primary',
                    'حذف کلی پنل های مخفی'  => 'danger',
                    'backadmin'             => 'danger',
                    'backmenu'              => 'danger',
                ],
                'admin_stats' => [
                    'today_stat'         => 'primary',
                    'yesterday_stat'     => 'primary',
                    'hoursago_stat'      => 'primary',
                    'view_stat_time'     => 'primary',
                    'month_current_stat' => 'success',
                    'month_old_stat'     => 'success',
                    'stat_all_bot'       => 'success',
                    'status_var'         => 'primary',
                    'showprice'          => 'success',
                ],
                'admin_cart_advanced' => [
                    'cart_autocheck'   => 'success',
                    'cart_autotime'    => 'primary',
                    'cart_except_user' => 'primary',
                    'cart_export_num'  => 'primary',
                    'cart_show_num'    => 'success',
                    'cart_hide_num'    => 'danger',
                    'cart_group_num'   => 'primary',
                    'checkpay'         => 'success',
                    'paydirect'        => 'success',
                ],
                'admin_search' => [
                    'searchorder'   => 'primary',
                    'searchservice' => 'primary',
                    'searchuser'    => 'primary',
                    'selectname'    => 'success',
                ],
                'admin_user_lists' => [
                    'alllistusers'    => 'primary',
                    'agentlistusers'  => 'primary',
                    'balanceuserlist' => 'success',
                    'cartuserlist'    => 'success',
                    'adminlist'       => 'primary',
                    'listrefral'      => 'primary',
                    'zerobalance'     => 'danger',
                    'balanceaddall'   => 'success',
                ],
                'features_bot' => [
                    'subject'             => 'primary',
                    'subjectde'           => 'primary',
                    'statusbot'           => 'success',
                    'stautsrolee'         => 'primary',
                    'Authenticationphone' => 'success',
                    'Authenticationiran'  => 'success',
                    'verify'              => 'success',
                    'verifybyuser'        => 'success',
                    'inlinebtnmain'       => 'success',
                ],
                'features_users' => [
                    'usernamebtn'       => 'success',
                    'statusnewuser'     => 'success',
                    'statussupportpv'   => 'success',
                    'statusnamecustom'  => 'success',
                    'statusnamecustomf' => 'success',
                ],
                'features_shop' => [
                    'bulkbuy'             => 'success',
                    'btn_status_category' => 'success',
                    'keyconfig'           => 'success',
                    'copycart'            => 'success',
                    'Debtsettlement'      => 'success',
                    'changeloc'           => 'success',
                    'changeloclimit'      => 'primary',
                    'infocard_status'     => 'success',
                    'infocard_color_menu' => 'primary',
                    'linkappstatus'       => 'success',
                    'linkappsetting'      => 'primary',
                ],
                'features_lottery' => [
                    'wheel_luck'         => 'success',
                    'gradonhshans'       => 'primary',
                    'wheelagentfirst'    => 'success',
                    'wheelagent'         => 'success',
                    'score'              => 'success',
                    'scoresetting'       => 'primary',
                    'Lotteryagent'       => 'success',
                    'affiliatesstatus'   => 'success',
                    'settingaffiliatesf' => 'primary',
                    'Dice'               => 'success',
                ],
                'features_crons' => [
                    'cronday'                 => 'success',
                    'settimecornday'          => 'primary',
                    'on_hold'                 => 'success',
                    'setting_on_holdcron'     => 'primary',
                    'cronvolume'              => 'success',
                    'settimecornvolume'       => 'primary',
                    'notifremove'             => 'danger',
                    'settimecornremove'       => 'primary',
                    'notifremove_volume'      => 'danger',
                    'settimecornremovevolume' => 'primary',
                    'cronjobs_settings'       => 'success',
                ],
                'features_antispam' => [
                    'antispam_toggle'      => 'success',
                    'antispam_set_count'   => 'primary',
                    'antispam_set_seconds' => 'primary',
                    'antispam_set_mute'    => 'primary',
                ],
                'features_nav' => [
                    'featcat_bot'            => 'success',
                    'featcat_users'          => 'success',
                    'featcat_shop'           => 'success',
                    'featcat_lottery'        => 'success',
                    'featcat_crons'          => 'success',
                    'featcat_antispam'       => 'success',
                    'premium_emoji_settings' => 'primary',
                    'featcat_main'           => 'danger',
                    'close_stat'             => 'danger',
                ],
                'admin_pagination' => [
                    'next_page'                => 'primary',
                    'previous_page'            => 'primary',
                    'next_page_extends'        => 'primary',
                    'previous_page_extends'    => 'primary',
                    'next_pageuser'            => 'primary',
                    'previous_pageuser'        => 'primary',
                    'next_pageuserbalance'     => 'primary',
                    'previous_pageuserbalance' => 'primary',
                    'next_pageusercart'        => 'primary',
                    'previous_pageusercart'    => 'primary',
                    'next_pageuserrefral'      => 'primary',
                    'previous_pageuserrefral'  => 'primary',
                    'next_pageuserzero'        => 'primary',
                    'previous_pageuserzero'    => 'primary',
                ],
                'admin_filters' => [
                    'typecustomer_all'         => 'primary',
                    'typecustomer_customer'    => 'success',
                    'typecustomer_notcustomer' => 'danger',
                    'typebalanceall_all'       => 'primary',
                    'typebalanceall_f'         => 'success',
                    'typebalanceall_n2'        => 'success',
                    'typebalanceall_nl'        => 'success',
                    'typeaddprice_percent'     => 'primary',
                    'typeaddprice_static'      => 'primary',
                    'typeagenteditproduct_f'   => 'success',
                    'typeagenteditproduct_n'   => 'success',
                    'typeagenteditproduct_n2'  => 'success',
                    'discounttype_all'         => 'primary',
                    'discounttype_buy'         => 'success',
                    'discounttype_extend'      => 'success',
                    'discountlimitbuy_0'       => 'primary',
                    'discountlimitbuy_1'       => 'success',
                    'typegift_day'             => 'success',
                    'typegift_volume'          => 'success',
                    'voloume_or_day_all'       => 'primary',
                    'agenttypshowlist_all'     => 'primary',
                    'agenttypshowlist_n'       => 'success',
                    'agenttypshowlist_n2'      => 'success',
                ],
                'admin_node' => [
                    'namenode'       => 'primary',
                    'changenamenode' => 'primary',
                    'changeipnode'   => 'primary',
                    'addiplogin'     => 'success',
                    'iploginset'     => 'primary',
                    'removenode'     => 'danger',
                    'reconnectnode'  => 'success',
                    'bakcnode'       => 'danger',
                    'actionnode'     => 'success',
                ],
                'admin_gateway_extra' => [
                    'cartsetting'            => 'primary',
                    'carttocart'             => 'success',
                    'aqayepardakhtsetting'   => 'primary',
                    'zarinpalsetting'        => 'primary',
                    'zarinpeysetting'        => 'primary',
                    'plisiosetting'          => 'primary',
                    'nowpaymentsetting'      => 'primary',
                    'iranpay1setting'        => 'primary',
                    'iranpay2setting'        => 'primary',
                    'iranpay3setting'        => 'primary',
                    'affilnecurrency'        => 'success',
                    'affilnecurrencysetting' => 'primary',
                    'arzireyali1'            => 'success',
                    'arzireyali2'            => 'success',
                    'oniranpay3'             => 'success',
                ],
                'admin_crypto' => [
                    'cryptowallet_TON'        => 'primary',
                    'cryptowallet_TRX'        => 'success',
                    'cryptowallet_USDT_TON'   => 'primary',
                    'cryptowallet_USDT_TRC20' => 'success',
                    'walletaddress'           => 'success',
                    'cryptomemo_yes_TON'      => 'success',
                    'cryptomemo_no_TON'       => 'danger',
                    'cryptomemo_yes_USDT_TON' => 'success',
                    'cryptomemo_no_USDT_TON'  => 'danger',
                ],
                'admin_iplogin' => [
                    'addiplogin'       => 'success',
                    'iploginunlim_on'  => 'success',
                    'iploginunlim_off' => 'danger',
                    'iploginset'       => 'primary',
                ],
                'admin_infocard' => [
                    'infocard_setcolor_red'    => 'danger',
                    'infocard_setcolor_green'  => 'success',
                    'infocard_setcolor_blue'   => 'primary',
                    'infocard_setcolor_orange' => 'primary',
                    'infocard_setcolor_purple' => 'primary',
                    'infocard_setcolor_yellow' => 'primary',
                ],
                'admin_discount_settings' => [
                    'discountextend'     => 'success',
                    'startgift'          => 'success',
                    'get_gift_start'     => 'success',
                    'statuscategorytime' => 'primary',
                    'statustimeextra'    => 'primary',
                ],
                'admin_misc_actions' => [
                    'addnewadmin'             => 'success',
                    'customsellvolume'        => 'primary',
                    'changecoefficient'       => 'primary',
                    'changgestatus'           => 'primary',
                    'categroygenral'          => 'primary',
                    'changenote'              => 'primary',
                    'optimizebot'             => 'success',
                    'removeresid'             => 'danger',
                    'productcheckdata'        => 'primary',
                    'mainbalanceaccount'      => 'success',
                    'maxbalanceaccount'       => 'primary',
                    'kharidanbuh'             => 'success',
                    'systemsms'               => 'primary',
                    'linkappdownlod'          => 'primary',
                    'fqQuestions'             => 'primary',
                    'disorderss'              => 'primary',
                    'reasetchangeloc'         => 'danger',
                    'serviceextendselect_pre' => 'primary',
                    'removeservicebackbtn'    => 'danger',
                    'startelegram'            => 'success',
                ],
                'admin_premium_stock' => [
                    'premium_emoji_add'  => 'success',
                    'premium_emoji_noop' => 'primary',
                    'nm_del_all_stock'   => 'danger',
                    'nm_del_one_stock'   => 'danger',
                    'antispam_noop'      => 'primary',
                ],
                'recheckcrypto_admin_buttons' => [
                    'confirmcryptomanual' => 'success',
                    'rejectcryptomanual'  => 'danger',
                    'cmauto'              => 'success',
                    'cmmanual'            => 'primary',
                    'cmback'              => 'danger',
                ],
                'admin_lists' => [
                    'panel_list'            => 'primary',
                    'paneluser_list'        => 'primary',
                    'usertest_list'         => 'primary',
                    'changeloc_list'        => 'primary',
                    'product_list'          => 'primary',
                    'user_services_list'    => 'primary',
                    'discount_list'         => 'primary',
                    'inbound_list'          => 'primary',
                    'help_list'             => 'primary',
                    'channel_list'          => 'primary',
                    'card_list'             => 'primary',
                    'protocol_list'         => 'primary',
                    'category_list'         => 'primary',
                    'user_list'             => 'primary',
                    'agent_list'            => 'primary',
                    'feature_toggle'        => 'success',
                    'support_response'      => 'primary',
                    'extra_purchase'        => 'success',
                    'btnmsg_settings'       => 'primary',
                    'user_confirms'         => 'success',
                    'admin_removes'         => 'danger',
                    'crypto_actions'        => 'success',
                    'service_actions'       => 'primary',
                    'pagination_btns'       => 'primary',
                    'broadcast_actions'     => 'success',
                    'affiliate_actions'     => 'primary',
                    'shop_edit_actions'     => 'primary',
                    'node_actions'          => 'primary',
                    'admin_iplogin_dyn'     => 'danger',
                    'crypto_manual_actions' => 'success',


                ],
                'service' => [
                    'updateinfo'       => 'primary',
                    'config'           => 'success',
                    'linksub'          => 'primary',
                    'extend'           => 'success',
                    'Extra_volume'     => 'success',
                    'Extra_time'       => 'success',
                    'changestatus'     => 'primary',
                    'change-location'  => 'primary',
                    'transfor'         => 'primary',
                    'ekhtelal'         => 'primary',
                    'removeservice'    => 'danger',
                    'changelink'       => 'primary',
                    'changenameconfig' => 'primary',
                    'backorder'        => 'danger',
                ],
                'account' => [
                    'Discount'    => 'success',
                    'Add_Balance' => 'success',
                    'backuser'    => 'danger',
                ],
                'payment' => [
                    'cart_to_offline'  => 'success',
                    'aqayepardakht'    => 'success',
                    'zarinpal'         => 'success',
                    'zarinpey'         => 'success',
                    'iranpay1'         => 'success',
                    'iranpay2'         => 'success',
                    'iranpay3'         => 'success',
                    'paymentnotverify' => 'success',
                    'plisio'           => 'primary',
                    'nowpayment'       => 'primary',
                    'digitaltron'      => 'primary',
                    'startelegrams'    => 'primary',
                    'colselist'        => 'danger',
                ],
                'user_nav' => [
                    'confirm_pay'      => 'success',
                    'confirm_discount' => 'success',
                    'confirm_back'     => 'danger',
                    'rules_accept'     => 'success',
                    'nav_back'         => 'danger',
                    'contact_phone'    => 'success',
                    'contact_back'     => 'danger',
                ],
                'pay_receipt' => [
                    'pay_sendreceipt' => 'success',
                    'pay_done'        => 'success',
                    'pay_cancel'      => 'danger',
                    'pay_back'        => 'danger',
                    'pay_wallet_copy' => 'primary',
                    'pay_card_copy'   => 'primary',
                    'pay_check'       => 'primary',
                ],
                'user_subscription' => [
                    'buy_service'     => 'success',
                    'helpbtn'         => 'primary',
                    'support'         => 'primary',
                    'Status'          => 'primary',
                    'LastTraffic'     => 'primary',
                    'RemainingVolume' => 'primary',
                    'expirationDate'  => 'primary',
                    'extravolunme'    => 'success',
                    'exntedagei'      => 'success',
                    'Responseuser'    => 'primary',
                    'requestagent'    => 'success',
                    'iduser'          => 'primary',
                    'username'        => 'primary',
                    'notusernameme'   => 'primary',
                ],
                'invoice_copy_buttons' => [
                    'currency_pick' => 'primary',
                    'mode_external' => 'primary',
                    'mode_iranian'  => 'primary',
                    'copy_wallet'   => 'primary',
                    'copy_amount'   => 'primary',
                    'copy_memo'     => 'primary',
                    'paid_submit'   => 'success',
                    'invoice_back'  => 'danger',
                ],
                'recheckcrypto_buttons' => [
                    'recheckcrypto'        => 'success',
                    'rcc_pick_TRX'         => 'primary',
                    'rcc_pick_TON'         => 'primary',
                    'rcc_pick_USDT_TRC20'  => 'primary',
                    'rcc_pick_USDT_TON'    => 'primary',
                    'rcc_skip_photo'       => 'primary',
                    'rcc_cancel'           => 'danger',
                ],
                'user_dynamic_lists' => [
                    'product_buy'      => 'primary',
                    'category_buy'     => 'primary',
                    'time_buy'         => 'primary',
                    'volume_buy'       => 'primary',
                    'helpsection'      => 'primary',
                    'channel_join'     => 'success',
                    'panel_buy'        => 'primary',
                    'panel_back'       => 'danger',
                    'product_back'     => 'danger',
                    'category_back'    => 'danger',
                    'custom_volume'    => 'success',
                    'auto_inline_btn'  => 'primary',
                ],
                'cron_notifications' => [
                    'cron_extend'         => 'success',
                    'cron_manage_user'    => 'primary',
                    'cron_manage_panel'   => 'primary',
                    'cron_action_btn'     => 'primary',
                    'cron_buy_service'    => 'success',
                    'cron_start_bot'      => 'success',
                    'cron_usertest'       => 'success',
                    'cron_help'           => 'primary',
                    'cron_affiliates'     => 'success',
                    'cron_addbalance'     => 'success',
                    'cron_cancel'         => 'danger',
                ],
                'misc_buttons' => [
                    'adm_backmenu'                 => 'danger',
                    'adm_backadmin'                => 'danger',
                    'hide_mini_app'                => 'danger',
                    'backproductadmin'             => 'danger',
                    'backadmin'                    => 'danger',
                    'backlistuser'                 => 'danger',
                    'buyback'                      => 'danger',
                    'cancel_gift'                  => 'danger',
                    'cancel_hash_input'            => 'danger',
                    'cancel_sendmessage'           => 'danger',
                    'close_listusers'              => 'danger',
                    'close_stat'                   => 'danger',
                    'cronjobs_back_settings'       => 'danger',
                    'resetbot_cancel'              => 'danger',
                    'resetbot_confirm'             => 'success',
                    'broadcast_status_refresh'     => 'primary',
                    'reject'                       => 'danger',
                    'accept'                       => 'success',
                    'acceptrule'                   => 'success',
                    'confirmpaid'                  => 'success',
                    'confirmandgetservice'         => 'success',
                    'confirmandgetserviceDiscount' => 'success',
                    'confirmchannel'               => 'success',
                    'confirmserdiscount'           => 'success',
                    'confirmserivce'               => 'success',
                    'agentpanel'                   => 'primary',
                    'cronjob_display'              => 'primary',
                    'startaction'                  => 'success',
                    'locationedit_all'             => 'primary',
                    'locationmessage_all'          => 'primary',


                    'backuser'                     => 'danger',
                    'nav_back'                     => 'danger',
                    'backorder'                    => 'danger',
                    'colselist'                    => 'danger',
                    'supportbtns'                  => 'primary',
                    'helpbtns'                     => 'primary',
                    'usertestbtn'                  => 'success',
                    'inert_label'                  => 'primary',
                ],
            ];










            $rxPanelReplyCommon = [
                "⚙️ وضعیت قابلیت ها پنل"            => 'success',
                "✍️ نام پنل"                          => 'primary',
                "❌ حذف پنل"                          => 'danger',
                "🔐 ویرایش رمز عبور"                  => 'primary',
                "👤 ویرایش نام کاربری"                => 'primary',
                "🔗 ویرایش آدرس پنل"                  => 'primary',
                "🔋 روش تمدید سرویس"                  => 'success',
                "💡 روش ساخت نام کاربری"              => 'primary',
                "🚨 محدودیت ساخت اکانت"               => 'success',
                "📍 تغییر گروه کاربری"                => 'primary',
                "⏳ زمان سرویس تست"                  => 'success',
                "💾 حجم اکانت تست"                   => 'success',
                "🌍 قیمت تغییر لوکیشن"                => 'success',
                "➕ قیمت حجم اضافه"                   => 'success',
                "⏳ قیمت زمان اضافه"                  => 'success',
                "⚙️ قیمت حجم سرویس دلخواه"          => 'success',
                "⏳ قیمت زمان دلخواه"                 => 'success',
                "📍 حداقل حجم دلخواه"                 => 'primary',
                "📍 حداکثر حجم دلخواه"                => 'primary',
                "📍 حداقل زمان دلخواه"                => 'primary',
                "📍 حداکثر زمان دلخواه"               => 'primary',
                "📦 انبار شبکه ملی"                   => 'primary',
                "📌 ثبت پنل اضطراری"                  => 'success',
                "🚨 پنل اضطراری"                      => 'danger',
                "🌐 وضعیت نت ملی"                    => 'success',
                "🫣 مخفی کردن پنل برای یک کاربر"      => 'primary',
                "❌  حذف کاربر از لیست مخفی شدگان"    => 'danger',
                "⚙️  اینباند اکانت غیرفعال"          => 'primary',
            ];

            $cache['admin_panel_marzban'] = $rxPanelReplyCommon + [
                "⚙️ تنظیم پروتکل و اینباند" => 'success',
            ];
            $cache['admin_panel_guard'] = $rxPanelReplyCommon + [
                "🔐 ویرایش کلید"                => 'primary',
                "⁉️ وضعیت اتصال به پنل"       => 'primary',
                "⚙️ تنظیم سرویس ها"            => 'success',
                "🎛️ تنظیمات سرویس"            => 'primary',
            ];
            $cache['admin_panel_ibsng'] = $rxPanelReplyCommon + [
                '🎛 تنظیم نام گروه' => 'primary',
            ];
            $cache['admin_panel_mikrotik'] = $rxPanelReplyCommon + [
                '🎛 تنظیم نام گروه' => 'primary',
            ];
            $cache['admin_panel_s_ui'] = $rxPanelReplyCommon + [
                "⚙️ تنظیم پروتکل و اینباند" => 'success',
            ];
            $cache['admin_panel_wg'] = $rxPanelReplyCommon + [
                "💎 تنظیم شناسه اینباند" => 'success',
            ];
            $cache['admin_panel_marzneshin'] = $rxPanelReplyCommon + [
                "⚙️ تنظیمات سرویس" => 'primary',
            ];
            $cache['admin_panel_manualsale'] = $rxPanelReplyCommon + [
                "➕ اضافه کردن کانفیگ" => 'success',
                "❌ حذف کانفیگ "       => 'danger',
                "✏️ ویرایش کانفیگ"    => 'primary',
            ];
            $cache['admin_panel_x_ui_single'] = $rxPanelReplyCommon + [
                "💎 تنظیم شناسه اینباند" => 'success',
                '🔗 دامنه لینک ساب'      => 'primary',
            ];
            $cache['admin_panel_alireza_single'] = $rxPanelReplyCommon + [
                "💎 تنظیم شناسه اینباند" => 'success',
                '🔗 دامنه لینک ساب'      => 'primary',
            ];
            $cache['admin_panel_hiddify'] = $rxPanelReplyCommon + [
                '🔗 دامنه لینک ساب' => 'primary',
                "🔗 uuid admin"     => 'primary',
            ];
            $cache['admin_panel_athmarzban'] = [
                "🔧 ساخت کانفیگ دستی" => 'primary',
                "🖥 مدیریت نود ها"     => 'success',
            ];
            $cache['admin_panel_athx_ui'] = [
                "🔧 ساخت کانفیگ دستی" => 'primary',
            ];
            // Stock management keyboard (📦 انبار شبکه ملی) — coloured per-action.
            $cache['admin_panel_stock_manage'] = [
                "➕ افزودن انبار مدنظر"        => 'success',
                "➕ وارد کردن دسته‌ای انبار"   => 'success',
                "➕ افزودن کانفیگ تکی انبار"   => 'success',
                "✏️ ویرایش انبار"             => 'primary',
                "❌ حذف کانفیگ انبار"         => 'danger',
                "🗑 حذف کامل انبار"           => 'danger',
                "📊 گزارش موجودی انبار"        => 'primary',
                "🔄 همگام‌سازی محصولات انبار" => 'primary',
                "🚨 پنل اضطراری"              => 'danger',
                "🌐 وضعیت نت ملی"            => 'success',
                "🔙 بازگشت به انبار"           => 'primary',
                "🔢 حذف کانفیگ با آیدی"        => 'danger',
                "📋 نمایش لیست کانفیگ‌ها"      => 'primary',
                "🗑 حذف همه کانفیگ‌های فعال این انبار" => 'danger',
            ];
        }
        if ($section === null) {
            return $cache;
        }
        return isset($cache[$section]) && is_array($cache[$section]) ? $cache[$section] : [];
    }
}

if (!function_exists('rx_kb_guess_style_from_text')) {
    /**
     * Smart heuristic that picks a button style by inspecting the button's
     * visible text + leading emoji.
     *
     *  - success (green)  →  ✅/✔/🟢/☑/➕/👍   OR keywords like
     *                       تایید / موفق / ذخیره / افزایش / افزودن / ساخت /
     *                       ایجاد / فعال / روشن / می‌پذیرم / شروع / تمدید /
     *                       شارژ / خرید / پرداخت کردم / انجام دادم …
     *
     *  - danger  (red)    →  ❌/✖/🚫/🗑/⛔/🔴/🔙/◀/⬅   OR keywords like
     *                       ناموفق / حذف / لغو / انصراف / مسدود / بستن /
     *                       خاموش / متوقف / خروج / جعلی / بازگشت …
     *
     *  - primary (blue)   →  🔵/🛡/📊/⚙/🔧/ℹ/📋/📈/📉/⏱/⏰   OR keywords like
     *                       تنظیم / مدیریت / آمار / گزارش / اطلاعات /
     *                       جستجو / مشاهده / نمایش / لیست / وضعیت / ویرایش
     *
     *  - null              →  nothing matched (button stays uncoloured).
     *
     * Priority: success → danger → primary → null. First match wins so a
     * button that contains both ❌ and a success word will pick success
     * first (which is intentional — explicit ✅ on a button usually
     * overrides everything else).
     *
     * Used as a fallback when the user has not explicitly chosen a colour
     * in the web panel (i.e. picked "default") or when the button isn't
     * mapped through misc/features/prefix lookups.
     */
    function rx_kb_guess_style_from_text(string $text): ?string {
        $t = trim($text);
        if ($t === '') return null;

        static $rules = null;
        if ($rules === null) {
            $rules = [
                'success' => [

                    '✅', '✔', '🟢', '☑', '🆗', '👍', '➕', '🎉', '🆕', '🎁', '💎',

                    'تایید', 'تأیید', 'موفق', 'ذخیره',
                    'افزایش', 'افزودن', 'افزود', 'اضافه',
                    'ساخت', 'ساختن', 'ایجاد',
                    'فعال‌سازی', 'فعال سازی', 'فعال‌',
                    'روشن',
                    'می‌پذیرم', 'می پذیرم', 'بپذیر', 'پذیرفت',
                    'شروع',
                    'تمدید',
                    'شارژ',
                    'خرید',
                    'پرداخت کردم', 'پرداخت و',
                    'انجام دادم',
                    'دریافت هدیه', 'دریافت سرویس',
                    'ثبت کد', 'ثبت و',
                    'تایید پرداخت', 'تایید و',
                    'کش‌بک', 'کش بک',
                ],
                'danger' => [

                    '❌', '✖', '🔴', '🚫', '🗑', '⛔', '🛑', '⚠', '🔙', '◀', '⬅', '⬇',

                    'ناموفق',
                    'حذف',
                    'لغو',
                    'انصراف',
                    'مسدود', 'بلاک',
                    'بستن',
                    'خاموش',
                    'متوقف', 'توقف', 'پایان',
                    'خروج', 'اخراج',
                    'جعلی',
                    'بازگشت',
                    'کاهش',
                    'اختلال',
                    'خطا',
                    'هشدار',
                    'منقضی',
                    'محدود',
                ],
                'primary' => [

                    '🔵', '🛡', '📊', '⚙', '🔧', 'ℹ', '📋', '📈', '📉',
                    '⏱', '⏰', '⏳', '🔍', '👁', '🔗', '📡', '📂', '📁', '📑',
                    '📌', '📨', '📥', '📤', '📝', '🖥', '🌐', '🧪', '🎨', '🔄',
                    '♻', '🆔', '🗂', '🏷', '🌍', '🚚', '🎛',

                    'تنظیم', 'تنظیمات',
                    'مدیریت',
                    'آمار',
                    'گزارش',
                    'اطلاعات',
                    'جستجو',
                    'مشاهده', 'نمایش',
                    'لیست',
                    'وضعیت',
                    'ویرایش',
                    'تغییر',
                    'انتخاب',
                    'اعلان', 'اطلاع‌رسانی', 'اطلاع رسانی',
                    'درخواست',
                    'بروزرسانی', 'بروز رسانی',
                    'دریافت',
                    'انتقال',
                    'ارسال',
                    'لینک',
                    'مشخصات',
                    'پنل',
                    'کانال',
                    'راهنما', 'آموزش',
                    'یادداشت',
                    'دسته‌بندی', 'دسته بندی',
                    'محصول',
                    'موقعیت', 'لوکیشن',
                    'اشتراک',
                    'احراز هویت',
                    'کیف‌پول', 'کیف پول',
                ],
            ];
        }

        foreach ($rules as $style => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($t, $kw) !== false) return $style;
            }
        }
        return null;
    }
}

if (!function_exists('rx_kb_use_defaults')) {
    /**
     * Whether the bot should merge its built-in default keyboard colours into
     * the user's panel selection.
     *
     * Stored as the special key `_use_defaults` inside the
     * `setting.keyboard_styles_all` JSON column. Default is TRUE (preserves
     * existing behaviour for installs that never touched this).
     *
     * Cached per-request to avoid repeated DB hits.
     */
    function rx_kb_use_defaults(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;
        $cached = true;
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if ($row && !empty($row['keyboard_styles_all'])) {
                    $all = json_decode($row['keyboard_styles_all'], true);
                    if (is_array($all) && array_key_exists('_use_defaults', $all)) {
                        $cached = (bool) $all['_use_defaults'];
                    }
                }
            }
        } catch (Throwable $e) { /* keep default true */ }
        return $cached;
    }
}

if (!function_exists('rx_mergeKeyboardSectionDefaults')) {



    function rx_mergeKeyboardSectionDefaults(array $userStyles, $section) {
        if (!rx_kb_use_defaults()) {
            return $userStyles;
        }
        $defaults = rx_getKeyboardDefaultStyles($section);
        if (empty($defaults)) {
            return $userStyles;
        }

        return $userStyles + $defaults;
    }
}

function sendmessage($chat_id,$text,$keyboard,$parse_mode,$bot_token = null){
    if(intval($chat_id) == 0)return ['ok' => false];
    $text = applyPremiumEmojiTransform($text, $parse_mode);
    $keyboard = processKeyboardStyles($keyboard);
    $keyboard = applyPremiumEmojiToKeyboard($keyboard);
    return telegram('sendmessage',[
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        '_rx_already_transformed' => true,
        ],$bot_token);
}


if (!function_exists('rx_cron_style')) {
    /**
     * Resolve the style configured under panel section "cron_notifications"
     * for a given key (e.g. cron_buy_service, cron_start_bot, …).
     *
     * Returns the style name (success/primary/danger/...) or null when the
     * user picked "default" (no styling).
     *
     * Use this when building inline keyboards in cronbot/* where the
     * callback_data clashes with non-cron menus, so processKeyboardStyles
     * cannot disambiguate by callback_data alone.
     */
    function rx_cron_style(string $key): ?string {
        static $cronStyles = null;
        if ($cronStyles === null) {
            $cronStyles = [];
            try {
                global $pdo;
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
                    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if ($row && !empty($row['keyboard_styles_all'])) {
                        $all = json_decode($row['keyboard_styles_all'], true);
                        if (is_array($all) && !empty($all['cron_notifications']) && is_array($all['cron_notifications'])) {
                            $cronStyles = $all['cron_notifications'];
                        }
                    }
                }
            } catch (Throwable $e) { /* ignore — defaults below */ }
            if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
                $cronStyles = $cronStyles + rx_getKeyboardDefaultStyles('cron_notifications');
            }
        }
        $v = $cronStyles[$key] ?? null;
        if (!is_string($v) || $v === '' || $v === 'default') return null;
        return $v;
    }
}

if (!function_exists('rx_cron_btn')) {
    /**
     * Apply the panel-configured cron_notifications style to a button array
     * (pass-through if no style configured). Idempotent.
     *
     * Example:
     *   rx_cron_btn('cron_buy_service', ['text' => 'خرید', 'callback_data' => 'buy'])
     */
    function rx_cron_btn(string $cronKey, array $btn): array {
        if (isset($btn['style'])) return $btn;
        $st = rx_cron_style($cronKey);
        if ($st !== null) $btn['style'] = $st;
        return $btn;
    }
}
function prepareTelegramInputFile($input)
{
    if ($input instanceof CURLFile) {
        return $input;
    }

    if (is_string($input)) {
        if (preg_match('/^https?:\/\//i', $input)) {
            return $input;
        }

        $realPath = realpath($input);
        if ($realPath !== false && is_file($realPath) && is_readable($realPath)) {
            return new CURLFile($realPath);
        }

        error_log(sprintf('Telegram document path is not readable: %s', $input));
        return null;
    }

    error_log('Unsupported Telegram input file type: ' . gettype($input));
    return null;
}

function sendDocument($chat_id, $documentPath, $caption)
{
    $document = prepareTelegramInputFile($documentPath);
    if ($document === null) {
        return [
            'ok' => false,
            'description' => 'Document could not be prepared for Telegram upload.'
        ];
    }

    return telegram('sendDocument', [
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => $caption,
    ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    return telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption){
    telegram('sendphoto',[
        'chat_id' => $chat_id,
        'photo'=> $photoid,
        'caption'=> $caption,
    ]);
}
function sendvideo($chat_id,$videoid,$caption){
    telegram('sendvideo',[
        'chat_id' => $chat_id,
        'video'=> $videoid,
        'caption'=> $caption,
    ]);
}
function senddocumentsid($chat_id,$documentid,$caption){
    telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document'=> $documentid,
        'caption'=> $caption,
    ]);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard,$parse_mode = 'HTML'){
    $message_id = (int) $message_id;
    if ($message_id <= 0) {
        if (function_exists('sendmessage')) {
            return sendmessage($chat_id, $text, $keyboard, strtolower($parse_mode));
        }
        return ['ok' => false, 'description' => 'message_id missing'];
    }
    $text = applyPremiumEmojiTransform($text, $parse_mode);
    $keyboard = processKeyboardStyles($keyboard);
    $keyboard = applyPremiumEmojiToKeyboard($keyboard);

    
    
    
    global $update;
    $__callbackMsg = (isset($update['callback_query']['message']) && is_array($update['callback_query']['message']))
        ? $update['callback_query']['message'] : null;
    $__callbackMsgId = $__callbackMsg ? (int)($__callbackMsg['message_id'] ?? 0) : 0;
    $__isPhotoMsg = $__callbackMsg && (
        !empty($__callbackMsg['photo'])
        || !empty($__callbackMsg['video'])
        || !empty($__callbackMsg['animation'])
        || !empty($__callbackMsg['document'])
    );
    if ($__isPhotoMsg && $__callbackMsgId === (int)$message_id && function_exists('sendmessage')) {
        if (function_exists('deletemessage')) {
            @deletemessage($chat_id, $message_id);
        }
        return sendmessage($chat_id, $text, $keyboard, strtolower($parse_mode));
    }

    $result = telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        '_rx_already_transformed' => true,
    ]);


    if (is_array($result) && isset($result['ok']) && $result['ok'] === false) {
        $desc = strtolower((string)($result['description'] ?? ''));


        $needsFallback = (
            strpos($desc, 'no text in the message') !== false
            || strpos($desc, "message can't be edited") !== false
            || strpos($desc, 'message to edit not found') !== false
        );
        if ($needsFallback && function_exists('sendmessage')) {
            
            
            if (strpos($desc, 'no text in the message') !== false && function_exists('deletemessage')) {
                @deletemessage($chat_id, $message_id);
            }
            return sendmessage($chat_id, $text, $keyboard, strtolower($parse_mode));
        }
    }
    return $result;
}
 function deletemessage($chat_id, $message_id){
  $message_id = (int) $message_id;
  if ($message_id <= 0) return ['ok' => false, 'description' => 'message_id missing'];
  return telegram('deletemessage', [
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
 }
function getFileddire($photoid){
  return telegram('getFile', [
'file_id' => $photoid,
]);
 }
function pinmessage($from_id,$message_id){
  return telegram('pinChatMessage', [
'chat_id' => $from_id,
'message_id' => $message_id,
]);
 }
 function unpinmessage($from_id){
  return telegram('unpinAllChatMessages', [
'chat_id' => $from_id,
]);
 }
  function answerInlineQuery($inline_query_id,$results){
  return telegram('answerInlineQuery', [
      "inline_query_id" => $inline_query_id,
        "results" => json_encode($results)
]);
 }
function convertPersianNumbersToEnglish($string) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($persian_numbers, $english_numbers, $string);
}

function isDuplicateUpdate($updateId)
{
    static $memoryCache = [];

    if (!is_numeric($updateId) || $updateId <= 0) {
        return false;
    }

    $now = time();
    $timeToLive = 120;

    foreach ($memoryCache as $id => $timestamp) {
        if (!is_numeric($timestamp) || ($now - (int)$timestamp) > $timeToLive) {
            unset($memoryCache[$id]);
        }
    }

    if (isset($memoryCache[$updateId])) {
        return true;
    }

    $isDuplicate = false;

    $cacheDir = __DIR__ . '/storage/cache';
    $cacheDirReady = true;

    if (!is_dir($cacheDir)) {
        $cacheDirReady = @mkdir($cacheDir, 0775, true) || is_dir($cacheDir);
    }

    if ($cacheDirReady) {
        $cacheFile = $cacheDir . '/recent_updates.json';
        $handle = @fopen($cacheFile, 'c+');

        if ($handle !== false) {
            try {
                if (flock($handle, LOCK_EX)) {
                    rewind($handle);
                    $contents = stream_get_contents($handle);
                    $recentUpdates = $contents ? json_decode($contents, true) : [];
                    if (!is_array($recentUpdates)) {
                        $recentUpdates = [];
                    }

                    foreach ($recentUpdates as $id => $timestamp) {
                        if (!is_numeric($timestamp) || ($now - (int)$timestamp) > $timeToLive) {
                            unset($recentUpdates[$id]);
                        }
                    }

                    if (array_key_exists((string) $updateId, $recentUpdates) || array_key_exists($updateId, $recentUpdates)) {
                        $isDuplicate = true;
                    } else {
                        $recentUpdates[(string) $updateId] = $now;

                        if (count($recentUpdates) > 200) {
                            asort($recentUpdates);
                            $recentUpdates = array_slice($recentUpdates, -200, null, true);
                        }

                        $encoded = json_encode($recentUpdates);
                        if ($encoded !== false) {
                            rewind($handle);
                            ftruncate($handle, 0);
                            fwrite($handle, $encoded);
                            fflush($handle);
                        }
                    }

                    flock($handle, LOCK_UN);
                }
            } catch (Throwable $e) {
                try {
                    flock($handle, LOCK_UN);
                } catch (Throwable $ignored) {
                }
            }

            fclose($handle);
        }
    }

    if (!$isDuplicate) {
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            static $dbInitialised = false;
            static $lastCleanup = 0;

            try {
                if (!$dbInitialised) {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS processed_updates (
                        update_id BIGINT UNSIGNED PRIMARY KEY,
                        processed_at INT UNSIGNED NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                    $dbInitialised = true;
                }

                if ($lastCleanup === 0 || ($now - $lastCleanup) > 60) {
                    $stmtCleanup = $pdo->prepare('DELETE FROM processed_updates WHERE processed_at < :threshold');
                    $stmtCleanup->execute([':threshold' => $now - $timeToLive]);
                    $lastCleanup = $now;
                }

                $stmtInsert = $pdo->prepare('INSERT IGNORE INTO processed_updates (update_id, processed_at) VALUES (:id, :ts)');
                $stmtInsert->execute([':id' => $updateId, ':ts' => $now]);
                if ($stmtInsert->rowCount() === 0) {
                    $isDuplicate = true;
                }
            } catch (Throwable $e) {
                error_log('Duplicate update tracker database fallback error: ' . $e->getMessage());
            }
        }
    }

    if (!$isDuplicate) {
        $memoryCache[$updateId] = $now;
    }

    return $isDuplicate;
}


$rx_request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));


if ($rx_request_method !== 'POST') {
    return;
}


if (defined('FAOXIMA_SKIP_BOTAPI_ROUTER') && FAOXIMA_SKIP_BOTAPI_ROUTER) {
    return;
}


$rx_raw_body = @file_get_contents('php://input');
if (!is_string($rx_raw_body) || $rx_raw_body === '') {
    return;
}


$rx_update_probe = json_decode($rx_raw_body, true);
if (!is_array($rx_update_probe)) {
    return;
}


if (!isset($rx_update_probe['update_id'])) {
    return;
}


$update = $rx_update_probe;
$update_id = $update['update_id'] ?? 0;
if (isDuplicateUpdate($update_id)) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}


try {
    $rxSecretRow = $pdo->query("SELECT webhook_secret_token FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $rxStoredSecret = is_array($rxSecretRow) ? (string)($rxSecretRow['webhook_secret_token'] ?? '') : '';
} catch (\Throwable $rxSecretQueryErr) {
    $rxStoredSecret = '';
}
if ($rxStoredSecret !== '') {
    $rxIncomingSecret = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
        ? (string) $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']
        : '';
    if (!hash_equals($rxStoredSecret, $rxIncomingSecret)) {
        $rxRejectIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rxRejectIpKey = preg_replace('/[^A-Fa-f0-9.:]/', '_', $rxRejectIp);
        $rxRejectMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'botapi_reject_' . substr(md5($rxRejectIpKey), 0, 16) . '.flag';
        $rxShouldLog = true;
        if (is_file($rxRejectMarker) && (time() - (int) @filemtime($rxRejectMarker)) < 3600) {
            $rxShouldLog = false;
        }
        if ($rxShouldLog) {
            error_log('[botapi] Rejected webhook: bad or missing secret_token from ' . $rxRejectIp);
            @touch($rxRejectMarker);
        }
        if (!headers_sent()) {
            http_response_code(200);
        }
        exit;
    }
}

$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;
$time_message = $update['message']['date'] ?? $update['callback_query']['date'] ?? $update["inline_query"]['date'] ?? 0;
$is_bot = $update['message']['from']['is_bot'] ?? false;
$chat_member = $update['chat_member'] ?? null;
$language_code = strtolower($update['message']['from']['language_code'] ?? $update['callback_query']['from']['language_code'] ?? "fa");
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
if(isset($update['pre_checkout_query'])){
    $Chat_type = "private";
    $from_id = $update['pre_checkout_query']['from']['id'];
}


$rx_assertNumericId = static function ($value): int {
    if (!is_numeric($value)) {
        return 0;
    }
    if ((string)(int) $value !== (string) $value) {
        return 0;
    }
    return (int) $value;
};
if (!is_numeric($from_id) || (string)(int) $from_id !== (string) $from_id) {
    error_log('[botapi] Rejected non-numeric from_id from webhook payload');
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}
$from_id = (int) $from_id;
$text =convertPersianNumbersToEnglish($text);

$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$message_id = $rx_assertNumericId($message_id);
$time_message = $update["message"]["date"] ?? $update["callback_query"]["date"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$forward_from_id = $rx_assertNumericId($forward_from_id);
$datain = $update["callback_query"]["data"] ?? '';
$last_name = $update['message']['from']['last_name']  ?? $update["callback_query"]["from"]["last_name"] ?? $update["inline_query"]['from']['last_name'] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$contact_id = $rx_assertNumericId($contact_id);
$callback_query_id = $update["callback_query"]["id"] ?? 0;


if (!is_string($callback_query_id) && !is_int($callback_query_id)) {
    $callback_query_id = '';
} elseif (!preg_match('/^\d{1,32}$/', (string) $callback_query_id)) {
    $callback_query_id = '';
}
$inline_query_id = $update["inline_query"]["id"] ?? 0;
if (!is_string($inline_query_id) && !is_int($inline_query_id)) {
    $inline_query_id = '';
} elseif (!preg_match('/^\d{1,32}$/', (string) $inline_query_id)) {
    $inline_query_id = '';
}
$query = $update["inline_query"]["query"] ?? 0;
