<?php

if (isset($update["inline_query"])) {
    $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $query, PDO::PARAM_STR);
    $stmt->bindParam(':notes', $query, PDO::PARAM_STR);
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    $results = [];
    foreach ($invoices as $OrderUser) {
        if (isset($OrderUser['time_sell'])) {
            $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        } else {
            $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
        }
        if ($OrderUser['name_product'] == "سرویس تست") {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "مگابایت";
        } else {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "گیگابایت";
        }
        $results[] = [
            "type" => "article",
            "id" => uniqid(),
            'cache_time' => 0,
            'is_personal' => true,
            "title" => $OrderUser['username'],
            "input_message_content" => [
                "message_text" => "
🛒 شماره سفارش  :  {$OrderUser['id_invoice']}
🛒  وضعیت سفارش در ربات : {$OrderUser['Status']}
🙍‍♂️ شناسه کاربر : {$OrderUser['id_user']}
👤 نام کاربری اشتراک :  {$OrderUser['username']}
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']}
📆 تاریخ خرید : $datatime
"
            ]
        ];
    }
    answerInlineQuery($inline_query_id, $results);
} elseif (preg_match('/vieworderuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json, 'html');
} elseif (preg_match('/next_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
    $_stmt->bind_param("sii", $id_user, $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "متن دکمه گردونه شانس" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_wheel_luck'], $backadmin, 'HTML');
    step('text_wheel_luck', $from_id);
} elseif ($user['step'] == "text_wheel_luck") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wheel_luck");
    step('home', $from_id);
} elseif ($datain == "cartuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageusercart') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageusercart') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = (function() use ($connect, $start_index, $items_per_page) { $_s=(int)$start_index; $_p=(int)$items_per_page; $_stmt=$connect->prepare("SELECT * FROM user WHERE cardpayment = \'1\' LIMIT ?,?"); $_stmt->bind_param("ii",$_s,$_p); $_stmt->execute(); $r=$_stmt->get_result(); $_stmt->close(); return $r; })();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/createbot_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $checkbot = select("botsaz", "*", "id_user", $id_user, "count");
    $checkbots = select("botsaz", "*", null, null, "count");
    if ($checkbots >= 15) {
        nm_adminInstantReply($from_id, "❌  درحال حاضر فقط محدود به ساختن 15 ربات برای نماینده های خود هستید.", $keyboardadmin, 'HTML');
        return;
    }
    if ($checkbot != 0) {
        $textexitsbot = "❌ این ربات از قبل نصب شده است امکان نصب مجدد وجود ندارد.";
        nm_adminInstantReply($from_id, $textexitsbot, $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "id_user", $id_user);
    $texbot = "📌  از طریق این بخش شما می توانید برای نماینده خود یک ربات فروش بسازید تا نماینده با ربات اختصاصی خودش فروش داشته باشد

- جهت ساخت ربات توکن ربات را ارسال نمایید.";
    nm_adminInstantReply($from_id, $texbot, $backadmin, 'HTML');
    step("gettokenbot", $from_id);
} elseif ($user['step'] == "gettokenbot") {
    $getInfoToken = json_decode(file_get_contents("https://api.telegram.org/bot$text/getme"), true);
    if ($getInfoToken == false or !$getInfoToken['ok']) {
        nm_adminInstantReply($from_id, "❌ توکن نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $checkbot = select("botsaz", "*", "bot_token", $text, "count");
    if ($checkbot != 0) {
        nm_adminInstantReply($from_id, "📌 این توکن از قبل ثبت شده است", null, 'HTML');
        return;
    }
    savedata("save", "token", $text);
    savedata("save", "username", $getInfoToken['result']['username']);
    $texbot = "📌 آیدی عددی ادمین را ارسال نمایید";
    nm_adminInstantReply($from_id, $texbot, $backadmin, 'HTML');
    step("getadminidbot", $from_id);
} elseif ($user['step'] == "getadminidbot") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    step("home", $from_id);
    $admin_ids = json_encode(array(
        $userdate['id_user']
    ));
    $destination = getcwd();
    $dirsource = "$destination/vpnbot/{$userdate['id_user']}{$userdate['username']}";
    if (is_dir($dirsource) && !deleteDirectory($dirsource)) {
        error_log('Failed to remove existing bot directory: ' . $dirsource);
    }
    if (!copyDirectoryContents($destination . '/vpnbot/Default', $dirsource)) {
        error_log('Failed to copy default bot files into: ' . $dirsource);
    }
    $contentconfig = file_get_contents($dirsource . "/config.php");
    $new_code = str_replace('BotTokenNew', $userdate['token'], $contentconfig);
    file_put_contents($dirsource . "/config.php", $new_code);
    file_get_contents("https://api.telegram.org/bot{$userdate['token']}/setwebhook?url=https://$domainhosts/vpnbot/{$userdate['id_user']}{$userdate['username']}/index.php");
    file_get_contents("https://api.telegram.org/bot{$userdate['token']}/sendmessage?chat_id={$userdate['id_user']}&text=✅ کاربر عزیز ربات شما با موفقیت نصب گردید.");
    $datasetting = json_encode(array(
        "minpricetime" => 4000,
        "pricetime" => 4000,
        "minpricevolume" => 4000,
        "pricevolume" => 4000,
        "support_username" => "@support",
        "Channel_Report" => 0,
        "cart_info" => "جهت پرداخت مبلغ را به شماره کارت زیر واریز نمایید",
        'show_product' => true,
    ));
    $value = "{}";
    $stmt = $pdo->prepare("INSERT INTO botsaz (id_user,bot_token,admin_ids,username,time,setting,hide_panel) VALUES (:id_user,:bot_token,:admin_ids,:username,:time,:setting,:hide_panel)");
    $stmt->bindParam(':id_user', $userdate['id_user'], PDO::PARAM_STR);
    $stmt->bindParam(':bot_token', $userdate['token'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_ids', $admin_ids);
    $stmt->bindParam(':username', $userdate['username'], PDO::PARAM_STR);
    $time = date('Y/m/d H:i:s');
    $stmt->bindParam(':time', $time, PDO::PARAM_STR);
    $stmt->bindParam(':setting', $datasetting, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $value, PDO::PARAM_STR);
    $stmt->execute();
    $texbot = "✅ ربات نماینده با موفقیت ساخته شد.
⚙️ نام کاربری ربات  : @{$userdate['username']}
🤠 توکن ربات : <code>{$userdate['token']}</code>";
    nm_adminInstantReply($from_id, $texbot, $keyboardadmin, 'HTML');
} elseif (preg_match('/removebotsell_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $contentbto = select("botsaz", "*", "id_user", $id_user, "select");
    $destination = getcwd();
    $dirsource = "$destination/vpnbot/$id_user{$contentbto['username']}";
    if (is_dir($dirsource) && !deleteDirectory($dirsource)) {
        error_log('Failed to remove bot directory: ' . $dirsource);
    }
    if (!empty($contentbto['bot_token'])) {
        file_get_contents("https://api.telegram.org/bot{$contentbto['bot_token']}/deletewebhook");
    }
    $stmt = $pdo->prepare("DELETE FROM botsaz WHERE id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "❌ ربات فروش نماینده با موفقیت حذف گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/setvolumesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر گیگ حجم بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricevolumesrc", $from_id);
} elseif ($user['step'] == "getpricevolumesrc") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricevolume'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/settimepricesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر روز زمان بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricetimesrc", $from_id);
} elseif ($user['step'] == "getpricetimesrc") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricetime'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
}
if ($datain == "settimecornday" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید چند روز مانده است به پایان اشتراک به کاربر اطلاع داده شود. زمان برحسب روز است" . $setting['daywarn'] . "روز", $backadmin, 'HTML');
    step("getdaywarn", $from_id);
} elseif ($user['step'] == "getdaywarn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $keyboardadmin, 'HTML');
    step("home", $from_id);
    update("setting", "daywarn", $text);
} elseif ($datain == "linkappsetting") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب نمایید.", $keyboardlinkapp, 'HTML');
} elseif ($datain == "infocard_color_menu" && $adminrulecheck['rule'] == "administrator") {

    $currentRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
    $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
    $colorKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
            ],
            [
                ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
            ],
            [
                ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => 'close_stat'],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    if (function_exists('Editmessagetext') && isset($message_id)) {
        Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
    } else {
        nm_adminInstantReply($from_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard, 'HTML');
    }
} elseif (preg_match('/^infocard_setcolor_(yellow|green|red|blue|purple|orange)$/', $datain ?? '', $colorMatch) && $adminrulecheck['rule'] == "administrator") {
    $newColor = $colorMatch[1];
    $existing = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    if (is_array($existing) && isset($existing['Namevalue'])) {
        update("shopSetting", "value", $newColor, "Namevalue", "infocard_color");
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)");
            $stmt->execute([':n' => 'infocard_color', ':v' => $newColor]);
            if (function_exists('clearSelectCache')) clearSelectCache('shopSetting');
        } catch (\Throwable $e) {
            error_log('infocard_color insert failed: ' . $e->getMessage());
        }
    }
    if (isset($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ رنگ کارت تنظیم شد',
            'show_alert' => false,
            'cache_time' => 2,
        ]);
    }

    $currentRow = select("shopSetting", "*", "Namevalue", "infocard_color", "select");
    $current = (is_array($currentRow) && isset($currentRow['value'])) ? (string)$currentRow['value'] : 'yellow';
    $mark = function ($c) use ($current) { return $c === $current ? '✅ ' : ''; };
    $colorKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $mark('yellow') . '🟡 زرد',   'callback_data' => 'infocard_setcolor_yellow'],
                ['text' => $mark('green')  . '🟢 سبز',   'callback_data' => 'infocard_setcolor_green'],
            ],
            [
                ['text' => $mark('red')    . '🔴 قرمز',  'callback_data' => 'infocard_setcolor_red'],
                ['text' => $mark('blue')   . '🔵 آبی',   'callback_data' => 'infocard_setcolor_blue'],
            ],
            [
                ['text' => $mark('purple') . '🟣 بنفش',  'callback_data' => 'infocard_setcolor_purple'],
                ['text' => $mark('orange') . '🟠 نارنجی', 'callback_data' => 'infocard_setcolor_orange'],
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => 'close_stat'],
            ],
        ]
    ], JSON_UNESCAPED_UNICODE);
    if (function_exists('Editmessagetext') && isset($message_id)) {
        Editmessagetext($from_id, $message_id, "🎨 رنگ کارت مشخصات سرویس را انتخاب کنید:", $colorKeyboard);
    }
} elseif ($text == "🔗 اضافه کردن برنامه") {
    nm_adminInstantReply($from_id, "📌 جهت اضافه کردن لینک دانلود برنامه  نام اپ یا نام دکمه را ارسال نمایید.", $backadmin, 'HTML');
    step("getnamebtnapp", $from_id);
} elseif ($user['step'] == "getnamebtnapp") {
    if (strlen($text) > 200) {
        nm_adminInstantReply($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name", $text);
    nm_adminInstantReply($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
    step("geturlbtnapp", $from_id);
} elseif ($user['step'] == "geturlbtnapp") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO app (name, link) VALUES (:name, :link)");
    $stmt->bindParam(':name', $userdate['name'], PDO::PARAM_STR);
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
    nm_adminInstantReply($from_id, "✅ لینک اپ شما با موفقیت اضافه گردید.", $keyboardlinkapp, 'HTML');
    step("home", $from_id);
} elseif ($text == "❌ حذف برنامه") {
    nm_adminInstantReply($from_id, "📌 برای حذف برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("getnameappforremove", $from_id);
} elseif ($user['step'] == "getnameappforremove") {
    nm_adminInstantReply($from_id, "✅ برنامه با موفقیت حذف گردید.", $keyboardlinkapp, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM app WHERE name = :name");
    $stmt->bindParam(':name', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($text == "⚙️ وضعیت قابلیت ها پنل" && $adminrulecheck['rule'] == "administrator") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!in_array($panel['subvip'], ['offsubvip', 'onsubvip'])) {
        update("marzban_panel", "subvip", "offsubvip", "code_panel", $panel['code_panel']);
        $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    }
    $customvlume = json_decode($panel['customvolume'], true);
    if (!is_array($customvlume)) { $customvlume = ['f' => '0', 'n' => '0', 'n2' => '0']; }
    $_pOn  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_pOff = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig     = ($panel['config']      === 'onconfig')          ? $_pOn : $_pOff;
    $statussublink    = ($panel['sublink']      === 'onsublink')         ? $_pOn : $_pOff;
    $statusshowbuy    = ($panel['status']       === 'active')            ? $_pOn : $_pOff;
    $statusshowtest   = ($panel['TestAccount']  === 'ONTestAccount')     ? $_pOn : $_pOff;
    $statusconnecton  = ($panel['conecton']      === 'onconecton')       ? $_pOn : $_pOff;
    $status_extend    = ($panel['status_extend'] === 'on_extend')        ? $_pOn : $_pOff;
    $changeloc        = ($panel['changeloc']     === 'onchangeloc')      ? $_pOn : $_pOff;
    $inbocunddisable  = ($panel['inboundstatus'] === 'oninbounddisable') ? $_pOn : $_pOff;
    $subvip           = ($panel['subvip']        === 'onsubvip')         ? $_pOn : $_pOff;
    $customstatusf    = (((string)($customvlume['f']  ?? '0')) === '1') ? $_pOn : $_pOff;
    $customstatusn    = (((string)($customvlume['n']  ?? '0')) === '1') ? $_pOn : $_pOff;
    $customstatusn2   = (((string)($customvlume['n2'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $on_hold_test     = (((string)($panel['on_hold_test'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $version_panel_status = (((string)($panel['version_panel'] ?? '0')) === '1') ? $_pOn : $_pOff;
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ],
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify', 'guard'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard", "guard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    if ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        unset($Bot_Status['inline_keyboard'][2]);
        unset($Bot_Status['inline_keyboard'][3]);
        unset($Bot_Status['inline_keyboard'][4]);
        unset($Bot_Status['inline_keyboard'][5]);
        unset($Bot_Status['inline_keyboard'][6]);
        unset($Bot_Status['inline_keyboard'][7]);
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    $Bot_Status = json_encode($Bot_Status);
    nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editpanel-(.*)-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    $code_panel = $dataget[3];
    if ($type == "stautsconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("marzban_panel", "config", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "sublink") {
        if ($value == "onsublink") {
            $valuenew = "offsublink";
        } else {
            $valuenew = "onsublink";
        }
        update("marzban_panel", "sublink", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statusbuy") {
        if ($value == "active") {
            $valuenew = "disable";
        } else {
            $valuenew = "active";
        }
        update("marzban_panel", "status", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statustest") {
        if ($value == "ONTestAccount") {
            $valuenew = "OFFTestAccount";
        } else {
            $valuenew = "ONTestAccount";
        }
        update("marzban_panel", "TestAccount", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "versionpanel") {
        $valuenew = ((string)$value === "1") ? "0" : "1";
        update("marzban_panel", "version_panel", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "connecton") {
        if ($value == "onconecton") {
            $valuenew = "offconecton";
        } else {
            $valuenew = "onconecton";
        }
        update("marzban_panel", "conecton", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "stautsextend") {
        if ($value == "on_extend") {
            $valuenew = "off_extend";
        } else {
            $valuenew = "on_extend";
        }
        update("marzban_panel", "status_extend", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "changeloc") {
        if ($value == "onchangeloc") {
            $valuenew = "offchangeloc";
        } else {
            $valuenew = "onchangeloc";
        }
        update("marzban_panel", "changeloc", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "inbocunddisable") {
        if ($value == "oninbounddisable") {
            $valuenew = "offinbounddisable";
        } else {
            $valuenew = "oninbounddisable";
        }
        update("marzban_panel", "inboundstatus", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "subvip") {
        if ($value == "onsubvip") {
            $valuenew = "offsubvip";
        } else {
            $valuenew = "onsubvip";
        }
        update("marzban_panel", "subvip", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "customstatusf") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['f'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn2") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n2'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "nationalnet") {
        $valuenew = ($value == "on_national_net") ? "off_national_net" : "on_national_net";
        update("marzban_panel", "national_net_status", $valuenew, "code_panel", $code_panel);
        if ($valuenew == "on_national_net") update("marzban_panel", "stock_source_panel", $code_panel, "code_panel", $code_panel);
    } elseif ($type == "emergency") {
        $valuenew = ($value == "on_emergency_panel") ? "off_emergency_panel" : "on_emergency_panel";
        update("marzban_panel", "emergency_panel_status", $valuenew, "code_panel", $code_panel);
        if ($valuenew == "on_emergency_panel") update("marzban_panel", "emergency_source_panel", $code_panel, "code_panel", $code_panel);
    } elseif ($type == "on_hold_Test") {
        if ($value == "0") {
            $valuenew = "1";
        } else {
            $valuenew = "0";
        }
        update("marzban_panel", "on_hold_test", $valuenew, "code_panel", $code_panel);
    }
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");

    $customvlume = json_decode($panel['customvolume'], true);
    if (!is_array($customvlume)) {
        $customvlume = [];
    }
    $customvlume = array_merge([
        'f' => '0',
        'n' => '0',
        'n2' => '0',
    ], $customvlume);

    $_p2On  = (string)($textbotlang['Admin']['Status']['statuson']  ?? 'فعال');
    $_p2Off = (string)($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $statusconfig  = ($panel['config']     === 'onconfig')      ? $_p2On : $_p2Off;
    $statussublink = ($panel['sublink']    === 'onsublink')      ? $_p2On : $_p2Off;
    $statusshowbuy = ($panel['status']     === 'active')         ? $_p2On : $_p2Off;
    $statusshowtest= ($panel['TestAccount']=== 'ONTestAccount')  ? $_p2On : $_p2Off;
    $statusconnecton = [
        'onconecton' => $_p2On,
        'offconecton' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['conecton'] ?? 'offconecton'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['status_extend'] ?? 'off_extend'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['changeloc'] ?? 'offchangeloc'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['inboundstatus'] ?? 'offinbounddisable'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['subvip'] ?? 'offsubvip'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['f'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n2'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');

    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['on_hold_test'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $version_panel_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['version_panel'] ?? '0'] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ],
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status = json_encode($Bot_Status);
    Editmessagetext($from_id, $message_id, ($textbotlang['Admin']['Status']['BotTitle'] ?? '⚙️ وضعیت'), $Bot_Status);
} elseif (($datain == "premium_emoji_settings" || preg_match('/^premium_emoji_settings_(\d+)$/', (string)$datain, $rxPemPgMatch)) && $adminrulecheck['rule'] == "administrator") {


    $rxPemPage = isset($rxPemPgMatch[1]) ? max(1, (int)$rxPemPgMatch[1]) : 1;
    if (function_exists('rxRenderPremiumEmojiPanel')) {
        rxRenderPremiumEmojiPanel($from_id, $rxPemPage);
    }
} elseif ($datain == "premium_emoji_status" && $adminrulecheck['rule'] == "administrator") {

    nm_adminInstantReply($from_id, "🌟 برای مدیریت ایموجی‌های پرمیوم، روی «⚙️ تنظیمات» کنار همین دکمه بزنید یا از دکمه زیر استفاده کنید.", json_encode([
        'inline_keyboard' => [
            [['text' => "⚙️ تنظیمات ایموجی پرمیوم", 'callback_data' => "premium_emoji_settings"]],
            [['text' => "❌ بستن", 'callback_data' => "close_stat"]],
        ]
    ]), 'HTML');
} elseif ($datain == "premium_emoji_noop") {
    if (!empty($callback_query_id)) {
        try { telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]); } catch (\Throwable $e) {}
    }
} elseif ($datain == "premium_emoji_replace_confirm" && $adminrulecheck['rule'] == "administrator") {


    $rxPemBase = (string)($user['Processing_value'] ?? '');
    if ($rxPemBase === '') {
        nm_adminInstantReply($from_id, "❌ خطا: ایموجی پایه یافت نشد. دوباره از ابتدا شروع کنید.", json_encode([
            'inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => "premium_emoji_settings"]]]
        ]), 'HTML');
        step('home', $from_id);
        return;
    }
    nm_adminInstantReply($from_id, "✅ تأیید شد. حالا <b>ایموجی پرمیوم جدید</b> را برای [<b>{$rxPemBase}</b>] ارسال کنید.\n\n💡 آیدی فعلی پس از دریافت ایموجی جدید، جایگزین می‌شود.", json_encode([
        'inline_keyboard' => [[['text' => "🔙 لغو", 'callback_data' => "premium_emoji_settings"]]]
    ]), 'HTML');
    step('premium_emoji_get_id', $from_id);
} elseif ($datain == "premium_emoji_add" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "🌟 <b>افزودن ایموجی پرمیوم جدید</b>\n\n<b>مرحله ۱ از ۲:</b>\n📤 لطفاً ابتدا <b>ایموجی عادی (پایه)</b> را ارسال کنید.\n\nمثال: ✅ یا ❌ یا 🔥 یا 💎\n\n⏭ سپس از شما <b>ایموجی پرمیوم متناظر</b> را می‌خواهیم تا به‌صورت خودکار جایگزین شود.", $backadmin, 'HTML');
    step('premium_emoji_get_char', $from_id);
} elseif (preg_match('/^premium_emoji_edit_(\d+)$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
    $rxPemRowId = (int)$rxPemMatch[1];
    try {
        $rxPemStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id");
        $rxPemStmt->execute([':id' => $rxPemRowId]);
        $rxPemRow = $rxPemStmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { $rxPemRow = null; }
    if (!$rxPemRow) {
        nm_adminInstantReply($from_id, "❌ ایموجی یافت نشد.", null, 'HTML');
    } else {
        update("user", "Processing_value", (string)$rxPemRow['emoji'], "id", $from_id);
        $rxPemCurCid = (string)$rxPemRow['custom_emoji_id'];
        nm_adminInstantReply($from_id, "✏️ <b>ویرایش ایموجی [{$rxPemRow['emoji']}]</b>\n\n🆔 آیدی فعلی: <code>{$rxPemCurCid}</code>\n\n📤 <b>ایموجی پرمیوم جدید</b> را همین‌جا ارسال کنید — ربات آیدی جدید را خودکار تشخیص می‌دهد.", $backadmin, 'HTML');
        step('premium_emoji_edit_id', $from_id);
    }
} elseif (preg_match('/^premium_emoji_del_(\d+)$/', $datain, $rxPemMatch) && $adminrulecheck['rule'] == "administrator") {
    $rxPemRowId = (int)$rxPemMatch[1];
    try {
        $rxPemStmt = $pdo->prepare("DELETE FROM premium_emojis WHERE id = :id");
        $rxPemStmt->execute([':id' => $rxPemRowId]);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
        nm_adminInstantReply($from_id, "🗑 ایموجی حذف شد.", json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
    } catch (\Throwable $e) {
        nm_adminInstantReply($from_id, "❌ خطا در حذف ایموجی.", null, 'HTML');
    }
} elseif (preg_match('/^premium_emoji_replace_(\d+)_(\d+)$/', (string)$datain, $rxPemReplaceMatch) && $adminrulecheck['rule'] == "administrator") {


    $rxPemReplaceId = (int)$rxPemReplaceMatch[1];
    $rxPemReplaceCid = (string)$rxPemReplaceMatch[2];
    try {
        $rxPemRowStmt = $pdo->prepare("SELECT emoji, custom_emoji_id FROM premium_emojis WHERE id = :id LIMIT 1");
        $rxPemRowStmt->execute([':id' => $rxPemReplaceId]);
        $rxPemRowData = $rxPemRowStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($rxPemRowData)) {
            nm_adminInstantReply($from_id, "❌ ردیف مورد نظر یافت نشد. ممکن است حذف شده باشد.", json_encode([
                'inline_keyboard' => [[['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]]]
            ]), 'HTML');
            return;
        }
        $rxPemReplBase = (string)$rxPemRowData['emoji'];
        $rxPemReplOldCid = (string)$rxPemRowData['custom_emoji_id'];
        $rxPemUpd = $pdo->prepare("UPDATE premium_emojis SET custom_emoji_id = :c, updated_at = :t WHERE id = :id");
        $rxPemUpd->execute([':c' => $rxPemReplaceCid, ':t' => time(), ':id' => $rxPemReplaceId]);
        if (function_exists('getPremiumEmojiMap')) { getPremiumEmojiMap(true); }
        nm_adminInstantReply($from_id, "♻️ <b>آیدی پرمیوم با موفقیت جایگزین شد!</b>\n\n• ایموجی پایه: {$rxPemReplBase}\n• 🆔 آیدی قبلی: <code>{$rxPemReplOldCid}</code>\n• 🆔 آیدی جدید: <code>{$rxPemReplaceCid}</code>", json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 بازگشت به لیست", 'callback_data' => "premium_emoji_settings"]],
            ]
        ]), 'HTML');
    } catch (\Throwable $rxPemReplErr) {
        @error_log('[premium_emoji_replace] ' . $rxPemReplErr->getMessage());
        nm_adminInstantReply($from_id, "❌ خطا در جایگزینی آیدی.", null, 'HTML');
    }
} elseif ($datain == "startelegram") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $Startelegram, 'HTML');
} elseif ($text == "⬇️ حداقل مبلغ استار") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqstar", $from_id);
} elseif ($user['step'] == "getmainaqstar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancestar");
} elseif ($text == "⬆️ حداکثر مبلغ استار") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancestar", $from_id);
} elseif ($user['step'] == "maxbalancestar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancestar");
} elseif ($text == "⬇️ حداقل مبلغ nowpayment") {
    nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqnowpayment", $from_id);
} elseif ($user['step'] == "getmainaqnowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancenowpayment");
} elseif ($text == "⬆️ حداکثر مبلغ nowpayment") {
    nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancenowpayment", $from_id);
} elseif ($user['step'] == "maxbalancenowpayment") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancenowpayment");
} elseif ($text == "📚 تنظیم آموزش استار" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpstar", $from_id);
} elseif ($user['step'] == "gethelpstar") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpstar");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } else {
        nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
} elseif ($text == "💰 کش بک استار") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید )", $backadmin, 'HTML');
    step("chashbackstar", $from_id);
} elseif ($user['step'] == "chashbackstar") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackstar");
} elseif ($text == "🔋 تنظیم سریع قیمت حجم") {
    nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت ابتدا قیمت گروه f را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricef", $from_id);
} elseif ($user['step'] == "getpricef") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    nm_adminInstantReply($from_id, "📌 قیمت گروه n را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn", $from_id);
} elseif ($user['step'] == "getpricnn") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    nm_adminInstantReply($from_id, "📌 قیمت گروه n2 را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn2", $from_id);
} elseif ($user['step'] == "getpricnn2") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomvolume", $pricelist, null, null);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($text == "⏳ تنظیم سریع قیمت زمان") {
    nm_adminInstantReply($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید .
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت ابتدا قیمت گروه f را ارسال نمایید.", $backadmin, 'HTML');
    step("getpriceftime", $from_id);
} elseif ($user['step'] == "getpriceftime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    nm_adminInstantReply($from_id, "📌 قیمت گروه n را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnntime", $from_id);
} elseif ($user['step'] == "getpricnntime") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    nm_adminInstantReply($from_id, "📌 قیمت گروه n2 را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn2time", $from_id);
} elseif ($user['step'] == "getpricnn2time") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomtime", $pricelist, null, null);
    nm_adminInstantReply($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "changeloclimit") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب نمایید.
۱ - محدودیت کلی کاربر در کل چند بار می تواند تغییر لوکیشن انجام دهد.
۲ - محدودیت رایگان  کاربر از محدودیت کلی چند بار می تواند رایگان تغییر لوکیشن دهد.", $keyboardchangelimit, 'HTML');
} elseif ($text == "↙️ محدودیت کلی") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    nm_adminInstantReply($from_id, "📌  محدودیت کلی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['all']}", $backadmin, 'HTML');
    step("limitchangeall", $from_id);
} elseif ($user['step'] == "limitchangeall") {
    nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['all'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🆓 محدودیت رایگان") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    nm_adminInstantReply($from_id, "📌  محدودیت رایگانی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['free']}", $backadmin, 'HTML');
    step("limitfreechangefree", $from_id);
} elseif ($user['step'] == "limitfreechangefree") {
    nm_adminInstantReply($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['free'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🔄 ریست محدودیت کل کاربران") {
    $keyboarddata = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و صفر شدن", 'callback_data' => 'reasetchangeloc'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 با تأیید گزینه زیر، تمام تغییر لوکیشن هایی که توسط کاربر انجام شده است صفر خواهد شد. در صورت موافقت، روی گزینه زیر کلیک کنید.", $keyboarddata, 'HTML');
} elseif ($datain == "reasetchangeloc") {
    Editmessagetext($from_id, $message_id, "✅ تمامی محدودیت کاربران صفر شد.", null);
    update("user", "limitchangeloc", "0", null, null);
} elseif (preg_match('/changeloclimitbyuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "📌 محدودیت جدیدی که میخواهید برای کاربر تنظیم کنید را ارسال کنید توجه داشته باشید این قابلیت تعداد تعییر لوکیشن انجام شده را تغییر میدهد", $backadmin, 'HTML');
    step("getlimitchangenewbyuser", $from_id);
} elseif ($user['step'] == "getlimitchangenewbyuser") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    update("user", "limitchangeloc", $text, "id", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ تعداد استفاده کاربر با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/hidepanel_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "❌ پنل هایی که می خواهید برای این نماینده نشان داده نشود از دکمه  زیر انتخاب نمایید بعد از انتخاب دستور /finish را ارسال کنید تا ذخیره شود.", $json_list_marzban_panel, 'HTML');
    step("getpanelhidebotsaz", $from_id);
} elseif ($text == "/finish") {
    nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل های برای کاربر مخفی شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getpanelhidebotsaz") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /finish را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif (preg_match('/removehide_(\w+)/', $datain, $datagetr)) {
    global $list_hide_panel;
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $id_user, "select")['hide_panel'], true);
    $list_hide_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_panel as $panelname) {
        $list_hide_panel['keyboard'][] = [
            ['text' => $panelname]
        ];
    }
    $list_hide_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $list_hide_panel = json_encode($list_hide_panel);
    nm_adminInstantReply($from_id, "❌ از لیست زیر پنل هایی که میخواهید مجددا در ربات نماینده نشان داده شود را  انتخاب نمایید بعد از انتخاب تمامی پنل ها  دستور /remove را ارسال کنید تا ذخیره شود.", $list_hide_panel, 'HTML');
    step("getremovehidepanel", $from_id);
} elseif ($text == "/remove") {
    nm_adminInstantReply($from_id, "✅ نمایش پنل ها با موفقیت انجام و پنل های برای کاربر فعال شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getremovehidepanel") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (!in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_panel as $panel) {
        if ($panel == $text) {
            unset($list_panel[$count]);
            break;
        }
        $count += 1;
    }
    $list_panel = array_values($list_panel);
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /remove را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($datain == "voloume_or_day_all") {
    $userslistData = '[]';
    if (is_file('cronbot/username.json')) {
        $fileContents = file_get_contents('cronbot/username.json');
        if ($fileContents !== false && $fileContents !== '') {
            $userslistData = $fileContents;
        }
    }
    $userslist = json_decode($userslistData, true);
    if (is_array($userslist) && count($userslist) != 0) {
        nm_adminInstantReply($from_id, "❌ سیستم ارسال هدیه درحال انجام عملیات است پس از پایان و اطلاع رسانی  می توانید پیام جدید را ارسال نمایید.", $keyboardadmin, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 برای سرویس های کدام پنل میخواهید حجم یا زمان هدیه دهید؟", $json_list_marzban_panel, "html");
    step("getpanelgift", $from_id);
} elseif ($user['step'] == "getpanelgift") {
    $panel = select("marzban_panel", "*", "name_panel", $text, "count");
    if ($panel == 0) {
        nm_adminInstantReply($from_id, "❌ پنل وجود ندارد", null, "html");
        return;
    }
    savedata("clear", "name_panel", $text);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔋 حجم", 'callback_data' => 'typegift_volume'],
                ['text' => "⏳ زمان", 'callback_data' => 'typegift_day'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 یکی از هدیه های زیر را انتخاب نمایید.", $keyboardstatistics, "html");
    step('home', $from_id);
} elseif (preg_match('/typegift_(\w+)/', $datain, $datagetr)) {
    $typegift = $datagetr[1];
    savedata("save", "typegift", $typegift);
    deletemessage($from_id, $message_id);
    if ($typegift == "volume") {
        nm_adminInstantReply($from_id, "📌 چند گیگ حجم می خواهید به سرویس های کاربر اضافه شود", $backadmin, "html");
    } else {
        nm_adminInstantReply($from_id, "📌 چند روز می خواهید به سرویس های کاربران اضافه شود", $backadmin, "html");
    }
    step("getvaluegift", $from_id);
} elseif ($user['step'] == "getvaluegift") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "value", $text);
    nm_adminInstantReply($from_id, "📌 متنی که می خواهید برای کاربر ارسال شود را ارسال کنید", $backadmin, "html");
    step("gettextgift", $from_id);
} elseif ($user['step'] == "gettextgift") {
    savedata("save", "text", $text);
    savedata("save", "id_admin", $from_id);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و شروع فرآیند", 'callback_data' => 'startgift'],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 ادمین عزیز با تایید بر روی گزینه زیر فرآیند اعمال هدیه ها آغاز خواهد شد توجه داشته باشید با توجه به محدودیت ها اعمال هدیه زمان بر خواهد بود.", $keyboardstatistics, "html");
    step("home", $from_id);
} elseif ($datain == "startgift") {
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌ لفو ارسال هدیه", 'callback_data' => 'cancel_gift'],
            ],
        ]
    ]);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typegift'])) {
        nm_adminInstantReply($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید.", $keyboardstatistics, "html");
        return;
    }
    $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات ارسال هدیه با موفقیت آغاز گردید پس از اضافه شدن و اتمام به شما اطلاع داده می شود.", $keyboardstatistics);
    $userdata['id_message'] = $message_id['result']['message_id'];
    $stmt = $pdo->prepare("SELECT username FROM invoice WHERE  (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$userdata['name_panel']}' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $userslist = json_encode($stmt->fetchAll());
    file_put_contents('cronbot/gift', json_encode($userdata));
    file_put_contents('cronbot/username.json', $userslist);
} elseif ($datain == "cancel_gift") {
    unlink('cronbot/username.json');
    unlink('cronbot/gift');
    deletemessage($from_id, $message_id);
    nm_adminInstantReply($from_id, "📌 ارسال هدیه لغو گردید.", null, 'HTML');
} elseif (preg_match('/expireset_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    nm_adminInstantReply($from_id, "🕘 زمان انقضا نمایندگی را ارسال نمایید. پس از پایان تعداد روز تعیین شده کاربر از حالت نمایندگی خارج شده و گروه کاربر f خواهد شد.
توجه داشته باشید این قابلیت ارتباطی با قابلیت ربات ساز یا ربات فروش نماینده ندارد و فقط مربوط به ربات اصلی شما است

📌 تعداد روز را ارسال نمایید", $backadmin, 'HTML');
    step("gettime_expire_agent", $from_id);
} elseif ($user['step'] == "gettime_expire_agent") {
    if (!ctype_digit($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $timestamp = time() + (intval(value: $text) * 86400);
    update("user", "expire", $timestamp, "id", $userdate['id_user']);
    nm_adminInstantReply($from_id, "✅ تاریخ انقضا تنظیم شد.
📌 پس از پایان زمان گروه کاربری کاربر به f تغییر داده می شود و به کاربر اطلاع داده می شود.", $keyboardadmin, 'HTML');
} elseif ($text == "♻️ نمایش گروهی شماره کارت") {
    nm_adminInstantReply($from_id, "📌 لیست آیدی هایی که  می خواهید شماره کارت برایشان نشان داده شود را ارسال شود
مثال :
1234435423
23423131", $backadmin, 'HTML');
    step("getlistidcart", $from_id);
} elseif ($user['step'] == "getlistidcart") {
    $list = explode("\n", $text);
    foreach ($list as $id_user) {
        if (!userExists($id_user)) {
            nm_adminInstantReply($from_id, "📌 کاربر با آیدی عددی $id_user در  دیتابیس وجود ندارد", $backadmin, 'HTML');
            continue;
        }
        update("user", "cardpayment", "1", "id", $id_user);
    }
    nm_adminInstantReply($from_id, "✅ شماره کارت برای کاربران ارسال شده فعال گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "📄 خروجی افراد شماره کارت فعال") {
    $listusers = select("user", "id", "cardpayment", "1", "fetchAll");
    if (!$listusers) {
        nm_adminInstantReply($from_id, "📌 برای کاربری شماره کارت فعال نشده است", $CartManage, 'HTML');
        return;
    }
    $filename = 'cartlist.txt';
    foreach ($listusers as $id_user) {
        file_put_contents($filename, $id_user['id'] . "\n", FILE_APPEND);
    }
    sendDocument($from_id, $filename, "🪪 لیست کاربرانی که شماره کارت برای آنها فعال است");
    unlink($filename);
} elseif ($text == "🎉 پورسانت فقط برای خرید اول" && $adminrulecheck['rule'] == "administrator") {
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "on_buy_porsant") {
    update("affiliates", "porsant_one_buy", "off_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($datain == "off_buy_porsant") {
    update("affiliates", "porsant_one_buy", "on_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($text == "متن توضیحات درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_request_agent_dec']}</code>", $backadmin, 'HTML');
    step('text_request_agent_dec', $from_id);
} elseif ($user['step'] == "text_request_agent_dec") {
    if (!$text) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_request_agent_dec");
    step('home', $from_id);
} elseif (preg_match('/changestatusadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        nm_adminInstantReply($from_id, "❌ هنوز به کانفیگ متصل نشده است کانفیگ و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisableadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disablebyadmin", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/removefull-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و حذف ", 'callback_data' => "confirmremovefulls-" . $id_invoice],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 با تایید بر روی گزینه زیر این سرویس بطور کامل از دیتابیس ربات حذف خواهد شد و دیگرجزء آمار حساب نخواهد شد ( این بخش سرویس را از پنل حذف نمی کند و فقط از دیتابیس ربات حذف می کند)", $bakinfos);
} elseif (preg_match('/confirmremovefulls-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invocie = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id_invoice");
    $stmt->bindParam(':id_invoice', $id_invoice, PDO::PARAM_STR);
    $stmt->execute();
    Editmessagetext($from_id, $message_id, "✅ سرویس با موفقیت حذف گردید.", json_encode(['inline_keyboard' => []]));
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => "🔗 یک ادمین یک سرویس را از دیتابیس ربات حذف کرد.

- آیدی عددی ادمین :‌$from_id
- نام ادمین : $first_name
- نام کاربری سرویس :‌ {$invocie['username']}",
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "🛒 اضافه کردن دسته بندی") {
    nm_adminInstantReply($from_id, "📌 جهت اضافه کردن دسته بندی نام دسته بندی را ارسال کنید.", $backadmin, 'HTML');
    step("getremarkcategory", $from_id);
} elseif ($user['step'] == "getremarkcategory") {
    nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت اضافه گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
    $stmt->bindParam(1, $text);
    $stmt->execute();
} elseif ($text == "❌ حذف دسته بندی") {
    nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت حذف انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("removecategory", $from_id);
} elseif ($user['step'] == "removecategory") {
    nm_adminInstantReply($from_id, "✅ دسته بندی با موفقیت حذف گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("DELETE FROM category WHERE remark = :remark ");
    $stmt->bindParam(':remark', $text);
    $stmt->execute();
} elseif ($text == "مخفی کردن پنل" && $adminrulecheck['rule'] == "administrator") {
    if ($user['Processing_value_one'] != "/all") {
        nm_adminInstantReply($from_id, "📌 این قابلیت فقط زمانی کاربرد دارد که شما لوکیشن محصول را /all تعریف کرده باشید.", null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 در صورتی که لوکیشن پنل را /all انتخاب کرده باشید اما نیاز داشته باشید که یک پنل را نشان ندهید از این قابلیت می توانید استفاده نمایید

جهت مخفی کردن پنل  از لیست زیر پنل های خود را اتنخاب کنید سپس دستور /end_hide را ارسال نمایید.", $json_list_marzban_panel, 'HTML');
    step('getlistpanel', $from_id);
} elseif ($text == "/end_hide") {
    nm_adminInstantReply($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل ها برای محصول انتخابی مخفی شد.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getlistpanel") {
    $list_panel = json_decode(select("product", "hide_panel", "id", $user['Processing_value'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        nm_adminInstantReply($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("product", "hide_panel", json_encode($list_panel), "id", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /end_hide را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($text == "حذف کلی پنل های مخفی" && $adminrulecheck['rule'] == "administrator") {
    update("product", "hide_panel", "{}", "name_product", $user['Processing_value']);
    nm_adminInstantReply($from_id, "✅ تمامی پنل های مخفی حذف شدند", null, 'HTML');
} elseif ($text == "🔗 وبهوک مجدد ربات های نماینده") {
    $bots_agent = select("botsaz", "*", null, null, "fetchAll");
    if (count($bots_agent) == 0) {
        nm_adminInstantReply($from_id, "❌ رباتی وجود ندارد", null, 'HTML');
        return;
    }
    nm_adminInstantReply($from_id, "📌 در انجام وبهوک ...", null, 'HTML');
    foreach ($bots_agent as $bot) {
        file_get_contents("https://api.telegram.org/bot{$bot['bot_token']}/setwebhook?url=https://$domainhosts/vpnbot/{$bot['id_user']}{$bot['username']}/index.php");
    }
    nm_adminInstantReply($from_id, "✅ وبهوک با موفقیت انجام شد.", null, 'HTML');
} elseif (preg_match('/statuscronuser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $user_status = select("user", "*", "id", $id_user);
    if (intval($user_status['status_cron']) == 0) {
        update("user", "status_cron", "1", "id", $id_user);
        nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر فعال گردید.", null, 'HTML');
    } else {
        update("user", "status_cron", "0", "id", $id_user);
        nm_adminInstantReply($from_id, "✅ اطلاعیه های کرون برای کاربر غیرفعال گردید.", null, 'HTML');
    }
} elseif ($text == "🗂 مدیریت دسته بندی") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
} elseif ($text == "⬅️ بازگشت به منوی فروشگاه") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 مدیریت محصولات" || $datain == "backproductadmin") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
} elseif ($text == "✏️ ویرایش دسته بندی") {
    nm_adminInstantReply($from_id, "📌 دسته بندی خود را جهت ویرایش انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("editcategory_name", $from_id);
} elseif ($user['step'] == "editcategory_name") {
    savedata("clear", "category", $text);
    nm_adminInstantReply($from_id, "📌  نام جدید دسته بندی را ارسال کنید", $backadmin, 'HTML');
    step("get_name_new_category", $from_id);
} elseif ($user['step'] == "get_name_new_category") {
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, "✅ نام دسته بندی با موفقیت تغییر کرد.", $keyboard_Category_manage, 'HTML');
    step("home", $from_id);
    update("category", "remark", $text, "remark", $userdata['category']);
    update("product", "category", $text, "category", $userdata['category']);
} elseif ($datain == "zerobalance") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserzero') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserzero') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_s = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM user WHERE Balance < 0 LIMIT ?, ?");
    $_stmt->bind_param("ii", $_s, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "✏️ ویرایش برنامه") {
    nm_adminInstantReply($from_id, "📌 برای ویرایش برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("edit_app", $from_id);
} elseif ($user['step'] == "edit_app") {
    savedata("clear", "nameapp", $text);
    step("get_new_lin_app", $from_id);
    nm_adminInstantReply($from_id, "📌 لینک جدید اپ را ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_lin_app") {
    step("home", $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    nm_adminInstantReply($from_id, "✅ لینک برنامه با موفقیت بروزرسانی گردید.", $keyboardlinkapp, 'HTML');
    update("app", "link", $text, "name", $userdata['nameapp']);
} elseif ($datain == "nowpaymentsetting") {
    nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "⏳ زمان تایید خودکار بدون بررسی") {
    nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید که قابلیت تایید خودکار بدون بررسی  بعد از چند دقیقه رسید را تایید کند.
زمان خود را بر حسب دقیقه ارسال کنید
زمان فعلی : {$setting['timeauto_not_verify']}", $backadmin, 'HTML');
    step("gettimeauto", $from_id);
} elseif ($user['step'] == "gettimeauto") {
    if (!is_numeric($text)) {
        nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("setting", "timeauto_not_verify", $text);
    nm_adminInstantReply($from_id, "✅ زمان با موفقیت ثبت گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "نمایش برای خرید اول") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :name_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    nm_adminInstantReply($from_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response, 'HTML');
} elseif (preg_match('/status_on_buy-(.*)-(.*)/', $datain, $dataget)) {
    $code_product = $dataget[1];
    $status_now = $dataget[2];
    if ($status_now == '0') {
        $status_now = '1';
    } else {
        $status_now = '0';
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET one_buy_status = :one_buy_status WHERE code_product = :code_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':one_buy_status', $status_now);
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response);
} elseif ($text == "💳 استثناء کردن کاربر از تایید خودکار") {
    nm_adminInstantReply($from_id, "📌 یک گزینه را انتخاب کنید
⚠️ این بخش برای تایید خودکار بدون بررسی می باشد", $Exception_auto_cart_keyboard, 'HTML');
} elseif ($text == "➕ استثناء کردن کاربر") {
    nm_adminInstantReply($from_id, "📌 آیدی عددی کاربر را ارسال کنید", $backadmin, 'HTML');
    step("getidExceptio", $from_id);
} elseif ($user['step'] == "getidExceptio") {
    if (!userExists($text)) {
        nm_adminInstantReply($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود دارد", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions[] = $text;
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت به لیست اضافه گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "❌ حذف کاربر از لیست") {
    sendmessage($from_id, "📌 آیدی عددی کاربر را جهت حذف از لیست ارسال کنید", $backadmin, 'HTML');
    step("getidExceptioremove", $from_id);
} elseif ($user['step'] == "getidExceptioremove") {
    if (!userExists($text)) {
        sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (!in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود ندارد", $backadmin, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_Exceptions as $list) {
        if ($list == $text) {
            unset($list_Exceptions[$count]);
            break;
        }
        $count += 1;
    }
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت از لیست حذف گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "👁 نمایش لیست افراد") {
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (count($list_Exceptions) == 0) {
        sendmessage($from_id, "❌ کاربری در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $list = "";
    foreach ($list_Exceptions as $list_ex) {
        $list .= $list_ex . "\n";
    }
    sendmessage($from_id, "لیست افراد👇", null, 'HTML');
    sendmessage($from_id, $list, null, 'HTML');
} elseif ($text == "تنظیم api" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_floypay")['ValuePay'];
    $textaqayepardakht = "api دریافت شده را در این بخش ارسال کنید

مرچنت کد فعلی شما : $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('marchent_floypay', $from_id);
} elseif ($user['step'] == "marchent_floypay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $Swapinokey, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_floypay");
    step('home', $from_id);
}