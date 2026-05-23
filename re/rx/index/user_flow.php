<?php

if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || ($text == $datatextbot['text_usertest'] || $datain == "usertestbtn" || $text == "usertest")) {
    if (!check_active_btn($setting['keyboardmain'], "text_usertest")) {
        sendmessage($from_id, "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $userlimit = select("user", "*", "id", $from_id, "select");
    if ($userlimit['limit_usertest'] <= 0 && !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    if ($setting['get_number'] == "onAuthenticationphone" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "onAuthenticationphone")
        return;
    $locationproduct = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "count");
    if ($locationproduct == 1) {
        $panel = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "select");
        if ($panel['hide_user'] != null) {
            $list_user = json_decode($panel['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
        }
        $location = $panel['code_panel'];
    } else {
        if (isset($dataget[1])) {
            $location = $dataget[1];
        } else {
            if ($user['step'] != "createusertest") {
                return;
            } else {
                $location = $user['Processing_value_one'];
            }
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $location, "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if ($user['step'] != "createusertest") {
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $location, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
            return;
        }
    } else {
        $name_panel = $location;
    }
    if ($user['step'] == "createusertest") {
        $name_panel = $user['Processing_value_one'];
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
            return;
        }
    } else {
        deletemessage($from_id, $message_id);
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $value = "usertest";
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $value);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, "❌ موجودی این سرویس به پایان رسیده.", null, 'HTML');
            return;
        }
    }
    $limit_usertest = $userlimit['limit_usertest'] - 1;
    update("user", "limit_usertest", $limit_usertest, "id", $from_id);
    $randomString = bin2hex(random_bytes(4));
    $text = strtolower($text);
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $name_panel, "select");
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $user['username'], $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", strtotime("+" . $marzban_list_get['time_usertest'] . "hours"))),
        'data_limit' => $marzban_list_get['val_usertest'] * 1048576,
        'from_id' => $from_id,
        'username' => $username_ac,
        'type' => 'usertest'
    );
    $date = time();
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?,  ?, ?, ?, ?, ?,?,?,?,?)");
    $Status = "active";
    $info_product['name_product'] = "سرویس تست";
    $info_product['price_product'] = "0";
    $Status = "active";
    $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $marzban_list_get['val_usertest'], $marzban_list_get['time_usertest'], $Status, $notifctions);
    $stmt->execute();
    $stmt->close();
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], "usertest", $username_ac, $datac);
    if ($dataoutput['username'] == null) {
        $dataoutput['msg'] = json_encode($dataoutput['msg']);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        update("invoice", "Status", "Unsuccessful", "id_invoice", $randomString);
        return;
    }
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
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
    if ($marzban_list_get['type'] == "WGDashboard") {
        $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    $datatextbot['textaftertext'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textaftertext'];
    $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textaftertext']);
    $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $marzban_list_get['time_usertest'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $marzban_list_get['val_usertest'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString);
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
    $text_report = "📣 جزئیات ساخت اکانت تست در ربات شما ثبت شد .
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :$username_ac
▫️نام کاربر : $first_name
▫️موقعیت سرویس : {$marzban_list_get['name_panel']}
▫️زمان خریداری شده : {$marzban_list_get['time_usertest']} ساعت
▫️حجم خریداری شده : {$marzban_list_get['val_usertest']} MB
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif ($text == $datatextbot['text_help'] || $datain == "helpbtn" || $datain == "helpbtns" || $text == "/help" || $text == "help") {
    if (!check_active_btn($setting['keyboardmain'], "text_help")) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    if ($setting['categoryhelp'] == "1") {
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, "📌 یک دسته را انتخاب نمایید", $json_list_helpـcategory, 'HTML');
        } else {
            sendmessage($from_id, "📌 یک دسته را انتخاب نمایید", $json_list_helpـcategory, 'HTML');
        }
    } else {
        $helplist = select("help", "*", null, null, "fetchAll");
        $helpidos = ['inline_keyboard' => []];
        foreach ($helplist as $result) {
            $helpidos['inline_keyboard'][] = [
                ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
            ];
        }
        if ($setting['linkappstatus'] == "1") {
            $helpidos['inline_keyboard'][] = [
                ['text' => "🔗 لینک دانلود برنامه", 'callback_data' => "linkappdownlod"],
            ];
        }
        $helpidos['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"],
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
        if (strlen($helpdata['Media_os']) != 0) {
            if ($helpdata['type_Media_os'] == "video") {
                $backinfoss = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
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
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
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
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ]
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
            sendmessage($from_id, $helpdata['Description_os'], $backinfoss, 'HTML');
        }
    }
} elseif ($text == $datatextbot['text_support'] || $datain == "supportbtns" || $text == "/support") {
    if (!check_active_btn($setting['keyboardmain'], "text_support")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain == "supportbtns") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $supportoption);
    } else {
        sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $supportoption, 'HTML');
    }
} elseif ($datain == "support") {
    Editmessagetext($from_id, $message_id, "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.", $list_departman, 'HTML');
} elseif (preg_match('/^departman_(.*)/', $datain, $dataget)) {
    $iddeparteman = $dataget[1];
    savedata("clear", "iddeparteman", $iddeparteman);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 پیام خود را ارسال نمایید", $backuser, 'HTML');
    step("gettextticket", $from_id);
} elseif ($user['step'] == "gettextticket" && $text) {
    $userdata = json_decode($user['Processing_value'], true);
    $departeman = select("departman", "*", "id", $userdata['iddeparteman'], "select");
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (:Tracking,:idsupport,:iduser,:name_departman,:text,:time,:status)");
    $status = "Unseen";
    $stmt->bindParam(':Tracking', $randomString);
    $stmt->bindParam(':idsupport', $departeman['idsupport']);
    $stmt->bindParam(':iduser', $from_id);
    $stmt->bindParam(':name_departman', $departeman['name_departman']);
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    if ($photo) {
        sendphoto($departeman['idsupport'], $photoid, null);
    }
    if ($video) {
        sendvideo($departeman['idsupport'], $videoid, null);
    }
    $textsuppoer = "
    📣 پشتیبان عزیز یک پیام از سمت کاربر برای شما ارسال گردید.

آیدی عددی کاربر : <a href = \"tg://user?id=$from_id\">$from_id</a>
زمان ارسال : $timejalali
وضعیت پیام : پاسخ داده نشده
نام کاربری کاربر : @$username
نام دپارتمان : {$departeman['name_departman']}

متن پیام : $text $caption";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesupport_' . $randomString],
            ],
        ]
    ]);
    sendmessage($departeman['idsupport'], $textsuppoer, $Response, 'HTML');
    sendmessage($from_id, "✅ پیام شما با موفقیت ارسال و پس از بررسی به شما پاسخ داده خواهد شد.", $keyboard, 'HTML');
    step("home", $from_id);
    step("home", $departeman['idsupport']);
} elseif (preg_match('/Responsesupport_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $trakingdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($trakingdetail)) {
        if (function_exists('rx_log_event')) {
            rx_log_event('SUPPORT_REPLY_UNKNOWN_TICKET', 'Reply attempt for missing ticket', [
                'from_id' => $from_id,
                'tracking' => $idtraking,
            ]);
        }
        sendmessage($from_id, "❌ این تیکت وجود ندارد.", null, 'HTML');
        return;
    }


    if ((string) ($trakingdetail['idsupport'] ?? '') !== (string) $from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('SUPPORT_REPLY_FORBIDDEN', 'Non-support user attempted to reply to ticket', [
                'from_id' => $from_id,
                'tracking' => $idtraking,
                'idsupport' => $trakingdetail['idsupport'] ?? null,
            ]);
        }
        sendmessage($from_id, "❌ شما به این تیکت دسترسی ندارید.", null, 'HTML');
        return;
    }
    if ($trakingdetail['status'] == "Answered") {
        sendmessage($from_id, "❌ پیام توسط ادمین دیگری پاسخ داده شده.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید", $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextsupport", $from_id);
} elseif ($user['step'] == "getextsupport") {
    $trakingdetail = select("support_message", "*", "Tracking", $user['Processing_value']);
    if (!is_array($trakingdetail)) {
        sendmessage($from_id, "❌ این تیکت وجود ندارد.", null, 'HTML');
        step("home", $from_id);
        return;
    }


    if ((string) ($trakingdetail['idsupport'] ?? '') !== (string) $from_id) {
        sendmessage($from_id, "❌ شما به این تیکت دسترسی ندارید.", null, 'HTML');
        step("home", $from_id);
        return;
    }
    $time = date('Y/m/d H:i:s');
    update("support_message", "status", "Answered", "Tracking", $user['Processing_value']);
    update("support_message", "result", $text, "Tracking", $user['Processing_value']);
    $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.

متن پیام :
$text";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesusera_' . $trakingdetail['Tracking']],
            ],
        ]
    ]);
    sendmessage($trakingdetail['iduser'], $textSendAdminToUser, $Response, 'HTML');
    sendmessage($from_id, "پیام با موفقیت ارسال گردید", null, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/Responsesusera_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید", $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextuserfors", $from_id);
} elseif ($user['step'] == "getextuserfors") {
    $trakingdetail = select("support_message", "*", "Tracking", $user['Processing_value']);
    step("home", $from_id);
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $randomString = bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,time,status) VALUES (:Tracking,:idsupport,:iduser,:name_departman,:text,:time,:status)");
    $status = "Customerresponse";
    $stmt->bindParam(':Tracking', $randomString);
    $stmt->bindParam(':idsupport', $trakingdetail['idsupport']);
    $stmt->bindParam(':iduser', $trakingdetail['iduser']);
    $stmt->bindParam(':name_departman', $trakingdetail['name_departman']);
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    $textsuppoer = "
    📣 پشتیبان عزیز یک پیام از سمت کاربر برای شما ارسال گردید.

آیدی عددی کاربر : <a href = \"tg://user?id=$from_id\">$from_id</a>
زمان ارسال : $timejalali
وضعیت پیام : پاسخ مشتری
نام کاربری کاربر : @$username
نام دپارتمان : {$trakingdetail['name_departman']}

متن پیام : $text";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responsesupport_' . $randomString],
            ],
        ]
    ]);
    if ($photo) {
        sendphoto($trakingdetail['idsupport'], $photoid, null);
    }
    if ($video) {
        sendvideo($trakingdetail['idsupport'], $videoid, null);
    }
    sendmessage($trakingdetail['idsupport'], $textsuppoer, $Response, 'HTML');
    sendmessage($from_id, "✅  پیام شما برای این درخواست با موفقیت ارسال گردید پس از بررسی پاسخ داده خواهد شد.", null, 'HTML');
} elseif ($datain == "fqQuestions") {
    sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
} elseif ($text == $datatextbot['accountwallet'] || $datain == "account" || $text == "/wallet") {
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
    $text_account = "
🗂 اطلاعات حساب کاربری شما :


🪪 شناسه کاربری: <code>$from_id</code>
👤 نام: <code>$first_name</code>
👨‍👩‍👦 کد معرف شما : <code>{$user['codeInvitation']}</code>
📱 شماره تماس :$numberphone
⌚️زمان ثبت نام : $userjoin
💰 موجودی: $Balanceuser تومان
🛒 تعداد سرویس های خریداری شده : $countorder عدد
📑 تعداد فاکتور های پرداخت شده :  : $countpayment عدد
🤝 تعداد زیر مجموعه های شما : {$user['affiliatescount']} نفر
🔖 گروه کاربری : $groupuser
$textscore
$textinvite

📆 $dateacc → ⏰ $timeacc
                    ";
    if ($datain == "account") {
        Editmessagetext($from_id, $message_id, $text_account, $keyboardPanel);
    } else {
        sendmessage($from_id, $text_account, $keyboardPanel, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (($text == $datatextbot['text_sell'] || $datain == "buy" || $datain == "buyback" || $text == "/buy" || $text == "buy") && $statusnote) {
    if ($setting['get_number'] == "onAuthenticationphone" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "onAuthenticationphone")
        return;
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
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
} elseif ($text == $datatextbot['text_sell'] || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom") {
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active'");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($setting['get_number'] == "onAuthenticationphone" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "onAuthenticationphone")
        return;

    if (mysqli_num_rows($locationproduct) == 1) {
        $singlePanelRow = mysqli_fetch_assoc($locationproduct);
        if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
            if ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback") {
                Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_user);
            } else {
                sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'HTML');
            }
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
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE status = 'active' AND (status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
        $stmt->execute();
        $countinovoice = $stmt->rowCount();
        if ($locationproduct['limit_panel'] != "unlimited") {
            if ($countinovoice >= $locationproduct['limit_panel']) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanelfirst'], null, 'HTML');
                return;
            }
        }
        if ($user['step'] == "statusnamecustom") {
            savedata('clear', "nameconfig", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        if ($setting['statuscategory'] == "offcategory") {
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
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
                if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
                    return;
                }
                $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
                sendmessage($from_id, $textcustom, $backuser, 'html');
                step('gettimecustomvol', $from_id);
                return;
            }
            if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
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
                $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')";
                $queryParams = [
                    ':location' => $location,
                    ':agent' => $user['agent']
                ];
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                    $statuscustom = true;
                } else {
                    $statuscustom = false;
                }
                $textproduct = $textbotlang['users']['sell']['Service-select-first'];
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
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
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
        savedata('clear', "nameconfig", $text);
        step("home", $from_id);
    }
    error_log($text);
    if ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback") {
        Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_user);
    } else {
        sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'HTML');
    }
} elseif (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") {
    $userdate = json_decode($user['Processing_value'], true);
    if ($datain != "backproduct") {
        $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    } else {
        $location = $userdate['name_panel'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $locationproductcount = select("marzban_panel", "*", "name_panel", $location, "count");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND  Service_location = :loc");
    $stmt->execute([':loc' => (string)($marzban_list_get['name_panel'] ?? '')]);
    $countinovoice = $stmt->rowCount();
    if ($marzban_list_get['limit_panel'] != "unlimited") {
        if ($countinovoice >= $marzban_list_get['limit_panel']) {
            // If this panel has an emergency fallback configured (and enabled),
            // skip the hard cap here — the create-user step (≈line 1330) will
            // automatically route the order to the emergency panel when the
            // main one rejects it. Same for national-net inventory mode.
            $hasEmergencyFallback =
                (function_exists('nmPanelEmergencyEnabled') && nmPanelEmergencyEnabled($marzban_list_get)
                    && trim((string)($marzban_list_get['emergency_source_panel'] ?? '')) !== '')
                || (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get));
            if (!$hasEmergencyFallback) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanel'], null, 'HTML');
                return;
            }
        }
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
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
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
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        step('gettimecustomvol', $from_id);
        return;
    }
    if (function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get))) {
        // Only force the category picker when the shop actually sells via
        // categories AND there is at least one matching category; otherwise the
        // list would be empty. Fall through to the normal product-listing logic
        // below so national-net mode behaves like the regular (working) flow.
        if (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent'])) {
            $back = isset($userdate['nameconfig']) ? "buybacktow" : "buyback";
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $back));
            return;
        }
    }
    if ($setting['statuscategory'] == "offcategory") {
        if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], "buybacktow"));
        } else {
            $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')";
            $queryParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
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
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, "customsellvolume", $user['agent'], $queryParams));
        }
    } else {
        $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')");
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
    // Match products that reference this category by remark OR id OR name/title
    // (mirrors KeyboardCategory/nmSellableCategoryRows) so a category that was
    // shown in the list always yields its products.
    $catValues = function_exists('nmCategoryLookupValues') ? nmCategoryLookupValues($categorynames) : [];
    foreach ([$categorynames, $categoryId] as $extra) { if (trim((string)$extra) !== '') $catValues[] = (string)$extra; }
    $catValues = array_values(array_unique(array_filter(array_map('strval', $catValues), static function ($v) { return trim($v) !== ''; })));
    if (!$catValues) $catValues = [(string)$categorynames];
    $catIn = [];
    $catParams = [];
    foreach ($catValues as $ci => $cv) { $key = ':catv' . $ci; $catIn[] = $key; $catParams[$key] = $cv; }
    $catClause = 'category IN (' . implode(',', $catIn) . ')';
    if (isset($userdate['monthproduct']) && !(function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get)))) {
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND {$catClause} AND Service_time = :service_time AND (agent = :agent OR agent = 'all')";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':service_time' => $userdate['monthproduct'],
            ':agent' => $user['agent']
        ], $catParams);
    } else {
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND {$catClause} AND (agent = :agent OR agent = 'all')";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':agent' => $user['agent']
        ], $catParams);
    }
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservices_";
    } else {
        $datakeyboard = "prodcutservice_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select-first'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (function_exists('nmPanelNationalEnabled') && (nmPanelNationalEnabled($marzban_list_get) || nmPanelEmergencyEnabled($marzban_list_get))) {
        // Show the category picker only when the shop sells via categories and
        // at least one matching category exists; otherwise fall through to the
        // normal time-based product listing so the list isn't empty.
        if (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($marzban_list_get['name_panel'], $user['agent'])) {
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], "location_{$marzban_list_get['code_panel']}"));
            return;
        }
    }
    if ($setting['statuscategorygenral'] == "oncategorys" && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($userdate['name_panel'], $user['agent']))) {
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
        $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND Service_time = :service_time AND (agent = :agent OR agent = 'all')";
        $queryParams = [
            ':location' => $userdate['name_panel'],
            ':service_time' => $monthenumber,
            ':agent' => $user['agent']
        ];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
            $datakeyboard = "prodcutservices_";
        } else {
            $datakeyboard = "prodcutservice_";
        }
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
            $statuscustom = true;
        } else {
            $statuscustom = false;
        }
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select-first'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams));
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
🔔قیمت هر گیگ حجم $custompricevalue تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvol', $from_id);
} elseif ($user['step'] == "gettimecustomvol") {
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
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        step('getvolumecustomusername', $from_id);
    } else {
        step('getvolumecustomuser', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusername" || preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    if ($user['step'] == "getvolumecustomusername") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
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
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusers', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuser', $from_id);
        deletemessage($from_id, $message_id);
    }
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
} elseif ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuser") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($user['step'] == "getvolumecustomuser") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
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
        sendmessage($from_id, "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
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
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
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
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
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
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
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
        '{username}' => $username_ac,
        '{name_product}' => $info_product['name_product'],
        '{Service_time}' => $info_product['Service_time'],


        '{note}' => $info_product['note'] ?? '',
        '{price}' => $info_product_price_product,
        '{Volume}' => $info_product['Volume_constraint'],
        '{userBalance}' => $userBalance
    ];
    $textin = strtr($datatextbot['text_pishinvoice'], $replacements);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textin = str_replace('گیگ', "", $textin);
    }
    if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], ["نام کاربری دلخواه", "نام کاربری دلخواه + عدد رندوم"])) {
        Editmessagetext($from_id, $message_id, $textin, $payment);
    } else {
        sendmessage($from_id, $textin, $payment, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && in_array($datain, ["confirmandgetservice", "confirmandgetserviceDiscount"], true)) {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));

    $parts = explode("_", $user['Processing_value_one']);

    $partsdic = explode("_", $user['Processing_value_four']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
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
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
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
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
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
            sendmessage($from_id, "❌ امکان خرید با این کد کد تخفیف وجود ندارد", null, 'HTML');
            return;
        }
        $priceproduct = $partsdic[1];
    } else {
        $priceproduct = $info_product['price_product'];
    }
    $username_ac = strtolower($user['Processing_value_tow']);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        sendmessage($from_id, "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
        return;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $random_number = rand(1000000, 9999999);
    if (in_array($randomString, $id_invoice)) {
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
            sendmessage($from_id, "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
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
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions);
    $stmt->execute();
    $stmt->close();
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2" && intval($priceproduct) != 0) {
        $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
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
    if ($datain == "confirmandgetserviceDiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
        if ($SellDiscountlimit != false) {
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            $stmt = $connect->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (?,?)");
            $stmt->bind_param("ss", $from_id, $partsdic[0]);
            $stmt->execute();
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[0]);
            $text_report = "⭕️ یک کاربر با نام کاربری @$username  و آیدی عددی $from_id از کد تخفیف {$partsdic[0]} استفاده کرد.";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
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
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        $emergencyPanel = function_exists('nmPanelEmergencyPanel') ? nmPanelEmergencyPanel($marzban_list_get) : false;
        if ($emergencyPanel) {
            $emergencyProduct = function_exists('nmEmergencyProductFor') ? nmEmergencyProductFor($info_product, $emergencyPanel) : $info_product;
                $datac['data_limit'] = ($emergencyProduct['Volume_constraint'] ?? $info_product['Volume_constraint']) * pow(1024, 3);
                $datac['expire'] = strtotime('+' . (int)($emergencyProduct['Service_time'] ?? $info_product['Service_time']) . ' day');
                $emergencyOut = $ManagePanel->createUser($emergencyPanel['name_panel'], $emergencyProduct['code_product'], $username_ac, $datac);
            if (isset($emergencyOut['username']) && $emergencyOut['username'] !== null && $emergencyOut['username'] !== '') {
                $dataoutput = $emergencyOut;
                $marzban_list_get = $emergencyPanel;
            }
        }
    }
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        if (function_exists('nmPanelEmergencyEnabled') && nmPanelEmergencyEnabled($marzban_list_get)) {
            if (nmStockCompleteBuyFromInventory($from_id, $user, $marzban_list_get, $info_product, $randomString, $username_ac, true, 'emergency_buy_stock')) {
                sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
        }
        $errorMessage = $dataoutput['msg'] ?? 'unknown error';
        if (is_array($errorMessage) || is_object($errorMessage)) {
            $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $errorMessage = (string) $errorMessage;
        }
        $dataoutput['msg'] = $errorMessage;
        sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
        $texterros = "⭕️ خطای ساخت اشتراک
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
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
    update("invoice", "Status", "active", "username", $username_ac);
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }
    $Shoppinginfo = json_encode($Shoppinginfo);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
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
                    sendmessage($user['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $user['affiliates']);
                }


                $stmtAffComm1 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
                $stmtAffComm1->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
                $stmtAffComm1->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
                $stmtAffComm1->execute();
                $result = number_format($result);
                $dateacc = date('Y/m/d H:i:s');
                $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
تایم : $dateacc";
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
                sendmessage($user['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                $scorenew = $user_Balance['score'] + 2;
                update("user", "score", $scorenew, "id", $user['affiliates']);
            }

            $stmtAffComm2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
            $stmtAffComm2->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
            $stmtAffComm2->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
            $stmtAffComm2->execute();
            $result = number_format($result);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = "🎁  پرداخت پورسانت

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
            $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
تایم : $dateacc";
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
        sendmessage($from_id, "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = "📌 خرید اول کاربر";
    }
    $balanceformatsellbefore = number_format($user['Balance'], 0);
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
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :$username_ac
▫️نام کاربر : $first_name
▫️موقعیت سرویس سرویس : {$userdate['name_panel']}
▫️نام محصول :{$info_product['name_product']}
▫️زمان خریداری شده :{$info_product['Service_time']} روز
▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB
▫️موجودی قبل خرید : $balanceformatsellbefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️دسته بندی محصول : {$info_product['category']}
▫️قیمت محصول : {$info_product['price_product']} تومان
▫️قیمت نهایی : $priceproduct تومان
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "aptdc") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscount', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscount") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!isset($userdate['name_panel'])) {
        sendmessage($from_id, "❌ مراحل خرید را مجددا از اول انجام دهید", $keyboard, 'HTML');
        return;
    }
    if (function_exists('rxResolveProductForPanel')) {
        $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :Location or Location = '/all') LIMIT 1");
        $stmt->bindParam(':code_product', $user['Processing_value_one'], PDO::PARAM_STR);
        $stmt->bindParam(':Location', $userdate['name_panel'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($info_product) || !isset($info_product['code_product'])) {
        error_log('Discount code check failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell WHERE (code_product = :code_product OR code_product = 'all') AND (code_panel = :code_panel OR code_panel = '/all') AND codeDiscount = :codeDiscount AND (agent = :agent OR agent = 'allusers') AND (type = 'all' OR type = 'buy')");
    $stmt->bindParam(':code_product', $info_product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $marzban_list_get['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->execute();
    $SellDiscountlimit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM Giftcodeconsumed WHERE id_user = :from_id AND code = :code");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $Checkcodesql = $stmt->rowCount();
    if ($SellDiscountlimit == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidcodedis'], null, 'HTML');
        return;
    }
    if (intval($SellDiscountlimit['time']) != 0 and time() >= intval($SellDiscountlimit['time'])) {
        sendmessage($from_id, "❌ زمان کد تخفیف به پایان رسیده است.", null, 'HTML');
        return;
    }
    if (($SellDiscountlimit['limitDiscount'] <= $SellDiscountlimit['usedDiscount'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimit'], null, 'HTML');
        return;
    }
    if ($Checkcodesql >= $SellDiscountlimit['useuser']) {
        $textoncode = "⭕️ این کد تنها {$SellDiscountlimit['useuser']}  بار قابل استفاده است";
        sendmessage($from_id, $textoncode, $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($SellDiscountlimit['usefirst'] == "1") {
        $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
        $_stmt->bind_param("s", $from_id); $_stmt->execute();
        $countinvoice = $_stmt->get_result(); $_stmt->close();
        if (mysqli_num_rows($countinvoice) != 0) {
            sendmessage($from_id, $textbotlang['users']['Discount']['firstdiscount'], null, 'HTML');
            return;
        }
    }
    sendmessage($from_id, "🤩 کد تخفیف شما درست بود و  {$SellDiscountlimit['price']} درصد تخفیف روی فاکتور شما اعمال شد.", null, 'HTML');
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
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
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
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $result = ($SellDiscountlimit['price'] / 100) * $info_product['price_product'];

    $info_productmain = $info_product['price_product'];
    $info_product['price_product'] = $info_product['price_product'] - $result;
    $info_product['price_product'] = round($info_product['price_product']);
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>{$user['Processing_value_tow']}</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت اصلی : <del>$info_productmain تومان</del>
💶 قیمت با تخفیف: {$info_product['price_product']}  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : {$user['Balance']}

        💰 سفارش شما آماده پرداخت است.  ";
    $paymentDiscount = json_encode([
        'inline_keyboard' => [
            [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetserviceDiscount"]],
            [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]]
        ]
    ]);
    $parametrsendvalue = $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
} elseif ($text == "🗂 خرید انبوه" || $datain == "kharidanbuh") {
    if ($setting['bulkbuy'] == "offbulk") {
        sendmessage($from_id, "❌ این بخش در حال غیرفعال می باشد", null, 'HTML');
        return;
    }
    $PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shopSetting WHERE Namevalue = 'minbalancebuybulk'"))['value'];
    if ($user['Balance'] < $PaySetting) {
        sendmessage($from_id, "❌ برای خرید انبوه باید حداقل $PaySetting تومان موجودی داشته باشید.", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($setting['get_number'] == "onAuthenticationphone" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "onAuthenticationphone")
        return;

    if ($datain == "kharidanbuh") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    }
    step('getcountconfig', $from_id);
} elseif ($user['step'] == "getcountconfig") {
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
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (Location = :location OR Location = '/all') AND agent = :agent");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        return;
    }
    update("user", "Processing_value", $location, "id", $from_id);
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND agent = :agent";
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['Service-select'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolumeom", $user['agent'], $productCountParams));
} elseif ($datain == "customsellvolumeom") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = $eextraprice[$user['agent']];
    $textcustom = "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :
📌 تعرفه هر گیگ :  $custompricevalue
🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvolom', $from_id);
} elseif ($user['step'] == "gettimecustomvolom") {
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
📌 تعرفه هر روز  : $customtimevalueprice  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        step('getvolumecustomusernameom', $from_id);
    } else {
        step('getvolumecustomuserom', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusernameom" || preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    if ($user['step'] == "getvolumecustomusernameom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
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
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
} elseif ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserom") {
    if ($user['step'] == "getvolumecustomuserom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'], $backuser, 'HTML');
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
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
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
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
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
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
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
    $price_product_format = number_format($info_product['price_product']);
    $userbalancepish = number_format($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>$username_ac</code>
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
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') LIMIT 1");
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
        sendmessage($from_id, "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
    Editmessagetext($from_id, $message_id, $text_inline, null);
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
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
    for ($i = 0; $i < $user['Processing_value_four']; $i++) {
        $random_number = rand(1000000, 9999999);
        $username_acc = $username_ac . "_" . $i;
        if (isset($usernameinvoice) && is_array($usernameinvoice) && in_array($username_acc, $usernameinvoice)) {
            $username_acc = $random_number . "_" . $username_acc;
        }
        $randomString = bin2hex(random_bytes(4));
        if (in_array($randomString, $id_invoice)) {
            $randomString = $random_number . $randomString;
        }

        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            $stock = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($marzban_list_get, $info_product, $from_id, $randomString, 'national_direct_buy')
                : false;
            if (!$stock) {
                if (!empty($nmInventoryDirectCharge)) {
                    try {
                        if (function_exists('balance_atomic_charge')) {
                            $__allowNegNm = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
                            balance_atomic_charge($from_id, (float)$nmInventoryDirectCharge, $__allowNegNm);
                            $__nmRow = select("user", "*", "id", $from_id, "select");
                            $newBalanceForStockCharge = (float)($__nmRow['Balance'] ?? 0);
                        } else {
                            $freshUserForStockCharge = select("user", "*", "id", $from_id, "select");
                            $newBalanceForStockCharge = (float)($freshUserForStockCharge['Balance'] ?? $user['Balance'] ?? 0) - (float)$nmInventoryDirectCharge;
                            update("user", "Balance", $newBalanceForStockCharge, "id", $from_id);
                        }
                    } catch (Throwable $e) {
                        error_log('nm national direct partial charge failed: ' . $e->getMessage());
                    }
                }
                sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. خرید انجام نشد و مبلغی از کیف پول کسر نشد.", $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
            $Status = "active";
            $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
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
            $nmInventoryDirectCharge = (float)($nmInventoryDirectCharge ?? 0) + (float)($info_product['price_product'] ?? 0);
            continue;
        }

        $get_username_Check = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc);
        if (isset($get_username_Check['username']) || (isset($usernameinvoice) && is_array($usernameinvoice) && in_array($username_acc, $usernameinvoice))) {
            $username_acc = $random_number . "_" . $username_acc;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت اکانت در بخش انبوه
✍️ دلیل خطا :
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$marzban_list_get['name_panel']}";
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
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
        $Status = "active";
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
        $stmt->execute();
        $stmt->close();
        $config = "";
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
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
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (function_exists('balance_atomic_charge')) {
        $__allowNegBp = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        balance_atomic_charge($from_id, (float)$priceproduct, $__allowNegBp);
        $__bulkBalanceRow = select("user", "*", "id", $from_id, "select");
        $Balance_prim = (float)($__bulkBalanceRow['Balance'] ?? 0);
    } else {
        $user_Balance = select("user", "*", "id", $from_id, "select");
        $Balance_prim = $user_Balance['Balance'] - $priceproduct;
        update("user", "Balance", $Balance_prim, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
    $count_service = $user['Processing_value_four'];
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت انبوه در ربات شما ثبت شد .
▫️آیدی عددی کاربر : <code>$from_id</code>
▫️نام کاربری کاربر :@$username
▫️نام کاربری کانفیگ :{$username_ac}_0-$count_service
▫️نام کاربر : $first_name
▫️موقعیت سرویس سرویس : {$user['Processing_value']}
▫️نام محصول :{$info_product['name_product']}
▫️زمان خریداری شده :{$info_product['Service_time']} روز
▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB
▫️موجودی قبل خرید : $balanceformatsellbefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: $randomString
▫️نوع کاربر : {$user['agent']}
▫️شماره تلفن کاربر : {$user['number']}
▫️قیمت محصول : {$info_product['price_product']} تومان
▫️قیمت نهایی : {$info_product['price_product']} تومان
▫️تعداد کانفیگ : {$user['Processing_value_four']} عدد
▫️زمان خرید : $timejalali";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($datain == "Add_Balance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ($setting['get_number'] == "onAuthenticationphone" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "onAuthenticationphone")
        return;
    $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
    $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "💸 مبلغ را  به تومان وارد کنید:
✅  حداقل مبلغ $minbalance حداکثر مبلغ $maxbalance تومان می باشد", $bakinfos, 'HTML');
    step('getprice', $from_id);
    update("user", 'Processing_value', $message_id, "id", $from_id);
} elseif ($datain == "rcc_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, "❌ درخواست بررسی مجدد لغو شد.", null);
    } else {
        sendmessage($from_id, "❌ درخواست بررسی مجدد لغو شد.", null, 'HTML');
    }
} elseif ($datain == "recheckcrypto") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    $rccMsg = "🔁 <b>بررسی مجدد هش کریپتو</b>\n\n"
            . "اگر قبلاً پرداخت کریپتو انجام داده‌اید ولی فاکتورتان به‌صورت خودکار تایید نشده، می‌توانید از این بخش درخواست بررسی دستی ثبت کنید.\n\n"
            . "ابتدا <b>ارز پرداختی</b> را انتخاب کنید:";
    $rccKb = json_encode([
        'inline_keyboard' => [
            [['text' => '🟥 ترون (TRX)',     'callback_data' => 'rcc_pick_TRX']],
            [['text' => '🟦 تون (TON)',      'callback_data' => 'rcc_pick_TON']],
            [['text' => '🟢 تتر روی ترون',    'callback_data' => 'rcc_pick_USDT_TRC20']],
            [['text' => '🟢 تتر روی تون',     'callback_data' => 'rcc_pick_USDT_TON']],
            [['text' => '❌ انصراف',          'callback_data' => 'rcc_cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccMsg, $rccKb);
    } else {
        sendmessage($from_id, $rccMsg, $rccKb, 'HTML');
    }
    step('home', $from_id);
} elseif (preg_match('/^rcc_pick_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $rccPick)) {
    $rccCur = $rccPick[1];
    update("user", "Processing_value_one", $rccCur, "id", $from_id);
    $rccCurFa = [
        'TRX' => 'ترون (TRX)',
        'TON' => 'تون (TON)',
        'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
        'USDT_TON' => 'تتر روی تون (USDT-TON)',
    ][$rccCur] ?? $rccCur;
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
        sendmessage($from_id, "❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.", null, 'HTML');
        return;
    }
    $rccCurForRate = (string) ($user['Processing_value_one'] ?? '');
    $rateForCalc = function_exists('crypto_irt_rate_for') ? crypto_irt_rate_for($rccCurForRate) : null;
    if ($rateForCalc === null || $rateForCalc <= 0) {
        sendmessage($from_id, "❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.", null, 'HTML');
        return;
    }
    $calcIrr = (int) round(((float) $coinAmount) * $rateForCalc);
    if ($calcIrr <= 0) {
        sendmessage($from_id, "❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.", null, 'HTML');
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
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش خام یا لینک تراکنش را بفرستید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $hashTry, "id", $from_id);
    $photoKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⏭ ارسال بدون عکس (رد شدن)', 'callback_data' => 'rcc_skip_photo']],
            [['text' => '❌ انصراف',                    'callback_data' => 'rcc_cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید.", $photoKb, 'HTML');
    step('rcc_photo', $from_id);
} elseif ($user['step'] == "rcc_photo" && ($datain == "rcc_skip_photo" || !empty($photoid))) {
    $rccCur     = (string) $user['Processing_value_one'];
    $rccCoin    = (string) $user['Processing_value_tow'];
    $rccIrr     = (int)    $user['Processing_value'];
    $rccHash    = (string) $user['Processing_value_four'];
    $rccPhotoId = $datain === "rcc_skip_photo" ? '' : (string) $photoid;
    if (!in_array($rccCur, ['TRX', 'TON', 'USDT_TRC20', 'USDT_TON'], true) || $rccCoin === '' || $rccIrr <= 0 || $rccHash === '') {
        sendmessage($from_id, "❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    try {
        $dupChk = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h LIMIT 1");
        $dupChk->execute([':h' => $rccHash]);
        if ($dupChk->fetch()) {
            sendmessage($from_id, "❌ این هش قبلاً برای فاکتور دیگری ثبت شده است. اگر این پرداخت متعلق به شماست، با پشتیبانی تماس بگیرید.", null, 'HTML');
            step('home', $from_id);
            return;
        }
    } catch (Throwable $e) {  }
    $rccOrderId = bin2hex(random_bytes(6));
    $rccNow = date('Y/m/d H:i:s');
    $rccCoinFmt = number_format((float) $rccCoin, 9, '.', '');
    $rccCoinFmt = rtrim(rtrim($rccCoinFmt, '0'), '.');
    $rccNetwork = in_array($rccCur, ['TRX', 'USDT_TRC20'], true) ? 'TRON' : 'TON';
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
        sendmessage($from_id, "❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.", null, 'HTML');
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
        : $rccHash;
    $userTagLine = '@' . ($user['username'] ?? 'none');
    $rccAdminCaption = "🔁 <b>درخواست بررسی دستی پرداخت کریپتو</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$userTagLine})\n"
        . "💎 ارز: <b>{$rccCur}</b> ({$rccNetwork})\n"
        . "🪙 مقدار ادعاشده: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی ادعاشده: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>{$rccHash}</code>\n"
        . "🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در مرورگر بلاکچین</a>";
    $rccAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ کیف پول', 'callback_data' => 'confirmcryptomanual_' . $rccOrderId],
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
    deletemessage($from_id, $user['Processing_value']);
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
    $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
    $balancelast = $text;
    if ($text > $maxbalance or $text < $minbalance) {
        $minbalance = number_format($minbalance);
        $maxbalance = number_format($maxbalance);
        sendmessage($from_id, "❌ خطا
💬 مبلغ باید حداقل $minbalance تومان و حداکثر $maxbalance تومان باشد", null, 'HTML');
        return;
    }
    if ($user['Balance'] < 0 and intval($setting['Debtsettlement']) == 1) {
        $balancruser = abs($user['Balance']);
        if ($text < $balancruser) {
            sendmessage($from_id, "❌ شما بدهی دارید، باید حداقل $balancruser تومان پرداخت کنید.
         میبغ خود را مجددا ارسال نمایید", null, 'HTML');
            return;
        }
    }
    update("user", "Processing_value", $balancelast, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    if ($datain == "cart_to_offline") {
        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
        $from_id_sql = mysqli_real_escape_string($connect, (string) $from_id);
        $_stmt = $connect->prepare("SELECT * FROM Payment_report WHERE id_user = ? AND payment_Status = 'Unpaid'");
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
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        $cardQuery = mysqli_query($connect, "SELECT * FROM card_number  ORDER BY RAND() LIMIT 1");
        if ($cardQuery === false) {
            error_log('Failed to fetch card_number data: ' . mysqli_error($connect));
            sendmessage($from_id, "❌ خطای داخلی در بازیابی کارت بانکی رخ داد. لطفاً بعداً تلاش کنید.", null, 'HTML');
            return;
        }

        $card_info = mysqli_fetch_assoc($cardQuery);
        if (!$card_info || empty($card_info['cardnumber']) || empty($card_info['namecard'])) {
            sendmessage($from_id, "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            mysqli_free_result($cardQuery);
            return;
        }

        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        mysqli_free_result($cardQuery);
        $price_copy = $user['Processing_value'];
        if ($PaySetting == "onautoconfirm") {
            $random_number = rand(0, 2000);
            $user['Processing_value'] = intval($user['Processing_value']) + $random_number;
            if (in_array($user['Processing_value'], $pricepayment)) {
                $random_number = rand(0, 2000);
                $user['Processing_value'] = intval($user['Processing_value']) + $random_number;
            }
            $valueshow = "{$user['Processing_value']}0";
            $replacements = [
                '{price}' => $valueshow,
                '{card_number}' => $card_number,
                '{name_card}' => $PaySettingname,
            ];
            $price_copy = $valueshow;
            $textcart = strtr($datatextbot['text_cart_auto'], $replacements);
            update("user", "Processing_value", $user['Processing_value'], "id", $from_id);
        } else {
            $valueprice = number_format($user['Processing_value']);
            $replacements = [
                '{price}' => $valueprice,
                '{card_number}' => $card_number,
                '{name_card}' => $PaySettingname,
            ];
            $price_copy = intval($user['Processing_value'] . "0");
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
        deletemessage($from_id, $message_id);
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        if ($setting['statuscopycart'] == "1") {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "کپی شماره کارت", 'copy_text' => ["text" => $card_number]],
                        ['text' => "کپی مبلغ", 'copy_text' => ["text" => $price_copy]]
                    ],
                    [
                        rx_kb_style(['text' => "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                    ]
                ]
            ]);
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
        $message_id = telegram('sendmessage', [
            'chat_id' => $from_id,
            'text' => $textcart,
            'reply_markup' => $sendresidcart,
            'parse_mode' => "html",
        ]);
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "aqayepardakht") {
        if ($user['Processing_value'] < 5000) {
            sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalanceaqayepardakht", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalanceaqayepardakht", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayaqayepardakht($user['Processing_value'], $randomString);
        if ($pay['status'] != "success") {
            $text_error = json_encode($pay);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک اقای پردات
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
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
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "aqayepardakht";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://panel.aqayepardakht.ir/startpay/" . $pay['transid']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ فاکتور پرداخت ایجاد شد.\n\n🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش امکان‌پذیر نیست.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpaqayepardakht", "select")['ValuePay'];
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
    } elseif ($datain == "zarinpey") {
        $minbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpey", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpey", "select")['ValuePay'];

        if ($user['Processing_value'] < $minbalance || $user['Processing_value'] > $maxbalance) {
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($textbotlang['users']['Balance']['zarinpey'], $minbalance, $maxbalance), null, 'HTML');
            return;
        }

        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayZarinpey($user['Processing_value'], $randomString, $from_id);

        if (empty($pay['success'])) {
            $error_text = $pay['message'] ?? $textbotlang['users']['Balance']['errorLinkPayment'];
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پی\n✍️ دلیل خطا : {$error_text}\n\nآیدی کابر : $from_id\nنام کاربری کاربر : @$username";
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => 'HTML'
                ]);
            }
            return;
        }

        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpay";
        $pendingMetadata = [
            'gateway' => 'zarinpay',
            'authority' => $pay['authority'] ?? null,
            'amount_rial' => $pay['amount_rial'] ?? null,
        ];
        $pendingMetadata = array_filter(
            $pendingMetadata,
            static function ($value, $key) {
                if ($key === 'gateway') {
                    return true;
                }

                return !($value === null || $value === '');
            },
            ARRAY_FILTER_USE_BOTH
        );

        if (!empty($pendingMetadata)) {
            $pendingNote = json_encode($pendingMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $pendingNote = (string) ($pay['authority'] ?? '');
        }

        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pendingNote);
        $stmt->execute();

        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['payment_link'] ?? 'https://zarinpay.me'],
                ]
            ]
        ]);

        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ فاکتور پرداخت ایجاد شد.\n\n🔢 شماره فاکتور : $randomString\n💰 مبلغ فاکتور : $price_format تومان\n\n❌ این تراکنش به مدت ۳۰ دقیقه (نیم ساعت) اعتبار دارد و پس از آن امکان پرداخت این تراکنش امکان‌پذیر نیست.\n\n📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.\n\nجهت پرداخت از دکمه زیر استفاده کنید👇🏻";

        $gethelp = getPaySettingValue('helpzarinpey', '2');
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if (is_array($data)) {
                if ($data['type'] == "text") {
                    sendmessage($from_id, $data['text'], null, 'HTML');
                } elseif ($data['type'] == "photo") {
                    sendphoto($from_id, $data['photoid'], null);
                } elseif ($data['type'] == "video") {
                    sendvideo($from_id, $data['videoid'], null);
                }
            }
        }

        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
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
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
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
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
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

❌ این تراکنش به مدت یک روز اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

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
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
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
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
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
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
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
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
نام کاربری کاربر : @$username";
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
    } elseif ($datain == "iranpay1") {
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
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay1", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay1", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 1";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $pay = createInvoiceiranpay1($user['Processing_value'], $randomString);
        if ($pay['status'] != "100") {
            $text_error = $pay['message'];
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
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
        update("Payment_report", "dec_not_confirmed", $pay['Authority'], "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "پرداخت", 'url' => $pay['payment_url_bot']]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>


💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

❌ این تراکنش به مدت ۲۴ ساعت اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.


✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay1", "select")['ValuePay'];
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
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay2", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay2", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
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
        $payment = trnado($randomString, $trxprice);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس ترنادو'];
        } elseif ((isset($payment['success']) && $payment['success'] === false) || (isset($payment['error']) && !isset($payment['IsSuccessful']))) {
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
✍️ دلیل خطا : <pre>$safeErrorText</pre>

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
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

        $paymentSuccessful = function_exists('rxGatewayTruthy') ? rxGatewayTruthy($payment['IsSuccessful'] ?? false) : (($payment['IsSuccessful'] ?? null) == true || ($payment['IsSuccessful'] ?? null) === 'true');
        $paymentToken = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($payment) : (string) ($payment['Data']['Token'] ?? '');
        if (!$paymentSuccessful || $paymentToken === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : <pre>$safeErrorText</pre>

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
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
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://t.me/tronado_robot/customerpayment?startapp={$paymentToken}"]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا یک روز اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .
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
    } elseif ($datain == "iranpay3") {
        $dateacc = date('Y/m/d');
        $query = "SELECT SUM(price) as price FROM Payment_report WHERE  Payment_Method = 'Currency Rial 1' AND  time LIKE '%$dateacc%'";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sumpayment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (intval($sumpayment['price']) > 1000000) {
            sendmessage($from_id, "تعداد افراد در صف درخواست درگاه پرداخت بشدت زیاد است 📊

‼️درحال حاظر از روش پرداخت دیگری استفاده کنید", null, 'HTML');
            return;
        }
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
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد", null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], null, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 3";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $paylink = createInvoice($trxprice);
        if (!$paylink['success']) {
            $text_error = $paylink['message'];
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
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
        update("Payment_report", "dec_not_confirmed", $paylink['data']['id'], "id_order", $randomString);
        $pricetoman = number_format($user['Processing_value'], 0);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "💎 پرداخت", 'url' => "t.me/AvidTrx_Bot?start=" . $paylink['data']['id']]
                ],
            ]
        ]);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code> تومان


💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

❌ این تراکنش به مدت یک روز اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay3", "select")['ValuePay'];
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
        sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        step("getvoocherx", $from_id);
        savedata("clear", "id_payment", $randomString);
    } elseif ($datain == "digitaltron") {


        $mainbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "minbalancedigitaltron", "select")['ValuePay'];
        $maxbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "maxbalancedigitaltron", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancedigitaltron || $user['Processing_value'] > $maxbalancedigitaltron) {
            $minF = number_format($mainbalancedigitaltron);
            $maxF = number_format($maxbalancedigitaltron);
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $minF و حداکثر $maxF تومان باشد", null, 'HTML');
            return;
        }

        if (!function_exists('crypto_active_wallets')) {
            sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $available = crypto_active_wallets();
        if (empty($available)) {
            sendmessage($from_id, "❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است. لطفاً با پشتیبانی در تماس باشید.", $keyboard, 'HTML');
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
        $rows = [];
        foreach ($available as $w) {
            $cur = (string) $w['currency'];
            $label = $faShort[$cur] ?? $cur;
            $btn = ['text' => $label, 'callback_data' => 'digitaltron_pay_' . $cur];
            if ($styleCurrency) $btn['style'] = $styleCurrency;
            $rows[] = [$btn];
        }
        $rows[] = [['text' => '❌ بستن', 'callback_data' => 'colselist']];
        $picker = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        $msg = "💎 <b>پرداخت با ارز دیجیتال</b>\n\n"
             . "شبکه‌ای که می‌خواهید با آن پرداخت کنید را انتخاب کنید. بعد از انتخاب، ربات یک آدرس کیف پول و یک <b>مبلغ دقیق</b> به شما اعلام می‌کند.\n\n"
             . "ℹ️ پس از پرداخت، فقط هش (Hash / TxID) تراکنش را برای ربات می‌فرستید — نیازی به ارسال عکس نیست. "
             . "هش هم به‌صورت خام و هم به‌صورت لینک (مثلاً <code>tonviewer.com/transaction/...</code> یا <code>tronscan.org/#/transaction/...</code>) قابل ارسال است.";
        sendmessage($from_id, $msg, $picker, 'HTML');
    } elseif (preg_match('/^digitaltron_pay_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $dpm)) {

        $cur = $dpm[1];
        $faLabels = [
            'TRX'        => 'ترون (TRX)',
            'TON'        => 'تون (TON)',
            'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
            'USDT_TON'   => 'تتر روی تون (USDT-TON)',
        ];
        $curFa = $faLabels[$cur] ?? $cur;
        $modeMsg = "💳 <b>روش پرداخت را انتخاب کنید</b>\n\n"
                 . "ارز انتخابی: <b>{$curFa}</b>\n\n"
                 . "🌍 <b>کیف پول خارجی:</b>\n"
                 . "مبلغ با اعشار دقیق (مثلاً <code>0.129184</code>). مناسب کیف پول‌های شخصی مثل Tonkeeper / Trust Wallet.\n\n"
                 . "🇮🇷 <b>صرافی ایرانی:</b>\n"
                 . "مبلغ گرد به یک رقم اعشار (مثلاً <code>0.2</code>). مناسب برداشت از نوبیتکس و صرافی‌های ایرانی.";
        $styleExt   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('mode_external') : null;
        $styleIr    = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('mode_iranian')  : null;
        $styleBack2 = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('invoice_back')  : null;
        $btnExt  = ['text' => '🌍 کیف پول خارجی', 'callback_data' => 'digitaltron_paymode_ext_' . $cur];
        $btnIr   = ['text' => '🇮🇷 صرافی ایرانی',   'callback_data' => 'digitaltron_paymode_ir_'  . $cur];
        $btnBack = ['text' => '🔙 بازگشت',         'callback_data' => 'digitaltron'];
        if ($styleExt)   $btnExt['style']  = $styleExt;
        if ($styleIr)    $btnIr['style']   = $styleIr;
        if ($styleBack2) $btnBack['style'] = $styleBack2;
        $modeKb = json_encode([
            'inline_keyboard' => [
                [$btnExt],
                [$btnIr],
                [$btnBack],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, $modeMsg, $modeKb, 'HTML');
    } elseif (preg_match('/^digitaltron_paymode_(ext|ir)_(TRX|TON|USDT_TRC20|USDT_TON)$/', (string) $datain, $dpmm)) {

        $iranianMode = ($dpmm[1] === 'ir');
        $cur = $dpmm[2];
        $amountIrt = (int) ($user['Processing_value'] ?? 0);
        if ($amountIrt <= 0) {
            sendmessage($from_id, "❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $invoiceMeta = sprintf('%s|%s', $user['Processing_value_tow'] ?? '', $user['Processing_value_one'] ?? '');
        $result = crypto_create_invoice($from_id, $amountIrt, $cur, $invoiceMeta, $iranianMode);
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
        $supported = crypto_supported_currencies();
        $coinDecimals = $supported[$cur]['decimals'];
        $amountStr = number_format($result['amount_coin'], $coinDecimals, '.', '');
        $amountStr = rtrim(rtrim($amountStr, '0'), '.');
        $expireMin = max(1, (int) round(($result['expires_at'] - time()) / 60));
        $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
            ? '<i>(لینک‌های Tronscan قابل قبول است)</i>'
            : '<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>';
        $walletEsc = htmlspecialchars((string) $result['wallet']);
        $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
        $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);
        $modeLabel = $iranianMode ? '🇮🇷 صرافی ایرانی' : '🌍 کیف پول خارجی';

        if ($iranianMode) {
            $amountNotice = "💡 این مبلغ گرد به یک رقم اعشار شده تا از صرافی ایرانی قابل برداشت باشد.\n"
                          . "❗️ مبلغ <b>{$amountStr}</b> را ارسال کنید (تلرانس کم پذیرفته می‌شود).";
        } else {
            $amountNotice = "❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید (تا چند رقم اعشار). تفاوت در ارقام اعشار باعث می‌شود ربات تراکنش شما را شناسایی نکند.";
        }

        $memoBlock = "";
        if ($isTonNetwork && $walletMemo !== '') {
            $memoEsc = htmlspecialchars($walletMemo);
            $memoBlock = "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{$memoEsc}</code>\n"
                       . "⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n";
        }

        $invoiceMsg = "💎 <b>فاکتور پرداخت کریپتو</b>\n\n"
             . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
             . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
             . "💳 روش: <b>{$modeLabel}</b>\n"
             . "💸 مبلغ تومانی: " . number_format($amountIrt) . " تومان\n\n"
             . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n\n"
             . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
             . $memoBlock
             . $amountNotice . "\n"
             . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
             . "بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{$explorerHint}";

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
        try {
            $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
            $autoload = $rootDir . '/vendor/autoload.php';
            if (is_file($autoload)) @require_once $autoload;
            if (class_exists('\\Endroid\\QrCode\\Builder\\Builder') && function_exists('addBackgroundImage')) {
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
            sendmessage($from_id, "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
            return;
        }
        if (!in_array($rowChk['payment_Status'], ['Unpaid', 'AwaitingHash'], true)) {
            sendmessage($from_id, "❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.", $keyboard, 'HTML');
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
            sendmessage($from_id, "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد", null, 'HTML');
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
✍️ دلیل خطا : $text_error

آیدی کابر : $from_id
روش پرداخت : $Payment_Method
نام کاربری کاربر : @$username";
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
}