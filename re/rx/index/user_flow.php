<?php

if (!defined('RX_MIN_TRANSFER_AMOUNT')) {
    define('RX_MIN_TRANSFER_AMOUNT', 1000);
}

if (!function_exists('tk_miniapp_ticket_mode_active')) {
    function tk_miniapp_ticket_mode_active()
    {
        global $setting;
        return (string)($setting['miniapp_ticket_mode'] ?? '0') === '1';
    }
}

if (!function_exists('tk_send_miniapp_redirect')) {
    function tk_send_miniapp_redirect($from_id, $message_id, $user)
    {
        global $datatextbot, $domainhosts;
        $text = trim((string)($datatextbot['miniapp_ticket_suggest'] ?? ''));
        if ($text === '') { $text = '🎫 ارسال و پیگیری تیکت‌های پشتیبانی فقط از طریق مینی‌اپ امکان‌پذیر است.'; }
        $first = trim((string)($user['first_name'] ?? ''));
        $text = str_replace('{first_name}', $first !== '' ? $first : 'کاربر', $text);
        $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
        $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => '🚀 باز کردن مینی‌اپ', 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets']]]]], JSON_UNESCAPED_UNICODE) : null;
        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }
}

if (!function_exists('tk_album_send')) {
    function tk_album_send($chat, $topic, $media)
    {
        if (!is_array($media) || count($media) === 0) return;
        if (count($media) === 1) {
            $m = $media[0];
            $p = ['chat_id' => $chat, $m['type'] => $m['file_id']];
            if ($topic) $p['message_thread_id'] = $topic;
            telegram($m['type'] === 'video' ? 'sendvideo' : 'sendphoto', $p);
            return;
        }
        $arr = [];
        foreach ($media as $m) { $arr[] = ['type' => $m['type'], 'media' => $m['file_id']]; }
        $p = ['chat_id' => $chat, 'media' => $arr];
        if ($topic) $p['message_thread_id'] = $topic;
        telegram('sendMediaGroup', $p);
    }
}

$tkCancelKb = json_encode(['inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE);

if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest")) {
    $rxTestIsAdmin = in_array($from_id, $admin_ids);
    $userlimit = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    if (!is_array($userlimit) || empty($userlimit)) {
        $userlimit = $user;
    }
    if (!rx_test_feature_enabled()) {
        sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $rxTestGate = rx_test_verification_gate($userlimit, $rxTestIsAdmin);
    if ($rxTestGate === 'phone') {
        if ($user['step'] != "get_number") {
            sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
            step('get_number', $from_id);
        }
        return;
    }
    if ($rxTestGate === 'verify') {
        sendmessage($from_id, $datatextbot['dyn_testaccount_verify_required'] ?? "⚠️ حساب شما هنوز احراز هویت نشده است.", null, 'HTML');
        return;
    }
    $rxTestCode = null;
    if (preg_match('/^locationtest_(.+)$/', (string) $datain, $rxTestMatch)) {
        $rxTestCode = $rxTestMatch[1];
    } elseif ($user['step'] == "createusertest") {
        $rxTestCode = (string) $user['Processing_value_one'];
    }
    $rxTestEligible = rx_test_eligible_panels($userlimit);
    if ($rxTestCode === null && count($rxTestEligible) !== 1) {
        return;
    }
    $marzban_list_get = rx_test_resolve_panel($userlimit, $rxTestCode, $rxTestEligible);
    if ($marzban_list_get === null) {
        if ($user['step'] == "createusertest") {
            step('home', $from_id);
        }
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    $rxTestSettings = rx_test_panel_settings($marzban_list_get);
    $rxTestQuota = rx_test_quota_state($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin);
    if (!$rxTestQuota['can_create']) {
        if ($user['step'] == "createusertest") {
            step('home', $from_id);
        }
        sendmessage($from_id, $rxTestQuota['reason'] === 'audience_restricted' ? rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    $location = $marzban_list_get['code_panel'];
    $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        if ($user['step'] != "createusertest") {
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $location, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_usernameKb, 'html');
            return;
        }
    }
    if ($user['step'] == "createusertest") {
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
    } else {
        deletemessage($from_id, $message_id);
    }
    if (!rx_test_manual_stock_available($marzban_list_get)) {
        step('home', $from_id);
        sendmessage($from_id, faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
        return;
    }
    $rxTestReserveError = '';
    $rxTestReservation = rx_test_reserve($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin, 'bot', $rxTestReserveError);
    if ($rxTestReservation === null) {
        step('home', $from_id);
        if ($rxTestReserveError === 'panel_unavailable') {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
            return;
        }
        sendmessage($from_id, $rxTestReserveError === 'audience_restricted' ? rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    $randomString = bin2hex(random_bytes(4));
    $text = strtolower((string) $text);
    try {
        $username_ac = rx_test_build_username($userlimit, $marzban_list_get, $text, $randomString);
    } catch (Throwable $rxTestErr) {
        rx_test_reservation_update($rxTestReservation, 'failed');
        rx_test_release($rxTestReservation);
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        return;
    }
    $rxTestProvision = rx_test_provision($userlimit, $marzban_list_get, $rxTestSettings, $rxTestReservation, $username_ac, $randomString);
    if (empty($rxTestProvision['ok'])) {
        step('home', $from_id);
        if ($rxTestProvision['error'] === 'stock_empty') {
            sendmessage($from_id, faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
            return;
        }
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $rxTestErrReason = htmlspecialchars((string) $rxTestProvision['msg'], ENT_QUOTES, 'UTF-8');
        $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
<blockquote>✍️ دلیل خطا :
{$rxTestErrReason}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
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
    $dataoutput = $rxTestProvision['output'];
    $username_ac = (string) ($rxTestProvision['username'] ?? $username_ac);
    rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
    $output_config_link = "";
    $config = "";
    $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }

    $usertestinfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    if ($marzban_list_get['type'] == "WGDashboard" || trim((string) ($datatextbot['textaftertext'] ?? '')) === "") {
        $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    $usertest_day = rx_test_display_time($rxTestSettings);
    $usertest_volume = rx_test_display_volume($rxTestSettings);
    $textcreatuser = str_replace('{username}', guardDisplayUsername($dataoutput['username'], $marzban_list_get), $datatextbot['textaftertext']);
    $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $usertest_day, $textcreatuser);
    $textcreatuser = preg_replace('/\{volume\}[ \t\x{200c}]*(?:گیگابایت|گیگ|GB|مگابایت|مگ|MB)?/iu', $usertest_volume, $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    $rxTestDelivered = true;
    try {
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString);
    } catch (Throwable $rxTestErr) {
        $rxTestDelivered = false;
        error_log('[usertest] delivery failed: ' . $rxTestErr->getMessage());
    }
    rx_test_mark_delivery($rxTestReservation, $rxTestDelivered);
    if (!$rxTestDelivered) {
        sendmessage($from_id, $datatextbot['dyn_testaccount_delivery_failed_saved'] ?? "⚠️ اکانت تست ساخته شد اما ارسال جزئیات آن ناموفق بود. اطلاعات سرویس در بخش «سرویس‌های من» در دسترس است.", null, 'HTML');
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    step('home', $from_id);
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $rxTestReportTime = !empty($rxTestSettings['time_unlimited']) ? rx_test_unlimited_label() : rx_test_stored_hours($rxTestSettings) . " ساعت";
    $rxTestReportVolume = !empty($rxTestSettings['volume_unlimited']) ? rx_test_unlimited_label() : rx_test_stored_mb($rxTestSettings) . " MB";
    $text_report = "📣 جزئیات ساخت اکانت تست در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️زمان خریداری شده : {$rxTestReportTime}</blockquote>
<blockquote>▫️حجم خریداری شده : {$rxTestReportVolume}</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (($text !== '' && $text == $datatextbot['text_help']) || $datain == "helpbtn" || $datain == "helpbtns" || $text == "/help" || $text == "help") {
    if (!check_active_btn($setting['keyboardmain'], "text_help")) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    if ($setting['categoryhelp'] == "1") {
        $rxSelectCategoryTxt = $datatextbot['dyn_tickets_select_category'] ?? "📌 یک دسته را انتخاب نمایید";
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
        } else {
            sendmessage($from_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
        }
    } else {
        $helplist = select("help", "*", null, null, "fetchAll");
        $helpidos = ['inline_keyboard' => []];
        foreach ($helplist as $result) {
            $helpidos['inline_keyboard'][] = [
                ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
            ];
        }
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        $helpidos['inline_keyboard'][] = [
            rx_kb_style(['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s),
        ];
        $json_list_help = json_encode($helpidos);
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        } else {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        }
    }
} elseif (preg_match('/^helpctgoryـ(.*)/', $datain, $dataget)) {
    $helplist = select("help", "*", "category", $dataget[1], "fetchAll");
    $helpidos = ['inline_keyboard' => []];
    foreach ($helplist as $result) {
        $helpidos['inline_keyboard'][] = [
            ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
        ];
    }
    $helpidos['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "helpbtns"],
    ];
    $json_list_help = json_encode($helpidos);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
} elseif (preg_match('/^helpos_(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns"],
            ]
        ]
    ]);
    $helpid = $dataget[1];
    $helpdata = select("help", "*", "id", $helpid, "select");
    if ($helpdata !== false) {
        $helpAppLinkRow = [];
        if (!empty($helpdata['app_link'])) {
            $helpAppLinkRow[] = [
                ['text' => $helpdata['app_title'] ?: 'دانلود برنامه', 'url' => $helpdata['app_link']],
            ];
        }
        if (strlen($helpdata['Media_os']) != 0) {
            if ($helpdata['type_Media_os'] == "video") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendvideo', [
                    'chat_id' => $from_id,
                    'video' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "document") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendDocument', [
                    'chat_id' => $from_id,
                    'document' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "photo") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            $backinfoss = json_encode([
                'inline_keyboard' => array_merge($helpAppLinkRow, [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns"],
                    ]
                ])
            ]);
            sendmessage($from_id, $helpdata['Description_os'], $backinfoss, 'HTML');
        }
    }
} elseif (($text !== '' && $text == $datatextbot['text_support']) || $datain == "supportbtns" || $text == "/support") {
    if (!check_active_btn($setting['keyboardmain'], "text_support")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain == "supportbtns") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $supportoption);
    } else {
        sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $supportoption, 'HTML');
    }
} elseif ($datain == "support" || $datain == "supporttickets") {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $myt = $pdo->prepare("SELECT Tracking, name_departman, MAX(id) lid, MAX(time) lt, MAX(status) st FROM support_message WHERE iduser = ? GROUP BY Tracking, name_departman ORDER BY lid DESC LIMIT 20");
    $myt->execute([$from_id]);
    $ticketmenu = ['inline_keyboard' => []];
    foreach ($myt->fetchAll(PDO::FETCH_ASSOC) as $rowt) {
        $stt = $rowt['st'] == 'close' ? '🔒' : '🟢';
        $ticketmenu['inline_keyboard'][] = [['text' => $stt . ' ' . $rowt['name_departman'] . ' | #' . $rowt['Tracking'], 'callback_data' => 'ticketopen_' . $rowt['Tracking']]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $ticketmenu['inline_keyboard'][] = [['text' => '➕ تیکت جدید', 'callback_data' => 'ticketnew']];
    $ticketmenu['inline_keyboard'][] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)];
    $ticketmenu = json_encode($ticketmenu, JSON_UNESCAPED_UNICODE);
    $tickethead = "🎫 تیکت‌های پشتیبانی شما\n\nیک تیکت را برای ادامه انتخاب کنید یا تیکت جدید بسازید.";
    if (!empty($datain) && intval($message_id) > 0) {
        Editmessagetext($from_id, $message_id, $tickethead, $ticketmenu);
    } else {
        sendmessage($from_id, $tickethead, $ticketmenu, 'HTML');
    }
} elseif ($datain == "ticketnew") {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_select_department'] ?? "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.", $list_departman, 'HTML');
} elseif (preg_match('/^departman_(.*)/', $datain, $dataget)) {
    $iddeparteman = $dataget[1];
    savedata("clear", "iddeparteman", $iddeparteman);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_subject'] ?? "📝 موضوع تیکت را وارد نمایید (مثلاً: مشکل در اتصال):", $backuser, 'HTML');
    step("getticketsubj", $from_id);
} elseif ($datain == "tk_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    step("home", $from_id);
    $rxTkCanceledTxt = $datatextbot['dyn_tickets_operation_canceled'] ?? "❌ عملیات لغو شد.";
    if (intval($message_id) > 0) {
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        Editmessagetext($from_id, $message_id, $rxTkCanceledTxt, json_encode(['inline_keyboard' => [[rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
    } else {
        sendmessage($from_id, $rxTkCanceledTxt, $keyboard, 'HTML');
    }
} elseif ($user['step'] == "getticketsubj" && (string)$text !== '') {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "subject", mb_substr(trim((string)$text), 0, 120, 'UTF-8'));
    savedata("save", "ctx", "create");
    savedata("save", "media", []);
    step("tk_media_choice", $from_id);
    $rxTkMediaYes = $datatextbot['dyn_tickets_media_yes'] ?? "🖼 بله";
    $rxTkMediaNo = $datatextbot['dyn_tickets_media_no'] ?? "✏️ فقط متن";
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_new'] ?? "📎 می‌خواهید همراه تیکت عکس/ویدیو هم بفرستید؟", json_encode(['inline_keyboard' => [[['text' => $rxTkMediaYes, 'callback_data' => 'tk_media_yes'], ['text' => $rxTkMediaNo, 'callback_data' => 'tk_media_no']], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif ($user['step'] == "getticketsubj") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_subject_text'] ?? "❌ لطفاً موضوع تیکت را به‌صورت متن وارد کنید.", null, 'HTML');
    return;
} elseif ($datain == "tk_media_no" && $user['step'] == "tk_media_choice") {
    if (!isset($update['callback_query'])) { return; }
    step("tk_desc", $from_id);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_body_text_short'] ?? "✍️ متن پیام خود را بنویسید:", $tkCancelKb);
} elseif ($datain == "tk_media_yes" && $user['step'] == "tk_media_choice") {
    if (!isset($update['callback_query'])) { return; }
    step("tk_media_count", $from_id);
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_media_count'] ?? "🔢 چند رسانه می‌خواهید بفرستید؟ (۱ تا ۵)", json_encode(['inline_keyboard' => [[
        ['text' => '۱', 'callback_data' => 'tk_cnt_1'],
        ['text' => '۲', 'callback_data' => 'tk_cnt_2'],
        ['text' => '۳', 'callback_data' => 'tk_cnt_3'],
        ['text' => '۴', 'callback_data' => 'tk_cnt_4'],
        ['text' => '۵', 'callback_data' => 'tk_cnt_5'],
    ], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE));
} elseif (preg_match('/^tk_cnt_([1-5])$/', $datain, $dataget) && $user['step'] == "tk_media_count") {
    if (!isset($update['callback_query'])) { return; }
    savedata("save", "target", (int)$dataget[1]);
    savedata("save", "media", []);
    step("tk_collect", $from_id);
    $rxTkUploadTpl = $datatextbot['dyn_tickets_ask_media_upload'] ?? "📤 رسانهٔ ۱ از %s را بفرستید (فقط عکس یا ویدیو).";
    Editmessagetext($from_id, $message_id, sprintf($rxTkUploadTpl, $dataget[1]), $tkCancelKb);
} elseif (($user['step'] == "tk_media_choice" || $user['step'] == "tk_media_count") && empty($datain)) {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_choice'] ?? "❌ لطفاً از دکمه‌های بالا انتخاب کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
    return;
} elseif ($user['step'] == "tk_collect") {
    global $update;
    $mm = $update['message'] ?? [];
    $badMedia = isset($mm['animation']) || isset($mm['sticker']) || isset($mm['document']) || isset($mm['voice']) || isset($mm['audio']) || isset($mm['forward_date']) || isset($mm['forward_origin']) || isset($mm['forward_from']);
    if ($badMedia || (!$photo && !$video)) {
        sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_type'] ?? "❌ فقط عکس یا ویدیوی معمولی مجاز است (گیف/استیکر/فوروارد/فایل/ویس رد می‌شود). دوباره بفرستید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
        return;
    }
    $ud = json_decode($user['Processing_value'], true);
    if (!is_array($ud)) $ud = [];
    $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
    $media[] = $photo ? ['type' => 'photo', 'file_id' => $photoid] : ['type' => 'video', 'file_id' => $videoid];
    savedata("save", "media", $media);
    $target = (int)($ud['target'] ?? 1);
    if (count($media) < $target) {
        $nx = count($media) + 1;
        $rxTkReceivedTpl = $datatextbot['dyn_tickets_media_received'] ?? "✅ دریافت شد (%s از %s). رسانهٔ بعدی را بفرستید.";
        sendmessage($from_id, sprintf($rxTkReceivedTpl, $nx, $target), $tkCancelKb, 'HTML');
        return;
    }
    step("tk_desc", $from_id);
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_body_text'] ?? "✍️ حالا متن پیام خود را بنویسید:", $tkCancelKb, 'HTML');
} elseif ($user['step'] == "tk_desc" && ((string)$text !== '' || $photo || $video)) {
    if (!isset($update['message']) && empty($text)) { return; }
    $ud = json_decode($user['Processing_value'], true);
    if (!is_array($ud)) $ud = [];
    $ctx = (string)($ud['ctx'] ?? 'create');
    $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
    $desc = (string)$text !== '' ? (string)$text : (string)$caption;
    $mediaTokens = '';
    foreach ($media as $mone) { $mediaTokens .= "\n[[{$mone['type']}:{$mone['file_id']}]]"; }
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    $reportTopic = select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
    $channel = (string)($setting['Channel_Report'] ?? '');
    $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
    if ($ctx === 'reply') {
        $idtraking = (string)($ud['t'] ?? '');
        $tkdetail = select("support_message", "*", "Tracking", $idtraking);
        if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
            sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
            update("user", "Processing_value", "0", "id", $from_id);
            step("home", $from_id);
            return;
        }
        $body = trim($desc . $mediaTokens);
        $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$idtraking, $tkdetail['idsupport'], $from_id, $tkdetail['name_departman'], $body, 'user', $time, 'Customerresponse']);
        update("support_message", "status", "Customerresponse", "Tracking", $idtraking);
        $textreport = "💬 پاسخ جدید کاربر در تیکت\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$tkdetail['name_departman']}\nکد پیگیری : <code>$idtraking</code>\nزمان : $timejalali\n\nمتن : $desc";
        $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $idtraking]]]], JSON_UNESCAPED_UNICODE);
        $pvTargets = [];
        $isp = (string)($tkdetail['idsupport'] ?? '');
        if ($isp !== '' && $isp !== '0') $pvTargets[$isp] = true;
        if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
        foreach (array_keys($pvTargets) as $tg) {
            sendmessage($tg, $textreport, $adminReplyKb, 'HTML');
            tk_album_send($tg, null, $media);
        }
        if ($channel !== '') {
            telegram('sendmessage', ['chat_id' => $channel, 'message_thread_id' => $reportTopic, 'text' => $textreport, 'parse_mode' => 'HTML']);
            tk_album_send($channel, $reportTopic, $media);
        }
        sendmessage($from_id, $datatextbot['dyn_tickets_reply_recorded'] ?? "✅ پیام شما ثبت شد و پس از بررسی پاسخ داده می‌شود.", $keyboard, 'HTML');
    } else {
        $departeman = select("departman", "*", "id", $ud['iddeparteman'] ?? 0, "select");
        if (!is_array($departeman)) $departeman = select("departman", "*", null, null, "select");
        $subject = trim((string)($ud['subject'] ?? ''));
        $randomString = bin2hex(random_bytes(4));
        $subjToken = $subject !== '' ? "[[subj:$subject]]\n" : "";
        $body = trim($subjToken . $desc . $mediaTokens);
        $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$randomString, $departeman['idsupport'], $from_id, $departeman['name_departman'], $body, 'user', $time, 'Unseen']);
        $textreport = "🎫 تیکت جدید پشتیبانی\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$departeman['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nکد پیگیری : <code>$randomString</code>\nزمان : $timejalali\n\nمتن : $desc";
        $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $randomString]]]], JSON_UNESCAPED_UNICODE);
        $pvTargets = [];
        $isp = (string)($departeman['idsupport'] ?? '');
        if ($isp !== '' && $isp !== '0') $pvTargets[$isp] = true;
        if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
        foreach (array_keys($pvTargets) as $tg) {
            sendmessage($tg, $textreport, $adminReplyKb, 'HTML');
            tk_album_send($tg, null, $media);
        }
        if ($channel !== '') {
            telegram('sendmessage', ['chat_id' => $channel, 'message_thread_id' => $reportTopic, 'text' => $textreport, 'parse_mode' => 'HTML']);
            tk_album_send($channel, $reportTopic, $media);
        }
        $rxTkCreatedTpl = $datatextbot['dyn_tickets_created'] ?? "✅ تیکت شما با کد پیگیری <code>%s</code> ثبت شد و پس از بررسی پاسخ داده می‌شود.";
        sendmessage($from_id, sprintf($rxTkCreatedTpl, $randomString), $keyboard, 'HTML');
    }
    update("user", "Processing_value", "0", "id", $from_id);
    step("home", $from_id);
} elseif ($user['step'] == "tk_desc") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_body_text'] ?? "❌ لطفاً متن پیام را وارد کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
    return;
} elseif (preg_match('/^ticketopen_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    $msgs = $pdo->prepare("SELECT * FROM support_message WHERE Tracking = ? ORDER BY id ASC");
    $msgs->execute([$idtraking]);
    $allmsgs = $msgs->fetchAll(PDO::FETCH_ASSOC);
    $subject = '';
    if (isset($allmsgs[0]['text']) && preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$allmsgs[0]['text'], $sm)) {
        $subject = trim($sm[1]);
    }
    $lines = '';
    $hasMedia = false;
    foreach ($allmsgs as $mrow) {
        $who = ($mrow['result'] === 'admin') ? '👨‍💻 پشتیبانی' : '👤 شما';
        if (preg_match('/\[\[(photo|video):[^\]]+\]\]/', (string)$mrow['text'])) $hasMedia = true;
        $bodyline = trim(preg_replace(['/\[\[(photo|video):[^\]]+\]\]/', '/\[\[subj:[^\]]*\]\]/u'], ['🖼 رسانه', ''], (string)$mrow['text']));
        if ($bodyline === '') { $bodyline = '—'; }
        $lines .= "$who:\n$bodyline\n— — —\n";
    }
    $isclosed = $tkdetail['status'] == 'close';
    $statelabel = $isclosed ? '🔒 بسته' : '🟢 باز';
    $tickethead = "🎫 تیکت <code>$idtraking</code>\nدپارتمان : {$tkdetail['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nوضعیت : $statelabel\n\n$lines";
    $kbrows = [];
    if ($hasMedia) {
        $kbrows[] = [['text' => '📎 مشاهده رسانه‌ها', 'callback_data' => 'tk_album_' . $idtraking]];
    }
    if (!$isclosed) {
        $kbrows[] = [['text' => '✍️ ارسال پیام', 'callback_data' => 'ticketmsg_' . $idtraking]];
        $kbrows[] = [['text' => '🔒 بستن تیکت', 'callback_data' => 'ticketclose_' . $idtraking]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $kbrows[] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)];
    Editmessagetext($from_id, $message_id, $tickethead, json_encode(['inline_keyboard' => $kbrows], JSON_UNESCAPED_UNICODE));
} elseif (preg_match('/^(?:ticketmsg|ticketreply)_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    if ($tkdetail['status'] == 'close') {
        sendmessage($from_id, $datatextbot['dyn_tickets_closed_locked'] ?? "🔒 این تیکت بسته است؛ تا زمانی که پشتیبانی پاسخ ندهد یا آن را باز نکند نمی‌توانید پیام ارسال کنید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value", json_encode(['ctx' => 'reply', 't' => $idtraking, 'media' => []], JSON_UNESCAPED_UNICODE), "id", $from_id);
    step("tk_media_choice", $from_id);
    $rxTkMediaYes = $datatextbot['dyn_tickets_media_yes'] ?? "🖼 بله";
    $rxTkMediaNo = $datatextbot['dyn_tickets_media_no'] ?? "✏️ فقط متن";
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_reply'] ?? "📎 می‌خواهید همراه پاسخ عکس/ویدیو هم بفرستید؟", json_encode(['inline_keyboard' => [[['text' => $rxTkMediaYes, 'callback_data' => 'tk_media_yes'], ['text' => $rxTkMediaNo, 'callback_data' => 'tk_media_no']], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif (preg_match('/^tk_album_(\w+)$/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $tkd = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkd) || (string)($tkd['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    $rws = $pdo->prepare("SELECT text FROM support_message WHERE Tracking = ? ORDER BY id ASC");
    $rws->execute([$idtraking]);
    $allMedia = [];
    foreach ($rws->fetchAll(PDO::FETCH_ASSOC) as $rr) {
        if (preg_match_all('/\[\[(photo|video):([^\]]+)\]\]/', (string)$rr['text'], $mmm, PREG_SET_ORDER)) {
            foreach ($mmm as $one) { $allMedia[] = ['type' => $one[1], 'file_id' => $one[2]]; }
        }
    }
    if (count($allMedia) === 0) {
        sendmessage($from_id, $datatextbot['dyn_tickets_no_media'] ?? "🖼 رسانه‌ای در این تیکت نیست.", null, 'HTML');
        return;
    }
    foreach (array_chunk($allMedia, 10) as $chunk) { tk_album_send($from_id, null, $chunk); }
} elseif (preg_match('/^ticketclose_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    update("support_message", "status", "close", "Tracking", $idtraking);
    $reportTopic = select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
    $closetext = "🔒 کاربر <a href=\"tg://user?id=$from_id\">$from_id</a> تیکت <code>$idtraking</code> را بست.";
    $adminpv = (string)($tkdetail['idsupport'] ?? '');
    $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
    $pvTargets = [];
    if ($adminpv !== '' && $adminpv !== '0') $pvTargets[$adminpv] = true;
    if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
    foreach (array_keys($pvTargets) as $tg) {
        sendmessage($tg, $closetext, null, 'HTML');
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $reportTopic, 'text' => $closetext, 'parse_mode' => 'HTML']);
    }
    $rxTkClosedTpl = $datatextbot['dyn_tickets_closed_confirm'] ?? "🔒 تیکت <code>%s</code> بسته شد. برای ارسال پیام جدید باید پشتیبانی پاسخ دهد یا آن را باز کند.";
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    Editmessagetext($from_id, $message_id, sprintf($rxTkClosedTpl, $idtraking), json_encode(['inline_keyboard' => [[rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
} elseif ($datain == "my_miniapp_pending") {
    $stmtMp = $pdo->prepare(
        "SELECT id_order, price FROM Payment_report
          WHERE id_user = :uid
            AND payment_Status = 'waiting'
            AND Payment_Method = 'cart to cart'
            AND source = 'miniapp'
            AND dec_not_confirmed = 'receipt-submitted'
          ORDER BY id DESC LIMIT 5"
    );
    $stmtMp->execute([':uid' => $from_id]);
    $mpRows = $stmtMp->fetchAll();
    if (empty($mpRows)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ هیچ رسید تایید نشده‌ای از مینی‌اپ ندارید.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    $mpText = "⏳ <b>رسیدهای در انتظار تأیید از مینی‌اپ</b>\n\n";
    foreach ($mpRows as $mp) {
        $mpText .= "🛒 کد پیگیری: <code>{$mp['id_order']}</code>\n";
        $mpText .= "💰 مبلغ: " . number_format((int)$mp['price']) . " تومان\n";
        $mpText .= "📌 وضعیت: در انتظار بررسی ادمین\n\n";
    }
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $mpKb = json_encode(['inline_keyboard' => [[rx_kb_style(['text' => '🔙 بازگشت', 'callback_data' => 'account'], 'backinfo', $_rx_acc_s)]]], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, $mpText, $mpKb);
} elseif ($datain == "fqQuestions") {
    sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['accountwallet']) || $datain == "account" || $text == "/wallet") {
    $dateacc = jdate('Y/m/d');
    $current_time = time();
    $timeacc = jdate('H:i:s', $current_time);
    if (!is_string($user['codeInvitation']) || trim($user['codeInvitation']) === '') {
        $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
    }
    $first_name = htmlspecialchars($first_name);
    $Balanceuser = number_format($user['Balance'], 0);
    if ($user['number'] == "none") {
        $numberphone = "🔴 ارسال نشده است 🔴";
    } else {
        $numberphone = $user['number'];
    }
    if ($user['number'] == "confrim number by admin") {
        $numberphone = "✅ تایید شده توسط ادمین";
    } else {
        $numberphone = $numberphone;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->execute([
        ':id_user' => $from_id
    ]);
    $countorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $countpayment = $stmt->rowCount();
    $groupuser = [
        'f' => "عادی",
        'n' => "نماینده",
        'n2' => "نمایندگی پیشرفته",
    ][$user['agent']];
    $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    if (intval($setting['scorestatus']) == 1) {
        $textscore = "🥅 امتیاز حساب کاربری شما : {$user['score']}";
    } else {
        $textscore = "";
    }
    $textinvite = "";
    if ($setting['verifybucodeuser'] == "onverify" and $setting['verifystart'] == "onverify") {
        $textscore = "

🔗 لینک ریفرال جهت احراز زیر مجموعه :
https://t.me/$usernamebot?start={$user['codeInvitation']}";
    }
    $text_account_tpl = $datatextbot['text_account_info'] ?? "
🗂 اطلاعات حساب کاربری شما :

🪪 شناسه کاربری: <code>{shanase}</code>
👤 نام: <code>{name}</code>
👨‍👩‍👦 کد معرف شما : <code>{referral_code}</code>
📱 شماره تماس :{phone}
⌚️زمان ثبت نام : {register_date}
💰 موجودی: {balance} تومان
🛒 تعداد سرویس های خریداری شده : {order_count} عدد
📑 تعداد فاکتور های پرداخت شده :  : {payment_count} عدد
🤝 تعداد زیر مجموعه های شما : {affiliate_count} نفر
🔖 گروه کاربری : {group}

📆 {date_now} → ⏰ {time_now}
                    ";
    $text_account_replacements = [
        '{shanase}' => $from_id,
        '{name}' => $first_name,
        '{referral_code}' => $user['codeInvitation'],
        '{phone}' => $numberphone,
        '{register_date}' => $userjoin,
        '{balance}' => $Balanceuser,
        '{order_count}' => $countorder,
        '{payment_count}' => $countpayment,
        '{affiliate_count}' => $user['affiliatescount'],
        '{group}' => $groupuser,
        '{date_now}' => $dateacc,
        '{time_now}' => $timeacc,
    ];
    $text_account = strtr($text_account_tpl, $text_account_replacements) . "\n$textscore\n$textinvite";
    $stmtPendingMa = $pdo->prepare(
        "SELECT id_order, price FROM Payment_report
          WHERE id_user = :uid
            AND payment_Status = 'waiting'
            AND Payment_Method = 'cart to cart'
            AND source = 'miniapp'
            AND dec_not_confirmed = 'receipt-submitted'
          LIMIT 1"
    );
    $stmtPendingMa->execute([':uid' => $from_id]);
    $hasPendingMa = $stmtPendingMa->rowCount() > 0;
    $kbData = json_decode($keyboardPanel, true);
    if ($hasPendingMa) {
        $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
        array_unshift($kbData['inline_keyboard'], [
            rx_kb_style(['text' => '⏳ رسید تایید نشده از مینی‌اپ', 'callback_data' => 'my_miniapp_pending'], 'my_miniapp_pending', $_rx_acc_s)
        ]);
    }
    $activeKeyboard = json_encode($kbData, JSON_UNESCAPED_UNICODE);
    if ($datain == "account") {
        Editmessagetext($from_id, $message_id, $text_account, $activeKeyboard);
    } else {
        sendmessage($from_id, $text_account, $activeKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif ($datain == "MyTransactions") {
    $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
    $txRows = [
        [
            rx_kb_style(['text' => '🕐 ۲۴ ساعت گذشته', 'callback_data' => 'tx_24h'], 'tx_24h', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '📅 ۳ روز گذشته', 'callback_data' => 'tx_3d'], 'tx_3d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '🗓 ۷ روز گذشته', 'callback_data' => 'tx_7d'], 'tx_7d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '📆 ۳۰ روز گذشته', 'callback_data' => 'tx_30d'], 'tx_30d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account'], 'tx_back', $_rx_tx_s),
        ],
    ];
    $txKeyboard = json_encode(['inline_keyboard' => $txRows], JSON_UNESCAPED_UNICODE);
    $txPromptText = "📑 تراکنش‌های من\n\nبازه‌ی زمانی مورد نظر را برای مشاهده‌ی تراکنش‌های کیف پول خود انتخاب کنید:";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $txPromptText, $txKeyboard, 'HTML');
    } else {
        sendmessage($from_id, $txPromptText, $txKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^tx_(24h|3d|7d|30d)(?:_(\d+))?$/', (string)$datain, $txRangeMatch)) {
    $txRangeSeconds = [
        '24h' => 24 * 60 * 60,
        '3d'  => 3 * 24 * 60 * 60,
        '7d'  => 7 * 24 * 60 * 60,
        '30d' => 30 * 24 * 60 * 60,
    ][$txRangeMatch[1]];
    $txRangeLabel = [
        '24h' => '۲۴ ساعت گذشته',
        '3d'  => '۳ روز گذشته',
        '7d'  => '۷ روز گذشته',
        '30d' => '۳۰ روز گذشته',
    ][$txRangeMatch[1]];
    $txCategoryLabels = [
        'topup_card'           => 'شارژ کارت به کارت',
        'topup_crypto'         => 'شارژ ارز دیجیتال',
        'topup_gateway'        => 'شارژ درگاه پرداخت',
        'admin_credit'         => 'افزایش موجودی توسط ادمین',
        'admin_debit'          => 'کاهش موجودی توسط ادمین',
        'purchase'             => 'خرید سرویس',
        'refund'               => 'بازگشت وجه',
        'affiliate_commission' => 'پورسانت زیرمجموعه',
        'gift_code'            => 'کد هدیه',
        'lottery'              => 'جایزه قرعه‌کشی',
        'cashback'             => 'هدیه بازگشت وجه',
        'service_action'       => 'عملیات سرویس',
        'transfer_out'         => 'انتقال موجودی به کاربر دیگر',
        'transfer_in'          => 'انتقال موجودی از کاربر دیگر',
        'referral_gift'        => 'هدیه عضویت زیرمجموعه',
    ];
    $txRangeKey = $txRangeMatch[1];
    $txLimit = 5;
    $txPage = isset($txRangeMatch[2]) && $txRangeMatch[2] !== '' ? max(1, (int)$txRangeMatch[2]) : 1;
    $txOffset = ($txPage - 1) * $txLimit;
    $txCutoff = time() - $txRangeSeconds;

    $txCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM wallet_ledger WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)"
    );
    $txCountStmt->execute([':u' => (string)$from_id, ':cutoff' => $txCutoff]);
    $txTotal = (int)$txCountStmt->fetchColumn();
    $txTotalPages = $txTotal > 0 ? (int)ceil($txTotal / $txLimit) : 1;
    if ($txPage > $txTotalPages) {
        $txPage = $txTotalPages;
        $txOffset = ($txPage - 1) * $txLimit;
    }

    $txStmt = $pdo->prepare(
        "SELECT direction, amount, category, description, created_at
           FROM wallet_ledger
          WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)
          ORDER BY id DESC
          LIMIT :limit OFFSET :offset"
    );
    $txStmt->bindValue(':u', (string)$from_id);
    $txStmt->bindValue(':cutoff', $txCutoff);
    $txStmt->bindValue(':limit', $txLimit, PDO::PARAM_INT);
    $txStmt->bindValue(':offset', $txOffset, PDO::PARAM_INT);
    $txStmt->execute();
    $txRowsData = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($txRowsData)) {
        $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\nدر این بازه‌ی زمانی، تراکنشی یافت نشد.";
    } else {
        $txLines = [];
        foreach ($txRowsData as $txRow) {
            $txSign = $txRow['direction'] === 'credit' ? '➕' : '➖';
            $txLabel = $txCategoryLabels[$txRow['category']] ?? $txRow['category'];
            $txAmountFmt = number_format((int)$txRow['amount']);
            $txDateFmt = jdate('Y/m/d H:i', strtotime($txRow['created_at']));
            $txLines[] = "{$txSign} {$txAmountFmt} تومان — {$txLabel}\n🕐 {$txDateFmt}";
        }
        $txPageInfo = $txTotalPages > 1 ? "\n\n📄 صفحه {$txPage} از {$txTotalPages}" : '';
        $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\n" . implode("\n\n", $txLines) . $txPageInfo;
    }

    $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
    $txKbRows = [];
    if ($txTotalPages > 1) {
        $txNavRow = [];
        if ($txPage > 1) {
            $txNavRow[] = rx_kb_style(['text' => '◀️ قبلی', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage - 1)], 'tx_prev', $_rx_tx_s);
        }
        if ($txPage < $txTotalPages) {
            $txNavRow[] = rx_kb_style(['text' => 'بعدی ▶️', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage + 1)], 'tx_next', $_rx_tx_s);
        }
        if (!empty($txNavRow)) {
            $txKbRows[] = $txNavRow;
        }
    }
    $txKbRows[] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'MyTransactions'], 'tx_back', $_rx_tx_s)];
    $txBackKeyboard = json_encode(['inline_keyboard' => $txKbRows], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, $txText, $txBackKeyboard, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "TransferBalance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $trBackKb = json_encode(['inline_keyboard' => [
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account'], 'backuser', $_rx_acc_s)],
    ]], JSON_UNESCAPED_UNICODE);
    $trPromptText = "🔄 انتقال موجودی\n\nآیدی عددی تلگرام کاربر مقصد را ارسال کنید:";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $trPromptText, $trBackKb, 'HTML');
    } else {
        sendmessage($from_id, $trPromptText, $trBackKb, 'HTML');
    }
    step('getTransferRecipient', $from_id);
    return;
} elseif ($user['step'] == "getTransferRecipient") {
    if (!isset($update['message']) && empty($text)) { return; }
    $trRecipientId = trim((string)$text);
    if (!ctype_digit($trRecipientId)) {
        sendmessage($from_id, "❌ آیدی عددی نامعتبر است. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return;
    }
    if ($trRecipientId === (string)$from_id) {
        sendmessage($from_id, "❌ نمی‌توانید به خودتان موجودی انتقال دهید. آیدی عددی دیگری ارسال کنید:", null, 'HTML');
        return;
    }
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربری با این آیدی یافت نشد. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $trRecipientId, "id", $from_id);
    sendmessage($from_id, sprintf("💸 مبلغ مورد نظر برای انتقال را به تومان وارد کنید:\n✅ حداقل مبلغ %s تومان می‌باشد", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
    step('getTransferAmount', $from_id);
} elseif ($user['step'] == "getTransferAmount") {
    if (!isset($update['message']) && empty($text)) { return; }
    $trAmount = trim((string)$text);
    if (!ctype_digit($trAmount) || (int)$trAmount <= 0) {
        sendmessage($from_id, "❌ مبلغ نامعتبر است. مبلغ را به تومان و به صورت عدد ارسال کنید:", null, 'HTML');
        return;
    }
    $trAmount = (int)$trAmount;
    if ($trAmount < RX_MIN_TRANSFER_AMOUNT) {
        sendmessage($from_id, sprintf("❌ مبلغ باید حداقل %s تومان باشد. مجددا ارسال کنید:", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
        return;
    }
    if ((float)$user['Balance'] < $trAmount) {
        sendmessage($from_id, "❌ موجودی کیف پول شما کافی نیست.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $trRecipientId = $user['Processing_value_one'];
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    update("user", "Processing_value", $trAmount, "id", $from_id);
    $trRecipientLabel = trim((string)($trRecipientUser['username'] ?? '')) !== ''
        ? '@' . $trRecipientUser['username']
        : $trRecipientId;
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $trConfirmKb = json_encode(['inline_keyboard' => [
        [
            rx_kb_style(['text' => '✅ تایید و انتقال', 'callback_data' => 'transferbal_confirm'], 'transferbal_confirm', $_rx_acc_s),
            rx_kb_style(['text' => '❌ انصراف', 'callback_data' => 'transferbal_cancel'], 'transferbal_cancel', $_rx_acc_s),
        ],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, sprintf("🔄 تایید انتقال موجودی\n\n👤 گیرنده: %s\n💰 مبلغ: %s تومان\n\nآیا از انجام این تراکنش اطمینان دارید؟", $trRecipientLabel, number_format($trAmount)), $trConfirmKb, 'HTML');
    step('getTransferConfirm', $from_id);
} elseif ($datain == "transferbal_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, "❌ انتقال موجودی لغو شد.", null, 'HTML');
    step('home', $from_id);
} elseif ($datain == "transferbal_confirm" && $user['step'] == "getTransferConfirm") {
    $trAmount = (int)$user['Processing_value'];
    $trRecipientId = $user['Processing_value_one'];
    if ($trAmount <= 0 || $trRecipientId === '' || $trRecipientId === null) {
        Editmessagetext($from_id, $message_id, "❌ اطلاعات تراکنش نامعتبر است. مجددا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        Editmessagetext($from_id, $message_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    $trCharge = balance_atomic_charge($from_id, $trAmount, 0);
    if (empty($trCharge['ok'])) {
        Editmessagetext($from_id, $message_id, "❌ موجودی کیف پول شما کافی نیست.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    wallet_ledger_record($from_id, 'debit', $trAmount, 'transfer_out', 'انتقال موجودی به کاربر ' . $trRecipientId, null, 'user', (string)$trRecipientId);
    $trCredited = balance_atomic_credit($trRecipientId, $trAmount);
    if (!$trCredited) {
        $trRefunded = balance_atomic_credit($from_id, $trAmount);
        if ($trRefunded) {
            wallet_ledger_record($from_id, 'credit', $trAmount, 'refund', 'بازگشت وجه انتقال ناموفق', null, 'user', (string)$trRecipientId);
        }
        Editmessagetext($from_id, $message_id, "❌ خطا در واریز به کاربر مقصد. مبلغ به کیف پول شما بازگشت داده شد.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    wallet_ledger_record($trRecipientId, 'credit', $trAmount, 'transfer_in', 'انتقال موجودی از کاربر ' . $from_id, null, 'user', (string)$from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, sprintf("✅ انتقال موجودی با موفقیت انجام شد.\n\n💰 مبلغ: %s تومان\n👤 گیرنده: %s", number_format($trAmount), $trRecipientId), null, 'HTML');
    $trSenderUsername = trim((string)($user['username'] ?? ''));
    $trSenderLines = $trSenderUsername !== '' ? "نام کاربری: @{$trSenderUsername}\n" : '';
    $trSenderLines .= "آیدی عددی: <code>{$from_id}</code>";
    $trNotifyText = sprintf("مبلغ %s تومان به کیف پول شما واریز شد.\n\n<blockquote>%s</blockquote>", number_format($trAmount), $trSenderLines);
    sendmessage($trRecipientId, $trNotifyText, null, 'HTML');
    step('home', $from_id);
} elseif ((($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buyback" || $text == "/buy" || $text == "buy") && $statusnote) {
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain == "buy") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['notestep'], $backuser);
    } elseif ($datain == "buyback") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    }
    step("statusnamecustom", $from_id);
    return;
} elseif (($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom") {
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active'");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;

    if (!isset($datatextbot['textselectlocation']) || trim((string)$datatextbot['textselectlocation']) === '') {
        $datatextbot['textselectlocation'] = '📍 لطفاً لوکیشن (سرور) مورد نظر را انتخاب کنید:';
    }
    if (mysqli_num_rows($locationproduct) == 1) {
        $singlePanelRow = mysqli_fetch_assoc($locationproduct);
        if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
            $_rx_buy_isCb = ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback");
            rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, $_rx_buy_isCb);
            return;
        }
        $location = $singlePanelRow['name_panel'];
        $locationproduct = select("marzban_panel", "*", "name_panel", $location, "select");
        if ($locationproduct['hide_user'] != null) {
            $list_user = json_decode($locationproduct['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
        }
        if (panel_creation_limit_reached($locationproduct)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanelfirst'], null, 'HTML');
            return;
        }
        if ($user['step'] == "statusnamecustom") {
            if (!isset($update['message']) && empty($text)) { return; }
            savedata('clear', "nameconfig", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        if (!panel_feature_enabled($location, 'categorytime')) {
            $marzban_list_get = $locationproduct;
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = $eextraprice[$user['agent']];
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
                if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
                    return;
                }
                $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
                sendmessage($from_id, $textcustom, $backuser, 'html');
                step('gettimecustomvol', $from_id);
                return;
            }
            if (panel_feature_enabled($location, 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                if ($setting['statusnamecustom'] == 'onnamecustom') {
                    $backuser = "buyback";
                } else {
                    $backuser = "backuser";
                }
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser));
                } else {
                    sendmessage($from_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser), 'HTML');
                }
            } else {
                $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $queryParams = [
                    ':location' => $location,
                    ':agent' => $user['agent']
                ];
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                    $statuscustom = true;
                } else {
                    $statuscustom = false;
                }
                $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first-no-category'], [
                    'panel' => $marzban_list_get['name_panel'],
                ]);
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
                } else {
                    sendmessage($from_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams), 'HTML');
                }
            }
        } else {
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            $statuscustom = false;
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
                $statuscustom = true;
            if ($statusnote) {
                $back = "buyback";
            } else {
                $back = "backuser";
            }
            $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", $back, $statuscustom, false);
            if ($datain == "buy" || $datain == "buybacktow") {
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
            } else {
                sendmessage($from_id, $textbotlang['Admin']['month']['title'], $monthkeyboard, 'HTML');
            }
        }
        return;
    }
    if ($user['step'] == "statusnamecustom") {
        if (!isset($update['message']) && empty($text)) { return; }
        savedata('clear', "nameconfig", $text);
        step("home", $from_id);
    }
    $rx_loc_kb = ['inline_keyboard' => []];
    $rx_loc_q = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active'");
    $rx_loc_q->execute();
    while ($rx_p = $rx_loc_q->fetch(PDO::FETCH_ASSOC)) {
        $rx_hide = json_decode((string)($rx_p['hide_user'] ?? ''), true);
        if (is_array($rx_hide) && in_array($from_id, $rx_hide)) continue;
        if (($rx_p['type'] ?? '') == "Manualsale") {
            $rx_ms = $pdo->prepare("SELECT 1 FROM manualsell WHERE codepanel = :c AND status = 'active'");
            $rx_ms->execute([':c' => $rx_p['code_panel']]);
            if ($rx_ms->rowCount() == 0) continue;
        }
        $rx_loc_kb['inline_keyboard'][] = [['text' => (string)$rx_p['name_panel'], 'callback_data' => "location_{$rx_p['code_panel']}"]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $_rx_loc_back_key = $statusnote ? "buyback" : "backuser";
    $rx_loc_kb['inline_keyboard'][] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => $_rx_loc_back_key], $_rx_loc_back_key, $_rx_nav_s)];
    $list_marzban_panel_user = json_encode($rx_loc_kb, JSON_UNESCAPED_UNICODE);
    rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback"));
} elseif (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") {
    $userdate = json_decode($user['Processing_value'], true);
    if ($datain != "backproduct") {
        $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    } else {
        $location = $userdate['name_panel'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $locationproductcount = select("marzban_panel", "*", "name_panel", $location, "count");
    if (panel_creation_limit_reached($marzban_list_get)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanel'], null, 'HTML');
        return;
    }
    if ($statusnote) {
        savedata('save', "name_panel", $location);
    } else {
        savedata('clear', "name_panel", $location);
    }
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
        $mainvolume = $mainvolume[$user['agent']];
        $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
        $maxvolume = $maxvolume[$user['agent']];
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
        if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
            return;
        }
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        step('gettimecustomvol', $from_id);
        return;
    }
    if (!panel_feature_enabled($location, 'categorytime')) {
        if (panel_feature_enabled($location, 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], "buybacktow"));
        } else {
            $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $queryParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
                $datakeyboard = "prodcutservices_";
            } else {
                $datakeyboard = "prodcutservice_";
            }
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                $statuscustom = true;
            } else {
                $statuscustom = false;
            }
            if (isset($userdate['nameconfig'])) {
                $back = "buybacktow";
            } else {
                $back = "buyback";
            }
            $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
                'panel' => $marzban_list_get['name_panel'],
            ]);
            Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, "customsellvolume", $user['agent'], $queryParams));
        }
    } else {
        $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $productCountStmt->execute($productCountParams);
        $nullproduct = (int)$productCountStmt->fetchColumn();
        if ($nullproduct == 0) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
            return;
        }
        $statuscustom = false;
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
            $statuscustom = true;
        $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", "buybacktow", $statuscustom, false);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif (preg_match('/^categorynames_(.*)/', $datain, $dataget)) {
    $categorynames = $dataget[1];
    $categoryId = $categorynames;
    $categoryRow = select("category", "*", "id", $categoryId, "select");
    $categorynames = is_array($categoryRow) && isset($categoryRow['remark']) ? $categoryRow['remark'] : $categoryId;
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");


    $catValues = function_exists('nmCategoryLookupValues') ? nmCategoryLookupValues($categorynames) : [];
    foreach ([$categorynames, $categoryId] as $extra) { if (trim((string)$extra) !== '') $catValues[] = (string)$extra; }
    $catValues = array_values(array_unique(array_filter(array_map('strval', $catValues), static function ($v) { return trim($v) !== ''; })));
    if (!$catValues) $catValues = [(string)$categorynames];
    [$catClause, $catParams] = nmBuildFindInSetClause('category', $catValues, 'catv');
    if ($catClause === '') $catClause = '1=0';
    if (isset($userdate['monthproduct'])) {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':service_time' => $userdate['monthproduct'],
            ':agent' => $user['agent']
        ], $catParams);
    } else {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':agent' => $user['agent']
        ], $catParams);
    }
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $datakeyboard = "prodcutservices_";
    } else {
        $datakeyboard = "prodcutservice_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first'], [
        'category' => $categorynames,
        'panel' => $marzban_list_get['name_panel'],
    ]);
    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (panel_feature_enabled($userdate['name_panel'], 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($userdate['name_panel'], $user['agent']))) {
        savedata("save", "monthproduct", $monthenumber);
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active'");
        $stmt->execute();
        $count_panel = $stmt->rowCount();
        if ($count_panel == 1) {
            $back = "buybacktow";
        } else {
            $back = "location_{$marzban_list_get['code_panel']}";
        }
        Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], $back));
    } else {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = [
            ':location' => $userdate['name_panel'],
            ':service_time' => $monthenumber,
            ':agent' => $user['agent']
        ];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
            $datakeyboard = "prodcutservices_";
        } else {
            $datakeyboard = "prodcutservice_";
        }
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
            $statuscustom = true;
        } else {
            $statuscustom = false;
        }
        $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first-no-category'], [
            'panel' => $marzban_list_get['name_panel'],
        ]);
        Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
    }
} elseif ($datain == "customsellvolume") {
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvol', $from_id);
} elseif ($user['step'] == "gettimecustomvol") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit((string) $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ((int) $text > (int) $maxvolume || (int) $text < (int) $mainvolume) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        step('getvolumecustomusername', $from_id);
    } else {
        step('getvolumecustomuser', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusername" || preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($user['step'] == "getvolumecustomusername") {
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
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusers', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuser', $from_id);
        deletemessage($from_id, $message_id);
    }
    $_rx_selectusername_kb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
} elseif ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuser") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($user['step'] == "getvolumecustomuser") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusers" || $user['step'] == "endstepuser") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($loc, $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase preview failed: product not found for code=' . $loc . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        $resultper = ($info_product['price_product'] * $user['pricediscount']) / 100;
        $info_product['price_product'] = $info_product['price_product'] - $resultper;
    }
    $randomString = bin2hex(random_bytes(2));
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $requestedUsername_ac = $username_ac;
    $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
    $random_number = rand(1000000, 9999999);
    $usernameWasRenamed = isset($DataUserOut['username']) || rxTableValueExists('invoice', 'username', $username_ac);
    if ($usernameWasRenamed) {
        $username_ac = $random_number . "-" . $username_ac;
    }
    if (isset($username_ac))
        update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product_price_product = number_format($info_product['price_product']);
    $userBalance = number_format($user['Balance']);
    $replacements = [
        '{username}' => guardDisplayUsername($username_ac, $marzban_list_get),
        '{name_product}' => $info_product['name_product'],
        '{Service_time}' => $info_product['Service_time'],

        '{note}' => $info_product['note'] ?? '',
        '{price}' => $info_product_price_product,
        '{Volume}' => $info_product['Volume_constraint'],
        '{userBalance}' => $userBalance
    ];
    $textin = strtr($datatextbot['text_pishinvoice'], $replacements);
    if ($usernameWasRenamed) {
        $textin = "⚠️ نام کاربری «" . guardDisplayUsername($requestedUsername_ac, $marzban_list_get) . "» قبلاً استفاده شده بود؛ نام کاربری «" . guardDisplayUsername($username_ac, $marzban_list_get) . "» برای شما در نظر گرفته شد.\n\n" . $textin;
    }
    if (intval($info_product['Volume_constraint']) == 0) {
        $textin = str_replace('گیگ', "", $textin);
    }
    $previewSymbolicLimitText = faoxima_symbolic_limit_label($info_product);
    if ($previewSymbolicLimitText !== null) {
        $textin .= "\n👥 تعداد کاربر: $previewSymbolicLimitText";
    } elseif (($marzban_list_get['type'] ?? '') == 'x-ui_single' && (($marzban_list_get['ip_limit_guard'] ?? '') === 'onipguard')) {
        $previewIpLimit = intval($info_product['ip_limit'] ?? 0);
        $previewIpLimitText = $previewIpLimit > 0 ? "{$previewIpLimit} کاربر" : 'نامحدود';
        $textin .= "\n👥 تعداد کاربر: $previewIpLimitText";
    }
    if (in_array($marzban_list_get['type'] ?? '', ['pasarguard', 'remnawave', 'x-ui_single'], true)) {
        $previewHwidLimit = intval($info_product['hwid_limit'] ?? 0);
        if ($previewHwidLimit > 0) {
            $textin .= "\n🔒 محدودیت دستگاه: {$previewHwidLimit} دستگاه";
        }
    }
    $__discEligibleBuy = MiniDiscount::hasEligible('buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
    $__paymentKeyboard = $__discEligibleBuy ? $payment : $paymentom;
    if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], ["نام کاربری دلخواه", "نام کاربری دلخواه + عدد رندوم", "متن دلخواه کاربر + رندوم"])) {
        Editmessagetext($from_id, $message_id, $textin, $__paymentKeyboard);
    } else {
        sendmessage($from_id, $textin, $__paymentKeyboard, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && in_array($datain, ["confirmandgetservice", "confirmandgetserviceDiscount"], true)) {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));

    $parts = explode("_", $user['Processing_value_one']);

    $partsdic = explode("_", $user['Processing_value_four']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }

    if (!array_key_exists('category', $info_product)) {
        $info_product['category'] = '';
    }
    if (!array_key_exists('note', $info_product)) {
        $info_product['note'] = '';
    }
    if ($datain == "confirmandgetserviceDiscount") {
        $discountcode = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "count");
        if ($discountcode == 0) {
            sendmessage($from_id, $datatextbot['dyn_errors_discount_code_not_applicable'] ?? "❌ امکان خرید با این کد کد تخفیف وجود ندارد", null, 'HTML');
            return;
        }
        $priceproduct = $partsdic[1];
    } else {
        $priceproduct = $info_product['price_product'];
    }
    $username_ac = strtolower($user['Processing_value_tow']);
    $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
    if (isset($DataUserOut['username']) || rxTableValueExists('invoice', 'username', $username_ac)) {
        sendmessage($from_id, $datatextbot['dyn_errors_restart_buy_process_short'] ?? "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
        return;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $random_number = rand(1000000, 9999999);
    if (rxTableValueExists('invoice', 'id_invoice', $randomString)) {
        $randomString = $random_number . $randomString;
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $info_product['code_product']);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, $datatextbot['dyn_errors_stock_low_purchase_blocked'] ?? "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
    $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
    $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
    $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
    $stmt->execute();
    $stmt->close();
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2" && intval($priceproduct) != 0) {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        $Balance_prim = $priceproduct - $user['Balance'];
        if ($Balance_prim <= 1)
            $Balance_prim = 0;
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
        } else {
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            update("user", "Processing_value_one", $username_ac, "id", $from_id);
            update("user", "Processing_value_tow", "getconfigafterpay", "id", $from_id);
            if ($datain == "confirmandgetserviceDiscount")
                update("user", "Processing_value_four", "dis_{$partsdic[0]}", "id", $from_id);
        }
        return;
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (intval($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    Editmessagetext($from_id, $message_id, "♻️ در حال ساختن سرویس شما...", null);
    $SellDiscountlimit = false;
    if ($datain == "confirmandgetserviceDiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
        if ($SellDiscountlimit != false) {
            update('invoice', 'discount_code', $partsdic[0], 'id_invoice', $randomString);
            update('invoice', 'discount_amount', (string)((float)($info_product['price_product'] ?? 0) - (float)$priceproduct), 'id_invoice', $randomString);
            update('invoice', 'price_before_discount', (string)($info_product['price_product'] ?? 0), 'id_invoice', $randomString);
        }
    }
    $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($info_product['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buy'
    );
    $Shoppinginfo = [
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ];
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (nmStockCompleteBuyFromInventory($from_id, $user, $marzban_list_get, $info_product, $randomString, $username_ac, true, 'national_buy')) {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. لطفاً محصول دیگری انتخاب کنید یا با پشتیبانی ارتباط بگیرید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac, true);
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        $dataoutput['msg'] = rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
        sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
        $texterros = "⭕️ خطای ساخت اشتراک
<blockquote>✍️ دلیل خطا :
{$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        return;
    }
    $username_ac = rx_adopt_created_username($dataoutput, $username_ac, $randomString);
    rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
    update("invoice", "Status", "active", "username", $username_ac);
    $output_config_link = "";
    $config = "";
    $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }
    $Shoppinginfo = json_encode($Shoppinginfo);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>" . guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (intval($priceproduct) != 0) {

        if (($user['agent'] ?? '') === 'n2') {
            $stmtBuyDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
        } else {
            $stmtBuyDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid AND Balance >= :check_delta");
            $stmtBuyDeduct->bindValue(':check_delta', (int) $priceproduct, PDO::PARAM_INT);
        }
        $stmtBuyDeduct->bindValue(':delta', (int) $priceproduct, PDO::PARAM_INT);
        $stmtBuyDeduct->bindValue(':uid', $from_id, PDO::PARAM_STR);
        $stmtBuyDeduct->execute();
        if ($stmtBuyDeduct->rowCount() === 0 && function_exists('rx_log_event')) {
            rx_log_event('PURCHASE_DOUBLE_SPEND_OR_INSUFFICIENT', 'Atomic buy-deduct affected 0 rows after panel account already created', [
                'from_id'   => $from_id,
                'invoice'   => $randomString ?? null,
                'price'     => $priceproduct,
                'agent'     => $user['agent'] ?? null,
            ]);
        }
    }
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if ($affiliatescommission['status_commission'] == "oncommission" && ($user['affiliates'] != null && intval($user['affiliates']) != 0)) {
        if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
            if ($countinvoice == 1) {
                $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
                if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                    $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                    sendmessage($user['affiliates'], sprintf($rxPointsEarnedTpl, 2), null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $user['affiliates']);
                }

                $stmtAffComm1 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
                $stmtAffComm1->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
                $stmtAffComm1->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
                $stmtAffComm1->execute();
                $result = number_format($result);
                $dateacc = date('Y/m/d H:i:s');
                $textadd = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
                $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($user['affiliates'], $textadd, null, 'HTML');
            }
        } else {

            $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
            $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
            if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                sendmessage($user['affiliates'], sprintf($rxPointsEarnedTpl, 2), null, 'html');
                $scorenew = $user_Balance['score'] + 2;
                update("user", "score", $scorenew, "id", $user['affiliates']);
            }

            $stmtAffComm2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
            $stmtAffComm2->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
            $stmtAffComm2->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
            $stmtAffComm2->execute();
            $result = number_format($result);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
            $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($user['affiliates'], $textadd, null, 'HTML');
        }
    }
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = faoxima_textbot_get('dyn_purchase_first_buy_flag', "📌 خرید اول کاربر");
    }
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $rxFmtInfoProductPrice = rxFormatToman($info_product['price_product']);
    $rxFmtPriceproduct = rxFormatToman($priceproduct);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت در ربات شما ثبت شد .

$textonebuy
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$userdate['name_panel']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️دسته بندی محصول : {$info_product['category']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtPriceproduct} تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        rx_sendTopicReport([
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ], ['flow' => 'buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('new_sub', [
            'user_id'    => $from_id,
            'amount'     => $info_product['name_product'],
            'price'      => number_format((float)$priceproduct),
            'panel_name' => $userdate['name_panel'] ?? '',
            'category'   => $info_product['category'] ?? '',
        ], $setting);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "aptdc") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscount', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscount") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    if (!isset($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_restart_process'] ?? "❌ مراحل خرید را مجددا از اول انجام دهید", $keyboard, 'HTML');
        return;
    }
    $parts = explode("_", (string)$user['Processing_value_one']);
    if (($parts[0] ?? '') === "customvolume") {
        $info_product = [
            'code_product' => 'customvolume',
            'name_product' => $textbotlang['users']['customsellvolume']['title'],
            'Volume_constraint' => $parts[2] ?? 0,
            'Service_time' => $parts[1] ?? 0,
        ];
    } elseif (function_exists('rxResolveProductForPanel')) {
        $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
        $stmt->execute([
            ':code_product' => $user['Processing_value_one'],
            ':Location' => $userdate['name_panel'],
            ':agent' => $user['agent'],
        ]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($info_product) || !isset($info_product['code_product'])) {
        error_log('Discount code check failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__dv = MiniDiscount::validateSell($text, 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
    if (empty($__dv['ok'])) {
        sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
        return;
    }
    $__dvt = $__dv['value_type'];
    $__dval = (float)$__dv['value'];
    $__dlabel = $__dv['label'];
    sendmessage($from_id, sprintf($datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.", $__dlabel), null, 'HTML');
    step('payment', $from_id);
    $parts = explode("_", $user['Processing_value_one']);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Discount purchase preview failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $info_productmain = $info_product['price_product'];
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $rxFmtInfoProductmain = rxFormatToman($info_productmain);
    $rxFmtInfoProductPriceDiscounted = rxFormatToman($info_product['price_product']);
    $rxFmtUserBalanceInvoicePreview = rxFormatToman($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>{$user['Processing_value_tow']}</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت اصلی : <del>{$rxFmtInfoProductmain} تومان</del>
💶 قیمت با تخفیف: {$rxFmtInfoProductPriceDiscounted}  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : {$rxFmtUserBalanceInvoicePreview}

        💰 سفارش شما آماده پرداخت است.  ";
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $paymentDiscount = json_encode([
        'inline_keyboard' => [
            [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetserviceDiscount"]],
            [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s)]
        ]
    ]);
    $parametrsendvalue = $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
} elseif ($text == "🗂 خرید انبوه" || $datain == "kharidanbuh") {
    if ($setting['bulkbuy'] == "offbulk") {
        sendmessage($from_id, $datatextbot['dyn_errors_section_disabled'] ?? "❌ این بخش در حال غیرفعال می باشد", null, 'HTML');
        return;
    }
    $PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shopSetting WHERE Namevalue = 'minbalancebuybulk'"))['value'];
    if ($user['Balance'] < $PaySetting) {
        $rxMinBulkTpl = $datatextbot['dyn_errors_min_bulk_purchase_balance'] ?? "❌ برای خرید انبوه باید حداقل %s تومان موجودی داشته باشید.";
        sendmessage($from_id, sprintf($rxMinBulkTpl, $PaySetting), null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;

    if ($datain == "kharidanbuh") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    }
    step('getcountconfig', $from_id);
} elseif ($user['step'] == "getcountconfig") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (intval($text) > 15 || intval($text) < 1)
        return sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backuser, 'HTML');
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_userom, 'HTML');
    update("user", "Processing_value_four", $text, "id", $from_id);
    step('home', $from_id);
} elseif (preg_match('/^locationom_(.*)/', $datain, $dataget)) {
    $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1], "select");
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        return;
    }
    update("user", "Processing_value", $location, "id", $from_id);
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
    $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
        'panel' => $marzban_list_get['name_panel'],
    ]);
    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolumeom", $user['agent'], $productCountParams));
} elseif ($datain == "customsellvolumeom") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $textcustom = "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :
📌 تعرفه هر گیگ :  " . rxFormatToman($custompricevalue) . "
🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvolom', $from_id);
} elseif ($user['step'] == "gettimecustomvolom") {
    if (!isset($update['message']) && empty($text)) { return; }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        step('getvolumecustomusernameom', $from_id);
    } else {
        step('getvolumecustomuserom', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusernameom" || preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($user['step'] == "getvolumecustomusernameom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
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
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusersom', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuserom', $from_id);
    }
    $_rx_selectusername_kb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
} elseif ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserom") {
    if ($user['step'] == "getvolumecustomuserom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > $maxtime || intval($text) < $maintime) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusersom" || $user['step'] == "endstepuserom") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase preview failed: product not found for code=' . ($loc ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $randomString = bin2hex(random_bytes(2));
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ($info_product['Volume_constraint'] == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product['price_product'] = intval($info_product['price_product']) * intval($user['Processing_value_four']);
    update("user", "Processing_value_price", $info_product['price_product'], "id", $from_id);
    $price_product_format = number_format($info_product['price_product']);
    $userbalancepish = number_format($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>" . guardDisplayUsername($username_ac, $marzban_list_get) . "</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت: $price_product_format  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : $userbalancepish
⭕️تعداد کانفیگ : {$user['Processing_value_four']}

💰 سفارش شما آماده پرداخت است.  ";
    sendmessage($from_id, $textin, $paymentom, 'HTML');
    step('payments', $from_id);
} elseif ($user['step'] == "payments" && $datain == "confirmandgetservice") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = $eextraprice[$user['agent']];
    $parts = explode("_", $user['Processing_value_one']);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($user['Processing_value_price'] !== null && $user['Processing_value_price'] !== '') {
        $priceproduct = (float) $user['Processing_value_price'];
    } else {
        $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
    }
    Editmessagetext($from_id, $message_id, $text_inline, null);
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($user['Processing_value'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
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
            $Balance_prim = $priceproduct - $user['Balance'];
            $Balance_prims = $user['Balance'] - $priceproduct;
            if ($Balance_prims <= 1)
                $Balance_prims = 0;
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $datep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + $user['Processing_value_four'];
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + $user['Processing_value_four'];
            update("setting", "numbercount", $value);
        }
    }
    if ($info_product['Service_time'] == 0) {
        $datep = 0;
    } else {
        $datep = strtotime(date("Y-m-d H:i:s", $datep));
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", $datep)),
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buyomdh'
    );
    if ($info_product['inbounds'] != null) {
        $marzban_list_get['inboundid'] = $info_product['inbounds'];
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    $__bulkQty = max(1, (int)$user['Processing_value_four']);
    $__bulkUnitCharge = intdiv((int)round($priceproduct), $__bulkQty);
    $__bulkUnitChargeRemainder = ((int)round($priceproduct)) - ($__bulkUnitCharge * $__bulkQty);
    $__bulkChargedTotal = 0;
    $__bulkAllowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    for ($i = 0; $i < $user['Processing_value_four']; $i++) {
        $__bulkItemCharge = $__bulkUnitCharge + (($i == $__bulkQty - 1) ? $__bulkUnitChargeRemainder : 0);
        $random_number = rand(1000000, 9999999);
        $username_acc = $username_ac . "-" . $i;
        if (rxTableValueExists('invoice', 'username', $username_acc)) {
            $username_acc = $random_number . "-" . $username_acc;
        }
        $randomString = bin2hex(random_bytes(4));
        if (rxTableValueExists('invoice', 'id_invoice', $randomString)) {
            $randomString = $random_number . $randomString;
        }


        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            $stock = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($marzban_list_get, $info_product, $from_id, $randomString, 'national_direct_buy')
                : false;
            if (!$stock) {
                sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. خرید انجام نشد و مبلغی از کیف پول کسر نشد.", $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
            $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
            $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
            $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
            $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
            $Status = "active";
            $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
            $stmt->execute();
            $stmt->close();
            try {
                update("invoice", "user_info", $stock['content'], "id_invoice", $randomString);
                update("invoice", "source_panel_code", $marzban_list_get['code_panel'] ?? '', "id_invoice", $randomString);
            } catch (Throwable $e) {
                error_log('nm national direct invoice update failed: ' . $e->getMessage());
            }
            $inventoryInvoice = [
                'id_user' => $from_id,
                'id_invoice' => $randomString,
                'username' => $username_acc,
                'Service_location' => $marzban_list_get['name_panel'] ?? '',
                'name_product' => $info_product['name_product'] ?? '',
                'Volume' => $info_product['Volume_constraint'] ?? 0,
                'Service_time' => $info_product['Service_time'] ?? 0,
                'price_product' => $info_product['price_product'] ?? 0,
            ];
            nmStockDeliverConfig($stock, $inventoryInvoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار شبکه‌ملی تحویل شد');
            if (function_exists('balance_atomic_charge')) {
                $__bulkChargeNm = balance_atomic_charge($from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
                if (!empty($__bulkChargeNm['ok']) && function_exists('wallet_ledger_record')) {
                    wallet_ledger_record($from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس (انبار ملی)', (string)$randomString, 'invoice', (string)$randomString);
                }
            }
            $__bulkChargedTotal += $__bulkItemCharge;
            continue;
        }
        $get_username_Check = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc) : null;
        if (isset($get_username_Check['username']) || rxTableValueExists('invoice', 'username', $username_acc)) {
            $username_acc = $random_number . "-" . $username_acc;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac, true);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت اکانت در بخش انبوه
<blockquote>✍️ دلیل خطا :
{$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            step('home', $from_id);
            return;
        }
        $username_acc = rx_adopt_created_username($dataoutput, $username_acc);
        rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
        $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
        $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
        $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
        $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
        $Status = "active";
        $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
        $stmt->execute();
        $stmt->close();
        $config = "";
        $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
        if ($marzban_list_get['config'] == "onconfig") {
            if (is_array($dataoutput['configs'])) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= "\n" . $configs;
                }
            }
        }
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        if ($marzban_list_get['type'] == "WGDashboard") {
            $datatextbot['textafterpay'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
        }
        $textcreatuser = str_replace('{username}', "<code>" . guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
        if (function_exists('balance_atomic_charge')) {
            $__bulkChargeLive = balance_atomic_charge($from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
            if (!empty($__bulkChargeLive['ok']) && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس', (string)$randomString, 'invoice', (string)$randomString);
            }
        } else {
            update("user", "Balance", select("user", "Balance", "id", $from_id, "select")['Balance'] - $__bulkItemCharge, "id", $from_id);
        }
        $__bulkChargedTotal += $__bulkItemCharge;
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    $__bulkBalanceRow = select("user", "*", "id", $from_id, "select");
    $Balance_prim = (float)($__bulkBalanceRow['Balance'] ?? 0);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
    $count_service = $user['Processing_value_four'];
    $rxFmtBulkInfoProductPrice = rxFormatToman($info_product['price_product']);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت انبوه در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$username_ac}_0-$count_service</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$user['Processing_value']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️تعداد کانفیگ : {$user['Processing_value_four']} عدد</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        rx_sendTopicReport([
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ], ['flow' => 'bulk_buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('new_sub', [
            'user_id'    => $from_id,
            'amount'     => $info_product['name_product'],
            'price'      => number_format((float)$pricebulk),
            'panel_name' => $user['Processing_value'] ?? '',
            'category'   => $info_product['category'] ?? '',
        ], $setting);
    }
    step('home', $from_id);
} elseif ($datain == "Add_Balance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    $randomWalletStatusRow = select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
    $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
    if ($randomWalletStatusValue === '1') {
        $rwAmounts = rx_randomWalletAmounts();
        $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
        $rwRows = [];
        for ($rwI = 0; $rwI < count($rwAmounts); $rwI += 2) {
            $rwRow = [rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI]], 'rw_pick', $_rx_acc_s)];
            if (isset($rwAmounts[$rwI + 1])) {
                $rwRow[] = rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI + 1]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI + 1]], 'rw_pick', $_rx_acc_s);
            }
            $rwRows[] = $rwRow;
        }
        $rwRows[] = [rx_kb_style(['text' => "✍️ رقم دلخواه", 'callback_data' => "rw_custom"], 'rw_custom', $_rx_acc_s)];
        $rwRows[] = [rx_kb_style(['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"], 'backinfo', $_rx_acc_s)];
        $rwKeyboard = json_encode(['inline_keyboard' => $rwRows], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_select_amount'] ?? "💰 مبلغ مورد نظر را برای شارژ کیف پول انتخاب کنید:", $rwKeyboard, 'HTML');
        return;
    }
    rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
} elseif (preg_match('/^rw_pick_(\d+)$/', (string)$datain, $rwPickMatch)) {
    $rwAmount = (int)$rwPickMatch[1];
    deletemessage($from_id, $message_id);
    rx_walletChargeAmountAccepted($from_id, $user, $rwAmount);
} elseif ($datain == "rw_custom") {
    rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
} elseif ($datain == "rcc_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null);
    } else {
        sendmessage($from_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null, 'HTML');
    }
} elseif ($datain == "recheckcrypto") {
    if (function_exists('crypto_active_wallets') && empty(crypto_active_wallets())) {
        $rccNoWalletMsg = "⚠️ در حال حاضر هیچ درگاه پرداخت کریپتویی فعال نیست. لطفاً با پشتیبانی تماس بگیرید.";
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $rccNoWalletMsg, null);
        } else {
            sendmessage($from_id, $rccNoWalletMsg, null, 'HTML');
        }
        step('home', $from_id);
        return;
    }
    $rccListRows = [];
    try {
        $rccListStmt = $pdo->prepare(
            "SELECT id_order, crypto_currency, crypto_amount, price, time
               FROM Payment_report
              WHERE id_user = :u
                AND payment_Status = 'reject'
                AND crypto_tx_hash IS NOT NULL
                AND crypto_tx_hash <> ''
              ORDER BY id DESC
              LIMIT 10"
        );
        $rccListStmt->execute([':u' => (string) $from_id]);
        $rccListRows = $rccListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {  }

    if (empty($rccListRows)) {
        $rccEmptyMsg = "📭 <b>هیچ تراکنش رد شده‌ای ندارید</b>\n\n"
            . "تراکنش‌های کریپتویی شما که توسط ربات رد شده باشن، در این بخش نمایش داده میشن.";
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $rccEmptyMsg, null);
        } else {
            sendmessage($from_id, $rccEmptyMsg, null, 'HTML');
        }
        step('home', $from_id);
        return;
    }

    $rccKbRows = [];
    foreach ($rccListRows as $r) {
        $rccLabel = '🪙 ' . (string) ($r['crypto_currency'] ?? '?')
            . ' | ' . number_format((int) ($r['price'] ?? 0)) . ' ت'
            . ' | ' . (string) ($r['id_order'] ?? '');
        $rccKbRows[] = [
            ['text' => $rccLabel, 'callback_data' => 'rcc_view_' . (string) ($r['id_order'] ?? '')],
        ];
    }
    $rccKb = json_encode(['inline_keyboard' => $rccKbRows], JSON_UNESCAPED_UNICODE);
    $rccListMsg = "🔁 <b>تراکنش‌های رد شده شما</b>\n\n"
        . "روی هر تراکنش کلیک کنید تا جزئیاتش رو ببینید و در صورت لزوم برای بررسی دستی ادمین ارسال کنید.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccListMsg, $rccKb);
    } else {
        sendmessage($from_id, $rccListMsg, $rccKb, 'HTML');
    }
    step('home', $from_id);
} elseif (strpos((string) $datain, 'rcc_view_') === 0) {
    $rccVOid = substr((string) $datain, strlen('rcc_view_'));
    $rccVRow = null;
    try {
        $rccVStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccVStm->execute([':o' => $rccVOid, ':u' => (string) $from_id]);
        $rccVRow = $rccVStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccVRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccVExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccVRow['crypto_currency'] ?? ''), (string) ($rccVRow['crypto_tx_hash'] ?? ''))
        : '';
    $rccVReasonRaw = (string) ($rccVRow['crypto_last_error'] ?? ($rccVRow['dec_not_confirmed'] ?? ''));
    $rccVReason = $rccVReasonRaw !== '' ? mb_substr($rccVReasonRaw, 0, 200) : '—';

    $rccVText = "🧾 <b>جزئیات تراکنش رد شده</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccVOid) . "</code>\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccVRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccVRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccVRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccVRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📅 زمان: " . htmlspecialchars((string) ($rccVRow['time'] ?? '-')) . "\n"
        . "📝 دلیل عدم تایید: " . htmlspecialchars($rccVReason)
        . ($rccVExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccVExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

    $rccVKb = json_encode([
        'inline_keyboard' => [
            [['text' => '📨 ارسال مجدد به ادمین', 'callback_data' => 'rcc_resubmit_' . $rccVOid]],
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'recheckcrypto']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccVText, $rccVKb);
    } else {
        sendmessage($from_id, $rccVText, $rccVKb, 'HTML');
    }
} elseif (strpos((string) $datain, 'rcc_resubmit_') === 0) {
    $rccROid = substr((string) $datain, strlen('rcc_resubmit_'));
    $rccRRow = null;
    try {
        $rccRStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccRStm->execute([':o' => $rccROid, ':u' => (string) $from_id]);
        $rccRRow = $rccRStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccRRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    try {
        $rccRUpd = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'ManualPending', at_updated = :now
              WHERE id_order = :o AND payment_Status = 'reject'"
        );
        $rccRUpd->execute([':now' => date('Y/m/d H:i:s'), ':o' => $rccROid]);
        if ($rccRUpd->rowCount() < 1) {
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => '❌ قبلاً ارسال شده',
                    'show_alert' => true,
                ]);
            }
            return;
        }
    } catch (Throwable $e) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ خطای داخلی',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccRExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccRRow['crypto_currency'] ?? ''), (string) ($rccRRow['crypto_tx_hash'] ?? ''))
        : '';
    $rccRUserTag = '@' . ($user['username'] ?? 'none');
    $rccRReasonRaw = (string) ($rccRRow['crypto_last_error'] ?? ($rccRRow['dec_not_confirmed'] ?? ''));
    $rccRReason = $rccRReasonRaw !== '' ? mb_substr($rccRReasonRaw, 0, 200) : '—';
    $rccRAdminCaption = "🔁 <b>درخواست بررسی دستی توسط کاربر</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$rccRUserTag})\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccRRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccRRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccRRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccRRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📝 دلیل اولیه رد: " . htmlspecialchars($rccRReason)
        . ($rccRExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccRExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

    if (function_exists('crypto_lookup_verified_hash')) {
        $rccRExisting = crypto_lookup_verified_hash((string) ($rccRRow['crypto_tx_hash'] ?? ''));
        if (is_array($rccRExisting)) {
            $rccRAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string) $rccRExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string) ($rccRExisting['user_id'] ?? '-')) . "</code>";
        }
    }

    $rccRAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید خودکار', 'callback_data' => 'cmauto_' . $rccROid],
                ['text' => '✏️ تایید دستی',   'callback_data' => 'cmmanual_' . $rccROid],
            ],
            [
                ['text' => '🗑️ لغو و حذف از دیتابیس', 'callback_data' => 'cmdelete_' . $rccROid],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $rccRAdminIds = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($rccRAdminIds)) {
        $rccRAdminIds = [];
    }
    if (function_exists('telegram')) {
        foreach ($rccRAdminIds as $rccRAdminOne) {
            if (!is_numeric($rccRAdminOne)) continue;
            @telegram('sendmessage', [
                'chat_id' => (string)$rccRAdminOne,
                'text' => $rccRAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccRAdminKb,
            ]);
        }
    }

    $rccRUserMsg = "✅ <b>درخواست شما برای بررسی دستی ارسال شد</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام می‌شود.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccRUserMsg, null);
    } else {
        sendmessage($from_id, $rccRUserMsg, null, 'HTML');
    }
} elseif (preg_match('/^rcc_pick_([A-Za-z0-9_]{2,20})$/', (string) $datain, $rccPick)
    && in_array($rccPick[1], array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency'), true)
) {
    $rccCur = $rccPick[1];
    update("user", "Processing_value_one", $rccCur, "id", $from_id);
    $rccWalletsFa = array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'label', 'currency');
    $rccCurFa = [
        'TRX' => 'ترون (TRX)',
        'TON' => 'تون (TON)',
        'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
        'USDT_TON' => 'تتر روی تون (USDT-TON)',
    ][$rccCur] ?? ($rccWalletsFa[$rccCur] ?? $rccCur);
    $askAmount = "💎 ارز انتخابی: <b>{$rccCurFa}</b>\n\n"
               . "🪙 لطفاً <b>مقدار ارز پرداخت‌شده</b> را وارد کنید (مثلاً <code>0.2</code> یا <code>1.25</code>):";
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $askAmount, $cancelKb);
    } else {
        sendmessage($from_id, $askAmount, $cancelKb, 'HTML');
    }
    step('rcc_amount', $from_id);
} elseif ($user['step'] == "rcc_amount" && empty($datain)) {
    $coinAmount = trim(str_replace([',', '،'], ['.', '.'], (string) $text));
    if (!is_numeric($coinAmount) || (float) $coinAmount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_amount_not_numeric'] ?? "❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.", null, 'HTML');
        return;
    }
    $rccCurForRate = (string) ($user['Processing_value_one'] ?? '');
    $rateForCalc = function_exists('crypto_irt_rate_for') ? crypto_irt_rate_for($rccCurForRate) : null;
    if ($rateForCalc === null || $rateForCalc <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_rate_unavailable'] ?? "❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.", null, 'HTML');
        return;
    }
    $calcIrr = (int) round(((float) $coinAmount) * $rateForCalc);
    if ($calcIrr <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_calculated_amount_invalid'] ?? "❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_tow", $coinAmount, "id", $from_id);
    update("user", "Processing_value", (string) $calcIrr, "id", $from_id);
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    sendmessage(
        $from_id,
        "💰 <b>محاسبه‌ی خودکار قیمت</b>\n\n"
        . "💎 ارز: <b>{$rccCurForRate}</b>\n"
        . "🪙 مقدار: <code>{$coinAmount}</code>\n"
        . "📈 نرخ لحظه‌ای: <code>" . number_format((float) $rateForCalc) . "</code> تومان\n"
        . "💵 <b>معادل تومانی محاسبه‌شده:</b> " . number_format($calcIrr) . " تومان\n\n"
        . "🔗 حالا <b>هش (TxID) تراکنش</b> را ارسال کنید:\n"
        . "<i>می‌توانید لینک کامل Tonviewer / Tonscan / Tronscan یا هش خام را بفرستید.</i>",
        $cancelKb,
        'HTML'
    );
    step('rcc_hash', $from_id);
} elseif ($user['step'] == "rcc_hash" && empty($datain)) {
    $hashTry = function_exists('crypto_extract_hash') ? crypto_extract_hash((string) $text) : null;
    if ($hashTry === null) {
        sendmessage($from_id, $datatextbot['dyn_wallet_hash_not_found'] ?? "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را بفرستید — نه آدرس کیف‌پول.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $hashTry, "id", $from_id);
    $photoKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⏭ ارسال بدون عکس (رد شدن)', 'callback_data' => 'rcc_skip_photo']],
            [['text' => '❌ انصراف',                    'callback_data' => 'rcc_cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, $datatextbot['dyn_wallet_ask_receipt_photo'] ?? "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید.", $photoKb, 'HTML');
    step('rcc_photo', $from_id);
} elseif ($user['step'] == "rcc_photo" && ($datain == "rcc_skip_photo" || !empty($photoid))) {
    $rccCur     = (string) $user['Processing_value_one'];
    $rccCoin    = (string) $user['Processing_value_tow'];
    $rccIrr     = (int)    $user['Processing_value'];
    $rccHash    = (string) $user['Processing_value_four'];
    $rccPhotoId = $datain === "rcc_skip_photo" ? '' : (string) $photoid;
    $rccKnownCurs = array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency');
    if (!in_array($rccCur, $rccKnownCurs, true) || $rccCoin === '' || $rccIrr <= 0 || $rccHash === '') {
        sendmessage($from_id, $datatextbot['dyn_wallet_incomplete_info'] ?? "❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    try {
        $dupChk = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h LIMIT 1");
        $dupChk->execute([':h' => $rccHash]);
        if ($dupChk->fetch()) {
            sendmessage($from_id, $datatextbot['dyn_wallet_hash_already_used'] ?? "❌ این هش قبلاً برای فاکتور دیگری ثبت شده است. اگر این پرداخت متعلق به شماست، با پشتیبانی تماس بگیرید.", null, 'HTML');
            step('home', $from_id);
            return;
        }
    } catch (Throwable $e) {  }
    $rccOrderId = bin2hex(random_bytes(6));
    $rccNow = date('Y/m/d H:i:s');
    $rccCoinFmt = number_format((float) $rccCoin, 9, '.', '');
    $rccCoinFmt = rtrim(rtrim($rccCoinFmt, '0'), '.');
    $rccManualCurs = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    if (isset($rccManualCurs[$rccCur])) {
        $rccNetwork = (string) $rccManualCurs[$rccCur]['network'];
    } else {
        $rccNetwork = in_array($rccCur, ['TRX', 'USDT_TRC20'], true) ? 'TRON' : 'TON';
    }
    try {
        $rccIns = $connect->prepare(
            "INSERT INTO Payment_report
                (id_user, id_order, time, price, payment_Status, Payment_Method,
                 id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_tx_hash, crypto_hash_at, dec_not_confirmed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $userIdStr = (string) $from_id;
        $statusManual = 'ManualPending';
        $methodLabel = 'manual crypto recheck';
        $invoiceMeta = '0|0';
        $irrStr = (string) $rccIrr;
        $nowUnix = time();
        $rccIns->bind_param(
            'sssssssssssis',
            $userIdStr, $rccOrderId, $rccNow, $irrStr, $statusManual, $methodLabel,
            $invoiceMeta, $rccCur, $rccNetwork, $rccCoinFmt, $rccHash, $nowUnix, $rccPhotoId
        );
        $rccIns->execute();
        $rccIns->close();
    } catch (Throwable $e) {
        error_log('[recheckcrypto] insert failed: ' . $e->getMessage());
        sendmessage($from_id, $datatextbot['dyn_wallet_internal_error_retry'] ?? "❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }

    sendmessage(
        $from_id,
        "✅ <b>درخواست بررسی دستی شما ثبت شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "💎 ارز: <b>{$rccCur}</b>\n"
        . "🪙 مقدار: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>" . substr($rccHash, 0, 16) . "…</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام خواهد شد.",
        null,
        'HTML'
    );

    $explorerUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url($rccCur, $rccHash)
        : '';
    $userTagLine = '@' . ($user['username'] ?? 'none');
    $rccAdminCaption = "🔁 <b>درخواست بررسی دستی پرداخت کریپتو</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$userTagLine})\n"
        . "💎 ارز: <b>{$rccCur}</b> ({$rccNetwork})\n"
        . "🪙 مقدار ادعاشده: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی ادعاشده: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>{$rccHash}</code>"
        . ($explorerUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در مرورگر بلاکچین</a>" : '');
    if (function_exists('crypto_lookup_verified_hash')) {
        $rccExisting = crypto_lookup_verified_hash($rccHash);
        if (is_array($rccExisting)) {
            $rccAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string)$rccExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string)($rccExisting['user_id'] ?? '-')) . "</code>\n"
                . "📅 تایید در: " . htmlspecialchars((string)($rccExisting['verified_at'] ?? '-'));
        }
    }
    $rccAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $rccOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $rccOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $admin_ids_local = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($admin_ids_local)) $admin_ids_local = [];
    foreach ($admin_ids_local as $idAdminLocal) {
        if (!is_numeric($idAdminLocal)) continue;
        if ($rccPhotoId !== '') {
            telegram('sendphoto', [
                'chat_id' => $idAdminLocal,
                'photo'   => $rccPhotoId,
                'caption' => $rccAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccAdminKb,
            ]);
        } else {
            sendmessage((string) $idAdminLocal, $rccAdminCaption, $rccAdminKb, 'HTML');
        }
    }
    if (!empty($setting['Channel_Report'])) {
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'caption' => $rccAdminCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => $rccAdminKb,
        ];
        if (!empty($paymentreports)) {
            $payload['message_thread_id'] = $paymentreports;
        }
        if ($rccPhotoId !== '') {
            $payload['photo'] = $rccPhotoId;
            telegram('sendphoto', $payload);
        } else {
            unset($payload['caption']);
            $payload['text'] = $rccAdminCaption;
            telegram('sendmessage', $payload);
        }
    }
    step('home', $from_id);
} elseif ($user['step'] == "getprice") {
    if (!isset($update['message']) && empty($text)) { return; }
    deletemessage($from_id, $user['Processing_value']);
    rx_walletChargeAmountAccepted($from_id, $user, $text);
} elseif ($datain == "chargenodiscount") {
    update("user", "Processing_value_four", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment);
    step('get_step_payment', $from_id);
} elseif ($datain == "chargehasdiscount") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountcharge', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountcharge") {
    if (!isset($update['message']) && empty($text)) { return; }
    $__amount = intval($user['Processing_value']);
    if ($__amount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_invalid_charge_amount'] ?? "❌ مبلغ شارژ نامعتبر است. مجددا تلاش کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $dv = MiniDiscount::validateSell($text, 'charge', '', '', '', $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__gatewayAmount = (int) round(MiniDiscount::applyToPrice($dv['row'], $__amount));
    if ($__gatewayAmount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_discount_not_applicable_zero'] ?? "❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).", $backuser, 'HTML');
        return;
    }
    $__bonus = $__amount - $__gatewayAmount;
    if ($__bonus < 0) $__bonus = 0;
    update("user", "Processing_value", $__gatewayAmount, "id", $from_id);
    update("user", "Processing_value_four", "chg|" . $__bonus . "|" . $text . "|" . $__amount, "id", $from_id);
    $__amountfmt  = number_format($__amount, 0);
    $__gatewayfmt = number_format($__gatewayAmount, 0);
    $__txt = "🤩 کد تخفیف {$dv['label']} روی شارژ کیف پول اعمال شد.\n\n💎 مبلغ شارژ کیف پول : {$__amountfmt} تومان\n💸 مبلغ قابل پرداخت : {$__gatewayfmt} تومان\n\nروش پرداخت را انتخاب کنید:";
    sendmessage($from_id, $__txt, $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    if (isset($update['message']) && empty($datain)) { return; }
    $__chargeBonus = function_exists('nm_pending_charge_bonus') ? nm_pending_charge_bonus($user) : 0;
    if ($datain == "cart_to_offline") {
        $from_id_sql = (string) $from_id;

        $stale_cutoff = date('Y/m/d H:i:s', time() - 15 * 60);
        $_purge = $connect->prepare("DELETE FROM Payment_report WHERE id_user = ? AND payment_Status = 'Unpaid' AND Payment_Method = 'cart to cart' AND time < ?");
        $_purge->bind_param("ss", $from_id_sql, $stale_cutoff);
        $_purge->execute();
        $_purge->close();

        $_purge_ab = $connect->prepare("DELETE FROM Payment_report WHERE id_user = ? AND payment_Status IN ('Unpaid','pending') AND Payment_Method = 'cart to cart' AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')");
        $_purge_ab->bind_param("s", $from_id_sql);
        $_purge_ab->execute();
        $_purge_ab->close();

        $_stmt = $connect->prepare("SELECT id FROM Payment_report WHERE id_user = ? AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting' OR payment_Status = 'pending') AND Payment_Method = 'cart to cart' LIMIT 1");
        $_stmt->bind_param("s", $from_id_sql);
        $_stmt->execute();
        $checkpay = $_stmt->get_result();
        $_stmt->close();
        if (mysqli_num_rows($checkpay) != 0) {
            sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['issetpay'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancecart", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancecart", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        $_uid = intval($from_id);
        $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = {$_uid})";
        $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
        $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
        $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

        $card_info = null;
        $_card2 = null;

        if ($_cmode == 'direct') {
            $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
            $_dcards = [];
            if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
            $card_info = $_dcards[0] ?? null;
            $_card2 = $_dcards[1] ?? null;
        } else {
            $_ck = "card_cycle_{$_uid}";
            $_ck_esc = mysqli_real_escape_string($connect, $_ck);
            $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
            $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
            $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
            $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
            $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

            $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

            if (!$card_info) {
                $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
                $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
                $_seen = [];
            }

            if ($card_info) {
                $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
                $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
                mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
                if (mysqli_affected_rows($connect) === 0) {
                    mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
                }
            }
        }

        if (!$card_info || empty($card_info['cardnumber']) || empty($card_info['namecard'])) {
            sendmessage($from_id, $datatextbot['dyn_wallet_no_active_card'] ?? "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            return;
        }

        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        $valueprice = number_format($user['Processing_value']);
        $price_copy = intval($user['Processing_value'] . "0");

        if ($_cmode == 'direct' && $_card2) {
            $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
        } else {
            $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
            $textcart = strtr($datatextbot['text_cart'], $replacements);
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "cart to cart";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();

        $_cv_active = !in_array($from_id, $admin_ids)
            && ($setting['card_verify_status'] ?? 'offcardverify') === 'oncardverify'
            && intval($user['card_verify_bypass'] ?? 0) !== 1;
        if ($_cv_active) {
            $_cv_min = (int)($setting['card_verify_min_amount'] ?? 0);
            if ($_cv_min > 0 && (int)$user['Processing_value'] < $_cv_min) {
                $_cv_active = false;
            }
        }
        if ($_cv_active) {
            deletemessage($from_id, $message_id);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => '🔒 احراز هویت کارت‌به‌کارت فعال است',
                'show_alert'        => true,
            ]);
            update("user", "Processing_value", $randomString, "id", $from_id);

            $_vc_rows = [];
            $_vc_result = $connect->prepare("SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $_vc_result->bind_param("s", $from_id);
            $_vc_result->execute();
            $_vc_res = $_vc_result->get_result();
            while ($_vc_row = $_vc_res->fetch_assoc()) {
                $_vc_rows[] = $_vc_row['last4'];
            }
            $_vc_result->close();

            if (!empty($_vc_rows)) {
                $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
                $_vc_keyboard_rows = [];
                foreach ($_vc_rows as $_vc_last4) {
                    $_vc_keyboard_rows[] = [rx_kb_style(['text' => "💳 **** **** **** {$_vc_last4}", 'callback_data' => "cv_use_{$_vc_last4}"], 'cv_use', $_prcpt_s)];
                }
                $_vc_keyboard_rows[] = [rx_kb_style(['text' => "📷 پرداخت با کارت جدید", 'callback_data' => "cv_new"], 'cv_new', $_prcpt_s)];
                $_vc_kb = json_encode(['inline_keyboard' => $_vc_keyboard_rows]);
                step('card_select_step', $from_id);
                sendmessage($from_id, $datatextbot['dyn_wallet_select_verified_card'] ?? "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید یا با کارت جدید پرداخت کنید:", $_vc_kb, 'HTML');
            } else {
                step('card_photo_step', $from_id);
                sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.\n\n🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", $backuser, 'HTML');
            }
            return;
        }

        deletemessage($from_id, $message_id);
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        if ($setting['statuscopycart'] == "1") {
            $_kb_rows = [[
                ['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num'],
                ['text' => "کپی مبلغ", 'copy_text' => ["text" => $price_copy], '_rxck' => 'card_amount']
            ]];
            if (isset($_cmode) && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
            }
            $_kb_rows[] = [rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)];
            $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
        } else {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [
                        rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                    ]
                ]
            ]);
        }
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], $data['text']);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], $data['text']);
            }
        }
        $message_id = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, "html");
        updatePaymentMessageId($message_id, $randomString);
    } elseif (preg_match('/^rxpgw_cancel_(zarinpal|plisio|nowpayment|iranpay2|tonpay|blupal|atlaspay|cubepay|variza|abangateway)_(\w+)$/', (string) $datain, $rxPgwMatch)) {
        $rxPgwGatewayKey = $rxPgwMatch[1];
        $rxPgwOrderId = $rxPgwMatch[2];
        $rxPgwMethodMap = rx_gateway_payment_method_map();
        $rxPgwMethod = $rxPgwMethodMap[$rxPgwGatewayKey] ?? $rxPgwGatewayKey;

        $rxPgwCancelled = cancelPendingGatewayInvoice($from_id, $rxPgwMethod, $rxPgwOrderId);
        if (!$rxPgwCancelled) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ این فاکتور دیگر قابل لغو نیست.',
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }

        $rxPgwLabel = rx_gateway_display_name($rxPgwGatewayKey);
        $rxPgwText = "✅ فاکتور قبلی لغو شد.\n\nبرای دریافت فاکتور جدید، دکمه زیر را بزنید.";
        $rxPgwKb = json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 دریافت فاکتور جدید {$rxPgwLabel}", 'callback_data' => $rxPgwGatewayKey]],
                [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $rxPgwText, $rxPgwKb, 'HTML');
    } elseif ($datain == "zarinpal") {
        if ($user['Processing_value'] < 5000) {
            sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpal", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpal", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'zarinpal');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'zarinpal');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayZarinpal($user['Processing_value'], $randomString);
        if ($pay['data']['code'] != 100) {
            $text_error = json_encode($pay['errors']);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پال
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpal";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['data']['authority']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://www.zarinpal.com/pg/StartPay/" . $pay['data']['authority']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "
✅ فاکتور پرداخت ایجاد شد.

🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpzarinpal", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "plisio") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        if ($usdprice <= 1) {
            sendmessage($from_id, $textbotlang['users']['Balance']['nowpayments'], null, 'HTML');
            return;
        }
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceplisio", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceplisio", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'plisio');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'plisio');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = plisio($randomString, $trxprice);
        $Payment_Method = "plisio";
        if (!is_array($pay) || isset($pay['message']) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $text_error = is_array($pay) ? ($pay['message'] ?? json_encode($pay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : 'پاسخ نامعتبر از Plisio';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['txn_id']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpplisio", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "nowpayment") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalancenowpayment", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalancenowpayment", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'nowpayment');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'nowpayment');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = nowPayments('invoice', $usdprice, $randomString, "order");
        $Payment_Method = "nowpayment";
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $text_error = json_encode($pay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['id']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

<blockquote>⚠️ پس از پرداخت، در صورتی که مبلغ تراکنش به‌درستی واریز شده باشد، موجودی شما حداکثر تا ۱۵ دقیقه آینده به‌صورت خودکار شارژ خواهد شد.</blockquote>

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpnowpayment", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "iranpay2") {
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay2", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay2", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'Currency Rial 2');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'iranpay2');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 2";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = trnado($randomString, (float) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس ترونادو'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['status_code'])) {
                $errorLines[] = "کد وضعیت HTTP: " . $paymentErrorData['status_code'];
            }
            if (isset($paymentErrorData['errno'])) {
                $errorLines[] = "کد خطای cURL: " . $paymentErrorData['errno'];
            }
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (isset($paymentErrorData['raw_response'])) {
                $errorLines[] = "پاسخ خام: " . $paymentErrorData['raw_response'];
            }

            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $paymentToken = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($payment) : (string) ($payment['Token'] ?? '');
        if ($paymentToken === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "dec_not_confirmed", $paymentToken, "id_order", $randomString);
        $paymentFullUrl = trim((string) ($payment['FullPaymentUrl'] ?? ''));
        if ($paymentFullUrl === '') {
            $paymentFullUrl = "https://t.me/tronado_robot/customerpayment?startapp={$paymentToken}";
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentFullUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .
❌ پس از تراکنش 15 تا یک ساعت زمان میبرد تا تراکنش تایید شود

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay2", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "tonpay") {
        $mainbalancetonpay = select("PaySetting", "ValuePay", "NamePay", "minbalancetonpay", "select")['ValuePay'];
        $maxbalancetonpay = select("PaySetting", "ValuePay", "NamePay", "maxbalancetonpay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancetonpay || $user['Processing_value'] > $maxbalancetonpay) {
            $mainbalancetonpay = number_format($mainbalancetonpay);
            $maxbalancetonpay = number_format($maxbalancetonpay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancetonpay, $maxbalancetonpay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'tonpay');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'tonpay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "tonpay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = tonpayCreateInvoice($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس تون‌پی'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $invoiceId = trim((string) ($payment['invoice_id'] ?? ''));
        $invoiceUrl = trim((string) ($payment['invoice_url'] ?? ''));
        if ($invoiceId === '' || $invoiceUrl === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "tonpay_invoice_id", $invoiceId, "id_order", $randomString);
        update("Payment_report", "tonpay_invoice_url", $invoiceUrl, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $invoiceUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۲۴ ساعت اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helptonpay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "blupal") {
        $mainbalanceblupal = select("PaySetting", "ValuePay", "NamePay", "minbalanceblupal", "select")['ValuePay'];
        $maxbalanceblupal = select("PaySetting", "ValuePay", "NamePay", "maxbalanceblupal", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceblupal || $user['Processing_value'] > $maxbalanceblupal) {
            $mainbalanceblupal = number_format($mainbalanceblupal);
            $maxbalanceblupal = number_format($maxbalanceblupal);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceblupal, $maxbalanceblupal), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'blupal');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'blupal');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "blupal";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = blupalCreateInvoice($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس بلوپال'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $invoiceId = trim((string) ($payment['invoice_id'] ?? ''));
        $paymentLink = trim((string) ($payment['payment_link'] ?? ''));
        if ($invoiceId === '' || $paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "blupal_invoice_id", $invoiceId, "id_order", $randomString);
        update("Payment_report", "blupal_payment_link", $paymentLink, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpblupal", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "atlaspay") {
        $mainbalanceatlaspay = select("PaySetting", "ValuePay", "NamePay", "minbalanceatlaspay", "select")['ValuePay'];
        $maxbalanceatlaspay = select("PaySetting", "ValuePay", "NamePay", "maxbalanceatlaspay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceatlaspay || $user['Processing_value'] > $maxbalanceatlaspay) {
            $mainbalanceatlaspay = number_format($mainbalanceatlaspay);
            $maxbalanceatlaspay = number_format($maxbalanceatlaspay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceatlaspay, $maxbalanceatlaspay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'atlaspay');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'atlaspay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "atlaspay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = atlaspayCreateOrder($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس اطلس‌پی'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $atlaspayOrderId = trim((string) ($payment['orderId'] ?? ''));
        $paymentUrl = trim((string) ($payment['customerStartLink'] ?? ''));
        $trackingCode = trim((string) ($payment['trackingCode'] ?? ''));
        if ($atlaspayOrderId === '' || $paymentUrl === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $totalAmountToman = isset($payment['totalAmountToman']) ? (int) $payment['totalAmountToman'] : (int) $user['Processing_value'];
        update("Payment_report", "atlaspay_order_id", $atlaspayOrderId, "id_order", $randomString);
        update("Payment_report", "atlaspay_tracking_code", $trackingCode, "id_order", $randomString);
        update("Payment_report", "atlaspay_payment_url", $paymentUrl, "id_order", $randomString);
        update("Payment_report", "price", $totalAmountToman, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($totalAmountToman, 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>" . ($trackingCode !== '' ? $trackingCode : $randomString) . "</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۲۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpatlaspay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "cubepay") {
        $mainbalancecubepay = select("PaySetting", "ValuePay", "NamePay", "minbalancecubepay", "select")['ValuePay'];
        $maxbalancecubepay = select("PaySetting", "ValuePay", "NamePay", "maxbalancecubepay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancecubepay || $user['Processing_value'] > $maxbalancecubepay) {
            $mainbalancecubepay = number_format($mainbalancecubepay);
            $maxbalancecubepay = number_format($maxbalancecubepay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancecubepay, $maxbalancecubepay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'cubepay');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'cubepay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "cubepay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = cubepayCreatePayment($randomString, (int) $user['Processing_value'], $from_id);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['message' => 'پاسخ نامعتبر از سرویس کیوب‌پی'];
        } elseif (empty($payment['success'])) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['message'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['message'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $authority = trim((string) ($payment['authority'] ?? ''));
        $paymentLink = trim((string) ($payment['payment_link'] ?? ''));
        if ($paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "cubepay_authority", $authority, "id_order", $randomString);
        update("Payment_report", "cubepay_payment_link", $paymentLink, "id_order", $randomString);
        update("Payment_report", "cubepay_method", (string) ($payment['method'] ?? 'choice'), "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcubepay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);

        // 🤖 اگر فروشنده در پنل کیوب‌پی گزینه‌ی «نمایش کارت در ربات» را روشن کرده
        // باشد، شماره‌کارت را همین‌جا داخل چت هم می‌فرستیم تا مشتری لازم نباشد از
        // تلگرام بیرون برود. این پیام «کنارِ» پیامِ بالا می‌نشیند، نه جای آن.
        if (!empty($payment['show_card_in_bot']) && !empty($payment['card']['number'])) {
            $cubepayCard = $payment['card'];
            $cubepayAmount = (int) ($payment['pay_amount_toman'] ?? 0);
            $cubepayMinutes = (int) ($payment['expires_in_minutes'] ?? 30);
            $cubepayCardText = "💳 <b>پرداخت کارت‌به‌کارت</b>\n"
                . "━━━━━━━━━━━━━━━\n\n"
                . "🔢 شماره کارت:\n<code>" . htmlspecialchars((string) $cubepayCard['number'], ENT_QUOTES, 'UTF-8') . "</code>\n"
                . (!empty($cubepayCard['holder']) ? "👤 به نام: " . htmlspecialchars((string) $cubepayCard['holder'], ENT_QUOTES, 'UTF-8') . "\n" : '')
                . "\n💰 مبلغ دقیق: <b>" . number_format($cubepayAmount) . "</b> تومان\n"
                . "⏳ مهلت پرداخت: " . $cubepayMinutes . " دقیقه\n\n"
                . "⚠️ مبلغ باید رقم‌به‌رقم دقیق باشد — تاییدِ خودکار فقط با همین عدد انجام می‌شود.";
            sendmessage($from_id, $cubepayCardText, null, 'HTML');
        }
    } elseif ($datain == "variza") {
        $mainbalancevariza = select("PaySetting", "ValuePay", "NamePay", "minbalancevariza", "select")['ValuePay'];
        $maxbalancevariza = select("PaySetting", "ValuePay", "NamePay", "maxbalancevariza", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancevariza || $user['Processing_value'] > $maxbalancevariza) {
            $mainbalancevariza = number_format($mainbalancevariza);
            $maxbalancevariza = number_format($maxbalancevariza);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancevariza, $maxbalancevariza), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'variza');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'variza');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "variza";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = varizaCreatePayment($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['message' => 'پاسخ نامعتبر از سرویس واریزا'];
        } elseif (empty($payment['success'])) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['message'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['message'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $slug = trim((string) ($payment['slug'] ?? ''));
        $paymentLink = trim((string) ($payment['pay_url'] ?? ''));
        if ($paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "dec_not_confirmed", $slug, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpvariza", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "abangateway") {
        $mainbalanceabangateway = select("PaySetting", "ValuePay", "NamePay", "minbalanceabangateway", "select")['ValuePay'];
        $maxbalanceabangateway = select("PaySetting", "ValuePay", "NamePay", "maxbalanceabangateway", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceabangateway || $user['Processing_value'] > $maxbalanceabangateway) {
            $mainbalanceabangateway = number_format($mainbalanceabangateway);
            $maxbalanceabangateway = number_format($maxbalanceabangateway);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceabangateway, $maxbalanceabangateway), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'abangateway');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'abangateway');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "abangateway";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = abangatewayCreatePayment($randomString, (int) $user['Processing_value'], $from_id);

        // abangatewayCreatePayment() only reports success with an authority and
        // an https link in hand, so one refusal path covers every failure.
        if (!is_array($payment) || empty($payment['success'])) {
            $text_error = "پیام خطا: " . (is_array($payment) && !empty($payment['message']) ? $payment['message'] : 'پاسخ نامعتبر از آبان گیت وی');
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        // The callback looks the order up by id and reads the authority from
        // here, the way the other gateways keep their invoice handle.
        update("Payment_report", "dec_not_confirmed", $payment['authority'], "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $payment['pay_url']]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textabangatewaypay = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 صفحه پرداخت شماره کارت و <b>مبلغ دقیق</b> را نشان میدهد. همان عدد را، رقم به رقم، کارت به کارت کنید؛ تایید خودکار با همان عدد انجام میشود.
🔹 مهلت پرداخت روی همان صفحه نمایش داده میشود.
🔹 بعد از واریز لازم نیست رسید بفرستید؛ تایید خودکار است و نتیجه همین جا به شما اطلاع داده میشود.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpabangateway", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode((string) $gethelp, true);
            if (is_array($data)) {
                if (($data['type'] ?? '') == "text") {
                    sendmessage($from_id, $data['text'], null, 'HTML');
                } elseif (($data['type'] ?? '') == "photo") {
                    sendphoto($from_id, $data['photoid'], null);
                } elseif (($data['type'] ?? '') == "video") {
                    sendvideo($from_id, $data['videoid'], null);
                }
            }
        }
        $message_id = sendmessage($from_id, $textabangatewaypay, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "digitaltron") {

        $mainbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "minbalancedigitaltron", "select")['ValuePay'];
        $maxbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "maxbalancedigitaltron", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancedigitaltron || $user['Processing_value'] > $maxbalancedigitaltron) {
            $minF = number_format($mainbalancedigitaltron);
            $maxF = number_format($maxbalancedigitaltron);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $minF, $maxF), null, 'HTML');
            return;
        }

        if (!function_exists('crypto_active_wallets')) {
            sendmessage($from_id, $datatextbot['dyn_wallet_hashchecker_not_loaded'] ?? "❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $available = crypto_active_wallets();
        if (empty($available)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_no_wallet_address_configured'] ?? "❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است. لطفاً با پشتیبانی در تماس باشید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $faShort = [
            'TRX' => '🟥 ترون (TRX)',
            'TON' => '🟦 تون (TON)',
            'USDT_TRC20' => '🟢 تتر روی ترون',
            'USDT_TON'   => '🟢 تتر روی تون',
        ];
        $styleCurrency = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('currency_pick') : null;
        $manualCurListPicker = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        $rows = [];
        $hasAutoInList = false;
        $hasManualInList = false;
        foreach ($available as $w) {
            $cur = (string) $w['currency'];
            $isManualPick = isset($manualCurListPicker[$cur]);
            if ($isManualPick) { $hasManualInList = true; } else { $hasAutoInList = true; }
            $label = $faShort[$cur] ?? $cur;
            if ($isManualPick) { $label .= ' 🛠'; }
            $btn = ['text' => $label, 'callback_data' => 'digitaltron_pay_' . $cur];
            if ($styleCurrency) $btn['style'] = $styleCurrency;
            $rows[] = [$btn];
        }
        $rows[] = [['text' => '❌ بستن', 'callback_data' => 'colselist']];
        $picker = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        if ($hasAutoInList && $hasManualInList) {
            $hashHint = "ℹ️ برای شبکه‌های <b>خودکار</b> (بدون 🛠)، فقط هش (Hash / TxID) تراکنش کافی است — نیازی به ارسال عکس نیست و ربات ظرف چند دقیقه به‌صورت خودکار تایید می‌کند.\n"
                      . "ℹ️ برای شبکه‌های <b>دستی</b> (🛠)، بعد از هش باید <b>عکس رسید تراکنش</b> نیز ارسال شود و تایید نهایی توسط ادمین انجام می‌شود.";
        } elseif ($hasManualInList) {
            $hashHint = "ℹ️ این شبکه‌ها به‌صورت خودکار بررسی نمی‌شوند. بعد از پرداخت، هش (Hash / TxID) و سپس <b>عکس رسید تراکنش</b> را ارسال می‌کنید و تایید نهایی توسط ادمین انجام می‌شود.";
        } else {
            $hashHint = "ℹ️ پس از پرداخت، فقط هش (Hash / TxID) تراکنش را برای ربات می‌فرستید — نیازی به ارسال عکس نیست. "
                      . "هش هم به‌صورت خام و هم به‌صورت لینک (مثلاً <code>tonviewer.com/transaction/...</code> یا <code>tronscan.org/#/transaction/...</code>) قابل ارسال است.";
        }
        $msg = "💎 <b>پرداخت با ارز دیجیتال</b>\n\n"
             . "شبکه‌ای که می‌خواهید با آن پرداخت کنید را انتخاب کنید. بعد از انتخاب، ربات یک آدرس کیف پول و یک <b>مبلغ دقیق</b> به شما اعلام می‌کند.\n\n"
             . $hashHint;
        sendmessage($from_id, $msg, $picker, 'HTML');
    } elseif (preg_match('/^digitaltron_(?:pay|paymode_(?:ext|ir))_([A-Za-z0-9_]{2,20})$/', (string) $datain, $dpmm)
        && in_array($dpmm[1], array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency'), true)
    ) {

        $cur = $dpmm[1];
        $manualCurList = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        $isManualCur = isset($manualCurList[$cur]);
        $amountIrt = (int) ($user['Processing_value'] ?? 0);
        if ($amountIrt <= 0) {
            sendmessage($from_id, $datatextbot['dyn_wallet_charge_amount_undetectable'] ?? "❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $invoiceMeta = sprintf('%s|%s', $user['Processing_value_tow'] ?? '', $user['Processing_value_one'] ?? '');
        $result = crypto_create_invoice($from_id, $amountIrt, $cur, $invoiceMeta);
        if (empty($result['ok'])) {
            $errMap = [
                'currency-not-supported' => 'این ارز فعال نیست.',
                'wallet-not-configured'  => 'آدرس کیف پول این ارز توسط ادمین تنظیم نشده است.',
                'below-min'              => 'مبلغ کمتر از حداقل مجاز است.',
                'above-max'              => 'مبلغ بیشتر از حداکثر مجاز است.',
                'rate-unavailable'       => 'دریافت نرخ لحظه‌ای ممکن نبود؛ لطفاً دقایقی دیگر تلاش کنید.',
                'db-write-failed'        => 'خطای داخلی در ثبت فاکتور.',
            ];
            $reason = $result['error'] ?? 'unknown';
            $faMsg = $errMap[$reason] ?? $reason;
            sendmessage($from_id, "❌ {$faMsg}", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $displayDecimals = function_exists('crypto_display_decimals') ? crypto_display_decimals($cur) : 2;
        $amountStr = number_format((float) $result['amount_coin'], $displayDecimals, '.', '');
        if (strpos($amountStr, '.') !== false) {
            $amountStr = rtrim(rtrim($amountStr, '0'), '.');
        }
        $expireMin = max(1, (int) round(($result['expires_at'] - time()) / 60));
        if ($isManualCur) {
            $explorerHint = '<i>(این شبکه به‌صورت خودکار بررسی نمی‌شود؛ هش تراکنش پس از ارسال توسط ادمین بررسی خواهد شد)</i>';
        } else {
            $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
                ? '<i>(لینک‌های Tronscan قابل قبول است)</i>'
                : '<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>';
        }
        $walletEsc = htmlspecialchars((string) $result['wallet']);
        $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
        $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);

        $finalAmountIrt = (int) ($result['final_amount_irt'] ?? $amountIrt);
        $priceBlock = "💸 مبلغ قابل پرداخت: " . number_format($finalAmountIrt) . " تومان\n";

        $amountNotice = "❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.";

        $memoBlock = "";
        if ($isTonNetwork && $walletMemo !== '') {
            $memoEsc = htmlspecialchars($walletMemo);
            $memoBlock = "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{$memoEsc}</code>\n"
                       . "⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n";
        }

        if ($isManualCur) {
            $invoiceMsg = "🛠 <b>فاکتور پرداخت کریپتو (بررسی دستی)</b>\n\n"
                 . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                 . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                 . $priceBlock . "\n"
                 . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                 . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                 . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                 . $memoBlock
                 . $amountNotice . "\n"
                 . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                 . "⚠️ <b>این شبکه به‌صورت خودکار بررسی نمی‌شود.</b> بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید، "
                 . "هش/لینک تراکنش را ارسال کنید و سپس <b>عکس رسید تراکنش را نیز ارسال کنید</b> — بدون عکس رسید، درخواست شما برای ادمین ثبت نمی‌شود.\n{$explorerHint}";
        } else {
            $invoiceMsg = "💎 <b>فاکتور پرداخت کریپتو</b>\n\n"
                 . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                 . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                 . $priceBlock . "\n"
                 . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                 . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                 . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                 . $memoBlock
                 . $amountNotice . "\n"
                 . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                 . "بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{$explorerHint}";
        }

        $styleWallet = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_wallet')  : null;
        $styleAmount = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_amount')  : null;
        $styleMemo   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_memo')    : null;
        $stylePaid   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('paid_submit')  : null;
        $styleBack   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('invoice_back') : null;

        $btnCopyWallet = ['text' => '🔗 کپی آدرس ولت', 'copy_text' => ['text' => (string) $result['wallet']]];
        if ($styleWallet) $btnCopyWallet['style'] = $styleWallet;

        $btnCopyAmount = ['text' => '🪙 کپی مقدار', 'copy_text' => ['text' => $amountStr]];
        if ($styleAmount) $btnCopyAmount['style'] = $styleAmount;

        $btnPaid = ['text' => '✅ پرداخت کردم | ارسال هش (TXID) 🧾', 'callback_data' => 'digitaltron_submit_' . $result['order_id']];
        if ($stylePaid) $btnPaid['style'] = $stylePaid;

        $invoiceKbRows = [
            [$btnCopyWallet, $btnCopyAmount],
        ];
        if ($isTonNetwork && $walletMemo !== '') {
            $btnCopyMemo = ['text' => '🏷 کپی ممو', 'copy_text' => ['text' => $walletMemo]];
            if ($styleMemo) $btnCopyMemo['style'] = $styleMemo;
            $invoiceKbRows[] = [$btnCopyMemo];
        }
        $invoiceKbRows[] = [$btnPaid];
        $btnInvoiceBack = ['text' => '🔙 بازگشت (لغو فاکتور)', 'callback_data' => 'crypto_cancel_' . $result['order_id']];
        if ($styleBack) $btnInvoiceBack['style'] = $styleBack;
        $invoiceKbRows[] = [$btnInvoiceBack];
        $invoiceKb = json_encode(['inline_keyboard' => $invoiceKbRows], JSON_UNESCAPED_UNICODE);

        $resp = null;
        $fancyQrPath = null;
        $fancyOk = false;
        if (!(function_exists('isQrDisabled') && isQrDisabled())) {
            try {
                $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
                $autoload = $rootDir . '/vendor/autoload.php';
                if (is_file($autoload)) @require_once $autoload;
                if (class_exists('\\Endroid\\QrCode\\Builder\\Builder') && function_exists('addBackgroundImage') && strlen((string) $result['wallet']) <= 2048) {
                    $builder = new \Endroid\QrCode\Builder\Builder(
                        writer: new \Endroid\QrCode\Writer\PngWriter(),
                        writerOptions: [],
                        data: (string) $result['wallet'],
                        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                        size: 560,
                        margin: 2,
                    );
                    $built = $builder->build();
                    $fancyQrPath = $rootDir . DIRECTORY_SEPARATOR . 'cryptoqr_' . bin2hex(random_bytes(3)) . '.png';
                    @file_put_contents($fancyQrPath, $built->getString());
                    $made = @addBackgroundImage($fancyQrPath, $built, 'images.jpeg');
                    if ($made && is_file($fancyQrPath) && function_exists('telegram')) {
                        $resp = @telegram('sendphoto', [
                            'chat_id'      => (string) $from_id,
                            'photo'        => new \CURLFile($fancyQrPath),
                            'caption'      => $invoiceMsg,
                            'parse_mode'   => 'HTML',
                            'reply_markup' => $invoiceKb,
                        ]);
                        $fancyOk = true;
                    }
                }
            } catch (\Throwable $_) {  }
            if ($fancyQrPath && is_file($fancyQrPath)) { @unlink($fancyQrPath); }
        }

        if (!$fancyOk) {
            $resp = sendmessage($from_id, $invoiceMsg, $invoiceKb, 'HTML');
        }
        updatePaymentMessageId($resp, $result['order_id']);
    } elseif (preg_match('/^crypto_cancel_([A-Za-z0-9_\-]+)$/', (string) $datain, $dcm)) {

        $orderId = $dcm[1];
        if (function_exists('getDatabaseConnection')) {
            $pdo = getDatabaseConnection();
            if ($pdo instanceof \PDO) {
                try {
                    if (function_exists('rx_release_unpaid_discount')) {
                        $rxRep = $pdo->prepare("SELECT time FROM Payment_report WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','AwaitingHash') LIMIT 1");
                        $rxRep->execute([':o' => $orderId, ':u' => (string) $from_id]);
                        $rxRepRow = $rxRep->fetch(\PDO::FETCH_ASSOC);
                        $rxRefTime = (is_array($rxRepRow) && !empty($rxRepRow['time'])) ? strtotime(str_replace('/', '-', (string) $rxRepRow['time'])) : null;
                        rx_release_unpaid_discount((string) $from_id, null, $rxRefTime ?: null);
                    }
                    $stmt = $pdo->prepare(
                        "UPDATE Payment_report
                            SET payment_Status = 'expire'
                          WHERE id_order = :o
                            AND id_user = :u
                            AND payment_Status IN ('Unpaid', 'AwaitingHash')"
                    );
                    $stmt->execute([':o' => $orderId, ':u' => (string) $from_id]);
                } catch (\Throwable $_) {  }
            }
        }
        if (!empty($message_id) && function_exists('deletemessage')) {
            @deletemessage((string) $from_id, (int) $message_id);
        }
        sendmessage((string) $from_id, "✅ فاکتور لغو شد.", $keyboard, 'HTML');
    } elseif (preg_match('/^digitaltron_submit_([A-Za-z0-9_\-]+)$/', (string) $datain, $dsm)) {

        $orderId = $dsm[1];
        $reportStmt = $connect->prepare("SELECT id_order, payment_Status FROM Payment_report WHERE id_order = ? AND id_user = ? LIMIT 1");
        $userIdStr = (string) $from_id;
        $reportStmt->bind_param('ss', $orderId, $userIdStr);
        $reportStmt->execute();
        $rowChk = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (!is_array($rowChk)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
            return;
        }
        if (!in_array($rowChk['payment_Status'], ['Unpaid', 'AwaitingHash'], true)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_pending'] ?? "❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.", $keyboard, 'HTML');
            return;
        }
        update("user", "Processing_value_four", $orderId, "id", $from_id);
        step('digitaltron_hash_input', $from_id);
        $cancelKb = json_encode([
            'inline_keyboard' => [
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_hash_input']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage(
            $from_id,
            "📨 لطفاً <b>هش (TxID) تراکنش</b> را ارسال کنید.\n\n"
            . "هر دو فرمت قابل قبول است:\n"
            . "• هش خام (مثلاً <code>09d6aaee138447689f92a0ee7a4382dd01fcc88413f8644f2dd8fb772b0c9402</code>)\n"
            . "• لینک کامل از Tonviewer / Tonscan / Tronscan",
            $cancelKb,
            'HTML'
        );
    } elseif ($datain == "startelegrams") {
        $rates = requireTronRates(['USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $usd = $rates['USD'];
        if (!is_numeric($usd) || $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $userAmountUsd = round($user['Processing_value'] / $usd, 2);
        $starPriceSetting = getPaySettingValue('star_price_usd', '0.016');
        if (is_string($starPriceSetting)) {
            $starPriceSetting = str_replace(',', '.', $starPriceSetting);
        }
        $starPriceUsd = is_numeric($starPriceSetting) ? (float) $starPriceSetting : 0.016;
        if ($starPriceUsd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $starAmount = (int) ceil($userAmountUsd / $starPriceUsd);
        if ($starAmount < 1) {
            $starAmount = 1;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancestar", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancestar", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Star Telegram";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $affilnecurrency = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
        $invoiceParams = [
            'title' => "Buy for Price {$user['Processing_value']}",
            'description' => "Buy price",
            'payload' => $randomString,
            'currency' => "XTR",
            'prices' => json_encode(array(
                array(
                    'label' => "Price",
                    'amount' => $starAmount
                )
            ))
        ];
        if (($invoiceParams['currency'] ?? null) === 'XTR') {
            unset($invoiceParams['provider'], $invoiceParams['provider_token']);
        }
        $straCreateLink = telegram('createInvoiceLink', $invoiceParams);
        if ($straCreateLink['ok'] == false) {
            $text_error = json_encode($straCreateLink);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
خطا در هنگام ساخت فاکتور استار
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $straCreateLink['result']]
                ]
            ]
        ]);
        $formatprice = number_format($user['Processing_value'], 0);
        $approxStarUsd = number_format($starAmount * $starPriceUsd, 2);
        $textstar = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری: <code>$randomString</code>
💲 مبلغ تراکنش: $starAmount ⭐ (حدوداً $approxStarUsd دلار | معادل $formatprice تومان)

📌 لطفاً مبلغ $formatprice تومان را به استار تلگرام تبدیل کرده و واریز نمایید.

💢 نکات مهم قبل از پرداخت: 👇
🔹 هر تراکنش ۱ روز معتبر است؛ بعد از انقضا از واریز خودداری کنید.

✅ در صورت مشکل، با پشتیبانی در ارتباط باشید.";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpstar", "select")['ValuePay'];
        if (intval($gethelp) != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textstar, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    }

    if (isset($randomString) && $randomString !== '') {
        if ($__chargeBonus > 0) {
            update("Payment_report", "charge_bonus", $__chargeBonus, "id_order", $randomString);
        }
        $__pv4 = (string)($user['Processing_value_four'] ?? '');
        if (strpos($__pv4, 'chg|') === 0) {
            $__pv4Parts = explode('|', $__pv4);
            $__pendingDiscountCode = isset($__pv4Parts[2]) ? trim((string)$__pv4Parts[2]) : '';
            $__pendingPriceBefore  = isset($__pv4Parts[3]) ? (float)$__pv4Parts[3] : 0.0;
            if ($__pendingDiscountCode !== '') {
                update("Payment_report", "discount_code", $__pendingDiscountCode, "id_order", $randomString);
                update("Payment_report", "discount_amount", (string)$__chargeBonus, "id_order", $randomString);
                update("Payment_report", "price_before_discount", (string)$__pendingPriceBefore, "id_order", $randomString);
            }
        }
    }
}
