<?php

$version = file_get_contents('version');
date_default_timezone_set('Asia/Tehran');
$new_marzban = isset($new_marzban) ? $new_marzban : false;
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');
ini_set('memory_limit', '-1');
if (!defined('FAOXIMA_LAZY_MYSQLI')) {
    define('FAOXIMA_LAZY_MYSQLI', true);
}
require_once 'config.php';
require_once REFACTORED_LEGACY_ROOT . '/lib/WebhookAuth.php';
FaoximaWebhookAuth::enforce((string) ($APIKEY ?? ''));
require_once 'botapi.php';
require_once 'jdf.php';
require_once 'function.php';
require_once 'keyboard.php';
require_once 'vendor/autoload.php';
require_once 'panels.php';
require_once 'infocard.php';
$setting = select("setting", "*");
$textbotlang = languagechange('text.json');
if ($is_bot)
    return;
if (isset($update['chat_member'])) {
    $status = $update['chat_member']['new_chat_member']['status'];
    $from_id = $update['chat_member']['new_chat_member']['user']['id'];
    $user = select("user", "id", $from_id);
    $keyboard_channel_left = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📌 عضویت مجدد", 'url' => "https://t.me/{$update['chat_member']['chat']['username']}"],
            ],
        ]
    ]);
    if (in_array($status, ['left', 'kicked', 'restricted'])) {
        sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
        return;
    }
}
if (!in_array($Chat_type, ["private", "supergroup"]))
    return;
if ($Chat_type === "supergroup" && isset($update['message'])) {
    $_rxGroupAdminStep = '';
    if (function_exists('rx_isAdminChat') && rx_isAdminChat($from_id)) {
        $_rxGroupAdminRow = select("user", "step", "id", $from_id, "select", ['cache' => false]);
        $_rxGroupAdminStep = is_array($_rxGroupAdminRow) ? (string) ($_rxGroupAdminRow['step'] ?? '') : '';
    }
    if (!in_array($_rxGroupAdminStep, ['reject-dec'], true)) {
        if (function_exists('rx_replyGroupIdCommand')) {
            rx_replyGroupIdCommand($update, $from_id, $Chat_type);
        }
        return;
    }
}
if (isset($chat_member))
    return;
$first_name = sanitizeUserName($first_name);
if (!is_array($setting)) {
    $rxSettingMissingMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_setting_missing.flag';
    if (!is_file($rxSettingMissingMarker) || (time() - (int) @filemtime($rxSettingMissingMarker)) > 3600) {
        error_log('Settings data is unavailable. Ensure the `setting` table exists and contains records.');
        @touch($rxSettingMissingMarker);
    }
    unset($rxSettingMissingMarker);
    return;
}
$ManagePanel = new ManagePanel();
$keyboard_check = json_decode($setting['keyboardmain'], true);
if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    update("setting", "keyboardmain", $keyboardmain, null, null);
}

if (!checktelegramip())
    die("Unauthorized access");

if (intval($from_id) == 0)
    return;

if (!empty($callback_query_id) && function_exists('rx_flushPendingReplyKeyboardCleanup')) {
    rx_flushPendingReplyKeyboardCleanup($from_id);
}

$user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
$isNewUser = !is_array($user);
$otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
if ($isNewUser && $setting['statusnewuser'] == "onnewuser") {
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $newuser = sprintf($textbotlang['Admin']['ManageUser']['newuser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $newuser,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
$date = time();
if ($from_id != 0 && $isNewUser) {
    if ($setting['verifystart'] != "onverify") {
        $valueverify = 1;
    } else {
        $valueverify = 0;
    }
    $randomString = bin2hex(random_bytes(6));
    $initialProcessingValue = '0';
    $initialProcessingValueOne = 'none';
    $initialProcessingValueTow = 'none';
    $initialProcessingValueFour = '0';
    $initialRollStatus = '0';
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four)");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':showcard', $setting['showcard']);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->bindParam(':roll_status', $initialRollStatus);
    $stmt->bindParam(':processing_value', $initialProcessingValue);
    $stmt->bindParam(':processing_value_one', $initialProcessingValueOne);
    $stmt->bindParam(':processing_value_tow', $initialProcessingValueTow);
    $stmt->bindParam(':processing_value_four', $initialProcessingValueFour);
    $stmt->execute();
    clearSelectCache('user');
    $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    $isNewUser = !is_array($user);
}
if (!is_array($user)) {
    $user = array();
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'agent' => '',
        'username' => '',
        'limit_usertest' => '',
        'message_count' => '',
        'affiliates' => '',
        'last_message_time' => '',
        'cardpayment' => '',
        'roll_Status' => '',
        'number_username' => '',
        'number' => '',
        'register' => '',
        'codeInvitation' => '',
        'pricediscount' => '',
        'joinchannel' => '',
        'score' => "",
        'limitchangeloc' => ''
    );
} else {
    $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
}
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN", ['cache' => false]);
if (!is_array($admin_ids)) {
    $admin_ids = [];
}
$admin_ids_str = array_map('strval', $admin_ids);

if (!function_exists('rx_forced_miniapp_active')) {
    function rx_forced_miniapp_active($setting, $from_id, $admin_ids_str)
    {
        return is_array($setting)
            && (string)($setting['forced_miniapp_mode'] ?? '0') === '1'
            && !in_array((string)$from_id, $admin_ids_str, true);
    }
}
if (!function_exists('rx_send_forced_miniapp_redirect')) {
    function rx_send_forced_miniapp_redirect($from_id, $message_id, $user, $callback_query_id)
    {
        global $domainhosts;
        $text = '📱 برای استفاده از خدمات ما، مینی‌اپ را باز کنید.';
        $first = trim((string)($user['first_name'] ?? ''));
        $text = str_replace('{first_name}', $first !== '' ? $first : 'کاربر', $text);
        $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
        $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => '🚀 باز کردن مینی‌اپ', 'web_app' => ['url' => 'https://' . $host . '/app/']]]]], JSON_UNESCAPED_UNICODE) : null;
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 0,
            ]);
        }
        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }
}
if (rx_forced_miniapp_active($setting, $from_id, $admin_ids_str)) {
    rx_send_forced_miniapp_redirect($from_id, $message_id, $user, $callback_query_id);
    return;
}

if (
    isset($from_id)
    && intval($from_id) !== 0
    && is_array($setting)
    && (string)($setting['antispam_status'] ?? '0') === '1'
    && !in_array((string)$from_id, $admin_ids_str, true)
    && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)
) {
    try {
        $rxAsGateStmt = $pdo->prepare("SELECT antispam_muted_until FROM user WHERE id = :id LIMIT 1");
        $rxAsGateStmt->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsGateStmt->execute();
        $rxAsGateRow = $rxAsGateStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($rxAsGateRow)) {
            $rxAsGateMutedUntil = (int)($rxAsGateRow['antispam_muted_until'] ?? 0);
        } else {
            $rxAsGateMutedUntil = 0;
        }
    } catch (\Throwable $rxAsGateErr) {

        $rxAsGateMutedUntil = (int)($user['antispam_muted_until'] ?? 0);
    }
    if ($rxAsGateMutedUntil > 0 && time() < $rxAsGateMutedUntil) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 0,
                ]);
            } catch (\Throwable $rxAsAckErr) {  }
        }
        return;
    }
}
$datatextbotget = isset($GLOBALS['_rx_textbot_rows']) && is_array($GLOBALS['_rx_textbot_rows'])
    ? $GLOBALS['_rx_textbot_rows']
    : select("textbot", "*", null, null, "fetchAll");
$datatxtbot = array();
$topic_id = select("topicid", "*", null, null, "fetchAll");
$statusnote = false;
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'reportcron')
        $reportcron = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];
    if ($topic['report'] == 'receiptreport')
        $receiptreport = $topic['idreport'];

}
if ($setting['statusnamecustom'] == 'onnamecustom')
    $statusnote = true;
if ($setting['statusnoteforf'] == "0" && $user['agent'] == "f")
    $statusnote = false;
if (!function_exists('createForumTopicIfMissing')) {
    function createForumTopicIfMissing($currentId, $reportKey, $topicName, $channelId)
    {
        $numericId = intval($currentId);
        if ($numericId !== 0) {
            return;
        }

        $channelId = trim((string)$channelId);
        if ($channelId === '' || $channelId === '0') {
            return;
        }

        $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'faoxima_topic_' . md5($channelId . '|' . $reportKey) . '.lock';
        $markerExisted = is_file($marker);
        $markerHandle = @fopen($marker, 'c');
        if ($markerHandle !== false) {
            if (!@flock($markerHandle, LOCK_EX)) {
                @fclose($markerHandle);
                return;
            }
            clearstatcache(true, $marker);
            $lastAttempt = (int) @filemtime($marker);
            if ($markerExisted && $lastAttempt > 0 && (time() - $lastAttempt) < 3600) {
                @flock($markerHandle, LOCK_UN);
                @fclose($markerHandle);
                return;
            }
            @touch($marker);
            @flock($markerHandle, LOCK_UN);
            @fclose($markerHandle);
        }

        $response = telegram('createForumTopic', [
            'chat_id' => $channelId,
            'name' => $topicName
        ]);

        if (!is_array($response) || empty($response['ok'])) {
            $context = is_array($response) ? json_encode($response) : 'empty response';
            error_log("Failed to create forum topic {$reportKey}: {$context}");

            if (is_array($response) && isset($response['error_code']) && in_array($response['error_code'], [400, 403], true)) {
                update("topicid", "idreport", -1, "report", $reportKey);
            }

            return;
        }

        $threadId = $response['result']['message_thread_id'] ?? null;
        if ($threadId !== null) {
            update("topicid", "idreport", $threadId, "report", $reportKey);
        }
    }
}

createForumTopicIfMissing($porsantreport, 'porsantreport', $textbotlang['Admin']['affiliates']['titletopic'], $setting['Channel_Report']);
createForumTopicIfMissing($reportnight, 'reportnight', $textbotlang['Admin']['report']['reportnight'], $setting['Channel_Report']);
createForumTopicIfMissing($reportcron, 'reportcron', $textbotlang['Admin']['report']['reportcron'], $setting['Channel_Report']);
createForumTopicIfMissing($reportbackup, 'backupfile', "🤖 بکاپ ربات نماینده", $setting['Channel_Report']);
createForumTopicIfMissing($receiptreport, 'receiptreport', $textbotlang['Admin']['report']['receiptreport'], $setting['Channel_Report']);
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_roll' => '',
    'text_fq' => '',
    'text_dec_fq' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_channel' => '',
    'text_Tariff_list' => '',
    'text_dec_Tariff_list' => '',
    'text_affiliates' => '',
    'text_pishinvoice' => '',
    'text_account_info' => '',
    'text_service_detail' => '',
    'dyn_errors_verification_failed' => '',
    'dyn_errors_restart_process' => '',
    'dyn_errors_concurrency_insufficient_stock' => '',
    'dyn_errors_panel_connection_error' => '',
    'dyn_errors_button_disabled' => '',
    'dyn_errors_feature_unavailable' => '',
    'dyn_errors_feature_unavailable_short' => '',
    'dyn_errors_feature_currently_unavailable_nodot' => '',
    'dyn_errors_renewal_failed_restart' => '',
    'dyn_errors_renewal_support_error' => '',
    'dyn_errors_purchase_info_incomplete' => '',
    'dyn_errors_panel_not_found' => '',
    'dyn_errors_panel_unavailable_choose_other' => '',
    'dyn_errors_extra_volume_purchase_error' => '',
    'dyn_errors_location_change_limit_reached' => '',
    'dyn_errors_min_max_deposit_amount' => '',
    'dyn_errors_stock_depleted_no_charge' => '',
    'dyn_errors_stock_extend_success' => '',
    'dyn_errors_invalid_subscription_link' => '',
    'dyn_errors_service_found_count' => '',
    'dyn_errors_service_not_found' => '',
    'dyn_errors_view_account_unavailable' => '',
    'dyn_errors_qr_unavailable' => '',
    'dyn_errors_service_deleted' => '',
    'dyn_errors_config_read_error' => '',
    'dyn_errors_panel_unavailable_dot' => '',
    'dyn_errors_config_read_from_panel_error' => '',
    'dyn_errors_usage_stats_failed' => '',
    'dyn_errors_server_list_failed' => '',
    'dyn_errors_not_connected_change_status' => '',
    'dyn_errors_extend_unavailable_panel' => '',
    'dyn_errors_not_connected_extend' => '',
    'dyn_errors_extend_current_plan_unavailable' => '',
    'dyn_errors_extend_restart_process' => '',
    'dyn_errors_exclusive_discount_conflict' => '',
    'dyn_errors_discount_expired' => '',
    'dyn_errors_discount_applied' => '',
    'dyn_errors_link_change_service_disabled' => '',
    'dyn_errors_extra_volume_unavailable_panel' => '',
    'dyn_errors_extra_volume_restart_process' => '',
    'dyn_errors_purchase_failed_restart' => '',
    'dyn_errors_location_change_unavailable' => '',
    'dyn_errors_generic_restart_process' => '',
    'dyn_errors_restart_buy_process_short' => '',
    'dyn_errors_transfer_panel_unavailable' => '',
    'dyn_errors_transfer_unused_config_only' => '',
    'dyn_errors_removal_request_thanks' => '',
    'dyn_errors_extra_time_unavailable_panel' => '',
    'dyn_errors_extra_time_restart_process' => '',
    'dyn_errors_service_removed' => '',
    'dyn_errors_service_removal_unavailable' => '',
    'dyn_errors_removal_reason_prompt' => '',
    'dyn_errors_test_service_unavailable' => '',
    'dyn_errors_min_bulk_purchase_balance' => '',
    'dyn_errors_section_disabled' => '',
    'dyn_errors_discount_code_not_applicable' => '',
    'dyn_errors_stock_low_purchase_blocked' => '',
    'dyn_referral_welcome_alert' => '',
    'dyn_referral_inviter_reward_alert' => '',
    'dyn_referral_commission_credited' => '',
    'dyn_rewards_points_earned' => '',
    'dyn_rewards_cashback_gift' => '',
    'dyn_tickets_select_category' => '',
    'dyn_tickets_select_department' => '',
    'dyn_tickets_ask_subject' => '',
    'dyn_tickets_operation_canceled' => '',
    'dyn_tickets_invalid_subject_text' => '',
    'dyn_tickets_ask_media_new' => '',
    'dyn_tickets_ask_media_reply' => '',
    'dyn_tickets_media_yes' => '',
    'dyn_tickets_media_no' => '',
    'dyn_tickets_cancel' => '',
    'dyn_tickets_ask_media_count' => '',
    'dyn_tickets_ask_media_upload' => '',
    'dyn_tickets_invalid_media_choice' => '',
    'dyn_tickets_invalid_media_type' => '',
    'dyn_tickets_media_received' => '',
    'dyn_tickets_ask_body_text' => '',
    'dyn_tickets_ask_body_text_short' => '',
    'dyn_tickets_not_available' => '',
    'dyn_tickets_reply_recorded' => '',
    'dyn_tickets_invalid_body_text' => '',
    'dyn_tickets_created' => '',
    'dyn_tickets_closed_locked' => '',
    'dyn_tickets_no_media' => '',
    'dyn_tickets_closed_confirm' => '',
    'dyn_wallet_select_amount' => '',
    'dyn_wallet_recheck_canceled' => '',
    'dyn_wallet_amount_not_numeric' => '',
    'dyn_wallet_rate_unavailable' => '',
    'dyn_wallet_calculated_amount_invalid' => '',
    'dyn_wallet_hash_not_found' => '',
    'dyn_wallet_ask_receipt_photo' => '',
    'dyn_wallet_incomplete_info' => '',
    'dyn_wallet_hash_already_used' => '',
    'dyn_wallet_internal_error_retry' => '',
    'dyn_wallet_invalid_charge_amount' => '',
    'dyn_wallet_discount_not_applicable_zero' => '',
    'dyn_wallet_no_active_card' => '',
    'dyn_wallet_select_verified_card' => '',
    'dyn_wallet_card_auth_active_notice' => '',
    'dyn_wallet_queue_too_busy' => '',
    'dyn_wallet_hashchecker_not_loaded' => '',
    'dyn_wallet_no_wallet_address_configured' => '',
    'dyn_wallet_charge_amount_undetectable' => '',
    'dyn_wallet_invoice_not_found' => '',
    'dyn_wallet_invoice_not_pending' => '',
    'dyn_affiliates_extra_section_disabled' => '',
    'dyn_affiliates_extra_not_affiliate_of_anyone' => '',
    'dyn_affiliates_extra_gift_already_received' => '',
    'dyn_affiliates_extra_gift_credited_inviter' => '',
    'dyn_affiliates_extra_gift_activated' => '',
    'dyn_wheel_button_disabled_you' => '',
    'dyn_wheel_no_purchase_users_only' => '',
    'dyn_wheel_price_unavailable' => '',
    'dyn_wallet_prompt_amount_prompt' => '',
    'dyn_wallet_prompt_amount_out_of_range' => '',
    'dyn_wallet_prompt_debt_must_pay_first' => '',
    'dyn_wallet_prompt_has_discount_yes' => '',
    'dyn_wallet_prompt_has_discount_no' => '',
    'dyn_wallet_prompt_ask_has_discount' => '',
    'dyn_wallet_prompt_auth_success' => '',
    'dyn_group_setup_topic_not_active' => '',
    'dyn_group_setup_insufficient_access' => '',
    'dyn_group_setup_chat_id_received' => '',
    'accountwallet' => '',
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'crontest' => '',
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_cart' => '',
    'text_request_agent_dec' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'tonpay' => '',
    'cubepay' => '',
    'blupal' => '',
    'variza' => '',
    'abangateway' => '',
    'atlaspay' => '',
    'zarinpal' => '',
    'textsnowpayment' => '',
    'textpaymentnotverify' => "",
    'text_star_telegram' => '',
    'text_extend' => '',
    'text_wgdashboard' => '',
    'text_Discount' => '',
    'miniapp_suggest_1' => '',
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
$time_Start = jdate('Y/m/d');
$date_start = jdate('H:i:s', time());
$time_string = "📆 $date_start → ⏰ $time_Start";
$varable_start = [
    '{username}' => $username,
    '{first_name}' => $first_name,
    '{last_name}' => $last_name,
    '{time}' => $time_string,
    '{version}' => $version
];
$datatextbot['text_start'] = strtr($datatextbot['text_start'], $varable_start);
if (function_exists('rx_resolveInlineButtonText') && isset($datain) && is_string($datain) && strpos($datain, 'rxb_') === 0) {
    $rxResolvedInlineText = rx_resolveInlineButtonText($datain);
    if ($rxResolvedInlineText !== null && $rxResolvedInlineText !== '') {
        $datain = $rxResolvedInlineText;
        $text = $rxResolvedInlineText;
    }
}

if (
    function_exists('rx_restorePremiumReplyText')
    && isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] !== 'oninline'
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = rx_restorePremiumReplyText($text);
}

if (
    function_exists('stripReplyStyleEmoji')
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = stripReplyStyleEmoji($text);
}

if (
    is_string($datain) && $datain !== ''
    && function_exists('rx_resolveAdminPanelCallback')
    && (strpos($datain, 'apn:') === 0 || strpos($datain, 'apnh:') === 0)
) {
    $rxResolvedText = rx_resolveAdminPanelCallback($datain);
    if ($rxResolvedText !== null && $rxResolvedText !== '') {

        if (!empty($callback_query_id) && function_exists('telegram')) {
            @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        }
        $text  = $rxResolvedText;
        $datain = '';
    }
}

if (class_exists('logNavigation')) {
    logNavigation::button($from_id);
}
if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
if ($user['register'] == "none") {
    update("user", "register", time(), "id", $from_id);
}
if (!in_array($user['agent'], ["n", "n2", "f"]))
    update("user", "agent", "f", "id", $from_id);

if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
    $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}

$timebot = time();
$rxAntispamStatus = (string)($setting['antispam_status'] ?? '0');
if ($rxAntispamStatus === '1' && !in_array((string)$from_id, $admin_ids_str, true) && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)) {

    $rxAsMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCount < 1)    { $rxAsMsgCount = 1; }
    if ($rxAsMsgCount > 1000) { $rxAsMsgCount = 1000; }
    $rxAsSeconds = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSeconds < 1)    { $rxAsSeconds = 1; }
    if ($rxAsSeconds > 3600) { $rxAsSeconds = 3600; }
    $rxAsMuteSeconds = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSeconds < 1)     { $rxAsMuteSeconds = 1; }
    if ($rxAsMuteSeconds > 86400) { $rxAsMuteSeconds = 86400; }

    $rxAsShouldDrop = false;
    $rxAsHandledByRedis = false;

    if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
        try {
            $rxAsLuaScript = "local key = KEYS[1] "
                . "local now = tonumber(ARGV[1]) "
                . "local win_seconds = tonumber(ARGV[2]) "
                . "local msg_count = tonumber(ARGV[3]) "
                . "local mute_seconds = tonumber(ARGV[4]) "
                . "local win_start = tonumber(redis.call('HGET', key, 'window_start') or '0') "
                . "local win_count = tonumber(redis.call('HGET', key, 'window_count') or '0') "
                . "local muted_until = tonumber(redis.call('HGET', key, 'muted_until') or '0') "
                . "local should_drop = 0 "
                . "local sync_needed = 0 "
                . "if muted_until > 0 and now < muted_until then "
                . "  should_drop = 1 "
                . "elseif muted_until > 0 and now >= muted_until then "
                . "  muted_until = 0 win_start = now win_count = 1 sync_needed = 1 "
                . "elseif win_start == 0 or (now - win_start) >= win_seconds then "
                . "  win_start = now win_count = 1 sync_needed = 1 "
                . "else "
                . "  win_count = win_count + 1 "
                . "  if win_count > msg_count then "
                . "    muted_until = now + mute_seconds should_drop = 1 sync_needed = 1 "
                . "  end "
                . "end "
                . "redis.call('HSET', key, 'window_start', win_start, 'window_count', win_count, 'muted_until', muted_until) "
                . "local ttl = win_seconds "
                . "if mute_seconds > ttl then ttl = mute_seconds end "
                . "redis.call('EXPIRE', key, ttl + 5) "
                . "return {win_start, win_count, muted_until, should_drop, sync_needed}";

            $rxAsRedisKey = 'faoxima:antispam:' . $from_id;
            $rxAsRedisClient = getRedisConnection();
            if ($rxAsRedisClient instanceof \Redis) {
                $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, [$rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds], 1);
            } else {
                $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, 1, $rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds);
            }

            if (is_array($rxAsLuaResult) && count($rxAsLuaResult) === 5) {
                $rxAsNewWinStart   = (int) $rxAsLuaResult[0];
                $rxAsNewWinCount   = (int) $rxAsLuaResult[1];
                $rxAsNewMutedUntil = (int) $rxAsLuaResult[2];
                $rxAsShouldDrop    = ((int) $rxAsLuaResult[3]) === 1;
                $rxAsSyncNeeded    = ((int) $rxAsLuaResult[4]) === 1;

                $user['antispam_window_start'] = (string) $rxAsNewWinStart;
                $user['antispam_window_count'] = (string) $rxAsNewWinCount;
                $user['antispam_muted_until']  = (string) $rxAsNewMutedUntil;

                if ($rxAsSyncNeeded) {
                    try {
                        $rxAsSyncStmt = $pdo->prepare(
                            "UPDATE user SET "
                            . "antispam_window_start = :ws, "
                            . "antispam_window_count = :wc, "
                            . "antispam_muted_until  = :mu "
                            . "WHERE id = :id"
                        );
                        $rxAsSyncStmt->bindValue(':ws', (string) $rxAsNewWinStart,   PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':wc', (string) $rxAsNewWinCount,   PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':mu', (string) $rxAsNewMutedUntil, PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':id', (string) $from_id,          PDO::PARAM_STR);
                        $rxAsSyncStmt->execute();
                        if (function_exists('clearSelectCacheRow')) {
                            clearSelectCacheRow('user', 'id', (string) $from_id);
                        }
                    } catch (\Throwable $rxAsSyncErr) {
                        error_log('antispam Redis->MySQL sync failed: ' . $rxAsSyncErr->getMessage());
                    }
                }

                $rxAsHandledByRedis = true;
            }
        } catch (\Throwable $rxAsRedisErr) {
            $rxAsHandledByRedis = false;
        }
    }

    if (!$rxAsHandledByRedis) {
    $rxAsAtomicOk   = false;
    $rxAsTxOpened   = false;
    try {
        $pdo->beginTransaction();
        $rxAsTxOpened = true;

        $rxAsSel = $pdo->prepare(
            "SELECT antispam_window_start, antispam_window_count, antispam_muted_until "
            . "FROM user WHERE id = :id FOR UPDATE"
        );
        $rxAsSel->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsSel->execute();
        $rxAsRow = $rxAsSel->fetch(PDO::FETCH_ASSOC);

        if (!is_array($rxAsRow)) {

            $pdo->commit();
            $rxAsTxOpened = false;
            $rxAsAtomicOk = true;
        } else {
            $rxAsWinStart   = (int)($rxAsRow['antispam_window_start'] ?? 0);
            $rxAsWinCount   = (int)($rxAsRow['antispam_window_count'] ?? 0);
            $rxAsMutedUntil = (int)($rxAsRow['antispam_muted_until']  ?? 0);

            $rxAsNewWinStart   = $rxAsWinStart;
            $rxAsNewWinCount   = $rxAsWinCount;
            $rxAsNewMutedUntil = $rxAsMutedUntil;

            if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {

                $rxAsShouldDrop = true;
            } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {

                $rxAsNewMutedUntil = 0;
                $rxAsNewWinStart   = $timebot;
                $rxAsNewWinCount   = 1;
            } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {

                $rxAsNewWinStart = $timebot;
                $rxAsNewWinCount = 1;
            } else {

                $rxAsNewWinCount = $rxAsWinCount + 1;
                if ($rxAsNewWinCount > $rxAsMsgCount) {

                    $rxAsNewMutedUntil = $timebot + $rxAsMuteSeconds;
                    $rxAsShouldDrop    = true;
                }
            }

            $rxAsUpd = $pdo->prepare(
                "UPDATE user SET "
                . "antispam_window_start = :ws, "
                . "antispam_window_count = :wc, "
                . "antispam_muted_until  = :mu "
                . "WHERE id = :id"
            );
            $rxAsUpd->bindValue(':ws', (string)$rxAsNewWinStart,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':wc', (string)$rxAsNewWinCount,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':mu', (string)$rxAsNewMutedUntil, PDO::PARAM_STR);
            $rxAsUpd->bindValue(':id', (string)$from_id,           PDO::PARAM_STR);
            $rxAsUpd->execute();

            $pdo->commit();
            $rxAsTxOpened = false;

            $user['antispam_window_start'] = (string)$rxAsNewWinStart;
            $user['antispam_window_count'] = (string)$rxAsNewWinCount;
            $user['antispam_muted_until']  = (string)$rxAsNewMutedUntil;

            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('user', 'id', (string)$from_id);
            } elseif (function_exists('clearSelectCache')) {
                clearSelectCache('user');
            }

            $rxAsAtomicOk = true;
        }
    } catch (\Throwable $rxAsTxErr) {

        if ($rxAsTxOpened) {
            try { $pdo->rollBack(); } catch (\Throwable $rxAsRbErr) {  }
        }
        $rxAsAtomicOk = false;
    }

    if (!$rxAsAtomicOk) {
        $rxAsWinStart   = (int)($user['antispam_window_start']  ?? 0);
        $rxAsWinCount   = (int)($user['antispam_window_count']  ?? 0);
        $rxAsMutedUntil = (int)($user['antispam_muted_until']   ?? 0);

        if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {
            $rxAsShouldDrop = true;
        } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {
            update("user", "antispam_muted_until",  "0",                "id", $from_id);
            update("user", "antispam_window_start", (string)$timebot,   "id", $from_id);
            update("user", "antispam_window_count", "1",                "id", $from_id);
            $user['antispam_muted_until']  = "0";
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {
            update("user", "antispam_window_start", (string)$timebot, "id", $from_id);
            update("user", "antispam_window_count", "1",              "id", $from_id);
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } else {
            $rxAsNewCount = $rxAsWinCount + 1;
            update("user", "antispam_window_count", (string)$rxAsNewCount, "id", $from_id);
            $user['antispam_window_count'] = (string)$rxAsNewCount;
            if ($rxAsNewCount > $rxAsMsgCount) {
                $rxAsMuteEnd = $timebot + $rxAsMuteSeconds;
                update("user", "antispam_muted_until", (string)$rxAsMuteEnd, "id", $from_id);
                $user['antispam_muted_until'] = (string)$rxAsMuteEnd;
                $rxAsShouldDrop = true;
            }
        }
    }
    }

    if ($rxAsShouldDrop) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 0,
                ]);
            } catch (\Throwable $rxAsAckErr2) {  }
        }
        return;
    }
} else {

    $TimeLastMessage = $timebot - intval($user['last_message_time']);
    if (floor($TimeLastMessage / 60) >= 1) {
        update("user", "last_message_time", $timebot, "id", $from_id);
        update("user", "message_count", "1", "id", $from_id);
    } else {
        if (!in_array($from_id, $admin_ids)) {
            $addmessage = intval($user['message_count']) + 1;
            update("user", "message_count", $addmessage, "id", $from_id);
            $spamThreshold = 35;
            if ($addmessage >= $spamThreshold) {
                $User_Status = "block";
                $textblok = sprintf($textbotlang['users']['spam']['spamedreport'], $from_id);
                $Response = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                        ],
                    ]
                ]);
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $otherservice,
                        'text' => $textblok,
                        'parse_mode' => "HTML",
                        'reply_markup' => $Response
                    ]);
                }
                update("user", "User_Status", $User_Status, "id", $from_id);
                update("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
                sendmessage($from_id, $textbotlang['users']['spam']['spamedmessage'], null, 'html');
                return;
            }
        }
    }
}

if (strpos($text, "/start ") !== false && $user['step'] != "gettextSystemMessage") {
    $affiliatesid = explode(" ", $text)[1];
    if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
        isValidInvitationCode($setting, $from_id, $user['verify']);
        if ($setting['affiliatesstatus'] == "offaffiliates") {
            sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
            return;
        }
        if (is_numeric($affiliatesid) && userExists($affiliatesid)) {
            if ($affiliatesid == $from_id) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                return;
            }
            $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
            if (intval($user['affiliates']) != 0) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            }
            update("user", "affiliates", $affiliatesid, "id", $from_id);
            $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
            $rxWelcomeAlertTpl = $datatextbot['dyn_referral_welcome_alert'] ?? "<b>🎉 خوش آمدی!</b>\n\nشما با دعوت <b>@%s</b> وارد ربات شدی و به عنوان زیرمجموعه ثبت شدی ✅\n\nبرای دریافت هدیه عضویت:\n🔘 به منوی <b>زیرمجموعه‌گیری</b> برو\n🔘 دکمه <b>🎁 هدیه عضویت</b> را بزن\n\nبا این کار، هم خودت و هم معرفت هدیه می‌گیرید! 💰";
            sendmessage($from_id, sprintf($rxWelcomeAlertTpl, $useraffiliates['username']), $keyboard, 'html');
            $rxInviterRewardTpl = $datatextbot['dyn_referral_inviter_reward_alert'] ?? "<b>🎉 یک زیرمجموعه جدید!</b>\n\nکاربر <b>@%s</b> با لینک دعوت شما وارد ربات شد ✅\n\nبا خریدهای این کاربر، <b>سهم هدیه شما</b> به حسابت واریز می‌شه 🔥";
            sendmessage($affiliatesid, sprintf($rxInviterRewardTpl, $username), $keyboard, 'html');
            $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
            update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
            $dateacc = date('Y/m/d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO reagent_report (user_id, get_gift, time, reagent)
                                   VALUES (:user_id, :get_gift, :time, :reagent)
                                   ON DUPLICATE KEY UPDATE reagent = VALUES(reagent), get_gift = VALUES(get_gift), time = VALUES(time)");
            $stmt->execute([
                ':user_id' => $from_id,
                ':get_gift' => 0,
                ':time' => $dateacc,
                ':reagent' => $affiliatesid,
            ]);
            if (function_exists('clearSelectCache')) {
                clearSelectCache('reagent_report');
            }
        } else {
            rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
            update("user", "Processing_value", "0", "id", $from_id);
            update("user", "Processing_value_one", "0", "id", $from_id);
            update("user", "Processing_value_tow", "0", "id", $from_id);
            update("user", "Processing_value_four", "0", "id", $from_id);
            step('home', $from_id);
        }
    } else {
        $text = $affiliatesid;
    }
}
if (intval($user['verify']) == 0 && !in_array($from_id, $admin_ids) && $setting['verifystart'] == "onverify" && !rx_auth_skip_user($user)) {
    $textverify = "⚠️ حساب شما احراز هویت نشده است پیام  شما  به ادمین ارسال شده
    در صورت پیگیری  سریع تر می توانید به آیدی زیر پیام دهید
    @{$setting['id_support']}";
    sendmessage($from_id, $textverify, null, 'html');
    return;
}
;

if ($setting['roll_Status'] == "rolleon" && $user['roll_Status'] == 0 && ($text != "✅ قوانین را می پذیرم" and $datain != "acceptrule") && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_roll'], $confrimrolls, 'html');
    return;
}
if ($text == "✅ قوانین را می پذیرم" or $datain == "acceptrule") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Rules'], $keyboard, 'html');
    $confrim = true;
    update("user", "roll_Status", $confrim, "id", $from_id);
}

if ($setting['Bot_Status'] == "botstatusoff" && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_bot_off'], null, 'html');
    return;
}

$isStartRequest = ($text == "/start" || $datain == "start" || $text == "start" || (is_string($text) && strpos($text, "/start ") === 0));
$isConfirmChannel = ($datain == "confirmchannel");
$shouldCheckChannel = true;

if ($shouldCheckChannel && !in_array($from_id, $admin_ids)) {
    $channels_id = select("channels", "link", null, null, "FETCH_COLUMN", ['cache' => false]);
    if (!empty($channels_id) && is_array($channels_id)) {
        $channels = null;
        $rxChannelCacheApcu = function_exists('apcu_fetch') && function_exists('apcu_store') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
        $rxChannelCacheKey = 'faoxima:joinchannel:' . $from_id . ':' . md5(implode(',', $channels_id));
        if (!$isConfirmChannel && !$isStartRequest && $rxChannelCacheApcu) {
            $rxChannelCacheHit = false;
            $rxChannelCached = apcu_fetch($rxChannelCacheKey, $rxChannelCacheHit);
            if ($rxChannelCacheHit && is_array($rxChannelCached)) {
                $channels = $rxChannelCached;
            }
        }
        if ($channels === null) {
            $channels = channel($channels_id);
            if ($rxChannelCacheApcu) {
                @apcu_store($rxChannelCacheKey, $channels, 30);
            }
        }
        if ($isConfirmChannel) {
            if (count($channels) == 0) {
                update("user", "joinchannel", "active", "id", $from_id);
                $user['joinchannel'] = "active";
                deletemessage($from_id, $message_id);
                rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['channel']['confirmed'],
                    'show_alert' => false,
                    'cache_time' => 5,
                ]);
                $partsaffiliates = explode("_", (string)($user['Processing_value_four'] ?? ''));
                if ($partsaffiliates[0] == "affiliates") {
                    $affiliatesid = $partsaffiliates[1] ?? '';
                    if (!userExists($affiliatesid) || $affiliatesid == $from_id || intval($user['affiliates']) != 0) {
                        update("user", "Processing_value_four", "none", "id", $from_id);
                        return;
                    }
                    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                    $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
                    $stmtGiftGuard = $pdo->prepare("UPDATE user SET affiliates = :affiliatesid WHERE id = :from_id AND (affiliates IS NULL OR affiliates = 0 OR affiliates = '')");
                    $stmtGiftGuard->execute([':affiliatesid' => $affiliatesid, ':from_id' => $from_id]);
                    if (function_exists('clearSelectCache')) {
                        clearSelectCache('user');
                    }
                    if ($stmtGiftGuard->rowCount() > 0) {
                        if ($marzbanDiscountaffiliates['Discount'] == "onDiscountaffiliates") {
                            $Balance_add_user = $useraffiliates['Balance'] + $marzbanDiscountaffiliates['price_Discount'];
                            update("user", "Balance", $Balance_add_user, "id", $affiliatesid);
                            $addbalancediscount = number_format($marzbanDiscountaffiliates['price_Discount'], 0);
                            $rxCommissionCreditedTpl = $datatextbot['dyn_referral_commission_credited'] ?? "🎁 مبلغ %s به موجودی شما از طرف زیر مجموعه با شناسه کاربری %s اضافه گردید.";
                            sendmessage($affiliatesid, sprintf($rxCommissionCreditedTpl, $addbalancediscount, $from_id), null, 'html');
                        }
                        $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
                        update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
                    }
                    update("user", "Processing_value_four", "none", "id", $from_id);
                }
                return;
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select", ['cache' => false]);
                if (empty($channelremark['remark']) || empty($channelremark['linkjoin'])) {
                    continue;
                }
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            Editmessagetext($from_id, $message_id, $datatextbot['text_channel'], $keyboardchannel);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }

        if (count($channels) != 0) {
            if ($user['joinchannel'] == "active") {
                update("user", "joinchannel", "0", "id", $from_id);
                $user['joinchannel'] = "0";
            }
            if (is_string($text) && strpos($text, "/start ") === 0) {
                $startParts = explode(" ", $text, 2);
                if (!empty($startParts[1])) {
                    update("user", "Processing_value_four", "affiliates_" . trim($startParts[1]), "id", $from_id);
                }
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select", ['cache' => false]);
                if (empty($channelremark['remark']) || empty($channelremark['linkjoin'])) {
                    continue;
                }
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            sendmessage($from_id, $datatextbot['text_channel'], $keyboardchannel, 'html');
            return;
        } else {
            if ($user['joinchannel'] != "active") {
                update("user", "joinchannel", "active", "id", $from_id);
                $user['joinchannel'] = "active";
            }
        }
    }
}
if ($text == "/start" || $datain == "start" || $text == "start") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if (function_exists('removeReplyKeyboardOnStartIfNeeded')) {
        removeReplyKeyboardOnStartIfNeeded($from_id);
    }
    rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, "html");
    return;
}
if ((!isset($connect) || !($connect instanceof mysqli)) && function_exists('getMysqliConnection')) {
    $connect = getMysqliConnection();
}
if ($text == "version") {
    sendmessage($from_id, $version, null, 'html');
} elseif ($text == $textbotlang['users']['backbtn'] || $datain == "backuser" || $datain == "admin_back") {
    if ($datain == "backuser" || $datain == "admin_back")
        deletemessage($from_id, $message_id);
    $message_id = sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    $_bk_id  = (string) $from_id;
    $_bk_del = $connect->prepare("DELETE FROM Payment_report WHERE id_user = ? AND payment_Status IN ('Unpaid','pending') AND Payment_Method = 'cart to cart' AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')");
    $_bk_del->bind_param("s", $_bk_id);
    $_bk_del->execute();
    $_bk_del->close();
    return;
} elseif ($user['step'] == 'get_number' && in_array($from_id, $admin_ids)) {
} elseif ($user['step'] == 'get_number' && !in_array($from_id, $admin_ids)) {
    if (empty($user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['false'], $request_contact, 'html');
        return;
    }
    if ($contact_id != $from_id) {
        sendmessage($from_id, $textbotlang['users']['number']['Warning'], $request_contact, 'html');
        return;
    }
    if ($setting['iran_number'] == "onAuthenticationiran" && !preg_match("/989[0-9]{9}$/", $user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['erroriran'], $request_contact, 'html');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['number']['active'], json_encode(['inline_keyboard' => [], 'remove_keyboard' => true]), 'html');
    rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
    update("user", "number", $user_phone, "id", $from_id);
    if ($setting['verifystart'] == "onverify") {
        update("user", "verify", "1", "id", $from_id);
    }
    step('home', $from_id);
} elseif (($text !== '' && $text == $datatextbot['text_Purchased_services']) || $datain == "backorder" || $text == "/services") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_null($invoices) && $setting['NotUser'] == "offnotuser") {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
        return;
    }

    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        ['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json, 'html');
    }


} elseif ($datain == 'next_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == 'previous_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $previous_page, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . $row['username'] . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == "notusernameme") {
    sendmessage($from_id, $textbotlang['users']['stateus']['SendUsername'], $backuser, 'html');
    step('getusernameinfo', $from_id);
} elseif ($user['step'] == "getusernameinfo") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (empty($text))
        return;
    $usernameconfig = "";
    if (strlen($text) > 32) {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = outputlunksub($text);
        if (!isset($date)) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = json_decode($date, true);
        if (!isset($date['username'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $usernameconfig = $date['username'];
    } else {
        $text = str_replace('_', '-', $text);
        if (!preg_match('/^[A-Za-z0-9-]{3,32}$/', $text)) {
            sendmessage($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
            return;
        }
        $usernameconfig = $text;
    }
    update("user", "Processing_value", $usernameconfig, "id", $from_id);
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'html');
    step('getdata', $from_id);
} elseif (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1]);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        if ($DataUserOut['msg'] == "User not found") {
            sendmessage($from_id, $textbotlang['users']['stateus']['notUsernameget'], $keyboard, 'html');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }

    $status = $DataUserOut['status'];
    $bExp = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp > 0 && $bExp <= time()) {
        $status = 'expired';
    } elseif ($bDl > 0.0 && $bUt >= $bDl) {
        $status = 'limited';
    }
    $status_map = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $keyboardinfo = [
        'inline_keyboard' => [
            [
                ['text' => $DataUserOut['username'], 'callback_data' => "username"],
                ['text' => $textbotlang['users']['stateus']['username'], 'callback_data' => 'username'],
            ],
            [
                ['text' => $status_var, 'callback_data' => 'status_var', '_rxsk' => 'Status'],
                ['text' => $textbotlang['users']['stateus']['stateus'], 'callback_data' => 'status_var', '_rxsk' => 'Status'],
            ],
            [
                ['text' => $expirationDate, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['stateus']['expirationDate'], 'callback_data' => 'expirationDate'],
            ],
            [],
            [
                ['text' => $day, 'callback_data' => 'روز', '_rxsk' => 'daysleft'],
                ['text' => $textbotlang['users']['stateus']['daysleft'], 'callback_data' => 'day', '_rxsk' => 'daysleft'],
            ],
            [
                ['text' => $LastTraffic, 'callback_data' => 'LastTraffic'],
                ['text' => $textbotlang['users']['stateus']['LastTraffic'], 'callback_data' => 'LastTraffic'],
            ],
            [
                ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic'],
                ['text' => $textbotlang['users']['stateus']['usedTrafficGb'], 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic'],
            ],
            [
                ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume'],
                ['text' => $textbotlang['users']['stateus']['RemainingVolume'], 'callback_data' => 'RemainingVolume'],
            ]
        ]
    ];
    $marzbanstatusextra = panel_feature_enabled($marzban_list_get, 'extravolume') ? "onextra" : "offextra";
    if ($marzbanstatusextra == "onextra") {
        $keyboardinfo['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]],
            ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volumes_' . $DataUserOut['username'] . '_' . $dataget[1]],
        ];
    } else {
        $keyboardinfo['inline_keyboard'][] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]]];
    }
    $keyboardinfo = json_encode($keyboardinfo);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['info'], $keyboardinfo);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
    step('home', $from_id);
} elseif (function_exists('nmMaybeHandleStockCallback') && nmMaybeHandleStockCallback($datain ?? '', $from_id, $message_id ?? null, $callback_query_id ?? null)) {
    return;
} elseif (preg_match('/^quickview_(\w+)/', $datain, $dataget) || preg_match('/^product_(\w+)/', $datain, $dataget) || preg_match('/updateproduct_(\w+)/', $datain, $dataget) || $user['step'] == "getuseragnetservice" || $datain == "productcheckdata") {

    if (is_string($datain) && strpos($datain, 'quickview_') === 0) {
        $id_invoice_qv = $dataget[1];
        $stmtQv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :i AND id_user = :u LIMIT 1");
        $stmtQv->execute([':i' => $id_invoice_qv, ':u' => $from_id]);
        $nameloc_qv = $stmtQv->fetch(PDO::FETCH_ASSOC);
        if (!is_array($nameloc_qv)) {
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ سرویس پیدا نشد', 'show_alert' => true]);
            }
            return;
        }
        if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, null, $nameloc_qv)) {
            if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            step('home', $from_id);
            return;
        }
        if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc_qv, $from_id, null)) {
            if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            step('home', $from_id);
            return;
        }
        $panel_qv = select("marzban_panel", "*", "name_panel", $nameloc_qv['Service_location'], "select");
        $cardPath_qv = null;
        if (is_array($panel_qv) && function_exists('nm_renderInfoCardForInvoice')) {
            $cardPath_qv = nm_renderInfoCardForInvoice($panel_qv, $nameloc_qv['username'], $nameloc_qv['id_invoice'], $from_id);
        }
        if ($cardPath_qv !== null && is_file($cardPath_qv)) {
            $note_qv = isset($nameloc_qv['note']) && $nameloc_qv['note'] !== '' ? ' | ' . $nameloc_qv['note'] : '';
            $caption_qv = '✨ <b>' . htmlspecialchars((string)$nameloc_qv['username'], ENT_QUOTES, 'UTF-8') . '</b>'
                        . htmlspecialchars($note_qv, ENT_QUOTES, 'UTF-8');
            $__qvRow = [['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $nameloc_qv['id_invoice']]];
            if (!(function_exists('isQrDisabled') && isQrDisabled())) {
                $__qvRow[] = ['text' => '📷 دریافت QR Code', 'callback_data' => 'infocard_qr_' . $nameloc_qv['id_invoice']];
            }
            $kb_qv = json_encode([
                'inline_keyboard' => [
                    $__qvRow,
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'] ?? '🔙 بازگشت', 'callback_data' => 'backorder'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo'   => new CURLFile($cardPath_qv),
                'caption' => $caption_qv,
                'parse_mode' => 'HTML',
                'reply_markup' => $kb_qv,
            ]);
            @unlink($cardPath_qv);
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            }
            step('home', $from_id);
            return;
        }

        $datain = "product_" . $id_invoice_qv;
        $dataget = [$datain, $id_invoice_qv];
    }

    if ($user['step'] == "getuseragnetservice") {
        $username = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND id_user = :id_user AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Service_time', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Volume', $username, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $username, PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain == "productcheckdata") {
        $username = $user['Processing_value'];
        $sql = "SELECT * FROM invoice WHERE username = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain[0] == "u") {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "♻️ اطلاعات بروز شد",
            'show_alert' => false,
            'cache_time' => 0,
        ));
    } else {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    }
    if ($user['step'] == "getuseragnetservice" && $stmt->rowCount() > 1) {
        $countservice = $stmt->rowCount();
        $pages = 1;
        update("user", "pagenumber", $pages, "id", $from_id);
        $page = 1;
        $items_per_page = 20;
        $start_index = ($page - 1) * $items_per_page;
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        if ($setting['statusnamecustom'] == 'onnamecustom') {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = "";
                if ($row != null)
                    $data = " | {$row['note']}";
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . $row['username'] . $data . rxServiceListStatusSuffix($row) . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        } else {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . $row['username'] . rxServiceListStatusSuffix($row) . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        }
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        $backuser = [
            rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
        ];
        if ($setting['NotUser'] == "onnotuser") {
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
        }
        $keyboardlists['inline_keyboard'][] = $backuser;
        $keyboard_json = json_encode($keyboardlists);
        $rxSvcFoundTpl = $datatextbot['dyn_errors_service_found_count'] ?? "🛍 %s عدد سرویس یافت برای مشاهده و مدیریت سرویس روی یکی از سرویس ها کلیک کنید";
        sendmessage($from_id, sprintf($rxSvcFoundTpl, $countservice), $keyboard_json, 'html');
        step("home", $from_id);
        return;
    }
    $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($nameloc)) {
        sendmessage($from_id, $datatextbot['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $username = $nameloc['id_invoice'];
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_view_account_unavailable'] ?? "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $marzban = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($marzban) && isset($marzban['name_panel']) && $marzban['name_panel'] != null) {
        update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (is_array($DataUserOut) && isset($DataUserOut['status']) && $DataUserOut['status'] !== 'Unsuccessful') {
        try {
            update("invoice", "user_info", json_encode($DataUserOut, JSON_UNESCAPED_UNICODE), "id_invoice", $nameloc['id_invoice']);
        } catch (Throwable $e) {
        }
        $bSt = strtolower((string)$DataUserOut['status']);
        if ($bSt === 'expired' && $nameloc['Status'] !== 'end_of_time') {
            update("invoice", "Status", "end_of_time", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'end_of_time';
        } elseif ($bSt === 'limited' && $nameloc['Status'] !== 'end_of_volume') {
            update("invoice", "Status", "end_of_volume", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'end_of_volume';
        } elseif ($bSt === 'on_hold' && $nameloc['Status'] !== 'send_on_hold') {
            update("invoice", "Status", "send_on_hold", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'send_on_hold';
        } elseif ($bSt === 'disabled' && !in_array($nameloc['Status'], ['disabled', 'disablebyadmin'], true)) {
            update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'disabled';
        } elseif ($bSt === 'active' && !in_array($nameloc['Status'], ['active', 'sendedwarn'], true)) {
            update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'active';
        }
    }
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        if (empty($nameloc['invalidated_at'])) {
            update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
        }
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['UserNotFound'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "Panel Not Found") {
            if (empty($nameloc['invalidated_at'])) {
                update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
            }
            update("invoice", "Status", "Unsuccessful", "id_invoice", $nameloc['id_invoice']);
        }
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['panelNotConnected'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $bExp2 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl2 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt2 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp2 > 0 && $bExp2 <= time()) {
        $status = 'expired';
    } elseif ($bDl2 > 0.0 && $bUt2 >= $bDl2) {
        $status = 'limited';
    }
    $status_map2 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map2[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    if ($timeDiff < 0) {
        $day = 0;
    } else {
        $day = "";
        $timemonth = floor($timeDiff / 2592000);
        if ($timemonth > 0) {
            $day .= $timemonth . $textbotlang['users']['stateus']['month'];
            $timeDiffday = $timeDiff - (2592000 * $timemonth);
        } else {
            $timeDiffday = $timeDiff;
        }
        $timereminday = floor($timeDiffday / 86400);
        if ($timereminday > 0) {
            $day .= $timereminday . $textbotlang['users']['stateus']['day'];
        }
        $timehoures = intval(($timeDiffday - ($timereminday * 86400)) / 3600);
        if ($timehoures > 0) {
            $day .= $timehoures . $textbotlang['users']['stateus']['hour'];
        }
        $timehoursall = $timeDiffday - ($timereminday * 86400);
        $timehoursall = $timehoursall - ($timehoures * 3600);
        $timeminuts = intval($timehoursall / 60);
        if ($timeminuts > 0) {
            $day .= $timeminuts . $textbotlang['users']['stateus']['min'];
        }
        $day .= " دیگر";
    }

    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }

    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
            ]
        ]
    ]);
    $userpassword = "";
    if ($marzban['type'] == "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        $manualHasConfig = !empty($manualFileItems);
        if (!$manualHasConfig) {
            foreach ($manualDelivery['configs'] as $manualCfgItem) {
                if (is_string($manualCfgItem) && trim($manualCfgItem) !== '') {
                    $manualHasConfig = true;
                    break;
                }
            }
        }
        $manualHasSub = trim((string) $manualDelivery['sub']) !== '';
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
نام کاربری سرویس : {$DataUserOut['username']}
📎 کد پیگیری سرویس : {$nameloc['id_invoice']}

📅 تاریخ اتمام : $expirationDate ($day)";
        $manualStatusExtend  = ($marzban['status_extend'] ?? 'on_extend') != "off_extend";
        $manualRefundEnabled = panel_feature_enabled($nameloc['Service_location'], 'refund');
        $manualRows = [];
        if ($manualHasConfig) {
            $manualRows[] = [
                ['text' => "🔐 دریافت کانفیگ", 'callback_data' => 'config_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualHasSub) {
            $manualRows[] = [
                ['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => 'subscriptionurl_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualStatusExtend) {
            $manualRows[] = [
                ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualRefundEnabled) {
            $manualRows[] = [
                ['text' => $textbotlang['users']['stateus']['removeservice'], 'callback_data' => 'removeserviceuser_' . $nameloc['id_invoice']],
            ];
        }
        $manualRows[] = [
            ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
        ];
        $keyboardsettingManual = json_encode(['inline_keyboard' => $manualRows], JSON_UNESCAPED_UNICODE);
        if ($user['step'] == "getuseragnetservice") {
            sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
        } elseif ($datain == "productcheckdata") {
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
        } else {
            Editmessagetext($from_id, $message_id, $textinfo, $keyboardsettingManual);
        }
        return;
    }
    $nameconfig = "";
    if ($nameloc['note'] != null) {
        $nameconfig = "✍️ یادداشت کانفیگ : {$nameloc['note']}";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
    $stmt->execute([
        ':username' => $nameloc['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "*", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $nameloc['name_product'] = $codeproduct['name_product'];
                $nameloc['Volume'] = $codeproduct['Volume_constraint'];
                $nameloc['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }

    $statustimeextra = panel_feature_enabled($nameloc['Service_location'], 'timeextra') ? "ontimeextraa" : "offtimeextraa";
    $marzbanstatusextra = panel_feature_enabled($nameloc['Service_location'], 'extravolume') ? "onextra" : "offextra";
    $statusdisorder = panel_feature_enabled($nameloc['Service_location'], 'disorder') ? "ondisorder" : "offdisorder";
    $statuschangeservice = panel_feature_enabled($nameloc['Service_location'], 'changeservice') ? "onstatus" : "offstatus";
    $statusshowconfig = panel_feature_enabled($nameloc['Service_location'], 'configbtn') ? "onconfig" : "offconfig";
    $statusremoveserveice = panel_feature_enabled($nameloc['Service_location'], 'refund') ? "on" : "off";
    if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
👤 نام کاربری سرویس : <code>{$DataUserOut['username']}</code>
🌍 موقعیت سرویس :{$nameloc['Service_location']}
نام محصول :{$nameloc['name_product']}

📶 اخرین زمان اتصال شما : $lastonline

🔋 ترافیک : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 تاریخ اتمام :  $expirationDate ($day)

$nameconfig";

        $keyboardsetting = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username],
                    ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username],
                ],
                [
                    ['text' => "❌ حذف سرویس", 'callback_data' => 'removeauto-' . $username],
                    ['text' => $textbotlang['users']['Extra_time']['title'], 'callback_data' => 'Extra_time_' . $username],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                ]
            ]
        ];
        if ($statustimeextra == "offtimeextraa")
            unset($keyboardsetting['inline_keyboard'][1][1]);
        if ($marzbanstatusextra == "offextra")
            unset($keyboardsetting['inline_keyboard'][0][1]);
        $keyboardsetting['inline_keyboard'] = array_values($keyboardsetting['inline_keyboard']);
        $keyboardsetting = json_encode($keyboardsetting);
    } else {
        $marzbancount = select("marzban_panel", "*", "status", "active", "count");
        if ($DataUserOut['status'] == "active") {
            $namestatus = '❌ خاموش کردن اکانت';
        } else {
            $namestatus = '💡 روشن کردن اکانت';
        }
        $keyboarddate = array(
            'updateinfo' => array(
                'text' => "♻️ بروزرسانی اطلاعات",
                'callback_data' => "updateproduct_"
            ),
            'linksub' => array(
                'text' => $textbotlang['users']['stateus']['linksub'],
                'callback_data' => "subscriptionurl_"
            ),
            'config' => array(
                'text' => $textbotlang['users']['stateus']['config'],
                'callback_data' => "config_"
            ),
            'extend' => array(
                'text' => $textbotlang['users']['extend']['title'],
                'callback_data' => "extend_"
            ),
            'changelink' => array(
                'text' => $textbotlang['users']['changelink']['btntitle'],
                'callback_data' => "changelink_"
            ),
            'removeservice' => array(
                'text' => $textbotlang['users']['stateus']['removeservice'],
                'callback_data' => "removeserviceuser_"
            ),
            'changenameconfig' => array(
                'text' => '📝 تغییر یادداشت',
                'callback_data' => "changenote_"
            ),
            'Extra_volume' => array(
                'text' => $textbotlang['users']['Extra_volume']['sellextra'],
                'callback_data' => "Extra_volume_"
            ),
            'Extra_time' => array(
                'text' => $textbotlang['users']['Extra_time']['title'],
                'callback_data' => "Extra_time_"
            ),
            'changestatus' => array(
                'text' => $namestatus,
                'callback_data' => "changestatus_"
            ),
            'transfor' => array(
                'text' => $textbotlang['Admin']['transfor']['title'],
                'callback_data' => "transfer_"
            ),
            'change-location' => array(
                'text' => $textbotlang['Admin']['change-location']['title'],
                'callback_data' => "changeloc_"
            ),
            'ekhtelal' => array(
                'text' => "⚠️ ارسال گزارش اختلال",
                'callback_data' => "disorder-"
            )
        );
        if ($nameloc['name_product'] == "سرویس تست") {
            unset($keyboarddate['transfor']);
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['removeservice']);
        }
        if ($marzban['type'] == "eylanpanel") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['type'] == "WGDashboard") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changestatus']);
            unset($keyboarddate['change-location']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['type'] == "remnawave") {
            if (($marzban['remna_revoke'] ?? 'off') !== "on") {
                unset($keyboarddate['changelink']);
            }
            unset($keyboarddate['transfor']);
            if (($marzban['changeloc'] ?? '') !== "onchangeloc") {
                unset($keyboarddate['change-location']);
            }
            if (($marzban['sublink'] ?? '') !== "onsublink") {
                unset($keyboarddate['linksub']);
            }
            if (($marzban['config'] ?? '') !== "onconfig") {
                unset($keyboarddate['config']);
            }
            if (($marzban['remna_usage_btn'] ?? 'on') === "on") {
                $keyboarddate['remna_usage'] = ['text' => '📊 مصرف به‌تفکیک سرور', 'callback_data' => 'remnausage_'];
            }
            if (($marzban['remna_server_picker'] ?? 'off') === "on") {
                $keyboarddate['remna_picker'] = ['text' => '🌍 انتخاب سرور', 'callback_data' => 'remnapicker_'];
            }
        }
        if ($marzban['status_extend'] == "off_extend") {
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['extend']);
        }
        if ($statusremoveserveice == "off")
            unset($keyboarddate['removeservice']);
        if ($statusshowconfig == "offconfig")
            unset($keyboarddate['config']);
        if ($statusdisorder == "offdisorder")
            unset($keyboarddate['ekhtelal']);
        if ($nameloc['Service_time'] == "0")
            unset($keyboarddate['Extra_time']);
        if ($nameloc['Volume'] == "0") {
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['Extra_time']);
        }
        if ($statuschangeservice == "offstatus")
            unset($keyboarddate['changestatus']);
        if ($setting['statusnamecustom'] == 'offnamecustom')
            unset($keyboarddate['changenameconfig']);
        if ($marzbancount == 1)
            unset($keyboarddate['change-location']);
        if ($marzban['changeloc'] == "offchangeloc")
            unset($keyboarddate['change-location']);
        if ($statustimeextra == "offtimeextraa")
            unset($keyboarddate['Extra_time']);
        if ($marzbanstatusextra == "offextra")
            unset($keyboarddate['Extra_volume']);
        $_rx_svc_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbd_s = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbd_s) && !empty($_rx_all_kbd_s['service'])) {
                $_rx_svc_styles = $_rx_all_kbd_s['service'];
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_svc_styles = $_rx_svc_styles + rx_getKeyboardDefaultStyles('service');
        }
        $tempArray = [];
        $keyboardsetting = ['inline_keyboard' => []];
        foreach ($keyboarddate as $_rx_svc_key => $keyboardtext) {
            $_rx_svc_btn = ['text' => $keyboardtext['text'], 'callback_data' => $keyboardtext['callback_data'] . $username];
            if (!empty($_rx_svc_styles[$_rx_svc_key]) && $_rx_svc_styles[$_rx_svc_key] !== 'default') {
                $_rx_svc_btn['style'] = $_rx_svc_styles[$_rx_svc_key];
            }
            $tempArray[] = $_rx_svc_btn;
            if (count($tempArray) == 2 or $keyboardtext['text'] == "♻️ بروزرسانی اطلاعات") {
                $keyboardsetting['inline_keyboard'][] = $tempArray;
                $tempArray = [];
            }
        }
        if (count($tempArray) > 0) {
            $keyboardsetting['inline_keyboard'][] = $tempArray;
        }
        $_rx_back_btn = ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'];
        if (!empty($_rx_svc_styles['backorder']) && $_rx_svc_styles['backorder'] !== 'default') {
            $_rx_back_btn['style'] = $_rx_svc_styles['backorder'];
        }
        $keyboardsetting['inline_keyboard'][] = [$_rx_back_btn];
        $keyboardsetting = json_encode($keyboardsetting);
        $subLastUserAgent = trim((string)($DataUserOut['sub_last_user_agent'] ?? ''));
        $subClientLine = $subLastUserAgent !== '' && strcasecmp($subLastUserAgent, 'Faoxima-Mini/1.0') !== 0
            ? "\n#️⃣ کلاینت متصل شده :<code>{$subLastUserAgent}</code>"
            : '';
        if ($DataUserOut['sub_updated_at'] !== null) {
            $textconnect = "
📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate{$subClientLine}";
        } elseif ($marzban['type'] == "WGDashboard") {
            $textconnect = "";
        } else {
            $textconnect = "📶 اخرین زمان اتصال شما : $lastonline";
        }
        $rwChangelinkHint = (($marzban['type'] ?? '') != "remnawave") ? "💡 برای قطع دسترسی دیگران کافیست روی گزینه \"تغییر لینک\" کلیک کنید." : "";
        $textinfo_tpl = $datatextbot['text_service_detail'] ?? "📊وضعیت سرویس : {status}
👤 نام سرویس : <code>{username}</code>
🌍 موقعیت سرویس :{location}
🗂 نام محصول :{product}

🔋 ترافیک : {traffic}
📥 حجم مصرفی : {used}
💢 حجم باقی مانده : {remaining} ({percent}%)

📅 تاریخ اتمام : {expire_date} ({days_left})";
        $textinfo_replacements = [
            '{status}' => $status_var,
            '{username}' => $DataUserOut['username'],
            '{location}' => $nameloc['Service_location'],
            '{product}' => $nameloc['name_product'],
            '{traffic}' => $LastTraffic,
            '{used}' => $usedTrafficGb,
            '{remaining}' => $RemainingVolume,
            '{percent}' => $Percent,
            '{expire_date}' => $expirationDate,
            '{days_left}' => $day,
        ];
        $textinfo = strtr($textinfo_tpl, $textinfo_replacements);
        $textinfo = "$textinfo\n$userpassword\n$nameconfig\n\n$textconnect\n\n$rwChangelinkHint";
    }
    if (($marzban['type'] ?? '') == "remnawave") {
        $rwLight = !empty($DataUserOut['is_online']) ? '🟢 آنلاین' : '🔴 آفلاین';
        $textinfo .= "\n📶 وضعیت اتصال: $rwLight";
        if (!empty($DataUserOut['node_name'])) {
            $textinfo .= "\n🌍 سرور فعال: " . $DataUserOut['node_name'];
        }
    }
    $symbolicLimitLabel = faoxima_symbolic_limit_label($nameloc);
    if ($symbolicLimitLabel !== null) {
        $textinfo .= "\n👥 تعداد کاربر مجاز: $symbolicLimitLabel";
    } elseif (($marzban['type'] ?? '') == "x-ui_single") {
        $xuiIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
        if ($xuiIpGuardActive) {
            $xuiIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
            $xuiIpLimitText = $xuiIpLimitConfigured > 0 ? "{$xuiIpLimitConfigured} کاربر" : 'نامحدود';
            $textinfo .= "\n👥 تعداد کاربر مجاز: $xuiIpLimitText";
        }
        if (function_exists('xui_panel_uses_token') && xui_panel_uses_token($marzban)) {
            $xuiOnlineCheck = xui_client_is_online($marzban, $DataUserOut['username'] ?? '');
            if (!empty($xuiOnlineCheck['status'])) {
                $textinfo .= "\n📶 وضعیت اتصال: " . (!empty($xuiOnlineCheck['online']) ? '🟢 آنلاین' : '⚪ آفلاین');
            }
            if ($xuiIpGuardActive) {
                $xuiIpSummary = xui_client_ip_summary($marzban, $DataUserOut['username'] ?? '');
                if (!empty($xuiIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $xuiIpSummary = faoxima_cap_ip_summary($xuiIpSummary, $xuiIpLimitConfigured);
                }
                if (!empty($xuiIpSummary['status'])) {
                    $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($xuiIpSummary['count']);
                    if (!empty($xuiIpSummary['raw'])) {
                        foreach (array_slice($xuiIpSummary['raw'], 0, 5) as $xuiRawIp) {
                            $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($xuiRawIp, ENT_QUOTES, 'UTF-8');
                        }
                    }
                } else {
                    $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                }
            }
            $xuiHwidLimitConfigured = intval($nameloc['hwid_limit'] ?? 0);
            if ($xuiHwidLimitConfigured > 0 && function_exists('xui_client_hwid_devices')) {
                $xuiHwidResponse = xui_client_hwid_devices($marzban, $DataUserOut['username'] ?? '');
                if (!empty($xuiHwidResponse['status'])) {
                    $xuiHwidList = is_array($xuiHwidResponse['devices'] ?? null) ? $xuiHwidResponse['devices'] : [];
                    $textinfo .= "\n👥 دستگاه‌های متصل: " . count($xuiHwidList) . " از {$xuiHwidLimitConfigured} دستگاه";
                    foreach (array_slice($xuiHwidList, 0, 5) as $xuiHwidEntry) {
                        $xuiDeviceOs = (string) ($xuiHwidEntry['deviceOs'] ?? '');
                        $xuiDeviceModel = (string) ($xuiHwidEntry['deviceModel'] ?? '');
                        $xuiDeviceLabel = $xuiDeviceOs !== '' ? ($xuiDeviceModel !== '' ? "{$xuiDeviceOs} ({$xuiDeviceModel})" : $xuiDeviceOs) : 'نامشخص';
                        $xuiLastSeenRaw = intval($xuiHwidEntry['lastSeen'] ?? 0);
                        $xuiLastSeenText = '';
                        if ($xuiLastSeenRaw > 0) {
                            try {
                                $xuiLastSeenDt = new DateTime('@' . intval($xuiLastSeenRaw / 1000));
                                $xuiLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                                $xuiLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $xuiLastSeenDt->getTimestamp());
                            } catch (Throwable $e) {
                            }
                        }
                        $textinfo .= "\n📱 دستگاه: {$xuiDeviceLabel}{$xuiLastSeenText}";
                    }
                } else {
                    $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$xuiHwidLimitConfigured} دستگاه";
                }
            }
        }
    } elseif (($marzban['type'] ?? '') == "rebecca") {
        $rebeccaIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
        if ($rebeccaIpGuardActive) {
            $rebeccaIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
            $rebeccaIpLimitText = $rebeccaIpLimitConfigured > 0 ? "{$rebeccaIpLimitConfigured} کاربر" : 'نامحدود';
            $textinfo .= "\n👥 تعداد کاربر مجاز: $rebeccaIpLimitText";
            $rebeccaInsightsAllowed = !function_exists('rebeccaAccessInsightsPermissionStatus')
                || (rebeccaAccessInsightsPermissionStatus($marzban['name_panel'])['status'] ?? false) === true;
            if ($rebeccaInsightsAllowed && function_exists('rebeccaClientIpSummary')) {
                $rebeccaIpSummary = rebeccaClientIpSummary($marzban['name_panel'], $DataUserOut['username'] ?? '');
                if (!empty($rebeccaIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $rebeccaIpSummary = faoxima_cap_ip_summary($rebeccaIpSummary, $rebeccaIpLimitConfigured);
                }
                if (!empty($rebeccaIpSummary['status'])) {
                    $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($rebeccaIpSummary['count']);
                    if (!empty($rebeccaIpSummary['raw'])) {
                        foreach (array_slice($rebeccaIpSummary['raw'], 0, 5) as $rebeccaRawIp) {
                            $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($rebeccaRawIp, ENT_QUOTES, 'UTF-8');
                        }
                    }
                } else {
                    $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                }
            }
        }
    } elseif (($marzban['type'] ?? '') == "pasarguard") {
        $pasarguardHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
        if ($pasarguardHwidLimitConfigured > 0 && function_exists('pasarguardGetUserHwids')) {
            $pasarguardHwidResponse = pasarguardGetUserHwids($DataUserOut['username'] ?? $nameloc['username'], $marzban['name_panel']);
            if (empty($pasarguardHwidResponse['error']) && !empty($pasarguardHwidResponse['body'])) {
                $pasarguardHwidData = json_decode($pasarguardHwidResponse['body'], true);
                $pasarguardHwidList = is_array($pasarguardHwidData['hwids'] ?? null) ? $pasarguardHwidData['hwids'] : [];
                $textinfo .= "\n👥 دستگاه‌های متصل: " . count($pasarguardHwidList) . " از {$pasarguardHwidLimitConfigured} دستگاه";
                foreach (array_slice($pasarguardHwidList, 0, 5) as $pasarguardHwidEntry) {
                    $pasarguardDeviceOs = (string) ($pasarguardHwidEntry['device_os'] ?? '');
                    $pasarguardDeviceModel = (string) ($pasarguardHwidEntry['device_model'] ?? '');
                    $pasarguardDeviceLabel = $pasarguardDeviceOs !== '' ? ($pasarguardDeviceModel !== '' ? "{$pasarguardDeviceOs} ({$pasarguardDeviceModel})" : $pasarguardDeviceOs) : 'نامشخص';
                    $pasarguardLastUsedRaw = (string) ($pasarguardHwidEntry['last_used_at'] ?? '');
                    $pasarguardLastSeenText = '';
                    if ($pasarguardLastUsedRaw !== '') {
                        try {
                            $pasarguardLastSeenDt = new DateTime($pasarguardLastUsedRaw, new DateTimeZone('UTC'));
                            $pasarguardLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                            $pasarguardLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $pasarguardLastSeenDt->getTimestamp());
                        } catch (Throwable $e) {
                        }
                    }
                    $textinfo .= "\n📱 دستگاه: {$pasarguardDeviceLabel}{$pasarguardLastSeenText}";
                }
            } else {
                $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$pasarguardHwidLimitConfigured} دستگاه";
            }
        }
    } elseif (($marzban['type'] ?? '') == "remnawave") {
        $remnaHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
        if ($remnaHwidLimitConfigured > 0 && function_exists('remnawave_get_user_hwid_devices')) {
            $remnaHwidResponse = remnawave_get_user_hwid_devices($marzban['name_panel'], $DataUserOut['username'] ?? $nameloc['username']);
            if (($remnaHwidResponse['status'] ?? '') === 'successful') {
                $remnaHwidList = is_array($remnaHwidResponse['devices'] ?? null) ? $remnaHwidResponse['devices'] : [];
                $textinfo .= "\n👥 دستگاه‌های متصل: " . count($remnaHwidList) . " از {$remnaHwidLimitConfigured} دستگاه";
                foreach (array_slice($remnaHwidList, 0, 5) as $remnaHwidEntry) {
                    $remnaDevicePlatform = (string) ($remnaHwidEntry['platform'] ?? '');
                    $remnaDeviceModel = (string) ($remnaHwidEntry['deviceModel'] ?? '');
                    $remnaDeviceLabel = $remnaDevicePlatform !== '' ? ($remnaDeviceModel !== '' ? "{$remnaDevicePlatform} ({$remnaDeviceModel})" : $remnaDevicePlatform) : 'نامشخص';
                    $remnaLastUsedRaw = (string) ($remnaHwidEntry['updatedAt'] ?? '');
                    $remnaLastSeenText = '';
                    if ($remnaLastUsedRaw !== '') {
                        try {
                            $remnaLastSeenDt = new DateTime($remnaLastUsedRaw, new DateTimeZone('UTC'));
                            $remnaLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                            $remnaLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $remnaLastSeenDt->getTimestamp());
                        } catch (Throwable $e) {
                        }
                    }
                    $textinfo .= "\n📱 دستگاه: {$remnaDeviceLabel}{$remnaLastSeenText}";
                }
            } else {
                $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$remnaHwidLimitConfigured} دستگاه";
            }
        }
    }
    if ($user['step'] == "getuseragnetservice") {
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } elseif ($datain == "productcheckdata") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } else {
        Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^infocard_qr_(\w+)$/', $datain, $dataget)) {

    if (function_exists('isQrDisabled') && isQrDisabled()) {
        if (isset($callback_query_id)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
        }
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler infocard_qr on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'infocard_qr',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || empty($DataUserOut['subscription_url'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_qr_unavailable'] ?? "❌ امکان دریافت QR Code در حال حاضر وجود ندارد.", null, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));
    $urlimage = "$from_id$randomString.png";
    $qrCode = createqrcode($subscriptionurl);
    if ($qrCode === null) {
        sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از دکمهٔ کپی/متن بالا استفاده کنید.", null, 'HTML');
        sendmessage($from_id, "<code>{$subscriptionurl}</code>", rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک'), 'HTML');
        return;
    }
    file_put_contents($urlimage, $qrCode->getString());
    addBackgroundImage($urlimage, $qrCode, 'images.jpg');
    $subQrPayload = [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => "<code>{$subscriptionurl}</code>",
        'parse_mode' => "HTML",
    ];
    $subQrCopyKb = rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک');
    if ($subQrCopyKb !== null) {
        $subQrPayload['reply_markup'] = $subQrCopyKb;
    }
    telegram('sendphoto', $subQrPayload);
    unlink($urlimage);
    if (isset($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ QR Code ارسال شد',
            'show_alert' => false,
            'cache_time' => 0,
        ]);
    }
} elseif (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/sub ") !== false)) {
    if (is_string($text) && $text !== '' && $text[0] == "/") {
        $id_invoice = explode(' ', $text)[1];
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    }
    if ($nameloc == false)
        return;
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (($marzban_list_get['type'] ?? '') == "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        if (trim((string) $manualDelivery['sub']) === '') {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            return;
        }
        $manualBackRow = [[
            'text' => $textbotlang['users']['stateus']['backinfo'],
            'callback_data' => "productcheckdata",
        ]];
        $bakinfos = rx_copy_button_keyboard($manualDelivery['sub'], '📋 کپی لینک اشتراک', [$manualBackRow]);
        if ($bakinfos === null) {
            $bakinfos = json_encode(['inline_keyboard' => [$manualBackRow]]);
        }
        update("user", "Processing_value", $nameloc['username'], "id", $from_id);
        sendmessage($from_id, "
{$textbotlang['users']['stateus']['linksub']}

<code>{$manualDelivery['sub']}</code>", $bakinfos, 'HTML');
        return;
    }
    $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    if ($marzban_list_get['type'] == "WGDashboard") {
        $textsub = "qrcode اشتراک شما";
    } else {
        $textsub = "
{$textbotlang['users']['stateus']['linksub']}

<code>$subscriptionurl</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    update("user", "Processing_value", $nameloc['username'], "id", $from_id);
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));
    $subBackRow = [[
        'text' => $textbotlang['users']['stateus']['backinfo'],
        'callback_data' => "productcheckdata",
    ]];
    $subCopyKb = rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک', [$subBackRow]);
    if ($subCopyKb !== null) {
        $bakinfos = $subCopyKb;
    }

    if ($marzban_list_get['type'] == "WGDashboard") {
        sendmessage($from_id, $textsub, $bakinfos, 'HTML');
        $urlimage = normalizeConfigFilename("{$marzban_list_get['inboundid']}-{$nameloc['username']}.conf", $marzban_list_get['namecustom'] ?? '', true);
        file_put_contents($urlimage, $DataUserOut['subscription_url']);
        sendDocument($from_id, $urlimage, "⚙️ کانفیگ شما");
        unlink($urlimage);
    } elseif ((!function_exists('isQrDisabled') || !isQrDisabled())
        && !(function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get))
        && trim((string) $subscriptionurl) !== '') {
        $qrCode = createqrcode($subscriptionurl);
        if ($qrCode === null) {
            sendmessage($from_id, $textsub, $bakinfos, 'HTML');
            sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
            return;
        }
        $urlimage = "{$from_id}{$randomString}.png";
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => $textsub,
            'parse_mode' => "HTML",
            'reply_markup' => $bakinfos,
        ]);
        unlink($urlimage);
    } else {
        sendmessage($from_id, $textsub, $bakinfos, 'HTML');
    }
} elseif (preg_match('/removeauto-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeauto on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeauto',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    update('invoice', 'status', 'removebyuser', 'id_invoice', $id_invoice);
    $tetremove = "ادمین عزیز یک کاربر سرویس خود را پس از پایان حجم یا زمان حدف کرده است
نام کاربری کانفیک : {$nameloc['username']}";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $tetremove,
            'parse_mode' => "HTML"
        ]);
    }
    sendmessage($from_id, $datatextbot['dyn_errors_service_deleted'] ?? "📌 سرویس با موفقیت حذف شد", null, 'html');
} elseif (preg_match('/^configpage_(.*)_(\d+)$/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $page = (int) $dataget[2];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configpage on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configpage',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || !is_array($DataUserOut['links'] ?? null)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_dot'] ?? "❌ پنل در دسترس نیست.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", keyboard_config($DataUserOut['links'], $id_invoice, true, $page));
} elseif (preg_match('/config_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/link ") !== false)) {
    $textCommand = is_string($text) ? $text : '';
    if ($textCommand !== '' && $textCommand[0] === "/") {
        $parts = explode(' ', $textCommand, 2);
        $id_invoice = $parts[1] ?? null;
        if ($id_invoice === null) {
            sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler config on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'config',
                ]);
            }
            $nameloc = false;
        }
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $_configPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_configPanelChk) && ($_configPanelChk['type'] ?? '') === "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        if (!empty($manualFileItems)) {
            $manualBackKb = json_encode(['inline_keyboard' => [[
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$nameloc['id_invoice']}"],
            ]]], JSON_UNESCAPED_UNICODE);
            $sentFiles = rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', $manualBackKb);
            if (!$sentFiles) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            }
            return;
        }
        $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
            return is_string($item) && trim($item) !== '';
        }));
        if (empty($manualConfigs)) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            return;
        }
        Editmessagetext($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", keyboard_config($manualConfigs, $nameloc['id_invoice']));
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if (!is_array($DataUserOut['links'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", keyboard_config($DataUserOut['links'], $nameloc['id_invoice']));
} elseif (preg_match('/configget_(.*)_(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configget on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configget',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $_configgetPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_configgetPanelChk) && ($_configgetPanelChk['type'] ?? '') === "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        if (!empty($manualFileItems)) {
            $sentFiles = rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', null);
            if (!$sentFiles) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            }
            return;
        }
        $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
            return is_string($item) && trim($item) !== '';
        }));
        if (empty($manualConfigs)) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            return;
        }
        if ($dataget[2] == "1520") {
            for ($i = 0; $i < count($manualConfigs); ++$i) {
                sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$i], ENT_QUOTES, 'UTF-8') . "</code>", rx_copy_button_keyboard($manualConfigs[$i], '📋 کپی کانفیگ'), 'HTML');
            }
            return;
        }
        if (!isset($manualConfigs[$dataget[2]])) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            return;
        }
        sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$dataget[2]], ENT_QUOTES, 'UTF-8') . "</code>", rx_copy_button_keyboard($manualConfigs[$dataget[2]], '📋 کپی کانفیگ'), 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_dot'] ?? "❌ پنل در دسترس نیست.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    if (!isset($DataUserOut['links']) || !is_array($DataUserOut['links']) || count($DataUserOut['links']) === 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
        return;
    }
    $rwCgSel = $DataUserOut['links'][$dataget[2]] ?? '';
    $config = "";
    if ($dataget[2] == "1520") {
        for ($i = 0; $i < count($DataUserOut['links']); ++$i) {
            if ($i === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
                continue;
            }
            $allConfigItem = (string) $DataUserOut['links'][$i];
            if (function_exists('xui_wg_conf_from_link')) {
                $allWgConf = xui_wg_conf_from_link($allConfigItem);
                if (is_array($allWgConf)) {
                    $allWgName = normalizeConfigFilename(xui_wg_conf_filename($allConfigItem, $allWgConf['protocol'], $i), $_configgetPanelChk['namecustom'] ?? '', true);
                    $allWgDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $allWgName;
                    file_put_contents($allWgDest, $allWgConf['conf']);
                    $allWgLabel = $allWgConf['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
                    telegram('sendDocument', [
                        'chat_id' => $from_id,
                        'document' => new CURLFile($allWgDest, 'application/octet-stream', $allWgName),
                        'caption' => "⚙️ کانفیگ شما ({$allWgLabel})",
                        'parse_mode' => 'HTML',
                    ]);
                    unlink($allWgDest);
                    continue;
                }
            }
            if (preg_match('/^https?:\/\//i', $allConfigItem)) {
                $allConfigItem = strtok($allConfigItem, '#');
            }
            sendmessage($from_id, "<code>{$allConfigItem}</code>", rx_copy_button_keyboard($allConfigItem, '📋 کپی کانفیگ'), 'HTML');
        }
        return;
    }
    $selectedConfig = (string) $DataUserOut['links'][$dataget[2]];
    if ((int) $dataget[2] === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
        $singleConfigFile = $DataUserOut['single_config_file'];
        $xuiFileName = normalizeConfigFilename($singleConfigFile['filename'], $_configgetPanelChk['namecustom'] ?? '', true);
        $xuiFileDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $xuiFileName;
        file_put_contents($xuiFileDest, $singleConfigFile['value']);
        telegram('sendDocument', [
            'chat_id' => $from_id,
            'document' => new CURLFile($xuiFileDest, 'application/octet-stream', $xuiFileName),
            'caption' => "⚙️ کانفیگ شما",
            'parse_mode' => 'HTML',
        ]);
        unlink($xuiFileDest);
        return;
    }
    if (function_exists('xui_wg_conf_from_link')) {
        $xuiWgConf = xui_wg_conf_from_link($selectedConfig);
        if (is_array($xuiWgConf)) {
            $xuiWgName = normalizeConfigFilename(xui_wg_conf_filename($selectedConfig, $xuiWgConf['protocol'], (int) $dataget[2]), $_configgetPanelChk['namecustom'] ?? '', true);
            $xuiWgDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $xuiWgName;
            file_put_contents($xuiWgDest, $xuiWgConf['conf']);
            $xuiWgLabel = $xuiWgConf['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
            telegram('sendDocument', [
                'chat_id' => $from_id,
                'document' => new CURLFile($xuiWgDest, 'application/octet-stream', $xuiWgName),
                'caption' => "⚙️ کانفیگ شما ({$xuiWgLabel})",
                'parse_mode' => 'HTML',
            ]);
            unlink($xuiWgDest);
            return;
        }
    }
    if (function_exists('rebeccaConfigDownloadInfo')) {
        $rebeccaDownloadInfo = rebeccaConfigDownloadInfo($selectedConfig);
        if ($rebeccaDownloadInfo !== null) {
            $rebeccaPublicName = normalizeConfigFilename($rebeccaDownloadInfo['filename'], $_configgetPanelChk['namecustom'] ?? '', $rebeccaDownloadInfo['kind'] === 'wireguard');
            $rebeccaDownloadDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $rebeccaPublicName;
            if (rebeccaDownloadConfigFile($rebeccaDownloadInfo['url'], $rebeccaDownloadDest)) {
                sendDocument($from_id, $rebeccaDownloadDest, "⚙️ کانفیگ شما", $rebeccaPublicName);
                unlink($rebeccaDownloadDest);
            } else {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            }
            return;
        }
    }
    if (preg_match('/^https?:\/\//i', $selectedConfig)) {
        $selectedConfig = strtok($selectedConfig, '#');
    }
    $isStructuredConnectionInfo = strpos($selectedConfig, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $selectedConfig);
    $qrWantedForConfig = (!function_exists('isQrDisabled') || !isQrDisabled())
        && !(function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($_configgetPanelChk))
        && !$isStructuredConnectionInfo
        && !preg_match('#^(tg|tg-proxy|proxy|wireguard|wg|vpn|awg|amneziawg)://#i', trim($selectedConfig))
        && trim($selectedConfig) !== '';
    $selectedConfigCopyKb = rx_copy_button_keyboard($selectedConfig, '📋 کپی کانفیگ');
    if ($qrWantedForConfig) {
        $qrCode = createqrcode($selectedConfig);
        if ($qrCode === null) {
            sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
            sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این کانفیگ ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
            return;
        }
        $urlimage = "{$from_id}" . bin2hex(random_bytes(3)) . ".png";
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        $photoPayload = [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => "<code>{$selectedConfig}</code>",
            'parse_mode' => "HTML",
        ];
        if ($selectedConfigCopyKb !== null) {
            $photoPayload['reply_markup'] = $selectedConfigCopyKb;
        }
        telegram('sendphoto', $photoPayload);
        unlink($urlimage);
    } else {
        sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
    }
} elseif (preg_match('/^remnausage_(.+)$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwBw = function_exists('remnawave_user_bandwidth') ? remnawave_user_bandwidth($rwInv['Service_location'], $rwInv['username']) : ['status' => 'Unsuccessful', 'text' => ''];
    if (($rwBw['status'] ?? '') !== 'successful') {
        sendmessage($from_id, $datatextbot['dyn_errors_usage_stats_failed'] ?? "❌ دریافت آمار مصرف ناموفق بود.", null, 'html');
        return;
    }
    $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
    sendmessage($from_id, "📊 مصرف ۳۰ روز اخیر به‌تفکیک سرور:\n\n" . $rwBw['text'], $rwBack, 'HTML');
    return;
} elseif (preg_match('/^remnasetsquad_(.+)_([0-9a-fA-F-]{36})$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwSquad = $rwm[2];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwPanel = select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
    if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
        return;
    }
    $rwSet = function_exists('remnawave_set_squads') ? remnawave_set_squads($rwInv['Service_location'], $rwInv['username'], [$rwSquad]) : ['status' => 'Unsuccessful'];
    $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
    sendmessage($from_id, ($rwSet['status'] ?? '') === 'successful' ? "✅ سرور با موفقیت تغییر کرد." : "❌ تغییر سرور ناموفق بود.", $rwBack, 'HTML');
    return;
} elseif (preg_match('/^remnapicker_(.+)$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwPanel = select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
    if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
        return;
    }
    $rwMgr = function_exists('remnawave_manager_for') ? remnawave_manager_for($rwInv['Service_location']) : null;
    $rwSq = $rwMgr ? $rwMgr->getInternalSquads($pdo) : ['ok' => false];
    if (empty($rwSq['ok']) || !is_array($rwSq['data'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_server_list_failed'] ?? "❌ دریافت لیست سرورها ناموفق بود.", null, 'html');
        return;
    }
    $rwList = is_array($rwSq['data']['internalSquads'] ?? null) ? $rwSq['data']['internalSquads'] : (isset($rwSq['data'][0]) ? $rwSq['data'] : []);
    $rwKb = ['inline_keyboard' => []];
    foreach ($rwList as $sq) {
        if (!is_array($sq) || empty($sq['uuid'])) {
            continue;
        }
        $rwKb['inline_keyboard'][] = [['text' => "🌍 " . (string)($sq['name'] ?? $sq['uuid']), 'callback_data' => "remnasetsquad_{$rwInv['id_invoice']}_{$sq['uuid']}"]];
    }
    $rwKb['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]];
    Editmessagetext($from_id, $message_id, "🌍 یک سرور (Squad) انتخاب کنید:", json_encode($rwKb));
    return;
} elseif (preg_match('/changestatus_(\w+)/', $datain, $dataget)) {
    $rxChangeStatusInvoice = select("invoice", "*", "id_invoice", $dataget[1], "select");
    if (is_array($rxChangeStatusInvoice) && !panel_feature_enabled($rxChangeStatusInvoice['Service_location'], 'changeservice')) {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changestatus on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changestatus',
            ]);
        }
        return;
    }
    if ($nameloc['Status'] == "disablebyadmin") {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;

    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $changeStatusPanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($DataUserOut['status'] == "on_hold" || ($DataUserOut['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($changeStatusPanel) ? $changeStatusPanel : null, $DataUserOut))) {
        sendmessage($from_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisable_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmaccountdisable on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmaccountdisable',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $currentStatusData = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (is_array($currentStatusData) && $currentStatusData['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($marzban_list_get) ? $marzban_list_get : null, $currentStatusData)) {
        Editmessagetext($from_id, $message_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", $bakinfos);
        return;
    }
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        if (($dataoutput['code'] ?? '') === 'not_connected') {
            Editmessagetext($from_id, $message_id, $dataoutput['msg'], $bakinfos);
            return;
        }
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extend on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'extend',
            ]);
        }
        return;
    }
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $stmt->execute([
        ':service_location' => $marzban_list_get['name_panel'],
        ':agent' => $user['agent'],
    ]);
    $product = $stmt->rowCount();
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    if ($product == 0) {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if (!panel_feature_enabled($nameloc['Service_location'], 'categorytime')) {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
        ]);
        $productextend = ['inline_keyboard' => []];
        $statusshowprice = panel_feature_enabled($nameloc['Service_location'], 'showprice') ? "onshowprice" : "offshowprice";
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hide_panel = json_decode($result['hide_panel'], true);
            if (is_array($hide_panel) && in_array($nameloc['Service_location'], $hide_panel)) {
                error_log("Product {$result['code_product']} is marked hidden for {$nameloc['Service_location']} but was kept visible for extend.");
            }
            if (intval($user['pricediscount']) != 0) {
                $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                $result['price_product'] = $result['price_product'] - $resultper;
            }
            if ($statusshowprice == "offshowprice") {
                $namekeyboard = $result['name_product'];
            } else {
                $result['price_product'] = number_format($result['price_product']);
                $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
            }
            $productextend['inline_keyboard'][] = [
                ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
            ];
        }
        $productextend['inline_keyboard'][] = [
            ['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]
        ];
        $productextend['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
        ];

        $json_list_product_lists = json_encode($productextend);
        $textselectservice = function_exists('faoxima_render_text')
            ? faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
            : $textbotlang['users']['extend']['selectservice'];
        Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
    } else {
        $monthkeyboard = keyboardTimeCategory($nameloc['Service_location'], $user['agent'], "productextendmonths_", "product_$id_invoice", false, true);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif ($user['step'] == "gettimecustomvolomforextend") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextend', $from_id);
} elseif (preg_match('/productextendmonths_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND Service_time = :monthe AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $stmt->execute([
        ':service_location' => $nameloc['Service_location'],
        'monthe' => $monthenumber,
        ':agent' => $user['agent'],
    ]);
    $productextend = ['inline_keyboard' => []];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $statusshowprice = panel_feature_enabled($marzban_list_get, 'showprice') ? "onshowprice" : "offshowprice";
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (intval($user['pricediscount']) != 0) {
            $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        if ($statusshowprice == "offshowprice") {
            $namekeyboard = $result['name_product'];
        } else {
            $result['price_product'] = number_format($result['price_product']);
            $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
        }
        $productextend['inline_keyboard'][] = [
            ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
        ];
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $productextend['inline_keyboard'][] = [
            ['text' => "📍 انتخاب سرویس فعلی", 'callback_data' => "serviceextendselect_pre"]
        ];
    }
    $productextend['inline_keyboard'][] = [
        ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
    ];

    $json_list_product_lists = json_encode($productextend);
    $textselectservice = function_exists('faoxima_render_text')
        ? faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
        : $textbotlang['users']['extend']['selectservice'];
    Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
} elseif (preg_match('/^serviceextendselect_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserforextend" || $datain == "exntedagei") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($user['step'] == "getvolumecustomuserforextend") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
    } elseif ($datain == "exntedagei") {
        $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
        $stmt->execute([
            ':username' => $nameloc['username'],
        ]);
        if ($stmt->rowCount() == 0) {
            $codeproduct = select("product", "*", "name_product", $nameloc['name_product']);
        } else {
            $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true)))) {
                sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
                return;
            }
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "code_product", "code_product", $service_other['code_product'], "select");
        }
        if ($codeproduct == false) {
            sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
            return;
        }
        $codeproduct = $codeproduct['code_product'];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    } else {
        $codeproduct = $dataget[1];
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($user['step'] == "getvolumecustomuserforextend") {
        $product['name_product'] = $nameloc['name_product'];
        $product['code_product'] = "customvolume";
        $product['note'] = "";
        $product['price_product'] = (intval($userdate['volume']) * $custompricevalue) + ($text * $customtimevalueprice);
        $product['Service_time'] = $text;
        $product['Volume_constraint'] = $userdate['volume'];
        step("home", $from_id);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent'],
        ]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_restart_process'] ?? "❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.", $keyboard, 'HTML');
        return;
    }
    savedata("save", "time", $product['Service_time']);
    savedata("save", "data_limit", $product['Volume_constraint']);
    savedata("save", "price_product", $product['price_product']);
    savedata("save", "code_product", $product['code_product']);
    $__discEligibleExtend = MiniDiscount::hasEligible('extend', (string)($product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($product['category'] ?? ''), $user);
    $__extendConfirmRow = [['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"]];
    if ($__discEligibleExtend) {
        $__extendConfirmRow[] = ['text' => $textbotlang['users']['extend']['discount'], 'callback_data' => "discountextend"];
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            $__extendConfirmRow,
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ]
    ]);
    if (intval($user['pricediscount']) != 0) {
        $result = ($product['price_product'] * $user['pricediscount']) / 100;
        $pricelastextend = number_format(round($product['price_product'] - $result, 0));
    } else {
        $pricelastextend = number_format($product['price_product']);
    }
    $volumeextend = intval($product['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $product['Volume_constraint'] . ' گیگ';
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$product['name_product']}
💸 مبلغ تمدید : $pricelastextend تومان
⏱ مدت زمان تمدید :{$product['Service_time']} روز
🔋 حجم تمدید :$volumeextend
✍️ توضیحات : {$product['note']}
💸 موجودی کیف پول : " . rxFormatToman($user['Balance']) . "
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    if ($user['step'] == "getvolumecustomuserforextend") {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif ($datain == "discountextend") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountextend', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountextend") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_exclusive_discount_conflict'] ?? "❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.", $backuser, 'HTML');
        return;
    }
    $__extendProductRow = select("product", "*", "code_product", $userdate['code_product'] ?? '', "select");
    $__extendCategory = is_array($__extendProductRow) ? (string)($__extendProductRow['category'] ?? '') : '';
    $__dv = MiniDiscount::validateSell($text, 'extend', (string)($userdate['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), $__extendCategory, $user);
    if (empty($__dv['ok'])) {
        sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
        return;
    }
    $__dvt = $__dv['value_type'];
    $__dval = (float)$__dv['value'];
    $__dlabel = $__dv['label'];
    $rxDiscountAppliedTpl = $datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.";
    sendmessage($from_id, sprintf($rxDiscountAppliedTpl, $__dlabel), $keyboard, 'HTML');
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $info_product['code_product'] = "pre";
        $info_product['name_product'] = $nameloc['name_product'];
        $info_product['price_product'] = ($userdate['data_limit'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice);
        $info_product['Service_time'] = $userdate['time'];
        $info_product['Volume_constraint'] = $userdate['data_limit'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
        $stmt->bindParam(':code_product', $userdate['code_product'], PDO::PARAM_STR);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel'], PDO::PARAM_STR);
        $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $rxFmtExtendInfoProductPrice = rxFormatToman($info_product['price_product']);
    $rxFmtExtendUserBalance = rxFormatToman($user['Balance']);
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$info_product['name_product']}
💸 مبلغ تمدید :{$rxFmtExtendInfoProductPrice}
⏱ مدت زمان تمدید :{$info_product['Service_time']} روز
🔋 حجم تمدید :{$info_product['Volume_constraint']} گیگ
✍️ توضیحات : {$info_product['note']}
💸 موجودی کیف پول : {$rxFmtExtendUserBalance}

✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"],
            ]
        ]
    ]);
    sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    $parametrsendvalue = "dis_" . $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    step("home", $from_id);
} elseif ($datain == "confirmserivce" || $datain == "confirmserdiscount") {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $partsdic = explode("_", $user['Processing_value_four']);
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $randomString = bin2hex(random_bytes(2));
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $prodcut['code_product'] = "custom_volume";
        $prodcut['name_product'] = $nameloc['name_product'];
        $prodcut['price_product'] = ($userdata['data_limit'] * $custompricevalue) + ($userdata['time'] * $customtimevalueprice);
        $prodcut['Service_time'] = $userdata['time'];
        $prodcut['Volume_constraint'] = $userdata['data_limit'];
        $prodcut['inbounds'] = $marzban_list_get['inboundid'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $userdata['code_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $pricelastextend = $prodcut['price_product'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    if ($datain == "confirmserdiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($SellDiscountlimit != false) {
            $pricelastextend = $partsdic[2];
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($pricelastextend * $user['pricediscount']) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($user['Balance'] < $pricelastextend && $user['agent'] != "n2" && intval($pricelastextend) != 0) {
        $marzbandirectpay = panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
            $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $Balance_prim = $pricelastextend - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?,?, ?, ?,?,?,?)");
            $dateacc = date('Y/m/d H:i:s');
            $value = json_encode(array(
                "volumebuy" => $prodcut['Volume_constraint'],
                "Service_time" => $prodcut['Service_time'],
                "oldvolume" => $DataUserOut['data_limit'],
                "oldtime" => $DataUserOut['expire'],
                'code_product' => $prodcut['code_product'],
                'id_order' => $randomString
            ));
            $type = "extend_user";
            $status = "unpaid";
            $extend = '';
            $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend, $status);
            $stmt->execute();
            $stmt->close();
            update("user", "Processing_value_one", "{$nameloc['username']}%$randomString", "id", $from_id);
            update("user", "Processing_value_tow", "getextenduser", "id", $from_id);
            return;
        }
    }
    $rxExtendDiscountRow = false;
    if ($datain == "confirmserdiscount") {
        $rxExtendDiscountRow = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($rxExtendDiscountRow != false) {
            update('invoice', 'discount_code', $partsdic[1], 'id_invoice', $nameloc['id_invoice']);
            update('invoice', 'discount_amount', (string)((float)($prodcut['price_product'] ?? 0) - (float)$pricelastextend), 'id_invoice', $nameloc['id_invoice']);
            update('invoice', 'price_before_discount', (string)($prodcut['price_product'] ?? 0), 'id_invoice', $nameloc['id_invoice']);
        }
    }
    if ($user['agent'] == "f") {
        $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
    } else {
        $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$user['agent']];
    }
    $renewCashbackEligible = !function_exists('rx_shopCashbackEligible')
        || rx_shopCashbackEligible("chashbackextend", $user['register'] ?? null, "getextenduser");
    $rxCashbackDiscount = 0;
    if ($renewCashbackEligible && intval($valurcashbackextend) != 0 and intval($pricelastextend) != 0) {
        $rxCashbackDiscount = ($prodcut['price_product'] * $valurcashbackextend) / 100;
        $pricelastextend = $pricelastextend - $rxCashbackDiscount;
        if ($pricelastextend < 0) {
            $pricelastextend = 0;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $pricelastextend) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $__allowNegEx = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__chargedExUser = false;
    if (intval($pricelastextend) > 0) {
        $__chargeEx = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $pricelastextend, $__allowNegEx) : ['ok' => false];
        if (empty($__chargeEx['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEx['new_balance'];
        $__chargedExUser = true;
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $pricelastextend, 'service_action', 'تمدید سرویس', $randomString, 'invoice', (string)$id_invoice);
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    if ($nameloc['name_product'] == "سرویس تست") {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
    }
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        if ($__chargedExUser && function_exists('balance_atomic_credit')) {
            $__refundedEx = balance_atomic_credit($from_id, $pricelastextend);
            if ($__refundedEx && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $pricelastextend, 'refund', 'بازگشت وجه تمدید سرویس', $randomString, 'invoice', (string)$id_invoice);
            }
        }
        $rxStockEmpty = ($extend['code'] ?? '') === 'manual_stock_empty';
        $rxQueuedExists = ($extend['code'] ?? '') === 'queued_renewal_exists';
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
        if ($rxStockEmpty) {
            sendmessage($from_id, $datatextbot['dyn_errors_renewal_stock_empty'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } elseif ($rxQueuedExists) {
            sendmessage($from_id, $datatextbot['dyn_errors_queued_renewal_exists'] ?? "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } else {
            sendmessage($from_id, $datatextbot['dyn_errors_renewal_support_error'] ?? "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        }
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if ($rxCashbackDiscount > 0) {
        $rxCashbackGiftTpl = $datatextbot['dyn_rewards_cashback_gift'] ?? "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ %s تومان حساب شما شارژ گردید";
        sendmessage($from_id, sprintf($rxCashbackGiftTpl, $rxCashbackDiscount), null, 'HTML');
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?, ?, ?,?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
        'id_order' => $randomString
    ));
    $type = "extend_user";
    $status = "paid";
    $extend_json = json_encode($extend);
    $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'],$extend_json, $status);
    $stmt->execute();
    $stmt->close();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $id_invoice,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'renewal',
        'name_product' => $prodcut['name_product'],
        'amount' => $pricelastextend,
        'source' => 'bot',
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 2), null, 'html');
        $scorenew = $user['score'] + 2;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $keyboardextendfnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $priceproductformat = number_format($pricelastextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $rxFmtProdcutPriceExtend = rxFormatToman($prodcut['price_product']);
    if (!empty($extend['queued'])) {
        $rxQueuedSuccessTpl = $datatextbot['dyn_renewconfirm_queued_success'] ?? '✅ سرویس خریداری شده رزرو شد و به محض پایان سرویس فعلی فعال می‌گردد.';
        $textextend = "$rxQueuedSuccessTpl

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
    } else {
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
    }
    sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}</blockquote>
<blockquote>▫️نام محصول : {$prodcut['name_product']}</blockquote>
<blockquote>▫️حجم محصول : {$prodcut['Volume_constraint']}</blockquote>
<blockquote>▫️زمان محصول : {$prodcut['Service_time']}</blockquote>
<blockquote>▫️مبلغ تمدید : {$rxFmtProdcutPriceExtend} تومان</blockquote>
<blockquote>▫️موجودی قبل از خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد از خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('renewal', [
            'user_id'    => $from_id,
            'amount'     => $prodcut['name_product'],
            'price'      => $priceproductformat,
            'panel_name' => $nameloc['Service_location'] ?? '',
            'category'   => $prodcut['category'] ?? '',
        ], $setting);
    }
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changelink on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changelink',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "disabled" || $DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_link_change_service_disabled'] ?? "❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.", null, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['changelink']['confirm'], 'callback_data' => "confirmchange_" . $nameloc['id_invoice']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['warnchange'], $keyboardextend);
} elseif (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchange on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchange',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, '❌ خطایی در تغییر لینک رخ داده است.', null, 'HTML');
        return;
    }
    $textconfig = "✅ کانفیگ شما با موفقیت بروزرسانی گردید.";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $DataUserOut['subscription_url'];
        $textconfig .= "اشتراک شما : <code>$output_config_link</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if ($marzban_list_get['config'] == "onconfig") {
        Editmessagetext($from_id, $message_id, $textconfig, keyboard_config($DataUserOut['configs'], $nameloc['id_invoice'], true));
    } else {
        Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات تغییر لینک در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️زمان تغییر لینک : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
        ]);
    }
} elseif (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_volume on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_volume',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_unavailable_panel'] ?? "❌ امکان خرید حجم اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = " ⭕️ مقدار حجمی که میخواهید خریداری کنید را ارسال کنید.
❌ مبلغ را به انگلیسی ارسال نمایید.
        ⚠️ هر گیگ  حجم اضافه " . rxFormatToman($extrapricevalue) . " تومان  است.";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('getvolumeextra', $from_id);
} elseif ($user['step'] == "getvolumeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $__volSourceProduct = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__volCategory = is_array($__volSourceProduct) ? (string)($__volSourceProduct['category'] ?? '') : '';
    $__discEligibleVolume = MiniDiscount::hasEligible('volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory, $user);
    $__volumeRows = [
        [
            ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $extrapricevalue * $text],
        ],
    ];
    if ($__discEligibleVolume) {
        $__volumeRows[] = [
            ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discountvolume-' . $extrapricevalue * $text],
        ];
    }
    $keyboardsetting = json_encode(['inline_keyboard' => $__volumeRows]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید حجم اضافه برای شما ایجاد شد.

📌 تعرفه هر گیگابایت حجم اضافه : $extrapricevalues تومان
🔋 حجم اضافه درخواستی : $text گیگابایت
💰 مبلغ فاکتور شما : $priceextra تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discountvolume-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "prevol_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountvolume', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountvolume") {
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseVol = (strpos($__pv4, 'prevol_') === 0) ? intval(substr($__pv4, 7)) : 0;
    if ($__baseVol <= 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__volSourceProduct2 = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__volCategory2 = is_array($__volSourceProduct2) ? (string)($__volSourceProduct2['category'] ?? '') : '';
    $dv = MiniDiscount::validateSell($text, 'volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory2, $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__discounted = (int)round(MiniDiscount::applyToPrice($dv['row'], $__baseVol));
    if ($__discounted < 0) $__discounted = 0;
    update("user", "Processing_value_four", "disv_" . $text . "_" . $__baseVol . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseVol, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextradiscount-' . $__baseVol],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextra(discount)?-(\w+)/', $datain, $dataget)) {
    $volume = $dataget[2];
    $__volDiscountCode = '';
    $__volCharge = (float)$volume;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'disv_') === 0) {
            $__pp = explode('_', $__pv4);
            $__volDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__volCharge = (float)$__pp[3];
        }
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $__volCharge && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuevolume = intval($volume) / intval($extrapricevalue);
            if (intval($user['pricediscount']) != 0) {
                $result = ($__volCharge * $user['pricediscount']) / 100;
                $__volCharge = $__volCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $__volCharge - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
            step('get_step_payment', $from_id);
            update("user", "Processing_value_one", "{$nameloc['username']}%{$valuevolume}", "id", $from_id);
            update("user", "Processing_value_tow", "getextravolumeuser", "id", $from_id);
            if ($__volDiscountCode !== '') {
                update("user", "Processing_value_four", "dvol|" . $__volDiscountCode . "|" . $volume, "id", $from_id);
            }
            return;
        }
    }
    deletemessage($from_id, $message_id);
    $volumepricelast = $__volCharge;
    if (intval($user['pricediscount']) != 0) {
        $result = ($__volCharge * $user['pricediscount']) / 100;
        $volumepricelast = $__volCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volumepricelast) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $__allowNegVx = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__chargedVxUser = false;
    if ($volumepricelast > 0) {
        $__chargeVx = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $volumepricelast, $__allowNegVx) : ['ok' => false];
        if (empty($__chargeVx['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeVx['new_balance'];
        $__chargedVxUser = true;
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $volumepricelast, 'service_action', 'خرید حجم اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'volume_value' => intval($volume) / intval($extrapricevalue),
        'priceـper_gig' => $extrapricevalue,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $data_limit = intval($volume) / intval($extrapricevalue);
    $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $data_limit);
    if ($extra_volume['status'] == false) {
        if ($__chargedVxUser && function_exists('balance_atomic_credit')) {
            $__refundedVx = balance_atomic_credit($from_id, $volumepricelast);
            if ($__refundedVx && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $volumepricelast, 'refund', 'بازگشت وجه خرید حجم اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
        }
        $extra_volume['msg'] = json_encode($extra_volume['msg']);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $nameloc['id_invoice'] ?? null,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'volume',
        'name_product' => $nameloc['name_product'] ?? null,
        'amount' => $volumepricelast,
        'source' => 'bot',
    ]);
    if ($__volDiscountCode !== '') {
        MiniDiscount::markSellUsed($__volDiscountCode, $user);
        MiniDiscount::logOrderDiscount([
            'id_user' => $from_id,
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'code' => $__volDiscountCode,
            'kind' => 'sell',
            'price_before' => $volume,
            'discount_amount' => (float)$volume - (float)$volumepricelast,
            'price_after' => $volumepricelast,
            'section' => 'volume',
        ]);
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volumepricelast,
        ':output' => json_encode($extra_volume),
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($volumepricelast, 0);
    $volumes = $volume / $extrapricevalue;
    $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس  : {$nameloc['username']}
▫️حجم اضافه : $volumes گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
    sendmessage($from_id, $textvolume, $keyboardextrafnished, 'HTML');
    $text_report = "⭕️ یک کاربر حجم اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 حجم خریداری شده  : $volumes گیگ</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>
<blockquote>موجودی کاربر قبل خرید : " . rxFormatToman($user['Balance']) . "</blockquote>
";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/changeloc_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    if ($user['limitchangeloc'] > $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changeloc on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changeloc',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || $DataUserOut['status'] == "disabled") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_userschange);
} elseif (preg_match('/changelocselectlo-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_one", $dataget[1], "id", $from_id);
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $userlimitlast = $limitchangeloc['all'] - $user['limitchangeloc'];
    $userlimitlastfree = $limitchangeloc['free'] - $user['limitchangeloc'];
    if ($userlimitlastfree < 0)
        $userlimitlastfree = 0;
    $Pricechange = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['priceChangeloc'];
    $textchange = "📍 با  تایید کردن انتقال موقعیت سرویس شما در این موقعیت حذف و به موقعیت جدید منتقل خواهد شد.
💰 هزینه انتقال " . rxFormatToman($Pricechange) . " تومان می باشد
📌 محدودیت باقی مانده شما : $userlimitlast عدد (تعداد محدودیت رایگان باقی مانده :‌$userlimitlastfree عدد)

✅ برای تایید انتقال روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['change-location']['confirm'], 'callback_data' => "confirmchangeloccha_" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textchange, $keyboardextend);
} elseif (preg_match('/confirmchangeloccha_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchangeloccha on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchangeloccha',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $marzban_list_get_new = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $limitfree = true;
    if ($user['limitchangeloc'] < $limitchangeloc['free'] and intval($setting['statuslimitchangeloc']) == 1) {
        $limitfree = false;
    }
    if ($user['limitchangeloc'] >= $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    if ($marzban_list_get_new['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    if ($marzban_list_get_new == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_generic_restart_process'] ?? "❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید", null, 'html');
        return;
    }
    $Pricechange = $marzban_list_get_new['priceChangeloc'];
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $prodcut['code_product'] = "🛍 حجم دلخواه";
        $product['inbounds'] = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND name_product = :name_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            'name_product' => $nameloc['name_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product['inbounds'] != null) {
        $marzban_list_get_new['inboundid'] = $prodcut['inbounds'];
    }
    if ($marzban_list_get_new['type'] == "Manualsale" && $marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        sendmessage($from_id, $datatextbot['dyn_errors_transfer_panel_unavailable'] ?? "❌ امکان انتقال به پنل وجود ندارد.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_transfer_unused_config_only'] ?? "❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] != "active") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($limitfree == false) {
        $Pricechange = 0;
    }
    if ($user['Balance'] < $Pricechange && $user['agent'] != "n2" && $limitfree) {
        $marzbandirectpay = panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($Pricechange * $user['pricediscount']) / 100;
                $Pricechange = $Pricechange - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($Pricechange) != 0) {
                $Balance_prim = $Pricechange - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                step('get_step_payment', $from_id);
                return;
            }
        }
    }
    if (intval($user['pricediscount']) != 0 and intval($Pricechange) != 0) {
        $result = ($Pricechange * $user['pricediscount']) / 100;
        $Pricechange = $Pricechange - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $Pricechange) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $value = json_encode(array(
        "old_panel" => $marzban_list_get['code_panel'],
        "new_panel" => $marzban_list_get_new['code_panel'],
        "volume" => $DataUserOut['data_limit'],
        "used_traffic" => $DataUserOut['used_traffic'],
        "expire" => $DataUserOut['expire'],
        "stateus" => $DataUserOut['status']
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $type = "change_location";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product']);
    $stmt->execute();
    $stmt->close();
    if ($DataUserOut['data_limit'] == 0 || $DataUserOut['data_limit'] == null) {
        $data_limit = 0;
    } else {
        $data_limit = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    }
    $datac = array(
        'expire' => $DataUserOut['expire'],
        'data_limit' => $data_limit,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest',
        'ip_limit' => intval($nameloc['ip_limit'] ?? 0),
    );
    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";
    if ($marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
    } else {
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "خطا هنگام تغییر موقعیت سرویس
<blockquote>دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام پنل مقصد : {$marzban_list_get_new['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    }
    $output_config_link = "";
    if ($marzban_list_get_new['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get_new['config'] == "onconfig") {
        if (is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $configs) {
                $output_config_link .= "\n" . $configs;
            }
        }
    }
    $limitnew = $user['limitchangeloc'] + 1;
    update("user", "limitchangeloc", $limitnew, "id", $from_id);
    $textchangeloc = "✅ کانفیگ شما باموفقیت به سرور ({$marzban_list_get_new['name_panel']}) انتقال یافت.

🖥 نام سرویس : {$nameloc['username']}
💠 حجم سرویس : $RemainingVolume
⏳ زمان انقضا :  $expirationDate | $day

🔗 لینک اشتراک شما:

<code>$output_config_link</code>";
    if (intval($Pricechange) != 0) {
        $__allowNegPc = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargePc = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $Pricechange, $__allowNegPc) : ['ok' => false];
        if (empty($__chargePc['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargePc['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $Pricechange, 'service_action', 'تغییر موقعیت سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    }
    update("invoice", "Service_location", $marzban_list_get_new['name_panel'], "username", $nameloc['username']);
    if ($marzban_list_get_new['inboundid'] != null) {
        update("invoice", "inboundid", $marzban_list_get_new['inboundid'], "username", $nameloc['username']);
    }
    Editmessagetext($from_id, $message_id, $textchangeloc, $keyboardextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $format_byte = formatBytes($data_limit);
    $textreport = "
تغییر موقعیت سرویس

<blockquote>🔻آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>🔻نام کاربری : @$username</blockquote>
<blockquote>🔻نام پنل قدیم : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>🔻نام پنل جدید : {$marzban_list_get_new['name_panel']}</blockquote>
<blockquote>🔻 نام کاربری مشتری در پنل  :{$nameloc['username']}</blockquote>
<blockquote>🔻حجم نهایی سرویس : $format_byte</blockquote>
<blockquote>🔻موجودی کاربر : $balanceformatsell تومان</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/disorder-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler disorder on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'disorder',
            ]);
        }
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ علت اختلال خود را بنویسید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textdisorder, $keyboarddisorder);
    step("getdesdisorder", $from_id);
} elseif ($user['step'] == "getdesdisorder") {
    update("user", "Processing_value", $text, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ آیا از ارسال گزارش اختلال اطمینان دارید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ ارسال گزارش اختلال", 'callback_data' => "confirmdisorders-" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    sendmessage($from_id, $textdisorder, $keyboarddisorder, 'html');
    step("home", $from_id);
} elseif (preg_match('/confirmdisorders-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmdisorders on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmdisorders',
            ]);
        }
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    $textdisorder = "
    ⚠️ یک کاربر با اطلاعات زیر یک گزارش اختلال در سرویس ثبت کرده است .

- نام کاربری : @$username
- آیدی عددی : $from_id
- نام کاربری کانفیگ : {$nameloc['username']}
- نام پلن تهیه شده : {$nameloc['name_product']}
- موقعیت سرویس : {$nameloc['Service_location']}
- توضیحات اختلال : {$user['Processing_value']}";
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $bExp3 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl3 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt3 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp3 > 0 && $bExp3 <= time()) {
        $status = 'expired';
    } elseif ($bDl3 > 0.0 && $bUt3 >= $bDl3) {
        $status = 'limited';
    }
    $status_map3 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map3[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $textdisorder .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
    foreach ($admin_ids as $admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        sendmessage($admin, $textdisorder, $Response, 'html');
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_request_thanks'] ?? "✅ با تشکر از ثبت درخواست ،درخواست شما  ارسال شده و درحال بررسی توسط پشتیبانی می باشد.", $bakinfos, 'html');
} elseif (preg_match('/Extra_time_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_time on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_time',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_unavailable_panel'] ?? "❌ امکان خرید زمان اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = "📆 تعداد روز اضافه مورد نظر را وارد کنید ( برحسب روز ) :

📌 تعرفه هر روز:  $extratimepricevalue";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('gettimeextra', $from_id);
} elseif ($user['step'] == "gettimeextra") {
    if (!ctype_digit($text) || $text < 1) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextratime = $extratimepricevalue * $text;
    $__timeSourceProduct = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__timeCategory = is_array($__timeSourceProduct) ? (string)($__timeSourceProduct['category'] ?? '') : '';
    $__discEligibleTime = MiniDiscount::hasEligible('time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory, $user);
    $__timeRows = [
        [
            ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $extratimepricevalue * $text],
        ],
    ];
    if ($__discEligibleTime) {
        $__timeRows[] = [
            ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discounttime-' . $extratimepricevalue * $text],
        ];
    }
    $keyboardsetting = json_encode(['inline_keyboard' => $__timeRows]);
    $priceextratime = number_format($priceextratime, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید زمان اضافه برای شما ایجاد شد.

📌 تعرفه هر روز زمان اضافه : $extratimepricevalue تومان
📆 تعداد روز اضافه درخواستی : $text روز
💰 مبلغ فاکتور شما : $priceextratime تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discounttime-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "pretime_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscounttime', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscounttime") {
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseTime = (strpos($__pv4, 'pretime_') === 0) ? intval(substr($__pv4, 8)) : 0;
    if ($__baseTime <= 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__timeSourceProduct2 = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__timeCategory2 = is_array($__timeSourceProduct2) ? (string)($__timeSourceProduct2['category'] ?? '') : '';
    $dv = MiniDiscount::validateSell($text, 'time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory2, $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__discounted = (int)round(MiniDiscount::applyToPrice($dv['row'], $__baseTime));
    if ($__discounted < 0) $__discounted = 0;
    update("user", "Processing_value_four", "distime_" . $text . "_" . $__baseTime . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseTime, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratimediscount-' . $__baseTime],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextratime(discount)?-(\w+)/', $datain, $dataget)) {
    $tmieextra = $dataget[2];
    $__timeDiscountCode = '';
    $__timeCharge = (float)$tmieextra;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'distime_') === 0) {
            $__pp = explode('_', $__pv4);
            $__timeDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__timeCharge = (float)$__pp[3];
        }
    }
    $pricelasttime = $__timeCharge;
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $__timeCharge && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuetime = $tmieextra / $extratimepricevalue;
            if (intval($user['pricediscount']) != 0) {
                $result = ($__timeCharge * $user['pricediscount']) / 100;
                $pricelasttime = $__timeCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($pricelasttime) != 0) {
                $Balance_prim = $pricelasttime - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                step('get_step_payment', $from_id);
                update("user", "Processing_value_one", "{$nameloc['username']}%{$valuetime}", "id", $from_id);
                update("user", "Processing_value_tow", "getextratimeuser", "id", $from_id);
                if ($__timeDiscountCode !== '') {
                    update("user", "Processing_value_four", "dtime|" . $__timeDiscountCode . "|" . $tmieextra, "id", $from_id);
                }
                return;
            }
        }
    }
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0 and intval($pricelasttime) != 0) {
        $result = ($__timeCharge * $user['pricediscount']) / 100;
        $pricelasttime = $__timeCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $Balance_Low_user = $user['Balance'] - $pricelasttime;
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if ($Balance_Low_user < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }

    $__allowNegEt = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    if ($pricelasttime > 0) {
        $__chargeEt = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $pricelasttime, $__allowNegEt) : ['ok' => false];
        if (empty($__chargeEt['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEt['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $pricelasttime, 'service_action', 'خرید زمان اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $__chargedEtUser = true;
    update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
    $extratimeday = $tmieextra / $extratimepricevalue;
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'day' => $extratimeday,
        'priceـper_day' => $extratimeday,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $timeservice = $DataUserOut['expire'] - time();
    $day = floor($timeservice / 86400);
    $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $extratimeday);
    if ($extra_time['status'] == false) {
        $extra_time['msg'] = json_encode($extra_time['msg']);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_time['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }

        if (!empty($__chargedEtUser) && function_exists('balance_atomic_credit')) {
            $__refundedEt = balance_atomic_credit($from_id, $pricelasttime);
            if ($__refundedEt && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $pricelasttime, 'refund', 'بازگشت وجه زمان اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
        }
        return;
    }

    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $nameloc['id_invoice'] ?? null,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'time',
        'name_product' => $nameloc['name_product'] ?? null,
        'amount' => $pricelasttime,
        'source' => 'bot',
    ]);
    if ($__timeDiscountCode !== '') {
        MiniDiscount::markSellUsed($__timeDiscountCode, $user);
        MiniDiscount::logOrderDiscount([
            'id_user' => $from_id,
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'code' => $__timeDiscountCode,
            'kind' => 'sell',
            'price_before' => $tmieextra,
            'discount_amount' => (float)$tmieextra - (float)$pricelasttime,
            'price_after' => $pricelasttime,
            'section' => 'time',
        ]);
        update("user", "Processing_value_four", "", "id", $from_id);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_time_user";
    $output = json_encode($extra_time);
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $pricelasttime,
        ':output' => $output,
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($tmieextra);
    $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️زمان اضافه : $extratimeday روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
    sendmessage($from_id, $textextratime, $keyboardextrafnished, 'HTML');
    $volumes = $tmieextra / $extratimepricevalue;
    $text_report = "⭕️ یک کاربر زمان اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 زمان خریداری شده  : $volumes روز</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('time_extra', [
            'user_id' => $from_id,
            'amount'  => $volumes,
            'price'   => $volumesformat,
        ], $setting);
    }
} elseif (preg_match('/deletelist-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (is_array($nameloc) && (string) $nameloc['id_user'] === (string) $from_id) {
        if (function_exists('deleteInvoiceFromList')) {
            deleteInvoiceFromList($id_invoice, $from_id);
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :invoice_id AND id_user = :user_id");
            $stmtDelete->bindParam(':invoice_id', $id_invoice);
            $stmtDelete->bindParam(':user_id', $from_id);
            $stmtDelete->execute();
        }
        sendmessage($from_id, $datatextbot['dyn_errors_service_removed'] ?? "📌 سرویس از لیست شما حذف شد", null, 'html');
    } else {
        sendmessage($from_id, $datatextbot['dyn_errors_service_removal_unavailable'] ?? "❌ امکان حذف سرویس وجود ندارد.", null, 'html');
    }
} elseif (preg_match('/removeserviceuser_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeserviceuser on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeserviceuser',
            ]);
        }
        return;
    }
    savedata("clear", "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_reason_prompt'] ?? "📌 دلیل حذف سرویس خود را ارسال کنید.", $bakinfos);
    step("getdisdeleteconfig", $from_id);
} elseif ($user['step'] == "getdisdeleteconfig") {
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    savedata("save", "descritionsremove", $text);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorusertest'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        step("home", $from_id);
        return;
    }
    $requestcheck = select("cancel_service", "*", "username", $nameloc['username'], "count");
    if ($requestcheck != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorexits'], null, 'html');
        return;
    }
    $confirmremove = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅  درخواست حذف سرویس را دارم", 'callback_data' => "confirmremoveservices-$id_invoice"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['stateus']['descriptions_removeservice'], $confirmremove, "html");
    step("home", $from_id);
} elseif (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user = :from_id AND status = 'waiting'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $checkcancelservicecount = $stmt->rowCount();
    if ($checkcancelservicecount != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['exitsrequsts'], null, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmremoveservices on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmremoveservices',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $stmt = $connect->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
    $descriptions = "0";
    $Status = "waiting";
    $stmt->bind_param("ssss", $from_id, $nameloc['username'], $descriptions, $Status);
    $stmt->execute();
    $stmt->close();
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['panelNotConnected'], null, 'html');
        step('home', $from_id);
        return;
    }

    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);
    $status = $DataUserOut['status'];
    $bExp4 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl4 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt4 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp4 > 0 && $bExp4 <= time()) {
        $status = 'expired';
    } elseif ($bDl4 > 0.0 && $bUt4 >= $bDl4) {
        $status = 'limited';
    }
    $status_map4 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map4[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $textinfoadmin = "سلام ادمین 👋

📌 یک درخواست حذف سرویس  توسط کاربر برای شما ارسال شده است. لطفا بررسی کرده و در صورت درست بودن و موافقت تایید کنید.

📊 اطلاعات سرویس کاربر :
آیدی عددی کاربر : $from_id
نام کاربری کاربر : @$username
نام کاربری کانفیگ : {$nameloc['username']}
وضعیت سرویس : $status_var
موقعیت سرویس : {$nameloc['Service_location']}
کد سرویس:{$nameloc['id_invoice']}

🟢 اخرین زمان اتصال شما : $lastonline

📥 حجم مصرفی : $usedTrafficGb
♾ حجم سرویس : $LastTraffic
🪫 حجم باقی مانده : $RemainingVolume
📅 فعال تا تاریخ : $expirationDate ($day)

<b>❌ ادمین گرامی توجه داشته باشید دکمه حذف سرویس که میزنید ربات خودکار حساب میکند و احتمال اشتباه وجود دارد پیشنهاد می شود از  حذف دستی  استفاده نمایید</b>

دلیل حذف سرویس : {$userdata['descritionsremove']}";
    $confirmremoveadmin = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌حذف دستی", 'callback_data' => "remoceserviceadminmanual-{$nameloc['id_invoice']}"],
                ['text' => "❌حذف سرویس", 'callback_data' => "remoceserviceadmin-{$nameloc['id_invoice']}"],
                ['text' => "❌لغو حذف", 'callback_data' => "rejectremoceserviceadmin-{$nameloc['id_invoice']}"],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
        step("home", $admin);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['stateus']['sendrequestsremove'], $keyboard, 'html');
} elseif (preg_match('/transfer_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler transfer on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'transfer',
            ]);
        }
        return;
    }
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['transfornotvalid'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['discription'], $bakinfos);
    step("getidfortransfer", $from_id);
    update("user", "Processing_value_one", $nameloc['username'], "id", $from_id);
    update("user", "Processing_value_tow", $nameloc['id_invoice'], "id", $from_id);
} elseif ($user['step'] == "getidfortransfer") {
    if (!userExists($text)) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notusertrns'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $confirmtransfer = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید انتقال سرویس", 'callback_data' => "confrimtransfers_{$user['Processing_value_tow']}"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['transfor']['confirm'], $confirmtransfer, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/confrimtransfers_(\w+)/', $datain, $dataget)) {
    if ($from_id == $user['Processing_value_one']) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notsendserviceyou'], $keyboard, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confrimtransfers on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confrimtransfers',
            ]);
        }
        return;
    }
    update("invoice", "id_user", $user['Processing_value_one'], "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "backorder"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['confirmed'], $bakinfos);
    $texttransfer = "✅ کاربر گرامی  سرویس با نام کاربری {$nameloc['username']} از طرف کاربر با شناسه کاربری $from_id  به حساب کاربری شما منتقل گردید.";
    sendmessage($user['Processing_value_one'], $texttransfer, $keyboard, 'HTML');
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $value = $user['Processing_value_one'];
    $dateacc = date('Y/m/d H:i:s');
    $type = "transfertouser";
    $price = "0";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $price);
    $stmt->execute();
    $stmt->close();
} elseif (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest") {
    if (!check_active_btn($setting['keyboardmain'], "text_usertest")) {
        sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($locationproduct != 1) {
        if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
            sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
            step('get_number', $from_id);
        }
        if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
            return;
        if ($user['limit_usertest'] <= 0 && !in_array($from_id, $admin_ids)) {
            sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
            return;
        }
        sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_usertest, 'html');
    }
}
